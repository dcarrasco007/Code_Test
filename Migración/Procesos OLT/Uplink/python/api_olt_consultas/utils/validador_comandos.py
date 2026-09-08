# ============================================================================
# utils/validador_comandos.py
# Proyecto: api_olt_consultas
# Descripción: Validación de los comandos que un cliente envía a
#              /ejecutar/comandos (F6) ANTES de que cualquiera llegue a una
#              sesión telnet real. Puramente sintáctico/léxico — no abre
#              conexiones, no consulta BD por sí mismo (los templates
#              aprobados los trae el llamador desde OLT_API_COMANDOS_APROBADOS).
#
# Reglas (ver PLAN_API_OLT.md → Seguridad → "Inyección de comandos telnet"):
#   1. Cada comando ≤ LARGO_MAXIMO caracteres, solo ASCII, sin \r \n ; | & ni
#      caracteres de control.
#   2. La deny-list absoluta se evalúa SIEMPRE, incluso si el comando ya
#      pasó como lectura o como template aprobado (defensa en profundidad).
#   3. Es válido si (a) es una lectura ('display ...') o (b) coincide EXACTO
#      con un template activo de OLT_API_COMANDOS_APROBADOS Y el cliente
#      tiene el scope 'config_aprobada'.
#   4. La navegación (enable/config/interface/quit) la genera la API desde
#      parámetros tipados — nunca llega aquí como texto libre del cliente
#      (ver utils/ejecutor.py, F4/F6). Por eso 'quit' y 'return' están en la
#      deny-list: si aparecen en un comando del cliente es siempre inválido.
#
# Para modificar:
#   - Límites (largo, máx. comandos/OLT por request) → app/config/settings.py
#   - Deny-list / regex de lectura → constantes de este archivo
# ============================================================================

from __future__ import annotations

import re
from dataclasses import dataclass
from typing import Iterable, List, Optional, Sequence

from app.config import settings

LARGO_MAXIMO_COMANDO = 200

# Caracteres de control (incluye \r \n \t), ';', '|', '&', y todo lo no-ASCII
# (>0x7F). Un match de este patrón invalida el comando de inmediato.
_REGEX_CARACTERES_PROHIBIDOS = re.compile(r"[\x00-\x1f\x7f-\xff;|&]")

# Lectura permitida por defecto para cualquier cliente con scope 'lectura'
# (no requiere 'config_aprobada'): solo 'display ...' en minúsculas.
_REGEX_LECTURA = re.compile(r"^display\s[a-z0-9\s\-/]+$")

# Deny-list absoluta: si el comando contiene alguna de estas palabras como
# palabra completa, se rechaza SIEMPRE, sin excepción, sin importar el scope
# ni si matchea un template aprobado.
_PALABRAS_DENY = (
    "undo", "reset", "reboot", "save", "delete", "erase", "format", "load",
    "shutdown", "activate", "deactivate", "board", "patch", "upgrade",
    "terminal", "user", "password", "quit", "return",
)
_REGEX_DENY_PALABRAS = re.compile(r"\b(" + "|".join(_PALABRAS_DENY) + r")\b", re.IGNORECASE)
# Frases de 2 palabras que la deny-list de una sola palabra no cubriría.
_REGEX_DENY_FRASES = re.compile(r"\bont\s+(add|delete)\b", re.IGNORECASE)


class ComandoInvalido(ValueError):
    """Un comando no pasó la validación. `.motivo` no se debe reenviar al
    cliente sin criterio: es útil para logs/auditoría, pero puede confirmar
    a un atacante qué probó exactamente (ver nota en validar_comando)."""

    def __init__(self, comando: str, motivo: str):
        self.comando = comando
        self.motivo = motivo
        super().__init__(f"Comando inválido ({motivo}): {comando!r}")


def validar_caracteres(comando: str) -> None:
    if not comando or len(comando) > LARGO_MAXIMO_COMANDO:
        raise ComandoInvalido(comando, f"largo fuera de rango (máx {LARGO_MAXIMO_COMANDO})")
    if _REGEX_CARACTERES_PROHIBIDOS.search(comando):
        raise ComandoInvalido(comando, "contiene caracteres no permitidos (control, ; | & o no-ASCII)")


def es_deny_list(comando: str) -> bool:
    return bool(_REGEX_DENY_PALABRAS.search(comando) or _REGEX_DENY_FRASES.search(comando))


def es_lectura(comando: str) -> bool:
    return bool(_REGEX_LECTURA.match(comando))


def matches_aprobado(comando: str, templates: Sequence[str]) -> bool:
    """`templates`: regex ANCLADAS (^...$) ya leídas de
    OLT_API_COMANDOS_APROBADOS.activo=1. Un template mal formado en BD no
    debe tumbar la API: se ignora ese template puntual (defensa en
    profundidad — el contenido de esa tabla lo revisa un humano antes de
    insertarlo, pero un regex inválido no debe convertirse en un 500)."""
    for template in templates:
        try:
            if re.match(template, comando):
                return True
        except re.error:
            continue
    return False


def validar_comando(
    comando: str,
    *,
    tiene_scope_config_aprobada: bool,
    templates_aprobados: Optional[Sequence[str]] = None,
) -> None:
    """Lanza ComandoInvalido si el comando no puede ejecutarse. No devuelve
    nada si es válido."""
    validar_caracteres(comando)

    if es_deny_list(comando):
        raise ComandoInvalido(comando, "coincide con la deny-list absoluta")

    if es_lectura(comando):
        return

    if tiene_scope_config_aprobada and templates_aprobados and matches_aprobado(comando, templates_aprobados):
        return

    raise ComandoInvalido(comando, "no es una lectura y no coincide con ningún template aprobado")


@dataclass
class ResultadoValidacionLista:
    ok: bool
    errores: List[str]


def validar_lista_comandos(
    comandos: Iterable[str],
    *,
    tiene_scope_config_aprobada: bool,
    templates_aprobados: Optional[Sequence[str]] = None,
    max_comandos: Optional[int] = None,
) -> ResultadoValidacionLista:
    """Valida una lista completa (orden que el cliente envía a
    /ejecutar/comandos). No detiene en el primer error: junta todos para que
    el cliente pueda corregir todo de una vez."""
    comandos = list(comandos)
    tope = max_comandos if max_comandos is not None else settings.MAX_COMANDOS_REQUEST
    errores: List[str] = []

    if not comandos:
        errores.append("la lista de comandos no puede estar vacía")
    elif len(comandos) > tope:
        errores.append(f"máximo {tope} comandos por petición, se recibieron {len(comandos)}")

    for i, comando in enumerate(comandos, start=1):
        try:
            validar_comando(
                comando,
                tiene_scope_config_aprobada=tiene_scope_config_aprobada,
                templates_aprobados=templates_aprobados,
            )
        except ComandoInvalido as exc:
            errores.append(f"comando #{i}: {exc.motivo} ({comando!r})")

    return ResultadoValidacionLista(ok=not errores, errores=errores)


def validar_cantidad_olts(olts: Sequence[str], *, max_olts: Optional[int] = None) -> None:
    tope = max_olts if max_olts is not None else settings.MAX_OLTS_REQUEST
    if not olts:
        raise ValueError("la lista de OLT no puede estar vacía")
    if len(olts) > tope:
        raise ValueError(f"máximo {tope} OLT por petición, se recibieron {len(olts)}")
