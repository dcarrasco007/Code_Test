<?php
//$idPag = 'vista_mac_duplicadas';
/* $idPag2 = '999';
include_once('../../perfiles/getPerfiles.php');
include('../../perfiles/proceso.php');
checkAccV2(getUser(),$idPag2); */
?>
<!DOCTYPE html>
<html>
    <head>
        <title>SN/MAC Duplicadas</title>
    </head>
    <body>
        <div class="row">
            <div class="col-md-12 text-center"><h3>ONUs con SN/MAC en m&aacute;s de un puerto</h3></div>
        </div><br />
        <div class="row">
            <div class="col-md-12 text-center">
                <button type="button" class="btn btn-primary" onclick="generarReporte()">Generar Datos</button>
                &nbsp;
                <a id="btnExportar" href="export_mac_duplicadas.php" class="btn btn-success" style="display:none;">Exportar Excel</a>
            </div>
        </div><br />
        <div class="row">
            <div class="col-md-1"></div>
            <div class="col-md-10">
                <div id="cont_mac_dup" class="aling-left"></div>
            </div>
        </div>
    </body>
</html>
<script type="text/javascript">
function generarReporte() {
    $('#cont_mac_dup').html('<center><br>Generando informe, por favor espera...</center>');
    $('#btnExportar').hide();
    $.post('tabla_mac_duplicadas.php', {}, function(data) {
        $('#cont_mac_dup').html(data);
        $('#btnExportar').show();
        $('#tblMacDup').tablesorter({
            theme: 'blue',
            widgets: ["zebra", "stickyHeaders", "filter"]
        });
    });
}
</script>
