# ============================================================================
# tests/test_lectura.py
# Proyecto: api_olt_consultas
# Descripción: F5 — routers de lectura. Prueba la lógica compartida
#              (utils/func.py) y el catálogo/lecturas de
#              model/lectura/m_lectura_model.py SIN tocar una BD real:
#                - serialización de filas (datetime/Decimal/bytes, columna id)
#                - normalización de ?desde&hasta
#                - contrato de respuesta uniforme
#                - atender_lectura(): 404 OLT desconocida, último dato, con_log
#                  sin log, 422 rango sobre columna de texto, 422 sin IP
#                - integridad del catálogo (las 20 consultas del seed)
#              Ejecutar: python -m tests.test_lectura (desde la raíz).
# ============================================================================

import datetime as dt
from decimal import Decimal

from fastapi import HTTPException

from utils import func
from model.lectura import m_lectura_model as lectura


# ─── Fake engine mínimo (solo para leer_ultimo / ultimo_log_telnet) ─────────

class _FakeResult:
    def __init__(self, filas):
        self._filas = filas

    def mappings(self):
        return self

    def all(self):
        return list(self._filas)

    def first(self):
        return self._filas[0] if self._filas else None


class _FakeConn:
    def __init__(self, filas, capturas):
        self._filas = filas
        self._capturas = capturas

    def __enter__(self):
        return self

    def __exit__(self, *a):
        return False

    def execute(self, sql, binds=None):
        self._capturas.append((str(sql), binds or {}))
        return _FakeResult(self._filas)


class FakeEngine:
    def __init__(self, filas):
        self._filas = filas
        self.capturas = []

    def connect(self):
        return _FakeConn(self._filas, self.capturas)


# ─── serialización ─────────────────────────────────────────────────────────

def test_serializar_valor_tipos():
    assert func.serializar_valor(Decimal("1.50000")) == 1.5
    assert func.serializar_valor(dt.datetime(2026, 9, 8, 10, 0, 0)) == "2026-09-08 10:00:00"
    assert func.serializar_valor(dt.date(2026, 9, 8)) == "2026-09-08"
    assert func.serializar_valor(b"abc") == "abc"
    assert func.serializar_valor("texto") == "texto"
    assert func.serializar_valor(None) is None


def test_serializar_fila_oculta_id():
    fila = {"id": 99, "server": "OLT-X", "peak": Decimal("3.5")}
    assert func.serializar_fila(fila) == {"server": "OLT-X", "peak": 3.5}


# ─── ?desde&hasta ──────────────────────────────────────────────────────────

def test_rango_fechas():
    assert func.rango_fechas(None, None) is None
    ini, fin = func.rango_fechas(dt.date(2026, 9, 1), dt.date(2026, 9, 3))
    assert ini == dt.datetime(2026, 9, 1, 0, 0, 0)
    assert fin.hour == 23 and fin.minute == 59 and fin.date() == dt.date(2026, 9, 3)
    # solo 'desde' → hasta = hoy
    r = func.rango_fechas(dt.date(2026, 1, 1), None)
    assert r is not None and r[0] == dt.datetime(2026, 1, 1, 0, 0, 0)


def test_rango_fechas_invertido_es_422():
    try:
        func.rango_fechas(dt.date(2026, 9, 10), dt.date(2026, 9, 1))
    except HTTPException as e:
        assert e.status_code == 422
    else:
        raise AssertionError("esperaba 422")


# ─── contrato de respuesta ─────────────────────────────────────────────────

def test_respuesta_dato_con_registros():
    olt = {"server": "OLT-X", "ip": "10.0.0.1", "modelo": "MA5600T"}
    r = func.respuesta_dato(consulta="fan", olt=olt,
                            registros=[{"nombre": "FAN0", "estado": "NORMAL"}],
                            fecha_dato=dt.datetime(2026, 9, 8, 10, 0, 0))
    assert r["ok"] and r["consulta"] == "fan" and r["fuente"] == "bd"
    assert r["olt"] == olt
    assert r["dato"] == {"registros": [{"nombre": "FAN0", "estado": "NORMAL"}], "cantidad": 1}
    assert r["fecha_dato"] == "2026-09-08 10:00:00"
    assert r["job"] is None


def test_respuesta_dato_sin_registros_pone_motivo():
    r = func.respuesta_dato(consulta="fan", olt={"server": "OLT-X"}, registros=[])
    assert r["dato"] is None
    assert r["meta"]["motivo"] == "sin registros"


def test_formatear_log():
    assert func.formatear_log(None) is None
    fila = {"id": 12, "fecha": dt.datetime(2026, 9, 8, 9, 0, 0),
            "comandos_enviados": "enable\nconfig\ndisplay emu 0\n",
            "log_crudo": "...", "duracion_ms": 1830, "exito": 1, "error": None}
    log = func.formatear_log(fila)
    assert log["comandos"] == ["enable", "config", "display emu 0"]
    assert log["exito"] is True and log["id"] == 12


# ─── atender_lectura (todo monkeypatched, sin engine real) ──────────────────

def _patch(monkey):
    """Aplica una serie de (obj, attr, valor) y devuelve un restaurador."""
    originales = [(o, a, getattr(o, a)) for (o, a, _) in monkey]
    for o, a, v in monkey:
        setattr(o, a, v)

    def restaurar():
        for o, a, v in originales:
            setattr(o, a, v)
    return restaurar


def test_atender_lectura_olt_desconocida_404():
    rest = _patch([
        (func, "get_engine", lambda: object()),
        (func, "obtener_olt", lambda engine, server: None),
    ])
    try:
        func.atender_lectura("fan", "OLT-NOEXISTE")
    except HTTPException as e:
        assert e.status_code == 404
    else:
        raise AssertionError("esperaba 404")
    finally:
        rest()


def test_atender_lectura_ultimo_dato_ok():
    olt = {"server": "OLT-X", "ip": "10.0.0.1", "modelo": "MA5600T"}
    rest = _patch([
        (func, "get_engine", lambda: object()),
        (func, "obtener_olt", lambda engine, server: olt),
        (func._lectura, "leer_ultimo",
         lambda engine, codigo, valor: ([{"id": 1, "nombre": "FAN0", "estado": "NORMAL"}],
                                        dt.datetime(2026, 9, 8, 10, 0, 0))),
    ])
    try:
        r = func.atender_lectura("fan", "OLT-X")
        assert r["dato"]["cantidad"] == 1
        assert r["dato"]["registros"][0] == {"nombre": "FAN0", "estado": "NORMAL"}
        assert r["fecha_dato"] == "2026-09-08 10:00:00"
        assert r["log"] is None
    finally:
        rest()


def test_atender_lectura_con_log_sin_log_agrega_motivo():
    olt = {"server": "OLT-X", "ip": "10.0.0.1", "modelo": "MA5600T"}
    rest = _patch([
        (func, "get_engine", lambda: object()),
        (func, "obtener_olt", lambda engine, server: olt),
        (func._lectura, "leer_ultimo", lambda engine, codigo, valor: ([{"id": 1, "x": 1}], None)),
        (func._lectura, "ultimo_log_telnet", lambda engine, olt_server, codigo: None),
    ])
    try:
        r = func.atender_lectura("fan", "OLT-X", con_log=True)
        assert r["log"] is None
        assert "motivo_log" in r["meta"]
    finally:
        rest()


def test_atender_lectura_rango_sobre_texto_es_422():
    olt = {"server": "OLT-X", "ip": "10.0.0.1", "modelo": "MA5600T"}

    def _boom(engine, codigo, valor, rango):
        raise lectura.RangoNoDisponible(codigo)

    rest = _patch([
        (func, "get_engine", lambda: object()),
        (func, "obtener_olt", lambda engine, server: olt),
        (func._lectura, "leer_rango", _boom),
    ])
    try:
        func.atender_lectura("fan", "OLT-X",
                             rango=(dt.datetime(2026, 9, 1), dt.datetime(2026, 9, 3)))
    except HTTPException as e:
        assert e.status_code == 422
    else:
        raise AssertionError("esperaba 422")
    finally:
        rest()


def test_atender_lectura_pon_sin_ip_es_422():
    olt_sin_ip = {"server": "OLT-X", "ip": None, "modelo": "MA5600T"}
    rest = _patch([
        (func, "get_engine", lambda: object()),
        (func, "obtener_olt", lambda engine, server: olt_sin_ip),
    ])
    try:
        func.atender_lectura("pon_trafico", "OLT-X")
    except HTTPException as e:
        assert e.status_code == 422
    else:
        raise AssertionError("esperaba 422")
    finally:
        rest()


# ─── catálogo de lectura ───────────────────────────────────────────────────

_CODIGOS_SEED = {
    "uplink_trafico", "alarmas_activas", "alarmas_detalle", "alarmas_critical_los",
    "alarmas_los_ont", "potencia_optica_uplink", "tarjetas", "fan", "vlan_cantidad",
    "vlan_trafico", "vlan_servicios", "energia_alarma", "energia_estado", "pon_trafico",
    "temperatura_cpu", "uptime_gpon", "uplink_state", "ont_detalle", "ont_reporte", "version",
}


def test_catalogo_cubre_las_20_consultas_del_seed():
    assert set(lectura.codigos_legibles()) == _CODIGOS_SEED
    assert len(_CODIGOS_SEED) == 20


def test_valor_filtro_server_o_ip():
    olt = {"server": "OLT-X", "ip": "10.0.0.1"}
    assert lectura.valor_filtro("fan", olt) == "OLT-X"            # filtra por nombre
    assert lectura.valor_filtro("pon_trafico", olt) == "10.0.0.1"  # filtra por IP


def test_fuente_de_desconocida_lanza():
    try:
        lectura.fuente_de("no_existe")
    except lectura.ConsultaNoLegible:
        pass
    else:
        raise AssertionError("esperaba ConsultaNoLegible")


def test_leer_ultimo_agrupa_por_ultimo_lote():
    filas = [
        {"id": 10, "equipo": "OLT-X", "fecha": "2026-09-08 10:00:00", "nombre": "FAN0"},
        {"id": 11, "equipo": "OLT-X", "fecha": "2026-09-08 10:00:00", "nombre": "FAN1"},
    ]
    eng = FakeEngine(filas)
    res, fecha = lectura.leer_ultimo(eng, "fan", "OLT-X")
    assert len(res) == 2 and fecha == "2026-09-08 10:00:00"
    sql, binds = eng.capturas[0]
    assert "OLT_FAN_ESTADO" in sql and binds == {"olt": "OLT-X"}
    assert "`equipo`" in sql and "`fecha`" in sql


def test_leer_rango_rechaza_columna_texto():
    try:
        lectura.leer_rango(FakeEngine([]), "fan", "OLT-X", (dt.datetime.now(), dt.datetime.now()))
    except lectura.RangoNoDisponible:
        pass
    else:
        raise AssertionError("esperaba RangoNoDisponible (OLT_FAN_ESTADO.fecha es texto)")


def test_leer_rango_datetime_ok_y_trunca():
    filas = [{"id": i, "server": "OLT-X", "fecha": dt.datetime(2026, 9, 8, 10, i % 60)} for i in range(3)]
    eng = FakeEngine(filas)
    original = lectura.LIMITE_FILAS_RANGO
    lectura.LIMITE_FILAS_RANGO = 2
    try:
        res, truncado = lectura.leer_rango(
            eng, "uplink_trafico", "OLT-X", (dt.datetime(2026, 9, 1), dt.datetime(2026, 9, 30)))
        assert len(res) == 2 and truncado is True
        sql, binds = eng.capturas[0]
        assert "OLT_TRAFICO_UPLINK_HORA" in sql
        assert binds["desde"] == dt.datetime(2026, 9, 1) and binds["limite"] == 3
    finally:
        lectura.LIMITE_FILAS_RANGO = original


if __name__ == "__main__":
    tests = [
        test_serializar_valor_tipos,
        test_serializar_fila_oculta_id,
        test_rango_fechas,
        test_rango_fechas_invertido_es_422,
        test_respuesta_dato_con_registros,
        test_respuesta_dato_sin_registros_pone_motivo,
        test_formatear_log,
        test_atender_lectura_olt_desconocida_404,
        test_atender_lectura_ultimo_dato_ok,
        test_atender_lectura_con_log_sin_log_agrega_motivo,
        test_atender_lectura_rango_sobre_texto_es_422,
        test_atender_lectura_pon_sin_ip_es_422,
        test_catalogo_cubre_las_20_consultas_del_seed,
        test_valor_filtro_server_o_ip,
        test_fuente_de_desconocida_lanza,
        test_leer_ultimo_agrupa_por_ultimo_lote,
        test_leer_rango_rechaza_columna_texto,
        test_leer_rango_datetime_ok_y_trunca,
    ]
    for t in tests:
        t()
    print(f"tests/test_lectura.py: OK ({len(tests)}/{len(tests)})")
