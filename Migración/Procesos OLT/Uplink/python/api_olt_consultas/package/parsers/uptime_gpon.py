# ============================================================================
# package/parsers/uptime_gpon.py
# Consulta: uptime_gpon — comando: 'display port state 0' bajo
#           'interface gpon 0/{slot}' (repetido por slot 0..16)
# CONFIANZA: MEDIA — tabla Huawei de estado de puerto: 'PortID  Status  ...'
#            con Status ∈ {Up, Down, ...}. Confirmar columnas en F9. El
#            "uptime" real (tiempo desde el último Up) NO lo entrega este
#            comando por sí solo en la mayoría de firmwares — se deja
#            constancia del estado, que es la señal que el proceso PHP de
#            origen usaba para decidir si "subió" o "bajó" el puerto.
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

_RE_ESTADO = re.compile(r"^\s*(\d+)\s{2,}(Up|Down)\b", re.IGNORECASE)


def parsear(texto_crudo: str) -> dict:
    puertos = []
    for linea in normalizar_lineas(texto_crudo):
        m = _RE_ESTADO.match(linea)
        if m:
            puertos.append({"puerto": m.group(1), "estado": m.group(2).capitalize()})
    return {"puertos": puertos}
