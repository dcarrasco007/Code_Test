-- ============================================================================
-- sql/03_purga_logs.sql
-- Proyecto: api_olt_consultas
--
-- QUE HACE: crea un EVENT de MySQL que corre una vez al dia y purga/limpia
--           las tablas OLT_API_* que acumulan filas sin parar (logs, jobs,
--           auditoria, sesiones). Sin esto, OLT_API_LOG_TELNET en particular
--           crecería indefinidamente (guarda el texto crudo de cada sesion
--           telnet).
--
-- POR QUE: LOG_RETENCION_DIAS en el .env (default 30) es el valor que debe
--          coincidir con el DELETE de OLT_API_LOG_TELNET de abajo. Si se
--          cambia esa variable, actualizar tambien el numero aqui (el EVENT
--          no lee el .env de la API).
--
-- REQUIERE: el scheduler de eventos de MySQL activo. Verificar primero:
--     SHOW VARIABLES LIKE 'event_scheduler';
--   Si dice OFF, activarlo (afecta a todo el servidor MySQL, no solo a esta
--   tabla — revisar con el DBA si ya hay otros EVENT dependiendo de esto):
--     SET GLOBAL event_scheduler = ON;
--
-- QUIEN LO EJECUTA: el responsable del proyecto / DBA. El agente de IA no
--          ejecuta CREATE EVENT (es DDL) — ver README_LIMITES.md.
--
-- ROLLBACK: DROP EVENT IF EXISTS evt_purga_api_olt_logs;
-- ============================================================================

DELIMITER $$

CREATE EVENT IF NOT EXISTS `evt_purga_api_olt_logs`
ON SCHEDULE EVERY 1 DAY
STARTS (CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 3 HOUR)  -- corre a las 03:00, fuera de horas punta
COMMENT 'Purga/retencion de las tablas OLT_API_* de api_olt_consultas. Ver sql/03_purga_logs.sql.'
DO
BEGIN
    -- Log crudo de telnet: retencion segun LOG_RETENCION_DIAS del .env
    -- (default 30 dias). Si esa variable cambia, actualizar el numero aqui.
    DELETE FROM OLT_API_LOG_TELNET
    WHERE fecha < (NOW() - INTERVAL 30 DAY);

    -- Auditoria: se conserva mas tiempo (90 dias) por trazabilidad de seguridad.
    DELETE FROM OLT_API_AUDITORIA
    WHERE fecha < (NOW() - INTERVAL 90 DAY);

    -- Intentos de autenticacion: limpiar solo los que ya no estan bloqueados
    -- y llevan mas de 7 dias sin actividad (no se tocan los bloqueados vigentes).
    DELETE FROM OLT_API_INTENTOS_AUTH
    WHERE (bloqueado_hasta IS NULL OR bloqueado_hasta < NOW())
      AND ultima_fecha < (NOW() - INTERVAL 7 DAY);

    -- Jobs terminados (OK/ERROR/TIMEOUT): conservar 30 dias.
    DELETE FROM OLT_API_JOBS
    WHERE estado IN ('OK','ERROR','TIMEOUT')
      AND fecha_fin IS NOT NULL
      AND fecha_fin < (NOW() - INTERVAL 30 DAY);

    -- Sesiones telnet ya CERRADAS: conservar 7 dias (solo utiles para
    -- diagnostico reciente de concurrencia/cupo).
    DELETE FROM OLT_API_SESIONES_TELNET
    WHERE estado = 'CERRADA'
      AND fecha_cierre IS NOT NULL
      AND fecha_cierre < (NOW() - INTERVAL 7 DAY);

    -- Sesiones HUERFANAS muy antiguas: en condiciones normales la API las
    -- cierra sola (logout garantizado en utils/telnet_olt.py). Si una queda
    -- HUERFANA mas de 1 dia, algo no la marco bien al cerrarse — se migra a
    -- CERRADA con motivo explicito para no perder el registro ni dejar
    -- basura reservando cupo indefinidamente en el gobernador.
    UPDATE OLT_API_SESIONES_TELNET
    SET estado = 'CERRADA',
        fecha_cierre = NOW(),
        motivo_cierre = 'purga automatica: huerfana por mas de 1 dia'
    WHERE estado = 'HUERFANA'
      AND ultimo_latido < (NOW() - INTERVAL 1 DAY);
END$$

DELIMITER ;

-- ============================================================================
-- VERIFICACION
-- ============================================================================
-- SHOW EVENTS LIKE 'evt_purga_api_olt_logs';
-- SELECT EVENT_NAME, STATUS, LAST_EXECUTED, INTERVAL_VALUE, INTERVAL_FIELD
--   FROM information_schema.EVENTS
--   WHERE EVENT_SCHEMA = 'Aden' AND EVENT_NAME = 'evt_purga_api_olt_logs';

-- Para forzar una corrida manual de prueba sin esperar al horario programado:
--   CALL <no aplica: los EVENT de MySQL no son procedimientos invocables>
-- En su lugar, para probar la logica, copiar el cuerpo del BEGIN...END y
-- ejecutarlo suelto en una sesion de prueba (revisando antes los DELETE).

-- ============================================================================
-- ROLLBACK
-- ============================================================================
-- DROP EVENT IF EXISTS evt_purga_api_olt_logs;
