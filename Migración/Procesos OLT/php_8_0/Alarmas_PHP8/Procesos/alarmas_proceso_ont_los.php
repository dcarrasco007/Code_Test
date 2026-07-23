<?php
date_default_timezone_set('America/Santiago');
//include ('../../conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=22;//ID DEL PROCESO YA REGISTRADO
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
$nprocesos=0;
sleep(1);
//--------------------
$sql_ip = "SELECT ip,server,region,modelo,sw,patch,tipo,pop FROM OLT_SERVER WHERE
OLT_SERVER.server <> 'OLT-SBERNARDO-1'
AND OLT_SERVER.server <> 'OLT-CNT-2'
AND OLT_SERVER.server <> 'OLT-VALPARAISO-1'
AND OLT_SERVER.server <> 'OLT-RECREO-2' 
AND OLT_SERVER.server <> 'OLT1-1-LABONNET-5800'
AND OLT_SERVER.server <> 'OLT1-2-LABONNET-5600'";
$result = $mysqli->query($sql_ip) or die("error 2");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMA_LOS_ONT");


while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $nprocesos++;
        $ip = $row[0];
        $server = $row[1];
        $modelo = $row[3];
        $tipo=$row[6];
            if($y < 100){    
                    shell_exec("nohup php -f /var/www/procesos/php/Alarmas_PHP8/Procesos/proceso_los_ont_exped.php $ip $server $tipo $modelo $lote_id > /var/www/procesos/php/Alarmas_PHP8/logs/log_los_ont/$server.log &");
            }
        
        echo "EQUIPO: ".$server."-".$ip."\n";
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
$fecha=date('Y-m-d H:i:s');
echo 'Fin: '.$fecha;


?>