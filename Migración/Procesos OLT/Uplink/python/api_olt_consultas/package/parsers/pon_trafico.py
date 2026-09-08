# ============================================================================
# package/parsers/pon_trafico.py
# Consulta: pon_trafico — comando: 'display port traffic {0..15}' bajo
#           'interface gpon 0/{slot}' — MISMO comando/formato que uplink_trafico.
# CONFIANZA: ALTA — reutiliza el parser ya validado de uplink_trafico.py
#            (mismo marcador de línea 'display port traffic').
# ============================================================================

from package.parsers.uplink_trafico import parsear as _parsear_trafico


def parsear(texto_crudo: str) -> dict:
    return _parsear_trafico(texto_crudo)
