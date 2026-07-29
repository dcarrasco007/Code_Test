<?php
include ('/var/www/html/OLT/crontab127/conexion/conexion_db.php');
echo "Inicio:".date('Y-m-d H:i:s')."\n";
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=6;//ID DEL PROCESO YA REGISTRADO
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
$mysqli->query('TRUNCATE TABLE OLT_ESTADO_ENERGIA');

$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER';
$result_ip = $mysqli->query($sql_ip) or die("error 1");
$nprocesos=0;
while ($row = $result_ip->fetch_array(MYSQLI_NUM)) {
    $nprocesos++;
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    $modelo = trim($row[3]);
    $region = str_replace(' ','_',$region);

    shell_exec("nohup php -f /u01/crontab127/OLT/Energia_CPU/shell_energia_exped.php $ip $server $region $modelo $lote_id > /u01/crontab127/OLT/Energia_CPU/log/$server.log &");
  
    
   
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
echo "Fin:".date('Y-m-d H:i:s')."\n";
mysqli_close($mysqli);


?>