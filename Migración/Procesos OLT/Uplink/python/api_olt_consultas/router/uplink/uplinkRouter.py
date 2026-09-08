# ============================================================================
# router/uplink/uplinkRouter.py
# Proyecto: api_olt_consultas
# Descripción: Lectura de datos de los puertos de uplink (sin telnet).
#
#   GET /uplink/trafico/{server}          -> OLT_TRAFICO_UPLINK_HORA
#   GET /uplink/potencia-optica/{server}  -> OLT_POTENCIA_OPTICA_UPLINK
#   GET /uplink/state/{server}            -> OLT_UPLINKS_STATE
#
# NOTA: la consulta 'uplink_state' está DESACTIVADA para ejecución en vivo
# (activo=0 en el seed, el PHP original se apagó el 24-06-2025). El endpoint de
# lectura se mantiene: si la tabla tiene datos históricos los devuelve, si no
# responde 200 con dato:null y meta.motivo "sin registros".
#
# Parámetros comunes: ?con_log=true, ?desde=&hasta=. Scope: 'lectura'.
# ============================================================================

from __future__ import annotations

from fastapi import APIRouter, Depends

from utils.func import atender_lectura, cliente_lectura, rango_fechas
from utils.token import Cliente

uplink_router = APIRouter(prefix="/uplink")


@uplink_router.get("/trafico/{server}", summary="Tráfico de uplink por puerto")
def trafico(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("uplink_trafico", server, rango=rango, con_log=con_log)


@uplink_router.get("/potencia-optica/{server}", summary="Potencia óptica (TX/RX) de los puertos de uplink")
def potencia_optica(server: str, con_log: bool = False, rango=Depends(rango_fechas),
                    cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("potencia_optica_uplink", server, rango=rango, con_log=con_log)


@uplink_router.get("/state/{server}", summary="Estado de los puertos de uplink (consulta desactivada, solo histórico)")
def state(server: str, con_log: bool = False, rango=Depends(rango_fechas),
          cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("uplink_state", server, rango=rango, con_log=con_log)
