<?php
//include ('../../../conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
echo"Inicio Nuevo:\n".date('H:i:s');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=1;//ID DEL PROCESO YA REGISTRADO
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


$sql_ip = "SELECT
        OLT_SERVER.`server`,
        OLT_SERVER.ip,
        OLT_SERVER.modelo
        FROM 
        OLT_SERVER
        WHERE 
        OLT_SERVER.modelo = 'MA5800-X15'";
$result = $mysqli->query($sql_ip) or die("error 1");
$ERROR=0;
$ERROR2=0;
$nprocesos=0;
$semana=date('W');
$hora=date('Y-m-d H:i:s');
$date2=date('Y-m-d');
$hora = strtotime ( '-2 hour' , strtotime ($hora));
$hora = strtotime ( '-30 minute' , $hora);
$hora = date ( 'H:i:s' , $hora);
//$hora2= date ( 'H:i:s' , $hora);
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $nprocesos++;
    $contador16=0;
    $contador17=0;
    $contador18=0;
    $contador08=0;
    $contador09=0;
    $puerto16=0;
    $puerto17=0;
    $puerto18=0;
    $puerto08=0;
    $puerto09=0;
    $ip = $row[1];
    $y = ping_ip($ip);
    $server = $row[0];
    $fecha = date("Y-m-d H:i:s");
    $sql_port = "SELECT
        OLT_SERVER.pto1,
        OLT_SERVER.pto2,
        OLT_SERVER.pto3,
        OLT_SERVER.pto4,
        OLT_SERVER.pto5,
        OLT_SERVER.pto6,
        OLT_SERVER.pto7,
        OLT_SERVER.pto8,
        OLT_SERVER.pto9,
        OLT_SERVER.pto10,
        OLT_SERVER.pto11,
        OLT_SERVER.pto12
        FROM 
        OLT_SERVER
        WHERE 
        OLT_SERVER.modelo = 'MA5800-X15'
        AND OLT_SERVER.server='$row[0]'";
    $result2 = $mysqli->query($sql_port) or die("error 1");
    $row2 = $result2->fetch_array(MYSQLI_NUM);
        //$ip = '10.99.30.6';
        //$server = 'OLT-SBOTICELLI-1';
        /* if(trim($y)){
            if($y < 100){ */        
            for ($i=0; $i <12 ; $i++) { 
                if($row2[$i]!=null){
                    $puerta_completa =$row2[$i];
                    $partes = explode("/", $puerta_completa);
                    $parte1=$partes[0].'/'.$partes[1];
                    $parte2=$partes[2];
                    
                    if($parte1=='0/16'){
                        $puerto16++;
                    }elseif($parte1=='0/17') {
                        $puerto17++;
                    }elseif ($parte1=='0/18') {
                        $puerto18++;
                    }elseif ($parte1=='0/8') {
                        $puerto08++;
                    }elseif ($parte1=='0/9') {
                        $puerto09++;
                    }
                }    
            } 
            $total=$puerto16+$puerto17+$puerto18;

                //echo 'cantidad '.$puerto16.'|'.$puerto17.'|'.$puerto18.'|';
                if($ip=='10.99.17.38' || $ip=='10.99.30.70' || $ip=='10.99.26.150' || $ip=='10.99.29.50' || $ip=='10.99.26.66' || $ip=='10.99.30.14' || $ip=='10.99.26.254' || $ip =='10.99.9.150' || $ip=='10.99.30.102'){//concepcion3 puerto 0/8 0/9
                    shell_exec("nohup php -f /u01/crontab127/OLT/Uplink/Procesos/proceso_uplink_trafico_exped.php $server $semana $ip $puerto08 $puerto09 $hora $lote_id > /u01/crontab127/OLT/Uplink/Procesos/logs/MA5800/log$ip.log &");
                    
                }else{
                    
                    shell_exec("nohup php -f /u01/crontab127/OLT/Uplink/Procesos/proceso_uplink_trafico_exped.php $server $semana $ip $puerto16 $puerto17 $puerto18 $hora $lote_id > /u01/crontab127/OLT/Uplink/Procesos/logs/MA5800/log$ip.log &");
                }
                
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
echo "\nFin: ".date('H:i:s');
die;

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

?>