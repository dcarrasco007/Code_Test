# ============================================================================
# utils/ejecutor.py
# Proyecto: api_olt_consultas
# Descripción: Resuelve una CONSULTA (código de OLT_API_CONSULTAS) a la
#              secuencia de comandos fija definida en BD
#              (OLT_API_CONSULTA_COMANDOS), la ejecuta por telnet respetando
#              el cupo del gobernador, y persiste el log crudo en
#              OLT_API_LOG_TELNET. Es la pieza que conecta F1 (catálogo en
#              BD) + F2 (validación/scopes — el LLAMADOR ya autenticó/validó
#              antes de invocar esto) + F3 (telnet + gobernador).
#
# NO incluye los parsers (package/parsers/*, este mismo F4): ejecutor.py deja
# el texto crudo completo listo; extraer el "dato" estructurado es
# responsabilidad de quien llama (routers de F5/F6), que le pasa ese texto al
# parser correspondiente.
#
# Modelo de las filas de OLT_API_CONSULTA_COMANDOS (ver sql/02_seed_consultas_comandos.sql
# para el "cómo leer las filas" completo):
#   - Prioridad de filas aplicables: server específico > modelo específico >
#     generales (server=NULL, modelo=NULL). Ver _filas_aplicables().
#   - repetir_por=NULL     → la fila se envía una sola vez, tal cual.
#   - repetir_por='slot'|'vlan' → fila "de navegación/outer": {slot}/{vlan}
#     se resuelve iterando fuente_lista, y CADA valor abre su propia
#     iteración (si contexto='interface', la ejecución entra a esa interfaz).
#   - repetir_por='puerto' → fila "interna": se repite dentro de CADA
#     iteración outer vigente (o una sola vez si no hay outer), resolviendo
#     {puerto} con fuente_lista (que puede depender del slot/vlan outer
#     actual, ej. OLT_SERVER.pto1_12 cuenta los puertos de ESE slot).
#   Entre dos valores distintos de una fila outer con contexto='interface',
#   el ejecutor manda 'quit' automáticamente (un Huawei no acepta 'interface
#   X' estando parado dentro de otra interfaz) — así no hace falta guardar
#   ese 'quit' como fila propia, igual que decidió F1 para no inflar el seed.
#
# Para modificar:
#   - Resolver una fuente_lista nueva → agregar a RESOLVEDORES_LISTA
#   - Prioridad server/modelo/general  → _filas_aplicables()
# ============================================================================

from __future__ import annotations

import time
from dataclasses import dataclass, field
from typing import Callable, Dict, List, Optional, Sequence

from fastapi.concurrency import run_in_threadpool
from loguru import logger
from sqlalchemy import text

from app.config import settings
from utils.sesiones_telnet import (
    CircuitoAbiertoError,
    CupoAgotadoError,
    GobernadorSesiones,
)
from utils.telnet_olt import ClienteTelnetOLT, ErrorTelnet, RechazoPorLimiteSesiones

# Excepciones que el LLAMADOR (routers F6) necesita distinguir para elegir el
# código HTTP correcto (503 cupo/circuito, 503 rechazo, 404/409 negocio...).
# Se re-lanzan tal cual, sin envolverlas en ErrorEjecucion ni persistir un log
# vacío del intento que nunca llegó a enviar nada.
_EXCEPCIONES_TRANSPARENTES = (CupoAgotadoError, CircuitoAbiertoError, RechazoPorLimiteSesiones)


class ErrorEjecucion(Exception):
    """Error de negocio (consulta desconocida, sin comandos para ese
    modelo, etc.) — distinto de ErrorTelnet (falla de red/CLI)."""


@dataclass
class ComandoResuelto:
    orden: int
    contexto: str
    comando: str  # ya con los placeholders {slot}/{puerto}/{vlan} resueltos


@dataclass
class ResultadoEjecucion:
    ok: bool
    log_crudo: str
    comandos_enviados: List[str] = field(default_factory=list)
    duracion_ms: int = 0
    error: Optional[str] = None
    # Solo lo llena ejecutar_comandos(): [{"comando": str, "salida": str}, ...]
    salida_por_comando: Optional[List[dict]] = None


# ─── Resolución de listas para {slot}/{puerto}/{vlan} ──────────────────────
# Cada resolvedor: (engine, *, server, modelo, slot_actual) -> List[str].
# `slot_actual` solo lo usan los resolvedores 'puerto' que dependen de en
# qué slot outer está la iteración (ver OLT_SERVER.pto1_12 abajo).

def _expandir_lista_fija(lista_fija: Optional[str]) -> List[str]:
    """'16,17,18' -> ['16','17','18']; '0-15' -> ['0',...,'15']. Vacío -> []."""
    if not lista_fija or not lista_fija.strip():
        return []
    texto = lista_fija.strip()
    if "," not in texto and "-" in texto:
        try:
            inicio, fin = texto.split("-", 1)
            return [str(i) for i in range(int(inicio), int(fin) + 1)]
        except ValueError:
            pass  # no era un rango numérico: se trata como lista de 1 valor
    return [v.strip() for v in texto.split(",") if v.strip()]


def _resolver_fijo(engine, *, lista_fija, **_) -> List[str]:
    return _expandir_lista_fija(lista_fija)


def _resolver_olt_server_pto1_12(engine, *, server, modelo, slot_actual, **_) -> List[str]:
    """Cuenta cuántos puertos configurados hay para EL SLOT outer vigente,
    leyendo OLT_SERVER.pto1..pto12 (mismo query y mismo criterio ya validado
    en uplink_trafico_15m/model/.../get_puertos_pto1_12: cada columna no
    vacía es un puerto tipo '0/16/0'; su prefijo 'slot0/slot1' indica a qué
    slot pertenece). Devuelve ['0','1',...,'N-1'] — N puertos de ese slot."""
    if slot_actual is None:
        return []
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                """
                SELECT pto1, pto2, pto3, pto4, pto5, pto6,
                       pto7, pto8, pto9, pto10, pto11, pto12
                FROM OLT_SERVER WHERE modelo = :modelo AND server = :server
                """
            ),
            {"modelo": modelo, "server": server},
        ).fetchone()
    if not fila:
        return []
    prefijo_slot = f"0/{slot_actual}/" if not slot_actual.startswith("0/") else f"{slot_actual}/"
    conteo = 0
    for valor in fila:
        if valor and str(valor).startswith(prefijo_slot):
            conteo += 1
    return [str(i) for i in range(conteo)]


def _resolver_olt_puertas_uplinks_gb(engine, *, server, **_) -> List[str]:
    """[CONFIANZA MEDIA — confirmar con datos reales, ver SOLICITUDES_PENDIENTES.txt]
    Puertas uplink catalogadas para esta OLT en OLT_PUERTAS_UPLINKS_GB.olt."""
    with engine.connect() as conn:
        filas = conn.execute(
            text("SELECT puerta FROM OLT_PUERTAS_UPLINKS_GB WHERE olt = :server"),
            {"server": server},
        ).fetchall()
    return [str(f[0]) for f in filas if f[0] is not None]


def _resolver_olt_vlan_servicios(engine, **_) -> List[str]:
    """[CONFIANZA MEDIA — confirmar con datos reales, ver SOLICITUDES_PENDIENTES.txt]
    Catálogo completo de VLAN con servicio asociado (no filtra por OLT: la
    tabla de origen tampoco lo hace)."""
    with engine.connect() as conn:
        filas = conn.execute(text("SELECT vlan FROM OLT_VLAN_SERVICIOS")).fetchall()
    return [str(f[0]) for f in filas if f[0] is not None]


def _resolver_olt_voltaje_tarjeta(engine, *, server, **_) -> List[str]:
    """[CONFIANZA BAJA — confirmar con datos reales, ver SOLICITUDES_PENDIENTES.txt]
    `tarjetas` es un longtext con la lista de slots ya detectados en la
    última corrida (CSV); se reutiliza tal cual como fuente de slots para no
    reconsultar por telnet lo que este proceso está a punto de telnet-ear."""
    with engine.connect() as conn:
        fila = conn.execute(
            text("SELECT tarjetas FROM OLT_VOLTAJE_TARJETA WHERE equipo = :server ORDER BY id DESC LIMIT 1"),
            {"server": server},
        ).fetchone()
    if not fila or not fila[0]:
        return []
    return [v.strip() for v in str(fila[0]).split(",") if v.strip()]


RESOLVEDORES_LISTA: Dict[str, Callable[..., List[str]]] = {
    "fijo": _resolver_fijo,
    "OLT_SERVER.pto1_12": _resolver_olt_server_pto1_12,
    "OLT_PUERTAS_UPLINKS_GB": _resolver_olt_puertas_uplinks_gb,
    "OLT_VLAN_SERVICIOS": _resolver_olt_vlan_servicios,
    "OLT_VOLTAJE_TARJETA": _resolver_olt_voltaje_tarjeta,
}


def _resolver_lista(engine, fuente_lista: str, *, lista_fija, server, modelo, slot_actual=None) -> List[str]:
    resolvedor = RESOLVEDORES_LISTA.get(fuente_lista)
    if resolvedor is None:
        raise ErrorEjecucion(f"fuente_lista desconocida: {fuente_lista!r}")
    return resolvedor(engine, lista_fija=lista_fija, server=server, modelo=modelo, slot_actual=slot_actual)


# ─── Resolución de la consulta a filas de BD ────────────────────────────────

def _consulta_por_codigo(engine, codigo: str) -> Optional[dict]:
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                "SELECT id, codigo, timeout_seg, perfil_credencial, modo_sync_max_olts, activo "
                "FROM OLT_API_CONSULTAS WHERE codigo = :codigo"
            ),
            {"codigo": codigo},
        ).mappings().first()
    return dict(fila) if fila else None


def _filas_de_comandos(engine, consulta_id: int, modelo: str, server: str) -> List[dict]:
    with engine.connect() as conn:
        filas = conn.execute(
            text(
                """
                SELECT modelo, server, orden, contexto, interface_tipo, comando_template,
                       enter_extra, repetir_por, fuente_lista, lista_fija
                FROM OLT_API_CONSULTA_COMANDOS
                WHERE consulta_id = :cid AND activo = 1
                  AND (server = :server OR server IS NULL)
                  AND (modelo = :modelo OR modelo IS NULL)
                ORDER BY orden
                """
            ),
            {"cid": consulta_id, "server": server, "modelo": modelo},
        ).mappings().all()
    return [dict(f) for f in filas]


def _filas_aplicables(filas: List[dict]) -> List[dict]:
    """Prioridad: si hay alguna fila server-específica, usar SOLO esas; si
    no, si hay alguna modelo-específica, usar SOLO esas; si no, las
    generales (server IS NULL AND modelo IS NULL)."""
    especificas_server = [f for f in filas if f["server"] is not None]
    if especificas_server:
        return sorted(especificas_server, key=lambda f: f["orden"])
    especificas_modelo = [f for f in filas if f["modelo"] is not None]
    if especificas_modelo:
        return sorted(especificas_modelo, key=lambda f: f["orden"])
    generales = [f for f in filas if f["modelo"] is None and f["server"] is None]
    return sorted(generales, key=lambda f: f["orden"])


# ─── Ejecución de la secuencia ya resuelta contra una sesión telnet abierta ──

async def _ejecutar_filas(
    cliente: ClienteTelnetOLT, filas: Sequence[dict], engine, *, server: str, modelo: str,
    enviados: List[ComandoResuelto], acumulado: List[str],
) -> None:
    """Navega enable→config y ejecuta la secuencia, expandiendo outer
    (repetir_por en {'slot','vlan'}) e inner (repetir_por='puerto') según el
    modelo descrito en la cabecera del archivo.

    `enviados` y `acumulado` los crea y lee el LLAMADOR (no se devuelven):
    así, si esta función lanza una excepción a mitad de la secuencia (falla
    de telnet en el comando N), lo que ya se alcanzó a enviar y a leer sigue
    disponible para persistir en OLT_API_LOG_TELNET — un fallo parcial no
    debe perder el log de lo que sí se ejecutó antes de fallar."""

    async def _enviar(orden: int, contexto: str, comando: str) -> None:
        # Registra el comando en `enviados` SOLO tras que el envío+lectura
        # ya se completó: así, si falla a mitad de camino, `enviados` refleja
        # exactamente lo que sí se alcanzó a ejecutar (ver docstring de arriba).
        texto = await cliente.enviar_comando(comando)
        acumulado.append(texto)
        enviados.append(ComandoResuelto(orden, contexto, comando))

    # Navegación mínima necesaria según los contextos de la secuencia. Algunas
    # consultas (ej. 'energia_estado') trabajan en la vista de usuario '>' y
    # NUNCA hacen enable/config — mandarles 'enable'/'config' cambiaría la
    # salida del CLI. Ver sql/02_seed_consultas_comandos.sql (consulta 13).
    contextos = {f["contexto"] for f in filas}
    if contextos & {"config", "interface"}:
        await cliente.enable()
        await cliente.config()
    elif "enable" in contextos:
        await cliente.enable()

    i = 0
    interface_actual: Optional[str] = None
    while i < len(filas):
        fila = filas[i]
        if fila["repetir_por"] in ("slot", "vlan"):
            valores_outer = _resolver_lista(
                engine, fila["fuente_lista"], lista_fija=fila["lista_fija"], server=server, modelo=modelo,
            )
            # Filas "internas" siguientes: hasta la próxima fila outer (o el final).
            j = i + 1
            while j < len(filas) and filas[j]["repetir_por"] not in ("slot", "vlan"):
                j += 1
            filas_internas = filas[i + 1 : j]

            for valor in valores_outer:
                comando_nav = fila["comando_template"].format(**{fila["repetir_por"]: valor})
                if fila["contexto"] == "interface" and interface_actual is not None and interface_actual != comando_nav:
                    await _enviar(fila["orden"], "interface", "quit")
                if fila["contexto"] == "interface":
                    interface_actual = comando_nav
                await _enviar(fila["orden"], fila["contexto"], comando_nav)

                for interna in filas_internas:
                    if interna["repetir_por"] == "puerto":
                        valores_inner = _resolver_lista(
                            engine, interna["fuente_lista"], lista_fija=interna["lista_fija"],
                            server=server, modelo=modelo, slot_actual=valor,
                        )
                        for v_inner in valores_inner:
                            comando = interna["comando_template"].format(puerto=v_inner)
                            if interna["enter_extra"]:
                                await _enviar(interna["orden"], interna["contexto"], "")
                            await _enviar(interna["orden"], interna["contexto"], comando)
                    else:
                        comando = interna["comando_template"]
                        if interna["enter_extra"]:
                            await _enviar(interna["orden"], interna["contexto"], "")
                        await _enviar(interna["orden"], interna["contexto"], comando)
            i = j
            continue

        # Fila suelta, sin outer (repetir_por es NULL o 'puerto' sin outer previo).
        if fila["repetir_por"] == "puerto":
            valores = _resolver_lista(
                engine, fila["fuente_lista"], lista_fija=fila["lista_fija"],
                server=server, modelo=modelo, slot_actual=None,
            )
            for valor in valores:
                comando = fila["comando_template"].format(puerto=valor)
                if fila["enter_extra"]:
                    await _enviar(fila["orden"], fila["contexto"], "")
                await _enviar(fila["orden"], fila["contexto"], comando)
        else:
            comando = fila["comando_template"]
            if fila["enter_extra"]:
                await _enviar(fila["orden"], fila["contexto"], "")
            await _enviar(fila["orden"], fila["contexto"], comando)
        i += 1


# ─── Persistencia del log ───────────────────────────────────────────────────

def _recortar_log(texto: str) -> str:
    """[F8] Tope defensivo al texto crudo que se guarda en OLT_API_LOG_TELNET:
    un telnet que se descontrola (bucle de 'More', firmware raro) no debe
    poder escribir cientos de MB en una fila. El log que se devuelve al
    cliente en la MISMA respuesta no pasa por aquí."""
    tope = settings.MAX_LOG_CRUDO_BYTES
    if texto and len(texto) > tope:
        return texto[:tope] + f"\n...[recortado: {len(texto) - tope} caracteres más]"
    return texto


def _persistir_log(
    engine, *, olt, ip, consulta_codigo, origen, job_uuid, cliente_id,
    comandos_enviados, log_crudo, duracion_ms, exito, error,
) -> None:
    log_crudo = _recortar_log(log_crudo)
    with engine.begin() as conn:
        conn.execute(
            text(
                """
                INSERT INTO OLT_API_LOG_TELNET
                    (olt, ip, consulta_codigo, origen, job_uuid, cliente_id,
                     comandos_enviados, log_crudo, duracion_ms, exito, error)
                VALUES
                    (:olt, :ip, :consulta_codigo, :origen, :job_uuid, :cliente_id,
                     :comandos_enviados, :log_crudo, :duracion_ms, :exito, :error)
                """
            ),
            {
                "olt": olt, "ip": ip, "consulta_codigo": consulta_codigo, "origen": origen,
                "job_uuid": job_uuid, "cliente_id": cliente_id,
                "comandos_enviados": "\n".join(comandos_enviados), "log_crudo": log_crudo,
                "duracion_ms": duracion_ms, "exito": 1 if exito else 0, "error": (error or "")[:255] or None,
            },
        )


# ─── API pública ─────────────────────────────────────────────────────────────

async def ejecutar_consulta(
    *,
    engine,
    gobernador: GobernadorSesiones,
    codigo: str,
    server: str,
    ip: str,
    modelo: str,
    usuario_telnet: str,
    password_telnet: str,
    origen: str = "api",
    cliente_id: Optional[int] = None,
    job_uuid: Optional[str] = None,
    fabrica_cliente_telnet: Callable[..., ClienteTelnetOLT] = ClienteTelnetOLT,
) -> ResultadoEjecucion:
    """Punto de entrada de F4: resuelve `codigo` a comandos (BD), adquiere
    cupo del gobernador, ejecuta por telnet, persiste OLT_API_LOG_TELNET, y
    devuelve el resultado. `fabrica_cliente_telnet` es inyectable para tests
    (apunta a tests/fake_olt_cli.py en vez de una OLT real)."""
    consulta = await run_in_threadpool(_consulta_por_codigo, engine, codigo)
    if consulta is None or not consulta.get("activo"):
        raise ErrorEjecucion(f"consulta desconocida o inactiva: {codigo!r}")

    filas_todas = await run_in_threadpool(_filas_de_comandos, engine, consulta["id"], modelo, server)
    filas = _filas_aplicables(filas_todas)
    if not filas:
        raise ErrorEjecucion(f"'{codigo}' no tiene comandos definidos para el modelo {modelo!r}")

    inicio = time.monotonic()
    # `enviados`/`acumulado` los llena _ejecutar_filas EN VIVO (no al final):
    # si falla a mitad de camino, lo que ya se alcanzó a mandar y a leer
    # sigue disponible para persistir — un fallo parcial no debe perder el
    # log de lo que sí se ejecutó antes de fallar.
    enviados: List[ComandoResuelto] = []
    acumulado: List[str] = []
    ok = False
    error: Optional[str] = None

    try:
        async with gobernador.adquirir(
            olt=server, ip=ip, usuario=usuario_telnet, engine=engine,
            origen=origen, cliente_id=cliente_id, job_uuid=job_uuid,
        ):
            timeout = consulta.get("timeout_seg") or settings.TELNET_TIMEOUT_SEG
            cliente = fabrica_cliente_telnet(ip, timeout=timeout)
            try:
                await cliente.conectar(usuario_telnet, password_telnet)
                await _ejecutar_filas(
                    cliente, filas, engine, server=server, modelo=modelo,
                    enviados=enviados, acumulado=acumulado,
                )
                ok = True
            except RechazoPorLimiteSesiones:
                gobernador.abrir_circuito(server, usuario_telnet)
                raise
            finally:
                await cliente.cerrar()
    except _EXCEPCIONES_TRANSPARENTES:
        # Cupo agotado / circuito abierto / rechazo por límite: nada se llegó a
        # enviar, así que no se persiste log. El router lo mapea a 503.
        raise
    except Exception as exc:  # noqa: BLE001 — se persiste el error y se re-expone al llamador
        error = str(exc)[:255]
        logger.warning("ejecutor: fallo ejecutando '{}' en {} ({}): {}", codigo, server, ip, exc)

    comandos_enviados = [r.comando for r in enviados]
    log_crudo = "".join(acumulado)
    duracion_ms = int((time.monotonic() - inicio) * 1000)
    await run_in_threadpool(
        _persistir_log, engine, olt=server, ip=ip, consulta_codigo=codigo, origen=origen,
        job_uuid=job_uuid, cliente_id=cliente_id, comandos_enviados=comandos_enviados,
        log_crudo=log_crudo, duracion_ms=duracion_ms, exito=ok, error=error,
    )

    if not ok and error:
        raise ErrorEjecucion(error)

    return ResultadoEjecucion(
        ok=ok, log_crudo=log_crudo, comandos_enviados=comandos_enviados,
        duracion_ms=duracion_ms, error=error,
    )


# ─── /ejecutar/comandos: lista de comandos con orden del cliente ────────────

_TIPOS_INTERFAZ = {"eth", "giu", "scu", "mpu", "gpon"}


async def ejecutar_comandos(
    *,
    engine,
    gobernador: GobernadorSesiones,
    server: str,
    ip: str,
    usuario_telnet: str,
    password_telnet: str,
    comandos: Sequence[str],
    navegacion: Optional[dict] = None,
    timeout_seg: Optional[int] = None,
    origen: str = "api",
    cliente_id: Optional[int] = None,
    job_uuid: Optional[str] = None,
    fabrica_cliente_telnet: Callable[..., ClienteTelnetOLT] = ClienteTelnetOLT,
) -> ResultadoEjecucion:
    """Ejecuta `comandos` (YA validados por utils/validador_comandos.py — este
    módulo NO valida) en UNA sola sesión telnet.

    La navegación la genera esta función a partir de `navegacion`
    (`{"tipo": "gpon", "slot": 3}` con tipo/slot ya tipados por el schema
    EjecutarComandosRequest) → `enable` + `config` + `interface gpon 0/3`. El
    cliente NUNCA envía comandos de navegación (deny-list del validador).

    Persiste OLT_API_LOG_TELNET con consulta_codigo=NULL (comandos libres).
    `ResultadoEjecucion.salida_por_comando` trae la salida separada por comando."""
    if navegacion is not None:
        tipo = str(navegacion.get("tipo", "")).lower()
        slot = navegacion.get("slot")
        if tipo not in _TIPOS_INTERFAZ or not isinstance(slot, int):
            raise ErrorEjecucion(f"navegación inválida: {navegacion!r}")

    inicio = time.monotonic()
    enviados: List[str] = []
    acumulado: List[str] = []
    salida_por_comando: List[dict] = []
    ok = False
    error: Optional[str] = None

    async def _enviar(cliente: ClienteTelnetOLT, comando: str, *, registrar_salida: bool) -> None:
        texto = await cliente.enviar_comando(comando)
        acumulado.append(texto)
        enviados.append(comando)
        if registrar_salida:
            salida_por_comando.append({"comando": comando, "salida": texto})

    try:
        async with gobernador.adquirir(
            olt=server, ip=ip, usuario=usuario_telnet, engine=engine,
            origen=origen, cliente_id=cliente_id, job_uuid=job_uuid,
        ):
            cliente = fabrica_cliente_telnet(ip, timeout=timeout_seg or settings.TELNET_TIMEOUT_SEG)
            try:
                await cliente.conectar(usuario_telnet, password_telnet)
                await cliente.enable()
                await cliente.config()
                if navegacion is not None:
                    await _enviar(
                        cliente,
                        f"interface {navegacion['tipo']} 0/{navegacion['slot']}",
                        registrar_salida=False,
                    )
                for comando in comandos:
                    await _enviar(cliente, comando, registrar_salida=True)
                ok = True
            except RechazoPorLimiteSesiones:
                gobernador.abrir_circuito(server, usuario_telnet)
                raise
            finally:
                await cliente.cerrar()
    except _EXCEPCIONES_TRANSPARENTES:
        raise
    except Exception as exc:  # noqa: BLE001
        error = str(exc)[:255]
        logger.warning("ejecutor: fallo en /ejecutar/comandos en {} ({}): {}", server, ip, exc)

    log_crudo = "".join(acumulado)
    duracion_ms = int((time.monotonic() - inicio) * 1000)
    await run_in_threadpool(
        _persistir_log, engine, olt=server, ip=ip, consulta_codigo=None, origen=origen,
        job_uuid=job_uuid, cliente_id=cliente_id, comandos_enviados=enviados,
        log_crudo=log_crudo, duracion_ms=duracion_ms, exito=ok, error=error,
    )

    if not ok and error:
        raise ErrorEjecucion(error)

    return ResultadoEjecucion(
        ok=ok, log_crudo=log_crudo, comandos_enviados=enviados,
        duracion_ms=duracion_ms, error=error, salida_por_comando=salida_por_comando,
    )
