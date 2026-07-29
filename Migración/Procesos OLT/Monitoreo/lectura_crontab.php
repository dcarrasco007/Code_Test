<?php
include ('/u01/crontab127/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

// ─── OBTENER CRONTAB ─────────────────────────────────────────────────────────
/**
 * Ejecuta `crontab -l` y retorna un array con las líneas.
 * Retorna array vacío si no hay crontab o el usuario no tiene permiso.
 */
mysqli_query($mysqli,"TRUNCATE TABLE MONITOREO_CRON_JOBS");
$ip_servidor="192.168.66.126";
$usuario_cron="reytdcarrasco";
$fecha_obtencion=date('Y-m-d H:i:s');
function getCrontabLines() {
    $output = array();
    $return = 0;
    exec('crontab -l 2>&1', $output, $return);

    // Si el usuario no tiene crontab, crontab -l devuelve código distinto de 0
    if ($return !== 0) {
        $msg = implode(' ', $output);
        // "no crontab for ..." es un mensaje normal, no un error crítico
        if (stripos($msg, 'no crontab') !== false) {
            return array();
        }
        die('[ERROR] No se pudo leer el crontab: ' . $msg . PHP_EOL);
    }

    return $output;
}

// ─── PARSEO DE LÍNEAS ────────────────────────────────────────────────────────
/**
 * Parsea una línea del crontab.
 *
 * Formatos soportados:
 *   1. Expresión estándar: minuto hora dia_mes mes dia_semana comando
 *   2. Macros: @reboot, @hourly, @daily, @weekly, @monthly, @yearly, @annually, @midnight
 *   3. Variables de entorno: CLAVE=VALOR
 *   4. Comentarios: # ...
 *   5. Líneas vacías
 *
 * Retorna array con los campos o NULL si la línea debe ignorarse (vacía).
 */
function parseCrontabLine($line) {
    $line = trim($line);

    // Línea vacía
    if ($line === '') {
        return null;
    }

    // Comentario
    if ($line[0] === '#') {
        return array(
            'tipo'           => 'comentario',
            'minuto'         => null,
            'hora'           => null,
            'dia_mes'        => null,
            'mes'            => null,
            'dia_semana'     => null,
            'macro'          => null,
            'comando'        => null,
            'variable_env'   => null,
            'linea_original' => $line,
            'activo'         => 0,
        );
    }

    // Variable de entorno: KEY=VALUE
    if (preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/i', $line, $m)) {
        return array(
            'tipo'           => 'variable',
            'minuto'         => null,
            'hora'           => null,
            'dia_mes'        => null,
            'mes'            => null,
            'dia_semana'     => null,
            'macro'          => null,
            'comando'        => null,
            'variable_env'   => $m[1] . '=' . $m[2],
            'linea_original' => $line,
            'activo'         => 1,
        );
    }

    // Macro: @reboot, @daily, etc.
    if (preg_match('/^(@\w+)\s+(.+)$/', $line, $m)) {
        return array(
            'tipo'           => 'macro',
            'minuto'         => null,
            'hora'           => null,
            'dia_mes'        => null,
            'mes'            => null,
            'dia_semana'     => null,
            'macro'          => $m[1],
            'comando'        => trim($m[2]),
            'variable_env'   => null,
            'linea_original' => $line,
            'activo'         => 1,
        );
    }

    // Expresión estándar de 5 campos: min hora dia_mes mes dia_semana comando
    // Cada campo puede ser: *, número, lista (1,2,3), rango (1-5), paso (*/5)
    $cronField = '[*0-9][0-9,\-\/\*]*';
    $pattern = '/^(' . $cronField . ')\s+(' . $cronField . ')\s+(' . $cronField . ')\s+(' . $cronField . ')\s+(' . $cronField . ')\s+(.+)$/';

    if (preg_match($pattern, $line, $m)) {
        return array(
            'tipo'           => 'tarea',
            'minuto'         => $m[1],
            'hora'           => $m[2],
            'dia_mes'        => $m[3],
            'mes'            => $m[4],
            'dia_semana'     => $m[5],
            'macro'          => null,
            'comando'        => trim($m[6]),
            'variable_env'   => null,
            'linea_original' => $line,
            'activo'         => 1,
        );
    }

    // Línea no reconocida → se guarda como desconocida para no perder info
    return array(
        'tipo'           => 'desconocido',
        'minuto'         => null,
        'hora'           => null,
        'dia_mes'        => null,
        'mes'            => null,
        'dia_semana'     => null,
        'macro'          => null,
        'comando'        => null,
        'variable_env'   => null,
        'linea_original' => $line,
        'activo'         => 0,
    );
}

// ─── DESCRIPCIÓN LEGIBLE DE LA EXPRESIÓN CRON ────────────────────────────────
/**
 * Convierte los 5 campos cron en una descripción humana básica en español.
 */
function cronDescription($min, $hora, $diaMes, $mes, $diaSem) {
    $meses = array(
        1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
        5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
        9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
    );
    $dias = array(
        0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',
        4=>'Jueves',5=>'Viernes',6=>'Sábado',7=>'Domingo'
    );

    $partes = array();

    // Minuto
    if ($min === '*')            $partes[] = 'cada minuto';
    elseif (strpos($min,'/')!==false) {
        $p = explode('/', $min);
        $partes[] = 'cada ' . $p[1] . ' minuto(s)';
    } else                       $partes[] = 'al minuto ' . $min;

    // Hora
    if ($hora === '*')           {} // ya cubierto arriba
    elseif (strpos($hora,'/')!==false) {
        $p = explode('/', $hora);
        $partes[] = 'cada ' . $p[1] . ' hora(s)';
    } else                       $partes[] = 'a las ' . str_pad($hora,2,'0',STR_PAD_LEFT) . ':' . str_pad($min,2,'0',STR_PAD_LEFT);

    // Día del mes
    if ($diaMes !== '*')         $partes[] = 'el día ' . $diaMes . ' del mes';

    // Mes
    if ($mes !== '*') {
        $m = (int)$mes;
        $partes[] = 'en ' . (isset($meses[$m]) ? $meses[$m] : $mes);
    }

    // Día de la semana
    if ($diaSem !== '*') {
        $d = (int)$diaSem;
        $partes[] = 'los ' . (isset($dias[$d]) ? $dias[$d] : $diaSem);
    }

    return ucfirst(implode(', ', $partes));
}

// ─── INSERCIÓN EN BASE DE DATOS ───────────────────────────────────────────────
/**
 * Inserta un registro parseado en la tabla cron_jobs.
 * Usa INSERT IGNORE para evitar duplicados por linea_original.
 */
function insertarRegistro($conn, $registro, $ip_servidor, $usuario_cron, $fecha_obtencion) {
    $ip=$ip_servidor;
    $usuario=$usuario_cron;
    $descripcion = null;
    if ($registro['tipo'] === 'tarea') {
        $descripcion = cronDescription(
            $registro['minuto'],
            $registro['hora'],
            $registro['dia_mes'],
            $registro['mes'],
            $registro['dia_semana']
        );
    } elseif ($registro['tipo'] === 'macro') {
        $macroDesc = array(
            '@reboot'    => 'Al iniciar el servidor',
            '@hourly'    => 'Cada hora (minuto 0)',
            '@daily'     => 'Cada día a medianoche',
            '@midnight'  => 'Cada día a medianoche',
            '@weekly'    => 'Cada semana (domingo a medianoche)',
            '@monthly'   => 'El primer día de cada mes',
            '@yearly'    => 'El 1 de enero a medianoche',
            '@annually'  => 'El 1 de enero a medianoche',
        );
        $macro = strtolower($registro['macro']);
        $descripcion = isset($macroDesc[$macro]) ? $macroDesc[$macro] : $registro['macro'];
    }

    $sql = "INSERT INTO MONITOREO_CRON_JOBS
                (tipo, minuto, hora, dia_mes, mes, dia_semana,
                 macro, comando, variable_env, descripcion,
                 linea_original, activo, servidor_host, usuario_cron)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo '[WARN] prepare() falló: ' . $conn->error . PHP_EOL;
        return false;
    }

    $stmt->bind_param(
        'sssssssssssiss',
        $registro['tipo'],
        $registro['minuto'],
        $registro['hora'],
        $registro['dia_mes'],
        $registro['mes'],
        $registro['dia_semana'],
        $registro['macro'],
        $registro['comando'],
        $registro['variable_env'],
        $descripcion,
        $registro['linea_original'],
        $registro['activo'],
        $ip,
        $usuario
    );

    $resultado = $stmt->execute();
    if (!$resultado) {
        echo '[WARN] execute() falló: ' . $stmt->error . PHP_EOL;
    }
    $stmt->close();
    return $resultado;
}

// ─── EJECUCIÓN PRINCIPAL ──────────────────────────────────────────────────────
echo '=== Importador de Crontab ===' . PHP_EOL;
echo 'Fecha: ' . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

$lineas = getCrontabLines();

if (empty($lineas)) {
    echo 'No se encontraron entradas en el crontab.' . PHP_EOL;
    $conn->close();
    exit(0);
}

echo 'Líneas leídas del crontab: ' . count($lineas) . PHP_EOL . PHP_EOL;

$insertadas  = 0;
$ignoradas   = 0;
$errores     = 0;

foreach ($lineas as $numLinea => $linea) {
    $registro = parseCrontabLine($linea);

    if ($registro === null) {
        $ignoradas++;
        continue;
    }

    $ok = insertarRegistro($mysqli, $registro, $ip_servidor, $usuario_cron, $fecha_obtencion);

    if ($ok) {
        $insertadas++;
        echo sprintf(
            '[OK]  Línea %02d [%-11s] %s' . PHP_EOL,
            $numLinea + 1,
            $registro['tipo'],
            $registro['linea_original']
        );
    } else {
        $errores++;
        echo sprintf(
            '[ERR] Línea %02d [%-11s] %s' . PHP_EOL,
            $numLinea + 1,
            $registro['tipo'],
            $registro['linea_original']
        );
    }
}

echo PHP_EOL . '─────────────────────────────────────' . PHP_EOL;
echo 'Insertadas : ' . $insertadas . PHP_EOL;
echo 'Ignoradas  : ' . $ignoradas  . PHP_EOL;
echo 'Errores    : ' . $errores    . PHP_EOL;

mysqli_close($mysqli);
echo PHP_EOL . 'Proceso finalizado.' . PHP_EOL;