# ============================================================================
# model/ejecutar/m_ejecutar_model.py
# Proyecto: api_olt_consultas
# Descripción: Datos que el router de ejecución (F6) necesita ANTES de llamar
#              a utils/ejecutor.py:
#                - metadatos de la consulta (perfil de credencial, timeout,
#                  si está activa)   → OLT_API_CONSULTAS
#                - templates de configuración aprobados y activos, para
#                  utils/validador_comandos.py  → OLT_API_COMANDOS_APROBADOS
#
# (utils/ejecutor.py ya lee por su cuenta las FILAS de comandos de
#  OLT_API_CONSULTA_COMANDOS; aquí solo va lo que el router usa aparte.)
#
# Convención GERET: solo SQL parametrizado.
# ============================================================================

from __future__ import annotations

from typing import Any, Dict, List, Optional

from sqlalchemy import text


def consulta_meta(engine, codigo: str) -> Optional[Dict[str, Any]]:
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                """
                SELECT id, codigo, descripcion, tabla_dato, timeout_seg,
                       perfil_credencial, modo_sync_max_olts, activo
                FROM OLT_API_CONSULTAS WHERE codigo = :codigo
                """
            ),
            {"codigo": codigo},
        ).mappings().first()
    return dict(fila) if fila else None


def templates_aprobados_activos(engine) -> List[str]:
    """Regex de OLT_API_COMANDOS_APROBADOS.activo=1. Día 1 la tabla nace
    vacía (decisión confirmada): esto devuelve [] hasta que el responsable
    apruebe comandos de configuración vía INSERT."""
    with engine.connect() as conn:
        filas = conn.execute(
            text("SELECT template FROM OLT_API_COMANDOS_APROBADOS WHERE activo = 1")
        ).fetchall()
    return [f[0] for f in filas if f[0]]
