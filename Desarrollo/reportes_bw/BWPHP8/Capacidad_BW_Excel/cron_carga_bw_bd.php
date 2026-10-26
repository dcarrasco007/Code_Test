<?php
date_default_timezone_set('America/Santiago');
/**
 * Etapa 2: carga automática de capacidades BW en BD (Nacional, EQUIFIBER y ONNET).
 *
 *   --tipo=semana  -> OLT_CAPACIDAD_BW*       semana anterior        (cron lunes 04:30, después del Excel)
 *   --tipo=mes     -> OLT_CAPACIDAD_BW_MES*   mes calendario anterior (cron día 1 de cada mes)
 *   --tipo=anio    -> OLT_CAPACIDAD_BW_YEAR*  año del reporte recién cerrado. Puede quedar en el cron de
 *                     todos los lunes: solo carga cuando la semana anterior es la última del año.
 *
 * Antes de cargar cada tabla deja el Excel del período en su carpeta, igual que la subida manual:
 *   Archivos/Semana*, Mes*, Year* (sufijo _EQUIFIBER / _ONNET) con nombres Capacidades_OLTs_W-42_2026.xlsx,
 *   Capacidades_OLTs_Octubre_2026.xlsx y Capacidades_OLTs_2026.xlsx
 *
 * Opciones:
 *   --sinexcel                           carga la BD sin guardar el Excel
 *   --anio=2026 --semana=42 | --mes=10   período específico (con --tipo=anio basta --anio)
 *   --red=nacional,equifiber,onnet       redes a cargar (por defecto las 3)
 *   --simular                            no escribe en BD, solo informa
 *   --comparar                           compara lo generado con lo que ya hay en la tabla (no escribe)
 *   --csv=/ruta/carpeta                  deja lo generado en .csv para revisarlo
 *   --fuente=bd                          Nacional desde BD aunque exista el Excel
 *   --datos=prueba.json                  datos de prueba sin BD
 *
 * PHP 8.0 - estructura contingencia_v3 (módulo en /var/www/html/Capacidad_BW_Excel).
 */
if (php_sapi_name() != 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Solo ejecución por línea de comandos');
}
set_time_limit(0);
ini_set('memory_limit', '1024M');
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/control_error.log');

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
    'fuente' => isset($args['fuente']) ? $args['fuente'] : null,
    'datos' => isset($args['datos']) ? $args['datos'] : null,
    'sinexcel' => isset($args['sinexcel']),
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
