"""Logica de negocio del login propio del bot.

La clave del bot es independiente de la del portal OLT: la del portal nunca
viaja por Telegram. Se guarda con PBKDF2-SHA256 y salt (ver utils/seguridad.py).

El login solo pide la CLAVE, no el usuario: el chat_id ya identifica a la
persona a traves de OLT_BOT_USUARIOS.

Estados posibles de un usuario ya autorizado por chat_id:

    SIN_CLAVE        -> nunca se le asigno clave; debe pedirsela a un admin
    BLOQUEADO        -> supero los intentos fallidos; espera unos minutos
    REQUIERE_LOGIN   -> tiene clave pero no hay sesion vigente
    REQUIERE_CAMBIO  -> la sesion esta abierta, pero la clave es temporal
    ACTIVO           -> puede operar
"""

import asyncio
import os
from typing import Optional

from loguru import logger

from robot.model import consultas
from utils import seguridad

# Estados
SIN_CLAVE = "sin_clave"
BLOQUEADO = "bloqueado"
REQUIERE_LOGIN = "requiere_login"
REQUIERE_CAMBIO = "requiere_cambio"
ACTIVO = "activo"

# Clave con la que un administrador da de alta a alguien. El bot obliga a
# cambiarla en el primer ingreso.
CLAVE_INICIAL = "123456"


def _entero_env(nombre: str, default: int) -> int:
    try:
        return int(os.getenv(nombre, default))
    except (TypeError, ValueError):
        return default


def horas_sesion() -> int:
    return _entero_env("SESION_HORAS", 12)


def max_intentos() -> int:
    return _entero_env("MAX_INTENTOS_LOGIN", 5)


def minutos_bloqueo() -> int:
    return _entero_env("BLOQUEO_LOGIN_MINUTOS", 15)


def estado(usuario: dict) -> str:
    """Determina en que estado esta el usuario, sin tocar la base.

    Trabaja sobre el dict que devuelve `verificar_usuario`, que ya trae
    resueltos por SQL los campos sesion_vigente y bloqueado.
    """
    if not usuario:
        return REQUIERE_LOGIN

    if int(usuario.get("bloqueado") or 0):
        return BLOQUEADO
    if not usuario.get("pass_bot"):
        return SIN_CLAVE
    if not int(usuario.get("sesion_vigente") or 0):
        return REQUIERE_LOGIN
    if int(usuario.get("pass_temporal") or 0):
        return REQUIERE_CAMBIO

    return ACTIVO


def minutos_restantes_bloqueo(usuario: dict) -> int:
    minutos = usuario.get("minutos_bloqueo")
    try:
        return max(1, int(minutos))
    except (TypeError, ValueError):
        return minutos_bloqueo()


async def intentar_login(usuario: dict, clave: str) -> tuple:
    """Verifica la clave y abre la sesion si corresponde.

    Retorna (resultado, dato):
      ("ok", None)               sesion abierta
      ("temporal", None)         sesion abierta, pero debe cambiar la clave
      ("incorrecta", restantes)  intentos que le quedan antes del bloqueo
      ("bloqueado", minutos)     se acaba de bloquear la cuenta
    """
    chat_id = usuario["chat_id"]

    # PBKDF2 es costoso a proposito: va en un thread aparte para no bloquear
    # el event loop mientras se verifica.
    valida = await asyncio.to_thread(seguridad.verificar, clave, usuario.get("pass_bot"))

    if not valida:
        await consultas.sumar_intento_fallido(chat_id)
        fallidos = int(usuario.get("intentos_fallidos") or 0) + 1
        restantes = max_intentos() - fallidos

        if restantes <= 0:
            await consultas.bloquear_cuenta(chat_id, minutos_bloqueo())
            logger.warning(
                f"Cuenta bloqueada por intentos fallidos: {usuario.get('usuario')} "
                f"(chat_id {chat_id})"
            )
            return "bloqueado", minutos_bloqueo()

        logger.warning(
            f"Login fallido de {usuario.get('usuario')} (chat_id {chat_id}), "
            f"quedan {restantes} intentos"
        )
        return "incorrecta", restantes

    await consultas.abrir_sesion(chat_id, horas_sesion())
    logger.info(f"Login correcto de {usuario.get('usuario')} (chat_id {chat_id})")

    if int(usuario.get("pass_temporal") or 0):
        return "temporal", None
    return "ok", None


async def cerrar(chat_id: int) -> bool:
    return bool(await consultas.cerrar_sesion(chat_id))


async def cambiar_clave(chat_id: int, nueva: str) -> bool:
    """Establece una clave definitiva para el usuario del chat."""
    hash_nuevo = await asyncio.to_thread(seguridad.hashear, nueva)
    return bool(await consultas.cambiar_clave_bot(chat_id, hash_nuevo))


async def buscar_para_asignar(usuario: str) -> Optional[dict]:
    """Busca un usuario dado de alta en el bot. None si no existe."""
    filas = await consultas.buscar_usuario_bot((usuario or "").strip())
    return filas[0] if filas else None


async def asignar_clave_inicial(usuario: str, admin: Optional[dict] = None) -> bool:
    """Deja al usuario con la clave inicial, marcada como temporal."""
    quien = (admin or {}).get("usuario") or "desconocido"
    hash_inicial = await asyncio.to_thread(seguridad.hashear, CLAVE_INICIAL)

    ok = bool(await consultas.asignar_clave_bot(usuario, hash_inicial))
    if ok:
        logger.info(f"Clave del bot asignada a '{usuario}' por '{quien}'")
    else:
        logger.warning(f"No se pudo asignar clave del bot a '{usuario}' (por '{quien}')")
    return ok
