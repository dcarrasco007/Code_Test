<?php
/**
 * Inicialización común de las páginas web del reporte BW anual (contingencia_v3, PHP 8.0).
 */
if (!defined('BW_CONEXION')) {
    // /var/www/html/Capacidad_BW_Excel/lib -> /var/www/html/conexion/conexion_db.php
    define('BW_CONEXION', dirname(__DIR__, 2) . '/conexion/conexion_db.php');
}
require_once __DIR__ . '/bw_anual.php';

function bw_h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// URL relativa de las páginas del módulo (las páginas se cargan desde menu/ con $(".contenido").load)
define('BW_URL_MODULO', '../Capacidad_BW_Excel/');
