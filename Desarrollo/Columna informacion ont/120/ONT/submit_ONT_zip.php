<?php
ini_set("log_errors", 1);
ini_set("error_log","../ONT/Archivo/control_error.log");
set_time_limit(50000000000);
ini_set('memory_limit', -1);
include (__DIR__ . '/../conexion/conexion_db.php');

//echo "Parte 1";
include '/var/www/html/crontab127/OLT/js/PHPExcel/Classes/PHPExcel/IOFactory.php';
//echo "Parte 2";

// Ruta donde doajaxfileupload10.php deja los .zip; cada .zip se descomprime en su propia subcarpeta
$extractPath = '/var/www/html/ONT/Archivo_ONT';

// Columna de OLT_INFO_ONT_PRUEBA => titulo de la columna en el CSV.
// Se busca por nombre porque los reportes no traen las mismas columnas (OnNet agrega "Running Status" y "ONU Version")
$mapaColumnas = array(
    'equipo'                  => 'Device Name',
    'fn'                      => 'Frame',
    'sn'                      => 'Slot',
    'pn'                      => 'Port',
    'onu'                     => 'ONU ID',
    'name'                    => 'Name',
    'alias'                   => 'Alias',
    'serial_number'           => 'SN',
    'line_profile'            => 'Line Profile',
    'terminal_type'           => 'Terminal Type',
    'service_profile'         => 'Service Profile',
    'onu_general_vas_profile' => 'ONU General VAS Profile'
);

// Función para limpiar el directorio (zip subidos y carpetas de extracción)
function limpiarDirectorio($path) {
    foreach (glob($path . '/zip_*', GLOB_ONLYDIR) as $dir) {
        foreach (glob($dir . '/*') as $file) {
            if (is_file($file)) { unlink($file); }
        }
        rmdir($dir);
    }
    foreach (array_merge(glob($path . '/*.csv'), glob($path . '/*.zip')) as $file) {
        if (is_file($file)) { unlink($file); }
    }
}

// Avanza el CSV hasta la fila de encabezado ("Device Name", ...) y devuelve la posición de cada columna.
// $total recibe el "Total N Records" del reporte si aparece antes del encabezado.
function leerEncabezado($handle, $mapa, &$total) {
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        // Los CSV vienen con BOM UTF-8 al inicio; con el BOM delante fgetcsv deja las comillas del primer campo
        if (isset($data[0])) { $data[0] = trim(preg_replace('/^\xEF\xBB\xBF/', '', $data[0]), " \t\""); }
        $primera = isset($data[0]) ? $data[0] : '';
        if (preg_match('/^Total\s+([\d,\.]+)\s+Records/i', $primera, $m)) {
            $total = (int)str_replace(array(',', '.'), '', $m[1]);
        }
        if ($primera === 'Device Name') {
            $titulos = array_map('trim', $data);
            $pos = array();
            $faltan = array();
            foreach ($mapa as $campo => $titulo) {
                $i = array_search($titulo, $titulos, true);
                if ($i === false) { $faltan[] = $titulo; } else { $pos[$campo] = $i; }
            }
            return array(
                'pos' => $pos,
                'faltan' => $faltan,
                'columnas' => count($titulos),
                'formato' => in_array('Running Status', $titulos, true) ? 'con Running Status' : 'sin Running Status'
            );
        }
    }
    return null;
}

function fmt($n) { return number_format($n, 0, ',', '.'); }

// Archivos recibidos: name_xls[] (varios .zip, renombrados por la vista para que no se pisen) o name_xls (uno).
// name_orig[] trae el nombre original de cada uno, solo para mostrarlo en el resumen
$nombres = isset($_REQUEST['name_xls']) ? (array)$_REQUEST['name_xls'] : array();
$nombres = array_values(array_filter(array_map('trim', $nombres), 'strlen'));
$originales = isset($_REQUEST['name_orig']) ? array_values((array)$_REQUEST['name_orig']) : array();
if (!$nombres) {
    die("No se recibieron archivos .zip.\n");
}

// Restos de una carga anterior interrumpida (solo carpetas de extracción; los .zip recién subidos se mantienen)
foreach (glob($extractPath . '/zip_*', GLOB_ONLYDIR) as $dir) {
    array_map('unlink', glob($dir . '/*'));
    rmdir($dir);
}

// 1) Descomprimir y validar encabezados de todos los CSV antes de tocar la BD
$errores = array();
$plan = array();
foreach ($nombres as $n => $nombre) {
    $nombre = basename($nombre);
    $original = isset($originales[$n]) ? basename(trim($originales[$n])) : '';
    $etiqueta = 'Archivo ' . ($n + 1) . ' (' . ($original !== '' ? $original : $nombre) . ')';
    $zipFilePath = $extractPath . '/' . $nombre;
    $destino = $extractPath . '/zip_' . $n;
    $zip = new ZipArchive();
    if ($zip->open($zipFilePath) !== TRUE) {
        $errores[] = "$etiqueta: no se pudo descomprimir.";
        continue;
    }
    mkdir($destino);
    $zip->extractTo($destino);
    $zip->close();

    $csvFiles = glob($destino . '/*.csv');
    if (!$csvFiles) {
        $errores[] = "$etiqueta: no contiene archivos .csv.";
        continue;
    }
    $plan[$etiqueta] = array('csv' => array(), 'total' => 0, 'formato' => '', 'columnas' => 0, 'cargadas' => 0);
    foreach ($csvFiles as $csvFile) {
        $handle = fopen($csvFile, "r");
        $total = 0;
        $enc = $handle ? leerEncabezado($handle, $mapaColumnas, $total) : null;
        if ($handle) { fclose($handle); }
        if (!$enc) {
            $errores[] = "$etiqueta / " . basename($csvFile) . ": no se encontró la fila de encabezado (Device Name).";
        } elseif ($enc['faltan']) {
            $errores[] = "$etiqueta / " . basename($csvFile) . ": faltan columnas " . implode(', ', $enc['faltan']) . ".";
        } else {
            $plan[$etiqueta]['csv'][] = $csvFile;
            $plan[$etiqueta]['total'] += $total;
            $plan[$etiqueta]['formato'] = $enc['formato'];
            $plan[$etiqueta]['columnas'] = $enc['columnas'];
        }
    }
}
if ($errores) {
    limpiarDirectorio($extractPath);
    die("No se cargó nada, revisa los archivos:\n- " . implode("\n- ", $errores) . "\n");
}

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
$delete=$mysqli->query("TRUNCATE TABLE OLT_INFO_ONT_PRUEBA");

// 2) Cargar cada CSV usando la posición de cada columna según su encabezado
$campos = implode(',', array_keys($mapaColumnas));
$fallos = 0;
foreach ($plan as $etiqueta => $info) {
    foreach ($info['csv'] as $csvFile) {
        $handle = fopen($csvFile, "r");
        $total = 0;
        $enc = leerEncabezado($handle, $mapaColumnas, $total);
        $pos = $enc['pos'];
        $data1 = array();
        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
            if (!isset($data[$pos['onu']]) || trim($data[0]) === '') {
                continue;
            }
            $valores = array();
            foreach ($pos as $i) {
                // trim() también quita el tabulador que el reporte antepone a los "--"
                $valores[] = "'" . $mysqli->real_escape_string(trim($data[$i])) . "'";
            }
            $data1[] = '(' . implode(',', $valores) . ')';
            if (count($data1) == 2000) {
                if (!$mysqli->query("INSERT INTO OLT_INFO_ONT_PRUEBA ($campos) VALUES " . implode(",", $data1))) {
                    $fallos++;
                    error_log("Error insert OLT_INFO_ONT_PRUEBA ($etiqueta): " . $mysqli->error);
                } else {
                    $plan[$etiqueta]['cargadas'] += count($data1);
                }
                $data1 = array();
            }
        }
        if ($data1) {
            if (!$mysqli->query("INSERT INTO OLT_INFO_ONT_PRUEBA ($campos) VALUES " . implode(",", $data1))) {
                $fallos++;
                error_log("Error insert OLT_INFO_ONT_PRUEBA ($etiqueta): " . $mysqli->error);
            } else {
                $plan[$etiqueta]['cargadas'] += count($data1);
            }
        }
        fclose($handle);
    }
}

// Resumen por archivo
$resumen = array();
foreach ($plan as $etiqueta => $info) {
    $linea = "$etiqueta: " . count($info['csv']) . " CSV, formato " . $info['formato'] . " (" . $info['columnas'] . " columnas), "
        . fmt($info['cargadas']) . " ONT cargadas";
    if ($info['total']) {
        $linea .= " de " . fmt($info['total']) . " informadas" . ($info['total'] != $info['cargadas'] ? " (DIFERENCIA)" : "");
    }
    $resumen[] = $linea;
}

limpiarDirectorio($extractPath);

if ($fallos) {
    die("Error al insertar en OLT_INFO_ONT_PRUEBA ($fallos bloques fallidos, ver control_error.log). No se actualizó el VAS Profile.\n" . implode("\n", $resumen) . "\n");
}

// Actualizar VAS Profile en OLT_INFORMACION_ONT_DETALLE_COMPLETO (equipo/frame/slot/port/onu); sin coincidencia -> "--"
$update_vas="UPDATE OLT_INFORMACION_ONT_DETALLE_COMPLETO d
    LEFT JOIN OLT_INFO_ONT_PRUEBA p
        ON p.equipo=d.equipo AND p.fn=d.frame_id AND p.sn=d.slot_id AND p.pn=d.port_id AND p.onu=d.onu_id
    SET d.vas_profile=COALESCE(NULLIF(TRIM(p.onu_general_vas_profile),''),'--')";
if(!$mysqli->query($update_vas)){
    error_log("Error update vas_profile: ".$mysqli->error);
    die("Se cargaron los archivos pero falló la actualización del VAS Profile (ver control_error.log).\n" . implode("\n", $resumen) . "\n");
}

echo implode("\n", $resumen) . "\nOK";
?>
