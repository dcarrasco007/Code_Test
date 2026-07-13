# ============================================================================
# scripts/uplink_trafico_15m_otros/script.py
# Proceso: uplink_trafico_15m_otros
# Equivalente PHP: proceso_uplink_trafico_603_680.php (monolítico)
# Descripción: Proceso de un solo paso (sin worker). Lista OLT (modelo ≠ MA5800-X15
#              y ≠ MA5600T) y procesa cada una secuencialmente: gate de ping →
#              telnet (scu/giu según server/modelo) → parseo → INSERT detalle + peak.
#              MONITOREO (proceso_id=5).
# Para modificar:
#   - Mapa de comandos telnet por server/modelo → buscar [PARIDAD-PHP] _comandos_dispatch
#   - Correcciones sobre el PHP original         → buscar [FIX-PHP]
# ============================================================================

import logging
from datetime import datetime, timedelta

from app.db import get_engine
from config.settings import (
    MODELO_OTROS_MA5603T,
    PROCESO_ID_OTROS,
    SERVER_OTROS_CONCEPCION,
    SERVER_OTROS_LASCONDES,
)
from model.uplink_trafico_15m_otros.uplink_trafico_15m_otros_model import (
    get_olts,
    get_puertos_gb,
    insert_detalle,
    insert_hora,
)
from utils import monitoreo
from utils.parser_trafico import parsear_trafico
from utils.ping import es_alcanzable, ping_ip
from utils.telnet_olt import leer_trafico_puertos, respuesta_valida

# Slots relevantes para este proceso — igual que el PHP, salvo dos omisiones
# documentadas (ver _contar_puertos).
_ORDEN_SLOTS = ("0/17", "0/18", "0/7", "0/8", "0/9")
_SLOTS_CON_DUPLICADO = {"0/17", "0/18", "0/7", "0/8", "0/9"}


def _contar_puertos(filas_puertos):
    """Cuenta puertos por slot desde OLT_PUERTAS_UPLINKS_GB.

    Equivalente PHP: bucle de clasificación en proceso_uplink_trafico_603_680.php.

    [PARIDAD-PHP] Si la PRIMERA fila vista de un slot tiene parte2=='1', el
                  contador de ese slot se incrementa una SEGUNDA vez —
                  comportamiento tal cual el PHP original. Aplica a los 5
                  slots rastreados aquí (17,18,7,8,9).
    [PARIDAD-PHP] El PHP también contaba slots '0/16', '0/19' y '0/20', pero
                  NINGUNA de las 3 funciones de dispatch de este proceso
                  (MA5603T/CONCEPCION-1/LASCONDES-1) los usa jamás — son dead
                  code en el original (el 0/16 tampoco se usa en los otros dos
                  procesos; 0/19-0/20 sí eran relevantes en MA5600T pero NO en
                  este proceso). No se replica ese conteo inerte.
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
    compartida con el proceso MA5600T que hace el mismo gate de ping).

    Equivalente PHP: if(trim($y)){ if($y < 100){ ...procesar... } }
    """
    return es_alcanzable(ping_ip(ip))


def _comandos_dispatch(server, modelo, conteo):
    """Decide qué comandos telnet enviar según modelo/server, replicando el
    árbol if/elseif/elseif del PHP (orden de prioridad: MA5603T, luego
    CONCEPCION-1, luego LASCONDES-1). Devuelve None si ninguno matchea —
    equivalente a que el PHP deje $texto sin definir (no se hace telnet, pero
    igual se inserta peak=0 al final; ver run()).

    [PARIDAD-PHP] MA5603T (estado_equipo4): interface giu 0/7, 0/8, 0/9.
    [PARIDAD-PHP] OLT-CONCEPCION-1 (estado_equipo): interface scu 0/7, 0/8.
    [FIX-PHP] OLT-LASCONDES-1 (estado_equipo3): el PHP original tenía un bug
              real — el for-loop de puerto18 usaba por error el límite de
              $puerto08 (`for($x=0;$x<$puerto08;$x++)`) en vez de $puerto18,
              y además un 'break' inmediatamente después del bloque dejaba
              código muerto (un chequeo final inalcanzable). Aquí se usa
              correctamente conteo["0/18"] como límite. Los parámetros
              puerto07/puerto08 que el PHP le pasaba a estado_equipo3 nunca
              se usaban dentro de esa función — se omiten aquí sin cambiar
              el resultado.
    """
    if modelo == MODELO_OTROS_MA5603T:
        return [
            ("interface giu 0/7", conteo["0/7"]),
            ("interface giu 0/8", conteo["0/8"]),
            ("interface giu 0/9", conteo["0/9"]),
        ]
    if server == SERVER_OTROS_CONCEPCION:
        return [
            ("interface scu 0/7", conteo["0/7"]),
            ("interface scu 0/8", conteo["0/8"]),
        ]
    if server == SERVER_OTROS_LASCONDES:
        return [
            ("interface giu 0/17", conteo["0/17"]),
            ("interface giu 0/18", conteo["0/18"]),  # [FIX-PHP] antes usaba puerto08 como límite
        ]
    return None


def _ejecutar_con_reintento(ip, comandos):
    """Ejecuta la sesión telnet inicial y, si falla, UN reintento con los
    mismos comandos.

    [PARIDAD-PHP] El PHP reintenta llamando a la MISMA función estado_equipo*
                  una segunda vez si verifica_equipo()==2, sin más reintentos
                  que ese (a diferencia de MA5600T, que tiene un árbol de
                  reintentos más elaborado).

    Retorna (texto, fallo_definitivo).
    """
    texto = leer_trafico_puertos(ip, comandos)
    valido = respuesta_valida(texto)
    if not valido:
        texto = leer_trafico_puertos(ip, comandos)
        valido = respuesta_valida(texto)
    return texto, (not valido)


def run(fecha=None):
    """Proceso monolítico 'otros'. Llamado desde main.py.
    En este proceso el modo --worker no aplica (no hay subprocess por OLT).

    Equivalente PHP: proceso_uplink_trafico_603_680.php.

    [PARIDAD-PHP] 'fecha' se acepta por consistencia con el CLI de main.py pero
                  NO se usa: 'semana' y 'hora' (ahora+9h) se calculan aquí mismo
                  con datetime.now(), igual que el PHP original (no aceptaba
                  override de fecha). No confundir con la variable local 'hora'
                  usada más abajo para insert_hora(fecha=hora, ...) — es un
                  cálculo interno, no relacionado con este parámetro.
    """
    logging.info(f"{datetime.now():%Y-%m-%d %H:%M:%S} | Proceso iniciado")

    engine = get_engine()

    # [PARIDAD-PHP] $semana y $hora se calculan UNA vez para todo el run (no por
    #               OLT) — mismo valor para todas las OLTs procesadas en esta pasada.
    semana = datetime.now().isocalendar()[1]
    hora = (datetime.now() + timedelta(hours=9)).strftime("%Y-%m-%d %H:%M:%S")

    with engine.begin() as conn:
        # [SQL] MONITOREO: registro padre (RUNNING). No hay hijos (proceso monolítico).
        lote_id = monitoreo.iniciar(conn, PROCESO_ID_OTROS, mensaje="Porceso Primario")
        # [SQL] Catálogo de OLT (modelo != MA5800-X15 y != MA5600T)
        olts = get_olts(conn)

    if not olts:
        logging.warning("No se encontraron OLTs (modelo != MA5800-X15 y != MA5600T).")
        with engine.begin() as conn:
            monitoreo.finalizar(conn, lote_id, cantidad_subprocesos=0)
        return

    logging.info(f"OLTs encontradas: {len(olts)}")
    nprocesos = 0

    for server, ip, modelo in olts:
        # [PARIDAD-PHP] Se cuenta ANTES del gate de ping, igual que el PHP
        #               ($nprocesos++ al inicio del while, antes de ping_ip()).
        nprocesos += 1

        if not _ping_ok(ip):
            logging.info(f"[{server}] Ping no exitoso — se omite telnet.")
            continue

        with engine.begin() as conn:
            # [SQL] Puertos configurados de esta OLT.
            filas_puertos = get_puertos_gb(conn, server)
            conteo = _contar_puertos(filas_puertos)

            comandos = _comandos_dispatch(server, modelo, conteo)

            if comandos is None:
                # [PARIDAD-PHP] Sin regla de dispatch conocida: el PHP no hace
                #               telnet ($texto queda indefinido), pero SÍ llega
                #               a insertar peak=0 al final (fuera de cualquier
                #               condicional de dispatch).
                logging.warning(f"[{server}] modelo='{modelo}' sin regla de dispatch conocida.")
                insert_hora(
                    conn, server=server, ip=ip, modelo=modelo,
                    peak=0.0, fecha=hora, week=semana,
                )
                continue

            texto, fallo = _ejecutar_con_reintento(ip, comandos)
            # [PARIDAD-PHP] A diferencia de MA5600T, aquí NO hay marcador de fallo
            #               (modelo='...2') ni se limpia $texto tras un fallo
            #               definitivo — el PHP simplemente sigue parseando lo
            #               que haya en $texto (que, si sigue inválido, no
            #               contendrá líneas de tráfico reconocibles).

            # [SQL] Parseo + inserción de detalle, en el orden 17,18,7,8,9 del PHP.
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
                        conn, server=server, ip=ip, modelo=modelo,
                        puerto=puerta_ingreso, trafico=bajada, week=semana,
                        trafico_up=subida,
                    )
                    peak += float(bajada or 0)

            # [SQL] Resumen (peak). fecha=hora (NO NOW()) — ver [PARIDAD-PHP] arriba.
            insert_hora(
                conn, server=server, ip=ip, modelo=modelo,
                peak=peak, fecha=hora, week=semana,
            )

    with engine.begin() as conn:
        monitoreo.finalizar(conn, lote_id, cantidad_subprocesos=nprocesos)

    logging.info(
        f"{datetime.now():%Y-%m-%d %H:%M:%S} | Proceso finalizado | OLTs procesadas={nprocesos}"
    )
