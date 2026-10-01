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
    #odfComunaV2 .panel-heading-odf{background:#2E75B6;color:#fff;padding:18px 24px;font-size:20px;font-weight:600;border-radius:4px 4px 0 0;}
    #odfComunaV2 .panel-heading-odf svg{margin-right:10px;vertical-align:-3px;}
    #odfComunaV2 .toolbar{background:#fff;padding:18px 24px;border-left:1px solid #E0E4E8;border-right:1px solid #E0E4E8;}
    #odfComunaV2 .toolbar .form-control{box-shadow:none;border-color:#CFD8DC;}
    #odfComunaV2 .toolbar .input-group-addon{background:#fff;border-color:#CFD8DC;color:#90A4AE;}
    #odfComunaV2 .total-badge{color:#607D8B;font-size:13px;margin-top:8px;display:block;}
    #odfComunaV2 .grid-wrap{background:#fff;padding:26px 28px 30px;border:1px solid #E0E4E8;border-top:none;border-radius:0 0 4px 4px;}
    #odfComunaV2 .celda-comuna-odf{padding-left:10px;padding-right:10px;margin-bottom:20px;}
    #odfComunaV2 .comuna-card{background:#fff;border:1px solid #E0E4E8;border-left:4px solid #2E75B6;border-radius:4px;padding:16px 14px;height:100%;min-height:64px;cursor:pointer;transition:box-shadow .15s ease, transform .15s ease, border-color .15s ease;display:flex;align-items:center;}
    #odfComunaV2 .comuna-card:hover{box-shadow:0 3px 10px rgba(0,0,0,0.12);transform:translateY(-2px);border-left-color:#1a5290;}
    #odfComunaV2 .comuna-icon{width:30px;height:30px;min-width:30px;border-radius:50%;background:#E6F1FB;color:#2E75B6;display:flex;align-items:center;justify-content:center;margin-right:12px;}
    #odfComunaV2 .comuna-name{font-size:13px;font-weight:600;color:#263238;line-height:1.3;}
    #odfComunaV2 .sin-resultados{color:#90A4AE;padding:20px;text-align:center;font-size:13px;}
</style>
<div class="row">
    <div class="col-md-1"></div>
    <div class="col-md-10">
    <div id="odfComunaV2" class="container-fluid">
    <div class="panel-heading-odf">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-7.3-7-12a7 7 0 0 1 14 0c0 4.7-7 12-7 12z"></path><circle cx="12" cy="9" r="2.5"></circle></svg>ODF por Comunas
    </div>
    <div class="toolbar">
        <div class="row">
            <div class="col-sm-5">
                <div class="input-group">
                    <span class="input-group-addon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
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
            <div class="col-xs-6 col-sm-4 col-md-4 col-lg-3 celda-comuna-odf">
                <div class="comuna-card" onclick="muestraODFComuna('<?php echo addslashes($c); ?>')">
                    <div class="comuna-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-7.3-7-12a7 7 0 0 1 14 0c0 4.7-7 12-7 12z"></path><circle cx="12" cy="9" r="2.5"></circle></svg>
                    </div>
                    <span class="comuna-name"><?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
<?php endforeach; ?>
        </div>
    </div>
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
