# ============================================================================
# package/parsers/vlan_trafico.py
# Consulta: vlan_trafico — comando: 'display traffic vlan {vlan}' (repetido
#           por cada VLAN de OLT_VLAN_SERVICIOS)
# CONFIANZA: BAJA — no hay una transcripción de origen disponible para este
#            comando en el análisis; se usa el parser genérico de pares
#            "Etiqueta : valor" como mejor esfuerzo. CONFIRMAR EN F9 antes de
#            depender de este parser en producción (ver SOLICITUDES_PENDIENTES.txt).
# ============================================================================

from package.parsers._base import parsear_pares_clave_valor


def parsear(texto_crudo: str) -> dict:
    return {"pares": parsear_pares_clave_valor(texto_crudo)}
