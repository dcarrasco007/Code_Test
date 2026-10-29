-- Elimina del reporte anual (etapa 1) las OLTs agregadas a bw_olts_excluidas() y renumera `orden`
-- para que las filas del Excel y las celdas de las alertas (orden + 2) queden sin huecos.
-- 2026-10-28: OLT-DURZUA-13, OLT-DURZUA-14, OLT-DURZUA-15, OLT-LASCONDES-9

-- 1. Revisar qué se va a eliminar
SELECT anio, olt, MIN(orden) AS orden, MIN(pe_com) AS pe_com, COUNT(*) AS dias, MIN(fecha) AS desde, MAX(fecha) AS hasta
FROM OLT_REPORTE_BW_ANUAL
WHERE olt IN ('OLT-DURZUA-13', 'OLT-DURZUA-14', 'OLT-DURZUA-15', 'OLT-LASCONDES-9')
GROUP BY anio, olt
ORDER BY anio, orden;

-- 2. Eliminar y renumerar
START TRANSACTION;

DELETE FROM OLT_REPORTE_BW_ANUAL
WHERE olt IN ('OLT-DURZUA-13', 'OLT-DURZUA-14', 'OLT-DURZUA-15', 'OLT-LASCONDES-9');

DROP TEMPORARY TABLE IF EXISTS bw_orden;
CREATE TEMPORARY TABLE bw_orden (
  nuevo SMALLINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  viejo SMALLINT NOT NULL
);
INSERT INTO bw_orden (viejo)
SELECT DISTINCT orden FROM OLT_REPORTE_BW_ANUAL WHERE anio = 2026 ORDER BY orden;

UPDATE OLT_REPORTE_BW_ANUAL a
JOIN bw_orden o ON o.viejo = a.orden
SET a.orden = o.nuevo
WHERE a.anio = 2026;

DROP TEMPORARY TABLE bw_orden;

COMMIT;

-- 3. Verificar: 0 filas de las OLTs y orden de 1 a N sin huecos (min = 1, max = olts)
SELECT COUNT(*) AS quedan
FROM OLT_REPORTE_BW_ANUAL
WHERE olt IN ('OLT-DURZUA-13', 'OLT-DURZUA-14', 'OLT-DURZUA-15', 'OLT-LASCONDES-9');

SELECT COUNT(DISTINCT olt) AS olts, MIN(orden) AS min_orden, MAX(orden) AS max_orden
FROM OLT_REPORTE_BW_ANUAL WHERE anio = 2026;
