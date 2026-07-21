<?php
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/../expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
//include ('../../../conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
$year=date('Y');
$tabla="OLT_VLAN_TRAFICO_".$year."";
//mysqli_query($mysqli,"TRUNCATE TABLE OLT_VLAN_TRAFICO");

$sql_ip = "SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER";
$result = $mysqli->query($sql_ip) or die("error 1 $sql_ip");

$query_vlan = "SELECT OLT_VLAN_SERVICIOS.vlan,OLT_VLAN_SERVICIOS.servicio
               FROM OLT_VLAN_SERVICIOS";
$result_vlan = $mysqli->query($query_vlan) or die("error 2 $query_vlan");
while($row_vlan = $result_vlan->fetch_array(MYSQLI_NUM)){
    $array_vlan [] = 'display traffic vlan '.$row_vlan[0];
    $array_servicio [$row_vlan[0]] = $row_vlan[1];
}

$cantidad = count($array_vlan);

while($row = $result->fetch_array(MYSQLI_NUM)){
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    //$ip = '10.99.9.142';
    //$server = 'OLT-NVA13NORTE-3';
    //$ip = '10.99.24.68';
    //$server = 'OLT-DURZUA-4';

    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){     
            
            if($ip == '10.99.24.68'){
                $texto = estado_equipo2($ip,$array_vlan,$cantidad);
            }else{
                $texto = estado_equipo($ip,$array_vlan,$cantidad);
            }
        
            //$texto = estado_equipo($ip,$puerta_10G);
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                $data[] = $linea;
            }
            
                       
            for($j=0;$j<count($data);$j++){
                $line = preg_replace('/\s+/', ' ', $data[$j]);
                if(stristr($line,"display traffic vlan") && !stristr($line," display traffic vlan")){
                    if(!stristr($data[$j+1],"Failure") && !stristr($data[$j+1],"}:")){
                        echo " entro aca $line \n";
                        $datos = explode(' ',trim($data[$j+6]));//posible 6
                        $datos = array_filter($datos, "strlen");
                        $datos = array_values($datos);
                        $vlan = $datos[0];
                        $traf_up = $datos[1]/1000;
                        $traf_down = $datos[2]/1000;
                        
                        foreach($array_servicio as $key => $serv){
                            if($vlan == $key){
                                $servicio = $serv;
                            }
                        }
                        
                        if($vlan != ''){
                            $query_insert = "INSERT INTO OLT_VLAN_TRAFICO
                                             (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                             VALUES
                                             ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                            $result_insert = $mysqli->query($query_insert) or die("error 3 $query_insert");
                            $query_insert2 = "INSERT INTO $tabla
                                             (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                             VALUES
                                             ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                            $result_insert2 = $mysqli->query($query_insert2) or die("error 3 $query_insert2");
                        }
                    }elseif(stristr($data[$j+1],"}:")){
                        if(!stristr($data[$j+5],"Failure")){
                            $datos = explode(' ',trim($data[$j+10]));
                            $datos = array_filter($datos, "strlen");
                            $datos = array_values($datos);
                            $vlan = $datos[0];
                            $traf_up = $datos[1]/1000;
                            $traf_down = $datos[2]/1000;
                            
                            foreach($array_servicio as $key => $serv){
                                if($vlan == $key){
                                    $servicio = $serv;
                                }
                            }
                            
                            if($vlan != ''){
                                $query_insert = "INSERT INTO OLT_VLAN_TRAFICO
                                                 (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                                 VALUES
                                                 ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                                $result_insert = $mysqli->query($query_insert) or die("error 4 $query_insert");
                                $query_insert2 = "INSERT INTO $tabla
                                             (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                             VALUES
                                             ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                            $result_insert2 = $mysqli->query($query_insert2) or ("error 3 $query_insert2");
                            }
                        }else{
                            $vlan = explode(' ',trim($line));
                            $vlan = $vlan[3];
                            $traf_up = 0;
                            $traf_down = 0;
                            
                            foreach($array_servicio as $key => $serv){
                                if($vlan == $key){
                                    $servicio = $serv;
                                }
                            }
                            
                            if($vlan != ''){
                                $query_insert = "INSERT INTO OLT_VLAN_TRAFICO
                                                 (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                                 VALUES
                                                 ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                                $result_insert = $mysqli->query($query_insert) or die("error 5 $query_insert");
                                $query_insert2 = "INSERT INTO $tabla
                                             (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                             VALUES
                                             ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                            $result_insert2 = $mysqli->query($query_insert2) or die("error 3 $query_insert2");
                            }
                        }                        
                    }else{
                        $vlan = explode(' ',trim($line));
                        $vlan = $vlan[3];
                        $traf_up = 0;
                        $traf_down = 0;
                        
                        foreach($array_servicio as $key => $serv){
                            if($vlan == $key){
                                $servicio = $serv;
                            }
                        }
                        
                        $query_insert = "INSERT INTO OLT_VLAN_TRAFICO
                                         (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                         VALUES
                                         ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                        $result_insert = $mysqli->query($query_insert) or die("error 5 $query_insert");
                        $query_insert2 = "INSERT INTO $tabla
                                             (equipo,ip,vlan,fecha_hora,uplink,downlink,servicio)
                                             VALUES
                                             ('$server','$ip','$vlan',NOW(),'$traf_up','$traf_down','$servicio')";
                            $result_insert2 = $mysqli->query($query_insert2) or die("error 3 $query_insert2");
                    }
                }
            }          
        }
    }
    //echo '<pre>';
    //print_r($data);
    //echo '</pre>';
    //die;
    unset($data);
}

mysqli_close($mysqli);

function estado_equipo($server,$vlan,$cantidad)
{
    $server = $server;
    $vlan = $vlan;   
    $cantidad = $cantidad;
    $cant2 = 0;
    $b=0;
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
                if($cant2<$cantidad){
                    $var = $vlan[$cant2];
                    ++$cant2;
                    expect_send($stream, "$var\n");
                }else{
                    break 2;
                    $x = false;
                }
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
                expect_send($stream, "\n\n");
                $uname .= $match[0];
                break;
            case EXP_EOF:
                break;
        }
    }
    $x = false;
    expect_close($stream);
    return $uname;
}

function estado_equipo2($server,$vlan,$cantidad)
{
    $server = $server;
    $vlan = $vlan;   
    $cantidad = $cantidad;
    $cant2 = 0;
    
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
            array("OLT-DURZUA-4(config)#",SHELL_CONFIG,EXP_EXACT),
            array("OLT-DURZUA-4#",SHELL2,EXP_EXACT),
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
                expect_send($stream, "enable\n");
                break;
            case SHELL2:
                expect_send($stream, "config\n");
                break;
            case SHELL_CONFIG:
                if($cant2<$cantidad){
                    $var = $vlan[$cant2];
                    ++$cant2;
                    expect_send($stream, "$var\n");
                }else{
                    break 2;
                    $x = false;
                }
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
    $x = false;
    expect_close($stream);
    return $uname;
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