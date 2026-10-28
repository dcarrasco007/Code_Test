<?php
date_default_timezone_set('America/Santiago');
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/../expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
// -------------------------------------------------------------------------
// Etapa 1 (bloqueos BD, 2026-10-28): el hijo ya no se conecta a la BD. Hace ping + telnet a su OLT y deja
// el resultado en <carpeta del lote>/<OLT>.json; el padre (alarmas_proceso_detalle.php) junta los .json de
// todas las OLTs y hace una sola carga. Plan: Pendientes/Bloqueos_BD_2026-10/03.1 - Plan implementacion etapa 1
// Uso: php alarmas_proceso_detalle_exped.php <ip> <server> <region> <lote_id> <carpeta_lote>
//include ('../../conexion/conexion_db.php');
echo "Inicio: ".date('d-m-Y H:i:s')."\n";
/* Etapa 1: sin conexión a la BD en el hijo
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
*/
/* Etapa 1: el monitoreo queda fuera del diseño, se deja comentado
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
*/
if (count($argv) < 6 || !is_dir($argv[5])) {
    die("Uso: php alarmas_proceso_detalle_exped.php <ip> <server> <region> <lote_id> <carpeta_lote>\n");
}

$fecha_proceso = date('Y-m-d');

$anio=date('Y');
        $ip = $argv[1];
        $server = $argv[2];
        $region = $argv[3];
        $lote = $argv[4];
        $dir = $argv[5];

        /* $ip = '10.99.24.68';
        $server = 'OLT-DURZUA-4';
        $region = 'METROPOLITANA SANTIAGO'; */

        // Resultado del hijo. escribir_resultado() deja el .json al terminar, también si el script se cae.
        $filas = ['CRITICAL' => [], 'MAJOR' => [], 'MINOR' => [], 'WARNING' => []];
        $cantidad = null;   // fila de OLT_CANTIDAD_ALARMAS_DETALLE; queda null si no hay ping (igual que antes)
        $estado = 'OK';
        register_shutdown_function('escribir_resultado');

        $data = [];
        $y = ping_ip($ip);
        if(trim($y) && $y < 100){
                if($ip == '10.99.24.68'){
                    $texto = estado_equipo2($ip);
                }else{
                    $texto = estado_equipo($ip);
                }
                if ($texto === 'TELNET_ERROR' || $texto === 'TIMEOUT' || $texto === 2) {
                    $estado = 'TELNET_ERROR';
                }
                foreach (explode(chr(13), $texto) as $linea)
                {
                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                    $data[] = $linea;
                }
                print_r($data);
                $puerto = '';
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

                        // Cada caso arma $fecha y $linea_alarma; la severidad se resuelve en registrar_alarma()
                        $linea_alarma = null;
                        if(stristr($data[$j-1],'QUALITY')){
                             echo"\nEntro QUALITY -1 --\n";
                            $fecha = explode('QUALITY',$data[$j-1]);
                            $fecha = explode(' ',trim($fecha[1]));
                            $fecha = $fecha[0].' '.$fecha[1];//agregado
                            $linea_alarma = trim($data[$j-1]);

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

                        }elseif(stristr($data[$j-1],'ERROR')){
                            $fecha = explode('ERROR',$data[$j-1]);
                            $fecha = explode(' ',trim($fecha[1]));
                            $fecha = $fecha[0].' '.$fecha[1];
                            $linea_alarma = trim($data[$j-1]);

                        }elseif(stristr($data[$j-2],'ERROR')){
                            $fecha = explode('ERROR',$data[$j-2]);
                            $fecha = trim($fecha[1]);
                            $linea_alarma = trim($data[$j-2]);

                        }elseif(stristr($data[$j-1],'EQUIPMENT')){
                            $fecha = explode('EQUIPMENT',$data[$j-1]);
                            $fecha = trim($fecha[1]);
                            $linea_alarma = trim($data[$j-1]);
                        }

                        if ($linea_alarma !== null) {
                            registrar_alarma($filas, $linea_alarma, $fecha, $server, $ip, $puerto, $alarma, $fecha_proceso, $anio);
                        }
                    }
                }

                $cantidad = [$server, $ip, $region,
                             count($filas['CRITICAL']), count($filas['MAJOR']),
                             count($filas['MINOR']), count($filas['WARNING']), $fecha_proceso];
        }else{
            $estado = 'SIN_PING';
        }
        unset($data);
    //}
/* Etapa 1: monitoreo comentado
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
*/
echo "Fin: ".date('d-m-Y H:i:s')."\n";

/**
 * Agrega la alarma a su severidad (antes eran 4 INSERT copiados en cada uno de los 5 casos del parseo).
 * Solo cuenta las alarmas con fecha del año en curso, como antes.
 */
function registrar_alarma(array &$filas, string $linea, string $fecha, string $server, string $ip,
                          string $puerto, string $alarma, string $fecha_proceso, string $anio): void
{
    if (!stristr($fecha, $anio)) return;
    foreach (['CRITICAL', 'MAJOR', 'MINOR', 'WARNING'] as $sev) {
        if (stristr($linea, $sev)) {
            $filas[$sev][] = [$server, $ip, $puerto, $alarma, $fecha, $fecha_proceso];
            return;
        }
    }
}

/**
 * Deja <carpeta_lote>/<OLT>.json. Se escribe a .tmp y se renombra, para que el padre nunca lea un archivo
 * a medio escribir. Corre al terminar el script (register_shutdown_function), también tras un error fatal.
 * Si timeout mata al hijo no corre: el padre lo anota como OLT sin respuesta.
 */
function escribir_resultado()
{
    global $dir, $server, $estado, $filas, $cantidad;
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $estado = 'ERROR';
    }
    $json = json_encode([
        'equipo'   => $server,
        'estado'   => $estado,
        'alarmas'  => $filas,
        'cantidad' => $cantidad,
        'fin'      => date('Y-m-d H:i:s'),
    ], JSON_INVALID_UTF8_SUBSTITUTE);
    $tmp = "$dir/$server.json.tmp";
    if ($json === false || file_put_contents($tmp, $json) === false || !rename($tmp, "$dir/$server.json")) {
        echo "Error escribiendo $dir/$server.json\n";
        return;
    }
    echo "Resultado: $dir/$server.json ($estado)\n";
}

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