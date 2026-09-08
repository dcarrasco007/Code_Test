# ============================================================================
# schema/ejecucion_schema.py
# Proyecto: api_olt_consultas
# Descripción: Modelos Pydantic v2 para los bodies de los endpoints de
#              ejecución en vivo (F6). Solo validación estructural / de tipos;
#              la validación de negocio de cada comando (deny-list, lectura vs
#              template aprobado, scope) la hace utils/validador_comandos.py
#              en el router, porque necesita el scope del cliente y los
#              templates de OLT_API_COMANDOS_APROBADOS.
#
# NOTA (README_PYTHON_API.md): se crean estos schemas porque los endpoints
# reciben un BODY con varios campos; los GET de F5 no llevan schema (solo
# params de ruta/query).
# ============================================================================

from __future__ import annotations

from typing import List, Literal, Optional

from pydantic import BaseModel, Field, field_validator

# `server` y `codigo` nunca se interpolan en SQL (las queries usan binds), pero
# se acotan igual para rechazar basura temprano y evitar que un valor absurdo
# llegue al telnet o a la auditoría.
_SERVER_PATTERN = r"^[A-Za-z0-9][A-Za-z0-9_.\-]{0,98}[A-Za-z0-9]$"
_CODIGO_PATTERN = r"^[a-z][a-z0-9_]{1,49}$"

TipoInterfaz = Literal["eth", "giu", "scu", "mpu", "gpon"]


class EjecutarConsultaRequest(BaseModel):
    server: str = Field(..., pattern=_SERVER_PATTERN, description="Nombre de la OLT (OLT_SERVER.server)")
    forzar_async: bool = Field(
        False,
        description="Forzar respuesta 202 + job aunque la consulta entre en modo sincrónico.",
    )


class NavegacionComandos(BaseModel):
    """A qué interfaz entrar antes de correr los comandos. La API arma
    'interface {tipo} 0/{slot}'; el cliente no envía navegación."""

    tipo: TipoInterfaz
    slot: int = Field(..., ge=0, le=20)


class EjecutarComandosRequest(BaseModel):
    server: str = Field(..., pattern=_SERVER_PATTERN)
    navegacion: Optional[NavegacionComandos] = None
    comandos: List[str] = Field(..., min_length=1, description="Comandos en orden. Cada uno se valida en el router.")

    @field_validator("comandos")
    @classmethod
    def _sin_vacios(cls, v: List[str]) -> List[str]:
        limpios = [c.strip() for c in v]
        if any(not c for c in limpios):
            raise ValueError("ningún comando puede estar vacío")
        return limpios


class EjecutarLoteRequest(BaseModel):
    codigo: str = Field(..., pattern=_CODIGO_PATTERN, description="Código de consulta de OLT_API_CONSULTAS")
    servers: List[str] = Field(..., min_length=1, description="OLT sobre las que correr la consulta")

    @field_validator("servers")
    @classmethod
    def _servers_validos(cls, v: List[str]) -> List[str]:
        import re

        for s in v:
            if not re.match(_SERVER_PATTERN, s):
                raise ValueError(f"nombre de OLT inválido: {s!r}")
        if len(set(v)) != len(v):
            raise ValueError("hay OLT repetidas en la lista")
        return v
