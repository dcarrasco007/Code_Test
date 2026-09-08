# ============================================================================
# tests/fake_olt_cli.py
# Proyecto: api_olt_consultas
# Descripción: Servidor TCP asyncio que imita lo suficiente del CLI Huawei
#              para ejercitar utils/telnet_olt.py y utils/sesiones_telnet.py
#              SIN una OLT real: login, enable, config, 'interface', un
#              comando 'display' (con 'More' y/o confirmación opcionales),
#              logout, y el LÍMITE de sesiones simultáneas por usuario (la
#              pieza clave del requisito del usuario sobre el cupo de 3).
#
# No es un emulador completo del CLI (no hace falta para F3: ese detalle por
# comando/consulta es tarea de utils/ejecutor.py y los parsers en F4). Solo
# reproduce las transiciones de contexto y los prompts que el automatón debe
# saber navegar.
#
# Uso:
#   servidor = await FakeOltCLI(usuario="geretapi", password="x", max_sesiones=3).iniciar()
#   cliente = ClienteTelnetOLT(servidor.host, servidor.port, timeout=2)
#   ...
#   await servidor.detener()
# ============================================================================

import asyncio


class FakeOltCLI:
    def __init__(
        self,
        usuario: str = "geretapi",
        password: str = "clave-telnet",
        hostname: str = "OLT-TEST-1",
        max_sesiones: int = 3,
        con_more: bool = False,
        con_confirmacion: bool = False,
    ):
        self.usuario = usuario
        self.password = password
        self.hostname = hostname
        self.max_sesiones = max_sesiones
        self.con_more = con_more
        self.con_confirmacion = con_confirmacion

        self.host = "127.0.0.1"
        self.port = None
        self.sesiones_activas = 0
        self.total_logins_aceptados = 0
        self.total_rechazos_por_limite = 0
        self._server = None

    async def iniciar(self) -> "FakeOltCLI":
        self._server = await asyncio.start_server(self._manejar_conexion, self.host, 0)
        self.port = self._server.sockets[0].getsockname()[1]
        return self

    async def detener(self) -> None:
        if self._server is not None:
            self._server.close()
            await self._server.wait_closed()

    # ─── Protocolo interno ───────────────────────────────────────────────────

    async def _enviar(self, writer: asyncio.StreamWriter, texto: str) -> None:
        writer.write(texto.replace("\n", "\r\n").encode("utf-8"))
        await writer.drain()

    async def _leer_linea(self, reader: asyncio.StreamReader) -> str:
        """Lee hasta CRLF (lo que manda ClienteTelnetOLT para cada comando) y
        devuelve la línea sin el terminador."""
        datos = await reader.readuntil(b"\r\n")
        return datos.decode("utf-8", errors="replace")[:-2]

    async def _manejar_conexion(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter) -> None:
        try:
            await self._enviar(writer, "User name:")
            usuario = await self._leer_linea(reader)
            await self._enviar(writer, "\nUser password:")
            password = await self._leer_linea(reader)

            if usuario != self.usuario or password != self.password:
                await self._enviar(writer, "\nError: Invalid user name or password\n")
                return

            if self.sesiones_activas >= self.max_sesiones:
                self.total_rechazos_por_limite += 1
                # Mensaje real conocido del CLI Huawei ante el 4to login del
                # mismo usuario (ver utils/telnet_olt.py → _PATRONES_RECHAZO_SESIONES).
                await self._enviar(writer, "\nThe number of users has reached the upper limit.\n")
                return

            self.sesiones_activas += 1
            self.total_logins_aceptados += 1
            try:
                await self._enviar(writer, f"\n{self.hostname}>")
                await self._ciclo_comandos(reader, writer)
            finally:
                self.sesiones_activas -= 1
        except (asyncio.IncompleteReadError, ConnectionResetError, ConnectionAbortedError):
            pass
        finally:
            try:
                writer.close()
            except Exception:
                pass

    async def _ciclo_comandos(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter) -> None:
        contexto = "user"
        while True:
            try:
                cmd = (await self._leer_linea(reader)).strip()
            except asyncio.IncompleteReadError:
                return

            if contexto == "user" and cmd == "enable":
                contexto = "enable"
                await self._enviar(writer, f"\n{self.hostname}#")
                continue

            if contexto == "user" and cmd == "quit":
                # Igual que en 'enable': salir desde el modo usuario también
                # dispara la confirmación de logout (deja cerrar una sesión
                # que nunca llegó a 'enable', ej. tras un error temprano).
                await self._enviar(writer, "\nAre you sure to log out? (y/n)[n]:")
                try:
                    await self._leer_linea(reader)  # "y"
                except asyncio.IncompleteReadError:
                    pass
                return

            if contexto == "enable" and cmd == "config":
                contexto = "config"
                await self._enviar(writer, f"\n{self.hostname}(config)#")
                continue

            if contexto == "enable" and cmd == "quit":
                await self._enviar(writer, "\nAre you sure to log out? (y/n)[n]:")
                try:
                    await self._leer_linea(reader)  # "y"
                except asyncio.IncompleteReadError:
                    pass
                return

            if contexto == "config":
                if cmd == "quit":
                    contexto = "enable"
                    await self._enviar(writer, f"\n{self.hostname}#")
                    continue
                if cmd.startswith("interface "):
                    # El prompt de interfaz sigue conteniendo "config", el
                    # automatón genérico no distingue el sub-nivel.
                    await self._enviar(writer, f"\n{self.hostname}(config-if)#")
                    continue
                if cmd.startswith("display"):
                    await self._responder_display(reader, writer)
                    continue
                if cmd == "":
                    await self._enviar(writer, f"\n{self.hostname}(config)#")
                    continue
                await self._enviar(
                    writer, "\n% Unknown command, the error locates at '^'\n" + f"{self.hostname}(config)#"
                )
                continue

    async def _responder_display(self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter) -> None:
        bloque = "puerto 0/1  entrada:100  salida:200\n"
        if self.con_more:
            await self._enviar(writer, "\n" + bloque + "---- More ( Press 'Q' to break ) ----")
            await reader.read(1)  # el cliente responde con UN espacio, sin \n
            bloque = "puerto 0/2  entrada:150  salida:250\n"  # "segunda página"
        if self.con_confirmacion:
            await self._enviar(writer, "\n" + bloque + "{ <cr>||<K> }:")
            await self._leer_linea(reader)  # el cliente responde con un Enter (CRLF)
            bloque = ""  # ya se mostró; el prompt final no repite el bloque
        await self._enviar(writer, "\n" + bloque + f"{self.hostname}(config)#")
