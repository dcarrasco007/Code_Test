# ============================================================================
# tests/test_ejecucion.py
# Proyecto: api_olt_consultas
# Descripción: F6 — ejecución en vivo, cola de jobs, y helpers de los routers
#              de ejecución. Sin OLT ni BD reales: FakeClienteTelnet en
#              memoria + funciones de modelo/persistencia monkey-patcheadas
#              (mismo estilo que tests/test_ejecutor.py).
#              Ejecutar: python -m tests.test_ejecucion (desde la raíz).
# ============================================================================

import asyncio

from fastapi import HTTPException

import utils.ejecutor as ejecutor
import utils.jobs as jobs_mod
import utils.sesiones_telnet as sesiones_telnet
from app.config import settings
from schema.ejecucion_schema import (
    EjecutarComandosRequest,
    EjecutarConsultaRequest,
    EjecutarLoteRequest,
    NavegacionComandos,
)
from utils.ejecutor import ErrorEjecucion
from utils.sesiones_telnet import CircuitoAbiertoError, CupoAgotadoError, GobernadorSesiones
from utils.telnet_olt import RechazoPorLimiteSesiones


# ─── Fakes ──────────────────────────────────────────────────────────────────

class FakeClienteTelnet:
    def __init__(self, host, timeout=30, fallar_en=None):
        self.host = host
        self.timeout = timeout
        self.enviados = []
        self.enable_llamado = False
        self.config_llamado = False
        self.cerrado = False
        self._fallar_en = fallar_en
        self._n = 0

    async def conectar(self, usuario, password):
        self.usuario = usuario

    async def enable(self):
        self.enable_llamado = True

    async def config(self):
        self.config_llamado = True

    async def enviar_comando(self, comando):
        self._n += 1
        if self._fallar_en == self._n:
            from utils.telnet_olt import ErrorTelnet
            raise ErrorTelnet(f"falla simulada en #{self._n}")
        self.enviados.append(comando)
        return f">>> {comando}\n"

    async def cerrar(self):
        self.cerrado = True


def _fabrica(fallar_en=None):
    creadas = []

    def _crear(host, timeout=30):
        c = FakeClienteTelnet(host, timeout=timeout, fallar_en=fallar_en)
        creadas.append(c)
        return c

    _crear.creadas = creadas
    return _crear


def _gobernador_fake_bd():
    sesiones_telnet._registrar_apertura = lambda engine, **kw: 1
    sesiones_telnet._registrar_cierre = lambda engine, sid, estado, motivo: None
    return GobernadorSesiones()


_LOGS = []


def _fake_persistencia():
    _LOGS.clear()
    ejecutor._persistir_log = lambda engine, **kw: _LOGS.append(kw)


# ─── ejecutor: navegación mínima según contexto ────────────────────────────

def test_consulta_contexto_user_no_hace_enable_ni_config():
    async def _run():
        _fake_persistencia()
        ejecutor._consulta_por_codigo = lambda e, c: {
            "id": 1, "codigo": c, "timeout_seg": 5, "perfil_credencial": "DEFAULT",
            "modo_sync_max_olts": 1, "activo": 1,
        }
        # energia_estado: todas las filas en contexto 'user'
        ejecutor._filas_de_comandos = lambda e, cid, modelo, server: [{
            "modelo": "MA5600T", "server": None, "orden": 10, "contexto": "user",
            "interface_tipo": None, "comando_template": "display board 0/{slot}",
            "enter_extra": 0, "repetir_por": "slot", "fuente_lista": "fijo", "lista_fija": "19,20",
        }]
        fab = _fabrica()
        await ejecutor.ejecutar_consulta(
            engine="fake", gobernador=_gobernador_fake_bd(), codigo="energia_estado",
            server="OLT-A", ip="10.0.0.1", modelo="MA5600T",
            usuario_telnet="geret2016", password_telnet="x", fabrica_cliente_telnet=fab,
        )
        cli = fab.creadas[0]
        assert cli.enable_llamado is False and cli.config_llamado is False
        assert cli.enviados == ["display board 0/19", "display board 0/20"]

    asyncio.run(_run())


def test_consulta_contexto_config_si_hace_enable_y_config():
    async def _run():
        _fake_persistencia()
        ejecutor._consulta_por_codigo = lambda e, c: {
            "id": 1, "codigo": c, "timeout_seg": 5, "perfil_credencial": "DEFAULT",
            "modo_sync_max_olts": 1, "activo": 1,
        }
        ejecutor._filas_de_comandos = lambda e, cid, modelo, server: [{
            "modelo": None, "server": None, "orden": 10, "contexto": "config",
            "interface_tipo": None, "comando_template": "display emu 0",
            "enter_extra": 0, "repetir_por": None, "fuente_lista": None, "lista_fija": None,
        }]
        fab = _fabrica()
        await ejecutor.ejecutar_consulta(
            engine="fake", gobernador=_gobernador_fake_bd(), codigo="fan",
            server="OLT-A", ip="10.0.0.1", modelo="MA5600T",
            usuario_telnet="geret2016", password_telnet="x", fabrica_cliente_telnet=fab,
        )
        cli = fab.creadas[0]
        assert cli.enable_llamado and cli.config_llamado

    asyncio.run(_run())


# ─── ejecutor.ejecutar_comandos ────────────────────────────────────────────

def test_ejecutar_comandos_con_navegacion():
    async def _run():
        _fake_persistencia()
        fab = _fabrica()
        res = await ejecutor.ejecutar_comandos(
            engine="fake", gobernador=_gobernador_fake_bd(), server="OLT-A", ip="10.0.0.1",
            usuario_telnet="geretapi", password_telnet="x",
            comandos=["display port state 0", "display ont info summary 0"],
            navegacion={"tipo": "gpon", "slot": 3}, fabrica_cliente_telnet=fab,
        )
        assert res.ok
        cli = fab.creadas[0]
        assert cli.enviados[0] == "interface gpon 0/3"
        assert [s["comando"] for s in res.salida_por_comando] == [
            "display port state 0", "display ont info summary 0",
        ]
        # el log se persiste con consulta_codigo=None (comandos libres)
        assert _LOGS[0]["consulta_codigo"] is None

    asyncio.run(_run())


def test_ejecutar_comandos_falla_a_mitad_persiste_parcial_y_lanza():
    async def _run():
        _fake_persistencia()
        fab = _fabrica(fallar_en=2)
        try:
            await ejecutor.ejecutar_comandos(
                engine="fake", gobernador=_gobernador_fake_bd(), server="OLT-A", ip="10.0.0.1",
                usuario_telnet="geretapi", password_telnet="x",
                comandos=["display a", "display b", "display c"], fabrica_cliente_telnet=fab,
            )
            raise AssertionError("debió lanzar ErrorEjecucion")
        except ErrorEjecucion:
            pass
        assert _LOGS and _LOGS[0]["exito"] == 0
        assert "display a" in _LOGS[0]["comandos_enviados"]

    asyncio.run(_run())


def test_ejecutar_comandos_cupo_agotado_se_propaga():
    async def _run():
        _fake_persistencia()

        class GobSinCupo(GobernadorSesiones):
            def adquirir(self, **kw):
                raise CupoAgotadoError("sin cupo")

        try:
            await ejecutor.ejecutar_comandos(
                engine="fake", gobernador=GobSinCupo(), server="OLT-A", ip="10.0.0.1",
                usuario_telnet="u", password_telnet="p", comandos=["display x"],
                fabrica_cliente_telnet=_fabrica(),
            )
            raise AssertionError("debió propagar CupoAgotadoError")
        except CupoAgotadoError:
            pass
        assert not _LOGS  # nada que persistir: no se envió nada

    asyncio.run(_run())


# ─── settings.credencial_telnet ────────────────────────────────────────────

def test_credencial_telnet_dedicado_vs_perfil():
    orig = (settings.OLT_TELNET_API_DEDICADO, settings.OLT_TELNET_API_USER,
            settings.OLT_TELNET_API_PASS, dict(settings.PERFILES_TELNET))
    try:
        settings.OLT_TELNET_API_DEDICADO = True
        settings.OLT_TELNET_API_USER = "geretapi"
        settings.OLT_TELNET_API_PASS = "secreto"
        assert settings.credencial_telnet("ONT") == ("geretapi", "secreto")

        settings.OLT_TELNET_API_DEDICADO = False
        settings.PERFILES_TELNET["ONT"] = ("geretont", "pass_ont")
        assert settings.credencial_telnet("ONT") == ("geretont", "pass_ont")
        # perfil inexistente cae a DEFAULT
        settings.PERFILES_TELNET["DEFAULT"] = ("geret2016", "pass_def")
        assert settings.credencial_telnet("NOEXISTE") == ("geret2016", "pass_def")
    finally:
        (settings.OLT_TELNET_API_DEDICADO, settings.OLT_TELNET_API_USER,
         settings.OLT_TELNET_API_PASS) = orig[:3]
        settings.PERFILES_TELNET.clear()
        settings.PERFILES_TELNET.update(orig[3])


# ─── schema ────────────────────────────────────────────────────────────────

def test_schema_consulta_valida_server():
    EjecutarConsultaRequest(server="OLT-VITACURA-1")
    for malo in ("", "a b", "OLT;DROP", "-x", "x-"):
        try:
            EjecutarConsultaRequest(server=malo)
            raise AssertionError(f"debió rechazar {malo!r}")
        except Exception:
            pass


def test_schema_navegacion():
    NavegacionComandos(tipo="gpon", slot=0)
    for kw in ({"tipo": "xxx", "slot": 1}, {"tipo": "eth", "slot": -1}, {"tipo": "eth", "slot": 99}):
        try:
            NavegacionComandos(**kw)
            raise AssertionError(f"debió rechazar {kw}")
        except Exception:
            pass


def test_schema_comandos_min_y_sin_vacios():
    EjecutarComandosRequest(server="OLT-A", comandos=["display x"])
    for c in ([], ["  "], ["display x", ""]):
        try:
            EjecutarComandosRequest(server="OLT-A", comandos=c)
            raise AssertionError(f"debió rechazar {c}")
        except Exception:
            pass


def test_schema_lote_sin_duplicados():
    EjecutarLoteRequest(codigo="fan", servers=["OLT-A", "OLT-B"])
    for kw in ({"codigo": "fan", "servers": ["OLT-A", "OLT-A"]},
               {"codigo": "FAN", "servers": ["OLT-A"]},
               {"codigo": "fan", "servers": []}):
        try:
            EjecutarLoteRequest(**kw)
            raise AssertionError(f"debió rechazar {kw}")
        except Exception:
            pass


# ─── cola de jobs ──────────────────────────────────────────────────────────

class FakeJobsModel:
    def __init__(self):
        self.estados = {}
        self.resultados = {}
        self.errores = {}

    def crear(self, engine, *, uuid, cliente_id, tipo, parametros):
        self.estados[uuid] = "PENDIENTE"

    def marcar_running(self, engine, uuid):
        self.estados[uuid] = "RUNNING"

    def marcar_fin(self, engine, uuid, estado, *, resultado=None, error=None):
        self.estados[uuid] = estado
        self.resultados[uuid] = resultado
        self.errores[uuid] = error


def _gestor_con_fake():
    fake = FakeJobsModel()
    jobs_mod.m_jobs_model = fake
    g = jobs_mod.GestorJobs()
    return g, fake


def test_job_ok_guarda_resultado():
    async def _run():
        g, fake = _gestor_con_fake()

        async def handler(params):
            return {"eco": params["x"]}

        g.registrar_handler("t", handler)
        await g.iniciar(engine="fake")
        uuid = await g.encolar(tipo="t", parametros={"x": 42}, cliente_id=1)
        await asyncio.sleep(0.05)
        await g.detener()
        assert fake.estados[uuid] == "OK"
        assert fake.resultados[uuid] == {"eco": 42}

    asyncio.run(_run())


def test_job_handler_error_marca_error():
    async def _run():
        g, fake = _gestor_con_fake()

        async def handler(params):
            raise RuntimeError("boom")

        g.registrar_handler("t", handler)
        await g.iniciar(engine="fake")
        uuid = await g.encolar(tipo="t", parametros={}, cliente_id=1)
        await asyncio.sleep(0.05)
        await g.detener()
        assert fake.estados[uuid] == "ERROR"
        assert "boom" in (fake.errores[uuid] or "")

    asyncio.run(_run())


def test_encolar_tipo_desconocido():
    async def _run():
        g, _ = _gestor_con_fake()
        try:
            await g.encolar(tipo="no_existe", parametros={}, cliente_id=1)
            raise AssertionError("debió lanzar ValueError")
        except ValueError:
            pass

    asyncio.run(_run())


# ─── router: _map_error ────────────────────────────────────────────────────

def test_map_error_codigos_http():
    from router.ejecutar.ejecutarRouter import _map_error

    assert _map_error(CupoAgotadoError("x")).status_code == 503
    assert _map_error(CircuitoAbiertoError("OLT-A", "u", 30)).status_code == 503
    assert _map_error(RechazoPorLimiteSesiones("x")).status_code == 503
    assert _map_error(asyncio.TimeoutError()).status_code == 504
    assert _map_error(ErrorEjecucion("'fan' no tiene comandos definidos para el modelo 'X'")).status_code == 409
    assert _map_error(ErrorEjecucion("consulta desconocida o inactiva: 'x'")).status_code == 404
    assert _map_error(ErrorEjecucion("algo raro por telnet")).status_code == 502


if __name__ == "__main__":
    tests = [
        test_consulta_contexto_user_no_hace_enable_ni_config,
        test_consulta_contexto_config_si_hace_enable_y_config,
        test_ejecutar_comandos_con_navegacion,
        test_ejecutar_comandos_falla_a_mitad_persiste_parcial_y_lanza,
        test_ejecutar_comandos_cupo_agotado_se_propaga,
        test_credencial_telnet_dedicado_vs_perfil,
        test_schema_consulta_valida_server,
        test_schema_navegacion,
        test_schema_comandos_min_y_sin_vacios,
        test_schema_lote_sin_duplicados,
        test_job_ok_guarda_resultado,
        test_job_handler_error_marca_error,
        test_encolar_tipo_desconocido,
        test_map_error_codigos_http,
    ]
    for t in tests:
        t()
    print(f"tests/test_ejecucion.py: OK ({len(tests)}/{len(tests)})")
