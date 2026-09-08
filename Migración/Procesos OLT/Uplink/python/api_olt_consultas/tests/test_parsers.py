# ============================================================================
# tests/test_parsers.py
# Proyecto: api_olt_consultas
# Descripción: Los 20 parsers de package/parsers/, contra transcripciones
#              SINTÉTICAS (no de un equipo real — ver el aviso de confianza
#              en cada archivo de package/parsers/ y en PLAN_API_OLT.md F4:
#              "tests con transcripciones sintéticas ahora, reales en F9").
#              Prueban que cada parser extrae lo que SÍ está en el texto y
#              no revienta con texto vacío/inesperado — no certifican
#              fidelidad byte a byte contra el CLI real de una OLT.
#              Ejecutar: python -m tests.test_parsers (desde la raíz).
# ============================================================================

from package.parsers import (
    alarmas_activas, alarmas_critical_los, alarmas_detalle, alarmas_los_ont,
    energia_alarma, energia_estado, fan, ont_detalle, ont_reporte, pon_trafico,
    potencia_optica_uplink, tarjetas, temperatura_cpu, uplink_state,
    uplink_trafico, uptime_gpon, version, vlan_cantidad, vlan_servicios,
    vlan_trafico,
)
from package.parsers.registry import PARSERS, parsear

# ─── Transcripciones sintéticas ─────────────────────────────────────────────

_TRAFICO = """
The received traffic of this port(kbits/s) = 1234
line2
line3
line4
line5
line6
line7
The transmitted traffic of this port(kbits/s) = 5678
"""

_ALARMAS = """
Sequence  AlarmName                Level      Location
1         Power failure detected  Critical   0/1
2         LOS alarm on ONT         Major      0/2/3
3         Fan speed low            Minor      0/0
Number of active alarms: 3
"""

_DDM_INFO = """
  Temperature(C)     : 35.00
  Voltage(V)          : 3.30
  Bias(mA)            : 6.00
  RX power(dBm)       : -3.20
  TX power(dBm)       : -2.10
"""

_BOARD = """
SlotID  BoardName   Status
0       H901MPUB    Normal
1       H902GPBD    Normal
"""

_POWER = """
PowerID  Status   Voltage
0        Normal   53.5
1        Normal   53.6
"""

_FAN = """
FAN 0  Normal
FAN 1  Fault
"""

_VLAN_TABLE = """
VLAN ID  VLAN Type  Attribute
100      smart      tag
200      smart      tag
"""

_PORT_STATE = """
PortID  Status
0       Up
1       Down
"""

_ONT_SUMMARY_Y_OPTICA = """
In port 0/1/0, the total of ONTs are: 32, online: 30
1   -3.20   2.10
2   -4.10   2.20
"""

_ONT_REPORTE = """
F/S/P   ONTID  SN          Ctrl  RunState
0/1/0   1      HWTC1234    ---   Online
0/1/0   2      HWTC5678    ---   Offline
"""

_VERSION = """
PRODUCT NAME       : MA5800-X15
MAIN BOARD VERSION : H901MPUB
SOFTWARE VERSION   : V800R019
PATCH VERSION      : PATCH001
"""


def test_uplink_trafico():
    r = uplink_trafico.parsear(_TRAFICO)
    assert r["lecturas"] == [{"bajada": "1234", "subida": "5678"}]


def test_pon_trafico_reusa_uplink_trafico():
    assert pon_trafico.parsear(_TRAFICO) == uplink_trafico.parsear(_TRAFICO)


def test_alarmas_activas():
    r = alarmas_activas.parsear(_ALARMAS)
    assert r["cantidad"] == 3
    assert len(r["alarmas"]) == 3
    assert r["alarmas"][0]["nivel"] == "Critical"


def test_alarmas_detalle_clasifica_por_nivel():
    r = alarmas_detalle.parsear(_ALARMAS)
    assert r["cantidad_total"] == 3
    assert len(r["critical"]) == 1
    assert len(r["major"]) == 1
    assert len(r["minor"]) == 1
    assert r["warning"] == []


def test_alarmas_critical_los_filtra_por_nombre():
    r = alarmas_critical_los.parsear(_ALARMAS)
    assert r["cantidad"] == 1
    assert "LOS" in r["alarmas_los"][0]["nombre"]


def test_alarmas_los_ont():
    r = alarmas_los_ont.parsear(_ALARMAS)
    assert r["cantidad"] == 1


def test_potencia_optica_uplink():
    r = potencia_optica_uplink.parsear(_DDM_INFO)
    assert r["temperatura"] == "35.00"
    assert r["potencia_rx"] == "-3.20"
    assert r["potencia_tx"] == "-2.10"


def test_tarjetas():
    r = tarjetas.parsear(_BOARD + _POWER)
    assert r["cantidad_tarjetas"] == 2
    assert r["tarjetas"][0]["tipo"] == "H901MPUB"
    assert len(r["voltajes"]) == 2


def test_fan():
    r = fan.parsear(_FAN)
    assert len(r["fans"]) == 2
    assert r["todos_normales"] is False


def test_fan_todos_normales():
    r = fan.parsear("FAN 0  Normal\nFAN 1  Normal\n")
    assert r["todos_normales"] is True


def test_vlan_cantidad():
    r = vlan_cantidad.parsear(_VLAN_TABLE)
    assert r["cantidad"] == 2
    assert r["vlans"] == ["100", "200"]


def test_vlan_servicios():
    r = vlan_servicios.parsear(_VLAN_TABLE)
    assert r["cantidad_total"] == 2


def test_vlan_trafico_no_revienta_con_texto_arbitrario():
    r = vlan_trafico.parsear("Traffic(bps) : 1000\n")
    assert r["pares"].get("Traffic(bps)") == "1000"


def test_energia_alarma_filtra_por_palabra_clave():
    r = energia_alarma.parsear(_ALARMAS)
    assert r["cantidad"] == 1
    assert "Power" in r["alarmas"][0]["nombre"]


def test_energia_estado():
    r = energia_estado.parsear(_BOARD)
    assert len(r["slots"]) == 2


def test_temperatura_cpu():
    r = temperatura_cpu.parsear("Temperature(C) : 42\nCPU usage : 15%\n")
    assert r["temperatura"] == "42"
    assert r["uso_cpu"] == "15%"


def test_uptime_gpon():
    r = uptime_gpon.parsear(_PORT_STATE)
    assert r["puertos"] == [{"puerto": "0", "estado": "Up"}, {"puerto": "1", "estado": "Down"}]


def test_uplink_state_combina_estado_y_ddm():
    r = uplink_state.parsear(_PORT_STATE + _DDM_INFO)
    assert len(r["puertos"]) == 2
    assert r["ddm_info"]["potencia_rx"] == "-3.20"


def test_ont_detalle():
    r = ont_detalle.parsear(_ONT_SUMMARY_Y_OPTICA)
    assert r["total_onts"] == 32
    assert r["onts_online"] == 30
    assert len(r["potencia_optica_por_ont"]) == 2


def test_ont_reporte():
    r = ont_reporte.parsear(_ONT_REPORTE)
    assert r["cantidad_total"] == 2
    assert r["cantidad_online"] == 1
    assert r["onts"][0]["serial"] == "HWTC1234"


def test_version():
    r = version.parsear(_VERSION)
    assert r["producto"] == "MA5800-X15"
    assert r["version_software"] == "V800R019"
    assert r["version_patch"] == "PATCH001"


def test_ninguno_de_los_20_revienta_con_texto_vacio_o_basura():
    """Ningún parser debe lanzar excepción ante entradas degeneradas — debe
    devolver campos vacíos/None, nunca reventar la respuesta del router."""
    for codigo, funcion in PARSERS.items():
        for entrada in ("", "   \n\n  ", "texto sin ningun formato reconocible\r\ncon varias lineas\r\n"):
            resultado = funcion(entrada)
            assert isinstance(resultado, dict), f"{codigo} no devolvió un dict para {entrada!r}"


def test_registry_tiene_las_20_consultas_del_seed():
    esperadas = {
        "uplink_trafico", "alarmas_activas", "alarmas_detalle", "alarmas_critical_los",
        "alarmas_los_ont", "potencia_optica_uplink", "tarjetas", "fan", "vlan_cantidad",
        "vlan_trafico", "vlan_servicios", "energia_alarma", "energia_estado", "pon_trafico",
        "temperatura_cpu", "uptime_gpon", "uplink_state", "ont_detalle", "ont_reporte", "version",
    }
    assert set(PARSERS.keys()) == esperadas
    assert len(esperadas) == 20


def test_registry_parsear_devuelve_none_si_no_existe():
    assert parsear("codigo_inexistente", "texto") is None
    assert parsear("version", _VERSION)["producto"] == "MA5800-X15"


if __name__ == "__main__":
    tests = [
        test_uplink_trafico,
        test_pon_trafico_reusa_uplink_trafico,
        test_alarmas_activas,
        test_alarmas_detalle_clasifica_por_nivel,
        test_alarmas_critical_los_filtra_por_nombre,
        test_alarmas_los_ont,
        test_potencia_optica_uplink,
        test_tarjetas,
        test_fan,
        test_fan_todos_normales,
        test_vlan_cantidad,
        test_vlan_servicios,
        test_vlan_trafico_no_revienta_con_texto_arbitrario,
        test_energia_alarma_filtra_por_palabra_clave,
        test_energia_estado,
        test_temperatura_cpu,
        test_uptime_gpon,
        test_uplink_state_combina_estado_y_ddm,
        test_ont_detalle,
        test_ont_reporte,
        test_version,
        test_ninguno_de_los_20_revienta_con_texto_vacio_o_basura,
        test_registry_tiene_las_20_consultas_del_seed,
        test_registry_parsear_devuelve_none_si_no_existe,
    ]
    for t in tests:
        t()
    print(f"tests/test_parsers.py: OK ({len(tests)}/{len(tests)})")
