<?php
//include ('/var/www/html/conexion/conexion_db.php');
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
$ayer= date('Y-m-d', strtotime('-1 day'));
$mes= date('Y-m-d', strtotime('-30 day'));
echo "Carga de datos: ".$ayer." eliminacion de datos: ".$mes." || ";
$sql_ip3="SELECT * FROM OLT_VLAN_TRAFICO WHERE DATE(fecha_hora)= '$ayer'";
$sql_eliminacion="DELETE FROM OLT_VLAN_TRAFICO_MES WHERE DATE(fecha_hora)= '$mes'";
$sql_eliminacion = $mysqli->query($sql_eliminacion) or die("error eliminacion");
$var=0;
$ciclos=0;

    $data1 = $mysqli->query($sql_ip3) or die("error 1");
    //$row = $olt->fetch_array(MYSQLI_NUM);
    while($dc= $data1->fetch_array(MYSQLI_NUM)){
        $var++;
        $data[]='("'.$dc[1].'","'.$dc[2].'","'.$dc[3].'","'.$dc[4].'","'.$dc[5].'","'.$dc[6].'","'.$dc[7].'")';
            if($var==1000){
                $ciclos++;
                echo ' | Ciclo: '.$ciclos;
                $var=0;
                $inserta="INSERT INTO OLT_VLAN_TRAFICO_MES (equipo, ip, vlan, fecha_hora, uplink, downlink, servicio) 
                VALUES".implode(",",$data);
                $insert=$mysqli->query($inserta);
                unset($data);
            }          
    }
    if($data!=''){
        $var=0;
        $inserta="INSERT INTO OLT_VLAN_TRAFICO_MES (equipo, ip, vlan, fecha_hora, uplink, downlink, servicio) 
                VALUES".implode(",",$data);
                $insert=$mysqli->query($inserta);
                unset($data);
    }

?>