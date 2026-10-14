<?php
/**
 * Configuración del reporte semanal de capacidades BW (Excel anual por semana ISO).
 * Compatible con PHP 5.3.
 */
if (!ini_get('date.timezone')) {
    date_default_timezone_set('America/Santiago');
}

// Carpeta del módulo (Capacidad_BW_Excel)
define('BW_DIR_MODULO', dirname(dirname(__FILE__)));

// Carpeta donde se dejan los Excel generados, sus alertas (.json) y el log
define('BW_DIR_SEMANA', BW_DIR_MODULO . '/Archivos/Semana');

// Archivo de conexión con $host144_geret, $user144_geret, $pass144_geret
define('BW_CONEXION', BW_DIR_MODULO . '/../../conexion/conexion_db.php');

// Base de datos
define('BW_BD', 'Aden');

// % Uplink desde el cual se genera alerta
define('BW_UMBRAL_ALERTA', 99);

// Nombre base de los archivos
define('BW_PREFIJO_ARCHIVO', 'Capacidades OLTs');

define('BW_LOG', BW_DIR_SEMANA . '/log_reporte_bw_semanal.log');

function bw_log($mensaje)
{
    $linea = date('Y-m-d H:i:s') . ' ' . $mensaje . "\n";
    @file_put_contents(BW_LOG, $linea, FILE_APPEND);
    if (php_sapi_name() == 'cli') {
        echo $linea;
    }
}
