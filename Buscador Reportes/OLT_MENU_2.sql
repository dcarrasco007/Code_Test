/*
 Navicat Premium Data Transfer

 Source Server         : 57
 Source Server Type    : MySQL
 Source Server Version : 50638
 Source Host           : 172.29.159.57:3306
 Source Schema         : Aden

 Target Server Type    : MySQL
 Target Server Version : 50638
 File Encoding         : 65001

 Date: 03/06/2026 15:56:52
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for OLT_MENU_2
-- ----------------------------
DROP TABLE IF EXISTS `OLT_MENU_2`;
CREATE TABLE `OLT_MENU_2`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `pocision` int(11) NULL DEFAULT NULL,
  `principal` int(11) NULL DEFAULT NULL,
  `url` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `id_olt_menu_1` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 148 CHARACTER SET = latin1 COLLATE = latin1_swedish_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of OLT_MENU_2
-- ----------------------------
INSERT INTO `OLT_MENU_2` VALUES (1, 'Corte de Fibra', 1, 2, '#', 1);
INSERT INTO `OLT_MENU_2` VALUES (2, 'Corte de Energia', 2, 2, '#', 1);
INSERT INTO `OLT_MENU_2` VALUES (3, 'Reporte Uplinks', 3, 2, '#', 1);
INSERT INTO `OLT_MENU_2` VALUES (4, 'Panel Corte Energia', 4, 1, '../Corte_Energia/vista_regional_corte.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (5, 'Panel Corte Fibra GPON', 5, 1, '../regiones/vista_regional.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (6, 'Panel Corte Uplink OLT ONNET', 6, 1, '../Uplinks/vista_regional_corte_uplinks_ONNET.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (7, 'Panel Slide', 7, 1, '../slide.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (8, 'Inicio Alarmas', 8, 1, '../menu/inicio_paneles.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (9, 'Reporte General', 1, 2, '#', 2);
INSERT INTO `OLT_MENU_2` VALUES (10, 'Lista Equipos', 1, 2, '#', 3);
INSERT INTO `OLT_MENU_2` VALUES (11, 'Nuevo Equipo', 2, 1, '../mantenedores/mant_server.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (12, 'Uplinks', 3, 2, '#', 3);
INSERT INTO `OLT_MENU_2` VALUES (13, 'Subir Informacion ONT', 4, 1, '../ONT/index_subir_archivo.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (14, 'Informacion ONT', 5, 1, '../ONT/index_info_ont.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (15, 'Informacion ONT Detalle', 6, 1, '../ONT/Informacion_ONT/index_info_ont_v2.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (16, 'IP Consola', 7, 1, '../ip_safe/mantenedor.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (17, 'Reporte Hitorico', 1, 2, '#', 4);
INSERT INTO `OLT_MENU_2` VALUES (18, 'Graficos', 2, 2, '#', 4);
INSERT INTO `OLT_MENU_2` VALUES (19, 'Ocupacion', 3, 2, '#', 4);
INSERT INTO `OLT_MENU_2` VALUES (20, 'Capacidad', 4, 2, '#', 4);
INSERT INTO `OLT_MENU_2` VALUES (21, 'Uplink', 5, 2, '#', 4);
INSERT INTO `OLT_MENU_2` VALUES (22, 'Potencias Opticas de Uplink', 6, 2, '#', 4);
INSERT INTO `OLT_MENU_2` VALUES (23, 'Grafico de OLT por PE', 7, 1, '../Grafico_Tiempo/index_grafico_tiempo.php', 4);
INSERT INTO `OLT_MENU_2` VALUES (24, 'Ingresar ODF', 1, 1, '../ODF/ingreso_odf.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (25, 'Ver ODF', 2, 1, '../ODF/ver_odf.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (26, 'Subir Info Gabinete', 3, 1, '../ODF/gabinete.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (27, 'ODF por Comuna', 4, 1, '../ODF/odf_comuna.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (28, 'Temperatura/CPU', 1, 2, '#', 6);
INSERT INTO `OLT_MENU_2` VALUES (29, 'Reporte ONT', 2, 2, '#', 6);
INSERT INTO `OLT_MENU_2` VALUES (30, 'Reporte Alarmas', 3, 2, '#', 6);
INSERT INTO `OLT_MENU_2` VALUES (31, 'Reporte Firmware/Patch', 4, 1, '../Versiones/muestra_data.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (32, 'Reporte Tarjetas', 5, 2, '#', 6);
INSERT INTO `OLT_MENU_2` VALUES (33, 'Reporte Poder', 6, 1, '../Voltajes/muestra_voltaje.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (34, 'Reporte Vlan', 7, 2, '#', 6);
INSERT INTO `OLT_MENU_2` VALUES (35, 'Reporte FAN Estado', 8, 1, '../FAN/muestra_fan.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (36, 'Reporte Uptime OLT', 9, 1, '../Uptime/muestra_data.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (37, 'Reporte Uptime PON', 10, 2, '#', 6);
INSERT INTO `OLT_MENU_2` VALUES (38, 'Equipamiento GPON', 11, 1, '../Tarjetas/mantenedor.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (39, 'Equipo', 1, 1, '../Mantencion_OLT/ver_equipo_2.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (40, 'Subir Gantt', 2, 1, '../Mantencion_OLT/index_subir_gantt.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (41, 'Ver Gantt', 3, 1, '../Mantencion_OLT/ver_gantt.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (42, 'Detalle Gantt', 4, 1, '../Mantencion_OLT/ver_detalle_gantt_2.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (43, 'Gestion Usuario', 1, 1, '../usuarios/index.php', 8);
INSERT INTO `OLT_MENU_2` VALUES (44, 'Gestion Perfiles', 2, 1, '../perfiles/index_nuevo.php', 8);
INSERT INTO `OLT_MENU_2` VALUES (45, 'Gestion Cuenta', 3, 1, '../menu/cambioPass.php', 8);
INSERT INTO `OLT_MENU_2` VALUES (46, 'Salir Panel', 5, 1, '../menu/logout.php', 8);
INSERT INTO `OLT_MENU_2` VALUES (47, 'Reporte de Incidencias y Clasificacion de fallas', 1, 2, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (48, 'Reporte de Capacidad disponible de cada OLT  ONNET', 3, 1, '../Ocupacion/ocupacion_ont_disponibles_ONNET.php', 9);
INSERT INTO `OLT_MENU_2` VALUES (49, 'Reporte con el estado de la Interfaz del Servicio  ONNET', 4, 1, '../Ocupacion/ocupacion_uplinks_ONNET.php', 9);
INSERT INTO `OLT_MENU_2` VALUES (50, 'Reporte con % de ocupacion de los puertos PON', 5, 2, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (51, 'Informe de Trabajos Programados', 6, 2, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (52, 'Contadores y estadistica necesaria para la Operacion y Mantencion de los Servicios', 7, 2, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (53, 'Reporte Planes de accion e informes de Incidencias de alto impacto', 8, 1, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (54, 'Reporte Inventario fisico y logico', 9, 2, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (55, 'Reporte de avance plan de mantenimiento preventivo Planta Externa y Planta Interna', 10, 2, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (56, 'Reporte de hallazgos', 11, 2, '#', 9);
INSERT INTO `OLT_MENU_2` VALUES (59, 'Falsos Positivos y Factibilidades Negativas', 1, 2, '#', 10);
INSERT INTO `OLT_MENU_2` VALUES (61, 'ONT ID e informacion de ONT conectadas a la Red de Fibra Optica', 4, 1, '../ONNET/ONT_ID_Informacion_ONT/index_info_onnet_3.php', 10);
INSERT INTO `OLT_MENU_2` VALUES (62, 'Reporte de ONT', 5, 1, '../ONNET/Reporte_ONNET_ONT/index_info_onnet.php', 10);
INSERT INTO `OLT_MENU_2` VALUES (63, 'Procesos de ordenamiento y cruzadas en gabinete', 6, 2, '#', 10);
INSERT INTO `OLT_MENU_2` VALUES (64, 'Tasa de derivacion a Planta Externa ONNET', 1, 1, '../ONNET/Reporte_Tasa_derivacion/index_ver_reporte.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (65, 'Alta de Servicios ONNET', 3, 1, '../ONNET/Reporte_Incidencias/index_ver_reporte.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (66, 'Baja de Servicios ONNET', 5, 1, '../ONNET/Reporte_Incidencias_Baja/index_ver_reporte.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (67, 'Provision Fibra Oscura ONNET', 7, 1, '../ONNET/Reporte_Provision_Fibra_Oscura/index_ver_reporte.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (68, 'Compilado de los documentos en PDF ONNET', 1, 1, '../ONNET/Documentos_PDF/index_ver_reporte.php', 12);
INSERT INTO `OLT_MENU_2` VALUES (69, 'Subir Tasa de derivacion a Planta Externa ONNET', 2, 1, '../ONNET/Reporte_Tasa_derivacion/index_subir_archivo.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (70, 'Subir Alta de Servicios ONNET', 4, 1, '../ONNET/Reporte_Incidencias/index_subir_archivo.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (71, 'Subir Baja de Servicios ONNET', 6, 1, '../ONNET/Reporte_Incidencias_Baja/index_subir_archivo.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (72, 'Subir Provision Fibra Oscura ONNET', 8, 1, '../ONNET/Reporte_Provision_Fibra_Oscura/index_subir_archivo.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (73, 'Subir Compilado de los documentos en PDF ONNET', 2, 1, '../ONNET/Documentos_PDF/index_subir_archivo.php', 12);
INSERT INTO `OLT_MENU_2` VALUES (75, 'Reporte de CTOs', 2, 2, '#', 10);
INSERT INTO `OLT_MENU_2` VALUES (76, 'Reporte de Splitter', 3, 2, '#', 10);
INSERT INTO `OLT_MENU_2` VALUES (77, 'Reporte Alarmas PON ONNET', 12, 1, '../ONNET/Disponibilidad_Alarma_OLT/index_disponibilidad.php', 9);
INSERT INTO `OLT_MENU_2` VALUES (78, 'Resumen Mantencion OLT', 5, 1, '../Mantencion_OLT/ver_resumen_mantenimiento.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (79, 'Buscar ODF', 5, 1, '../ODF/index_detalle_ODF.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (80, 'Dashboard Mantenimiento', 6, 1, '../Mantencion_OLT/index_dash_fecha.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (81, 'Equipo', 1, 1, '../Mantencion_OLT_ONNET/ver_equipo_2.php', 13);
INSERT INTO `OLT_MENU_2` VALUES (82, 'Subir Gantt', 2, 1, '../Mantencion_OLT_ONNET/index_subir_gantt.php', 13);
INSERT INTO `OLT_MENU_2` VALUES (83, 'Ver Gantt', 3, 1, '../Mantencion_OLT_ONNET/ver_gantt.php', 13);
INSERT INTO `OLT_MENU_2` VALUES (84, 'Detalle Gantt', 4, 1, '../Mantencion_OLT_ONNET/ver_detalle_gantt_2.php', 13);
INSERT INTO `OLT_MENU_2` VALUES (85, 'Resumen Mantencion OLT', 5, 1, '../Mantencion_OLT_ONNET/ver_resumen_mantenimiento.php', 13);
INSERT INTO `OLT_MENU_2` VALUES (86, 'Dashboard Mantenimiento', 6, 1, '../Mantencion_OLT_ONNET/index_dash_fecha.php', 13);
INSERT INTO `OLT_MENU_2` VALUES (87, 'Equipo', 1, 1, '../Mantencion_OLT_V2/ver_equipo_2.php', 14);
INSERT INTO `OLT_MENU_2` VALUES (88, 'Subir Gantt', 2, 1, '../Mantencion_OLT_V2/index_subir_gantt.php', 14);
INSERT INTO `OLT_MENU_2` VALUES (89, 'Ver Gantt', 3, 1, '../Mantencion_OLT_V2/ver_gantt.php', 14);
INSERT INTO `OLT_MENU_2` VALUES (90, 'Detalle Gantt', 4, 1, '../Mantencion_OLT_V2/ver_detalle_gantt_2.php', 14);
INSERT INTO `OLT_MENU_2` VALUES (91, 'Resumen Mantencion OLT', 5, 1, '../Mantencion_OLT_V2/ver_resumen_mantenimiento.php', 14);
INSERT INTO `OLT_MENU_2` VALUES (92, 'Dashboard Mantenimiento', 6, 1, '../Mantencion_OLT_V2/index_dash_fecha.php', 14);
INSERT INTO `OLT_MENU_2` VALUES (93, 'IP Consola ONNET', 8, 1, '../ip_safe/mantenedor_ONNET.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (94, 'Ver ODF ONNET', 6, 1, '../ODF/ver_odf_ONNET.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (95, 'ODF por Comuna ONNET', 7, 1, '../ODF/odf_comuna_ONNET.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (96, 'Buscar ODF ONNET', 8, 1, '../ODF/index_detalle_ODF_ONNET.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (97, 'Reporte Firmware/Patch ONNET', 13, 1, '../Versiones/muestra_data_ONNET.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (98, 'Reporte de Capacidad disponible de cada OLT EQUIFIBER', 12, 1, '../Ocupacion/ocupacion_ont_disponibles_EQUIFIBER.php', 9);
INSERT INTO `OLT_MENU_2` VALUES (99, 'Reporte con el estado de la Interfaz del Servicio EQUIFIBER', 13, 1, '../Ocupacion/ocupacion_uplinks_EQUIFIBER.php', 9);
INSERT INTO `OLT_MENU_2` VALUES (100, 'Reporte Alarmas PON EQUIFIBER', 13, 1, '../ONNET/Disponibilidad_Alarma_OLT/index_disponibilidad_EQUIFIBER.php', 9);
INSERT INTO `OLT_MENU_2` VALUES (101, 'ONT ID e informacion de ONT conectadas a la Red de Fibra Optica EQUIFIBER', 7, 1, '../ONNET/ONT_ID_Informacion_ONT/index_info_onnet_3_EQUIFIBER.php', 10);
INSERT INTO `OLT_MENU_2` VALUES (102, 'Reporte de ONT EQUIFIBER', 8, 1, '../ONNET/Reporte_ONNET_ONT/index_info_EQUIFIBER.php', 10);
INSERT INTO `OLT_MENU_2` VALUES (103, 'Reporte Uptime OLT ONNET', 14, 1, '../Uptime/muestra_data_ONNET.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (104, 'Reporte Uptime OLT EQUIFIBER', 15, 1, '../Uptime/muestra_data_EQUIFIBER.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (105, 'IP Consola EQUIFIBER', 9, 1, '../ip_safe/mantenedor_EQUIFIBER.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (106, 'Ver ODF EQUIFIBER', 9, 1, '../ODF/ver_odf_EQUIFIBER.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (107, 'ODF por Comuna EQUIFIBER', 9, 1, '../ODF/odf_comuna_EQUIFIBER.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (108, 'Buscar ODF EQUIFIBER', 10, 1, '../ODF/index_detalle_ODF_EQUIFIBER.php', 5);
INSERT INTO `OLT_MENU_2` VALUES (109, 'Tasa de derivacion a Planta Externa EQUIFIBER', 9, 1, '../ONNET/Reporte_Tasa_derivacion/index_ver_reporte_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (110, NULL, 11, NULL, NULL, NULL);
INSERT INTO `OLT_MENU_2` VALUES (111, NULL, 13, NULL, NULL, NULL);
INSERT INTO `OLT_MENU_2` VALUES (112, NULL, 15, NULL, NULL, NULL);
INSERT INTO `OLT_MENU_2` VALUES (113, 'Subir Tasa de derivacion a Planta Externa EQUIFIBER', 10, 1, '../ONNET/Reporte_Tasa_derivacion/index_subir_archivo_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (114, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `OLT_MENU_2` VALUES (115, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `OLT_MENU_2` VALUES (116, NULL, NULL, NULL, NULL, NULL);
INSERT INTO `OLT_MENU_2` VALUES (117, 'Subir Baja de Servicios EQUIFIBER', 14, 1, '../ONNET/Reporte_Incidencias_Baja/index_subir_archivo_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (118, 'Baja de Servicios EQUIFIBER', 13, 1, '../ONNET/Reporte_Incidencias_Baja/index_ver_reporte_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (119, 'Provision Fibra Oscura EQUIFIBER', 15, 1, '../ONNET/Reporte_Provision_Fibra_Oscura/index_ver_reporte_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (120, 'Subir Provision Fibra Oscura EQUIFIBER', 16, 1, '../ONNET/Reporte_Provision_Fibra_Oscura/index_subir_archivo_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (121, 'Compilado de los documentos en PDF EQUIFIBER', 3, 1, '../ONNET/Documentos_PDF/index_ver_reporte_EQUIFIBER.php', 12);
INSERT INTO `OLT_MENU_2` VALUES (122, 'Subir Compilado de los documentos en PDF EQUIFIBER', 4, 1, '../ONNET/Documentos_PDF/index_subir_archivo_EQUIFIBER.php', 12);
INSERT INTO `OLT_MENU_2` VALUES (124, 'Alta de Servicios EQUIFIBER', 11, 1, '../ONNET/Reporte_Incidencias/index_ver_reporte_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (125, 'Subir Alta de Servicios EQUIFIBER', 12, 1, '../ONNET/Reporte_Incidencias/index_subir_archivo_EQUIFIBER.php', 11);
INSERT INTO `OLT_MENU_2` VALUES (126, 'Reporte Firmware/Patch EQUIFIBER', 16, 1, '../Versiones/muestra_data_EQUIFIBER.php', 6);
INSERT INTO `OLT_MENU_2` VALUES (127, 'Reporte Ejecutivo', 9, 1, '../Reporte_Ejecutivo/reporte.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (128, 'Reporte Ejecutivo ONNET', 10, 1, '../Reporte_Ejecutivo/reporte_ONNET.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (129, 'Reporte Ejecutivo EQUIFIBER', 11, 1, '../Reporte_Ejecutivo/reporte_EQUIFIBER.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (130, 'Respaldo Reporte ONT ONNET', 9, 1, '../ONT/Respaldo_ONT/ONNET/index.php', 10);
INSERT INTO `OLT_MENU_2` VALUES (131, 'Respaldo Reporte ONT EQUIFIBER', 10, 1, '../ONT/Respaldo_ONT/EQUIFIBER/index.php', 10);
INSERT INTO `OLT_MENU_2` VALUES (132, 'Reporte Emergencia', 12, 1, '../Reporte_Emergencia/reporte_emergencia.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (133, 'Panel Corte Uplink OLT EQUIFIBER', 6, 1, '../Uplinks/vista_regional_corte_uplinks_EQUIFIBER.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (134, 'Reporte de Conexiones', 4, 1, '../Conexiones_Usuarios/index_usuarios.php', 8);
INSERT INTO `OLT_MENU_2` VALUES (135, 'Subir Service Port ONT', 10, 1, '../ONT/index_subir_archivo_2.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (136, 'Informacion ONT Detalle Service Port', 11, 1, '../ONT/Informacion_ONT_service_port/index_info_ont_service_port.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (137, 'Respaldo ONT Detalle Service Port', 12, 1, '../ONT/Informacion_ONT_service_port/index_respaldo.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (138, 'Visita Tecnica', 7, 1, '../Mantencion_OLT/Visita_Tecnica/ver_equipo_2.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (139, 'Subir GANTT Visita Tecnica', 8, 1, '../Mantencion_OLT/Visita_Tecnica/index_subir_gantt.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (140, 'Ver GANTT Visita Tecnica ', 9, 1, '../Mantencion_OLT/Visita_Tecnica/ver_gantt.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (141, 'Detalle Gantt Visita Tecnica', 10, 1, '../Mantencion_OLT/Visita_Tecnica/ver_detalle_gantt_2.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (142, 'Respaldo Reporte ONT ENTEL', 10, 1, '../ONT/Respaldo_ONT/ENTEL/index.php', 10);
INSERT INTO `OLT_MENU_2` VALUES (143, 'Template reporte NCE ONT', 13, 1, '../ONT/Informacion_ONT/index_info_ont_v3.php', 3);
INSERT INTO `OLT_MENU_2` VALUES (144, 'Dash OLT (Desarrollo)', 13, 1, '../Dash_OLT/index.php', 1);
INSERT INTO `OLT_MENU_2` VALUES (145, 'Grafico Alarmas (En Desarrollo)', 11, 1, '../Grafico_Alarmas/index_grafico.php', 7);
INSERT INTO `OLT_MENU_2` VALUES (146, 'Grafico Alarmas (EN DESARROLLO)', 7, 1, '../Grafico_Alarmas/index_grafico_ONNET.php', 13);
INSERT INTO `OLT_MENU_2` VALUES (147, 'Grafico Alarmas (En Desarrollo)', 7, 1, '../Grafico_Alarmas/index_grafico_EQUIFIBER.php', 14);

SET FOREIGN_KEY_CHECKS = 1;
