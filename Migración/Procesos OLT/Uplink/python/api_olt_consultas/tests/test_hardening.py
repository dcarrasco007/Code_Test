# ============================================================================
# tests/test_hardening.py
# Proyecto: api_olt_consultas
# Descripción: F8 — endurecimiento HTTP: cabeceras de seguridad, límite de
#              tamaño del body, request-id, handler de error genérico (sin
#              filtrar trazas) y tope del log crudo persistido.
#              Sin httpx: se conduce la app ASGI a mano (no requiere lifespan
#              ni BD para /health).
#              Ejecutar: python -m tests.test_hardening (desde la raíz).
# ============================================================================

import asyncio
from types import SimpleNamespace

import main
from app.config import settings
from utils.ejecutor import _recortar_log


# ─── Mini cliente ASGI (evita depender de httpx / TestClient) ───────────────

def asgi(method: str, path: str, headers=None, body: bytes = b""):
    hdrs = [(k.lower().encode(), v.encode()) for k, v in (headers or {}).items()]
    scope = {
        "type": "http", "http_version": "1.1", "method": method, "path": path,
        "raw_path": path.encode(), "query_string": b"", "headers": hdrs,
        "client": ("127.0.0.1", 5555), "server": ("127.0.0.1", 5001), "scheme": "http",
    }
    estado = {"enviado": False}

    async def receive():
        if estado["enviado"]:
            return {"type": "http.disconnect"}
        estado["enviado"] = True
        return {"type": "http.request", "body": body, "more_body": False}

    salida = {"status": None, "headers": {}, "body": b""}

    async def send(msg):
        if msg["type"] == "http.response.start":
            salida["status"] = msg["status"]
            salida["headers"] = {k.decode().lower(): v.decode() for k, v in msg["headers"]}
        elif msg["type"] == "http.response.body":
            salida["body"] += msg.get("body", b"")

    asyncio.run(main.app(scope, receive, send))
    return salida


_CABECERAS = ("x-content-type-options", "x-frame-options", "referrer-policy",
              "cache-control", "content-security-policy", "x-request-id")


def test_health_lleva_cabeceras_de_seguridad():
    r = asgi("GET", "/health")
    assert r["status"] == 200
    for h in _CABECERAS:
        assert h in r["headers"], f"falta la cabecera {h}"
    assert r["headers"]["x-content-type-options"] == "nosniff"
    assert r["headers"]["cache-control"] == "no-store"


def test_body_gigante_es_413():
    grande = str(settings.MAX_BODY_BYTES + 1)
    r = asgi("POST", "/ejecutar/comandos", headers={"content-length": grande},
             body=b"x")
    assert r["status"] == 413
    assert b"request_id" in r["body"]
    assert r["headers"]["x-content-type-options"] == "nosniff"


def test_request_id_se_respeta_si_viene_en_el_header():
    r = asgi("GET", "/health", headers={"x-request-id": "abc123"})
    assert r["headers"]["x-request-id"] == "abc123"


def test_recortar_log_trunca_por_encima_del_tope():
    original = settings.MAX_LOG_CRUDO_BYTES
    settings.MAX_LOG_CRUDO_BYTES = 100
    try:
        corto = _recortar_log("a" * 50)
        largo = _recortar_log("b" * 500)
        assert corto == "a" * 50
        assert largo.startswith("b" * 100)
        assert "recortado" in largo
        assert len(largo) < 500
    finally:
        settings.MAX_LOG_CRUDO_BYTES = original


def test_handler_error_no_filtra_traza():
    from main import _error_no_controlado

    req = SimpleNamespace(state=SimpleNamespace(request_id="rid-1"),
                          method="GET", url=SimpleNamespace(path="/x"))
    resp = asyncio.run(_error_no_controlado(req, ValueError("detalle interno secreto")))
    assert resp.status_code == 500
    cuerpo = bytes(resp.body).decode()
    assert "detalle interno secreto" not in cuerpo
    assert "error interno del servidor" in cuerpo
    assert "rid-1" in cuerpo


def test_middleware_auditoria_audita_un_500():
    from main import middleware_auditoria

    auditado = []

    class FakeReq:
        def __init__(self):
            self.state = SimpleNamespace(cliente=SimpleNamespace(id=7))
            self.url = SimpleNamespace(path="/ejecutar/consulta/fan")
            self.client = SimpleNamespace(host="10.0.0.9")

    async def call_next_que_revienta(_req):
        raise RuntimeError("boom en el endpoint")

    import utils.auditoria as auditoria_mod
    orig = auditoria_mod.registrar
    auditoria_mod.registrar = lambda engine, **kw: auditado.append(kw)
    # get_engine se llama dentro; con .env vacío haría sys.exit → lo neutralizamos.
    import app.config.db as db_mod
    orig_engine = db_mod.get_engine
    db_mod.get_engine = lambda: object()
    import main as main_mod
    main_mod.get_engine = db_mod.get_engine
    try:
        try:
            asyncio.run(middleware_auditoria(FakeReq(), call_next_que_revienta))
            raise AssertionError("debió re-lanzar la excepción del endpoint")
        except RuntimeError:
            pass
        assert auditado and auditado[0]["accion"] == "RECHAZADO"
        assert auditado[0]["resultado"] == "ERROR"
    finally:
        auditoria_mod.registrar = orig
        db_mod.get_engine = orig_engine
        main_mod.get_engine = orig_engine


if __name__ == "__main__":
    tests = [
        test_health_lleva_cabeceras_de_seguridad,
        test_body_gigante_es_413,
        test_request_id_se_respeta_si_viene_en_el_header,
        test_recortar_log_trunca_por_encima_del_tope,
        test_handler_error_no_filtra_traza,
        test_middleware_auditoria_audita_un_500,
    ]
    for t in tests:
        t()
    print(f"tests/test_hardening.py: OK ({len(tests)}/{len(tests)})")
