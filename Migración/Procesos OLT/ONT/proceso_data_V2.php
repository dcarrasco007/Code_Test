<?php
include ('/u01/crontab127/conexion/conexion_db.php');
echo $hora_inicio=date("H:i:s");
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
/* $truncate = "TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE";
$mysqli->query($truncate) or die("Error query ont $truncate");*/
    $ip = $argv[1];
    $server = $argv[2];
    $modelo = $argv[3];
    $region = $argv[4];
    /* $ip = '10.99.25.90';
    $server = 'OLT-DURZUA-12';
    $modelo = 'MA5800-X15';
    $region = 'METROPOLITANA_SANTIAGO'; */
    $puertos=obtenerPuertos($ip,$mysqli);
    print_r($puertos);
    $y = ping_ip($ip);
    $tarjetas = "";
    $cont_up = 0;
    $cont_pon = 0;
    $cont_poder = 0;
    $cont_con = 0;
    
    
//print_r($detalle);
    if(trim($y)){
        if($y < 100){   
                $texto = estado_equipo($ip,$puertos, $modelo);         
                
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                $linea = str_replace(array('','[16D','[16D [16','[16D                [16D','[16D','[16D                [16D ',' [16D [16D ',' [16D[16D','[1D',
            '[37D','[37D                                     ','[37D                                     [37D',"---- More ( Press 'Q' to break ) ----",' [1D','}:','Command:',"% Unknown command, the error locates at '^'",'^'),'',$linea);
                $data[] = $linea;
            } 
            print_r($data);  
            /* die("Fin 2"); */
            
            $grep = preg_grep("/interface gpon 0/", $data);
            $llaves = array_keys($grep);
            echo "llaves: \n";
            print_r($llaves);
            echo "\n";

            if (count($puertos) == 1) {
                for ($i = $llaves[0]; $i < count($data); $i++) {
                    $array1[] = $data[$i];
                }
            } else {
                // Crear un array dinámico de porciones
                $arrays = array();
                
                for ($i = 0; $i < count($llaves); $i++) {
                    $arrays[$i] = array();
                }
                
                for ($i = 0; $i < count($data); $i++) {
                    // Encontrar en qué sección cae el índice actual
                    for ($j = 0; $j < count($llaves) - 1; $j++) {
                        if ($i >= $llaves[$j] && $i < $llaves[$j + 1]) {
                            $arrays[$j][] = $data[$i];
                            break;
                        }
                    }
                    
                    // Última sección (si es el último índice de llaves)
                    if ($i >= end($llaves)) {
                        $arrays[count($llaves) - 1][] = $data[$i];
                    }
                }
            
                // Extraer los arrays en variables individuales si es necesario
                list($array1, $array2, $array3, $array4, $array5, $array6, $array7, 
                     $array8, $array9, $array10, $array11, $array12, $array13, 
                     $array14, $array15) = array_pad($arrays, 15, []);
            }

           /*  if(count($puertos)==1){
                for($i = $llaves[0]; $i <count($data);$i++){
                    $array1[]=$data[$i];
                }
            }else{
                for($i = 0; $i <count($data);$i++){
                    if($i>=$llaves[0] && $i<$llaves[1]){
                        $array1[]=$data[$i];
                    }else if($i>=$llaves[1] && $i<$llaves[2]){
                        $array2[]=$data[$i];
                    }else if($i>=$llaves[2] && $i<$llaves[3]){
                        $array3[]=$data[$i];
                    }else if($i>=$llaves[3] && $i<$llaves[4]){
                        $array4[]=$data[$i];
                    }else if($i>=$llaves[4] && $i<$llaves[5]){
                        $array5[]=$data[$i];
                    }else if($i>=$llaves[5] && $i<$llaves[6]){
                        $array6[]=$data[$i];
                    }else if($i>=$llaves[6] && $i<$llaves[7]){
                        $array7[]=$data[$i];
                    }else if($i>=$llaves[7] && $i<$llaves[8]){
                        $array8[]=$data[$i];
                    }else if($i>=$llaves[8] && $i<$llaves[9]){
                        $array9[]=$data[$i];
                    }else if($i>=$llaves[9] && $i<$llaves[10]){
                        $array10[]=$data[$i];
                    }else if($i>=$llaves[10] && $i<$llaves[11]){
                        $array11[]=$data[$i];
                    }else if($i>=$llaves[11] && $i<$llaves[12]){
                        $array12[]=$data[$i];
                    }else if($i>=$llaves[12] && $i<$llaves[13]){
                        $array13[]=$data[$i];
                    }else if($i>=$llaves[13] && $i<$llaves[14]){
                        $array14[]=$data[$i];
                    }else if($i>=$llaves[14]){
                        $array15[]=$data[$i];
                    }
                }
            } */
            //echo'array 1: '.$llaves[0].'-------';
            /* print_r($array1);
            print_r($array2);
            print_r($array3);
            print_r($array4);
            print_r($array5);
            print_r($array6);
            print_r($array7);
            print_r($array8);
            print_r($array9);
            print_r($array10);
            print_r($array11);
            print_r($array12);
            print_r($array13);
            print_r($array14);
            print_r($array15); */
            //die("Fin");
            $mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
            $mysqli ->  set_charset("utf8");
            echo "---------------------------------";
            if(isset($puertos[0])){
                $puerto1=$puertos[0];
                
                obtenerData2($array1, $server);
                
            }
            
            if(isset($puertos[1])){
                $puerto1=$puertos[1];
                obtenerData2($array2,$server);
            }
            if(isset($puertos[2])){
                $puerto1=$puertos[2];
                obtenerData2($array3, $server);
            }
            if(isset($puertos[3])){
                $puerto1=$puertos[3];
                obtenerData2($array4, $server);
            }
            if(isset($puertos[4])){
                $puerto1=$puertos[4];
                obtenerData2($array5,$server );
            }
            if(isset($puertos[5])){
                $puerto1=$puertos[5];
                obtenerData2($array6,$server);
            }
            if(isset($puertos[6])){
                $puerto1=$puertos[6];
                obtenerData2($array7,$server);
            }
            if(isset($puertos[7])){
                $puerto1=$puertos[7];
                obtenerData2($array8,$server);
            }
            if(isset($puertos[8])){
                $puerto1=$puertos[8];
                obtenerData2($array9,$server);
            }
            if(isset($puertos[9])){
                $puerto1=$puertos[9];
                obtenerData2($array10,$server);
            }
            if(isset($puertos[10])){
                $puerto1=$puertos[10];
                obtenerData2($array11,$server);
            }
            if(isset($puertos[11])){
                $puerto1=$puertos[11];
                obtenerData2($array12,$server);
            }
            if(isset($puertos[12])){
                $puerto1=$puertos[12];
                obtenerData2($array13,$server);
            }
            if(isset($puertos[13])){
                $puerto1=$puertos[13];
                obtenerData2($array14,$server);
            }
            if(isset($puertos[14])){
                $puerto1=$puertos[14];
                obtenerData2($array15,$server);
            }
         
        }
    }
    //print_r($detalle);
    unset($data);
    unset($array_1);
    unset($array_2);
    
    function obtenerData2($log,$server){
        global $mysqli;
        $current_port = null;
        $section = 0;
        $ont_data = array();
        
        //print_r($detalle);
        for ($i=0; $i <count($log) ; $i++) { 
            
            //echo"----Linea:".$log[$i]."\n";
            // Identificar secciones
            if (strpos($log[$i], "ONT  Run") !== false) {
                $section = 1; // Sección ONT Info Summary
                //echo"--2--".$log[$i-2]."\n";
                preg_match('/\s*In port (\d+\/\d+\/\d+),/', $log[$i-2], $matches);
                $current_port = isset($matches[1]) ? $matches[1] : null;
                /* echo"--Entro 1\n";
                echo"--puerto: $current_port\n"; */
                // Si el puerto no existe en el array, inicializarlo
                if ($current_port !== null && !array_key_exists($current_port, $ont_data)) {
                    $ont_data[$current_port] = array(
                        "ONT Info Summary" => array(),
                        "ONT Info Details" => array(),
                        "ONT Optical Info" => array()
                    );
                }
                continue;
            }
            if (strpos($log[$i], "ONT        SN") !== false) {
                $section = 2; // Sección ONT Info Details
                //echo"--Entro 2\n";
                continue;
            }
            if (strpos($log[$i], "ONT  Rx power") !== false) {
                $section = 3; // Sección ONT Optical Info
                //echo"--Entro 3\n";
                continue;
            }
            if (strpos($log[$i], "ONT-ID    Line profile name") !== false) {
                $section = 4; // Nueva sección: ONT Profile
                continue;
            }
        
            // Procesar cada sección
            if ($section === 1 && preg_match('/^\s*(\d+)\s+(\w+)\s+([\d-]+ \d+:\d+:\d+)\s+([\d-]+ \d+:\d+:\d+)\s+([\w-]+)/', $log[$i], $matches)) {
                $ont_data[$current_port]["ONT Info Summary"][] = array(
                    "ONT ID" => $matches[1],
                    "State" => $matches[2],
                    "Last UpTime" => $matches[3],
                    "Last DownTime" => $matches[4],
                    "Last DownCause" => $matches[5]
                );
            }
        
            /* if ($section === 2 && preg_match('/^\s*(\d+)\s+([\w\d]+)\s+([\w\d-]+)\s+([\d-]+)\s+([-\d.]+\/[-\d.]+)\s+([\d-]+)/', $log[$i], $matches)) {
                $ont_data[$current_port]["ONT Info Details"][] = array(
                    "ONT ID" => $matches[1],
                    "SN" => $matches[2],
                    "Type" => $matches[3],
                    "Distance" => $matches[4], 
                    "Rx/Tx Power" => $matches[5], 
                    "Description" => $matches[6]
                );
            } */

            if ($section === 2 && preg_match('/^\s*(\d+)\s+([A-F0-9]+)\s+([\w-]+)\s+(\d+|-)\s+([-.\d]+\/[-.\d]+)\s+(.+)$/i', $log[$i], $matches)) {
            $ont_data[$current_port]["ONT Info Details"][] = array(
                "ONT ID" => $matches[1],
                "SN" => $matches[2],
                "Type" => $matches[3],
                "Distance" => $matches[4],
                "Rx/Tx Power" => $matches[5],
                "Description" => trim($matches[6])
            );
        }

        
            if ($section === 3 && preg_match('/^\s*(\d+)\s+([\d.-]+)\s+([\d.-]+)\s+([\d.-]+)\s+(\d+)\s+([\d.-]+)\s+(\d+)\s+(\d+)/', $log[$i], $matches)) {
                $ont_data[$current_port]["ONT Optical Info"][] = array(
                    "ONT ID" => $matches[1],
                    "Rx power" => $matches[2],
                    "Tx Power" => $matches[3],
                    "OLT Rx ONT power" => $matches[4],
                    "Temperature" => $matches[5],
                    "Voltage" => $matches[6],
                    "Current" => $matches[7],
                    "Distance" => $matches[8]
                );
            }

            if ($section === 4 && preg_match('/^\s*(\d+)\s+([\w\d_]+)\s+(.+)$/', $log[$i], $matches)) {
                $ont_data[$current_port]["ONT Profile"][] = array(
                    "ONT ID" => $matches[1],
                    "Line Profile" => $matches[2],
                    "Service Profile" => trim($matches[3]) // Asegura que no haya espacios extra
                );
            }
        }
        // Imprimir los resultados
        /* foreach ($ont_data as $port => $data) {
            echo "\n**Port: $port**\n";
            echo "ONT Info Summary:\n";
            print_r($data["ONT Info Summary"]);
        
            echo "\nONT Info Details:\n";
            print_r($data["ONT Info Details"]);
        
            echo "\nONT Optical Info:\n";
            print_r($data["ONT Optical Info"]);

            echo "\nONT Profile:\n";
            print_r($data["ONT Profile"]);
        } */
        // Imprimir los resultados
        foreach ($ont_data as $port => $data) {
            echo "\n**Port: $port**\n";
            $auxpuerta=explode("/",$port);
            $query1 = "SELECT 
            OLT_INFO_ONT_PRUEBA.equipo, 
            OLT_SERVER.ip, 
            OLT_SERVER.comuna, 
            CONCAT(OLT_INFO_ONT_PRUEBA.fn, '/', OLT_INFO_ONT_PRUEBA.sn, '/', OLT_INFO_ONT_PRUEBA.pn) AS puerta,
                OLT_INFO_ONT_PRUEBA.onu,
                OLT_INFO_ONT_PRUEBA.line_profile,
                OLT_INFO_ONT_PRUEBA.serial_number,
                OLT_SERVER.region,
                OLT_INFO_ONT_PRUEBA.`name`
            FROM OLT_INFO_ONT_PRUEBA 
            INNER JOIN OLT_SERVER 
                ON OLT_SERVER.`server` = OLT_INFO_ONT_PRUEBA.equipo
                    
            WHERE
            OLT_INFO_ONT_PRUEBA.equipo='$server'AND OLT_INFO_ONT_PRUEBA.fn = '$auxpuerta[0]' AND OLT_INFO_ONT_PRUEBA.sn='$auxpuerta[1]' AND OLT_INFO_ONT_PRUEBA.pn='$auxpuerta[2]'";
            $result1=$mysqli->query($query1) or die("Error query ont detalle 1 $query1");
            while ($row = $result1->fetch_array(MYSQLI_NUM)) {
                $rx_power = '';
                $estado = '';
                $up = '';
                $down = '';
                $sn = '';
                $type = '';
                $distance = '';
                $Rx_Tx_Power = '';
                $description = '';
                $rx_power = '';
                $tx_power = '';
                $OLT_Rx_ONT_power = '';
                $temperature = '';
                $voltage = '';
                $distance = '';
                $service_profile = '';
                
                foreach ($data["ONT Profile"] as $item) {
                    $ont_id_profile = $item["ONT ID"];
                    if($row[4]==$ont_id_profile){
                        $service_profile = $item["Service Profile"];
                        break;
                    }
                
                }
                foreach ($data["ONT Optical Info"] as $item) {
                    $ont_id_info = $item["ONT ID"];
                    if($row[4]==$ont_id_info){
                        $rx_power = $item["Rx power"];
                        $tx_power = $item["Tx Power"];
                        $OLT_Rx_ONT_power = $item["OLT Rx ONT power"];
                        $temperature = $item["Temperature"];
                        $voltage = $item["Voltage"];
                        $distance = $item["Distance"];
                        break;
                    }
                
                }
                foreach ($data["ONT Info Details"] as $item) {
                    $ont_id_details = $item["ONT ID"];
                    if($row[4]==$ont_id_details){
                        $sn = $item["SN"];
                        $type = $item["Type"];
                        $distance = $item["Distance"];
                        $Rx_Tx_Power = $item["Rx/Tx Power"];
                        $description = $item["Description"];
                        break;
                    }
                }
                foreach ($data["ONT Info Summary"] as $item) {
                    $ont_id_summary = $item["ONT ID"];
                    if($row[4]==$ont_id_summary){
                        $rx_power2 = $item["Rx power"];
                        $estado = $item["State"];
                        $up = $item["Last UpTime"];
                        $down = $item["Last DownTime"];
                        break;
                    }

                }
                $query="INSERT INTO Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO
                (equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name, onu_alias, sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time, distancia, line_profile_name, service_profile_name, fecha_registo, modelo)
                VALUES('$server', '$row[1]', '$auxpuerta[0]', '$auxpuerta[1]', '$auxpuerta[2]', '$row[4]', '$estado', '$row[7]', '$row[2]', '$row[8]', '$row[8]', '$row[6]', '$OLT_Rx_ONT_power', '$tx_power', '$rx_power', '$voltage', '$temperature', '$up', '$down', '$distance', '$row[5]', '$service_profile', NOW(),'$type');";
                $result=$mysqli->query($query) or die("Error query ont detalle 1 $query");
                if ($result) {
                    echo "Inserción : ".$port." ID ONT: ".$row[4]." || ";
                } else {
                    echo"------------ Error en la consulta: " . $mysqli->error . " - Query: " . $query;
                }
            
            }
            
        
        }
    }   
$hora_fin=date('H:i:s');

$inicio_timestamp = strtotime($hora_inicio);
$fin_timestamp = strtotime($hora_fin);

$diferencia_segundos = $fin_timestamp - $inicio_timestamp;

// Convierte la diferencia a formato H:i:s
$horas = floor($diferencia_segundos / 3600);
$minutos = floor(($diferencia_segundos % 3600) / 60);
$segundos = $diferencia_segundos % 60;

echo "Hora de inicio: $hora_inicio\n";
echo "Hora de fin: $hora_fin\n";
echo "Diferencia: " . sprintf("%02d:%02d:%02d", $horas, $minutos, $segundos);
function estado_equipo($server, $puertos, $modelo)
{
    $i=0;
    $server = $server;
    $puertos = $puertos;
    $cantidadPuertos=count($puertos);
    if($modelo=='MA5800-X15'){
        $cantidadSlot=16;//16
    }else{
        $cantidadSlot=8;//8
    }
    for ($e=0; $e <$cantidadPuertos ; $e++) {
        $comandos[]='interface gpon 0/'.$puertos[$e];
        for ($i=0; $i <$cantidadSlot ; $i++) { 
            $comandos[]="display ont info summary $i";
            $comandos[]="display ont optical-info $i all";
            $comandos[]="display ont profile $i all";
        } 
        $comandos[]='quit';
    }
//print_r($comandos);
    $user = 'geretont';
    $pass = 'Geret#2024*2029';
    ini_set("expect.timeout", 60);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $uname = "";
    $x = true;
    $a=0;
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
                fwrite($stream, $pass . "\n");
                break;
            case USER:
                fwrite($stream, $user . "\n");
                break;
            case SHELL:
                if($b == 0){
                    fwrite($stream, "enable\n");
                    $b++;
                }elseif($b == 1){
                    fwrite($stream, "\n");
                }
                break;
            case SHELL2:
                fwrite($stream, "config\n");
                sleep(1);
                break;
            case SHELL_CONFIG:
                //echo "entra 1";
                    
                    if($a<count($comandos)){    
                       
                        $var = $comandos[$a];
                        //echo $var;
                        sleep(1);
                        fwrite($stream, $var. "\n");
                        $a++;
                        sleep(1);
                    }else{    
                        sleep(1);
                        fwrite($stream, "quit\n");  
                        fclose($stream);
                        return $uname;
                    }
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

function verifica_equipo($texto){
    $texto=$texto;
    $val=1;
    foreach (explode(chr(13), $texto) as $linea){
        $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
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
function obtenerPuertos($ip,$mysqli){

    //obtener puertas pon
    $sql_ip = "SELECT tarjetas,equipo FROM OLT_VOLTAJE_TARJETA WHERE ip = '$ip'";
    $result = $mysqli->query($sql_ip) or die("error 2");
    $row = $result->fetch_array(MYSQLI_NUM);

    $puertas=explode("<br>",$row[0]);
    //print_r($puertas);
    for ($i=3; $i < count($puertas); $i++) { 
        $div=explode(" ",$puertas[$i]);
        //print_r($div);
    if($div[2]!=16){
        if($div[4]!='' && $div[6]=='Normal'){
            if($div[4]=='H801GPBC' || $div[4]=='H802GPBD' || $div[4]=='H805GPBD' || $div[4]=='H901FGHF' || $div[4]=='H807GPBH' || $div[4]=='H901GPHF' || $div[4]=='H902GPHF' || $div[4]=='H902FLHF'){
                $cantidadPuerta[]=$div[2];
            }
        }
    }else{
        break;
    }  
    }
    
   

    return $cantidadPuerta;
}

?>