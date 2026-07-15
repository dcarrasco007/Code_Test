<?php
//include ('../../../conexion/conexion_db.php');
include ('/u01/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

mysqli_query($mysqli,"TRUNCATE TABLE OLT_ONT_DETALLE_2");//ONT Online (Si hay ONT con totales con data y online en 0 ejecutar shell_ont_repaso_equipos_detalle.php)
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ONT_EQUIPOS3");// ONT Totales

$fecha = date("Y-m-d");

$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER';
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    $region= str_replace(" ","_",$region);

    shell_exec("nohup php -f /u01/crontab127/OLT/ONT/Procesos/reporte_general_v2_data.php $ip $server $region > /u01/crontab127/OLT/ONT/logs/$ip.log &");

}   
mysqli_close($mysqli);
?>