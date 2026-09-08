# ============================================================================
# tests/test_token.py
# Proyecto: api_olt_consultas
# Descripción: utils/token.py — autenticación, scopes y fuerza bruta SIMULADA
#              (nunca toca una base de datos real: las 4 funciones de acceso
#              a datos se reemplazan por un fake en memoria que reproduce el
#              mismo comportamiento de sql/01_tablas_api.sql). Ejecutar:
#              python -m tests.test_token (desde la raíz del proyecto).
# ============================================================================

import asyncio
from datetime import datetime, timedelta
from types import SimpleNamespace

from fastapi import HTTPException

import utils.token as token
from app.config import settings
from utils import seguridad

_ip_actual = "10.0.0.1"


class FakeBD:
    """Reproduce OLT_API_CLIENTES + OLT_API_INTENTOS_AUTH en memoria, con la
    misma semántica que las funciones reales de token.py (ver sql/01_tablas_api.sql
    y los comentarios de _registrar_intento_fallido)."""

    def __init__(self):
        self.clientes = {}   # prefijo -> dict
        self.intentos = {}   # ip -> {"intentos_fallidos", "bloqueado_hasta"}
        self.auditoria = []  # lista de kwargs pasados a auditoria.registrar

    def alta_cliente(self, *, id, nombre, api_key, scopes="lectura", activo=True,
                      ips_permitidas="10.0.0.1", rate_limit_peticiones=60,
                      rate_limit_ventana_seg=60, max_ejecuciones_hora=60):
        prefijo = seguridad.prefijo(api_key)
        self.clientes[prefijo] = {
            "id": id, "nombre": nombre, "api_key_hash": seguridad.hashear(api_key),
            "scopes": scopes, "ips_permitidas": ips_permitidas, "activo": 1 if activo else 0,
            "rate_limit_peticiones": rate_limit_peticiones,
            "rate_limit_ventana_seg": rate_limit_ventana_seg,
            "max_ejecuciones_hora": max_ejecuciones_hora,
        }

    # --- firmas idénticas a las funciones reales de utils/token.py ---
    def buscar_cliente_por_prefijo(self, engine, prefijo):
        return self.clientes.get(prefijo)

    def estado_bloqueo_ip(self, engine, ip):
        fila = self.intentos.get(ip)
        if not fila:
            return None
        bloqueada = fila["bloqueado_hasta"] is not None and fila["bloqueado_hasta"] > datetime.now()
        return {**fila, "bloqueada": bloqueada}

    def registrar_intento_fallido(self, engine, ip, prefijo):
        fila = self.intentos.setdefault(ip, {"intentos_fallidos": 0, "bloqueado_hasta": None})
        vencido = fila["bloqueado_hasta"] is not None and fila["bloqueado_hasta"] <= datetime.now()
        nuevo = 1 if vencido else fila["intentos_fallidos"] + 1
        fila["intentos_fallidos"] = nuevo
        fila["bloqueado_hasta"] = (
            datetime.now() + timedelta(minutes=settings.BLOQUEO_AUTH_MINUTOS)
            if nuevo >= settings.MAX_INTENTOS_AUTH else None
        )

    def limpiar_bloqueo(self, engine, ip):
        self.intentos[ip] = {"intentos_fallidos": 0, "bloqueado_hasta": None}

    def registrar_auditoria(self, engine, **kwargs):
        self.auditoria.append(kwargs)


def _instalar_fake(fakebd: FakeBD):
    """Reemplaza el acceso a datos de utils/token.py por el fake, y acelera
    el retardo fijo de fallos para que los tests no tarden segundos."""
    token.get_engine = lambda: "fake-engine"
    token._buscar_cliente_por_prefijo = fakebd.buscar_cliente_por_prefijo
    token._estado_bloqueo_ip = fakebd.estado_bloqueo_ip
    token._registrar_intento_fallido = fakebd.registrar_intento_fallido
    token._limpiar_bloqueo = fakebd.limpiar_bloqueo
    token.auditoria.registrar = fakebd.registrar_auditoria
    token._RETARDO_FALLO_SEG = 0.0


def _fake_request(ip=_ip_actual, path="/algo"):
    return SimpleNamespace(
        client=SimpleNamespace(host=ip),
        url=SimpleNamespace(path=path),
        state=SimpleNamespace(),
    )


def _espera_401(coro):
    try:
        asyncio.run(coro)
        assert False, "debió lanzar HTTPException 401"
    except HTTPException as exc:
        assert exc.status_code == 401
        return exc


def test_key_correcta_autentica_y_expone_scopes():
    fakebd = FakeBD()
    _instalar_fake(fakebd)
    fakebd.alta_cliente(id=1, nombre="Portal OLT", api_key="clave-correcta-123",
                         scopes="lectura,ejecutar_consulta")

    cliente = asyncio.run(token.verify_api_key(_fake_request(), api_key="clave-correcta-123"))
    assert cliente.id == 1
    assert cliente.tiene_scope("lectura")
    assert cliente.tiene_scope("ejecutar_consulta")
    assert not cliente.tiene_scope("admin")


def test_key_incorrecta_401_generico():
    fakebd = FakeBD()
    _instalar_fake(fakebd)
    fakebd.alta_cliente(id=1, nombre="Portal OLT", api_key="clave-correcta-123")

    exc = _espera_401(token.verify_api_key(_fake_request(), api_key="clave-adivinada"))
    assert exc.detail == token._MENSAJE_401  # no distingue el motivo


def test_sin_header_401():
    fakebd = FakeBD()
    _instalar_fake(fakebd)
    _espera_401(token.verify_api_key(_fake_request(), api_key=None))


def test_ip_no_autorizada_para_ese_cliente_401():
    fakebd = FakeBD()
    _instalar_fake(fakebd)
    fakebd.alta_cliente(id=1, nombre="Portal OLT", api_key="clave-correcta-123",
                         ips_permitidas="192.168.1.50")
    _espera_401(token.verify_api_key(_fake_request(ip="10.0.0.1"), api_key="clave-correcta-123"))


def test_cliente_inactivo_401():
    fakebd = FakeBD()
    _instalar_fake(fakebd)
    fakebd.alta_cliente(id=1, nombre="Portal OLT", api_key="clave-correcta-123", activo=False)
    _espera_401(token.verify_api_key(_fake_request(), api_key="clave-correcta-123"))


def test_fuerza_bruta_bloquea_tras_max_intentos_y_persiste_aunque_la_key_sea_correcta():
    fakebd = FakeBD()
    _instalar_fake(fakebd)
    fakebd.alta_cliente(id=1, nombre="Portal OLT", api_key="clave-correcta-123")

    # MAX_INTENTOS_AUTH fallos consecutivos con la key equivocada.
    for _ in range(settings.MAX_INTENTOS_AUTH):
        _espera_401(token.verify_api_key(_fake_request(), api_key="clave-mala"))

    # La IP ya debe estar bloqueada.
    bloqueo = fakebd.estado_bloqueo_ip(None, _ip_actual)
    assert bloqueo["bloqueada"] is True

    # Y ahora, aunque llegue la key CORRECTA, se sigue rechazando (401) porque
    # la IP está bloqueada — este es el requisito explícito del usuario.
    exc = _espera_401(token.verify_api_key(_fake_request(), api_key="clave-correcta-123"))
    assert "Retry-After" in exc.headers


def test_login_correcto_limpia_el_contador_de_fallos():
    fakebd = FakeBD()
    _instalar_fake(fakebd)
    fakebd.alta_cliente(id=1, nombre="Portal OLT", api_key="clave-correcta-123")

    # Fallos por debajo del umbral (no llega a bloquear).
    for _ in range(max(1, settings.MAX_INTENTOS_AUTH - 2)):
        _espera_401(token.verify_api_key(_fake_request(), api_key="clave-mala"))

    asyncio.run(token.verify_api_key(_fake_request(), api_key="clave-correcta-123"))

    bloqueo = fakebd.estado_bloqueo_ip(None, _ip_actual)
    assert bloqueo["intentos_fallidos"] == 0
    assert bloqueo["bloqueado_hasta"] is None


def test_requerir_scopes_and_semantico():
    cliente_lectura = token.Cliente(id=1, nombre="X", scopes=frozenset({"lectura"}))
    cliente_admin = token.Cliente(id=2, nombre="Y", scopes=frozenset({"lectura", "admin"}))

    dependencia = token.requerir_scopes("admin")
    try:
        asyncio.run(dependencia(cliente=cliente_lectura))
        assert False, "debió lanzar 403 (sin scope admin)"
    except HTTPException as exc:
        assert exc.status_code == 403

    resultado = asyncio.run(dependencia(cliente=cliente_admin))
    assert resultado is cliente_admin

    dependencia_multi = token.requerir_scopes("lectura", "admin")
    try:
        asyncio.run(dependencia_multi(cliente=cliente_lectura))
        assert False, "debió lanzar 403 (le falta 'admin')"
    except HTTPException:
        pass


def test_ip_permitida_pure():
    assert token._ip_permitida("10.0.0.5", "10.0.0.5")
    assert token._ip_permitida("10.0.0.5", "10.0.0.1,10.0.0.5,10.0.0.9")
    assert token._ip_permitida("10.0.0.5", "10.0.0.0/24")
    assert not token._ip_permitida("10.0.1.5", "10.0.0.0/24")
    assert not token._ip_permitida("10.0.0.5", None)
    assert not token._ip_permitida("10.0.0.5", "")
    assert not token._ip_permitida("10.0.0.5", "entrada-invalida, 10.0.0.9")  # ignora la mala, sigue evaluando
    assert token._ip_permitida("10.0.0.9", "entrada-invalida, 10.0.0.9")


if __name__ == "__main__":
    tests = [
        test_key_correcta_autentica_y_expone_scopes,
        test_key_incorrecta_401_generico,
        test_sin_header_401,
        test_ip_no_autorizada_para_ese_cliente_401,
        test_cliente_inactivo_401,
        test_fuerza_bruta_bloquea_tras_max_intentos_y_persiste_aunque_la_key_sea_correcta,
        test_login_correcto_limpia_el_contador_de_fallos,
        test_requerir_scopes_and_semantico,
        test_ip_permitida_pure,
    ]
    for t in tests:
        t()
    print(f"tests/test_token.py: OK ({len(tests)}/{len(tests)})")
