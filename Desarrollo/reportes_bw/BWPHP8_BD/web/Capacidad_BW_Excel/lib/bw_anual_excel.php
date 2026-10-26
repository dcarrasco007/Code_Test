<?php
/**
 * Arma el Excel anual desde OLT_REPORTE_BW_ANUAL (servidor web), con el mismo formato del archivo
 * original: encabezados, día combinado en la fila 2, PE-com combinado por grupo, colores 60/70 y
 * fórmula % Uplink = +Tráfico/Capacidad. Usa como formato plantilla/plantilla_reporte_bw.xlsx.
 * PHP 8.0.
 */
require_once __DIR__ . '/bw_anual.php';
require_once __DIR__ . '/bw_xlsx.php';

define('BW_PLANTILLA', dirname(__DIR__) . '/plantilla/plantilla_reporte_bw.xlsx');

/**
 * Genera en $destino el Excel del año $anio con los días hasta el domingo de la semana $semana.
 */
function bw_anual_generar_excel($mysqli, $anio, $semana, $destino)
{
    $datos = bw_anual_datos_excel($mysqli, $anio, $semana);
    if (count($datos['grupos']) == 0) {
        throw new Exception("No hay datos de $anio en " . BW_TABLA_ANUAL);
    }
    $libro = new BwLibroXlsx();
    $libro->abrir(BW_PLANTILLA);
    $proto = $libro->capturarPrototipo($libro->numDias() - 1);
    $proto['filas'] = array(3 => reset($proto['filas']));
    $libro->truncarDias(0);
    $libro->reconstruirFilas($datos['grupos']);

    $colsPct = array();
    foreach ($datos['fechas'] as $k => $fecha) {
        $valores = array();
        foreach ($datos['valores'] as $fila => $dias) {
            $valores[$fila] = isset($dias[$fecha]) ? $dias[$fecha] : array(null, null);
        }
        $colsPct[] = $libro->agregarDia($k, bw_etiqueta_fecha($fecha), $proto, $valores);
    }
    $libro->agregarFormatoCondicional($colsPct, 3, $libro->ultimaFila());
    $diaIniSemana = ((int)$semana - 1) * 7;
    $libro->ajustarVista(end($colsPct), bw_col_dia($diaIniSemana));
    $libro->guardar($destino, (int)$anio);
}
