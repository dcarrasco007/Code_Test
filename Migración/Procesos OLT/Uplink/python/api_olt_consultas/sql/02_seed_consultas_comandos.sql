-- ============================================================================
-- sql/02_seed_consultas_comandos.sql
-- Proyecto: api_olt_consultas
--
-- QUE HACE: puebla OLT_API_CONSULTAS y OLT_API_CONSULTA_COMANDOS con las 20
--           consultas identificadas en el análisis de los procesos PHP de
--           php_8_0/ (ver PLAN_API_OLT.md, tabla "Análisis consolidado").
--           OLT_API_COMANDOS_APROBADOS queda SIN insertar nada (nace vacía:
--           decisión confirmada — solo "display" hasta que el responsable
--           apruebe comandos de configuración).
--
-- COMO LEER LAS FILAS DE COMANDOS: cada fila es una PLANTILLA, no un comando
--           ya expandido. El ejecutor (utils/ejecutor.py, F4) la resuelve así:
--             - contexto='user'/'enable'/'config'/'interface' indica en qué
--               nivel del CLI se envía (la navegación enable/config/interface/
--               quit la genera siempre la API, nunca el cliente).
--             - repetir_por=NULL      -> se envía tal cual (comando_template
--               ya es el comando final, sin placeholders).
--             - repetir_por='slot'    -> {slot} se reemplaza iterando
--               lista_fija (si fuente_lista='fijo') o la fuente indicada.
--             - repetir_por='puerto'  -> {puerto} se reemplaza iterando la
--               fuente_lista para ESE slot ya resuelto.
--             - repetir_por='vlan'    -> {vlan} se reemplaza iterando
--               fuente_lista (ej. OLT_VLAN_SERVICIOS).
--           Una fila con server NO NULO es una EXCEPCIÓN puntual (ej.
--           OLT-LAFLORIDA-1): el ejecutor la prioriza sobre la fila general
--           del mismo modelo cuando el server coincide.
--
-- ALCANCE DE ESTE SEED (documentado, no oculto): cubre el camino GENERAL de
--           cada consulta por modelo con fidelidad razonable. Las excepciones
--           MENORES/cosméticas del PHP original NO se replican aquí (ya se
--           decidió lo mismo en uplink_trafico_15m para casos análogos):
--             - Prompts exactos hardcodeados para OLT-DURZUA-4 (10.99.24.68).
--             - Variantes "estado_equipo2/3" que solo agregan una línea en
--               blanco extra o repiten un comando sin cambiar el dato.
--           Si al implementar F4 (parsers) con datos reales hace falta alguna
--           de estas excepciones para que el parser funcione, agregarla aquí
--           como una fila adicional con `server` puntual — está pensado para
--           extenderse así, no hace falta rehacer el catálogo.
--
-- QUIEN LO EJECUTA: el responsable del proyecto, DESPUES de correr
--           01_tablas_api.sql. El agente de IA no ejecuta INSERT (ver
--           README_LIMITES.md).
--
-- IDEMPOTENCIA: pensado para correr UNA SOLA VEZ (usa LAST_INSERT_ID(), no
--           ON DUPLICATE KEY). Para reiniciar el catálogo completo, ver el
--           bloque de RESET al final del archivo (ejecutarlo manualmente).
-- ============================================================================


-- ============================================================================
-- 1. uplink_trafico — trafico de uplink por puerto (equivalente a los cron
--    Python ya migrados en uplink_trafico / uplink_trafico_15m)
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('uplink_trafico', 'Trafico de uplink por puerto (bajada/subida)', 'OLT_TRAFICO_UPLINK_HORA', 15, 'DEFAULT', 1, 1);
SET @id_uplink_trafico = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, interface_tipo, comando_template, repetir_por, fuente_lista, lista_fija) VALUES
(@id_uplink_trafico, 'MA5800-X15', 10, 'interface', 'eth', 'interface eth 0/{slot}', 'slot', 'fijo', '16,17,18'),
(@id_uplink_trafico, 'MA5800-X15', 11, 'interface', NULL,  'display port traffic {puerto}', 'puerto', 'OLT_SERVER.pto1_12', NULL),
(@id_uplink_trafico, 'MA5600T',    10, 'interface', 'giu', 'interface giu 0/{slot}', 'slot', 'fijo', '17,18'),
(@id_uplink_trafico, 'MA5600T',    11, 'interface', NULL,  'display port traffic {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_uplink_trafico, 'MA5680T',    10, 'interface', 'giu', 'interface giu 0/{slot}', 'slot', 'fijo', '17,18'),
(@id_uplink_trafico, 'MA5680T',    11, 'interface', NULL,  'display port traffic {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_uplink_trafico, 'MA5603T',    10, 'interface', 'giu', 'interface giu 0/{slot}', 'slot', 'fijo', '8,9'),
(@id_uplink_trafico, 'MA5603T',    11, 'interface', NULL,  'display port traffic {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL);
-- NOTA: excepciones por IP (X15 mpu 0/8-0/9, MA5600T scu 0/7-0/8) se resuelven
-- en codigo (utils/ejecutor.py) reutilizando las listas ya existentes en
-- python/uplink_trafico_15m/config/settings.py (IPS_MA5800X15_MPU_8_9, etc.)
-- para no duplicar esas listas en dos lugares. Ver PLAN_API_OLT.md F4.


-- ============================================================================
-- 2. alarmas_activas — alarmas activas (todas)
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('alarmas_activas', 'Alarmas activas de la OLT', 'OLT_ALARMAS', 5, 'DEFAULT', 1, 1);
SET @id_alarmas_activas = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_alarmas_activas, NULL, 10, 'config', 'display alarm active all');


-- ============================================================================
-- 3. alarmas_detalle — alarmas activas clasificadas por severidad
--    (mismo comando que alarmas_activas; la clasificacion CRITICAL/MAJOR/
--    MINOR/WARNING la hace el parser de esta consulta, no el comando)
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('alarmas_detalle', 'Alarmas activas clasificadas por severidad', 'OLT_CANTIDAD_ALARMAS_DETALLE', 5, 'DEFAULT', 1, 1);
SET @id_alarmas_detalle = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_alarmas_detalle, NULL, 10, 'config', 'display alarm active all');


-- ============================================================================
-- 4. alarmas_critical_los — alarmas criticas + historico de recuperadas
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('alarmas_critical_los', 'Alarmas criticas activas y su historico de recuperacion', 'OLT_ALARMA_CRITICAL_LOS', 5, 'DEFAULT', 1, 1);
SET @id_alarmas_critical_los = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_alarmas_critical_los, NULL,         10, 'config', 'display alarm active alarmlevel critical'),
(@id_alarmas_critical_los, 'MA5800-X15', 11, 'config', 'display alarm history alarmlevel cleared alarmclass recovery detail'),
(@id_alarmas_critical_los, NULL,         12, 'config', 'display alarm history alarmclass recovery alarmlevel critical detail');
-- La fila 12 (modelo=NULL) aplica a MA5600T/MA5603T/MA5680T; el ejecutor debe
-- omitirla si el modelo es MA5800-X15 (ya cubierto por la fila 11). Ver F4.


-- ============================================================================
-- 5. alarmas_los_ont — perdida de senal optica (LOS) de ONTs
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('alarmas_los_ont', 'ONTs con perdida de senal optica (LOS)', 'OLT_ALARMA_LOS_ONT', 5, 'DEFAULT', 1, 1);
SET @id_alarmas_los_ont = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_alarmas_los_ont, NULL, 10, 'config', 'display alarm active alarmlevel major list');


-- ============================================================================
-- 6. potencia_optica_uplink — potencia TX/RX de los puertos de uplink
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('potencia_optica_uplink', 'Potencia optica (TX/RX) de los puertos de uplink', 'OLT_POTENCIA_OPTICA_UPLINK', 60, 'DEFAULT', 1, 1);
SET @id_potencia_optica = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, server, orden, contexto, interface_tipo, comando_template, repetir_por, fuente_lista, lista_fija) VALUES
(@id_potencia_optica, 'MA5600T',    NULL, 10, 'interface', 'scu', 'interface scu 0/{slot}', 'slot', 'fijo', '7,8'),
(@id_potencia_optica, 'MA5600T',    NULL, 11, 'interface', NULL,  'display port ddm-info {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_potencia_optica, 'MA5600T',    NULL, 12, 'interface', 'giu', 'interface giu 0/{slot}', 'slot', 'fijo', '19,20,18,17'),
(@id_potencia_optica, 'MA5600T',    NULL, 13, 'interface', NULL,  'display port ddm-info {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_potencia_optica, 'MA5800-X15', NULL, 10, 'interface', 'eth', 'interface eth 0/{slot}', 'slot', 'fijo', '16,19,20,18,17'),
(@id_potencia_optica, 'MA5800-X15', NULL, 11, 'interface', NULL,  'display port ddm-info {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_potencia_optica, 'MA5800-X15', NULL, 12, 'interface', 'mpu', 'interface mpu 0/{slot}', 'slot', 'fijo', '8,9'),
(@id_potencia_optica, 'MA5800-X15', NULL, 13, 'interface', NULL,  'display port ddm-info {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_potencia_optica, 'MA5800-X15', 'OLT-CONCEPCION-3', 10, 'interface', 'mpu', 'interface mpu 0/{slot}', 'slot', 'fijo', '8,9'),
(@id_potencia_optica, 'MA5800-X15', 'OLT-CONCEPCION-3', 11, 'interface', NULL,  'display port ddm-info {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_potencia_optica, 'MA5603T',    NULL, 10, 'interface', 'giu', 'interface giu 0/{slot}', 'slot', 'fijo', '8,9,19,20,18,17'),
(@id_potencia_optica, 'MA5603T',    NULL, 11, 'interface', NULL,  'display port ddm-info {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL),
(@id_potencia_optica, 'MA5680T',    NULL, 10, 'interface', 'giu', 'interface giu 0/{slot}', 'slot', 'fijo', '19,20,18,17'),
(@id_potencia_optica, 'MA5680T',    NULL, 11, 'interface', NULL,  'display port ddm-info {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB', NULL);
-- server='OLT-CONCEPCION-3' tiene prioridad sobre la fila general MA5800-X15
-- (la excepcion real: estado_equipo_CONCE del PHP usa solo mpu, no eth).


-- ============================================================================
-- 7. tarjetas — voltaje y tipo de tarjetas instaladas
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('tarjetas', 'Voltaje y tipo de tarjetas instaladas en la OLT', 'OLT_DETALLE_TARJETA', 5, 'DEFAULT', 1, 1);
SET @id_tarjetas = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_tarjetas, NULL, 10, 'config', 'display power 0'),
(@id_tarjetas, NULL, 11, 'config', 'display board 0');


-- ============================================================================
-- 8. fan — estado de los ventiladores (FAN/EMU)
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('fan', 'Estado de los ventiladores (EMU/FAN)', 'OLT_FAN_ESTADO', 3, 'DEFAULT', 1, 1);
SET @id_fan = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_fan, NULL, 10, 'config', 'display emu 0');


-- ============================================================================
-- 9. vlan_cantidad — cantidad de VLAN por puerta
--    NOTA: el PHP original abre UNA sesion telnet POR PUERTA (no una sesion
--    con todas las puertas adentro). El ejecutor debe respetar esto: cada
--    puerta consume su propio turno de cupo del gobernador de sesiones.
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('vlan_cantidad', 'Cantidad de VLAN configuradas por puerta', 'OLT_CANTIDAD_VLAN', 5, 'DEFAULT', 1, 1);
SET @id_vlan_cantidad = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template, repetir_por, fuente_lista) VALUES
(@id_vlan_cantidad, NULL, 10, 'config', 'display port vlan {puerto}', 'puerto', 'OLT_PUERTAS_UPLINKS_GB');


-- ============================================================================
-- 10. vlan_trafico — trafico por VLAN de servicio
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('vlan_trafico', 'Trafico de subida/bajada por VLAN de servicio', 'OLT_VLAN_TRAFICO', 10, 'DEFAULT', 1, 1);
SET @id_vlan_trafico = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template, repetir_por, fuente_lista) VALUES
(@id_vlan_trafico, NULL, 10, 'config', 'display traffic vlan {vlan}', 'vlan', 'OLT_VLAN_SERVICIOS');


-- ============================================================================
-- 11. vlan_servicios — listado de VLAN configuradas y clientes por VLAN
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('vlan_servicios', 'Listado de VLAN configuradas y cantidad de clientes', 'OLT_VLAN_SERVICIO_CANTIDAD', 5, 'DEFAULT', 1, 1);
SET @id_vlan_servicios = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_vlan_servicios, NULL, 10, 'config', 'display vlan all');


-- ============================================================================
-- 12. energia_alarma — alarmas de energia (fuente de poder)
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('energia_alarma', 'Alarmas de energia / fuente de poder', 'OLT_ALARMA_ENERGIA', 5, 'DEFAULT', 1, 1);
SET @id_energia_alarma = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_energia_alarma, 'MA5800-X15', 10, 'config', 'display alarm history alarmlevel minor detail'),
(@id_energia_alarma, 'MA5800-X15', 11, 'config', 'display alarm history alarmlevel cleared detail'),
(@id_energia_alarma, NULL,         10, 'config', 'display alarm active alarmlevel major detail'),
(@id_energia_alarma, NULL,         11, 'config', 'display alarm history alarmlevel major detail');
-- Filas modelo=NULL aplican a MA5600T/MA5603T/MA5680T (el ejecutor las omite
-- si el modelo es MA5800-X15, igual que en alarmas_critical_los).


-- ============================================================================
-- 13. energia_estado — estado de tarjetas de energia (board/power)
--    OJO: a diferencia de casi todas las demas consultas, esta NUNCA hace
--    enable/config: el PHP original trabaja en la vista de usuario ('>').
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('energia_estado', 'Estado de las tarjetas de energia (board status / power status)', 'OLT_ESTADO_ENERGIA', 5, 'DEFAULT', 1, 1);
SET @id_energia_estado = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, server, orden, contexto, comando_template, enter_extra, repetir_por, fuente_lista, lista_fija) VALUES
(@id_energia_estado, 'MA5603T',    NULL, 10, 'user', 'display board 0/{slot}', 0, 'slot', 'fijo', '6,7'),
(@id_energia_estado, 'MA5600T',    NULL, 10, 'user', 'display board 0/{slot}', 0, 'slot', 'fijo', '19,20'),
(@id_energia_estado, 'MA5600T', 'OLT-LAFLORIDA-1', 10, 'user', 'display board 0/{slot}', 0, 'slot', 'fijo', '9,10'),
(@id_energia_estado, 'MA5680T',    NULL, 10, 'user', 'display board 0/{slot}', 0, 'slot', 'fijo', '19,20'),
(@id_energia_estado, 'MA5800-X15', NULL, 10, 'user', 'display board 0/{slot}', 1, 'slot', 'fijo', '18,19');
-- enter_extra=1 en MA5800-X15: el PHP envia un Enter adicional antes de cada
-- 'display board' en esta consulta para ese modelo.


-- ============================================================================
-- 14. pon_trafico — trafico de puertos PON (GPON) por slot
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('pon_trafico', 'Trafico de subida/bajada de los puertos PON (GPON)', 'OLT_TRAFICOGPON_HORA', 120, 'DEFAULT', 1, 1);
SET @id_pon_trafico = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, interface_tipo, comando_template, repetir_por, fuente_lista, lista_fija) VALUES
(@id_pon_trafico, NULL, 10, 'interface', 'gpon', 'interface gpon 0/{slot}', 'slot', 'OLT_VOLTAJE_TARJETA', NULL),
(@id_pon_trafico, NULL, 11, 'interface', NULL,   'display port traffic {puerto}', 'puerto', 'fijo', '0-15');


-- ============================================================================
-- 15. temperatura_cpu — temperatura y uso de CPU por slot
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('temperatura_cpu', 'Temperatura y porcentaje de uso de CPU por slot', 'OLT_TEMP_CPU', 5, 'DEFAULT', 1, 1);
SET @id_temperatura_cpu = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, server, orden, contexto, comando_template, enter_extra, repetir_por, fuente_lista, lista_fija) VALUES
(@id_temperatura_cpu, 'MA5603T',    NULL, 10, 'config', 'display temperature 0/{slot}', 0, 'slot', 'fijo', '6,7'),
(@id_temperatura_cpu, 'MA5603T',    NULL, 11, 'config', 'display cpu 0/{slot}', 0, 'slot', 'fijo', '6,7'),
(@id_temperatura_cpu, 'MA5600T',    NULL, 10, 'config', 'display temperature 0/{slot}', 0, 'slot', 'fijo', '7,8'),
(@id_temperatura_cpu, 'MA5600T',    NULL, 11, 'config', 'display cpu 0/{slot}', 0, 'slot', 'fijo', '7,8'),
(@id_temperatura_cpu, 'MA5600T', 'OLT-LAFLORIDA-1', 10, 'config', 'display temperature 0/{slot}', 0, 'slot', 'fijo', '9,10'),
(@id_temperatura_cpu, 'MA5600T', 'OLT-LAFLORIDA-1', 11, 'config', 'display cpu 0/{slot}', 0, 'slot', 'fijo', '9,10'),
(@id_temperatura_cpu, 'MA5680T',    NULL, 10, 'config', 'display temperature 0/{slot}', 0, 'slot', 'fijo', '7,8'),
(@id_temperatura_cpu, 'MA5680T',    NULL, 11, 'config', 'display cpu 0/{slot}', 0, 'slot', 'fijo', '7,8'),
(@id_temperatura_cpu, 'MA5800-X15', NULL, 10, 'config', 'display temperature 0/{slot}', 1, 'slot', 'fijo', '8,9'),
(@id_temperatura_cpu, 'MA5800-X15', NULL, 11, 'config', 'display cpu 0/{slot}', 1, 'slot', 'fijo', '8,9');


-- ============================================================================
-- 16. uptime_gpon — tiempo de actividad (uptime) de los puertos GPON
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('uptime_gpon', 'Uptime / ultima caida de los puertos GPON', 'OLT_UPTIME', 10, 'DEFAULT', 1, 1);
SET @id_uptime_gpon = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, interface_tipo, comando_template, repetir_por, fuente_lista, lista_fija) VALUES
(@id_uptime_gpon, NULL, 10, 'interface', 'gpon', 'interface gpon 0/{slot}', 'slot', 'fijo', '0-16'),
(@id_uptime_gpon, NULL, 11, 'interface', NULL,   'display port state 0', NULL, NULL, NULL);


-- ============================================================================
-- 17. uplink_state — estado y potencia optica de puertos de uplink
--     DESACTIVADA: el PHP original (Uplink_State_PHP8) esta apagado desde el
--     24-06-2025 (die("Script Eliminado 24-06-2025")) y ademas tenia bugs
--     fatales en PHP 8 (ver PLAN_API_OLT.md, catalogo de origen). Se deja el
--     catalogo CORRECTO (sin replicar esos bugs) por si se reactiva, pero
--     activo=0: no aparece en /admin/consultas ni es ejecutable hasta que
--     el responsable decida reactivarla explicitamente.
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('uplink_state', 'Estado y potencia optica de los puertos de uplink (DESACTIVADA)', 'OLT_UPLINKS_STATE', 30, 'DEFAULT', 1, 0);
SET @id_uplink_state = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, interface_tipo, comando_template, repetir_por, fuente_lista, activo) VALUES
(@id_uplink_state, 'MA5800-X15', 10, 'interface', 'eth', 'interface eth 0/{slot}', 'slot', 'OLT_PUERTAS_UPLINKS_GB', 0),
(@id_uplink_state, NULL,         10, 'interface', 'giu', 'interface giu 0/{slot}', 'slot', 'OLT_PUERTAS_UPLINKS_GB', 0),
(@id_uplink_state, NULL,         11, 'interface', NULL,  'display port state all', NULL, NULL, 0),
(@id_uplink_state, NULL,         12, 'interface', NULL,  'display port ddm-info 0', NULL, NULL, 0),
(@id_uplink_state, NULL,         13, 'interface', NULL,  'display port ddm-info 1', NULL, NULL, 0);


-- ============================================================================
-- 18. ont_detalle — informacion detallada de ONTs (summary/optical/profile)
--     Usa el perfil de credencial ONT (usuario 'geretont' en el PHP original).
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('ont_detalle', 'Informacion detallada de ONTs por puerto GPON (summary, optica, perfil)', 'OLT_INFORMACION_ONT_DETALLE_COMPLETO', 30, 'ONT', 1, 1);
SET @id_ont_detalle = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, interface_tipo, comando_template, repetir_por, fuente_lista, lista_fija) VALUES
(@id_ont_detalle, NULL,         10, 'config',    NULL,   'display ont version 0 all', NULL, NULL, NULL),
(@id_ont_detalle, NULL,         20, 'interface', 'gpon', 'interface gpon 0/{slot}', 'slot', 'OLT_VOLTAJE_TARJETA', NULL),
(@id_ont_detalle, 'MA5800-X15', 21, 'interface', NULL,   'display ont info summary {puerto}', 'puerto', 'fijo', '0-15'),
(@id_ont_detalle, 'MA5800-X15', 22, 'interface', NULL,   'display ont optical-info {puerto} all', 'puerto', 'fijo', '0-15'),
(@id_ont_detalle, 'MA5800-X15', 23, 'interface', NULL,   'display ont profile {puerto} all', 'puerto', 'fijo', '0-15'),
(@id_ont_detalle, NULL,         21, 'interface', NULL,   'display ont info summary {puerto}', 'puerto', 'fijo', '0-7'),
(@id_ont_detalle, NULL,         22, 'interface', NULL,   'display ont optical-info {puerto} all', 'puerto', 'fijo', '0-7'),
(@id_ont_detalle, NULL,         23, 'interface', NULL,   'display ont profile {puerto} all', 'puerto', 'fijo', '0-7');
-- Filas modelo=NULL (rango 0-7) aplican a MA5600T/MA5603T/MA5680T; el
-- ejecutor las omite si el modelo es MA5800-X15 (que usa 0-15).


-- ============================================================================
-- 19. ont_reporte — conteo de ONTs online/total y version por puerto
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('ont_reporte', 'Conteo de ONTs online/total y version de software por puerto', 'OLT_ONT_DETALLE_2', 10, 'DEFAULT', 1, 1);
SET @id_ont_reporte = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_ont_reporte, NULL, 10, 'config', 'display ont version 0 all'),
(@id_ont_reporte, NULL, 11, 'config', 'display ont info 0 all');


-- ============================================================================
-- 20. version — version de software, parche y modelo del equipo
-- ============================================================================
INSERT INTO OLT_API_CONSULTAS (codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial, modo_sync_max_olts, activo)
VALUES ('version', 'Version de software, parche y modelo del equipo', 'OLT_VERSION_PARCHE_MODELO', 5, 'DEFAULT', 1, 1);
SET @id_version = LAST_INSERT_ID();

INSERT INTO OLT_API_CONSULTA_COMANDOS (consulta_id, modelo, orden, contexto, comando_template) VALUES
(@id_version, NULL, 10, 'config', 'display version');


-- ============================================================================
-- VERIFICACION (ejecutar despues de correr este script)
-- ============================================================================
-- SELECT COUNT(*) AS consultas FROM OLT_API_CONSULTAS;                 -- esperado: 20
-- SELECT COUNT(*) AS comandos  FROM OLT_API_CONSULTA_COMANDOS;
-- SELECT c.codigo, COUNT(*) AS filas_comandos
--   FROM OLT_API_CONSULTAS c
--   JOIN OLT_API_CONSULTA_COMANDOS cc ON cc.consulta_id = c.id
--   GROUP BY c.codigo ORDER BY c.codigo;

-- ============================================================================
-- RESET (ejecutar manualmente, con cuidado, solo si hay que reiniciar el
-- catalogo completo para volver a correr este script desde cero)
-- ============================================================================
-- DELETE FROM OLT_API_CONSULTA_COMANDOS;
-- DELETE FROM OLT_API_CONSULTAS;
