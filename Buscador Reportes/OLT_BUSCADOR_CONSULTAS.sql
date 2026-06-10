/*
 Tabla para el Buscador/Consulta Inteligente (asincrono via BD)
 Flujo:
   1) El front inserta una fila con estado='pendiente'.
   2) Un proceso externo lee las 'pendiente', genera la respuesta,
      escribe 'respuesta', pone fecha_respuesta=NOW() y estado='listo' (o 'error').
   3) El front consulta por 'id' cada pocos segundos hasta que estado='listo'/'error'.
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `OLT_BUSCADOR_CONSULTAS`;
CREATE TABLE `OLT_BUSCADOR_CONSULTAS` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) DEFAULT NULL,
  `id_perfil` int(11) DEFAULT NULL,
  `consulta` text,
  `respuesta` mediumtext,
  -- estados posibles: pendiente | procesando | listo | error
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` datetime DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_perfil` (`id_perfil`),
  KEY `idx_estado_fecha` (`estado`, `fecha_solicitud`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET FOREIGN_KEY_CHECKS = 1;
