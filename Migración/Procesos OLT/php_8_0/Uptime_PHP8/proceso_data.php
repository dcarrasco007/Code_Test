<?php
date_default_timezone_set('America/Santiago');
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
include ('/var/www/procesos/php/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

//-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    $lote=$argv[4];
    $proceso_id=21;//ID DEL PROCESO YA REGISTRADO
    $ip=$argv[1];
    $sql_Monitor = "INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
    (proceso_id,parent_id,lote_id,fecha_inicio,estado,fecha_registro,mensaje)
    VALUES
    ($proceso_id,$lote,$lote,'$fecha_monitor','RUNNING','$fecha_monitor','$ip')";
    if (!$mysqli->query($sql_Monitor)) {
        die("Error insert padre: " . $mysqli->error);
    }
    $parent_id = $mysqli->insert_id;
    // el lote será el mismo id del padre
    $id_hijo = $parent_id;
    sleep(1);
    //--------------------
//mysqli_query($mysqli,"TRUNCATE TABLE OLT_UPTIME");
    $ip = $argv[1];
    $server = $argv[2];
    $region = $argv[3];
echo $fecha = date('Y-m-d H:i:s');

for($i=0;$i<17;$i++){
    $comandos [] = ("interface gpon 0/$i");
}

    
    //$ip = '10.99.17.24';
    //$server = 'OLT-TOME2PCS-1';
    //$region = 'VIII';
    
    $cont_alarmas = 0;
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){   
            foreach($comandos as $comando){
                if($ip == '10.99.24.68'){
                    $texto = estado_equipo2($ip,$comando);
                }else{
                    $texto = estado_equipo($ip,$comando);
                }
                foreach (explode(chr(13), $texto) as $linea)
                {
                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                    $data[] = $linea;
                }
                
                $puerto = '';
                $cause = '';
                $up = '';
                $down = '';
                for($j=0;$j<count($data);$j++){
                    $line = preg_replace('/\s+/', ' ', $data[$j]);
                    if (stristr($line,'F/S/P')){
                        $puerto = explode(' ',trim($line));
                        $puerto = $puerto[1];
                    }
                    if (stristr($line,'Port state')){
                        $state = explode('state',trim($line));
                        $state = trim($state[1]);
                    }
                    if (stristr($line,'Last down cause')){
                        $cause = explode('cause',trim($line));
                        $cause = $cause[1];
                    }
                    if (stristr($line,'Last up time')){
                        $up1 = explode('time',trim($line));
                        $up2 = explode(' ',trim($up1[1]));
                        $up3 = explode('-',trim($up2[1]));
                        $up = $up2[0].' '.$up3[0];
                        $dia2 = trim($up2[0]);
                        $hora2 = trim($up3[0]);
                    }
                    if (stristr($line,'Last down time')){
                        $down1 = explode('time',trim($line));
                        $down2 = explode(' ',trim($down1[1]));
                        $down3 = explode('-',trim($down2[1]));
                        $down = $down2[0].' '.$down3[0];
                        $dia1 = trim($down2[0]);
                        $hora1 = trim($down3[0]);
                    }
                }
                
                if($dia1 == $dia2){
                    $time1 = new DateTime($hora1);
                    $time2 = new DateTime($hora2);
                    $interval = $time1->diff($time2);
                    $hour = $interval->format("%h");
                    $min = $interval->format("%i");
                    $sec = $interval->format("%s");
                    $corte = $hour.':'.$min.':'.$sec;
                }else{
                    $corte = '-';
                }
                
                if($puerto != ''){
                    echo $query_uptime = "INSERT INTO OLT_UPTIME
                                      (equipo,ip,region,puerta,alarma,last_down,last_up,fecha,state,corte) VALUES 
                                      ('$server','$ip','$region','$puerto','$cause','$down','$up','$fecha','$state','$corte')";
                    $mysqli->query($query_uptime) or die("Error query uptime");
                    
                    $query_historico = "INSERT INTO OLT_UPTIME_HISTORICO
                                      (equipo,ip,region,puerta,alarma,last_down,last_up,fecha,state,corte) VALUES 
                                      ('$server','$ip','$region','$puerto','$cause','$down','$up','$fecha','$state','$corte')";
                    $mysqli->query($query_historico) or die("Error query uptime");
                }
                 
                unset($data);
            }
            //die;
        }
    }   
    //-----------MONITOREO FIN
$fecha_monitor=date('Y-m-d H:i:s');
$sql = "UPDATE MONITOREO_PROCESOS_EJECUCIONES
SET 
    fecha_fin = '$fecha_monitor',
    duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, '$fecha_monitor'),
    estado = 'OK'
WHERE id = $id_hijo";

if (!$mysqli->query($sql)) {
    die("Error update padre: " . $mysqli->error);
}
//---------------------------------
echo "--Fin Proceso--".$fecha;
mysqli_close($mysqli);

function estado_equipo($server,$comando)
{
    $comando = $comando;
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
                    expect_send($stream, "$comando\n");
                }
                if ($i==1) {
                    expect_send($stream, "display port state 0\n");
                }
                if ($i==2) {
                    expect_send($stream, "quit\n");
                }
                if ($i==3) {
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

function estado_equipo2($server,$comando)
{
    $comando = $comando;
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
            array("OLT-DURZUA-4>",SHELL,EXP_EXACT),
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT-DURZUA-4#",SHELL2,EXP_EXACT),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
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
                }
                if($b == 1){
                    expect_send($stream, "\n");
                    $b++;
                }
                break;
            case SHELL2:
                expect_send($stream, "config\n");
                sleep(1);
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    expect_send($stream, "$comando\n");
                    sleep(1);
                }
                if ($i==1) {
                    expect_send($stream, "display port state 0\n");
                    sleep(1);
                }
                if ($i==2) {
                    expect_send($stream, "display port state 0\n");
                    sleep(1);
                }
                if ($i==3) {
                    expect_send($stream, "quit\n");
                    sleep(1);
                }
                if ($i==4) {
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