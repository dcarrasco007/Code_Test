# ============================================================================
# scripts/uplink_trafico_15m_ma5600t/worker.py
# Proceso: uplink_trafico_15m_ma5600t
# Equivalente PHP: proceso_uplink_trafico_MA5600T_exped.php
# Descripción: Procesa UNA OLT MA5600T: gate de ping, cuenta sus puertos
#              configurados (OLT_PUERTAS_UPLINKS_GB), hace telnet (giu 0/17-18
#              por defecto; scu 0/7-8 o giu 0/19-20 según el server) con un
#              árbol de reintentos que varía por grupo de server, parsea el
#              tráfico e inserta detalle + peak. MONITOREO (proceso_id=4).
# Para modificar:
#   - Mapa de comandos telnet por server/slot → buscar [PARIDAD-PHP]
#   - Árbol de reintentos                     → _ejecutar_con_reintentos
# ============================================================================

import logging
from datetime import datetime

from app.db import get_engine
from config.settings import (
    PROCESO_ID_MA5600T_WORKER,
    SERVER_MA5600T_GIU_19_20,
    SERVERS_MA5600T_REINTENTO_SCU_7_8,
    SERVERS_MA5600T_SCU_7_8,
)
from model.uplink_trafico_15m_ma5600t.uplink_trafico_15m_ma5600t_model import (
    get_puertos_gb,
    insert_detalle,
    insert_hora,
)
from utils import monitoreo
from utils.parser_trafico import parsear_trafico
from utils.ping import es_alcanzable, ping_ip
from utils.telnet_olt import leer_trafico_puertos, respuesta_valida

_MODELO = "MA5600T"

# Orden de labeling/parseo — igual que el elseif chain del PHP (17,18,19,20,7,8).
# [PARIDAD-PHP] El PHP también contaba un slot '0/16' en la clasificación, pero
#               ninguna función de telnet ni el parseo lo usan en este proceso
#               (dead code en el original) — se omite ese conteo inerte.
_ORDEN_SLOTS = ("0/17", "0/18", "0/19", "0/20", "0/7", "0/8")

# Slots donde aplica el "duplicado" condicional (ver _contar_puertos). No aplica a 19/20.
_SLOTS_CON_DUPLICADO = {"0/17", "0/18", "0/7", "0/8"}


def _contar_puertos(filas_puertos):
    """Cuenta puertos por slot desde OLT_PUERTAS_UPLINKS_GB.

    Equivalente PHP: bucle de clasificación en proceso_uplink_trafico_MA5600T_exped.php.

    [PARIDAD-PHP] Si la PRIMERA fila vista de un slot tiene parte2=='1' (ej.
                  '0/17/1' en vez de '0/17/0'), el contador de ese slot se
                  incrementa una SEGUNDA vez — comportamiento tal cual el PHP
                  original (no se intenta corregir/entender la intención).
    """
    conteo = {slot: 0 for slot in _ORDEN_SLOTS}

    for _olt, puerta in (filas_puertos or []):
        if not puerta:
            continue
        partes = puerta.split("/")
        slot = f"{partes[0]}/{partes[1]}"
        parte2 = partes[2] if len(partes) > 2 else None

        if slot in conteo:
            conteo[slot] += 1
            if slot in _SLOTS_CON_DUPLICADO and parte2 == "1" and conteo[slot] == 1:
                conteo[slot] += 1

    return conteo


def _ping_ok(ip):
    """Determina si la OLT es alcanzable (wrapper fino sobre utils.ping.es_alcanzable,
    compartida con el proceso "otros" que hace el mismo gate de ping).

    Equivalente PHP: if(trim($y)){ if($y < 100){ ...procesar... } }
    """
    return es_alcanzable(ping_ip(ip))


def _comandos(conteo, slot_a, slot_b, tipo):
    """Arma la lista [(interface_cmd, n_puertos), ...] para dos slots contiguos."""
    return [
        (f"interface {tipo} {slot_a}", conteo[slot_a]),
        (f"interface {tipo} {slot_b}", conteo[slot_b]),
    ]


def _ejecutar_con_reintentos(ip, server, conteo):
    """Ejecuta la sesión telnet inicial y, si falla, los reintentos según el
    grupo de servers al que pertenece — replica el árbol de decisión de
    proceso_uplink_trafico_MA5600T_exped.php (estado_equipo/2/3/4 + reintentos).

    [PARIDAD-PHP] estado_equipo2 (variante con una línea en blanco extra antes
                  del primer 'interface giu 0/17') se trata como equivalente a
                  estado_equipo — la diferencia es cosmética (una línea en
                  blanco de más) y no se replica; ver PLAN_MIGRACION_15M.md §2.4.
    [PARIDAD-PHP] El grupo de reintento (SERVERS_MA5600T_REINTENTO_SCU_7_8) NO
                  incluye OLT-VITACURA-1 aunque sí participa del primer intento
                  — posible descuido del PHP original, replicado tal cual.

    Retorna (texto_final, fallo_definitivo).
    """
    cmd_17_18 = _comandos(conteo, "0/17", "0/18", "giu")
    cmd_7_8 = _comandos(conteo, "0/7", "0/8", "scu")
    cmd_19_20 = _comandos(conteo, "0/19", "0/20", "giu")

    # ── Intento inicial: dispatch según el server ────────────────────────────
    if server == SERVER_MA5600T_GIU_19_20:
        texto = leer_trafico_puertos(ip, cmd_19_20)
    elif server in SERVERS_MA5600T_SCU_7_8:
        texto = leer_trafico_puertos(ip, cmd_7_8)
    else:
        texto = leer_trafico_puertos(ip, cmd_17_18)

    valido = respuesta_valida(texto)

    # ── Reintentos si el intento inicial falló ───────────────────────────────
    if not valido:
        if server in SERVERS_MA5600T_REINTENTO_SCU_7_8:
            # [PARIDAD-PHP] Un único reintento con scu 0/7-0/8, sin más reintentos.
            texto = leer_trafico_puertos(ip, cmd_7_8)
            valido = respuesta_valida(texto)
        else:
            # [PARIDAD-PHP] Hasta 3 reintentos con giu 0/17-0/18 (colapsa
            # estado_equipo/estado_equipo2/estado_equipo en un mismo comando).
            for _ in range(3):
                texto = leer_trafico_puertos(ip, cmd_17_18)
                valido = respuesta_valida(texto)
                if valido:
                    break

    return texto, (not valido)


def procesar_olt(server, ip, fecha, lote_id=None):
    """Procesa una OLT MA5600T. Llamado desde main.py en modo --worker.

    Equivalente PHP: proceso_uplink_trafico_MA5600T_exped.php.

    [PARIDAD-PHP] 'fecha' se acepta por consistencia con el CLI de main.py pero
                  NO se usa: este proceso siempre opera sobre 'ahora' (semana
                  calculada con datetime.now(), inserts con NOW()), igual que
                  el PHP original (no aceptaba override de fecha).
    """
    logging.info(f"{datetime.now():%Y-%m-%d %H:%M:%S} | server={server} ip={ip}")

    engine = get_engine()
    semana = datetime.now().isocalendar()[1]  # [PARIDAD-PHP] equivalente a date('W')

    with engine.begin() as conn:
        # [SQL] MONITOREO: registro hijo, vinculado al padre vía lote_id.
        registro_id = monitoreo.iniciar(
            conn, PROCESO_ID_MA5600T_WORKER, mensaje=ip,
            parent_id=lote_id, lote_id=lote_id,
        )

        # [PARIDAD-PHP] Gate de ping: si la OLT no responde (o packet-loss>=100%),
        #               el PHP no hace NADA (ni telnet ni inserts) — solo cierra
        #               MONITOREO con estado OK, igual que en el caso exitoso.
        if not _ping_ok(ip):
            logging.info(f"[{server}] Ping no exitoso — se omite telnet.")
            monitoreo.finalizar(conn, registro_id)
            return

        # [SQL] Puertos configurados de esta OLT.
        filas_puertos = get_puertos_gb(conn, _MODELO, server)
        conteo = _contar_puertos(filas_puertos)

        texto, fallo = _ejecutar_con_reintentos(ip, server, conteo)

        if fallo:
            # [PARIDAD-PHP] Marcador de fallo (modelo='MA5600T2', peak=0). El PHP
            #               inserta esto Y ADEMÁS sigue con el insert normal de
            #               abajo (también peak=0, ya que $texto queda vacío) —
            #               resultando en DOS filas en OLT_TRAFICO_UPLINK_HORA.
            insert_hora(conn, server=server, ip=ip, modelo="MA5600T2", peak=0, week=semana)
            texto = ""  # [PARIDAD-PHP] el PHP limpia $texto tras el fallo definitivo

        # [SQL] Parseo + inserción de detalle, en el orden 17,18,19,20,7,8 del PHP.
        lecturas = parsear_trafico(texto)
        idx_lectura = 0
        peak = 0.0

        for slot in _ORDEN_SLOTS:
            for contador in range(conteo[slot]):
                if idx_lectura >= len(lecturas):
                    break
                bajada, subida = lecturas[idx_lectura]
                idx_lectura += 1

                puerta_ingreso = f"{slot}/{contador}"
                insert_detalle(
                    conn, server=server, ip=ip, modelo=_MODELO,
                    puerto=puerta_ingreso, trafico=bajada, week=semana,
                    trafico_up=subida,
                )
                peak += float(bajada or 0)

        # [SQL] Resumen (peak) — siempre se ejecuta, incluso tras un fallo (peak=0).
        insert_hora(conn, server=server, ip=ip, modelo=_MODELO, peak=peak, week=semana)

        monitoreo.finalizar(conn, registro_id)

    logging.info(f"{datetime.now():%Y-%m-%d %H:%M:%S} | Finalizado server={server}")
