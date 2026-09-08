# ============================================================================
# model/logs/m_logs_model.py
# Proyecto: api_olt_consultas
# Descripción: Acceso a OLT_API_LOG_TELNET para GET /logs/telnet (scope admin).
#              El uso típico: un operador reporta un dato raro y se busca el
#              log crudo de esa consulta/OLT para compararlo con lo parseado
#              (ver CASOS_DE_USO.md CU-05).
#
# Convención GERET: solo SQL parametrizado.
# ============================================================================

from __future__ import annotations

import datetime as _dt
from typing import Any, Dict, List, Optional

from sqlalchemy import text

# Columnas de la lista (SIN log_crudo: puede pesar MB). El texto crudo completo
# se pide fila a fila con obtener_log(id).
_COLS_LISTA = (
    "id, fecha, olt, ip, consulta_codigo, origen, job_uuid, cliente_id, "
    "comandos_enviados, duracion_ms, exito, error"
)


def buscar(
    engine,
    *,
    olt: Optional[str] = None,
    consulta: Optional[str] = None,
    desde: Optional[_dt.datetime] = None,
    hasta: Optional[_dt.datetime] = None,
    exito: Optional[bool] = None,
    limite: int = 20,
) -> List[Dict[str, Any]]:
    condiciones = []
    binds: Dict[str, Any] = {"limite": max(1, min(limite, 100))}
    if olt:
        condiciones.append("olt = :olt")
        binds["olt"] = olt
    if consulta:
        condiciones.append("consulta_codigo = :consulta")
        binds["consulta"] = consulta
    if desde:
        condiciones.append("fecha >= :desde")
        binds["desde"] = desde
    if hasta:
        condiciones.append("fecha <= :hasta")
        binds["hasta"] = hasta
    if exito is not None:
        condiciones.append("exito = :exito")
        binds["exito"] = 1 if exito else 0
    where = (" WHERE " + " AND ".join(condiciones)) if condiciones else ""
    with engine.connect() as conn:
        filas = conn.execute(
            text(f"SELECT {_COLS_LISTA} FROM OLT_API_LOG_TELNET{where} ORDER BY fecha DESC, id DESC LIMIT :limite"),
            binds,
        ).mappings().all()
    return [dict(f) for f in filas]


def obtener_log(engine, log_id: int) -> Optional[Dict[str, Any]]:
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                f"SELECT {_COLS_LISTA}, log_crudo FROM OLT_API_LOG_TELNET WHERE id = :id"
            ),
            {"id": log_id},
        ).mappings().first()
    return dict(fila) if fila else None
