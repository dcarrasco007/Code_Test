# ============================================================================
# utils/monitoreo.py
# Proyecto: uplink_trafico_15m (compartido por los 3 procesos)
# Equivalente PHP: bloques MONITOREO (INSERT RUNNING / UPDATE OK) de cada proceso
# Descripción: Registra la ejecución de cada proceso en MONITOREO_PROCESOS_EJECUCIONES.
#              Dos patrones (según el PHP):
#                - Proceso PADRE (orquestador o script monolítico): sin parent_id/
#                  lote_id, con cantidad_subprocesos.
#                - Proceso HIJO (worker): con parent_id/lote_id (= id del padre),
#                  sin cantidad_subprocesos.
#              IDs de proceso fijos: ver config/settings.py [CONFIG] PROCESO_ID_*.
# Para modificar:
#   - IDs de proceso → config/settings.py
#   - Columnas/tabla → buscar [SQL]
# ============================================================================

from sqlalchemy import text


def iniciar(conn, proceso_id, mensaje="Porceso Primario", parent_id=None, lote_id=None):
    """Inserta una fila RUNNING en MONITOREO_PROCESOS_EJECUCIONES y devuelve el id generado.

    Equivalente PHP (proceso padre — orquestador o script monolítico):
        INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
        (proceso_id,fecha_inicio,estado,fecha_registro,cantidad_subprocesos,
         cantidad_subprocesos_completados,mensaje)
        VALUES ($proceso_id,'$fecha_monitor','RUNNING','$fecha_monitor',0,0,'Porceso Primario')

    Equivalente PHP (proceso hijo — worker, cuando se pasan parent_id/lote_id):
        INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
        (proceso_id,parent_id,lote_id,fecha_inicio,estado,fecha_registro,mensaje)
        VALUES ($proceso_id,$lote,$lote,'$fecha_monitor','RUNNING','$fecha_monitor','$ip')

    [PARIDAD-PHP] El PHP calcula $fecha_monitor en la aplicación antes del INSERT;
                  aquí se usa NOW() del servidor de BD (equivalente, sin drift relevante).
    """
    if parent_id is None:
        # [SQL] Proceso padre: sin parent_id/lote_id, arranca contadores de subprocesos en 0.
        sql = text("""
            INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
                (proceso_id, fecha_inicio, estado, fecha_registro,
                 cantidad_subprocesos, cantidad_subprocesos_completados, mensaje)
            VALUES
                (:proceso_id, NOW(), 'RUNNING', NOW(), 0, 0, :mensaje)
        """)
        result = conn.execute(sql, {"proceso_id": proceso_id, "mensaje": mensaje})
    else:
        # [SQL] Proceso hijo: referencia al padre vía parent_id/lote_id (mismo valor, como el PHP).
        sql = text("""
            INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
                (proceso_id, parent_id, lote_id, fecha_inicio, estado, fecha_registro, mensaje)
            VALUES
                (:proceso_id, :parent_id, :lote_id, NOW(), 'RUNNING', NOW(), :mensaje)
        """)
        result = conn.execute(sql, {
            "proceso_id": proceso_id,
            "parent_id":  parent_id,
            "lote_id":    lote_id,
            "mensaje":    mensaje,
        })

    return result.lastrowid


def finalizar(conn, registro_id, estado="OK", cantidad_subprocesos=None):
    """Actualiza la fila a estado final, calculando duración.

    Equivalente PHP (proceso padre, con cantidad_subprocesos=$nprocesos):
        UPDATE MONITOREO_PROCESOS_EJECUCIONES
        SET fecha_fin=..., duracion=TIMESTAMPDIFF(SECOND, fecha_inicio, ...),
            estado='OK', cantidad_subprocesos=$nprocesos
        WHERE id = $parent_id

    Equivalente PHP (proceso hijo, sin cantidad_subprocesos):
        UPDATE MONITOREO_PROCESOS_EJECUCIONES
        SET fecha_fin=..., duracion=TIMESTAMPDIFF(SECOND, fecha_inicio, ...), estado='OK'
        WHERE id = $id_hijo
    """
    if cantidad_subprocesos is not None:
        # [SQL] Cierre de proceso padre: incluye cantidad_subprocesos.
        sql = text("""
            UPDATE MONITOREO_PROCESOS_EJECUCIONES
            SET fecha_fin = NOW(),
                duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, NOW()),
                estado = :estado,
                cantidad_subprocesos = :cantidad_subprocesos
            WHERE id = :id
        """)
        conn.execute(sql, {
            "estado": estado,
            "cantidad_subprocesos": cantidad_subprocesos,
            "id": registro_id,
        })
    else:
        # [SQL] Cierre de proceso hijo: no toca cantidad_subprocesos.
        sql = text("""
            UPDATE MONITOREO_PROCESOS_EJECUCIONES
            SET fecha_fin = NOW(),
                duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, NOW()),
                estado = :estado
            WHERE id = :id
        """)
        conn.execute(sql, {"estado": estado, "id": registro_id})
