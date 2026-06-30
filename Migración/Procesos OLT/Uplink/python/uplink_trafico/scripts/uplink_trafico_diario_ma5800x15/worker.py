# ============================================================================
# scripts/uplink_trafico_diario_ma5800x15/worker.py
# Proceso: uplink_trafico_diario_ma5800x15
# Equivalente PHP: proceso_uplink_trafico_diario_exped.php
# Descripción: Procesa UNA OLT: calcula promedios y picos de tráfico diario
#              por puerto e inserta los resultados en las tablas destino.
# Para modificar:
#   - Cálculos críticos (redondeos, conversión a Mbps) → buscar [PARIDAD-PHP]
#   - Llamadas al model → buscar [SQL]
#   - Comportamiento de transacción → buscar [TRANSACCION]
# ============================================================================

import logging
from datetime import datetime

from app.db import get_engine
from model.uplink_trafico_diario_ma5800x15.uplink_trafico_diario_ma5800x15_model import (
    get_max_puerto,
    get_promedios_puerto,
    get_puertos,
    insert_dia_puerta,
    insert_ocupacion,
    insert_resumen_dia,
)


def _formato_mbps(valor_raw):
    """Convierte un valor raw de tráfico a Mbps con formato de 2 decimales.

    Equivalente PHP:
        $valor = $raw / 1024;
        $valor = round($valor, 2);
        $valor = number_format($valor, 2);   // ej: 1234.5 → "1,234.50"

    [PARIDAD-PHP] number_format($v, 2) en PHP usa ',' como separador de miles
                  y '.' como decimal. Python f"{v:,.2f}" produce el mismo resultado.
                  Si las columnas up_mbps/down_mbps son numéricas en la BD (no VARCHAR),
                  cambiar este método para que devuelva float en lugar de string.
                  Verificar tipo de columna en Fase 6.
    """
    mbps = round(valor_raw / 1024, 2)
    return f"{mbps:,.2f}"


def procesar_olt(server, ip, fecha):
    """Procesa una OLT completa para la fecha dada.

    Equivalente PHP: proceso_uplink_trafico_diario_exped.php
    Parámetros (replicando $argv[1], $argv[2], $argv[3] del PHP):
        server : nombre del equipo (columna 'server' en OLT_SERVER).
        ip     : dirección IP del equipo.
        fecha  : string 'YYYY-MM-DD' a procesar (por defecto ayer, calculado en orquestador).

    [TRANSACCION] El PHP usa autocommit (commit por sentencia). Aquí se usa una
                  transacción única por OLT: si falla cualquier INSERT, se revierten
                  todos los de esa OLT. Esto es una mejora intencional sobre el PHP.
    """
    logging.info(f"{datetime.now().strftime('%Y-%m-%d %H:%M:%S')} | server={server} ip={ip} fecha={fecha}")

    engine = get_engine()

    with engine.begin() as conn:  # [TRANSACCION] auto-commit al salir, rollback en excepción

        # [SQL] Obtener puertos con datos para este server y fecha
        puertos = get_puertos(conn, server, fecha)

        # Acumuladores — equivale a las variables $promedios, $peak, etc. del PHP
        promedios   = 0
        promediosUP = 0
        peak        = 0
        peakUP      = 0
        week        = 0

        for row_puerto in puertos:
            puerto = row_puerto[0]

            # ── Promedios del puerto ──────────────────────────────────────────
            # [SQL] AVG(trafico) y AVG(trafico_up) para este puerto y día
            row_avg = get_promedios_puerto(conn, server, fecha, puerto)

            # [PARIDAD-PHP] Equivale a $row3 del PHP:
            #   [0]=server, [1]=ip, [2]=week, [3]=AVG(trafico), [4]=AVG(trafico_up)
            avg_trafico    = float(row_avg[3] or 0)
            avg_trafico_up = float(row_avg[4] or 0)
            week = row_avg[2]

            promedios   += avg_trafico
            promediosUP += avg_trafico_up

            # [PARIDAD-PHP] Para OLT_TRAFICO_DIA_PUERTA: round(avg / 1024, 2)
            #               No se aplica number_format aquí (solo en ocupación).
            valorDown_1 = round(avg_trafico    / 1024, 2)
            valorUp_1   = round(avg_trafico_up / 1024, 2)

            # [SQL] Insertar promedio diario por puerta
            insert_dia_puerta(
                conn,
                equipo       = row_avg[0],   # server del resultado del AVG
                ip           = row_avg[1],   # ip del resultado del AVG
                puerta       = puerto,
                promedio_down= valorDown_1,
                fecha        = fecha,
                week         = week,
                promedio_up  = valorUp_1,
            )

            # [PARIDAD-PHP] Para OLT_TRAFICOGPON / OLT_TRAFICOGPON2:
            #               valorDown = number_format(round(avg/1024, 2), 2)
            #               Produce string con separador de miles, ej: "1,234.50"
            valorDown = _formato_mbps(avg_trafico)
            valorUP   = _formato_mbps(avg_trafico_up)

            # [SQL] Insertar ocupación en las dos tablas (mismo orden que el PHP)
            insert_ocupacion(conn, "OLT_TRAFICOGPON2",
                             ip_equipo=row_avg[1], port=puerto,
                             up_mbps=valorUP, down_mbps=valorDown, fecha=fecha)
            insert_ocupacion(conn, "OLT_TRAFICOGPON",
                             ip_equipo=row_avg[1], port=puerto,
                             up_mbps=valorUP, down_mbps=valorDown, fecha=fecha)

            # ── Picos del puerto ──────────────────────────────────────────────
            # [SQL] MAX(trafico) y MAX(trafico_up) para este puerto y día
            row_max = get_max_puerto(conn, server, fecha, puerto)

            # [PARIDAD-PHP] Equivale a $row5 del PHP: [3]=MAX(trafico), [4]=MAX(trafico_up)
            peak   += float(row_max[3] or 0)
            peakUP += float(row_max[4] or 0)

        # ── Resumen final de la OLT ───────────────────────────────────────────
        if promedios > 0:
            # [PARIDAD-PHP] promedio y peak se redondean a 1 decimal.
            #               promediosUP y peakUP se insertan SIN redondear (comportamiento PHP).
            promedio_final = round(promedios, 1)
            peak_final     = round(peak, 1)

            logging.info(
                f"Insertando resumen | server={server} promedio={promedio_final} "
                f"peak={peak_final} week={week}"
            )

            # [SQL] Insertar resumen diario de la OLT
            insert_resumen_dia(
                conn,
                server              = server,
                ip                  = ip,
                promedio_trafico    = promedio_final,
                peack_trafico       = peak_final,
                fecha               = fecha,
                week                = week,
                promedio_trafico_up = promediosUP,  # sin redondear [PARIDAD-PHP]
                peack_trafico_up    = peakUP,       # sin redondear [PARIDAD-PHP]
            )
        else:
            logging.info("sin datos para insertar")

    logging.info(f"{datetime.now().strftime('%Y-%m-%d %H:%M:%S')} | Finalizado server={server}")
