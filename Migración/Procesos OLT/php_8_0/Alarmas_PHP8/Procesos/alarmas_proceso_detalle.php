<?php
date_default_timezone_set('America/Santiago');
/**
 * Etapa 1 (bloqueos BD, 2026-10-28). Plan: Pendientes/Bloqueos_BD_2026-10/03.1 - Plan implementacion etapa 1
 *  1. Lee OLT_SERVER y cierra la conexión antes de lanzar los hijos.
 *  2. Lanza un hijo por OLT con nohup timeout 450; cada hijo deja <OLT>.json en tmp/lote_<id>/ sin tocar la BD.
 *  3. Espera 3 min y revisa cada 1 min, hasta 8 min como máximo.
 *  4. Carga lo que haya llegado en 1 conexión y 1 transacción (DELETE + INSERT multi-fila, sin TRUNCATE).
 *     Si la carga falla hace ROLLBACK: las tablas quedan con los datos de la corrida anterior.
 *
 * Modo prueba: php alarmas_proceso_detalle.php 3  -> solo 3 OLTs y esperas de 20 s / 10 s.
 * Ojo: igual reemplaza las tablas con los datos de esas OLTs.
 */
const TIMEOUT_HIJO   = 450;    // timeout de cada hijo; menor que la espera máxima del padre (180 + 5 x 60 = 480 s)
const ESPERA_INICIAL = 180;    // primera espera después de lanzar el último hijo
const INTERVALO      = 60;     // espera entre revisiones
const MAX_CICLOS     = 5;      // revisiones extra después de la inicial -> 3 + 5 = 8 min máximo
const TAM_BLOQUE     = 1000;   // filas por INSERT multi-fila (todos dentro de la misma transacción)
const DIR_LOTES      = '/var/www/procesos/php/Alarmas_PHP8/tmp';
const HIJO           = '/var/www/procesos/php/Alarmas_PHP8/Procesos/alarmas_proceso_detalle_exped.php';
const DIR_LOGS       = '/var/www/procesos/php/Alarmas_PHP8/logs/Alarmas_detalle';

//include ('../../conexion/conexion_db.php');
echo "Inicio: ".date('d-m-Y H:i:s')."\n";
include ('/var/www/procesos/php/conexion/conexion_db.php');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);   // errores de mysqli como excepción (por defecto desde 8.1)

$limite = isset($argv[1]) ? (int)$argv[1] : 0;
$espera_inicial = $limite ? 20 : ESPERA_INICIAL;
$intervalo = $limite ? 10 : INTERVALO;

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
/* Etapa 1: el monitoreo queda fuera del diseño, se deja comentado
//-----------MONITOREO
$proceso_id=12;//ID DEL PROCESO YA REGISTRADO
$fecha_monitor=date('Y-m-d H:i:s');
$sql_Monitor = "INSERT INTO MONITOREO_PROCESOS_EJECUCIONES
(proceso_id,fecha_inicio,estado,fecha_registro,cantidad_subprocesos,cantidad_subprocesos_completados,mensaje)
VALUES
($proceso_id,'$fecha_monitor','RUNNING','$fecha_monitor',0,0,'Porceso Primario')";
if (!$mysqli->query($sql_Monitor)) {
    die("Error insert padre: " . $mysqli->error);
}
$parent_id = $mysqli->insert_id;
// el lote será el mismo id del padre
$lote_id = $parent_id;
sleep(1);
//--------------------
*/
$lote_id = date('YmdHi');   // antes salía del insert_id del monitoreo
echo"\nlote: ".$lote_id."\n";
/* Etapa 1: sin TRUNCATE (bloqueaba las pantallas y las dejaba vacías). Se reemplaza por DELETE + INSERT
   en una transacción al final del proceso.
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_CRITICAL");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_MAJOR");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_MINOR");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_ALARMAS_WARNING");
mysqli_query($mysqli,"TRUNCATE TABLE OLT_CANTIDAD_ALARMAS_DETALLE");
*/

$sql_ip = "SELECT ip,server,region FROM OLT_SERVER" . ($limite ? " LIMIT $limite" : '');
$olts = $mysqli->query($sql_ip)->fetch_all(MYSQLI_NUM);
mysqli_close($mysqli);   // los hijos no usan la BD y el padre no la necesita hasta la carga

// Carpetas de lotes de más de 1 día que hayan quedado de corridas fallidas
foreach (glob(DIR_LOTES . '/lote_*', GLOB_ONLYDIR) ?: [] as $viejo) {
    if (filemtime($viejo) < time() - 86400) {
        array_map('unlink', glob("$viejo/*"));
        rmdir($viejo);
    }
}

$dir = DIR_LOTES . "/lote_$lote_id";
if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
    die("Error: no se pudo crear $dir\n");
}

foreach ($olts as [$ip, $server, $region]) {
    $region = str_replace(' ','_',$region);
    shell_exec("nohup timeout " . TIMEOUT_HIJO . " php -f " . HIJO . " $ip $server $region $lote_id $dir > " . DIR_LOGS . "/$server.log 2>&1 &");
}
$esperados = count($olts);
echo date('H:i:s') . " $esperados hijos lanzados\n";

// Espera inicial y revisiones cada INTERVALO hasta que estén todas o se cumpla el máximo
sleep($espera_inicial);
for ($ciclo = 0; $ciclo <= MAX_CICLOS; $ciclo++) {
    $listos = count(glob("$dir/*.json"));
    echo date('H:i:s') . " revisión $ciclo: $listos/$esperados\n";
    if ($listos >= $esperados) break;
    if ($ciclo < MAX_CICLOS) sleep($intervalo);
}

$archivos = glob("$dir/*.json");
$llegaron = array_map(fn($f) => basename($f, '.json'), $archivos);
$faltan = array_diff(array_column($olts, 1), $llegaron);
if ($faltan) {
    echo "Sin respuesta (" . count($faltan) . "): " . implode(', ', $faltan) . "\n";
}

// Juntar los resultados de los hijos
$filas = ['CRITICAL' => [], 'MAJOR' => [], 'MINOR' => [], 'WARNING' => []];
$cantidades = [];
$con_error = [];
foreach ($archivos as $archivo) {
    $r = json_decode(file_get_contents($archivo), true);
    if (!is_array($r)) {
        $con_error[] = basename($archivo, '.json') . ' (json inválido)';
        continue;
    }
    foreach ($r['alarmas'] as $sev => $lista) {
        array_push($filas[$sev], ...$lista);
    }
    if ($r['cantidad']) {
        $cantidades[] = $r['cantidad'];
    }
    if ($r['estado'] != 'OK') {
        $con_error[] = "$r[equipo] ($r[estado])";
    }
}
if ($con_error) {
    echo "Con error (" . count($con_error) . "): " . implode(', ', $con_error) . "\n";
}
echo "Alarmas: CRITICAL " . count($filas['CRITICAL']) . ", MAJOR " . count($filas['MAJOR'])
   . ", MINOR " . count($filas['MINOR']) . ", WARNING " . count($filas['WARNING'])
   . ", resumen de " . count($cantidades) . " OLTs\n";

// Si no llegó ninguna OLT (caída de red, error general) no se toca nada: quedan los datos de la hora anterior
if (!$archivos) {
    echo "Error: ningún hijo dejó resultado, no se carga. Se deja $dir para revisar\n";
    echo "Fin: ".date('d-m-Y H:i:s')."\n";
    exit(1);
}

// Carga en una conexión y una transacción: las pantallas ven los datos anteriores hasta el COMMIT
$tablas = ['CRITICAL' => 'OLT_ALARMAS_CRITICAL', 'MAJOR' => 'OLT_ALARMAS_MAJOR',
           'MINOR' => 'OLT_ALARMAS_MINOR', 'WARNING' => 'OLT_ALARMAS_WARNING'];
$cargado = false;
$db = null;
try {
    $db = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
    $db->set_charset("utf8");
    $db->query('SET SESSION lock_wait_timeout = 10');
    $db->query('SET SESSION innodb_lock_wait_timeout = 10');
    $db->begin_transaction();

    foreach ($tablas as $tabla) {
        $db->query("DELETE FROM $tabla");
    }
    $db->query('DELETE FROM OLT_CANTIDAD_ALARMAS_DETALLE');

    foreach ($tablas as $sev => $tabla) {
        insertar_lote($db, $tabla, '(equipo,ip,puerta,alarma,fecha,fecha_proceso)', $filas[$sev]);
    }
    insertar_lote($db, 'OLT_CANTIDAD_ALARMAS_DETALLE',
        '(equipo,ip,region,cantidad_critical,cantidad_major,cantidad_minor,cantidad_warning,fecha)',
        $cantidades);

    $db->commit();
    $cargado = true;
    echo date('H:i:s') . " Carga OK\n";
} catch (Throwable $e) {
    if ($db) {
        try { $db->rollback(); } catch (Throwable $x) {}
    }
    echo "Error en la carga (ROLLBACK, quedan los datos anteriores): " . $e->getMessage() . "\n";
} finally {
    if ($db) {
        $db->close();
    }
}

// Si cargó, se borra el lote; si falló, se deja para revisar (se limpia solo al día siguiente)
if ($cargado) {
    array_map('unlink', glob("$dir/*"));
    rmdir($dir);
} else {
    echo "Se deja $dir para revisar\n";
}
/* Etapa 1: monitoreo comentado
//-----------MONITOREO FIN
$fecha_monitor=date('Y-m-d H:i:s');
$sql = "UPDATE MONITOREO_PROCESOS_EJECUCIONES
SET
    fecha_fin = '$fecha_monitor',
    duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, '$fecha_monitor'),
    estado = 'OK',
    cantidad_subprocesos=$nprocesos
WHERE id = $parent_id";

if (!$mysqli->query($sql)) {
    die("Error update padre: " . $mysqli->error);
}
//---------------------------------
mysqli_close($mysqli);
*/
echo "Fin: ".date('d-m-Y H:i:s')."\n";
exit($cargado ? 0 : 1);

/**
 * INSERT multi-fila en bloques de TAM_BLOQUE, con sentencias preparadas (las comillas en el texto de la
 * alarma ya no rompen el INSERT). Se llama dentro de la transacción: para la BD sigue siendo una sola carga.
 */
function insertar_lote(mysqli $db, string $tabla, string $cols, array $filas): void
{
    foreach (array_chunk($filas, TAM_BLOQUE) as $chunk) {
        $n    = count($chunk[0]);
        $fila = '(' . implode(',', array_fill(0, $n, '?')) . ')';
        $stmt = $db->prepare("INSERT INTO $tabla $cols VALUES " . implode(',', array_fill(0, count($chunk), $fila)));
        $stmt->bind_param(str_repeat('s', $n * count($chunk)), ...array_merge(...$chunk));
        $stmt->execute();
        $stmt->close();
    }
}

?>
