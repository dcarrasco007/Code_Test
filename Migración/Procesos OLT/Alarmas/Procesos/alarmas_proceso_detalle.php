<?php
//include ('../../conexion/conexion_db.php');
echo "Inicio: ".date('d-m-Y H:i:s')."\n";
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=12;//ID DEL PROCESO YA REGISTRADO
$fecha_monitor=date('Y-m-d H:i:s');
$sql_Monitor = "INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
(proceso_id,fecha_inicio,estado,fecha_registro,cantidad_subprocesos,cantidad_subprocesos_completados,mensaje)
VALUES
($proceso_id,'$fecha_monitor','RUNNING','$fecha_monitor',0,0,'Porceso Primario')";
if (!$mysqli->query($sql_Monitor)) {
    die("Error insert padre: " . $mysqli->error);
}
$parent_id = $mysqli->insert_id;
// el lote será el mismo id del padre
$lote_id = $parent_id;
echo"\nlote: ".$lote_id;
sleep(1);
//--------------------
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_CRITICAL");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_MAJOR");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_MINOR");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_WARNING");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_CANTIDAD_ALARMAS_DETALLE"); 

$fecha_proceso = date('Y-m-d');
$nprocesos=0;
$sql_ip = "SELECT ip,server,region,modelo,sw,patch,tipo,pop FROM OLT_SERVER";
$result = $mysqli->query($sql_ip) or die("error 2");
$anio=date('Y');
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    //if($row[7] != 'No Tiene' && $row[7] != 'SOLO SITIOS MOVILES'){
        $nprocesos++;
        $ip = $row[0];
        $server = $row[1];
        $region = $row[2];
        $region = str_replace(' ','_',$region);
        shell_exec("nohup php -f /u01/crontab127/OLT/Alarmas/Procesos/alarmas_proceso_detalle_exped.php $ip $server $region $lote_id > /u01/crontab127/OLT/Alarmas/logs/Alarmas_detalle/$server.log &");
        
}
//-----------MONITOREO FIN
$fecha_monitor=date('Y-m-d H:i:s');
$sql = "UPDATE MONITOREO_PROCESOS_EJECUCIONES
SET 
    fecha_fin = '$fecha_monitor',
    duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, '$fecha_monitor'),
    estado = 'OK',
    cantidad_subprocesos=$nprocesos
WHERE id = $parent_id";

if (!$mysqli->query($sql)) {
    die("Error update padre: " . $mysqli->error);
}
//---------------------------------
mysqli_close($mysqli);
echo "Fin: ".date('d-m-Y H:i:s')."\n";


?>