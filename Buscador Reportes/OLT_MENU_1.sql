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

 Date: 03/06/2026 15:56:35
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for OLT_MENU_1
-- ----------------------------
DROP TABLE IF EXISTS `OLT_MENU_1`;
CREATE TABLE `OLT_MENU_1`  (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  `posicion` int(11) NULL DEFAULT NULL,
  `principal` int(11) NULL DEFAULT NULL,
  `url` varchar(250) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 15 CHARACTER SET = latin1 COLLATE = latin1_swedish_ci ROW_FORMAT = Compact;

-- ----------------------------
-- Records of OLT_MENU_1
-- ----------------------------
INSERT INTO `OLT_MENU_1` VALUES (1, 'Reportes', 1, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (2, 'ONNET', 2, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (3, 'Equipos', 3, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (4, 'Trafico', 4, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (5, 'ODF', 5, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (6, 'Kpis', 6, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (7, 'Mantencion OLT', 7, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (8, 'Administracion', 20, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (9, 'Reportes enfocados a O&M', 9, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (10, 'Reportes para control y seguimiento de KPIs', 10, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (11, 'Provision', 11, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (12, 'Procedimientos y Flujos', 12, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (13, 'Mantencion OLT ONNET', 13, 2, '#');
INSERT INTO `OLT_MENU_1` VALUES (14, 'Mantencion OLT EQUIFIBER', 14, 2, '#');

SET FOREIGN_KEY_CHECKS = 1;
