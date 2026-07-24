<?php
date_default_timezone_set('America/Santiago');
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/../expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
include ('/var/www/procesos/php/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----Hora
$sql_hora = "SELECT NOW()";
$resultHora = $mysqli->query($sql_hora) or die("error $sql_hora");
$hora = $resultHora->fetch_array(MYSQLI_NUM);
echo "Inicio: ".$hora[0]."\n";
//-----Fin

$fecha = date("Y-m-d");

$sql_ip = "SELECT OLT_VOLTAJE_TARJETA.ip,OLT_VOLTAJE_TARJETA.equipo,OLT_VOLTAJE_TARJETA.region
           FROM OLT_VOLTAJE_TARJETA
           WHERE
           OLT_VOLTAJE_TARJETA.cantidad = '0'
UNION 

SELECT OLT_SERVER.ip, OLT_SERVER.server, OLT_SERVER.region FROM OLT_SERVER 
			WHERE OLT_SERVER.server NOT IN (SELECT DISTINCT (OLT_VOLTAJE_TARJETA.equipo) FROM OLT_VOLTAJE_TARJETA)";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");

$sql_tipo_tarjeta = "SELECT nombre,tipo_tarjeta FROM OLT_TIPO_TARJETA WHERE tipo_tarjeta IS NOT NULL";
$result_tipo = $mysqli->query($sql_tipo_tarjeta) or die("error $sql_tipo_tarjeta");

while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    $sql_delete1 = "DELETE FROM OLT_VOLTAJE_TARJETA WHERE OLT_VOLTAJE_TARJETA.equipo = '$row[1]'";
    $mysqli->query($sql_delete1) or die("error $sql_delete1");
    
    $sql_delete2 = "DELETE FROM OLT_TARJETA_CANTIDAD WHERE OLT_TARJETA_CANTIDAD.equipo = '$row[1]'";
    $mysqli->query($sql_delete2) or die("error $sql_delete2");
    
    $ip = $row[0];
    //$ip = '10.99.26.254';
    $server = $row[1];
    //$server = 'OLT-DRRENEANZIETAPCS-2';
    $region = $row[2];
    //$region = 'XIII';
    $total_tarjetas = 0;
    $y = ping_ip($ip);
    $tarjetas = "";
    $cont_up = 0;
    $cont_pon = 0;
    $cont_poder = 0;
    $cont_con = 0;
    $cont_e1 = 0;
    if(trim($y)){
        if($y < 100){   
            if($ip == '10.99.24.68'){
                $texto = estado_equipo2($ip);
            }else{
                $texto = estado_equipo($ip);
            }
            $valida= verifica_equipo($texto);
                //echo 'El Valida tiene: '.$valida.' |||';
                if ($valida==2) {
                    if($ip == '10.99.24.68'){
                        $texto = estado_equipo2($ip);
                    }else{
                        $texto = estado_equipo($ip);
                    }
                    $ERROR++;
                    $valida2= verifica_equipo($texto);
                    if ($valida2==2) {
                        if($ip == '10.99.24.68'){
                            $texto = estado_equipo2($ip);
                        }else{
                            $texto = estado_equipo($ip);
                        }
                        $ERROR2++;
                    }
                }
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                $data[] = $linea;
            }
            
            for($j=0;$j<count($data);$j++){
                $line = preg_replace('/\s+/', ' ', $data[$j]);
                if(stristr($line,"unit:Watt")){
                    $linea_voltaje = $data[$j+2];
                    $voltaje = explode(' ',$linea_voltaje);
                    $cont_vol = 0;
                    foreach($voltaje as $val_vol){                       
                        if($val_vol != ''){
                            if($cont_vol == 1){
                                $voltaje_f = trim($val_vol);//VOLTAJE
                            }
                            $cont_vol++;
                        }
                    }
                    
                }               
                
                if(stristr($line,"BoardName")){
                    $tarjetas .= '----------------------------------------------------------------------------------<br>';
                    $tarjetas .= $line.'<br>';
                    $tarjetas .= '----------------------------------------------------------------------------------<br>';
                    for($k=$j+2;$k<count($data);$k++){
                        $line2 = preg_replace('/\s+/', ' ', $data[$k]);
                        
                        //GUARDA CADA NOMBRE DE LAS TARJETAS AUNQUE ESTE REPETIDO
                        $board_name = explode(' ',$line2);   
                                          
                        if(count($board_name) == 5 || count($board_name) == 6 || count($board_name) == 7){ 
                            $slot = trim($board_name[1]);
                            $nombre = trim($board_name[2]);
                            $query_tipo = "SELECT tipo_tarjeta FROM OLT_TIPO_TARJETA WHERE nombre = '$nombre'";
                            $result_t = $mysqli->query($query_tipo) or die("error $query_tipo");
                            $row_t = $result_t->fetch_array(MYSQLI_NUM);
                            if($row_t[0]){
                                if(stristr($row_t[0], 'UPLINK')){
                                    $tippp = 'UPLINK';
                                }
                                if(stristr($row_t[0], 'PON')){
                                    $tippp = 'PON';
                                }
                                if(stristr($row_t[0], 'CONTROL')){
                                    $tippp = 'CONTROL';
                                }
                                if(stristr($row_t[0], 'PODER')){
                                    $tippp = 'PODER';
                                }
                                if(stristr($row_t[0], 'E1')){
                                    $tippp = 'E1';
                                }
                                
                                switch($tippp){                                
                                    case 'UPLINK':  
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','UPLINK','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_up++;
                                                    break;
                                    case 'PON':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PON','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_pon++;
                                                    break;
                                    case 'CONTROL':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','CONTROL','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_con++;
                                                    break;
                                    case 'PODER':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PODER','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_poder++;
                                                    break;
                                    case 'E1':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','E1','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_e1++;
                                                    break;
                                }
                            }   
                        }elseif(count($board_name) == 14){
                            $slot = trim($board_name[10]);
                            $nombre = trim($board_name[11]);
                            $query_tipo = "SELECT tipo_tarjeta FROM OLT_TIPO_TARJETA WHERE nombre = '$nombre'";
                            $result_t = $mysqli->query($query_tipo) or die("error $query_tipo");
                            $row_t = $result_t->fetch_array(MYSQLI_NUM);
                            if($row_t[0]){
                                if(stristr($row_t[0], 'UPLINK')){
                                    $tippp = 'UPLINK';
                                }
                                if(stristr($row_t[0], 'PON')){
                                    $tippp = 'PON';
                                }
                                if(stristr($row_t[0], 'CONTROL')){
                                    $tippp = 'CONTROL';
                                }
                                if(stristr($row_t[0], 'PODER')){
                                    $tippp = 'PODER';
                                }
                                if(stristr($row_t[0], 'E1')){
                                    $tippp = 'E1';
                                }
                                
                                switch($tippp){                                
                                    case 'UPLINK':  
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','UPLINK','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_up++;
                                                    break;
                                    case 'PON':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PON','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_pon++;
                                                    break;
                                    case 'CONTROL':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','CONTROL','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_con++;
                                                    break;
                                    case 'PODER':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PODER','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_poder++;
                                                    break;
                                    case 'E1':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','E1','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_e1++;
                                                    break;
                                }
                            }
                        }                       
                        
                        if(!stristr($line2,"-------------------------------------------------------------------------")){
                            if(stristr($line2,"---- More ( Press 'Q' to break ) ----[37D [37D")){
                                $line2 = str_replace("---- More ( Press 'Q' to break ) ----[37D [37D"," ",$line2);
                            }
                            $linea3 = str_replace(" ","  ",$line2);
                            $tarjetas .= $linea3.'<br>';
                            
                            $cantidad_t = explode(' ',$line2);
                            $num = count($cantidad_t);
                            if($num > 3){
                                $total_tarjetas++;
                            }
                        }                      
                    }
                }          
            }   
            
            $query_voltaje = "INSERT INTO OLT_VOLTAJE_TARJETA
                              (equipo,ip,region,voltaje,cantidad,tarjetas,fecha) VALUES 
                              ('$server','$ip','$region','$voltaje_f','$total_tarjetas','$tarjetas','$fecha')";
            $mysqli->query($query_voltaje) or die("Error query voltaje $query_voltaje");
            
            $query_insert_tipo = "INSERT INTO OLT_TARJETA_CANTIDAD
                                 (equipo,ip,region,uplink,pon,control,poder,e1,fecha) VALUES
                                 ('$server','$ip','$region','$cont_up','$cont_pon','$cont_con','$cont_poder','$cont_e1','$fecha')";
            $mysqli->query($query_insert_tipo) or die("Error query tipo $query_insert_tipo");

        }
    }
    unset($data);
}
//-----Hora
$sql_hora = "SELECT NOW()";
$resultHora = $mysqli->query($sql_hora) or die("error $sql_hora");
$hora = $resultHora->fetch_array(MYSQLI_NUM);
echo "Fin: ".$hora[0]."\n";
//-----Fin

mysqli_close($mysqli);
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
            //array("*.The user password is too simple.*",SHELL,EXP_REGEXP),
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
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
                    expect_send($stream, "display power 0\n");
                    sleep(2);
                }
                if ($i==1) {
                    expect_send($stream, "display board 0\n");
                    sleep(2);
                }
                if ($i>=2) {
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

function estado_equipo2($server)
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
            array("OLT-DURZUA-4>",SHELL,EXP_EXACT),
            array("OLT-DURZUA-4(config)#",SHELL_CONFIG,EXP_EXACT),
            array("OLT-DURZUA-4#",SHELL2,EXP_EXACT),
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
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
                    expect_send($stream, "display power 0\n");
                }
                if ($i==1) {
                    expect_send($stream, "display board 0\n");
                }
                if ($i>=2) {
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