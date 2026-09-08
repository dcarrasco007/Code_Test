# ============================================================================
# utils/func.py
# Proyecto: api_olt_consultas
# Descripción: Funciones auxiliares compartidas por los routers de lectura (F5)
#              y de ejecución (F6):
#                - contrato de respuesta uniforme (respuesta_dato)
#                - dependencia FastAPI para endpoints de LECTURA
#                  (auth + scope 'lectura' + rate-limit en un solo Depends)
#                - serialización de filas de BD (datetime/Decimal -> JSON)
#                - normalización de los parámetros ?desde&hasta
#
# Contrato de respuesta (ver PLAN_API_OLT.md → "Contrato de respuesta"):
#   { "ok": true,
#     "consulta": "fan",
#     "olt":    {"server": "...", "ip": "...", "modelo": "..."},
#     "fuente": "bd" | "telnet",
#     "fecha_dato": "2026-09-08 10:00:00" | null,
#     "dato":   {"registros": [...], "cantidad": N} | null,
#     "log":    {"id": 123, "comandos": [...], "crudo": "...",
#                "duracion_ms": 1830, "fecha": "...", "exito": true} | null,
#     "meta":   {"motivo": "..."} (opcional),
#     "job":    null }
#
# Para modificar:
#   - Campos del contrato → respuesta_dato() (un solo lugar)
#   - Política de la dependencia de lectura → cliente_lectura()
# ============================================================================

from __future__ import annotations

import datetime as _dt
from decimal import Decimal
from typing import Any, Dict, Iterable, List, Optional

from fastapi import Depends, HTTPException, Query

from app.config.db import get_engine
from model.lectura import m_lectura_model as _lectura
from model.olt.m_olt_model import obtener_olt
from utils.ratelimit import verificar_rate_limit
from utils.token import Cliente, requerir_scopes


# ─── Dependencia para endpoints de lectura ───────────────────────────────────

async def cliente_lectura(
    cliente: Cliente = Depends(requerir_scopes("lectura")),
) -> Cliente:
    """Auth (X-API-Key) + scope 'lectura' + rate-limit por cliente, en un solo
    Depends para no repetir las tres cosas en cada endpoint GET.

    verify_api_key ya dejó `request.state.cliente`, así que el middleware de
    auditoría de main.py registra la LECTURA automáticamente; los routers no
    tienen que auditar nada."""
    verificar_rate_limit(cliente)
    return cliente


# ─── Serialización de filas de BD ────────────────────────────────────────────

_COLUMNAS_OCULTAS = {"id"}


def serializar_valor(valor: Any) -> Any:
    """Convierte tipos de SQLAlchemy/MySQL a algo serializable a JSON."""
    if isinstance(valor, Decimal):
        # float es suficiente para potencias ópticas / tráfico; no se necesita
        # precisión arbitraria del lado del consumidor.
        return float(valor)
    if isinstance(valor, (_dt.datetime, _dt.date)):
        return valor.isoformat(sep=" ") if isinstance(valor, _dt.datetime) else valor.isoformat()
    if isinstance(valor, (bytes, bytearray)):
        return valor.decode("utf-8", "replace")
    return valor


def serializar_fila(fila: Dict[str, Any], *, ocultar: Iterable[str] = _COLUMNAS_OCULTAS) -> Dict[str, Any]:
    ocultas = set(ocultar)
    return {
        clave: serializar_valor(valor)
        for clave, valor in fila.items()
        if clave not in ocultas
    }


def serializar_filas(filas: Iterable[Dict[str, Any]]) -> List[Dict[str, Any]]:
    return [serializar_fila(f) for f in filas]


# ─── Parámetros ?desde&hasta ─────────────────────────────────────────────────

def rango_fechas(
    desde: Optional[_dt.date] = Query(
        None, description="Inicio del rango (YYYY-MM-DD). Si se omite, se devuelve el último dato."
    ),
    hasta: Optional[_dt.date] = Query(
        None, description="Fin del rango (YYYY-MM-DD, inclusive). Requiere 'desde'."
    ),
) -> Optional[tuple]:
    """None → el endpoint devuelve el último dato. (desde, hasta) → serie histórica.

    'hasta' se lleva al final del día para que el rango sea inclusivo. Si solo
    se pasa 'hasta', se ignora (no tiene sentido sin 'desde') → 422 sería más
    ruidoso que útil aquí; se documenta en el manual de usuario."""
    if desde is None:
        return None
    if hasta is None:
        hasta = _dt.date.today()
    if hasta < desde:
        raise HTTPException(status_code=422, detail="'hasta' no puede ser anterior a 'desde'.")
    inicio = _dt.datetime.combine(desde, _dt.time.min)
    fin = _dt.datetime.combine(hasta, _dt.time.max)
    return (inicio, fin)


# ─── Contrato de respuesta uniforme ─────────────────────────────────────────

def formatear_log(log_fila: Optional[Dict[str, Any]]) -> Optional[Dict[str, Any]]:
    """Da forma al último OLT_API_LOG_TELNET para el campo `log` del contrato.
    None si no hay log (el endpoint pone meta.motivo = 'cron sin log')."""
    if not log_fila:
        return None
    comandos_txt = log_fila.get("comandos_enviados") or ""
    return {
        "id": log_fila.get("id"),
        "fecha": serializar_valor(log_fila.get("fecha")),
        "comandos": [c for c in comandos_txt.splitlines() if c.strip()],
        "crudo": log_fila.get("log_crudo"),
        "duracion_ms": log_fila.get("duracion_ms"),
        "exito": bool(log_fila.get("exito")),
        "error": log_fila.get("error") or None,
    }


def respuesta_dato(
    *,
    consulta: str,
    olt: Dict[str, Any],
    registros: Optional[List[Dict[str, Any]]],
    fecha_dato: Any = None,
    log: Optional[Dict[str, Any]] = None,
    meta: Optional[Dict[str, Any]] = None,
    fuente: str = "bd",
) -> Dict[str, Any]:
    """Arma el dict del contrato uniforme. `registros` None o [] → dato: null
    y meta.motivo 'sin registros' (no es error: puede que el cron no haya
    corrido todavía — ver CASOS_DE_USO.md CU-01/A2)."""
    meta = dict(meta or {})
    if registros:
        dato = {"registros": registros, "cantidad": len(registros)}
    else:
        dato = None
        meta.setdefault("motivo", "sin registros")

    return {
        "ok": True,
        "consulta": consulta,
        "olt": {
            "server": olt.get("server"),
            "ip": olt.get("ip"),
            "modelo": olt.get("modelo"),
        },
        "fuente": fuente,
        "fecha_dato": serializar_valor(fecha_dato),
        "dato": dato,
        "log": log,
        "meta": meta or None,
        "job": None,
    }


# ─── Handler compartido de todos los endpoints de lectura ────────────────────

def atender_lectura(
    codigo: str,
    server: str,
    *,
    rango: Optional[tuple] = None,
    con_log: bool = False,
) -> Dict[str, Any]:
    """Lógica común de los 20 GET de F5: resuelve la OLT, lee el dato (último
    lote o rango) y opcionalmente adjunta el último log crudo de telnet.

    Los routers solo aportan el `codigo` de consulta y exponen los parámetros;
    toda la mecánica (404 OLT desconocida, 422 rango no disponible, contrato
    de respuesta) está aquí para que sea idéntica en todos los módulos."""
    engine = get_engine()

    olt = obtener_olt(engine, server)
    if olt is None:
        raise HTTPException(status_code=404, detail=f"OLT '{server}' no existe en OLT_SERVER.")

    valor = _lectura.valor_filtro(codigo, olt)
    if not valor:
        # p.ej. pon_trafico se filtra por IP y la fila de OLT_SERVER no la tiene.
        raise HTTPException(
            status_code=422,
            detail=f"La OLT '{server}' no tiene el dato necesario en OLT_SERVER para esta consulta.",
        )

    meta: Dict[str, Any] = {}
    if rango is not None:
        try:
            filas, truncado = _lectura.leer_rango(engine, codigo, valor, rango)
        except _lectura.RangoNoDisponible:
            raise HTTPException(
                status_code=422,
                detail=(
                    "El filtro ?desde&hasta no está disponible para esta consulta: "
                    "el cron que la alimenta guarda la fecha como texto y no como fecha. "
                    "Use el endpoint sin rango para obtener el último dato."
                ),
            )
        fecha_dato = None
        if truncado:
            meta["motivo"] = f"rango truncado a {_lectura.LIMITE_FILAS_RANGO} filas"
    else:
        filas, fecha_dato = _lectura.leer_ultimo(engine, codigo, valor)

    log = None
    if con_log:
        log = formatear_log(_lectura.ultimo_log_telnet(engine, olt["server"], codigo))
        if log is None:
            meta["motivo_log"] = (
                "sin log de telnet registrado para esta consulta/OLT. "
                "Los crons pueden alimentar OLT_API_LOG_TELNET (origen='cron'); "
                "hoy solo lo hace uplink_trafico si LOG_API_TELNET=true "
                "(ver docs/INTEGRACION_CRONS.md)."
            )

    return respuesta_dato(
        consulta=codigo,
        olt=olt,
        registros=serializar_filas(filas),
        fecha_dato=fecha_dato,
        log=log,
        meta=meta,
    )
