<?php
/**
 * Inicialización común de las páginas web del reporte BW anual (portal OLT de contingencia, PHP 5.3+).
 * Módulo: contingencia/OLT/Capacidad_BW_Excel (librerías en lib_bd/ para no pisar lib/ del cron antiguo).
 */
if (!defined('BW_CONEXION')) {
    // Se usa el primer archivo que exista. La conexión del portal está en contingencia/conexion/conexion_db.php
    // (dos niveles sobre el módulo, igual que los index_reporte_excel*.php: ../../conexion/conexion_db.php).
    $bwModulo = dirname(dirname(__FILE__));
    $bwCandidatos = array(
        $bwModulo . '/../../conexion/conexion_db.php',   // contingencia/conexion
        $bwModulo . '/../conexion/conexion_db.php',      // contingencia/OLT/conexion
    );
    $bwConexion = $bwCandidatos[0];
    foreach ($bwCandidatos as $c) {
        if (is_file($c)) {
            $bwConexion = $c;
            break;
        }
    }
    define('BW_CONEXION', $bwConexion);
}
require_once dirname(__FILE__) . '/bw_anual.php';

function bw_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_IGNORE, 'UTF-8');
}

// URL relativa de las páginas del módulo (las páginas se cargan desde OLT/menu/ con $(".contenido").load)
define('BW_URL_MODULO', '../Capacidad_BW_Excel/');
