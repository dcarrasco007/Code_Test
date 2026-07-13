# ============================================================================
# utils/parser_trafico.py
# Proyecto: uplink_trafico_15m (compartido por los 3 procesos)
# Equivalente PHP: el bucle for($j...) que parsea la salida de 'display port traffic'
# Descripción: Parsea el texto crudo capturado por telnet y devuelve, en el mismo
#              orden en que aparecen en el texto, los pares (bajada, subida) de
#              cada lectura 'display port traffic'. Este módulo NO sabe a qué
#              puerto/slot corresponde cada lectura — esa asignación depende del
#              orden en que el worker emitió los comandos telnet y es
#              responsabilidad del worker (Fases 5/6/7), no de este util.
# Para modificar:
#   - Cadena marcador de la línea de bajada → buscar [CONFIG] MARCADOR_BAJADA
#   - Offset fijo hacia la línea de subida  → buscar [PARIDAD-PHP] OFFSET_LINEA_SUBIDA
# NOTA: Validado en Fase 2 con una transcripción SINTÉTICA (ver tests/). Falta
#       validar con una transcripción REAL de 'display port traffic' (Fase 9).
# ============================================================================

import re

# [CONFIG] Cadena que identifica la línea de tráfico de bajada, ya sin espacios
#          (igual que hace el PHP al buscarla: preg_replace('/\s+/','', ...)).
MARCADOR_BAJADA = "Thereceivedtrafficofthisport(kbits/s)="

# [PARIDAD-PHP] Offset fijo entre la línea de bajada y la de subida en la salida
#               de 'display port traffic'. Depende del formato fijo del CLI Huawei.
#               Ver PLAN_MIGRACION_15M.md §2.2 — pendiente de confirmar con
#               transcripción real (Fase 9).
OFFSET_LINEA_SUBIDA = 7


def normalizar_lineas(texto_crudo):
    """Divide el texto crudo del telnet en líneas, replicando el preprocesado PHP:
        explode(chr(13), $texto) + eregi_replace("[\\n|\\r|\\n\\r]", '', $linea)
    Es decir: separar por \\r y luego quitar cualquier \\n, \\r o '|' residual.

    Pública porque también la usa utils/telnet_olt.py (respuesta_valida, equivalente
    a verifica_equipo del PHP) — mismo preprocesado, no duplicar la lógica.
    """
    lineas = texto_crudo.split("\r")
    return [re.sub(r"[\n|\r]", "", linea) for linea in lineas]


def quitar_espacios(linea):
    """Equivalente PHP: preg_replace('/\\s+/', '', $linea) — quita TODO espacio en blanco.

    Pública por el mismo motivo que normalizar_lineas (reutilizada en telnet_olt.py).
    """
    return re.sub(r"\s+", "", linea)


def _extraer_valor_bajada(linea_sin_espacios):
    """Extrae el valor numérico (como string) de una línea de bajada ya confirmada.

    Equivalente PHP:
        $valor1 = strstr($linea_dato, '=');   // desde el primer '=' hasta el final
        $valor1 = trim($valor1);
        $valor2 = explode("=", $valor1);
        $valor3 = $valor2[1];
    """
    pos_igual = linea_sin_espacios.find("=")
    valor1 = linea_sin_espacios[pos_igual:].strip()
    return valor1.split("=")[1]


def _extraer_valor_subida(linea_sin_espacios):
    """Extrae el valor numérico (como string) de la línea de subida (offset +7).

    Equivalente PHP:
        $valorUp = explode("=", $line2);
        $valorUpFinal = $valorUp[1];
    """
    return linea_sin_espacios.split("=")[1]


def parsear_trafico(texto_crudo):
    """Parsea el texto crudo del telnet y devuelve una lista de tuplas
    (valor_bajada: str, valor_subida: str | None), en el mismo orden en que
    aparecen las lecturas 'display port traffic' en el texto.

    El worker que llama a esta función es quien sabe, por el orden en que emitió
    los comandos telnet, a qué puerto/slot corresponde cada elemento de la lista
    devuelta (replicando el orden de $contador16/$contador17/... del PHP).

    valor_subida es None si no hay línea +7 disponible (fin de buffer) — mejora
    de robustez sobre el PHP, que indexaría fuera de rango en ese caso.
    """
    lineas = normalizar_lineas(texto_crudo)
    lecturas = []

    for j, linea in enumerate(lineas):
        linea_sin_espacios = quitar_espacios(linea)
        if MARCADOR_BAJADA not in linea_sin_espacios:
            continue

        valor_bajada = _extraer_valor_bajada(linea_sin_espacios)

        valor_subida = None
        j_subida = j + OFFSET_LINEA_SUBIDA
        if j_subida < len(lineas):
            linea_subida_sin_espacios = quitar_espacios(lineas[j_subida])
            if "=" in linea_subida_sin_espacios:
                valor_subida = _extraer_valor_subida(linea_subida_sin_espacios)

        lecturas.append((valor_bajada, valor_subida))

    return lecturas
