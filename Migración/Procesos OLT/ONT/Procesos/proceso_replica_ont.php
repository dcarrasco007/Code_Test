<?php
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

mysqli_query($mysqli,"TRUNCATE TABLE OLT_ONT_EQUIPOS2");//ONT Online (Si hay ONT con totales con data y online en 0 ejecutar shell_ont_repaso_equipos_detalle.php)
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ONT_EQUIPOS");// ONT Totales

//$fecha = date("Y-m-d");
echo $fecha = date("Y-m-d");
//sumo 1 día
/* $fecha= date("d-m-Y",strtotime($fecha_actual."+ 1 days")); */
$week  = date("W");
$year  = date("Y"); 
//$equipo="OLT-DURZUA-1";

/*$sql_ip = "SELECT OLT_ONT_EQUIPOS3.`server`,OLT_ONT_EQUIPOS3.ip,OLT_ONT_EQUIPOS3.region,OLT_ONT_EQUIPOS3.ont_online,
            OLT_ONT_EQUIPOS3.ont_total,OLT_ONT_EQUIPOS3.puerto,OLT_ONT_EQUIPOS3.fecha
            FROM
            OLT_ONT_EQUIPOS3 WHERE OLT_ONT_EQUIPOS3.`server`='$equipo'";*/
$sql_ip = "SELECT OLT_ONT_EQUIPOS3.`server`,OLT_ONT_EQUIPOS3.ip,OLT_ONT_EQUIPOS3.region,OLT_ONT_EQUIPOS3.ont_online,
            OLT_ONT_EQUIPOS3.ont_total,OLT_ONT_EQUIPOS3.puerto,OLT_ONT_EQUIPOS3.fecha
            FROM
            OLT_ONT_EQUIPOS3";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row3 = $result->fetch_array(MYSQLI_NUM)) {
    $sql_insert3 = "INSERT INTO OLT_ONT_EQUIPOS (server,ip,region,ont_online,ont_total,puerto,fecha) 
                   VALUES ('$row3[0]','$row3[1]','$row3[2]','$row3[3]','$row3[4]','$row3[5]','$row3[6]')";
    $sql_insert4 = "INSERT INTO OLT_ONT_EQUIPOS2 (server,ip,region,ont_online,ont_total,puerto,fecha) 
                   VALUES ('$row3[0]','$row3[1]','$row3[2]','$row3[3]','$row3[4]','$row3[5]','$row3[6]')";
    $sql_insert5 = "INSERT INTO OLT_ONT_HISTORICO
                   (equipo,ip,region,puerto,ont_online,ont_total,week,year,fecha)
                   VALUES
                   ('$row3[0]','$row3[1]','$row3[2]','$row3[5]','$row3[3]','$row3[4]','$week','$year','$fecha')";

    $result5 = $mysqli->query($sql_insert5) or die("error $sql_insert5");
    $result3 = $mysqli->query($sql_insert3) or die("error $sql_insert3");
    $result4 = $mysqli->query($sql_insert4) or die("error $sql_insert4"); 

}   
mysqli_close($mysqli);
?>