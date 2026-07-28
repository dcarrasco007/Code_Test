<?php
date_default_timezone_set('America/Santiago');
//echo 'INICIO: '.date('Y-m-d H:i:s');
//include ('/var/www/html/conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
$anio=date("Y");
//$anio=2023;
$fecha=date("Y-m-d");

$semana=date('W');
//$semana=18;
$semana=$semana-1;

    echo ' || INICIO: '.date('Y-m-d H:i:s');
    

    $semanaActual=date('W');
    //$semanaActual=17;
    $diferencia=$semana-$semanaActual;//30-32=-2

    $day = date('w');
    $day=$day-1;
    //$day=7;
    $dia1SemanaActual = date('d-m-'.$anio, strtotime('-'.($day).' days'));
    $dia01 = date( $anio."-m-d", strtotime( $dia1SemanaActual." ".$diferencia." week" ) );
    $dia02 = date( $anio."-m-d", strtotime( $dia01." + 1 day" ) );
    $dia03 = date( $anio."-m-d", strtotime( $dia02." + 1 day" ) );
    $dia04 = date( $anio."-m-d", strtotime( $dia03." + 1 day" ) );
    $dia05 = date( $anio."-m-d", strtotime( $dia04." + 1 day" ) );
    $dia06 = date( $anio."-m-d", strtotime( $dia05." + 1 day" ) );
    $dia07 = date( "Y-m-d", strtotime( $dia06." + 1 day" ) );

    $dia1 = date( "d-m-".$anio, strtotime( $dia1SemanaActual." ".$diferencia." week" ) );
    $dia2 = date( "d-m-".$anio, strtotime( $dia01." + 1 day" ) );
    $dia3 = date( "d-m-".$anio, strtotime( $dia02." + 1 day" ) );
    $dia4 = date( "d-m-".$anio, strtotime( $dia03." + 1 day" ) );
    $dia5 = date( "d-m-".$anio, strtotime( $dia04." + 1 day" ) );
    $dia6 = date( "d-m-".$anio, strtotime( $dia05." + 1 day" ) );
    $dia7 = date( "d-m-Y", strtotime( $dia06." + 1 day" ) ); 
/*     $dia01 = "2025-04-21";
    $dia02 = "2025-04-22";
    $dia03 = "2025-04-23";
    $dia04 = "2025-04-24";
    $dia05 = "2025-04-25";
    $dia06 = "2025-04-26";
    $dia07 = "2025-04-27"; 

    $dia1 = "21-04-2025";
    $dia2 = "22-04-2025";
    $dia3 = "23-04-2025";
    $dia4 = "24-04-2025";
    $dia5 = "25-04-2025";
    $dia6 = "26-04-2025";
    $dia7 = "27-04-2025"; */

    //echo "Lunes0: ".$dia01." Domingo0: ".$dia07."\n";
    echo "Lunes1: ".$dia1.' Domingo1: '.$dia7."\n";
    
    $entra = 0;
    //echo $tabla;
    $query2 = "SELECT
    s.server,
    p.puerta,
    MAX(CASE WHEN p.fecha = '$dia01' THEN p.potencia END) AS LUNES,
    MAX(CASE WHEN p.fecha = '$dia02' THEN p.potencia END) AS MARTES,
    MAX(CASE WHEN p.fecha = '$dia03' THEN p.potencia END) AS MIERCOLES,
    MAX(CASE WHEN p.fecha = '$dia04' THEN p.potencia END) AS JUEVES,
    MAX(CASE WHEN p.fecha = '$dia05' THEN p.potencia END) AS VIERNES,
    MAX(CASE WHEN p.fecha = '$dia06' THEN p.potencia END) AS SABADO,
    MAX(CASE WHEN p.fecha = '$dia07' THEN p.potencia END) AS DOMINGO
FROM
    OLT_SERVER s
LEFT JOIN (
    SELECT 
        equipo,
        puerta,
        DATE(fecha) AS fecha,
        CONCAT(CAST(potencia_tx AS DECIMAL(10,2)), '|', CAST(potencia_rx AS DECIMAL(10,2))) AS potencia
    FROM 
        OLT_POTENCIA_OPTICA_UPLINK
    WHERE 
        fecha >= '$dia01 00:00:00' AND fecha < '$dia07 23:59:59'
        AND week = $semana
) p
    ON s.server = p.equipo
GROUP BY
    s.server, p.puerta;";

    $resp2 = $mysqli->query($query2) or die("error 3 $query2");

    echo ' --- Querys ejecutadas ---> '.date('Y-m-d H:i:s').'----------->';

    //$prome1=buscapromedio(OLT-MALLTOBALABAPCS-1,2021-08-11);
    $query4 = "INSERT INTO OLT_TABLA_POTENCIA_SEMANA (equipo,puerta,lunes_tx,lunes_rx,martes_tx,martes_rx,miercoles_tx,miercoles_rx,jueves_tx,jueves_rx,viernes_tx,viernes_rx,sabado_tx,sabado_rx,domingo_tx,domingo_rx,fecha,week) 
                VALUES ('EQUIPO','PUERTA','$dia1','$dia1','$dia2','$dia2','$dia3','$dia3','$dia4','$dia4','$dia5','$dia5','$dia6','$dia6','$dia7','$dia7','$fecha','$semana')";
                
                $resp4 = $mysqli->query($query4) or die("error 4 $query4");
    while($row = $resp2->fetch_array(MYSQLI_NUM)){
        $lunes=explode("|",$row[2]);
        $lunes_tx=$lunes[0].' dBm';
        $lunes_rx=$lunes[1].' dBm';

        $martes=explode("|",$row[3]);
        
        $martes_tx=$martes[0].' dBm';
        $martes_rx=$martes[1].' dBm';

        $miercoles=explode("|",$row[4]);
        $miercoles_tx=$miercoles[0].' dBm';
        $miercoles_rx=$miercoles[1].' dBm';

        $jueves=explode("|",$row[5]);
        $jueves_tx=$jueves[0].' dBm';
        $jueves_rx=$jueves[1].' dBm';

        $viernes=explode("|",$row[6]);
        $viernes_tx=$viernes[0].' dBm';
        $viernes_rx=$viernes[1].' dBm';

        $sabado=explode("|",$row[7]);
        $sabado_tx=$sabado[0].' dBm';
        $sabado_rx=$sabado[1].' dBm';

        $domingo=explode("|",$row[8]);
        $domingo_tx=$domingo[0].' dBm';
        $domingo_rx=$domingo[1].' dBm';
        
                $query3 = "INSERT INTO OLT_TABLA_POTENCIA_SEMANA (equipo,puerta,lunes_tx,lunes_rx,martes_tx,martes_rx,miercoles_tx,miercoles_rx,jueves_tx,jueves_rx,viernes_tx,viernes_rx,sabado_tx,sabado_rx,domingo_tx,domingo_rx,fecha,week) 
                VALUES ('$row[0]','$row[1]','$lunes_tx','$lunes_rx','$martes_tx','$martes_rx','$miercoles_tx','$miercoles_rx','$jueves_tx','$jueves_rx','$viernes_tx','$viernes_rx','$sabado_tx','$sabado_rx','$domingo_tx','$domingo_rx','$fecha','$semana')";
                
                $resp3 = $mysqli->query($query3) or die("error 3 $query3");
        $entra++;
    }   
    echo 'Se guardaron: '.$entra.' registros. de la semana: '.$semana;  

?>
