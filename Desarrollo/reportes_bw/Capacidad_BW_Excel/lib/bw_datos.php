<?php
/**
 * Obtención de datos de tráfico peak y capacidad uplink por OLT.
 * Misma fuente que OLT/Reporte_BW/reporte_bw_fecha_peak.php:
 *   capacidad: OLT_PUERTAS_UPLINKS_GB (capacidad_total, ej "100GB" => 100 Gbps => 100000 Mbps)
 *   tráfico:   OLT_TRAFICO_PEAK_DAY.peak_mb por ip y fecha
 * Compatible con PHP 5.3.
 */

function bw_conectar_bd()
{
    include BW_CONEXION;
    $mysqli = new mysqli($host144_geret, $user144_geret, $pass144_geret, BW_BD);
    if ($mysqli->connect_error) {
        throw new Exception('Error al conectar BD ' . BW_BD . ': ' . $mysqli->connect_error);
    }
    $mysqli->set_charset('utf8');
    return $mysqli;
}

// Convierte "25452,2" o "25452.2" a número
function bw_num($v)
{
    if ($v === null || $v === '') {
        return null;
    }
    return (float)str_replace(',', '.', trim($v));
}

function bw_clave_olt($nombre)
{
    return strtoupper(trim($nombre));
}

/**
 * OLTs con capacidad uplink conocida.
 * Retorna array(clave => array('olt','ip','pe_id','pe_com','cap'))  (cap en Mbps)
 * En reporte_bw_fecha_peak.php la capacidad de la OLT es la de su puerta de mayor
 * capacidad (subconsulta ordenada por cap DESC + GROUP BY olt), equivalente a MAX(cap).
 */
function bw_obtener_olts($mysqli)
{
    $sql = "SELECT T1.olt, MAX(OLTSERVER.ip) AS ip, MAX(OLTPE.id) AS pe_id, MAX(OLTPE.pe_com) AS pe_com, MAX(T1.cap) AS cap
            FROM (
                SELECT OLT_PUERTAS_UPLINKS_GB.olt,
                       CAST(LEFT(OLT_PUERTAS_UPLINKS_GB.capacidad_total, LENGTH(OLT_PUERTAS_UPLINKS_GB.capacidad_total)-2) AS SIGNED) AS cap
                FROM OLT_PUERTAS_UPLINKS_GB
                WHERE OLT_PUERTAS_UPLINKS_GB.capacidad_total <> ''
            ) T1
            INNER JOIN OLT_SERVER AS OLTSERVER ON T1.olt = OLTSERVER.`server`
            INNER JOIN OLT_PE_COM AS OLTPE ON OLTSERVER.pe_com = OLTPE.id
            GROUP BY T1.olt
            ORDER BY pe_id ASC, cap DESC";
    $res = $mysqli->query($sql);
    if (!$res) {
        throw new Exception('Error consulta OLTs: ' . $mysqli->error);
    }
    $olts = array();
    while ($row = $res->fetch_assoc()) {
        $olts[bw_clave_olt($row['olt'])] = array(
            'olt'    => $row['olt'],
            'ip'     => $row['ip'],
            'pe_id'  => (int)$row['pe_id'],
            'pe_com' => $row['pe_com'],
            'cap'    => ((int)$row['cap']) * 1000,
        );
    }

    // OLTs del Excel que no tengan capacidad cargada igual necesitan su ip para el tráfico
    $res = $mysqli->query("SELECT `server`, ip, pe_com FROM OLT_SERVER");
    if (!$res) {
        throw new Exception('Error consulta OLT_SERVER: ' . $mysqli->error);
    }
    while ($row = $res->fetch_assoc()) {
        $clave = bw_clave_olt($row['server']);
        if (!isset($olts[$clave]) && $row['ip'] != '') {
            $olts[$clave] = array('olt' => $row['server'], 'ip' => $row['ip'], 'pe_id' => (int)$row['pe_com'],
                'pe_com' => '', 'cap' => null);
        }
    }
    return $olts;
}

/**
 * Tráfico peak (Mbps) por ip y fecha. Retorna array(ip => array('Y-m-d' => mbps))
 */
function bw_obtener_trafico($mysqli, $desde, $hasta)
{
    $sql = "SELECT ip, fecha, peak_mb
            FROM OLT_TRAFICO_PEAK_DAY
            WHERE fecha BETWEEN '" . $mysqli->real_escape_string($desde) . "' AND '" . $mysqli->real_escape_string($hasta) . "'";
    $res = $mysqli->query($sql);
    if (!$res) {
        throw new Exception('Error consulta tráfico: ' . $mysqli->error);
    }
    $trafico = array();
    while ($row = $res->fetch_assoc()) {
        $ip = trim($row['ip']);
        $fecha = substr($row['fecha'], 0, 10);
        $mb = bw_num($row['peak_mb']);
        if ($mb === null) {
            continue;
        }
        if (!isset($trafico[$ip][$fecha]) || $mb > $trafico[$ip][$fecha]) {
            $trafico[$ip][$fecha] = $mb;
        }
    }
    return $trafico;
}

/**
 * Datos de prueba desde un .json con el mismo formato:
 * {"olts": {clave: {olt, ip, pe_id, pe_com, cap}}, "trafico": {ip: {fecha: mbps}}}
 */
function bw_datos_desde_json($archivo)
{
    $d = json_decode(file_get_contents($archivo), true);
    if (!is_array($d) || !isset($d['olts']) || !isset($d['trafico'])) {
        throw new Exception("Archivo de datos inválido: $archivo");
    }
    $olts = array();
    foreach ($d['olts'] as $o) {
        $olts[bw_clave_olt($o['olt'])] = $o;
    }
    return array($olts, $d['trafico']);
}
