# ============================================================================
# main.py
# Proyecto: uplink_trafico_15m (entrada unificada de los 3 procesos)
# Descripción: Punto de entrada CLI. Selecciona el proceso y despacha al modo
#              orquestador, worker o script (monolítico) según corresponda.
# Para modificar:
#   - Registrar un proceso nuevo → buscar [PROCESO] y añadir al dict PROCESOS
#   - Formato de logging          → buscar _configurar_logging
# Modos:
#   Orquestador: main.py --proceso <nombre>
#   Worker:      main.py --proceso <nombre> --worker --server S --ip I
#   Todos:       main.py               (ejecuta todos los procesos registrados)
# Nota: el proceso 'otros' es monolítico (kind="script"): no admite --worker.
#
# [PARIDAD-PHP] El modo "todos" (sin --proceso) es una CONVENIENCIA para pruebas
#   manuales. En producción (Fase 10), cada uno de los 3 procesos se programa en
#   su PROPIA entrada de cron con --proceso explícito — igual que el PHP original
#   (3 archivos, 3 líneas de cron independientes), no un cron único que los
#   ejecute los tres de una vez.
# ============================================================================

import argparse
import logging
import sys

from scripts.uplink_trafico_15m_ma5800x15 import orquestador as orc_5800
from scripts.uplink_trafico_15m_ma5800x15 import worker as wrk_5800
from scripts.uplink_trafico_15m_ma5600t import orquestador as orc_5600
from scripts.uplink_trafico_15m_ma5600t import worker as wrk_5600
from scripts.uplink_trafico_15m_otros import script as scr_otros

# ─── Registro de procesos ─────────────────────────────────────────────────────
# [PROCESO] Para agregar un proceso nuevo:
#   1. Importar su(s) módulo(s) arriba.
#   2. Añadir una entrada aquí.
#      kind="orquestador_worker" → tiene orquestador.run() y worker.procesar_olt()
#      kind="script"             → monolítico, solo script.run() (sin --worker)
PROCESOS = {
    "uplink_trafico_15m_ma5800x15": {
        "kind":        "orquestador_worker",
        "orquestador": orc_5800,
        "worker":      wrk_5800,
    },
    "uplink_trafico_15m_ma5600t": {
        "kind":        "orquestador_worker",
        "orquestador": orc_5600,
        "worker":      wrk_5600,
    },
    "uplink_trafico_15m_otros": {
        "kind":   "script",
        "script": scr_otros,
    },
    # ── Agregar nuevos procesos aquí ── [PROCESO]
}


def _configurar_logging():
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
        stream=sys.stdout,
    )


def _listar_procesos():
    print("Procesos disponibles:")
    for nombre, cfg in PROCESOS.items():
        print(f"  - {nombre}  ({cfg['kind']})")


def main():
    _configurar_logging()

    parser = argparse.ArgumentParser(
        description="Ejecuta procesos de recolección de tráfico OLT (cada 15 min, telnet).",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument("--proceso", metavar="NOMBRE",
                        help="Proceso a ejecutar. Si se omite, se ejecutan todos.")
    parser.add_argument("--worker", action="store_true",
                        help="Modo worker: procesa una sola OLT (requiere --proceso, --server, --ip).")
    parser.add_argument("--server", help="Nombre del servidor OLT (solo --worker).")
    parser.add_argument("--ip", help="IP del equipo OLT (solo --worker).")
    parser.add_argument("--fecha",
                        help="Reservado por consistencia con el proyecto 'uplink_trafico' "
                             "(diario). NINGUNO de los 3 procesos de este proyecto lo usa "
                             "hoy: todos operan sobre 'ahora' (ping/telnet/NOW()), igual que "
                             "el PHP original (que tampoco aceptaba override de fecha).")
    parser.add_argument("--lote", type=int, default=None,
                        help="ID del registro MONITOREO padre (lote_id), pasado por el "
                             "orquestador a cada worker para vincular padre/hijo.")
    parser.add_argument("--list", action="store_true", dest="listar",
                        help="Muestra los procesos disponibles y sale.")
    args = parser.parse_args()

    if args.listar:
        _listar_procesos()
        sys.exit(0)

    if args.proceso and args.proceso not in PROCESOS:
        logging.error(f"Proceso '{args.proceso}' no encontrado. "
                      f"Disponibles: {', '.join(PROCESOS)}")
        sys.exit(1)

    if args.worker and not args.proceso:
        logging.error("--worker requiere --proceso.")
        sys.exit(1)

    if args.worker and not all([args.server, args.ip]):
        logging.error("--worker requiere --server y --ip.")
        sys.exit(1)

    targets = [args.proceso] if args.proceso else list(PROCESOS.keys())
    exit_code = 0

    for nombre in targets:
        cfg = PROCESOS[nombre]
        try:
            if args.worker:
                if cfg["kind"] != "orquestador_worker":
                    logging.error(f"[{nombre}] es monolítico (kind=script): no admite --worker.")
                    exit_code = 1
                    continue
                logging.info(f"[{nombre}] Worker iniciado | ip={args.ip}")
                cfg["worker"].procesar_olt(args.server, args.ip, args.fecha, args.lote)
            else:
                logging.info(f"[{nombre}] Iniciado ({cfg['kind']})")
                if cfg["kind"] == "orquestador_worker":
                    cfg["orquestador"].run(fecha=args.fecha)
                else:  # kind == "script"
                    cfg["script"].run(fecha=args.fecha)
        except (Exception, SystemExit) as e:
            # [PARIDAD-PHP] app/db.py usa sys.exit(1) para errores de credenciales
            # (SystemExit no hereda de Exception) — se captura aquí también para
            # que, en modo "todos", un target con .env incompleto no aborte el
            # resto de los procesos del lote. En invocación normal (un solo
            # --proceso por cron) esto no cambia nada: el exit_code final sigue
            # siendo != 0.
            logging.error(f"[{nombre}] Error: {e}", exc_info=True)
            exit_code = 1

    sys.exit(exit_code)


if __name__ == "__main__":
    main()
