<?php
/**
 * Fragmento HTML con los botones de descarga y las alertas de una semana (calculadas desde
 * OLT_REPORTE_BW_ANUAL). Lo carga index_reporte_bw_semanal.php al cambiar la semana.
 *   alertas_reporte_bw.php?anio=2026&semana=42
 * PHP 8.0.
 */
require_once __DIR__ . '/lib/bw_web.php';

$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : 0;
$semana = isset($_GET['semana']) ? (int)$_GET['semana'] : 0;
if ($anio < 2000 || $semana < 1 || $semana > bw_semanas_del_anio($anio)) {
    echo '<center>Semana inválida.</center>';
    return;
}
try {
    $mysqli = bw_conectar_bd();
    $al = bw_anual_alertas($mysqli, $anio, $semana);
    $mysqli->close();
} catch (Exception $e) {
    echo '<center>Error al leer las alertas: ' . bw_h($e->getMessage()) . '</center>';
    return;
}
$nombres = bw_nombres_archivo($anio, $semana);
$desde = date('d-m-Y', bw_ts_dia($anio, ($semana - 1) * 7));
$hasta = date('d-m-Y', bw_ts_dia($anio, ($semana - 1) * 7 + 6));
$url = BW_URL_MODULO . 'descarga_reporte_bw.php?anio=' . $anio . '&semana=' . $semana . '&archivo=';
$hayFallas = count($al['uplink']) + count($al['sin_registro']) > 0;
$total = count($al['uplink']) + count($al['sin_registro']) + count($al['olt_nueva']) + count($al['capacidad']);
?>
<div class="text-center bw-botones">
    <a class="btn btn-primary" href="<?php echo bw_h($url . 'semana'); ?>">
        <span class="glyphicon glyphicon-download-alt"></span> <?php echo bw_h($nombres['semana']); ?></a>
    <a class="btn btn-primary" href="<?php echo bw_h($url . 'act'); ?>">
        <span class="glyphicon glyphicon-download-alt"></span> <?php echo bw_h($nombres['act']); ?></a>
    <div><small>El Excel se arma al momento con los datos de la BD (puede tardar unos segundos).</small></div>
</div>

<div class="bw-alertas">
    <div class="panel <?php echo $hayFallas ? 'panel-danger' : 'panel-success'; ?>">
        <div class="panel-heading"><strong>Alertas de la semana W-<?php echo $semana; ?> (<?php echo $desde; ?> al <?php echo $hasta; ?>)</strong></div>
        <div class="panel-body">
<?php if ($total == 0) { ?>
            Sin alertas: ninguna OLT superó el <?php echo (int)BW_UMBRAL_ALERTA; ?>% de Uplink y todas tienen registros.
<?php } ?>
<?php if (count($al['uplink']) > 0) { ?>
            <h4>% Uplink igual o superior al <?php echo (int)BW_UMBRAL_ALERTA; ?>%</h4>
            <table class="table table-bordered table-condensed table-striped">
                <thead><tr><th>Día</th><th>PE-com</th><th>OLT</th><th>Tráfico (Mbps)</th><th>Capacidad (Mbps)</th><th>% Uplink</th><th>Celda Excel</th></tr></thead>
                <tbody>
<?php foreach ($al['uplink'] as $a) { ?>
                    <tr><td><?php echo bw_h($a['dia']); ?></td><td><?php echo bw_h($a['pe_com']); ?></td><td><?php echo bw_h($a['olt']); ?></td>
                        <td><?php echo number_format($a['trafico'], 0, ',', '.'); ?></td><td><?php echo number_format($a['capacidad'], 0, ',', '.'); ?></td>
                        <td class="bw-pct"><?php echo number_format($a['porcentaje'], 2, ',', '.'); ?>%</td><td><?php echo bw_h($a['celda']); ?></td></tr>
<?php } ?>
                </tbody>
            </table>
<?php } ?>
<?php if (count($al['sin_registro']) > 0) { ?>
            <h4>OLTs sin registro de tráfico</h4>
            <table class="table table-bordered table-condensed table-striped">
                <thead><tr><th>PE-com</th><th>OLT</th><th>Fila Excel</th><th>Detalle</th></tr></thead>
                <tbody>
<?php foreach ($al['sin_registro'] as $a) { ?>
                    <tr><td><?php echo bw_h($a['pe_com']); ?></td><td><?php echo bw_h($a['olt']); ?></td><td><?php echo (int)$a['fila']; ?></td><td><?php echo bw_h($a['detalle']); ?></td></tr>
<?php } ?>
                </tbody>
            </table>
<?php } ?>
<?php if (count($al['capacidad']) > 0) { ?>
            <h4>Cambios de capacidad respecto de la semana anterior</h4>
            <table class="table table-bordered table-condensed table-striped">
                <thead><tr><th>PE-com</th><th>OLT</th><th>Fila Excel</th><th>Capacidad anterior (Mbps)</th><th>Capacidad nueva (Mbps)</th></tr></thead>
                <tbody>
<?php foreach ($al['capacidad'] as $a) { ?>
                    <tr><td><?php echo bw_h($a['pe_com']); ?></td><td><?php echo bw_h($a['olt']); ?></td><td><?php echo (int)$a['fila']; ?></td>
                        <td><?php echo number_format($a['anterior'], 0, ',', '.'); ?></td><td><?php echo number_format($a['nueva'], 0, ',', '.'); ?></td></tr>
<?php } ?>
                </tbody>
            </table>
<?php } ?>
<?php if (count($al['olt_nueva']) > 0) { ?>
            <h4>OLTs nuevas agregadas al reporte</h4>
            <ul>
<?php foreach ($al['olt_nueva'] as $a) { ?>
                <li><strong><?php echo bw_h($a['olt']); ?></strong> (<?php echo bw_h($a['pe_com']); ?>): <?php echo bw_h($a['detalle']); ?></li>
<?php } ?>
            </ul>
<?php } ?>
        </div>
    </div>
</div>
