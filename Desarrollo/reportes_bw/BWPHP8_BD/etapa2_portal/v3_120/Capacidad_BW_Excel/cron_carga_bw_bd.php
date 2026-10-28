<?php
date_default_timezone_set('America/Santiago');
/**
 * Etapa 2 en el servidor 120 (portal OLT dentro del contenedor podman): carga de capacidades BW en las
 * tablas de Nacional, EQUIFIBER y ONNET y, antes de cada carga, deja el Excel del período en Archivos/
 * (Semana*, Mes*, Year*), igual que la subida manual. En el servidor 181 el link "Descargar Archivo"
 * (descargar_excel_bw.php) arma ese Excel desde la BD si no lo tiene.
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
 *   --simular                            no escribe en BD ni deja Excel, solo informa
 *   --comparar                           compara lo generado con lo que ya hay en la tabla (no escribe)
 *   --sinexcel                           carga la BD sin dejar el Excel en Archivos/
 *   --csv=/ruta/carpeta                  deja lo generado en .csv para revisarlo
 *
 * Se ejecuta desde el crontab del host 120 con podman exec (el log queda en el host, fuera de la web):
 *   30 4 * * 1 /usr/bin/podman exec <contenedor> php <modulo>/cron_carga_bw_bd.php --tipo=semana >> <log> 2>&1
 *
 * Servidor 120: contenedor podman con PHP 5.5 (contingencia_v3).
 */
if (php_sapi_name() != 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Solo ejecución por línea de comandos');
}
set_time_limit(0);
ini_set('memory_limit', '1024M');

// Conexión del portal (lib/bw_web.php busca conexion/conexion_db.php) y carpeta de los Excel
define('BW_DIR_ARCHIVOS', __DIR__ . '/Archivos');
require_once __DIR__ . '/lib/bw_web.php';
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
    'sinexcel' => isset($args['sinexcel']),
    'csv' => isset($args['csv']) ? $args['csv'] : null,
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
