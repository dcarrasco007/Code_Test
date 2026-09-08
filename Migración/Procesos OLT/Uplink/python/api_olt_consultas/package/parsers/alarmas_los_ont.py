# ============================================================================
# package/parsers/alarmas_los_ont.py
# Consulta: alarmas_los_ont — comando: 'display alarm active alarmlevel major list'
# CONFIANZA: MEDIA — misma tabla genérica, filtrada a nivel Major (que ya
#            filtra el propio comando) + nombre con 'LOS'.
# ============================================================================

from package.parsers._base import parsear_tabla_alarmas


def parsear(texto_crudo: str) -> dict:
    todas = parsear_tabla_alarmas(texto_crudo)
    los_ont = [a for a in todas if "los" in a["nombre"].lower()]
    return {"cantidad": len(los_ont), "alarmas": los_ont}
