# ============================================================================
# router/admin/adminRouter.py
# Proyecto: api_olt_consultas
# Descripción: Endpoints de administración / observabilidad (F6).
#              Ver CASOS_DE_USO.md CU-05 / CU-08.
#
#   GET /admin/consultas       catálogo de OLT_API_CONSULTAS (+ nº de comandos)
#   GET /admin/auditoria       filas de OLT_API_AUDITORIA (filtros)
#   GET /admin/sesiones        sesiones telnet ABIERTAS ahora + cupo efectivo
#   GET /admin/clientes/me     ficha del cliente que llama (sin el hash)
#
# Scope: 'admin' en todos.
# ============================================================================

from __future__ import annotations

from typing import Optional

from fastapi import APIRouter, Depends, HTTPException, Query

from app.config import settings
from app.config.db import get_engine
from model.admin import m_admin_model
from utils.func import rango_fechas, serializar_fila
from utils.token import Cliente, requerir_scopes

admin_router = APIRouter(prefix="/admin")


@admin_router.get("/consultas", summary="Catálogo de consultas soportadas")
def consultas(cliente: Cliente = Depends(requerir_scopes("admin"))):
    filas = m_admin_model.listar_consultas(get_engine())
    return {"ok": True, "cantidad": len(filas), "consultas": [serializar_fila(f, ocultar=()) for f in filas]}


@admin_router.get("/auditoria", summary="Auditoría de peticiones")
def auditoria(
    accion: Optional[str] = Query(None, description="AUTH_OK|AUTH_FAIL|LECTURA|EJECUCION|RECHAZADO"),
    cliente_id: Optional[int] = None,
    server: Optional[str] = None,
    limite: int = Query(100, ge=1, le=500),
    rango=Depends(rango_fechas),
    cliente: Cliente = Depends(requerir_scopes("admin")),
):
    desde, hasta = (rango or (None, None))
    filas = m_admin_model.listar_auditoria(
        get_engine(), accion=accion, cliente_id=cliente_id, olt=server,
        desde=desde, hasta=hasta, limite=limite,
    )
    return {"ok": True, "cantidad": len(filas), "auditoria": [serializar_fila(f, ocultar=()) for f in filas]}


@admin_router.get("/sesiones", summary="Sesiones telnet abiertas ahora + cupo efectivo")
def sesiones(cliente: Cliente = Depends(requerir_scopes("admin"))):
    filas = m_admin_model.sesiones_abiertas(get_engine())
    return {
        "ok": True,
        "cupo": {
            "usuario_dedicado": settings.OLT_TELNET_API_DEDICADO,
            "por_olt_usuario": settings.cupo_por_olt_usuario(),
            "limite_duro_equipo": settings.LIMITE_DURO_SESIONES_OLT,
            "max_concurrentes_global": settings.TELNET_MAX_CONCURRENTES,
        },
        "abiertas": len(filas),
        "sesiones": [serializar_fila(f, ocultar=()) for f in filas],
    }


@admin_router.get("/clientes/me", summary="Ficha del cliente autenticado")
def cliente_me(cliente: Cliente = Depends(requerir_scopes("admin"))):
    fila = m_admin_model.cliente_por_id(get_engine(), cliente.id)
    if fila is None:
        raise HTTPException(status_code=404, detail="el cliente autenticado no está en OLT_API_CLIENTES.")
    return {"ok": True, "cliente": serializar_fila(fila, ocultar=())}
