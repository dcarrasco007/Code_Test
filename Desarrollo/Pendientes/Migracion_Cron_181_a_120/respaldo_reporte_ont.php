<?php
date_default_timezone_set('America/Santiago');
/**
 * Respaldo diario del reporte ONT de ONNET y EQUIFIBER (servidor 120, contenedor podman portal-php53).
 * Exporta las tablas completas OLT_INFORMACION_ONT_DETALLE_COMPLETO_ONNET / _EQUIFIBER a CSV separado por ';'
 * en Archivos/ONNET y Archivos/EQUIFIBER, que es donde lo leen las pantallas ONT/Respaldo_ONT y unir_archivos.php.
 * Mismo formato de columnas y de nombre de archivo que la versión anterior (mysql_*), ahora con mysqli y
 * escribiendo fila a fila, sin cargar la tabla completa en memoria.
 *
 * Migrado desde el servidor 181 (cron eliminado) el 2026-10-28. Compatible con PHP 5.5.
 * Crontab del host 120 (usuario reyt_dcarrasco, podman rootless); el log queda en el host, fuera de la web:
 *   XDG_RUNTIME_DIR=/run/user/<uid>
 *   50 14 * * * /usr/bin/podman exec --user www-data portal-php53 php /var/www/html/ONNET/Reporte_ONNET_ONT/respaldo_reporte_ont.php >> $HOME/logs/geret/respaldo_reporte_ont.log 2>&1
 *
 * Opciones:
 *   --red=onnet,equifiber   redes a exportar (por defecto las 2)
 *   --retencion=90          borra los CSV de más de N días de esa carpeta (por defecto no borra nada)
 */
if (php_sapi_name() != 'cli') {
    header('HTTP/1.0 403 Forbidden');
    exit('Solo ejecución por línea de comandos');
}
set_time_limit(0);
ini_set('memory_limit', '512M');

include '/var/www/html/conexion/conexion_db.php';

define('DIR_ARCHIVOS', '/var/www/html/ONNET/Reporte_ONNET_ONT/Archivos/');

$redes = array(
    'onnet' => array('tabla' => 'OLT_INFORMACION_ONT_DETALLE_COMPLETO_ONNET', 'carpeta' => 'ONNET', 'prefijo' => 'Export_Info_ONT_ONNET_'),
    'equifiber' => array('tabla' => 'OLT_INFORMACION_ONT_DETALLE_COMPLETO_EQUIFIBER', 'carpeta' => 'EQUIFIBER', 'prefijo' => 'Export_Info_ONT_EQUIFIBER_'),
);

// Orden de salida igual al CSV anterior: ... MODELO; SOFTWARE_VERSION; FECHA_REGISTRO
$columnas = 'equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name, onu_alias, sn_mac,
             olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature, last_up_time, last_down_time,
             distancia, line_profile_name, service_profile_name, modelo, version_sw, fecha_registo';
$encabezado = 'EQUIPO;IP;FRAME_ID;SLOT_ID;PORT_ID;ONU_ID;ESTADO;REGION;COMUNA;ONU_NAME;ONU_ALIAS;SN_MAC;OLT_RX_ONU;'
            . 'TX_OPTICAL_POWER;RX_OPTICAL_POWER;VOLTAJE;TEMPERATURE;LAST_UP_TIME;LAST_DOWN_TIME;DISTANCIA;'
            . 'LINE_PROFILE_NAME;SERVICE_PROFILE_NAME;MODELO;SOFTWARE_VERSION;FECHA_REGISTRO';

$args = array();
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--([a-z]+)(?:=(.*))?$/', $a, $m)) {
        $args[$m[1]] = isset($m[2]) ? $m[2] : true;
    }
}
$elegidas = isset($args['red']) ? explode(',', strtolower($args['red'])) : array_keys($redes);
$retencion = isset($args['retencion']) ? (int)$args['retencion'] : 0;

function log_msg($msg)
{
    echo date('Y-m-d H:i:s') . ' ' . $msg . "\n";
}

/**
 * Exporta una tabla a CSV. Escribe primero a .tmp y renombra al final, para que el portal
 * nunca liste un archivo a medio escribir. Devuelve la cantidad de filas o false si falla.
 */
function exportar_red(mysqli $db, array $red, $columnas, $encabezado)
{
    $carpeta = DIR_ARCHIVOS . $red['carpeta'] . '/';
    if (!is_dir($carpeta) || !is_writable($carpeta)) {
        log_msg("ERROR {$red['carpeta']}: la carpeta $carpeta no existe o no tiene permiso de escritura");
        return false;
    }
    $ruta = $carpeta . $red['prefijo'] . date('Ymd_His') . '.csv';
    $tmp = $ruta . '.tmp';

    $res = $db->query("SELECT $columnas FROM Aden.{$red['tabla']}", MYSQLI_USE_RESULT);
    if (!$res) {
        log_msg("ERROR {$red['carpeta']}: consulta a {$red['tabla']}: " . $db->error);
        return false;
    }
    $f = fopen($tmp, 'w');
    if (!$f) {
        $res->free();
        log_msg("ERROR {$red['carpeta']}: no se pudo crear $tmp");
        return false;
    }

    fwrite($f, $encabezado . "\n");
    $filas = 0;
    while ($row = $res->fetch_row()) {
        fwrite($f, implode(';', $row) . "\n");
        $filas++;
    }
    $res->free();
    fclose($f);

    if ($db->errno) {
        unlink($tmp);
        log_msg("ERROR {$red['carpeta']}: lectura interrumpida: " . $db->error);
        return false;
    }
    if (!rename($tmp, $ruta)) {
        log_msg("ERROR {$red['carpeta']}: no se pudo renombrar $tmp");
        return false;
    }
    log_msg("Archivo guardado correctamente en: $ruta ($filas filas)");
    return $filas;
}

function limpiar_antiguos(array $red, $dias)
{
    $limite = time() - $dias * 86400;
    $borrados = 0;
    $archivos = glob(DIR_ARCHIVOS . $red['carpeta'] . '/' . $red['prefijo'] . '*.csv');
    foreach ($archivos ? $archivos : array() as $archivo) {
        if (filemtime($archivo) < $limite && unlink($archivo)) {
            $borrados++;
        }
    }
    log_msg("{$red['carpeta']}: $borrados archivo(s) de más de $dias días borrados");
}

$inicio = microtime(true);
log_msg('Inicio respaldo reporte ONT: ' . implode(', ', $elegidas));

$db = new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
if ($db->connect_errno) {
    log_msg('ERROR conexión BD: ' . $db->connect_error);
    exit(1);
}
$db->set_charset('utf8');

$errores = 0;
foreach ($elegidas as $clave) {
    if (!isset($redes[$clave])) {
        log_msg("ERROR red desconocida: $clave (usar onnet o equifiber)");
        $errores++;
        continue;
    }
    if (exportar_red($db, $redes[$clave], $columnas, $encabezado) === false) {
        $errores++;
    } elseif ($retencion > 0) {
        limpiar_antiguos($redes[$clave], $retencion);
    }
}
$db->close();

log_msg(sprintf('Fin (%.1f s, %d error(es))', microtime(true) - $inicio, $errores));
exit($errores ? 1 : 0);
