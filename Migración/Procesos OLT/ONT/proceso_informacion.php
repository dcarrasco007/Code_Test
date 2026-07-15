<?php
include ('../../conexion/conexion_db.php');
echo 'Inicio del Script...';
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

mysqli_query($mysqli,"TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE");


$fecha = date("Y-m-d");

$sql_ip = "SELECT ip,server,comuna,modelo,sw,patch,tipo FROM OLT_SERVER WHERE
OLT_SERVER.server <> 'OLT-SBERNARDO-1'
AND OLT_SERVER.server <> 'OLT-CNT-2'
AND OLT_SERVER.server <> 'OLT-VALPARAISO-1'
AND OLT_SERVER.server <> 'OLT-RECREO-2'";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    
    //sleep(1);
    $ip = $row[0];
    $server = $row[1];
    $modelo = $row[3];
    $region = $row[2];
    $region = str_replace(' ','_',$region);
    shell_exec("nohup php -f /var/www/html/OLT/crontab127/OLT/ONT/proceso_data.php $ip $server $modelo $region> /var/www/html/OLT/crontab127/OLT/ONT/logs/informacion_ont/$ip.log &");

}
//creamos el archivo csv
sleep(100);
$query = "SELECT equipo, modelo, ip, comuna, puerta, id_ont, rx_power, distance, sn, cs, fecha
FROM Aden.OLT_INFORMACION_ONT_DETALLE";
$result2 = $mysqli->query($query) or die("error $query");

$tabla2 .= "EQUIPO;MODELO;IP;COMUNA;PUERTA;ID_ONT;RX_POWER;DISTANCE;SN;CS;FECHA\n";          
                
while ($row2 = $result2->fetch_array(MYSQLI_NUM)) {
    
    $tabla2 .= $row2[0].';'.$row2[1].';'.$row2[2].';'.$row2[3].';'.$row2[4].';'.$row2[5].';'.$row2[6].';'.$row2[7].';'.$row2[8].';'.$row2[9].';'.$row2[10]."\n";

}
$nombreArchivo="Reporte_ONT__$fecha.csv";
$sfile = "/var/www/html/OLT/ONT/Informacion_ONT/Archivos/$nombreArchivo"; // Ruta del archivo a generar
$fp = fopen($sfile, "w");
fwrite($fp, $tabla2);
fclose($fp);
//enviar archivo a ftp

//$nombreArchivo=$nombreArchivo.'_'.$fecha;
$localFile = "/var/www/html/OLT/ONT/Informacion_ONT/Archivos/$nombreArchivo"; // Ruta del archivo a generar
//SFTP
//$localFile='/var/www/html/OLT/Ocupacion/prueba.txt';
$remoteFile="/archivos/$nombreArchivo";
$host = "172.29.151.101";
$port = 22;
$user = "estadisticasGpon";
$pass = "sTsGp0n.,!23";

$connection = ssh2_connect($host, $port);
ssh2_auth_password($connection, $user, $pass);
$sftp = ssh2_sftp($connection);

$stream = fopen("ssh2.sftp://$sftp$remoteFile", 'w');
$file = file_get_contents($localFile);
fwrite($stream, $file);
fclose($stream);
mysqli_close($mysqli);
echo 'Fin Script.';   
?>