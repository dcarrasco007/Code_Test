# ============================================================================
# package/parsers/potencia_optica_uplink.py
# Consulta: potencia_optica_uplink — comando: 'display port ddm-info {puerto}'
# CONFIANZA: MEDIA — el CLI Huawei devuelve un bloque "Etiqueta : valor"
#            (Temperature/Voltage/Bias/RX power/TX power); se usa el parser
#            genérico de pares y se exponen alias en español para las 3
#            lecturas que consume OLT_POTENCIA_OPTICA_UPLINK. Confirmar
#            nombres exactos de etiqueta contra una OLT real en F9.
# ============================================================================

from package.parsers._base import parsear_pares_clave_valor


def _buscar(pares: dict, *nombres_posibles: str):
    for etiqueta, valor in pares.items():
        etiqueta_norm = etiqueta.lower().replace(" ", "")
        for candidato in nombres_posibles:
            if candidato in etiqueta_norm:
                return valor
    return None


def parsear(texto_crudo: str) -> dict:
    pares = parsear_pares_clave_valor(texto_crudo)
    return {
        "temperatura": _buscar(pares, "temperature"),
        "voltaje": _buscar(pares, "voltage"),
        "corriente_bias": _buscar(pares, "bias"),
        "potencia_rx": _buscar(pares, "rxpower", "rx"),
        "potencia_tx": _buscar(pares, "txpower", "tx"),
        "crudo": pares,
    }
