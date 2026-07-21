<?php
include ('../../../conexion/conexion_db.php');
//$idPag = 'reporte_cantidad_vlan_servicio';
$idPag2 = '63466';
include_once('../../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
checkAccV2(getUser(),$idPag2);
include('../../perfiles/proceso.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");


//echo '<pre>';
//print_r($peak_mensual);
//echo '</pre>';

?>


<!DOCTYPE html>
<html>
    <head>
        <title></title>
    </head>
    <body>
        <form class="form-inline">
            <div class="row">           
                <div class="col-md-12 text-center"><h3>Cantidad de Vlan de Servicios por OLT</h3></div>
            </div>
            <div class="row">
                <div class="col-md-4"></div>
                <div class="col-md-4">
                    </br></br>
                    <table class="table">
                        <tr>
                        	<td>Equipo:</td>
                        	<td><?
                                $query = "SELECT server, ip FROM OLT_SERVER ORDER BY server";
                                $result = $mysqli->query($query) or die("error $query");
                                $t = '<select class="js-example-basic-single" id="equipo_olt" style="width:200px;">';
                                $t .= "<option value=''>Ingrese opci&oacute;n</option>";
                                while ($row = $result->fetch_array(MYSQLI_NUM)) {
                                    $t .= "<option id=".$row[1]." >$row[0]</option>";
                                }
                                $t .= "</select>";
                                echo $t;
                                ?>
                            </td>
                        </tr>
                        <tr id="e_fechas1">
                            <td>Fecha</td>
                            <td>
                                <div class="form-group" id="fecha_olt">
                                    <input type="text" class="form-control" id="dtpInicio" placeholder="Fecha"style="width:200px;"/>
                                </div>          
                            </td>
                        </tr>
                        <tr>
                        	<td colspan="2"><br/><center><button type="button" id="crear" class="btn btn-primary">Crear</button></center></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-4"></div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="container-fluid">
                        <div class="center-block">
                            <div class="row">
                                <div class="col-xs-12" id="panelhome">
                                    <div id="cont" class="contenedor_tabla">
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </body>
</html>

<script type="text/javascript">

$(function(){
    
    $('#dtpInicio, #dtpFin').datetimepicker({
        locale: 'es',
        format: 'YYYY-MM-DD',
        maxDate: new Date(),
        minDate: '2023-06-13',
    });
    
    $("#crear").click(function(){
        var today = new Date();
        if (!$("#equipo_olt").val()) 
        {   alert("Debe seleccionar un equipo."); 
            return false; 
        }
        if (!$("#dtpInicio").val()) 
        {   alert("Debe seleccionar una fecha."); 
            return false; 
        }

        
            $.post("../Vlan/Vlan_Servicio/tabla_vlan_servicio.php", { ip: $("#equipo_olt option:selected").attr("id"), fecha: $("#dtpInicio").val()})
                    .done(function(data) {
                    $(".contenedor_tabla").html(data);
            }); 
        
         
    }); // end function
});

</script>
<script>
    $(document).ready(function() {
        $('.js-example-basic-single').select2();
    });
</script>
