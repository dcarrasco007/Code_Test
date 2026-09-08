# ============================================================================
# tests/test_validador_comandos.py
# Proyecto: api_olt_consultas
# Descripción: Fuzz de comandos, deny-list absoluta y validación de listas
#              completas (utils/validador_comandos.py). No abre telnet ni BD.
#              Ejecutar: python -m tests.test_validador_comandos (desde la raíz).
# ============================================================================

from utils.validador_comandos import (
    ComandoInvalido,
    es_deny_list,
    es_lectura,
    matches_aprobado,
    validar_caracteres,
    validar_cantidad_olts,
    validar_comando,
    validar_lista_comandos,
)


def _espera_invalido(comando, **kwargs):
    try:
        validar_comando(comando, **kwargs)
        assert False, f"debió rechazar: {comando!r}"
    except ComandoInvalido:
        pass


def test_lectura_simple_aceptada():
    validar_comando("display port state 0/1", tiene_scope_config_aprobada=False)
    validar_comando("display ont info 0 1 2", tiene_scope_config_aprobada=False)


def test_lectura_con_mayusculas_o_formato_raro_rechazada():
    # El regex de lectura exige minúsculas y el set [a-z0-9\s\-/]; cualquier
    # otro caracter (mayúscula, '_', '.') no matchea y no es deny-list, así
    # que cae al motivo "no coincide con ningún template".
    _espera_invalido("display Port State 0/1", tiene_scope_config_aprobada=False)


def test_caracteres_prohibidos_rechazados():
    for comando in [
        "display port state 0/1\r\nreboot",   # CRLF + comando extra inyectado
        "display port state 0/1; reboot",       # separador de comandos
        "display port state 0/1 | grep algo",   # pipe
        "display port state 0/1 & reboot",      # &
        "display port state 0/1\x00",           # NUL
        "display puertañ 0/1",                   # no-ASCII
    ]:
        _espera_invalido(comando, tiene_scope_config_aprobada=False)


def test_largo_maximo():
    comando_largo = "display " + "a" * 250
    _espera_invalido(comando_largo, tiene_scope_config_aprobada=False)
    try:
        validar_caracteres("")
        assert False, "cadena vacía debió rechazarse"
    except ComandoInvalido:
        pass


def test_deny_list_absoluta_incluso_con_scope_y_template():
    # Aunque el cliente tenga el scope y exista un template que "matchee" en
    # apariencia, la deny-list gana siempre.
    templates = [r"^reboot\s+system$"]
    for comando in [
        "reboot system",
        "undo vlan 100",
        "save configuration",
        "delete unit-file",
        "ont add 0/1/2 3",
        "ont delete 0/1/2 3",
        "quit",
        "return",
        "user add geret2016",
        "password change",
    ]:
        assert es_deny_list(comando), f"debía estar en deny-list: {comando!r}"
        _espera_invalido(
            comando, tiene_scope_config_aprobada=True, templates_aprobados=templates,
        )


def test_no_falso_positivo_deny_list_en_palabras_parciales():
    # 'reset' no debe activarse por substrings dentro de otra palabra.
    assert not es_deny_list("display ont opticalinfo 0/1/2 3")
    assert es_lectura("display ont opticalinfo 0/1/2 3")


def test_template_aprobado_matchea_y_requiere_scope():
    templates = [r"^display\s+debug\s+information\s+level\s+[0-5]$"]
    comando = "vlan 100 description internet"  # no es 'display', necesita template
    # Sin scope: rechazado aunque el comando fuera válido para un template.
    _espera_invalido(comando, tiene_scope_config_aprobada=False, templates_aprobados=[r"^vlan\s\d+\sdescription\s[a-z]+$"])
    # Con scope y template que matchea: aceptado.
    validar_comando(
        comando, tiene_scope_config_aprobada=True,
        templates_aprobados=[r"^vlan\s\d+\sdescription\s[a-z]+$"],
    )
    # matches_aprobado no debe tumbarse con un regex roto en la lista.
    assert matches_aprobado("display debug information level 3", templates + ["(regex-invalido["])


def test_lista_completa_junta_todos_los_errores():
    resultado = validar_lista_comandos(
        ["display port state 0/1", "reboot", "display X\r\n"],
        tiene_scope_config_aprobada=False,
    )
    assert not resultado.ok
    assert len(resultado.errores) == 2  # el primero es válido, los otros 2 no


def test_lista_vacia_y_exceso_de_comandos():
    vacio = validar_lista_comandos([], tiene_scope_config_aprobada=False)
    assert not vacio.ok

    exceso = validar_lista_comandos(
        ["display port state 0/1"] * 31, tiene_scope_config_aprobada=False, max_comandos=30,
    )
    assert not exceso.ok
    assert any("máximo" in e for e in exceso.errores)


def test_validar_cantidad_olts():
    validar_cantidad_olts(["OLT-A", "OLT-B"], max_olts=5)
    for olts in ([], ["OLT-" + str(i) for i in range(6)]):
        try:
            validar_cantidad_olts(olts, max_olts=5)
            assert False, f"debió rechazar: {olts!r}"
        except ValueError:
            pass


if __name__ == "__main__":
    tests = [
        test_lectura_simple_aceptada,
        test_lectura_con_mayusculas_o_formato_raro_rechazada,
        test_caracteres_prohibidos_rechazados,
        test_largo_maximo,
        test_deny_list_absoluta_incluso_con_scope_y_template,
        test_no_falso_positivo_deny_list_en_palabras_parciales,
        test_template_aprobado_matchea_y_requiere_scope,
        test_lista_completa_junta_todos_los_errores,
        test_lista_vacia_y_exceso_de_comandos,
        test_validar_cantidad_olts,
    ]
    for t in tests:
        t()
    print(f"tests/test_validador_comandos.py: OK ({len(tests)}/{len(tests)})")
