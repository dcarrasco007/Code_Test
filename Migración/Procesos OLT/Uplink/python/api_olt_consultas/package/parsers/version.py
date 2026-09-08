# ============================================================================
# package/parsers/version.py
# Consulta: version — comando: 'display version'
# CONFIANZA: MEDIA — bloque "Etiqueta : valor" (PRODUCT NAME / MAIN BOARD
#            VERSION / SOFTWARE VERSION / PATCH VERSION, etc.) — se usa el
#            parser genérico y se exponen alias para los 3 campos que
#            consume OLT_VERSION_PARCHE_MODELO. Confirmar etiquetas exactas
#            en F9 (varían algo entre MA5600T/MA5800-X15).
# ============================================================================

from package.parsers._base import parsear_pares_clave_valor


def _buscar(pares: dict, *fragmentos: str):
    for etiqueta, valor in pares.items():
        etiqueta_norm = etiqueta.lower()
        if all(f in etiqueta_norm for f in fragmentos):
            return valor
    return None


def parsear(texto_crudo: str) -> dict:
    pares = parsear_pares_clave_valor(texto_crudo)
    return {
        "producto": _buscar(pares, "product"),
        "version_software": _buscar(pares, "software", "version"),
        "version_patch": _buscar(pares, "patch"),
        "pares": pares,
    }
