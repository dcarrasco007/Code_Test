<?php
include ('/var/www/html/OLT/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

    $server=$argv[1];
    
    $ip=$argv[2];
    //$server='OLT-HUECHURABA-2';
    //echo $ip='10.99.25.106';
$fecha=date("Y-m-d");
//obtener puertas pon
$sql_ip = "SELECT tarjetas,equipo FROM OLT_VOLTAJE_TARJETA WHERE ip = '$ip'";
$result = $mysqli->query($sql_ip) or die("error 2");
$row = $result->fetch_array(MYSQLI_NUM);

echo "Equipo: ".$server."\n";
echo date('Y-m-d H:i:s')."\n";
$puertas=explode("<br>",$row[0]);
print_r($puertas);
for ($i=3; $i < count($puertas); $i++) { 
    $div=explode(" ",$puertas[$i]);
  if($div[2]!=16){
    if($div[4]!='' && $div[6]=='Normal'){
        $cantidadPuerta[]=$div[2];
    
    }
  }else{
    break;
  }  
}
//fin obtencion puertas pon
$aux=0;
print_r($cantidadPuerta);

    $texto = estado_equipo($ip,$cantidadPuerta);
    foreach (explode(chr(13), $texto) as $linea){
        $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
        $data[] = $linea;
    }
    print_r($data);

    for($i=0; $i <count($cantidadPuerta); $i++){
        $palabra="interface gpon 0/";
        $patron=$palabra.$i;
        $grep2 = preg_grep("/interface gpon 0\/$cantidadPuerta[$i]/", $data);
        $key = key($grep2); 
        $llaves[]=$key;  
    }
    print_r($llaves);
    
    $upstream_values = array();
    $downstream_values = array();
    for ($i=0; $i <=count($llaves)-1 ; $i++) { 
        if($i==(count($llaves)-1)){
            echo"Entro IF 1:";
            for($l=$llaves[$i]; $l <count($data); $l++) { 
                $segmentoData[]=$data[$l];
             }
        }else{
            echo"Entro Else:";
            for($l=$llaves[$i]; $l <$llaves[$i+1]; $l++) { 
                $segmentoData[]=$data[$l];
             }
        }
        echo"SegmentoData:";print_r($segmentoData);
        $port = null;
        foreach ($segmentoData as $line) {
            if (preg_match('/display port traffic (\d+)/', $line, $matches)) {
                $port = $matches[1];
            } elseif ($port !== null && preg_match('/Up traffic \(kbps\) *: *(\d+)/', $line, $matches)) {
                $ups = $matches[1];
            } elseif ($port !== null && preg_match('/Down traffic \(kbps\) *: *(\d+)/', $line, $matches)) {
                $ups=$ups/1024;
                $ups=number_format($ups,2);
                $downs= $matches[1];
                $downs=$downs/1024;
                $downs=number_format($downs,2);
                
                $puertoGuardado="0/".$cantidadPuerta[$aux]."/".$port;
                $sql_trafico = "INSERT INTO OLT_TRAFICOGPON_HORA (ip_equipo,port,up_mbps,down_mbps,fecha) 
                VALUES ('$ip','$puertoGuardado','$ups','$downs','$fecha')";
                mysqli_query($mysqli,$sql_trafico);
            }           
        }
        $aux++;
        // Imprimir los valores obtenidos
        /* foreach ($upstream_values as $port => $value) {
           $traficoMb=$value/1024;
           $traficoMb=number_format($traficoMb,2);
            echo "Upstream unicast frames for port $port: $traficoMb\n";
            $sql_trafico = "INSERT INTO OLT_TRAFICOGPON2 (ip_equipo,port,up_mbps,down_mbps,fecha) 
                            VALUES ('$ip','$puerto','$up','$down','$fecha')";
            mysqli_query($conn,$sql_trafico);
            
            $sql_trafico1 = "INSERT INTO OLT_TRAFICOGPON (ip_equipo,port,up_mbps,down_mbps,fecha) 
                            VALUES ('$ip','$puerto','$up','$down','$fecha')";
            mysqli_query($conn,$sql_trafico1);
        }

        foreach ($downstream_values as $port => $value) {
            $traficoMb=$value/1024;
            $traficoMb=number_format($traficoMb,2);
            echo "Downstream unicast frames for port $port: $traficoMb\n";
            $sql_trafico = "INSERT INTO OLT_TRAFICOGPON2 (ip_equipo,port,up_mbps,down_mbps,fecha) 
                            VALUES ('$ip','$puerto','$up','$down','$fecha')";
            mysqli_query($conn,$sql_trafico);
            
            $sql_trafico1 = "INSERT INTO OLT_TRAFICOGPON (ip_equipo,port,up_mbps,down_mbps,fecha) 
                            VALUES ('$ip','$puerto','$up','$down','$fecha')";
            mysqli_query($conn,$sql_trafico1);
        } */
        unset($segmentoData);
    }   
    mysqli_close($mysqli); 
    die("Fin Proceso");


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
function estado_equipo($ip,$puertas){
    $t=0;

    $server = $ip;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    ini_set("expect.timeout", 120);
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
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array(".*config.*.#",SHELL_CONFIG1,EXP_REGEXP),
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
                    sleep(2);
                    fwrite($stream, "\n");
                    sleep(2);
                }
                break;
            case SHELL2:
                fwrite($stream, "config\n");
                break;
            case SHELL_CONFIG1:
                if($t<count($puertas)){
                    //echo "entro puerta: ".$puertas[$t];
                        sleep(2);
                        fwrite($stream, "interface gpon 0/$puertas[$t]\n");
                        sleep(2);
                        for ($i=0; $i < 16; $i++) { 
                            echo "puerta: 0/".$puertas[$t]."/".$i."\n";
                            sleep(1);
                            fwrite($stream, "display port traffic $i\n");
                            sleep(1);
                            fwrite($stream, "\n");
                            
                        }
                        
                        
                        fwrite($stream, "quit\n");
                        $t++;
                    
                    break;
                 }else{
                    
                    $uname .= $match[0];
                    sleep(1);
                    echo " entro final! ";
                    fwrite($stream, "quit\n");
                    sleep(1);
                    fwrite($stream, "quit\n");
                    sleep(1);
                    $x=false;
                    return $uname;
                 }
                         /* if($t==0){
                            $t++;
                           for ($p=0; $p <count($puertas) ; $p++) { 
                            echo "entro puerta: ".$puertas[$p];
                                sleep(1);
                                fwrite($stream, "interface gpon 0/$puertas[$p]\n");
                                sleep(1);
                                for ($i=0; $i < 16; $i++) { 
                                    echo "puerta: 0/".$puertas[$p]."/".$i."\n";
                                    sleep(1);
                                    fwrite($stream, "display port traffic $i\n");
                                    sleep(1);
                                    fwrite($stream, "\n");
                                    
                                }
                                
                                
                                fwrite($stream, "quit\n");
                                
                            }
                            break;
                         }else{
                            
                            $uname .= $match[0];
                            echo " entro final! ";
                            fwrite($stream, "quit\n");
                            sleep(1);
                            fwrite($stream, "quit\n");
                            sleep(1);
                            return $uname;
                         } */
                         
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                $x=false;
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