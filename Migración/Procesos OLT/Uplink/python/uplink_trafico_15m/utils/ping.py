# ============================================================================
# utils/ping.py
# Proyecto: uplink_trafico_15m (compartido por los 3 procesos)
# Equivalente PHP: función ping_ip()
# Descripción: Hace ping a una IP y devuelve el token de pérdida de paquetes
#              (ej. '0%', '100%'), igual que el PHP. El caller decide si la OLT
#              es procesable comparando ese valor contra 100 (ver [PARIDAD-PHP]).
# Para modificar:
#   - Nº de paquetes enviados     → buscar [CONFIG] PING_COUNT
#   - Comando ping según SO       → buscar [RUTA]
# ============================================================================

import subprocess
import sys

# [CONFIG] Cantidad de paquetes ICMP a enviar. Equivalente PHP: $count = 3.
PING_COUNT = 3


def _comando_ping(ip, count):
    """Arma el comando ping según el sistema operativo.

    [RUTA] Linux (producción, igual que el PHP): ping <ip> -c <count>
           Windows (solo sirve para pruebas de plumbing en dev): ping <ip> -n <count>
    """
    flag = "-n" if sys.platform == "win32" else "-c"
    return ["ping", ip, flag, str(count)]


def _extraer_packet_loss(salida_ping):
    """Extrae el token de pérdida de paquetes de la salida de 'ping -c N' (Linux).

    Equivalente PHP:
        $datos = explode(',', $outputIP1);
        foreach ($datos as $x) {
            if (stristr($x, "packet loss")) {
                $aux = explode(' ', trim($x));
                $paquetes = $aux[0];
            }
        }
        return $paquetes;

    [PARIDAD-PHP] Solo reconoce el formato de salida de ping de Linux
                  ("... 0% packet loss, ..."). En Windows (dev) el texto es distinto
                  y no habrá coincidencia — ping.py en Windows solo sirve para
                  verificar que el subprocess se ejecuta, no para paridad de datos.
    """
    paquetes = ""
    for segmento in salida_ping.split(","):
        if "packet loss" in segmento.lower():
            aux = segmento.strip().split(" ")
            paquetes = aux[0]
    return paquetes


def ping_ip(ip, count=PING_COUNT):
    """Hace ping a la IP y devuelve el token de pérdida de paquetes (ej. '0%', '100%'),
    tal como el PHP ping_ip(). Cadena vacía si no se pudo determinar.

    Equivalente PHP:
        $commandA = "ping $ip1 -c $count";
        $outputIP1 = shell_exec($commandA);
        return ... (ver _extraer_packet_loss)
    """
    resultado = subprocess.run(
        _comando_ping(ip, count),
        capture_output=True,
        text=True,
    )
    return _extraer_packet_loss(resultado.stdout)


def es_alcanzable(valor_packet_loss):
    """Determina si una OLT es alcanzable a partir del valor devuelto por ping_ip().

    Equivalente PHP (patrón usado en MA5600T y "otros"):
        if(trim($y)){ if($y < 100){ ...procesar... } }
    Es decir: hay un valor de packet-loss reconocible Y es menor a 100%.

    Función compartida por los workers MA5600T y "otros" (ambos hacen este
    mismo gate de ping antes de intentar telnet) — evita duplicar la lógica
    de parseo del porcentaje en cada proceso.
    """
    if not valor_packet_loss.strip():
        return False
    try:
        numero = float(valor_packet_loss.rstrip("%") or 0)
    except ValueError:
        return False
    return numero < 100
