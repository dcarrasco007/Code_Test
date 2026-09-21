<?php
// Reporte: ONUs con SN/MAC registrada en mas de un puerto.
// Consulta unica con GROUP_CONCAT (evita el patron N+1: una sola query
// para los 100+ grupos de duplicados, en vez de 1 SELECT por SN/MAC).
include ('../../../conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli->set_charset("utf8");

$mysqli->query("SET SESSION group_concat_max_len = 1000000");

$sql = "SELECT sn_mac, COUNT(*) AS veces,
        GROUP_CONCAT(onu_name ORDER BY id SEPARATOR ' | ') AS onu_names,
        GROUP_CONCAT(CONCAT(equipo,' F',frame_id,'/S',slot_id,'/P',port_id) ORDER BY id SEPARATOR ' | ') AS puertos,
        GROUP_CONCAT(equipo ORDER BY id SEPARATOR ' | ') AS olts,
        GROUP_CONCAT(estado ORDER BY id SEPARATOR ' | ') AS estados_run,
        GROUP_CONCAT(DISTINCT line_profile_name ORDER BY line_profile_name SEPARATOR ' | ') AS profiles,
        GROUP_CONCAT(DISTINCT modelo ORDER BY modelo SEPARATOR ' | ') AS terminales
        FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO
        GROUP BY sn_mac
        HAVING COUNT(*) > 1
        ORDER BY sn_mac ASC";
$result = $mysqli->query($sql) or die("Error consulta MAC duplicadas: " . $mysqli->error);

$filas = array();
$n_online = 0;
$n_mixto = 0;
$n_offline = 0;

while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
    $estados = explode(' | ', $row['estados_run']);
    $hayOnline = false;
    $hayOffline = false;
    foreach ($estados as $e) {
        if ($e === 'online') { $hayOnline = true; }
        if ($e === 'offline') { $hayOffline = true; }
    }
    if ($hayOnline && !$hayOffline) {
        $clase = 'online';
        $color = '#C6EFCE';
        $n_online++;
    } elseif ($hayOnline && $hayOffline) {
        $clase = 'mixto';
        $color = '#FFD966';
        $n_mixto++;
    } else {
        $clase = 'offline';
        $color = '#FFCCCC';
        $n_offline++;
    }

    $estadosDisplay = str_replace(array('online', 'offline'), array('Online', 'Offline'), $row['estados_run']);

    $filas[] = array(
        'sn_mac'     => $row['sn_mac'],
        'veces'      => $row['veces'],
        'onu_names'  => $row['onu_names'],
        'puertos'    => $row['puertos'],
        'olts'       => $row['olts'],
        'estados'    => $estadosDisplay,
        'profiles'   => $row['profiles'],
        'terminales' => $row['terminales'],
        'color'      => $color,
        'clase'      => $clase,
    );
}
$mysqli->close();

$total = count($filas);
$fechaHoy = date('d/m/Y');

$tabla = "<div><strong>ONUs con SN/MAC en m&aacute;s de un puerto &mdash; Access $fechaHoy</strong></div>";
$tabla .= "<div>Total: $total SN/MAC en 2+ puertos &nbsp;|&nbsp; "
        . "<span style='color:#2e7d32'>&#128994; Solo Online: $n_online</span>&nbsp; "
        . "<span style='color:#f9a825'>&#128993; Mixto: $n_mixto</span>&nbsp; "
        . "<span style='color:#c62828'>&#128308; Solo Offline: $n_offline</span></div><br>";

$tabla .= "<table align='center' id='tblMacDup' class='table table-bordered table-striped' border='1' width='100%'>
        <thead>
        <tr>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>SN/MAC</center></th>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>Veces</center></th>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>ONU Name(s)</center></th>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>Puertos</center></th>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>OLTs</center></th>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>Estado Run</center></th>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>Line Profile(s)</center></th>
            <th bgcolor='#2E75B6' style='color:#FFFFFF;'><center>Terminal Type</center></th>
        </tr>
        </thead>
        <tbody>";

foreach ($filas as $f) {
    $tabla .= "<tr style='background-color:" . $f['color'] . " !important;'>";
    $tabla .= "<td>" . $f['sn_mac'] . "</td>";
    $tabla .= "<td><center>" . $f['veces'] . "</center></td>";
    $tabla .= "<td>" . $f['onu_names'] . "</td>";
    $tabla .= "<td>" . $f['puertos'] . "</td>";
    $tabla .= "<td>" . $f['olts'] . "</td>";
    $tabla .= "<td>" . $f['estados'] . "</td>";
    $tabla .= "<td>" . $f['profiles'] . "</td>";
    $tabla .= "<td>" . $f['terminales'] . "</td>";
    $tabla .= "</tr>";
}
$tabla .= "</tbody></table>";

echo $tabla;
?>
