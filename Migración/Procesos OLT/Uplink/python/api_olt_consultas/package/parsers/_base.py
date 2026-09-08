# ============================================================================
# package/parsers/_base.py
# Proyecto: api_olt_consultas
# Descripción: Utilidades COMPARTIDAS por los 20 parsers de package/parsers/.
#              Cada parser recibe el texto crudo que devuelve
#              utils/ejecutor.py (ya scrubeado de contraseñas) y aplica
#              regex ANCLADOS para extraer el "dato" estructurado — nunca al
#              revés (nunca se adivina un campo que el CLI no mostró).
#
# ALCANCE (igual criterio que sql/02_seed_consultas_comandos.sql, F1): estos
# parsers cubren el formato GENERAL de cada comando Huawei, con fidelidad
# razonable según el análisis de los ~112 PHP de origen. NO se garantiza
# fidelidad 1:1 contra una transcripción real hasta F9 (validación en
# producción) — así lo deja explícito el propio plan ("tests con
# transcripciones sintéticas ahora, reales en F9"). Si un campo no aparece en
# el texto, el parser devuelve None/[] en ese campo en vez de inventarlo.
#
# Para modificar:
#   - Limpieza de líneas / ANSI → funciones de este archivo
# ============================================================================

from __future__ import annotations

import re
from typing import List

# [PARIDAD-PHP] Basura de control ANSI que algunos CLI Huawei intercalan al
# repintar la línea (ej. '\x1b[37D', '[37D' sin el ESC si el terminal ya lo
# comió) — se limpia antes de aplicar cualquier regex de datos.
_RE_ANSI = re.compile(r"\x1b\[[0-9;]*[A-Za-z]|\[[0-9]{1,3}[A-DK]")

_RE_ESPACIOS = re.compile(r"\s+")


def limpiar_ansi(texto: str) -> str:
    return _RE_ANSI.sub("", texto)


def normalizar_lineas(texto_crudo: str) -> List[str]:
    """Divide el texto crudo en líneas ya limpias de ANSI y de \\r sueltos —
    mismo preprocesado que uplink_trafico_15m/utils/parser_trafico.py
    (explode por \\r, sin arrastrar \\n/\\r residual), reutilizado aquí para
    no duplicar criterios de parsing entre proyectos hermanos."""
    texto = limpiar_ansi(texto_crudo)
    lineas = texto.replace("\r\n", "\r").replace("\n", "\r").split("\r")
    return [linea for linea in lineas]


def quitar_espacios(linea: str) -> str:
    """Igual que uplink_trafico_15m: preg_replace('/\\s+/', '', $linea)."""
    return _RE_ESPACIOS.sub("", linea)


def lineas_no_vacias(texto_crudo: str) -> List[str]:
    return [l for l in normalizar_lineas(texto_crudo) if l.strip()]


_NIVELES_ALARMA = ("Critical", "Major", "Minor", "Warning")

# [CONFIANZA MEDIA — confirmar con transcripción real en F9] Layout genérico
# observado en el CLI Huawei para 'display alarm active/history ...':
# columnas Sequence / AlarmName (puede tener espacios) / Level / Location,
# separadas por 2+ espacios; el resto de la línea (fecha) se descarta si no
# hace falta. No intenta cubrir columnas adicionales que agreguen firmwares
# distintos — un patrón demasiado laxo arriesga falsos positivos.
_RE_FILA_ALARMA = re.compile(
    r"^\s*(\d+)\s{2,}(\S.*?)\s{2,}(" + "|".join(_NIVELES_ALARMA) + r")\s{2,}(\S+)",
)

# 'Number of active alarms: 5' / 'Total: 5' / variantes con o sin espacios.
_RE_CANTIDAD_ALARMAS = re.compile(r"(?:number of.*alarms|total)\s*[:=]\s*(\d+)", re.IGNORECASE)


def parsear_tabla_alarmas(texto_crudo: str) -> List[dict]:
    """Filas de una tabla 'display alarm ...' con forma
    Sequence / AlarmName / Level / Location. Devuelve [] si el formato no
    matchea (nunca inventa filas)."""
    filas = []
    for linea in normalizar_lineas(texto_crudo):
        m = _RE_FILA_ALARMA.match(linea)
        if m:
            filas.append({
                "secuencia": m.group(1),
                "nombre": m.group(2).strip(),
                "nivel": m.group(3),
                "ubicacion": m.group(4),
            })
    return filas


def contar_alarmas(texto_crudo: str) -> "int | None":
    """Busca una línea resumen tipo 'Number of active alarms: N'. None si no
    aparece (el llamador puede optar por usar len(parsear_tabla_alarmas())
    como alternativa)."""
    for linea in normalizar_lineas(texto_crudo):
        m = _RE_CANTIDAD_ALARMAS.search(linea)
        if m:
            return int(m.group(1))
    return None


# [CONFIANZA MEDIA] Muchos comandos 'display' de Huawei devuelven bloques
# de pares "Etiqueta : valor" (ddm-info, version, temperature/cpu puntuales).
# Un solo parser genérico de pares evita reescribir la misma regex 20 veces.
_RE_PAR_CLAVE_VALOR = re.compile(r"^\s*([A-Za-z][A-Za-z0-9 _/().%-]*?)\s*[:=]\s*(\S.*?)\s*$")


def parsear_pares_clave_valor(texto_crudo: str) -> dict:
    """'Temperature(C)  : 35.00' -> {'Temperature(C)': '35.00'}. Conserva la
    etiqueta tal cual la mostró el CLI (no la normaliza a snake_case) para
    no adivinar un nombre de campo que el equipo no usó literalmente."""
    pares = {}
    for linea in normalizar_lineas(texto_crudo):
        m = _RE_PAR_CLAVE_VALOR.match(linea)
        if m:
            clave, valor = m.group(1).strip(), m.group(2).strip()
            if clave and valor:
                pares[clave] = valor
    return pares


def primer_grupo(patron: "re.Pattern", lineas: List[str]):
    """Devuelve el primer grupo(1) que matchee `patron` en `lineas`, o None.
    Ayuda a que cada parser quede como una lista corta de 'buscar este
    patrón, con este nombre de campo' en vez de un loop manual repetido."""
    for linea in lineas:
        m = patron.search(linea)
        if m:
            return m.group(1)
    return None
