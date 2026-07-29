<?php
/**
 * expect_compat.php
 * ------------------------------------------------------------------------
 * Capa de compatibilidad en PHP PURO para reemplazar la extensión PECL
 * "expect" (que NO tiene soporte para PHP 8). Implementa las funciones y
 * constantes que usan los procesos de telnet, hablando el protocolo Telnet
 * directamente por socket (fsockopen), SIN ninguna extensión ni binario
 * externo. Funciona en PHP 8.0 nativo.
 *
 * API reproducida (misma semántica que usaba el código con la extensión):
 *   $stream = expect_popen("telnet <ip>");
 *   switch (expect_expectl($stream, [[patron, valor, tipo], ...], $match)) {...}
 *   expect_send($stream, "texto\n");     // reemplaza a fwrite($stream, ...)
 *   expect_set_timeout(2);               // reemplaza a ini_set("expect.timeout", 2)
 *
 * Tipos de patrón:
 *   EXP_EXACT  -> coincidencia de subcadena literal
 *   EXP_REGEXP -> expresión regular (PCRE; '.' NO cruza saltos de línea)
 *   (sin tipo) -> se trata como subcadena literal (equivalente para estos textos)
 *
 * Retornos especiales:
 *   EXP_TIMEOUT -> el equipo dejó de enviar datos durante 'timeout' segundos
 *                  (así es como el código sale del bucle: case EXP_TIMEOUT).
 *   EXP_EOF     -> definido por compatibilidad (aquí las desconexiones se
 *                  reportan como EXP_TIMEOUT para salir siempre limpio).
 * ------------------------------------------------------------------------
 * AJUSTES: si al probar contra una OLT real hiciera falta afinar, cambia
 * EXPECT_COMPAT_EOL (fin de línea enviado), los timeouts, o la negociación
 * Telnet en __expect_process_telnet().
 * ------------------------------------------------------------------------
 */

// ---- Constantes de la extensión (se definen sólo si no existen) ----
if (!defined('EXP_GLOB'))       define('EXP_GLOB', 1);
if (!defined('EXP_EXACT'))      define('EXP_EXACT', 2);
if (!defined('EXP_REGEXP'))     define('EXP_REGEXP', 3);
if (!defined('EXP_EOF'))        define('EXP_EOF', -11);
if (!defined('EXP_TIMEOUT'))    define('EXP_TIMEOUT', -2);
if (!defined('EXP_FULLBUFFER')) define('EXP_FULLBUFFER', -5);

// ---- Parámetros ajustables ----
if (!defined('EXPECT_COMPAT_EOL'))             define('EXPECT_COMPAT_EOL', "\r\n");        // "Enter" enviado al equipo
if (!defined('EXPECT_COMPAT_CONNECT_TIMEOUT')) define('EXPECT_COMPAT_CONNECT_TIMEOUT', 10); // seg. para conectar el socket
if (!defined('EXPECT_COMPAT_DEFAULT_TIMEOUT')) define('EXPECT_COMPAT_DEFAULT_TIMEOUT', 10); // seg. de silencio -> EXP_TIMEOUT

$GLOBALS['__expect_compat_timeout'] = EXPECT_COMPAT_DEFAULT_TIMEOUT;

if (!function_exists('expect_set_timeout')) {
    function expect_set_timeout($seconds) {
        // Equivale a ini_set("expect.timeout", $seconds): segundos de silencio
        // del equipo tras los cuales expect_expectl() devuelve EXP_TIMEOUT.
        $GLOBALS['__expect_compat_timeout'] = (float)$seconds;
        return true;
    }
}

if (!class_exists('ExpectCompatConn')) {
    class ExpectCompatConn {
        public $sock    = false;
        public $buffer  = '';
        public $timeout = 10.0;
        public $host    = '';
        public $port    = 23;
        // Estado del parser Telnet IAC entre lecturas (por si una secuencia
        // queda partida entre dos fread):
        public $iacState = 0;   // 0=normal 1=IAC 2=opcion 3=SB 4=SB+IAC
        public $iacVerb  = 0;
    }
}

if (!function_exists('expect_popen')) {
    function expect_popen($command) {
        $conn = new ExpectCompatConn();
        $conn->timeout = (float)$GLOBALS['__expect_compat_timeout'];

        // Extrae host [y puerto] de "telnet <host> [puerto]"
        $parts = preg_split('/\s+/', trim($command));
        $host = '';
        $port = 23;
        for ($i = 1; $i < count($parts); $i++) {   // salta la palabra "telnet"
            if ($parts[$i] === '') continue;
            if ($host === '')                    { $host = $parts[$i]; }
            elseif (ctype_digit($parts[$i]))     { $port = (int)$parts[$i]; }
        }
        $conn->host = $host;
        $conn->port = $port;

        $errno = 0; $errstr = '';
        $sock = @fsockopen($host, $port, $errno, $errstr, EXPECT_COMPAT_CONNECT_TIMEOUT);
        if ($sock === false) {
            // Equipo inalcanzable: se devuelve una conexión "muerta"; el primer
            // expect_expectl() devolverá EXP_TIMEOUT y el flujo terminará limpio
            // (mismo efecto que cuando el equipo no responde por telnet).
            $conn->sock = false;
            return $conn;
        }
        stream_set_blocking($sock, true);
        $conn->sock = $sock;
        return $conn;
    }
}

if (!function_exists('expect_send')) {
    function expect_send($conn, $data) {
        if (!($conn instanceof ExpectCompatConn) || $conn->sock === false) {
            return false;
        }
        // Traduce el salto de línea "\n" al fin de línea de Telnet (CR LF).
        $out = str_replace("\n", EXPECT_COMPAT_EOL, $data);
        // Escapa IAC (0xFF) por si apareciera en los datos (no ocurre con los
        // comandos actuales, pero es lo correcto en Telnet).
        $out = str_replace("\xFF", "\xFF\xFF", $out);
        return @fwrite($conn->sock, $out);
    }
}

if (!function_exists('expect_close')) {
    function expect_close($conn) {
        // Cierra la sesión telnet abierta por expect_popen().
        // Reemplaza a fclose($stream): con la extensión original $stream era un
        // recurso; aquí es un objeto ExpectCompatConn, así que fclose() fallaba.
        if ($conn instanceof ExpectCompatConn) {
            if (is_resource($conn->sock)) { @fclose($conn->sock); }
            $conn->sock = false;
            return true;
        }
        // Compatibilidad: si fuese un recurso real, ciérralo normalmente.
        if (is_resource($conn)) { return @fclose($conn); }
        return false;
    }
}

if (!function_exists('expect_expectl')) {
    function expect_expectl($conn, $patterns, &$match = null) {
        $match = array();
        if (!($conn instanceof ExpectCompatConn) || $conn->sock === false) {
            return EXP_TIMEOUT;
        }
        $deadline = microtime(true) + $conn->timeout;
        while (true) {
            // 1) ¿Ya hay coincidencia en el buffer acumulado?
            $res = __expect_match($conn, $patterns, $match);
            if ($res !== null) {
                return $res;
            }
            // 2) Esperar más datos hasta agotar el timeout de silencio.
            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                return EXP_TIMEOUT;
            }
            $r = array($conn->sock); $w = null; $e = null;
            $sec  = (int)floor($remaining);
            $usec = (int)(($remaining - $sec) * 1000000);
            $n = @stream_select($r, $w, $e, $sec, $usec);
            if ($n === false) {
                return EXP_TIMEOUT;   // error de socket -> salir limpio
            }
            if ($n === 0) {
                return EXP_TIMEOUT;   // silencio: el equipo dejó de enviar
            }
            $chunk = @fread($conn->sock, 4096);
            if ($chunk === '' || $chunk === false) {
                if (feof($conn->sock)) {
                    return EXP_TIMEOUT;  // conexión cerrada -> salir por timeout
                }
                continue;
            }
            $clean = __expect_process_telnet($conn, $chunk);
            if ($clean !== '') {
                $conn->buffer .= $clean;
                // Mientras lleguen datos, reiniciamos el contador de silencio.
                $deadline = microtime(true) + $conn->timeout;
            }
        }
    }
}

if (!function_exists('__expect_match')) {
    function __expect_match($conn, $patterns, &$match) {
        // Devuelve el valor del PRIMER patrón (en el orden de la lista) que
        // coincida en el buffer; consume el buffer hasta el final de la
        // coincidencia. Devuelve null si ninguno coincide todavía.
        foreach ($patterns as $p) {
            $pattern = $p[0];
            $value   = $p[1];
            $type    = isset($p[2]) ? $p[2] : EXP_GLOB;

            if ($type === EXP_REGEXP) {
                if (@preg_match('/' . $pattern . '/', $conn->buffer, $m, PREG_OFFSET_CAPTURE)) {
                    $matchedText  = $m[0][0];
                    $endOffset    = $m[0][1] + strlen($m[0][0]);
                    $conn->buffer = substr($conn->buffer, $endOffset);
                    $match = array($matchedText);
                    return $value;
                }
            } else {
                // EXP_EXACT o sin tipo: subcadena literal.
                $pos = strpos($conn->buffer, $pattern);
                if ($pos !== false) {
                    $endOffset    = $pos + strlen($pattern);
                    $conn->buffer = substr($conn->buffer, $endOffset);
                    $match = array($pattern);
                    return $value;
                }
            }
        }
        return null;
    }
}

if (!function_exists('__expect_process_telnet')) {
    function __expect_process_telnet($conn, $chunk) {
        // Elimina las secuencias de control Telnet (IAC) del flujo entrante y
        // responde la negociación mínima para dejar una sesión de texto usable.
        $IAC = 255; $DONT = 254; $DO = 253; $WONT = 252; $WILL = 251;
        $SB  = 250; $SE = 240;
        $OPT_ECHO = 1; $OPT_SGA = 3;

        $out  = '';
        $resp = '';
        $len  = strlen($chunk);
        for ($i = 0; $i < $len; $i++) {
            $b = ord($chunk[$i]);
            switch ($conn->iacState) {
                case 0: // texto normal
                    if ($b === $IAC) { $conn->iacState = 1; }
                    else            { $out .= chr($b); }
                    break;
                case 1: // byte tras IAC
                    if ($b === $IAC) { $out .= chr($IAC); $conn->iacState = 0; } // IAC IAC = 0xFF literal
                    elseif ($b === $SB) { $conn->iacState = 3; }
                    elseif ($b === $WILL || $b === $WONT || $b === $DO || $b === $DONT) {
                        $conn->iacVerb = $b; $conn->iacState = 2;
                    } else { $conn->iacState = 0; } // otros comandos de 2 bytes: se ignoran
                    break;
                case 2: // opción tras WILL/WONT/DO/DONT
                    $opt = $b;
                    if ($conn->iacVerb === $WILL) {
                        // El equipo ofrece hacer algo: aceptamos ECHO y SGA, el resto no.
                        if ($opt === $OPT_ECHO || $opt === $OPT_SGA) $resp .= chr($IAC).chr($DO).chr($opt);
                        else                                          $resp .= chr($IAC).chr($DONT).chr($opt);
                    } elseif ($conn->iacVerb === $WONT) {
                        $resp .= chr($IAC).chr($DONT).chr($opt);
                    } elseif ($conn->iacVerb === $DO) {
                        // El equipo nos pide activar una opción: no la activamos.
                        $resp .= chr($IAC).chr($WONT).chr($opt);
                    } elseif ($conn->iacVerb === $DONT) {
                        $resp .= chr($IAC).chr($WONT).chr($opt);
                    }
                    $conn->iacState = 0;
                    break;
                case 3: // dentro de subnegociación (SB), esperando IAC
                    if ($b === $IAC) { $conn->iacState = 4; }
                    break;
                case 4: // subnegociación: IAC visto
                    if ($b === $SE) { $conn->iacState = 0; } // IAC SE = fin de subnegociación
                    else            { $conn->iacState = 3; }
                    break;
            }
        }
        if ($resp !== '') { @fwrite($conn->sock, $resp); }
        return $out;
    }
}
