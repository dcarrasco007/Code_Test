<?php
/*
 * Configuración del módulo Eliminar Archivos.
 * Servidor 120 (contingencia_v3): el módulo va en /var/www/html/Eliminar_Archivos.
 * Compatible con PHP 5.3+.
 */

// Raíz del portal: las rutas del mantenedor son relativas a esta carpeta.
define('EA_RAIZ', dirname(dirname(__DIR__)));

// Conexión a BD del portal (define $host144_geret, $user144_geret, $pass144_geret).
define('EA_CONEXION', EA_RAIZ . '/conexion/conexion_db.php');

// Máximo de archivos a listar en una carga (evita respuestas gigantes).
define('EA_MAX_ARCHIVOS', 5000);

// Únicas extensiones que se listan y se pueden eliminar.
function ea_extensiones()
{
    return array('csv', 'xls', 'xlsx');
}

// Carpetas de primer nivel que no se pueden registrar como ruta.
function ea_carpetas_protegidas()
{
    return array('conexion', 'Lib', 'js', 'menu', 'perfiles', 'usuarios', 'mantenedores',
        'Select2', 'Highcharts-4.2.3', 'lee_web', 'ip_safe', 'extras', 'Eliminar_Archivos');
}

// Acceso total: ven el mantenedor de rutas (agregar, activar, desactivar y quitar),
// el log de movimientos y eliminan archivos.
function ea_usuarios_total()
{
    return array('admin');
}

// Solo eliminar: ven la lista de archivos de las rutas activas y los eliminan de a uno.
// No ven el mantenedor de rutas ni el log. Si un usuario está en las dos listas, manda la de acceso total.
function ea_usuarios_eliminar()
{
    return array();
}
