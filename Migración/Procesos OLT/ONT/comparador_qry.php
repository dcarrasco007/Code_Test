<?php

include ('/u01/crontab127/conexion/conexion_db.php');
echo 'Inicio del Script...';
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

// Traer solo las 3 columnas necesarias de cada tabla
$resA = $mysqli->query("SELECT equipo, fn, sn,pn,onu FROM OLT_INFO_ONT_PRUEBA");
$resB = $mysqli->query("SELECT equipo, frame_id, slot_id, port_id, onu_id FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO");
echo "\nconsultas realizadas...\n";
// Pasar tablaB a un array hash para comparación rápida
$tablaB = [];
while ($row = $resB->fetch_assoc()) {
    $key = $row['equipo'].'|'.$row['frame_id'].'|'.$row['slot_id'].'|'.$row['port_id'].'|'.$row['onu_id'];
    $tablaB[$key] = true;
}
echo "\nGuardado A...\n";
// Comparar registros de tablaA contra tablaB
echo "\nInicio comparativa...\n";
$diferencias = [];
while ($row = $resA->fetch_assoc()) {
    $key = $row['equipo'].'|'.$row['fn'].'|'.$row['sn'].'|'.$row['pn'].'|'.$row['onu'];
    if (!isset($tablaB[$key])) {
        $diferencias[] = $row; // Guardar los que no están en tablaB
    }
}
echo "\Fin comparativa...\n";
// Mostrar resultado
echo "Total diferencias: ".count($diferencias)."\n";
foreach ($diferencias as $dif) {
    echo $dif['equipo']." | ".$dif['fn']."/".$dif['sn']."/".$dif['pn']." - ".$dif['onu']."\n";
}

mysqli_close($mysqli);
echo 'Fin Script.';   
?>