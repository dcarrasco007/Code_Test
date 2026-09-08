/*
 ============================================================================
 OLT_BOT_USUARIOS — v2: clave propia del bot y control de sesion
 ----------------------------------------------------------------------------
 QUE HACE
   Agrega a la tabla OLT_BOT_USUARIOS las columnas necesarias para que el bot
   pida una contrasena propia antes de dejar consultar: el hash de la clave,
   el control de intentos fallidos y la vigencia de la sesion.

 POR QUE
   Hasta ahora bastaba con que el chat_id estuviera dado de alta. Si alguien
   toma el telefono de un usuario autorizado, entra al bot sin mas. Esta capa
   exige ademas una clave.

   La clave es INDEPENDIENTE de la del portal OLT: la del portal nunca viaja
   por Telegram, y si esta se filtra, la cuenta del portal no se ve afectada.
   Por lo mismo no usa MD5 sino PBKDF2-SHA256 con salt por usuario.

 RIESGOS
   - ALTER TABLE sobre OLT_BOT_USUARIOS. Es la tabla propia del bot: el portal
     no la usa, asi que no afecta a la web.
   - Bloquea la tabla mientras corre. Con pocas filas es instantaneo.
   - Tras ejecutarlo, TODOS los usuarios quedan con pass_bot en NULL y el bot
     les pedira que soliciten su clave a un administrador. Por eso el paso 2
     habilita al primer administrador.

 QUIEN LO EJECUTA
   El responsable del proyecto, manualmente.

 ROLLBACK
   ALTER TABLE `OLT_BOT_USUARIOS`
     DROP COLUMN `pass_bot`,
     DROP COLUMN `pass_temporal`,
     DROP COLUMN `fecha_cambio_pass`,
     DROP COLUMN `intentos_fallidos`,
     DROP COLUMN `bloqueado_hasta`,
     DROP COLUMN `sesion_expira`;
 ============================================================================
*/

-- ----------------------------------------------------------------
-- PASO 1 — Columnas nuevas
-- ----------------------------------------------------------------

ALTER TABLE `OLT_BOT_USUARIOS`
  ADD COLUMN `pass_bot`          varchar(255) DEFAULT NULL
      COMMENT 'Hash PBKDF2-SHA256 de la clave del bot. NULL = sin clave asignada',
  ADD COLUMN `pass_temporal`     tinyint(1)   NOT NULL DEFAULT 1
      COMMENT '1 = clave asignada por un admin; debe cambiarla al ingresar',
  ADD COLUMN `fecha_cambio_pass` datetime     DEFAULT NULL
      COMMENT 'Ultima vez que se establecio la clave',
  ADD COLUMN `intentos_fallidos` int(11)      NOT NULL DEFAULT 0
      COMMENT 'Intentos de login fallidos consecutivos',
  ADD COLUMN `bloqueado_hasta`   datetime     DEFAULT NULL
      COMMENT 'Bloqueo temporal por intentos fallidos',
  ADD COLUMN `sesion_expira`     datetime     DEFAULT NULL
      COMMENT 'Vigencia de la sesion; sobrevive reinicios del bot';

-- El login busca por chat_id y valida la sesion en la misma consulta.
ALTER TABLE `OLT_BOT_USUARIOS`
  ADD KEY `idx_sesion` (`chat_id`, `sesion_expira`);


-- ----------------------------------------------------------------
-- PASO 2 — Habilitar al primer administrador
-- ----------------------------------------------------------------
--
-- Sin esto NADIE puede entrar: toda clave la asigna un administrador desde el
-- bot, y no habria ninguno con clave para empezar.
--
-- El hash de abajo corresponde a la clave "123456" y esta marcado como
-- temporal, de modo que el bot obligue a cambiarla en el primer ingreso.
--
-- REEMPLAZA 'tu_usuario' por el usuario administrador que corresponda. Debe
-- existir en OLT_BOT_USUARIOS y su perfil en OLT_USUARIOS debe ser 1.

UPDATE `OLT_BOT_USUARIOS`
SET `pass_bot`          = 'pbkdf2_sha256$200000$aIkxqzVf1Zp1juarBe8xAA==$mae/UPpVr6ZK20zCZKHTC/MNXKz8SLLHpwpmDJoMFoc=',
    `pass_temporal`     = 1,
    `fecha_cambio_pass` = NOW(),
    `intentos_fallidos` = 0,
    `bloqueado_hasta`   = NULL,
    `sesion_expira`     = NULL
WHERE `usuario` = 'tu_usuario';

-- Verificacion: debe devolver 1 fila, con perfil = 1.
--
--   SELECT b.usuario, b.chat_id, u.perfil,
--          CASE WHEN b.pass_bot IS NULL THEN 'sin clave' ELSE 'con clave' END AS clave
--   FROM OLT_BOT_USUARIOS b
--   LEFT JOIN OLT_USUARIOS u ON u.usuario = b.usuario
--   WHERE b.usuario = 'tu_usuario';


/*
 ----------------------------------------------------------------------------
 OPERACION HABITUAL
 ----------------------------------------------------------------------------
 De aqui en adelante todo se hace desde el bot, sin tocar SQL:

   - "Asignar clave del bot"  (menu de administrador) deja una clave inicial
     temporal a un usuario ya dado de alta.
   - "Cambiar mi clave"       la cambia el propio usuario.
   - "Cerrar sesion"          invalida la sesion vigente.

 Consultas utiles:

   -- Quien tiene clave asignada y quien no
   SELECT usuario, chat_id,
          CASE WHEN pass_bot IS NULL THEN 'sin clave'
               WHEN pass_temporal = 1 THEN 'temporal'
               ELSE 'definitiva' END AS estado_clave,
          fecha_cambio_pass, sesion_expira
   FROM OLT_BOT_USUARIOS
   ORDER BY usuario;

   -- Cuentas bloqueadas en este momento
   SELECT usuario, intentos_fallidos, bloqueado_hasta
   FROM OLT_BOT_USUARIOS
   WHERE bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW();

   -- Desbloquear a alguien manualmente (tambien se resuelve reasignando
   -- la clave desde el bot)
   UPDATE OLT_BOT_USUARIOS
   SET intentos_fallidos = 0, bloqueado_hasta = NULL
   WHERE usuario = 'jperez';
*/
