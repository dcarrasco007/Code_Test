<?php
/**
 * Excel de un período (semana / mes / año) armado desde las columnas de OLT_CAPACIDAD_BW* (campos `1`..`162`),
 * con el formato del reporte: encabezados, día combinado en la fila 2, PE-com combinado por grupo, colores 60/70
 * y fórmula % Uplink = +Tráfico/Capacidad. Usa como formato plantilla/plantilla_reporte_bw.xlsx del módulo.
 *
 * Nombres y carpetas iguales a la subida manual (js/upload/doajaxfileupload*.php):
 *   Archivos/Semana{_EQUIFIBER|_ONNET}/Capacidades_OLTs_W-{semana}_{año}.xlsx
 *   Archivos/Mes{_EQUIFIBER|_ONNET}/Capacidades_OLTs_{Mes}_{año}.xlsx
 *   Archivos/Year{_EQUIFIBER|_ONNET}/Capacidades_OLTs_{año}.xlsx
 *
 * Lo usan el cron de la etapa 2 (al cargar) y descargar_excel_bw.php (si el archivo no está en el servidor).
 * Compatible con PHP 5.3 (portal de contingencia) y 8.0.
 */
require_once dirname(__FILE__) . '/bw_fechas.php';
require_once dirname(__FILE__) . '/bw_xlsx.php';

if (!defined('BW_PLANTILLA_PERIODO')) {
    define('BW_PLANTILLA_PERIODO', dirname(dirname(__FILE__)) . '/plantilla/plantilla_reporte_bw.xlsx');
}

/**
 * Carpeta (relativa a Archivos/) y nombre del Excel del período.
 * $tipo: semana | mes | anio ; $num: semana o mes (como viene en la tabla) ; $red: nacional | equifiber | onnet
 */
function bw_excel_ubicacion($tipo, $red, $anio, $num = null)
{
    $sufijos = array('nacional' => '', 'equifiber' => '_EQUIFIBER', 'onnet' => '_ONNET');
    if (!isset($sufijos[$red])) {
        throw new Exception("Red desconocida: $red");
    }
    if ($tipo == 'semana') {
        return array('Semana' . $sufijos[$red], 'Capacidades_OLTs_W-' . (int)$num . '_' . (int)$anio . '.xlsx');
    }
    if ($tipo == 'mes') {
        return array('Mes' . $sufijos[$red], 'Capacidades_OLTs_' . bw_nombre_mes((int)$num) . '_' . (int)$anio . '.xlsx');
    }
    if ($tipo == 'anio') {
        return array('Year' . $sufijos[$red], 'Capacidades_OLTs_' . (int)$anio . '.xlsx');
    }
    throw new Exception("Tipo inválido: $tipo");
}

function bw_excel_num($v)
{
    $v = trim((string)$v);
    return ($v === '' || !is_numeric($v)) ? null : (float)$v;
}

/**
 * Genera en $destino el Excel a partir de las columnas de la tabla: array de columnas, cada una
 * array(1..162 => texto) (índices 1..162, o 0..161 si vienen de fetch_row).
 */
function bw_excel_desde_columnas($columnas, $destino, $anioHoja)
{
    // Normaliza a índices 1..N
    $cols = array();
    foreach (array_values($columnas) as $c) {
        $c = array_values($c);
        array_unshift($c, null);
        unset($c[0]);
        $cols[] = $c;
    }
    if (count($cols) < 7) {
        throw new Exception('El período no tiene días cargados');
    }

    // Grupos de PE-com: empiezan donde la columna A trae nombre; la OLT está en D (filas 3 en adelante)
    $grupos = array();
    $filas = array();
    $nFilas = count($cols[3]);
    for ($r = 3; $r <= $nFilas; $r++) {
        $olt = trim((string)$cols[3][$r]);
        if ($olt === '') {
            break;
        }
        if (count($grupos) == 0 || trim((string)$cols[0][$r]) !== '') {
            $grupos[] = array('pe' => (string)$cols[0][$r], 'b' => bw_excel_num($cols[1][$r]),
                'c' => bw_excel_num($cols[2][$r]), 'olts' => array());
        }
        $grupos[count($grupos) - 1]['olts'][] = $olt;
        $filas[] = $r;
    }
    if (count($filas) == 0) {
        throw new Exception('El período no tiene OLTs');
    }

    $libro = new BwLibroXlsx();
    $libro->abrir(BW_PLANTILLA_PERIODO);
    $proto = $libro->capturarPrototipo($libro->numDias() - 1);
    $proto['filas'] = array(3 => reset($proto['filas']));
    $libro->truncarDias(0);
    $libro->reconstruirFilas($grupos);

    $colsPct = array();
    $nDias = (int)((count($cols) - 4) / 3);
    for ($k = 0; $k < $nDias; $k++) {
        $ct = $cols[4 + 3 * $k];
        $cc = $cols[5 + 3 * $k];
        $valores = array();
        foreach ($filas as $r) {
            $valores[$r] = array(bw_excel_num($ct[$r]), bw_excel_num($cc[$r]));
        }
        $colsPct[] = $libro->agregarDia($k, (string)$ct[2], $proto, $valores);
    }
    $libro->agregarFormatoCondicional($colsPct, 3, $libro->ultimaFila());
    $libro->ajustarVista(end($colsPct), 5);
    $libro->guardar($destino, (int)$anioHoja);
}

/**
 * Guarda el Excel del período en su carpeta de Archivos/ (la crea si no existe) y retorna la ruta.
 */
function bw_excel_guardar_periodo($dirArchivos, $tipo, $red, $anio, $num, $columnas)
{
    list($carpeta, $nombre) = bw_excel_ubicacion($tipo, $red, $anio, $num);
    $dir = rtrim($dirArchivos, '/\\') . '/' . $carpeta;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        throw new Exception("No se pudo crear la carpeta $dir");
    }
    $ruta = $dir . '/' . $nombre;
    bw_excel_desde_columnas($columnas, $ruta, $anio);
    @chmod($ruta, 0666);   // el usuario web debe poder reemplazarlo (el cron puede correr con otro usuario)
    return $ruta;
}
