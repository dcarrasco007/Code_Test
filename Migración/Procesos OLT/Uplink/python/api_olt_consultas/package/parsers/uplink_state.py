# ============================================================================
# package/parsers/uplink_state.py
# Consulta: uplink_state — DESACTIVADA (activo=0 en el seed: el propio PHP de
#           origen hace 'die("Script Eliminado 24-06-2025")'). Se deja el
#           parser implementado por completitud del catálogo, no porque
#           haya un consumidor activo hoy.
# CONFIANZA: BAJA — combina 'display port state all' (estado por puerto,
#            igual formato que uptime_gpon.py) con 'display port ddm-info
#            0/1' (potencia óptica, igual formato que potencia_optica_uplink.py).
# ============================================================================

from package.parsers.potencia_optica_uplink import parsear as _parsear_ddm
from package.parsers.uptime_gpon import parsear as _parsear_estado


def parsear(texto_crudo: str) -> dict:
    estado = _parsear_estado(texto_crudo)
    ddm = _parsear_ddm(texto_crudo)
    return {"puertos": estado["puertos"], "ddm_info": ddm}
