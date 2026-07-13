# ============================================================================
# tests/fake_olt_cli.py
# Proyecto: uplink_trafico_15m
# Descripción: CLI de una OLT Huawei SIMULADA, para ejercitar la máquina de
#              estados de utils/telnet_olt.py en dev sin una OLT real ni
#              telnet (pexpect.spawn no funciona en Windows; este script se
#              spawnea vía PopenSpawn en lugar de 'telnet <ip>'). NO reemplaza
#              la validación en producción (Fase 9) — solo prueba el flujo de
#              login → enable → config → ciclo de puerto → logout.
# Uso: python tests/fake_olt_cli.py [--con-error]
#   --con-error: simula que la OLT responde con '%Unknown command...' en vez
#                del bloque de tráfico (para probar respuesta_valida()==False).
# ============================================================================

import sys

CON_ERROR = "--con-error" in sys.argv

# Mismo bloque sintético de Fase 2 (8 líneas, bajada en la línea 0, subida en la +7).
BLOQUE_TRAFICO = [
    "    The received traffic of this port(kbits/s) = 15234",
    "    The received traffic rate of this port(kbits/s) = 12",
    "    The received packets of this port = 500",
    "    The received bytes of this port = 64000",
    "    The received errors of this port = 0",
    "    The received discards of this port = 0",
    "    The received unicast packets of this port = 480",
    "    The transmitted traffic of this port(kbits/s) = 8721",
]


def emitir(*lineas):
    for linea in lineas:
        print(linea, flush=True)


def main():
    estado = "LOGIN"
    pendiente_swallow = 0  # 'quit' de cierre del ciclo interface/display a ignorar

    emitir("User name:")
    for linea in sys.stdin:
        linea = linea.strip()

        if estado == "LOGIN":
            emitir("User password:")
            estado = "PASSWORD"
        elif estado == "PASSWORD":
            emitir("FAKE-OLT>")
            estado = "USERMODE"
        elif estado == "USERMODE":
            if linea == "enable":
                emitir("FAKE-OLT#")
                estado = "ENABLEMODE"
        elif estado == "ENABLEMODE":
            if linea == "config":
                emitir("FAKE-OLT(config)#")
                estado = "CONFIGMODE"
            elif linea == "quit":
                emitir("Are you sure to log out? (y/n)[n]:")
                estado = "CONFIRM"
        elif estado == "CONFIGMODE":
            if linea.startswith("display port traffic"):
                if CON_ERROR:
                    emitir("%Unknown command, the error locates at '^'")
                else:
                    emitir(*BLOQUE_TRAFICO)
                emitir("FAKE-OLT(config)#")
                pendiente_swallow = 1
            elif linea == "quit":
                if pendiente_swallow > 0:
                    pendiente_swallow -= 1  # quit de salida del ciclo de puerto: se ignora
                else:
                    emitir("FAKE-OLT#")
                    estado = "ENABLEMODE"
            # 'interface ...' y la línea en blanco no producen salida propia.
        elif estado == "CONFIRM":
            if linea == "y":
                emitir("Bye.")
                return


if __name__ == "__main__":
    main()
