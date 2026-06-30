# ============================================================================
# scripts/uplink_trafico_diario_ma5800x15/orquestador.py
# Proceso: uplink_trafico_diario_ma5800x15
# Equivalente PHP: proceso_uplink_trafico_diario_conexped.php
# Descripción: Lista las OLT modelo MA5800-X15 y lanza un subprocess worker
#              por cada una en paralelo, con su propio archivo de log por IP.
#              Equivalente a: nohup php -f exped.php $server $ip $fecha > log$ip.log &
# Para modificar:
#   - Ejecutable Python y ruta de main.py → buscar [RUTA]
#   - Ruta de logs por IP              → buscar [RUTA]
#   - Modelo de OLT filtrado           → config/settings.py  [CONFIG]
#   - Modo apertura de log (w/a)       → buscar [CONFIG]
#   - Espera de workers                → config/settings.py  ORQUESTADOR_ESPERA_WORKERS
#   - Comando completo del subprocess  → buscar [PROCESO]
# ============================================================================

import logging
import subprocess
import sys
from datetime import datetime, timedelta

from app.db import get_engine
from config.settings import (
    LOGS_MA5800X15,
    MAIN_PY,
    MODELO_OLT_MA5800X15,
    ORQUESTADOR_ESPERA_WORKERS,
    PYTHON_BIN,
)
from model.uplink_trafico_diario_ma5800x15.uplink_trafico_diario_ma5800x15_model import (
    get_olts_ma5800x15,
)

# Nombre del proceso — debe coincidir con la clave en el registro de main.py  [PROCESO]
_NOMBRE_PROCESO = "uplink_trafico_diario_ma5800x15"


def run(fecha=None):
    """Punto de entrada del orquestador. Llamado desde main.py.

    fecha: string 'YYYY-MM-DD' o None para usar ayer (comportamiento PHP por defecto).
    """
    # ── Calcular fecha ────────────────────────────────────────────────────────
    # [PARIDAD-PHP] PHP: $fecha = date('Y-m-d', strtotime('-1 day'))
    if fecha is None:
        fecha = (datetime.now() - timedelta(days=1)).strftime("%Y-%m-%d")

    logging.info(
        f"{datetime.now().strftime('%Y-%m-%d %H:%M:%S')} | "
        f"Orquestador iniciado | fecha={fecha}"
    )

    # ── Crear directorio de logs si no existe ────────────────────────────────
    # [RUTA] Ruta configurada en config/settings.py como LOGS_MA5800X15.
    #        Para cambiar la ruta base, editar LOGS_DIR en .env o settings.py.
    LOGS_MA5800X15.mkdir(parents=True, exist_ok=True)

    # ── Obtener lista de OLT del catálogo ────────────────────────────────────
    engine = get_engine()
    with engine.connect() as conn:
        # [SQL] Lectura de OLT_SERVER filtrada por modelo MA5800-X15
        olts = get_olts_ma5800x15(conn, MODELO_OLT_MA5800X15)

    if not olts:
        logging.warning(
            f"No se encontraron OLTs con modelo '{MODELO_OLT_MA5800X15}' en OLT_SERVER."
        )
        return

    logging.info(f"OLTs encontradas: {len(olts)}")

    # ── Lanzar un subprocess worker por cada OLT ─────────────────────────────
    # [PARIDAD-PHP] Equivale a: exec("nohup php -f exped.php $server $ip $fecha > log$ip.log &")
    # En Python usamos subprocess.Popen (sin shell) con start_new_session=True para
    # desacoplar el worker del proceso padre (equivalente a nohup en Linux).
    handles = []  # (server, ip, Popen, file_handle)

    for row in olts:
        server = row[0]
        ip     = row[1]

        # [RUTA] Archivo de log por IP: logs/uplink_trafico_diario_ma5800x15/MA5800-x15/log<ip>.log
        log_path = LOGS_MA5800X15 / f"log{ip}.log"

        # [PROCESO] Comando equivalente al 'nohup php -f exped.php $server $ip $fecha ... &'
        # Los argumentos van como lista (sin shell): no se necesitan comillas alrededor de los valores.
        cmd = [
            PYTHON_BIN,          # [RUTA] Python del entorno virtual del proyecto
            MAIN_PY,             # [RUTA] Punto de entrada main.py
            "--proceso", _NOMBRE_PROCESO,
            "--worker",
            "--server", server,
            "--ip",     ip,
            "--fecha",  fecha,
        ]

        # [CONFIG] Modo de apertura del log:
        #   "w" = truncar en cada ejecución (igual que '>' en el PHP)
        #   "a" = acumular entre ejecuciones (más útil para diagnóstico)
        log_file = open(log_path, "a", encoding="utf-8")

        proc = subprocess.Popen(
            cmd,
            stdout=log_file,
            stderr=log_file,
            # [PROCESO] start_new_session=True: desacopla el proceso hijo del padre.
            # En Linux equivale a setsid() / nohup — el worker sobrevive si el padre termina.
            # En Windows (desarrollo) crea un nuevo grupo de procesos.
            start_new_session=True,
        )

        handles.append((server, ip, proc, log_file))
        logging.info(
            f"Worker lanzado | server={server} ip={ip} pid={proc.pid} log={log_path}"
        )

    # ── Esperar o no a los workers ────────────────────────────────────────────
    # [CONFIG] Controlado por ORQUESTADOR_ESPERA_WORKERS en config/settings.py.
    # False = comportamiento nohup original (el orquestador termina sin esperar).
    # True  = útil para depuración o para asegurar que todos terminen antes de continuar.
    if ORQUESTADOR_ESPERA_WORKERS:
        logging.info("Esperando a que terminen todos los workers ...")
        for server, ip, proc, log_file in handles:
            proc.wait()
            log_file.close()
            logging.info(
                f"Worker finalizado | server={server} ip={ip} rc={proc.returncode}"
            )
    else:
        # Cerrar los handles del padre; los workers mantienen su propio descriptor.
        for _, _, _, log_file in handles:
            log_file.close()

    logging.info(
        f"{datetime.now().strftime('%Y-%m-%d %H:%M:%S')} | "
        f"Orquestador finalizado | workers lanzados={len(handles)}"
    )
