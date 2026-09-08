# ============================================================================
# tests/test_f9.py
# Proyecto: api_olt_consultas
# Descripción: F9 — la lógica PURA de scripts/f9_paridad.py (recolección de
#              números y resumen comparativo). Los scripts en sí hacen HTTP y
#              se ejercitan en producción con docs/VALIDACION_F9.md.
#              Ejecutar: python -m tests.test_f9 (desde la raíz).
# ============================================================================

import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "scripts"))

import f9_paridad  # noqa: E402


def test_numeros_recolecta_recursivo_y_strings_numericas():
    acc = []
    f9_paridad._numeros(
        {"a": 1, "b": "2.5", "c": ["3", {"d": 4}], "e": "texto", "f": True}, acc
    )
    assert sorted(acc) == [1.0, 2.5, 3.0, 4.0]  # True/"texto" no cuentan


def test_comparar_cantidades_iguales_sin_observaciones_duras():
    r = f9_paridad.comparar_datos(
        {"registros": [{"x": "10"}], "cantidad": 1},
        {"registros": [{"trafico": "10"}], "cantidad": 1},
    )
    assert r["cantidad_telnet"] == 1 and r["cantidad_bd"] == 1
    assert not any("cantidad distinta" in o for o in r["observaciones"])


def test_comparar_cantidades_distintas_lo_marca():
    r = f9_paridad.comparar_datos(
        {"registros": [1, 2, 3], "cantidad": 3},
        {"registros": [1], "cantidad": 1},
    )
    assert any("cantidad distinta: telnet=3 vs bd=1" in o for o in r["observaciones"])


def test_comparar_dato_null_de_un_lado():
    r = f9_paridad.comparar_datos({"registros": [1], "cantidad": 1}, None)
    assert any("dato: null" in o for o in r["observaciones"])


def test_comparar_suma_numerica_difiere_mas_de_5pct():
    r = f9_paridad.comparar_datos(
        {"registros": [{"v": 100.0}], "cantidad": 1},
        {"registros": [{"v": 200.0}], "cantidad": 1},
    )
    assert any("numéricos difiere >5%" in o for o in r["observaciones"])


def test_endpoint_lectura_cubre_los_20_menos_excepciones():
    # 19 consultas legibles tienen endpoint GET (uplink_state incluida).
    assert set(f9_paridad.ENDPOINT_LECTURA) == {
        "uplink_trafico", "potencia_optica_uplink", "uplink_state",
        "alarmas_activas", "alarmas_detalle", "alarmas_critical_los", "alarmas_los_ont",
        "tarjetas", "fan", "energia_alarma", "energia_estado", "temperatura_cpu",
        "version", "uptime_gpon", "vlan_cantidad", "vlan_trafico", "vlan_servicios",
        "pon_trafico", "ont_detalle", "ont_reporte",
    }


if __name__ == "__main__":
    tests = [
        test_numeros_recolecta_recursivo_y_strings_numericas,
        test_comparar_cantidades_iguales_sin_observaciones_duras,
        test_comparar_cantidades_distintas_lo_marca,
        test_comparar_dato_null_de_un_lado,
        test_comparar_suma_numerica_difiere_mas_de_5pct,
        test_endpoint_lectura_cubre_los_20_menos_excepciones,
    ]
    for t in tests:
        t()
    print(f"tests/test_f9.py: OK ({len(tests)}/{len(tests)})")
