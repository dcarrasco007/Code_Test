<?php
date_default_timezone_set('America/Santiago');
include ('/var/www/procesos/php/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=18;//ID DEL PROCESO YA REGISTRADO
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
//mysqli_query($mysqli,"TRUNCATE TABLE OLT_UPTIME");

echo $fecha = date('Y-m-d H-i-s');

//$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER';
$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER';
$result = $mysqli->query($sql_ip) or die("error 2");


$nprocesos=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $nprocesos++;
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    $modelo = $row[3];
    
    $region = str_replace(' ','_',$region);
    shell_exec("nohup php -f /var/www/procesos/php/Energia_Alarma_PHP8/proceso_data.php $ip $server $region $modelo $lote_id > /var/www/procesos/php/Energia_Alarma_PHP8/logs/logs-$ip-$server.log &");
    echo $server."\n";
    //$ip = '10.99.17.24';
    //$server = 'OLT-TOME2PCS-1';
    //$region = 'VIII';
 
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

?>