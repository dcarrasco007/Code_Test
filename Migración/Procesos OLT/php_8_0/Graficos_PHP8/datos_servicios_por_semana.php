<?php
date_default_timezone_set('America/Santiago');
include ('/var/www/procesos/php/conexion/conexion_db.php');

$conn = mysqli_connect($host144_geret,$user144_geret,$pass144_geret,"Aden") or die("error de conexion: ".mysqli_connect_error()); // Migración PHP 8.0: mysql_* eliminado en PHP 7
// Migración PHP 8.0: seleccion de BD integrada en mysqli_connect (4o parametro)

//mysqli_query($conn, 'TRUNCATE TABLE OLT_TRAFICO_SERVICIOS_SEMANA');

$query = "SELECT DISTINCT
        OLT_TRAFICOGPON2.fecha,
        WEEK(OLT_TRAFICOGPON2.fecha,1),
        YEAR(OLT_TRAFICOGPON2.fecha)
        FROM
        OLT_TRAFICOGPON2";
$result = mysqli_query($conn, $query) or die("Error $query" . mysqli_error($conn));
while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
    $num_week [] = $row[1].'|'.$row[2].'|'.$row[0];
}
 
//$num_week[0] = date("W"); 
//$num_week[0] = $num_week[0] - 1;
//echo $num_week[0];

foreach($num_week as $week_anio){
    
    $week_f = explode('|',$week_anio);
    $week = $week_f[0];
    $year = $week_f[1];
    $dia = $week_f[2];
    
    $week = '33';
    $year = '2018';
    $dia = '2018-08-14';

    /*for($i=-1; $i<6; $i++){
        $semana [] = date('Y-m-d', strtotime('01/01 +' . ($week - 1) . ' weeks first day +' . $i . ' day'));
    }*/

    $servicios = array('3Play Empresas','3Play Personas','3Play Empresas/Personas','No Tiene');
    foreach($servicios as $servicio){
        $suma_up = 0;
        $suma_down = 0;

        $query_up = "SELECT DISTINCT
                SUM(REPLACE(OLT_TRAFICOGPON2.up_mbps,',','')) AS total,
                OLT_SERVER.pop
                FROM
                OLT_TRAFICOGPON2
                INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON2.ip_equipo = OLT_SERVER.ip
                INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON2.`port` = OLT_PUERTOS_UPLINKS.puerto
                WHERE
                OLT_TRAFICOGPON2.fecha = '$dia'
                AND OLT_TRAFICOGPON2.up_mbps <> '0.000'
                AND OLT_TRAFICOGPON2.up_mbps <> '0.001'
                AND OLT_SERVER.pop = '$servicio'";
        $result_up = mysqli_query($conn, $query_up) or die("Error $query" . mysqli_error($conn));
        $row_up = mysqli_fetch_array($result_up, MYSQLI_NUM);
        
        $query_down = "SELECT DISTINCT
                SUM(REPLACE(OLT_TRAFICOGPON2.down_mbps,',','')) AS total,
                OLT_SERVER.pop
                FROM
                OLT_TRAFICOGPON2
                INNER JOIN OLT_SERVER ON OLT_TRAFICOGPON2.ip_equipo = OLT_SERVER.ip
                INNER JOIN OLT_PUERTOS_UPLINKS ON OLT_TRAFICOGPON2.`port` = OLT_PUERTOS_UPLINKS.puerto
                WHERE
                OLT_TRAFICOGPON2.fecha = '$dia'
                AND OLT_TRAFICOGPON2.down_mbps <> '0.000'
                AND OLT_TRAFICOGPON2.down_mbps <> '0.001'
                AND OLT_SERVER.pop = '$servicio'";
        $result_down = mysqli_query($conn, $query_down) or die("Error $query" . mysqli_error($conn));
        $row_down = mysqli_fetch_array($result_down, MYSQLI_NUM);

        if($row_up[0]){
            $suma_up = $suma_up + $row_up[0];
            $suma_down = $suma_down + $row_down[0];
        }
        
        $q_semana = "INSERT INTO OLT_TRAFICO_SERVICIOS_SEMANA
                     (servicio,up,down,semana,anio) VALUES
                     ('$servicio','$suma_up','$suma_down','$week',$year)";
        mysqli_query($conn, $q_semana) or die ("datos_servicios_por_semana.php $q_semana");     
    }
    die;
    //unset($semana);
}

?>