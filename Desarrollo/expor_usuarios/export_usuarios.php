<?php
include_once(__DIR__ . '/../perfiles/getPerfiles.php');
include (__DIR__ . '/../conexion/conexion_db.php');
require_once '../../js/PHPExcel/Classes/PHPExcel.php';

$tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'todo';

$mysqli1 = new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
$mysqli1->set_charset("utf8");

$where = "";
if ($tipo == 'filtro') {
    // ids de las filas visibles en la tabla (solo enteros)
    $ids = array_filter(array_map('intval', explode(',', isset($_POST['ids']) ? $_POST['ids'] : '')));
    if (!count($ids)) { $ids = array(0); }
    $where = " WHERE OLT_USUARIOS.id_usuario IN (" . implode(',', $ids) . ")";
}

$query = "SELECT
    OLT_USUARIOS.usuario,
    OLT_USUARIOS.nombre_user,
    OLT_PERFILES.nombre,
    OLT_USUARIOS.correo,
    OLT_USUARIOS.estado
    FROM
    OLT_USUARIOS
    LEFT JOIN OLT_PERFILES ON OLT_USUARIOS.perfil = OLT_PERFILES.id_perfil" . $where . " ORDER BY OLT_USUARIOS.id_usuario DESC";
$result = $mysqli1->query($query) or die("Error " . $mysqli1->error);

$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();
$sheet->setTitle('Usuarios');
$sheet->getColumnDimension('A')->setWidth(20);
$sheet->getColumnDimension('B')->setWidth(35);
$sheet->getColumnDimension('C')->setWidth(25);
$sheet->getColumnDimension('D')->setWidth(35);
$sheet->getColumnDimension('E')->setWidth(12);

$sheet->setCellValue('A1', 'USUARIO');
$sheet->setCellValue('B1', 'NOMBRE');
$sheet->setCellValue('C1', 'PERFIL');
$sheet->setCellValue('D1', 'CORREO');
$sheet->setCellValue('E1', 'ESTADO');
$sheet->getStyle('A1:E1')->getFont()->setBold(true);

$contador_excel = 2;
while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $sheet->setCellValueExplicit('A'.$contador_excel, $row[0], PHPExcel_Cell_DataType::TYPE_STRING);
    $sheet->setCellValue('B'.$contador_excel, $row[1]);
    $sheet->setCellValue('C'.$contador_excel, $row[2]);
    $sheet->setCellValue('D'.$contador_excel, $row[3]);
    $sheet->setCellValue('E'.$contador_excel, ($row[4] == 1) ? 'Activo' : 'Inactivo');
    $contador_excel++;
}
$sheet->setAutoFilter('A1:E' . ($contador_excel - 1));
$mysqli1->close();

$nombreArchivo = ($tipo == 'filtro' ? 'Usuarios_Filtro_' : 'Usuarios_') . date('Ymd_His') . '.xlsx';

while (ob_get_level()) { ob_end_clean(); }
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
header('Cache-Control: max-age=0');

$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$objWriter->save('php://output');
exit;
?>
