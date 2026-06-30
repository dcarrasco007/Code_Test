# ============================================================================
# app/db.py
# Proceso: (compartido por todos los procesos del proyecto)
# Descripción: Crea y expone el engine de SQLAlchemy. Lee credenciales de .env.
#              Un único engine se reutiliza en todo el proyecto.
# Para modificar:
#   - Cadena de conexión / driver → buscar [RUTA]
#   - Credenciales → editar el .env (nunca aquí)
#   - Parámetros del pool → buscar [CONFIG]
# ============================================================================

import os
import sys

from dotenv import load_dotenv
from sqlalchemy import create_engine

load_dotenv()

# ─── Credenciales leídas de .env ─────────────────────────────────────────────
# [CONFIG] Todos los valores vienen del .env; no hardcodear aquí.
_DB_HOST    = os.getenv("DB_HOST")
_DB_PORT    = os.getenv("DB_PORT", "3306")
_DB_USER    = os.getenv("DB_USER")
_DB_PASS    = os.getenv("DB_PASS")
_DB_NAME    = os.getenv("DB_NAME", "Aden")
_DB_CHARSET = os.getenv("DB_CHARSET", "utf8")


def _validar_credenciales():
    """Verifica que las credenciales obligatorias estén definidas en .env.
    Detiene el proceso con un mensaje claro si falta alguna.
    """
    faltantes = [
        var for var, val in {
            "DB_HOST": _DB_HOST,
            "DB_USER": _DB_USER,
            "DB_PASS": _DB_PASS,
        }.items()
        if not val
    ]
    if faltantes:
        print(
            f"[ERROR] Faltan credenciales en el archivo .env: {', '.join(faltantes)}\n"
            "  Completar con los valores de /u01/crontab127/conexion/conexion_db.php\n"
            "  y volver a ejecutar.",
            file=sys.stderr,
        )
        sys.exit(1)


# ─── Cadena de conexión ───────────────────────────────────────────────────────
# [RUTA] Driver: mysql-connector-python (mysql+mysqlconnector).
#        Para cambiar de driver: reemplazar 'mysql+mysqlconnector' por el nuevo
#        y actualizar requirements.txt.
def _build_url():
    return (
        f"mysql+mysqlconnector://{_DB_USER}:{_DB_PASS}"
        f"@{_DB_HOST}:{_DB_PORT}/{_DB_NAME}?charset={_DB_CHARSET}"
    )


# ─── Engine único del proyecto (singleton) ───────────────────────────────────
_engine = None


def get_engine():
    """Devuelve el engine SQLAlchemy (singleton). Valida credenciales al primer uso.

    Uso recomendado con context manager:
        with get_engine().connect() as conn:
            resultado = conn.execute(text("SELECT 1"))
    """
    global _engine
    if _engine is None:
        _validar_credenciales()
        _engine = create_engine(
            _build_url(),
            pool_pre_ping=True,  # [CONFIG] Verifica la conexión antes de usarla
        )
    return _engine
