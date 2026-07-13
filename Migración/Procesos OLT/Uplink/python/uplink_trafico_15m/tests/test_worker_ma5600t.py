# ============================================================================
# tests/test_worker_ma5600t.py
# Proyecto: uplink_trafico_15m
# Descripción: Valida la lógica PURA del worker MA5600T: conteo de puertos
#              (con el 'duplicado' condicional), gate de ping, y el árbol de
#              decisión de reintentos telnet (mockeando leer_trafico_puertos/
#              respuesta_valida — sin BD ni telnet reales). La orquestación
#              completa (procesar_olt) requiere BD y telnet reales — Fase 9.
# Ejecutar: python -m tests.test_worker_ma5600t (desde la raíz del proyecto).
# ============================================================================

from unittest.mock import patch

from scripts.uplink_trafico_15m_ma5600t import worker


# ─── _contar_puertos ──────────────────────────────────────────────────────────

def test_contar_puertos_basico():
    filas = [("OLT-X", "0/17/0"), ("OLT-X", "0/18/0"), ("OLT-X", "0/19/0")]
    conteo = worker._contar_puertos(filas)
    assert conteo == {"0/17": 1, "0/18": 1, "0/19": 1, "0/20": 0, "0/7": 0, "0/8": 0}, conteo


def test_contar_puertos_duplicado_por_parte2_uno():
    # Primera fila del slot 0/17 con parte2=='1' -> cuenta doble.
    filas = [("OLT-X", "0/17/1")]
    conteo = worker._contar_puertos(filas)
    assert conteo["0/17"] == 2, conteo


def test_contar_puertos_sin_duplicado_si_parte2_no_es_uno():
    filas = [("OLT-X", "0/17/0")]
    conteo = worker._contar_puertos(filas)
    assert conteo["0/17"] == 1, conteo


def test_contar_puertos_duplicado_no_aplica_a_19_20():
    filas = [("OLT-X", "0/19/1")]
    conteo = worker._contar_puertos(filas)
    assert conteo["0/19"] == 1, conteo  # sin duplicado para 19/20


def test_contar_puertos_ignora_slot_16_inerte():
    filas = [("OLT-X", "0/16/0"), ("OLT-X", "0/17/0")]
    conteo = worker._contar_puertos(filas)
    assert "0/16" not in conteo
    assert conteo["0/17"] == 1


# ─── _ping_ok ─────────────────────────────────────────────────────────────────

def test_ping_ok_con_perdida_cero():
    with patch.object(worker, "ping_ip", return_value="0%"):
        assert worker._ping_ok("10.0.0.1") is True


def test_ping_ok_con_perdida_total():
    with patch.object(worker, "ping_ip", return_value="100%"):
        assert worker._ping_ok("10.0.0.1") is False


def test_ping_ok_vacio_es_no_alcanzable():
    with patch.object(worker, "ping_ip", return_value=""):
        assert worker._ping_ok("10.0.0.1") is False


# ─── _ejecutar_con_reintentos: árbol de decisión ─────────────────────────────

def _conteo_generico():
    return {"0/17": 1, "0/18": 1, "0/19": 1, "0/20": 1, "0/7": 1, "0/8": 1}


def test_laflorida_exito_primer_intento_no_reintenta():
    with patch.object(worker, "leer_trafico_puertos", return_value="ok") as m_leer, \
         patch.object(worker, "respuesta_valida", return_value=True):
        texto, fallo = worker._ejecutar_con_reintentos(
            "10.0.0.1", "OLT-LAFLORIDA-1", _conteo_generico()
        )
        assert fallo is False
        assert m_leer.call_count == 1
        comandos_usados = m_leer.call_args[0][1]
        assert comandos_usados[0][0] == "interface giu 0/19"


def test_laflorida_falla_reintenta_con_giu_17_18():
    with patch.object(worker, "leer_trafico_puertos", return_value="err") as m_leer, \
         patch.object(worker, "respuesta_valida", return_value=False):
        texto, fallo = worker._ejecutar_con_reintentos(
            "10.0.0.1", "OLT-LAFLORIDA-1", _conteo_generico()
        )
        assert fallo is True
        # 1 intento inicial (19/20) + 3 reintentos (17/18) = 4 llamadas
        assert m_leer.call_count == 4, m_leer.call_count
        primer_intento = m_leer.call_args_list[0][0][1]
        reintento = m_leer.call_args_list[1][0][1]
        assert primer_intento[0][0] == "interface giu 0/19"
        assert reintento[0][0] == "interface giu 0/17"


def test_altopenuelas_falla_reintenta_solo_una_vez_con_scu():
    with patch.object(worker, "leer_trafico_puertos", return_value="err") as m_leer, \
         patch.object(worker, "respuesta_valida", return_value=False):
        texto, fallo = worker._ejecutar_con_reintentos(
            "10.0.0.1", "OLT-ALTOPENUELAS-1", _conteo_generico()
        )
        assert fallo is True
        assert m_leer.call_count == 2, m_leer.call_count  # 1 inicial + 1 reintento, ambos scu
        for llamada in m_leer.call_args_list:
            assert llamada[0][1][0][0] == "interface scu 0/7"


def test_vitacura_no_esta_en_lista_de_reintento_cae_a_giu():
    # [PARIDAD-PHP] VITACURA-1 usa scu 7/8 en el primer intento (SERVERS_MA5600T_SCU_7_8),
    # pero NO está en la lista de reintento -> cae al grupo 'else' (giu 17/18).
    with patch.object(worker, "leer_trafico_puertos", return_value="err") as m_leer, \
         patch.object(worker, "respuesta_valida", return_value=False):
        texto, fallo = worker._ejecutar_con_reintentos(
            "10.0.0.1", "OLT-VITACURA-1", _conteo_generico()
        )
        assert fallo is True
        primer_intento = m_leer.call_args_list[0][0][1]
        reintento = m_leer.call_args_list[1][0][1]
        assert primer_intento[0][0] == "interface scu 0/7"
        assert reintento[0][0] == "interface giu 0/17"


def test_general_exito_en_segundo_reintento_detiene_el_ciclo():
    respuestas = [False, False, True]  # falla, falla, exito -> se detiene
    with patch.object(worker, "leer_trafico_puertos", return_value="texto") as m_leer, \
         patch.object(worker, "respuesta_valida", side_effect=respuestas):
        texto, fallo = worker._ejecutar_con_reintentos(
            "10.0.0.1", "OLT-GENERICA-1", _conteo_generico()
        )
        assert fallo is False
        assert m_leer.call_count == 3, m_leer.call_count  # 1 inicial + 2 reintentos


if __name__ == "__main__":
    test_contar_puertos_basico()
    test_contar_puertos_duplicado_por_parte2_uno()
    test_contar_puertos_sin_duplicado_si_parte2_no_es_uno()
    test_contar_puertos_duplicado_no_aplica_a_19_20()
    test_contar_puertos_ignora_slot_16_inerte()
    test_ping_ok_con_perdida_cero()
    test_ping_ok_con_perdida_total()
    test_ping_ok_vacio_es_no_alcanzable()
    test_laflorida_exito_primer_intento_no_reintenta()
    test_laflorida_falla_reintenta_con_giu_17_18()
    test_altopenuelas_falla_reintenta_solo_una_vez_con_scu()
    test_vitacura_no_esta_en_lista_de_reintento_cae_a_giu()
    test_general_exito_en_segundo_reintento_detiene_el_ciclo()
    print("tests/test_worker_ma5600t.py: OK (13/13)")
