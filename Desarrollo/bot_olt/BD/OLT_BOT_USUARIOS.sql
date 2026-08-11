/*
 ============================================================================
 OLT_BOT_USUARIOS — usuarios autorizados del bot de Telegram
 ----------------------------------------------------------------------------
 QUE HACE
   Crea la tabla que el bot consulta para decidir si un chat de Telegram
   puede usarlo. Sin esta tabla el bot rechaza a todos los usuarios.

 POR QUE
   El bot identifica a la persona por su `chat_id` de Telegram, que no existe
   en OLT_USUARIOS. Esta tabla hace de puente entre el chat_id y el usuario
   de la plataforma OLT.

 RIESGOS
   - CREATE TABLE sobre el esquema Aden. No modifica ni borra tablas
     existentes: no lleva DROP TABLE por seguridad. Si la tabla ya existe,
     el script falla sin tocar nada (comportamiento buscado).
   - Revisar que el usuario de la aplicacion tenga permiso SELECT sobre ella.

 QUIEN LO EJECUTA
   El responsable del proyecto, manualmente. Ni el bot ni el agente de IA
   ejecutan DDL ni escrituras contra la base.

 ROLLBACK
   DROP TABLE `OLT_BOT_USUARIOS`;
 ============================================================================
*/

CREATE TABLE `OLT_BOT_USUARIOS` (
  `id`            int(11)      NOT NULL AUTO_INCREMENT,
  `chat_id`       bigint(20)   NOT NULL COMMENT 'ID del chat de Telegram',
  `usuario`       varchar(100) DEFAULT NULL COMMENT 'Usuario de la plataforma OLT (OLT_USUARIOS.usuario)',
  `nombre`        varchar(150) DEFAULT NULL COMMENT 'Nombre para mostrar en el saludo',
  `perfil`        int(11)      DEFAULT NULL COMMENT 'Perfil de acceso, equivalente a OLT_USUARIOS.perfil',
  `activo`        tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1 = habilitado, 0 = revocado',
  `fecha_alta`    datetime     DEFAULT NULL,
  `fecha_baja`    datetime     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_id` (`chat_id`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*
 Alta de un usuario (ejemplo). El chat_id lo entrega el propio bot: al
 escribirle sin estar autorizado, responde con el ID a solicitar.

   INSERT INTO `OLT_BOT_USUARIOS` (chat_id, usuario, nombre, perfil, activo, fecha_alta)
   VALUES (123456789, 'jperez', 'Juan Perez', 1, 1, NOW());

 Revocar el acceso sin borrar el registro:

   UPDATE `OLT_BOT_USUARIOS` SET activo = 0, fecha_baja = NOW() WHERE chat_id = 123456789;
*/
