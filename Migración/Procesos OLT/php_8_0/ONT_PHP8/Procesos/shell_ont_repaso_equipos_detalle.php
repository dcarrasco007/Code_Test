<?php
require_once __DIR__ . '/../expect_compat.php'; // Reemplazo en PHP puro de la extensión PECL 'expect' (no soportada en PHP 8)
// --- Migración a PHP 8.0: constantes bareword para los switch de expect_expectl ---
foreach (['USER','PASSWORD','SALTO','SHELL','SHELL_CONFIG','SHELL_CONFIG1','SHELL2','SALIR','LOGOUT','LOGOUT2','ESPACIO','ESPACIO2','ESPACIO3','EXP_EXACT','EXP_REGEXP','EXP_TIMEOUT','EXP_EOF','ACEPTAR'] as $__const) {
    if (!defined($__const)) { define($__const, $__const); }
}
include ('/u01/crontab127/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//$fecha = date("Y-m-d");
//$fecha=date('d-m-Y');
$fecha_actual = date("d-m-Y");
//sumo 1 día
    $fecha= date("d-m-Y",strtotime($fecha_actual."+ 1 days")); 
$sql_ip = "SELECT
            ip,
            server,
            region 
            FROM
            OLT_SERVER A1 
            WHERE
            not EXISTS (
            SELECT
                NULL
            FROM
                OLT_ONT_DETALLE_2 A2
            WHERE 
                A2.equipo =A1.server 
            GROUP BY
                equipo)";
/*
$sql_ip="SELECT
ip,
server,
region
FROM
OLT_SERVER A1
WHERE
A1.server ='OLT-CALLEJONDELOSPERROS2PCS-1'"; */
                
$result_ip = $mysqli->query($sql_ip) or die("error $sql_ip");

while ($row = $result_ip->fetch_array(MYSQLI_NUM)){  
    
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
        
    //$ip = '10.99.29.2';
    //$server = 'OLT-MCCDLVALLEPCS-1';
    //$region = 'XIII';
    
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){      
            if($server == 'OLT-DURZUA-4'){
                $texto = obtener_ont($ip);
                //echo "parte1";
            }else{
                
                $texto = obtener_ont($ip);
                //echo "parte2";
            }
            //print_r($texto);
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = preg_replace('/[\n|\r|\n\r]/', '', $linea); // Migración PHP 8.0: eregi_replace() eliminada en PHP 7;
                $data[] = $linea;
            }
            $cantidad22=count($data);
            for($j=0;$j<=count($data);$j++){
                $line = preg_replace('/\s+/', ' ', $data[$j]);
                if(stristr($line,'F/S/P/ONT-ID')){
                    for($k=$j+3;$k<=count($data);$k++){
                        $line2 = preg_replace('/\s+/', ' ', $data[$k]);
                        $datos = explode(' ',$line2);
                        $cantidad=count($datos);
                        //echo 'cantidad: '.$cantidad;
                        
                        if(count($datos) == 10){
                            if(!stristr($datos[1],'ALARM') && !stristr($datos[1],'PARAMETERS') && !stristr($datos[1],'NAME') && !stristr($datos[2],'Equipment') && !stristr($datos[1],'data')){
                                if(!stristr($datos[4],'HWTC')){
                                    $puerto = $datos[1].$datos[2].$datos[3];
                                    $puerto = substr($puerto, 0, -1);
                                    $nombre = $datos[6];
                                    $version = $datos[7];
                                    $ont = $datos[4];
                                    if(!stristr($puerto,'/')){
                                        echo ' dato erroneo 1: '.$puerto;
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
                                        //echo ' '.$puerto.' '.$nombre.' '.$version.' '.$ont;
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
                                    echo ' dato erroneo 3: '.$puerto;
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
                                    echo ' dato erroneo 4: '.$puerto;
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
                                    echo ' dato erroneo 5: '.$puerto;
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
                                    echo ' dato erroneo 6: '.$puerto;
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
                                            echo ' dato erroneo 7: '.$puerto;
                                        }else{
                                        if($nombre == '245H'){
                                            $nombre = 'HG8245H';
                                        }elseif($nombre == '240'){
                                            $nombre = 'HG8240';
                                        }elseif($nombre == '245Q'){
                                            $nombre = 'HG8245Q';
                                        }elseif($nombre == '245'){
                                            $nombre = 'HG8245';
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
                                            echo ' dato erroneo 8: '.$puerto;
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
                                            echo ' dato erroneo 9: '.$puerto;
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
                                        echo ' dato erroneo 10: '.$puerto;
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
                                        echo ' dato erroneo 11: '.$puerto;
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
                                        echo ' dato erroneo 12: '.$puerto;
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
        }
    }
    unset($data);
}
mysqli_close($mysqli);
function obtener_ont($ip)
{
    $i=0;
    $a=0;
    $b=0;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout( 30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $ip);
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
            array(".config.",SHELL_CONFIG,EXP_REGEXP),
            array("^OLT.*.#",SHELL2,EXP_REGEXP),
            array("^Are you sure to log out.",LOGOUT,EXP_REGEXP),
            array(".Check whether system data has been changed. Please save data before logout. Are you sure to log out?.",LOGOUT2,EXP_REGEXP),
            array(".Check whether system data has been changed. Please save data before logout. Are you sure to log out?.",LOGOUT2,EXP_EXACT),
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
                    sleep(1);
                    $b++;
                }elseif($b == 1){
                    expect_send($stream, "\n");
                }
                break;
            case SHELL2:
                if ($i == 0) {
                    expect_send($stream, "config\n");
                    sleep(1);
                    $i++;
                } elseif ($i == 1) {
                    expect_send($stream, "quit\n");
                    $i++;     
                }
                break;
            case SHELL_CONFIG:
                if ($a == 0) {
                    //expect_send($stream, "\n");
                    $a++;
                    expect_send($stream, "display ont version 0 all\n");
                    sleep(1);
                } else {
                    expect_send($stream, "quit\n");    
                }
                break;
            case LOGOUT:
                expect_send($stream, "y\n");
                $x = false;
                fclose($stream);
                return $uname;
                unset($uname);
                break;
            case LOGOUT2:
                expect_send($stream, "y\n");
                $x = false;
                fclose($stream);
                return $uname;
                unset($uname);
                break;
            case SALTO:
                $uname .= $match[0];
                break;
            case EXP_TIMEOUT:
                $x = false;
                fclose($stream);
                return $uname;
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

function obtener_ont2($ip)
{
    $i=0;
    $a=0;
    $b=0;
    $user = 'geret2016';
    $pass = 'Geret#2016*2021';
    expect_set_timeout( 30);
    ini_set('memory_limit', '-1');
    $stream = expect_popen("telnet " . $ip);
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
            array("^Are you sure to log out.",LOGOUT,EXP_REGEXP),
            array(".Check whether system data has been changed. Please save data before logout. Are you sure to log out?.",LOGOUT2,EXP_REGEXP),
            array(".Check whether system data has been changed. Please save data before logout. Are you sure to log out?.",LOGOUT2,EXP_EXACT),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
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
                }elseif($b == 1){
                    expect_send($stream, "\n");
                }$b++;
                break;
            case SHELL2:
                if ($i == 0) {
                    expect_send($stream, "config\n");
                } 
                if ($i == 2) {
                    expect_send($stream, "quit\n");    
                }
                $i++;
                break;
            case SHELL_CONFIG:
                if ($a == 0) {
                    expect_send($stream, "display ont version 0 all\n");
                    $a++;
                } else {
                    expect_send($stream, "quit\n");    
                }
                break;
            case LOGOUT:
                expect_send($stream, "y\n");
                $x = false;
                fclose($stream);
                return $uname;
                unset($uname);
                break;
            case LOGOUT2:
                expect_send($stream, "y\n");
                $x = false;
                fclose($stream);
                return $uname;
                unset($uname);
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