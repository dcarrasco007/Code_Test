<?php
/**
 * Inicialización común de las páginas web del reporte BW anual y del cron de la etapa 2
 * (contingencia_v3, servidor 120: contenedor podman con PHP 5.5).
 */
if (!defined('BW_CONEXION')) {
    // Se usa el primer archivo que exista. En contingencia_v3 la conexión está un nivel sobre el módulo
    // (raíz del sitio/conexion/conexion_db.php). En el cron (CLI) no hay DOCUMENT_ROOT: vale la primera ruta.
    $bwCandidatos = array(
        dirname(dirname(__DIR__)) . '/conexion/conexion_db.php',
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
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_IGNORE, 'UTF-8');
}

// URL relativa de las páginas del módulo (las páginas se cargan desde menu/ con $(".contenido").load)
define('BW_URL_MODULO', '../Capacidad_BW_Excel/');
