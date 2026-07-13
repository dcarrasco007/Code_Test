# ============================================================================
# scripts/uplink_trafico_15m_ma5600t/orquestador.py
# Proceso: uplink_trafico_15m_ma5600t
# Equivalente PHP: proceso_uplink_trafico_MA5600T.php
# Descripción: Lista OLT modelo MA5600T y lanza un subprocess worker por OLT.
#              Registra el proceso padre en MONITOREO (proceso_id=3).
# Para modificar:
#   - Comando del subprocess → buscar [PROCESO]
#   - Rutas de logs/python   → buscar [RUTA]
#
# [PARIDAD-PHP] A diferencia de MA5800-X15, este orquestador YA delegaba todo
#   el conteo de puertos al worker en el PHP original (solo lista OLTs y lanza
#   subprocesos) — se implementa tal cual, sin simplificaciones adicionales.
# ============================================================================

import logging
import subprocess
from datetime import datetime

from app.db import get_engine
from config.settings import LOGS_MA5600T, MAIN_PY, PROCESO_ID_MA5600T_PADRE, PYTHON_BIN
from model.uplink_trafico_15m_ma5600t.uplink_trafico_15m_ma5600t_model import get_olts
from utils import monitoreo

# Nombre del proceso — debe coincidir con la clave en el registro de main.py  [PROCESO]
_NOMBRE_PROCESO = "uplink_trafico_15m_ma5600t"
_MODELO = "MA5600T"


def run(fecha=None):
    """Orquestador MA5600T: lista las OLT y lanza un worker por cada una.

    Equivalente PHP: proceso_uplink_trafico_MA5600T.php.

    [PARIDAD-PHP] 'fecha' se acepta por consistencia con el CLI de main.py y,
                  si se pasa explícitamente, se reenvía al worker vía --fecha —
                  pero el worker tampoco la usa hoy (ver worker.py). Ninguno
                  de los 3 procesos de este proyecto soporta reprocesar una
                  fecha pasada; todos operan sobre 'ahora'.
    """
    logging.info(f"{datetime.now():%Y-%m-%d %H:%M:%S} | Orquestador iniciado")

    # [RUTA] Carpeta de logs de este proceso (una entrada por SERVER, no por IP).
    LOGS_MA5600T.mkdir(parents=True, exist_ok=True)

    engine = get_engine()

    with engine.begin() as conn:
        # [SQL] MONITOREO: registro padre (RUNNING)
        lote_id = monitoreo.iniciar(conn, PROCESO_ID_MA5600T_PADRE, mensaje="Porceso Primario")
        # [SQL] Catálogo de OLT MA5600T
        olts = get_olts(conn, _MODELO)

    if not olts:
        logging.warning(f"No se encontraron OLTs con modelo '{_MODELO}' en OLT_SERVER.")
        with engine.begin() as conn:
            monitoreo.finalizar(conn, lote_id, cantidad_subprocesos=0)
        return

    logging.info(f"OLTs encontradas: {len(olts)}")

    nprocesos = 0
    for row in olts:
        server, ip = row[0], row[1]

        # [RUTA] Log por SERVER (no por IP) — igual que el PHP original
        #        (logs/MA5600T_15M/$server.log).
        log_path = LOGS_MA5600T / f"{server}.log"

        # [PROCESO] Comando equivalente a
        #   'nohup php -f MA5600T_exped.php $ip $server $lote_id > $server.log &'
        cmd = [
            PYTHON_BIN, MAIN_PY,
            "--proceso", _NOMBRE_PROCESO,
            "--worker",
            "--server", server,
            "--ip", ip,
            "--lote", str(lote_id),
        ]
        if fecha:
            cmd += ["--fecha", fecha]

        with open(log_path, "a", encoding="utf-8") as log_file:
            subprocess.Popen(
                cmd,
                stdout=log_file,
                stderr=log_file,
                # [PROCESO] Desacopla el worker del padre (equivalente a nohup ... &).
                start_new_session=True,
            )
        nprocesos += 1
        logging.info(f"Worker lanzado | server={server} ip={ip} log={log_path}")

    with engine.begin() as conn:
        monitoreo.finalizar(conn, lote_id, cantidad_subprocesos=nprocesos)

    logging.info(
        f"{datetime.now():%Y-%m-%d %H:%M:%S} | Orquestador finalizado | workers={nprocesos}"
    )
