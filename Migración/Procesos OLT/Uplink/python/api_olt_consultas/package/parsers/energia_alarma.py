# ============================================================================
# package/parsers/energia_alarma.py
# Consulta: energia_alarma — comandos: variantes de 'display alarm active/
#           history alarmlevel major/minor detail' (según modelo)
# CONFIANZA: MEDIA — misma tabla genérica de alarmas, filtrada por nombre
#            relacionado a energía/power. Confirmar palabra clave exacta en F9.
# ============================================================================

from package.parsers._base import parsear_tabla_alarmas

_PALABRAS_ENERGIA = ("power", "energ", "voltage", "pwr")


def parsear(texto_crudo: str) -> dict:
    todas = parsear_tabla_alarmas(texto_crudo)
    energia = [a for a in todas if any(p in a["nombre"].lower() for p in _PALABRAS_ENERGIA)]
    return {"cantidad": len(energia), "alarmas": energia}
