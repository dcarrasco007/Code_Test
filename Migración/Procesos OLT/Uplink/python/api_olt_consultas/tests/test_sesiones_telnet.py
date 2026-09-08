# ============================================================================
# tests/test_sesiones_telnet.py
# Proyecto: api_olt_consultas
# Descripción: El gobernador de cupo (utils/sesiones_telnet.py) — cupo por
#              (OLT, usuario), cola con timeout, circuit-breaker, y que el
#              registro en BD se llama con los datos correctos. Las 3
#              funciones de acceso a datos se reemplazan por un fake en
#              memoria (nunca toca una base real). Ejecutar:
#              python -m tests.test_sesiones_telnet (desde la raíz).
# ============================================================================

import asyncio
from types import SimpleNamespace

import utils.sesiones_telnet as sesiones_telnet
from app.config import settings


class FakeRegistro:
    """Reemplaza _registrar_apertura/_registrar_cierre/_registrar_latido."""

    def __init__(self):
        self.aperturas = []
        self.cierres = []
        self.latidos = []
        self._siguiente_id = 1

    def apertura(self, engine, **kwargs):
        id_ = self._siguiente_id
        self._siguiente_id += 1
        self.aperturas.append((id_, kwargs))
        return id_

    def cierre(self, engine, sesion_id, estado, motivo):
        self.cierres.append((sesion_id, estado, motivo))

    def latido(self, engine, sesion_id):
        self.latidos.append(sesion_id)


def _instalar_fake(registro: FakeRegistro) -> None:
    sesiones_telnet._registrar_apertura = registro.apertura
    sesiones_telnet._registrar_cierre = registro.cierre
    sesiones_telnet._registrar_latido = registro.latido


def test_adquirir_registra_apertura_y_cierre_normal():
    async def _run():
        registro = FakeRegistro()
        _instalar_fake(registro)
        gob = sesiones_telnet.GobernadorSesiones()

        async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake") as sesion_id:
            assert sesion_id == 1
            assert registro.aperturas[0][1]["olt"] == "OLT-A"
            assert registro.aperturas[0][1]["usuario_telnet"] == "geretapi"

        assert registro.cierres == [(1, "CERRADA", None)]

    asyncio.run(_run())


def test_error_dentro_del_bloque_se_registra_y_libera_el_cupo_igual():
    async def _run():
        registro = FakeRegistro()
        _instalar_fake(registro)
        gob = sesiones_telnet.GobernadorSesiones()

        try:
            async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake"):
                raise RuntimeError("boom")
        except RuntimeError:
            pass

        sesion_id, estado, motivo = registro.cierres[-1]
        assert estado == "CERRADA"
        assert "boom" in (motivo or "")

        # El cupo se liberó a pesar del error: se puede adquirir de inmediato.
        async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake"):
            pass

    asyncio.run(_run())


def test_respeta_cupo_de_3_y_la_4ta_agota_la_espera():
    async def _run():
        registro = FakeRegistro()
        _instalar_fake(registro)
        gob = sesiones_telnet.GobernadorSesiones()

        original_dedicado = settings.OLT_TELNET_API_DEDICADO
        original_espera = settings.ESPERA_MAX_SESION_SEG
        settings.OLT_TELNET_API_DEDICADO = True  # cupo = 3 (ver settings.cupo_por_olt_usuario)
        settings.ESPERA_MAX_SESION_SEG = 0.1
        try:
            entraron = []

            async def _tomar_y_mantener(indice):
                async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake"):
                    entraron.append(indice)
                    await asyncio.sleep(0.3)

            tareas = [asyncio.create_task(_tomar_y_mantener(i)) for i in range(3)]
            await asyncio.sleep(0.05)
            assert len(entraron) == 3  # las 3 primeras entraron sin esperar

            try:
                async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake"):
                    assert False, "no debía conseguir cupo: las 3 sesiones están ocupadas"
            except sesiones_telnet.CupoAgotadoError:
                pass

            await asyncio.gather(*tareas)
            assert len(registro.cierres) == 3
        finally:
            settings.OLT_TELNET_API_DEDICADO = original_dedicado
            settings.ESPERA_MAX_SESION_SEG = original_espera

    asyncio.run(_run())


def test_cupo_es_independiente_por_olt_y_por_usuario():
    async def _run():
        registro = FakeRegistro()
        _instalar_fake(registro)
        gob = sesiones_telnet.GobernadorSesiones()

        original_espera = settings.ESPERA_MAX_SESION_SEG
        settings.ESPERA_MAX_SESION_SEG = 0.1
        try:
            async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake"):
                # Cupo lleno para (OLT-A, geretapi) con cupo=1 (sin usuario
                # dedicado, default de settings.cupo_por_olt_usuario()).
                try:
                    async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake"):
                        assert False
                except sesiones_telnet.CupoAgotadoError:
                    pass

                # Otra OLT: cupo propio, no se ve afectada.
                async with gob.adquirir(olt="OLT-B", ip="10.0.0.2", usuario="geretapi", engine="fake"):
                    pass

                # Mismo OLT, otro usuario: cupo propio también.
                async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geret2016", engine="fake"):
                    pass
        finally:
            settings.ESPERA_MAX_SESION_SEG = original_espera

    asyncio.run(_run())


def test_circuito_abierto_bloquea_reintentos_por_clave():
    async def _run():
        registro = FakeRegistro()
        _instalar_fake(registro)
        gob = sesiones_telnet.GobernadorSesiones()

        gob.abrir_circuito("OLT-A", "geretapi")
        try:
            async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake"):
                assert False, "no debía entrar: circuito abierto"
        except sesiones_telnet.CircuitoAbiertoError as exc:
            assert exc.segundos_restantes > 0
            assert exc.olt == "OLT-A" and exc.usuario == "geretapi"

        # Otra clave (OLT u usuario distinto) no se ve afectada.
        async with gob.adquirir(olt="OLT-B", ip="10.0.0.2", usuario="geretapi", engine="fake"):
            pass
        async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="otro_usuario", engine="fake"):
            pass

    asyncio.run(_run())


def test_latido_llama_al_registro():
    async def _run():
        registro = FakeRegistro()
        _instalar_fake(registro)
        gob = sesiones_telnet.GobernadorSesiones()

        async with gob.adquirir(olt="OLT-A", ip="10.0.0.1", usuario="geretapi", engine="fake") as sesion_id:
            await gob.latido("fake", sesion_id)

        assert registro.latidos == [1]

    asyncio.run(_run())


class _FakeConn:
    def __init__(self, rowcount):
        self._rowcount = rowcount

    def execute(self, stmt, params=None):
        return SimpleNamespace(rowcount=self._rowcount)

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        return False


class _FakeEngine:
    def __init__(self, rowcount):
        self._rowcount = rowcount

    def begin(self):
        return _FakeConn(self._rowcount)


def test_marcar_huerfanas_al_iniciar_devuelve_filas_afectadas():
    assert sesiones_telnet.marcar_huerfanas_al_iniciar(_FakeEngine(rowcount=2)) == 2
    assert sesiones_telnet.marcar_huerfanas_al_iniciar(_FakeEngine(rowcount=0)) == 0


if __name__ == "__main__":
    tests = [
        test_adquirir_registra_apertura_y_cierre_normal,
        test_error_dentro_del_bloque_se_registra_y_libera_el_cupo_igual,
        test_respeta_cupo_de_3_y_la_4ta_agota_la_espera,
        test_cupo_es_independiente_por_olt_y_por_usuario,
        test_circuito_abierto_bloquea_reintentos_por_clave,
        test_latido_llama_al_registro,
        test_marcar_huerfanas_al_iniciar_devuelve_filas_afectadas,
    ]
    for t in tests:
        t()
    print(f"tests/test_sesiones_telnet.py: OK ({len(tests)}/{len(tests)})")
