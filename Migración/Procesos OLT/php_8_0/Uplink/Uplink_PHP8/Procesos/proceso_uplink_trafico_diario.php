<?php
include ('../../../conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}

$fecha = date('Y-m-d', strtotime('-1 day'));
//$fecha = '2021-08-11';
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

    $query2 = "SELECT DISTINCT (puerto) 
    FROM OLT_TRAFICO_UPLINK_MA5800_X15
    WHERE server='$row[0]' AND DATE(fecha) ='$fecha'";
    $resp2 = $mysqli->query($query2) or die("error 2 $query");
    $promedios=0;
    $peak=0;
    $week=0;
    while($row2 = $resp2->fetch_array(MYSQLI_NUM)){
        $query3 = "SELECT server,ip,week, AVG(trafico)
            FROM OLT_TRAFICO_UPLINK_MA5800_X15
            WHERE server='$row[0]' AND DATE(fecha) ='$fecha' AND puerto = '$row2[0]'";
            $resp3 = $mysqli->query($query3) or die("error 2 $query"); 
            $row3 = $resp3->fetch_array(MYSQLI_NUM);
            $promedios=($row3[3]+$promedios);
            $week=$row3[2];

            $query5 = "SELECT server,ip,week, MAX(trafico)
            FROM OLT_TRAFICO_UPLINK_MA5800_X15
            WHERE server='$row[0]' AND DATE(fecha) ='$fecha' AND puerto = '$row2[0]'";
            $resp5 = $mysqli->query($query5) or die("error 2 $query5"); 
            $row5 = $resp5->fetch_array(MYSQLI_NUM);
            $peak=($row5[3]+$peak);
    }
    if($promedios>0){
        $promedios=round($promedios,1);
        $peak=round($peak,1);
        $query3 = "INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15_DAY (server, ip, promedio_trafico, peack_trafico, fecha, week) VALUES
                ('$row[0]','$row[1]','$promedios','$peak','$fecha','$week')";
        $mysqli->query($query3) or die("error 2 $query3");
    }else{
        echo 'sin datos para insertar';
    }

}
?>