<?php
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$fecha = date('Y-m-d');
$puerta_1G = '';
$puerta_10G = '';
$fecha=date('Y-m-d H:i:s');
echo $fecha;
    //if($row[1] != 'OLT-VITACURA-1' && $row[1] != 'OLT-VITACURA-2'){
        $ip = $argv[1];
        $server = $argv[2];
        /* $ip = '10.99.26.30';
        $server = 'OLT-CALLEJONDELOSPERROS2PCS-1'; */
        $region = $argv[3];
        $total_vlan_10 = "";
        $total_vlan_1 = "";
        $y = ping_ip($ip);
        $vlan_detalle_10 = "";
        $vlan_detalle_1 = "";
        $puerta_10G = "";
        $puerta_1G = "";
        $cont_tipos = 0;//cuenta tipos trafico, si tiene de 10g o mas y de 1g su valor final sera 2 sino 1
        $cont_vlan_total = 0;
        if(trim($y)){
            if($y < 100){
                echo "Ping OK\n";     
                $query_GB = "SELECT puerta,capacidad_total FROM OLT_PUERTAS_UPLINKS_GB
                             WHERE olt = '$server' AND capacidad_total <> ''";
                $result_GB = $mysqli->query($query_GB) or die("error 2");
                
                while ($row = $result_GB->fetch_array(MYSQLI_NUM)) {
                    if($row[1] == '10Gb' || $row[1] == '20Gb' || $row[1] == '40Gb' || $row[1] == '60Gb' || $row[1] == '80Gb'|| $row[1] == '100Gb' || $row[1] == '120Gb'|| $row[1] == '200Gb'){
                        $puerta_10G = $row[0];
                    }elseif($row[1] == '1Gb' || $row[1] == '2Gb'){
                        $puerta_1G = $row[0];
                    }
                }
                echo " Puertas: ".$puerta_10G."\n";
                if($puerta_10G != "" && $puerta_1G != ""){
                    //SI EL EQUIPO TIENE PUERTAS DE 10G Y DE 1 G
                    $cont_tipos = 2;
                }else{
                    //SI EL EQUIPO TIENE SOLO PUERTA DE 10G O DE 1G
                    $cont_tipos = 1;
                }
    
                if($puerta_10G != ''){//si es de 10Gb    
                    
                    $query_pe = "SELECT uplink FROM OLT_UPLINKS WHERE equipo = '$server' AND puerto = '$puerta_10G'";
                    $result_pe = $mysqli->query($query_pe) or die("error $query_pe");
                    $row_pe_10g = $result_pe->fetch_array(MYSQLI_NUM);
                    $pe_10g = explode(' ',$row_pe_10g[0]);
                    $pe_10g = $pe_10g[0];
                    
                    if($ip == '10.99.24.68'){
                        $texto = estado_equipo2($ip,$puerta_10G);
                    }else{
                        $texto = estado_equipo($ip,$puerta_10G);
                    }
                    $valida= verifica_equipo($texto);
                            //echo 'El Valida tiene: '.$valida.' |||';
                            if ($valida==2){
                                if($ip == '10.99.24.68'){
                                    $texto = estado_equipo2($ip,$puerta_10G);
                                }else{
                                    $texto = estado_equipo($ip,$puerta_10G);
                                }
                                $ERROR2++;
                                $valida2= verifica_equipo($texto);
                                if ($valida2==2) {
                                    if($ip == '10.99.24.68'){
                                        $texto = estado_equipo2($ip,$puerta_10G);
                                    }else{
                                        $texto = estado_equipo($ip,$puerta_10G);
                                    }
                                    $ERROR2++;
                                }
                                $valida3= verifica_equipo($texto);
                                if ($valida3==2) {
                                    if($ip == '10.99.24.68'){
                                        $texto = estado_equipo2($ip,$puerta_10G);
                                    }else{
                                        $texto = estado_equipo($ip,$puerta_10G);
                                    }
                                    $ERROR2++;
                                }
                            }
                    //$texto = estado_equipo($ip,$puerta_10G);
                    foreach (explode(chr(13), $texto) as $linea)
                    {
                        $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                        $data[] = $linea;
                    }
                    print_r($data);
                    for($j=0;$j<count($data);$j++){
                        $line = preg_replace('/\s+/', ' ', $data[$j]);
                        if(stristr($line,"display port")){
                            if(!stristr($data[$j+3],"Command:")){
                                for($k=$j+1;$k<count($data);$k++){
                                    $line2 = preg_replace('/\s+/', ' ', $data[$k]);
                                    if(stristr($line2,"Total:")){
                                        if(stristr($line2,"Press")){                                          
                                            $aaaaa = explode(' ',$line2);
                                            $aux_tot = 0;
                                            foreach($aaaaa as $vall){
                                                if(stristr($vall,"Total")){
                                                    $total_vlan_10 .= $vall;
                                                    $aux_tot++;
                                                }elseif($aux_tot > 0){
                                                    $total_vlan_10 .= ' '.$vall;
                                                    $aux_tot = 0;
                                                    $aux = explode(':',$total_vlan_10);
                                                    $cont_vlan_total = $cont_vlan_total + trim($aux[1]);
                                                }
                                            }
                                        }else{                                          
                                            $total_vlan_10 = trim($line2);
                                            $aux = explode(':',$total_vlan_10);
                                            $cont_vlan_total = $cont_vlan_total + trim($aux[1]);
                                        }
                                    } 
                                    if(stristr($line2,"---- More ( Press 'Q' to break ) ----[37D [37D")){
                                        $line2 = str_replace("---- More ( Press 'Q' to break ) ----[37D [37D"," ",$line2);
                                    }
                                    $linea3 = str_replace("  ","      ",$line2);
                                    $linea3 = str_replace("quit","",$linea3);
                                    $linea3 = str_replace("Check whether system data has been changed. Please save data before logout.","",$linea3);
                                    $linea3 = str_replace("Are you sure to log out? (y/n)[n]:","",$linea3);
                                    $vlan_detalle_10 .= $linea3.'<br>';
                                }
                                break;
                            }else{
                                for($k=$j+5;$k<count($data);$k++){
                                    $line2 = preg_replace('/\s+/', ' ', $data[$k]);
                                    if(stristr($line2,"Total:")){
                                        $total_vlan_10 = trim($line2);
                                        $total_vlan_10 = str_replace("---- More ( Press 'Q' to break ) ----[37D [37D ","",$total_vlan_10);
                                        $aux = explode(':',$total_vlan_10);
                                        $cont_vlan_total = $cont_vlan_total + trim($aux[1]);
                                    } 
                                    if(stristr($line2,"---- More ( Press 'Q' to break ) ----[37D [37D")){
                                        $line2 = str_replace("---- More ( Press 'Q' to break ) ----[37D [37D"," ",$line2);
                                    }
                                    $line2 = str_replace("---- More ( Press 'Q' to break ) ----","",$line2);
                                    $line2 = str_replace("[37D                                     [37D  ","",$line2);
                                    $line2 = str_replace("quit","",$line2);
                                    $line2 = str_replace("Check whether system data has been changed. Please save data before logout.","",$line2);
                                    $line2 = str_replace("Are you sure to log out? (y/n)[n]:","",$line2);
                                    $line2 = str_replace("quit","",$line2);
                                    $vlan_detalle_10 .= $line2.'<br>';
                                }
                                break;
                            }                            
                        }                      
                    }     
                    $vlan_detalle_10 = $mysqli -> real_escape_string($vlan_detalle_10);
                    $total_vlan_10 = $mysqli -> real_escape_string($total_vlan_10);
                    //echo "|".$total_vlan_10."|";
                    $query_vlan_10 = "INSERT INTO OLT_CANTIDAD_VLAN (`equipo`,`ip`,`region`,`cantidad`,`detalle`,`fecha`,`trafico`,`tipos`,`pe`) 
                                     VALUES 
                                     ('$server','$ip','$region','$total_vlan_10','$vlan_detalle_10','$fecha','10/20/40GB','$cont_tipos','$pe_10g')";
                    $mysqli->query($query_vlan_10) or die("Error query vlan 10G: ".$mysqli->error."\n");      
                }
                
                unset($data);
                
                if($puerta_1G != ''){//si es de 1Gb
                    
                    $query_pe = "SELECT uplink FROM OLT_UPLINKS WHERE equipo = '$server' AND puerto = '$puerta_1G'";
                    $result_pe = $mysqli->query($query_pe) or die("error $query_pe");
                    $row_pe_1g = $result_pe->fetch_array(MYSQLI_NUM);
                    $pe_1g = explode(' ',$row_pe_1g[0]);
                    $pe_1g = $pe_1g[0];
                    
                    if($ip == '10.99.24.68'){
                        $texto = estado_equipo2($ip,$puerta_1G);
                    }else{
                        $texto = estado_equipo($ip,$puerta_1G);
                    }
                    $valida= verifica_equipo($texto);
                            //echo 'El Valida tiene: '.$valida.' |||';
                            if ($valida==2){
                                if($ip == '10.99.24.68'){
                                    $texto = estado_equipo2($ip,$puerta_1G);
                                }else{
                                    $texto = estado_equipo($ip,$puerta_1G);
                                }
                                $ERROR2++;
                                $valida2= verifica_equipo($texto);
                                if ($valida2==2) {
                                    if($ip == '10.99.24.68'){
                                        $texto = estado_equipo2($ip,$puerta_1G);
                                    }else{
                                        $texto = estado_equipo($ip,$puerta_1G);
                                    }
                                    $ERROR2++;
                                }
                            }
                    //$texto = estado_equipo($ip,$puerta_1G);
                    foreach (explode(chr(13), $texto) as $linea)
                    {
                        $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                        $data[] = $linea;
                    }
                    
                    for($j=0;$j<count($data);$j++){
                        $line = preg_replace('/\s+/', ' ', $data[$j]);  
                        
                        if(stristr($line,"display port")){
                            if(!stristr($data[$j+3],"Command:")){
                                for($k=$j+1;$k<count($data);$k++){
                                    $line2 = preg_replace('/\s+/', ' ', $data[$k]);
                                    if(stristr($line2,"Total:")){
                                        if(stristr($line2,"Press")){                                          
                                            $bbbbb = explode(' ',$line2);
                                            $aux_tot = 0;
                                            foreach($bbbbb as $vall){
                                                if(stristr($vall,"Total")){
                                                    $total_vlan_1 .= $vall;
                                                    $aux_tot++;
                                                }elseif($aux_tot > 0){
                                                    $total_vlan_1 .= ' '.$vall;
                                                    $aux_tot = 0;
                                                    $aux = explode(':',$total_vlan_1);
                                                    $cont_vlan_total = $cont_vlan_total + trim($aux[1]);
                                                }
                                            }
                                        }else{                                          
                                            $total_vlan_1 = trim($line2);
                                            $aux = explode(':',$total_vlan_1);
                                            $cont_vlan_total = $cont_vlan_total + trim($aux[1]);
                                        }
                                    }  
                                    if(stristr($line2,"---- More ( Press 'Q' to break ) ----[37D [37D")){
                                        $line2 = str_replace("---- More ( Press 'Q' to break ) ----[37D [37D"," ",$line2);
                                    }
                                    $linea3 = str_replace("  ","      ",$line2);
                                    $linea3 = str_replace("quit","",$linea3);
                                    $linea3 = str_replace("Check whether system data has been changed. Please save data before logout.","",$linea3);
                                    $linea3 = str_replace("Are you sure to log out? (y/n)[n]:","",$linea3);
                                    $vlan_detalle_1 .= $linea3.'<br>';
                                }
                                break;
                            }else{
                                for($k=$j+5;$k<count($data);$k++){
                                    $line2 = preg_replace('/\s+/', ' ', $data[$k]);
                                    if(stristr($line2,"Total:")){
                                        $total_vlan_1 = trim($line2);
                                        $aux = explode(':',$total_vlan_1);
                                        $cont_vlan_total = $cont_vlan_total + trim($aux[1]);
                                    } 
                                    if(stristr($line2,"---- More ( Press 'Q' to break ) ----[37D [37D")){
                                        $line2 = str_replace("---- More ( Press 'Q' to break ) ----[37D [37D"," ",$line2);
                                    }
                                    $linea3 = str_replace("  ","      ",$line2);
                                    $linea3 = str_replace("quit","",$linea3);
                                    $linea3 = str_replace("Check whether system data has been changed. Please save data before logout.","",$linea3);
                                    $linea3 = str_replace("Are you sure to log out? (y/n)[n]:","",$linea3);
                                    $vlan_detalle_1 .= $linea3.'<br>';
                                }
                                break;
                            }                            
                        }                    
                    }     
                    //if($vlan_detalle_1 != ''){                                   
                        $query_vlan_1 = "INSERT INTO OLT_CANTIDAD_VLAN
                                      (equipo,ip,region,cantidad,detalle,fecha,trafico,tipos,pe) VALUES 
                                      ('$server','$ip','$region','$total_vlan_1','$vlan_detalle_1','$fecha','1GB','$cont_tipos','$pe_1g')";
                        $mysqli->query($query_vlan_1) or die("Error query vlan 1G $query_vlan_1");
                    //}

                }
            }
        }
        unset($data);
    //}


mysqli_close($mysqli);

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

function estado_equipo($server,$puerto)
{
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
            array(".*>",SHELL,EXP_REGEXP),
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
                sleep(1);
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
                sleep(1);
                break;
            case SHELL:
                if($b == 0){
                    fwrite($stream, "enable\n");
                    sleep(1);
                    $b++;
                }elseif($b == 1){
                    fwrite($stream, "\n");
                    sleep(1);
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
                    sleep(1);
                }
                if ($i>=1) {
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

function estado_equipo2($server,$puerto)
{
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
                if ($i>=1) {
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