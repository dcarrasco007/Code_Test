<?php
ini_set("log_errors", 1);
ini_set("error_log","../control_error.log");
set_time_limit(50000000000);
ini_set('memory_limit', -1);
include ('../../conexion/conexion_db.php');

echo "Parte 1";
include '/var/www/html/OLT/crontab127/OLT/js/PHPExcel/Classes/PHPExcel/IOFactory.php';
echo "Parte 2";
  //$nombreArchivo=trim($_REQUEST['name_xls']);
    $inputFileName='../ONT/Archivo/Informacion_ONT_W-49_02-12-2024.xlsx';
    //$inputFileName='../ONT/Archivo/'.trim($_REQUEST['name_xls']);
    $objReaderDetect = PHPExcel_IOFactory::identify($inputFileName); // xls o xlsx ?
    echo "Parte 3";
    $objReader = PHPExcel_IOFactory::createReader($objReaderDetect); // cargo dependiendo la extension
    echo "Parte 4";
    $objPHPExcel = $objReader->load($inputFileName); // carga documento xls
    echo "Parte 5";
    $read=$objPHPExcel->getSheet(0);
    echo "Parte 6";
$highestColumm = $objPHPExcel->setActiveSheetIndex(0)->getHighestColumn(); //columnas
echo "Parte 7";
$highestRow = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow(); //filas
echo "Parte 8";
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
echo "Parte 9";
$delete=$mysqli->query("TRUNCATE TABLE OLT_INFO_ONT_PRUEBA");


$entra=0;

if(($read->getCell('B1')=='EQUIPO') && ($read->getCell('C1')=='FN') && ($read->getCell('D1')=='SN') && ($read->getCell('E1')=='PN') && ($read->getCell('F1')=='ONU ID') && ($read->getCell('G1')=='NAME') && ($read->getCell('H1')=='ALIAS') && ($read->getCell('I1')=='SERIAL NUMBER') && ($read->getCell('J1')=='LINE PROFILE')){
 /*  echo "estoyaca"; */
  for ($i=2; $i<=$highestRow; $i++) { 
    $var++;
    $equipo=$read->getCell('B'.$i);
    $FN=$read->getCell('C'.$i);
    $SN=$read->getCell('D'.$i);
    $PN=$read->getCell('E'.$i);
    $ONU=$read->getCell('F'.$i);
    $NAME=$read->getCell('G'.$i);
    $SERIAL=$read->getCell('H'.$i);
    $ALIAS=$read->getCell('I'.$i);
    $LINE_PROFILE=$read->getCell('J'.$i);
    $data[]='("'.$equipo.'","'.$FN.'","'.$SN.'","'.$PN.'","'.$ONU.'","'.$NAME.'","'.$SERIAL.'","'.$ALIAS.'","'.$LINE_PROFILE.'")';
    if($var==1000){
        $var=0;
        $inserta="INSERT INTO OLT_INFO_ONT_PRUEBA (equipo,fn,sn,pn,onu,name,alias,serial_number,line_profile) 
        VALUES".implode(",",$data);
        $insert=$mysqli->query($inserta);
        unset($data);
    }
  }
  if($data!=''){
    $var=0;
    $inserta="INSERT INTO OLT_INFO_ONT_PRUEBA (equipo,fn,sn,pn,onu,name,alias,serial_number,line_profile) 
    VALUES".implode(",",$data);
    $insert=$mysqli->query($inserta);
    unset($data);
  }
  $data="OK";
  echo "OK";  
  $entra=1;
}else{
  echo 'Formato no coincide';
  $data="El Formato no Coincide";
}

//concatenar los values de a 10 mil para 1 insert
$entra=1;
//$nombreArchivo=trim('Informacion_TEST_ONT_W-47_21-11-2023.xlsx');
//$fecha=date('d-m-Y');
/* if ($entra==1) {
  $nombreArchivo=trim('Informacion_TEST_ONT_W-47_21-11-2023.xlsx');
  $nombreArchivo=trim($_REQUEST['name_xls']);
  //$nombreArchivo=$nombreArchivo.'_'.$fecha;
  $localFile = "/var/www/html/OLT/ONT/Archivo/$nombreArchivo"; // Ruta del archivo a generar
  //SFTP
  //$localFile='/var/www/html/OLT/Ocupacion/prueba.txt';
  $remoteFile="/stage/outbox/shbi/dataquality/input/aaa/$nombreArchivo";
  $host = "10.51.17.37";
  $port = 22;
  $user = "oymredes";
  $pass = 'tSDe$m4aS8!S(2';
  
  $connection = ssh2_connect($host, $port);
  ssh2_auth_password($connection, $user, $pass);
  $sftp = ssh2_sftp($connection);
  
  $stream = fopen("ssh2.sftp:/$sftp$remoteFile", 'w');
  $file = file_get_contents($localFile);
  fwrite($stream, $file);
  fclose($stream);
  } */

  //NO CONECTA  AL SERVIDOR
/* 
  $connection = ssh2_connect('10.51.17.37', 22); // Reemplaza 'hostname' con tu dirección IP o nombre de host SSH
  ssh2_auth_password($connection, 'oymredes', 'tSDe$m4aS8!S(2'); // Reemplaza 'username' y 'password' con tus credenciales SSH
  
  $localFile = "/var/www/html/OLT/ONT/Archivo/$nombreArchivo"; // Ruta al archivo local que deseas cargar
  $remoteFile = "/stage/outbox/shbi/dataquality/input/OYMREDES/$nombreArchivo"; // Ruta al archivo remoto donde deseas cargar el archivo local
  
  if (!file_exists($localFile)) {
    die('El archivo local no existe.');
}

if (!ssh2_scp_send($connection, $localFile, $remoteFile, 0644)) {
    $error = error_get_last();
    echo "Error al cargar el archivo en el servidor remoto: " . $error['message'];
} else {
    echo 'El archivo se ha cargado correctamente en el servidor remoto.';
}
print_r($error); */



/* if ($entra==1) {
$nombreArchivo=trim($_REQUEST['name_xls']);
//$nombreArchivo=$nombreArchivo.'_'.$fecha;
$localFile = "/var/www/html/OLT/ONT/Archivo/$nombreArchivo"; // Ruta del archivo a generar
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
} */

?>