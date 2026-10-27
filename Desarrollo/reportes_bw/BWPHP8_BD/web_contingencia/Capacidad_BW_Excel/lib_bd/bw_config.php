<?php
/**
 * Configuración del reporte BW anual guardado en BD (tabla OLT_REPORTE_BW_ANUAL).
 * La misma carpeta lib/ se usa en el servidor del cron (solo BD) y en el servidor web
 * (arma el Excel al descargar). PHP 8.0 / portal de contingencia PHP 5.3+.
 *
 * BW_CONEXION y BW_LOG los define el script de entrada antes de incluir este archivo:
 *   cron: /var/www/procesos/php/conexion/conexion_db.php
 *   web : /var/www/html/conexion/conexion_db.php (contingencia_v3)
 *   web : contingencia/conexion/conexion_db.php (portal OLT de contingencia, ver lib_bd/bw_web.php)
 */
date_default_timezone_set('America/Santiago');

// Base de datos y tabla
define('BW_BD', 'Aden');
define('BW_TABLA_ANUAL', 'OLT_REPORTE_BW_ANUAL');

// % Uplink desde el cual se genera alerta
define('BW_UMBRAL_ALERTA', 99);

// Nombre base de los archivos Excel
define('BW_PREFIJO_ARCHIVO', 'Capacidades OLTs');

/**
 * OLTs que no se incluyen en el reporte (laboratorio). Para volver a considerar una,
 * basta con borrarla o comentarla de esta lista: la próxima ejecución la agrega.
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
    if (defined('BW_LOG')) {
        @file_put_contents(BW_LOG, $linea, FILE_APPEND);
    }
    if (php_sapi_name() == 'cli') {
        echo $linea;
    }
}
