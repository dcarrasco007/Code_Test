<?php
//include ('/var/www/html/OLT/crontab127/conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
echo "Inicio:".date('Y-m-d H:i:s')."\n";
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
    //-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    $lote=$argv[5];
    $proceso_id=9;//ID DEL PROCESO YA REGISTRADO
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

    
    $ip = $argv[1];
    $server = $argv[2];
    $region = $argv[3];
    $modelo = $argv[4];

    
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){
            
            switch($modelo){
                
                case 'MA5603T':
                                $puerto1 = '0/6';
                                $puerto2 = '0/7';
                                $texto = estado_equipo($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                                    $data[] = $linea;
                                }
                                parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2);
                                unset($data); 
                                break;
                
                case 'MA5600T':
                                if($server == 'OLT-LAFLORIDA-1'){
                                    $puerto1 = '0/9';
                                    $puerto2 = '0/10';
                                }else{
                                    $puerto1 = '0/7';
                                    $puerto2 = '0/8';
                                }
                                $texto = estado_equipo($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                                    $data[] = $linea;
                                }
                                parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2);
                                unset($data);
                                break;
                
                case 'MA5680T':
                                $puerto1 = '0/7';
                                $puerto2 = '0/8';
                                $texto = estado_equipo($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                                    $data[] = $linea;
                                }
                                parseo($data,$server,$ip,$region,$modelo,$puerto1,$puerto2);
                                unset($data);
                                break;
                
                case 'MA5800-X15':
                                $puerto1 = '0/8';
                                $puerto2 = '0/9';
                                $texto = estado_equipo2($ip,$puerto1,$puerto2);
                                foreach (explode(chr(13), $texto) as $linea)
                                {
                                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
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

function estado_equipo($server,$puerto1,$puerto2)
{
    $i=0;
    $j=0;
    $b=0;
    $ser = $server;
    $p1 = $puerto1;
    $p2 = $puerto2;  
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 30);
    ini_set('memory_limit', '256M');
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
                    sleep(2);
                    fwrite($stream, "enable\n");
                    sleep(2);
                    $b++;
                }elseif($b == 1){
                    fwrite($stream, "\n");
                }
                break;
            case SHELL2:
                sleep(2);
                fwrite($stream, "config\n");
                sleep(2);
                break;
            case SHELL_CONFIG:
                sleep(2);
                if ($i==0) {
                    fwrite($stream, "display temperature $p1\n");
                }
                if ($i==1) {
                    fwrite($stream, "display temperature $p2\n");
                }
                if ($i==2) {
                    fwrite($stream, "display cpu $p1\n");
                }
                if ($i==3) {
                    fwrite($stream, "display cpu $p2\n");
                }
                if ($i==4) {
                    fwrite($stream, "quit\n");  
                    fclose($stream);
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

function estado_equipo2($server,$puerto1,$puerto2)
{
    $i=0;
    $ser = $server;
    $p1 = $puerto1;
    $p2 = $puerto2;  
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 30);
    ini_set('memory_limit', '256M');
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
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{.*.}:", ESPACIO2,EXP_REGEXP),
            ), $match))
        {
            case PASSWORD:
                fwrite($stream, $pass . "\n");
                break;
            case USER:
                fwrite($stream, $user . "\n");
                break;
            case SHELL:
                fwrite($stream, "enable\n");
                sleep(2);
                break;
            case SHELL2:
                sleep(2);
                fwrite($stream, "config\n");
                sleep(2);
                break;
            case SHELL_CONFIG:
                sleep(2);
                if ($i==0) {
                   
                            fwrite($stream, "display temperature $p1\n\n");
                        
                }
                if ($i==1) {
                   
                            fwrite($stream, "display temperature $p2\n\n");
                     
                }
                if ($i==2) {
                   
                            fwrite($stream, "display cpu $p1\n\n");
                       
                    
                }
                if ($i==3) {
                  
                            fwrite($stream, "display cpu $p2\n\n");
                       
                    
                }
                if ($i==4) {
                    fwrite($stream, "quit\n");  
                    fclose($stream);
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
                fwrite($stream, " ");
                $uname .= $match[0];
                break;
            case ESPACIO2:
                fwrite($stream, "\n");
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
    $grep = preg_grep("/.The temperature./", $data);
    $grep = array_values($grep);
    $grep1 = preg_grep("/.CPU./", $data);
    $grep1 = array_values($grep1);
    
    //--------- PUERTA 1 --------------------------
    $temp1 = trim($grep[0]);
    $temp1 = explode('(',$temp1);
    $tempe1 = $temp1[0];
    $tempe1 = explode(':',$tempe1);
    $tempe1 = $tempe1[1];
    
    $cpu1 = explode(':',$grep1[0]);
    $cpu1 = trim($cpu1[1]);


    //--------- PUERTA 2 --------------------------
    $temp2 = trim($grep[1]);
    $temp2 = explode('(',$temp2);
    $tempe2 = $temp2[0];
    $tempe2 = explode(':',$tempe2);
    $tempe2 = $tempe2[1];
    
    $cpu2 = explode(':',$grep1[1]);
    $cpu2 = trim($cpu2[1]);


    $sql = "INSERT INTO OLT_TEMP_CPU (equipo,region,ip,puerta,temperatura,cpu,fecha) 
            VALUES ('$server','$region','$ip','$puerto1','$tempe1','$cpu1','$ahora')";
    $resultado = $mysqli->query($sql) or die("error 1");
    
    $sql1 = "INSERT INTO OLT_TEMP_CPU (equipo,region,ip,puerta,temperatura,cpu,fecha) 
            VALUES ('$server','$region','$ip','$puerto2','$tempe2','$cpu2','$ahora')";
    $resultado1 = $mysqli->query($sql1) or die("error 1");
    
}

?>