<?php
include ('/var/www/html/OLT/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
$fecha=date('Y-m-d');
//$fecha='2023-01-30';
$anio=date('Y');
$week = date('W', strtotime('-1 week'));
//$week='04';
echo "\nAño: ".$anio." Semana: ".$week."\n";
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
    $query = "SELECT server,ip,week, AVG(peack_trafico)
    FROM OLT_TRAFICO_UPLINK_MA5800_X15_DAY
    WHERE server='$row[0]' AND week ='$week' AND YEAR (fecha)='$anio'"; 
    /* $query = "SELECT server,ip,week, AVG(promedio_trafico)
              FROM OLT_TRAFICO_UPLINK_MA5800_X15_DAY
              WHERE server='$row[0]' AND week ='$week'"; */
    $resp = $mysqli->query($query) or die("error 2 $query");                    
    $row1 = $resp->fetch_array(MYSQLI_NUM);
        if($row1[0]){
            $promedio=round($row1[3],1);
            $query3 = "INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15_WEEK (server, ip, promedio_trafico, fecha, week) VALUES
                    ('$row1[0]','$row1[1]','$promedio','$fecha','$row1[2]')";
            $mysqli->query($query3) or die("error 2 $query3");
        }else{
            echo 'sin datos para insertar';
        } 
}
?>