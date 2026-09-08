# ============================================================================
# package/parsers/alarmas_activas.py
# Consulta: alarmas_activas — comando: 'display alarm active all'
# CONFIANZA: MEDIA — formato genérico de tabla de alarmas Huawei (ver
#            _base.parsear_tabla_alarmas). Confirmar contra transcripción
#            real en F9 (columnas/anchos pueden variar por firmware).
# ============================================================================

from package.parsers._base import contar_alarmas, parsear_tabla_alarmas


def parsear(texto_crudo: str) -> dict:
    alarmas = parsear_tabla_alarmas(texto_crudo)
    cantidad = contar_alarmas(texto_crudo)
    return {
        "cantidad": cantidad if cantidad is not None else len(alarmas),
        "alarmas": alarmas,
    }
