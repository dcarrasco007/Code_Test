<?php
date_default_timezone_set('America/Santiago');
include ('/var/www/procesos/php/conexion/conexion_db.php');

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
echo "--Inicio proceso--\n";
echo date('Y-m-d H:i:s')."\n";
$sql_ip = "SELECT
        OLT_SERVER.`server`,
        OLT_SERVER.ip,
        OLT_SERVER.modelo
        FROM 
        OLT_SERVER";
$result = $mysqli->query($sql_ip) or die("error 1");

while ($row = $result->fetch_array(MYSQLI_NUM)) {
        $server=$row[0];
        $ip=$row[1];        
    shell_exec("nohup php -f /var/www/procesos/php/Ocupacion_PHP8/Procesos/proceso_trafico_pon_exped.php $server $ip > /var/www/procesos/php/Ocupacion_PHP8/Procesos/log/log$ip.log &");    
    echo $ip."\n";     
                
}

mysqli_close($mysqli);

?>