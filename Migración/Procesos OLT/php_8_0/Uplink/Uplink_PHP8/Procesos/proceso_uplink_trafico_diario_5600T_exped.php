<?php
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
echo date("Y-m-d H:i:s")."\n";
$server=$argv[1];
$ip=$argv[2];
$fecha=$argv[3];
//echo $server=$_GET['server'];
//echo $ip=$_GET['ip'];
/* $ip = trim(json_decode($_GET['ip']), '"');
$fecha = date('Y-m-d', strtotime('-1 day'));
 */
$semana=date('W');

    $query2 = "SELECT DISTINCT (puerto) 
    FROM OLT_TRAFICO_UPLINK_MA5600T
    WHERE server='$server' AND DATE(fecha) ='$fecha'";
    $resp2 = $mysqli->query($query2) or die("error 2 $query");
    $promedios=0;
    $peak=0;
    $promediosUP=0;
    $peakUP=0;
    $week=0;
    while($row2 = $resp2->fetch_array(MYSQLI_NUM)){
        $query3 = "SELECT server,ip,week, AVG(trafico),AVG(trafico_up)
            FROM OLT_TRAFICO_UPLINK_MA5600T
            WHERE server='$server' AND DATE(fecha) ='$fecha' AND puerto = '$row2[0]'";
            $resp3 = $mysqli->query($query3) or die("error 2 $query"); 
            $row3 = $resp3->fetch_array(MYSQLI_NUM);
            $promedios=($row3[3]+$promedios);
            $promediosUP=($row3[4]+$promediosUP);
            $week=$row3[2];

             // Gurda promedio día por puerta
             $valorDown_1=$row3[3]/1024;
             $valorDown_1=round($valorDown_1, 2);
            $valorDown=number_format($valorDown_1,2);
             $valorUp_1=$row3[4]/1024;
             $valorUp_1=round($valorUp_1, 2);
            $valorUp_1=number_format($valorUp_1,2);
             $sqlpromedio="INSERT INTO Aden.OLT_TRAFICO_DIA_PUERTA
             ( equipo, ip, puerta, promedio_trafico_down, fecha, week, promedio_trafico_up)
             VALUES('$row3[0]', '$row3[1]', '$row2[0]', $valorDown_1, '$fecha', $row3[2],$valorUp_1)";
             $resp4 = $mysqli->query($sqlpromedio) or die("error 2 $sqlpromedio");

            // Tablas Ocupacion
            $valorDown=$row3[3]/1024;
            $valorDown=round($valorDown, 2);
            $valorDown=number_format($valorDown,2);

            $valorUP=$row3[4]/1024;
            $valorUP=round($valorUP, 2);
            $valorUP=number_format($valorUP,2);
            $sql_trafico = "INSERT INTO OLT_TRAFICOGPON2 (ip_equipo,port,up_mbps,down_mbps,fecha) 
                        VALUES ('$row3[1]','$row2[0]','$valorUP','$valorDown','$fecha')";
            $resp6 = $mysqli->query($sql_trafico) or die("error 2 $sql_trafico");
            $sql_trafico1 = "INSERT INTO OLT_TRAFICOGPON (ip_equipo,port,up_mbps,down_mbps,fecha) 
                            VALUES ('$row3[1]','$row2[0]','$valorUP','$valorDown','$fecha')";
            $resp7 = $mysqli->query($sql_trafico1) or die("error 2 $sql_trafico1");
            // Fin tablas Ocupacion

            $query5 = "SELECT server,ip,week, MAX(trafico), MAX(trafico_up)
            FROM OLT_TRAFICO_UPLINK_MA5600T
            WHERE server='$server' AND DATE(fecha) ='$fecha' AND puerto = '$row2[0]'";
            $resp5 = $mysqli->query($query5) or die("error 2 $query5"); 
            $row5 = $resp5->fetch_array(MYSQLI_NUM);
            $peak=($row5[3]+$peak);
            $peakUP=($row5[4]+$peakUP);
    }
    if($promedios>=0){
        $promedios=round($promedios,1);
        $peak=round($peak,1);
        $query3 = "INSERT INTO OLT_TRAFICO_UPLINK_MA5600T_DAY (server, ip, promedio_trafico, peack_trafico, fecha, week, promedio_trafico_up, peack_trafico_up) VALUES
                ('$server','$ip','$promedios','$peak','$fecha','$week','$promediosUP','$peakUP')";
        $mysqli->query($query3) or die("error 2 $query3");
    }else{
        echo 'sin datos para insertar';
    }
    echo date("Y-m-d H:i:s")."\n";
?>