# ============================================================================
# config/settings.py
# Proceso: (compartido por todos los procesos del proyecto)
# Descripción: Rutas y parámetros centralizados. TODAS las rutas del proyecto
#              se definen aquí. Ninguna ruta debe hardcodearse en la lógica.
# Para modificar:
#   - Rutas de archivos/logs/ejecutable → buscar [RUTA]
#   - Parámetros ajustables (modelos, flags) → buscar [CONFIG]
# ============================================================================

import os
import sys
from pathlib import Path

from dotenv import load_dotenv

load_dotenv()

# ─── Raíz del proyecto ───────────────────────────────────────────────────────
# [RUTA] Calculada desde la ubicación de este archivo. No hardcodear.
BASE_DIR = Path(__file__).resolve().parent.parent

# ─── Ejecutable de Python para subprocess (orquestadores) ────────────────────
# [RUTA] Apunta al Python del entorno virtual del proyecto.
#        En Linux (producción): env/bin/python
#        En Windows (desarrollo): env/Scripts/python.exe
#        Si PYTHON_BIN está definido en .env, ese valor tiene prioridad.
PYTHON_BIN = os.getenv(
    "PYTHON_BIN",
    str(
        BASE_DIR
        / "env"
        / ("Scripts" if sys.platform == "win32" else "bin")
        / "python"
    ),
)

# ─── Directorio base de logs ─────────────────────────────────────────────────
# [RUTA] Por defecto: <BASE_DIR>/logs
#        Para cambiar la ruta en producción, definir LOGS_DIR en .env.
LOGS_DIR = Path(os.getenv("LOGS_DIR", str(BASE_DIR / "logs")))

# ─── Punto de entrada principal (main.py) ────────────────────────────────────
# [RUTA] Usado por los orquestadores para construir el comando subprocess.
MAIN_PY = str(BASE_DIR / "main.py")

# =============================================================================
# Parámetros por proceso
# Para agregar un proceso nuevo: añadir un bloque similar al de abajo.
# Ver PLAN_MIGRACION.md §9.
# =============================================================================

# ── Proceso: uplink_trafico_diario_ma5800x15 ──────────────────────────────────
# [CONFIG] Modelo de OLT que filtra la tabla OLT_SERVER.
#          Cambiar aquí si el modelo varía (sin tocar la lógica del script).
MODELO_OLT_MA5800X15 = os.getenv("MODELO_OLT", "MA5800-X15")

# [RUTA] Carpeta de logs de este proceso.
#        Estructura: LOGS_DIR / nombre_proceso / subcarpeta_modelo
LOGS_MA5800X15 = LOGS_DIR / "uplink_trafico_diario_ma5800x15" / "MA5800-x15"

# [CONFIG] ¿El orquestador espera a que terminen todos los workers antes de salir?
#          False = comportamiento nohup original (lanza y no espera).
ORQUESTADOR_ESPERA_WORKERS = False
