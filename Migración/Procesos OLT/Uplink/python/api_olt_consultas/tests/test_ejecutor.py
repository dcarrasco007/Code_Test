# ============================================================================
# tests/test_ejecutor.py
# Proyecto: api_olt_consultas
# Descripción: utils/ejecutor.py — resolución consulta→comandos, expansión
#              outer/inner (slot→puerto), el 'quit' automático al cambiar de
#              interfaz, persistencia parcial del log si falla a mitad de
#              camino, y la integración con el circuit-breaker del
#              gobernador. Todo con fakes en memoria: nunca una OLT ni una
#              BD reales. Ejecutar: python -m tests.test_ejecutor (desde la
#              raíz del proyecto).
# ============================================================================

import asyncio

import utils.ejecutor as ejecutor
import utils.sesiones_telnet as sesiones_telnet
from utils.ejecutor import ErrorEjecucion, _expandir_lista_fija, _filas_aplicables
from utils.sesiones_telnet import GobernadorSesiones
from utils.telnet_olt import ErrorTelnet, RechazoPorLimiteSesiones


class FakeClienteTelnet:
    """Sustituye a ClienteTelnetOLT: no abre ningún socket. Registra qué se
    le pidió enviar y devuelve texto sintético por comando (o falla en el
    comando N-ésimo, si se configura, para probar la persistencia parcial)."""

    def __init__(self, host, timeout=30, fallar_en_comando=None):
        self.host = host
        self.timeout = timeout
        self.comandos: list = []
        self.cerrado = False
        self._fallar_en_comando = fallar_en_comando
        self._contador = 0

    async def conectar(self, usuario, password):
        self.usuario_conectado = usuario

    async def enable(self):
        pass

    async def config(self):
        pass

    async def enviar_comando(self, comando):
        self._contador += 1
        if self._fallar_en_comando == self._contador:
            raise ErrorTelnet(f"falla simulada en el comando #{self._contador}")
        self.comandos.append(comando)
        return f">>> {comando}\n"

    async def cerrar(self):
        self.cerrado = True


def _fabrica(fallar_en_comando=None):
    creadas = []

    def _crear(host, timeout=30):
        c = FakeClienteTelnet(host, timeout=timeout, fallar_en_comando=fallar_en_comando)
        creadas.append(c)
        return c

    _crear.creadas = creadas
    return _crear


def _filas_uplink_x15():
    return [
        {
            "modelo": "MA5800-X15", "server": None, "orden": 10, "contexto": "interface",
            "interface_tipo": "eth", "comando_template": "interface eth 0/{slot}",
            "enter_extra": 0, "repetir_por": "slot", "fuente_lista": "fijo", "lista_fija": "16,17,18",
        },
        {
            "modelo": "MA5800-X15", "server": None, "orden": 11, "contexto": "interface",
            "interface_tipo": None, "comando_template": "display port traffic {puerto}",
            "enter_extra": 0, "repetir_por": "puerto", "fuente_lista": "OLT_SERVER.pto1_12", "lista_fija": None,
        },
    ]


def _instalar_fakes_catalogo(conteo_puertos_por_slot):
    ejecutor._consulta_por_codigo = lambda engine, codigo: {
        "id": 1, "codigo": codigo, "timeout_seg": 5, "perfil_credencial": "DEFAULT",
        "modo_sync_max_olts": 1, "activo": 1,
    }
    ejecutor._filas_de_comandos = lambda engine, consulta_id, modelo, server: _filas_uplink_x15()
    ejecutor.RESOLVEDORES_LISTA["OLT_SERVER.pto1_12"] = (
        lambda engine, *, lista_fija, server, modelo, slot_actual, **_: [
            str(i) for i in range(conteo_puertos_por_slot.get(slot_actual, 0))
        ]
    )


class FakeRegistroSesiones:
    def __init__(self):
        self.aperturas = []
        self.cierres = []
        self._siguiente_id = 1

    def apertura(self, engine, **kwargs):
        id_ = self._siguiente_id
        self._siguiente_id += 1
        self.aperturas.append(kwargs)
        return id_

    def cierre(self, engine, sesion_id, estado, motivo):
        self.cierres.append((sesion_id, estado, motivo))


def _instalar_fake_gobernador():
    registro = FakeRegistroSesiones()
    sesiones_telnet._registrar_apertura = registro.apertura
    sesiones_telnet._registrar_cierre = registro.cierre
    return registro


_LOGS_PERSISTIDOS = []


def _instalar_fake_persistencia():
    _LOGS_PERSISTIDOS.clear()
    ejecutor._persistir_log = lambda engine, **kwargs: _LOGS_PERSISTIDOS.append(kwargs)


def test_expande_slot_outer_y_puerto_inner_con_quit_entre_slots():
    async def _run():
        _instalar_fakes_catalogo({"16": 2, "17": 1, "18": 0})
        _instalar_fake_gobernador()
        _instalar_fake_persistencia()
        fabrica = _fabrica()

        resultado = await ejecutor.ejecutar_consulta(
            engine="fake", gobernador=GobernadorSesiones(), codigo="uplink_trafico",
            server="OLT-A", ip="10.0.0.1", modelo="MA5800-X15",
            usuario_telnet="geretapi", password_telnet="x",
            fabrica_cliente_telnet=fabrica,
        )

        assert resultado.ok
        assert resultado.comandos_enviados == [
            "interface eth 0/16", "display port traffic 0", "display port traffic 1",
            "quit",
            "interface eth 0/17", "display port traffic 0",
            "quit",
            "interface eth 0/18",
        ]
        assert ">>> interface eth 0/16" in resultado.log_crudo
        assert fabrica.creadas[0].cerrado is True
        assert len(_LOGS_PERSISTIDOS) == 1
        assert _LOGS_PERSISTIDOS[0]["exito"] is True
        assert _LOGS_PERSISTIDOS[0]["comandos_enviados"] == resultado.comandos_enviados

    asyncio.run(_run())


def test_falla_a_mitad_de_camino_persiste_el_log_parcial():
    async def _run():
        _instalar_fakes_catalogo({"16": 2, "17": 1, "18": 0})
        _instalar_fake_gobernador()
        _instalar_fake_persistencia()
        # El comando #2 ("display port traffic 0") falla: el #1 ("interface
        # eth 0/16") sí se alcanzó a mandar y debe seguir en el log.
        fabrica = _fabrica(fallar_en_comando=2)

        try:
            await ejecutor.ejecutar_consulta(
                engine="fake", gobernador=GobernadorSesiones(), codigo="uplink_trafico",
                server="OLT-A", ip="10.0.0.1", modelo="MA5800-X15",
                usuario_telnet="geretapi", password_telnet="x",
                fabrica_cliente_telnet=fabrica,
            )
            assert False, "debió lanzar ErrorEjecucion"
        except ErrorEjecucion:
            pass

        assert len(_LOGS_PERSISTIDOS) == 1
        persistido = _LOGS_PERSISTIDOS[0]
        assert persistido["exito"] is False
        assert persistido["comandos_enviados"] == ["interface eth 0/16"]
        assert "falla simulada" in persistido["error"]
        assert fabrica.creadas[0].cerrado is True  # el cierre siempre corre, aunque falle

    asyncio.run(_run())


def test_rechazo_por_limite_abre_el_circuito():
    async def _run():
        _instalar_fakes_catalogo({"16": 1})
        _instalar_fake_gobernador()
        _instalar_fake_persistencia()

        class ClienteQueRechaza(FakeClienteTelnet):
            async def conectar(self, usuario, password):
                raise RechazoPorLimiteSesiones("cupo agotado en la OLT")

        gob = GobernadorSesiones()
        # F6: RechazoPorLimiteSesiones se propaga TAL CUAL (no envuelto en
        # ErrorEjecucion) para que el router lo mapee a 503; el circuito se
        # abre igual.
        try:
            await ejecutor.ejecutar_consulta(
                engine="fake", gobernador=gob, codigo="uplink_trafico",
                server="OLT-A", ip="10.0.0.1", modelo="MA5800-X15",
                usuario_telnet="geretapi", password_telnet="x",
                fabrica_cliente_telnet=lambda host, timeout=30: ClienteQueRechaza(host, timeout),
            )
            assert False, "debió lanzar RechazoPorLimiteSesiones"
        except RechazoPorLimiteSesiones:
            pass

        assert gob._circuito_abierto_hasta("OLT-A", "geretapi") is not None

    asyncio.run(_run())


def test_consulta_desconocida_lanza_error_ejecucion():
    async def _run():
        ejecutor._consulta_por_codigo = lambda engine, codigo: None
        _instalar_fake_gobernador()
        try:
            await ejecutor.ejecutar_consulta(
                engine="fake", gobernador=GobernadorSesiones(), codigo="no_existe",
                server="OLT-A", ip="10.0.0.1", modelo="MA5800-X15",
                usuario_telnet="geretapi", password_telnet="x",
                fabrica_cliente_telnet=_fabrica(),
            )
            assert False
        except ErrorEjecucion:
            pass

    asyncio.run(_run())


def test_sin_comandos_para_ese_modelo_lanza_error_ejecucion():
    async def _run():
        ejecutor._consulta_por_codigo = lambda engine, codigo: {
            "id": 1, "codigo": codigo, "timeout_seg": 5, "perfil_credencial": "DEFAULT",
            "modo_sync_max_olts": 1, "activo": 1,
        }
        ejecutor._filas_de_comandos = lambda engine, consulta_id, modelo, server: []
        _instalar_fake_gobernador()
        try:
            await ejecutor.ejecutar_consulta(
                engine="fake", gobernador=GobernadorSesiones(), codigo="uplink_trafico",
                server="OLT-A", ip="10.0.0.1", modelo="MA5680T",
                usuario_telnet="geretapi", password_telnet="x",
                fabrica_cliente_telnet=_fabrica(),
            )
            assert False
        except ErrorEjecucion:
            pass

    asyncio.run(_run())


def test_expandir_lista_fija():
    assert _expandir_lista_fija("16,17,18") == ["16", "17", "18"]
    assert _expandir_lista_fija("0-3") == ["0", "1", "2", "3"]
    assert _expandir_lista_fija(None) == []
    assert _expandir_lista_fija("  ") == []
    assert _expandir_lista_fija("solo-uno-con-guion-no-numerico") == ["solo-uno-con-guion-no-numerico"]


def test_filas_aplicables_prioriza_server_sobre_modelo_sobre_general():
    filas = [
        {"server": None, "modelo": None, "orden": 1},
        {"server": None, "modelo": "MA5800-X15", "orden": 2},
        {"server": "OLT-DURZUA-4", "modelo": "MA5800-X15", "orden": 3},
    ]
    assert [f["orden"] for f in _filas_aplicables(filas)] == [3]
    assert [f["orden"] for f in _filas_aplicables(filas[:2])] == [2]
    assert [f["orden"] for f in _filas_aplicables(filas[:1])] == [1]


if __name__ == "__main__":
    tests = [
        test_expande_slot_outer_y_puerto_inner_con_quit_entre_slots,
        test_falla_a_mitad_de_camino_persiste_el_log_parcial,
        test_rechazo_por_limite_abre_el_circuito,
        test_consulta_desconocida_lanza_error_ejecucion,
        test_sin_comandos_para_ese_modelo_lanza_error_ejecucion,
        test_expandir_lista_fija,
        test_filas_aplicables_prioriza_server_sobre_modelo_sobre_general,
    ]
    for t in tests:
        t()
    print(f"tests/test_ejecutor.py: OK ({len(tests)}/{len(tests)})")
