<?php
//include ('../../../conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$fecha = date("Y-m-d");
//---------------------------------//
// Repaso del script de obtención ONT para aquellos equipos que en su log no extrajeron data
//---------------------------------//
// Con exped 
//---------------------------------//
$sql_ip = "SELECT OLT_ONT_EQUIPOS3.ip,OLT_ONT_EQUIPOS3.`server`,OLT_ONT_EQUIPOS3.region
FROM OLT_ONT_EQUIPOS3
WHERE
OLT_ONT_EQUIPOS3.ont_online = '0' AND OLT_ONT_EQUIPOS3.ont_total = '0'
UNION 

SELECT OLT_SERVER.ip, OLT_SERVER.server, OLT_SERVER.region 
FROM OLT_SERVER WHERE OLT_SERVER.server NOT IN (SELECT OLT_ONT_EQUIPOS3.server  FROM OLT_ONT_EQUIPOS3)";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    $region= str_replace(" ","_",$region);
    $sql_delete = "DELETE FROM OLT_ONT_EQUIPOS3
                WHERE
                OLT_ONT_EQUIPOS3.`server` = '$server'
                AND
                OLT_ONT_EQUIPOS3.ont_online = '0'
                AND
                OLT_ONT_EQUIPOS3.ont_total = '0'";
    $sql_delete2 = "DELETE FROM Aden.OLT_ONT_DETALLE_2
                WHERE equipo='$server'";
    $result2 = $mysqli->query($sql_delete) or die("error $sql_delete");
    $result3 = $mysqli->query($sql_delete2) or die("error $sql_delete2");
    shell_exec("nohup php -f /u01/crontab127/OLT/ONT/Procesos/reporte_general_v2_data.php $ip $server $region > /u01/crontab127/OLT/ONT/logs/$ip.log &");
    $array[]=$ip;
}
print_r($array);   
?>