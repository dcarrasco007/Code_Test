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
echo "Inicio:".date('Y-m-d H:i:s')."\n";
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
    
    //-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    $lote=$argv[5];
    $proceso_id=7;//ID DEL PROCESO YA REGISTRADO
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
    /* $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    $modelo = trim($row[3]); */

    $ip = $argv[1];
    $server = $argv[2];
    $region = $argv[3];
    $modelo = $argv[4];

    /*$ip = '10.99.26.82';
    $server = 'OLT-PARQUETITANIUM-2';
    $region = 'XIII';
    $modelo = 'MA5800-X15';*/
    
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){
            
            switch($modelo){
                
                case 'MA5603T':
                                $puerto1 = '0/10';
                                $puerto2 = '0/11';
                                $texto = estado_equipo($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                    $data[] = $linea;
                                }
                                parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2);
                                unset($data); 
                                break;
                
                case 'MA5600T':
                                $puerto1 = '0/19';
                                $puerto2 = '0/20';
                                $texto = estado_equipo($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                    $data[] = $linea;
                                }
                                //print_r($data);
                                parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2);
                                unset($data);
                                break;
                
                case 'MA5680T':
                                $puerto1 = '0/19';
                                $puerto2 = '0/20';
                                $texto = estado_equipo($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                    $data[] = $linea;
                                }
                                parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2);
                                unset($data);
                                break;
                
                case 'MA5800-X15':
                                $puerto1 = '0/18';
                                $puerto2 = '0/19';
                                $texto = estado_equipo2($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                    $data[] = $linea;
                                }
                                parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2);
                                unset($data);
                                break;
            } 
            
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
echo "Fin:".date('Y-m-d H:i:s')."\n";
mysqli_close($mysqli);

function parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2){
    global $mysqli;
    $ahora = date("Y-m-d H:i:s");
    $data = $data;
    $ip = $ip;
    $server = $server;
    $region = $region;
    $modelo = $modelo;
    $puerto1 = $puerto1;
    $puerto2 = $puerto2;
    
    $grep = array();
    $grep = preg_grep("/.Board Status./", $data);
    //print_r($grep);
    
    $claves = array_keys($grep);
    //print_r($claves);
    
    //--------- PUERTA 1 --------------------------
    $pars = explode(':',$data[$claves[0]]);
    $board_status1 = trim($pars[1]);
    $pars = explode('-',$data[$claves[0]+6]);
    $power_status1 = trim($pars[0]).' '.trim($pars[1]);
    
    if($board_status1 == ''){
        $board_status1 = 'Normal';
    }
    if($power_status1 == ' '){
        $power_status1 = 'POWER ON';
    }
    if($power_status1 == 'Huawei Integrated Access Software (MA5600T).'){
        $power_status1 = 'POWER ON';
    }
    if($power_status1 == 'Huawei Integrated Access Software (MA5800).'){
        $power_status1 = 'POWER ON';
    }
    if($power_status1 == 'Huawei Integrated Access Software (MA5600T). '){
        $power_status1 = 'POWER ON';
    }
    if($power_status1 == 'Huawei Integrated Access Software (MA5800). '){
        $power_status1 = 'POWER ON';
    }

    //--------- PUERTA 2 --------------------------
    $pars = explode(':',$data[$claves[1]]);
    $board_status2 = trim($pars[1]);
    $pars = explode('-',$data[$claves[1]+6]);
    $power_status2 = trim($pars[0]).' '.trim($pars[1]);
    
    if($board_status2 == ''){
        $board_status2 = 'Normal';
    }
    if($power_status2 == ' '){
        $power_status2 = 'POWER ON';
    }
    if($power_status2 == 'Huawei Integrated Access Software (MA5600T).'){
        $power_status2 = 'POWER ON';
    }
    if($power_status2 == 'Huawei Integrated Access Software (MA5600T). '){
        $power_status2 = 'POWER ON';
    }
    if($power_status2 == 'Huawei Integrated Access Software (MA5800).'){
        $power_status2 = 'POWER ON';
    }
    if($power_status2 == 'Huawei Integrated Access Software (MA5800). '){
        $power_status2 = 'POWER ON';
    }

    $sql = "INSERT INTO OLT_ESTADO_ENERGIA (equipo,region,ip,puerta,board,power,modelo,fecha) 
            VALUES ('$server','$region','$ip','$puerto1','$board_status1','$power_status1','$modelo',NOW())";
    $resultado = $mysqli->query($sql) or die("error 1");
    
    $sql1 = "INSERT INTO OLT_ESTADO_ENERGIA (equipo,region,ip,puerta,board,power,modelo,fecha) 
            VALUES ('$server','$region','$ip','$puerto2','$board_status2','$power_status2','$modelo',NOW())";
    $resultado1 = $mysqli->query($sql1) or die("error 1");

}

function estado_equipo($server,$puerto1,$puerto2)
{
    $i=0;
    $ser = $server;
    $p1 = $puerto1;
    $p2 = $puerto2;  
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
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                sleep(1);
                break;
            case USER:
                expect_send($stream, $user . "\n");
                break;
            case SHELL:
                if ($i==0) {
                    expect_send($stream, "display board $p1\n");
                    sleep(1);
                }
                if ($i==1) {
                    expect_send($stream, "display board $p2\n");
                    sleep(1);
                }
                if ($i==2) {
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

function estado_equipo2($server,$puerto1,$puerto2)
{
    $i=0;
    $ser = $server;
    $p1 = $puerto1;
    $p2 = $puerto2;  
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout(30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
    $uname = "";
    $x = true;
    $xx = true;
    while ($x)
    {
        switch (expect_expectl($stream, array(
            array("User name:", USER),
            array("User password:",PASSWORD,EXP_EXACT),
            array(".*\n",SALTO,EXP_REGEXP),
            array(".*>",SHELL,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                sleep(1);
                break;
            case USER:
                expect_send($stream, $user . "\n");
                break;
            case SHELL:
                if ($i==0) {
                    expect_send($stream, "display board $p1\n\n");
                    sleep(1);
                }                            
                if ($i==1) {
                    expect_send($stream, "display board $p2\n\n");
                    sleep(1);
                }
                if ($i==2) {
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