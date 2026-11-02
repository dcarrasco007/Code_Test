<?php
/*
 * Configuración del módulo Eliminar Archivos.
 * Servidor 181 (contingencia): el módulo va en la carpeta OLT/Eliminar_Archivos.
 * Compatible con PHP 5.3+.
 */

// Raíz del portal (carpeta OLT): las rutas del mantenedor son relativas a esta carpeta.
define('EA_RAIZ', dirname(dirname(__DIR__)));

// Conexión a BD del portal (define $host144_geret, $user144_geret, $pass144_geret).
// En el 181 la conexión está fuera de OLT (igual que ../../conexion desde los módulos).
define('EA_CONEXION', EA_RAIZ . '/../conexion/conexion_db.php');

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

// Usuarios con acceso al módulo.
function ea_usuarios_permitidos()
{
    return array('admin');
}

// Perfiles con acceso al módulo (id_perfil de OLT_PERFILES). Vacío = solo los usuarios de arriba.
function ea_perfiles_permitidos()
{
    return array();
}
