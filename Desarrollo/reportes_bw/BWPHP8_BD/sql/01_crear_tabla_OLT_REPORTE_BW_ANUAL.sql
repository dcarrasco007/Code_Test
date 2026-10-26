-- ---------------------------------------------------------------------------
-- Reporte BW anual (etapa 1): una fila por OLT y día.
-- Reemplaza al Excel anual guardado en disco. El cron agrega cada semana los
-- 7 días nuevos y la ventana web arma el Excel desde esta tabla al descargar.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `OLT_REPORTE_BW_ANUAL` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `anio`       SMALLINT(6)  NOT NULL COMMENT 'Año del reporte (semana 1 = la que contiene el 1 de enero)',
  `semana`     TINYINT(4)   NOT NULL COMMENT 'Semana del reporte (1..52/53)',
  `fecha`      DATE         NOT NULL COMMENT 'Día',
  `orden`      SMALLINT(6)  NOT NULL COMMENT 'Posición de la OLT en el Excel (1 = fila 3)',
  `grupo`      SMALLINT(6)  NOT NULL COMMENT 'Grupo de PE-com (celdas A/B/C combinadas)',
  `pe_com`     VARCHAR(100) NOT NULL COMMENT 'PE-com (columna A)',
  `olt`        VARCHAR(100) NOT NULL COMMENT 'OLT (columna D)',
  `trafico`    INT(11)      NULL     COMMENT 'Tráfico peak del día en Mbps (NULL = sin registro)',
  `capacidad`  INT(11)      NULL     COMMENT 'Capacidad uplink en Mbps',
  `cap_pe`     INT(11)      NULL     COMMENT 'Columna C: capacidad del PE-com (mismo valor en todo el grupo)',
  `alta`       DATE         NULL     COMMENT 'Lunes de la semana en que la OLT se agregó al reporte (NULL = carga inicial)',
  `actualizado` DATETIME    NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_anio_olt_fecha` (`anio`, `olt`, `fecha`),
  KEY `ix_anio_semana` (`anio`, `semana`),
  KEY `ix_anio_fecha` (`anio`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Cambiar la capacidad de un PE-com (columna C) para todo el año:
-- UPDATE OLT_REPORTE_BW_ANUAL SET cap_pe = 40000 WHERE anio = 2026 AND grupo = (
--   SELECT g FROM (SELECT DISTINCT grupo AS g FROM OLT_REPORTE_BW_ANUAL WHERE anio = 2026 AND pe_com = 'PE6-IQUIQUE') t);
