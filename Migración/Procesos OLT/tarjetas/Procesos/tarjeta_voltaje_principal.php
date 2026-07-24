<?php
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----Hora
$sql_hora = "SELECT NOW()";
$resultHora = $mysqli->query($sql_hora) or die("error $sql_hora");
$hora = $resultHora->fetch_array(MYSQLI_NUM);
echo "Inicio: ".$hora[0]."\n";
//-----Fin
mysqli_query($mysqli,"TRUNCATE TABLE OLT_VOLTAJE_TARJETA");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_TARJETA_CANTIDAD");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_DETALLE_TARJETA");

$fecha = date("Y-m-d");

$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER';
$result = $mysqli->query($sql_ip) or die("error $sql_ip");

while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    $ip = $row[0];
    //$ip = '10.99.26.150';
    $server = $row[1];
    //$server = 'OLT-LOSQUILLAYESPCS-1';
    $region = $row[2];
    $region= str_replace(" ","_",$region);
    shell_exec("nohup php -f /u01/crontab127/OLT/tarjetas/Procesos/tarjeta_voltaje_exped.php $ip $server $region > /u01/crontab127/OLT/tarjetas/Procesos/logs/$ip.log &");
}    
//-----Hora
$sql_hora = "SELECT NOW()";
$resultHora = $mysqli->query($sql_hora) or die("error $sql_hora");
$hora = $resultHora->fetch_array(MYSQLI_NUM);
echo "Inicio: ".$hora[0]."\n";
//-----Fin     
mysqli_close($mysqli);
?>