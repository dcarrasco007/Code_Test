"""Control de abuso: cache de autorizacion, limite de peticiones y silenciado.

El bot es publico —cualquiera que conozca su username puede escribirle—, asi
que sin estos controles cada mensaje recibido, autorizado o no, se traduce en
una consulta contra la base de produccion.

Todo el estado vive en memoria del proceso: se pierde al reiniciar el bot, que
es el comportamiento deseado (los contadores no deben sobrevivir un deploy).
Los handlers de python-telegram-bot corren en un unico event loop, por lo que
no hace falta sincronizacion entre hilos.

Los valores se configuran por .env; los defaults son conservadores.
"""

import os
import time
from typing import Optional


def _entero_env(nombre: str, default: int) -> int:
    try:
        return int(os.getenv(nombre, default))
    except (TypeError, ValueError):
        return default


# Segundos que se recuerda el resultado de verificar un chat_id.
AUTH_CACHE_TTL = _entero_env("AUTH_CACHE_TTL", 60)

# Peticiones permitidas por chat_id dentro de la ventana.
RATE_LIMIT_PETICIONES = _entero_env("RATE_LIMIT_PETICIONES", 20)
RATE_LIMIT_VENTANA = _entero_env("RATE_LIMIT_VENTANA", 60)

# Intentos de un no autorizado antes de dejar de responderle.
MAX_INTENTOS_NO_AUTORIZADO = _entero_env("MAX_INTENTOS_NO_AUTORIZADO", 3)
SILENCIO_NO_AUTORIZADO = _entero_env("SILENCIO_NO_AUTORIZADO", 300)

# Tope de chat_id distintos que se recuerdan, para que los diccionarios no
# crezcan sin limite si alguien escribe desde muchas cuentas.
_MAX_ENTRADAS = 5000


# =============================================================
# Cache de autorizacion
# =============================================================
#
# Se cachean tambien los resultados negativos: de lo contrario, quien no esta
# autorizado seguiria generando una query por cada mensaje que envie, que es
# justamente el vector que se quiere cerrar.

_cache_auth: dict = {}  # chat_id -> (expira_en, usuario | None)


def auth_en_cache(chat_id: int) -> tuple:
    """Retorna (hay_dato, usuario). `usuario` es None si no esta autorizado."""
    entrada = _cache_auth.get(chat_id)
    if not entrada:
        return False, None

    expira_en, usuario = entrada
    if time.monotonic() > expira_en:
        _cache_auth.pop(chat_id, None)
        return False, None

    return True, usuario


def guardar_auth(chat_id: int, usuario: Optional[dict]) -> None:
    """Guarda el resultado de la verificacion (positivo o negativo)."""
    if len(_cache_auth) >= _MAX_ENTRADAS:
        _purgar_cache()
    _cache_auth[chat_id] = (time.monotonic() + AUTH_CACHE_TTL, usuario)


def invalidar_auth(chat_id: Optional[int] = None) -> None:
    """Olvida lo cacheado de un chat, o de todos si no se indica cual.

    Util despues de dar de alta o revocar a alguien en OLT_BOT_USUARIOS, para
    no esperar a que expire el TTL.
    """
    if chat_id is None:
        _cache_auth.clear()
    else:
        _cache_auth.pop(chat_id, None)


def _purgar_cache() -> None:
    ahora = time.monotonic()
    vencidos = [k for k, (expira, _) in _cache_auth.items() if ahora > expira]
    for k in vencidos:
        _cache_auth.pop(k, None)
    if len(_cache_auth) >= _MAX_ENTRADAS:  # todos vigentes: se vacia entero
        _cache_auth.clear()


# =============================================================
# Limite de peticiones por chat
# =============================================================

_peticiones: dict = {}  # chat_id -> [timestamps]
_avisados: set = set()  # chat_id a los que ya se les aviso en esta ventana


def permitir(chat_id: int) -> tuple:
    """Ventana deslizante por chat_id.

    Retorna (permitido, avisar). `avisar` es True solo en el primer rechazo
    de la ventana, para no responderle un mensaje a cada intento de quien
    esta haciendo spam.
    """
    ahora = time.monotonic()
    desde = ahora - RATE_LIMIT_VENTANA

    marcas = [t for t in _peticiones.get(chat_id, []) if t > desde]

    if len(marcas) >= RATE_LIMIT_PETICIONES:
        _peticiones[chat_id] = marcas
        avisar = chat_id not in _avisados
        _avisados.add(chat_id)
        return False, avisar

    marcas.append(ahora)
    if len(_peticiones) >= _MAX_ENTRADAS:
        _purgar_peticiones(desde)
    _peticiones[chat_id] = marcas
    _avisados.discard(chat_id)
    return True, False


def _purgar_peticiones(desde: float) -> None:
    vacios = [k for k, v in _peticiones.items() if not [t for t in v if t > desde]]
    for k in vacios:
        _peticiones.pop(k, None)
        _avisados.discard(k)
    if len(_peticiones) >= _MAX_ENTRADAS:
        _peticiones.clear()
        _avisados.clear()


def segundos_para_reintentar(chat_id: int) -> int:
    """Cuanto falta para que se libere el cupo del chat."""
    marcas = _peticiones.get(chat_id)
    if not marcas:
        return 0
    return max(0, int(RATE_LIMIT_VENTANA - (time.monotonic() - min(marcas))) + 1)


# =============================================================
# Silenciado de no autorizados
# =============================================================
#
# A quien no esta autorizado se le responde un par de veces indicandole su
# chat_id (que es como solicita el acceso). Pasado ese margen se deja de
# responder por un rato: insistir deja de tener efecto y de costar recursos.

_intentos: dict = {}  # chat_id -> (cantidad, silenciado_hasta)


def registrar_no_autorizado(chat_id: int) -> bool:
    """Suma un intento fallido. Retorna True si aun corresponde responderle."""
    cantidad, _ = _intentos.get(chat_id, (0, 0.0))
    cantidad += 1

    if cantidad >= MAX_INTENTOS_NO_AUTORIZADO:
        _intentos[chat_id] = (cantidad, time.monotonic() + SILENCIO_NO_AUTORIZADO)
        return False

    _intentos[chat_id] = (cantidad, 0.0)
    return True


def esta_silenciado(chat_id: int) -> bool:
    """True si a este chat no se le responde nada por ahora."""
    entrada = _intentos.get(chat_id)
    if not entrada:
        return False

    _, hasta = entrada
    if hasta and time.monotonic() < hasta:
        return True

    if hasta:  # cumplio el castigo: se le da otra oportunidad
        _intentos.pop(chat_id, None)
    return False


def limpiar_intentos(chat_id: int) -> None:
    """Se llama cuando el chat pasa a estar autorizado."""
    _intentos.pop(chat_id, None)
