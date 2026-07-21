<?php
//include ('/var/www/html/conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

echo "Inicio";
//---------------------------------//
// Script de obtención de Cantidad de Vlan 
//---------------------------------//
// Con exped 
//---------------------------------//
//mysqli_query($mysqli,"TRUNCATE TABLE OLT_CANTIDAD_VLAN");
$sql_ip = "SELECT OLT_SERVER.server, OLT_SERVER.ip, OLT_SERVER.region  FROM OLT_SERVER INNER JOIN OLT_CANTIDAD_VLAN ON OLT_CANTIDAD_VLAN.equipo = OLT_SERVER.server WHERE OLT_SERVER.server NOT IN (SELECT OLT_CANTIDAD_VLAN.equipo  FROM OLT_CANTIDAD_VLAN) OR OLT_CANTIDAD_VLAN.cantidad=''";
//$sql_ip = "SELECT OLT_SERVER.server, OLT_SERVER.ip, OLT_SERVER.region FROM OLT_SERVER WHERE OLT_SERVER.server NOT IN (SELECT OLT_CANTIDAD_VLAN.equipo  FROM OLT_CANTIDAD_VLAN)";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    $ip = $row[1];
    $server = $row[0];
    $region = $row[2];
    $region = str_replace(" ","_",$region);
    mysqli_query($mysqli,"DELETE FROM Aden.OLT_CANTIDAD_VLAN WHERE equipo='$server'");
    shell_exec("nohup php -f /u01/crontab127/OLT/Vlan/Procesos/cantidad_vlan_data.php $ip $server > /u01/crontab127/OLT/Vlan/Procesos/logs/$ip.log &");
    $array[]=$ip.' - '.$server;
}
mysqli_close($mysqli);
print_r($array);   

?>