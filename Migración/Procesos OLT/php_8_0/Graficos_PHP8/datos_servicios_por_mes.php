<?php
date_default_timezone_set('America/Santiago');
include ('/var/www/procesos/php/conexion/conexion_db.php');

$conn = mysqli_connect($host144_geret,$user144_geret,$pass144_geret,"Aden") or die("error de conexion: ".mysqli_connect_error()); // Migración PHP 8.0: mysql_* eliminado en PHP 7
// Migración PHP 8.0: seleccion de BD integrada en mysqli_connect (4o parametro)

//mysqli_query($conn, 'TRUNCATE TABLE OLT_TRAFICO_SERVICIOS_MES');

function data_first_month_day($month) {
    $month = $month;
    $year = date('Y');
    return date('Y-m-d', mktime(0,0,0, $month, 1, $year));
}

function data_last_month_day($month) {
    $month = $month;
    $year = date('Y');
    $day = date('d', mktime(0,0,0, $month+1, 0, $year));
    return date('Y-m-d', mktime(0,0,0, $month, $day, $year));
}

$anio = date('Y');
$mes = date('F');

if($mes == 'January'){$mes = '01';}
if($mes == 'February'){$mes = '02';}
if($mes == 'March'){$mes = '03';}
if($mes == 'April'){$mes = '04';}
if($mes == 'May'){$mes = '05';}
if($mes == 'June'){$mes = '06';}
if($mes == 'July'){$mes ='07';}
if($mes == 'August'){$mes = '08';}
if($mes == 'September'){$mes = '09';}
if($mes == 'October'){$mes = '10';}
if($mes == 'November'){$mes = '11';}
if($mes == 'December'){$mes = '12';}

$fecha_first1 = data_first_month_day(date($mes));
$fecha_last1 = data_last_month_day(date($mes));

//$anio = '2018';
//$mes = '10';
//$fecha_first1 = '2018-10-01';
//$fecha_last1 = '2018-10-31';

$servicios = array('3Play Empresas','3Play Personas','3Play Empresas/Personas','No Tiene');
foreach($servicios as $servicio){ 
    $query_up = "SELECT DISTINCT
            SUM(REPLACE(OLT_TRAFICOGPON.up_mbps,',','')) AS total,
            OLT_SERVER.pop
            FROM
            OLT_TRAFICOGPON
            INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON.ip_equipo = OLT_SERVER.ip
            INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON.`port` = OLT_PUERTOS_UPLINKS.puerto
            WHERE
            OLT_TRAFICOGPON.fecha BETWEEN '$fecha_first1' AND '$fecha_last1'
            AND OLT_TRAFICOGPON.up_mbps <> '0.000'
            AND OLT_TRAFICOGPON.up_mbps <> '0.001'
            AND OLT_SERVER.pop = '$servicio'";
    $result_up = mysqli_query($conn, $query_up) or die("Error $query" . mysqli_error($conn));
    $row_up = mysqli_fetch_array($result_up, MYSQLI_NUM);

    $query_down = "SELECT DISTINCT
            SUM(REPLACE(OLT_TRAFICOGPON.down_mbps,',','')) AS total,
            OLT_SERVER.pop
            FROM
            OLT_TRAFICOGPON
            INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON.ip_equipo = OLT_SERVER.ip
            INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON.`port` = OLT_PUERTOS_UPLINKS.puerto
            WHERE
            OLT_TRAFICOGPON.fecha BETWEEN '$fecha_first1' AND '$fecha_last1'
            AND OLT_TRAFICOGPON.down_mbps <> '0.000'
            AND OLT_TRAFICOGPON.down_mbps <> '0.001'
            AND OLT_SERVER.pop = '$servicio'";
    $result_down = mysqli_query($conn, $query_down) or die("Error $query" . mysqli_error($conn));
    $row_down = mysqli_fetch_array($result_down, MYSQLI_NUM);

    if($row_up[1]){
        $serv = $row_up[1];
        $up = round($row_up[0],2);
        $down = round($row_down[0],2);
        $query_insert_mes = "INSERT INTO OLT_TRAFICO_SERVICIOS_MES (servicio,up,down,mes,anio)
                                VALUES ('$serv','$up','$down','$mes','$anio')"; 
        mysqli_query($conn, $query_insert_mes) or die ("crea_grafico_3play.php $query_insert_mes");
    }
}


?>