<?php
include ('../../conexion/conexion_db.php');
$idPag2 = '41920';
include_once('../perfiles/getPerfiles.php');
//checkAccV2(getUser(),$idPag2);
include('../perfiles/proceso.php');
require_once '/var/www/html/contingencia/js/PHPExcel/Classes/PHPExcel.php';

$mysqli = new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
$mysqli->set_charset("utf8");

// Las puertas utilizadas se calculan con el trafico del dia anterior,
// igual que en option_gpon.php (rama del if(isset($_POST['option']))).
// Si el proceso diario aun no carga el dia anterior, se usa la ultima fecha disponible.
$diaanterior = date("Y-m-d", strtotime("-1 day"));

$query_fecha = "SELECT MAX(fecha) FROM OLT_TRAFICOGPON WHERE fecha <= '$diaanterior'";
$res_fecha = $mysqli->query($query_fecha) or die("error puertas_pon_utilizadas.php $query_fecha");
$row_fecha = $res_fecha->fetch_row();
$fecha_trafico = $row_fecha[0] ? $row_fecha[0] : $diaanterior;

$query = "SELECT
        OLT_SERVER.`server`                     AS olt,
        MAX(OLT_SERVER.region)                  AS region,
        MAX(OLT_ONT_PCS.zs_comercial)           AS puertas_totales,
        COALESCE(MAX(TRAFICO.utilizadas), 0)    AS puertas_utilizadas
        FROM OLT_SERVER
        LEFT JOIN OLT_ONT_PCS ON OLT_ONT_PCS.`server` = OLT_SERVER.`server`
        LEFT JOIN (
            SELECT
                OLT_TRAFICOGPON.ip_equipo,
                COUNT(DISTINCT OLT_TRAFICOGPON.port) AS utilizadas
            FROM OLT_TRAFICOGPON
            WHERE OLT_TRAFICOGPON.fecha = '$fecha_trafico'
              AND OLT_TRAFICOGPON.up_mbps > 0
            GROUP BY OLT_TRAFICOGPON.ip_equipo
        ) AS TRAFICO ON TRAFICO.ip_equipo = OLT_SERVER.ip
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

    $olt        = $row['olt'];
    $region     = $row['region'];
    $totales    = ($olt == 'OLT-IQUIQUE-1') ? 4 : (int)$row['puertas_totales'];
    $utilizadas = (int)$row['puertas_utilizadas'];

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
