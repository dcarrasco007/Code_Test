<?php
ini_set("log_errors", 1);
ini_set("error_log","../ONT/Archivo/control_error.log");
set_time_limit(50000000000);
ini_set('memory_limit', -1);
include (__DIR__ . '/../conexion/conexion_db.php');

//echo "Parte 1";
include '/var/www/html/crontab127/OLT/js/PHPExcel/Classes/PHPExcel/IOFactory.php';
//echo "Parte 2";
  $zipFilePath=trim($_REQUEST['name_xls']);
$zipFilePath='/var/www/html/ONT/Archivo_ONT/'.$zipFilePath;

    // Ruta del archivo ZIP

// Ruta de la carpeta de extracción
$extractPath = '/var/www/html/ONT/Archivo_ONT';

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
$delete=$mysqli->query("TRUNCATE TABLE OLT_INFO_ONT_PRUEBA");
// Función para limpiar el directorio
function limpiarDirectorio($path) {
    $files = glob($path . '/*.csv');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            sleep(2);
        }
    }
    $files = glob($path . '/*.zip');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
             sleep(2);
        }
    }
}

// Descomprimir el archivo ZIP
$zip = new ZipArchive();
if ($zip->open($zipFilePath) === TRUE) {
    $zip->extractTo($extractPath);
    $zip->close();
    //echo "Archivo ZIP descomprimido correctamente.\n";
} else {
    die("Error al descomprimir el archivo ZIP.\n");
}

// Leer los archivos descomprimidos
$csvFiles = glob($extractPath . '/*.csv');

foreach ($csvFiles as $csvFile) {
    //echo "Procesando: $csvFile\n";

    // Abrir el archivo CSV
    if (($handle = fopen($csvFile, "r")) !== FALSE) {
        if($csvFile=="/var/www/html/ONT/Archivo_ONT/All_GPON_ONU.csv"){
            //echo "entro if \n";
            $rowIndex = 0;
        }else{
            $rowIndex = 9;
        }
        $var=0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $rowIndex++;
            $var++;
            // Saltar las filas antes de la fila 10
            if ($rowIndex >10) {
                //if($rowIndex ==11){ print_r($data);}
                // Obtener las columnas necesarias (ajusta los índices según tu archivo CSV)
                $col1 = isset($data[0]) ? $data[0] : null; // Primera columna
                $col2 = isset($data[1]) ? $data[2] : null; // Segunda columna
                $col3 = isset($data[1]) ? $data[3] : null;
                $col4 = isset($data[1]) ? $data[4] : null;
                $col5 = isset($data[1]) ? $data[5] : null;
                $col6 = isset($data[1]) ? $data[6] : null;
                $col7 = isset($data[1]) ? $data[7] : null;
                $col8 = isset($data[1]) ? $data[8] : null;
                $col9 = isset($data[1]) ? $data[20] : null;
                $col10 = isset($data[1]) ? $data[13] : null;
                $col11 = isset($data[1]) ? $data[21] : null;
                $col18 = isset($data[1]) ? $data[18] : null;

                // Insertar en la base de datos
                $data1[]='("'.$col1.'","'.$col2.'","'.$col3.'","'.$col4.'","'.$col5.'","'.$col6.'","'.$col7.'","'.$col8.'","'.$col9.'","'.$col10.'","'.$col11.'","'.$col18.'")';
                if($var==1000){
                    $var=0;
                    $inserta="INSERT INTO OLT_INFO_ONT_PRUEBA (equipo,fn,sn,pn,onu,name,alias,serial_number,line_profile,terminal_type,service_profile,onu_general_vas_profile)
                    VALUES".implode(",",$data1);
                    $insert=$mysqli->query($inserta);
                    unset($data1);
                }
            }
        }
                $var=0;
                if(!empty($data1)){
                $inserta="INSERT INTO OLT_INFO_ONT_PRUEBA (equipo,fn,sn,pn,onu,name,alias,serial_number,line_profile,terminal_type,service_profile,onu_general_vas_profile)
                VALUES".implode(",",$data1);
                $insert=$mysqli->query($inserta);
                }
                unset($data1);
        fclose($handle);
    } else {
        echo "No se pudo abrir el archivo: $csvFile\n";
    }
}

// Actualizar VAS Profile en OLT_INFORMACION_ONT_DETALLE_COMPLETO (equipo/frame/slot/port/onu); sin coincidencia -> "--"
$update_vas="UPDATE OLT_INFORMACION_ONT_DETALLE_COMPLETO d
    LEFT JOIN OLT_INFO_ONT_PRUEBA p
        ON p.equipo=d.equipo AND p.fn=d.frame_id AND p.sn=d.slot_id AND p.pn=d.port_id AND p.onu=d.onu_id
    SET d.vas_profile=COALESCE(NULLIF(TRIM(p.onu_general_vas_profile),''),'--')";
if(!$mysqli->query($update_vas)){
    error_log("Error update vas_profile: ".$mysqli->error);
}

// Limpiar los archivos descomprimidos
limpiarDirectorio($extractPath);
sleep(5);
echo "OK";
// Eliminar el archivo ZIP
/* if (file_exists($zipFilePath)) {
    unlink($zipFilePath);
    echo "Archivo ZIP eliminado correctamente.\n";
} else {
    echo "No se encontró el archivo ZIP para eliminar.\n";
} */
?>