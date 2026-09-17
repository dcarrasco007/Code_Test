<?php
// Exporta el reporte de SN/MAC duplicadas a .xlsx (2 hojas), replicando
// el formato/colores del informe de referencia.
ini_set("memory_limit", "1024M");
set_time_limit(300);

include ('../../../conexion/conexion_db.php');
require_once '../../../js/PHPExcel/Classes/PHPExcel.php';

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
        $n_online++;
    } elseif ($hayOnline && $hayOffline) {
        $clase = 'mixto';
        $n_mixto++;
    } else {
        $clase = 'offline';
        $n_offline++;
    }

    $estadosDisplay = str_replace(array('online', 'offline'), array('Online', 'Offline'), $row['estados_run']);

    $filas[] = array(
        $row['sn_mac'],
        $row['veces'],
        $row['onu_names'],
        $row['puertos'],
        $row['olts'],
        $estadosDisplay,
        $row['profiles'],
        $row['terminales'],
        $clase,
    );
}
$mysqli->close();

$total = count($filas);
$fechaHoy = date('d/m/Y');

// ---- Colores (extraidos del informe de referencia) ----
$COLOR_TITULO    = '1F4E79';
$COLOR_SUBTITULO = 'D6E4F0';
$COLOR_HEADER    = '2E75B6';
$COLOR_ONLINE    = 'C6EFCE';
$COLOR_MIXTO     = 'FFD966';
$COLOR_OFFLINE   = 'FFCCCC';

$objPHPExcel = new PHPExcel();

// ================== HOJA 1: MACs Duplicadas ==================
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();
$sheet->setTitle('MACs Duplicadas');

$headers = array('SN/MAC', 'Veces', 'ONU Name(s)', 'Puertos', 'OLTs', 'Estado Run', 'Line Profile(s)', 'Terminal Type');
$cols = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H');

$sheet->getColumnDimension('A')->setWidth(22);
$sheet->getColumnDimension('B')->setWidth(8);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(46);
$sheet->getColumnDimension('E')->setWidth(28);
$sheet->getColumnDimension('F')->setWidth(16);
$sheet->getColumnDimension('G')->setWidth(35);
$sheet->getColumnDimension('H')->setWidth(16);

// Titulo
$sheet->setCellValue('A1', 'ONUs con SN/MAC en más de un puerto — Access ' . $fechaHoy);
$sheet->mergeCells('A1:H1');
$sheet->getRowDimension(1)->setRowHeight(32);
$sheet->getStyle('A1')->getFont()->setName('Arial')->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$sheet->getStyle('A1')->getFill()->getStartColor()->setRGB($COLOR_TITULO);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

// Subtitulo
$subt = "Total: $total SN/MAC en 2+ puertos | Solo Online: $n_online  Mixto: $n_mixto  Solo Offline: $n_offline";
$sheet->setCellValue('A2', $subt);
$sheet->mergeCells('A2:H2');
$sheet->getStyle('A2')->getFont()->setName('Arial')->setItalic(true)->setSize(10);
$sheet->getStyle('A2')->getFont()->getColor()->setRGB('595959');
$sheet->getStyle('A2')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$sheet->getStyle('A2')->getFill()->getStartColor()->setRGB($COLOR_SUBTITULO);

// Encabezados
for ($i = 0; $i < count($headers); $i++) {
    $sheet->setCellValue($cols[$i] . '3', $headers[$i]);
}
$sheet->getRowDimension(3)->setRowHeight(28);
$sheet->getStyle('A3:H3')->getFont()->setName('Arial')->setBold(true)->setSize(10);
$sheet->getStyle('A3:H3')->getFont()->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A3:H3')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$sheet->getStyle('A3:H3')->getFill()->getStartColor()->setRGB($COLOR_HEADER);
$sheet->getStyle('A3:H3')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('A3:H3')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
$sheet->getStyle('A3:H3')->getAlignment()->setWrapText(true);

// Filas de datos
$fila = 4;
foreach ($filas as $f) {
    $sheet->setCellValue('A' . $fila, $f[0]);
    $sheet->setCellValueExplicit('B' . $fila, $f[1], PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $sheet->setCellValue('C' . $fila, $f[2]);
    $sheet->setCellValue('D' . $fila, $f[3]);
    $sheet->setCellValue('E' . $fila, $f[4]);
    $sheet->setCellValue('F' . $fila, $f[5]);
    $sheet->setCellValue('G' . $fila, $f[6]);
    $sheet->setCellValue('H' . $fila, $f[7]);

    if ($f[8] === 'online') {
        $colorFila = $COLOR_ONLINE;
    } elseif ($f[8] === 'mixto') {
        $colorFila = $COLOR_MIXTO;
    } else {
        $colorFila = $COLOR_OFFLINE;
    }

    $sheet->getStyle('A' . $fila . ':H' . $fila)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getFill()->getStartColor()->setRGB($colorFila);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getFont()->setName('Arial')->setSize(9);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getAlignment()->setWrapText(true);
    $sheet->getStyle('A' . $fila . ':H' . $fila)->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
    $sheet->getStyle('B' . $fila)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($fila)->setRowHeight(32);

    $fila++;
}

$sheet->freezePane('A4');

// ================== HOJA 2: Resumen ==================
$objPHPExcel->createSheet(1);
$objPHPExcel->setActiveSheetIndex(1);
$sheet2 = $objPHPExcel->getActiveSheet();
$sheet2->setTitle('Resumen');
$sheet2->setShowGridlines(false);

$sheet2->getColumnDimension('A')->setWidth(44);
$sheet2->getColumnDimension('B')->setWidth(12);
$sheet2->getColumnDimension('C')->setWidth(52);

$sheet2->setCellValue('A1', 'Resumen por Estado Running');
$sheet2->mergeCells('A1:C1');
$sheet2->getRowDimension(1)->setRowHeight(28);
$sheet2->getStyle('A1')->getFont()->setName('Arial')->setBold(true)->setSize(14);
$sheet2->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
$sheet2->getStyle('A1')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$sheet2->getStyle('A1')->getFill()->getStartColor()->setRGB($COLOR_TITULO);
$sheet2->getStyle('A1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
$sheet2->getStyle('A1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

$sheet2->setCellValue('A2', 'Estado');
$sheet2->setCellValue('B2', 'Cantidad');
$sheet2->setCellValue('C2', 'Descripción');
$sheet2->getRowDimension(2)->setRowHeight(20);
$sheet2->getStyle('A2:C2')->getFont()->setName('Arial')->setBold(true)->setSize(10);
$sheet2->getStyle('A2:C2')->getFont()->getColor()->setRGB('FFFFFF');
$sheet2->getStyle('A2:C2')->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
$sheet2->getStyle('A2:C2')->getFill()->getStartColor()->setRGB($COLOR_HEADER);
$sheet2->getStyle('A2:C2')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

$resumenRows = array(
    array('Solo Online (ambas entradas activas)', $n_online, 'ONU activa en 2 puertos simultáneamente — revisar urgente', $COLOR_ONLINE),
    array('Mixto (un Online, un Offline)', $n_mixto, 'Migración de puerto — entrada anterior aún registrada en NMS', $COLOR_MIXTO),
    array('Solo Offline (ambas inactivas)', $n_offline, 'ONU inactiva en 2 puertos — registro huérfano a depurar', $COLOR_OFFLINE),
);

$fila2 = 3;
foreach ($resumenRows as $r) {
    $sheet2->setCellValue('A' . $fila2, $r[0]);
    $sheet2->setCellValueExplicit('B' . $fila2, $r[1], PHPExcel_Cell_DataType::TYPE_NUMERIC);
    $sheet2->setCellValue('C' . $fila2, $r[2]);
    $sheet2->getRowDimension($fila2)->setRowHeight(22);
    $sheet2->getStyle('A' . $fila2 . ':C' . $fila2)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
    $sheet2->getStyle('A' . $fila2 . ':C' . $fila2)->getFill()->getStartColor()->setRGB($r[3]);
    $sheet2->getStyle('A' . $fila2 . ':C' . $fila2)->getFont()->setName('Arial')->setSize(10);
    $sheet2->getStyle('B' . $fila2)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    $fila2++;
}

$sheet2->setCellValue('A' . $fila2, 'TOTAL');
$sheet2->setCellValueExplicit('B' . $fila2, $total, PHPExcel_Cell_DataType::TYPE_NUMERIC);
$sheet2->getStyle('A' . $fila2 . ':C' . $fila2)->getFont()->setName('Arial')->setBold(true)->setSize(10);
$sheet2->getStyle('B' . $fila2)->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

$objPHPExcel->setActiveSheetIndex(0);

// ================== Descarga ==================
$nombreArchivo = 'MACs_duplicadas_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save('php://output');
exit;
?>
