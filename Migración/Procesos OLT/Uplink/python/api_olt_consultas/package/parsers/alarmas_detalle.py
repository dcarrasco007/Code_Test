# ============================================================================
# package/parsers/alarmas_detalle.py
# Consulta: alarmas_detalle — comando: 'display alarm active all', clasificado
#           por nivel (CRITICAL/MAJOR/MINOR/WARNING) — igual tabla que
#           OLT_ALARMAS_{CRITICAL,MAJOR,MINOR,WARNING} en el análisis de origen.
# CONFIANZA: MEDIA (misma tabla genérica que alarmas_activas.py).
# ============================================================================

from package.parsers._base import parsear_tabla_alarmas


def parsear(texto_crudo: str) -> dict:
    alarmas = parsear_tabla_alarmas(texto_crudo)
    por_nivel = {"Critical": [], "Major": [], "Minor": [], "Warning": []}
    for alarma in alarmas:
        por_nivel.setdefault(alarma["nivel"], []).append(alarma)
    return {
        "cantidad_total": len(alarmas),
        "critical": por_nivel["Critical"],
        "major": por_nivel["Major"],
        "minor": por_nivel["Minor"],
        "warning": por_nivel["Warning"],
    }
