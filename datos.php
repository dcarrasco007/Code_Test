<?php
/**
 * datos.php - Endpoint de ejemplo (PHP 5.3 compatible)
 * En produccion, reemplazar la generacion de datos por consultas reales a BD.
 *
 * -----------------------------------------------------------------------
 * MODO PRINCIPAL (sin parametro 'nombre'):
 *   GET filtro => 'semana' | 'mes' | 'anual'
 *   GET valor  => numero de semana, numero de mes (1-12) o anio
 *   GET anio   => anio del periodo (solo para filtro semana/mes)
 *   Responde: JSON array [ { "nombre": "...", "cantidad": N }, ... ]
 *             donde cantidad = suma de las 3 sub-queries para ese nombre.
 *
 * MODO DETALLE (con parametro 'nombre'):
 *   GET filtro, valor, anio, nombre => nombre especifico a detallar
 *   Responde: JSON object {
 *               "nombre":  "...",
 *               "total":   N,
 *               "detalle": [
 *                 { "fuente": "Consulta 1", "cantidad": N1 },
 *                 { "fuente": "Consulta 2", "cantidad": N2 },
 *                 { "fuente": "Consulta 3", "cantidad": N3 }
 *               ]
 *             }
 *             donde N1 + N2 + N3 = total (identico a la cantidad del grafico principal).
 * -----------------------------------------------------------------------
 */

header('Content-Type: application/json; charset=utf-8');

$filtros_validos = array('semana', 'mes', 'anual');
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'anual';
$valor  = isset($_GET['valor'])  ? intval($_GET['valor']) : intval(date('Y'));
$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
$anio   = isset($_GET['anio'])   ? intval($_GET['anio'])  : intval(date('Y'));

if (!in_array($filtro, $filtros_validos)) {
    $filtro = 'anual';
}

/* Para filtro anual el año ya esta en $valor; unificar en $anio */
if ($filtro === 'anual') {
    $anio = $valor;
}

/* -------------------------------------------------------------------
 * Genera el total de un nombre de forma reproducible.
 * Incluye $anio en la semilla para distinguir periodos del mismo
 * numero de semana/mes en distintos años.
 * En produccion: resultado de la suma de las 3 queries para ese nombre.
 * ------------------------------------------------------------------- */
function generarTotal($filtro, $anio, $valor, $nombre) {
    mt_srand(abs(crc32($filtro . $anio . $valor . $nombre)));
    return mt_rand(0, 100);
}

/* -------------------------------------------------------------------
 * Divide un total en 3 partes que suman exactamente $total.
 * En produccion: valores reales de cada sub-query.
 * ------------------------------------------------------------------- */
function dividirEnTres($filtro, $anio, $valor, $nombre, $total) {
    if ($total === 0) {
        return array(0, 0, 0);
    }
    mt_srand(abs(crc32($filtro . $anio . $valor . $nombre . '_det')));
    $p1   = mt_rand(0, $total);
    $rest = $total - $p1;
    $p2   = ($rest > 0) ? mt_rand(0, $rest) : 0;
    $p3   = $total - $p1 - $p2;
    return array($p1, $p2, $p3);
}

/* ===================================================================
 * MODO DETALLE: se recibio un nombre especifico
 * =================================================================== */
if ($nombre !== '') {

    $total  = generarTotal($filtro, $anio, $valor, $nombre);
    $partes = dividirEnTres($filtro, $anio, $valor, $nombre, $total);

    $respuesta = array(
        'nombre' => $nombre,
        'total'  => $total,
        'detalle' => array(
            array('fuente' => 'Consulta 1', 'cantidad' => $partes[0]),
            array('fuente' => 'Consulta 2', 'cantidad' => $partes[1]),
            array('fuente' => 'Consulta 3', 'cantidad' => $partes[2])
        )
    );

    echo json_encode($respuesta);
    exit;
}

/* ===================================================================
 * MODO PRINCIPAL: devuelve todos los nombres con su total
 * =================================================================== */

/* Nombres de ejemplo (~25). En produccion, obtener desde BD. */
$nombres = array(
    'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon',
    'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa',
    'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron',
    'Pi', 'Rho', 'Sigma', 'Tau', 'Upsilon',
    'Phi', 'Chi', 'Psi', 'Omega', 'Alfa2'
);

$datos = array();
foreach ($nombres as $n) {
    $datos[] = array(
        'nombre'   => $n,
        'cantidad' => generarTotal($filtro, $anio, $valor, $n)
    );
}

echo json_encode($datos);
exit;
