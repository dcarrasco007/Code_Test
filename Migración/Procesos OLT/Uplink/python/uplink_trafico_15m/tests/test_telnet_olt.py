# ============================================================================
# tests/test_telnet_olt.py
# Proyecto: uplink_trafico_15m
# Descripción: Ejercita la máquina de estados de utils/telnet_olt.py contra
#              tests/fake_olt_cli.py (login → enable → config → ciclo de
#              puerto → logout). Esto valida el FLUJO/CONTROL del autómata,
#              NO el comportamiento real de una OLT Huawei — eso queda para
#              la validación en producción (Fase 9).
# Ejecutar: python -m tests.test_telnet_olt (desde la raíz del proyecto).
# ============================================================================

import sys
from pathlib import Path

from utils.telnet_olt import leer_trafico_puertos, respuesta_valida
from utils.parser_trafico import parsear_trafico

_FAKE_CLI = str(Path(__file__).resolve().parent / "fake_olt_cli.py")


def _comando_fake(con_error=False):
    cmd = [sys.executable, _FAKE_CLI]
    if con_error:
        cmd.append("--con-error")
    return cmd


def test_login_enable_config_un_puerto_y_logout():
    texto = leer_trafico_puertos(
        ip="127.0.0.1",
        comandos=[("interface eth 0/16", 1)],
        timeout=5,
        comando_conexion=_comando_fake(),
    )
    assert "FAKE-OLT(config)#" in texto, texto
    assert respuesta_valida(texto) is True

    lecturas = parsear_trafico(texto)
    assert lecturas == [("15234", "8721")], lecturas


def test_dos_puertos_mismo_slot():
    texto = leer_trafico_puertos(
        ip="127.0.0.1",
        comandos=[("interface eth 0/16", 2)],
        timeout=5,
        comando_conexion=_comando_fake(),
    )
    lecturas = parsear_trafico(texto)
    assert lecturas == [("15234", "8721"), ("15234", "8721")], lecturas


def test_dos_slots_distintos_en_orden():
    texto = leer_trafico_puertos(
        ip="127.0.0.1",
        comandos=[("interface eth 0/16", 1), ("interface eth 0/17", 1)],
        timeout=5,
        comando_conexion=_comando_fake(),
    )
    lecturas = parsear_trafico(texto)
    assert lecturas == [("15234", "8721"), ("15234", "8721")], lecturas


def test_respuesta_invalida_detecta_error_cli():
    texto = leer_trafico_puertos(
        ip="127.0.0.1",
        comandos=[("interface eth 0/16", 1)],
        timeout=5,
        comando_conexion=_comando_fake(con_error=True),
    )
    assert respuesta_valida(texto) is False


if __name__ == "__main__":
    test_login_enable_config_un_puerto_y_logout()
    test_dos_puertos_mismo_slot()
    test_dos_slots_distintos_en_orden()
    test_respuesta_invalida_detecta_error_cli()
    print("tests/test_telnet_olt.py: OK (4/4)")
    print()
    print("RECORDATORIO: valida el FLUJO del automata contra un CLI simulado,")
    print("NO el comportamiento real de una OLT Huawei (pendiente Fase 9).")
