<?php
// Registra que reporte abre un usuario desde el menu (usuario + url + hora).
// Se llama en segundo plano desde cambioPag() en menu_nuevo.php. Nunca debe
// romper ni demorar la carga del reporte: ante cualquier problema sale en silencio.
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
$usuario = isset($_SESSION['user_olt']) ? $_SESSION['user_olt'] : '';
// Libera el lock de sesion de inmediato para no bloquear la carga del reporte.
session_write_close();

if ($usuario === '' || !isset($_POST['u'])) {
    exit;
}

$url = trim($_POST['u']);
$pos = strpos($url, '?');
if ($pos !== false) {
    $url = substr($url, 0, $pos);
}
if (strlen($url) > 255 || !preg_match('/^[A-Za-z0-9_\.\-\/]+\.php$/', $url)) {
    exit;
}

include ('../../conexion/conexion_db.php');
$mysqli = @new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
if ($mysqli->connect_errno) {
    error_log("log_acceso.php conexion: " . $mysqli->connect_error);
    exit;
}
$mysqli->set_charset("utf8");

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$stmt = $mysqli->prepare("INSERT INTO OLT_LOG_ACCESO_REPORTE (fecha, usuario, url, ip) VALUES (NOW(), ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param('sss', $usuario, $url, $ip);
    if (!$stmt->execute()) {
        error_log("log_acceso.php insert: " . $stmt->error);
    }
    $stmt->close();
} else {
    error_log("log_acceso.php prepare: " . $mysqli->error);
}
$mysqli->close();
?>
