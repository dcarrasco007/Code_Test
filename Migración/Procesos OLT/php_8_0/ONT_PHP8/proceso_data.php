<?php
require_once __DIR__ . '/expect_compat.php'; // Reemplazo en PHP puro de la extensión PECL 'expect' (no soportada en PHP 8)
// --- Migración a PHP 8.0: constantes bareword para los switch de expect_expectl ---
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF','ACEPTAR'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
include ('../../conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
/* $truncate = "TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE";
$mysqli->query($truncate) or die("Error query ont $truncate");*/
    $ip = $argv[1];
    $server = $argv[2];
    $modelo = $argv[3];
    $region = $argv[4];
    /* $ip = '10.99.24.115';
    $server = 'OLT-LASCONDES-2';
    $modelo = 'MA5600T';
    $region = 'LAS_CONDES'; */
    $puertos=obtenerPuertos($modelo);
    $y = ping_ip($ip);
    $tarjetas = "";
    $cont_up = 0;
    $cont_pon = 0;
    $cont_poder = 0;
    $cont_con = 0;
    $query = "SELECT
    OLT_INFO_ONT_PRUEBA.equipo,
    OLT_INFO_ONT_PRUEBA.fn,
    OLT_INFO_ONT_PRUEBA.sn,
    OLT_INFO_ONT_PRUEBA.pn,
    OLT_INFO_ONT_PRUEBA.onu,
    OLT_INFO_ONT_PRUEBA.name ,
    OLT_INFO_ONT_PRUEBA.serial_number
    FROM
    OLT_INFO_ONT_PRUEBA
    WHERE OLT_INFO_ONT_PRUEBA.equipo ='$server'";
    $result=$mysqli->query($query) or die("Error query ont detalle 1 $query");
    while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $detalle[]=$row;
    }

   
    if(trim($y)){
        if($y < 100){   
                $texto = estado_equipo($ip,$puertos);         

            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea); // Migración PHP 8.0: eregi_replace() eliminada en PHP 7;
                $linea = str_replace(array('','[16D','[16D [16','[16D                [16D','[16D','[16D                [16D ',' [16D [16D ',' [16D[16D','[1D',
            '[37D','[37D                                     ','[37D                                     [37D',"---- More ( Press 'Q' to break ) ----",' [1D','}:','Command:',"% Unknown command, the error locates at '^'",'^'),'',$linea);
                $data[] = $linea;
            } 
            //print_r($data);  
            $grep = array();
            $grep = preg_grep("/interface gpon 0/", $data);
            $grep[] = key($grep);
            //print_r($grep);
            foreach($grep as $key => $value){
                $llaves[] = $key;
            }
            //print_r($llaves);
            for($i = 0; $i <count($data);$i++){
                if($i>=$llaves[0] && $i<$llaves[1]){
                    $array1[]=$data[$i];
                }else if($i>=$llaves[1] && $i<$llaves[2]){
                    $array2[]=$data[$i];
                }else if($i>=$llaves[2] && $i<$llaves[3]){
                    $array3[]=$data[$i];
                }else if($i>=$llaves[3] && $i<$llaves[4]){
                    $array4[]=$data[$i];
                }else if($i>=$llaves[4] && $i<$llaves[5]){
                    $array5[]=$data[$i];
                }else if($i>=$llaves[5] && $i<$llaves[6]){
                    $array6[]=$data[$i];
                }else if($i>=$llaves[6] && $i<$llaves[7]){
                    $array7[]=$data[$i];
                }else if($i>=$llaves[7] && $i<$llaves[8]){
                    $array8[]=$data[$i];
                }else if($i>=$llaves[8] && $i<$llaves[9]){
                    $array9[]=$data[$i];
                }else if($i>=$llaves[9] && $i<$llaves[10]){
                    $array10[]=$data[$i];
                }else if($i>=$llaves[10] && $i<$llaves[11]){
                    $array11[]=$data[$i];
                }else if($i>=$llaves[11] && $i<$llaves[12]){
                    $array12[]=$data[$i];
                }else if($i>=$llaves[12] && $i<$llaves[13]){
                    $array13[]=$data[$i];
                }else if($i>=$llaves[13] && $i<$llaves[14]){
                    $array14[]=$data[$i];
                }else if($i>=$llaves[14]){
                    $array15[]=$data[$i];
                }
            }
            //echo'array 1: '.$llaves[0].'-------';
            print_r($array1);
            print_r($array2);
            print_r($array3);
            print_r($array4);
            print_r($array5);
            print_r($array6);
            print_r($array7);
            print_r($array8);
            print_r($array9);
            print_r($array10);
            print_r($array11);
            print_r($array12);
            print_r($array13);
            print_r($array14);
            print_r($array15);
            $mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
            $mysqli ->  set_charset("utf8");
        if($modelo=='MA5800-X15'){
            if(isset($puertos[0])){
                $puerto1=$puertos[0];
                obtenerData2($array1,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[1])){
                $puerto1=$puertos[1];
                obtenerData2($array2,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[2])){
                $puerto1=$puertos[2];
                obtenerData2($array3,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[3])){
                $puerto1=$puertos[3];
                obtenerData2($array4,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[4])){
                $puerto1=$puertos[4];
                obtenerData2($array5,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[5])){
                $puerto1=$puertos[5];
                obtenerData2($array6,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[6])){
                $puerto1=$puertos[6];
                obtenerData2($array7,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[7])){
                $puerto1=$puertos[7];
                obtenerData2($array8,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[8])){
                $puerto1=$puertos[8];
                obtenerData2($array9,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[9])){
                $puerto1=$puertos[9];
                obtenerData2($array10,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[10])){
                $puerto1=$puertos[10];
                obtenerData2($array11,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[11])){
                $puerto1=$puertos[11];
                obtenerData2($array12,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[12])){
                $puerto1=$puertos[12];
                obtenerData2($array13,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[13])){
                $puerto1=$puertos[13];
                obtenerData2($array14,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
        }else{
            if(isset($puertos[0])){
                $puerto1=$puertos[0];
                obtenerData2($array1,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[1])){
                $puerto1=$puertos[1];
                obtenerData2($array2,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[2])){
                $puerto1=$puertos[2];
                obtenerData2($array3,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[3])){
                $puerto1=$puertos[3];
                obtenerData2($array4,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[4])){
                $puerto1=$puertos[4];
                obtenerData2($array5,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[5])){
                $puerto1=$puertos[5];
                obtenerData2($array6,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[6])){
                $puerto1=$puertos[6];
                obtenerData2($array7,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[7])){
                $puerto1=$puertos[7];
                obtenerData2($array8,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[8])){
                $puerto1=$puertos[8];
                obtenerData2($array9,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[9])){
                $puerto1=$puertos[9];
                obtenerData2($array10,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[10])){
                $puerto1=$puertos[10];
                obtenerData2($array11,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[11])){
                $puerto1=$puertos[11];
                obtenerData2($array12,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[12])){
                $puerto1=$puertos[12];
                obtenerData2($array13,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
            if(isset($puertos[13])){
                $puerto1=$puertos[13];
                obtenerData2($array14,$puerto1, $region, $ip, $detalle, $modelo, $server);
            }
        }    
        }
        
    }
    //print_r($detalle);
    unset($data);
    unset($array_1);
    unset($array_2);
    
    function obtenerData2($array1, $puertos, $region, $ip, $detalle, $modelo, $server){
        global $mysqli;
        $xy=0;
        $grep = array();
        if(trim($array1[1])=='Failure: This board does not exist'){
            return 0;
        }else{
            for($i=0; $i<16; $i++){
                $grep = preg_grep("/display ont optical-info $i all/", $array1);
                $grep = key($grep);
                    if(count($grep)>1){
                        array_shift($grep);
                    }
                /* echo' i: '.$i."\n";
                echo $grep."\n"; */
                $valor2=trim($array1[$grep+1]);
                $valor3=trim($array1[$grep+5]);
                /* echo 'valor2'.$valor2."\n";
                echo 'valor3'.$valor3."\n"; */
                if($valor2!='The ONT optical module information does not exist' && $valor3!='The ONT optical module information does not exist'){
                    
                    $aux2=0;
                    for($j=$grep;$j<count($array1);$j++){
                        if(trim($array1[$j])=='-----------------------------------------------------------------------------'){
                            
                            $aux2++;
                        }
                        if($aux2==2){
                            if(trim($array1[$j])!='-----------------------------------------------------------------------------'){
                                
                                $valor = preg_replace('/\s+/', ' ',trim($array1[$j]));
                                $aux=explode(' ',$valor);
                                //$numArrayDetalle=0;
                                $auxPuerto=explode('/',$puertos);
                                $numArrayDetalle='S/N';
                                for($a = 0; $a <count($detalle);$a++){
                                    if($detalle[$a][1]==0 && $detalle[$a][2]==$auxPuerto[1] && $detalle[$a][3]==$i && $detalle[$a][4]==$aux[0]){
                                        $numArrayDetalle=$a;
                                        //echo 'El numero es: '.$numArrayDetalle."\n";
                                        break;
                                    }    
                                }
                                
                                //echo 'Contador:'.$xy."\n";
                                
                                    $puertoCompleto=$puertos.'/'.$i;
                                    //echo 'segundo El numero es: '.$numArrayDetalle."\n";
                                    //intval($numArrayDetalle);
                                    if(stristr($numArrayDetalle,'S/N')){
                                        //echo 'entro en S/N'."\n";
                                        $sn=0;
                                        $cs=0;
                                    }else{
                                    
                                        if($numArrayDetalle=='0'){
                                            //echo'entre aqui2';
                                            $sn=$detalle[0][6];
                                            $cs=$detalle[0][5];
                                           
                                        }else{
                                            $sn=$detalle[$numArrayDetalle][6];
                                            $cs=$detalle[$numArrayDetalle][5];
                                        }
                                    }
                                    if($aux[1]=='-'){$aux[1]=0;}
                                    $query = "INSERT INTO OLT_INFORMACION_ONT_DETALLE
                                    (equipo, modelo, ip, comuna, puerta, id_ont, rx_power, distance, sn, cs, fecha)
                                    VALUES('$server', '$modelo', '$ip', '$region', '$puertoCompleto', $aux[0], $aux[1], $aux[7], '$sn', '$cs', NOW())";
                                                $mysqli->query($query) or die("Error query ont detalle 1 $query");
                                    $info=$puertoCompleto.' | '.$aux[0].' | '.$aux[1].' | '.$aux[2].' | '.$aux[7];
                                    echo $info."\n";
                                    
                            }    
                        }
                        
                        if($aux2==3){
                            break;
                        }    
                    }   
                }
            }
        }
                //print_r($grep);
        
    }
    
function estado_equipo($server, $puertos)
{
    $i=0;
    $server = $server;
    $puertos = $puertos;
    $cantidadPuertos=count($puertos);
    
    for ($e=0; $e <$cantidadPuertos ; $e++) { 
        $comandos[]='interface gpon '.$puertos[$e];
        $comandos[]='display ont optical-info 0 all';
        $comandos[]='display ont optical-info 1 all';
        $comandos[]='display ont optical-info 2 all';
        $comandos[]='display ont optical-info 3 all';
        $comandos[]='display ont optical-info 4 all';
        $comandos[]='display ont optical-info 5 all';
        $comandos[]='display ont optical-info 6 all';
        $comandos[]='display ont optical-info 7 all';
        $comandos[]='display ont optical-info 8 all';
        $comandos[]='display ont optical-info 9 all';
        $comandos[]='display ont optical-info 10 all';
        $comandos[]='display ont optical-info 11 all';
        $comandos[]='display ont optical-info 12 all';
        $comandos[]='display ont optical-info 13 all';
        $comandos[]='display ont optical-info 14 all';
        $comandos[]='display ont optical-info 15 all';
        $comandos[]='quit';
    }
//print_r($comandos);
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout( 30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada (antes se usaba sin definir -> Warning)
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
                        expect_send($stream, $var. "\n");
                        $a++;
                        sleep(1);
                    }else{    

                        expect_send($stream, "quit\n");  
                        fclose($stream);
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
        $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea); // Migración PHP 8.0: eregi_replace() eliminada en PHP 7;
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
function obtenerPuertos($modelo){

    switch ($modelo) {
        case 'MA5600T':
            $puertos=array('0/1','0/2','0/3'  ,'0/4','0/5','0/6','0/9','0/10','0/11','0/12','0/13','0/14','0/15','0/16');
            break;
        case 'MA5680T':
            $puertos=array('0/1','0/2','0/3','0/4','0/5','0/6','0/9','0/10','0/11','0/12','0/13');
            break;
        case 'MA5603T':
            $puertos=array('0/0','0/1','0/2','0/3','0/4','0/5');
                break;
        case 'MA5800-X15':
            $puertos=array('0/1', '0/2','0/3' ,'0/4','0/5','0/6','0/7','0/10','0/11','0/12','0/13', '0/14','0/15');
            break;
        
    }

    return $puertos;
}

?>