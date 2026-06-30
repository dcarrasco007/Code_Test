<?php
include ('/u01/crontab127/conexion/conexion_db.php');
echo "Inicio: ".date('d-m-Y H:i:s')."\n";
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$proceso_id=3;//ID DEL PROCESO YA REGISTRADO
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
$nprocesos=0;
//---------------------------------
$sql_ip = "SELECT
        OLT_SERVER.`server`,
        OLT_SERVER.ip,
        OLT_SERVER.modelo
        FROM 
        OLT_SERVER
        WHERE 
        OLT_SERVER.modelo = 'MA5600T'";
$result = $mysqli->query($sql_ip) or die("error 1");

while ($row = $result->fetch_array(MYSQLI_NUM)) {

    $ip = $row[1];
    $server = $row[0];
    $nprocesos++;
    shell_exec("nohup php -f /u01/crontab127/OLT/Uplink/proceso_uplink_trafico_MA5600T_exped.php $ip $server $lote_id > /u01/crontab127/OLT/Uplink/Procesos/logs/MA5600T_15M/$server.log &");
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
die;

?>