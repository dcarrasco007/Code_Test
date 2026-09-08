# ============================================================================
# package/parsers/ont_reporte.py
# Consulta: ont_reporte — comandos: 'display ont version 0 all' +
#           'display ont info 0 all'
# CONFIANZA: MEDIA — tabla Huawei por ONT: F/S/P (posición) / ONTID / SN /
#            Control-flag / Run-state / .... Se extraen ONTID, serial y
#            estado de línea (Online/Offline) con un regex laxo — confirmar
#            columnas exactas en F9.
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

# Fila típica: 'F/S/P   ONTID  SN          Ctrl  RunState' — la posición
# F/S/P (ej. '0/1/0') va primero, luego el ONTID numérico, el serial, y en
# algún punto de la línea el estado Online/Offline.
_RE_ONT = re.compile(
    r"^\s*\S+\s{2,}(\d+)\s{2,}(\S+)\s{2,}\S+\s{2,}(Online|Offline)\b", re.IGNORECASE
)


def parsear(texto_crudo: str) -> dict:
    onts = []
    for linea in normalizar_lineas(texto_crudo):
        m = _RE_ONT.match(linea)
        if m:
            onts.append({"ont": m.group(1), "serial": m.group(2), "estado": m.group(3).capitalize()})
    online = sum(1 for o in onts if o["estado"].lower() == "online")
    return {"cantidad_total": len(onts), "cantidad_online": online, "onts": onts}
