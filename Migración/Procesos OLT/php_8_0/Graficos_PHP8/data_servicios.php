<?php
date_default_timezone_set('America/Santiago');
include ('/var/www/procesos/php/conexion/conexion_db.php');

$conn = mysqli_connect($host144_geret,$user144_geret,$pass144_geret,"Aden") or die("error de conexion: ".mysqli_connect_error()); // Migración PHP 8.0: mysql_* eliminado en PHP 7
// Migración PHP 8.0: seleccion de BD integrada en mysqli_connect (4o parametro)

mysqli_query($conn, 'TRUNCATE TABLE OLT_TRAFICO_SERVICIOS');

$servicios = array('3Play Empresas','3Play Personas','3Play Empresas/Personas','No Tiene');

$fecha_first = data_first_month_day(date('m'));
$fecha_last = data_last_month_day(date('m'));

function data_last_month_day($month) {
    $month = $month;
    $year = date('Y');
    $day = date('d', mktime(0,0,0, $month+1, 0, $year));
    return date('Y-m-d', mktime(0,0,0, $month, $day, $year));
}

function data_first_month_day($month) {
    $month = $month;
    $year = date('Y');
    return date('Y-m-d', mktime(0,0,0, $month, 1, $year));
}

$year=date("Y");
$month=date("m");
$day=date("d"); 
            
# Obtenemos el numero de la semana
$semana=date("W",mktime(0,0,0,$month,$day,$year));            
# Obtenemos el día de la semana de la fecha dada
$diaSemana=date("w",mktime(0,0,0,$month,$day,$year));            
# el 0 equivale al domingo...
if($diaSemana==0)
    $diaSemana=7;            
# A la fecha recibida, le restamos el dia de la semana y obtendremos el lunes
$primerDia=date("Y-m-d",mktime(0,0,0,$month,$day-$diaSemana+1,$year));
 
# A la fecha recibida, le sumamos el dia de la semana menos siete y obtendremos el domingo
$ultimoDia=date("Y-m-d",mktime(0,0,0,$month,$day+(7-$diaSemana),$year));

foreach($servicios as $servicio){   
    $query_7dias_down = "SELECT DISTINCT
                    SUM(REPLACE(OLT_TRAFICOGPON.down_mbps,',','')) AS total,
                    OLT_SERVER.pop
                    FROM
                    OLT_TRAFICOGPON
                    INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON.ip_equipo = OLT_SERVER.ip
                    INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON.`port` = OLT_PUERTOS_UPLINKS.puerto
                    WHERE
                    OLT_TRAFICOGPON.fecha BETWEEN '$primerDia' AND '$ultimoDia'
                    AND OLT_TRAFICOGPON.down_mbps <> '0.000'
                    AND OLT_TRAFICOGPON.down_mbps <> '0.001'
                    AND OLT_SERVER.pop = '$servicio'";
    $result_down = mysqli_query($conn, $query_7dias_down) or die("Error $query_7dias_down" . mysqli_error($conn));
    $row_down = mysqli_fetch_array($result_down, MYSQLI_NUM);

    $query_7dias_up = "SELECT DISTINCT
                    SUM(REPLACE(OLT_TRAFICOGPON.up_mbps,',','')) AS total,
                    OLT_SERVER.pop
                    FROM
                    OLT_TRAFICOGPON
                    INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON.ip_equipo = OLT_SERVER.ip
                    INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON.`port` = OLT_PUERTOS_UPLINKS.puerto
                    WHERE
                    OLT_TRAFICOGPON.fecha BETWEEN '$primerDia' AND '$ultimoDia'
                    AND OLT_TRAFICOGPON.up_mbps <> '0.000'
                    AND OLT_TRAFICOGPON.up_mbps <> '0.001'
                    AND OLT_SERVER.pop = '$servicio'";
    $result_up = mysqli_query($conn, $query_7dias_up) or die("Error $query_7dias_up" . mysqli_error($conn));
    $row_up = mysqli_fetch_array($result_up, MYSQLI_NUM);
    
    $up = round($row_up[0], 2);
    $down = round($row_down[0], 2);
    $serv = $row_up[1];
    $date = date('Y-m-d');
    
    if($serv){
        $query_insert_7dias = "INSERT INTO OLT_TRAFICO_SERVICIOS (servicio,up,down,rango,fecha)
                               VALUES ('$serv','$up','$down','Semana_actual','$date')"; 
        mysqli_query($conn, $query_insert_7dias) or die ("error data_servicios.php $query_insert_7dias");
    }
    
//---------------------------------------------------------------------------------------------------------

    $query_30dias_down = "SELECT
                    SUM(REPLACE(OLT_TRAFICOGPON.down_mbps,',','')) AS total,
                    OLT_SERVER.pop
                    FROM
                    OLT_TRAFICOGPON
                    INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON.ip_equipo = OLT_SERVER.ip
                    INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON.`port` = OLT_PUERTOS_UPLINKS.puerto
                    WHERE
                    OLT_TRAFICOGPON.fecha BETWEEN '$fecha_first' AND '$fecha_last'
                    AND OLT_TRAFICOGPON.down_mbps <> '0.000'
                    AND OLT_TRAFICOGPON.down_mbps <> '0.001'
                    AND OLT_SERVER.pop = '$servicio'";
    $result_down30 = mysqli_query($conn, $query_30dias_down) or die("Error $query_30dias_down" . mysqli_error($conn));
    $row_down30 = mysqli_fetch_array($result_down30, MYSQLI_NUM);
    
    $query_30dias_up = "SELECT
                    SUM(REPLACE(OLT_TRAFICOGPON.up_mbps,',','')) AS total,
                    OLT_SERVER.pop
                    FROM
                    OLT_TRAFICOGPON
                    INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON.ip_equipo = OLT_SERVER.ip
                    INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON.`port` = OLT_PUERTOS_UPLINKS.puerto
                    WHERE
                    OLT_TRAFICOGPON.fecha BETWEEN '$fecha_first' AND '$fecha_last'
                    AND OLT_TRAFICOGPON.up_mbps <> '0.000'
                    AND OLT_TRAFICOGPON.up_mbps <> '0.001'
                    AND OLT_SERVER.pop = '$servicio'";
    $result_up30 = mysqli_query($conn, $query_30dias_up) or die("Error $query_30dias_up" . mysqli_error($conn));
    $row_up30 = mysqli_fetch_array($result_up30, MYSQLI_NUM);
    
    $up30 = round($row_up30[0], 2);
    $down30 = round($row_down30[0], 2);
    $serv30 = $row_up30[1];
    
    if($serv30){
        $query_insert_30dias = "INSERT INTO OLT_TRAFICO_SERVICIOS (servicio,up,down,rango,fecha)
                               VALUES ('$serv30','$up30','$down30','Mes_actual','$date')"; 
        mysqli_query($conn, $query_insert_30dias) or die ("error data_servicios.php $query_insert_30dias");    
    }     
}

?>