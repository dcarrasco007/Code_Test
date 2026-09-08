# ============================================================================
# package/parsers/alarmas_critical_los.py
# Consulta: alarmas_critical_los — comandos:
#   'display alarm active alarmlevel critical' +
#   'display alarm history alarmlevel cleared alarmclass recovery detail' (X15)
#   / '... alarmclass recovery alarmlevel critical detail' (resto)
# CONFIANZA: MEDIA — misma tabla genérica; filtra por nombre que contenga
#            'LOS' (Loss of Signal), criterio del propio nombre de la
#            consulta. Confirmar en F9 si el CLI usa otro texto (ej. 'Los').
# ============================================================================

from package.parsers._base import parsear_tabla_alarmas


def parsear(texto_crudo: str) -> dict:
    todas = parsear_tabla_alarmas(texto_crudo)
    los = [a for a in todas if "los" in a["nombre"].lower()]
    return {"cantidad": len(los), "alarmas_los": los, "cantidad_total_critical": len(todas)}
