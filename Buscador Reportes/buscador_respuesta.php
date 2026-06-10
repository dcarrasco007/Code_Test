<?php
/*
 * [BUSCADOR CONSULTA] Devuelve el estado y la respuesta de una consulta por su id.
 * El front lo llama periodicamente (polling) hasta que estado = 'listo' o 'error'.
 * Solo deja leer las consultas creadas por el mismo usuario en sesion.
 * Valida la sesion/perfil: si no hay usuario o id_perfil, aborta y pide login.
 * Compatible con PHP 5.3.
 */

// >>> CONFIGURA AQUI la URL de tu login (relativa a esta carpeta) <<<
$LOGIN_URL = '../menu/login.php';

include_once('../perfiles/getPerfiles.php');
include_once('../../conexion/conexion_db.php');

if (session_id() == '') { @session_start(); }

header('Content-Type: application/json; charset=utf-8');

$usuario  = function_exists('getUser')     ? getUser()     : '';
$idPerfil = function_exists('getIdPerfil') ? getIdPerfil() : '';

// --- Validacion de sesion / perfil: si falta algo, abortar y enviar a login ---
if ($usuario === '' || $usuario === null || $idPerfil === '' || $idPerfil === null || intval($idPerfil) <= 0) {
    echo json_encode(array(
        'ok'       => false,
        'auth'     => false,
        'redirect' => $LOGIN_URL,
        'error'    => 'Sesion no iniciada o sin perfil.'
    ));
    exit;
}

$id = 0;
if (isset($_POST['id']))      { $id = intval($_POST['id']); }
else if (isset($_GET['id']))  { $id = intval($_GET['id']); }

if ($id <= 0) {
    echo json_encode(array('ok' => false, 'error' => 'id invalido.'));
    exit;
}

$mysqli = @new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
if ($mysqli->connect_errno) {
    echo json_encode(array('ok' => false, 'error' => 'No se pudo conectar a la base de datos.'));
    exit;
}
$mysqli->set_charset('utf8');

$sql  = "SELECT estado, respuesta, fecha_respuesta
         FROM OLT_BUSCADOR_CONSULTAS
         WHERE id = ? AND usuario = ?";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo json_encode(array('ok' => false, 'error' => 'Error preparando la consulta.'));
    $mysqli->close();
    exit;
}

$stmt->bind_param('is', $id, $usuario);
$stmt->execute();
$stmt->bind_result($estado, $respuesta, $fecha_respuesta);

if ($stmt->fetch()) {
    echo json_encode(array(
        'ok'        => true,
        'id'        => $id,
        'estado'    => $estado,
        'respuesta' => $respuesta,
        'fecha'     => $fecha_respuesta
    ));
} else {
    echo json_encode(array('ok' => false, 'error' => 'Consulta no encontrada.'));
}

$stmt->close();
$mysqli->close();
?>
