# ============================================================================
# package/parsers/vlan_cantidad.py
# Consulta: vlan_cantidad — comando: 'display port vlan {puerta}'
# CONFIANZA: MEDIA — tabla Huawei 'VLAN ID / VLAN type / ...'; se cuenta una
#            fila por VLAN listada (primer token numérico de cada línea de
#            datos). Confirmar en F9.
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

_RE_VLAN = re.compile(r"^\s*(\d{1,4})\s{2,}\S")


def parsear(texto_crudo: str) -> dict:
    vlans = [m.group(1) for linea in normalizar_lineas(texto_crudo) if (m := _RE_VLAN.match(linea))]
    return {"cantidad": len(vlans), "vlans": vlans}
