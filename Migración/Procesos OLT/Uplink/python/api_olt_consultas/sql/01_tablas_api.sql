-- ============================================================================
-- sql/01_tablas_api.sql
-- Proyecto: api_olt_consultas
--
-- QUE HACE: crea las 9 tablas nuevas que necesita la API (prefijo OLT_API_).
--           Ninguna tabla existente (OLT_*) se modifica, ni se lee en este
--           script. Todas usan CREATE TABLE IF NOT EXISTS (re-ejecutable sin
--           romper si ya existen).
--
-- POR QUE: ver PLAN_API_OLT.md, seccion "Modelo de datos nuevo". Centraliza:
--   - Clientes/API keys y sus scopes                (OLT_API_CLIENTES)
--   - Anti fuerza bruta                              (OLT_API_INTENTOS_AUTH)
--   - Auditoria de toda peticion                      (OLT_API_AUDITORIA)
--   - Catalogo de consultas y sus comandos telnet      (OLT_API_CONSULTAS,
--                                                        OLT_API_CONSULTA_COMANDOS)
--   - Lista blanca de comandos de configuracion         (OLT_API_COMANDOS_APROBADOS)
--   - Log crudo de cada sesion telnet                   (OLT_API_LOG_TELNET)
--   - Ejecuciones asincronas                            (OLT_API_JOBS)
--   - Sesiones telnet abiertas (limite 3/OLT+usuario)   (OLT_API_SESIONES_TELNET)
--
-- RIESGOS: bajo. No se toca ninguna tabla existente; son tablas nuevas con
--          nombres que no colisionan con las 248 ya presentes en Aden.
--
-- QUIEN LO EJECUTA: el responsable del proyecto / DBA. El agente de IA NO
--          ejecuta CREATE/INSERT/ALTER/DROP contra la base de datos real
--          (ver skills/geret-estructura/references/README_LIMITES.md, seccion 3).
--          Revisar este archivo antes de correrlo.
--
-- REQUIERE: MySQL 5.7+ / 8.0 (usa el tipo JSON en OLT_API_JOBS.parametros).
--
-- ROLLBACK: ver bloque comentado al final del archivo.
-- ============================================================================

-- ── 1. Clientes (sistemas) autorizados a consumir la API ────────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_CLIENTES` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL COMMENT 'Nombre del sistema consumidor (ej. Portal OLT)',
  `prefijo_key` varchar(12) NOT NULL COMMENT 'Primeros caracteres de la API key; NO es secreto, sirve para ubicar el cliente sin recorrer hashes',
  `api_key_hash` varchar(255) NOT NULL COMMENT 'Hash PBKDF2-SHA256 de la API key completa (utils/seguridad.py). La key en claro NUNCA se guarda',
  `scopes` varchar(255) NOT NULL DEFAULT 'lectura' COMMENT 'CSV: lectura,ejecutar_consulta,ejecutar_comandos,config_aprobada,admin,log_cron',
  `ips_permitidas` varchar(500) DEFAULT NULL COMMENT 'CSV de IPs/CIDR permitidas. NULL o vacio = ninguna IP permitida (API sin TLS: se exige whitelist)',
  `rate_limit_peticiones` int(11) NOT NULL DEFAULT 60 COMMENT 'Peticiones permitidas dentro de rate_limit_ventana_seg',
  `rate_limit_ventana_seg` int(11) NOT NULL DEFAULT 60,
  `max_ejecuciones_hora` int(11) NOT NULL DEFAULT 60 COMMENT 'Tope de ejecuciones telnet en vivo por hora (protege a las OLT)',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_baja` datetime DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_prefijo_key` (`prefijo_key`) USING BTREE,
  KEY `idx_activo` (`activo`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Clientes (sistemas) autorizados a consumir la API, con su API key hasheada y scopes.';

-- ── 2. Anti fuerza bruta por IP ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_INTENTOS_AUTH` (
  `ip` varchar(45) NOT NULL COMMENT 'IP de origen (IPv4/IPv6)',
  `prefijo_key` varchar(12) DEFAULT NULL COMMENT 'Ultimo prefijo de key probado desde esa IP, si alguno',
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `ultima_fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Contador de intentos de autenticacion fallidos por IP (anti fuerza bruta).';

-- ── 3. Auditoria de toda peticion autenticada o rechazada ────────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_AUDITORIA` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `cliente_id` int(11) DEFAULT NULL COMMENT 'NULL si la autenticacion fallo antes de identificar al cliente',
  `ip_origen` varchar(45) NOT NULL,
  `accion` varchar(30) NOT NULL COMMENT 'AUTH_OK|AUTH_FAIL|LECTURA|EJECUCION|RECHAZADO',
  `endpoint` varchar(150) NOT NULL,
  `olt` varchar(100) DEFAULT NULL,
  `parametro` varchar(255) DEFAULT NULL COMMENT 'Detalle: consulta, comandos resumidos, motivo de rechazo, etc.',
  `resultado` varchar(20) DEFAULT NULL COMMENT 'OK|ERROR|RECHAZADO|TIMEOUT',
  `duracion_ms` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_fecha` (`fecha`) USING BTREE,
  KEY `idx_cliente` (`cliente_id`) USING BTREE,
  KEY `idx_accion_fecha` (`accion`, `fecha`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Auditoria de toda peticion autenticada o rechazada (modelo: tabla existente OLT_BOT_AUDITORIA).';

-- ── 4. Catalogo de consultas soportadas ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_CONSULTAS` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL COMMENT 'Identificador estable usado en la URL: /ejecutar/consulta/{codigo}',
  `descripcion` varchar(255) DEFAULT NULL,
  `tabla_dato` varchar(100) DEFAULT NULL COMMENT 'Tabla OLT_* de donde los routers de lectura leen el dato ya recolectado por los crons',
  `timeout_seg` int(11) NOT NULL DEFAULT 30,
  `perfil_credencial` varchar(20) NOT NULL DEFAULT 'DEFAULT' COMMENT 'DEFAULT|ALARMAS|ONT: que usuario telnet usar si no hay usuario dedicado geretapi',
  `modo_sync_max_olts` int(11) NOT NULL DEFAULT 1 COMMENT 'Sobre esta cantidad de OLT en una ejecucion, se responde con job_id en vez de esperar',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_codigo` (`codigo`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Catalogo de consultas soportadas (una fila por cada una de las ~20 identificadas en el analisis de origen).';

-- ── 5. Comandos fijos y ordenados de cada consulta ───────────────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_CONSULTA_COMANDOS` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `consulta_id` int(11) NOT NULL,
  `modelo` varchar(30) DEFAULT NULL COMMENT 'MA5800-X15|MA5600T|MA5603T|MA5680T. NULL = aplica a todos los modelos',
  `server` varchar(100) DEFAULT NULL COMMENT 'Excepcion por equipo puntual (ej. OLT-DURZUA-4, OLT-LAFLORIDA-1, OLT-CONCEPCION-1/3). NULL = fila general (no es excepcion)',
  `orden` int(11) NOT NULL COMMENT 'Orden de envio dentro de la secuencia telnet',
  `contexto` varchar(20) NOT NULL COMMENT 'user|enable|config|interface: nivel del CLI donde se envia el comando',
  `interface_tipo` varchar(10) DEFAULT NULL COMMENT 'eth|giu|scu|mpu|gpon, solo si contexto=interface',
  `comando_template` varchar(255) NOT NULL COMMENT 'Ej. "display port traffic {puerto}". Los placeholders {slot}/{puerto}/{vlan} los resuelve el ejecutor (utils/ejecutor.py, F4)',
  `enter_extra` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si=1, envia un Enter extra antes del comando (caso MA5800-X15 en varias consultas del analisis de origen)',
  `repetir_por` varchar(20) DEFAULT NULL COMMENT 'NULL|slot|puerto|vlan: si el comando se repite iterando una lista',
  `fuente_lista` varchar(50) DEFAULT NULL COMMENT 'OLT_PUERTAS_UPLINKS_GB|OLT_SERVER.pto1_12|OLT_VOLTAJE_TARJETA|OLT_VLAN_SERVICIOS|fijo',
  `lista_fija` varchar(255) DEFAULT NULL COMMENT 'CSV o rango "a-b" de valores si fuente_lista=fijo (ej. slots hardcodeados por modelo)',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_consulta` (`consulta_id`) USING BTREE,
  KEY `idx_consulta_modelo_server` (`consulta_id`, `modelo`, `server`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Comandos fijos y ordenados de cada consulta, por modelo/server. Reemplaza el hardcode duplicado en los ~112 archivos PHP.';

-- ── 6. Lista blanca de comandos de configuracion (NACE VACIA) ────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_COMANDOS_APROBADOS` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template` varchar(255) NOT NULL COMMENT 'Regex ANCLADA (^...$) con grupos tipados que valida el comando de configuracion aprobado',
  `descripcion` varchar(255) DEFAULT NULL,
  `scope_requerido` varchar(50) NOT NULL DEFAULT 'config_aprobada',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_alta` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aprobado_por` varchar(100) DEFAULT NULL COMMENT 'Quien autorizo este comando (registro manual, no automatizado)',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Lista blanca de comandos de configuracion aprobados. NACE VACIA (decision confirmada): dia 1 solo se aceptan comandos display.';

-- ── 7. Log crudo de cada sesion telnet ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_LOG_TELNET` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `fecha` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `olt` varchar(100) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `consulta_codigo` varchar(50) DEFAULT NULL COMMENT 'NULL si vino de /ejecutar/comandos (comandos libres, sin consulta fija asociada)',
  `origen` varchar(10) NOT NULL DEFAULT 'api' COMMENT 'api (ejecucion en vivo via este servicio) o cron (si un cron Python llega a escribir aqui, ver F7)',
  `job_uuid` char(36) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `comandos_enviados` text DEFAULT NULL COMMENT 'Lista de comandos enviados, uno por linea, YA sin la contrasena de login',
  `log_crudo` longtext DEFAULT NULL COMMENT 'Salida cruda del telnet, saneada (scrub de password) pero sin reformatear',
  `duracion_ms` int(11) DEFAULT NULL,
  `exito` tinyint(1) NOT NULL DEFAULT 0,
  `error` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_olt_consulta_fecha` (`olt`, `consulta_codigo`, `fecha`) USING BTREE,
  KEY `idx_job` (`job_uuid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Log crudo de cada sesion telnet abierta por la API (y, a futuro, por los crons). Retencion: ver sql/03_purga_logs.sql.';

-- ── 8. Ejecuciones asincronas (jobs) ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `OLT_API_JOBS` (
  `uuid` char(36) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `tipo` varchar(50) NOT NULL COMMENT 'ejecutar_consulta|ejecutar_comandos|ejecutar_lote',
  `parametros` json DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE' COMMENT 'PENDIENTE|RUNNING|OK|ERROR|TIMEOUT',
  `fecha_solicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `resultado` longtext DEFAULT NULL COMMENT 'JSON serializado con el resultado por OLT',
  `error` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`uuid`) USING BTREE,
  KEY `idx_cliente_estado` (`cliente_id`, `estado`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Ejecuciones asincronas (varias OLT o consultas largas). Ver GET /jobs/{uuid}.';

-- ── 9. Sesiones telnet abiertas (clave del limite de 3/OLT+usuario) ─────────
CREATE TABLE IF NOT EXISTS `OLT_API_SESIONES_TELNET` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `olt` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `usuario_telnet` varchar(50) NOT NULL,
  `origen` varchar(10) NOT NULL DEFAULT 'api',
  `job_uuid` char(36) DEFAULT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `fecha_apertura` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_latido` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `estado` varchar(10) NOT NULL DEFAULT 'ABIERTA' COMMENT 'ABIERTA|CERRADA|HUERFANA',
  `fecha_cierre` datetime DEFAULT NULL,
  `motivo_cierre` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_olt_usuario_estado` (`olt`, `usuario_telnet`, `estado`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='Registro vivo de sesiones telnet abiertas por la API. Clave del gobernador de cupo: el equipo admite max 3 sesiones simultaneas por (OLT, usuario); la 4a la cierra o bloquea.';

-- ============================================================================
-- VERIFICACION (ejecutar despues de correr este script)
-- ============================================================================
-- SHOW TABLES LIKE 'OLT_API_%';
-- SELECT TABLE_NAME, TABLE_ROWS, CREATE_TIME
--   FROM information_schema.TABLES
--   WHERE TABLE_SCHEMA = 'Aden' AND TABLE_NAME LIKE 'OLT_API_%';

-- ============================================================================
-- ROLLBACK (ejecutar manualmente si hace falta revertir este script completo)
-- Orden inverso para no dejar referencias logicas colgadas.
-- ============================================================================
-- DROP TABLE IF EXISTS OLT_API_SESIONES_TELNET;
-- DROP TABLE IF EXISTS OLT_API_JOBS;
-- DROP TABLE IF EXISTS OLT_API_LOG_TELNET;
-- DROP TABLE IF EXISTS OLT_API_COMANDOS_APROBADOS;
-- DROP TABLE IF EXISTS OLT_API_CONSULTA_COMANDOS;
-- DROP TABLE IF EXISTS OLT_API_CONSULTAS;
-- DROP TABLE IF EXISTS OLT_API_AUDITORIA;
-- DROP TABLE IF EXISTS OLT_API_INTENTOS_AUTH;
-- DROP TABLE IF EXISTS OLT_API_CLIENTES;
