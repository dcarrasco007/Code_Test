<?php
include ('/u01/crontab127/conexion/conexion_db.php');
echo $hora_inicio=date("H:i:s");
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
//-----------MONITOREO
$proceso_id=17;//ID DEL PROCESO YA REGISTRADO
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
echo"----- ELIMINACIÓN DE DATA----\n";
mysqli_query($mysqli,"TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE_COMPLETO_EQUIFIBER");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE_COMPLETO_ONNET");

echo"------CARGA EQUIFIBER-----\n";

$query="INSERT INTO Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO_EQUIFIBER
(equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name, onu_alias, sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time, distancia, line_profile_name, service_profile_name, fecha_registo, modelo,version_sw)
SELECT OLT_INFORMACION_ONT_DETALLE_COMPLETO.equipo, OLT_INFORMACION_ONT_DETALLE_COMPLETO.ip, OLT_INFORMACION_ONT_DETALLE_COMPLETO.frame_id, OLT_INFORMACION_ONT_DETALLE_COMPLETO.slot_id, OLT_INFORMACION_ONT_DETALLE_COMPLETO.port_id,
OLT_INFORMACION_ONT_DETALLE_COMPLETO.onu_id, OLT_INFORMACION_ONT_DETALLE_COMPLETO.estado, OLT_INFORMACION_ONT_DETALLE_COMPLETO.region, OLT_INFORMACION_ONT_DETALLE_COMPLETO.comuna, OLT_INFORMACION_ONT_DETALLE_COMPLETO.onu_name,
OLT_INFORMACION_ONT_DETALLE_COMPLETO.onu_alias, OLT_INFORMACION_ONT_DETALLE_COMPLETO.sn_mac, OLT_INFORMACION_ONT_DETALLE_COMPLETO.olt_rx_onu, OLT_INFORMACION_ONT_DETALLE_COMPLETO.tx_optical_power, OLT_INFORMACION_ONT_DETALLE_COMPLETO.rx_optical_power, 
OLT_INFORMACION_ONT_DETALLE_COMPLETO.voltaje, OLT_INFORMACION_ONT_DETALLE_COMPLETO.temperature, OLT_INFORMACION_ONT_DETALLE_COMPLETO.last_up_time, OLT_INFORMACION_ONT_DETALLE_COMPLETO.last_down_time, OLT_INFORMACION_ONT_DETALLE_COMPLETO.distancia, 
OLT_INFORMACION_ONT_DETALLE_COMPLETO.line_profile_name, OLT_INFORMACION_ONT_DETALLE_COMPLETO.service_profile_name, OLT_INFORMACION_ONT_DETALLE_COMPLETO.fecha_registo,OLT_INFORMACION_ONT_DETALLE_COMPLETO.modelo,OLT_INFORMACION_ONT_DETALLE_COMPLETO.version_sw
FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO INNER JOIN OLT_SERVER_V2 ON OLT_INFORMACION_ONT_DETALLE_COMPLETO.equipo=OLT_SERVER_V2.server;";
                $result=$mysqli->query($query) or die("Error query ont detalle 1 $query");

echo"------CARGA ONNET-----\n";

$query2="INSERT INTO Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO_ONNET
(equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name, onu_alias, sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time, distancia, line_profile_name, service_profile_name, fecha_registo, modelo,version_sw)
SELECT OLT_INFORMACION_ONT_DETALLE_COMPLETO.equipo, OLT_INFORMACION_ONT_DETALLE_COMPLETO.ip, OLT_INFORMACION_ONT_DETALLE_COMPLETO.frame_id, OLT_INFORMACION_ONT_DETALLE_COMPLETO.slot_id, OLT_INFORMACION_ONT_DETALLE_COMPLETO.port_id,
OLT_INFORMACION_ONT_DETALLE_COMPLETO.onu_id, OLT_INFORMACION_ONT_DETALLE_COMPLETO.estado, OLT_INFORMACION_ONT_DETALLE_COMPLETO.region, OLT_INFORMACION_ONT_DETALLE_COMPLETO.comuna, OLT_INFORMACION_ONT_DETALLE_COMPLETO.onu_name,
OLT_INFORMACION_ONT_DETALLE_COMPLETO.onu_alias, OLT_INFORMACION_ONT_DETALLE_COMPLETO.sn_mac, OLT_INFORMACION_ONT_DETALLE_COMPLETO.olt_rx_onu, OLT_INFORMACION_ONT_DETALLE_COMPLETO.tx_optical_power, OLT_INFORMACION_ONT_DETALLE_COMPLETO.rx_optical_power, 
OLT_INFORMACION_ONT_DETALLE_COMPLETO.voltaje, OLT_INFORMACION_ONT_DETALLE_COMPLETO.temperature, OLT_INFORMACION_ONT_DETALLE_COMPLETO.last_up_time, OLT_INFORMACION_ONT_DETALLE_COMPLETO.last_down_time, OLT_INFORMACION_ONT_DETALLE_COMPLETO.distancia, 
OLT_INFORMACION_ONT_DETALLE_COMPLETO.line_profile_name, OLT_INFORMACION_ONT_DETALLE_COMPLETO.service_profile_name, OLT_INFORMACION_ONT_DETALLE_COMPLETO.fecha_registo,OLT_INFORMACION_ONT_DETALLE_COMPLETO.modelo,OLT_INFORMACION_ONT_DETALLE_COMPLETO.version_sw 
FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO INNER JOIN OLT_SERVER_ONNET ON OLT_INFORMACION_ONT_DETALLE_COMPLETO.equipo=OLT_SERVER_ONNET.server;";
                $result=$mysqli->query($query2) or die("Error query ont detalle 1 $query2");
                
echo"------FIN CARGAS-----\n";
echo"------INICIO CARGA RESPALDO-----\n";
$queryRespaldo="INSERT INTO Aden.OLT_ONT_RESPALDO_DIARIO
(equipo,ip,online,total,semana,fecha_registro)
SELECT T1.`server`, T1.ip,
(SELECT COUNT(OLT_INFORMACION_ONT_DETALLE_COMPLETO.id) AS CANTIDAD FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO WHERE OLT_INFORMACION_ONT_DETALLE_COMPLETO.estado= 'online' AND OLT_INFORMACION_ONT_DETALLE_COMPLETO.equipo=T1.server)as online,
(SELECT COUNT(OLT_INFORMACION_ONT_DETALLE_COMPLETO.id) AS CANTIDAD FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO WHERE OLT_INFORMACION_ONT_DETALLE_COMPLETO.equipo=T1.server) AS Total,
WEEK(NOW(), 3) AS SEMANA,
DATE_FORMAT(NOW(), '%Y-%m-%d %H:%i:%s') AS FECHA
FROM
OLT_SERVER T1";
$result=$mysqli->query($queryRespaldo) or die("Error query ont detalle 1 $queryRespaldo");
sleep(10);
//-----------MONITOREO FIN
$fecha_monitor=date('Y-m-d H:i:s');
$sql = "UPDATE MONITOREO_PROCESOS_EJECUCIONES
SET 
    fecha_fin = '$fecha_monitor',
    duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, '$fecha_monitor'),
    estado = 'OK',
    cantidad_subprocesos=0
WHERE id = $parent_id";

if (!$mysqli->query($sql)) {
    die("Error update padre: " . $mysqli->error);
}
//---------------------------------
$mysqli->close();
echo"------FIN CARGA RESPALDO-----\n";
echo $hora_fin=date("H:i:s");
?>