<?php
//include ('/var/www/html/conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$sql_ip = "SELECT
        OLT_SERVER.`server`,
        OLT_SERVER.ip,
        OLT_SERVER.modelo,
        OLT_SERVER.region
        FROM 
        OLT_SERVER";
$result = $mysqli->query($sql_ip) or die("error 1");
$ERROR=0;
$ERROR2=0;
$semana=date('W');
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $contador16=0;
    $contador17=0;
    $contador18=0;
    $contador19=0;
    $contador20=0;
    $contador07=0;
    $contador08=0;
    $contador09=0;
    $puerto16=0;
    $puerto17=0;
    $puerto18=0;
    $puerto19=0;
    $puerto20=0;
    $puerto07=0;
    $puerto08=0;
    $puerto09=0;
    $ip = $row[1];
    $y = ping_ip($ip);
    $server = $row[0];
    $fecha = date("Y-m-d H:i:s");
    $sql_port = "SELECT OLT_PUERTAS_UPLINKS_GB.olt, 
        OLT_PUERTAS_UPLINKS_GB.puerta 
        FROM 
        OLT_SERVER INNER JOIN OLT_PUERTAS_UPLINKS_GB ON OLT_SERVER.server = OLT_PUERTAS_UPLINKS_GB.olt
        WHERE 
        OLT_SERVER.server='$row[0]'";
    $result2 = $mysqli->query($sql_port) or die("error 1");
    
        //$ip = '10.99.30.6';
        //$server = 'OLT-SBOTICELLI-1';
        if(trim($y)){
            if($y < 100){       
            while ($row2 = $result2->fetch_array(MYSQLI_NUM)){
                if($row2[1]!=null){
                    $puerta_completa =$row2[1];
                    $partes = explode("/", $puerta_completa);
                    $parte1=$partes[0].'/'.$partes[1];
                    $parte2=$partes[2];
                    echo 'la parte 1:'.$parte1.'||';
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
                    }elseif ($parte1=='0/9') {
                        $puerto09++;
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
                    if ($parte1=='0/9' && $parte2=='1' && $puerto09==1) {
                        $puerto09++;
                    }

                    if ($parte1=='0/16' && $parte2=='2' && $puerto16==1) {
                        $puerto16= $puerto16+2;
                    }
                    if ($parte1=='0/17' && $parte2=='2' && $puerto17==1) {
                        $puerto17=$puerto17+2;
                    }
                    if ($parte1=='0/18' && $parte2=='2' && $puerto18==1) {
                        $puerto18=$puerto18+2;
                    }
                    if ($parte1=='0/7' && $parte2=='2' && $puerto07==1) {
                        $puerto07=$puerto07+2;
                    }
                    if ($parte1=='0/8' && $parte2=='2' && $puerto08==1) {
                        $puerto08=$puerto08+2;
                    }
                    if ($parte1=='0/9' && $parte2=='2' && $puerto09==1) {
                        $puerto09=$puerto09+2;
                    }
                    //ecepciones para la florida
                    
                    
                }   
            } 
            if($row[2]=='MA5600T'){
                //echo' Entra 1';
                $texto =estado_equipo_MA5600T($ip,$puerto07,$puerto08,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20);
                $valida= verifica_equipo($texto);
                if($valida==2){
                    $texto =estado_equipo_MA5600T($ip,$puerto07,$puerto08,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20);
                    $valida2= verifica_equipo($texto);
                    $ERROR++;
                    if($valida2==2){
                        $texto =estado_equipo_MA5600T($ip,$puerto07,$puerto08,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20);
                        $ERROR2++;
                    }
                }

            }
                if($row[2]=='MA5800-X15' && $row[0]!='OLT-CONCEPCION-3'){
                    //echo' Entra 2';
                    $texto =estado_equipo_MA5800_X15($ip,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20,$puerto08,$puerto09);
                    $valida= verifica_equipo($texto);
                    if($valida==2){
                        $texto =estado_equipo_MA5800_X15($ip,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20,$puerto08,$puerto09);
                        $valida2= verifica_equipo($texto);
                        $ERROR++;
                        if($valida2==2){
                            $texto =estado_equipo_MA5800_X15($ip,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20,$puerto08,$puerto09);
                            $ERROR2++;
                        }
                    }
                }
                if($row[2]=='MA5680T'){
                    //echo' Entra 3';
                    if($row[0]=='OLT-CONCEPCION-1'){
                        $texto=estado_equipo_MA5600T($ip,$puerto07,$puerto08,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20);
                        $valida= verifica_equipo($texto);
                        if($valida==2){
                            $texto=estado_equipo_MA5600T($ip,$puerto07,$puerto08,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20);
                            $valida2= verifica_equipo($texto);
                            $ERROR++;
                            if($valida2==2){
                                $texto=estado_equipo_MA5600T($ip,$puerto07,$puerto08,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20);
                                $ERROR2++;
                            }
                        }
                    }else{
                        $texto =estado_equipo_MA5680T($ip,$puerto17,$puerto18,$puerto19,$puerto20);
                        $valida= verifica_equipo($texto);
                        if($valida==2){
                            $texto =estado_equipo_MA5680T($ip,$puerto17,$puerto18,$puerto19,$puerto20);
                            $valida2= verifica_equipo($texto);
                            $ERROR++;
                            if($valida2==2){
                                $texto =estado_equipo_MA5680T($ip,$puerto17,$puerto18,$puerto19,$puerto20);
                                $ERROR2++;
                            }
                        }
                    }
                    
                }
                if($row[2]=='MA5603T'){
                    //echo' Entra 4';
                    $texto =estado_equipo_MA5603T($ip,$puerto08,$puerto09);
                    $valida= verifica_equipo($texto);
                    if($valida==2){
                        $texto =estado_equipo_MA5603T($ip,$puerto08,$puerto09);
                        $valida2= verifica_equipo($texto);
                        $ERROR++;
                        if($valida2==2){
                            $texto =estado_equipo_MA5603T($ip,$puerto08,$puerto09);
                            $ERROR2++;
                        }
                    }
                }
                if($row[2]=='MA5800-X15' && $row[0]=='OLT-CONCEPCION-3'){
                    //echo' Entra 5';
                    $texto =estado_equipo_CONCE($ip,$puerto08,$puerto09);
                    $valida= verifica_equipo($texto);
                    if($valida==2){
                        $texto =estado_equipo_CONCE($ip,$puerto08,$puerto09);
                        $valida2= verifica_equipo($texto);
                        $ERROR++;
                        if($valida2==2){
                            $texto =estado_equipo_CONCE($ip,$puerto08,$puerto09);
                            $ERROR2++;
                        }
                    }
                }
                foreach (explode(chr(13), $texto) as $linea){
                    $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                    $data[] = $linea;
                }
                //print_r($data);
               $var=0;
                for($j=0;$j<count($data);$j++){
                    $line = preg_replace('/\s+/', '', $data[$j]);
                    //echo ' Linea: '.$line;
                    if(stristr($line,"interfacegiu0/17") || stristr($line,"interfaceeth0/17")){
                        //echo '##### entro bien #### ';
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/17/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                            
                        }    
                    }elseif(stristr($line,"interfacegiu0/18") || stristr($line,"interfaceeth0/18")){
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/18/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                        }    
                    }elseif(stristr($line,"interfacegiu0/19") || stristr($line,"interfaceeth0/19")){
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/19/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                        }    
                    }elseif(stristr($line,"interfacegiu0/20") || stristr($line,"interfaceeth0/20")){
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/20/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                        }    
                    }elseif(stristr($line,"interfacegiu0/7") || stristr($line,"interfaceeth0/7") || stristr($line,"interfacescu0/7")){
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/7/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                        }    
                    }elseif(stristr($line,"interfacegiu0/8") || stristr($line,"interfaceeth0/8") || stristr($line,"interfacempu0/8") || stristr($line,"interfacescu0/8")){
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/8/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                        }    
                    }elseif(stristr($line,"interfacegiu0/9") || stristr($line,"interfaceeth0/9") || stristr($line,"interfacempu0/9")){
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/9/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                        }    
                    }elseif(stristr($line,"interfacegiu0/16") || stristr($line,"interfaceeth0/16")){
                        $line2 = $data[$j+2];
                        if(stristr($line2,"display port ddm-info")){
                            $line2= explode(" ", $line2);
                            $numeroPuerto= $line2[3];
                            for ($i=2; $i <12 ; $i++) { 
                                $line3 = preg_replace('/\s+/', '', $data[$j+$i]);
                                if (stristr($line3,"TXpower(dBm):")) {
                                    $line2 = preg_replace('/\s+/', '', $data[$j+$i+1]);
                                    $puertaIngreso='0/16/'.$numeroPuerto;
                                    //TX
                                    $linea_dato = $line3;
                                    $valor1 = strstr($linea_dato, ':');
                                    $valor1= trim($valor1);
                                    $valor2= explode("[", $valor1);
                                    $valor3= $valor2[0];
                                    $valor3 = str_replace(':', '', $valor3);
                                    echo " #### Puerta: ".$puertaIngreso."  TX power: ".$valor3;
                                    //RX
                                    $linea_dato2 = $line2;
                                    $valor4 = strstr($linea_dato2, ':');
                                    $valor4= trim($valor4);
                                    $valor5= explode("[", $valor4);
                                    $valor6= $valor5[0];
                                    $valor6 = str_replace(':', '', $valor6);
                                    echo " RX power: ".$valor6;
                                    $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','$puertaIngreso','$valor3','$valor6','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                    $j=$j+$i;
                                    $i=12;
                                    $var++;
                                }
                            }
                        }    
                    }
                        
                }
            }unset($data);
            if ($var==0) {
                $query_detalle = "INSERT INTO OLT_POTENCIA_OPTICA_UPLINK
                                    (equipo,modelo,ip,region,puerta,potencia_tx,potencia_rx,fecha,week) VALUES ('$server','$row[2]','$ip','$row[3]','0/0/0','0','0','$fecha','$semana')";
                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
            }else{
                $var=0;
            }
        }
}
echo ' Entro al ciclo de errores: '.$ERROR.' veces. Y: '.$ERROR2.' EN EL SEGUNDO ';


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
function estado_equipo_MA5600T($ip,$puerto07,$puerto08,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20){
    $t=0;
    $i=0;
    $server = $ip;
    $puerto07=$puerto07;
    $puerto08=$puerto08;
    $puerto16=$puerto16;
    $puerto17=$puerto17;
    $puerto18=$puerto18;
    $puerto19=$puerto19;
    $puerto20=$puerto20;
    $total=$puerto17+$puerto18+$puerto20+$puerto19;
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
                break;
            case SHELL_CONFIG1:
                        if($puerto07>0){
                            for ($x=0; $x<$puerto07; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface scu 0/7\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto07=0;
                            break;
                        }
                        if($puerto08>0){
                            for ($x=0; $x<$puerto08; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface scu 0/8\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto08=0;
                            break;
                        }
                        if($puerto19>0){
                            for ($x=0; $x<$puerto19; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/19\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto19=0;
                            break;
                        }if($puerto20>0){
                            for ($x=0; $x<$puerto20; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/20\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto20=0;
                            break;
                        }
                        if($puerto18>0){
                            for ($x=0; $x<$puerto18; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/18\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto18=0;
                            break;
                        }
                        if($puerto17>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/17\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto17=0;
                            break;
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
function estado_equipo_MA5800_X15($ip,$puerto16,$puerto17,$puerto18,$puerto19,$puerto20,$puerto08,$puerto09){
    $t=0;
    $i=0;
    $server = $ip;
    $puerto16=$puerto16;
    $puerto17=$puerto17;
    $puerto18=$puerto18;
    $puerto19=$puerto19;
    $puerto20=$puerto20;
    $total=$puerto17+$puerto18+$puerto20+$puerto19;
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
                break;
            case SHELL_CONFIG1:
                        if($puerto16>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface eth 0/16\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto16=0;
                            break;
                        }
                        if($puerto19>0){
                            for ($x=0; $x<$puerto19; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface eth 0/19\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto19=0;
                            break;
                        }if($puerto20>0){
                            for ($x=0; $x<$puerto20; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface eth 0/20\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto20=0;
                            break;
                        }
                        if($puerto18>0){
                            for ($x=0; $x<$puerto18; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface eth 0/18\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto18=0;
                            break;
                        }
                        if($puerto17>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface eth 0/17\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto17=0;
                            break;
                        }if($puerto08>0){
                            for ($x=0; $x<$puerto08; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface mpu 0/8\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto08=0;
                            break;
                        }if($puerto09>0){
                            for ($x=0; $x<$puerto09; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface mpu 0/9\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto09=0;
                            break;
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
function estado_equipo_MA5680T($ip,$puerto17,$puerto18,$puerto19,$puerto20){
    $t=0;
    $i=0;
    $server = $ip;
    $puerto17=$puerto17;
    $puerto18=$puerto18;
    $puerto19=$puerto19;
    $puerto20=$puerto20;
    $total=$puerto17+$puerto18+$puerto20+$puerto19;
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
                break;
            case SHELL_CONFIG1:
                    
                        if($puerto19>0){
                            for ($x=0; $x<$puerto19; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/19\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto19=0;
                            break;
                        }if($puerto20>0){
                            for ($x=0; $x<$puerto20; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/20\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto20=0;
                            break;
                        }
                        if($puerto18>0){
                            for ($x=0; $x<$puerto18; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/18\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto18=0;
                            break;
                        }
                        if($puerto17>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/17\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto17=0;
                            break;
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

function estado_equipo_MA5603T($ip,$puerto08,$puerto09){
    $t=0;
    $i=0;
    $server = $ip;
    $puerto17=$puerto17;
    $puerto18=$puerto18;
    $puerto19=$puerto19;
    $puerto20=$puerto20;
    $puerto08=$puerto08;
    $puerto09=$puerto09;
    $total=$puerto17+$puerto18+$puerto20+$puerto19;
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
                break;
            case SHELL_CONFIG1:
                        if($puerto08>0){
                            for ($x=0; $x<$puerto08; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/8\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto08=0;
                            break;
                        }
                        if($puerto09>0){
                            for ($x=0; $x<$puerto09; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/9\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto09=0;
                            break;
                        }
                        if($puerto19>0){
                            for ($x=0; $x<$puerto19; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/19\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                                
                            }
                            //fwrite($stream, "quit\n");
                            $puerto19=0;
                            break;
                        }if($puerto20>0){
                            for ($x=0; $x<$puerto20; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/20\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto20=0;
                            break;
                        }
                        if($puerto18>0){
                            for ($x=0; $x<$puerto18; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/18\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto18=0;
                            break;
                        }
                        if($puerto17>0){
                            for ($x=0; $x<$puerto17; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface giu 0/17\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto17=0;
                            break;
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
function estado_equipo_CONCE($ip,$puerto08,$puerto09){
    $t=0;
    $i=0;
    $server = $ip;

    $puerto08=$puerto08;
    $puerto09=$puerto09;
    $total=$puerto08+$puerto09;
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
                break;
            case SHELL_CONFIG1:
                        if($puerto08>0){
                            for ($x=0; $x<$puerto08; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface mpu 0/8\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto08=0;
                            break;
                        }
                        if($puerto09>0){
                            for ($x=0; $x<$puerto09; $x++) { 
                                sleep(2);
                                fwrite($stream, "interface mpu 0/9\n");
                                fwrite($stream, "display port ddm-info $x\n");
                                $uname .= $match[0];
                                fwrite($stream, "\n");
                                fwrite($stream, "quit\n");
                            }
                            //fwrite($stream, "quit\n");
                            $puerto09=0;
                            break;
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