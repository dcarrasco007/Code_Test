# ============================================================================
# router/logs/logsRouter.py
# Proyecto: api_olt_consultas
# Descripción: Consulta del log crudo de telnet ya persistido (F6). Uso
#              típico: un operador reporta un dato raro → se busca el log de
#              esa consulta/OLT y se compara con lo parseado (CASOS_DE_USO.md
#              CU-05).
#
#   GET /logs/telnet          lista (sin el texto crudo, que puede pesar MB)
#   GET /logs/telnet/{id}     una fila con el log_crudo completo
#
# Scope: 'admin' (el log crudo puede contener información sensible del equipo).
# ============================================================================

from __future__ import annotations

from typing import Optional

from fastapi import APIRouter, Depends, HTTPException

from app.config.db import get_engine
from model.logs import m_logs_model
from utils.func import rango_fechas, serializar_fila
from utils.token import Cliente, requerir_scopes

logs_router = APIRouter(prefix="/logs")


@logs_router.get("/telnet", summary="Historial de sesiones telnet (sin el texto crudo)")
def listar_logs(
    server: Optional[str] = None,
    consulta: Optional[str] = None,
    exito: Optional[bool] = None,
    limite: int = 20,
    rango=Depends(rango_fechas),
    cliente: Cliente = Depends(requerir_scopes("admin")),
):
    desde, hasta = (rango or (None, None))
    filas = m_logs_model.buscar(
        get_engine(), olt=server, consulta=consulta, desde=desde, hasta=hasta,
        exito=exito, limite=limite,
    )
    return {"ok": True, "cantidad": len(filas), "logs": [serializar_fila(f, ocultar=()) for f in filas]}


@logs_router.get("/telnet/{log_id}", summary="Una sesión telnet con el log crudo completo")
def obtener_log(log_id: int, cliente: Cliente = Depends(requerir_scopes("admin"))):
    fila = m_logs_model.obtener_log(get_engine(), log_id)
    if fila is None:
        raise HTTPException(status_code=404, detail=f"log #{log_id} no encontrado.")
    return {"ok": True, "log": serializar_fila(fila, ocultar=())}
