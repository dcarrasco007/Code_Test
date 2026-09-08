# ============================================================================
# package/parsers/vlan_servicios.py
# Consulta: vlan_servicios — comando: 'display vlan all'
# CONFIANZA: MEDIA — tabla Huawei 'VLAN ID / Type / ...'; se reutiliza el
#            mismo patrón que vlan_cantidad. Confirmar en F9.
# ============================================================================

import re

from package.parsers._base import normalizar_lineas

_RE_VLAN = re.compile(r"^\s*(\d{1,4})\s{2,}\S")


def parsear(texto_crudo: str) -> dict:
    vlans = [m.group(1) for linea in normalizar_lineas(texto_crudo) if (m := _RE_VLAN.match(linea))]
    return {"cantidad_total": len(vlans), "vlans": vlans}
