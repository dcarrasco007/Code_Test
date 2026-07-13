# ============================================================================
# tests/test_ping.py
# Proyecto: uplink_trafico_15m
# Descripción: Valida _extraer_packet_loss() contra muestras de salida REAL de
#              'ping -c N' en Linux (formato estándar, no depende del hardware
#              OLT). Ejecutar: python -m tests.test_ping (desde la raíz del proyecto).
# ============================================================================

from utils.ping import _extraer_packet_loss

MUESTRA_0_PORCIENTO = """PING 10.99.30.74 (10.99.30.74) 56(84) bytes of data.
64 bytes from 10.99.30.74: icmp_seq=1 ttl=64 time=0.045 ms
64 bytes from 10.99.30.74: icmp_seq=2 ttl=64 time=0.048 ms
64 bytes from 10.99.30.74: icmp_seq=3 ttl=64 time=0.041 ms

--- 10.99.30.74 ping statistics ---
3 packets transmitted, 3 received, 0% packet loss, time 2000ms
rtt min/avg/max/mdev = 0.041/0.044/0.048/0.006 ms
"""

MUESTRA_100_PORCIENTO = """PING 10.99.99.99 (10.99.99.99) 56(84) bytes of data.

--- 10.99.99.99 ping statistics ---
3 packets transmitted, 0 received, 100% packet loss, time 2043ms
"""

MUESTRA_PARCIAL = """PING 10.99.50.5 (10.99.50.5) 56(84) bytes of data.
64 bytes from 10.99.50.5: icmp_seq=1 ttl=64 time=1.2 ms

--- 10.99.50.5 ping statistics ---
3 packets transmitted, 1 received, 66% packet loss, time 2010ms
rtt min/avg/max/mdev = 1.2/1.2/1.2/0.0 ms
"""


def test_0_porciento():
    assert _extraer_packet_loss(MUESTRA_0_PORCIENTO) == "0%"


def test_100_porciento():
    assert _extraer_packet_loss(MUESTRA_100_PORCIENTO) == "100%"


def test_porcentaje_parcial():
    assert _extraer_packet_loss(MUESTRA_PARCIAL) == "66%"


def test_sin_coincidencia_devuelve_vacio():
    assert _extraer_packet_loss("salida sin la frase esperada") == ""


if __name__ == "__main__":
    test_0_porciento()
    test_100_porciento()
    test_porcentaje_parcial()
    test_sin_coincidencia_devuelve_vacio()
    print("tests/test_ping.py: OK (4/4)")
