# ============================================================================
# utils/telnet_olt.py
# Proyecto: uplink_trafico_15m (compartido por los 3 procesos)
# Equivalente PHP: funciones estado_equipo/estado_equipo2/3/4 + verifica_equipo
#                  (expect_popen/expect_expectl)
# Descripción: Autómata telnet (pexpect) para leer el CLI de las OLT Huawei:
#              login → enable → config → por cada (interface, índice de puerto):
#              interface <tipo> 0/N → display port traffic X → capturar bloque
#              (manejando paginado 'More' y prompts de confirmación) → blanco →
#              quit → ... → logout. Devuelve el texto crudo capturado completo,
#              listo para pasar a utils/parser_trafico.parsear_trafico().
#
# DISEÑO — por qué esto no es una traducción línea-a-línea del PHP:
#   El PHP implementa el envío de comandos de forma "fire-and-forget" (escribe
#   varios fwrite() seguidos sin leer la respuesta entre medio) y deja que el
#   patrón catch-all ".*\n" (case SALTO) vaya acumulando línea por línea todo lo
#   que llega, en iteraciones sucesivas del while(). El resultado OBSERVABLE
#   (qué comandos llegan a la OLT, en qué orden, y qué texto queda acumulado en
#   $uname) es idéntico a hacer, para cada puerto: enviar los 4 comandos
#   (interface, display, blanco, quit) y esperar UNA vez a que reaparezca el
#   prompt de config — que es como está escrito aquí, con pexpect (bloqueante,
#   más simple y verificable). También se eliminó un 'case' de patrón DUPLICADO
#   e inalcanzable del PHP (SHELL_CONFIG con la misma regex que SHELL_CONFIG1,
#   por lo que SHELL_CONFIG nunca se ejecutaba). `# [FIX-PHP]`
#
# Para modificar:
#   - Credenciales telnet         → .env (OLT_TELNET_USER / OLT_TELNET_PASS)
#   - Timeout de expect           → config/settings.py [CONFIG] TELNET_TIMEOUT
#   - Prompts/patrones del CLI    → buscar [CONFIG]
#   - Detalles de paridad con PHP → buscar [PARIDAD-PHP]
#
# NOTA: pexpect.spawn (pty real) solo funciona en Linux/Mac — igual que el PHP
#       en producción. En Windows (dev) se usa pexpect.popen_spawn.PopenSpawn,
#       que permite ejercitar esta máquina de estados contra un CLI simulado
#       (ver tests/fake_olt_cli.py) SIN una OLT real. Ninguna de las dos formas
#       reemplaza la validación real en producción (Fase 9).
# ============================================================================

import re
import sys

import pexpect

from config.settings import OLT_TELNET_PASS, OLT_TELNET_USER, TELNET_TIMEOUT
from utils.parser_trafico import normalizar_lineas, quitar_espacios

# ─── Prompts / patrones del CLI Huawei ───────────────────────────────────────
# [CONFIG] Los patrones EXACTOS del PHP (EXP_EXACT) se escapan como literales;
#          los patrones REGEX del PHP (EXP_REGEXP) se mantienen como regex.
_PROMPT_USER      = re.escape("User name:")
_PROMPT_PASSWORD  = re.escape("User password:")
_PROMPT_SHELL     = r".*>"          # modo usuario, ej. "OLT-XYZ>"
_PROMPT_ENABLE    = r"OLT.*#"       # modo enable, ej. "OLT-XYZ#"
_PROMPT_CONFIG    = r".*config.*.#"  # modo config, ej. "OLT-XYZ(config)#" — patrón tal cual el PHP
_PROMPT_MORE      = re.escape("---- More ( Press 'Q' to break ) ----")
_PROMPT_CR        = re.escape("{ <cr>||<K> }:")
_PROMPT_LOCK      = re.escape("{ lock<K>|unlock<K> }:")
# [FIX-PHP] El PHP tenía "log out?" sin escapar el '?' (regex especial); aquí se
#           escapa para que sea un literal, evitando ambigüedad de coincidencia.
_PROMPT_LOGOUT_CONFIRM = r".*Are you sure to log out\?.*:"

# [PARIDAD-PHP] Marcador de error de comando desconocido — equivalente al
#               verifica_equipo($texto) del PHP (idéntico en los 3 procesos).
MARCADOR_ERROR_CLI = "%Unknowncommand,theerrorlocatesat'^'"


def _spawn(cmd, timeout):
    """Crea el proceso telnet (o el CLI simulado en tests). [RUTA]

    Linux/Mac (producción): pexpect.spawn — pty real, igual que expect_popen.
    Windows (dev/tests):    pexpect.popen_spawn.PopenSpawn — sin pty, pero
                            suficiente para ejercitar la máquina de estados
                            contra tests/fake_olt_cli.py.
    """
    if sys.platform == "win32":
        from pexpect.popen_spawn import PopenSpawn
        return PopenSpawn(cmd, timeout=timeout, encoding="utf-8")
    return pexpect.spawn(cmd[0], cmd[1:], timeout=timeout, encoding="utf-8")


def _cerrar(child):
    """Cierra el proceso telnet/simulado, tolerando que el backend no tenga
    exactamente los mismos métodos (spawn vs PopenSpawn)."""
    try:
        child.close(force=True)
    except AttributeError:
        try:
            child.kill(9)
        except Exception:
            pass


def _conectar(ip, timeout, comando_conexion=None):
    """Abre la sesión telnet, hace login y sube a modo enable + config.
    Devuelve el proceso ya posicionado en el prompt de config.

    Equivalente PHP: expect_popen("telnet ".$server) + casos USER/PASSWORD/
    SHELL/SHELL2 (login, "enable", "config").
    """
    cmd = comando_conexion or ["telnet", ip]
    child = _spawn(cmd, timeout)

    child.expect([_PROMPT_USER], timeout=timeout)
    child.sendline(OLT_TELNET_USER)

    child.expect([_PROMPT_PASSWORD], timeout=timeout)
    child.sendline(OLT_TELNET_PASS)

    child.expect([_PROMPT_SHELL], timeout=timeout)
    child.sendline("enable")

    child.expect([_PROMPT_ENABLE], timeout=timeout)
    child.sendline("config")

    child.expect([_PROMPT_CONFIG], timeout=timeout)
    return child


def _leer_bloque(child, timeout):
    """Lee hasta volver al prompt de config, manejando el paginado 'More' y los
    prompts de confirmación ({ <cr>||<K> }: / { lock<K>|unlock<K> }:) — igual
    que los casos ESPACIO/ESPACIO2 del PHP. Devuelve todo el texto intermedio
    (equivalente a lo que el PHP va acumulando en $uname vía el catch-all SALTO).
    """
    patrones = [_PROMPT_CONFIG, _PROMPT_MORE, _PROMPT_CR, _PROMPT_LOCK]
    texto = ""
    while True:
        idx = child.expect(patrones, timeout=timeout)
        texto += child.before + child.after

        if idx == 0:  # de vuelta en el prompt de config: bloque completo
            return texto
        elif idx == 1:  # 'More': enviar espacio para continuar  [PARIDAD-PHP] case ESPACIO
            child.send(" ")
        else:  # idx 2 o 3: prompt de confirmación → enviar salto de línea  [PARIDAD-PHP] case ESPACIO2
            child.sendline("")


def _desconectar(child, timeout):
    """Sale de config y de enable, confirmando el logout.
    Equivalente PHP: SHELL2 (segunda vez, cantConfig>0) + case SALIR.
    """
    try:
        child.sendline("quit")
        child.expect([_PROMPT_ENABLE], timeout=timeout)
        child.sendline("quit")
        child.expect([_PROMPT_LOGOUT_CONFIRM], timeout=timeout)
        child.sendline("y")
    except (pexpect.TIMEOUT, pexpect.EOF):
        pass  # equivalente PHP: EXP_TIMEOUT / EXP_EOF → no es un error fatal
    finally:
        _cerrar(child)


def leer_trafico_puertos(ip, comandos, timeout=None, comando_conexion=None):
    """Se conecta por telnet a la OLT y ejecuta la secuencia de comandos indicada,
    devolviendo el texto crudo capturado (equivalente al $uname del PHP).

    ip:       dirección IP de la OLT.
    comandos: lista de tuplas (interface_cmd, n_puertos), en el ORDEN en que
              deben ejecutarse. Para cada tupla se ejecuta
              'display port traffic X' para X en 0..n_puertos-1.
              Ej.: [("interface eth 0/16", 2), ("interface eth 0/17", 1)]
              replica el for($x=0;$x<$puertoNN;$x++){ interface...; display... }
              del PHP, en el orden de prioridad que decide el worker (Fases 5/6/7).
    timeout:  timeout de expect por paso (por defecto settings.TELNET_TIMEOUT).
    comando_conexion: comando a spawnear en vez de ["telnet", ip] — usado por
              los tests para apuntar a tests/fake_olt_cli.py en lugar de un
              telnet real.
    """
    timeout = timeout or TELNET_TIMEOUT
    child = _conectar(ip, timeout, comando_conexion)
    texto_total = ""

    try:
        for interface_cmd, n_puertos in comandos:
            for x in range(n_puertos):
                child.sendline(interface_cmd)
                child.sendline(f"display port traffic {x}")
                child.sendline("")   # línea en blanco, igual que el PHP
                child.sendline("quit")
                texto_total += _leer_bloque(child, timeout)
    finally:
        _desconectar(child, timeout)

    return texto_total


def respuesta_valida(texto_crudo):
    """Indica si la sesión telnet fue exitosa (sin error de comando desconocido).

    Equivalente PHP: verifica_equipo($texto) — devuelve 1 si OK, 2 si hay error;
    aquí se expone como booleano (True = sin error). El worker decide si
    reintentar cuando esto da False (ver 'valida==2' en los PHP de origen).
    """
    for linea in normalizar_lineas(texto_crudo):
        if MARCADOR_ERROR_CLI in quitar_espacios(linea):
            return False
    return True
