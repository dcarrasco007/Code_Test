# ============================================================================
# package/parsers/uplink_trafico.py
# Consulta: uplink_trafico — comando: 'display port traffic {puerto}'
# CONFIANZA: ALTA — port directo de la lógica ya validada en
#            uplink_trafico_15m/utils/parser_trafico.py (mismo comando,
#            mismo marcador de línea, mismo offset fijo +7 hacia la línea de
#            subida). Ver ese archivo para el detalle PHP-a-Python original.
# ============================================================================

from package.parsers._base import normalizar_lineas, quitar_espacios

MARCADOR_BAJADA = "Thereceivedtrafficofthisport(kbits/s)="
OFFSET_LINEA_SUBIDA = 7


def parsear(texto_crudo: str) -> dict:
    """Devuelve {"lecturas": [{"bajada": "123", "subida": "45"}, ...]} en el
    mismo orden en que aparecen en el texto — el llamador (ejecutor/router)
    es quien sabe a qué puerto corresponde cada elemento, por el orden en
    que se emitieron los comandos (igual que en uplink_trafico_15m)."""
    lineas = normalizar_lineas(texto_crudo)
    lecturas = []

    for j, linea in enumerate(lineas):
        linea_sin_espacios = quitar_espacios(linea)
        if MARCADOR_BAJADA not in linea_sin_espacios:
            continue

        pos_igual = linea_sin_espacios.find("=")
        bajada = linea_sin_espacios[pos_igual:].strip().split("=")[1]

        subida = None
        j_subida = j + OFFSET_LINEA_SUBIDA
        if j_subida < len(lineas):
            linea_subida = quitar_espacios(lineas[j_subida])
            if "=" in linea_subida:
                subida = linea_subida.split("=")[1]

        lecturas.append({"bajada": bajada, "subida": subida})

    return {"lecturas": lecturas}
