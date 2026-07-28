<?php
include ('/var/www/html/OLT/crontab127/conexion/conexion_db.php');

$conn = mysql_connect($host144_geret,$user144_geret,$pass144_geret) or die(mysql_error());
$db = mysql_select_db("Aden") or die("error de conexion");
mysql_set_charset('utf8');

//mysql_query('TRUNCATE TABLE OLT_VERSION_PARCHE_MODELO');

$fecha = date('Y-m-d');

$sql_ip = 'SELECT ip,server,region,modelo,sw,patch,tipo FROM OLT_SERVER WHERE OLT_SERVER.server NOT IN (SELECT DISTINCT OLT_VERSION_PARCHE_MODELO.equipo FROM OLT_VERSION_PARCHE_MODELO)';
$result_ip = mysql_query($sql_ip) or die ("error shell_energia.php 1 $sql_ip".mysql_error());

while ($row = mysql_fetch_array($result_ip)) {
    
    $ip = $row[0];
    $server = $row[1];
    $region = $row[2];
    
    $y = ping_ip($ip);
    if(trim($y)){
        if($y < 100){            
            $texto = estado_equipo($ip);
            foreach (explode(chr(13), $texto) as $linea)
            {
                $linea = @eregi_replace("[\n|\r|\n\r]", '', $linea);
                $data[] = $linea;
            }
            
            //print_r($data);
                        
            for($j=0;$j<count($data);$j++){
                $line = preg_replace('/\s+/', ' ', $data[$j]);
                if (!stristr($line,'Program') && !stristr($line,'Data')){
                    if (stristr($line,' VERSION :')){
                        $version = explode(':',$line);
                        $version = trim($version[1]);
                    }elseif (stristr($line,'PATCH')){
                        $parche = explode(':',$line);
                        $parche = trim($parche[1]);
                    }elseif (stristr($line,' PRODUCT :')){
                        $modelo = explode(':',$line);
                        $modelo = trim($modelo[1]);
                    }elseif (stristr($line,' Uptime is')){
                        $uptime = trim($line);
                    }  
                }
            }
            if ($uptime!='' && $version!='') {
                $query_vpm = "INSERT INTO OLT_VERSION_PARCHE_MODELO
                (equipo,ip,region,version,parche,modelo,fecha,uptime) 
                VALUES ('$server','$ip','$region','$version','$parche','$modelo','$fecha','$uptime')";
                mysql_query($query_vpm) or die ("error version_patch_modelo.php 1 $query_vpm".mysql_error());
            }
            
                     
        }
    }
    unset($data);
}
mysql_close($conn);

function estado_equipo($server)
{
    $i=0;
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
            array(".*config.*.#",SHELL_CONFIG,EXP_REGEXP),
            array("OLT.*.#",SHELL2,EXP_REGEXP),
            array("---- More ( Press 'Q' to break ) ----", ESPACIO),
            array("{ <cr>|backplane<K>|frameid/slotid<S><Length 1-15> }:", ESPACIO2,EXP_EXACT),
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
                sleep(1);
                break;
            case SHELL_CONFIG:
                if ($i==0) {
                    fwrite($stream, "display version\n");
                    sleep(1);
                }
                if ($i==1) {
                    fwrite($stream, "quit\n");  
                    fclose($stream);
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