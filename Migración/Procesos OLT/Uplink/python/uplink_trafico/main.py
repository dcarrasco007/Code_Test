# ============================================================================
# main.py
# Proceso: (entrada unificada para todos los procesos del proyecto)
# Descripción: Punto de entrada CLI. Parsea argumentos, selecciona el proceso
#              y despacha al modo orquestador o worker correspondiente.
# Para modificar:
#   - Registrar un proceso nuevo → buscar [PROCESO] y añadir al dict PROCESOS
#   - Cambiar formato de logging  → buscar _configurar_logging
#   - Cambiar comportamiento del CLI → buscar los bloques argparse
# Ver PLAN_MIGRACION.md §9 para el procedimiento completo de agregar un proceso.
# ============================================================================

import argparse
import logging
import sys

from scripts.uplink_trafico_diario_ma5800x15 import orquestador as orc_ma5800x15
from scripts.uplink_trafico_diario_ma5800x15 import worker as wrk_ma5800x15

# ─── Registro de procesos disponibles ────────────────────────────────────────
# [PROCESO] Para agregar un proceso nuevo:
#   1. Importar sus módulos arriba (orquestador y worker).
#   2. Añadir una entrada aquí con la misma estructura.
#   3. Ver PLAN_MIGRACION.md §9 para el resto de pasos.
PROCESOS = {
    "uplink_trafico_diario_ma5800x15": {
        "orquestador": orc_ma5800x15,
        "worker":      wrk_ma5800x15,
    },
    # ── Agregar nuevos procesos aquí ── [PROCESO]
    # "nombre_proceso": {
    #     "orquestador": orc_nombre,
    #     "worker":      wrk_nombre,
    # },
}


def _configurar_logging():
    """Configura logging a stdout con timestamp.
    Los subprocesos worker heredan stdout redirigido al log por IP (orquestador.py).
    """
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S",
        stream=sys.stdout,
    )


def _listar_procesos():
    print("Procesos disponibles:")
    for nombre in PROCESOS:
        print(f"  - {nombre}")


def main():
    _configurar_logging()

    parser = argparse.ArgumentParser(
        description=(
            "Ejecuta procesos de tráfico OLT.\n"
            "Modos:\n"
            "  Orquestador: main.py --proceso <nombre>\n"
            "  Worker:      main.py --proceso <nombre> --worker --server S --ip I\n"
            "  Todos:       main.py  (sin --proceso ejecuta todos los orquestadores)"
        ),
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument(
        "--proceso",
        metavar="NOMBRE",
        help=(
            "Nombre del proceso a ejecutar. "
            "Si se omite, se ejecutan todos los orquestadores registrados."
        ),
    )
    parser.add_argument(
        "--worker",
        action="store_true",
        help=(
            "Modo worker: procesa una sola OLT. "
            "Requiere --proceso, --server e --ip. "
            "Lanzado automáticamente por el orquestador."
        ),
    )
    parser.add_argument("--server", help="Nombre del servidor OLT (solo modo --worker).")
    parser.add_argument("--ip",     help="IP del equipo OLT (solo modo --worker).")
    parser.add_argument(
        "--fecha",
        help="Fecha a procesar YYYY-MM-DD. Por defecto: ayer.",
    )
    parser.add_argument(
        "--list",
        action="store_true",
        dest="listar",
        help="Muestra los procesos disponibles y sale.",
    )
    args = parser.parse_args()

    # ── Listar procesos y salir ───────────────────────────────────────────────
    if args.listar:
        _listar_procesos()
        sys.exit(0)

    # ── Validaciones previas al despacho ─────────────────────────────────────
    if args.proceso and args.proceso not in PROCESOS:
        logging.error(
            f"Proceso '{args.proceso}' no encontrado. "
            f"Disponibles: {', '.join(PROCESOS)}"
        )
        sys.exit(1)

    # --worker sin --proceso no tiene sentido semántico: cada worker pertenece
    # a un proceso específico y necesita saber a qué model/script apuntar.
    if args.worker and not args.proceso:
        logging.error(
            "--worker requiere --proceso. "
            f"Disponibles: {', '.join(PROCESOS)}"
        )
        sys.exit(1)

    if args.worker and not all([args.server, args.ip]):
        logging.error("--worker requiere --server y --ip.")
        sys.exit(1)

    # ── Despacho ─────────────────────────────────────────────────────────────
    # En modo worker siempre hay un --proceso específico (validado arriba).
    # En modo orquestador, si no hay --proceso se ejecutan todos.
    targets = [args.proceso] if args.proceso else list(PROCESOS.keys())

    exit_code = 0  # acumula fallos; permite que todos los targets corran aunque uno falle

    for nombre in targets:
        modulos = PROCESOS[nombre]
        try:
            if args.worker:
                logging.info(f"[{nombre}] Worker iniciado | ip={args.ip}")
                modulos["worker"].procesar_olt(args.server, args.ip, args.fecha)
            else:
                logging.info(f"[{nombre}] Orquestador iniciado")
                modulos["orquestador"].run(fecha=args.fecha)

        except Exception as e:
            logging.error(f"[{nombre}] Error: {e}", exc_info=True)
            exit_code = 1
            # Continúa con el siguiente proceso en lugar de salir inmediatamente,
            # para que un fallo aislado no cancele el resto del lote.

    sys.exit(exit_code)


if __name__ == "__main__":
    main()
