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

$conn = mysqli_connect($host144_geret,$user144_geret,$pass144_geret,"Aden") or die("error de conexion: ".mysqli_connect_error()); // Migración PHP 8.0: mysql_* eliminado en PHP 7
// Migración PHP 8.0: mysql_select_db integrado en mysqli_connect (4o parametro)
mysqli_set_charset($conn,'utf8');

mysqli_query($conn, 'TRUNCATE TABLE OLT_VERSION_PARCHE_MODELO');

$fecha = date('Y-m-d');

$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER';
$result_ip = mysqli_query($conn, $sql_ip) or die ("error shell_energia.php 1 $sql_ip".mysqli_error($conn));

while ($row = mysqli_fetch_array($result_ip)) {
    
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){            
            $texto = estado_equipo($ip);
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                $data[] = $linea;
            }
            
            //print_r($data);
                        
            for($j=0;$j<count($data);$j++){
                $line = preg_replace('/\s+/', ' ', $data[$j]);
                if (!stristr($line,'Program') && !stristr($line,'Data')){
                    if (stristr($line,' VERSION :')){
                        $version = explode(':',$line);
                        $version = trim($version[1]);
                    }elseif (stristr($line,'PATCH')){
                        $parche = explode(':',$line);
                        $parche = trim($parche[1]);
                    }elseif (stristr($line,' PRODUCT :')){
                        $modelo = explode(':',$line);
                        $modelo = trim($modelo[1]);
                    }elseif (stristr($line,' Uptime is')){
                        $uptime = trim($line);
                    }  
                }
            }

            $query_vpm = "INSERT INTO OLT_VERSION_PARCHE_MODELO
                          (equipo,ip,region,version,parche,modelo,fecha,uptime) 
                          VALUES ('$server','$ip','$region','$version','$parche','$modelo','$fecha','$uptime')";
            mysqli_query($conn, $query_vpm) or die ("error version_patch_modelo.php 1 $query_vpm".mysqli_error($conn));
                     
        }
    }
    unset($data);
}
mysqli_close($conn);

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
            array("{ <cr>|backplane<K>|frameid/slotid<S><Length 1-15> }:", ESPACIO2,EXP_EXACT),
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
                    expect_send($stream, "display version\n");
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