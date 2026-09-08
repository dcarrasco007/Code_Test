# ============================================================================
# utils/sesiones_telnet.py
# Proyecto: api_olt_consultas
# Descripción: El "gobernador" de sesiones telnet — aplica el requisito
#              explícito del usuario: cada OLT admite máximo 3 sesiones
#              telnet simultáneas por (OLT, usuario_telnet); la 4ª la cierra
#              o bloquea el equipo. Ver PLAN_API_OLT.md → "Control de
#              sesiones telnet" y docs/DIAGRAMAS.md → diagrama de secuencia
#              de ejecución respetando el límite.
#
# Piezas:
#   - asyncio.Semaphore por clave (olt, usuario_telnet), cupo =
#     settings.cupo_por_olt_usuario() (3 con usuario dedicado, 1 sin él).
#   - Cola: si no hay cupo libre, espera hasta ESPERA_MAX_SESION_SEG antes de
#     lanzar CupoAgotadoError (el llamador responde 503 + Retry-After, o deja
#     el job en PENDIENTE — F6).
#   - Circuit-breaker: si utils/telnet_olt.py detecta un rechazo por límite
#     (RechazoPorLimiteSesiones), abrir_circuito() corta nuevos intentos a
#     esa (OLT, usuario) durante BLOQUEO_OLT_SEG, para no seguir golpeando un
#     equipo que ya dijo que no.
#   - Registro en OLT_API_SESIONES_TELNET (alta/latido/cierre): observabilidad
#     (/admin/sesiones, F6) y recuperación de huérfanas tras un reinicio.
#
# LÍMITE CONOCIDO (documentar en el manual de desarrollador, F8): el cupo
# vive en memoria de ESTE proceso, igual que utils/ratelimit.py. Solo es
# correcto con UN SOLO proceso uvicorn (sin --workers) — decisión ya tomada
# para todo el proyecto. Escalar a varios workers exigiría mover el cupo a
# BD/Redis con locking real, no solo el registro de observabilidad actual.
#
# Para modificar:
#   - Cupo / timeouts de cola y de bloqueo → app/config/settings.py
#     (cupo_por_olt_usuario, ESPERA_MAX_SESION_SEG, BLOQUEO_OLT_SEG)
# ============================================================================

from __future__ import annotations

import asyncio
import time
from contextlib import asynccontextmanager
from typing import Dict, Optional, Tuple

from fastapi.concurrency import run_in_threadpool
from loguru import logger
from sqlalchemy import text

from app.config import settings


class CupoAgotadoError(Exception):
    """No se consiguió cupo dentro de ESPERA_MAX_SESION_SEG. El llamador debe
    responder 503 (con Retry-After) o, en un lote, dejar ese job PENDIENTE."""


class CircuitoAbiertoError(Exception):
    """La (OLT, usuario) está en cooldown tras un rechazo reciente por
    límite de sesiones. El llamador debe responder 503 con Retry-After."""

    def __init__(self, olt: str, usuario: str, segundos_restantes: int):
        self.olt = olt
        self.usuario = usuario
        self.segundos_restantes = segundos_restantes
        super().__init__(
            f"circuito abierto para ({olt}, {usuario}): reintentar en {segundos_restantes}s"
        )


class GobernadorSesiones:
    """Ver el docstring del módulo. Instanciar UNA vez por proceso — usar
    obtener_gobernador(), no instanciar esta clase directamente fuera de
    tests (que sí crean instancias propias para no compartir estado)."""

    def __init__(self):
        self._semaforos: Dict[Tuple[str, str], asyncio.Semaphore] = {}
        self._lock_semaforos = asyncio.Lock()
        self._circuit_breaker: Dict[Tuple[str, str], float] = {}
        # Tope GLOBAL de sesiones telnet simultáneas (todas las OLT juntas),
        # además del cupo por (OLT, usuario). Las OLT Huawei limitan las VTY
        # y no interesa que la API abra 50 sockets a la vez aunque sean a
        # equipos distintos. settings.TELNET_MAX_CONCURRENTES.
        self._semaforo_global = asyncio.Semaphore(settings.TELNET_MAX_CONCURRENTES)

    async def _obtener_semaforo(self, olt: str, usuario: str) -> asyncio.Semaphore:
        clave = (olt, usuario)
        async with self._lock_semaforos:
            sem = self._semaforos.get(clave)
            if sem is None:
                sem = asyncio.Semaphore(settings.cupo_por_olt_usuario())
                self._semaforos[clave] = sem
            return sem

    def _circuito_abierto_hasta(self, olt: str, usuario: str) -> Optional[float]:
        hasta = self._circuit_breaker.get((olt, usuario))
        if hasta is not None and time.monotonic() < hasta:
            return hasta
        return None

    def abrir_circuito(self, olt: str, usuario: str) -> None:
        """Llamar cuando utils/telnet_olt.py lanza RechazoPorLimiteSesiones.
        Corta nuevos intentos a esa (OLT, usuario) por BLOQUEO_OLT_SEG."""
        self._circuit_breaker[(olt, usuario)] = time.monotonic() + settings.BLOQUEO_OLT_SEG
        logger.warning(
            "sesiones_telnet: circuito ABIERTO para ({}, {}) durante {}s (rechazo por límite de sesiones)",
            olt, usuario, settings.BLOQUEO_OLT_SEG,
        )

    @asynccontextmanager
    async def adquirir(
        self,
        *,
        olt: str,
        ip: str,
        usuario: str,
        engine,
        origen: str = "api",
        cliente_id: Optional[int] = None,
        job_uuid: Optional[str] = None,
    ):
        """Espera un cupo de sesión para (olt, usuario), registra la apertura
        en BD, y SIEMPRE libera el semáforo + cierra el registro al salir
        (éxito o excepción). `engine` se recibe como parámetro (en vez de
        importar get_engine() aquí) para que los tests puedan pasar un fake
        sin necesidad de una base de datos real. Uso:

            async with gobernador.adquirir(olt=..., ip=..., usuario=..., engine=get_engine()) as sesion_id:
                cliente = ClienteTelnetOLT(ip, timeout=settings.TELNET_TIMEOUT_SEG)
                try:
                    await cliente.conectar(usuario, password)
                    ...
                except RechazoPorLimiteSesiones:
                    gobernador.abrir_circuito(olt, usuario)
                    raise
                finally:
                    await cliente.cerrar()   # logout garantizado, aparte de este context manager
        """
        hasta = self._circuito_abierto_hasta(olt, usuario)
        if hasta is not None:
            raise CircuitoAbiertoError(olt, usuario, int(hasta - time.monotonic()) + 1)

        sem = await self._obtener_semaforo(olt, usuario)
        espera = settings.ESPERA_MAX_SESION_SEG
        try:
            await asyncio.wait_for(sem.acquire(), timeout=espera)
        except asyncio.TimeoutError:
            raise CupoAgotadoError(
                f"sin cupo disponible para ({olt}, {usuario}) tras {espera}s de espera"
            )

        # Cupo por (OLT, usuario) concedido: ahora el tope global.
        try:
            await asyncio.wait_for(self._semaforo_global.acquire(), timeout=espera)
        except asyncio.TimeoutError:
            sem.release()
            raise CupoAgotadoError(
                f"tope global de {settings.TELNET_MAX_CONCURRENTES} sesiones telnet "
                f"alcanzado (espera {espera}s agotada)"
            )

        sesion_id = await run_in_threadpool(
            _registrar_apertura, engine, olt=olt, ip=ip, usuario_telnet=usuario,
            origen=origen, cliente_id=cliente_id, job_uuid=job_uuid,
        )
        motivo: Optional[str] = None
        try:
            yield sesion_id
        except Exception as exc:
            motivo = f"error: {exc}"[:100]
            raise
        finally:
            try:
                await run_in_threadpool(_registrar_cierre, engine, sesion_id, "CERRADA", motivo)
            finally:
                self._semaforo_global.release()
                sem.release()

    async def latido(self, engine, sesion_id: int) -> None:
        await run_in_threadpool(_registrar_latido, engine, sesion_id)


_gobernador_global = GobernadorSesiones()


def obtener_gobernador() -> GobernadorSesiones:
    """Un solo gobernador por proceso — coherente con el semáforo en
    memoria (ver 'LÍMITE CONOCIDO' en la cabecera del archivo)."""
    return _gobernador_global


# ─── Acceso a datos (funciones cortas, reemplazables en tests) ─────────────

def _registrar_apertura(engine, *, olt, ip, usuario_telnet, origen, cliente_id, job_uuid) -> int:
    with engine.begin() as conn:
        resultado = conn.execute(
            text(
                """
                INSERT INTO OLT_API_SESIONES_TELNET
                    (olt, ip, usuario_telnet, origen, job_uuid, cliente_id)
                VALUES (:olt, :ip, :usuario_telnet, :origen, :job_uuid, :cliente_id)
                """
            ),
            {
                "olt": olt, "ip": ip, "usuario_telnet": usuario_telnet,
                "origen": origen, "job_uuid": job_uuid, "cliente_id": cliente_id,
            },
        )
        return resultado.lastrowid


def _registrar_latido(engine, sesion_id: int) -> None:
    with engine.begin() as conn:
        conn.execute(
            text("UPDATE OLT_API_SESIONES_TELNET SET ultimo_latido = NOW() WHERE id = :id"),
            {"id": sesion_id},
        )


def _registrar_cierre(engine, sesion_id: int, estado: str, motivo: Optional[str]) -> None:
    with engine.begin() as conn:
        conn.execute(
            text(
                """
                UPDATE OLT_API_SESIONES_TELNET
                SET estado = :estado, fecha_cierre = NOW(), motivo_cierre = :motivo
                WHERE id = :id
                """
            ),
            {"id": sesion_id, "estado": estado, "motivo": motivo},
        )


def marcar_huerfanas_al_iniciar(engine) -> int:
    """Al arrancar la API, toda fila que haya quedado 'ABIERTA' es de un
    proceso anterior (los semáforos en memoria no sobreviven un restart, así
    que ningún cupo real sigue reservado) — se marca HUERFANA para no
    confundir /admin/sesiones (F6) ni la auditoría. Llamar desde el evento
    startup de main.py. Devuelve cuántas filas se actualizaron."""
    with engine.begin() as conn:
        resultado = conn.execute(
            text("UPDATE OLT_API_SESIONES_TELNET SET estado = 'HUERFANA' WHERE estado = 'ABIERTA'")
        )
        return resultado.rowcount
