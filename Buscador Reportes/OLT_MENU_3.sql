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

 Date: 03/06/2026 15:57:04
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for OLT_MENU_3
-- ----------------------------
DROP TABLE IF EXISTS `OLT_MENU_3`;
CREATE TABLE `OLT_MENU_3`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `posicion` int(11) NULL DEFAULT NULL,
  `principal` int(11) NULL DEFAULT NULL,
  `url` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `id_olt_menu_2` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 253 CHARACTER SET = latin1 COLLATE = latin1_swedish_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of OLT_MENU_3
-- ----------------------------
INSERT INTO `OLT_MENU_3` VALUES (1, 'Reporte General', 1, 1, '../info_corte_general.php', 1);
INSERT INTO `OLT_MENU_3` VALUES (2, 'Reporte Corte Fibra', 2, 1, '../info.php', 1);
INSERT INTO `OLT_MENU_3` VALUES (3, 'Reporte General', 1, 1, '../Corte_Energia/info.php', 2);
INSERT INTO `OLT_MENU_3` VALUES (4, 'Reporte Corte Energia', 2, 1, '../Corte_Energia/reporte_corte_energia_V2.php', 2);
INSERT INTO `OLT_MENU_3` VALUES (5, 'Estado General Uplinks', 1, 1, '../Uplinks/reporte_uplinks.php', 3);
INSERT INTO `OLT_MENU_3` VALUES (6, 'Estado de Corte Uplinks', 2, 1, '../Uplinks/reporte_uplinks_corte.php', 3);
INSERT INTO `OLT_MENU_3` VALUES (7, 'Subir Reporte General', 1, 1, '../ONNET/index_subir_archivo.php', 9);
INSERT INTO `OLT_MENU_3` VALUES (8, 'Reporte General', 2, 1, '../ONNET/index_info_onnet.php', 9);
INSERT INTO `OLT_MENU_3` VALUES (9, 'Reporte Potencia', 3, 1, '../ONNET/index_info_onnet_potencias.php', 9);
INSERT INTO `OLT_MENU_3` VALUES (10, 'Reporte Voltaje', 4, 1, '../ONNET/index_info_onnet_voltage.php', 9);
INSERT INTO `OLT_MENU_3` VALUES (11, 'Reporte Rx Onu', 5, 1, '../ONNET/index_info_onnet_rx_onu.php', 9);
INSERT INTO `OLT_MENU_3` VALUES (12, 'Reporte Ranging', 6, 1, '../ONNET/index_info_onnet_range.php', 9);
INSERT INTO `OLT_MENU_3` VALUES (13, 'Reporte Last Up Last Down', 7, 1, '../ONNET/index_info_onnet_up_down.php', 9);
INSERT INTO `OLT_MENU_3` VALUES (14, 'Agregar Uplink', 1, 1, '../Uplinks/mant_uplinks.php', 12);
INSERT INTO `OLT_MENU_3` VALUES (15, 'Trafico GPON', 1, 1, '../Trafico/trafico_gpon.php', 17);
INSERT INTO `OLT_MENU_3` VALUES (16, 'Trafico Uplinks', 2, 1, '../Trafico/trafico_uplinks.php', 17);
INSERT INTO `OLT_MENU_3` VALUES (17, 'Trafico GPON', 1, 1, '../Graficos/grafico_trafico_gpon.php', 18);
INSERT INTO `OLT_MENU_3` VALUES (18, 'Trafico Uplink', 2, 1, '../Graficos/grafico_trafico_uplinks.php', 18);
INSERT INTO `OLT_MENU_3` VALUES (19, 'Grafico Porcentaje de BW Uplinks', 3, 1, '../Graficos/grafico_bw.php', 18);
INSERT INTO `OLT_MENU_3` VALUES (20, 'ONT por OLT', 1, 1, '../Ocupacion/ocupacion_ont.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (21, 'Puertas Uplinks', 2, 1, '../Ocupacion/ocupacion_uplinks.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (22, 'Reporte Ocupacion GPon Mensual', 3, 1, '../Ocupacion/index_ocu_gpon.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (23, 'Reporte Ocupacion GPon Semanal', 4, 1, '../Ocupacion/index_ocu_gpon_semanal.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (24, 'Reporte Ocupacion Gpon Diario Semanal', 5, 1, '../Ocupacion/index_ocu_gpon_semanal_day.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (25, 'Tabla ONT Disponibles/Utilizadas Semanal', 6, 1, '../ONT/index_ocu_gpon_disp_ocu.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (26, 'Tabla ONT Disponibles', 7, 1, '../Ocupacion/ocupacion_ont_disponibles.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (27, 'Tabla ONT Utilizadas', 8, 1, '../Ocupacion/ocupacion_ont_utilizadas.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (28, 'Reporte BW Semanal', 1, 1, '../Reporte_BW/index_reporte.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (29, 'Reporte BW Diario', 2, 1, '../Reporte_BW/index_fecha.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (30, 'Reporte BW OLT/Week', 3, 1, '../Reporte_BW/index_reporte_semanal.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (31, 'Reporte BW Peak Week', 4, 1, '../Reporte_BW/index_peak_week.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (32, 'Reporte OLT/Puerta', 5, 1, '../Reporte_BW/index_peak_puerta.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (33, 'Reporte Trafico PON', 6, 1, '../Reporte_BW/index_fecha_pon.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (34, 'Informes Capacidades BW', 7, 2, '#', 20);
INSERT INTO `OLT_MENU_3` VALUES (35, 'UPLINK OLT MA5800-X15 PEAK Diario', 1, 1, '../Uplinks/muestra_uplink_MA5800-X15_day_peak.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (36, 'UPLINK OLT MA5800-X15 PROMEDIO Diario', 2, 1, '../Uplinks/muestra_uplink_MA5800-X15_day.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (37, 'UPLINK OLT MA5800-X15 Semanal', 3, 1, '../Uplinks/muestra_uplink_MA5800-X15_week.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (38, 'UPLINK OLT MA5600T PEAK Diario', 4, 1, '../Uplinks/muestra_uplink_MA5600T_day_peak.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (39, 'UPLINK OLT MA5600T PROMEDIO Diario', 5, 1, '../Uplinks/muestra_uplink_MA5600T_day_promedio.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (40, 'UPLINK OLT MA5600T Semanal', 6, 1, '../Uplinks/muestra_uplink_MA5600T_week.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (41, 'Reporte BW Uplink', 7, 1, '../Reporte_BW/index_fecha_uplink.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (42, 'Detalle Uplink diario', 8, 1, '../ONT/index_detalle_uplink.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (43, 'Potencia Diaria', 1, 1, '../Potencia_Optica_Uplink/muestra_potencia_optica.php', 22);
INSERT INTO `OLT_MENU_3` VALUES (44, 'Potencia Semanal', 2, 1, '../Potencia_Optica_Uplink/muestra_potencia_semana.php', 22);
INSERT INTO `OLT_MENU_3` VALUES (45, 'Reporte General', 1, 1, '../Temp_Cpu/reporte_general.php', 28);
INSERT INTO `OLT_MENU_3` VALUES (46, 'Reporte Temp/CPU', 2, 1, '../Temp_Cpu/info.php', 28);
INSERT INTO `OLT_MENU_3` VALUES (47, 'Reporte General', 1, 1, '../ONT/ont_general.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (48, 'Reporte ONT General', 2, 1, '../ONT/info_ont_equipos.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (49, 'Reporte ONT Detalle', 3, 1, '../ONT/info_ont_total.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (50, 'Reporte Historico Week', 4, 1, '../ONT/index_historico_week.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (51, 'Reporte Historico Week Grafico', 5, 1, '../ONT/index_historico_week_grafico.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (52, 'Reporte ONT Diario', 6, 1, '../ONT/Reporte_ONT_Diario/index_reporte_diario.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (53, 'Reporte ONT Diario por Equipo', 7, 1, '../ONT/Reporte_ONT_Diario/index_reporte_diario_equipo.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (54, 'Reporte General', 1, 1, '../Alarmas/alarmas_general.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (55, 'Reporte Detalle', 2, 1, '../Alarmas/alarmas.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (56, 'Uplinks LOS', 3, 1, '../Alarmas/tabla_alarma_critical.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (57, 'Reporte Alarmas Recovery', 4, 1, '../Alarmas/index_recovery.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (58, 'Uplinks LOS Hist&oacute;rico', 5, 1, '../Alarmas/tabla_alarma_critical_historial.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (59, 'Reporte General', 1, 1, '../Tarjetas/tarjeta_general.php', 32);
INSERT INTO `OLT_MENU_3` VALUES (60, 'Reporte Detalle', 2, 1, '../Tarjetas/muestra_tarjeta.php', 32);
INSERT INTO `OLT_MENU_3` VALUES (61, 'Reporte Cantidad Vlan', 1, 1, '../Vlan/muestra_vlan.php', 34);
INSERT INTO `OLT_MENU_3` VALUES (62, 'Reporte Trafico Vlan', 2, 1, '../Vlan/index_vlan_servicios.php', 34);
INSERT INTO `OLT_MENU_3` VALUES (63, 'Export Semanal Vlan/Servicios', 3, 1, '../Vlan/index_report.php', 34);
INSERT INTO `OLT_MENU_3` VALUES (64, 'Grafico Trafico Vlan/Servicios', 4, 1, '../Vlan/index_graficos.php', 34);
INSERT INTO `OLT_MENU_3` VALUES (65, 'Tr&aacute;fico de Vlan por OLT', 5, 1, '../Vlan/index_trafico_vlan_olt.php', 34);
INSERT INTO `OLT_MENU_3` VALUES (66, 'Cantidad de Vlan de Servicios por OLT', 6, 1, '../Vlan/Vlan_Servicio/index_servicios.php', 34);
INSERT INTO `OLT_MENU_3` VALUES (67, 'Reporte General', 1, 1, '../Uptime/muestra_uptime.php', 37);
INSERT INTO `OLT_MENU_3` VALUES (68, 'Reporte Historico', 2, 1, '../Uptime/index_reporte_historico.php', 37);
INSERT INTO `OLT_MENU_3` VALUES (73, 'Informe TP Planta Externa ONNET', 1, 1, '../ONNET/Reporte_planta_externa/tabla_reporte_planta_externa.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (74, 'Informe TP Planta Interna ONNET', 3, 1, '../ONNET/Reporte_planta_interna/tabla_reporte_planta_interna.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (75, 'MTTR', 1, 1, '../ONNET/MTTR/tabla_reporte_mttr.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (76, 'Subir MTTR', 2, 1, '../ONNET/MTTR/index_subir_archivo.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (77, 'Disponibilidad de la Red Nacional (Incidente)', 3, 1, '../ONNET/Disponibilidad_Red_Nacional/index_ver_reporte.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (78, 'Subir Disponibilidad de la Red Nacional (Incidente)', 4, 1, '../ONNET/Disponibilidad_Red_Nacional/index_subir_archivo.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (79, 'Tasas de fallas Nacionales', 5, 1, '../ONNET/Tasa_Falla_Nacional/index_ver_reporte.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (80, 'Subir Tasas de fallas Nacionales', 6, 1, '../ONNET/Tasa_Falla_Nacional/index_subir_archivo.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (81, 'Tiempos de resolucion Nacional', 7, 1, '../ONNET/reporte_resolucion_nacional/tabla_reporte_rnacional.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (82, 'Subir Tiempos de resolucion Nacional', 8, 1, '../ONNET/reporte_resolucion_nacional/index_subir_archivo.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (83, 'Disponibilidad en zonas operativas', 9, 1, '../ONNET/Reporte_Incidencias/tabla_onnet.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (84, 'Subir Disponibilidad en zonas operativas', 10, 1, '../ONNET/Reporte_Incidencias/index_subir_archivo.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (85, 'Reporte Anulacion Tecnica Empresa ONNET', 1, 1, '../ONNET/Reporte_Anulacion_Tecnica_Empresa/index_ver_reporte.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (86, 'Subir Anulacion Tecnica Empresa ONNET', 2, 1, '../ONNET/Reporte_Anulacion_Tecnica_Empresa/index_subir_archivo.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (87, 'Reporte Anulacion Tecnica Hogar ONNET', 3, 1, '../ONNET/Reporte_Anulacion_Tecnica_Hogar/index_ver_reporte.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (88, 'Subir Anulacion Tecnica Hogar ONNET', 4, 1, '../ONNET/Reporte_Anulacion_Tecnica_Hogar/index_subir_archivo.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (89, 'Reporte existe en portal GPON. (Backhaul)', 1, 1, '../ONNET/index_info_onnet.php', 61);
INSERT INTO `OLT_MENU_3` VALUES (90, 'Subir Reporte existe en portal GPON. (Backhaul)', 2, 1, '../ONNET/Reporte_Incidencias/index_subir_archivo.php', 61);
INSERT INTO `OLT_MENU_3` VALUES (91, 'Reporte existe en portal GPON. (Backhaul)', 1, 1, '../ONNET/index_info_onnet.php', 62);
INSERT INTO `OLT_MENU_3` VALUES (92, 'Subir Reporte existe en portal GPON. (Backhaul)', 2, 1, '../ONNET/Reporte_Incidencias/index_subir_archivo.php', 62);
INSERT INTO `OLT_MENU_3` VALUES (93, 'Proceso de Ordenamiento y Cruza ONNET', 1, 1, '../ONNET/Ordenamiento_Cruza_Gabinete/index_ver_reporte.php', 63);
INSERT INTO `OLT_MENU_3` VALUES (94, 'Subir Proceso de Ordenamiento y Cruza ONNET', 2, 1, '../ONNET/Ordenamiento_Cruza_Gabinete/index_subir_archivo.php', 63);
INSERT INTO `OLT_MENU_3` VALUES (99, 'Subir Informe TP Planta Externa ONNET', 2, 1, '../ONNET/Reporte_planta_externa/index_subir_archivo.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (100, 'Subir Informe TP Planta Interna ONNET', 4, 1, '../ONNET/Reporte_planta_interna/index_subir_archivo.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (101, 'Subir Reporte de Incidencias y Clasificacion de fallas ONNET', 1, 1, '../ONNET/Reporte_Incidencias_Fallas/index_subir_archivo_v2.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (102, 'Reporte de Incidencias y Clasificacion de fallas ONNET', 2, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v2.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (103, 'Graficos Contadores y Estadisticas', 11, 1, '../ONNET/ContadoresEstadisticas/indexContadores.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (104, 'Subir Reporte CTOs ONNET', 1, 1, '../ONNET/Reporte_CTOs/index_subir_archivo.php', 75);
INSERT INTO `OLT_MENU_3` VALUES (105, 'Reporte de CTOs ONNET', 2, 1, '../ONNET/Reporte_CTOs/index_ver_reporte.php', 75);
INSERT INTO `OLT_MENU_3` VALUES (106, 'Ver Reporte Inventario fisico y logico ONNET', 1, 1, '../ONNET/Reporte_Inventario_Fisico_logico/index_ver_reporte.php', 54);
INSERT INTO `OLT_MENU_3` VALUES (107, 'Subir Reporte Inventario fisico y logico ONNET', 2, 1, '../ONNET/Reporte_Inventario_Fisico_logico/index_subir_archivo.php', 54);
INSERT INTO `OLT_MENU_3` VALUES (108, 'Subir Reporte Splitter ONNET', 1, 1, '../ONNET/Reporte_Splitter/index_subir_archivo.php', 76);
INSERT INTO `OLT_MENU_3` VALUES (109, 'Reporte de Splitter ONNET', 2, 1, '../ONNET/Reporte_Splitter/index_ver_reporte.php', 76);
INSERT INTO `OLT_MENU_3` VALUES (110, 'Reporte con % de ocupacion de los puertos PON ONNET', 1, 1, '../Ocupacion/ocupacion_ont_ONNET.php', 50);
INSERT INTO `OLT_MENU_3` VALUES (111, 'Reporte con % de ocupacion semanal ONNET', 2, 1, '../ONNET/Reporte_Porcentaje_puertas_Pon/index_reporte_semanal_zl.php', 50);
INSERT INTO `OLT_MENU_3` VALUES (112, 'Reporte Factibilidad Negativa Empresa ONNET', 5, 1, '../ONNET/Reporte_Factibilidad_Negativa_Empresa/index_ver_reporte.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (113, 'Subir Factibilidad Negativa Empresa ONNET', 6, 1, '../ONNET/Reporte_Factibilidad_Negativa_Empresa/index_subir_archivo.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (114, 'Reporte Factibilidad Negativa Hogar ONNET', 7, 1, '../ONNET/Reporte_Factibilidad_Negativa_Hogar/index_ver_reporte.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (115, 'Subir Factibilidad Negativa Hogar ONNET', 8, 1, '../ONNET/Reporte_Factibilidad_Negativa_Hogar/index_subir_archivo.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (116, 'Reporte Planes de accion e informes de Incidencias de alto impacto ONNET', 1, 1, '../ONNET/Reporte_Falla_Critica/index_ver_reporte.php', 53);
INSERT INTO `OLT_MENU_3` VALUES (117, 'Subir Planes de accion e informes de Incidencias de alto impacto ONNET', 2, 1, '../ONNET/Reporte_Falla_Critica/index_subir_archivo.php', 53);
INSERT INTO `OLT_MENU_3` VALUES (118, 'Reporte Preventivo planta externa ONNET', 1, 1, '../ONNET/Reporte_Preventivo_Planta_Externa/index_ver_reporte.php', 55);
INSERT INTO `OLT_MENU_3` VALUES (119, 'Subir Reporte Preventivo planta externa ONNET', 2, 1, '../ONNET/Reporte_Preventivo_Planta_Externa/index_subir_archivo.php', 55);
INSERT INTO `OLT_MENU_3` VALUES (120, 'Subir Reporte de Hallazgos Planta Externa ONNET', 1, 1, '../ONNET/Reporte_Hallazgos_Externa/index_subir_archivo.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (121, 'Reporte de Hallazgos Planta Externa ONNET', 2, 1, '../ONNET/Reporte_Hallazgos_Externa/index_ver_reporte.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (122, 'Subir Reporte de Hallazgos Planta Interna ONNET', 3, 1, '../ONNET/Reporte_Hallazgos_Interna/index_subir_archivo.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (123, 'Reporte de Hallazgos Planta Interna ONNET', 4, 1, '../ONNET/Reporte_Hallazgos_Interna/index_ver_reporte.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (124, 'OLT ONNET', 2, 1, '../server_onnet.php', 10);
INSERT INTO `OLT_MENU_3` VALUES (125, 'OLT ENTEL', 1, 1, '../server.php', 10);
INSERT INTO `OLT_MENU_3` VALUES (126, 'Uplink Percentil 98%', 9, 1, '../Reporte_BW/index_fecha_percentil_98.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (127, 'Reporte Peak BW Uplink', 10, 1, '../Reporte_BW/index_fecha_peak_uplink.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (128, 'Disponibilidad de Red - Planta Externa ONNET', 3, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v3.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (129, 'Disponibilidad de Red - Planta Interna ONNET', 4, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v4.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (130, 'Disponibilidad de Red - Graficos Planta Interna ONNET', 5, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (131, 'Disponibilidad de Red  - Graficos Planta Externa ONNET', 6, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_2.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (132, 'Tiempos de Resolucion - Alto Impacto  Planta Externa ONNET', 7, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v5.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (133, 'Tiempos de Resolucion - Estandar  Planta Externa ONNET', 8, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v6.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (134, 'Tiempos de Resolucion - Planta Interna ONNET', 9, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v7.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (135, 'Tiempos de Resolucion - Graficos Planta Externa Alto Impacto ONNET', 10, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_3.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (136, 'Tiempos de Resolucion - Graficos Planta Externa Estandar ONNET', 11, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_4.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (137, 'Disponibilidad de Red - Graficos Planta Interna Anual ONNET', 12, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_anual_interna.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (138, 'Disponibilidad de Red - Graficos Planta Externa Anual ONNET', 13, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_anual_externa.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (139, 'Reporte Capacidad Peak BW Uplink', 11, 1, '../Reporte_BW/index_fecha_peak_uplink_capacidad.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (140, 'Reporte Capacidad Peak BW Uplink (ONNET)', 12, 1, '../Reporte_BW/index_fecha_peak_uplink_capacidad_onnet.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (141, 'OLT EQUIFIBER', 3, 1, '../server_v2.php', 10);
INSERT INTO `OLT_MENU_3` VALUES (142, 'ONT por OLT ONNET', 9, 1, '../Ocupacion/ocupacion_ont_ONNET.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (143, 'Puertas Uplinks ONNET', 10, 1, '../Ocupacion/ocupacion_uplinks_ONNET.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (144, 'Tabla ONT Disponibles/Utilizadas Semanal ONNET', 11, 1, '../ONT/index_ocu_gpon_disp_ocu_ONNET.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (145, 'Tabla ONT Disponibles ONNET', 12, 1, '../Ocupacion/ocupacion_ont_disponibles_ONNET.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (146, 'Tabla ONT Utilizadas ONNET', 13, 1, '../Ocupacion/ocupacion_ont_utilizadas_ONNET.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (147, 'Reporte Trafico PON ONNET', 8, 1, '../Reporte_BW/index_fecha_pon_ONNET.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (148, 'Reporte General ONNET', 3, 1, '../Temp_Cpu/reporte_general_ONNET.php', 28);
INSERT INTO `OLT_MENU_3` VALUES (149, 'Reporte Temp/CPU ONNET', 3, 1, '../Temp_Cpu/info_ONNET.php', 28);
INSERT INTO `OLT_MENU_3` VALUES (150, 'Reporte General ONNET', 8, 1, '../ONT/ont_general_ONNET_KPI.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (151, 'Reporte ONT General ONNET', 9, 1, '../ONT/info_ont_equipos_ONNET.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (152, 'Reporte ONT Detalle ONNET', 10, 1, '../ONT/info_ont_total_ONNET.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (153, 'Reporte Historico Week ONNET', 11, 1, '../ONT/index_historico_week_ONNET.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (154, 'Reporte Historico Week Grafico ONNET', 12, 1, '../ONT/index_historico_week_grafico_ONNET.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (155, 'Reporte ONT Diario ONNET', 13, 1, '../ONT/Reporte_ONT_Diario/index_reporte_diario_ONNET.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (156, 'Reporte ONT Diario por Equipo ONNET', 14, 1, '../ONT/Reporte_ONT_Diario/index_reporte_diario_equipo_ONNET.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (157, 'Reporte General ONNET', 6, 1, '../Alarmas/alarmas_general_ONNET.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (158, 'Reporte Detalle ONNET', 7, 1, '../Alarmas/alarmas_ONNET.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (159, 'Uplinks LOS ONNET', 8, 1, '../Alarmas/tabla_alarma_critical_ONNET.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (160, 'Uplinks LOS Hist&oacute;rico ONNET', 9, 1, '../Alarmas/tabla_alarma_critical_historial_ONNET.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (161, 'Reporte General ONNET', 3, 1, '../Tarjetas/tarjeta_general_ONNET.php', 32);
INSERT INTO `OLT_MENU_3` VALUES (162, 'Reporte Detalle ONNET', 4, 0, '../Tarjetas/muestra_tarjeta_ONNET.php', 32);
INSERT INTO `OLT_MENU_3` VALUES (163, 'Reporte General ONNET', 3, 1, '../Uptime/muestra_uptime_ONNET.php', 37);
INSERT INTO `OLT_MENU_3` VALUES (164, 'Reporte Historico ONNET', 4, 1, '../Uptime/index_reporte_historico_ONNET.php', 37);
INSERT INTO `OLT_MENU_3` VALUES (165, 'Reporte con % de ocupacion de los puertos PON EQUIFIBER', 3, 1, '../Ocupacion/ocupacion_ont_EQUIFIBER.php', 50);
INSERT INTO `OLT_MENU_3` VALUES (166, 'Reporte con % de ocupacion semanal EQUIFIBER', 4, 1, '../ONNET/Reporte_Porcentaje_puertas_Pon/index_reporte_semanal_zl_EQUIFIBER.php', 50);
INSERT INTO `OLT_MENU_3` VALUES (167, 'Reporte Capacidad Peak BW Uplink EQUIFIBER', 13, 1, '../Reporte_BW/index_fecha_peak_uplink_capacidad_EQUIFIBER.php', 21);
INSERT INTO `OLT_MENU_3` VALUES (168, 'ONT por OLT EQUIFIBER', 14, 1, '../Ocupacion/ocupacion_ont_EQUIFIBER.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (169, 'Puertas Uplinks EQUIFIBER', 15, 1, '../Ocupacion/ocupacion_uplinks_EQUIFIBER.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (170, 'Tabla ONT Disponibles/Utilizadas Semanal EQUIFIBER', 16, 1, '../ONT/index_ocu_gpon_disp_ocu_EQUIFIBER.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (171, 'Tabla ONT Disponibles EQUIFIBER', 17, 1, '../Ocupacion/ocupacion_ont_disponibles_EQUIFIBER.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (172, 'Tabla ONT Utilizadas EQUIFIBER', 17, 1, '../Ocupacion/ocupacion_ont_utilizadas_EQUIFIBER.php', 19);
INSERT INTO `OLT_MENU_3` VALUES (173, 'Reporte Trafico PON EQUIFIBER', 9, 1, '../Reporte_BW/index_fecha_pon_EQUIFIBER.php', 20);
INSERT INTO `OLT_MENU_3` VALUES (174, 'Reporte General EQUIFIBER', 4, 1, '../Temp_Cpu/reporte_general_EQUIFIBER.php', 28);
INSERT INTO `OLT_MENU_3` VALUES (175, 'Reporte Temp/CPU EQUIFIBER', 6, 1, '../Temp_Cpu/info_EQUIFIBER.php', 28);
INSERT INTO `OLT_MENU_3` VALUES (176, 'Reporte General EQUIFIBER', 15, 1, '../ONT/ont_general_EQUIFIBER_KPI.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (177, 'Reporte ONT General EQUIFIBER', 15, 1, '../ONT/info_ont_equipos_EQUIFIBER.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (178, 'Reporte ONT Detalle EQUIFIBER', 15, 1, '../ONT/info_ont_total_EQUIFIBER.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (179, 'Reporte Historico Week EQUIFIBER', 16, 1, '../ONT/index_historico_week_EQUIFIBER.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (180, 'Reporte Historico Week Grafico EQUIFIBER', 17, 1, '../ONT/index_historico_week_grafico_EQUIFIBER.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (181, 'Subir Reporte de Incidencias y Clasificacion de fallas EQUIFIBER', 14, 1, '../ONNET/Reporte_Incidencias_Fallas/index_subir_archivo_v2_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (182, 'Reporte ONT Diario EQUIFIBER', 18, 1, '../ONT/Reporte_ONT_Diario/index_reporte_diario_EQUIFIBER.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (183, 'Reporte ONT Diario por Equipo EQUIFIBER', 18, 1, '../ONT/Reporte_ONT_Diario/index_reporte_diario_equipo_EQUIFIBER.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (184, 'Reporte General EQUIFIBER', 10, 1, '../Alarmas/alarmas_general_EQUIFIBER.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (185, 'Reporte Detalle EQUIFIBER', 11, 1, '../Alarmas/alarmas_EQUIFIBER.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (186, 'Uplinks LOS EQUIFIBER', 12, 1, '../Alarmas/tabla_alarma_critical_EQUIFIBER.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (187, 'Reporte de Incidencias y Clasificacion de fallas EQUIFIBER', 15, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v2_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (188, 'Disponibilidad de Red - Planta Externa EQUIFIBER', 16, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v3_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (189, 'Disponibilidad de Red - Planta Interna EQUIFIBER', 17, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v4_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (190, 'Disponibilidad de Red - Graficos Planta Interna EQUIFIBER', 18, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (191, 'Disponibilidad de Red  - Graficos Planta Externa EQUIFIBER', 19, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_2_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (192, 'Tiempos de Resolucion - Alto Impacto  Planta Externa EQUIFIBER', 20, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v5_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (193, 'Tiempos de Resolucion - Estandar  Planta Externa EQUIFIBER', 21, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v6_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (194, 'Tiempos de Resolucion - Planta Interna EQUIFIBER', 22, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_reporte_v7_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (195, 'Tiempos de Resolucion - Graficos Planta Externa Alto Impacto EQUIFIBER', 23, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_3_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (196, 'Tiempos de Resolucion - Graficos Planta Externa Estandar EQUIFIBER', 24, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_4_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (197, 'Disponibilidad de Red - Graficos Planta Interna Anual EQUIFIBER', 25, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_anual_interna_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (198, 'Disponibilidad de Red - Graficos Planta Externa Anual EQUIFIBER', 26, 1, '../ONNET/Reporte_Incidencias_Fallas/index_ver_graficos_anual_externa_EQUIFIBER.php', 47);
INSERT INTO `OLT_MENU_3` VALUES (199, 'Uplinks LOS Hist&oacute;rico EQUIFIBER', 13, 1, '../Alarmas/tabla_alarma_critical_historial_EQUIFIBER.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (200, 'Informe TP Planta Externa EQUIFIBER', 5, 1, '../ONNET/Reporte_planta_externa/tabla_reporte_planta_externa_EQUIFIBER.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (201, 'Subir Informe TP Planta Externa EQUIFIBER', 6, 1, '../ONNET/Reporte_planta_externa/index_subir_archivo_EQUIFIBER.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (202, 'Informe TP Planta Interna EQUIFIBER', 7, 1, '../ONNET/Reporte_planta_interna/tabla_reporte_planta_interna_EQUIFIBER.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (203, 'Subir Informe TP Planta Interna EQUIFIBER', 8, 1, '../ONNET/Reporte_planta_interna/index_subir_archivo_EQUIFIBER.php', 51);
INSERT INTO `OLT_MENU_3` VALUES (204, 'MTTR EQUIFIBER', 12, 1, '../ONNET/MTTR/tabla_reporte_mttr_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (205, 'Subir MTTR EQUIFIBER', 13, 1, '../ONNET/MTTR/index_subir_archivo_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (206, 'Disponibilidad de la Red Nacional (Incidente) EQUIFIBER', 14, 1, '../ONNET/Disponibilidad_Red_Nacional/index_ver_reporte_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (207, 'Subir Disponibilidad de la Red Nacional (Incidente) EQUIFIBER', 15, 1, '../ONNET/Disponibilidad_Red_Nacional/index_subir_archivo_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (208, 'Tasas de fallas Nacionales EQUIFIBER', 16, 1, '../ONNET/Tasa_Falla_Nacional/index_ver_reporte_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (209, 'Subir Tasas de fallas Nacionales EQUIFIBER', 17, 1, '../ONNET/Tasa_Falla_Nacional/index_subir_archivo_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (210, 'Tiempos de resolucion Nacional EQUIFIBER', 18, 1, '../ONNET/reporte_resolucion_nacional/tabla_reporte_rnacional_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (211, 'Subir Tiempos de resolucion Nacional EQUIFIBER', 19, 1, '../ONNET/reporte_resolucion_nacional/index_subir_archivo_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (212, 'Disponibilidad en zonas operativas EQUIFIBER', 20, 1, '../ONNET/Reporte_Incidencias/tabla_onnet_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (213, 'Subir Disponibilidad en zonas operativas EQUIFIBER', 21, 1, '../ONNET/Reporte_Incidencias/index_subir_archivo_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (214, 'Graficos Contadores y Estadisticas EQUIFIBER', 22, 1, '../ONNET/ContadoresEstadisticas/indexContadores_EQUIFIBER.php', 52);
INSERT INTO `OLT_MENU_3` VALUES (215, 'Reporte Planes de accion e informes de Incidencias de alto impacto EQUIFIBER', 3, 1, '../ONNET/Reporte_Falla_Critica/index_ver_reporte_EQUIFIBER.php', 53);
INSERT INTO `OLT_MENU_3` VALUES (216, 'Subir Planes de accion e informes de Incidencias de alto impacto EQUIFIBER', 4, 1, '../ONNET/Reporte_Falla_Critica/index_subir_archivo_EQUIFIBER.php', 53);
INSERT INTO `OLT_MENU_3` VALUES (217, 'Ver Reporte Inventario fisico y logico EQUIFIBER', 3, 1, '../ONNET/Reporte_Inventario_Fisico_logico/index_ver_reporte_EQUIFIBER.php', 54);
INSERT INTO `OLT_MENU_3` VALUES (218, 'Subir Reporte Inventario fisico y logico EQUIFIBER', 4, 1, '../ONNET/Reporte_Inventario_Fisico_logico/index_subir_archivo_EQUIFIBER.php', 54);
INSERT INTO `OLT_MENU_3` VALUES (219, 'Reporte Preventivo planta externa EQUIFIBER', 3, 1, '../ONNET/Reporte_Preventivo_Planta_Externa/index_ver_reporte_EQUIFIBER.php', 55);
INSERT INTO `OLT_MENU_3` VALUES (220, 'Subir Reporte Preventivo planta externa EQUIFIBER', 4, 1, '../ONNET/Reporte_Preventivo_Planta_Externa/index_subir_archivo_EQUIFIBER.php', 55);
INSERT INTO `OLT_MENU_3` VALUES (221, 'Subir Reporte de Hallazgos Planta Externa EQUIFIBER', 5, 1, '../ONNET/Reporte_Hallazgos_Externa/index_subir_archivo_EQUIFIBER.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (222, 'Reporte de Hallazgos Planta Externa EQUIFIBER', 6, 1, '../ONNET/Reporte_Hallazgos_Externa/index_ver_reporte_EQUIFIBER.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (223, 'Subir Reporte de Hallazgos Planta Interna EQUIFIBER', 7, 1, '../ONNET/Reporte_Hallazgos_Interna/index_subir_archivo_EQUIFIBER.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (224, 'Reporte de Hallazgos Planta Interna EQUIFIBER', 8, 1, '../ONNET/Reporte_Hallazgos_Interna/index_ver_reporte_EQUIFIBER.php', 56);
INSERT INTO `OLT_MENU_3` VALUES (225, 'Reporte Anulacion Tecnica Empresa EQUIFIBER', 9, 1, '../ONNET/Reporte_Anulacion_Tecnica_Empresa/index_ver_reporteEQUIFIBER.php', NULL);
INSERT INTO `OLT_MENU_3` VALUES (226, 'Reporte Anulacion Tecnica Empresa EQUIFIBER', 9, 1, '../ONNET/Reporte_Anulacion_Tecnica_Empresa/index_ver_reporte_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (227, 'Subir Anulacion Tecnica Empresa EQUIFIBER', 10, 1, '../ONNET/Reporte_Anulacion_Tecnica_Empresa/index_subir_archivo_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (228, 'Reporte Anulacion Tecnica Hogar EQUIFIBER', 11, 1, '../ONNET/Reporte_Anulacion_Tecnica_Hogar/index_ver_reporte_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (229, 'Subir Anulacion Tecnica Hogar EQUIFIBER', 12, 1, '../ONNET/Reporte_Anulacion_Tecnica_Hogar/index_subir_archivo_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (230, 'Reporte Factibilidad Negativa Empresa EQUIFIBER', 13, 1, '../ONNET/Reporte_Factibilidad_Negativa_Empresa/index_ver_reporte_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (231, 'Subir Factibilidad Negativa Empresa EQUIFIBER', 14, 1, '../ONNET/Reporte_Factibilidad_Negativa_Empresa/index_subir_archivo_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (232, 'Reporte Factibilidad Negativa Hogar EQUIFIBER', 15, 1, '../ONNET/Reporte_Factibilidad_Negativa_Hogar/index_ver_reporte_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (233, 'Subir Factibilidad Negativa Hogar EQUIFIBER', 16, 1, '../ONNET/Reporte_Factibilidad_Negativa_Hogar/index_subir_archivo_EQUIFIBER.php', 59);
INSERT INTO `OLT_MENU_3` VALUES (234, 'Subir Reporte CTOs EQUIFIBER', 3, 1, '../ONNET/Reporte_CTOs/index_subir_archivo_EQUIFIBER.php', 75);
INSERT INTO `OLT_MENU_3` VALUES (235, 'Reporte de CTOs EQUIFIBER', 4, 1, '../ONNET/Reporte_CTOs/index_ver_reporte_EQUIFIBER.php', 75);
INSERT INTO `OLT_MENU_3` VALUES (236, 'Subir Reporte Splitter EQUIFIBER', 3, 1, '../ONNET/Reporte_Splitter/index_subir_archivo_EQUIFIBER.php', 76);
INSERT INTO `OLT_MENU_3` VALUES (237, 'Reporte de Splitter EQUIFIBER', 4, 1, '../ONNET/Reporte_Splitter/index_ver_reporte_EQUIFIBER.php', 76);
INSERT INTO `OLT_MENU_3` VALUES (238, 'Proceso de Ordenamiento y Cruza EQUIFIBER', 3, 1, '../ONNET/Ordenamiento_Cruza_Gabinete/index_ver_reporte_EQUIFIBER.php', 63);
INSERT INTO `OLT_MENU_3` VALUES (239, 'Subir Proceso de Ordenamiento y Cruza EQUIFIBER', 4, 1, '../ONNET/Ordenamiento_Cruza_Gabinete/index_subir_archivo_EQUIFIBER.php', 63);
INSERT INTO `OLT_MENU_3` VALUES (240, 'Reporte General EQUIFIBER', 5, 1, '../Tarjetas/tarjeta_general_EQUIFIBER.php', 32);
INSERT INTO `OLT_MENU_3` VALUES (241, 'Reporte Detalle EQUIFIBER', 4, 0, '../Tarjetas/muestra_tarjeta_EQUIFIBER.php', 32);
INSERT INTO `OLT_MENU_3` VALUES (242, 'Reporte General EQUIFIBER', 5, 1, '../Uptime/muestra_uptime_EQUIFIBER.php', 37);
INSERT INTO `OLT_MENU_3` VALUES (243, 'Reporte Historico EQUIFIBER', 6, 1, '../Uptime/index_reporte_historico_EQUIFIBER.php', 37);
INSERT INTO `OLT_MENU_3` VALUES (244, 'Puertas PON LOS', 3, 1, '../Alarmas/tabla_alarma_los_ont.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (245, 'Grafico ONT Fechas', 19, 1, '../ONT/Grafico_ONT/index_grafico_fecha.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (246, 'Grafico ONT Week', 20, 1, '../ONT/Grafico_ONT/index_grafico_week.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (247, 'Grafico ONT Mensual', 21, 1, '../ONT/Grafico_ONT/index_grafico_mes.php', 29);
INSERT INTO `OLT_MENU_3` VALUES (248, 'Puertas PON LOS Historico ', 4, 1, '../Alarmas/ont_loss/index_historico_los_ont.php', 30);
INSERT INTO `OLT_MENU_3` VALUES (249, 'Trafico Uplinks EQUIFIBER', 3, 1, '../Ocupacion/Ocupacion_Historico/ocupacion_uplinks_EQUIFIBER.php', 17);
INSERT INTO `OLT_MENU_3` VALUES (250, 'Trafico PON EQUIFIBER', 4, 1, '../Ocupacion/Ocupacion_Historico_PON/ocupacion_uplinks_EQUIFIBER.php', 17);
INSERT INTO `OLT_MENU_3` VALUES (251, 'Trafico GPON EQUIFIBER', 4, 1, '../Graficos/grafico_trafico_gpon_equifiber.php', 18);
INSERT INTO `OLT_MENU_3` VALUES (252, 'Trafico Uplink EQUIFIBER', 5, 1, '../Graficos/grafico_trafico_uplinks_equifiber.php', 18);

SET FOREIGN_KEY_CHECKS = 1;
