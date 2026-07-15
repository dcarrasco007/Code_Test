<?php
require_once __DIR__ . '/../expect_compat.php'; // Reemplazo en PHP puro de la extensión PECL 'expect' (no soportada en PHP 8)
// --- Migración a PHP 8.0: constantes bareword para los switch de expect_expectl ---
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF','ACEPTAR'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
//include ('../../../conexion/conexion_db.php');
include ('/u01/conexion/conexion_db.php');



    $ip = $argv[1];
    $server = $argv[2];
    $region = $argv[3];
    //pruebas
   /*  $ip = '10.99.5.150';
    $server = 'OLT-ALTOPENUELAS-2';
    $region = 'COQUIMBO'; */
    //------
    //$fecha=date('d-m-Y');
    $fecha_actual = date("Y-m-d");
$fecha_actual_obj = DateTime::createFromFormat("Y-m-d", $fecha_actual);
$fecha_actual_obj->modify("+1 day");
$fecha = $fecha_actual_obj->format("Y-m-d");
echo 'Fecha registro: '.$fecha;

    $region= str_replace("_"," ",$region);
    $total_tarjetas = 0;
    $y = ping_ip($ip);
    $tarjetas = "";
    $cont_up = 0;
    $cont_pon = 0;
    $cont_poder = 0;
    $cont_con = 0;
    if(trim($y)){
        if($y < 100){   
            if($ip == '10.99.24.68'){
                $texto = estado_equipo2($ip);
                $valida= verifica_equipo($texto);
            }else{
                $texto = estado_equipo($ip);
                $valida= verifica_equipo($texto);
            }
            
            if ($valida==2) {
                if($ip == '10.99.24.68'){
                    $texto = estado_equipo2($ip);
                    $valida= verifica_equipo($texto);
                }else{
                    $texto = estado_equipo($ip);
                    $valida= verifica_equipo($texto);
                }
            }
            if ($valida==2) {
                sleep(1);
                if($ip == '10.99.24.68'){
                    $texto = estado_equipo2($ip);
                    $valida= verifica_equipo($texto);
                }else{
                    $texto = estado_equipo($ip);
                    $valida= verifica_equipo($texto);
                }
            }
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea); // Migración PHP 8.0: eregi_replace() eliminada en PHP 7;
                $data[] = $linea;
            } 
            //print_r($data);  
            $grep2 = preg_grep("/display ont info 0 all/", $data);
            $key_grep2 = key($grep2);
            echo 'llave: '.$key_grep2;
            $array_1 = array();
            $array_2 = array();
            for ($i=0; $i <count($data); $i++) {
                if($i<=$key_grep2-1){
                    $array_1[]=$data[$i];
                }else{
                    $array_2[]=$data[$i];
                } 
                
            }
            echo " Array que pasa: ";print_r($array_2);
            $mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
            $mysqli ->  set_charset("utf8");
            parsear($array_2,$ip,$server,$region,$fecha);
            /*
            echo '<pre>';
            print_r($array_1);
            echo '</pre>';
            
            echo '<pre>';
            print_r($array_2);
            echo '</pre>';
            mysqli_close($mysqli);

            die;*/
            for($j=0;$j<=$key_grep2;$j++){
                $line = preg_replace('/\s+/', ' ', $array_1[$j]);
                if(stristr($line,'F/S/P/ONT-ID')){
                    for($k=$j+3;$k<=$key_grep2;$k++){
                        $line2 = preg_replace('/\s+/', ' ', $array_1[$k]);
                        $datos = explode(' ',$line2);
                        
                        
                        
                        if(count($datos) == 10){
                            if(!stristr($datos[1],'ALARM') && !stristr($datos[1],'PARAMETERS')){
                                if(!stristr($datos[4],'HWTC')){
                                    $puerto = $datos[1].$datos[2].$datos[3];
                                    $puerto = substr($puerto, 0, -1);
                                    $nombre = $datos[6];
                                    $version = $datos[7];
                                    $ont = $datos[4];
                                    if(!stristr($puerto,'/')){
                                        echo ' dato erroneo 2: '.$puerto;
                                    }else{
                                    if($nombre == '245H'){
                                        $nombre = 'HG8245H';
                                    }elseif($nombre == '240'){
                                        $nombre = 'HG8240';
                                    }elseif($nombre == '245Q'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == '245'){
                                        $nombre = 'HG8245';
                                    }elseif($nombre == 'Loco'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == 'OT928G'){
                                        $nombre = 'SmartAX OT928G';
                                    }
                                    
                                    $query = "INSERT INTO OLT_ONT_DETALLE_2
                                             (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                              VALUES 
                                             ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                    $mysqli->query($query) or die("Error query ont detalle 1 $query");
                                    }
                                }else{
                                    $puerto = $datos[1].$datos[2];
                                    $puerto = substr($puerto, 0, -1);
                                    $nombre = $datos[5].' '.$datos[6];
                                    $version = $datos[7];
                                    $ont = $datos[3];
                                    if(!stristr($puerto,'/')){
                                        echo ' dato erroneo 2: '.$puerto;
                                    }else{
                                    if($nombre == '245H'){
                                        $nombre = 'HG8245H';
                                    }elseif($nombre == '240'){
                                        $nombre = 'HG8240';
                                    }elseif($nombre == '245Q'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == '245'){
                                        $nombre = 'HG8245';
                                    }elseif($nombre == 'Loco'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == 'OT928G'){
                                        $nombre = 'SmartAX OT928G';
                                    }
                                    
                                    $query = "INSERT INTO OLT_ONT_DETALLE_2
                                             (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                              VALUES 
                                             ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                    $mysqli->query($query) or die("Error query ont detalle 1.2 $query");
                                    }
                                }
                            }
                        }  
                        if(count($datos) == 8){
                            if(stristr($datos[3],'HWTC')){
                                $puerto = $datos[1];
                                $puerto = substr($puerto, 0, -1);
                                $nombre = $datos[4];
                                $version = $datos[5];
                                $ont = $datos[2];
                                if(!stristr($puerto,'/')){
                                    echo ' dato erroneo 2: '.$puerto;
                                }else{
                                if($nombre == '245H'){
                                    $nombre = 'HG8245H';
                                }elseif($nombre == '240'){
                                    $nombre = 'HG8240';
                                }elseif($nombre == '245Q'){
                                    $nombre = 'HG8245Q';
                                }elseif($nombre == '245'){
                                    $nombre = 'HG8245';
                                }elseif($nombre == 'Loco'){
                                    $nombre = 'HG8245Q';
                                }elseif($nombre == 'OT928G'){
                                    $nombre = 'SmartAX OT928G';
                                }
                                                        
                                $query = "INSERT INTO OLT_ONT_DETALLE_2
                                         (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                          VALUES 
                                         ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                $mysqli->query($query) or die("Error query ont detalle 2 $query");
                                }
                            }
                        }                    
                        if(count($datos) == 9){
                            $puerto = $datos[1].$datos[2];
                            $puerto = substr($puerto, 0, -1);
                            $nombre = $datos[5];
                            $version = $datos[6];
                            $ont = $datos[3];
                            if(!stristr($puerto,'/')){
                                echo ' dato erroneo 2: '.$puerto;
                            }else{
                            if($nombre == '245H'){
                                $nombre = 'HG8245H';
                            }elseif($nombre == '240'){
                                $nombre = 'HG8240';
                            }elseif($nombre == '245Q'){
                                $nombre = 'HG8245Q';
                            }elseif($nombre == '245'){
                                $nombre = 'HG8245';
                            }elseif($nombre == 'Loco'){
                                $nombre = 'HG8245Q';
                            }elseif($nombre == 'OT928G'){
                                $nombre = 'SmartAX OT928G';
                            }
                                                    
                            $query = "INSERT INTO OLT_ONT_DETALLE_2
                                     (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                      VALUES 
                                     ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                            $mysqli->query($query) or die("Error query ont detalle 3 $query");
                            }
                        }
                        if(count($datos) > 10){
                            if(count($datos) == 19){
                                $puerto = $datos[10].$datos[11].$datos[12];
                                $puerto = substr($puerto, 0, -1);
                                $nombre = $datos[15];
                                $version = $datos[16];
                                $ont = $datos[13];
                                if(!stristr($puerto,'/')){
                                    echo ' dato erroneo 2: '.$puerto;
                                }else{
                                if($nombre == '245H'){
                                    $nombre = 'HG8245H';
                                }elseif($nombre == '240'){
                                    $nombre = 'HG8240';
                                }elseif($nombre == '245Q'){
                                    $nombre = 'HG8245Q';
                                }elseif($nombre == '245'){
                                    $nombre = 'HG8245';
                                }elseif($nombre == 'Loco'){
                                    $nombre = 'HG8245Q';
                                }elseif($nombre == 'OT928G'){
                                    $nombre = 'SmartAX OT928G';
                                }
                                                                
                                $query = "INSERT INTO OLT_ONT_DETALLE_2
                                         (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                          VALUES 
                                         ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                $mysqli->query($query) or die("Error query ont detalle 4 $query");
                                }
                            }elseif(count($datos) == 18){
                                $puerto = $datos[10].$datos[11];
                                $puerto = substr($puerto, 0, -1);
                                $nombre = $datos[14];
                                $version = $datos[15];
                                $ont = $datos[12];
                                if(!stristr($puerto,'/')){
                                    echo ' dato erroneo 2: '.$puerto;
                                }else{
                                if($nombre == '245H'){
                                    $nombre = 'HG8245H';
                                }elseif($nombre == '240'){
                                    $nombre = 'HG8240';
                                }elseif($nombre == '245Q'){
                                    $nombre = 'HG8245Q';
                                }elseif($nombre == '245'){
                                    $nombre = 'HG8245';
                                }elseif($nombre == 'Loco'){
                                    $nombre = 'HG8245Q';
                                }elseif($nombre == 'OT928G'){
                                    $nombre = 'SmartAX OT928G';
                                }
                                                                
                                $query = "INSERT INTO OLT_ONT_DETALLE_2
                                         (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                          VALUES 
                                         ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                $mysqli->query($query) or die("Error query ont detalle 4.1 $query");
                                }
                            }elseif(count($datos) == 11){
                                if(stristr($datos[7],"V")){
                                    if(!stristr($datos[4],"HWTC")){
                                        $puerto = $datos[1].$datos[2].$datos[3];
                                        $puerto = substr($puerto, 0, -1);
                                        $nombre = $datos[6];
                                        $version = $datos[7].' '.$datos[8];
                                        $ont = $datos[4];
                                        if(!stristr($puerto,'/')){
                                            echo ' dato erroneo 2: '.$puerto;
                                        }else{
                                        if($nombre == '245H'){
                                            $nombre = 'HG8245H';
                                        }elseif($nombre == '240'){
                                            $nombre = 'HG8240';
                                        }elseif($nombre == '245Q'){
                                            $nombre = 'HG8245Q';
                                        }elseif($nombre == '245'){
                                            $nombre = 'HG8245';
                                        }elseif($nombre == 'Loco'){
                                            $nombre = 'HG8245Q';
                                        }elseif($nombre == 'OT928G'){
                                            $nombre = 'SmartAX OT928G';
                                        }
                                                                        
                                        $query = "INSERT INTO OLT_ONT_DETALLE_2
                                                 (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                                  VALUES 
                                                 ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                        $mysqli->query($query) or die("Error query ont detalle 4.2 $query");
                                        }
                                    }else{
                                        $puerto = $datos[1].$datos[2];
                                        $puerto = substr($puerto, 0, -1);
                                        $nombre = $datos[5].' '.$datos[6];
                                        $version = $datos[7].' '.$datos[8];
                                        $ont = $datos[3];
                                        if(!stristr($puerto,'/')){
                                            echo ' dato erroneo 2: '.$puerto;
                                        }else{
                                        if($nombre == '245H'){
                                            $nombre = 'HG8245H';
                                        }elseif($nombre == '240'){
                                            $nombre = 'HG8240';
                                        }elseif($nombre == '245Q'){
                                            $nombre = 'HG8245Q';
                                        }elseif($nombre == '245'){
                                            $nombre = 'HG8245';
                                        }elseif($nombre == 'Loco'){
                                            $nombre = 'HG8245Q';
                                        }elseif($nombre == 'OT928G'){
                                            $nombre = 'SmartAX OT928G';
                                        }
                                                                        
                                        $query = "INSERT INTO OLT_ONT_DETALLE_2
                                                 (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                                  VALUES 
                                                 ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                        $mysqli->query($query) or die("Error query ont detalle 4.3 $query");
                                        }
                                    }
                                }else{
                                    if(!stristr($datos[1],"More")){
                                        $puerto = $datos[1].$datos[2].$datos[3];
                                        $puerto = substr($puerto, 0, -1);
                                        $nombre = $datos[6].' '.$datos[7];
                                        $version = $datos[8];
                                        $ont = $datos[4];
                                        if(!stristr($puerto,'/')){
                                            echo ' dato erroneo 2: '.$puerto;
                                        }else{
                                        if($nombre == '245H'){
                                            $nombre = 'HG8245H';
                                        }elseif($nombre == '240'){
                                            $nombre = 'HG8240';
                                        }elseif($nombre == '245Q'){
                                            $nombre = 'HG8245Q';
                                        }elseif($nombre == '245'){
                                            $nombre = 'HG8245';
                                        }elseif($nombre == 'Loco'){
                                            $nombre = 'HG8245Q';
                                        }elseif($nombre == 'OT928G'){
                                            $nombre = 'SmartAX OT928G';
                                        }
                                                                        
                                        $query = "INSERT INTO OLT_ONT_DETALLE_2
                                                 (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                                  VALUES 
                                                 ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                        $mysqli->query($query) or die("Error query ont detalle 4.4 $query");
                                        }
                                    }
                                }
                            }elseif(count($datos) == 12){
                                if(stristr($datos[8],"V")){
                                    $puerto = $datos[1].$datos[2].$datos[3];
                                    $puerto = substr($puerto, 0, -1);
                                    $nombre = $datos[6].' '.$datos[7];
                                    $version = $datos[8].' '.$datos[9];
                                    $ont = $datos[4];
                                    if(!stristr($puerto,'/')){
                                        echo ' dato erroneo 2: '.$puerto;
                                    }else{
                                    if($nombre == '245H'){
                                        $nombre = 'HG8245H';
                                    }elseif($nombre == '240'){
                                        $nombre = 'HG8240';
                                    }elseif($nombre == '245Q'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == '245'){
                                        $nombre = 'HG8245';
                                    }elseif($nombre == 'Loco'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == 'OT928G'){
                                        $nombre = 'SmartAX OT928G';
                                    }
                                                                    
                                    $query = "INSERT INTO OLT_ONT_DETALLE_2
                                             (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                              VALUES 
                                             ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                    $mysqli->query($query) or die("Error query ont detalle 4.5 $query");
                                    }
                                }else{
                                    $puerto = $datos[1].$datos[2].$datos[3];
                                    $puerto = substr($puerto, 0, -1);
                                    $nombre = $datos[6].' '.$datos[7];
                                    $version = $datos[8];
                                    $ont = $datos[4];
                                    if(!stristr($puerto,'/')){
                                        echo ' dato erroneo 2: '.$puerto;
                                    }else{
                                    if($nombre == '245H'){
                                        $nombre = 'HG8245H';
                                    }elseif($nombre == '240'){
                                        $nombre = 'HG8240';
                                    }elseif($nombre == '245Q'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == '245'){
                                        $nombre = 'HG8245';
                                    }elseif($nombre == 'Loco'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == 'OT928G'){
                                        $nombre = 'SmartAX OT928G';
                                    }
                                                                    
                                    $query = "INSERT INTO OLT_ONT_DETALLE_2
                                             (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                              VALUES 
                                             ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                    $mysqli->query($query) or die("Error query ont detalle 4.6 $query");
                                    }
                                }
                            }else{
                                if($datos[1] != 'More'){
                                    $puerto = $datos[10].$datos[11].$datos[12];
                                    $puerto = substr($puerto, 0, -1);
                                    $nombre = $datos[15];
                                    $version = $datos[16];
                                    $ont = $datos[13];
                                    if(!stristr($puerto,'/')){
                                        echo ' dato erroneo 2: '.$puerto;
                                    }else{
                                    if($nombre == '245H'){
                                        $nombre = 'HG8245H';
                                    }elseif($nombre == '240'){
                                        $nombre = 'HG8240';
                                    }elseif($nombre == '245Q'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == '245'){
                                        $nombre = 'HG8245';
                                    }elseif($nombre == 'Loco'){
                                        $nombre = 'HG8245Q';
                                    }elseif($nombre == 'OT928G'){
                                        $nombre = 'SmartAX OT928G';
                                    }
                                    
                                    $query = "INSERT INTO OLT_ONT_DETALLE_2
                                             (equipo,ip,region,puerto,ont,nombre,version,fecha) 
                                              VALUES 
                                             ('$server','$ip','$region','$puerto','$ont','$nombre','$version','$fecha')";
                                    $mysqli->query($query) or die("Error query ont detalle 4.7 $query");
                                    }
                                }
                            }
                        }
                    }
                    break;
                }  
            }
            //unset($data);
        }
        
    }
    
    unset($data);
    unset($array_1);
    unset($array_2);
    mysqli_close($mysqli);

function estado_equipo($server)
{
    $i=0;
    $cantConfig = 0;
    $contadorLogin=0;
    $contadorPass=0;
    $server = $server;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout( 180);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada (antes se usaba sin definir -> Warning)
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
                if ($contadorPass==0) {
                    expect_send($stream, $pass . "\n");
                    $contadorPass++;
                }
                break;
            case SALIR:
                expect_send($stream, "y\n");
                $uname .= $match[0];
                sleep(2);
                $x = false;
                return $uname;
                break;
            case USER:
                if ($contadorLogin==0) {
                    expect_send($stream, $user . "\n");
                    $contadorLogin++;
                }
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
                if($cantConfig==0){
                    expect_send($stream, "config\n");
                    sleep(1);
                }else{
                    sleep(2);
                    expect_send($stream, "quit\n");
                    sleep(2);
                }
                $cantConfig++;
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    expect_send($stream, "display ont version 0 all\n");
                    sleep(2);
                }
                if ($i==1) {
                    expect_send($stream, "display ont info 0 all\n");  
                    sleep(2);
                }
                if ($i==2) {
                    sleep(2);
                    expect_send($stream, "quit\n");
                    sleep(2);
                }
                ++$i;
                break;
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                echo "Fin por Time Out.";
                $x = false;
                return $uname;
                break;
            case ESPACIO:
                
                expect_send($stream, " ");
                sleep(1);
                $uname .= $match[0];
                break;
            case ESPACIO2:
                
                expect_send($stream, "\n");
                sleep(1);
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
    expect_set_timeout( 180);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $server);
    $b = 0; // Migración PHP 8.0: inicializada (antes se usaba sin definir -> Warning)
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
            array("{ <cr>|detail<K>|list<K> }:", ESPACIO3,EXP_EXACT),
            array("{ lock<K> }:", ESPACIO2,EXP_EXACT),
            array("{ lock<K>|unlock<K> }:", ESPACIO2,EXP_EXACT),
            ), $match))
        {
            case PASSWORD:
                expect_send($stream, $pass . "\n");
                break;
            case SALIR:
                expect_send($stream, "y\n");
                $uname .= $match[0];
                sleep(2);
                $x = false;
                return $uname;
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
                if($cantConfig==0){
                    expect_send($stream, "config\n");
                    sleep(2);
                }else{
                    expect_send($stream, "quit\n");
                    sleep(2);
                }
                $cantConfig++;
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    expect_send($stream, "display ont version 0 all\n");
                    sleep(2);
                }
                if ($i==1) {
                    expect_send($stream, "display ont info 0 all\n");
                    sleep(2);
                }
                if ($i==2) {
                    sleep(2);
                    expect_send($stream, "quit\n");
                    sleep(2);
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
function parsear($data,$ip,$server,$region,$fecha){
    global $mysqli;
    $ahora = date("Y-m-d H:i:s");
    $server = $server;
    $ip = $ip;
    $region = $region;
    $fecha=$fecha;
    $grep = array();
    $grep = preg_grep("/.the total of ONTs./", $data);
    $grep = array_values($grep);
    print_r($grep);
    if($grep){
        echo " Entro IF ";
        foreach($grep as $valor){
            $puerto = explode(',',$valor);
            $puerto = explode(' ',$puerto[0]);
            $puerto = array_filter($puerto, 'strlen');
            $puerto = array_values($puerto);
            
            if(!$puerto[3]){
                $port = $puerto[2];
            }elseif(!$puerto[4]){
                $port = $puerto[2].$puerto[3];
            }else{
                $port = $puerto[12].$puerto[13];
            }
        
            $ont_fila = explode(':',$valor);
            $ont_online = trim($ont_fila[2]);
            $ont_fila = explode(',',$ont_fila[1]);
            $ont_total = trim($ont_fila[0]);
            
            $sql_insert = "INSERT INTO OLT_ONT_EQUIPOS3 (server,ip,region,ont_online,ont_total,puerto,fecha) 
                VALUES ('$server','$ip','$region','$ont_online','$ont_total','$port','$fecha')";
            $mysqli->query($sql_insert) or die("Error query ont detalle 5.0 $sql_insert");
            
            $ont_online = '';
            $ont_total = '';
            $port = '';       
        }
    }else{
        $sql_insert = "INSERT INTO OLT_ONT_EQUIPOS3 (server,ip,region,ont_online,ont_total,puerto,fecha) 
                VALUES ('$server','$ip','$region','0','0','-','$fecha')";
            $mysqli->query($sql_insert) or die("Error query ont detalle 5.1 $sql_insert");
            
            $ont_online = '';
            $ont_total = '';
            $port = ''; 
    }
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

?>