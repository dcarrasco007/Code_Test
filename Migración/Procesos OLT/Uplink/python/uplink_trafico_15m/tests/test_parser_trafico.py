# ============================================================================
# tests/test_parser_trafico.py
# Proyecto: uplink_trafico_15m
# Descripción: Valida parsear_trafico() contra una transcripción SINTÉTICA que
#              sigue el formato descrito en el PHP (líneas 'The received traffic
#              of this port(kbits/s)=N' y, 7 líneas después, la de subida).
#
#              ⚠️ IMPORTANTE: esta transcripción es sintética, construida a partir
#              de la lógica del PHP — NO ha sido validada contra una captura REAL
#              de 'display port traffic' de una OLT Huawei. Antes de dar por buena
#              la paridad (Fase 9), reemplazar/complementar estos fixtures con una
#              transcripción real (pedir al usuario 1-2 capturas de producción).
#
# Ejecutar: python -m tests.test_parser_trafico (desde la raíz del proyecto).
# ============================================================================

from utils.parser_trafico import parsear_trafico


def _bloque_sintetico(valor_bajada, valor_subida):
    """Arma un bloque de 8 líneas imitando la salida de 'display port traffic X':
    línea 0 = tráfico de bajada, línea 7 = tráfico de subida (offset fijo del PHP).
    Las líneas intermedias son relleno (otras estadísticas del puerto) para que
    el offset +7 sea representativo de un bloque real con múltiples campos.
    """
    return [
        f"    The received traffic of this port(kbits/s) = {valor_bajada}",
        "    The received traffic rate of this port(kbits/s) = 12",
        "    The received packets of this port = 500",
        "    The received bytes of this port = 64000",
        "    The received errors of this port = 0",
        "    The received discards of this port = 0",
        "    The received unicast packets of this port = 480",
        f"    The transmitted traffic of this port(kbits/s) = {valor_subida}",
    ]


def _transcripcion_sintetica(bloques):
    """Concatena bloques con \\r\\n, igual que el texto capturado por telnet."""
    lineas = []
    for bloque in bloques:
        lineas.extend(bloque)
    return "\r\n".join(lineas) + "\r\n"


def test_un_solo_puerto():
    texto = _transcripcion_sintetica([_bloque_sintetico(15234, 8721)])
    resultado = parsear_trafico(texto)
    assert resultado == [("15234", "8721")], resultado


def test_dos_puertos_en_orden():
    texto = _transcripcion_sintetica([
        _bloque_sintetico(15234, 8721),
        _bloque_sintetico(987, 321),
    ])
    resultado = parsear_trafico(texto)
    assert resultado == [("15234", "8721"), ("987", "321")], resultado


def test_no_confunde_linea_de_rate_con_linea_de_trafico():
    # La línea "traffic rate" no debe interpretarse como la línea de tráfico total.
    texto = _transcripcion_sintetica([_bloque_sintetico(100, 50)])
    resultado = parsear_trafico(texto)
    assert len(resultado) == 1, resultado


def test_texto_vacio_devuelve_lista_vacia():
    assert parsear_trafico("") == []


def test_bloque_truncado_sin_linea_de_subida():
    # Si el buffer se corta antes de llegar al offset +7, subida debe ser None
    # (mejora de robustez sobre el PHP, que indexaría fuera de rango).
    bloque = _bloque_sintetico(111, 222)[:5]  # solo 5 de 8 líneas: falta la de subida
    texto = "\r\n".join(bloque) + "\r\n"
    resultado = parsear_trafico(texto)
    assert resultado == [("111", None)], resultado


if __name__ == "__main__":
    test_un_solo_puerto()
    test_dos_puertos_en_orden()
    test_no_confunde_linea_de_rate_con_linea_de_trafico()
    test_texto_vacio_devuelve_lista_vacia()
    test_bloque_truncado_sin_linea_de_subida()
    print("tests/test_parser_trafico.py: OK (5/5)")
    print()
    print("RECORDATORIO: transcripcion sintetica, falta validar con captura real (Fase 9).")
