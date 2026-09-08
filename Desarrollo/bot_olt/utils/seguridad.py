"""Hash y verificacion de la clave propia del bot.

Usa PBKDF2-SHA256 de `hashlib`, de la libreria estandar: no agrega ninguna
dependencia y funciona en cualquier Python 3.9 a 3.12, igual que el resto del
proyecto.

Esta clave es independiente de la del portal OLT. Por eso no hay obligacion de
usar MD5 aqui: es una credencial nueva y se guarda con un algoritmo apto para
contrasenas, con salt distinto por usuario.

Formato almacenado:

    pbkdf2_sha256$<iteraciones>$<salt_b64>$<hash_b64>

Las iteraciones viajan dentro del string, asi que subirlas mas adelante no
invalida las claves ya existentes: cada una se verifica con las suyas.
"""

import base64
import hashlib
import hmac
import os
from typing import Optional

ALGORITMO = "pbkdf2_sha256"
ITERACIONES = 200_000
LARGO_SALT = 16

# Reglas de la clave del bot.
LARGO_MINIMO = 6
LARGO_MAXIMO = 64


def _b64(datos: bytes) -> str:
    return base64.b64encode(datos).decode("ascii")


def _des_b64(texto: str) -> bytes:
    return base64.b64decode(texto.encode("ascii"))


def hashear(clave: str, iteraciones: int = ITERACIONES) -> str:
    """Genera el hash con un salt aleatorio nuevo.

    Es una operacion deliberadamente costosa (unos cientos de milisegundos):
    llamarla siempre desde `asyncio.to_thread` para no bloquear el bot.
    """
    salt = os.urandom(LARGO_SALT)
    derivado = hashlib.pbkdf2_hmac("sha256", clave.encode("utf-8"), salt, iteraciones)
    return "%s$%d$%s$%s" % (ALGORITMO, iteraciones, _b64(salt), _b64(derivado))


def verificar(clave: str, almacenado: Optional[str]) -> bool:
    """Compara la clave contra el hash guardado.

    La comparacion final usa `hmac.compare_digest`, que tarda lo mismo acierte
    o no: comparar con `==` filtraria informacion por el tiempo de respuesta.
    """
    if not clave or not almacenado:
        return False

    try:
        algoritmo, iteraciones, salt_b64, hash_b64 = almacenado.split("$")
        if algoritmo != ALGORITMO:
            return False

        derivado = hashlib.pbkdf2_hmac(
            "sha256", clave.encode("utf-8"), _des_b64(salt_b64), int(iteraciones)
        )
        return hmac.compare_digest(derivado, _des_b64(hash_b64))
    except (ValueError, TypeError):
        # Hash con formato invalido o corrupto: se trata como clave incorrecta.
        return False


def validar_clave_nueva(clave: str, clave_inicial: str = "") -> Optional[str]:
    """Valida una clave que el usuario quiere establecer.

    Retorna None si es aceptable, o el motivo del rechazo para mostrarselo.
    """
    clave = clave or ""

    if len(clave) < LARGO_MINIMO:
        return "La clave debe tener al menos %d caracteres." % LARGO_MINIMO
    if len(clave) > LARGO_MAXIMO:
        return "La clave no puede superar los %d caracteres." % LARGO_MAXIMO
    if clave.strip() != clave or " " in clave:
        return "La clave no puede contener espacios."
    if clave_inicial and clave == clave_inicial:
        return "No puedes dejar la clave inicial: elige una distinta."

    return None
