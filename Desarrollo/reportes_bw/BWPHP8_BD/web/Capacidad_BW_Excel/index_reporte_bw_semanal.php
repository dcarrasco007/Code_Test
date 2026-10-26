<?php
/**
 * Ventana del reporte BW semanal: selector de semana, botones de descarga (el Excel se arma desde
 * OLT_REPORTE_BW_ANUAL al presionarlos) y alertas de la semana.
 * Se carga desde el menú: $(".contenido").load('../Capacidad_BW_Excel/index_reporte_bw_semanal.php');
 * PHP 8.0 - contingencia_v3.
 */
//$idPag = 'informe_capacidad_bw';
if (file_exists(__DIR__ . '/../perfiles/getPerfiles.php')) {
    include_once(__DIR__ . '/../perfiles/getPerfiles.php');
    include(__DIR__ . '/../perfiles/proceso.php');
}
require_once __DIR__ . '/lib/bw_web.php';

$semanas = array();
$error = '';
try {
    $mysqli = bw_conectar_bd();
    $semanas = bw_anual_semanas($mysqli);
    $mysqli->close();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<style>
    .bw-botones .btn { margin: 0 6px 8px 6px; }
    .bw-alertas { max-width: 1100px; margin: 0 auto; text-align: left; }
    .bw-alertas table { font-size: 12px; }
    .bw-alertas .bw-pct { font-weight: bold; color: #c00; }
</style>
<body>
    <center><h1>Reporte BW Semanal</h1></center></br>
<?php if ($error != '') { ?>
    <center>Error al leer la BD: <?php echo bw_h($error); ?></center>
<?php } elseif (count($semanas) == 0) { ?>
    <center>Aún no hay semanas cargadas en <?php echo BW_TABLA_ANUAL; ?>.</center>
<?php } else { ?>
    <div class="form-group text-center">
        <label for="bw_semana">Semana:</label>
        <select id="bw_semana" class="form-control" style="display:inline-block; width:auto;" onchange="bwCargarSemana(this.value)">
<?php foreach ($semanas as $s) {
    $desde = date('d-m-Y', bw_ts_dia($s[0], ($s[1] - 1) * 7));
    $hasta = date('d-m-Y', bw_ts_dia($s[0], ($s[1] - 1) * 7 + 6)); ?>
            <option value="<?php echo $s[0] . '-' . $s[1]; ?>">W-<?php echo $s[1]; ?> <?php echo $s[0]; ?> (<?php echo $desde; ?> al <?php echo $hasta; ?>)</option>
<?php } ?>
        </select>
    </div>
    <div id="bw_detalle"><center>Cargando...</center></div>
    <script type="text/javascript">
        function bwCargarSemana(valor) {
            var p = valor.split('-');
            var div = document.getElementById('bw_detalle');
            div.innerHTML = '<center>Cargando...</center>';
            fetch('<?php echo BW_URL_MODULO; ?>alertas_reporte_bw.php?anio=' + p[0] + '&semana=' + p[1])
                .then(function (r) { return r.text(); })
                .then(function (html) { div.innerHTML = html; })
                .catch(function () { div.innerHTML = '<center>No se pudieron cargar las alertas.</center>'; });
        }
        bwCargarSemana(document.getElementById('bw_semana').value);
    </script>
<?php } ?>
</body>
