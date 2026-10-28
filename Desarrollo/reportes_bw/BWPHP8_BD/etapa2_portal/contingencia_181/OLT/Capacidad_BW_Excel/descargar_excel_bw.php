<?php
/**
 * "Descargar Archivo" de los reportes de capacidad BW (tabla_reporte_excel*.php), en los dos portales:
 *   - Si el Excel está en su carpeta de Archivos/ (subida manual, o el cron de la etapa 2 en el servidor 120),
 *     se entrega ese archivo.
 *   - Si no está (ej: servidor 181, o el archivo es anterior a la última carga del período), se arma desde
 *     OLT_CAPACIDAD_BW* en BD, se guarda en la carpeta (así el servidor también lo tiene) y se entrega.
 *
 *   descargar_excel_bw.php?tipo=semana&red=nacional&anio=2026&num=42
 *   descargar_excel_bw.php?tipo=mes&red=onnet&anio=2026&num=09
 *   descargar_excel_bw.php?tipo=anio&red=equifiber&anio=2026
 *
 * Usa las librerías de lib_bd/ (portal de contingencia) o lib/ (contingencia_v3). PHP 5.3+ y 8.0.
 */
set_time_limit(300);
ini_set('memory_limit', '1024M');
$bwLib = is_dir(dirname(__FILE__) . '/lib_bd') ? dirname(__FILE__) . '/lib_bd' : dirname(__FILE__) . '/lib';
require_once $bwLib . '/bw_web.php';
require_once $bwLib . '/bw_excel_periodo.php';

// Si el archivo es anterior a la última carga del período en BD (menos este margen), se vuelve a armar
define('BW_MARGEN_VIGENCIA', 3600);

$tablas = array('semana' => 'OLT_CAPACIDAD_BW', 'mes' => 'OLT_CAPACIDAD_BW_MES', 'anio' => 'OLT_CAPACIDAD_BW_YEAR');
$sufijos = array('nacional' => '', 'equifiber' => '_EQUIFIBER', 'onnet' => '_ONNET');
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$red = isset($_GET['red']) ? strtolower($_GET['red']) : 'nacional';
$anio = isset($_GET['anio']) ? trim($_GET['anio']) : '';
$num = isset($_GET['num']) ? trim($_GET['num']) : '';

function bw_descarga_error($mensaje)
{
    header('HTTP/1.0 404 Not Found');
    header('Content-Type: text/html; charset=utf-8');
    echo '<p style="font-family:sans-serif;margin:30px">No se pudo descargar el archivo: ' . bw_h($mensaje)
        . '</p><p style="font-family:sans-serif;margin:30px"><a href="javascript:history.back()">Volver</a></p>';
    exit;
}

function bw_descarga_enviar($ruta, $nombre)
{
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: no-store');
    readfile($ruta);
}

if (!isset($tablas[$tipo]) || !isset($sufijos[$red]) || !preg_match('/^\d{4}$/', $anio)
    || ($tipo != 'anio' && !preg_match('/^\d{1,2}$/', $num))) {
    bw_descarga_error('parámetros inválidos');
}
$tabla = $tablas[$tipo] . $sufijos[$red];
list($carpeta, $nombre) = bw_excel_ubicacion($tipo, $red, $anio, $num);
$dir = dirname(__FILE__) . '/Archivos/' . $carpeta;
$ruta = $dir . '/' . $nombre;

$tmp = null;
try {
    $mysqli = bw_conectar_bd();
    // Mismo filtro que tabla_reporte_excel*.php (el valor tal como viene: '42', '09', ...)
    if ($tipo == 'semana') {
        $where = "week = '" . $mysqli->real_escape_string($num) . "'";
    } elseif ($tipo == 'mes') {
        $where = "mes = '" . $mysqli->real_escape_string($num) . "'";
    } else {
        $where = '1 = 1';
    }
    $where .= " AND `year` = '" . $mysqli->real_escape_string($anio) . "'";
    $res = bw_anual_q($mysqli, "SELECT MAX(fecha) FROM `$tabla` WHERE $where");
    $fila = $res->fetch_row();
    $cargado = ($fila && $fila[0]) ? strtotime($fila[0]) : null;

    // Archivo en el servidor y vigente: se entrega tal cual
    if (is_file($ruta) && ($cargado === null || filemtime($ruta) >= $cargado - BW_MARGEN_VIGENCIA)) {
        $mysqli->close();
        bw_descarga_enviar($ruta, $nombre);
        exit;
    }
    if ($cargado === null) {
        $mysqli->close();
        bw_descarga_error("no existe $nombre en el servidor ni hay datos del período en $tabla");
    }

    // Se arma desde la tabla
    $campos = array();
    for ($i = 1; $i <= 162; $i++) {
        $campos[] = '`' . $i . '`';
    }
    $res = bw_anual_q($mysqli, 'SELECT ' . implode(', ', $campos) . " FROM `$tabla` WHERE $where ORDER BY id");
    $columnas = array();
    while ($r = $res->fetch_row()) {
        $columnas[] = $r;
    }
    $mysqli->close();

    $base = tempnam(sys_get_temp_dir(), 'bw');
    $tmp = $base . '.xlsx';
    @unlink($base);
    bw_excel_desde_columnas($columnas, $tmp, $anio);

    // Se deja una copia en la carpeta del servidor (si no se puede escribir, igual se descarga)
    if ((is_dir($dir) || @mkdir($dir, 0775, true)) && @copy($tmp, $ruta)) {
        @chmod($ruta, 0666);
    }
    bw_descarga_enviar($tmp, $nombre);
} catch (Exception $e) {
    if ($tmp && file_exists($tmp)) {
        unlink($tmp);
    }
    bw_descarga_error($e->getMessage());
}
if ($tmp && file_exists($tmp)) {
    unlink($tmp);
}
