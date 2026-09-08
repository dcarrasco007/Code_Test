# ============================================================================
# utils/jobs.py
# Proyecto: api_olt_consultas
# Descripción: Cola de ejecuciones asíncronas EN PROCESO (F6). Cuando una
#              ejecución afecta a varias OLT o se pide con forzar_async, el
#              endpoint responde 202 + job_uuid y el trabajo real lo hace un
#              único worker asyncio de fondo, dejando el estado en
#              OLT_API_JOBS (PENDIENTE → RUNNING → OK|ERROR|TIMEOUT).
#
# Por qué UN solo worker: procesa los jobs de a uno. Es la forma más simple
# de acotar cuántas sesiones telnet abre la API por su cuenta, además del
# gobernador de cupo por (OLT, usuario). Igual que el resto del proyecto, esto
# vale para UN proceso uvicorn (sin --workers) — ver PLAN_API_OLT.md.
#
# Los jobs NO se reanudan tras un reinicio: al arrancar, main.py llama a
# m_jobs_model.marcar_interrumpidos_al_iniciar() y todo lo que quedó
# PENDIENTE/RUNNING pasa a ERROR (CASOS_DE_USO.md CU-04/A1).
#
# Para modificar:
#   - Timeout de un job → app/config/settings.py JOB_TIMEOUT_SEG
#   - Handlers por tipo → registrar_handler() (lo hace router/ejecutar)
# ============================================================================

from __future__ import annotations

import asyncio
import uuid as _uuid
from typing import Any, Awaitable, Callable, Dict, Optional

from fastapi.concurrency import run_in_threadpool
from loguru import logger

from app.config import settings
from model.jobs import m_jobs_model

# handler(parametros: dict) -> dict (lo que se guarda en OLT_API_JOBS.resultado)
Handler = Callable[[Dict[str, Any]], Awaitable[Dict[str, Any]]]


class GestorJobs:
    """Instanciar UNA vez por proceso — usar obtener_gestor()."""

    def __init__(self):
        self._cola: asyncio.Queue = asyncio.Queue()
        self._handlers: Dict[str, Handler] = {}
        self._worker: Optional[asyncio.Task] = None
        self._engine = None

    # ─── configuración ─────────────────────────────────────────────────────
    def registrar_handler(self, tipo: str, handler: Handler) -> None:
        self._handlers[tipo] = handler

    async def iniciar(self, engine) -> None:
        self._engine = engine
        if self._worker is None or self._worker.done():
            self._worker = asyncio.create_task(self._bucle(), name="jobs-worker")
            logger.info("jobs: worker de fondo iniciado")

    async def detener(self) -> None:
        if self._worker is not None:
            self._worker.cancel()
            try:
                await self._worker
            except asyncio.CancelledError:
                pass
            self._worker = None

    # ─── encolar ───────────────────────────────────────────────────────────
    async def encolar(self, *, tipo: str, parametros: Dict[str, Any], cliente_id: Optional[int]) -> str:
        if tipo not in self._handlers:
            raise ValueError(f"tipo de job desconocido: {tipo!r}")
        uuid = str(_uuid.uuid4())
        await run_in_threadpool(
            m_jobs_model.crear, self._engine,
            uuid=uuid, cliente_id=cliente_id, tipo=tipo, parametros=parametros,
        )
        await self._cola.put((uuid, tipo, parametros))
        return uuid

    # ─── worker ────────────────────────────────────────────────────────────
    async def _bucle(self) -> None:
        while True:
            uuid, tipo, parametros = await self._cola.get()
            try:
                await self._ejecutar_job(uuid, tipo, parametros)
            except asyncio.CancelledError:
                raise
            except Exception as exc:  # noqa: BLE001 — un job no debe tumbar el worker
                logger.exception("jobs: error no controlado en el job {} ({})", uuid, tipo)
                await self._marcar_fin_seguro(uuid, "ERROR", error=str(exc))
            finally:
                self._cola.task_done()

    async def _ejecutar_job(self, uuid: str, tipo: str, parametros: Dict[str, Any]) -> None:
        await run_in_threadpool(m_jobs_model.marcar_running, self._engine, uuid)
        handler = self._handlers.get(tipo)
        if handler is None:
            await self._marcar_fin_seguro(uuid, "ERROR", error=f"sin handler para {tipo!r}")
            return
        try:
            resultado = await asyncio.wait_for(handler(parametros), timeout=settings.JOB_TIMEOUT_SEG)
        except asyncio.TimeoutError:
            logger.warning("jobs: job {} ({}) excedió {}s → TIMEOUT", uuid, tipo, settings.JOB_TIMEOUT_SEG)
            await self._marcar_fin_seguro(uuid, "TIMEOUT", error=f"excedió {settings.JOB_TIMEOUT_SEG}s")
            return
        except Exception as exc:  # noqa: BLE001
            logger.warning("jobs: job {} ({}) falló: {}", uuid, tipo, exc)
            await self._marcar_fin_seguro(uuid, "ERROR", error=str(exc))
            return
        await self._marcar_fin_seguro(uuid, "OK", resultado=resultado)

    async def _marcar_fin_seguro(self, uuid: str, estado: str, *, resultado: Any = None, error: Optional[str] = None) -> None:
        try:
            await run_in_threadpool(
                m_jobs_model.marcar_fin, self._engine, uuid, estado, resultado=resultado, error=error
            )
        except Exception:  # noqa: BLE001 — best effort, igual que la auditoría
            logger.exception("jobs: no se pudo escribir el estado final {} del job {}", estado, uuid)


_gestor_global = GestorJobs()


def obtener_gestor() -> GestorJobs:
    return _gestor_global
