<?php
//$idPag = 'vista_mac_duplicadas_onnet';
/* $idPag2 = '999';
include_once('../../perfiles/getPerfiles.php');
include('../../perfiles/proceso.php');
checkAccV2(getUser(),$idPag2); */
?>
<!DOCTYPE html>
<html>
    <head>
        <title>SN/MAC Duplicadas ONNET</title>
    </head>
    <body>
        <div class="row">
            <div class="col-md-12 text-center"><h3>ONUs con SN/MAC en m&aacute;s de un puerto &mdash; ONNET</h3></div>
        </div><br />
        <div class="row">
            <div class="col-md-12 text-center">
                <button type="button" class="btn btn-primary" onclick="generarReporte()">Generar Datos</button>
                &nbsp;
                <a id="btnExportar" href="../ONT/Reporte_MAC_Duplicadas/export_mac_duplicadas_ONNET.php" class="btn btn-success" style="display:none;">Exportar Excel</a>
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
    $.post('../ONT/Reporte_MAC_Duplicadas/tabla_mac_duplicadas_ONNET.php', {}, function(data) {
        $('#cont_mac_dup').html(data);
        $('#btnExportar').show();
        var $tabla = $('#tblMacDup');
        $tabla.tablesorter({
            theme: 'blue',
            widgets: ["zebra", "stickyHeaders", "filter"]
        });
        $('.btn-filtro-estado').on('click', function() {
            var clase = $(this).data('clase');
            var filtros = ($tabla.data('lastSearch') || []).slice();
            filtros[8] = clase;
            $tabla.trigger('search', [filtros]);
            $('.btn-filtro-estado').removeClass('activo');
            $(this).addClass('activo');
        });
    });
}

$('#btnExportar').on('click', function(e) {
    var $btn = $(this);
    if ($btn.hasClass('exportando')) {
        e.preventDefault();
        return false;
    }
    $btn.addClass('exportando');
    $btn.css('pointer-events', 'none');
    $btn.css('opacity', '0.6');
    $btn.text('Generando Excel...');
    setTimeout(function() {
        $btn.removeClass('exportando');
        $btn.css('pointer-events', 'auto');
        $btn.css('opacity', '1');
        $btn.text('Exportar Excel');
    }, 8000);
});
</script>
