# ============================================================================
# tests/test_log_api.py
# Proyecto: uplink_trafico_15m
# Descripción: utils/log_api.py — integración opcional (F7) que deja el log
#              crudo del telnet en OLT_API_LOG_TELNET (origen='cron'). Sin BD
#              real: engine fake que captura el INSERT.
#              Ejecutar: python -m tests.test_log_api (desde la raíz).
# ============================================================================

from utils import log_api


class _FakeConn:
    def __init__(self, capturas, revienta):
        self._capturas = capturas
        self._revienta = revienta

    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False

    def execute(self, sql, params=None):
        if self._revienta:
            raise RuntimeError("tabla OLT_API_LOG_TELNET no existe")
        self._capturas.append((str(sql), params or {}))


class FakeEngine:
    def __init__(self, revienta=False):
        self.capturas = []
        self._revienta = revienta
        self.begin_llamado = 0

    def begin(self):
        self.begin_llamado += 1
        return _FakeConn(self.capturas, self._revienta)


def _con_flag(valor):
    log_api.LOG_API_TELNET = valor
    log_api._aviso_dado = False


def test_flag_off_no_toca_la_bd():
    _con_flag(False)
    eng = FakeEngine()
    log_api.registrar_log_telnet(eng, olt="OLT-X", ip="10.0.0.1", log_crudo="x",
                                 duracion_ms=10, exito=True)
    assert eng.begin_llamado == 0


def test_flag_on_inserta_con_los_campos_correctos():
    _con_flag(True)
    eng = FakeEngine()
    log_api.registrar_log_telnet(
        eng, olt="OLT-X", ip="10.0.0.1",
        comandos=["interface giu 0/17", "display port traffic 0"],
        log_crudo="salida cruda", duracion_ms=1234, exito=True,
    )
    assert len(eng.capturas) == 1
    sql, params = eng.capturas[0]
    assert "OLT_API_LOG_TELNET" in sql
    assert params["olt"] == "OLT-X"
    assert params["ip"] == "10.0.0.1"
    assert params["codigo"] == "uplink_trafico"
    assert params["comandos"] == "interface giu 0/17\ndisplay port traffic 0"
    assert params["crudo"] == "salida cruda"
    assert params["dur"] == 1234
    assert params["exito"] == 1
    assert params["error"] is None


def test_flag_on_fallo_no_propaga_y_avisa_una_vez():
    _con_flag(True)
    eng = FakeEngine(revienta=True)
    # No debe lanzar ninguna excepción, ni la primera ni la segunda vez.
    log_api.registrar_log_telnet(eng, olt="OLT-X", ip="10.0.0.1", log_crudo="x",
                                 duracion_ms=1, exito=False, error="algo")
    log_api.registrar_log_telnet(eng, olt="OLT-Y", ip="10.0.0.2", log_crudo="y",
                                 duracion_ms=1, exito=False)
    assert log_api._aviso_dado is True


def test_exito_false_guarda_cero():
    _con_flag(True)
    eng = FakeEngine()
    log_api.registrar_log_telnet(eng, olt="O", ip="i", log_crudo="", duracion_ms=0,
                                 exito=False, error="x" * 400)
    _, params = eng.capturas[0]
    assert params["exito"] == 0
    assert len(params["error"]) == 255  # truncado


def test_expandir_comandos():
    pares = [("interface giu 0/17", 2), ("interface giu 0/18", 0)]
    assert log_api.expandir_comandos(pares) == [
        "interface giu 0/17", "display port traffic 0", "display port traffic 1",
        "interface giu 0/18",
    ]
    assert log_api.expandir_comandos(None) == []
    assert log_api.expandir_comandos([("x", None)]) == ["x"]


if __name__ == "__main__":
    tests = [
        test_flag_off_no_toca_la_bd,
        test_flag_on_inserta_con_los_campos_correctos,
        test_flag_on_fallo_no_propaga_y_avisa_una_vez,
        test_exito_false_guarda_cero,
        test_expandir_comandos,
    ]
    for t in tests:
        t()
    print(f"tests/test_log_api.py: OK ({len(tests)}/{len(tests)})")
