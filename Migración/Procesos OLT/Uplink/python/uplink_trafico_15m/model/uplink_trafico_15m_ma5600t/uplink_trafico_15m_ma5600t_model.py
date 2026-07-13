# ============================================================================
# model/uplink_trafico_15m_ma5600t/uplink_trafico_15m_ma5600t_model.py
# Proceso: uplink_trafico_15m_ma5600t
# Equivalente PHP: proceso_uplink_trafico_MA5600T.php + proceso_uplink_trafico_MA5600T_exped.php
# Descripción: Queries del proceso MA5600T (lectura OLT_SERVER + OLT_PUERTAS_UPLINKS_GB,
#              escritura OLT_TRAFICO_UPLINK_MA5600T + OLT_TRAFICO_UPLINK_HORA).
# Para modificar: agregar/cambiar query → buscar [SQL]
# ============================================================================

from sqlalchemy import text


def get_olts(conn, modelo):
    """Lista las OLT del catálogo filtradas por modelo (MA5600T).

    Equivalente PHP (proceso_uplink_trafico_MA5600T.php):
        SELECT `server`, ip, modelo FROM OLT_SERVER WHERE modelo = 'MA5600T'

    Retorna lista de Row: (server, ip, modelo)
    """
    # [SQL] Lectura del catálogo OLT_SERVER.
    sql = text("""
        SELECT
            OLT_SERVER.`server`,
            OLT_SERVER.ip,
            OLT_SERVER.modelo
        FROM OLT_SERVER
        WHERE OLT_SERVER.modelo = :modelo
    """)
    return conn.execute(sql, {"modelo": modelo}).fetchall()


def get_puertos_gb(conn, modelo, server):
    """Obtiene los puertos uplink de una OLT desde OLT_PUERTAS_UPLINKS_GB.

    Equivalente PHP (proceso_uplink_trafico_MA5600T_exped.php):
        SELECT OLT_PUERTAS_UPLINKS_GB.olt, OLT_PUERTAS_UPLINKS_GB.puerta
        FROM OLT_SERVER INNER JOIN OLT_PUERTAS_UPLINKS_GB
            ON OLT_SERVER.server = OLT_PUERTAS_UPLINKS_GB.olt
        WHERE OLT_SERVER.modelo = 'MA5600T' AND OLT_SERVER.server = '$server'

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
        WHERE OLT_SERVER.modelo = :modelo
          AND OLT_SERVER.server = :server
    """)
    return conn.execute(sql, {"modelo": modelo, "server": server}).fetchall()


def insert_detalle(conn, server, ip, modelo, puerto, trafico, week, trafico_up):
    """Inserta el detalle de tráfico de un puerto (bajada + subida).

    Equivalente PHP:
        INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
        (server,ip,modelo,puerto,trafico,fecha,week,trafico_up)
        VALUES ('$server','$ip','MA5600T','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')
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


def insert_hora(conn, server, ip, modelo, peak, week):
    """Inserta el peak (pico) de tráfico de la OLT para esta corrida de 15 min.

    Equivalente PHP:
        INSERT INTO OLT_TRAFICO_UPLINK_HORA
        (server,ip,modelo,peak,fecha,week)
        VALUES ('$server','$ip','MA5600T','$peak',NOW(),'$semana')

    [PARIDAD-PHP] El worker también usa esta función para el caso 'valida==2'
                  (reintentos telnet agotados), pasando modelo='MA5600T2' y peak='0'
                  — un marcador de fallo, no un modelo real. Ver PLAN_MIGRACION_15M.md §2.
    """
    # [SQL] Escritura de peak. Tabla: OLT_TRAFICO_UPLINK_HORA. fecha=NOW().
    sql = text("""
        INSERT INTO OLT_TRAFICO_UPLINK_HORA
            (server, ip, modelo, peak, fecha, week)
        VALUES
            (:server, :ip, :modelo, :peak, NOW(), :week)
    """)
    conn.execute(sql, {
        "server": server, "ip": ip, "modelo": modelo, "peak": peak, "week": week,
    })
