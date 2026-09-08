# ============================================================================
# router/ejecutar/ejecutarRouter.py
# Proyecto: api_olt_consultas
# Descripción: Ejecución de comandos EN VIVO por telnet (F6). A diferencia de
#              los GET de lectura (F5), estos endpoints SÍ abren una sesión
#              telnet a la OLT, respetando el cupo del gobernador.
#
#   POST /ejecutar/consulta/{codigo}   scope: ejecutar_consulta
#        Corre la secuencia fija de una consulta (comandos en BD) contra 1 OLT.
#        Respuesta 200 (sincrónica) o 202 + job (si forzar_async).
#
#   POST /ejecutar/comandos            scope: ejecutar_comandos
#        Lista de comandos con orden del cliente. Cada comando debe ser
#        'display ...' o un template aprobado (scope config_aprobada); la
#        navegación la genera la API desde `navegacion`. 422 si algún comando
#        no pasa la validación.
#
#   POST /ejecutar/lote                scope: ejecutar_consulta
#        Una consulta contra varias OLT → SIEMPRE 202 + job.
#
# Todas: cuota `max_ejecuciones_hora` por cliente (429), auditoría EJECUCION
# vía middleware de main.py. Ver CASOS_DE_USO.md CU-02 / CU-03 / CU-04.
# ============================================================================

from __future__ import annotations

import asyncio
from typing import Any, Dict, Optional

from fastapi import APIRouter, Depends, HTTPException, Response
from loguru import logger

from app.config import settings
from app.config.db import get_engine
from model.ejecutar import m_ejecutar_model
from model.olt.m_olt_model import obtener_olt
from package.parsers.registry import parsear as parsear_consulta
from schema.ejecucion_schema import (
    EjecutarComandosRequest,
    EjecutarConsultaRequest,
    EjecutarLoteRequest,
)
from utils import ejecutor as _ejecutor
from utils.func import respuesta_dato
from utils.jobs import obtener_gestor
from utils.ratelimit import registrar_ejecucion, verificar_cuota_ejecucion
from utils.sesiones_telnet import (
    CircuitoAbiertoError,
    CupoAgotadoError,
    obtener_gobernador,
)
from utils.telnet_olt import RechazoPorLimiteSesiones
from utils.token import Cliente, requerir_scopes
from utils.validador_comandos import validar_cantidad_olts, validar_lista_comandos

ejecutar_router = APIRouter(prefix="/ejecutar")


# ─── helpers ────────────────────────────────────────────────────────────────

def _credenciales(perfil: str) -> tuple:
    usuario, password = settings.credencial_telnet(perfil)
    if not usuario or not password:
        raise HTTPException(
            status_code=503,
            detail=(
                "Credenciales telnet no configuradas en el servidor "
                "(.env: OLT_TELNET_API_* o OLT_TELNET_DEFAULT_*). "
                "Ver SOLICITUDES_PENDIENTES.txt."
            ),
        )
    return usuario, password


def _resolver_olt(engine, server: str) -> Dict[str, Any]:
    olt = obtener_olt(engine, server)
    if olt is None:
        raise HTTPException(status_code=404, detail=f"OLT '{server}' no existe en OLT_SERVER.")
    if not olt.get("ip") or not olt.get("modelo"):
        raise HTTPException(
            status_code=422,
            detail=f"OLT '{server}' sin IP o modelo en OLT_SERVER; no se puede ejecutar telnet.",
        )
    return olt


def _map_error(exc: Exception) -> HTTPException:
    """Traduce las excepciones de utils/ejecutor.py + gobernador al HTTP
    correcto (ver CASOS_DE_USO.md CU-02, errores)."""
    if isinstance(exc, CupoAgotadoError):
        return HTTPException(status_code=503, detail=str(exc), headers={"Retry-After": str(settings.ESPERA_MAX_SESION_SEG)})
    if isinstance(exc, CircuitoAbiertoError):
        return HTTPException(status_code=503, detail=str(exc), headers={"Retry-After": str(exc.segundos_restantes)})
    if isinstance(exc, RechazoPorLimiteSesiones):
        return HTTPException(status_code=503, detail=f"la OLT rechazó la sesión por límite de usuarios: {exc}", headers={"Retry-After": str(settings.BLOQUEO_OLT_SEG)})
    if isinstance(exc, asyncio.TimeoutError):
        return HTTPException(status_code=504, detail="timeout ejecutando la consulta por telnet")
    if isinstance(exc, _ejecutor.ErrorEjecucion):
        msg = str(exc)
        if "no tiene comandos definidos" in msg:
            return HTTPException(status_code=409, detail=msg)
        if "desconocida o inactiva" in msg:
            return HTTPException(status_code=404, detail=msg)
        return HTTPException(status_code=502, detail=f"error ejecutando por telnet: {msg}")
    return HTTPException(status_code=500, detail="error interno ejecutando la consulta")


async def _correr_consulta(
    engine, *, codigo: str, olt: Dict[str, Any], meta: Dict[str, Any],
    cliente_id: Optional[int], job_uuid: Optional[str] = None,
) -> Dict[str, Any]:
    """Ejecuta la consulta contra 1 OLT y devuelve el contrato uniforme.
    Propaga las excepciones de ejecutor/gobernador (las mapea el llamador)."""
    usuario, password = _credenciales(meta.get("perfil_credencial") or "DEFAULT")
    timeout = (meta.get("timeout_seg") or settings.TELNET_TIMEOUT_SEG) + settings.EJECUCION_SYNC_MARGEN_SEG

    resultado = await asyncio.wait_for(
        _ejecutor.ejecutar_consulta(
            engine=engine,
            gobernador=obtener_gobernador(),
            codigo=codigo,
            server=olt["server"],
            ip=olt["ip"],
            modelo=olt["modelo"],
            usuario_telnet=usuario,
            password_telnet=password,
            origen="api",
            cliente_id=cliente_id,
            job_uuid=job_uuid,
        ),
        timeout=timeout,
    )

    dato = None
    meta_resp: Dict[str, Any] = {}
    try:
        parsed = parsear_consulta(codigo, resultado.log_crudo)
        dato = [parsed] if parsed else None
        if parsed is None:
            meta_resp["motivo"] = "el parser no encontró datos en la salida (log crudo incluido)"
    except Exception as exc:  # noqa: BLE001 — nunca perder el log crudo por un parser
        logger.warning("ejecutar: parser de '{}' falló: {}", codigo, exc)
        meta_resp["motivo"] = f"el parser falló: {exc}"

    return respuesta_dato(
        consulta=codigo,
        olt=olt,
        registros=dato,
        fecha_dato=None,
        fuente="telnet",
        log={
            "comandos": resultado.comandos_enviados,
            "crudo": resultado.log_crudo,
            "duracion_ms": resultado.duracion_ms,
            "exito": resultado.ok,
            "error": resultado.error,
        },
        meta=meta_resp,
    )


# ─── POST /ejecutar/consulta/{codigo} ───────────────────────────────────────

@ejecutar_router.post("/consulta/{codigo}", summary="Ejecutar la secuencia fija de una consulta contra una OLT")
async def ejecutar_consulta_endpoint(
    codigo: str,
    body: EjecutarConsultaRequest,
    response: Response,
    cliente: Cliente = Depends(requerir_scopes("ejecutar_consulta")),
):
    engine = get_engine()
    meta = m_ejecutar_model.consulta_meta(engine, codigo)
    if meta is None or not meta.get("activo"):
        raise HTTPException(status_code=404, detail=f"consulta '{codigo}' inexistente o inactiva.")
    olt = _resolver_olt(engine, body.server)

    verificar_cuota_ejecucion(cliente)

    if body.forzar_async:
        uuid = await obtener_gestor().encolar(
            tipo="ejecutar_consulta",
            parametros={"codigo": codigo, "server": body.server},
            cliente_id=cliente.id,
        )
        response.status_code = 202
        return _respuesta_job(uuid)

    registrar_ejecucion(cliente)
    try:
        return await _correr_consulta(engine, codigo=codigo, olt=olt, meta=meta, cliente_id=cliente.id)
    except HTTPException:
        raise
    except Exception as exc:  # noqa: BLE001
        raise _map_error(exc)


# ─── POST /ejecutar/comandos ────────────────────────────────────────────────

@ejecutar_router.post("/comandos", summary="Ejecutar una lista de comandos (display / aprobados) con orden del cliente")
async def ejecutar_comandos_endpoint(
    body: EjecutarComandosRequest,
    cliente: Cliente = Depends(requerir_scopes("ejecutar_comandos")),
):
    engine = get_engine()
    olt = _resolver_olt(engine, body.server)

    templates = m_ejecutar_model.templates_aprobados_activos(engine)
    validacion = validar_lista_comandos(
        body.comandos,
        tiene_scope_config_aprobada=cliente.tiene_scope("config_aprobada"),
        templates_aprobados=templates,
        max_comandos=settings.MAX_COMANDOS_REQUEST,
    )
    if not validacion.ok:
        raise HTTPException(status_code=422, detail={"comandos_rechazados": validacion.errores})

    verificar_cuota_ejecucion(cliente)
    usuario, password = _credenciales("DEFAULT")
    registrar_ejecucion(cliente)

    try:
        resultado = await asyncio.wait_for(
            _ejecutor.ejecutar_comandos(
                engine=engine,
                gobernador=obtener_gobernador(),
                server=olt["server"],
                ip=olt["ip"],
                usuario_telnet=usuario,
                password_telnet=password,
                comandos=body.comandos,
                navegacion=body.navegacion.model_dump() if body.navegacion else None,
                origen="api",
                cliente_id=cliente.id,
            ),
            timeout=settings.TELNET_TIMEOUT_SEG * 2 + settings.EJECUCION_SYNC_MARGEN_SEG,
        )
    except HTTPException:
        raise
    except Exception as exc:  # noqa: BLE001
        raise _map_error(exc)

    return respuesta_dato(
        consulta="comandos",
        olt=olt,
        registros=resultado.salida_por_comando or None,
        fecha_dato=None,
        fuente="telnet",
        log={
            "comandos": resultado.comandos_enviados,
            "crudo": resultado.log_crudo,
            "duracion_ms": resultado.duracion_ms,
            "exito": resultado.ok,
            "error": resultado.error,
        },
    )


# ─── POST /ejecutar/lote ────────────────────────────────────────────────────

@ejecutar_router.post("/lote", status_code=202, summary="Ejecutar una consulta contra varias OLT (asíncrono, job)")
async def ejecutar_lote_endpoint(
    body: EjecutarLoteRequest,
    cliente: Cliente = Depends(requerir_scopes("ejecutar_consulta")),
):
    engine = get_engine()
    meta = m_ejecutar_model.consulta_meta(engine, body.codigo)
    if meta is None or not meta.get("activo"):
        raise HTTPException(status_code=404, detail=f"consulta '{body.codigo}' inexistente o inactiva.")
    try:
        validar_cantidad_olts(body.servers, max_olts=settings.MAX_OLTS_REQUEST)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=str(exc))

    verificar_cuota_ejecucion(cliente)
    uuid = await obtener_gestor().encolar(
        tipo="ejecutar_lote",
        parametros={"codigo": body.codigo, "servers": body.servers},
        cliente_id=cliente.id,
    )
    return _respuesta_job(uuid)


def _respuesta_job(uuid: str) -> Dict[str, Any]:
    return {"ok": True, "job": {"uuid": uuid, "estado": "PENDIENTE"}, "dato": None, "log": None}


# ─── handlers de jobs (registrados en el gestor al importar este módulo) ────

async def _job_ejecutar_consulta(parametros: Dict[str, Any]) -> Dict[str, Any]:
    engine = get_engine()
    codigo = parametros["codigo"]
    server = parametros["server"]
    meta = m_ejecutar_model.consulta_meta(engine, codigo)
    if meta is None or not meta.get("activo"):
        raise _ejecutor.ErrorEjecucion(f"consulta '{codigo}' desconocida o inactiva")
    olt = obtener_olt(engine, server)
    if olt is None:
        raise _ejecutor.ErrorEjecucion(f"OLT '{server}' no existe en OLT_SERVER")
    return await _correr_consulta(engine, codigo=codigo, olt=olt, meta=meta, cliente_id=None)


async def _job_ejecutar_lote(parametros: Dict[str, Any]) -> Dict[str, Any]:
    engine = get_engine()
    codigo = parametros["codigo"]
    meta = m_ejecutar_model.consulta_meta(engine, codigo)
    if meta is None or not meta.get("activo"):
        raise _ejecutor.ErrorEjecucion(f"consulta '{codigo}' desconocida o inactiva")

    resultados: Dict[str, Any] = {}
    for server in parametros["servers"]:
        olt = obtener_olt(engine, server)
        if olt is None:
            resultados[server] = {"ok": False, "error": "OLT no existe en OLT_SERVER"}
            continue
        try:
            contrato = await _correr_consulta(engine, codigo=codigo, olt=olt, meta=meta, cliente_id=None)
            resultados[server] = {
                "ok": bool(contrato["log"]["exito"]),
                "dato": contrato["dato"],
                "duracion_ms": contrato["log"]["duracion_ms"],
                "error": contrato["log"]["error"],
            }
        except Exception as exc:  # noqa: BLE001 — un fallo en una OLT no aborta el lote
            resultados[server] = {"ok": False, "error": str(exc)[:255]}
    return {"codigo": codigo, "olts": resultados}


obtener_gestor().registrar_handler("ejecutar_consulta", _job_ejecutar_consulta)
obtener_gestor().registrar_handler("ejecutar_lote", _job_ejecutar_lote)
