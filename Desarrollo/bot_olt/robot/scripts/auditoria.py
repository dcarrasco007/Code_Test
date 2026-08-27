"""Registro de auditoria: quien uso el bot, que pidio y cuando.

Es transversal: se invoca desde `cmd.py` en los puntos por donde pasa toda
interaccion, de modo que un menu nuevo queda auditado sin escribir una linea
extra. Ver `_auditar` en cmd.py.

Los eventos van a la tabla OLT_BOT_AUDITORIA (DDL en BD/). La escritura es
sincronica: se espera antes de responderle al usuario. Si el INSERT falla, el
error queda en log/bot.log y la operacion continua igual — un problema en la
auditoria no debe dejar el bot inutilizable.
"""

from typing import Optional

from loguru import logger

from robot.model import consultas

# Acciones registrables. Se usan como constantes para no repetir literales
# y para que agregar una accion nueva sea explicito.
INICIO_SESION = "INICIO_SESION"
CONSULTA = "CONSULTA"
DESCARGA = "DESCARGA"
RESETEO_PASSWORD = "RESETEO_PASSWORD"
ACCESO_DENEGADO = "ACCESO_DENEGADO"

# Topes de la tabla: se recorta aqui para que un texto largo no haga fallar
# el INSERT (y con el, la auditoria del evento).
_LARGO_DETALLE = 150
_LARGO_PARAMETRO = 255


def _recortar(valor: Optional[str], largo: int) -> Optional[str]:
    if valor is None:
        return None
    valor = str(valor)
    return valor if len(valor) <= largo else valor[: largo - 3] + "..."


async def registrar(
    chat_id: Optional[int],
    usuario: Optional[dict],
    accion: str,
    detalle: Optional[str] = None,
    parametro: Optional[str] = None,
) -> None:
    """Registra un evento. `usuario` es el dict de OLT_BOT_USUARIOS o None."""
    nombre = (usuario or {}).get("usuario")

    filas = await consultas.registrar_auditoria(
        chat_id=chat_id,
        usuario=nombre,
        accion=accion,
        detalle=_recortar(detalle, _LARGO_DETALLE),
        parametro=_recortar(parametro, _LARGO_PARAMETRO),
    )

    if not filas:
        # Respaldo en archivo: si la base rechazo el INSERT, al menos queda
        # rastro del evento en el log.
        logger.error(
            f"AUDITORIA NO REGISTRADA — chat_id={chat_id} usuario={nombre} "
            f"accion={accion} detalle={detalle} parametro={parametro}"
        )
