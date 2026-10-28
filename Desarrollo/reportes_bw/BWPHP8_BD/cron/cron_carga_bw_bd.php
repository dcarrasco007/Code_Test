<?php
date_default_timezone_set('America/Santiago');
/**
 * Etapa 2 (servidor de cron, solo BD): carga de capacidades BW en las tablas de Nacional, EQUIFIBER y ONNET.
 * Nacional se arma desde OLT_REPORTE_BW_ANUAL (etapa 1); EQUIFIBER y ONNET desde OLT_TRAFICO_PEAK_DAY.
 *
 *   --tipo=semana  -> OLT_CAPACIDAD_BW*       semana anterior        (lunes 04:30, después de la etapa 1)
 *   --tipo=mes     -> OLT_CAPACIDAD_BW_MES*   mes calendario anterior (día 1 de cada mes)
 *   --tipo=anio    -> OLT_CAPACIDAD_BW_YEAR*  año del reporte recién cerrado. Puede quedar en el cron de
 *                     todos los lunes: solo carga cuando la semana anterior es la última del año.
 *
 * Opciones:
 *   --anio=2026 --semana=42 | --mes=10   período específico (con --tipo=anio basta --anio)
 *   --red=nacional,equifiber,onnet       redes a cargar (por defecto las 3)
 *   --simular                            no escribe en BD, solo informa
 *   --comparar                           compara lo generado con lo que ya hay en la tabla (no escribe)
 *   --csv=/ruta/carpeta                  deja lo generado en .csv para revisarlo
 *   --datos=prueba.json                  OLTs y tráfico de prueba en vez de OLT_SERVER / OLT_TRAFICO_PEAK_DAY
 *
 * Cron (no usar "php -f archivo --tipo=..."):
 *   30 4 * * 1 php /var/www/procesos/php/BWPHP8/cron_carga_bw_bd.php --tipo=semana > /var/www/procesos/php/BWPHP8/logs/cron_carga_semana.log 2>&1
 *   45 4 * * 1 php /var/www/procesos/php/BWPHP8/cron_carga_bw_bd.php --tipo=anio   > /var/www/procesos/php/BWPHP8/logs/cron_carga_anio.log 2>&1
 *   0 5 1 * *  php /var/www/procesos/php/BWPHP8/cron_carga_bw_bd.php --tipo=mes    > /var/www/procesos/php/BWPHP8/logs/cron_carga_mes.log 2>&1
 *
 * Compatible con PHP 5.3+ y 8.0 (121 procesos: 8.0, 120 contenedor v3: 5.5, 181 contingencia: 5.3).
 */
if (php_sapi_name() != 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Solo ejecución por línea de comandos');
}
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/control_error.log');

// Conexión del servidor de procesos PHP 8 (misma convención que cron_reporte_bw_anual.php)
if (!defined('BW_CONEXION')) {
    define('BW_CONEXION', '/var/www/procesos/php/conexion/conexion_db.php');
}
if (!defined('BW_LOG')) {
    define('BW_LOG', __DIR__ . '/logs/log_carga_bw_bd.log');
}
require_once __DIR__ . '/lib/bw_carga_bd.php';

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}

$tipo = isset($args['tipo']) ? $args['tipo'] : 'semana';
$redes = isset($args['red']) ? explode(',', strtolower($args['red'])) : array_keys(bw_redes());
$opc = array(
    'simular' => isset($args['simular']),
    'comparar' => isset($args['comparar']),
    'csv' => isset($args['csv']) ? $args['csv'] : null,
    'datos' => isset($args['datos']) ? $args['datos'] : null,
);

try {
    if ($tipo == 'semana') {
        $anterior = bw_semana_anterior();
        $anio = isset($args['anio']) ? (int)$args['anio'] : $anterior[0];
        $num = isset($args['semana']) ? (int)$args['semana'] : $anterior[1];
    } elseif ($tipo == 'mes') {
        $mesAnterior = mktime(12, 0, 0, (int)date('n') - 1, 1, (int)date('Y'));
        $anio = isset($args['anio']) ? (int)$args['anio'] : (int)date('Y', $mesAnterior);
        $num = isset($args['mes']) ? (int)$args['mes'] : (int)date('n', $mesAnterior);
    } elseif ($tipo == 'anio') {
        $num = null;
        if (isset($args['anio'])) {
            $anio = (int)$args['anio'];
        } else {
            $anterior = bw_semana_anterior();
            if ($anterior[1] != bw_semanas_del_anio($anterior[0])) {
                bw_log("[BD] Carga anual: la semana anterior (W-$anterior[1] $anterior[0]) no cierra el año, no corresponde cargar");
                exit(0);
            }
            $anio = $anterior[0];
        }
    } else {
        throw new Exception("--tipo debe ser semana, mes o anio");
    }
    bw_cargar_bd($tipo, $anio, $num, $redes, $opc);
    exit(0);
} catch (Exception $e) {
    bw_log('[BD] ERROR carga ' . $tipo . ': ' . $e->getMessage());
    exit(1);
}
