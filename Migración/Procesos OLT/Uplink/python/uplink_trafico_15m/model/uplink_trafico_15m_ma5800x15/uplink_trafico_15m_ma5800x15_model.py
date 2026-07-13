# ============================================================================
# model/uplink_trafico_15m_ma5800x15/uplink_trafico_15m_ma5800x15_model.py
# Proceso: uplink_trafico_15m_ma5800x15
# Equivalente PHP: proceso_uplink_trafico.php + proceso_uplink_trafico_exped.php
# Descripción: Queries del proceso MA5800-X15 (lectura OLT_SERVER incl. pto1..pto12,
#              escritura OLT_TRAFICO_UPLINK_MA5800_X15 + OLT_TRAFICO_UPLINK_HORA).
# Para modificar: agregar/cambiar query → buscar [SQL]
# ============================================================================

from sqlalchemy import text


def get_olts(conn, modelo):
    """Lista las OLT del catálogo filtradas por modelo (MA5800-X15).

    Equivalente PHP (proceso_uplink_trafico.php):
        SELECT `server`, ip, modelo FROM OLT_SERVER WHERE modelo = 'MA5800-X15'

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


def get_puertos_pto1_12(conn, modelo, server):
    """Obtiene los 12 puertos configurados (pto1..pto12) de una OLT.

    Equivalente PHP (proceso_uplink_trafico.php):
        SELECT pto1, pto2, ..., pto12 FROM OLT_SERVER
        WHERE modelo = 'MA5800-X15' AND server = '$server'

    Retorna un único Row de 12 columnas (algunas pueden ser NULL).
    """
    # [SQL] Lectura de los puertos uplink configurados para esta OLT.
    sql = text("""
        SELECT
            OLT_SERVER.pto1, OLT_SERVER.pto2, OLT_SERVER.pto3, OLT_SERVER.pto4,
            OLT_SERVER.pto5, OLT_SERVER.pto6, OLT_SERVER.pto7, OLT_SERVER.pto8,
            OLT_SERVER.pto9, OLT_SERVER.pto10, OLT_SERVER.pto11, OLT_SERVER.pto12
        FROM OLT_SERVER
        WHERE OLT_SERVER.modelo = :modelo
          AND OLT_SERVER.server = :server
    """)
    return conn.execute(sql, {"modelo": modelo, "server": server}).fetchone()


def insert_detalle(conn, server, ip, modelo, puerto, trafico, week, trafico_up):
    """Inserta el detalle de tráfico de un puerto (bajada + subida).

    Equivalente PHP (proceso_uplink_trafico_exped.php):
        INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15
        (server,ip,modelo,puerto,trafico,fecha,week,trafico_up)
        VALUES ('$server','$ip','MA5800-X15','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')
    """
    # [SQL] Escritura de detalle por puerto. Tabla: OLT_TRAFICO_UPLINK_MA5800_X15. fecha=NOW().
    sql = text("""
        INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15
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

    Equivalente PHP (proceso_uplink_trafico_exped.php):
        INSERT INTO OLT_TRAFICO_UPLINK_HORA
        (server,ip,modelo,peak,fecha,week)
        VALUES ('$server','$ip','MA5800-X15','$peak',NOW(),'$semana')
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
