# ============================================================================
# model/olt/m_olt_model.py
# Proyecto: api_olt_consultas
# Descripción: Acceso a OLT_SERVER — la tabla que resuelve un `server` (nombre
#              que llega en la URL) a su modelo / IP / región. TODOS los
#              endpoints de lectura empiezan llamando aquí: si la OLT no existe
#              en OLT_SERVER, el router responde 404 (ver CASOS_DE_USO.md
#              CU-01, errores).
#
# Convención GERET (README_PYTHON_API.md): este archivo SOLO tiene SQL
# parametrizado (sqlalchemy.text() + binds). Nunca interpola valores del
# request en el string de la query.
# ============================================================================

from __future__ import annotations

from typing import Any, Dict, List, Optional

from sqlalchemy import text

# Columnas expuestas de OLT_SERVER (whitelist en código: la lista NUNCA viene
# del request). El resto de columnas de la tabla (pto1..pto12, fórmulas,
# flags de migración...) no son de interés para un consumidor de la API.
_COLUMNAS_OLT = (
    "server", "modelo", "ip", "region", "tipo", "servicio",
    "pop", "comuna", "ubicacion",
)
_SELECT_OLT = ", ".join(f"`{c}`" for c in _COLUMNAS_OLT)


def obtener_olt(engine, server: str) -> Optional[Dict[str, Any]]:
    """Fila de OLT_SERVER para ese `server`, o None si no existe.

    Si hubiera más de una fila con el mismo `server` (dato sucio), devuelve la
    de `id` más alto (la más reciente)."""
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                f"SELECT {_SELECT_OLT} FROM OLT_SERVER "
                f"WHERE server = :server ORDER BY id DESC LIMIT 1"
            ),
            {"server": server},
        ).mappings().first()
    return dict(fila) if fila else None


def listar_olts(
    engine,
    *,
    region: Optional[str] = None,
    modelo: Optional[str] = None,
) -> List[Dict[str, Any]]:
    """Catálogo de OLT conocidas, opcionalmente filtrado por región o modelo.
    Los filtros son exactos (no LIKE): se usan para acotar, no para buscar."""
    condiciones = []
    binds: Dict[str, Any] = {}
    if region:
        condiciones.append("region = :region")
        binds["region"] = region
    if modelo:
        condiciones.append("modelo = :modelo")
        binds["modelo"] = modelo
    where = (" WHERE " + " AND ".join(condiciones)) if condiciones else ""
    with engine.connect() as conn:
        filas = conn.execute(
            text(f"SELECT {_SELECT_OLT} FROM OLT_SERVER{where} ORDER BY server"),
            binds,
        ).mappings().all()
    return [dict(f) for f in filas]
