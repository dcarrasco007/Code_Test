<?php
/*
 * Endpoint AJAX del módulo Eliminar Archivos. Solo POST, responde JSON.
 * accion = cargar | agregar_ruta | estado_ruta | quitar_ruta | eliminar | log
 */
require_once __DIR__ . '/lib/ea_funciones.php';

header('Content-Type: application/json; charset=utf-8');

function ea_responder($datos)
{
    echo json_encode($datos);
    exit;
}

function ea_error($mensaje)
{
    ea_responder(array('ok' => false, 'error' => $mensaje));
}

function ea_post($campo)
{
    return isset($_POST[$campo]) ? trim($_POST[$campo]) : '';
}

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    ea_error('Método no permitido.');
}

$usuario = ea_usuario();
if ($usuario == '') {
    ea_error('Tu sesión expiró. Vuelve a ingresar al portal.');
}
if (ea_post('token') != ea_token()) {
    ea_error('La ventana quedó desactualizada. Recarga la opción del menú.');
}

$db = ea_conectar();
if (!$db) {
    ea_error('No se pudo conectar a la base de datos.');
}
if (!ea_tiene_acceso($db, $usuario)) {
    ea_error('Tu usuario no tiene acceso a esta opción.');
}

$error = '';
switch (ea_post('accion')) {
    case 'cargar':
        $rutas = ea_rutas($db);
        $activas = array();
        foreach ($rutas as $r) {
            if ($r['ACTIVA'] == 1) {
                $activas[] = $r;
            }
        }
        $lista = ea_listar_archivos($activas);
        foreach ($rutas as $i => $r) {
            $rutas[$i]['RESUMEN'] = isset($lista['resumen'][$r['ID']]) ? $lista['resumen'][$r['ID']] : null;
        }
        ea_responder(array(
            'ok' => true,
            'rutas' => $rutas,
            'archivos' => $lista['archivos'],
            'truncado' => $lista['truncado'],
            'max' => EA_MAX_ARCHIVOS
        ));
        break;

    case 'agregar_ruta':
        $error = ea_agregar_ruta($db, ea_post('ruta'), ea_post('descripcion'), ea_post('nota'), $usuario);
        break;

    case 'estado_ruta':
        $error = ea_cambiar_estado_ruta($db, (int)ea_post('id'), ea_post('activa') == '1', $usuario);
        break;

    case 'quitar_ruta':
        $error = ea_quitar_ruta($db, (int)ea_post('id'), $usuario);
        break;

    case 'eliminar':
        $error = ea_eliminar_archivo($db, (int)ea_post('id'), ea_post('k'), $usuario);
        break;

    case 'log':
        ea_responder(array('ok' => true, 'log' => ea_ultimos_log($db, 50)));
        break;

    default:
        $error = 'Acción no válida.';
}

if ($error != '') {
    ea_error($error);
}
ea_responder(array('ok' => true));
