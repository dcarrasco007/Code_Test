"""Logica de negocio: reseteo de contrasena de usuarios del portal OLT.

Replica lo que hace hoy el portal en el case "changePassw": deja la clave en
un valor conocido, actualiza `last_connection` y activa al usuario, de modo
que al entrar el portal lo mande a cambiarla.

Esta es la unica operacion de escritura del bot. Toda ejecucion queda
registrada en el log con quien la solicito y sobre que usuario.
"""

import hashlib
from typing import Optional

from loguru import logger

from robot.model import consultas

TITULO = "Reseteo de contrasena"

# Clave que deja el portal al resetear. El usuario la cambia al entrar.
PASSWORD_POR_DEFECTO = "123456"

# Resultados posibles de la busqueda previa a la confirmacion.
ENCONTRADO = "encontrado"
NO_EXISTE = "no_existe"
DUPLICADO = "duplicado"


def _hash_password(texto: str) -> str:
    """Hashea la clave con MD5.

    OJO: MD5 no es un algoritmo seguro para contrasenas. Se usa aqui
    unicamente porque es el que aplica el portal (`md5('123456')` en PHP) y
    el hash debe coincidir para que el usuario pueda entrar. Cambiarlo
    requiere migrar tambien el login del portal, no solo este bot.
    """
    return hashlib.md5(texto.encode("utf-8")).hexdigest()


def describir_estado(estado) -> str:
    """Traduce el campo `estado` a algo legible en el chat."""
    try:
        return "Activo" if int(estado) == 1 else "Inactivo"
    except (TypeError, ValueError):
        return f"Desconocido ({estado})"


async def buscar(usuario: str) -> tuple:
    """Busca el usuario antes de pedir confirmacion.

    Retorna (resultado, fila):
      - (ENCONTRADO, fila) si hay exactamente una coincidencia
      - (NO_EXISTE, None)  si no hay ninguna
      - (DUPLICADO, None)  si hay mas de una: se cancela por seguridad
    """
    usuario = (usuario or "").strip()
    if not usuario:
        return NO_EXISTE, None

    filas = await consultas.buscar_usuario_portal(usuario)

    if not filas:
        return NO_EXISTE, None
    if len(filas) > 1:
        # No deberia ocurrir con busqueda exacta, salvo que `usuario` no
        # tenga indice unico. Se cancela para no resetear varias cuentas.
        logger.warning(f"'{usuario}' devolvio {len(filas)} coincidencias: se cancela el reseteo")
        return DUPLICADO, None

    return ENCONTRADO, filas[0]


async def resetear(usuario: str, solicitante: Optional[dict] = None) -> bool:
    """Resetea la clave del usuario y lo deja activo.

    Deja registro en el log de quien solicito la operacion: es una accion
    privilegiada y conviene poder auditarla despues.
    """
    quien = (solicitante or {}).get("usuario") or (solicitante or {}).get("chat_id") or "desconocido"

    filas = await consultas.resetear_password(usuario, _hash_password(PASSWORD_POR_DEFECTO))

    if filas > 0:
        logger.info(f"Reseteo de contrasena de '{usuario}' solicitado por '{quien}' — OK")
        return True

    logger.warning(f"Reseteo de contrasena de '{usuario}' solicitado por '{quien}' — sin efecto")
    return False
