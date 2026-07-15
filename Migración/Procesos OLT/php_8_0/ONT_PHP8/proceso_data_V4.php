<?php
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
include ('/u01/crontab127/conexion/conexion_db.php');
echo $hora_inicio=date("H:i:s");
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
// Validar conexión
if ($mysqli->connect_errno) {
    echo "Error de conexión (" . $mysqli->connect_errno . "): " . $mysqli->connect_error . "\n";
    exit();
}
if (!$mysqli->set_charset("utf8")) {
    echo "Error cargando el conjunto de caracteres utf8: " . $mysqli->error . "\n";
    exit();
}
 //-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    $lote=$argv[6];
    $proceso_id=16;//ID DEL PROCESO YA REGISTRADO
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
//$mysqli ->  set_charset("utf8");
/* $truncate = "TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE_COMPLETO_TEST";
$mysqli->query($truncate) or die("Error query ont $truncate"); */
    $ip = $argv[1];
    $server = $argv[2];
    $modelo = $argv[3];
    $region = $argv[5];
    $comuna = $argv[4];
    /* $ip = '10.99.25.98';
    $server = 'OLT-ZOFRI-1';
    $modelo = 'MA5600T';
    $region = 'TARAPACA';
    $comuna = 'IQUIQUE'; */
    $puertos=obtenerPuertos($ip,$mysqli);
    print_r($puertos);
    
    $y = ping_ip($ip);
    $tarjetas = "";
    $cont_up = 0;
    $cont_pon = 0;
    $cont_poder = 0;
    $cont_con = 0;
    $puertas_vacias = array();
    $puertos_totales = array();
    $puerto_funcion_global = array();
    
//print_r($detalle);
    if(trim($y)){
        if($y < 100){ 
            //------ inicio carga de version---------

            $texto=estado_equipo_version($ip);
            foreach (explode(chr(13), $texto) as $linea)
                            {
                                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                                $linea = str_replace(array('','[16D','[16D [16','[16D                [16D','[16D','[16D                [16D ',' [16D [16D ',' [16D[16D','[1D',
                            '[37D','[37D                                     ','[37D                                     [37D',"---- More ( Press 'Q' to break ) ----",' [1D','}:','Command:',"% Unknown command, the error locates at '^'",'^','EchoLife:'),'',$linea);
                                $data3[] = $linea;
                            } 
            for($i= 0;$i<count($data3);$i++){
                if (strpos($data3[$i],"F/S/P/ONT-ID") !== false) {
                            $section = 5; continue;
                }
                // === ONT Version ===
                        
                if ($section === 5 && preg_match('/^\s*(\d+)\/\s*(\d+)\/\s*(\d+)\/\s*(\d+)\s+(\S+)\s+([\w\-]+)\s+(\S+)/', $data3[$i], $m)) {
                            $ont_data_version["ONT Version"][] = array(
                                "Frame" => $m[1],
                                "Slot" => $m[2],
                                "Port" => $m[3],
                                "ONT ID" => $m[4],
                                "Vendor" => $m[5],
                                "ONT Model" => $m[6],
                                "Software Version" => $m[7]
                            );
                }


            }             
            
            print_r($ont_data_version["ONT Version"]);    

            
        
            $texto = estado_equipo($ip,$puertos, $modelo);   
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                $linea = str_replace(array('','[16D','[16D [16','[16D                [16D','[16D','[16D                [16D ',' [16D [16D ',' [16D[16D','[1D',
            '[37D','[37D                                     ','[37D                                     [37D',"---- More ( Press 'Q' to break ) ----",' [1D','}:','Command:',"% Unknown command, the error locates at '^'",'^','EchoLife:'),'',$linea);
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
                     $array14, $array15, $array16) = array_pad($arrays, 16, []);
            }
            $mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
            $mysqli ->  set_charset("utf8");
            echo "---------------------------------";
            if(isset($puertos[0])){
                $puerto1=$puertos[0];
                echo "\nPuerto a revisar por funcion-----$puerto1\n";
                obtenerData2($array1, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[1])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[1];
                obtenerData2($array2, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[2])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[2];
                obtenerData2($array3, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[3])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[3];
                obtenerData2($array4, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[4])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[4];
                obtenerData2($array5, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[5])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[5];
                obtenerData2($array6, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[6])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[6];
                obtenerData2($array7, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[7])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[7];
                obtenerData2($array8, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[8])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[8];
                obtenerData2($array9, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[9])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[9];
                obtenerData2($array10, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[10])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[10];
                obtenerData2($array11, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[11])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[11];
                obtenerData2($array12, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[12])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[12];
                obtenerData2($array13, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            if(isset($puertos[13])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[13];
                obtenerData2($array14, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion -----$puerto1\n";
            }
            if(isset($puertos[14])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[14];
                obtenerData2($array15, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
            if(isset($puertos[15])){
                 echo "\nPuerto a revisar por funcion-----$puerto1\n";
                $puerto1=$puertos[15];
                obtenerData2($array16, $server, $ip, $region,$comuna, $puerto1,$ont_data_version);
                echo "\nFin Puerto a revisar por funcion-----$puerto1\n";
            }
        }
    }

    //print_r($detalle);
    unset($data);
    unset($array_1);
    unset($array_2);
  }  
    function obtenerData2($log,$server,$ip,$region,$comuna, $puerto1,$ont_data_version){
        global $mysqli;
        global $puertas_vacias;
        global $puerto_funcion_global;
        $current_port = null;
        $section = 0;
        $ont_data = array();
        $puerto_funcion=array();
        $contador=0;
        $fecha_registro = date('Y-m-d 08:00:00', strtotime('+1 day'));
        
        //print_r($detalle);
        // Variable para saber si estamos dentro de una tabla delimitada por ----

        for ($i=0; $i < count($log); $i++) {

            
            // Detectar puerto cuando aparezca "In port"
            if (preg_match('/In port\s+([\d\/ ]+)/', $log[$i], $matches)) {
                // Normalizar quitando espacios intermedios
                $current_port = str_replace(" ", "", $matches[1]);
                $puerto_funcion_global[]=$current_port;
                if($contador==0){
                    $puerto_funcion[]="0/".$puerto1."/0";
                    $contador++;
                }else{
                    $puerto_funcion[]=$current_port;
                }
                if (!isset($ont_data[$current_port])) {
                    $ont_data[$current_port] = array(
                        "ONT Info Summary" => array(),
                        "ONT Info Details" => array(),
                        "ONT Optical Info" => array(),
                        "ONT Profile" => array()
                    );
                }
                continue;
            }

            // Cambiar de sección según encabezado detectado
            if (strpos($log[$i], "ONT  Run") !== false) {
                $section = 1; continue;
            }
            if (strpos($log[$i], "ONT        SN") !== false) {
                $section = 2; continue;
            }
            if (strpos($log[$i], "ONT  Rx power") !== false) {
                $section = 3; continue;
            }
            if (preg_match('/ONT-?ID\s+Line profile name/i', $log[$i])) {
                $section = 4; continue;
            }
            


                // === ONT Info Summary ===
                if ($section === 1 && preg_match('/^\s*(\d+)\s+(\w+)\s+((?:\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})|-)\s+((?:\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})|-)\s+(\w+|-)/', $log[$i], $m)) {
                    $ont_data[$current_port]["ONT Info Summary"][] = array(
                        "ONT ID" => $m[1],
                        "State" => $m[2],
                        "Last UpTime" => $m[3],
                        "Last DownTime" => $m[4],
                        "Last DownCause" => $m[5]
                    );
                }

                // === ONT Info Details ===
                if ($section === 2 && preg_match('/^\s*(\d+)\s+([A-F0-9]+)\s+(.+?)\s+(\d+|-)\s+(.+?)\s+(.+)$/i', $log[$i], $m)) {
                    $ont_data[$current_port]["ONT Info Details"][] = array(
                        "ONT ID" => $m[1],
                        "SN" => $m[2],
                        "Type" => $m[3],
                        "Distance" => $m[4],
                        "Rx/Tx Power" => $m[5],
                        "Description" => trim($m[6])
                    );
                }
                // === ONT Optical Info ===
                if ($section === 3 && preg_match('/^\s*(\d+)\s+([\-\d.]+|-)'
                    .'\s+([\-\d.]+|-)'
                    .'\s+([\-\d.]+|-)'
                    .'\s+([\-\d.]+|-)'
                    .'\s+([\-\d.]+|-)'
                    .'\s+([\-\d.]+|-)'
                    .'\s+(\d+)/', $log[$i], $m)) {

                    $ont_data[$current_port]["ONT Optical Info"][] = array(
                        "ONT ID" => $m[1],
                        "Rx power" => $m[2],
                        "Tx Power" => $m[3],
                        "OLT Rx ONT power" => $m[4],
                        "Temperature" => $m[5],
                        "Voltage" => $m[6],
                        "Current" => $m[7],
                        "Distance" => $m[8]
                    );
                }

                // === ONT Profile ===
                if ($section === 4 && preg_match('/^\s*(\d+)\s+([\w\d\-_]+)\s+([\w\d\-_]+)/', $log[$i], $m)) {
                    $ont_data[$current_port]["ONT Profile"][] = array(
                        "ONT ID" => $m[1],
                        "Line Profile" => $m[2],
                        "Service Profile" => $m[3]
                    );
                }

            
        }


        // Imprimir los resultados
         /*foreach ($ont_data as $port => $data) {
            echo "\n**Port: $port**\n";
            echo "ONT Info Summary:\n";
            print_r($data["ONT Info Summary"]);
        
            echo "\nONT Info Details:\n";
            print_r($data["ONT Info Details"]);
        
            echo "\nONT Optical Info:\n";
            print_r($data["ONT Optical Info"]);

            echo "\nONT Profile:\n";
            print_r($data["ONT Profile"]);
            echo "\nONT Version:\n"; 
            print_r($data["ONT Version"]);
        } */
        
        echo "\n**Data**\n";
        //print_r($ont_data);
        echo "\n*******\n";
        
        // Imprimir los resultados
        foreach ($ont_data as $port => $data) {
            echo "\n**Port: $port**\n";
            /* if($port=='0/1/2'){
                echo "\n**ONT Info Details: $port**\n";
                print_r($data["ONT Info Details"]);
                echo "\n**ONT Optical Info: $port**\n";
                print_r($data["ONT Optical Info"]);
                echo "\n**ONT Info Summary: $port**\n";
                print_r($data["ONT Info Summary"]);
                echo "\n**ONT Profile: $port**\n";
                print_r($data["ONT Profile"]);
            } */
            $idsONT = array();
            foreach ($data["ONT Info Details"] as $item2) {
                    $idsONT[] = $item2["ONT ID"];                
            }
             
            print_r($idsONT);
            if(empty($idsONT)){
                if (empty($port)) {
                    echo"\n Entro a guardado vacio por puerta!!!!!\n";
                    // Buscar el último puerto válido en lista_puertos
                    $ultimo_puerto = end($puerto_funcion); // ej: "0/2/8"
                    
                    if ($ultimo_puerto) {
                        $aux = explode("/", $ultimo_puerto);
                        $aux[2] = intval($aux[2]) + 1; // sumamos +1 al último dígito
                        $port = implode("/", $aux);   // queda "0/2/9"
                    }
                }
                echo"\n Entro a guardado vacio por puerta: $port \n";
                $puertas_vacias[]=$port;
                print_r($puertas_vacias);
            }
            $auxpuerta=explode("/",$port);
            
           foreach($idsONT as $row ) {
            echo "\n**IDs: $row**\n";
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
                $line_profile = '';
                $version_sw = '';
                
                foreach ($data["ONT Profile"] as $item) {
                    $ont_id_profile = $item["ONT ID"];
                    if($row==$ont_id_profile){
                        $service_profile = $item["Service Profile"];
                        $line_profile = $item["Line Profile"];
                        break;
                    }
                
                }
                foreach ($data["ONT Optical Info"] as $item) {
                    $ont_id_info = $item["ONT ID"];
                    if($row==$ont_id_info){
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
                    if($row==$ont_id_details){
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
                    if($row==$ont_id_summary){
                        echo"\nencontro mach estado:$row v/s $ont_id_summary \n";
                        $rx_power2 = $item["Rx power"];
                        $estado = $item["State"];
                        $up = $item["Last UpTime"];
                        $down = $item["Last DownTime"];
                        break;
                    }

                }
                
                if($type!='5612' && $type!='SmartAX OT928G'){

                    for ($i=0; $i <count($ont_data_version["ONT Version"]) ; $i++) { 
                        $rev=$ont_data_version["ONT Version"][$i];
                        if($rev['Frame']==$auxpuerta[0] && $rev['Slot']==$auxpuerta[1] && $rev['Port']==$auxpuerta[2] && $rev['ONT ID']==$row){
                        $versionSW=$rev['Software Version'];
                        echo"\nEncontro versión!!!!\n";
                        break;
                        }
                    }

               
                    $query="INSERT INTO Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO
                    (equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name, onu_alias, sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time, distancia, line_profile_name, service_profile_name, fecha_registo, modelo,version_sw)
                    VALUES('$server', '$ip', '$auxpuerta[0]', '$auxpuerta[1]', '$auxpuerta[2]', '$row', '$estado', '$region', '$comuna', '$description', '$description', '$sn', '$OLT_Rx_ONT_power', '$tx_power', '$rx_power', '$voltage', '$temperature', '$up', '$down', '$distance', '$line_profile', '$service_profile', '$fecha_registro','$type','$versionSW');";
                    $result=$mysqli->query($query) or die("Error query ont detalle 1 $query");
                    if ($result) {
                        echo "Inserción : ".$port." ID ONT: ".$row." || ";
                    } else {
                        echo"------------ Error en la consulta: " . $mysqli->error . " - Query: " . $query;
                    }
                }
            
            }
            
        
        }
    }  
    //die("Fin antes del repaso luego eliminar este die");
    echo"\nFin proceso inicia repaso\n";
    //Validador de puertos
                $texto = estado_equipo_repaso_2($ip,$puertos, $modelo);
                foreach (explode(chr(13), $texto) as $linea)
                {
                    $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
                    $linea = str_replace(array('','[16D','[16D [16','[16D                [16D','[16D','[16D                [16D ',' [16D [16D ',' [16D[16D','[1D',
                '[37D','[37D                                     ','[37D                                     [37D',"---- More ( Press 'Q' to break ) ----",' [1D','}:','Command:',"% Unknown command, the error locates at '^'",'^','EchoLife:'),'',$linea);
                    $data1[] = $linea;
                } 
                //print_r($data1);
                for ($i=0; $i < count($data1); $i++) {
                    // Detectar puerto cuando aparezca "In port"
                    if (preg_match('/In port\s+([\d\/ ]+)/', $data1[$i], $matches)) {
                        // Normalizar quitando espacios intermedios
                        $current_port = str_replace(" ", "", $matches[1]);  
                        $puertos_totales[]=$current_port;
                        
                    } 
                }   
                echo"\n puertos_totales:\n";
                print_r($puertos_totales);
                echo"\n puerto_funcion_global:\n";print_r($puerto_funcion_global);
                $array_puertos_revisar=array_diff($puertos_totales,$puerto_funcion_global);
                echo"\n Diferencia de array:\n";print_r($array_puertos_revisar);
                echo"\n puertas_vacias:\n";print_r($puertas_vacias);
                echo"\n Agregando puertas si es necesario:\n";
                 // 2. Revisar y agregar solo si no está en $array3
                foreach ($array_puertos_revisar as $valor) {
                    if (!in_array($valor, $puertas_vacias)) {
                        $puertas_vacias[] = $valor;
                    }
                }
                echo"\n puertas_vacias V2:\n";print_r($puertas_vacias);
    
    if(!empty($puertas_vacias)){
        echo"\nInicio Repaso de puertas vacias\n";

        $texto =estado_equipo_repaso($ip,$puertas_vacias, $modelo);
        unset($puertas_vacias);

        foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
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
                     $array14, $array15, $array16) = array_pad($arrays, 16, []);
            }
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
            if(isset($puertos[15])){
                $puerto1=$puertos[15];
                obtenerData2($array16,$server);
            }
         
    }
    
    if(!empty($puertas_vacias)){

        echo"\nInicio Repaso de puertas vacias\n";

        $texto =estado_equipo_repaso($ip,$puertas_vacias, $modelo);
        unset($puertas_vacias);

        foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
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
                     $array14, $array15, $array16) = array_pad($arrays, 16, []);
            }
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
            if(isset($puertos[15])){
                $puerto1=$puertos[15];
                obtenerData2($array16,$server);
            }
         
    }
    
    //print_r($detalle);
    
    unset($data);
    unset($array_1);
    unset($array_2);


      
    
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
function estado_equipo($server, $puertos, $modelo)
{
    $i=0;
    $cantConfig = 0;
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
    expect_set_timeout(60);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
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
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                break;
            case SALIR:
           
                sleep(1);
                expect_send($stream, "y\n");
                sleep(1);
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
                    sleep(1);
                }
                $cantConfig++;
            break;
            case SHELL_CONFIG:
                //echo "entra 1";
                    
                    if($a<count($comandos)){    
                       
                        $var = $comandos[$a];
                        //echo $var;
                        sleep(1);
                        expect_send($stream, $var. "\n");
                        $a++;
                        sleep(1);
                    }else{    
                        sleep(1);
                        expect_send($stream, "quit\n");  
                        expect_close($stream);
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
function estado_equipo_repaso($server, $puertos, $modelo)
{
    $i=0;
    $server = $server;
    $puertos = $puertos;
    $cantidadPuertos=count($puertos);

    for ($e=0; $e <$cantidadPuertos ; $e++) {
        if($puertos[$e]!=''){
            $divisiones=explode("/",$puertos[$e]);
            $comandos[]='interface gpon 0/'.$divisiones[1];
            $comandos[]="display ont info summary $divisiones[2]";
            $comandos[]="display ont optical-info $divisiones[2] all";
            $comandos[]="display ont profile $divisiones[2] all";
            $comandos[]='quit';
        }
    }
    print_r($comandos);
    
    $user = 'geretont';
    $pass = 'Geret#2024*2029';
    expect_set_timeout(60);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
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
                sleep(1);
                break;
            case SHELL_CONFIG:
                //echo "entra 1";
                    
                    if($a<count($comandos)){    
                       
                        $var = $comandos[$a];
                        //echo $var;
                        sleep(1);
                        expect_send($stream, $var. "\n");
                        $a++;
                        sleep(1);
                    }else{    
                        sleep(1);
                        expect_send($stream, "quit\n");  
                        expect_close($stream);
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
function estado_equipo_repaso_2($server, $puertos, $modelo)
{
    $i=0;
    $server = $server;
    $puertos = $puertos;

    $comandos[]="display ont info summary 0";
    $comandos[]='quit';
   
    print_r($comandos);
    
    $user = 'geretont';
    $pass = 'Geret#2024*2029';
    expect_set_timeout(60);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
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
                sleep(1);
                break;
            case SHELL_CONFIG:
                //echo "entra 1";
                    
                    if($a<count($comandos)){    
                       
                        $var = $comandos[$a];
                        //echo $var;
                        sleep(1);
                        expect_send($stream, $var. "\n");
                        $a++;
                        sleep(1);
                    }else{    
                        sleep(1);
                        expect_send($stream, "quit\n");  
                        expect_close($stream);
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
function estado_equipo_version($server)
{
    $i=0;
    $server = $server;
    
    $comandos[]="display ont version 0 all";
    $comandos[]='quit';
    $comandos[]='quit';
   
    print_r($comandos);
    
    $user = 'geretont';
    $pass = 'Geret#2024*2029';
    expect_set_timeout(60);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
    $uname = "";
    $x = true;
    $a=0;
    $C=0;
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
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:", ACEPTAR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",ACEPTAR,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                break;
            case ACEPTAR:
                sleep(1);
                expect_send($stream, "y\n");
                sleep(1);
                expect_close($stream);
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
                if($C == 0){
                    expect_send($stream, "config\n");
                    $C++;
                }elseif($C == 1){
                    expect_send($stream, "quit\n");
                }
                sleep(1);
                break;
            case SHELL_CONFIG:
                //echo "entra 1";
                    
                    if($a<count($comandos)){    
                       
                        $var = $comandos[$a];
                        //echo $var;
                        sleep(1);
                        expect_send($stream, $var. "\n");
                        $a++;
                        sleep(1);
                    }else{    
                        sleep(1);
                        expect_send($stream, "quit\n");  
                        expect_close($stream);
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
function obtenerPuertos($ip,$mysqli){

    //obtener puertas pon
    $sql_ip = "SELECT tarjetas,equipo FROM OLT_VOLTAJE_TARJETA WHERE ip = '$ip'";
    $result = $mysqli->query($sql_ip) or die("error 2");
    $row = $result->fetch_array(MYSQLI_NUM);
    if (!$result) {
        echo "Error en la consulta: " . $mysqli->error . "\n";
        exit();
    }
    $puertas=explode("<br>",$row[0]);
    print_r($puertas);
    for ($i=3; $i < count($puertas); $i++) { 
        $div=explode(" ",$puertas[$i]);
        //print_r($div);
    if($div[2]!=17){
        if($div[4]!='' && ($div[6]=='Normal' || $div[6]=='Mismatch' || $div[6]=='Failed' || $div[5]=='Mismatch')){
            if($div[4]=='H801GPBC' || $div[4]=='H802GPBD' || $div[4]=='H805GPBD' || $div[4]=='H901FGHF' || $div[4]=='H807GPBH' || $div[4]=='H901GPHF' || $div[4]=='H902GPHF' || $div[4]=='H902FLHF' || $div[4]=='H903GPHF' || $div[4]=='H906FLHF' || $div[4]=='H901FLHF'){
            //if($div[4]=='H903GPHF'){
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