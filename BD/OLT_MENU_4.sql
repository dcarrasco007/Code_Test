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

 Date: 03/06/2026 15:57:15
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for OLT_MENU_4
-- ----------------------------
DROP TABLE IF EXISTS `OLT_MENU_4`;
CREATE TABLE `OLT_MENU_4`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `posicion` int(11) NULL DEFAULT NULL,
  `principal` int(11) NULL DEFAULT NULL,
  `url` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `id_olt_menu_3` int(11) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 19 CHARACTER SET = latin1 COLLATE = latin1_swedish_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of OLT_MENU_4
-- ----------------------------
INSERT INTO `OLT_MENU_4` VALUES (1, 'Subir Archivo Semanal', 1, 1, '../Capacidad_BW_Excel/index_subir_excel.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (2, 'Reporte BW Capacidad Semanal', 2, 1, '../Capacidad_BW_Excel/index_reporte_excel.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (3, 'Subir Archivo Mensual', 3, 1, '../Capacidad_BW_Excel/index_subir_excel_mes.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (4, 'Reporte BW Capacidad Mensual', 4, 1, '../Capacidad_BW_Excel/index_reporte_excel_mes.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (5, 'Subir Archivo Anual', 5, 1, '../Capacidad_BW_Excel/index_subir_excel_year.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (6, 'Reporte BW Capacidad Anual', 6, 1, '../Capacidad_BW_Excel/index_reporte_excel_year.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (7, 'Reporte BW Capacidad Semanal ONNET', 7, 1, '../Capacidad_BW_Excel/index_reporte_excel_ONNET.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (8, 'Reporte BW Capacidad Mensual ONNET', 8, 1, '../Capacidad_BW_Excel/index_reporte_excel_mes_ONNET.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (9, 'Reporte BW Capacidad Anual ONNET', 9, 1, '../Capacidad_BW_Excel/index_reporte_excel_year_ONNET.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (10, 'Subir Archivo Semanal ONNET', 10, 1, '../Capacidad_BW_Excel/index_subir_excel_ONNET.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (11, 'Subir Archivo Mensual ONNET', 11, 1, '../Capacidad_BW_Excel/index_subir_excel_mes_ONNET.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (12, 'Subir Archivo Anual ONNET', 12, 1, '../Capacidad_BW_Excel/index_subir_excel_year_ONNET.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (13, 'Reporte BW Capacidad Semanal EQUIFIBER', 13, 1, '../Capacidad_BW_Excel/index_reporte_excel_EQUIFIBER.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (14, 'Reporte BW Capacidad Mensual EQUIFIBER', 14, 1, '../Capacidad_BW_Excel/index_reporte_excel_mes_EQUIFIBER.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (15, 'Reporte BW Capacidad Anual EQUIFIBER', 15, 1, '../Capacidad_BW_Excel/index_reporte_excel_year_EQUIFIBER.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (16, 'Subir Archivo Semanal EQUIFIBER', 16, 1, '../Capacidad_BW_Excel/index_subir_excel_EQUIFIBER.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (17, 'Subir Archivo Mensual EQUIFIBER', 17, 1, '../Capacidad_BW_Excel/index_subir_excel_mes_EQUIFIBER.php', 34);
INSERT INTO `OLT_MENU_4` VALUES (18, 'Subir Archivo Anual EQUIFIBER', 18, 1, '../Capacidad_BW_Excel/index_subir_excel_year_EQUIFIBER.php', 34);

SET FOREIGN_KEY_CHECKS = 1;
