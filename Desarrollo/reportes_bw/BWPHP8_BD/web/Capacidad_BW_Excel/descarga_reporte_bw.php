<?php
/**
 * Descarga del Excel anual armado desde OLT_REPORTE_BW_ANUAL.
 *   descarga_reporte_bw.php?anio=2026&semana=42&archivo=semana  -> "Capacidades OLTs Octubre 2026 W-42.xlsx"
 *   descarga_reporte_bw.php?anio=2026&semana=42&archivo=act     -> "Capacidades OLTs Octubre 2026 Act - Act.xlsx"
 * Ambos tienen el mismo contenido: el año hasta el domingo de la semana indicada.
 * PHP 8.0.
 */
set_time_limit(300);
ini_set('memory_limit', '1024M');
require_once __DIR__ . '/lib/bw_web.php';
require_once __DIR__ . '/lib/bw_anual_excel.php';

$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
$semana = isset($_GET['semana']) ? (int)$_GET['semana'] : 0;
$archivo = (isset($_GET['archivo']) && $_GET['archivo'] == 'act') ? 'act' : 'semana';

$tmp = null;
$base = null;
try {
    if ($anio < 2000 || $semana < 1 || $semana > bw_semanas_del_anio($anio)) {
        throw new Exception('Semana inválida');
    }
    $mysqli = bw_conectar_bd();
    $disponible = false;
    foreach (bw_anual_semanas($mysqli) as $s) {
        if ($s[0] == $anio && $s[1] == $semana) {
            $disponible = true;
        }
    }
    if (!$disponible) {
        throw new Exception("La semana W-$semana $anio no está cargada");
    }
    $base = tempnam(sys_get_temp_dir(), 'bw');
    $tmp = $base . '.xlsx';
    bw_anual_generar_excel($mysqli, $anio, $semana, $tmp);
    $mysqli->close();

    $nombres = bw_nombres_archivo($anio, $semana);
    $nombre = $nombres[$archivo];
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombre . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
} catch (Exception $e) {
    header('HTTP/1.0 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No se pudo generar el Excel: ' . $e->getMessage();
}
foreach (array($tmp, $base) as $f) {
    if ($f && file_exists($f)) {
        unlink($f);
    }
}
