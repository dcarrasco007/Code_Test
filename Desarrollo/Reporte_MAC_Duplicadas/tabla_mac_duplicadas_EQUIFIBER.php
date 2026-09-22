<?php
// Reporte: ONUs con SN/MAC registrada en mas de un puerto (solo OLTs EQUIFIBER).
// Consulta unica con GROUP_CONCAT (evita el patron N+1: una sola query
// para los grupos de duplicados, en vez de 1 SELECT por SN/MAC).
// El INNER JOIN contra OLT_SERVER_V2 limita la deteccion de duplicados
// a los equipos EQUIFIBER (mismo patron que proceso_informacion.php).
include ('../../../conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli->set_charset("utf8");

$mysqli->query("SET SESSION group_concat_max_len = 1000000");

$sql = "SELECT d.sn_mac, COUNT(*) AS veces,
        GROUP_CONCAT(d.onu_name ORDER BY d.id SEPARATOR ' | ') AS onu_names,
        GROUP_CONCAT(CONCAT(d.equipo,' F',d.frame_id,'/S',d.slot_id,'/P',d.port_id) ORDER BY d.id SEPARATOR ' | ') AS puertos,
        GROUP_CONCAT(d.equipo ORDER BY d.id SEPARATOR ' | ') AS olts,
        GROUP_CONCAT(d.estado ORDER BY d.id SEPARATOR ' | ') AS estados_run,
        GROUP_CONCAT(DISTINCT d.line_profile_name ORDER BY d.line_profile_name SEPARATOR ' | ') AS profiles,
        GROUP_CONCAT(DISTINCT d.modelo ORDER BY d.modelo SEPARATOR ' | ') AS terminales
        FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO d
        INNER JOIN OLT_SERVER_V2 s ON s.server = d.equipo
        GROUP BY d.sn_mac
        HAVING COUNT(*) > 1
        ORDER BY d.sn_mac ASC";
$result = $mysqli->query($sql) or die("Error consulta MAC duplicadas EQUIFIBER: " . $mysqli->error);

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
    $claseFiltro = ($clase === 'online') ? 'Online' : (($clase === 'mixto') ? 'Mixto' : 'Offline');

    $filas[] = array(
        'sn_mac'       => $row['sn_mac'],
        'veces'        => $row['veces'],
        'onu_names'    => $row['onu_names'],
        'puertos'      => $row['puertos'],
        'olts'         => $row['olts'],
        'estados'      => $estadosDisplay,
        'profiles'     => $row['profiles'],
        'terminales'   => $row['terminales'],
        'color'        => $color,
        'clase'        => $clase,
        'clase_filtro' => $claseFiltro,
    );
}
$mysqli->close();

$total = count($filas);
$fechaHoy = date('d/m/Y');

$tabla = "<style>
        #panelMacDup{background:#F5F6F8;border:1px solid #E3E6EA;border-radius:6px;padding:14px 18px;margin-bottom:14px;}
        #panelMacDup h4{margin:0 0 6px 0;color:#263238;font-weight:600;}
        #panelMacDup .meta{color:#607D8B;font-size:13px;margin-bottom:10px;}
        .badge-leyenda{display:inline-block;padding:4px 12px;border-radius:12px;font-size:13px;font-weight:600;margin-right:6px;}
        .badge-online{background:#C6EFCE;color:#1B5E20;}
        .badge-mixto{background:#FFE08A;color:#7A5B00;}
        .badge-offline{background:#FFCCCC;color:#B71C1C;}
        .btn-filtro-estado{border:1px solid #CFD8DC;background:#FFFFFF;color:#455A64;padding:5px 14px;border-radius:4px;font-size:13px;margin-right:6px;cursor:pointer;opacity:0.6;}
        .btn-filtro-estado.activo{opacity:1;font-weight:700;box-shadow:inset 0 0 0 1px rgba(0,0,0,0.15);}
        #tblMacDup th:nth-child(9), #tblMacDup td:nth-child(9){width:1px;max-width:1px;overflow:hidden;padding:0;font-size:0;line-height:0;border-left:0;}
        #tblMacDup thead th, .tablesorter-stickyHeader thead th{background:#37474F !important;color:#FFFFFF;}
        </style>";

$tabla .= "<div id='panelMacDup'>
        <div class='meta'>&Uacute;ltima generaci&oacute;n: $fechaHoy &nbsp;&bull;&nbsp; Total: $total SN/MAC en 2+ puertos (EQUIFIBER)</div>
        <div style='margin-bottom:10px;'>
            <span class='badge-leyenda badge-online'>&#128994; Solo Online: $n_online</span>
            <span class='badge-leyenda badge-mixto'>&#128993; Mixto: $n_mixto</span>
            <span class='badge-leyenda badge-offline'>&#128308; Solo Offline: $n_offline</span>
        </div>
        <div>
            <button type='button' class='btn-filtro-estado activo' data-clase=''>Todo ($total)</button>
            <button type='button' class='btn-filtro-estado' data-clase='Online'>Solo Online ($n_online)</button>
            <button type='button' class='btn-filtro-estado' data-clase='Mixto'>Mixto ($n_mixto)</button>
            <button type='button' class='btn-filtro-estado' data-clase='Offline'>Solo Offline ($n_offline)</button>
        </div>
    </div>";

$tabla .= "<table align='center' id='tblMacDup' class='table table-bordered table-striped' border='1' width='100%'>
        <thead>
        <tr>
            <th><center>SN/MAC</center></th>
            <th><center>Veces</center></th>
            <th><center>ONU Name(s)</center></th>
            <th><center>Puertos</center></th>
            <th><center>OLTs</center></th>
            <th><center>Estado Run</center></th>
            <th><center>Line Profile(s)</center></th>
            <th><center>Terminal Type</center></th>
            <th>Estado</th>
        </tr>
        </thead>
        <tbody>";

foreach ($filas as $f) {
    $bg = "style='background-color:" . $f['color'] . "'";
    $tabla .= "<tr>";
    $tabla .= "<td $bg>" . $f['sn_mac'] . "</td>";
    $tabla .= "<td $bg><center>" . $f['veces'] . "</center></td>";
    $tabla .= "<td $bg>" . $f['onu_names'] . "</td>";
    $tabla .= "<td $bg>" . $f['puertos'] . "</td>";
    $tabla .= "<td $bg>" . $f['olts'] . "</td>";
    $tabla .= "<td $bg>" . $f['estados'] . "</td>";
    $tabla .= "<td $bg>" . $f['profiles'] . "</td>";
    $tabla .= "<td $bg>" . $f['terminales'] . "</td>";
    $tabla .= "<td>" . $f['clase_filtro'] . "</td>";
    $tabla .= "</tr>";
}
$tabla .= "</tbody></table>";

echo $tabla;
?>
