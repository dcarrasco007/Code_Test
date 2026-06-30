<?php
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
echo date("Y-m-d H:i:s")."\n";
$fecha = date('Y-m-d', strtotime('-1 day'));
$fecha = "'".$fecha."'";
//$fecha="'2025-08-02'";
/* $sql_ip = "SELECT OLT_SERVER.`server`,OLT_SERVER.ip, OLT_SERVER.modelo FROM OLT_SERVER WHERE OLT_SERVER.modelo='MA5800-X15' AND OLT_SERVER.`server`NOT IN(
SELECT OLT_TRAFICO_UPLINK_MA5800_X15_DAY.`server` FROM OLT_TRAFICO_UPLINK_MA5800_X15_DAY WHERE OLT_TRAFICO_UPLINK_MA5800_X15_DAY.fecha='2025-05-24')"; */
$sql_ip = "SELECT
        OLT_SERVER.`server`,
        OLT_SERVER.ip,
        OLT_SERVER.modelo
        FROM 
        OLT_SERVER
        WHERE 
        OLT_SERVER.modelo = 'MA5800-X15'";
$result = $mysqli->query($sql_ip) or die("error 1");
$semana=date('W');
while($row = $result->fetch_array(MYSQLI_NUM)){
    $server="'".$row[0]."'";
    //$ip="'".$row[1]."'";
    $ip=$row[1];
    
    exec("nohup php -f /u01/crontab127/OLT/Uplink/Procesos/proceso_uplink_trafico_diario_exped.php $server $ip $fecha > /u01/crontab127/OLT/Uplink/Procesos/logs/MA5800-x15/log$ip.log &");
    //$arrLinks[] = "http://192.168.66.127/var/www/html/OLT/Uplinks/Procesos/proceso_uplink_trafico_diario_exped.php?ip=".json_encode($ip)."";
    
}


?>