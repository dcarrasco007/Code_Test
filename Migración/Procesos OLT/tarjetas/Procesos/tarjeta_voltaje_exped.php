<?php
//include ('../../../conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----Hora
$sql_hora = "SELECT NOW()";
$resultHora = $mysqli->query($sql_hora) or die("error $sql_hora");
$hora = $resultHora->fetch_array(MYSQLI_NUM);
echo "Inicio: ".$hora[0]."\n";
//-----Fin

$fecha = date("Y-m-d");

    $ip = $argv[1];
    $server = $argv[2];
    $region = $argv[3]; 
    /* $ip = '10.99.3.38';
    $server = 'OLT-ANTOFAGASTA-2';
    $region = 'ANTOFAGASTA'; */
    $region= str_replace("_"," ",$region);

    $total_tarjetas = 0;
    $tarjetas = "";
    $cont_up = 0;
    $cont_pon = 0;
    $cont_poder = 0;
    $cont_con = 0;
    $cont_e1 = 0;
    $validaSalida=0;


            if($ip == '10.99.24.68'){
                $texto = estado_equipo2($ip);
            }else{
                $texto = estado_equipo($ip);
            }
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                $data[] = $linea;
            }
            
            for($j=0;$j<count($data);$j++){
                $line = preg_replace('/\s+/', ' ', $data[$j]);
                if(stristr($line,"unit:Watt")){
                    $linea_voltaje = $data[$j+2];
                    $voltaje = explode(' ',$linea_voltaje);
                    $cont_vol = 0;
                    foreach($voltaje as $val_vol){                       
                        if($val_vol != ''){
                            if($cont_vol == 1){
                                $voltaje_f = trim($val_vol);//VOLTAJE
                            }
                            $cont_vol++;
                        }
                    }
                    
                }               
                
                if(stristr($line,"BoardName")){
                    $tarjetas .= '----------------------------------------------------------------------------------<br>';
                    $tarjetas .= $line.'<br>';
                    $tarjetas .= '----------------------------------------------------------------------------------<br>';
                    for($k=$j+2;$k<count($data);$k++){
                        $line2 = preg_replace('/\s+/', ' ', $data[$k]);
                        if(stristr($line2,"quit")){
                            $validaSalida++;
                            $tarjetas.='-------------------------------------------------------------------------</br>';
                            break;
                        }
                        //GUARDA CADA NOMBRE DE LAS TARJETAS AUNQUE ESTE REPETIDO
                        $board_name = explode(' ',$line2);   
                                          
                        if(count($board_name) == 5 || count($board_name) == 6 || count($board_name) == 7){ 
                            $slot = trim($board_name[1]);
                            $nombre = trim($board_name[2]);
                            $query_tipo = "SELECT tipo_tarjeta FROM OLT_TIPO_TARJETA WHERE nombre = '$nombre'";
                            $result_t = $mysqli->query($query_tipo) or die("error $query_tipo");
                            $row_t = $result_t->fetch_array(MYSQLI_NUM);
                            if($row_t[0]){
                                if(stristr($row_t[0], 'UPLINK')){
                                    $tippp = 'UPLINK';
                                }
                                if(stristr($row_t[0], 'PON')){
                                    $tippp = 'PON';
                                }
                                if(stristr($row_t[0], 'CONTROL')){
                                    $tippp = 'CONTROL';
                                }
                                if(stristr($row_t[0], 'PODER')){
                                    $tippp = 'PODER';
                                }
                                if(stristr($row_t[0], 'E1')){
                                    $tippp = 'E1';
                                }
                                
                                switch($tippp){                                
                                    case 'UPLINK':  
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','UPLINK','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_up++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'PON':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PON','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_pon++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'CONTROL':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','CONTROL','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_con++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'PODER':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PODER','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_poder++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'E1':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','E1','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_e1++;
                                                    //$total_tarjetas++;
                                                    break;
                                }
                            }   
                        }elseif(count($board_name) == 14){
                            $slot = trim($board_name[10]);
                            $nombre = trim($board_name[11]);
                            $query_tipo = "SELECT tipo_tarjeta FROM OLT_TIPO_TARJETA WHERE nombre = '$nombre'";
                            $result_t = $mysqli->query($query_tipo) or die("error $query_tipo");
                            $row_t = $result_t->fetch_array(MYSQLI_NUM);
                            if($row_t[0]){
                                if(stristr($row_t[0], 'UPLINK')){
                                    $tippp = 'UPLINK';
                                }
                                if(stristr($row_t[0], 'PON')){
                                    $tippp = 'PON';
                                }
                                if(stristr($row_t[0], 'CONTROL')){
                                    $tippp = 'CONTROL';
                                }
                                if(stristr($row_t[0], 'PODER')){
                                    $tippp = 'PODER';
                                }
                                if(stristr($row_t[0], 'E1')){
                                    $tippp = 'E1';
                                }
                                
                                switch($tippp){                                
                                    case 'UPLINK':  
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','UPLINK','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_up++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'PON':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PON','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_pon++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'CONTROL':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','CONTROL','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_con++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'PODER':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','PODER','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_poder++;
                                                    //$total_tarjetas++;
                                                    break;
                                    case 'E1':
                                                    $query_detalle = "INSERT INTO OLT_DETALLE_TARJETA
                                                                         (equipo,ip,region,tipo_tarjeta,nombre,fecha,slot) VALUES
                                                                         ('$server','$ip','$region','E1','$nombre','$fecha','$slot')";
                                                    $mysqli->query($query_detalle) or die("Error query tipo $query_detalle");
                                                    $cont_e1++;
                                                    //$total_tarjetas++;
                                                    break;
                                }
                            }
                        }                       
                        
                        if(!stristr($line2,"-------------------------------------------------------------------------")){
                            if(stristr($line2,"---- More ( Press 'Q' to break ) ----[37D [37D")){
                                $line2 = str_replace("---- More ( Press 'Q' to break ) ----[37D [37D"," ",$line2);
                            }
                            $linea3 = str_replace(" ","  ",$line2);
                            if(stristr($linea3,"quit")){
                                $validaSalida++;
                                $tarjetas.='-------------------------------------------------------------------------</br>';
                            }
                            if($validaSalida==0){
                                $tarjetas .= $linea3.'<br>';
                            }
                            
                            
                            $cantidad_t = explode(' ',$line2);
                            $num = count($cantidad_t);
                            if($num > 3){
                                $total_tarjetas++;
                            }
                        }                      
                    }
                }          
            }   
            
            echo $query_voltaje = "INSERT INTO OLT_VOLTAJE_TARJETA
                              (equipo,ip,region,voltaje,cantidad,tarjetas,fecha) VALUES 
                              ('$server','$ip','$region','$voltaje_f','$total_tarjetas','$tarjetas','$fecha')";
            $mysqli->query($query_voltaje) or die("Error query voltaje $query_voltaje");
            
            $query_insert_tipo = "INSERT INTO OLT_TARJETA_CANTIDAD
                                 (equipo,ip,region,uplink,pon,control,poder,e1,fecha) VALUES
                                 ('$server','$ip','$region','$cont_up','$cont_pon','$cont_con','$cont_poder','$cont_e1','$fecha')";
            $mysqli->query($query_insert_tipo) or die("Error query tipo $query_insert_tipo");

      
    unset($data);

//-----Hora
$sql_hora = "SELECT NOW()";
$resultHora = $mysqli->query($sql_hora) or die("error $sql_hora");
$hora = $resultHora->fetch_array(MYSQLI_NUM);
echo "Fin: ".$hora[0]."\n";
//-----Fin

mysqli_close($mysqli);

function estado_equipo($server)
{
    $i=0;
    $cantConfig = 0;
    $server = $server;
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
            //array("*.The user password is too simple.*",SHELL,EXP_REGEXP),
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
                    sleep(1);
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
                    fwrite($stream, "display power 0\n");
                    sleep(1);
                }
                if ($i==1) {
                    fwrite($stream, "display board 0\n");
                    sleep(1);
                }
                if ($i>=2) {
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
    $i=0;
    $cantConfig = 0;
    $server = $server;
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
                    sleep(1);
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
                    fwrite($stream, "display power 0\n");
                    sleep(1);
                }
                if ($i==1) {
                    fwrite($stream, "display board 0\n");
                    sleep(1);
                }
                if ($i>=2) {
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