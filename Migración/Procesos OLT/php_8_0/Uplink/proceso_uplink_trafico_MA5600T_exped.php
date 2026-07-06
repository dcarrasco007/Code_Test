<?php
include ('/u01/crontab127/conexion/conexion_db.php');
echo "Inicio: ".date('d-m-Y H:i:s')."\n";
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$fecha_monitor=date('Y-m-d H:i:s');
$lote=$argv[3];
$proceso_id=4;//ID DEL PROCESO YA REGISTRADO
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
sleep(3);
//--------------------
$ERROR=0;
$ERROR2=0;
$semana=date('W');
$hora=date('Y-m-d H:i:s');
$hora = strtotime ( '+4 hour' , strtotime ($hora));
$hora = strtotime ( '+20 minute' , $hora);
$hora = date ( 'Y-m-d H:i:s' , $hora);

    $contador16=0;
    $contador17=0;
    $contador18=0;
    $contador19=0;
    $contador20=0;
    $contador07=0;
    $contador08=0;
    $puerto16=0;
    $puerto17=0;
    $puerto18=0;
    $puerto19=0;
    $puerto20=0;
    $puerto07=0;
    $puerto08=0;
    $ip = $argv[1];
    $server = $argv[2]; 
    //$ip = $row[1];
    $y = ping_ip($ip);
    //$server = $server;
    $fecha = date("Y-m-d H:i:s");
    //obtiene las puertas a consultar de la OLT
    $sql_port = "SELECT OLT_PUERTAS_UPLINKS_GB.olt, 
        OLT_PUERTAS_UPLINKS_GB.puerta 
        FROM 
        OLT_SERVER INNER JOIN OLT_PUERTAS_UPLINKS_GB ON OLT_SERVER.server = OLT_PUERTAS_UPLINKS_GB.olt
        WHERE 
        OLT_SERVER.modelo = 'MA5600T'
        AND OLT_SERVER.server='$server'";
    $result2 = $mysqli->query($sql_port) or die("error 1");
    
        //$ip = '10.99.30.6';
        //$server = 'OLT-SBOTICELLI-1';
        if(trim($y)){
            if($y < 100){        
                while ($row2 = $result2->fetch_array(MYSQLI_NUM)){
                    if($row2[1]!=null){
                        $puerta_completa =$row2[1];
                        echo "Puerta Completa: ".$puerta_completa."\n";
                        $partes = explode("/", $puerta_completa);
                        $parte1=$partes[0].'/'.$partes[1];
                        $parte2=$partes[2];
                        echo 'la parte 1:'.$parte1."\n";
                        if($parte1=='0/17') {
                            $puerto17++;
                        }elseif ($parte1=='0/18') {
                            $puerto18++;
                        }elseif($parte1=='0/19') {
                            $puerto19++;
                        }elseif ($parte1=='0/20') {
                            $puerto20++;
                        }elseif ($parte1=='0/7') {
                            $puerto07++;
                        }elseif ($parte1=='0/8') {
                            $puerto08++;
                        }elseif ($parte1=='0/16') {
                            $puerto16++;
                        }

                        if ($parte1=='0/16' && $parte2=='1' && $puerto16==1) {
                            $puerto16++;
                        }
                        if ($parte1=='0/17' && $parte2=='1' && $puerto17==1) {
                            $puerto17++;
                        }
                        if ($parte1=='0/18' && $parte2=='1' && $puerto18==1) {
                            $puerto18++;
                        }
                        if ($parte1=='0/7' && $parte2=='1' && $puerto07==1) {
                            $puerto07++;
                        }
                        if ($parte1=='0/8' && $parte2=='1' && $puerto08==1) {
                            $puerto08++;
                        }
                        //ecepciones para la florida
                        
                        
                    }    
                } 
                //$total=$puerto17+$puerto18;

                    //echo 'cantidad '.$puerto16.'|'.$puerto17.'|'.$puerto18.'|';
                    if($server=='OLT-LAFLORIDA-1'){
                        echo 'entra bien contador19: '.$puerto19.' contadopr20: '.$puerto20;
                        $texto =estado_equipo3($ip,$puerto19,$puerto20);
                        $valida= verifica_equipo($texto);
                    }elseif($server=='OLT-ALTOPENUELAS-1' || $server=='OLT-CNT-2' || $server=='OLT-VALPARAISO-1'|| $server=='OLT-VITACURA-1'){
                        $texto =estado_equipo4($ip,$puerto07,$puerto08);
                        $valida= verifica_equipo($texto);
                    }else{
                        $texto = estado_equipo($ip,$puerto17,$puerto18);
                        $valida= verifica_equipo($texto);
                    }
                    //echo 'El Valida tiene: '.$valida.' |||';
                    
                    if ($valida==2) {
                        if($server=='OLT-ALTOPENUELAS-1' || $server=='OLT-CNT-2' || $server=='OLT-VALPARAISO-1'){
                            $texto =estado_equipo4($ip,$puerto07,$puerto08);
                            $valida= verifica_equipo($texto);
                        }else{
                            $texto = estado_equipo($ip,$puerto17,$puerto18);
                            $ERROR++;
                            $valida= verifica_equipo($texto);
                            if ($valida==2) {
                                $texto = estado_equipo2($ip,$puerto17,$puerto18);
                                $ERROR2++;
                                $valida= verifica_equipo($texto);
                            }
                            if ($valida==2) {
                                $texto = estado_equipo($ip,$puerto17,$puerto18);
                                $valida= verifica_equipo($texto);
                                $ERROR2++;
                                //$valida3= verifica_equipo($texto);
                            }
                        }
                    }
                    if($valida==2){
                        $query_hora = "INSERT INTO OLT_TRAFICO_UPLINK_HORA
                                (server,ip,modelo,peak,fecha,week) VALUES ('$server','$ip','MA5600T2','0',NOW(),'$semana')";
                                    $mysqli->query($query_hora) or die("Error query tipo $query_hora");
                        $texto='';
                    }
                    foreach (explode(chr(13), $texto) as $linea){
                        $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                        $data[] = $linea;
                    }
                    
                    $trafico17=0;
                    $trafico18=0;
                    $trafico19=0;
                    $trafico20=0;
                    $trafico07=0;
                    $trafico08=0;
                    for($j=0;$j<count($data);$j++){
                        $line = preg_replace('/\s+/', '', $data[$j]);
                            if (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto17>0) {
                                $puertaIngreso='0/17/'.$contador17;
                                $puerto17--;
                                $contador17++;
                                $linea_dato = $line;
                                //echo '###################### la linea: '.$line.'###########';
                                $valor1 = strstr($linea_dato, '=');
                                $valor1= trim($valor1);
                                $valor2= explode("=", $valor1);
                                $valor3= $valor2[1];
                                //if($trafico17<$valor3){$trafico17= $valor3;}
                                $trafico17=$trafico17+$valor3;
                                //echo "################### trafico: ".$valor1;
                                //---subida
                                $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                                $valorUp= explode("=", $line2);
                                $valorUpFinal= $valorUp[1];
                                echo "Linea UP: ".$valorUpFinal."\n";
                                //----
                                $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
                                (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5600T','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            }elseif (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto18>0) {
                                $puertaIngreso='0/18/'.$contador18;
                                $puerto18--;
                                $contador18++;
                                $linea_dato = $line;
                                //echo '###################### la linea: '.$line.'###########';
                                $valor1 = strstr($linea_dato, '=');
                                $valor1= trim($valor1);
                                $valor2= explode("=", $valor1);
                                $valor3= $valor2[1];
                                //if($trafico18<$valor3){$trafico18= $valor3;}
                                $trafico18=$trafico18+$valor3;
                                //echo "################### trafico: ".$valor1;
                                //---subida
                                $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                                $valorUp= explode("=", $line2);
                                $valorUpFinal= $valorUp[1];
                                echo "Linea UP: ".$valorUpFinal."\n";
                            //----
                                $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
                                (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5600T','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            }elseif (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto19>0) {
                                $puertaIngreso='0/19/'.$contador19;
                                $puerto19--;
                                $contador19++;
                                $linea_dato = $line;
                                //echo '###################### la linea: '.$line.'###########';
                                $valor1 = strstr($linea_dato, '=');
                                $valor1= trim($valor1);
                                $valor2= explode("=", $valor1);
                                $valor3= $valor2[1];
                                //if($trafico19<$valor3){$trafico19= $valor3;}
                                $trafico19=$trafico19+$valor3;
                                //echo "################### trafico: ".$valor1;
                                //---subida
                                $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                                $valorUp= explode("=", $line2);
                                $valorUpFinal= $valorUp[1];
                                echo "Linea UP: ".$valorUpFinal."\n";
                            //----
                                $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
                                (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5600T','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            }elseif (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto20>0) {
                                $puertaIngreso='0/20/'.$contador20;
                                $puerto20--;
                                $contador20++;
                                $linea_dato = $line;
                                //echo '###################### la linea: '.$line.'###########';
                                $valor1 = strstr($linea_dato, '=');
                                $valor1= trim($valor1);
                                $valor2= explode("=", $valor1);
                                $valor3= $valor2[1];
                                //if($trafico20<$valor3){$trafico20= $valor3;}
                                $trafico20=$trafico20+$valor3;
                                //echo "################### trafico: ".$valor1;
                                //---subida
                                $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                                $valorUp= explode("=", $line2);
                                $valorUpFinal= $valorUp[1];
                                echo "Linea UP: ".$valorUpFinal."\n";
                            //----
                                $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
                                (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5600T','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            }elseif (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto07>0) {
                                $puertaIngreso='0/7/'.$contador07;
                                $puerto07--;
                                $contador07++;
                                $linea_dato = $line;
                                //echo '###################### la linea: '.$line.'###########';
                                $valor1 = strstr($linea_dato, '=');
                                $valor1= trim($valor1);
                                $valor2= explode("=", $valor1);
                                $valor3= $valor2[1];
                                //if($trafico07<$valor3){$trafico07= $valor3;}
                                $trafico07=$trafico07+$valor3;
                                //echo "################### trafico: ".$valor1;
                                //---subida
                                $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                                $valorUp= explode("=", $line2);
                                $valorUpFinal= $valorUp[1];
                                echo "Linea UP: ".$valorUpFinal."\n";
                                //----
                                $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
                                (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5600T','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            }elseif (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto08>0) {
                                $puertaIngreso='0/8/'.$contador08;
                                $puerto08--;
                                $contador08++;
                                $linea_dato = $line;
                                //echo '###################### la linea: '.$line.'###########';
                                $valor1 = strstr($linea_dato, '=');
                                $valor1= trim($valor1);
                                $valor2= explode("=", $valor1);
                                $valor3= $valor2[1];
                                //if($trafico08<$valor3){$trafico08= $valor3;}
                                $trafico08=$trafico08+$valor3;
                                //echo "################### trafico: ".$valor1;
                                //---subida
                                $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                                $valorUp= explode("=", $line2);
                                $valorUpFinal= $valorUp[1];
                                echo "Linea UP: ".$valorUpFinal."\n";
                                //----
                                $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5600T
                                (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5600T','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            }
                            
                    }$peak=$trafico17+$trafico18+$trafico19+$trafico20+$trafico07+$trafico08;
                    
                    $query_hora = "INSERT INTO OLT_TRAFICO_UPLINK_HORA
                                (server,ip,modelo,peak,fecha,week) VALUES ('$server','$ip','MA5600T','$peak',NOW(),'$semana')";
                                    $mysqli->query($query_hora) or die("Error query tipo $query_hora");
            }unset($data);
        }

//sleep(15);
echo 'Entro al ciclo de errores: '.$ERROR.' veces. Y: '.$ERROR2.' EN EL SEGUNDO ';
/* $qry_final="SELECT
OLT_SERVER.server,
OLT_SERVER.ip,
OLT_SERVER.modelo
FROM
OLT_SERVER
WHERE
OLT_SERVER.server NOT IN(SELECT server FROM OLT_TRAFICO_UPLINK_HORA WHERE OLT_TRAFICO_UPLINK_HORA.fecha = NOW())
AND 
(OLT_SERVER.modelo ='MA5600T')";
$result_final = $mysqli->query($qry_final) or die("error 1");
$cuentas=0;
while ($row4 = $result_final->fetch_array(MYSQLI_NUM)) {
    $cuentas++;
    $query_hora1 = "INSERT INTO OLT_TRAFICO_UPLINK_HORA
                            (server,ip,modelo,peak,fecha,week) VALUES ('$row4[0]','$row4[1]','MA5600T','',NOW(),'$semana')";
                                $mysqli->query($query_hora1) or die("Error query tipo $query_hora1");
} 
echo ' Entro en vacios:'.$cuentas; */
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
die;
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
function estado_equipo($ip,$puerto17,$puerto18){
    $t=0;
    $i=0;
    $cantConfig = 0;
    $server = $ip;
    $puerto17=$puerto17;
    $puerto18=$puerto18;
    $total=$puerto17+$puerto18;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 2);
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
            array(".*config.*.#",SHELL_CONFIG1,EXP_REGEXP),
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                fwrite($stream, $pass . "\n");
                break;
            case SALIR:
                    fwrite($stream, "y\n");
                    //echo"entro en salir";
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
                    echo "quit 2";
                }
                
                $cantConfig++;
                break;
            case SHELL_CONFIG1:
                    
                        if($puerto17>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                fwrite($stream, "interface giu 0/17\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto17=0;
                            break;
                        }if($puerto18>0){
                            for ($x=0; $x<$puerto18; $x++) { 
                                fwrite($stream, "interface giu 0/18\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto18=0;
                            break;
                        }
                        if($puerto18==0 && $puerto17==0){
                            sleep(1);
                            fwrite($stream, "quit\n");
                            sleep(1);
                            //echo "quit 1";
                        }
                        break;   
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                return $uname;
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

function estado_equipo4($ip,$puerto07,$puerto08){
    $t=0;
    $i=0;
    $cantConfig = 0;
    $server = $ip;
    $puerto07=$puerto07;
    $puerto08=$puerto08;
    $total=$puerto08+$puerto07;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 2);
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
            array(".*config.*.#",SHELL_CONFIG1,EXP_REGEXP),
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                fwrite($stream, $pass . "\n");
                break;
            case SALIR:
                    fwrite($stream, "y\n");
                    //echo"entro en salir";
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
            case SHELL_CONFIG1:
                    
                        if($puerto07>0){
                            for ($x=0; $x<$puerto07; $x++) { 
                                fwrite($stream, "interface scu 0/7\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto07=0;
                            break;
                        }if($puerto08>0){
                            for ($x=0; $x<$puerto08; $x++) { 
                                fwrite($stream, "interface scu 0/8\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto08=0;
                            break;
                        }
                        if($puerto08==0 && $puerto07==0){
                            sleep(1);
                            fwrite($stream, "quit\n");
                            sleep(1);
                            //echo "quit 1";
                        }
                        break;   
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                return $uname;
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
function estado_equipo3($ip,$puerto19,$puerto20){
    $t=0;
    $i=0;
    $cantConfig = 0;
    $server = $ip;
    $puerto19=$puerto19;
    $puerto20=$puerto20;
    $total=$puerto20+$puerto19;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 2);
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
            array(".*config.*.#",SHELL_CONFIG1,EXP_REGEXP),
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                fwrite($stream, $pass . "\n");
                break;
            case SALIR:
                    fwrite($stream, "y\n");
                    //echo"entro en salir";
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
            case SHELL_CONFIG1:
                    
                        if($puerto19>0){
                            for ($x=0; $x<$puerto19; $x++) { 
                                fwrite($stream, "interface giu 0/19\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto19=0;
                            break;
                        }if($puerto20>0){
                            for ($x=0; $x<$puerto20; $x++) { 
                                fwrite($stream, "interface giu 0/20\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            $puerto20=0;
                            break;
                        }
                        if($puerto20==0 && $puerto19==0){
                            sleep(1);
                            fwrite($stream, "quit\n");
                            sleep(1);
                        }
                        break;   
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                return $uname;
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
function estado_equipo2($ip,$puerto17,$puerto18){
    $t=0;
    $i=0;
    $cantConfig = 0;
    $server = $ip;
    $puerto17=$puerto17;
    $puerto18=$puerto18;
    $total=$puerto17+$puerto18;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 2);
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
            array(".*config.*.#",SHELL_CONFIG1,EXP_REGEXP),
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array("Are you sure to log out? (y/n)[n]:",SALIR,EXP_EXACT),
            array(".*Are you sure to log out?.*:",SALIR,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>||<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                fwrite($stream, $pass . "\n");
                break;
            case SALIR:
                    fwrite($stream, "y\n");
                    //echo"entro en salir";
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
            case SHELL_CONFIG1:
                    
                        if($puerto17>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                fwrite($stream, "\n");
                                fwrite($stream, "interface giu 0/17\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto17=0;
                            break;
                        }if($puerto18>0){
                            for ($x=0; $x<$puerto18; $x++) { 
                                fwrite($stream, "interface giu 0/18\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto18=0;
                            break;
                        }
                        if($puerto18==0 && $puerto17==0){
                            sleep(1);
                            fwrite($stream, "quit\n");
                            sleep(1);
                        }
                        break;   
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                return $uname;
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