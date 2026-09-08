# ============================================================================
# package/parsers/ont_detalle.py
# Consulta: ont_detalle (V4) — comandos: 'display ont version 0 all' +
#           por slot PON: 'display ont info summary {puerto}' /
#           'display ont optical-info {puerto} all' / 'display ont profile {puerto} all'
# CONFIANZA: BAJA/MEDIA — 'info summary' suele traer una línea resumen tipo
#            "the total of ONTs are: N, online: M"; 'optical-info' una fila
#            por ONT con su potencia RX/TX. Confirmar contra una OLT real en
#            F9 (es el comando más complejo de las 20 consultas).
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

_RE_RESUMEN = re.compile(r"total.*ONTs?\s+are\s*:?\s*(\d+).*online\s*:?\s*(\d+)", re.IGNORECASE)
_RE_OPTICA = re.compile(r"^\s*(\d+)\s{2,}(-?[\d.]+)\s{2,}(-?[\d.]+)")


def parsear(texto_crudo: str) -> dict:
    lineas = normalizar_lineas(texto_crudo)
    total = online = None
    opticas = []
    for linea in lineas:
        m_resumen = _RE_RESUMEN.search(linea)
        if m_resumen:
            total, online = int(m_resumen.group(1)), int(m_resumen.group(2))
            continue
        m_optica = _RE_OPTICA.match(linea)
        if m_optica:
            opticas.append({"ont": m_optica.group(1), "potencia_rx": m_optica.group(2), "potencia_tx": m_optica.group(3)})

    return {
        "total_onts": total,
        "onts_online": online,
        "potencia_optica_por_ont": opticas,
    }
