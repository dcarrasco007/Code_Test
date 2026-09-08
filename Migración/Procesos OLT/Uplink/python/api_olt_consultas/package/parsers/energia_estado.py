# ============================================================================
# package/parsers/energia_estado.py
# Consulta: energia_estado — comando: 'display board 0/{slot}' x2, EN VISTA
#           DE USUARIO (sin enable) — la única consulta del catálogo con esa
#           excepción de contexto (ver OLT_API_CONSULTA_COMANDOS.contexto='user').
# CONFIANZA: MEDIA — misma tabla de slots que tarjetas.py (SlotID/BoardName/Status).
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

_RE_BOARD = re.compile(r"^\s*(\d+)\s{2,}(\S+)\s{2,}(\S+)")


def parsear(texto_crudo: str) -> dict:
    slots = []
    for linea in normalizar_lineas(texto_crudo):
        m = _RE_BOARD.match(linea)
        if m:
            slots.append({"slot": m.group(1), "tipo": m.group(2), "estado": m.group(3)})
    return {"slots": slots}
