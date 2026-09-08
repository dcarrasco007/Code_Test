# ============================================================================
# package/parsers/tarjetas.py
# Consulta: tarjetas — comandos: 'display power 0' + 'display board 0'
# CONFIANZA: MEDIA — tabla genérica Huawei de slots (columnas separadas por
#            2+ espacios: SlotID / BoardName / Status [/ Subtype]) y de
#            voltaje por unidad de potencia (PowerID / Status / Voltage).
#            Confirmar anchos/columnas exactos en F9.
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

_RE_BOARD = re.compile(r"^\s*(\d+)\s{2,}(\S+)\s{2,}(\S+)")
_RE_POWER = re.compile(r"^\s*(\d+)\s{2,}(\S+)\s{2,}(-?[\d.]+)")


def parsear(texto_crudo: str) -> dict:
    tarjetas = []
    voltajes = []
    for linea in normalizar_lineas(texto_crudo):
        m_board = _RE_BOARD.match(linea)
        if m_board and not m_board.group(3).replace(".", "").replace("-", "").isdigit():
            tarjetas.append({"slot": m_board.group(1), "tipo": m_board.group(2), "estado": m_board.group(3)})
            continue
        m_power = _RE_POWER.match(linea)
        if m_power:
            voltajes.append({"id": m_power.group(1), "estado": m_power.group(2), "voltaje": m_power.group(3)})

    return {
        "cantidad_tarjetas": len(tarjetas),
        "tarjetas": tarjetas,
        "voltajes": voltajes,
    }
