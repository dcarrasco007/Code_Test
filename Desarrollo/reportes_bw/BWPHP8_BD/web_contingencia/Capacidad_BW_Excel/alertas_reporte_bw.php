<?php
/**
 * Fragmento HTML con los botones de descarga y las alertas de una semana (calculadas desde
 * OLT_REPORTE_BW_ANUAL). Lo carga index_reporte_bw_semanal.php al cambiar la semana.
 *   alertas_reporte_bw.php?anio=2026&semana=42
 * Portal OLT de contingencia (PHP 5.3+).
 */
require_once __DIR__ . '/lib_bd/bw_web.php';

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
<div class="bw-descargas">
<?php foreach (array('semana', 'act') as $tipo) { ?>
    <a class="bw-dl" href="<?php echo bw_h($url . $tipo); ?>" data-nombre="<?php echo bw_h($nombres[$tipo]); ?>">
        <span class="bw-dl-icono">
            <svg class="bw-ico-xls" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="currentColor" opacity=".18"/><path d="M14 2v6h6M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8.5 12.5l4 5M12.5 12.5l-4 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            <span class="bw-spinner"></span>
            <svg class="bw-ico-ok" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
        <span class="bw-dl-texto">
            <span class="bw-dl-nombre"><?php echo bw_h($nombres[$tipo]); ?></span>
            <span class="bw-dl-estado">Descargar Excel</span>
        </span>
        <span class="bw-dl-barra"></span>
    </a>
<?php } ?>
    <div class="bw-dl-nota">El Excel se arma al momento con los datos de la BD (puede tardar unos segundos).</div>
    <div class="bw-dl-error" style="display:none"></div>
</div>

<div class="bw-alertas">
    <div class="bw-card">
        <div class="bw-card-cab">
            <span class="bw-card-icono <?php echo $hayFallas ? 'bw-rojo' : 'bw-verde'; ?>">
<?php if ($hayFallas) { ?>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5L2.5 20h19z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 10v4.5M12 17.3v.2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
<?php } else { ?>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
<?php } ?>
            </span>
            <div class="bw-card-titulo">
                <div>Alertas de la semana W-<?php echo $semana; ?></div>
                <small><?php echo $desde; ?> al <?php echo $hasta; ?></small>
            </div>
            <div class="bw-chips">
                <span class="bw-chip <?php echo count($al['uplink']) ? 'bw-rojo' : ''; ?>">Uplink ≥ <?php echo (int)BW_UMBRAL_ALERTA; ?>% <b><?php echo count($al['uplink']); ?></b></span>
                <span class="bw-chip <?php echo count($al['sin_registro']) ? 'bw-rojo' : ''; ?>">Sin registro <b><?php echo count($al['sin_registro']); ?></b></span>
                <span class="bw-chip <?php echo count($al['capacidad']) ? 'bw-ambar' : ''; ?>">Capacidad <b><?php echo count($al['capacidad']); ?></b></span>
                <span class="bw-chip <?php echo count($al['olt_nueva']) ? 'bw-azul' : ''; ?>">OLTs nuevas <b><?php echo count($al['olt_nueva']); ?></b></span>
            </div>
        </div>
<?php if ($total == 0) { ?>
        <div class="bw-vacio">Sin alertas: ninguna OLT superó el <?php echo (int)BW_UMBRAL_ALERTA; ?>% de Uplink y todas tienen registros.</div>
<?php } ?>
<?php if (count($al['uplink']) > 0) { ?>
        <div class="bw-seccion">
            <h4><span class="bw-punto bw-rojo"></span>% Uplink igual o superior al <?php echo (int)BW_UMBRAL_ALERTA; ?>%</h4>
            <div class="bw-tabla-scroll"><table class="bw-tabla">
                <thead><tr><th>Día</th><th>PE-com</th><th>OLT</th><th class="bw-num">Tráfico (Mbps)</th><th class="bw-num">Capacidad (Mbps)</th><th class="bw-num">% Uplink</th><th>Celda Excel</th></tr></thead>
                <tbody>
<?php foreach ($al['uplink'] as $a) { ?>
                    <tr><td><?php echo bw_h($a['dia']); ?></td><td><?php echo bw_h($a['pe_com']); ?></td><td class="bw-olt"><?php echo bw_h($a['olt']); ?></td>
                        <td class="bw-num"><?php echo number_format($a['trafico'], 0, ',', '.'); ?></td><td class="bw-num"><?php echo number_format($a['capacidad'], 0, ',', '.'); ?></td>
                        <td class="bw-num"><span class="bw-pct"><?php echo number_format($a['porcentaje'], 2, ',', '.'); ?>%</span></td><td><span class="bw-celda"><?php echo bw_h($a['celda']); ?></span></td></tr>
<?php } ?>
                </tbody>
            </table></div>
        </div>
<?php } ?>
<?php if (count($al['sin_registro']) > 0) { ?>
        <div class="bw-seccion">
            <h4><span class="bw-punto bw-rojo"></span>OLTs sin registro de tráfico</h4>
            <div class="bw-tabla-scroll"><table class="bw-tabla">
                <thead><tr><th>PE-com</th><th>OLT</th><th>Fila Excel</th><th>Detalle</th></tr></thead>
                <tbody>
<?php foreach ($al['sin_registro'] as $a) { ?>
                    <tr><td><?php echo bw_h($a['pe_com']); ?></td><td class="bw-olt"><?php echo bw_h($a['olt']); ?></td><td><span class="bw-celda"><?php echo (int)$a['fila']; ?></span></td><td><?php echo bw_h($a['detalle']); ?></td></tr>
<?php } ?>
                </tbody>
            </table></div>
        </div>
<?php } ?>
<?php if (count($al['capacidad']) > 0) { ?>
        <div class="bw-seccion">
            <h4><span class="bw-punto bw-ambar"></span>Cambios de capacidad respecto de la semana anterior</h4>
            <div class="bw-tabla-scroll"><table class="bw-tabla">
                <thead><tr><th>PE-com</th><th>OLT</th><th>Fila Excel</th><th class="bw-num">Capacidad anterior (Mbps)</th><th class="bw-num">Capacidad nueva (Mbps)</th></tr></thead>
                <tbody>
<?php foreach ($al['capacidad'] as $a) { ?>
                    <tr><td><?php echo bw_h($a['pe_com']); ?></td><td class="bw-olt"><?php echo bw_h($a['olt']); ?></td><td><span class="bw-celda"><?php echo (int)$a['fila']; ?></span></td>
                        <td class="bw-num"><?php echo number_format($a['anterior'], 0, ',', '.'); ?></td><td class="bw-num"><b><?php echo number_format($a['nueva'], 0, ',', '.'); ?></b></td></tr>
<?php } ?>
                </tbody>
            </table></div>
        </div>
<?php } ?>
<?php if (count($al['olt_nueva']) > 0) { ?>
        <div class="bw-seccion">
            <h4><span class="bw-punto bw-azul"></span>OLTs nuevas agregadas al reporte</h4>
            <div class="bw-nuevas">
<?php foreach ($al['olt_nueva'] as $a) { ?>
                <div class="bw-nueva"><span class="bw-olt"><?php echo bw_h($a['olt']); ?></span><span class="bw-nueva-pe"><?php echo bw_h($a['pe_com']); ?></span><small><?php echo bw_h($a['detalle']); ?></small></div>
<?php } ?>
            </div>
        </div>
<?php } ?>
    </div>
</div>
