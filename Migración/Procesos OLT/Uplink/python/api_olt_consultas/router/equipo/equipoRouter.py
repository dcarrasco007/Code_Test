# ============================================================================
# router/equipo/equipoRouter.py
# Proyecto: api_olt_consultas
# Descripción: Lectura de datos del equipo (chasis, energía, térmica, versión).
#
#   GET /equipo/tarjetas/{server}         -> OLT_DETALLE_TARJETA
#   GET /equipo/fan/{server}              -> OLT_FAN_ESTADO
#   GET /equipo/energia/alarma/{server}   -> OLT_ALARMA_ENERGIA
#   GET /equipo/energia/estado/{server}   -> OLT_ESTADO_ENERGIA
#   GET /equipo/temperatura-cpu/{server}  -> OLT_TEMP_CPU
#   GET /equipo/version/{server}          -> OLT_VERSION_PARCHE_MODELO
#   GET /equipo/uptime/{server}           -> OLT_UPTIME
#
# Parámetros comunes: ?con_log=true, ?desde=&hasta=. Scope: 'lectura'.
# ============================================================================

from __future__ import annotations

from fastapi import APIRouter, Depends

from utils.func import atender_lectura, cliente_lectura, rango_fechas
from utils.token import Cliente

equipo_router = APIRouter(prefix="/equipo")


@equipo_router.get("/tarjetas/{server}", summary="Voltaje y tipo de tarjetas instaladas")
def tarjetas(server: str, con_log: bool = False, rango=Depends(rango_fechas),
             cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("tarjetas", server, rango=rango, con_log=con_log)


@equipo_router.get("/fan/{server}", summary="Estado de los ventiladores (EMU/FAN)")
def fan(server: str, con_log: bool = False, rango=Depends(rango_fechas),
        cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("fan", server, rango=rango, con_log=con_log)


@equipo_router.get("/energia/alarma/{server}", summary="Alarmas de energía / fuente de poder")
def energia_alarma(server: str, con_log: bool = False, rango=Depends(rango_fechas),
                   cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("energia_alarma", server, rango=rango, con_log=con_log)


@equipo_router.get("/energia/estado/{server}", summary="Estado de las tarjetas de energía (board/power)")
def energia_estado(server: str, con_log: bool = False, rango=Depends(rango_fechas),
                   cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("energia_estado", server, rango=rango, con_log=con_log)


@equipo_router.get("/temperatura-cpu/{server}", summary="Temperatura y uso de CPU por slot")
def temperatura_cpu(server: str, con_log: bool = False, rango=Depends(rango_fechas),
                    cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("temperatura_cpu", server, rango=rango, con_log=con_log)


@equipo_router.get("/version/{server}", summary="Versión de software, parche y modelo del equipo")
def version(server: str, con_log: bool = False, rango=Depends(rango_fechas),
            cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("version", server, rango=rango, con_log=con_log)


@equipo_router.get("/uptime/{server}", summary="Uptime / última caída de los puertos GPON")
def uptime(server: str, con_log: bool = False, rango=Depends(rango_fechas),
           cliente: Cliente = Depends(cliente_lectura)):
    return atender_lectura("uptime_gpon", server, rango=rango, con_log=con_log)
