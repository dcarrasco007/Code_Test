# ============================================================================
# router/alarmas/alarmasRouter.py
# Proyecto: api_olt_consultas
# Descripción: Lectura de alarmas ya recolectadas por los crons (sin telnet).
#
#   GET /alarmas/activas/{server}       -> OLT_ALARMAS
#   GET /alarmas/detalle/{server}       -> OLT_CANTIDAD_ALARMAS_DETALLE
#   GET /alarmas/critical-los/{server}  -> OLT_ALARMA_CRITICAL_LOS
#   GET /alarmas/los-ont/{server}       -> OLT_ALARMA_LOS_ONT
#
# Parámetros comunes (todos los GET de lectura):
#   ?con_log=true      adjunta el último log crudo de telnet de esa consulta/OLT
#   ?desde=&hasta=     serie histórica en vez del último dato (solo consultas
#                      cuya columna de fecha es DATETIME; si no, 422)
# Scope requerido: 'lectura'.
# ============================================================================

from __future__ import annotations

from fastapi import APIRouter, Depends

from utils.func import atender_lectura, cliente_lectura, rango_fechas
from utils.token import Cliente

alarmas_router = APIRouter(prefix="/alarmas")


@alarmas_router.get("/activas/{server}", summary="Alarmas activas de la OLT")
def activas(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("alarmas_activas", server, rango=rango, con_log=con_log)


@alarmas_router.get("/detalle/{server}", summary="Alarmas activas clasificadas por severidad")
def detalle(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("alarmas_detalle", server, rango=rango, con_log=con_log)


@alarmas_router.get("/critical-los/{server}", summary="Alarmas críticas activas y su histórico de recuperación")
def critical_los(server: str, con_log: bool = False, rango=Depends(rango_fechas),
                 cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("alarmas_critical_los", server, rango=rango, con_log=con_log)


@alarmas_router.get("/los-ont/{server}", summary="ONTs con pérdida de señal óptica (LOS)")
def los_ont(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("alarmas_los_ont", server, rango=rango, con_log=con_log)
