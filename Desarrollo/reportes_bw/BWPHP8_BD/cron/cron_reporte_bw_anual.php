<?php
date_default_timezone_set('America/Santiago');
/**
 * Etapa 1 (servidor de cron, solo BD): agrega a OLT_REPORTE_BW_ANUAL los 7 días de la semana anterior
 * (y los días que falten si una ejecución anterior no corrió). No lee ni genera Excel: el Excel lo arma
 * la ventana web desde la tabla al presionar el botón de descarga.
 *
 * Uso:
 *   php /var/www/procesos/php/BWPHP8/cron_reporte_bw_anual.php                      -> semana anterior a hoy
 *   php /var/www/procesos/php/BWPHP8/cron_reporte_bw_anual.php --anio=2026 --semana=43 [--forzar]
 *   php /var/www/procesos/php/BWPHP8/cron_reporte_bw_anual.php --simular            -> no guarda (rollback)
 *   php /var/www/procesos/php/BWPHP8/cron_reporte_bw_anual.php --datos=prueba.json  -> datos de prueba sin BD de tráfico
 *
 * Cron (lunes 04:00):
 *   0 4 * * 1 php /var/www/procesos/php/BWPHP8/cron_reporte_bw_anual.php > /var/www/procesos/php/BWPHP8/logs/cron.log 2>&1
 * No usar "php -f archivo --anio=..." (PHP toma --anio como opción propia).
 *
 * PHP 8.0.
 */
if (php_sapi_name() != 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Solo ejecución por línea de comandos');
}
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/control_error.log');

// Conexión del servidor de procesos PHP 8 (misma convención que Alarmas_PHP8, Tarjetas_PHP8, etc.)
if (!defined('BW_CONEXION')) {
    define('BW_CONEXION', '/var/www/procesos/php/conexion/conexion_db.php');
}
if (!defined('BW_LOG')) {
    define('BW_LOG', __DIR__ . '/logs/log_reporte_bw_anual.log');
}
require_once __DIR__ . '/lib/bw_anual.php';

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}
$anterior = bw_semana_anterior();
$anio = isset($args['anio']) ? (int)$args['anio'] : $anterior[0];
$semana = isset($args['semana']) ? (int)$args['semana'] : $anterior[1];

try {
    bw_anual_generar_semana($anio, $semana, array(
        'forzar' => isset($args['forzar']),
        'simular' => isset($args['simular']),
        'datos' => isset($args['datos']) ? $args['datos'] : null,
    ));
    exit(0);
} catch (Exception $e) {
    bw_log("ERROR W-$semana $anio: " . $e->getMessage());
    exit(1);
}
