# ============================================================================
# main.py
# Proyecto: api_olt_consultas — API de consultas a OLT Huawei (FastAPI)
# Descripción: Punto de entrada de la API. Instancia FastAPI, CORS, y registra
#              los routers por módulo (convención GERET README_PYTHON_API.md).
# Para modificar:
#   - Registrar un router nuevo → buscar [ROUTER]
#   - Configuración (host, puerto, CORS, docs) → app/config/settings.py
#
# Arranque local:   .venv/Scripts/python -m uvicorn main:app --reload
# Arranque server:  .venv/bin/python -m uvicorn main:app --host <IP> --port 5001
#
# NOTA DE SEGURIDAD: esta API se expone sin nginx/TLS (decisión del proyecto),
# solo en red interna. La API key viaja en claro, por eso todo cliente debe
# tener 'ips_permitidas' y el puerto debe estar restringido por firewall.
# Ver PLAN_API_OLT.md → Seguridad → Transporte.
# ============================================================================

import time
import uuid
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request
from fastapi.concurrency import run_in_threadpool
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from loguru import logger

from app.config import settings
from app.config.db import get_engine
from utils import auditoria
from utils.jobs import obtener_gestor
from utils.sesiones_telnet import marcar_huerfanas_al_iniciar


@asynccontextmanager
async def lifespan(app: FastAPI):
    # [SEGURIDAD/OBSERVABILIDAD] Toda fila OLT_API_SESIONES_TELNET que haya
    # quedado 'ABIERTA' es de un proceso anterior (ver utils/sesiones_telnet.py).
    # Best-effort a propósito: si CONECTION_ADEN no está configurada, o la BD
    # no tiene todavía las tablas de F1, o no está alcanzable, la API debe
    # igual poder levantar (mismo criterio que /health, que tampoco exige BD
    # para responder). get_engine() haría sys.exit(1) si falta la variable —
    # eso es lo correcto para un endpoint que SÍ necesita BD, pero no para
    # este housekeeping de arranque, así que se evita llamarlo sin configurar.
    if not settings.CONECTION_ADEN:
        logger.warning("main: CONECTION_ADEN no configurada; se omite el housekeeping de arranque (sesiones/jobs)")
    else:
        engine = get_engine()
        try:
            n = await run_in_threadpool(marcar_huerfanas_al_iniciar, engine)
            if n:
                logger.info("main: {} sesion(es) telnet quedaron HUERFANA tras un reinicio anterior", n)
        except Exception as exc:
            logger.warning("main: no se pudo marcar sesiones telnet huérfanas al iniciar ({}); se continúa igual", exc)
        try:
            from model.jobs import m_jobs_model

            n = await run_in_threadpool(m_jobs_model.marcar_interrumpidos_al_iniciar, engine)
            if n:
                logger.info("main: {} job(s) quedaron interrumpidos por un reinicio anterior → ERROR", n)
        except Exception as exc:
            logger.warning("main: no se pudo limpiar jobs interrumpidos al iniciar ({}); se continúa igual", exc)
        # Worker de la cola de jobs asíncronos (F6).
        try:
            await obtener_gestor().iniciar(engine)
        except Exception as exc:
            logger.warning("main: no se pudo iniciar el worker de jobs ({}); /ejecutar/lote no funcionará", exc)

    yield

    # Apagado: detener el worker de jobs limpiamente.
    try:
        await obtener_gestor().detener()
    except Exception:
        pass


app = FastAPI(
    title="API OLT — Consultas",
    description=(
        "Consulta de datos de OLT Huawei (alarmas, tráfico, potencia óptica, ONT, "
        "VLAN, tarjetas, versión...) desde base de datos, y ejecución controlada "
        "de comandos por telnet devolviendo el log completo."
    ),
    version=settings.VERSION_API,
    # [CONFIG] /docs y /redoc se deshabilitan en producción desde el .env.
    docs_url="/docs" if settings.API_DOCS_HABILITADO else None,
    redoc_url="/redoc" if settings.API_DOCS_HABILITADO else None,
    openapi_url="/openapi.json" if settings.API_DOCS_HABILITADO else None,
    lifespan=lifespan,
)

# [CONFIG] Orígenes explícitos desde .env. Nunca ["*"] en producción.
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["GET", "POST"],
    allow_headers=["X-API-Key", "Content-Type"],
)


# ─── Middleware de auditoría ─────────────────────────────────────────────────
# utils/token.py (verify_api_key) ya audita AUTH_OK/AUTH_FAIL, porque en ese
# momento es quien tiene el contexto (motivo exacto del fallo). Este
# middleware audita el RESTO de peticiones ya autenticadas: éxito de lectura,
# éxito de ejecución, o rechazo posterior a la autenticación (403 de scopes,
# 422 de validación, 429 de rate-limit, etc.). Se apoya en
# `request.state.cliente`, que solo existe si verify_api_key ya corrió y
# aceptó la petición — por eso las peticiones no autenticadas (401, o rutas
# sin Depends(verify_api_key) como /health) no generan una segunda fila aquí.
@app.middleware("http")
async def middleware_auditoria(request: Request, call_next):
    inicio = time.monotonic()
    try:
        response = await call_next(request)
    except Exception:
        # Un fallo NO controlado de un endpoint: se audita (si el cliente ya
        # estaba identificado) y se re-lanza para que lo tome el
        # exception_handler global (respuesta 500 genérica, sin traza).
        cliente = getattr(request.state, "cliente", None)
        if cliente is not None:
            ip = request.client.host if request.client else "desconocida"
            await run_in_threadpool(
                auditoria.registrar, get_engine(),
                accion="RECHAZADO", ip_origen=ip, endpoint=str(request.url.path),
                cliente_id=cliente.id, resultado="ERROR",
                duracion_ms=int((time.monotonic() - inicio) * 1000),
            )
        raise
    cliente = getattr(request.state, "cliente", None)
    if cliente is not None:
        duracion_ms = int((time.monotonic() - inicio) * 1000)
        if response.status_code >= 400:
            accion, resultado = "RECHAZADO", f"HTTP_{response.status_code}"
        elif request.url.path.startswith("/ejecutar"):
            accion, resultado = "EJECUCION", "OK"
        else:
            accion, resultado = "LECTURA", "OK"
        ip = request.client.host if request.client else "desconocida"
        # Best-effort y en threadpool: nunca debe retrasar ni romper la
        # respuesta ya calculada al cliente.
        await run_in_threadpool(
            auditoria.registrar,
            get_engine(),
            accion=accion, ip_origen=ip, endpoint=str(request.url.path),
            cliente_id=cliente.id, resultado=resultado, duracion_ms=duracion_ms,
        )
    return response


# ─── Middleware de hardening HTTP (F8) ───────────────────────────────────────
# Se añade DESPUÉS del de auditoría → queda MÁS EXTERNO (Starlette apila en
# orden inverso): corre primero en la petición (rechaza bodies gigantes antes
# de que nadie los lea) y último en la respuesta (estampa cabeceras sobre lo
# que sea que devolvió el resto de la cadena, incluido un 500).
_CABECERAS_SEGURIDAD = {
    "X-Content-Type-Options": "nosniff",
    "X-Frame-Options": "DENY",
    "Referrer-Policy": "no-referrer",
    "Cache-Control": "no-store",
    "Content-Security-Policy": "default-src 'none'; frame-ancestors 'none'",
}


@app.middleware("http")
async def middleware_hardening(request: Request, call_next):
    request_id = request.headers.get("X-Request-ID") or uuid.uuid4().hex[:16]
    request.state.request_id = request_id

    # Límite de tamaño del body (solo hay body en POST /ejecutar/*).
    cl = request.headers.get("content-length")
    if cl is not None:
        try:
            if int(cl) > settings.MAX_BODY_BYTES:
                return JSONResponse(
                    status_code=413,
                    content={"ok": False, "error": f"cuerpo de la petición supera {settings.MAX_BODY_BYTES} bytes",
                             "request_id": request_id},
                    headers={**_CABECERAS_SEGURIDAD, "X-Request-ID": request_id},
                )
        except ValueError:
            pass

    with logger.contextualize(request_id=request_id):
        response = await call_next(request)

    for k, v in _CABECERAS_SEGURIDAD.items():
        response.headers.setdefault(k, v)
    response.headers["X-Request-ID"] = request_id
    return response


# ─── Handler global de excepciones no controladas ───────────────────────────
# Evita filtrar trazas / detalles internos al cliente. La traza completa va a
# logs (con el request_id para poder cruzarla con la auditoría).
@app.exception_handler(Exception)
async def _error_no_controlado(request: Request, exc: Exception):
    request_id = getattr(request.state, "request_id", "-")
    logger.opt(exception=exc).error("error no controlado [request_id={}] {} {}", request_id, request.method, request.url.path)
    return JSONResponse(
        status_code=500,
        content={"ok": False, "error": "error interno del servidor", "request_id": request_id},
        headers={**_CABECERAS_SEGURIDAD, "X-Request-ID": str(request_id)},
    )


# ─── Registro de routers ─────────────────────────────────────────────────────
# [ROUTER] Para agregar un módulo nuevo: crear router/<modulo>/<modulo>Router.py
#          e incluirlo aquí con su tag. Ver docs/MANUAL_DESARROLLADOR.md.
from router.salud.saludRouter import salud_router

app.include_router(salud_router, tags=["Salud"])

# ─── F5 · Routers de lectura (datos desde BD, sin telnet) ────────────────────
from router.olt.oltRouter import olt_router
from router.alarmas.alarmasRouter import alarmas_router
from router.uplink.uplinkRouter import uplink_router
from router.equipo.equipoRouter import equipo_router
from router.vlan.vlanRouter import vlan_router
from router.pon.ponRouter import pon_router
from router.ont.ontRouter import ont_router

app.include_router(olt_router, tags=["OLT"])
app.include_router(alarmas_router, tags=["Alarmas"])
app.include_router(uplink_router, tags=["Uplink"])
app.include_router(equipo_router, tags=["Equipo"])
app.include_router(vlan_router, tags=["VLAN"])
app.include_router(pon_router, tags=["PON"])
app.include_router(ont_router, tags=["ONT"])

# ─── F6 · Routers de ejecución en vivo + jobs + logs + admin ─────────────────
from router.ejecutar.ejecutarRouter import ejecutar_router
from router.jobs.jobsRouter import jobs_router
from router.logs.logsRouter import logs_router
from router.admin.adminRouter import admin_router

app.include_router(ejecutar_router, tags=["Ejecutar"])
app.include_router(jobs_router, tags=["Jobs"])
app.include_router(logs_router, tags=["Logs"])
app.include_router(admin_router, tags=["Admin"])
