# ============================================================================
# tests/test_script_otros.py
# Proyecto: uplink_trafico_15m
# Descripción: Valida la lógica PURA del proceso "otros" (603_680): conteo de
#              puertos (con el 'duplicado' condicional), dispatch de comandos
#              telnet por modelo/server (incl. el FIX-PHP del bug de LASCONDES),
#              y el reintento único (mockeando leer_trafico_puertos/
#              respuesta_valida — sin BD ni telnet reales). run() completo
#              requiere BD y telnet reales — Fase 9.
# Ejecutar: python -m tests.test_script_otros (desde la raíz del proyecto).
# ============================================================================

from unittest.mock import patch

from scripts.uplink_trafico_15m_otros import script


# ─── _contar_puertos ──────────────────────────────────────────────────────────

def test_contar_puertos_basico():
    filas = [("OLT-X", "0/17/0"), ("OLT-X", "0/7/0"), ("OLT-X", "0/9/0")]
    conteo = script._contar_puertos(filas)
    assert conteo == {"0/17": 1, "0/18": 0, "0/7": 1, "0/8": 0, "0/9": 1}, conteo


def test_contar_puertos_duplicado_por_parte2_uno():
    filas = [("OLT-X", "0/9/1")]
    conteo = script._contar_puertos(filas)
    assert conteo["0/9"] == 2, conteo  # a diferencia de MA5600T, aquí 0/9 SÍ duplica


def test_contar_puertos_ignora_slots_inertes_16_19_20():
    filas = [("OLT-X", "0/16/0"), ("OLT-X", "0/19/0"), ("OLT-X", "0/20/0"), ("OLT-X", "0/17/0")]
    conteo = script._contar_puertos(filas)
    assert "0/16" not in conteo
    assert "0/19" not in conteo
    assert "0/20" not in conteo
    assert conteo["0/17"] == 1


# ─── _comandos_dispatch ───────────────────────────────────────────────────────

def _conteo_generico():
    return {"0/17": 1, "0/18": 2, "0/7": 1, "0/8": 1, "0/9": 1}


def test_dispatch_ma5603t_usa_giu_7_8_9():
    comandos = script._comandos_dispatch("OLT-CUALQUIERA", "MA5603T", _conteo_generico())
    assert comandos == [
        ("interface giu 0/7", 1),
        ("interface giu 0/8", 1),
        ("interface giu 0/9", 1),
    ], comandos


def test_dispatch_concepcion_usa_scu_7_8():
    comandos = script._comandos_dispatch("OLT-CONCEPCION-1", "MA5800T-generico", _conteo_generico())
    assert comandos == [("interface scu 0/7", 1), ("interface scu 0/8", 1)], comandos


def test_dispatch_lascondes_usa_giu_17_18_con_limite_correcto():
    # [FIX-PHP] El PHP original usaba $puerto08 (=1) como límite del for de
    # puerto18 por error; aquí debe usar conteo['0/18'] (=2), el valor correcto.
    comandos = script._comandos_dispatch("OLT-LASCONDES-1", "MA5800T-generico", _conteo_generico())
    assert comandos == [("interface giu 0/17", 1), ("interface giu 0/18", 2)], comandos


def test_dispatch_sin_regla_conocida_devuelve_none():
    comandos = script._comandos_dispatch("OLT-DESCONOCIDA-1", "MODELO-X", _conteo_generico())
    assert comandos is None


def test_dispatch_prioridad_ma5603t_sobre_server():
    # Si modelo=='MA5603T' pero el server también fuera CONCEPCION/LASCONDES,
    # el PHP prioriza el chequeo de modelo (primer 'if' del árbol).
    comandos = script._comandos_dispatch("OLT-CONCEPCION-1", "MA5603T", _conteo_generico())
    assert comandos[0][0] == "interface giu 0/7"  # dispatch de MA5603T, no de CONCEPCION


# ─── _ejecutar_con_reintento ──────────────────────────────────────────────────

def test_reintento_exito_primer_intento_no_reintenta():
    with patch.object(script, "leer_trafico_puertos", return_value="ok") as m_leer, \
         patch.object(script, "respuesta_valida", return_value=True):
        texto, fallo = script._ejecutar_con_reintento("10.0.0.1", [("interface scu 0/7", 1)])
        assert fallo is False
        assert m_leer.call_count == 1


def test_reintento_falla_reintenta_una_vez_y_luego_se_rinde():
    with patch.object(script, "leer_trafico_puertos", return_value="err") as m_leer, \
         patch.object(script, "respuesta_valida", return_value=False):
        texto, fallo = script._ejecutar_con_reintento("10.0.0.1", [("interface scu 0/7", 1)])
        assert fallo is True
        assert m_leer.call_count == 2  # 1 intento inicial + 1 reintento, sin más


def test_reintento_exito_en_el_reintento():
    with patch.object(script, "leer_trafico_puertos", return_value="texto") as m_leer, \
         patch.object(script, "respuesta_valida", side_effect=[False, True]):
        texto, fallo = script._ejecutar_con_reintento("10.0.0.1", [("interface scu 0/7", 1)])
        assert fallo is False
        assert m_leer.call_count == 2


if __name__ == "__main__":
    test_contar_puertos_basico()
    test_contar_puertos_duplicado_por_parte2_uno()
    test_contar_puertos_ignora_slots_inertes_16_19_20()
    test_dispatch_ma5603t_usa_giu_7_8_9()
    test_dispatch_concepcion_usa_scu_7_8()
    test_dispatch_lascondes_usa_giu_17_18_con_limite_correcto()
    test_dispatch_sin_regla_conocida_devuelve_none()
    test_dispatch_prioridad_ma5603t_sobre_server()
    test_reintento_exito_primer_intento_no_reintenta()
    test_reintento_falla_reintenta_una_vez_y_luego_se_rinde()
    test_reintento_exito_en_el_reintento()
    print("tests/test_script_otros.py: OK (11/11)")
