<?php
date_default_timezone_set('America/Santiago');
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
die("Script Eliminado 24-06-2025");
//include ('/var/www/procesos/php/conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
expect_set_timeout(2500);
echo "Inicio:".date('Y-m-d H:i:s')."\n";
$conn = mysqli_connect($host144_geret,$user144_geret,$pass144_geret,"Aden") or die("error de conexion: ".mysqli_connect_error()); // Migración PHP 8.0: mysql_* eliminado en PHP 7
// Migración PHP 8.0: seleccion de BD integrada en mysqli_connect (4o parametro)
mysqli_set_charset($conn,'utf8');

mysqli_query($conn, 'TRUNCATE TABLE OLT_UPLINKS_STATE');

$sql_ip = "SELECT ip,server,region,modelo,tipo,marca_slot FROM OLT_SERVER WHERE OLT_SERVER.`server` <> 'OLT-DURZUA-4'";
$result_ip = mysqli_query($conn, $sql_ip) or die ("error shell_energia.php 1 $sql_ip".mysqli_error($conn));

while ($row = mysqli_fetch_array($result_ip)) {
    
    if($row[1] != 'OLT-DURZUA-4'){
    
        $ip = $row[0];
        $server = $row[1];
        $region = $row[2];
        $modelo = trim($row[3]);
        $tipo = trim($row[4]);
        $marca = $row[5];
    /*    
        $ip = '10.99.9.19';
        $server = 'OLT-RECREO-2';
        $region = 'V';
        $modelo = 'MA5603T';
        $marca = 2;
        
        $ip = '10.97.240.54';
        $server = 'OLT-COSTANERA-1';
        $region = 'XIII';
        $modelo = 'MA5600T';
        $marca = 1;
      
        $ip = '10.99.17.2';
        $server = 'OLT-CONCEPCION-1';
        $region = 'VIII';
        $modelo = 'MA5680T';
        $marca = 4;
        
        $ip = '10.99.26.94';
        $server = 'OLT-MALLTOBALABAPCS-1';
        $region = 'XIII';
        $modelo = 'MA5800-X15';
        $marca = 1;
    */      
        $y = ping_ip($ip);
        if(trim($y)){
            if($y < 100){
                
                switch($modelo){
                    
                    case 'MA5603T':
                                    $puerto1 = '0/6';//scu
                                    $puerto2 = '0/8';//giu
                                    $puerto3 = '0/9';//giu
                                    $comando1 = 'interface scu';
                                    $comando2 = 'interface giu';
                                    $comando3 = 'display port state all';
                                    $comando4 = 'display port ddm-info 0';
                                    $comando5 = 'display port ddm-info 1';
                                    $texto = estado_equipo($ip,$puerto1,$comando1,$comando3,$comando4,$comando5);
                                    $texto .= estado_equipo($ip,$puerto2,$comando2,$comando3,$comando4,$comando5);
                                    $texto .= estado_equipo($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                    foreach (explode(chr(13), $texto) as $linea)
                                    {
                                        $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                        $data[] = $linea;
                                    }
    
                                    parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2,$puerto3);
                                    unset($data); 
                                    break;
                    
                    case 'MA5600T':
                                    $puerto1 = '0/7';//scu
                                    $puerto2 = '0/8';//scu
                                    $puerto3 = '0/17';//giu
                                    $puerto4 = '0/18';//giu
                                    $comando1 = 'interface scu';
                                    $comando2 = 'interface giu';
                                    $comando3 = 'display port state all';
                                    $comando4 = 'display port ddm-info 0';
                                    $comando5 = 'display port ddm-info 1';
                                    if($server == 'OLT-LAFLORIDA-1'){
                                        $puerto3 = '0/19';//giu
                                        $puerto4 = '0/20';//giu  
                                    }
                                    
                                    if($server != 'OLT-13NORTE-1'){
                                        if($marca == '2'){
                                            $texto = estado_equipo($ip,$puerto1,$comando1,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto2,$comando1,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                        }elseif($marca == '3'){
                                            $texto = estado_equipo($ip,$puerto1,$comando1,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto4,$comando2,$comando3,$comando4,$comando5); 
                                        }elseif($marca == '4'){
                                            $texto = estado_equipo($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto4,$comando2,$comando3,$comando4,$comando5);
                                        }else{
                                            $texto = estado_equipo($ip,$puerto1,$comando1,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto2,$comando1,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                            $texto .= estado_equipo($ip,$puerto4,$comando2,$comando3,$comando4,$comando5);                                       
                                        }
                                        foreach (explode(chr(13), $texto) as $linea)
                                        {
                                            $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                            $data[] = $linea;
                                        }
    
                                        parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2,$puerto3,$puerto4);
                                        unset($data);
    
                                    }
                                    break;
                    
                    case 'MA5680T':
                                    $puerto1 = '0/7';//scu
                                    $puerto2 = '0/8';//scu
                                    $puerto3 = '0/17';//giu
                                    $puerto4 = '0/18';//giu
                                    $comando1 = 'interface scu';
                                    $comando2 = 'interface giu';
                                    $comando3 = 'display port state all';
                                    $comando4 = 'display port ddm-info 0';
                                    $comando5 = 'display port ddm-info 1';
                                    if($marca == '4'){
                                        $texto = estado_equipo($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                        $texto .= estado_equipo($ip,$puerto4,$comando2,$comando3,$comando4,$comando5);
                                    }else{
                                        $texto = estado_equipo($ip,$puerto1,$comando1,$comando3,$comando4,$comando5);
                                        $texto .= estado_equipo($ip,$puerto2,$comando1,$comando3,$comando4,$comando5);
                                        $texto .= estado_equipo($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                        $texto .= estado_equipo($ip,$puerto4,$comando2,$comando3,$comando4,$comando5);
                                    }
                                    foreach (explode(chr(13), $texto) as $linea)
                                    {
                                        $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                        $data[] = $linea;
                                    }
    
                                    parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2,$puerto3,$puerto4);
                                    unset($data);
                                    break;
                    
                    case 'MA5800-X15':
                                    $puerto1 = '0/8';//scu
                                    $puerto2 = '0/9';//scu
                                    $puerto3 = '0/16';//giu
                                    $puerto4 = '0/17';//giu
                                    $comando1 = 'interface mpu';
                                    $comando2 = 'interface eth';
                                    $comando3 = 'display port state all';
                                    $comando4 = 'display port ddm-info 0';
                                    $comando5 = 'display port ddm-info 1';
                                    $texto = estado_equipo_x15($ip,$puerto1,$comando1,$comando3,$comando4,$comando5);
                                    $texto .= estado_equipo_x15($ip,$puerto2,$comando1,$comando3,$comando4,$comando5);
                                    $texto .= estado_equipo_x15($ip,$puerto3,$comando2,$comando3,$comando4,$comando5);
                                    $texto .= estado_equipo_x15($ip,$puerto4,$comando2,$comando3,$comando4,$comando5);
                                    foreach (explode(chr(13), $texto) as $linea)
                                    {
                                        $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                        $data[] = $linea;
                                    }
    
                                    parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2,$puerto3,$puerto4);
                                    unset($data);
                                    break;
                } 
                
            }
        }
    }
}

mysqli_close($conn);

function parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2,$puerto3,$puerto4){
    global $conn;
    $ahora = date("Y-m-d H:i:s");
    $data = $data;
    $ip = $ip;
    $server = $server;
    $region = $region;
    $modelo = $modelo;
    $puerto1 = $puerto1;
    $puerto2 = $puerto2;
    $puerto3 = $puerto3;
    $puerto4 = $puerto4;
    $parametros = array();
    $puertos = array($puerto1,$puerto2,$puerto3,$puerto4);
    
    $y = 0;
    
    $grep = array();
    $grep1 = array();
    $grep = preg_grep("/.port state./", $data);
    $grep_rx = preg_grep("/.ddm-info./", $data);
    $array_key_rx = array();
    
    foreach($grep_rx as $key_rx => $val_rx){
        $array_key_rx[] = $key_rx;
    }
 
    $par = 2;
    if($modelo == 'MA5800-X15'){
        foreach($grep as $key_x15 => $val){
            if($par%2==0){
                $grep1[$key_x15] = $val;
            }
            ++$par;
        }
    }
    
    if($modelo == 'MA5800-X15'){
        $grep = $grep1;
    }

    $aux_rx = 0;
    foreach($grep as $key => $val){  
        $k = 0;
        if($modelo == 'MA5800-X15'){
            $k2 = 9;
        }else{
            $k2 = 5;
        }
        $inter = explode(' ',$data[$key-2]);

        if($inter[2] == 'scu'){
            $inter = $inter[3];
        }elseif($inter[2] == 'giu'){
            $inter = $inter[3];
        }elseif($modelo == 'MA5800-X15'){
            $inter = $puertos[$y];
        }else{
            $inter = $inter[2];
        }
        
        $rx_val_n = 'port is absence';
        if($data[$array_key_rx[$aux_rx]+1] == '  Failure: The optic module of port is absence, can not do such operation'){
            if($data[$array_key_rx[$aux_rx+1]+1] == '  Failure: The optic module of port is absence, can not do such operation'){
                $rx_val_0 = 'port is absence';
                $rx_val_1 = 'port is absence';
                $aux_rx = $aux_rx+2;
            }else{
                $rx_val_0 = 'port is absence';
                $rx_val_1 = explode(':',$data[$array_key_rx[$aux_rx+1]+5]);
                $rx_val_1 = trim($rx_val_1[1]);
                $aux_rx = $aux_rx+2;
            }
        }else{
            $rx_val_0 = explode(':',$data[$array_key_rx[$aux_rx]+5]);
            $rx_val_0 = trim($rx_val_0[1]);
            if($data[$array_key_rx[$aux_rx+1]+1] == '  Failure: The optic module of port is absence, can not do such operation'){
                $rx_val_1 = 'port is absence';
            }else{
                $rx_val_1 = explode(':',$data[$array_key_rx[$aux_rx+1]+5]);
                $rx_val_1 = trim($rx_val_1[1]);
            }
            $aux_rx = $aux_rx+2;
        }

        while($data[$key+$k2] != '  ------------------------------------------------------------------------------'){
            $valores = explode(' ',$data[$key+$k2]);
            foreach($valores as $valor){
                if($valor != ''){
                    $parametros[$k] = $valor;
                    ++$k;
                }
            }
            
            if($parametros[0] == 0){
                if($inter != 'Failure:'){
                    $sql = "INSERT INTO OLT_UPLINKS_STATE (equipo,ip,region,port,port_type,optic_status,
                            native_vlan,mdi,speed,duplex,flow_ctrl,active_state,link,interface,fecha,rx_power) VALUES 
                            ('$server','$ip','$region','$parametros[0]','$parametros[1]','$parametros[2]','$parametros[3]',
                            '$parametros[4]','$parametros[5]','$parametros[6]','$parametros[7]','$parametros[8]',
                            '$parametros[9]','$inter','$ahora','$rx_val_0')";
                    $resultado = mysqli_query($conn, $sql) or die ("error shell_uplinks_state.php 1 $sql".mysqli_error($conn));
                }
                ++$k2;
                $k = 0;
            }elseif($parametros[0] == 1){
                if($inter != 'Failure:'){
                    $sql = "INSERT INTO OLT_UPLINKS_STATE (equipo,ip,region,port,port_type,optic_status,
                            native_vlan,mdi,speed,duplex,flow_ctrl,active_state,link,interface,fecha,rx_power) VALUES 
                            ('$server','$ip','$region','$parametros[0]','$parametros[1]','$parametros[2]','$parametros[3]',
                            '$parametros[4]','$parametros[5]','$parametros[6]','$parametros[7]','$parametros[8]',
                            '$parametros[9]','$inter','$ahora','$rx_val_1')";
                    $resultado = mysqli_query($conn, $sql) or die ("error shell_uplinks_state.php 1 $sql".mysqli_error($conn));
                }
                ++$k2;
                $k = 0;
            }else{
                if($inter != 'Failure:'){
                    $sql = "INSERT INTO OLT_UPLINKS_STATE (equipo,ip,region,port,port_type,optic_status,
                            native_vlan,mdi,speed,duplex,flow_ctrl,active_state,link,interface,fecha,rx_power) VALUES 
                            ('$server','$ip','$region','$parametros[0]','$parametros[1]','$parametros[2]','$parametros[3]',
                            '$parametros[4]','$parametros[5]','$parametros[6]','$parametros[7]','$parametros[8]',
                            '$parametros[9]','$inter','$ahora','$rx_val_n')";
                    $resultado = mysqli_query($conn, $sql) or die ("error shell_uplinks_state.php 1 $sql".mysqli_error($conn));
                }
                ++$k2;
                $k = 0;
            }
        }
        ++$y;
    }
}

function estado_equipo($server,$puerto,$comando1,$comando2,$comando3,$comando4)
{
    $i=0;
    $b=0;
    $server = $server;
    $puerto = $puerto;
    $comando1 = $comando1;  
    $comando2 = $comando2;
    $comando3 = $comando3;
    $comando4 = $comando4;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout(30);
    //ini_set('memory_limit', '128M');
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
                    sleep(1);
                    $b++;
                }elseif($b == 1){
                    expect_send($stream, "\n");
                }
                break;
            case SHELL2:
                expect_send($stream, "config\n");
                sleep(1);
                break;
            case SHELL_CONFIG: //display port ddm-info 0
                if ($i==0) {
                    expect_send($stream, "$comando1 $puerto\n");
                }
                if ($i==1) {
                    expect_send($stream, "$comando2\n $comando3\n $comando4\n quit\n");
                }
                if ($i==2) {
                    expect_send($stream, "\n");
                }
                if ($i==3) {
                    expect_send($stream, "\n");
                }
                if ($i==4) {
                    expect_send($stream, "quit\n");  
                    expect_close($stream);
                    return $uname;
                }
                sleep(1);
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

function estado_equipo_x15($server,$puerto,$comando1,$comando2,$comando3)
{
    $i=0;
    $b=0;
    $server = $server;
    $puerto = $puerto;
    $comando1 = $comando1;  
    $comando2 = $comando2;
    $comando3 = $comando3;
    //echo $server.'--'.$puerto.'--'.$comando1.'--'.$comando2.'--'.$comando3."\n";
    //die;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout(30);
    //ini_set('memory_limit', '128M');
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
                sleep(1);
                break;
            case SHELL2:
                expect_send($stream, "config\n");
                sleep(1);
                break;
            case SHELL_CONFIG: //display port ddm-info 0/1
                if ($i==0) {
                    expect_send($stream, "$comando1 $puerto\n");
                }
                if ($i==1) {
                    expect_send($stream, "$comando2\n\n $comando3\n\n quit\n");
                }
                if ($i==2) {
                    expect_send($stream, "\n");
                }
                if ($i==3) {
                    expect_send($stream, "\n");
                }
                if ($i==4) {
                    expect_send($stream, "\n");
                }
                if ($i==5) {
                    expect_send($stream, "quit\n");  
                    expect_close($stream);
                    return $uname;
                }
                sleep(1);
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
echo "Fin:".date('Y-m-d H:i:s')."\n";
?>