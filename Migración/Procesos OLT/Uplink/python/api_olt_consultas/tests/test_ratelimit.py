# ============================================================================
# tests/test_ratelimit.py
# Proyecto: api_olt_consultas
# Descripción: Ventana deslizante de utils/ratelimit.py, con un reloj falso
#              (se reemplaza time.monotonic dentro del módulo, no el global)
#              para no depender de sleeps reales. Ejecutar:
#              python -m tests.test_ratelimit (desde la raíz del proyecto).
# ============================================================================

from types import SimpleNamespace

import utils.ratelimit as ratelimit

_monotonic_original = ratelimit.time.monotonic


def _usar_reloj_falso(inicio: float = 1000.0):
    """Devuelve (avanzar(seg)) y deja ratelimit.time.monotonic apuntando al
    reloj falso. Restaurar con _restaurar_reloj() al terminar cada test."""
    estado = {"t": inicio}
    ratelimit.time.monotonic = lambda: estado["t"]
    return estado


def _restaurar_reloj():
    ratelimit.time.monotonic = _monotonic_original


def _cliente(id_, peticiones=3, ventana=10, ejecuciones_hora=2):
    return SimpleNamespace(
        id=id_,
        rate_limit_peticiones=peticiones,
        rate_limit_ventana_seg=ventana,
        max_ejecuciones_hora=ejecuciones_hora,
    )


def _espera_429(func, *args, **kwargs):
    try:
        func(*args, **kwargs)
        assert False, "debió lanzar HTTPException 429"
    except Exception as exc:
        assert getattr(exc, "status_code", None) == 429, f"status inesperado: {exc}"
        return exc


def test_permite_hasta_el_tope_y_bloquea_el_siguiente():
    ratelimit._reset_total_para_tests()
    reloj = _usar_reloj_falso()
    try:
        cliente = _cliente(1, peticiones=3, ventana=10)
        ratelimit.verificar_rate_limit(cliente)
        ratelimit.verificar_rate_limit(cliente)
        ratelimit.verificar_rate_limit(cliente)
        exc = _espera_429(ratelimit.verificar_rate_limit, cliente)
        assert "Retry-After" in exc.headers
    finally:
        _restaurar_reloj()


def test_ventana_vencida_libera_cupo():
    ratelimit._reset_total_para_tests()
    reloj = _usar_reloj_falso()
    try:
        cliente = _cliente(2, peticiones=2, ventana=5)
        ratelimit.verificar_rate_limit(cliente)
        ratelimit.verificar_rate_limit(cliente)
        _espera_429(ratelimit.verificar_rate_limit, cliente)
        reloj["t"] += 6  # vence la ventana de 5s
        ratelimit.verificar_rate_limit(cliente)  # no debe lanzar
    finally:
        _restaurar_reloj()


def test_rate_limit_es_independiente_por_cliente():
    ratelimit._reset_total_para_tests()
    reloj = _usar_reloj_falso()
    try:
        cliente_a = _cliente(10, peticiones=1, ventana=60)
        cliente_b = _cliente(20, peticiones=1, ventana=60)
        ratelimit.verificar_rate_limit(cliente_a)
        _espera_429(ratelimit.verificar_rate_limit, cliente_a)
        ratelimit.verificar_rate_limit(cliente_b)  # cliente distinto, cupo propio
    finally:
        _restaurar_reloj()


def test_cuota_ejecucion_no_se_consume_solo_validando():
    ratelimit._reset_total_para_tests()
    reloj = _usar_reloj_falso()
    try:
        cliente = _cliente(3, ejecuciones_hora=1)
        ratelimit.verificar_cuota_ejecucion(cliente)  # solo valida, no consume
        ratelimit.verificar_cuota_ejecucion(cliente)  # sigue habiendo cupo libre
        ratelimit.registrar_ejecucion(cliente)        # ahora sí se consume
        _espera_429(ratelimit.verificar_cuota_ejecucion, cliente)
        reloj["t"] += 3601  # pasó una hora
        ratelimit.verificar_cuota_ejecucion(cliente)   # cupo liberado
    finally:
        _restaurar_reloj()


if __name__ == "__main__":
    tests = [
        test_permite_hasta_el_tope_y_bloquea_el_siguiente,
        test_ventana_vencida_libera_cupo,
        test_rate_limit_es_independiente_por_cliente,
        test_cuota_ejecucion_no_se_consume_solo_validando,
    ]
    for t in tests:
        t()
    print(f"tests/test_ratelimit.py: OK ({len(tests)}/{len(tests)})")
