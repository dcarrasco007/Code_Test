# ============================================================================
# scripts/uplink_trafico_15m_ma5800x15/orquestador.py
# Proceso: uplink_trafico_15m_ma5800x15
# Equivalente PHP: proceso_uplink_trafico.php
# Descripción: Lista OLT modelo MA5800-X15 y lanza un subprocess worker por OLT.
#              Registra el proceso padre en MONITOREO (proceso_id=1).
# Para modificar:
#   - Comando del subprocess → buscar [PROCESO]
#   - Rutas de logs/python   → buscar [RUTA]
#
# [PARIDAD-PHP] / [FIX-PHP]: el PHP original calculaba aquí $hora (offset
#   -2h -30min) y contaba los puertos pto1..pto12 por OLT, pasando TODO eso al
#   worker vía argv — pero el worker JAMÁS usaba $hora (insertaba con NOW()) y
#   el conteo de puertos es trivial de recalcular en el propio worker (que ya
#   conoce su 'server'). Aquí se simplifica: el orquestador solo lista OLTs y
#   lanza el worker con --server/--ip/--lote; el worker vuelve a consultar sus
#   propios puertos (Fase 4: get_puertos_pto1_12). Se elimina así un cálculo
#   muerto y se evita duplicar la lógica de conteo en dos lugares.
#   También se omite la llamada a ping_ip() que hacía el PHP: su resultado
#   nunca se usaba (el `if(trim($y)){ if($y<100){` que lo comprobaba estaba
#   comentado en el PHP), o sea que era una llamada sin ningún efecto.
# ============================================================================

import logging
import subprocess
from datetime import datetime

from app.db import get_engine
from config.settings import (
    LOGS_MA5800X15,
    MAIN_PY,
    PROCESO_ID_MA5800X15_PADRE,
    PYTHON_BIN,
)
from model.uplink_trafico_15m_ma5800x15.uplink_trafico_15m_ma5800x15_model import get_olts
from utils import monitoreo

# Nombre del proceso — debe coincidir con la clave en el registro de main.py  [PROCESO]
_NOMBRE_PROCESO = "uplink_trafico_15m_ma5800x15"
_MODELO = "MA5800-X15"


def run(fecha=None):
    """Orquestador MA5800-X15: lista las OLT y lanza un worker por cada una.

    Equivalente PHP: proceso_uplink_trafico.php.

    [PARIDAD-PHP] 'fecha' se acepta por consistencia con el CLI de main.py y,
                  si se pasa explícitamente, se reenvía al worker vía --fecha —
                  pero el worker tampoco la usa hoy (ver worker.py). Ninguno
                  de los 3 procesos de este proyecto soporta reprocesar una
                  fecha pasada; todos operan sobre 'ahora'.
    """
    logging.info(f"{datetime.now():%Y-%m-%d %H:%M:%S} | Orquestador iniciado")

    # [RUTA] Carpeta de logs de este proceso (una entrada por IP).
    LOGS_MA5800X15.mkdir(parents=True, exist_ok=True)

    engine = get_engine()

    with engine.begin() as conn:
        # [SQL] MONITOREO: registro padre (RUNNING)
        lote_id = monitoreo.iniciar(conn, PROCESO_ID_MA5800X15_PADRE, mensaje="Porceso Primario")
        # [SQL] Catálogo de OLT MA5800-X15
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

        # [RUTA] Log por IP: logs/uplink_trafico_15m_ma5800x15/log<ip>.log
        log_path = LOGS_MA5800X15 / f"log{ip}.log"

        # [PROCESO] Comando equivalente a 'nohup php -f exped.php ... &'.
        # El worker recibe --lote para vincular su registro MONITOREO al padre.
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
