/*
 ============================================================================
 OLT_BOT_AUDITORIA — registro de uso del bot
 ----------------------------------------------------------------------------
 QUE HACE
   Crea la tabla donde el bot deja constancia de quien hizo que y cuando:
   inicios de sesion, consultas ejecutadas, descargas, reseteos de contrasena
   e intentos de acceso denegados.

 POR QUE
   Permite auditar el uso del bot despues. Hasta ahora solo quedaba registro
   en log/bot.log, que rota cada 10 MB y se conserva 7 dias.

 RIESGOS
   - CREATE TABLE sobre el esquema Aden. No modifica ni borra nada existente:
     no lleva DROP TABLE. Si la tabla ya existe, el script falla sin tocar
     nada (comportamiento buscado).
   - El usuario de la aplicacion necesita permiso INSERT sobre esta tabla.
     Es la unica tabla, junto con OLT_USUARIOS, donde el bot escribe.
   - La tabla crece con el uso y NO se purga automaticamente (decision
     tomada). Ver la nota de mantenimiento al final.

 QUIEN LO EJECUTA
   El responsable del proyecto, manualmente. Ni el bot ni el agente de IA
   ejecutan DDL contra la base.

 ROLLBACK
   DROP TABLE `OLT_BOT_AUDITORIA`;
 ============================================================================
*/

CREATE TABLE `OLT_BOT_AUDITORIA` (
  `id`        bigint(20)   NOT NULL AUTO_INCREMENT,
  `fecha`     datetime     NOT NULL COMMENT 'Momento del evento',
  `chat_id`   bigint(20)   DEFAULT NULL COMMENT 'Chat de Telegram que origino el evento',
  `usuario`   varchar(100) DEFAULT NULL COMMENT 'Usuario del bot; NULL si no estaba autorizado',
  `accion`    varchar(30)  NOT NULL COMMENT 'INICIO_SESION | CONSULTA | DESCARGA | RESETEO_PASSWORD | ACCESO_DENEGADO',
  `detalle`   varchar(150) DEFAULT NULL COMMENT 'Opcion del menu o entrada solicitada',
  `parametro` varchar(255) DEFAULT NULL COMMENT 'Dato usado: fecha, IP, formato, usuario destino',
  PRIMARY KEY (`id`),
  KEY `idx_fecha` (`fecha`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_accion_fecha` (`accion`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*
 ----------------------------------------------------------------------------
 CONSULTAS UTILES DE AUDITORIA
 ----------------------------------------------------------------------------

 -- Actividad de un usuario en el ultimo mes
 SELECT fecha, accion, detalle, parametro
 FROM OLT_BOT_AUDITORIA
 WHERE usuario = 'jperez'
   AND fecha >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
 ORDER BY fecha DESC;

 -- Que se consulta mas
 SELECT detalle, COUNT(*) AS veces
 FROM OLT_BOT_AUDITORIA
 WHERE accion = 'CONSULTA'
 GROUP BY detalle
 ORDER BY veces DESC;

 -- Quien intento entrar sin autorizacion
 SELECT chat_id, COUNT(*) AS intentos, MIN(fecha) AS primero, MAX(fecha) AS ultimo
 FROM OLT_BOT_AUDITORIA
 WHERE accion = 'ACCESO_DENEGADO'
 GROUP BY chat_id
 ORDER BY intentos DESC;

 -- Historial de reseteos de contrasena
 SELECT fecha, usuario AS solicitante, parametro AS usuario_reseteado
 FROM OLT_BOT_AUDITORIA
 WHERE accion = 'RESETEO_PASSWORD'
 ORDER BY fecha DESC;

 ----------------------------------------------------------------------------
 MANTENIMIENTO
 ----------------------------------------------------------------------------
 Se decidio NO purgar automaticamente. Cuando la tabla lo amerite, esta es la
 sentencia de purga, para ejecutarla manualmente o por cron. El bot nunca
 ejecuta DELETE por iniciativa propia.

   DELETE FROM OLT_BOT_AUDITORIA
   WHERE fecha < DATE_SUB(NOW(), INTERVAL 1 YEAR);

 Conviene borrar por lotes si acumulo muchas filas, para no bloquear la tabla:

   DELETE FROM OLT_BOT_AUDITORIA
   WHERE fecha < DATE_SUB(NOW(), INTERVAL 1 YEAR)
   LIMIT 10000;
*/
