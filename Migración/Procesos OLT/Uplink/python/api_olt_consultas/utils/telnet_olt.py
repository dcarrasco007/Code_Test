# ============================================================================
# utils/telnet_olt.py
# Proyecto: api_olt_consultas
# Descripción: Cliente Telnet asyncio PURO (sin pexpect ni binario externo) para
#              hablar con el CLI de las OLT Huawei. Es el puerto a asyncio de
#              dos piezas ya validadas en este mismo repo:
#                - expect_compat.php   → negociación Telnet IAC por socket
#                  (aquí: asyncio.open_connection + _procesar_iac), CRLF como
#                  fin de línea, expect_expectl() → _esperar().
#                - uplink_trafico_15m/utils/telnet_olt.py (pexpect) → los
#                  PROMPTS y la secuencia de navegación (login→enable→config,
#                  manejo de 'More'/confirmaciones, logout garantizado).
#
#              Por qué asyncio y no pexpect: la API sirve muchas peticiones
#              concurrentes en UN SOLO proceso; pexpect.spawn bloquea el hilo
#              en I/O de un pty. asyncio.open_connection permite que el
#              gobernador de sesiones (utils/sesiones_telnet.py) tenga varias
#              sesiones telnet abiertas a la vez sin hilos ni subprocesos.
#
# Contexto del CLI (ver docs/DIAGRAMAS.md → "Contextos del CLI Huawei"):
#   user (prompt '...>') → enable ('OLT...#') → config ('...config...#').
#   El prompt de 'config' también aparece en sub-contextos (config-if-eth-0/16,
#   config-vlan, etc.) porque TODOS contienen la palabra "config" — por eso un
#   solo regex laxo (_RE_CONFIG) sirve para "cualquier nivel dentro de config",
#   igual que hacía el PHP de origen (SHELL_CONFIG/SHELL_CONFIG1, misma regex).
#
# Detección de rechazo por límite de sesiones (requisito explícito del
# usuario — ver PLAN_API_OLT.md → "Control de sesiones telnet"): el equipo
# Huawei niega o cierra la 4ª sesión simultánea del mismo usuario. Se detecta
# por CUALQUIERA de estas 3 señales tras enviar user/password:
#   1. Mensaje "number of users has reached the upper limit" (u similar).
#   2. Mensaje "Reenter times have reached the upper limit".
#   3. El equipo cierra la conexión (EOF) en vez de mostrar el prompt de shell.
#   tests/fake_olt_cli.py simula las 3 sin tocar un equipo real.
#
# Para modificar:
#   - Prompts/patrones del CLI     → constantes _RE_*/_PROMPT_* de abajo
#   - Timeout de silencio          → app/config/settings.py TELNET_TIMEOUT_SEG
#   - Mensajes de rechazo conocidos → _PATRONES_RECHAZO_SESIONES
# ============================================================================

from __future__ import annotations

import asyncio
import re
import time
from dataclasses import dataclass
from typing import List, Optional, Sequence, Tuple

# ─── Prompts / patrones del CLI Huawei (mismo criterio que expect_compat.php:
#     EXP_EXACT → literal, EXP_REGEXP → regex sobre el buffer acumulado) ─────
_PROMPT_USER = "User name:"
_PROMPT_PASSWORD = "User password:"
_RE_SHELL = re.compile(r".*>")            # modo usuario, ej. "OLT-XYZ>"
_RE_ENABLE = re.compile(r"OLT.*#")        # modo enable, ej. "OLT-XYZ#"
_RE_CONFIG = re.compile(r".*config.*#")   # config Y cualquier sub-nivel (config-if-*, config-vlan, ...)
_TXT_MORE = "---- More ( Press 'Q' to break ) ----"
# Familia de prompts de confirmación con opciones entre llaves, ej.
# "{ <cr>||<K> }:", "{ lock<K>|unlock<K> }:", "{ <cr>|detail<K>|list<K> }:".
# En vez de listar cada variante observada en el PHP (siguen apareciendo
# nuevas combinaciones por comando), se usa un regex genérico — ya usado tal
# cual en uno de los procesos de origen (Temp_CPU_PHP8: "{.*.}:").
_RE_CONFIRMACION = re.compile(r"\{[^}]*\}:")
_RE_LOGOUT_CONFIRM = re.compile(r".*Are you sure to log out\?.*:")

# [PARIDAD-PHP] Marcador de comando no reconocido por el CLI — igual al
# verifica_equipo($texto) de los ~112 PHP de origen y a MARCADOR_ERROR_CLI de
# uplink_trafico_15m/utils/telnet_olt.py.
MARCADOR_ERROR_CLI = "%Unknowncommand,theerrorlocatesat'^'"

# Mensajes conocidos de rechazo por límite de sesiones (ver cabecera del
# archivo). No exhaustivo a propósito: la señal 3 (EOF) cubre variantes de
# firmware que no manden ninguno de estos textos.
_PATRONES_RECHAZO_SESIONES = (
    re.compile(r"number of users has reached the upper limit", re.IGNORECASE),
    re.compile(r"reenter times have reached the upper limit", re.IGNORECASE),
)

_EOL = "\r\n"  # EXPECT_COMPAT_EOL del PHP


class ErrorTelnet(Exception):
    """Timeout de silencio, o cualquier error de socket no relacionado con el
    límite de sesiones."""


class RechazoPorLimiteSesiones(ErrorTelnet):
    """El equipo rechazó/cerró el login: cupo de sesiones agotado para este
    (OLT, usuario). El gobernador (utils/sesiones_telnet.py) usa esto para
    abrir el circuit-breaker de esa OLT."""


@dataclass
class _Patron:
    etiqueta: str
    regex: re.Pattern


def _lit(etiqueta: str, texto: str) -> _Patron:
    return _Patron(etiqueta, re.compile(re.escape(texto)))


def _rx(etiqueta: str, patron: re.Pattern) -> _Patron:
    return _Patron(etiqueta, patron)


# Etiquetas sintéticas devueltas por _esperar() cuando no hay match de texto.
_EOF = "__EOF__"
_TIMEOUT = "__TIMEOUT__"


class ClienteTelnetOLT:
    """Una sesión Telnet a una OLT Huawei. Uso típico (ver
    utils/sesiones_telnet.py para el cupo/gobernador que debe envolver esto):

        cliente = ClienteTelnetOLT(ip, timeout=settings.TELNET_TIMEOUT_SEG)
        try:
            await cliente.conectar(usuario, password)
            await cliente.enable()
            await cliente.config()
            log += await cliente.enviar_comando("interface eth 0/16")
            log += await cliente.enviar_comando("display port traffic 0")
        finally:
            await cliente.cerrar()   # logout garantizado, aunque algo falle arriba

    Todo el texto crudo devuelto por enviar_comando() ya pasó por el scrub de
    secretos (ver `secretos_a_ocultar`); el logueo a OLT_API_LOG_TELNET debe
    seguir usando ese texto, nunca el buffer interno.
    """

    def __init__(self, host: str, puerto: int = 23, timeout: float = 30.0):
        self.host = host
        self.puerto = puerto
        self.timeout = timeout
        self._reader: Optional[asyncio.StreamReader] = None
        self._writer: Optional[asyncio.StreamWriter] = None
        self._buffer = ""
        self._iac_estado = 0  # 0=normal 1=IAC 2=opcion 3=SB 4=SB+IAC
        self._iac_verbo = 0
        self.secretos_a_ocultar: List[str] = []  # se rellena en conectar()
        self._conectado = False
        self._contexto = "ninguno"  # ninguno|user|enable|config — para saber cuántos 'quit' hacen falta al cerrar

    # ─── Conexión de bajo nivel (puerto de expect_popen/__expect_process_telnet) ──

    async def _abrir_socket(self) -> None:
        self._reader, self._writer = await asyncio.wait_for(
            asyncio.open_connection(self.host, self.puerto), timeout=self.timeout
        )

    def _cerrar_socket(self) -> None:
        if self._writer is not None:
            try:
                self._writer.close()
            except Exception:
                pass
        self._reader = None
        self._writer = None

    async def _enviar_crudo(self, texto: str) -> None:
        """Traduce '\\n' a CRLF (igual que expect_send) y escribe. No hace
        scrub: quien llama decide si ese texto entra al log crudo o no."""
        if self._writer is None:
            raise ErrorTelnet("socket no conectado")
        datos = texto.replace("\n", _EOL).encode("utf-8", errors="replace")
        datos = datos.replace(b"\xff", b"\xff\xff")  # escape IAC si apareciera en el texto
        self._writer.write(datos)
        await self._writer.drain()

    def _procesar_iac(self, chunk: bytes) -> bytes:
        """Filtra la negociación Telnet IAC del chunk entrante y devuelve la
        respuesta mínima a escribir (ACEPTA ECHO/SGA, rechaza el resto) —
        puerto directo de __expect_process_telnet() de expect_compat.php."""
        IAC, DONT, DO, WONT, WILL, SB, SE = 255, 254, 253, 252, 251, 250, 240
        OPT_ECHO, OPT_SGA = 1, 3
        limpio = bytearray()
        resp = bytearray()
        for b in chunk:
            if self._iac_estado == 0:
                if b == IAC:
                    self._iac_estado = 1
                else:
                    limpio.append(b)
            elif self._iac_estado == 1:
                if b == IAC:
                    limpio.append(IAC)
                    self._iac_estado = 0
                elif b == SB:
                    self._iac_estado = 3
                elif b in (WILL, WONT, DO, DONT):
                    self._iac_verbo = b
                    self._iac_estado = 2
                else:
                    self._iac_estado = 0
            elif self._iac_estado == 2:
                opt = b
                if self._iac_verbo == WILL:
                    resp += bytes([IAC, DO if opt in (OPT_ECHO, OPT_SGA) else DONT, opt])
                elif self._iac_verbo == WONT:
                    resp += bytes([IAC, DONT, opt])
                elif self._iac_verbo == DO:
                    resp += bytes([IAC, WONT, opt])
                elif self._iac_verbo == DONT:
                    resp += bytes([IAC, WONT, opt])
                self._iac_estado = 0
            elif self._iac_estado == 3:
                if b == IAC:
                    self._iac_estado = 4
            elif self._iac_estado == 4:
                self._iac_estado = 3 if b != SE else 0
        if resp and self._writer is not None:
            self._writer.write(bytes(resp))
        return bytes(limpio)

    async def _esperar(self, patrones: Sequence[_Patron], timeout: Optional[float] = None) -> Tuple[str, str]:
        """Puerto de expect_expectl(): lee del socket hasta que el buffer
        acumulado contenga alguno de `patrones` (se evalúan EN ORDEN, igual
        que __expect_match — el primero de la lista que aparezca en el
        buffer gana, sin importar la posición). Devuelve (etiqueta, texto)
        donde texto = todo lo consumido del buffer hasta el fin del match
        (equivalente a $uname .= $match[0] del PHP).

        Devuelve etiqueta=_TIMEOUT si el equipo deja de responder, o _EOF si
        cierra la conexión — ninguna de las dos lanza excepción aquí: quien
        llama decide qué significa cada una en ese punto de la secuencia
        (login: EOF/mensajes → RechazoPorLimiteSesiones; logout: TIMEOUT/EOF
        no son fatales, igual que EXP_TIMEOUT/EXP_EOF en el PHP)."""
        timeout = timeout if timeout is not None else self.timeout
        limite = time.monotonic() + timeout
        while True:
            encontrado = self._intentar_match(patrones)
            if encontrado is not None:
                return encontrado
            restante = limite - time.monotonic()
            if restante <= 0:
                return _TIMEOUT, self._buffer
            try:
                chunk = await asyncio.wait_for(self._reader.read(4096), timeout=restante)
            except asyncio.TimeoutError:
                return _TIMEOUT, self._buffer
            if not chunk:
                return _EOF, self._buffer
            limpio = self._procesar_iac(chunk)
            if limpio:
                self._buffer += limpio.decode("utf-8", errors="replace")
                limite = time.monotonic() + timeout  # llegaron datos: se reinicia el silencio

    def _intentar_match(self, patrones: Sequence[_Patron]) -> Optional[Tuple[str, str]]:
        for patron in patrones:
            m = patron.regex.search(self._buffer)
            if m:
                fin = m.end()
                texto = self._buffer[:fin]
                self._buffer = self._buffer[fin:]
                return patron.etiqueta, texto
        return None

    # ─── Navegación de contextos (puerto de _conectar/_leer_bloque/_desconectar) ──

    async def conectar(self, usuario: str, password: str) -> None:
        """Login. Lanza RechazoPorLimiteSesiones si el equipo niega la sesión
        (cupo agotado para ese usuario) — ver cabecera del archivo."""
        self.secretos_a_ocultar = [password]
        await self._abrir_socket()

        etiqueta, _ = await self._esperar([_lit("user", _PROMPT_USER)])
        if etiqueta != "user":
            raise ErrorTelnet(f"no llegó el prompt de usuario (llegó: {etiqueta})")
        await self._enviar_crudo(usuario + "\n")

        etiqueta, _ = await self._esperar([_lit("password", _PROMPT_PASSWORD)])
        if etiqueta != "password":
            raise ErrorTelnet(f"no llegó el prompt de password (llegó: {etiqueta})")
        await self._enviar_crudo(password + "\n")

        patrones_login = [
            _rx("shell", _RE_SHELL),
            _rx("rechazo", re.compile("|".join(p.pattern for p in _PATRONES_RECHAZO_SESIONES), re.IGNORECASE)),
        ]
        etiqueta, _ = await self._esperar(patrones_login)
        if etiqueta == "rechazo":
            raise RechazoPorLimiteSesiones(f"la OLT rechazó el login de '{usuario}' (límite de sesiones)")
        if etiqueta == _EOF:
            # [LIMITE CONOCIDO] Un EOF justo aquí también podría ser una
            # credencial mala (algunos firmwares Huawei cierran el socket en
            # vez de reprompt tras N intentos), no solo el límite de sesiones.
            # Con el usuario dedicado 'geretapi' (credenciales fijas en .env,
            # nunca provistas por el cliente HTTP) esta ambigüedad no debería
            # darse en la práctica; si F9 (validación real) la encuentra, se
            # puede afinar agregando un patrón explícito de "credenciales
            # inválidas" a _PATRONES_RECHAZO_SESIONES con su propia etiqueta.
            raise RechazoPorLimiteSesiones(
                f"la OLT cerró la conexión inmediatamente tras el login de '{usuario}' "
                "(límite de sesiones, o credenciales inválidas — ver comentario arriba)"
            )
        if etiqueta == _TIMEOUT:
            raise ErrorTelnet("timeout esperando el prompt de shell tras el login")
        self._conectado = True
        self._contexto = "user"

    async def enable(self) -> None:
        await self._enviar_crudo("enable\n")
        etiqueta, _ = await self._esperar([_rx("enable", _RE_ENABLE)])
        if etiqueta != "enable":
            raise ErrorTelnet(f"no llegó el prompt de enable (llegó: {etiqueta})")
        self._contexto = "enable"

    async def config(self) -> None:
        await self._enviar_crudo("config\n")
        etiqueta, _ = await self._esperar([_rx("config", _RE_CONFIG)])
        if etiqueta != "config":
            raise ErrorTelnet(f"no llegó el prompt de config (llegó: {etiqueta})")
        self._contexto = "config"

    async def enviar_comando(self, comando: str) -> str:
        """Envía un comando ya validado (ver utils/validador_comandos.py — este
        método NO valida nada) y devuelve el texto crudo hasta volver a un
        prompt tipo config (cualquier sub-nivel), manejando 'More' y los
        prompts de confirmación `{ ... }:` igual que _leer_bloque() en
        uplink_trafico_15m."""
        await self._enviar_crudo(comando + "\n")
        return await self._leer_hasta_config()

    async def _leer_hasta_config(self) -> str:
        patrones = [
            _rx("config", _RE_CONFIG),
            _lit("more", _TXT_MORE),
            _rx("confirmacion", _RE_CONFIRMACION),
        ]
        texto_total = ""
        while True:
            etiqueta, texto = await self._esperar(patrones)
            texto_total += texto
            if etiqueta == "config":
                return self._scrub(texto_total)
            if etiqueta == "more":
                await self._enviar_crudo(" ")  # sin \n: 'More' se avanza con espacio, igual que el PHP
                continue
            if etiqueta == "confirmacion":
                await self._enviar_crudo("\n")  # acepta la opción por defecto (<cr>)
                continue
            # _TIMEOUT o _EOF: el equipo dejó de responder a mitad de un bloque.
            raise ErrorTelnet(f"timeout/EOF leyendo la respuesta del comando (etiqueta={etiqueta!r})")

    async def cerrar(self) -> None:
        """Logout garantizado — SIEMPRE, incluso si algo previo falló a
        mitad de camino (llamar desde un finally, igual que _desconectar()
        en uplink_trafico_15m). Baja tantos niveles como haga falta según el
        contexto en el que quedó la sesión (config→enable→user→logout, o
        menos si nunca llegó a 'config'/'enable'), y siempre intenta el
        cierre físico del socket al final. No relanza errores: un fallo
        cerrando limpio no debe tapar el error original de quien llamó."""
        if self._writer is None:
            return
        try:
            if self._conectado:
                # Config→enable (si corresponde). No hace falta si nunca se
                # llamó a config() o si algo ya nos dejó en 'enable'/'user'.
                if self._contexto == "config":
                    await self._enviar_crudo("quit\n")
                    etiqueta, _ = await self._esperar(
                        [_rx("enable", _RE_ENABLE)], timeout=self.timeout
                    )
                    self._contexto = "enable" if etiqueta == "enable" else self._contexto

                # Enable→user + confirmación de logout (si corresponde).
                if self._contexto in ("enable", "user"):
                    await self._enviar_crudo("quit\n")
                    etiqueta, _ = await self._esperar(
                        [_rx("logout_confirm", _RE_LOGOUT_CONFIRM), _rx("shell", _RE_SHELL)],
                        timeout=self.timeout,
                    )
                    if etiqueta == "logout_confirm":
                        await self._enviar_crudo("y\n")
                    # etiqueta == "shell": el equipo solo bajó a modo usuario
                    # (no cerró la sesión) — un segundo 'quit' desde ahí es
                    # lo que la mayoría de los CLI Huawei esperan.
                    if etiqueta == "shell":
                        await self._enviar_crudo("quit\n")
                        etiqueta2, _ = await self._esperar(
                            [_rx("logout_confirm", _RE_LOGOUT_CONFIRM)], timeout=self.timeout
                        )
                        if etiqueta2 == "logout_confirm":
                            await self._enviar_crudo("y\n")
        except Exception:
            pass  # [PARIDAD-PHP] EXP_TIMEOUT/EXP_EOF en logout no es un error fatal
        finally:
            self._cerrar_socket()

    def _scrub(self, texto: str) -> str:
        """Nunca deja que la contraseña (u otro secreto marcado) quede en el
        texto que se persiste como log crudo."""
        for secreto in self.secretos_a_ocultar:
            if secreto:
                texto = texto.replace(secreto, "[REDACTED]")
        return texto


def respuesta_valida(texto_crudo: str) -> bool:
    """Igual semántica que uplink_trafico_15m/utils/telnet_olt.py
    (respuesta_valida / verifica_equipo del PHP): False si, en ALGUNA línea
    del log (tras quitarle todo espacio en blanco, para no fallar por saltos
    de línea o padding intermedios), aparece el marcador de comando no
    reconocido."""
    for linea in texto_crudo.replace("\r\n", "\n").replace("\r", "\n").split("\n"):
        if MARCADOR_ERROR_CLI in re.sub(r"\s+", "", linea):
            return False
    return True
