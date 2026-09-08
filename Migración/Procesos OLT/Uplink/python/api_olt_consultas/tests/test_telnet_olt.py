# ============================================================================
# tests/test_telnet_olt.py
# Proyecto: api_olt_consultas
# Descripción: utils/telnet_olt.py contra tests/fake_olt_cli.py (TCP real en
#              localhost, sin ninguna OLT real ni base de datos). Cubre:
#              login→enable→config→comando→logout, 'More', prompt de
#              confirmación, el hostname "cosmético" DURZUA, y — la pieza
#              clave del requisito del usuario — el límite de 3 sesiones
#              simultáneas por usuario.
#              Ejecutar: python -m tests.test_telnet_olt (desde la raíz).
# ============================================================================

import asyncio

from tests.fake_olt_cli import FakeOltCLI
from utils.telnet_olt import (
    ClienteTelnetOLT,
    RechazoPorLimiteSesiones,
    respuesta_valida,
)


def _cliente(servidor, timeout=2.0) -> ClienteTelnetOLT:
    return ClienteTelnetOLT(servidor.host, servidor.port, timeout=timeout)


async def _flujo_completo(servidor) -> str:
    cliente = _cliente(servidor)
    try:
        await cliente.conectar(servidor.usuario, servidor.password)
        await cliente.enable()
        await cliente.config()
        texto = await cliente.enviar_comando("interface eth 0/16")
        texto += await cliente.enviar_comando("display port traffic 0")
        return texto
    finally:
        await cliente.cerrar()


def test_login_enable_config_display_logout():
    async def _run():
        servidor = await FakeOltCLI(usuario="geretapi", password="clave-x").iniciar()
        try:
            texto = await _flujo_completo(servidor)
            assert "entrada:100" in texto
            assert servidor.total_logins_aceptados == 1
            # El cliente ya mandó 'y' y cerró su socket, pero el servidor
            # procesa esa última línea en su propia tarea: darle un instante
            # antes de mirar el contador (evita una carrera con la prueba).
            await asyncio.sleep(0.05)
            assert servidor.sesiones_activas == 0  # el logout liberó la sesión
        finally:
            await servidor.detener()

    asyncio.run(_run())


def test_password_nunca_queda_en_el_log_crudo():
    async def _run():
        servidor = await FakeOltCLI(usuario="geretapi", password="secreto-super-1").iniciar()
        try:
            cliente = _cliente(servidor)
            try:
                await cliente.conectar(servidor.usuario, servidor.password)
                await cliente.enable()
                await cliente.config()
                texto = await cliente.enviar_comando("display port traffic 0")
            finally:
                await cliente.cerrar()
            assert "secreto-super-1" not in texto
        finally:
            await servidor.detener()

    asyncio.run(_run())


def test_paginado_more_junta_las_paginas():
    async def _run():
        servidor = await FakeOltCLI(con_more=True).iniciar()
        try:
            cliente = _cliente(servidor)
            try:
                await cliente.conectar(servidor.usuario, servidor.password)
                await cliente.enable()
                await cliente.config()
                texto = await cliente.enviar_comando("display port traffic 0")
            finally:
                await cliente.cerrar()
            assert "entrada:100" in texto and "entrada:150" in texto
        finally:
            await servidor.detener()

    asyncio.run(_run())


def test_prompt_de_confirmacion_se_acepta_con_enter():
    async def _run():
        servidor = await FakeOltCLI(con_confirmacion=True).iniciar()
        try:
            cliente = _cliente(servidor)
            try:
                await cliente.conectar(servidor.usuario, servidor.password)
                await cliente.enable()
                await cliente.config()
                texto = await cliente.enviar_comando("display port traffic 0")
            finally:
                await cliente.cerrar()
            assert "entrada:100" in texto
        finally:
            await servidor.detener()

    asyncio.run(_run())


def test_hostname_durzua_no_requiere_caso_especial():
    # El prompt "OLT-DURZUA-4>" / "...(config)#" ya matchea los regex
    # genéricos (.*>, .*config.*#) sin necesitar un patrón dedicado — por
    # eso F1 decidió NO replicar esa excepción cosmética del PHP de origen.
    async def _run():
        servidor = await FakeOltCLI(hostname="OLT-DURZUA-4").iniciar()
        try:
            texto = await _flujo_completo(servidor)
            assert "entrada:100" in texto
        finally:
            await servidor.detener()

    asyncio.run(_run())


def test_credenciales_invalidas_no_cuelga_ni_rompe_el_cierre():
    async def _run():
        servidor = await FakeOltCLI(usuario="geretapi", password="correcta").iniciar()
        try:
            cliente = _cliente(servidor)
            try:
                try:
                    await cliente.conectar("geretapi", "incorrecta")
                    assert False, "debió lanzar"
                except RechazoPorLimiteSesiones:
                    pass  # ver nota en utils/telnet_olt.py: el EOF tras login es ambiguo a propósito
            finally:
                await cliente.cerrar()  # no debe lanzar aunque nunca hubo login real
        finally:
            await servidor.detener()

    asyncio.run(_run())


def test_limite_de_3_sesiones_simultaneas_y_liberacion():
    """El requisito explícito del usuario: la 4ta sesión del mismo usuario a
    la misma OLT se rechaza; al liberar una, vuelve a haber cupo."""

    async def _run():
        servidor = await FakeOltCLI(usuario="geretapi", password="x", max_sesiones=3).iniciar()
        try:
            clientes = [_cliente(servidor) for _ in range(4)]
            for c in clientes[:3]:
                await c.conectar(servidor.usuario, servidor.password)
            assert servidor.sesiones_activas == 3

            try:
                await clientes[3].conectar(servidor.usuario, servidor.password)
                assert False, "la 4ta sesión debió ser rechazada"
            except RechazoPorLimiteSesiones:
                pass
            assert servidor.total_rechazos_por_limite == 1
            assert servidor.sesiones_activas == 3
            await clientes[3].cerrar()  # socket abierto sin login real: no debe lanzar

            # Liberar una sesión real (la primera) y reintentar: ahora sí entra.
            await clientes[0].cerrar()
            await asyncio.sleep(0.05)
            cliente_nuevo = _cliente(servidor)
            await cliente_nuevo.conectar(servidor.usuario, servidor.password)
            assert servidor.sesiones_activas == 3
            await cliente_nuevo.cerrar()

            for c in clientes[1:3]:
                await c.cerrar()
            await asyncio.sleep(0.05)  # dar tiempo al servidor a procesar el último logout
            assert servidor.sesiones_activas == 0
        finally:
            await servidor.detener()

    asyncio.run(_run())


def test_respuesta_valida_detecta_comando_no_reconocido():
    assert respuesta_valida("todo bien\nOLT-X(config)#")
    assert not respuesta_valida("comando malo\n%   Unknown  command,  the error locates at  '^'\nOLT-X(config)#")


if __name__ == "__main__":
    tests = [
        test_login_enable_config_display_logout,
        test_password_nunca_queda_en_el_log_crudo,
        test_paginado_more_junta_las_paginas,
        test_prompt_de_confirmacion_se_acepta_con_enter,
        test_hostname_durzua_no_requiere_caso_especial,
        test_credenciales_invalidas_no_cuelga_ni_rompe_el_cierre,
        test_limite_de_3_sesiones_simultaneas_y_liberacion,
        test_respuesta_valida_detecta_comando_no_reconocido,
    ]
    for t in tests:
        t()
    print(f"tests/test_telnet_olt.py: OK ({len(tests)}/{len(tests)})")
