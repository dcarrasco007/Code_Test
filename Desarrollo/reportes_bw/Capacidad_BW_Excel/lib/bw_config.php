<?php
/**
 * Configuración del reporte semanal de capacidades BW (Excel anual por semana, ver lib/bw_fechas.php).
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

/**
 * OLTs que no se incluyen en el reporte (laboratorio). Para volver a considerar una,
 * basta con borrarla o comentarla de esta lista: la próxima ejecución la agrega al Excel.
 */
function bw_olts_excluidas()
{
    return array(
        'OLT1-1-LABONNET-5800',     // laboratorio
        'OLT1-2-LABONNET-5600',     // laboratorio
        'OLT61-LASERII-HW5800',     // laboratorio
        'OLT02-LASERII-HW5600',     // laboratorio
        'OLT62-LASERII-HW5800T',    // laboratorio
    );
}

function bw_log($mensaje)
{
    $linea = date('Y-m-d H:i:s') . ' ' . $mensaje . "\n";
    @file_put_contents(BW_LOG, $linea, FILE_APPEND);
    if (php_sapi_name() == 'cli') {
        echo $linea;
    }
}
