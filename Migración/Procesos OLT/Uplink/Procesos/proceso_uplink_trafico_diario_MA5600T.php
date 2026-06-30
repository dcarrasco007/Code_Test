<?php
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
echo 'Inicio Proceso';
$fecha = date('Y-m-d', strtotime('-1 day'));
//$fecha = '2025-07-31';
$sql_ip = "SELECT
        OLT_SERVER.`server`,
        OLT_SERVER.ip,
        OLT_SERVER.modelo
        FROM 
        OLT_SERVER
        WHERE 
        OLT_SERVER.modelo = 'MA5680T' OR 
        OLT_SERVER.modelo = 'MA5600T' OR
        OLT_SERVER.modelo = 'MA5603T'";
$result = $mysqli->query($sql_ip) or die("error 1");
$semana=date('W');
while($row = $result->fetch_array(MYSQLI_NUM)){

    $server="'".$row[0]."'";
    //$ip="'".$row[1]."'";
    $ip=$row[1];
    exec("nohup php -f /u01/crontab127/OLT/Uplink/Procesos/proceso_uplink_trafico_diario_5600T_exped.php $server $ip $fecha > /u01/crontab127/OLT/Uplink/Procesos/logs/MA5600T/log$ip.log &");

}

?>