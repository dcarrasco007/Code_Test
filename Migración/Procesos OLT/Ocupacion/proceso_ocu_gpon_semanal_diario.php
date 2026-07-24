<?php
//include ('../../conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
// obtener semana y fechas de Lunes a Domingo
//$f_inicio = '2021-07-26';
//$f_final = '2021-07-31';
//$semana = '30';
//$anio = '2021';
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
/* $anio='2025';
$f_inicio='2025-04-04';
$f_final='2025-08-10';
$semana='32'; */
echo "Inicio consulta semana: ".$semana."\n";


$query = "SELECT
        OLT_PEAKS_TRAFICO_GPON.equipo,
        OLT_PEAKS_TRAFICO_GPON.ip,
        OLT_PEAKS_TRAFICO_GPON.modelo,
        OLT_PEAKS_TRAFICO_GPON.puerta,
        OLT_PEAKS_TRAFICO_GPON.capacidad,
        OLT_PEAKS_TRAFICO_GPON.diario,
        OLT_PEAKS_TRAFICO_GPON.pdiario,
        OLT_PEAKS_TRAFICO_GPON.fecha_diario
        FROM
        OLT_PEAKS_TRAFICO_GPON
        WHERE
        OLT_PEAKS_TRAFICO_GPON.diario NOT LIKE '%,%'
        AND OLT_PEAKS_TRAFICO_GPON.fecha_diario BETWEEN '$f_inicio' AND '$f_final'
        ORDER BY OLT_PEAKS_TRAFICO_GPON.equipo,OLT_PEAKS_TRAFICO_GPON.puerta,OLT_PEAKS_TRAFICO_GPON.fecha_diario";
$resp = $mysqli->query($query) or die("error 2 $query");                    
echo "Fin consulta semana: ".$semana."\n";
echo "Inicio inserción semana: ".$semana."\n";
$values = array();
$counter = 0;

while ($row = $resp->fetch_array(MYSQLI_NUM)) {
    if ($row[0]) {
        $values[] = "('" . $mysqli->real_escape_string($row[0]) . "', 
                      '" . $mysqli->real_escape_string($row[1]) . "', 
                      '" . $mysqli->real_escape_string($row[2]) . "', 
                      '" . $mysqli->real_escape_string($row[3]) . "', 
                      '" . $mysqli->real_escape_string($row[4]) . "', 
                      '" . $mysqli->real_escape_string($row[5]) . "', 
                      '" . $mysqli->real_escape_string($row[6]) . "', 
                      '" . $mysqli->real_escape_string($row[7]) . "', 
                      '" . $mysqli->real_escape_string($semana) . "')";
        
        $counter++;

        // Ejecutar cada 500 registros
        if ($counter >= 500) {
            $query3 = "INSERT INTO OLT_PEAKS_TRAFICO_GPON_FINAL_WEEK_DAY
                       (equipo,ip,modelo,puerta,capacidad,diario,pdiario,fecha_diario,week) 
                       VALUES " . implode(",", $values);

            $mysqli->query($query3) or die("error en insert múltiple $query3");
            
            // Resetear acumuladores
            $values = array();
            $counter = 0;
        }
    }
}

// Insertar los que quedaron (menos de 500)
if (!empty($values)) {
    $query3 = "INSERT INTO OLT_PEAKS_TRAFICO_GPON_FINAL_WEEK_DAY
               (equipo,ip,modelo,puerta,capacidad,diario,pdiario,fecha_diario,week) 
               VALUES " . implode(",", $values);

    $mysqli->query($query3) or die("error en insert final $query3");
}
$mysqli ->close();
echo "Fin proceso para semana: ".$semana."\n";

?>