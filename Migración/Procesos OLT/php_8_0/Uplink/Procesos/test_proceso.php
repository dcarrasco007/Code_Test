<?php
include ('/var/www/html/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
$fecha = date("Y-m-d H:i:s");
$fecha2 = date("Y-m-d");
/* $hora=date('Y-m-d H:i:s');
$hora = strtotime ( '-3 hour' , strtotime ($hora));
$hora = strtotime ( '-30 minute' , $hora); */
//$hora = date ( 'Y-m-d');
    $contador16=0;
    $contador17=0;
    $contador18=0;
    $contador08=0;
    $contador09=0;
if(isset($argv[7])){
    echo "Puertos 16 17 18 \n";
    $server=$argv[1];
    $semana=$argv[2];
    $ip=$argv[3];
    $puerto16=$argv[4];
    $puerto17=$argv[5];
    $puerto18=$argv[6];
    $hora=$argv[7];
    $hora=$fecha2." ".$hora; 
    //$hora = date ( 'Y-m-d H:i:s' , $hora);
    //$hora=$hora.$hora2;
    $texto = estado_equipo($ip,$puerto16,$puerto17,$puerto18);
        $valida= verifica_equipo($texto);
                    //echo 'El Valida tiene: '.$valida.' |||';
        if ($valida==2) {
            $texto = estado_equipo($ip,$puerto16,$puerto17,$puerto18);
            $ERROR++;
            $valida2= verifica_equipo($texto);
            if ($valida2==2) {
            $texto = estado_equipo($ip,$puerto16,$puerto17,$puerto18);
            $ERROR2++;
            }
        }
}else{
    echo "Puertos 8 y 9 \n";
    $server='OLT-RECREO-3';
    $semana='47';
    $ip='10.99.9.150';
    $puerto08='2';
    $puerto09='2';
    $hora='16:30';
    $hora=$fecha2." ".$hora; 
    //$hora = date ( 'Y-m-d H:i:s' , $hora);
    $texto = estado_equipo3($ip,$puerto08,$puerto09);
    $valida= verifica_equipo($texto);
    if ($valida==2) {
        $texto = estado_equipo3($ip,$puerto08,$puerto09);
        $ERROR++;
        $valida2= verifica_equipo($texto);
        if ($valida2==2) {
            $texto = estado_equipo3($ip,$puerto08,$puerto09);
            $ERROR2++;
        }
    }
}
die("Fin---1");
   
                foreach (explode(chr(13), $texto) as $linea){
                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                    $data[] = $linea;
                }
                $trafico16=0;
                $trafico17=0;
                $trafico18=0;
                $trafico08=0;
                $trafico09=0;
                for($j=0;$j<count($data);$j++){
                    $line = preg_replace('/\s+/', '', $data[$j]);
                        if(stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto16>0){
                            
                            $puertaIngreso='0/16/'.$contador16;
                            $puerto16--;
                            $contador16++;
                            $linea_dato = $line;
                            //echo '###################### la linea: '.$line.'###########';
                            $valor1 = strstr($linea_dato, '=');
                            $valor1= trim($valor1);
                            $valor2= explode("=", $valor1);
                            $valor3= $valor2[1];
                            //echo "################### trafico: ".$valor1;
                            //if($trafico16<$valor3){$trafico16= $valor3;}
                            $trafico16=$trafico16+$valor3;
                            //---subida
                            $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                            $valorUp= explode("=", $line2);
                            $valorUpFinal= $valorUp[1];
                            echo "Linea UP: ".$valorUpFinal."\n";
                            //----
                            $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15
                            (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5800-X15','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                            //$mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            
                            //break;
                        }elseif (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto17>0) {
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
                            $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15
                            (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5800-X15','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                            //$mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                
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
                            $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15
                            (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5800-X15','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                            //$mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
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
                            $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15
                            (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5800-X15','$puertaIngreso','$valor3',NOW(),'$semana','$valorUpFinal')";
                            //$mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                            
                        }elseif (stristr($line,"Thereceivedtrafficofthisport(kbits/s)=") && $puerto09>0) {
                            $puertaIngreso='0/9/'.$contador09;
                            $puerto09--;
                            $contador09++;
                            $linea_dato = $line;
                            //echo '###################### la linea: '.$line.'###########';
                            $valor1 = strstr($linea_dato, '=');
                            $valor1= trim($valor1);
                            $valor2= explode("=", $valor1);
                            $valor3= $valor2[1];
                            //$valor3= str_replace(".",",", $valor3);
                            //if($trafico09<$valor3){$trafico09= $valor3;}
                            $trafico09=$trafico09+$valor3;
                            //echo "################### trafico: ".$valor1;
                            //---subida
                            $line2 = preg_replace('/\s+/', '', $data[$j+7]);
                            $valorUp= explode("=", $line2);
                            $valorUpFinal= $valorUp[1];
                            echo "Linea UP: ".$valorUpFinal."\n";
                            //----
                            $query_detalle = "INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15
                            (server,ip,modelo,puerto,trafico,fecha,week,trafico_up) VALUES ('$server','$ip','MA5800-X15','$puertaIngreso',$valor3,NOW(),'$semana','$valorUpFinal')";
                            //$mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                        }
                        
                }
                $peak=$trafico16+$trafico17+$trafico18+$trafico08+$trafico09;
                
                $query_hora = "INSERT INTO OLT_TRAFICO_UPLINK_HORA
                            (server,ip,modelo,peak,fecha,week) VALUES ('$server','$ip','MA5800-X15','$peak',NOW(),'$semana')";
                //$mysqli->query($query_hora) or die("Error query tipo $query_hora");
            unset($data);
        
echo 'Entro al ciclo de errores: '.$ERROR.' veces. Y: '.$ERROR2.' EN EL SEGUNDO ';


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
function estado_equipo($ip,$puerto16,$puerto17,$puerto18){
    $t=0;
    $i=0;
    $cantConfig = 0;
    $server = $ip;
    $puerto16=$puerto16;
    $puerto17=$puerto17;
    $puerto18=$puerto18;
    $total=$puerto16+$puerto17+$puerto18;
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
            array("Check whether system data has been changed. Please save data before logout. Are you sure to log out? (y/n)[n]",SALIR,EXP_EXACT),
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
                    sleep(1);
                    fwrite($stream, "y\n");
                    sleep(2);
                    $uname .= $match[0];
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
                    sleep(1);
                }
                
                $cantConfig++;
                break;
            case SHELL_CONFIG1:
                    
                        if($puerto16>0){        
                            for ($x=0; $x<$puerto16; $x++) { 
                                fwrite($stream, "interface eth 0/16\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto16=0;
                            break;
                        }if($puerto17>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                fwrite($stream, "interface eth 0/17\n");
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
                                fwrite($stream, "interface eth 0/18\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto18=0;
                            break;
                        }
                        if($puerto18==0 && $puerto17==0 && $puerto16==0){
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

function estado_equipo2($server,$puerto){
    $i=0;
    $cantConfig = 0;
    $server = $server;
    $puerto = $puerto;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 30);
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
            array("OLT-DURZUA-4>",SHELL,EXP_EXACT),
            array("OLT-DURZUA-4(config)#",SHELL_CONFIG,EXP_EXACT),
            array("OLT-DURZUA-4#",SHELL2,EXP_EXACT),
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
                }
                if($b == 1){
                    fwrite($stream, "\n");
                    $b++;
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
                    fwrite($stream, "display port vlan $puerto\n");
                }
                if ($i==1) {
                    fwrite($stream, "display port vlan $puerto\n");
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
function estado_equipo3($ip,$puerto08,$puerto09){
    $t=0;
    $i=0;
    $cantConfig = 0;
    $server = $ip;
    $puerto08=$puerto08;
    $puerto09=$puerto09;
    $total=$puerto08+$puerto09;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 10);
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
            array(".*config.*.#",SHELL_CONFIG1,EXP_REGEXP),
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
                    echo "entro shell";
                        if($puerto08>0){        
                            for ($x=0; $x<$puerto08; $x++) { 
                                fwrite($stream, "interface mpu 0/8\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto08=0;
                            break;
                        }if($puerto09>0){
                            for ($x=0; $x<$puerto09; $x++) { 
                                fwrite($stream, "interface mpu 0/9\n");
                                fwrite($stream, "display port traffic $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto09=0;
                            break;
                        }
                        if($puerto09==0 && $puerto08==0){
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