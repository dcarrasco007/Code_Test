# ============================================================================
# utils/seguridad.py
# Proyecto: api_olt_consultas
# Descripción: Generación y verificación de API keys. Usa PBKDF2-SHA256 de
#              hashlib (librería estándar, sin dependencias nuevas), mismo
#              patrón que Desarrollo/bot_olt/utils/seguridad.py.
#
#              La API key completa NUNCA se guarda en BD: solo su hash y un
#              prefijo no secreto (para ubicar el cliente sin recorrer todos
#              los hashes). Formato guardado:
#                  pbkdf2_sha256$<iteraciones>$<salt_b64>$<hash_b64>
#
#              Las iteraciones viajan dentro del string, así que subirlas más
#              adelante no invalida las keys ya existentes.
#
# Para modificar:
#   - Iteraciones / largo de la key / largo del prefijo → [CONFIG] abajo
# ============================================================================

import base64
import hashlib
import hmac
import os
import secrets
from typing import Optional

ALGORITMO = "pbkdf2_sha256"
ITERACIONES = 200_000  # [CONFIG] mismo valor que bot_olt
LARGO_SALT = 16

LARGO_KEY_BYTES = 32  # [CONFIG] 32 bytes de entropía -> ~43 caracteres url-safe
LARGO_PREFIJO = 10    # [CONFIG] caracteres del prefijo NO secreto (indexable en BD)


def _b64(datos: bytes) -> str:
    return base64.b64encode(datos).decode("ascii")


def _des_b64(texto: str) -> bytes:
    return base64.b64decode(texto.encode("ascii"))


def generar_api_key() -> str:
    """Genera una API key nueva, criptográficamente aleatoria.

    Se muestra al responsable UNA SOLA VEZ (ver scripts/crear_cliente.py).
    A partir de ahí solo existe su hash en la base de datos.
    """
    return secrets.token_urlsafe(LARGO_KEY_BYTES)


def prefijo(api_key: str) -> str:
    """Prefijo NO secreto de la key, para ubicar el cliente sin recorrer hashes."""
    return api_key[:LARGO_PREFIJO]


def hashear(api_key: str, iteraciones: int = ITERACIONES) -> str:
    """Genera el hash con un salt aleatorio nuevo.

    Operación deliberadamente costosa (cientos de ms): en FastAPI, llamarla
    desde un endpoint async debe hacerse vía run_in_threadpool (ver F2,
    utils/token.py) para no bloquear el event loop.
    """
    salt = os.urandom(LARGO_SALT)
    derivado = hashlib.pbkdf2_hmac("sha256", api_key.encode("utf-8"), salt, iteraciones)
    return "%s$%d$%s$%s" % (ALGORITMO, iteraciones, _b64(salt), _b64(derivado))


def verificar(api_key: str, almacenado: Optional[str]) -> bool:
    """Compara la key contra el hash guardado, en tiempo constante.

    [SEGURIDAD] hmac.compare_digest evita filtrar información por el tiempo
    de respuesta (a diferencia de comparar con '==').
    """
    if not api_key or not almacenado:
        return False
    try:
        algoritmo, iteraciones, salt_b64, hash_b64 = almacenado.split("$")
        if algoritmo != ALGORITMO:
            return False
        derivado = hashlib.pbkdf2_hmac(
            "sha256", api_key.encode("utf-8"), _des_b64(salt_b64), int(iteraciones)
        )
        return hmac.compare_digest(derivado, _des_b64(hash_b64))
    except (ValueError, TypeError):
        # Hash con formato inválido o corrupto: se trata como key incorrecta.
        return False
