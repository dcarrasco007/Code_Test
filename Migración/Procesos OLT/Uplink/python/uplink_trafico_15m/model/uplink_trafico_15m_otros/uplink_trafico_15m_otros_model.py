# ============================================================================
# model/uplink_trafico_15m_otros/uplink_trafico_15m_otros_model.py
# Proceso: uplink_trafico_15m_otros
# Equivalente PHP: proceso_uplink_trafico_603_680.php (monolítico)
# Descripción: Queries del proceso "otros" (modelos ≠ MA5800-X15 y ≠ MA5600T:
#              MA5603T + servers OLT-CONCEPCION-1 / OLT-LASCONDES-1).
#              Lectura OLT_SERVER + OLT_PUERTAS_UPLINKS_GB,
#              escritura OLT_TRAFICO_UPLINK_MA5600T + OLT_TRAFICO_UPLINK_HORA.
# Para modificar: agregar/cambiar query → buscar [SQL]
# ============================================================================

from sqlalchemy import text


def get_olts(conn):
    """Lista las OLT del catálogo que NO son MA5800-X15 ni MA5600T.

    Equivalente PHP (proceso_uplink_trafico_603_680.php):
        SELECT `server`, ip, modelo FROM OLT_SERVER
        WHERE modelo <> 'MA5800-X15' AND modelo <> 'MA5600T'

    Retorna lista de Row: (server, ip, modelo)
    """
    # [SQL] Lectura del catálogo OLT_SERVER, excluyendo los otros dos procesos.
    sql = text("""
        SELECT
            OLT_SERVER.`server`,
            OLT_SERVER.ip,
            OLT_SERVER.modelo
        FROM OLT_SERVER
        WHERE OLT_SERVER.modelo <> 'MA5800-X15'
          AND OLT_SERVER.modelo <> 'MA5600T'
    """)
    return conn.execute(sql).fetchall()


def get_puertos_gb(conn, server):
    """Obtiene los puertos uplink de una OLT desde OLT_PUERTAS_UPLINKS_GB.

    Equivalente PHP:
        SELECT OLT_PUERTAS_UPLINKS_GB.olt, OLT_PUERTAS_UPLINKS_GB.puerta
        FROM OLT_SERVER INNER JOIN OLT_PUERTAS_UPLINKS_GB
            ON OLT_SERVER.server = OLT_PUERTAS_UPLINKS_GB.olt
        WHERE OLT_SERVER.modelo <> 'MA5800-X15' AND OLT_SERVER.modelo <> 'MA5600T'
          AND OLT_SERVER.server = '$server'

    Retorna lista de Row: (olt, puerta)
    """
    # [SQL] Lectura de puertos uplink configurados. Tabla: OLT_PUERTAS_UPLINKS_GB.
    sql = text("""
        SELECT
            OLT_PUERTAS_UPLINKS_GB.olt,
            OLT_PUERTAS_UPLINKS_GB.puerta
        FROM OLT_SERVER
        INNER JOIN OLT_PUERTAS_UPLINKS_GB
            ON OLT_SERVER.server = OLT_PUERTAS_UPLINKS_GB.olt
        WHERE OLT_SERVER.modelo <> 'MA5800-X15'
          AND OLT_SERVER.modelo <> 'MA5600T'
          AND OLT_SERVER.server = :server
    """)
    return conn.execute(sql, {"server": server}).fetchall()


def insert_detalle(conn, server, ip, modelo, puerto, trafico, week, trafico_up):
    """Inserta el detalle de tráfico de un puerto (bajada + subida).

    Equivalente PHP:
        INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
        (server,ip,modelo,puerto,trafico,fecha,week,trafico_up)
        VALUES ('$server','$ip','$row[2]','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')

    [PARIDAD-PHP] modelo es el modelo REAL de la OLT (ej. 'MA5603T'), no un valor fijo
                  (a diferencia de los procesos MA5800-X15/MA5600T que sí lo fijan).
    """
    # [SQL] Escritura de detalle por puerto. Tabla: OLT_TRAFICO_UPLINK_MA5600T. fecha=NOW().
    sql = text("""
        INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
            (server, ip, modelo, puerto, trafico, fecha, week, trafico_up)
        VALUES
            (:server, :ip, :modelo, :puerto, :trafico, NOW(), :week, :trafico_up)
    """)
    conn.execute(sql, {
        "server": server, "ip": ip, "modelo": modelo, "puerto": puerto,
        "trafico": trafico, "week": week, "trafico_up": trafico_up,
    })


def insert_hora(conn, server, ip, modelo, peak, fecha, week):
    """Inserta el peak (pico) de tráfico de la OLT para esta corrida de 15 min.

    Equivalente PHP:
        INSERT INTO OLT_TRAFICO_UPLINK_HORA
        (server,ip,modelo,peak,fecha,week) VALUES ('$server','$ip','$row[2]','$peak','$hora','$semana')

    [PARIDAD-PHP] A diferencia de los otros dos procesos (que usan NOW() en SQL), este
                  SÍ recibe 'fecha' como parámetro explícito: el PHP la calcula como
                  'ahora + 9 horas' antes de insertar. El worker/script (Fase 7) debe
                  calcular ese mismo offset y pasarlo aquí. Ver PLAN_MIGRACION_15M.md §2.4.
    """
    # [SQL] Escritura de peak. Tabla: OLT_TRAFICO_UPLINK_HORA. fecha=parámetro (NO NOW()).
    sql = text("""
        INSERT INTO OLT_TRAFICO_UPLINK_HORA
            (server, ip, modelo, peak, fecha, week)
        VALUES
            (:server, :ip, :modelo, :peak, :fecha, :week)
    """)
    conn.execute(sql, {
        "server": server, "ip": ip, "modelo": modelo, "peak": peak,
        "fecha": fecha, "week": week,
    })
