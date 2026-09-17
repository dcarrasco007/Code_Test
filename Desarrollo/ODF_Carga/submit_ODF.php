<?php
ini_set("log_errors", 1);
ini_set("error_log","../control_error.log");
set_time_limit(50000000000);
ini_set('memory_limit', -1);
include ('../../conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
include '../../js/PHPExcel/Classes/PHPExcel/IOFactory.php';

define('ODF_BATCH_SIZE', 500);

// Inserta/actualiza un lote en una sola query, usando el indice unico
// (equipo, puerto, odf): misma fibra fisica -> actualiza comentario/gabinete;
// fibra/odf distinto en el mismo puerto -> fila nueva (splitter GPON legitimo).
function flush_batch_odf($mysqli, $rows) {
    if (empty($rows)) return;
    $sql = "INSERT INTO OLT_POS_ODF (equipo,puerto,odf,comentario) VALUES "
         . implode(',', $rows)
         . " ON DUPLICATE KEY UPDATE comentario = VALUES(comentario)";
    if (!$mysqli->query($sql)) {
        error_log("Error batch OLT_POS_ODF: " . $mysqli->error);
        throw new Exception($mysqli->error);
    }
}

$inputFileName='../Files2/'.trim($_REQUEST['name_xls']);

$objReaderDetect = PHPExcel_IOFactory::identify($inputFileName); // xls o xlsx ?
$objReader = PHPExcel_IOFactory::createReader($objReaderDetect); // cargo dependiendo la extension
$objPHPExcel = $objReader->load($inputFileName); // carga documento xls
$read=$objPHPExcel->getSheet(0);
$filas=$objPHPExcel->setActiveSheetIndex(0)->getHighestRow();

$OLT=$read->getCell('B5');
$vector=explode('ORIGEN:',$OLT);
$equipo=trim($vector[1]);

if(strlen($equipo)>0){//si existe equipo
    $equipoEsc = $mysqli->real_escape_string($equipo);
    $rows = array();
    $contador = 0;

    $mysqli->query('START TRANSACTION');
    try {
        for ($f=7; $f <$filas; $f++) {

            $puerto=$read->getCell('B'.$f).'/'.$read->getCell('C'.$f).'/'.$read->getCell('D'.$f);
            $odf='RACK:'.$read->getCell('E'.$f).'/ODF:'.$read->getCell('F'.$f).'/FIBRA:'.$read->getCell('G'.$f);
            $gabinete=$read->getCell('H'.$f);
            $gabinete=trim($gabinete);

            if(strlen($puerto)>3 && $read->getCell('E'.$f)!='' && $read->getCell('F'.$f)!='' && $read->getCell('G'.$f)!=''&& $read->getCell('H'.$f)!=''){
                $contador++;
                $rows[] = "('$equipoEsc','"
                    . $mysqli->real_escape_string($puerto) . "','"
                    . $mysqli->real_escape_string($odf) . "','"
                    . $mysqli->real_escape_string($gabinete) . "')";

                if (count($rows) >= ODF_BATCH_SIZE) {
                    flush_batch_odf($mysqli, $rows);
                    $rows = array();
                }
            }
        }
        flush_batch_odf($mysqli, $rows); // resto (< ODF_BATCH_SIZE)
        $mysqli->commit();
        echo "OK";
    } catch (Exception $e) {
        $mysqli->rollback();
        error_log("submit_ODF.php fallo, rollback: " . $e->getMessage());
        echo "ERROR";
    }
}else{
    echo "ERROR";
}

?>
