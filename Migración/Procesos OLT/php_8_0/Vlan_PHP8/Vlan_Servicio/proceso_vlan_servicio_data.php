<?php
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/../expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
$ERROR2 = 0; // Migración PHP 8.0: inicializada (contador usado sin definir)
// -------------------------------------------------------------------------
//include ('/var/www/html/conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$ip = $argv[1];
$server = $argv[2];
echo $ip."\n";
echo $server."\n";
$dataFecha =$mysqli->query("SELECT NOW()") or die("Error query Fecha.");
$row = $dataFecha->fetch_array(MYSQLI_NUM);
echo $row[0]."\n";
/* $ip = '10.99.3.38';
$server = 'OLT-ANTOFAGASTA-2'; */

$y = ping_ip($ip);
        if(trim($y)){
            if($y < 100){     
                $texto = estado_equipo($ip);
                $valida= verifica_equipo($texto);
                //echo 'El Valida tiene: '.$valida.' |||';
                if ($valida==2){
                    $texto = estado_equipo($ip);
                        $ERROR2++;
                        $valida2= verifica_equipo($texto);
                            if ($valida2==2) {
                                $texto = estado_equipo($ip);    
                                $ERROR2++;
                            }
                }
                    //$texto = estado_equipo($ip,$puerta_10G);
                    foreach (explode(chr(13), $texto) as $linea)
                    {
                        $linea = str_replace("---- More ( Press 'Q' to break ) ----","",$linea);
                        $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                        
                        //$linea = str_replace(" ","|",$linea);
                        $data[] = $linea;
                    }
                print_r($data);
                 
            }
        }

$grep2 = preg_grep("/VLAN/", $data);
$key = key($grep2);   
$key=$key+2;   
  
for ($i=$key; $i <count($data) ; $i++) { 
    $out = preg_replace('/\s+/', ' ', trim($data[$i]));
    if (strstr($out,'-----')){
        break;
    }
    
    $linea=explode(" ",$out);
    //echo trim($out)."\n";
    //print_r($linea);
    if(count($linea)==8){
        echo "Vlan: ".$linea[2]." Usuarios: ".$linea[6]."\n";
        $sql="INSERT INTO Aden.OLT_VLAN_SERVICIO_CANTIDAD
                (ip, server, vlan, clientes, fecha)
                VALUES('$ip', '$server', '$linea[2]', $linea[6], NOW())";
         $mysqli->query($sql) or die("Error query $sql");
    }else{
        echo "Vlan: ".$linea[0]." Usuarios: ".$linea[4]."\n";
        $sql="INSERT INTO Aden.OLT_VLAN_SERVICIO_CANTIDAD
                (ip, server, vlan, clientes, fecha)
                VALUES('$ip', '$server', '$linea[0]', $linea[4], NOW())";
        $mysqli->query($sql) or die("Error query $sql");    
    }
    
} 
mysqli_close($mysqli);
echo "Proceso Finalizado Correctamente...\n";
function verifica_equipo($texto){
    $texto=$texto;
    $val=1;
    foreach (explode(chr(13), $texto) as $linea){
        $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
        $data[] = $linea;
    }
    for($j=0;$j<count($data);$j++){
        $line = preg_replace('/\s+/', '', $data[$j]);
            if(stristr($line,"%Unknowncommand,theerrorlocatesat'^'")){
                $val=2;
            }
    }
    return $val;           
    
}

function estado_equipo($server)
{
    $i=0;
    $cantConfig = 0;
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
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                sleep(1);
                break;
            case SALIR:
                expect_send($stream, "y\n");
                $uname .= $match[0];
                sleep(2);
                $x = false;
                return $uname;
                break;
            case USER:
                expect_send($stream, $user . "\n");
                sleep(1);
                break;
            case SHELL:
                if($b == 0){
                    expect_send($stream, "enable\n");
                    sleep(1);
                    $b++;
                }elseif($b == 1){
                    expect_send($stream, "\n");
                    sleep(1);
                }
                break;
            case SHELL2:
                if($cantConfig==0){
                    expect_send($stream, "config\n");
                    sleep(1);
                }else{
                    expect_send($stream, "quit\n");
                    sleep(2);
                }
                $cantConfig++;
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    expect_send($stream, "display vlan all\n");
                    sleep(1);
                }
                if($i==1){
                    sleep(1);
                    expect_send($stream, "quit\n");
                    sleep(1);
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