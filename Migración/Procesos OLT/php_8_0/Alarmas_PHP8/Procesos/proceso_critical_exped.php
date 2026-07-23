<?php
date_default_timezone_set('America/Santiago');
// --- Migración a PHP 8.0: reemplazo de la extensión PECL 'expect' (no soportada en PHP 8) ---
require_once __DIR__ . '/../expect_compat.php';
// Constantes bareword usadas en los switch de expect_expectl:
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','ACEPTAR','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
// -------------------------------------------------------------------------
//include ('/var/www/html/conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
$fecha = date("Y-m-d H:i:s");
$anio = date("Y");
$hora=date('Y-m-d H:i:s');
 //-----------MONITOREO
    $fecha_monitor=date('Y-m-d H:i:s');
    $lote=$argv[5];
    $proceso_id=11;//ID DEL PROCESO YA REGISTRADO
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
        /* $modelo='MA5600T';
        $tipo='1';
        $server='OLT-CALLEJONDELOSPERROS2PCS-1';
        $ip='10.99.26.30'; */
        
        if ($tipo==1) {
            $texto=estado_equipo($ip,$modelo);
        }else{
            $texto=estado_equipo2($ip,$modelo);
        }
        foreach (explode(chr(13), $texto) as $linea)
        {
            $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea);
            $data[] = $linea;
        }
        print_r($data);
        $p2=0;
        for($j=0;$j<count($data);$j++){
            $line = preg_replace('/\s+/', ' ', $data[$j]);
            if(stristr($line,'display alarm history alarmlevel cleared alarmclass recovery detail') || stristr($line,'
            
            ')){

                $p2=$j;
                break;
            }    
            if (stristr($line,'ALARM NAME')){                                 
                $alarma = explode(':',$line);
                $alarma = trim($alarma[1]);
                
                if(stristr($data[$j+2],'PARAMETERS')){
                    $pto = explode(':',$data[$j+2]);
                    $port = explode(',',$pto[5]);
                    $puerto = $pto[1].$pto[2].$pto[3].$pto[4].$port[0];
                }elseif(stristr($data[$j+3],'PARAMETERS')){
                    $pto = explode(':',$data[$j+3]);
                    $port = explode(',',$pto[5]);
                    $puerto = $pto[1].$pto[2].$pto[3].$pto[4].$port[0];
                }
                
                $line2 = preg_replace('/\s+/', ' ', $data[$j-1]);
                $line2a = preg_replace('/\s+/', ' ', $data[$j-2]);
                if(stristr($line2,'EQUIPMENT')){
                    $id = explode(' ',$line2);
                    //print_r($id);
                    if($id[2]=='('){
                        $id = trim($id[11]);
                    }else{
                        $id = trim($id[2]);
                    }
                    if(stristr($data[$j-1],'EQUIPMENT')){
                    $fecha = explode('EQUIPMENT',$data[$j-1]);
                    $fecha = trim($fecha[1]);
                    
                    $linea_alarma = trim($data[$j-1]);
                    
                    if(stristr($linea_alarma,'CRITICAL')){
                        if(stristr($fecha,$anio)){
                            $queryHistorico="INSERT INTO OLT_ALARMA_CRITICAL_LOS_HISTORICO
                            (server, ip, ubicacion, alarma, fecha, fecha_proceso, estado, id_alarma)
                            VALUES('$server','$ip','$puerto','$alarma','$fecha',NOW(),'ACTIVA','$id')";
                            $result11=$mysqli->query($queryHistorico) or die("Error $queryHistorico");
                            $queryval="SELECT COUNT(OLT_ALARMA_CRITICAL_LOS.id) AS cantidad   FROM OLT_ALARMA_CRITICAL_LOS 
                            WHERE OLT_ALARMA_CRITICAL_LOS.id_alarma ='$id' AND OLT_ALARMA_CRITICAL_LOS.server='$server'";
                            $result1=$mysqli->query($queryval) or die("Error $queryval");
                            $row1 = $result1->fetch_array(MYSQLI_NUM);
                            echo'trae esto final1: '.$row1[0].' |';
                            if($row1[0]==0){
                                $query_alarma = "INSERT INTO OLT_ALARMA_CRITICAL_LOS
                                                (server,ip,ubicacion,alarma,fecha,fecha_proceso, estado, id_alarma) VALUES 
                                                ('$server','$ip','$puerto','$alarma','$fecha',NOW(),'INICIO','$id')";
                                $mysqli->query($query_alarma) or die("Error $query_alarma");
                            }                              
                        }

                    }
                    
                }
                }elseif(stristr($line2a,'EQUIPMENT')){
                    $id = explode(' ',$line2a);
                    //print_r($id);
                    if($id[2]=='('){
                        $id = trim($id[11]);
                    }else{
                        $id = trim($id[2]);
                    }
                    if(stristr($data[$j-2],'EQUIPMENT')){
                    $fecha = explode('EQUIPMENT',$data[$j-2]);
                    $fecha = trim($fecha[1]);
                    
                    $linea_alarma = trim($data[$j-2]);
                    
                    if(stristr($linea_alarma,'CRITICAL')){
                        if(stristr($fecha,$anio)){
                            $queryHistorico="INSERT INTO OLT_ALARMA_CRITICAL_LOS_HISTORICO
                            (server, ip, ubicacion, alarma, fecha, fecha_proceso, estado, id_alarma)
                            VALUES('$server','$ip','$puerto','$alarma','$fecha',NOW(),'ACTIVA','$id')";
                            $result11=$mysqli->query($queryHistorico) or die("Error $queryHistorico");
                            $queryval="SELECT COUNT(OLT_ALARMA_CRITICAL_LOS.id) AS cantidad   FROM OLT_ALARMA_CRITICAL_LOS 
                            WHERE OLT_ALARMA_CRITICAL_LOS.id_alarma ='$id' AND OLT_ALARMA_CRITICAL_LOS.server='$server'";
                            $result1=$mysqli->query($queryval) or die("Error $queryval");
                            $row1 = $result1->fetch_array(MYSQLI_NUM);
                            echo'trae esto final1: '.$row1[0].' |';
                            if($row1[0]==0){
                                $query_alarma = "INSERT INTO OLT_ALARMA_CRITICAL_LOS
                                                (server,ip,ubicacion,alarma,fecha,fecha_proceso, estado, id_alarma) VALUES 
                                                ('$server','$ip','$puerto','$alarma','$fecha',NOW(),'INICIO','$id')";
                                $mysqli->query($query_alarma) or die("Error $query_alarma");
                            }                              
                        }

                    }
                    
                }
                }
                
                
            }
        }
        //parte 2 log final
        for($j=$p2;$j<count($data);$j++){
            $line = preg_replace('/\s+/', ' ', $data[$j]);
                
            if (stristr($line,'ALARM NAME')){                                 
                $alarma = explode(':',$line);
                $alarma = trim($alarma[1]);
                $line2 = preg_replace('/\s+/', ' ', $data[$j-1]);
                if(stristr($line2,'EQUIPMENT')){
                    $id = explode(' ',$line2);
                    //print_r($id);
                    if($id[2]=='('){
                        $id = trim($id[11]);
                    }else{
                        $id = trim($id[2]);
                    }
                }
                
                if(stristr($data[$j+2],'PARAMETERS')){
                    $pto = explode(':',$data[$j+2]);
                    $port = explode(',',$pto[5]);
                    $puerto = $pto[1].$pto[2].$pto[3].$pto[4].$port[0];
                }elseif(stristr($data[$j+3],'PARAMETERS')){
                    $pto = explode(':',$data[$j+3]);
                    $port = explode(',',$pto[5]);
                    $puerto = $pto[1].$pto[2].$pto[3].$pto[4].$port[0];
                }
                                
                if(stristr($data[$j-1],'EQUIPMENT')){
                    $fecha = explode('EQUIPMENT',$data[$j-1]);
                    $fecha = trim($fecha[1]);
                    
                    $linea_alarma = trim($data[$j-1]);
                    
                    if(stristr($linea_alarma,'EQUIPMENT')){
                        if(stristr($fecha,$anio)){
                            $queryval2="SELECT OLT_ALARMA_CRITICAL_LOS.id ,OLT_ALARMA_CRITICAL_LOS.estado  FROM OLT_ALARMA_CRITICAL_LOS 
                            WHERE OLT_ALARMA_CRITICAL_LOS.id_alarma ='$id' AND OLT_ALARMA_CRITICAL_LOS.server='$server'";
                            $result=$mysqli->query($queryval2) or die("Error $queryval2");
                            $row2 = $result->fetch_array(MYSQLI_NUM);
                            //echo'trae esto final2: '.$row2[1].' |';
                            if($row2[1]=='INICIO'){
                                $query_alarma = "INSERT INTO OLT_ALARMA_CRITICAL_LOS
                                            (server,ip,ubicacion,alarma,fecha,fecha_proceso,estado,id_alarma) VALUES 
                                            ('$server','$ip','$puerto','$alarma','$fecha',NOW(),'FIN','$id')";
                            $mysqli->query($query_alarma) or die("Error $query_alarma");        
                            }
                                                  
                        }

                    }
                    
                }
            }
        } 
        unset($data); 
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

function estado_equipo($server,$modelo){
    $i=0;
    $cant2 = 0;
    $cantConfig = 0;
    $comandos2=
    $comandos2 = array("display alarm history alarmlevel cleared alarmclass recovery detail");
    $cant_com2 = count($comandos2);
    $server = $server;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout(30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
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
                sleep(1);
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
            if ($i==0) {
                expect_send($stream, "display alarm active alarmlevel critical\n");
                sleep(1);
            }
            if ($i==1) {
                if($modelo=='MA5800-X15'){
                    expect_send($stream, "display alarm history alarmlevel cleared alarmclass recovery detail\n");
                }else{
                    expect_send($stream, "display alarm history alarmclass recovery alarmlevel critical detail\n");
                }
                sleep(1);
            }
            if ($i>=2) {
                //echo"entro en quit";
                sleep(1);
                expect_send($stream, "quit\n");  
               /*  sleep(3);
                expect_send($stream, "quit\n"); 
                sleep(4); 
                expect_send($stream, "y\n");
                sleep(4);
                echo"fin en quit";
                expect_close($stream);
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
        case EXP_EOF:
            break;
        }
    }
}

function estado_equipo2($server,$modelo){
    $i=0;
    $server = $server;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout(30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada
    $uname = "";
    $x = true;
    while ($x){

        switch (expect_expectl($stream, array(
            array("User name:", USER),
            array("User password:",PASSWORD,EXP_EXACT),
            array(".*\n",SALTO,EXP_REGEXP),
            array("OLT-DURZUA-4>",SHELL,EXP_EXACT),
            array("OLT-DURZUA-4(config)#",SHELL_CONFIG,EXP_EXACT),
            array("OLT-DURZUA-4#",SHELL2,EXP_EXACT),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>|detail<K>|list<K> }:", ESPACIO3,EXP_EXACT),
            array("{ lock<K> }:", ESPACIO2,EXP_EXACT),
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
            }
            if($b == 1){
                expect_send($stream, "\n");
                $b++;
            }
            break;
        case SHELL2:
            expect_send($stream, "config\n");
            break;
        case SHELL_CONFIG:
            if ($i==0) {
                expect_send($stream, "display alarm active alarmlevel critical\n");
            }
            if ($i==1) {
                expect_send($stream, "display alarm active alarmlevel critical\n");
            }
            if ($i==2) {
                expect_send($stream, "display alarm history alarmclass recovery alarmlevel critical detail\n");   
            }
            if ($i==3) {
                expect_send($stream, "quit\n");  
                expect_close($stream);
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


?>