<?php
ini_set("memory_limit", "2048M");
set_time_limit(10000000);

include ('../../../conexion/conexion_db.php');

$conn = mysql_connect($host144_geret,$user144_geret,$pass144_geret) or die(mysql_error());
$db = mysql_select_db("Aden") or die("error de conexion");

$query = "SELECT equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name,modelo, sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time, distancia, line_profile_name, service_profile_name, vas_profile, fecha_registo
FROM Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO;";
$result = mysql_query($query) or die ("error 1 export_base.php $query");

$tabla2 .= "EQUIPO;IP;FRAME_ID;SLOT_ID;PORT_ID;ONU_ID;ESTADO;REGION;COMUNA;ONU_NAME;TERMINAL_TYPE;SN_MAC;OLT_RX_ONU;TX_OPTICAL_POWER;RX_OPTICAL_POWER;VOLTAJE;TEMPERATURE;LAST_UP_TIME;LAST_DOWN_TIME;DISTANCIA;LINE_PROFILE_NAME;SERVICE_PROFILE_NAME;VAS_PROFILE;FECHA_REGISTRO\n";          
                
while ($row = mysql_fetch_array($result)) {
    
    $tabla2 .= $row[0].';'.$row[1].';'.$row[2].';'.$row[3].';'.$row[4].';'.$row[5].';'.$row[6].';'.$row[7].';'.$row[8].';'.$row[9].';'.$row[10].';'.$row[11].';'.$row[12].';'.$row[13].';'.$row[14].';'.$row[15].';'.$row[16].';'.$row[17].';'.$row[18].';'.$row[19].';'.$row[20].';'.$row[21].';'.$row[22].';'.$row[23]."\n";

}

header("Content-Description: File Transfer");
header("Content-Type: application/force-download");
header("Content-Disposition: attachment; filename=Export_Info_ONT.csv");
echo $tabla2;
 

?>