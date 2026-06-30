# ============================================================================
# model/uplink_trafico_diario_ma5800x15/uplink_trafico_diario_ma5800x15_model.py
# Proceso: uplink_trafico_diario_ma5800x15
# Equivalente PHP: proceso_uplink_trafico_diario_exped.php (sección de queries)
#                  proceso_uplink_trafico_diario_conexped.php (query OLT_SERVER)
# Descripción: Todas las consultas SQL del proceso, parametrizadas con text().
#              NINGUNA query debe vivir fuera de este archivo.
# Para modificar:
#   - Agregar o cambiar una query → buscar [SQL]
#   - Cálculos que deben coincidir con el PHP → buscar [PARIDAD-PHP]
#   - Nombre de tabla dinámica permitida → buscar _TABLAS_OCUPACION
# ============================================================================

from sqlalchemy import text

# ─── Whitelist de tablas permitidas en insert_ocupacion ──────────────────────
# [PARIDAD-PHP] El PHP inserta en estas dos tablas de ocupación.
#               Agregar aquí si en el futuro se suman más tablas del mismo tipo.
_TABLAS_OCUPACION = frozenset({"OLT_TRAFICOGPON", "OLT_TRAFICOGPON2"})


# ─── LECTURA: catálogo de OLT ────────────────────────────────────────────────

def get_olts_ma5800x15(conn, modelo):
    """Lista las OLT del catálogo filtradas por modelo.

    Equivalente PHP (conexped.php):
        SELECT `server`, ip, modelo FROM OLT_SERVER WHERE modelo = 'MA5800-X15'

    Retorna lista de Row: (server, ip, modelo)
    """
    # [SQL] Lectura del catálogo OLT_SERVER. El modelo viene de settings.MODELO_OLT_MA5800X15.
    sql = text("""
        SELECT
            OLT_SERVER.`server`,
            OLT_SERVER.ip,
            OLT_SERVER.modelo
        FROM OLT_SERVER
        WHERE OLT_SERVER.modelo = :modelo
    """)
    return conn.execute(sql, {"modelo": modelo}).fetchall()


# ─── LECTURA: datos de tráfico de una OLT ────────────────────────────────────

def get_puertos(conn, server, fecha):
    """Obtiene los puertos con datos de tráfico para un server y fecha dados.

    Equivalente PHP (exped.php):
        SELECT DISTINCT(puerto)
        FROM OLT_TRAFICO_UPLINK_MA5800_X15
        WHERE server='$server' AND DATE(fecha)='$fecha'

    Retorna lista de Row: (puerto,)
    """
    # [SQL] Lectura de puertos distintos con datos ese día. Tabla: OLT_TRAFICO_UPLINK_MA5800_X15
    sql = text("""
        SELECT DISTINCT(puerto)
        FROM OLT_TRAFICO_UPLINK_MA5800_X15
        WHERE server = :server
          AND DATE(fecha) = :fecha
    """)
    return conn.execute(sql, {"server": server, "fecha": fecha}).fetchall()


def get_promedios_puerto(conn, server, fecha, puerto):
    """Promedio de tráfico down y up de un puerto en una fecha dada.

    Equivalente PHP (exped.php):
        SELECT server, ip, week, AVG(trafico), AVG(trafico_up)
        FROM OLT_TRAFICO_UPLINK_MA5800_X15
        WHERE server='$server' AND DATE(fecha)='$fecha' AND puerto='$puerto'

    Retorna un único Row: (server, ip, week, avg_trafico, avg_trafico_up)
    """
    # [SQL] AVG de tráfico por puerto. Tabla: OLT_TRAFICO_UPLINK_MA5800_X15
    sql = text("""
        SELECT server, ip, week, AVG(trafico), AVG(trafico_up)
        FROM OLT_TRAFICO_UPLINK_MA5800_X15
        WHERE server = :server
          AND DATE(fecha) = :fecha
          AND puerto = :puerto
    """)
    return conn.execute(sql, {"server": server, "fecha": fecha, "puerto": puerto}).fetchone()


def get_max_puerto(conn, server, fecha, puerto):
    """Pico (MAX) de tráfico down y up de un puerto en una fecha dada.

    Equivalente PHP (exped.php):
        SELECT server, ip, week, MAX(trafico), MAX(trafico_up)
        FROM OLT_TRAFICO_UPLINK_MA5800_X15
        WHERE server='$server' AND DATE(fecha)='$fecha' AND puerto='$puerto'

    Retorna un único Row: (server, ip, week, max_trafico, max_trafico_up)
    """
    # [SQL] MAX de tráfico por puerto. Tabla: OLT_TRAFICO_UPLINK_MA5800_X15
    sql = text("""
        SELECT server, ip, week, MAX(trafico), MAX(trafico_up)
        FROM OLT_TRAFICO_UPLINK_MA5800_X15
        WHERE server = :server
          AND DATE(fecha) = :fecha
          AND puerto = :puerto
    """)
    return conn.execute(sql, {"server": server, "fecha": fecha, "puerto": puerto}).fetchone()


# ─── ESCRITURA ───────────────────────────────────────────────────────────────

def insert_dia_puerta(conn, equipo, ip, puerta, promedio_down, fecha, week, promedio_up):
    """Inserta el promedio diario de tráfico por puerta (en Mbps).

    Equivalente PHP (exped.php):
        INSERT INTO Aden.OLT_TRAFICO_DIA_PUERTA
          (equipo, ip, puerta, promedio_trafico_down, fecha, week, promedio_trafico_up)
        VALUES(server, ip, puerto, round(avg/1024,2), fecha, week, round(avg_up/1024,2))

    [PARIDAD-PHP] Los valores promedio_down y promedio_up deben venir ya
                  calculados como round(avg/1024, 2) desde el worker.
    """
    # [SQL] Escritura de promedio por puerta. Tabla: Aden.OLT_TRAFICO_DIA_PUERTA
    sql = text("""
        INSERT INTO Aden.OLT_TRAFICO_DIA_PUERTA
            (equipo, ip, puerta, promedio_trafico_down, fecha, week, promedio_trafico_up)
        VALUES
            (:equipo, :ip, :puerta, :promedio_down, :fecha, :week, :promedio_up)
    """)
    conn.execute(sql, {
        "equipo":       equipo,
        "ip":           ip,
        "puerta":       puerta,
        "promedio_down": promedio_down,
        "fecha":        fecha,
        "week":         week,
        "promedio_up":  promedio_up,
    })


def insert_ocupacion(conn, tabla, ip_equipo, port, up_mbps, down_mbps, fecha):
    """Inserta registro de ocupación (up/down en Mbps formateado) en una tabla de tráfico.

    Se usa para dos tablas (misma estructura):
      - OLT_TRAFICOGPON2
      - OLT_TRAFICOGPON

    Equivalente PHP (exped.php):
        INSERT INTO OLT_TRAFICOGPON2 (ip_equipo, port, up_mbps, down_mbps, fecha)
        VALUES (ip, puerto, valorUP, valorDown, fecha)

    [PARIDAD-PHP] up_mbps y down_mbps deben venir ya formateados desde el worker
                  usando number_format(valor, 2) — ver nota en worker.py [PARIDAD-PHP].
    """
    if tabla not in _TABLAS_OCUPACION:
        raise ValueError(
            f"Tabla '{tabla}' no permitida. Válidas: {sorted(_TABLAS_OCUPACION)}"
        )

    # [SQL] Escritura de ocupación. Tabla dinámica (validada por whitelist).
    sql = text(f"""
        INSERT INTO {tabla} (ip_equipo, port, up_mbps, down_mbps, fecha)
        VALUES (:ip_equipo, :port, :up_mbps, :down_mbps, :fecha)
    """)
    conn.execute(sql, {
        "ip_equipo": ip_equipo,
        "port":      port,
        "up_mbps":   up_mbps,
        "down_mbps": down_mbps,
        "fecha":     fecha,
    })


def insert_resumen_dia(conn, server, ip, promedio_trafico, peack_trafico,
                       fecha, week, promedio_trafico_up, peack_trafico_up):
    """Inserta el resumen diario de tráfico de una OLT completa.

    Equivalente PHP (exped.php):
        INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15_DAY
          (server, ip, promedio_trafico, peack_trafico, fecha, week,
           promedio_trafico_up, peack_trafico_up)
        VALUES(server, ip, round(promedios,1), round(peak,1), fecha, week,
               promediosUP, peakUP)

    [PARIDAD-PHP] promedio_trafico y peack_trafico vienen con round(,1) del worker.
                  promedio_trafico_up y peack_trafico_up se insertan SIN redondear
                  (comportamiento original del PHP).
    Nota: 'peack' es el typo original del PHP/BD; se conserva para compatibilidad.
    """
    # [SQL] Escritura del resumen diario por OLT. Tabla: OLT_TRAFICO_UPLINK_MA5800_X15_DAY
    sql = text("""
        INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15_DAY
            (server, ip, promedio_trafico, peack_trafico, fecha, week,
             promedio_trafico_up, peack_trafico_up)
        VALUES
            (:server, :ip, :promedio_trafico, :peack_trafico, :fecha, :week,
             :promedio_trafico_up, :peack_trafico_up)
    """)
    conn.execute(sql, {
        "server":             server,
        "ip":                 ip,
        "promedio_trafico":   promedio_trafico,
        "peack_trafico":      peack_trafico,
        "fecha":              fecha,
        "week":               week,
        "promedio_trafico_up": promedio_trafico_up,
        "peack_trafico_up":   peack_trafico_up,
    })
