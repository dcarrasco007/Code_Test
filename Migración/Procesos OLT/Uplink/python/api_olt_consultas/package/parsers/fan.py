# ============================================================================
# package/parsers/fan.py
# Consulta: fan — comando: 'display emu 0'
# CONFIANZA: MEDIA — filas tipo 'FAN 0  Normal' / 'FAN  1  Fault'.
#            Confirmar formato exacto en F9.
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

_RE_FAN = re.compile(r"fan\s*(\d+)\D+(Normal|Fault|Abnormal|Absent)", re.IGNORECASE)


def parsear(texto_crudo: str) -> dict:
    fans = []
    for linea in normalizar_lineas(texto_crudo):
        m = _RE_FAN.search(linea)
        if m:
            fans.append({"fan": m.group(1), "estado": m.group(2).capitalize()})
    ok = all(f["estado"].lower() == "normal" for f in fans) if fans else None
    return {"fans": fans, "todos_normales": ok}
