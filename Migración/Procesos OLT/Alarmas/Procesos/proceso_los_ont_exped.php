<?php
//include ('/var/www/html/conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
$fecha = date("Y-m-d H:i:s");
$anio = date("Y");
$hora=date('Y-m-d H:i:s');
//-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    echo "Lote: ".$lote=$argv[5];
    $proceso_id=23;//ID DEL PROCESO YA REGISTRADO
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
//$hora = date ( 'Y-m-d');
        $modelo=$argv[4];
        $tipo=$argv[3];
        $server=$argv[2];
        $ip=$argv[1];
        /* $modelo='MA5800-X15';
        $tipo='1';
        $server='OLT-QUILICURA-2';
        $ip='10.99.26.238'; */
        
        $y = ping_ip($ip);
        if(trim($y)){       
        $texto=estado_equipo($ip,$modelo);
        
        foreach (explode(chr(13), $texto) as $linea)
        {
            $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
            $linea = preg_replace('/\s+/', ' ', $linea);
            $linea = str_replace(array('','[16D','[16D [16','[16D                [16D','[16D','[16D                [16D ',' [16D [16D ',' [16D[16D','[1D',
            '[37D','[37D                                     ','[37D                                     [37D',"---- More ( Press 'Q' to break ) ----",' [1D','}:','Command:',"% Unknown command, the error locates at '^'",'^'),'',$linea);
            $data[] = $linea;
        }
        print_r($data);
        
        $alarms = array();
        $count = count($data);

        for ($i = 0; $i < $count - 3; $i++) {
            $line = trim($data[$i]);
        
            // Buscar línea que inicia con un número (alarmSN) y contiene la frase de inicio
            if (preg_match('/^\d+\s+\d{4}-\d{2}-\d{2}.*The feeder fiber is broken or OLT$/', $line)) {
                $line2 = isset($data[$i + 1]) ? trim($data[$i + 1]) : '';
                $line3 = isset($data[$i + 2]) ? trim($data[$i + 2]) : '';
        echo "|----entro001\n";
                if (($line2 == 'can not receive any expected optical' && (strpos($line3, 'signals(LOS)')!== false) || ($line2 == 'can not receive any expected optical signals(LOS)') !== false)) {
                    $alarmData = array();
                    echo "|----entro\n";
                        // Extraer fecha y hora sin zona horaria
                    if (preg_match('/^\d+\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $fechaMatch)) {
                        $alarmData['Fecha'] = $fechaMatch[1];
                    }
                    // Extraer info de líneas siguientes
                    $info = '';
                    for ($j = 2; $j <= 6 && isset($data[$i + $j]); $j++) {
                        $info .= ' ' . trim($data[$i + $j]);
                    }
        
                    if (preg_match('/FrameID:\s*(\d+),\s*SlotID:\s*(\d+),\s*PortID:\s*(\d+)/', $info, $matches)) {
                        $alarmData['puerto'] = $matches[1]."/".$matches[2]."/".$matches[3];
                    }
        
                    if (preg_match('/The number of affected ONTs:\s*(\d+)/', $info, $matches)) {
                        $alarmData['Affected ONTs'] = $matches[1];
                    }
        
                    if (preg_match('/The number of DGi ONTs:\s*(\d+)/', $info, $matches)) {
                        $alarmData['DGi ONTs'] = $matches[1];
                    }
        
                    // Nuevo: capturar "The list of affected ONTs"
                    if (preg_match('/The list of affected ONTs:\s*([\d\-,]*)/', $info, $m)) {
                        $list = $m[1];

                        // Buscar si sigue en la línea siguiente
                        $k = $i + $j;
                        while ($k < $count && preg_match('/^\s*[\d,\-]+$/', trim($data[$k]))) {
                            if(strpos($data[$k], '------------------------------------------------------------------------')){
                                break;
                            }
                            $list .= ',' . trim($data[$k]);
                            $k++;
                        }

                        $alarmData['List of affected ONTs'] = $list;
                    }

                    if (!empty($alarmData)) {
                        $alarms[] = $alarmData;
                    }
                }
            }
        }
        
        // Mostrar resultado
        foreach ($alarms as $index => $alarm) {
            echo "Alarma #" . ($index + 1) . ":\n";
            $puerto='';
            $Affected_ONTs='';
            $DGi_ONTs='';
            $List_of_affected_ONTs='';
            foreach ($alarm as $key => $value) {
                if ($key=='puerto') {
                    $puerto=$value;
                }
                if ($key=='Affected ONTs') {
                    $Affected_ONTs=$value;
                }
                if ($key=='DGi ONTs') {
                    $DGi_ONTs=$value;
                }
                if ($key=='List of affected ONTs') {
                    $List_of_affected_ONTs=$value;
                }
                if ($key=='Fecha') {
                    $fecha=$value;
                }
                //echo "  $key: $value\n";
            }
            if($Affected_ONTs!='' && $List_of_affected_ONTs!=''){
                $queryval="INSERT INTO Aden.OLT_ALARMA_LOS_ONT
                            (server, ip, puerto, affected_ont, dgi_onts, list_affected_onts, fecha_alarma, fecha_registro)
                            VALUES('$server', '$ip', '$puerto', '$Affected_ONTs', '$DGi_ONTs', '$List_of_affected_ONTs','$fecha', NOW())";
                            $result1=$mysqli->query($queryval) or die("Error $queryval");
                $queryval2="INSERT INTO Aden.OLT_ALARMA_LOS_ONT_HISTORICO
                            (server, ip, puerto, affected_ont, dgi_onts, list_affected_onts, fecha_alarma, fecha_registro)
                            VALUES('$server', '$ip', '$puerto', '$Affected_ONTs', '$DGi_ONTs', '$List_of_affected_ONTs','$fecha', NOW())";
                            $result1=$mysqli->query($queryval2) or die("Error $queryval2");
            }
            echo "\n";
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
    mysqli_close($mysqli);
    die("\nFin ----");
function estado_equipo($server,$modelo){
    $i=0;
    $cant2 = 0;
    $cantConfig = 0;
    $comandos2=
    $comandos2 = array("display alarm active alarmlevel major list");
    $cant_com2 = count($comandos2);
    $server = $server;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $uname = "";
    $x = true;
    while ($x){
        switch (expect_expectl($stream, array(
        array("User name:", USER),
        array("User password:",PASSWORD,EXP_EXACT),
        array(".*\n",SALTO,EXP_REGEXP),
        array(".*>",SHELL,EXP_REGEXP),
        array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
        array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
        array("OLT.*.#",SHELL2,EXP_REGEXP),
        array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]",SALIR,EXP_EXACT),
        array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
        array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
        array("---- More ( Press 'Q' to break ) ----", ESPACIO),
        array("{ <cr>|detail<K>|list<K> }:", ESPACIO2,EXP_EXACT),
        ), $match))
        {
        case PASSWORD:
            fwrite($stream, $pass . "\n");
            break;
        case SALIR:
           
                sleep(1);
                fwrite($stream, "y\n");
                sleep(1);
                return $uname;
                break;
        case USER:
            fwrite($stream, $user . "\n");
            break;
        case SHELL:
            if($b == 0){
                fwrite($stream, "enable\n");
                sleep(1);
                $b++;
            }elseif($b == 1){
                fwrite($stream, "\n");
            }
            break;
        case SHELL2:
            if($cantConfig==0){
                fwrite($stream, "config\n");
                sleep(1);
            }else{
                fwrite($stream, "quit\n");
                sleep(1);
            }
            
            $cantConfig++;
            break;
        case SHELL_CONFIG:
            if ($i==0) {
                fwrite($stream, "display alarm active alarmlevel major list\n");
                sleep(1);
                $i++;
            }
            if ($i>=2) {
                //echo"entro en quit";
                sleep(1);
                fwrite($stream, "quit\n");  

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
            fwrite($stream, " ");
            $uname .= $match[0];
            break;
        case ESPACIO2:
            fwrite($stream, "\n");
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