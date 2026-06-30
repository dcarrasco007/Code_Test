<?php
echo "Inicio Proceso 1....\n";
//include ('../../../conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
echo "Inicio Proceso 2....\n";

$fecha = date('Y-m-d', strtotime('-1 day'));
//$fecha = '2024-11-22';
/* for ($i=1; $i <=22 ; $i++) { 
    if($i<=9){
        $dia='0'.$i;
    }else{
        $dia=$i;
    }
    $fecha='2024-04-'.$dia; */
    $sql_ip = "SELECT
            OLT_SERVER.`server`,
            OLT_SERVER.ip,
            OLT_SERVER.modelo
            FROM 
            OLT_SERVER";
    $result = $mysqli->query($sql_ip) or die("error 1");
    $semana=date('W');
    echo "Fecha: ".$fecha."\n";
    
    while($row = $result->fetch_array(MYSQLI_NUM)){

        $query2 = "SELECT
                    OLT_TRAFICO_UPLINK_HORA.ip,  MAX(OLT_TRAFICO_UPLINK_HORA.peak),
                    MAX(OLT_TRAFICO_UPLINK_HORA.peak)/1000 AS PEAK_MB,
                    DATE( OLT_TRAFICO_UPLINK_HORA.fecha ) 
                    FROM
                    OLT_TRAFICO_UPLINK_HORA 
                    WHERE
                    OLT_TRAFICO_UPLINK_HORA.ip='$row[1]'
                    AND DATE( OLT_TRAFICO_UPLINK_HORA.fecha ) ='$fecha'";
        $resp2 = $mysqli->query($query2) or die("error 2 $query");
        $row2 = $resp2->fetch_array(MYSQLI_NUM);
        $query3 = "INSERT INTO Aden.OLT_TRAFICO_PEAK_DAY
        (server, ip, modelo, peak_kb, peak_mb, fecha, week)
        VALUES ('$row[0]','$row[1]','$row[2]','$row2[1]','$row2[2]','$fecha',$semana)";
        $mysqli->query($query3) or die("error 2 $query3");


    }
//}
echo "Fin Proceso....\n";
?>
?>