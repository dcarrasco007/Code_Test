<?php
include ('../../conexion/conexion_db.php');
$idPag2 = '41920';
include_once('../perfiles/getPerfiles.php');
//checkAccV2(getUser(),$idPag2);
include('../perfiles/proceso.php');
require_once '/var/www/html/contingencia/js/PHPExcel/Classes/PHPExcel.php';

$mysqli = new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
$mysqli->set_charset("utf8");

/**
 * Devuelve los slots de la OLT que tienen tarjeta PON.
 * Los modelos validos ya no van fijos en el codigo: salen de
 * OLT_DETALLE_TARJETA.nombre filtrando por tipo_tarjeta = 'PON'.
 */
function obtenerPuertos($ip, $mysqli) {

    // Modelos de tarjeta PON por equipo. Se cargan una sola vez por ejecucion
    // y despues se toman solo los de la IP consultada.
    static $modelosPorIp = null;
    if ($modelosPorIp === null) {
        $modelosPorIp = array();
        $sql_modelos = "SELECT DISTINCT ip, nombre
                        FROM OLT_DETALLE_TARJETA
                        WHERE tipo_tarjeta = 'PON'
                          AND nombre <> ''";
        $res_modelos = $mysqli->query($sql_modelos) or die("error obtenerPuertos $sql_modelos");
        while ($row_modelo = $res_modelos->fetch_array(MYSQLI_NUM)) {
            $modelosPorIp[$row_modelo[0]][] = trim($row_modelo[1]);
        }
    }

    $modelosPon = isset($modelosPorIp[$ip]) ? $modelosPorIp[$ip] : array();
    if (!$modelosPon) {
        return array();
    }

    $cantidadPuerta = array();

    $sql_ip = "SELECT tarjetas,equipo FROM OLT_VOLTAJE_TARJETA WHERE ip = '$ip'";
    $result = $mysqli->query($sql_ip) or die("error obtenerPuertos $sql_ip");
    $row = $result->fetch_array(MYSQLI_NUM);
    if (!$row) {
        return $cantidadPuerta;
    }

    $puertas = explode("<br>", $row[0]);
    for ($i = 3; $i < count($puertas); $i++) {

        $div = explode(" ", $puertas[$i]);

        $slot    = isset($div[2]) ? trim($div[2]) : '';
        $modelo  = isset($div[4]) ? trim($div[4]) : '';
        $estado5 = isset($div[5]) ? trim($div[5]) : '';
        $estado6 = isset($div[6]) ? trim($div[6]) : '';

        if ($slot == 17) {
            break;
        }

        if ($modelo != '' && ($estado6 == 'Normal' || $estado6 == 'Mismatch' || $estado6 == 'Failed' || $estado5 == 'Mismatch')) {
            if (in_array($modelo, $modelosPon)) {
                $cantidadPuerta[] = (int)$slot;
            }
        }
    }

    return $cantidadPuerta;
}

// Las puertas utilizadas se calculan con el trafico del dia anterior,
// igual que en option_gpon.php (rama del if(isset($_POST['option']))).
// Si el proceso diario aun no carga el dia anterior, se usa la ultima fecha disponible.
$diaanterior = date("Y-m-d", strtotime("-1 day"));

$query_fecha = "SELECT MAX(fecha) FROM OLT_TRAFICOGPON WHERE fecha <= '$diaanterior'";
$res_fecha = $mysqli->query($query_fecha) or die("error puertas_pon_utilizadas.php $query_fecha");
$row_fecha = $res_fecha->fetch_array(MYSQLI_NUM);
$fecha_trafico = $row_fecha[0] ? $row_fecha[0] : $diaanterior;

// Puertas con trafico del dia, agrupadas por equipo. Despues se filtran
// dejando solo las que estan en un slot con tarjeta PON.
$query_trafico = "SELECT DISTINCT
        OLT_TRAFICOGPON.ip_equipo,
        OLT_TRAFICOGPON.port
        FROM OLT_TRAFICOGPON
        WHERE OLT_TRAFICOGPON.fecha = '$fecha_trafico'
          AND OLT_TRAFICOGPON.up_mbps > 0";
$res_trafico = $mysqli->query($query_trafico) or die("error puertas_pon_utilizadas.php $query_trafico");

$puertos_con_trafico = array();
while ($row_trafico = $res_trafico->fetch_array(MYSQLI_NUM)) {
    $puertos_con_trafico[$row_trafico[0]][] = $row_trafico[1];
}

$query = "SELECT
        OLT_SERVER.`server`             AS olt,
        MAX(OLT_SERVER.ip)              AS ip,
        MAX(OLT_SERVER.region)          AS region,
        MAX(OLT_ONT_PCS.zs_comercial)   AS puertas_totales
        FROM OLT_SERVER
        LEFT JOIN OLT_ONT_PCS ON OLT_ONT_PCS.`server` = OLT_SERVER.`server`
        GROUP BY OLT_SERVER.`server`
        ORDER BY region, olt";
$result = $mysqli->query($query) or die("error puertas_pon_utilizadas.php $query");

//-------------------- Excel --------------------
$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();
$sheet->setTitle('Puertas PON');

$sheet->getColumnDimension('A')->setWidth(10);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(22);
$sheet->getColumnDimension('E')->setWidth(24);

$sheet->setCellValue('C1', 'Puertas PON Utilizadas por OLT');
$sheet->setCellValue('C2', 'Trafico del dia: ' . $fecha_trafico);

$sheet->setCellValue('A4', 'N°');
$sheet->setCellValue('B4', 'OLT');
$sheet->setCellValue('C4', 'Region');
$sheet->setCellValue('D4', 'Puertas PON Totales');
$sheet->setCellValue('E4', 'Puertas PON Utilizadas');

$objDrawing = new PHPExcel_Worksheet_Drawing();
$objDrawing->setName('Logo Entel');
$objDrawing->setDescription('Logo de la compania');
$objDrawing->setPath('/var/www/html/contingencia/OLT/menu/logonuevo.png');
$objDrawing->setHeight(50);
$objDrawing->setCoordinates('A1');
$objDrawing->setOffsetX(10);
$objDrawing->setOffsetY(5);
$objDrawing->setWorksheet($sheet);

//-------------------- Tabla HTML --------------------
$tabla = "<table align='center' id='tblData' class='table table-bordered table-striped' border='1'>
            <thead class='bg-primary'><tr>
            <th bgcolor='#0566FC'><h5><center>N&deg;</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>OLT</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Regi&oacute;n</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Puertas PON Totales</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Puertas PON Utilizadas</center></h5></th>
            </tr></thead><tbody>";

$i = 1;
$fila_excel = 5;

while ($row = $result->fetch_assoc()) {

    $olt     = $row['olt'];
    $ip      = $row['ip'];
    $region  = $row['region'];
    $totales = ($olt == 'OLT-IQUIQUE-1') ? 4 : (int)$row['puertas_totales'];

    // Slots con tarjeta PON de esta OLT.
    $slots_pon = obtenerPuertos($ip, $mysqli);

    // Se cuentan solo las puertas con trafico que estan en un slot PON.
    // El formato de la puerta es frame/slot/puerta (ej: 0/2/5).
    $utilizadas = 0;
    if (isset($puertos_con_trafico[$ip])) {
        foreach ($puertos_con_trafico[$ip] as $port) {
            $partes = explode("/", $port);
            $slot = isset($partes[1]) ? (int)$partes[1] : -1;
            if (in_array($slot, $slots_pon, true)) {
                $utilizadas++;
            }
        }
    }

    $tabla .= "<tr>
                <td><h5><center>$i</center></h5></td>
                <td><h5><center>$olt</center></h5></td>
                <td><h5><center>$region</center></h5></td>
                <td><h5><center>$totales</center></h5></td>
                <td><h5><center>$utilizadas</center></h5></td>
               </tr>";

    $sheet->setCellValue('A'.$fila_excel, $i);
    $sheet->setCellValue('B'.$fila_excel, $olt);
    $sheet->setCellValue('C'.$fila_excel, $region);
    $sheet->setCellValue('D'.$fila_excel, $totales);
    $sheet->setCellValue('E'.$fila_excel, $utilizadas);

    $i++;
    $fila_excel++;
}

$tabla .= "</tbody></table>";

//-------------------- Guardar Excel --------------------
$carpetaDestino = '../Ocupacion/Archivos/';
$nombreArchivo  = 'Puertas_PON_Utilizadas.xlsx';

$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save($carpetaDestino . $nombreArchivo);

$mysqli->close();

echo "<h3><center>Informe Puertas PON Utilizadas por OLT.</center></h3>";
echo "<h5><center>Tr&aacute;fico del d&iacute;a: $fecha_trafico</center></h5>";
echo "<img src='/contingencia/OLT/menu/logonuevo.png' alt='Imagen fija' class='left-fixed-image'>";
echo "<a href='" . $carpetaDestino . $nombreArchivo . "'>Exportar Archivo</a>";
echo $tabla;

?>

<script>
$("#tblData").tablesorter({
    theme: 'blue',
    widgets: ["zebra", "stickyHeaders", "filter"],
});
</script>
