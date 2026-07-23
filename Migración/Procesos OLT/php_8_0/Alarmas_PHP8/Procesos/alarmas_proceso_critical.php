<?php
date_default_timezone_set('America/Santiago');
//include ('../../conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=10;//ID DEL PROCESO YA REGISTRADO
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
$sql_ip = "SELECT ip,server,region,modelo,sw,patch,tipo,pop FROM OLT_SERVER";
$result = $mysqli->query($sql_ip) or die("error 2");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMA_CRITICAL_LOS");

$nprocesos=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $nprocesos++;
        $ip = $row[0];
        $server = $row[1];
        $modelo = $row[3];
        $y = ping_ip($ip);
        if(trim($y)){
            if($y < 100){    
                if($ip == '10.99.24.68'){
                    $tipo=2;
                    
                    shell_exec("nohup php -f /var/www/procesos/php/Alarmas_PHP8/Procesos/proceso_critical_exped.php $ip $server $tipo $modelo $lote_id > /var/www/procesos/php/Alarmas_PHP8/logs/$ip.log &");
                }else{
                    $tipo=1;
                    
                    shell_exec("nohup php -f /var/www/procesos/php/Alarmas_PHP8/Procesos/proceso_critical_exped.php $ip $server $tipo $modelo $lote_id > /var/www/procesos/php/Alarmas_PHP8/logs/$ip.log &");
                }
            }
        }
        echo "IP: ".$ip."\n";
}
$fecha=date('Y-m-d H:i:s');
echo 'Fin: '.$fecha;
function ping_ip($ip1){
	$count = 3;
	$paquetes = '';
	$commandA = "ping $ip1 -c $count";
	$outputIP1 = shell_exec($commandA);
	$datos = explode(',',$outputIP1);
	foreach($datos as $x){
		if(stristr($x,"packet loss")){
			$aux = explode(' ',trim($x));
			$paquetes = $aux[0];
		}
	}
	return $paquetes;
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
?>