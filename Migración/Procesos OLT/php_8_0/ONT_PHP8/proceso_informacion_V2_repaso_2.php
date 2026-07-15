<?php

include ('/u01/crontab127/conexion/conexion_db.php');
echo 'Inicio del Script...';
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE_COMPLETO_V2");
$fecha = date("Y-m-d");
echo "\n$date\n";
$sql_ip = "SELECT ip,server,comuna,modelo,sw,patch,tipo,region FROM OLT_SERVER WHERE
OLT_SERVER.server <> 'OLT-SBERNARDO-1'
AND OLT_SERVER.server <> 'OLT-CNT-2'
AND OLT_SERVER.server <> 'OLT-VALPARAISO-1'
AND OLT_SERVER.server <> 'OLT-RECREO-2' 
AND OLT_SERVER.server <> 'OLT1-1-LABONNET-5800'
AND OLT_SERVER.server <> 'OLT1-2-LABONNET-5600'
AND OLT_SERVER.server <> 'OLT-VITACURA-1'";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    //sleep(1);
    $ip = $row[0];
    $server = $row[1];
    $modelo = $row[3];
    $comuna = $row[2];
    $region = $row[7];
    $region = str_replace(' ','_',$region);
    $comuna = str_replace(' ','_',$comuna);
    shell_exec("nohup php -f /u01/crontab127/OLT/ONT/proceso_data_V3.php $ip $server $modelo $region $comuna> /u01/crontab127/OLT/ONT/logs/informacion_ont_v2/$ip.log &");

}

mysqli_close($mysqli);
echo 'Fin Script.';   
?>