"""Configuracion central de loguru. Se llama una sola vez al arrancar el bot."""

import logging
import sys
from pathlib import Path

from loguru import logger

_FORMATO_CONSOLA = (
    "<green>{time:YYYY-MM-DD HH:mm:ss}</green> | "
    "<level>{level: <8}</level> | "
    "<cyan>{name}</cyan>:<cyan>{function}</cyan>:<cyan>{line}</cyan> - <level>{message}</level>"
)

_FORMATO_ARCHIVO = "{time:YYYY-MM-DD HH:mm:ss} | {level: <8} | {name}:{function}:{line} - {message}"

_LIBRERIAS_RUIDOSAS = ("httpx", "httpcore", "telegram", "asyncio", "urllib3")


class _InterceptHandler(logging.Handler):
    """Redirige logs de librerias (stdlib logging) hacia loguru."""

    def emit(self, record: logging.LogRecord) -> None:
        try:
            nivel = logger.level(record.levelname).name
        except ValueError:
            nivel = record.levelno
        logger.opt(depth=6, exception=record.exc_info).log(nivel, record.getMessage())


def setup_logging(log_file: str = "log/bot.log") -> None:
    Path(log_file).parent.mkdir(parents=True, exist_ok=True)

    # diagnose=False es obligatorio: con el valor por defecto (True) loguru
    # vuelca el contenido de cada variable del stack en el traceback, lo que
    # deja el BOT_TOKEN y las credenciales de la base escritos en el log.
    logger.remove()
    logger.add(
        sys.stderr,
        level="INFO",
        colorize=True,
        format=_FORMATO_CONSOLA,
        backtrace=True,
        diagnose=False,
    )
    logger.add(
        log_file,
        level="DEBUG",
        rotation="10 MB",
        retention="7 days",
        encoding="utf-8",
        format=_FORMATO_ARCHIVO,
        backtrace=True,
        diagnose=False,
    )

    logging.basicConfig(handlers=[_InterceptHandler()], level=0, force=True)
    for nombre in _LIBRERIAS_RUIDOSAS:
        logging.getLogger(nombre).setLevel(logging.WARNING)
