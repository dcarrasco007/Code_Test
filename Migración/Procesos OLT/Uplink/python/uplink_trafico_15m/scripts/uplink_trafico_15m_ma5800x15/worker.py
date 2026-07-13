# ============================================================================
# scripts/uplink_trafico_15m_ma5800x15/worker.py
# Proceso: uplink_trafico_15m_ma5800x15
# Equivalente PHP: proceso_uplink_trafico_exped.php
# Descripción: Procesa UNA OLT MA5800-X15: cuenta sus puertos configurados
#              (pto1..pto12), hace telnet (eth 0/16-0/18, o mpu 0/8-0/9 si la
#              IP está en la lista especial), parsea el tráfico e inserta
#              detalle + peak. Reintenta hasta 3 veces si la sesión telnet
#              devuelve un error de comando desconocido. MONITOREO (id=2).
# Para modificar:
#   - Mapa de comandos telnet por IP/slot → buscar [PARIDAD-PHP]
#   - Nº de reintentos telnet             → buscar [PARIDAD-PHP] _MAX_INTENTOS
#
# [PARIDAD-PHP] El PHP NO llamaba a ping_ip() en este worker (la función estaba
#   definida pero nunca invocada) — no se replica esa llamada sin efecto.
# ============================================================================

import logging
from datetime import datetime

from app.db import get_engine
from config.settings import IPS_MA5800X15_MPU_8_9, PROCESO_ID_MA5800X15_WORKER
from model.uplink_trafico_15m_ma5800x15.uplink_trafico_15m_ma5800x15_model import (
    get_puertos_pto1_12,
    insert_detalle,
    insert_hora,
)
from utils import monitoreo
from utils.parser_trafico import parsear_trafico
from utils.telnet_olt import leer_trafico_puertos, respuesta_valida

_MODELO = "MA5800-X15"

# [PARIDAD-PHP] Intento inicial + 2 reintentos, igual que el PHP
#               (estado_equipo -> verifica_equipo -> valida==2 -> reintenta, x2).
_MAX_INTENTOS = 3


def _contar_puertos(fila_puertos):
    """Cuenta cuántos puertos configurados hay por slot (0/16, 0/17, 0/18, 0/8, 0/9).

    Equivalente PHP: el bucle for($i=0;$i<12;$i++) que clasifica pto1..pto12
    según su prefijo (ej. '0/16/0' → slot '0/16').

    fila_puertos: iterable de 12 valores (pto1..pto12), cada uno None o un
                  string tipo '0/16/0'.
    """
    conteo = {"0/16": 0, "0/17": 0, "0/18": 0, "0/8": 0, "0/9": 0}
    for valor in (fila_puertos or []):
        if not valor:
            continue
        partes = valor.split("/")
        slot = f"{partes[0]}/{partes[1]}"
        if slot in conteo:
            conteo[slot] += 1
    return conteo


def _seleccionar_slots(ip):
    """Decide qué slots telnet usar según la IP, en el orden de prioridad del PHP.

    [PARIDAD-PHP] Las IPs en config.settings.IPS_MA5800X15_MPU_8_9 usan
                  'interface mpu 0/8' / 'interface mpu 0/9' (estado_equipo3);
                  el resto usa 'interface eth 0/16' / '0/17' / '0/18' (estado_equipo).

    Retorna lista ordenada de tuplas (slot_label, interface_cmd).
    """
    if ip in IPS_MA5800X15_MPU_8_9:
        return [
            ("0/8", "interface mpu 0/8"),
            ("0/9", "interface mpu 0/9"),
        ]
    return [
        ("0/16", "interface eth 0/16"),
        ("0/17", "interface eth 0/17"),
        ("0/18", "interface eth 0/18"),
    ]


def procesar_olt(server, ip, fecha, lote_id=None):
    """Procesa una OLT MA5800-X15. Llamado desde main.py en modo --worker.

    Equivalente PHP: proceso_uplink_trafico_exped.php.

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
            conn, PROCESO_ID_MA5800X15_WORKER, mensaje=ip,
            parent_id=lote_id, lote_id=lote_id,
        )

        # [SQL] Puertos configurados de esta OLT (pto1..pto12).
        fila_puertos = get_puertos_pto1_12(conn, _MODELO, server)
        conteo = _contar_puertos(fila_puertos)
        slots_orden = _seleccionar_slots(ip)

        # Solo se piden por telnet los slots con al menos 1 puerto configurado.
        comandos = [(cmd, conteo[slot]) for slot, cmd in slots_orden if conteo[slot] > 0]

        # [PARIDAD-PHP] Reintentos: hasta _MAX_INTENTOS sesiones telnet completas
        #               (cada una con su propio login/enable/config/logout) si la
        #               respuesta contiene el error de comando desconocido.
        texto = ""
        for intento in range(_MAX_INTENTOS):
            texto = leer_trafico_puertos(ip, comandos)
            if respuesta_valida(texto):
                break
            logging.warning(f"[{server}] Respuesta invalida en intento {intento + 1}")

        # [SQL] Parseo + inserción de detalle por puerto, en el orden de 'slots_orden'.
        lecturas = parsear_trafico(texto)
        idx_lectura = 0
        peak = 0.0

        for slot, _cmd in slots_orden:
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

        # [SQL] Resumen (peak) de la corrida de 15 min.
        insert_hora(conn, server=server, ip=ip, modelo=_MODELO, peak=peak, week=semana)

        monitoreo.finalizar(conn, registro_id)

    logging.info(f"{datetime.now():%Y-%m-%d %H:%M:%S} | Finalizado server={server}")
