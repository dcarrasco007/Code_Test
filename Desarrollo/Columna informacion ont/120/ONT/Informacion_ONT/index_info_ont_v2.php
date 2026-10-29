<?php
//$idPag = 'vista_Info_ont';
/* $idPag2 = '315';
include_once(__DIR__ . '/../../perfiles/getPerfiles.php'); */

/* include_once(__DIR__ . '/../../perfiles/getPerfiles.php');
include(__DIR__ . '/../../perfiles/proceso.php');
checkAcc(getUser(),$idPag); */
/* checkAccV2(getUser(),$idPag2);
include(__DIR__ . '/../../perfiles/proceso.php'); */
?>

<!DOCTYPE html>
<html>
    <head>
        <title></title>
        
        
        
    </head>
    <body>
        <form class="form-inline">
            <div class="row">
                <div class="col-md-12 text-center"><h3>Informaci&oacute;n de ONT Detalle</h3></div><br />
                
            </div></br>
            <div class="row">
                <div class="col-md-12">
                    <div>
                    <center><input type="button" class="btn btn-primary" value="Generar Datos" onclick="genera_tabla()"></center>
                    <!--<center><button type="button" onclick="genera_tabla() class="btn btn-primary">Ver Infor</button></center>-->  
                    </div>
                </div><div class="col-md-1"></div>
                <div class="col-md-10">
                    <div id="cont" class="aling-left"> 
                        <?php
                        $t = "<span class='label label-primary pull-left' style ='margin-right:10px; padding:10px 10px;'><a id='export' href='#' style='color:white'>Exportar .csv</a></span>";
                        //$c = "<span class='label label-warning pull-right' style ='margin-left:10px; padding:10px 10px;'><a  href='#' style='color:white'>$count Registros</a></span>";
                        echo $t;
                        echo $c;
                        echo $html = '<div class="tab-content p-0">
                        <table id="tblData99" class="display" class="tablesorter" width="100%" cellspacing="0" >
                            <thead>
                                <tr>
                                    <th>EQUIPO</th>
                                    <th>IP</th>
                                    <th>FRAME ID</th>
                                    <th>SLOT ID</th>
                                    <th>PORT ID</th>
                                    <th>ONU ID</th>
                                    <th>ESTADO</th>
                                    <th>REGION</th>
                                    <th>COMUNA</th>
                                    <th>ONU NAME</th>
                                    <th>TERMINAL TYPE</th>
                                    <th>SOFTWARE VERSION</th>
                                    <th>SN MAC</th>
                                    <th>OLT RX ONU</th>
                                    <th>TX OPTICAL POWER</th>
                                    <th>RX OPTICAL POWER</th>
                                    <th>VOLTAJE</th>
                                    <th>TEMPERATURE</th>
                                    <th>LAST UP TIME</th>
                                    <th>LAST DOWN TIME</th>
                                    <th>DISTANCIA</th>
                                    <th>LINE PROFILE NAME</th>
                                    <th>SERVICE PROFILE NAME</th>
                                    <th>VAS PROFILE</th>
                                    <th>FECHA REGISTRO</th>

                                </tr>
                            </thead>
                            <tfoot>
                                <tr>
                                    <th>EQUIPO</th>
                                    <th>IP</th>
                                    <th>FRAME ID</th>
                                    <th>SLOT ID</th>
                                    <th>PORT ID</th>
                                    <th>ONU ID</th>
                                    <th>ESTADO</th>
                                    <th>REGION</th>
                                    <th>COMUNA</th>
                                    <th>ONU NAME</th>
                                    <th>TERMINAL TYPE</th>
                                    <th>SOFTWARE VERSION</th>
                                    <th>SN MAC</th>
                                    <th>OLT RX ONU</th>
                                    <th>TX OPTICAL POWER</th>
                                    <th>RX OPTICAL POWER</th>
                                    <th>VOLTAJE</th>
                                    <th>TEMPERATURE</th>
                                    <th>LAST UP TIME</th>
                                    <th>LAST DOWN TIME</th>
                                    <th>DISTANCIA</th>
                                    <th>LINE PROFILE NAME</th>
                                    <th>SERVICE PROFILE NAME</th>
                                    <th>VAS PROFILE</th>
                                    <th>FECHA REGISTRO</th>
                                </tr>
                            </tfoot>
                        </table>
                        <hr>
                        
                        <br>
                        </div>';
                        ?>  
                    </div>
                    
                </div>
            </div>
        </form>
    </body>
    </html>
<script type="text/javascript">

function genera_tabla() {
    $('#tblData99').DataTable({
            
            "processing": true,
            "serverSide": true,
            "ajax":"../../ONT/Informacion_ONT/ver_info_ont_v2.php",

            "columnDefs": [
                
                {"orderable": false,  "targets": [ 0 ] },
                {"orderable": false,  "targets": [ 1 ] },
                {"orderable": false,  "targets": [ 2 ] },
                {"orderable": false,  "targets": [ 3 ] },
                {"orderable": false,  "targets": [ 4 ] },
                {"orderable": false,  "targets": [ 5 ] },
                {"orderable": false,  "targets": [ 6 ] },
                {"orderable": false,  "targets": [ 7 ] },
                {"orderable": false,  "targets": [ 8 ] },
                {"orderable": false,  "targets": [ 9 ] },
                {"orderable": false,  "targets": [ 10 ] }
            ]
    });
}
$("#export").click(function(){
        var exportButton = $(this);
        exportButton.css('pointer-events', 'none');
        exportButton.css('opacity', '0.6');
        exportButton.text('Exportando...');
       window.location.href = "../ONT/Informacion_ONT/export_info_ont_v2.php";
       setTimeout(function() {
            exportButton.css('pointer-events', 'auto');
            exportButton.css('opacity', '1');
            exportButton.text('Exportar .csv');
        }, 20000); // Ajusta el tiempo de espera según sea necesario
    });
</script>