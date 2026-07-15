<?php
include ('/u01/crontab127/conexion/conexion_db.php');
echo 'Inicio del Script...';
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=15;//ID DEL PROCESO YA REGISTRADO
$fecha_monitor=date('Y-m-d H:i:s');
$sql_Monitor = "INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
(proceso_id,fecha_inicio,estado,fecha_registro,cantidad_subprocesos,cantidad_subprocesos_completados,mensaje)
VALUES
($proceso_id,'$fecha_monitor','RUNNING','$fecha_monitor',0,0,'Porceso Primario')";
if (!$mysqli->query($sql_Monitor)) {
    die("Error insert padre: " . $mysqli->error);
}
$parent_id = $mysqli->insert_id;
// el lote será el mismo id del padre
$lote_id = $parent_id;
echo"\nlote: ".$lote_id;
sleep(1);
//--------------------
mysqli_query($mysqli,"TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE_COMPLETO_OLD");
sleep(5);
mysqli_query($mysqli,"INSERT INTO Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO_OLD
                    (equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name, onu_alias, modelo,sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time, distancia, line_profile_name, service_profile_name, fecha_registo, version_sw)
SELECT equipo, ip, frame_id, slot_id, port_id, onu_id, estado, comuna, region, onu_name,onu_alias, modelo,sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time, distancia, line_profile_name, service_profile_name, fecha_registo, version_sw
FROM Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO");

sleep(5);
mysqli_query($mysqli,"TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE_COMPLETO");


$fecha = date("Y-m-d");
$nprocesos=0;
$sql_ip = "SELECT ip,server,comuna,modelo,sw,patch,tipo,region FROM OLT_SERVER WHERE
OLT_SERVER.server <> 'OLT-SBERNARDO-1'
AND OLT_SERVER.server <> 'OLT-CNT-2'
AND OLT_SERVER.server <> 'OLT-VALPARAISO-1'
AND OLT_SERVER.server <> 'OLT-RECREO-2' 
AND OLT_SERVER.server <> 'OLT1-1-LABONNET-5800'
AND OLT_SERVER.server <> 'OLT1-2-LABONNET-5600'
AND OLT_SERVER.server <> 'OLT-VITACURA-1'";
/* $sql_ip = "SELECT ip,server,comuna,modelo,sw,patch,tipo,region FROM OLT_SERVER WHERE
OLT_SERVER.server IN ('OLT-PARQUETITANIUM-2','OLT-SIMONBOLIVARPCS-3','OLT-HERNANCIUDADPCS-2','OLT-HERNANCIUDADPCS-3','OLT-NVA13NORTE-4','OLT-PENCOPCS-2')"; */
$result = $mysqli->query($sql_ip) or die("error $sql_ip");
$contador=0;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $nprocesos++;
    //sleep(1);
    $ip = $row[0];
    $server = $row[1];
    $modelo = $row[3];
    $comuna = $row[2];
    $region = $row[7];
    $region = str_replace(' ','_',$region);
    $comuna = str_replace(' ','_',$comuna);
    shell_exec("nohup php -f /u01/crontab127/OLT/ONT/proceso_data_V4.php $ip $server $modelo $region $comuna $lote_id > /u01/crontab127/OLT/ONT/logs/informacion_ont/$ip.log &");

}
//-----------MONITOREO FIN
$fecha_monitor=date('Y-m-d H:i:s');
$sql = "UPDATE MONITOREO_PROCESOS_EJECUCIONES
SET 
    fecha_fin = '$fecha_monitor',
    duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, '$fecha_monitor'),
    estado = 'OK',
    cantidad_subprocesos=$nprocesos
WHERE id = $parent_id";

if (!$mysqli->query($sql)) {
    die("Error update padre: " . $mysqli->error);
}
//---------------------------------
mysqli_close($mysqli);
echo 'Fin Script.';   
?>