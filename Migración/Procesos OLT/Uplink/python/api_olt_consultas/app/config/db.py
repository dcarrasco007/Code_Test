# ============================================================================
# app/config/db.py
# Proyecto: api_olt_consultas
# Descripción: Engine SQLAlchemy hacia la BD Aden. Centraliza la conexión:
#              ningún otro archivo abre su propia conexión (convención GERET:
#              conexiones centralizadas en app/config/).
# Para modificar:
#   - Cadena de conexión → .env (CONECTION_ADEN), nunca hardcodeada aquí.
#   - Parámetros del pool → buscar [CONFIG]
# ============================================================================

import sys

from sqlalchemy import create_engine

from app.config import settings

_engine = None


def _validar_conexion():
    """Detiene el proceso con un mensaje claro si falta CONECTION_ADEN."""
    if not settings.CONECTION_ADEN:
        print(
            "[ERROR] Falta CONECTION_ADEN en el archivo .env.\n"
            "  Formato: mysql+pymysql://usuario:password@host:puerto/base?charset=utf8mb4\n"
            "  Completar y volver a ejecutar.",
            file=sys.stderr,
        )
        sys.exit(1)


def get_engine():
    """Devuelve el engine SQLAlchemy (singleton). Valida CONECTION_ADEN al primer uso.

    Uso recomendado con context manager:
        with get_engine().connect() as conn:
            conn.execute(text("SELECT 1"))
    """
    global _engine
    if _engine is None:
        _validar_conexion()
        _engine = create_engine(
            settings.CONECTION_ADEN,
            pool_pre_ping=True,  # [CONFIG] verifica la conexión antes de usarla
            pool_recycle=3600,   # [CONFIG] recicla conexiones cada hora (evita "MySQL server has gone away")
        )
    return _engine
