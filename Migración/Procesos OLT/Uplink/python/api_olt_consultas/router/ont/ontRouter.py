# ============================================================================
# router/ont/ontRouter.py
# Proyecto: api_olt_consultas
# Descripción: Lectura de datos de ONT (sin telnet).
#
#   GET /ont/detalle/{server}  -> OLT_INFORMACION_ONT_DETALLE_COMPLETO
#   GET /ont/reporte/{server}  -> OLT_ONT_DETALLE_2
#
# Parámetros comunes: ?con_log=true, ?desde=&hasta=. Scope: 'lectura'.
# ============================================================================

from __future__ import annotations

from fastapi import APIRouter, Depends

from utils.func import atender_lectura, cliente_lectura, rango_fechas
from utils.token import Cliente

ont_router = APIRouter(prefix="/ont")


@ont_router.get("/detalle/{server}", summary="Información detallada de ONTs por puerto GPON")
def detalle(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("ont_detalle", server, rango=rango, con_log=con_log)


@ont_router.get("/reporte/{server}", summary="Conteo de ONTs online/total y versión por puerto")
def reporte(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("ont_reporte", server, rango=rango, con_log=con_log)
