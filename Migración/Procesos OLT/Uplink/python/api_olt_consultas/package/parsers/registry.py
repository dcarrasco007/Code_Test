# ============================================================================
# package/parsers/registry.py
# Proyecto: api_olt_consultas
# Descripción: Mapa codigo de consulta (OLT_API_CONSULTAS.codigo) -> función
#              parsear(texto_crudo) -> dict. Los routers de lectura (F5) y de
#              ejecución (F6) usan esto para convertir el texto crudo que
#              devuelve utils/ejecutor.py en el campo "dato" del contrato de
#              respuesta uniforme.
#
# Para agregar una consulta nueva: crear package/parsers/<codigo>.py con una
# función parsear(texto_crudo) -> dict, e importarla/registrarla aquí.
# ============================================================================

from __future__ import annotations

from typing import Callable, Dict, Optional

from package.parsers import (
    alarmas_activas,
    alarmas_critical_los,
    alarmas_detalle,
    alarmas_los_ont,
    energia_alarma,
    energia_estado,
    fan,
    ont_detalle,
    ont_reporte,
    pon_trafico,
    potencia_optica_uplink,
    tarjetas,
    temperatura_cpu,
    uplink_state,
    uplink_trafico,
    uptime_gpon,
    version,
    vlan_cantidad,
    vlan_servicios,
    vlan_trafico,
)

PARSERS: Dict[str, Callable[[str], dict]] = {
    "uplink_trafico": uplink_trafico.parsear,
    "alarmas_activas": alarmas_activas.parsear,
    "alarmas_detalle": alarmas_detalle.parsear,
    "alarmas_critical_los": alarmas_critical_los.parsear,
    "alarmas_los_ont": alarmas_los_ont.parsear,
    "potencia_optica_uplink": potencia_optica_uplink.parsear,
    "tarjetas": tarjetas.parsear,
    "fan": fan.parsear,
    "vlan_cantidad": vlan_cantidad.parsear,
    "vlan_trafico": vlan_trafico.parsear,
    "vlan_servicios": vlan_servicios.parsear,
    "energia_alarma": energia_alarma.parsear,
    "energia_estado": energia_estado.parsear,
    "pon_trafico": pon_trafico.parsear,
    "temperatura_cpu": temperatura_cpu.parsear,
    "uptime_gpon": uptime_gpon.parsear,
    "uplink_state": uplink_state.parsear,
    "ont_detalle": ont_detalle.parsear,
    "ont_reporte": ont_reporte.parsear,
    "version": version.parsear,
}


def parsear(codigo: str, texto_crudo: str) -> Optional[dict]:
    """None si `codigo` no tiene parser registrado (el router decide qué
    hacer: p.ej. devolver solo el log crudo sin campo 'dato')."""
    funcion = PARSERS.get(codigo)
    if funcion is None:
        return None
    return funcion(texto_crudo)
