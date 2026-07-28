<?php
date_default_timezone_set('America/Santiago');
//$idPag = 'reporte_fan_estado';
$idPag2 = '635';
include_once('../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
checkAccV2(getUser(),$idPag2);
include('../perfiles/proceso.php');

include ('/var/www/procesos/php/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$sql_ip = 'SELECT equipo,ip,region,nombre,estado,fecha FROM OLT_FAN_ESTADO ORDER BY region';
$result = $mysqli->query($sql_ip) or die("error 2");

$titulo = "<h3><center>Informe FAN/Estado</center></h3>";
echo $titulo;

$tabla_fan .= "<table align='center' id='tblfan' class ='table table-bordered table-striped' border='1'>
            <thead class = 'bg-primary'><tr>
            <th bgcolor='#0566FC'><center>N&deg;</center></th>
            <th bgcolor='#0566FC'><center>EQUIPO OLT</center></th>
            <th bgcolor='#0566FC'><center>IP EQUIPO</center></th>
            <th bgcolor='#0566FC'><center>REGION</center></th>
            <th bgcolor='#0566FC'><center>NOMBRE</center></th>
            <th bgcolor='#0566FC'><center>ESTADO</center></th>
            <th bgcolor='#0566FC'><center>FECHA</center></th>
            </tr></thead>";

$cont = 1;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $tabla_fan .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                </tr>';
    $cont++;
    
}

$sfile = "../Reporte_fan_estado.xls"; // Ruta del archivo a generar
$fp = fopen($sfile, "w");
fwrite($fp, $tabla_fan);
fclose($fp);


$t = "<a href='../Reporte_fan_estado.xls'>Exportar Archivo</a>";
echo $t;

$tabla_fan .= "</table>";
echo $tabla_fan;

?>

<script>
    $("#tblfan").tablesorter({
        theme: 'blue',
    	widgets: ["zebra" , "stickyHeaders" , "filter"],
    });
</script>