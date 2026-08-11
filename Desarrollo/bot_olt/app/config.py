"""Definicion centralizada de las conexiones a base de datos.

Ningun otro archivo del proyecto crea engines. Si manana se agrega otra base,
se define aqui un engine nuevo y se agrega su variable al .env.
"""

import os

from dotenv import load_dotenv
from loguru import logger
from sqlalchemy import create_engine

try:
    load_dotenv()

    CONECTION_ADEN = os.getenv("CONECTION_ADEN")
    if not CONECTION_ADEN:
        raise RuntimeError("No se encontro CONECTION_ADEN en el .env")

    _engine = lambda url: create_engine(
        url,
        pool_size=20,
        max_overflow=5,
        pool_timeout=300,
        pool_recycle=1800,
        pool_pre_ping=True,
    )

    engine_aden = _engine(CONECTION_ADEN)

except Exception as e:
    logger.critical(f"Error al configurar la base de datos: {e}")
    raise
