"""Pruebas de los validadores y del control de abuso.

No tocan la base de datos: se pueden correr en cualquier maquina.

Uso:
    python -m tests.test_validadores
"""

import time

from utils import limites, seguridad
from utils.func import a_entero, validar_fecha, validar_ip, validar_texto

_fallos = []


def check(condicion, descripcion):
    estado = "OK  " if condicion else "FALLA"
    print(f"  [{estado}] {descripcion}")
    if not condicion:
        _fallos.append(descripcion)


def test_validar_ip():
    print("validar_ip")
    check(validar_ip("10.99.24.68") == "10.99.24.68", "IPv4 valida")
    check(validar_ip("  10.99.24.68  ") == "10.99.24.68", "recorta espacios")
    check(validar_ip("::1") == "::1", "IPv6 valida")
    check(validar_ip("10.99.24.999") is None, "octeto fuera de rango")
    check(validar_ip("10.99.24") is None, "faltan octetos")
    check(validar_ip("10.99.24.68.1") is None, "sobran octetos")
    check(validar_ip("no soy una ip") is None, "texto cualquiera")
    check(validar_ip("") is None, "vacio")
    check(validar_ip(None) is None, "None")
    check(validar_ip("'; DROP TABLE x; --") is None, "intento de inyeccion")


def test_validar_fecha():
    print("validar_fecha")
    check(validar_fecha("2026-08-10") == "2026-08-10", "fecha explicita")
    check(validar_fecha("HOY") is not None, "atajo hoy, sin importar mayusculas")
    check(validar_fecha("ayer") is not None, "atajo ayer")
    check(validar_fecha("10-08-2026") is None, "formato invertido")
    check(validar_fecha("2026-13-01") is None, "mes inexistente")
    check(validar_fecha("bla") is None, "texto cualquiera")


def test_validar_texto():
    print("validar_texto")
    check(validar_texto("jperez") == "jperez", "nombre normal")
    check(validar_texto("   jperez  ") == "jperez", "recorta espacios")
    check(validar_texto("") is None, "vacio")
    check(validar_texto("   ") is None, "solo espacios")
    check(validar_texto("x" * 101) is None, "excede el largo por defecto")
    check(validar_texto("x" * 50, largo_maximo=10) is None, "excede el largo indicado")


def test_a_entero():
    print("a_entero")
    check(a_entero("96") == 96, "texto numerico")
    check(a_entero(96) == 96, "entero")
    check(a_entero(None) == 0, "None cae a cero")
    check(a_entero("") == 0, "vacio cae a cero")
    check(a_entero("abc") == 0, "texto no numerico cae a cero")


def test_cache_auth():
    print("cache de autorizacion")
    limites.invalidar_auth()

    hay, _ = limites.auth_en_cache(111)
    check(not hay, "cache vacio no reporta dato")

    limites.guardar_auth(111, {"usuario": "jperez"})
    hay, usuario = limites.auth_en_cache(111)
    check(hay and usuario["usuario"] == "jperez", "guarda y recupera un autorizado")

    # El caso importante: tambien se cachea el negativo.
    limites.guardar_auth(222, None)
    hay, usuario = limites.auth_en_cache(222)
    check(hay and usuario is None, "cachea tambien el resultado negativo")

    limites.invalidar_auth(111)
    hay, _ = limites.auth_en_cache(111)
    check(not hay, "invalidar borra la entrada")


def test_rate_limit():
    print("limite de peticiones")
    limites._peticiones.clear()
    limites._avisados.clear()

    chat = 999
    permitidas = 0
    for _ in range(limites.RATE_LIMIT_PETICIONES + 5):
        ok, _ = limites.permitir(chat)
        if ok:
            permitidas += 1

    check(
        permitidas == limites.RATE_LIMIT_PETICIONES,
        f"deja pasar exactamente {limites.RATE_LIMIT_PETICIONES} y bloquea el resto",
    )

    _, avisar_primero = limites.permitir(chat)
    check(not avisar_primero, "no vuelve a avisar tras el primer rechazo")
    check(limites.segundos_para_reintentar(chat) > 0, "informa cuanto falta")

    ok, _ = limites.permitir(1000)
    check(ok, "el limite es por chat_id, no global")


def test_silenciado():
    print("silenciado de no autorizados")
    limites._intentos.clear()
    chat = 777

    respuestas = [limites.registrar_no_autorizado(chat)
                  for _ in range(limites.MAX_INTENTOS_NO_AUTORIZADO)]

    check(respuestas[0] is True, "responde el primer intento")
    check(respuestas[-1] is False, "deja de responder al alcanzar el tope")
    check(limites.esta_silenciado(chat), "queda silenciado")
    check(not limites.esta_silenciado(888), "el silencio no afecta a otros chats")

    limites.limpiar_intentos(chat)
    check(not limites.esta_silenciado(chat), "autorizarse limpia el silencio")


def test_seguridad():
    print("clave del bot (PBKDF2)")
    # Iteraciones bajas solo para que la prueba sea rapida.
    h = seguridad.hashear("miClave123", iteraciones=1000)

    check(seguridad.verificar("miClave123", h), "verifica la clave correcta")
    check(not seguridad.verificar("miClave124", h), "rechaza una clave distinta")
    check(not seguridad.verificar("", h), "rechaza clave vacia")
    check(not seguridad.verificar("miClave123", None), "rechaza hash nulo")
    check(not seguridad.verificar("x", "formato-invalido"), "rechaza hash corrupto")
    check(not seguridad.verificar("x", "md5$1$a$b"), "rechaza otro algoritmo")

    h2 = seguridad.hashear("miClave123", iteraciones=1000)
    check(h != h2, "dos hashes de la misma clave difieren (salt distinto)")
    check(seguridad.verificar("miClave123", h2), "ambos hashes verifican igual")

    check(h.startswith("pbkdf2_sha256$1000$"), "el formato incluye algoritmo e iteraciones")

    print("politica de claves")
    check(seguridad.validar_clave_nueva("claveSegura") is None, "clave valida")
    check(seguridad.validar_clave_nueva("abc") is not None, "rechaza demasiado corta")
    check(seguridad.validar_clave_nueva("x" * 80) is not None, "rechaza demasiado larga")
    check(seguridad.validar_clave_nueva("con espacio") is not None, "rechaza espacios")
    check(seguridad.validar_clave_nueva("123456", "123456") is not None,
          "rechaza mantener la clave inicial")
    check(seguridad.validar_clave_nueva("123456") is None,
          "acepta 123456 si no se declara como inicial")


if __name__ == "__main__":
    for prueba in (
        test_validar_ip,
        test_validar_fecha,
        test_validar_texto,
        test_a_entero,
        test_seguridad,
        test_cache_auth,
        test_rate_limit,
        test_silenciado,
    ):
        prueba()

    print()
    if _fallos:
        print(f"{len(_fallos)} prueba(s) fallaron:")
        for f in _fallos:
            print(f"  - {f}")
        raise SystemExit(1)
    print("Todas las pruebas pasaron.")
