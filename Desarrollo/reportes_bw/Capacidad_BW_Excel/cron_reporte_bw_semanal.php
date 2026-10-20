<?php
/**
 * Cron semanal (lunes 04:00): genera los Excel de capacidades BW de la semana anterior (semana 1 = la que contiene el 1 de enero)
 * en Archivos_Auto/Semana/ (BW_DIR_SEMANA) junto con sus alertas. La etapa 2 deja sus Excel en Archivos/.
 *
 * Uso:
 *   php cron_reporte_bw_semanal.php                       -> semana anterior a hoy
 *   php cron_reporte_bw_semanal.php --anio=2026 --semana=42 [--forzar]
 *   php cron_reporte_bw_semanal.php --datos=prueba.json   -> datos de prueba sin BD
 *   php cron_reporte_bw_semanal.php --plantilla=ruta.xlsx -> base distinta a la automática
 *
 * Compatible con PHP 5.3.
 */
if (php_sapi_name() != 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Solo ejecución por línea de comandos');
}
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('log_errors', 1);
ini_set('error_log', dirname(__FILE__) . '/control_error.log');

require_once dirname(__FILE__) . '/lib/bw_reporte.php';

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
    $res = bw_generar_reporte_semanal($anio, $semana, array(
        'forzar' => isset($args['forzar']),
        'datos' => isset($args['datos']) ? $args['datos'] : null,
        'plantilla' => isset($args['plantilla']) ? $args['plantilla'] : null,
    ));
    exit(0);
} catch (Exception $e) {
    bw_log('ERROR W-' . $semana . ' ' . $anio . ': ' . $e->getMessage());
    exit(1);
}
