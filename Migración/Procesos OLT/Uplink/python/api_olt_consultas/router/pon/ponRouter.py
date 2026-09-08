# ============================================================================
# router/pon/ponRouter.py
# Proyecto: api_olt_consultas
# Descripción: Lectura de tráfico de los puertos PON/GPON (sin telnet).
#
#   GET /pon/trafico/{server}  -> OLT_TRAFICOGPON_HORA
#
# OJO: OLT_TRAFICOGPON_HORA NO guarda el nombre de la OLT, solo su IP
# (columna ip_equipo). atender_lectura() resuelve server -> ip vía OLT_SERVER
# antes de filtrar (ver model/lectura/m_lectura_model.valor_filtro).
#
# Parámetros comunes: ?con_log=true, ?desde=&hasta=. Scope: 'lectura'.
# ============================================================================

from __future__ import annotations

from fastapi import APIRouter, Depends

from utils.func import atender_lectura, cliente_lectura, rango_fechas
from utils.token import Cliente

pon_router = APIRouter(prefix="/pon")


@pon_router.get("/trafico/{server}", summary="Tráfico de subida/bajada de los puertos PON (GPON)")
def trafico(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("pon_trafico", server, rango=rango, con_log=con_log)
