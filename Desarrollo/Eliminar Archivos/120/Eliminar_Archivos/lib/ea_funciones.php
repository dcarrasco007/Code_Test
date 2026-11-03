<?php
/*
 * Funciones del módulo Eliminar Archivos: rutas permitidas, listado y borrado de
 * archivos csv / xls / xlsx. Compatible con PHP 5.3+ (sin [] cortos, ??, finally).
 */
require_once __DIR__ . '/ea_config.php';

// ---------------------------------------------------------------------------
// Conexión, sesión y acceso
// ---------------------------------------------------------------------------

function ea_conectar()
{
    include EA_CONEXION;
    $db = @new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
    if ($db->connect_errno) {
        return null;
    }
    $db->set_charset('utf8');
    return $db;
}

function ea_usuario()
{
    if (session_id() == '') {
        session_start();
    }
    return isset($_SESSION['user_olt']) ? trim($_SESSION['user_olt']) : '';
}

// Nivel de acceso del usuario: 'total', 'eliminar' o '' (sin acceso). Ver lib/ea_config.php.
function ea_nivel_acceso($usuario)
{
    if ($usuario == '') {
        return '';
    }
    if (in_array($usuario, ea_usuarios_total(), true)) {
        return 'total';
    }
    if (in_array($usuario, ea_usuarios_eliminar(), true)) {
        return 'eliminar';
    }
    return '';
}

// Token por sesión para que los POST solo vengan desde la ventana del módulo.
function ea_token()
{
    if (session_id() == '') {
        session_start();
    }
    if (empty($_SESSION['ea_token'])) {
        $_SESSION['ea_token'] = md5(uniqid(mt_rand(), true));
    }
    return $_SESSION['ea_token'];
}

// ---------------------------------------------------------------------------
// Utilidades de rutas
// ---------------------------------------------------------------------------

// "\ONNET\\Reporte_X/Archivos/" -> "ONNET/Reporte_X/Archivos"
function ea_normalizar_ruta($ruta)
{
    $ruta = str_replace('\\', '/', trim($ruta));
    $ruta = preg_replace('#/+#', '/', $ruta);
    $ruta = preg_replace('#^(\./)+#', '', $ruta);
    return trim($ruta, '/');
}

// Ruta absoluta con "/" como separador (también en Windows, para pruebas locales).
function ea_real($ruta)
{
    $r = realpath($ruta);
    return $r === false ? false : rtrim(str_replace('\\', '/', $r), '/');
}

function ea_dentro_de($ruta, $base)
{
    return strpos($ruta, $base . '/') === 0;
}

function ea_tiene_punto_punto($ruta)
{
    foreach (explode('/', str_replace('\\', '/', $ruta)) as $seg) {
        if ($seg == '..' || $seg == '.') {
            return true;
        }
    }
    return false;
}

function ea_extension_valida($nombre)
{
    return in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), ea_extensiones());
}

// Los nombres de archivo pueden venir en latin1: se pasan a UTF-8 solo para mostrarlos.
function ea_utf8($s)
{
    return preg_match('//u', $s) ? $s : utf8_encode($s);
}

// Corta un texto UTF-8 sin partir caracteres (mbstring puede no estar instalado).
function ea_cortar($s, $largo)
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $largo, 'UTF-8');
    }
    return preg_replace('/^(.{0,' . (int)$largo . '}).*$/us', '$1', $s);
}

// Devuelve '' si la ruta se puede registrar, o el motivo por el que no.
function ea_validar_ruta($ruta)
{
    if ($ruta == '') {
        return 'Escribe una ruta.';
    }
    if (strpos($ruta, "\0") !== false || strpos($ruta, ':') !== false) {
        return 'La ruta tiene caracteres no permitidos.';
    }
    if (ea_tiene_punto_punto($ruta)) {
        return 'La ruta no puede contener "." ni "..".';
    }
    $segs = explode('/', $ruta);
    if (count($segs) < 2) {
        return 'Agrega al menos una subcarpeta (ej: ONNET/Reporte_Splitter/Archivos), no una carpeta de primer nivel.';
    }
    foreach (ea_carpetas_protegidas() as $p) {
        if (strcasecmp($segs[0], $p) == 0) {
            return 'La carpeta ' . $segs[0] . ' está protegida y no se puede agregar.';
        }
    }
    $raiz = ea_real(EA_RAIZ);
    $abs = ea_real(EA_RAIZ . '/' . $ruta);
    if ($abs === false || !is_dir($abs)) {
        return 'La carpeta no existe en este servidor.';
    }
    if (!ea_dentro_de($abs, $raiz)) {
        return 'La carpeta queda fuera del portal.';
    }
    return '';
}

// ---------------------------------------------------------------------------
// Rutas (tabla OLT_ELIMINAR_ARCHIVOS_RUTAS)
// ---------------------------------------------------------------------------

function ea_rutas($db, $soloActivas = false)
{
    $sql = "SELECT ID, RUTA, DESCRIPCION, NOTA, ACTIVA, USUARIO, FECHA_REGISTRO
            FROM OLT_ELIMINAR_ARCHIVOS_RUTAS" . ($soloActivas ? " WHERE ACTIVA = 1" : "") . "
            ORDER BY RUTA";
    $res = $db->query($sql);
    $out = array();
    while ($res && ($r = $res->fetch_assoc())) {
        $r['ID'] = (int)$r['ID'];
        $r['ACTIVA'] = (int)$r['ACTIVA'];
        $out[] = $r;
    }
    return $out;
}

function ea_ruta($db, $id)
{
    $res = $db->query("SELECT ID, RUTA, DESCRIPCION, NOTA, ACTIVA FROM OLT_ELIMINAR_ARCHIVOS_RUTAS WHERE ID = " . (int)$id);
    return $res ? $res->fetch_assoc() : null;
}

function ea_agregar_ruta($db, $ruta, $descripcion, $nota, $usuario)
{
    $ruta = ea_normalizar_ruta($ruta);
    $error = ea_validar_ruta($ruta);
    if ($error != '') {
        return $error;
    }
    // Sin rutas repetidas ni anidadas: un archivo debe pertenecer a una sola ruta.
    foreach (ea_rutas($db) as $otra) {
        if (strcasecmp($otra['RUTA'], $ruta) == 0) {
            return 'Esa ruta ya está registrada.';
        }
        if (stripos($ruta . '/', $otra['RUTA'] . '/') === 0) {
            return 'Esa carpeta ya está incluida en la ruta ' . $otra['RUTA'] . '/.';
        }
        if (stripos($otra['RUTA'] . '/', $ruta . '/') === 0) {
            return 'Esa carpeta contiene la ruta ' . $otra['RUTA'] . '/, ya registrada. Quítala primero.';
        }
    }
    $r = $db->real_escape_string($ruta);
    if (trim($descripcion) == '') {
        $segs = explode('/', $ruta);
        $descripcion = implode(' / ', array_slice($segs, -2));
    }
    $sql = sprintf("INSERT INTO OLT_ELIMINAR_ARCHIVOS_RUTAS (RUTA, DESCRIPCION, NOTA, ACTIVA, USUARIO, FECHA_REGISTRO)
                    VALUES ('%s', '%s', '%s', 1, '%s', NOW())",
        $r,
        $db->real_escape_string(ea_cortar(trim($descripcion), 150)),
        $db->real_escape_string(ea_cortar(trim($nota), 255)),
        $db->real_escape_string($usuario));
    if (!$db->query($sql)) {
        return 'No se pudo guardar la ruta: ' . $db->error;
    }
    ea_log($db, 'AGREGAR_RUTA', $db->insert_id, $ruta, '', null, $usuario);
    return '';
}

function ea_cambiar_estado_ruta($db, $id, $activa, $usuario)
{
    $ruta = ea_ruta($db, $id);
    if (!$ruta) {
        return 'La ruta no existe.';
    }
    $activa = $activa ? 1 : 0;
    if (!$db->query("UPDATE OLT_ELIMINAR_ARCHIVOS_RUTAS SET ACTIVA = $activa WHERE ID = " . (int)$id)) {
        return 'No se pudo actualizar la ruta: ' . $db->error;
    }
    ea_log($db, $activa ? 'ACTIVAR_RUTA' : 'DESACTIVAR_RUTA', $id, $ruta['RUTA'], '', null, $usuario);
    return '';
}

function ea_quitar_ruta($db, $id, $usuario)
{
    $ruta = ea_ruta($db, $id);
    if (!$ruta) {
        return 'La ruta no existe.';
    }
    if (!$db->query("DELETE FROM OLT_ELIMINAR_ARCHIVOS_RUTAS WHERE ID = " . (int)$id)) {
        return 'No se pudo quitar la ruta: ' . $db->error;
    }
    ea_log($db, 'QUITAR_RUTA', $id, $ruta['RUTA'], '', null, $usuario);
    return '';
}

// ---------------------------------------------------------------------------
// Archivos
// ---------------------------------------------------------------------------

/*
 * Recorre las rutas activas con sus subcarpetas y devuelve los csv/xls/xlsx.
 * Cada archivo se identifica con el ID de su ruta y "k": su ruta relativa en
 * base64 (bytes originales, así se puede borrar aunque el nombre no sea UTF-8).
 */
function ea_listar_archivos($rutas)
{
    $archivos = array();
    $resumen = array();
    $truncado = false;
    $raiz = ea_real(EA_RAIZ);

    foreach ($rutas as $ruta) {
        $id = (int)$ruta['ID'];
        $resumen[$id] = array('archivos' => 0, 'bytes' => 0, 'error' => '');
        $base = ea_real(EA_RAIZ . '/' . $ruta['RUTA']);
        if ($base === false || !is_dir($base)) {
            $resumen[$id]['error'] = 'La carpeta no existe en este servidor.';
            continue;
        }
        if (!ea_dentro_de($base, $raiz)) {
            $resumen[$id]['error'] = 'La carpeta queda fuera del portal.';
            continue;
        }
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            foreach ($it as $f) {
                if (!$f->isFile() || $f->isLink() || !ea_extension_valida($f->getFilename())) {
                    continue;
                }
                if (count($archivos) >= EA_MAX_ARCHIVOS) {
                    $truncado = true;
                    break 2;
                }
                $abs = str_replace('\\', '/', $f->getPathname());
                $rel = substr($abs, strlen($base) + 1);
                $carpeta = dirname($rel);
                $tam = $f->getSize();
                $archivos[] = array(
                    'r' => $id,
                    'k' => base64_encode($rel),
                    'nombre' => ea_utf8($f->getFilename()),
                    'carpeta' => $carpeta == '.' ? '' : ea_utf8($carpeta) . '/',
                    'ext' => strtolower(pathinfo($rel, PATHINFO_EXTENSION)),
                    'tamano' => $tam,
                    'fecha' => date('Y-m-d H:i', $f->getMTime())
                );
                $resumen[$id]['archivos']++;
                $resumen[$id]['bytes'] += $tam;
            }
        } catch (Exception $e) {
            $resumen[$id]['error'] = 'No se pudo leer la carpeta.';
        }
    }
    return array('archivos' => $archivos, 'resumen' => $resumen, 'truncado' => $truncado);
}

/*
 * Elimina un archivo. Todo se vuelve a validar aquí, sin confiar en el navegador:
 * ruta activa, archivo dentro de ella (realpath), archivo normal y extensión permitida.
 */
function ea_eliminar_archivo($db, $idRuta, $k, $usuario)
{
    $ruta = ea_ruta($db, $idRuta);
    if (!$ruta || (int)$ruta['ACTIVA'] != 1) {
        return 'La ruta no existe o está inactiva.';
    }
    $base = ea_real(EA_RAIZ . '/' . $ruta['RUTA']);
    if ($base === false || !ea_dentro_de($base, ea_real(EA_RAIZ))) {
        return 'La carpeta de la ruta no existe en este servidor.';
    }
    $rel = base64_decode($k, true);
    if ($rel === false || $rel == '' || strpos($rel, "\0") !== false || ea_tiene_punto_punto($rel)) {
        return 'Archivo no válido.';
    }
    $sinResolver = $base . '/' . $rel;
    if (is_link($sinResolver)) {
        return 'No se eliminan accesos directos.';
    }
    $abs = ea_real($sinResolver);
    if ($abs === false || !is_file($abs)) {
        return 'El archivo ya no existe.';
    }
    if (!ea_dentro_de($abs, $base)) {
        return 'El archivo queda fuera de la ruta permitida.';
    }
    if (!ea_extension_valida($abs)) {
        return 'Solo se pueden eliminar archivos csv, xls o xlsx.';
    }
    $tam = filesize($abs);
    if (!@unlink($abs)) {
        return 'No se pudo eliminar el archivo (revisar permisos del usuario web).';
    }
    ea_log($db, 'ELIMINAR', $idRuta, $ruta['RUTA'], ea_utf8($rel), $tam, $usuario);
    return '';
}

// ---------------------------------------------------------------------------
// Log (tabla OLT_ELIMINAR_ARCHIVOS_LOG)
// ---------------------------------------------------------------------------

function ea_log($db, $accion, $idRuta, $ruta, $archivo, $tamano, $usuario)
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    $sql = sprintf("INSERT INTO OLT_ELIMINAR_ARCHIVOS_LOG (ACCION, ID_RUTA, RUTA, ARCHIVO, TAMANO, USUARIO, IP, SERVIDOR, FECHA)
                    VALUES ('%s', %s, '%s', '%s', %s, '%s', '%s', '%s', NOW())",
        $db->real_escape_string($accion),
        $idRuta === null ? 'NULL' : (int)$idRuta,
        $db->real_escape_string($ruta),
        $db->real_escape_string(ea_cortar($archivo, 500)),
        $tamano === null ? 'NULL' : sprintf('%.0f', $tamano),
        $db->real_escape_string($usuario),
        $db->real_escape_string($ip),
        $db->real_escape_string(substr(php_uname('n'), 0, 60)));
    $db->query($sql);
}

function ea_ultimos_log($db, $limite = 50)
{
    $res = $db->query("SELECT FECHA, ACCION, RUTA, ARCHIVO, TAMANO, USUARIO, SERVIDOR
                       FROM OLT_ELIMINAR_ARCHIVOS_LOG ORDER BY ID DESC LIMIT " . (int)$limite);
    $out = array();
    while ($res && ($r = $res->fetch_assoc())) {
        $out[] = $r;
    }
    return $out;
}
