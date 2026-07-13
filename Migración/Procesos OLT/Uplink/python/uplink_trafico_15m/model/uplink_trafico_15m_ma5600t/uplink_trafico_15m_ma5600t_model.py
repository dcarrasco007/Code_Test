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
