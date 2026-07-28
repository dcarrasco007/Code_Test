<?php
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

 //-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    $lote=$argv[5];
    $proceso_id=19;//ID DEL PROCESO YA REGISTRADO
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

//mysqli_query($mysqli,"TRUNCATE TABLE OLT_UPTIME");
    $ip = $argv[1];
    $server = $argv[2];
    $region = $argv[3]; 
    $modelo = $argv[4]; 
    echo $fecha = date('Y-m-d H:i:s');

  
    /* $ip = '10.99.26.250';
    $server = 'OLT-LASCONDES-7';
    $region = 'VIII';
    $modelo = 'MA5800-X15'; */ 

    /* $ip = '10.99.24.116';
    $server = 'OLT-LASCONDES-3';
    $region = 'VIII';
    $modelo = 'MA5600T';  */
    
    $cont_alarmas = 0;
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){   
            if($modelo=="MA5800-X15"){   
                $texto = estado_equipo($ip);

                foreach (explode(chr(13), $texto) as $linea)
                {
                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                    $linea = str_replace("---- More ( Press 'Q' to break ) ----[37D                                     [37D","",$linea);
                    $data[] = $linea;
                }
                //echo"<pre>";print_r($data);echo"</pre>";
                $grep2 = preg_grep("/display alarm history alarmlevel minor detail/", $data);
                $comando1 = key($grep2);
                $grep2 = preg_grep("/display alarm history alarmlevel cleared detail/", $data);
                $comando2 = key($grep2);
                echo 'llave1: '.$comando1;

                echo ' llave2: '.$comando2;
                for ($i=$comando1; $i <$comando2 ; $i++) { 
                    $datos1[]=$data[$i];
                }
                for ($i=$comando2; $i <count($data) ; $i++) { 
                    $datos2[]=$data[$i];
                }
                echo"<pre>";print_r($datos1);echo"</pre>";
                echo"<pre>";print_r($datos2);echo"</pre>";
                //------------- display alarm history alarmlevel minor----------//
                $grep = preg_grep("/The power input of the local shelf fails/", $datos1);
                $coincidencia = key($grep);
                //echo "coincidencia: ".$coincidencia."\n";
                echo "Coincidencias: <pre>".print_r($grep); echo "</pre>";
                foreach ($grep as $key => $value) {
                        if($coincidencia!=''){
                            echo "Hay alarma 1\n";
                            $fecha_1=explode(" ",$datos1[$key-1]);
                        // echo "<pre>".print_r($fecha_1); echo "</pre>";
                            $tipo_alarma=$fecha_1[4].' '.$fecha_1[5];
                            $id_alarma=$fecha_1[3];
                            $hora=$fecha_1[9];
                            $fecha=$fecha_1[8];
                            $hora=explode("-",$hora);
                            $hora=$hora[0];
                            $fechaCompleta=$fecha.' '.$hora;
                            $slot=explode(":",$datos1[$key+2]);//aqui!
                            $slot=$slot[2].$slot[3];
                            $slot=str_replace("SlotID","/",$slot);
                            $slot=str_replace(", Board Name","",$slot);
                            $slot=str_replace(",","",$slot);
                            $slot=str_replace(" ","",$slot);
                            if($slot=='0/18' || $slot=='0/19'){
                                echo "\nModelo: ".$modelo. "Slot: ".$slot."\n";
                                $tipoAlarma=$fecha_1[2].$fecha_1[3];
                                $query_cantidad = "SELECT COUNT(id) AS CANTIDAD
                                                    FROM Aden.OLT_ALARMA_ENERGIA WHERE estado=1 AND id_alarma=$id_alarma AND alarm_name='The power input of the local shelf fails' AND equipo='$server'";
                                $resultado=$mysqli->query($query_cantidad) or die("Error query cantidad");
                                $row = $resultado->fetch_array(MYSQLI_NUM);
                                if($row[0]>=1){
                                    echo"Registro Existente...\n";
                                }else{
                                    echo"Guardando registro...\n";
                                    echo $query_uptime = "INSERT INTO Aden.OLT_ALARMA_ENERGIA
                                    (equipo, ip, dif_hora, fecha_registro, fecha_alarma, fecha_recovery, puerto, tipo_alarma, estado, id_alarma, alarm_name)
                                    VALUE('$server','$ip','',NOW(),'','$fechaCompleta','$slot','$tipo_alarma',1,$id_alarma,'The power input of the local shelf fails')";
                                    $mysqli->query($query_uptime) or die("Error query 1");
                                }
                            }
                        }
                }
                //------------- display alarm history alarmlevel cleared----------//
                $grep = preg_grep("/ALARM NAME  : The power input of the local shelf recovers/", $datos2);
                $coincidencia1 = key($grep);
                echo "Coincidencias Ultimo: <pre>".print_r($grep); echo "</pre>";
                    foreach ($grep as $key => $value) {
                            if($coincidencia!=''){
                                echo "Hay alarma 2\n";
                                $fecha_1=explode(" ",$datos2[$key-1]);
                             echo "<pre>".print_r($fecha_1); echo "</pre>";
                                $tipo_alarma=$fecha_1[4].' '.$fecha_1[5];
                                $id_alarma=$fecha_1[3];
                                $hora=$fecha_1[9];
                                $fecha=$fecha_1[8];
                                $hora=explode("-",$hora);
                                $hora=$hora[0];
                                $fechaCompleta=$fecha.' '.$hora;
                                $slot=explode(":",$datos2[$key+2]);//aqui!
                                $slot=$slot[2].$slot[3];
                                $slot=str_replace("SlotID","/",$slot);
                                $slot=str_replace(", Board Name","",$slot);
                                $slot=str_replace(",","",$slot);
                                $slot=str_replace(" ","",$slot);
                                $tipoAlarma=$fecha_1[2].$fecha_1[3];
                                if($slot=='0/18' || $slot=='0/19'){
                                    echo "\nModelo: ".$modelo. "Slot: ".$slot."\n";
                                    echo $query_cantidad = "SELECT COUNT(id) AS CANTIDAD
                                                        FROM Aden.OLT_ALARMA_ENERGIA WHERE estado=2 AND id_alarma=$id_alarma AND alarm_name='The power input of the local shelf recovers' AND equipo='$server'";
                                    $resultado=$mysqli->query($query_cantidad);
                                    if (!$resultado) {
                                        echo "Error query cantidad: " .
                                            $mysqli->error .
                                            " | Query: " . $query_cantidad;
                                            break;

                                    }
                                    $row = $resultado->fetch_array(MYSQLI_NUM);
                                    if($row[0]>=1){
                                        echo"Registro Existente...\n";
                                    }else{
                                        echo"Guardando registro...\n";
                                        echo $query_uptime = "INSERT INTO Aden.OLT_ALARMA_ENERGIA
                                        (equipo, ip, dif_hora, fecha_registro, fecha_alarma, fecha_recovery, puerto, tipo_alarma, estado, id_alarma, alarm_name)
                                        VALUE('$server','$ip','',NOW(),'','$fechaCompleta','$slot','$tipo_alarma',2,$id_alarma,'The power input of the local shelf recovers')";
                                        $mysqli->query($query_uptime) or die("Error query 1");
                                    }
                                }
                            }
                    }
                
                
            }else{
                $texto = estado_equipo2($ip);
              
                foreach (explode(chr(13), $texto) as $linea)
                {
                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                    $data[] = $linea;
                }
                //echo"<pre>";print_r($data);echo"</pre>";
                $grep2 = preg_grep("/display alarm active alarmlevel major detail/", $data);
                $comando1 = key($grep2);
                $grep2 = preg_grep("/display alarm history alarmlevel major detail/", $data);
                $comando2 = key($grep2);
                echo 'llave1: '.$comando1;

                echo ' llave2: '.$comando2;
                for ($i=$comando1; $i <$comando2 ; $i++) { 
                    $datos1[]=$data[$i];
                }
                for ($i=$comando2; $i <count($data) ; $i++) { 
                    $datos2[]=$data[$i];
                }
                echo"<pre>";print_r($datos1);echo"</pre>";
                echo"<pre>";print_r($datos2);echo"</pre>";
                //------------- display alarm history alarmlevel minor----------//
                $grep = preg_grep("/The communication between the board and the control board/", $datos1);
                $coincidencia = key($grep);
                foreach ($grep as $key => $value) {
                
                    echo "coincidencia2: ".$value."\n";
                    if($coincidencia1!=''){
                        echo "Hay alarma 2\n";
                        $fecha2=explode(" ",$datos2[$key-1]);
                        echo "<pre>".print_r($fecha2); echo "</pre>";

                        $tipo_alarma=$fecha2[4].' '.$fecha2[5];
                        $id_alarma=$fecha2[3];
                        $fecha22=$fecha2[8];
                        $hora2=$fecha2[9];
                        $hora2=explode("-",$hora2);
                        $hora2=$hora2[0];
                        $fechaCompleta2=$fecha22.' '.$hora2;
                        $slot2=explode(":",$datos2[$key+3]);//aqui!
                        echo "\nSLOT<pre>".print_r($fecha2); echo "</pre>";
                        $slot2=$slot2[2].$slot2[3];
                        $slot2=str_replace("SlotID","/",$slot2);
                        $slot2=str_replace(", Board Name","",$slot2);
                        $slot2=str_replace(",","",$slot2);
                        $slot2=str_replace(" ","",$slot2);
                        echo "Slot: ".$slot2."\n";
                        if(($modelo=='MA5603T' && ($slot2=='0/10' || $slot2=='0/11')) || ($modelo=='MA5600T' && ($slot2=='0/19' || $slot2=='0/20')) || ($modelo=='MA5680T' && ($slot2=='0/19' || $slot2=='0/20'))){
                            echo "\nModelo: ".$modelo. "Slot: ".$slot2."\n";
                            $query_cantidad = "SELECT COUNT(id) AS CANTIDAD
                                                FROM Aden.OLT_ALARMA_ENERGIA WHERE estado=1 AND id_alarma=$id_alarma AND alarm_name='The communication between the board and the control board detail' AND equipo='$server'";
                            $resultado=$mysqli->query($query_cantidad);
                            if (!$resultado) {
                                        echo "Error query cantidad: " .
                                            $mysqli->error .
                                            " | Query: " . $query_cantidad;
                                            break;

                                    }
                            $row = $resultado->fetch_array(MYSQLI_NUM);
                            if($row[0]>=1){
                                echo"Registro Existente...\n";
                            }else{
                                echo"Guardando registro...\n";
                                $query_uptime = "INSERT INTO Aden.OLT_ALARMA_ENERGIA
                                (equipo, ip, dif_hora, fecha_registro, fecha_alarma, fecha_recovery, puerto, tipo_alarma, estado, id_alarma, alarm_name)
                                VALUE('$server','$ip','',NOW(),'','$fechaCompleta2','$slot2','$tipo_alarma',1,$id_alarma,'The communication between the board and the control board detail')";
                                $mysqli->query($query_uptime) or die("Error query fan");
                            }
                        }   
                        
                    } 
                }
                //------------- display alarm history alarmlevel cleared----------//
                $grep = preg_grep("/The communication between the board and the control board/", $datos2);
                echo "Coincidencias: <pre>".print_r($grep); echo "</pre>";
                $coincidencia1 = key($grep);
                foreach ($grep as $key => $value) {
                
                    echo "coincidencia2: ".$value."\n";
                    if($coincidencia1!=''){
                        echo "Hay alarma 2\n";
                        $fecha2=explode(" ",$datos2[$key-1]);
                        echo "<pre>".print_r($fecha2); echo "</pre>";

                        $tipo_alarma=$fecha2[4].' '.$fecha2[5];
                        $id_alarma=$fecha2[3];
                        $fecha22=$fecha2[8];
                        $hora2=$fecha2[9];
                        $hora2=explode("-",$hora2);
                        $hora2=$hora2[0];
                        $fechaCompleta2=$fecha22.' '.$hora2;
                        $slot2=explode(":",$datos2[$key+3]);//aqui!
                        echo "\nSLOT<pre>".print_r($fecha2); echo "</pre>";
                        $slot2=$slot2[2].$slot2[3];
                        $slot2=str_replace("SlotID","/",$slot2);
                        $slot2=str_replace(", Board Name","",$slot2);
                        $slot2=str_replace(",","",$slot2);
                        $slot2=str_replace(" ","",$slot2);
                        echo "Slot: ".$slot2."\n";
                        

                        // Crear objetos DateTime para las fechas y horas
                        //$datetime1 = new DateTime($fecha . ' ' . $hora);
                        //$datetime2 = new DateTime($fecha22 . ' ' . $hora2);

                        // Calcular la diferencia entre las fechas y horas
                        //$interval = $datetime1->diff($datetime2);

                        // Mostrar la diferencia
                        // Mostrar la diferencia
                        //$diferencia= $interval->format('%R%a días') .' '. $interval->format('%H:%I:%S Horas');
                        //echo $diferencia."\n"; 
                        if(($modelo=='MA5603T' && ($slot2=='0/10' || $slot2=='0/11')) || ($modelo=='MA5600T' && ($slot2=='0/19' || $slot2=='0/20')) || ($modelo=='MA5680T' && ($slot2=='0/19' || $slot2=='0/20'))){
                            echo "\nModelo: ".$modelo. "Slot: ".$slot2."\n";
                            $query_cantidad = "SELECT COUNT(id) AS CANTIDAD
                                                FROM Aden.OLT_ALARMA_ENERGIA WHERE estado=2 AND id_alarma=$id_alarma AND alarm_name='The communication between the board and the control board fails' AND equipo='$server'";
                            $resultado=$mysqli->query($query_cantidad);
                            if (!$resultado) {
                                        echo "Error query cantidad: " .
                                            $mysqli->error .
                                            " | Query: " . $query_cantidad;
                                            break;

                                    }
                            $row = $resultado->fetch_array(MYSQLI_NUM);
                            if($row[0]>=1){
                                echo"Registro Existente...\n";
                            }else{
                                echo"Guardando registro...\n";
                                $query_uptime = "INSERT INTO Aden.OLT_ALARMA_ENERGIA
                                (equipo, ip, dif_hora, fecha_registro, fecha_alarma, fecha_recovery, puerto, tipo_alarma, estado, id_alarma, alarm_name)
                                VALUE('$server','$ip','',NOW(),'','$fechaCompleta2','$slot2','$tipo_alarma',2,$id_alarma,'The communication between the board and the control board fails')";
                                $mysqli->query($query_uptime) or die("Error query fan");
                            }
                        }   
                        
                    } 
                }
            } 
            //die;
        }
    }   

//--- calcular diferencia
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
echo date('Y-m-d H:i:s');
echo "--Fin Proceso--";

mysqli_close($mysqli);
die();
function estado_equipo($server)
{
    $cantConfig = 0;
    $i=0;
    $server = $server;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 60);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
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
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                fwrite($stream, $pass . "\n");
                break;
            case SALIR:
                fwrite($stream, "y\n");
                $uname .= $match[0];
                sleep(2);
                $x = false;
                return $uname;
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
                if($cantConfig==0){
                    fwrite($stream, "config\n");
                    sleep(1);
                }else{
                    fwrite($stream, "quit\n");
                    sleep(2);
                }
                $cantConfig++;
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    sleep(1);
                    fwrite($stream, "display alarm history alarmlevel minor detail\n");
                }
                if ($i==1) {
                    sleep(3);
                    fwrite($stream, "display alarm history alarmlevel cleared detail\n");
                }
                if($i>=2){
                    sleep(1);
                    fwrite($stream, "quit\n");
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

function estado_equipo2($server)
{
    $cantConfig = 0;
    $i=0;
    $server = $server;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 60);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
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
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                fwrite($stream, $pass . "\n");
                break;
            case SALIR:
                fwrite($stream, "y\n");
                $uname .= $match[0];
                sleep(2);
                $x = false;
                return $uname;
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
                if($cantConfig==0){
                    fwrite($stream, "config\n");
                    sleep(1);
                }else{
                    fwrite($stream, "quit\n");
                    sleep(2);
                }
                $cantConfig++;
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    sleep(1);
                    fwrite($stream, "display alarm active alarmlevel major detail\n");
                }
                if ($i==1) {
                    sleep(3);
                    fwrite($stream, "display alarm history alarmlevel major detail\n");
                }
                if($i>=2){
                    sleep(1);
                    fwrite($stream, "quit\n");
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