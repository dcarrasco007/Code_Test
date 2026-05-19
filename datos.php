<?php
/**
 * datos.php - Endpoint de ejemplo (PHP 5.3 compatible)
 * En produccion, reemplazar la generacion de datos por consulta a base de datos.
 *
 * GET params:
 *   filtro => 'semana' | 'mes' | 'anual'
 *   valor  => numero de semana, numero de mes (1-12) o anio
 *
 * Response: JSON array [ { "nombre": "...", "cantidad": N }, ... ]
 */

header('Content-Type: application/json; charset=utf-8');

$filtros_validos = array('semana', 'mes', 'anual');
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'anual';
$valor  = isset($_GET['valor'])  ? intval($_GET['valor']) : intval(date('Y'));

if (!in_array($filtro, $filtros_validos)) {
    $filtro = 'anual';
}

/* -------------------------------------------------------------------
 * Nombres de ejemplo (~25). En produccion, obtener desde BD.
 * ------------------------------------------------------------------- */
$nombres = array(
    'Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon',
    'Zeta', 'Eta', 'Theta', 'Iota', 'Kappa',
    'Lambda', 'Mu', 'Nu', 'Xi', 'Omicron',
    'Pi', 'Rho', 'Sigma', 'Tau', 'Upsilon',
    'Phi', 'Chi', 'Psi', 'Omega', 'Alfa2'
);

/* Semilla reproducible: mismo filtro+valor siempre devuelve los mismos datos */
mt_srand(abs(crc32($filtro . $valor)));

$datos = array();
foreach ($nombres as $nombre) {
    $datos[] = array(
        'nombre'   => $nombre,
        'cantidad' => mt_rand(0, 100)
    );
}

echo json_encode($datos);
exit;
