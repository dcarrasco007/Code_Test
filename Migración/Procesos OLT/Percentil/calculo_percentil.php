<?php
include ('/var/www/html/contingencia/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
//echo date("Y-m-d H:i:s")."\n";
//$server='OLT-DURZUA-10';

// Obtener el lunes de la semana pasada
//echo $lunes_pasado = date('Y-m-d', strtotime('last monday -6 days'));
//$lunes_pasado = strtotime('last monday');
echo$lunes_pasado = date('Y-m-d', strtotime("-7 days"));
//$lunes_pasado ='2025-05-19';
// Obtener el domingo de la semana pasada
echo "\n:".$domingo_pasado = date('Y-m-d', strtotime('last sunday'));
//$domingo_pasado ='2025-05-25';
$semana=date('W');
$semana=$semana-1;
//$semana=21;
//die("Fin ");
$queryolt = "SELECT OLT_SERVER.`server`,OLT_SERVER.ip FROM OLT_SERVER WHERE server<>'OLT-RECREO-2'AND
server<>'OLT-CNT-2' AND
server<>'OLT-SBERNARDO-1' AND
server<>'OLT-VITACURA-1'AND
server<>'OLT1-1-LABONNET-5800'AND
server<>'OLT1-2-LABONNET-5600'";
$respOLT = $mysqli->query($queryolt) or die("error 2 $queryolt");

while($row = $respOLT->fetch_array(MYSQLI_NUM)){
    $server=$row[0];
    $ip=$row[1];
    echo $server."\n";

    $query2 = "SELECT peak
    FROM OLT_TRAFICO_UPLINK_HORA
    WHERE server='$server' AND DATE(fecha) BETWEEN '$lunes_pasado' AND '$domingo_pasado'";
    $resp2 = $mysqli->query($query2) or die("error 2 $query");

    while($row2 = $resp2->fetch_array(MYSQLI_NUM)){
        $data[]=$row2[0];
    }
    //echo "Data del día:\n";
    $percentile_98_v2 = percentile98_v2($data);
    sort($data);
    $i=count($data)-1;
    //print_r($data);
    //echo "contador i:".$i."\n";

    $peak=$data[$i];

    
    $query3 = "SELECT OLT_PUERTAS_UPLINKS_GB.capacidad_total FROM OLT_PUERTAS_UPLINKS_GB WHERE OLT_PUERTAS_UPLINKS_GB.olt='$server' AND OLT_PUERTAS_UPLINKS_GB.capacidad_total<>'1Gb'";
    $resp3 = $mysqli->query($query3) or die("error 2 $query3");

    $row3 = $resp3->fetch_array(MYSQLI_NUM);
    $capacidad=$row3[0];
    if($capacidad==''){
        $capacidad='1Gb';
    }
    // CALCULOD E PORCENTAJES
    if($capacidad!='1Gb'){
    $capacidadLimpia=substr($capacidad, 0, -2);
    $capacidadLimpia=$capacidadLimpia/2;
    
    $capacidad=$capacidadLimpia."Gb";
    }else{
        $capacidad='1Gb';
        $capacidadLimpia=1;

    }
    
    // a GB
    $percentile_98_v2GB=round($percentile_98_v2/1000000,1);
    echo "\npercentilGB: ".$percentile_98_v2GB."\n";
    $peak_GB=round($peak/1000000,1);
    echo "peakGB: ".$peak_GB."\n";
    $porcentaje98=round(($percentile_98_v2GB*100)/$capacidadLimpia,1);
    $porcentaje98=$porcentaje98."%";
    $porcentajePeak=round(($peak_GB*100)/$capacidadLimpia,1);
    $porcentajePeak=$porcentajePeak."%";

    echo $query4 = "INSERT INTO OLT_PERCENTIL_98 (olt,ip,capacidad,percentil98,peak,week,fecha_registro,porcentaje98,porcentajePeak,fecha_inicio_semana) 
    VALUES ('$server','$ip','$capacidad',$percentile_98_v2,$peak,$semana, NOW(), '$porcentaje98','$porcentajePeak','$lunes_pasado')";
    $resp4 = $mysqli->query($query4) or die("error 4 $query4");

    echo "\n";
    unset($data);
}
$mysqli->close();
    
    
function percentile98_v2($data) {
        // Ordenar los datos de menor a mayor
        sort($data);
        
        // Calcular el índice del percentil 95
        $percentile_index =(98/100)*count($data);
        echo "\n indice: ".$percentile_index."\n";
        // Si el índice es un número entero, tomar el valor exacto
        if (is_int($percentile_index)) {
            $percentile_value = $data[$percentile_index - 1];
        } else {
            // Si el índice no es un número entero, interpolar entre los valores cercanos
            
            $percentile_value = $data[round($percentile_index)-1];
        }
        
        return $percentile_value;
}

