-- Columna VAS Profile + indices para el UPDATE de submit_ONT_zip.php
-- Ejecutar fuera de la ventana del cron proceso_informacion_V2.php (TRUNCATE + carga de DETALLE_COMPLETO)

ALTER TABLE Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO
    ADD COLUMN `vas_profile` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL AFTER `version_sw`,
    ADD INDEX `idx_equipo_fsp_onu` (`equipo`, `frame_id`, `slot_id`, `port_id`, `onu_id`);

ALTER TABLE Aden.OLT_INFO_ONT_PRUEBA
    ADD INDEX `idx_equipo_fsp_onu` (`equipo`, `fn`, `sn`, `pn`, `onu`);
