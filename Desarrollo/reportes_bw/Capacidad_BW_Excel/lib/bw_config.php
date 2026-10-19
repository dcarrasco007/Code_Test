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
if (!defined('BW_CONEXION')) {
    define('BW_CONEXION', BW_DIR_MODULO . '/../../conexion/conexion_db.php');
}

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

/**
 * OLTs de ONNET (misma lista que OLT/Reporte_BW/reporte_bw_fecha_peak_capacidad_onnet.php).
 * Para agregar o quitar una OLT de ONNET basta con editar esta lista.
 */
function bw_olts_onnet()
{
    return array(
        'OLT-QUILICURA-1',
        'OLT-HUECHURABA-2',
        'OLT-ZOFRI-1',
        'OLT-PUERTOVARAS-1',
        'OLT-IQUIQUE-2',
        'OLT-CALLEJONDELOSPERROS2PCS-1',
        'OLT-CALLELIRCAYPCS-1',
        'OLT-LOSANGELES-1',
        'OLT-OSORNO-1',
        'OLT-ANFIONMUNOZ-1',
        'OLT-CARDONAL-1',
        'OLT-CLUBHIPICO-1',
        'OLT-LAUTARO-1',
        'OLT-NVASANANTONIO-1',
        'OLT-SANTAJUANAPCS-1',
        'OLT-VALDIVIA-1',
        'OLT-COPIAPO-1',
        'OLT-CURICO-1',
        'OLT-MALLTOBALABAPCS-1',
        'OLT-3PONIENTEPCS-1',
        'OLT-LASPERDICESPCS-1',
        'OLT-ROJASMAGALLANESPCS-1',
        'OLT-NUNOA-3',
        'OLT-LOSJARDINESPCS-1',
        'OLT-MCCDLVALLEPCS-1',
        'OLT-RECREO-3',
        'OLT-NVAARLEGUI-1',
        'OLT-NVA13NORTE-3',
        'OLT-LAFLORIDA-2',
        'OLT-GRANAVENIDAPCS-2',
        'OLT-TEMPLOVOTIVOPCS-2',
        'OLT-AVPDTEBATLLEPCS-2',
        'OLT-GRANAVENIDAPCS-1',
        'OLT-QUILICURA2PCS-1',
        'OLT-SANJOSEDELAESTRELLAPCS-1',
        'OLT-MAIPU-3',
        'OLT-ROJASMAGALLANESPCS-2',
        'OLT-CHICUREO3PCS-1',
        'OLT-LOSQUILLAYESPCS-1',
        'OLT-QUILICURA-2',
        'OLT-RENACANORTEPCS-1',
        'OLT-GRALBONILLAPCS-2',
        'OLT-TEMPLOVOTIVOPCS-3',
        'OLT-CVELBOSQUEPCS-1',
        'OLT-LAFARFANAPCS-2',
        'OLT-QUILPUEPCS-1',
        'OLT-ELENCUENTROPCS-2',
        'OLT-DRRENEANZIETAPCS-2',
        'OLT-VILLAALEMANA-1',
        'OLT-VILLAALEMANA-2',
        'OLT-QUILPUEPCS-2',
        'OLT-QUILPUEPCS-3',
        'OLT-TEMUCO-2',
        'OLT-NVACURAUMA-1',
        'OLT-NUNOA-4',
        'OLT-CLAMONTANAPCS-1',
        'OLT-ANTOFAGASTA-2',
        'OLT-CHILLAN-2',
        'OLT-LOSJARDINESPCS-2',
        'OLT-GRANAVENIDAPCS-3',
        'OLT-COLON-2',
        'OLT-PUENTEALTO-2',
        'OLT-LASERENA-1',
        'OLT-SANTAELENA-2',
        'OLT-SANTAELENA-3',
        'OLT-CONCEPCION-3',
        'OLT-CONCEPCION-4',
        'OLT-BOMBEROSCOLLAO-1',
        'OLT-HERNANCIUDADPCS-2',
        'OLT-HERNANCIUDADPCS-3',
        'OLT-RANCAGUA-3',
        'OLT-RANCAGUA-4',
        'OLT-COQUIMBO-2',
        'OLT-ALTOPENUELAS-2',
        'OLT-ESTRELLADELSUR-1',
        'OLT-PADREHURTADOPCS-1',
        'OLT-GRALBONILLAPCS-3',
        'OLT-CINCOPINOSPCS-1',
        'OLT-TEMUCOPONIENTEPCS-1',
        'OLT-CVELBOSQUEPCS-2',
        'OLT-TALCASWPCS-1',
        'OLT-TALCASWPCS-2',
        'OLT-NUNOA-5',
        'OLT-AVPDTEBATLLEPCS-3',
        'OLT-VALPARAISO-2',
        'OLT-AVDELMARPCS-1',
        'OLT-AVDELMARPCS-2',
        'OLT-SSBERNARDOPCS-1',
        'OLT-LCARRERA-3',
        'OLT-BUIN-1',
        'OLT-INDEPENDENCIA-1',
        'OLT-NVATALCAHUANO-1',
        'OLT-LOSLIRIOSPCS-1',
        'OLT-LOSLIRIOSPCS-2',
        'OLT-TOME2PCS-2',
        'OLT-AVDACOLONPCS-1',
        'OLT-PENCOPCS-2',
        'OLT-TALCAORIENTEPCS-1',
        'OLT-COQUIMBO-3',
        'OLT-SANPEDRO3PCS-2',
        'OLT-NVA13NORTE-4',
        'OLT-PAINE-1',
        'OLT-AORIENTALNORPCS-1',
        'OLT-LAFARFANAPCS-3',
        'OLT-IQUIQUE-1',
    );
}

// Tablas destino y origen de OLTs por red
function bw_redes()
{
    return array(
        'nacional'  => array('nombre' => 'Nacional', 'sufijo' => '', 'tabla_server' => 'OLT_SERVER'),
        'equifiber' => array('nombre' => 'EQUIFIBER', 'sufijo' => '_EQUIFIBER', 'tabla_server' => 'OLT_SERVER_V2'),
        'onnet'     => array('nombre' => 'ONNET', 'sufijo' => '_ONNET', 'tabla_server' => 'OLT_SERVER'),
    );
}

// Cantidad de filas del Excel que caben en las tablas OLT_CAPACIDAD_BW* (campos `1`..`162`)
define('BW_FILAS_TABLA', 162);
