<?php
/**
 * Inicialización común de las páginas web del reporte BW anual (contingencia_v3, PHP 8.0).
 */
if (!defined('BW_CONEXION')) {
    // Se usa el primer archivo que exista. En contingencia_v3 el módulo está en /var/www/html/Capacidad_BW_Excel
    // y la conexión en /var/www/html/conexion/conexion_db.php (un nivel sobre el módulo).
    $bwCandidatos = array(
        dirname(__DIR__, 2) . '/conexion/conexion_db.php',
        (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') : '') . '/conexion/conexion_db.php',
        '/var/www/html/conexion/conexion_db.php',
        '/var/www/conexion/conexion_db.php',
    );
    $bwConexion = $bwCandidatos[0];
    foreach ($bwCandidatos as $c) {
        if ($c != '/conexion/conexion_db.php' && is_file($c)) {
            $bwConexion = $c;
            break;
        }
    }
    define('BW_CONEXION', $bwConexion);
}
require_once __DIR__ . '/bw_anual.php';

function bw_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// URL relativa de las páginas del módulo (las páginas se cargan desde menu/ con $(".contenido").load)
define('BW_URL_MODULO', '../Capacidad_BW_Excel/');
