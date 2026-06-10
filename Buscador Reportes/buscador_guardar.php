<?php
/*
 * [BUSCADOR CONSULTA] Recibe por AJAX (POST) la consulta del usuario,
 * la guarda en OLT_BUSCADOR_CONSULTAS con estado 'pendiente' y devuelve el id.
 * Valida la sesion/perfil: si no hay usuario o id_perfil, aborta y pide login.
 * Compatible con PHP 5.3.
 */

// >>> CONFIGURA AQUI la URL de tu login (relativa a esta carpeta) <<<
$LOGIN_URL = '../menu/login.php';

include_once('../perfiles/getPerfiles.php');
include_once('../../conexion/conexion_db.php');

// Aseguro la sesion iniciada sin romper el JSON (no incluyo session_check.php,
// que redirige con HTML; aqui la validacion la hacemos nosotros y respondemos JSON).
if (session_id() == '') { @session_start(); }

header('Content-Type: application/json; charset=utf-8');

// El usuario y el perfil se toman del servidor (sesion), nunca del cliente.
$usuario  = function_exists('getUser')     ? getUser()     : '';
$idPerfil = function_exists('getIdPerfil') ? getIdPerfil() : '';

// --- Validacion de sesion / perfil: si falta algo, abortar y enviar a login ---
if ($usuario === '' || $usuario === null || $idPerfil === '' || $idPerfil === null || intval($idPerfil) <= 0) {
    echo json_encode(array(
        'ok'       => false,
        'auth'     => false,           // el front redirige al login
        'redirect' => $LOGIN_URL,
        'error'    => 'Sesion no iniciada o sin perfil.'
    ));
    exit;
}
$idPerfil = intval($idPerfil);

$consulta = isset($_POST['consulta']) ? trim($_POST['consulta']) : '';
// Limite defensivo de tamano
if (strlen($consulta) > 5000) { $consulta = substr($consulta, 0, 5000); }

if ($consulta === '') {
    echo json_encode(array('ok' => false, 'error' => 'La consulta esta vacia.'));
    exit;
}

$mysqli = @new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
if ($mysqli->connect_errno) {
    echo json_encode(array('ok' => false, 'error' => 'No se pudo conectar a la base de datos.'));
    exit;
}
$mysqli->set_charset('utf8');

$sql  = "INSERT INTO OLT_BUSCADOR_CONSULTAS (usuario, id_perfil, consulta, estado, fecha_solicitud)
         VALUES (?, ?, ?, 'pendiente', NOW())";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(array('ok' => false, 'error' => 'Error preparando la consulta.'));
    $mysqli->close();
    exit;
}

$stmt->bind_param('sis', $usuario, $idPerfil, $consulta);

if ($stmt->execute()) {
    $id = $stmt->insert_id;
    echo json_encode(array('ok' => true, 'id' => $id));
} else {
    echo json_encode(array('ok' => false, 'error' => 'No se pudo guardar la consulta.'));
}

$stmt->close();
$mysqli->close();
?>
