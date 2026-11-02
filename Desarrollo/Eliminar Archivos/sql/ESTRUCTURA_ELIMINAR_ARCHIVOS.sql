-- ============================================================================
-- Módulo Eliminar Archivos (portal OLT) - BD Aden
-- Ejecutar una sola vez. Las dos tablas sirven para el 120 y el 181 (misma BD).
-- ============================================================================

-- Rutas desde donde se pueden listar y eliminar archivos csv, xls y xlsx.
-- RUTA es relativa a la raíz del portal (120: /var/www/html, 181: carpeta OLT),
-- sin "/" al inicio ni al final. Se incluyen todas sus subcarpetas.
CREATE TABLE IF NOT EXISTS Aden.OLT_ELIMINAR_ARCHIVOS_RUTAS (
    ID              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    RUTA            VARCHAR(255) NOT NULL,
    DESCRIPCION     VARCHAR(150) NOT NULL DEFAULT '',
    NOTA            VARCHAR(255) NOT NULL DEFAULT '',   -- aviso que se muestra al confirmar el borrado
    ACTIVA          TINYINT(1)   NOT NULL DEFAULT 1,    -- 1 = se lista, 0 = guardada pero oculta
    USUARIO         VARCHAR(50)  NOT NULL DEFAULT '',
    FECHA_REGISTRO  DATETIME     NOT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY UK_RUTA (RUTA)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Registro de todo lo que se hace en el módulo (borrados y cambios de rutas).
CREATE TABLE IF NOT EXISTS Aden.OLT_ELIMINAR_ARCHIVOS_LOG (
    ID              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ACCION          VARCHAR(20)  NOT NULL,              -- ELIMINAR, AGREGAR_RUTA, ACTIVAR_RUTA, DESACTIVAR_RUTA, QUITAR_RUTA
    ID_RUTA         INT UNSIGNED NULL,
    RUTA            VARCHAR(255) NOT NULL DEFAULT '',
    ARCHIVO         VARCHAR(500) NOT NULL DEFAULT '',   -- ruta del archivo relativa a RUTA
    TAMANO          BIGINT       NULL,                  -- bytes
    USUARIO         VARCHAR(50)  NOT NULL DEFAULT '',
    IP              VARCHAR(45)  NOT NULL DEFAULT '',
    SERVIDOR        VARCHAR(60)  NOT NULL DEFAULT '',   -- hostname, para distinguir 120 y 181
    FECHA           DATETIME     NOT NULL,
    PRIMARY KEY (ID),
    KEY IX_FECHA (FECHA)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------------------------------------------------------
-- Rutas iniciales: Reportes enfocados a O&M (ONNET y EQUIFIBER) e Informes de
-- Capacidad BW. Se pueden desactivar o quitar desde la misma ventana.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO Aden.OLT_ELIMINAR_ARCHIVOS_RUTAS (RUTA, DESCRIPCION, NOTA, ACTIVA, USUARIO, FECHA_REGISTRO) VALUES
('ONNET/Reporte_Incidencias_Fallas/Archivos',            'Incidencias y clasificación de fallas', '', 1, 'admin', NOW()),
('ONNET/Reporte_Falla_Critica/Archivos',                 'Planes de acción incidencias alto impacto', '', 1, 'admin', NOW()),
('ONNET/Reporte_Inventario_Fisico_logico/Archivos',      'Inventario físico y lógico', '', 1, 'admin', NOW()),
('ONNET/Reporte_Preventivo_Planta_Externa/Archivos',     'Preventivo planta externa', '', 1, 'admin', NOW()),
('ONNET/Reporte_planta_externa/Archivos',                'Trabajos programados planta externa', '', 1, 'admin', NOW()),
('ONNET/Reporte_planta_interna/Archivos',                'Trabajos programados planta interna', '', 1, 'admin', NOW()),
('ONNET/Reporte_Hallazgos_Externa/Archivos',             'Hallazgos planta externa', '', 1, 'admin', NOW()),
('ONNET/Reporte_Hallazgos_Interna/Archivos',             'Hallazgos planta interna', '', 1, 'admin', NOW()),
('ONNET/Reporte_Porcentaje_puertas_Pon/Archivos',        '% ocupación puertos PON', '', 1, 'admin', NOW()),
('ONNET/Reporte_CTOs/Archivos',                          'Reporte CTOs', '', 1, 'admin', NOW()),
('ONNET/Reporte_Splitter/Archivos',                      'Reporte splitter', '', 1, 'admin', NOW()),
('ONNET/Reporte_Incidencias/Archivos',                   'Alta de servicio', '', 1, 'admin', NOW()),
('ONNET/Reporte_Incidencias_Baja/Archivos',              'Baja de servicio', '', 1, 'admin', NOW()),
('ONNET/Reporte_Anulacion_Tecnica_Empresa/Archivos',     'Anulación técnica empresa', '', 1, 'admin', NOW()),
('ONNET/Reporte_Anulacion_Tecnica_Hogar/Archivos',       'Anulación técnica hogar', '', 1, 'admin', NOW()),
('ONNET/Reporte_Factibilidad_Negativa_Empresa/Archivos', 'Factibilidad negativa empresa', '', 1, 'admin', NOW()),
('ONNET/Reporte_Factibilidad_Negativa_Hogar/Archivos',   'Factibilidad negativa hogar', '', 1, 'admin', NOW()),
('ONNET/Reporte_Tasa_derivacion/Archivos',               'Tasa de derivación', '', 1, 'admin', NOW()),
('ONNET/Reporte_Provision_Fibra_Oscura/Archivos',        'Provisión fibra oscura', '', 1, 'admin', NOW()),
('ONNET/Ordenamiento_Cruza_Gabinete/Archivos',           'Ordenamiento y cruza gabinete', '', 1, 'admin', NOW()),
('ONNET/Tasa_Falla_Nacional/Archivos',                   'Tasa de fallas nacional', '', 1, 'admin', NOW()),
('ONNET/MTTR/Archivos',                                  'MTTR', '', 1, 'admin', NOW()),
('ONNET/reporte_resolucion_nacional/Archivos',           'Tiempos de resolución nacional', '', 1, 'admin', NOW()),
('ONNET/Disponibilidad_Zona_Operativa/Archivos',         'Disponibilidad zonas operativas', '', 1, 'admin', NOW()),
('Capacidad_BW_Excel/Archivos',                          'Informes capacidad BW',
 'Si el período sigue en BD, el Excel se vuelve a crear cuando alguien lo descargue. El cron del 120 crea la semana y el mes recién cerrados.', 1, 'admin', NOW());

-- ----------------------------------------------------------------------------
-- Opción de menú (completar ID, POSICION y el ID del menú padre antes de ejecutar). MySQL 5.6.
-- Columnas de OLT_MENU_3: (id, nombre, posicion, principal, url, id del menú 2). Después dar
-- acceso al perfil desde Administración > Perfiles.
-- ----------------------------------------------------------------------------
-- INSERT INTO Aden.OLT_MENU_3 VALUES (<ID>, 'Eliminar archivos csv / Excel', <POSICION>, 1, '../Eliminar_Archivos/index_eliminar_archivos.php', <ID_MENU_2>);
