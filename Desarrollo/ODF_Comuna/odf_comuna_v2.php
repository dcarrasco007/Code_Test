<?php
//$idPag = 'reporte_ont_general';
$idPag2 = '527';
include_once('../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
//checkAccV2(getUser(),$idPag2);
include('../perfiles/proceso.php');

include ('../../conexion/conexion_db.php');
include ('estilo.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$query_1 = "SELECT DISTINCT (OLT_SERVER.comuna) from OLT_SERVER INNER JOIN OLT_POS_ODF on OLT_SERVER.server = OLT_POS_ODF.equipo ORDER BY comuna ASC";
$res = $mysqli->query($query_1) or die("error $query_1");

$comunas = array();
while ($row = $res->fetch_array(MYSQLI_NUM)) {
    $comunas[] = $row[0];
}
mysqli_close($mysqli);

$total = count($comunas);
?>
<style>
    #odfComunaV2 .panel-heading-odf{background:#2E75B6;color:#fff;padding:16px 22px;font-size:20px;font-weight:600;border-radius:4px 4px 0 0;}
    #odfComunaV2 .panel-heading-odf .fa{margin-right:8px;}
    #odfComunaV2 .toolbar{background:#fff;padding:14px 22px;border-left:1px solid #E0E4E8;border-right:1px solid #E0E4E8;}
    #odfComunaV2 .toolbar .form-control{box-shadow:none;border-color:#CFD8DC;}
    #odfComunaV2 .toolbar .input-group-addon{background:#fff;border-color:#CFD8DC;color:#90A4AE;}
    #odfComunaV2 .total-badge{color:#607D8B;font-size:13px;margin-top:8px;display:block;}
    #odfComunaV2 .grid-wrap{background:#fff;padding:20px 16px 24px;border:1px solid #E0E4E8;border-top:none;border-radius:0 0 4px 4px;}
    #odfComunaV2 .comuna-card{background:#fff;border:1px solid #E0E4E8;border-left:4px solid #2E75B6;border-radius:4px;padding:14px 12px;margin-bottom:16px;cursor:pointer;transition:box-shadow .15s ease, transform .15s ease, border-color .15s ease;}
    #odfComunaV2 .comuna-card:hover{box-shadow:0 3px 10px rgba(0,0,0,0.12);transform:translateY(-2px);border-left-color:#1a5290;}
    #odfComunaV2 .comuna-icon{width:34px;height:34px;border-radius:50%;background:#E6F1FB;color:#2E75B6;display:flex;align-items:center;justify-content:center;float:left;margin-right:10px;font-size:14px;}
    #odfComunaV2 .comuna-name{font-size:13px;font-weight:600;color:#263238;line-height:34px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block;margin-left:44px;}
    #odfComunaV2 .sin-resultados{color:#90A4AE;padding:20px;text-align:center;font-size:13px;}
</style>
<div id="odfComunaV2" class="container-fluid" style="max-width:1100px;">
    <div class="panel-heading-odf">
        <i class="fa fa-map-marker" aria-hidden="true"></i>ODF por Comunas
    </div>
    <div class="toolbar">
        <div class="row">
            <div class="col-sm-5">
                <div class="input-group">
                    <span class="input-group-addon"><i class="fa fa-search"></i></span>
                    <input type="text" id="buscadorComunaODF" class="form-control" placeholder="Buscar comuna...">
                </div>
            </div>
            <div class="col-sm-7 text-right">
                <span class="total-badge"><span id="contadorComunaODF"><?php echo $total; ?></span> comunas</span>
            </div>
        </div>
    </div>
    <div class="grid-wrap">
        <div class="row" id="gridComunasODF">
<?php foreach ($comunas as $c): ?>
            <div class="col-xs-6 col-sm-4 col-md-3 col-lg-2 celda-comuna-odf">
                <div class="comuna-card" onclick="muestraODFComuna('<?php echo addslashes($c); ?>')">
                    <div class="comuna-icon"><i class="fa fa-map-marker"></i></div>
                    <span class="comuna-name"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
<?php endforeach; ?>
        </div>
    </div>
</div>
<script type="text/javascript">
(function() {
    var $buscador = $('#buscadorComunaODF');
    var $celdas = $('#gridComunasODF').find('.celda-comuna-odf');
    $buscador.on('keyup', function() {
        var q = $(this).val().toUpperCase();
        var visibles = 0;
        $celdas.each(function() {
            var nombre = $(this).find('.comuna-name').text().toUpperCase();
            if (nombre.indexOf(q) !== -1) {
                $(this).show();
                visibles++;
            } else {
                $(this).hide();
            }
        });
        $('#contadorComunaODF').text(visibles);
    });
})();
</script>
