# ============================================================================
# tests/test_worker_ma5800x15.py
# Proyecto: uplink_trafico_15m
# Descripción: Valida la lógica PURA del worker MA5800-X15 (conteo de puertos
#              pto1..pto12 y selección de slots según IP) sin BD ni telnet.
#              La orquestación completa (procesar_olt) requiere BD real y
#              telnet — queda para Fase 9 en producción.
# Ejecutar: python -m tests.test_worker_ma5800x15 (desde la raíz del proyecto).
# ============================================================================

from config.settings import IPS_MA5800X15_MPU_8_9
from scripts.uplink_trafico_15m_ma5800x15.worker import _contar_puertos, _seleccionar_slots


def test_contar_puertos_slot_eth():
    fila = ("0/16/0", "0/16/1", "0/17/0", None, None, None, None, None, None, None, None, None)
    conteo = _contar_puertos(fila)
    assert conteo == {"0/16": 2, "0/17": 1, "0/18": 0, "0/8": 0, "0/9": 0}, conteo


def test_contar_puertos_slot_mpu():
    fila = ("0/8/0", "0/9/0", None, None, None, None, None, None, None, None, None, None)
    conteo = _contar_puertos(fila)
    assert conteo == {"0/16": 0, "0/17": 0, "0/18": 0, "0/8": 1, "0/9": 1}, conteo


def test_contar_puertos_fila_vacia():
    assert _contar_puertos(None) == {"0/16": 0, "0/17": 0, "0/18": 0, "0/8": 0, "0/9": 0}
    assert _contar_puertos([None] * 12) == {"0/16": 0, "0/17": 0, "0/18": 0, "0/8": 0, "0/9": 0}


def test_contar_puertos_ignora_slots_desconocidos():
    fila = ("0/99/0", "0/16/0", None, None, None, None, None, None, None, None, None, None)
    conteo = _contar_puertos(fila)
    assert conteo["0/16"] == 1
    assert sum(conteo.values()) == 1  # '0/99' no se cuenta


def test_seleccionar_slots_ip_especial_usa_mpu():
    ip_especial = next(iter(IPS_MA5800X15_MPU_8_9))
    slots = _seleccionar_slots(ip_especial)
    assert slots == [("0/8", "interface mpu 0/8"), ("0/9", "interface mpu 0/9")], slots


def test_seleccionar_slots_ip_normal_usa_eth():
    slots = _seleccionar_slots("10.1.2.3")  # no está en la lista especial
    assert slots == [
        ("0/16", "interface eth 0/16"),
        ("0/17", "interface eth 0/17"),
        ("0/18", "interface eth 0/18"),
    ], slots


if __name__ == "__main__":
    test_contar_puertos_slot_eth()
    test_contar_puertos_slot_mpu()
    test_contar_puertos_fila_vacia()
    test_contar_puertos_ignora_slots_desconocidos()
    test_seleccionar_slots_ip_especial_usa_mpu()
    test_seleccionar_slots_ip_normal_usa_eth()
    print("tests/test_worker_ma5800x15.py: OK (6/6)")
