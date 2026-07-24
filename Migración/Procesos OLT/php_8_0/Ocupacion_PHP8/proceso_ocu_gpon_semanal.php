<?php
date_default_timezone_set('America/Santiago');
//include ('../../conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}

$fechahoy=date('Y-m-d');
$semana=date('W');
//lunes de la semana pasada
$semanaAnterior= date("Y-m-d",strtotime($fechahoy."- 1 week")); 
//domingo de la semana pasada
$diaanterior= date("Y-m-d",strtotime($fechahoy."- 1 Days")); 
$semanamenos=$semana-1; 
//año
$año=date('Y');
$f_inicio=$semanaAnterior;
$f_final=$diaanterior;
$semana=$semanamenos;


/* $f_inicio = '2024-03-11';
$f_final = '2024-03-17';
$semana = '11';
$mes = '03';
$anio = '2024'; */
echo $semana;
$query = "SELECT
        OLT_PEAKS_TRAFICO_GPON.equipo,
        OLT_PEAKS_TRAFICO_GPON.ip,
        OLT_PEAKS_TRAFICO_GPON.modelo,
        OLT_PEAKS_TRAFICO_GPON.puerta,
        OLT_PEAKS_TRAFICO_GPON.capacidad,
        MAX(OLT_PEAKS_TRAFICO_GPON.diario) AS maximo,
        OLT_PEAKS_TRAFICO_GPON.pdiario,
        OLT_PEAKS_TRAFICO_GPON.fecha_diario
        FROM
        OLT_PEAKS_TRAFICO_GPON
        WHERE
        OLT_PEAKS_TRAFICO_GPON.diario NOT LIKE '%,%'
        AND OLT_PEAKS_TRAFICO_GPON.fecha_diario BETWEEN '$f_inicio' AND '$f_final'
        GROUP BY OLT_PEAKS_TRAFICO_GPON.equipo,OLT_PEAKS_TRAFICO_GPON.puerta";
$resp = $mysqli->query($query) or die("error 2 $query");                    

while($row = $resp->fetch_array(MYSQLI_NUM)){
        
    $query1 = "SELECT
            OLT_PEAKS_TRAFICO_GPON.pdiario,
            OLT_PEAKS_TRAFICO_GPON.fecha_diario
            FROM
            OLT_PEAKS_TRAFICO_GPON
            WHERE
            OLT_PEAKS_TRAFICO_GPON.diario = '$row[5]'
            AND
            OLT_PEAKS_TRAFICO_GPON.equipo = '$row[0]'
            AND
            OLT_PEAKS_TRAFICO_GPON.puerta = '$row[3]'
            AND OLT_PEAKS_TRAFICO_GPON.fecha_diario BETWEEN '$f_inicio' AND '$f_final'";
            
            //AND MONTH(OLT_PEAKS_TRAFICO_GPON.fecha_diario) = '$mes'
            
    $resp1 = $mysqli->query($query1) or die("error 2 $query1");   
    $row1 = $resp1->fetch_array(MYSQLI_NUM);
    
    if($row1[0]){
        $query3 = "INSERT INTO OLT_PEAKS_TRAFICO_GPON_FINAL_WEEK
                   (equipo,ip,modelo,puerta,capacidad,diario,pdiario,fecha_diario,week) 
                   VALUES
                   ('$row[0]','$row[1]','$row[2]','$row[3]','$row[4]','$row[5]','$row1[0]','$row1[1]','$semana')";
        $mysqli->query($query3) or die("error 2 $query3");
    } 
}


?>