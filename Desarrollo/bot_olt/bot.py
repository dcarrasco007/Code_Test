"""Punto de entrada del bot de consultas OLT.

Unico archivo ejecutable del proyecto: arranca la aplicacion de Telegram,
registra los handlers y deja corriendo el long polling.
"""

import os

from dotenv import load_dotenv
from telegram.ext import ApplicationBuilder

from robot.scripts import cmd
from utils.logger import setup_logging


def start_bot():
    load_dotenv()
    setup_logging()

    token = os.getenv("BOT_TOKEN")
    if not token:
        raise RuntimeError("No se encontro BOT_TOKEN en el .env")

    builder = (
        ApplicationBuilder()
        .token(token)
        .connect_timeout(30)
        .read_timeout(60)
        .write_timeout(300)
        .pool_timeout(60)
        .connection_pool_size(100)
    )

    proxy = os.getenv("PROXY")
    if proxy:
        builder = builder.proxy(proxy).get_updates_proxy(proxy)

    application = builder.build()
    cmd.register_handlers(application)
    application.run_polling()


if __name__ == "__main__":
    start_bot()
