<?php
date_default_timezone_set('America/Santiago');
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
//include ('/var/www/procesos/php/conexion/conexion_db.php');
//include ('/var/www/procesos/php/conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=24;//ID DEL PROCESO YA REGISTRADO
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
mysqli_query($mysqli,"TRUNCATE TABLE OLT_FAN_ESTADO");

$fecha = date('Y-m-d');
echo "Fecha Proceso: ".$fecha."\n";
$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER';
$result = $mysqli->query($sql_ip) or die("error 2");

while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    $nprocesos++;
    $cont_alarmas = 0;
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){            
            $texto = estado_equipo($ip);
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                $data[] = $linea;
            }

            for($j=0;$j<count($data);$j++){
                $line = preg_replace('/\s+/', ' ', $data[$j]);
                if (stristr($line,'EMU name')){
                    $emu_name = explode(':',trim($line));
                    $emu_name = trim($emu_name[1]);
                }
                if (stristr($line,'EMU state')){
                    $emu_state = explode(':',trim($line));
                    $emu_state = trim($emu_state[1]);
                }
            }
                
            $query_fan = "INSERT INTO OLT_FAN_ESTADO
                              (equipo,ip,region,nombre,estado,fecha) VALUES 
                              ('$server','$ip','$region','$emu_name','$emu_state','$fecha')";
            $mysqli->query($query_fan) or die("Error query fan");
        }
    }  
    unset($data);   
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

function estado_equipo($server)
{
    $i=0;
    $server = $server;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout(30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
    $uname = "";
    $x = true;
    while ($x)
    {
        switch (expect_expectl($stream, array(
            array("User name:", USER),
            array("User password:",PASSWORD,EXP_EXACT),
            array(".*\n",SALTO,EXP_REGEXP),
            array(".*>",SHELL,EXP_REGEXP),
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                break;
            case USER:
                expect_send($stream, $user . "\n");
                break;
            case SHELL:
                if($b == 0){
                    expect_send($stream, "enable\n");
                    $b++;
                }elseif($b == 1){
                    expect_send($stream, "\n");
                }
                break;
            case SHELL2:
                expect_send($stream, "config\n");
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    expect_send($stream, "display emu 0\n");
                }
                if ($i==1) {
                    expect_send($stream, "quit\n");  
                    expect_close($stream);
                    return $uname;
                }
                ++$i;
                break;
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                return 2;
                break;
            case ESPACIO:
                expect_send($stream, " ");
                $uname .= $match[0];
                break;
            case ESPACIO2:
                expect_send($stream, "\n");
                $uname .= $match[0];
                break;
            case EXP_EOF:
                break;
        }
    }
}

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