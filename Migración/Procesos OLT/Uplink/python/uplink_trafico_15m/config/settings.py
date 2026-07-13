# ============================================================================
# config/settings.py
# Proyecto: uplink_trafico_15m (compartido por los 3 procesos)
# Descripción: Rutas y parámetros centralizados. TODAS las rutas y valores
#              ajustables del proyecto se definen aquí.
# Para modificar:
#   - Rutas de archivos/logs/ejecutable         → buscar [RUTA]
#   - Parámetros ajustables (timeouts, IDs)     → buscar [CONFIG]
#   - Servers/IPs con comportamiento especial   → buscar [PARIDAD-PHP]
# Ver PLAN_MIGRACION_15M.md §2.1 para el detalle de qué función PHP originó cada lista.
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
# [RUTA] Python del entorno virtual del proyecto.
#        Linux (producción): env/bin/python — Windows (dev): env/Scripts/python.exe
PYTHON_BIN = os.getenv(
    "PYTHON_BIN",
    str(
        BASE_DIR
        / "env"
        / ("Scripts" if sys.platform == "win32" else "bin")
        / "python"
    ),
)

# ─── Punto de entrada principal ──────────────────────────────────────────────
# [RUTA] Usado por los orquestadores para construir el comando subprocess.
MAIN_PY = str(BASE_DIR / "main.py")

# ─── Directorio base de logs ─────────────────────────────────────────────────
# [RUTA] Por defecto: <BASE_DIR>/logs. Override con LOGS_DIR en .env.
LOGS_DIR = Path(os.getenv("LOGS_DIR", str(BASE_DIR / "logs")))

# [RUTA] Carpeta de logs por proceso (un log por IP dentro de cada una).
LOGS_MA5800X15 = LOGS_DIR / "uplink_trafico_15m_ma5800x15"
LOGS_MA5600T   = LOGS_DIR / "uplink_trafico_15m_ma5600t"
LOGS_OTROS     = LOGS_DIR / "uplink_trafico_15m_otros"

# =============================================================================
# Telnet — credenciales y timeouts
# =============================================================================

# [CONFIG] Credenciales telnet de las OLT. En el PHP estaban hardcodeadas
#          ('geret2016' / 'Geret#2016*2021'); aquí vienen del .env.
OLT_TELNET_USER = os.getenv("OLT_TELNET_USER", "")
OLT_TELNET_PASS = os.getenv("OLT_TELNET_PASS", "")

# [CONFIG] Timeout de expect por paso telnet, en segundos.
#          Equivalente PHP: ini_set("expect.timeout", 2)
TELNET_TIMEOUT = int(os.getenv("TELNET_TIMEOUT", "2"))

# =============================================================================
# IDs de proceso — ya registrados en MONITOREO_PROCESOS_EJECUCIONES
# =============================================================================
# [CONFIG] Cada proceso PHP tiene un proceso_id fijo asignado en la BD.
#          No cambiar sin actualizar también el registro en la tabla de origen.
PROCESO_ID_MA5800X15_PADRE  = 1  # proceso_uplink_trafico.php (orquestador)
PROCESO_ID_MA5800X15_WORKER = 2  # proceso_uplink_trafico_exped.php
PROCESO_ID_MA5600T_PADRE    = 3  # proceso_uplink_trafico_MA5600T.php (orquestador)
PROCESO_ID_MA5600T_WORKER   = 4  # proceso_uplink_trafico_MA5600T_exped.php
PROCESO_ID_OTROS            = 5  # proceso_uplink_trafico_603_680.php (monolítico)

# =============================================================================
# Servers / IPs con comportamiento especial (comandos telnet distintos)
# Ver PLAN_MIGRACION_15M.md §2.1 — tabla de paridad de comandos por modelo/server.
# =============================================================================

# [PARIDAD-PHP] MA5800-X15: estas IPs usan puertos mpu 0/8-0/9 en vez de eth 0/16-0/18.
IPS_MA5800X15_MPU_8_9 = frozenset({
    "10.99.17.38", "10.99.30.70", "10.99.26.150", "10.99.29.50", "10.99.26.66",
    "10.99.30.14", "10.99.26.254", "10.99.9.150", "10.99.30.102",
})

# [PARIDAD-PHP] MA5600T: estos servers usan scu 0/7-0/8 (estado_equipo4) en vez de giu 0/17-0/18.
SERVERS_MA5600T_SCU_7_8 = frozenset({
    "OLT-ALTOPENUELAS-1", "OLT-CNT-2", "OLT-VALPARAISO-1", "OLT-VITACURA-1",
})

# [PARIDAD-PHP] MA5600T: este server usa giu 0/19-0/20 (estado_equipo3).
SERVER_MA5600T_GIU_19_20 = "OLT-LAFLORIDA-1"

# [PARIDAD-PHP] MA5600T: grupo de REINTENTO tras un primer intento fallido (usa
#               scu 0/7-0/8 de nuevo). Nótese que NO incluye 'OLT-VITACURA-1',
#               aunque ese server SÍ participa del primer intento
#               (ver SERVERS_MA5600T_SCU_7_8 arriba) — posible descuido del PHP
#               original (el autor omitió VITACURA-1 en esta lista puntual).
#               Se replica tal cual sin corregir; confirmar con negocio si es
#               intencional antes de "arreglarlo". Ver PLAN_MIGRACION_15M.md §2.4.
SERVERS_MA5600T_REINTENTO_SCU_7_8 = frozenset({
    "OLT-ALTOPENUELAS-1", "OLT-CNT-2", "OLT-VALPARAISO-1",
})

# [PARIDAD-PHP] otros (603_680): server que usa scu 0/7-0/8 (estado_equipo).
SERVER_OTROS_CONCEPCION = "OLT-CONCEPCION-1"

# [PARIDAD-PHP] otros (603_680): server que usa giu 0/17-0/18 (estado_equipo3).
SERVER_OTROS_LASCONDES = "OLT-LASCONDES-1"

# [PARIDAD-PHP] otros (603_680): modelo que usa giu 0/7-0/8-0/9 (estado_equipo4).
MODELO_OTROS_MA5603T = "MA5603T"
