# ============================================================================
# router/vlan/vlanRouter.py
# Proyecto: api_olt_consultas
# Descripción: Lectura de datos de VLAN (sin telnet).
#
#   GET /vlan/cantidad/{server}   -> OLT_CANTIDAD_VLAN
#   GET /vlan/trafico/{server}    -> OLT_VLAN_TRAFICO
#   GET /vlan/servicios/{server}  -> OLT_VLAN_SERVICIO_CANTIDAD
#
# Parámetros comunes: ?con_log=true, ?desde=&hasta=. Scope: 'lectura'.
# ============================================================================

from __future__ import annotations

from fastapi import APIRouter, Depends

from utils.func import atender_lectura, cliente_lectura, rango_fechas
from utils.token import Cliente

vlan_router = APIRouter(prefix="/vlan")


@vlan_router.get("/cantidad/{server}", summary="Cantidad de VLAN configuradas por puerta")
def cantidad(server: str, con_log: bool = False, rango=Depends(rango_fechas),
             cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("vlan_cantidad", server, rango=rango, con_log=con_log)


@vlan_router.get("/trafico/{server}", summary="Tráfico de subida/bajada por VLAN de servicio")
def trafico(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("vlan_trafico", server, rango=rango, con_log=con_log)


@vlan_router.get("/servicios/{server}", summary="VLAN configuradas y cantidad de clientes")
def servicios(server: str, con_log: bool = False, rango=Depends(rango_fechas),
              cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("vlan_servicios", server, rango=rango, con_log=con_log)
