<?php
date_default_timezone_set('America/Santiago');
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/../expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
//include ('../../conexion/conexion_db.php');
echo "Inicio: ".date('d-m-Y H:i:s')."\n";
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    $lote=$argv[4];
    $proceso_id=13;//ID DEL PROCESO YA REGISTRADO
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

$fecha_proceso = date('Y-m-d');

$anio=date('Y');
        $ip = $argv[1];
        $server = $argv[2];
        $region = $argv[3];

        /* $ip = '10.99.24.68';
        $server = 'OLT-DURZUA-4';
        $region = 'METROPOLITANA SANTIAGO'; */
        
        $cont_critical = 0;
        $cont_major = 0;
        $cont_minor = 0;
        $cont_warning = 0;
        
        $y = ping_ip($ip);
        if(trim($y)){
            if($y < 100){    
                if($ip == '10.99.24.68'){
                    $texto = estado_equipo2($ip);
                }else{
                    $texto = estado_equipo($ip);
                }
                foreach (explode(chr(13), $texto) as $linea)
                {
                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                    $data[] = $linea;
                }
                print_r($data);
                for($j=0;$j<count($data);$j++){
                    $line = preg_replace('/\s+/', ' ', $data[$j]);
                    if (stristr($line,'ALARM NAME')){                                 
                        $alarma = explode(':',$line);
                        $alarma = trim($alarma[1]);
                        echo"\nEntro como alarma--\n";
                        /*if(!stristr($data[$j+1],':')){
                            $alarma = $alarma.' '.trim($data[$j+1]);
                        }*/
                        
                        if(stristr($data[$j+2],'PARAMETERS')){
                             echo"\nEntro PARAMETERS +2 --\n";
                            $pto = explode(':',$data[$j+2]);
                            $port = explode(',',$pto[5]);
                            $puerto = $pto[1].$pto[2].$pto[3].$pto[4].$port[0];
                        }elseif(stristr($data[$j+3],'PARAMETERS')){
                            echo"\nEntro PARAMETERS +3 --\n";
                            $pto = explode(':',$data[$j+3]);
                            $port = explode(',',$pto[5]);
                            $puerto = $pto[1].$pto[2].$pto[3].$pto[4].$port[0];
                        }
                        
                        if(stristr($data[$j-1],'QUALITY')){
                             echo"\nEntro QUALITY -1 --\n";
                            $fecha = explode('QUALITY',$data[$j-1]);
                            $fecha = explode(' ',trim($fecha[1]));
                            $fecha = $fecha[0].' '.$fecha[1];//agregado
                            
                            $linea_alarma = trim($data[$j-1]);
                            
                            if(stristr($linea_alarma,'CRITICAL')){
                                if(stristr($fecha,$anio)){
                                    $cont_critical++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_CRITICAL
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                            
                                }
    
                            }elseif(stristr($linea_alarma,'MAJOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_major++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MAJOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                            
                                }
    
                            }elseif(stristr($linea_alarma,'MINOR')){   
                                if(stristr($fecha,$anio)){
                                    $cont_minor++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MINOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }elseif(stristr($linea_alarma,'WARNING')){
                                if(stristr($fecha,$anio)){
                                    $cont_warning++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_WARNING
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                             
                                }
    
                            }
     
                        }elseif(stristr($data[$j-2],'QUALITY')){   
                            echo"\nEntro QUALITY -2 --\n";                    
                            $fecha = explode('QUALITY',$data[$j-2]);
                            $fecha = trim($fecha[1]);
                            $fecha2 = explode(':',$data[$j-1]);
                            $inicio = substr($fecha2[0], -2);
                            $fecha = trim($fecha[1])." ".trim($inicio.":".$fecha2[1].":".$fecha2[2].":".$fecha2[3]);
                            $fecha = str_replace("DST", "", $fecha);
                            $fecha = str_replace("ST:::", "", $fecha);
                            //$fecha = $fecha.trim($data[$j-1]);//agregado
                            $linea_alarma = trim($data[$j-2]);
                            
                            if(stristr($linea_alarma,'CRITICAL')){
                                if(stristr($fecha,$anio)){
                                    $cont_critical++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_CRITICAL
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                             
                                }
    
                            }elseif(stristr($linea_alarma,'MAJOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_major++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MAJOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                            
                                }
    
                            }elseif(stristr($linea_alarma,'MINOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_minor++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MINOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }elseif(stristr($linea_alarma,'WARNING')){
                                if(stristr($fecha,$anio)){
                                    $cont_warning++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_WARNING
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }
                            
                        }elseif(stristr($data[$j-1],'ERROR')){
                            $fecha = explode('ERROR',$data[$j-1]);
                            $fecha = explode(' ',trim($fecha[1]));
                            $fecha = $fecha[0].' '.$fecha[1];
                            
                            $linea_alarma = trim($data[$j-1]);
                            
                            if(stristr($linea_alarma,'CRITICAL')){ 
                                if(stristr($fecha,$anio)){
                                    $cont_critical++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_CRITICAL
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                             
                                }
    
                            }elseif(stristr($linea_alarma,'MAJOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_major++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MAJOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                               
                                }
    
                            }elseif(stristr($linea_alarma,'MINOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_minor++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MINOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }elseif(stristr($linea_alarma,'WARNING')){
                                if(stristr($fecha,$anio)){
                                    $cont_warning++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_WARNING
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                             
                                }
    
                            }
                            
                        }elseif(stristr($data[$j-2],'ERROR')){
                            $fecha = explode('ERROR',$data[$j-2]);
                            $fecha = trim($fecha[1]);
                            
                            $linea_alarma = trim($data[$j-2]);
                            
                            if(stristr($linea_alarma,'CRITICAL')){
                                if(stristr($fecha,$anio)){
                                    $cont_critical++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_CRITICAL
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }elseif(stristr($linea_alarma,'MAJOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_major++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MAJOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }elseif(stristr($linea_alarma,'MINOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_minor++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MINOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                            
                                }
    
                            }elseif(stristr($linea_alarma,'WARNING')){
                                if(stristr($fecha,$anio)){
                                    $cont_warning++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_WARNING
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                             
                                }
                            }
                            
                        }elseif(stristr($data[$j-1],'EQUIPMENT')){
                            $fecha = explode('EQUIPMENT',$data[$j-1]);
                            $fecha = trim($fecha[1]);
                            
                            $linea_alarma = trim($data[$j-1]);
                            
                            if(stristr($linea_alarma,'CRITICAL')){
                                if(stristr($fecha,$anio)){
                                    $cont_critical++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_CRITICAL
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }elseif(stristr($linea_alarma,'MAJOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_major++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MAJOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                              
                                }
    
                            }elseif(stristr($linea_alarma,'MINOR')){
                                if(stristr($fecha,$anio)){
                                    $cont_minor++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_MINOR
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                            
                                }
    
                            }elseif(stristr($linea_alarma,'WARNING')){
                                if(stristr($fecha,$anio)){
                                    $cont_warning++;
                                    $query_alarma = "INSERT INTO OLT_ALARMAS_WARNING
                                                    (equipo,ip,puerta,alarma,fecha,fecha_proceso) VALUES 
                                                    ('$server','$ip','$puerto','$alarma','$fecha','$fecha_proceso')";
                                    $mysqli->query($query_alarma) or die("Error $query_alarma");                             
                                }
                            }
                            
                        }
                    }
                }
                    
                $query_cant = "INSERT INTO OLT_CANTIDAD_ALARMAS_DETALLE
                              (equipo,ip,region,cantidad_critical,cantidad_major,cantidad_minor,cantidad_warning,fecha) 
                               VALUES 
                              ('$server','$ip','$region','$cont_critical','$cont_major','$cont_minor','$cont_warning','$fecha_proceso')";
                $mysqli->query($query_cant) or die("Error $query_cant");
            }
        }  
        unset($data); 
    //}  
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
mysqli_close($mysqli);
echo "Fin: ".date('d-m-Y H:i:s')."\n";
function estado_equipo($server)
{
    $i = 0;
    $b = 0;
    $cantConfig = 0;

    $user = 'geret2016';
    $pass = 'Geret#2016*2021';

    expect_set_timeout(30);
    ini_set('memory_limit', '-1');

    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada

    if (!$stream) {
        return "TELNET_ERROR";
    }

    $uname = "";
    $x = true;

    while ($x) {

        $result = expect_expectl($stream, array(
            array("User name:", USER),
            array("User password:", PASSWORD, EXP_EXACT),
            array(".*\n", SALTO, EXP_REGEXP),
            array(".*>", SHELL, EXP_REGEXP),
            array(".*config.*.#", SHELL_CONFIG, EXP_REGEXP),
            array("OLT.*.#", SHELL2, EXP_REGEXP),
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:", SALIR, EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:", SALIR, EXP_EXACT),
            array(".*Are you sure to log out?.*:", SALIR, EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>|detail<K>|list<K> }:", ESPACIO2, EXP_EXACT),
        ), $match);

        switch ($result) {

            case PASSWORD:
                expect_send($stream, $pass . "\n");
                break;

            case SALIR:
                expect_send($stream, "y\n");
                sleep(1);
                $x = false;
                break;

            case USER:
                expect_send($stream, $user . "\n");
                break;

            case SHELL:
                if ($b == 0) {
                    expect_send($stream, "enable\n");
                    sleep(1);
                    $b++;
                } else {
                    expect_send($stream, "\n");
                }
                break;

            case SHELL2:
                if ($cantConfig == 0) {
                    expect_send($stream, "config\n");
                    sleep(1);
                } else {
                    expect_send($stream, "quit\n");
                    sleep(1);
                }
                $cantConfig++;
                break;

            case SHELL_CONFIG:
                if ($i == 0) {
                    expect_send($stream, "display alarm active all\n");
                    sleep(1);
                } elseif ($i == 1) {
                    expect_send($stream, "quit\n");
                    sleep(1);
                }
                $i++;
                break;

            case SALTO:
                $uname .= $match[0];
                break;

            case ESPACIO:
                expect_send($stream, " ");
                break;

            case ESPACIO2:
                expect_send($stream, "\n");
                break;

            case EXP_TIMEOUT:
                expect_close($stream);
                return "TIMEOUT";
                break;

            case EXP_EOF:
                // Telnet cerró la conexión → salir del loop
                $x = false;
                break;
        }
    }

    expect_close($stream);
    return $uname;
}


function estado_equipo2($server)
{
    $i=0;
    $server = $server;
    $cantConfig = 0;
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
            array("{ <cr>|detail<K>|list<K> }:", ESPACIO3,EXP_EXACT),
            array("{ lock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                break;
            case SALIR:
                    expect_send($stream, "y\n");
                    //echo"entro en salir";
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
                    //echo "quit 2";
                }
                $cantConfig++;
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    expect_send($stream, "display alarm active all\n");
                }
                if ($i==1) {
                    expect_send($stream, "quit\n");  
                    /* expect_close($stream);
                    return $uname; */
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
            case ESPACIO3:
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