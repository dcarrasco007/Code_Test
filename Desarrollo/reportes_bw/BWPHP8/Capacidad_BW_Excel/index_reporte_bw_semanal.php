<?php
//$idPag = 'informe_capacidad_bw';
if (file_exists(__DIR__ . '/../perfiles/getPerfiles.php')) {
    include_once(__DIR__ . '/../perfiles/getPerfiles.php');
    include(__DIR__ . '/../perfiles/proceso.php');
}
require_once __DIR__ . '/lib/bw_config.php';
require_once __DIR__ . '/lib/bw_fechas.php';

function bw_h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function bw_fecha_corta($ymd)
{
    $p = explode('-', $ymd);
    return $p[2] . '-' . $p[1] . '-' . $p[0];
}

// Semanas generadas (según sus .json de alertas), de la más reciente a la más antigua
$semanas = array();
$jsons = glob(BW_DIR_SEMANA . '/alertas_*_W*.json');
if ($jsons) {
    foreach ($jsons as $j) {
        $d = json_decode(file_get_contents($j), true);
        if (is_array($d) && isset($d['archivos']) && file_exists(BW_DIR_SEMANA . '/' . $d['archivos']['semana'])) {
            $semanas[sprintf('%04d%02d', $d['anio'], $d['semana'])] = $d;
        }
    }
}
krsort($semanas);
// El archivo "Act - Act" se sobrescribe cada semana del mes: solo corresponde a la última semana generada con ese nombre
$actVigente = array();
foreach ($semanas as $clave => $d) {
    if (!isset($actVigente[$d['archivos']['act']])) {
        $actVigente[$d['archivos']['act']] = $clave;
    }
}
// URL de la carpeta de BW_DIR_SEMANA (ej: Archivos_Auto/Semana), relativa al módulo
$urlBase = '../Capacidad_BW_Excel' . str_replace('\\', '/', substr(BW_DIR_SEMANA, strlen(BW_DIR_MODULO))) . '/';
?>
<style>
    .bw-semana { display: none; }
    .bw-semana.activa { display: block; }
    .bw-botones .btn { margin: 0 6px 8px 6px; }
    .bw-alertas { max-width: 1100px; margin: 0 auto; text-align: left; }
    .bw-alertas table { font-size: 12px; }
    .bw-alertas .bw-pct { font-weight: bold; color: #c00; }
</style>
<body>
    <center><h1>Reporte BW Semanal</h1></center></br>
<?php if (count($semanas) == 0) { ?>
    <center>Aún no hay reportes generados.</center>
<?php } else { ?>
    <div class="form-group text-center">
        <label for="bw_semana">Semana:</label>
        <select id="bw_semana" class="form-control" style="display:inline-block; width:auto;" onchange="bwMostrarSemana(this.value)">
<?php foreach ($semanas as $clave => $d) { ?>
            <option value="<?php echo $clave; ?>">W-<?php echo (int)$d['semana']; ?> <?php echo (int)$d['anio']; ?>
                (<?php echo bw_fecha_corta($d['desde']); ?> al <?php echo bw_fecha_corta($d['hasta']); ?>)</option>
<?php } ?>
        </select>
    </div>

<?php
$primera = true;
foreach ($semanas as $clave => $d) {
    $uplink = array();
    $sinRegistro = array();
    $nuevas = array();
    $capacidad = array();
    foreach ($d['alertas'] as $a) {
        if ($a['tipo'] == 'uplink') {
            $uplink[] = $a;
        } elseif ($a['tipo'] == 'sin_registro') {
            $sinRegistro[] = $a;
        } elseif ($a['tipo'] == 'olt_nueva') {
            $nuevas[] = $a;
        } elseif ($a['tipo'] == 'capacidad') {
            $capacidad[] = $a;
        }
    }
?>
    <div class="bw-semana<?php echo $primera ? ' activa' : ''; ?>" id="bw_semana_<?php echo $clave; ?>">
        <div class="text-center bw-botones">
            <a class="btn btn-primary" href="<?php echo $urlBase . rawurlencode($d['archivos']['semana']); ?>">
                <span class="glyphicon glyphicon-download-alt"></span> <?php echo bw_h($d['archivos']['semana']); ?></a>
<?php if ($actVigente[$d['archivos']['act']] == $clave && file_exists(BW_DIR_SEMANA . '/' . $d['archivos']['act'])) { ?>
            <a class="btn btn-primary" href="<?php echo $urlBase . rawurlencode($d['archivos']['act']); ?>">
                <span class="glyphicon glyphicon-download-alt"></span> <?php echo bw_h($d['archivos']['act']); ?></a>
<?php } ?>
            <div><small>Generado: <?php echo bw_h($d['generado']); ?></small></div>
        </div>

        <div class="bw-alertas">
            <div class="panel <?php echo (count($uplink) + count($sinRegistro)) > 0 ? 'panel-danger' : 'panel-success'; ?>">
                <div class="panel-heading"><strong>Alertas de la semana W-<?php echo (int)$d['semana']; ?>
                    (<?php echo bw_fecha_corta($d['desde']); ?> al <?php echo bw_fecha_corta($d['hasta']); ?>)</strong></div>
                <div class="panel-body">
<?php if (count($uplink) + count($sinRegistro) + count($nuevas) + count($capacidad) == 0) { ?>
                    Sin alertas: ninguna OLT superó el <?php echo (int)$d['umbral']; ?>% de Uplink y todas tienen registros.
<?php } ?>
<?php if (count($uplink) > 0) { ?>
                    <h4>% Uplink igual o superior al <?php echo (int)$d['umbral']; ?>%</h4>
                    <table class="table table-bordered table-condensed table-striped">
                        <thead><tr><th>Día</th><th>PE-com</th><th>OLT</th><th>Tráfico (Mbps)</th><th>Capacidad (Mbps)</th><th>% Uplink</th><th>Celda Excel</th></tr></thead>
                        <tbody>
<?php foreach ($uplink as $a) { ?>
                            <tr>
                                <td><?php echo bw_h($a['dia']); ?></td>
                                <td><?php echo bw_h($a['pe_com']); ?></td>
                                <td><?php echo bw_h($a['olt']); ?></td>
                                <td><?php echo number_format($a['trafico'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($a['capacidad'], 0, ',', '.'); ?></td>
                                <td class="bw-pct"><?php echo number_format($a['porcentaje'], 2, ',', '.'); ?>%</td>
                                <td><?php echo bw_h($a['celda']); ?></td>
                            </tr>
<?php } ?>
                        </tbody>
                    </table>
<?php } ?>
<?php if (count($sinRegistro) > 0) { ?>
                    <h4>OLTs sin registro de tráfico</h4>
                    <table class="table table-bordered table-condensed table-striped">
                        <thead><tr><th>PE-com</th><th>OLT</th><th>Fila Excel</th><th>Detalle</th></tr></thead>
                        <tbody>
<?php foreach ($sinRegistro as $a) { ?>
                            <tr>
                                <td><?php echo bw_h($a['pe_com']); ?></td>
                                <td><?php echo bw_h($a['olt']); ?></td>
                                <td><?php echo (int)$a['fila']; ?></td>
                                <td><?php echo bw_h($a['detalle']); ?></td>
                            </tr>
<?php } ?>
                        </tbody>
                    </table>
<?php } ?>
<?php if (count($capacidad) > 0) { ?>
                    <h4>Cambios de capacidad respecto de la semana anterior</h4>
                    <table class="table table-bordered table-condensed table-striped">
                        <thead><tr><th>PE-com</th><th>OLT</th><th>Fila Excel</th><th>Capacidad anterior (Mbps)</th><th>Capacidad nueva (Mbps)</th></tr></thead>
                        <tbody>
<?php foreach ($capacidad as $a) { ?>
                            <tr>
                                <td><?php echo bw_h($a['pe_com']); ?></td>
                                <td><?php echo bw_h($a['olt']); ?></td>
                                <td><?php echo (int)$a['fila']; ?></td>
                                <td><?php echo number_format($a['anterior'], 0, ',', '.'); ?></td>
                                <td><?php echo number_format($a['nueva'], 0, ',', '.'); ?></td>
                            </tr>
<?php } ?>
                        </tbody>
                    </table>
<?php } ?>
<?php if (count($nuevas) > 0) { ?>
                    <h4>OLTs nuevas agregadas al Excel</h4>
                    <ul>
<?php foreach ($nuevas as $a) { ?>
                        <li><strong><?php echo bw_h($a['olt']); ?></strong> (<?php echo bw_h($a['pe_com']); ?>): <?php echo bw_h($a['detalle']); ?></li>
<?php } ?>
                    </ul>
<?php } ?>
                </div>
            </div>
        </div>
    </div>
<?php
    $primera = false;
}
?>
    <script type="text/javascript">
        function bwMostrarSemana(clave) {
            var bloques = document.querySelectorAll('.bw-semana');
            for (var i = 0; i < bloques.length; i++) {
                bloques[i].className = (bloques[i].id == 'bw_semana_' + clave) ? 'bw-semana activa' : 'bw-semana';
            }
        }
    </script>
<?php } ?>
</body>
