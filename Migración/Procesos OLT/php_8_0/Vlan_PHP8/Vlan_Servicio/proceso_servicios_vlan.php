<?php
//include ('/var/www/html/conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

echo "Inicio";
//---------------------------------//
// Script de obtención Vlan servicios
//---------------------------------//
// Con exped 
//---------------------------------//
$sql_ip = "SELECT OLT_SERVER.server, OLT_SERVER.ip  FROM OLT_SERVER 
ORDER BY OLT_SERVER.server";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    $ip = $row[1];
    $server = $row[0];
    shell_exec("nohup php -f /u01/crontab127/OLT/Vlan/Vlan_Servicio/proceso_vlan_servicio_data.php $ip $server > /u01/crontab127/OLT/Vlan/Vlan_Servicio/logs/$ip.log &");
    $array[]=$ip;
}
mysqli_close($mysqli);
print_r($array);   
?>

?>