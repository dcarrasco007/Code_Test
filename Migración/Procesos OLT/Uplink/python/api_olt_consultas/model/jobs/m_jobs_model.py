# ============================================================================
# model/jobs/m_jobs_model.py
# Proyecto: api_olt_consultas
# Descripción: Acceso a OLT_API_JOBS (ejecuciones asíncronas). Lo usan
#              utils/jobs.py (el worker) y router/jobs/jobsRouter.py.
#
# Convención GERET: solo SQL parametrizado (text() + binds).
# ============================================================================

from __future__ import annotations

import json
from typing import Any, Dict, List, Optional

from sqlalchemy import text


def crear(engine, *, uuid: str, cliente_id: Optional[int], tipo: str, parametros: Dict[str, Any]) -> None:
    with engine.begin() as conn:
        conn.execute(
            text(
                """
                INSERT INTO OLT_API_JOBS (uuid, cliente_id, tipo, parametros, estado)
                VALUES (:uuid, :cliente_id, :tipo, :parametros, 'PENDIENTE')
                """
            ),
            {
                "uuid": uuid, "cliente_id": cliente_id, "tipo": tipo,
                "parametros": json.dumps(parametros, ensure_ascii=False),
            },
        )


def marcar_running(engine, uuid: str) -> None:
    with engine.begin() as conn:
        conn.execute(
            text(
                "UPDATE OLT_API_JOBS SET estado='RUNNING', fecha_inicio=NOW() "
                "WHERE uuid=:uuid AND estado='PENDIENTE'"
            ),
            {"uuid": uuid},
        )


def marcar_fin(engine, uuid: str, estado: str, *, resultado: Any = None, error: Optional[str] = None) -> None:
    """estado ∈ OK|ERROR|TIMEOUT. `resultado` se serializa a JSON."""
    with engine.begin() as conn:
        conn.execute(
            text(
                """
                UPDATE OLT_API_JOBS
                SET estado=:estado, fecha_fin=NOW(),
                    resultado=:resultado, error=:error
                WHERE uuid=:uuid
                """
            ),
            {
                "uuid": uuid, "estado": estado,
                "resultado": json.dumps(resultado, ensure_ascii=False, default=str) if resultado is not None else None,
                "error": (error or None) and str(error)[:255],
            },
        )


def obtener(engine, uuid: str) -> Optional[Dict[str, Any]]:
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                """
                SELECT uuid, cliente_id, tipo, parametros, estado,
                       fecha_solicitud, fecha_inicio, fecha_fin, resultado, error
                FROM OLT_API_JOBS WHERE uuid=:uuid
                """
            ),
            {"uuid": uuid},
        ).mappings().first()
    return dict(fila) if fila else None


def listar(
    engine, *, cliente_id: Optional[int] = None, estado: Optional[str] = None, limite: int = 50
) -> List[Dict[str, Any]]:
    """cliente_id None = todos (uso admin). Nunca devuelve `resultado` (puede
    ser enorme) — para eso está GET /jobs/{uuid}."""
    condiciones = []
    binds: Dict[str, Any] = {"limite": max(1, min(limite, 200))}
    if cliente_id is not None:
        condiciones.append("cliente_id = :cliente_id")
        binds["cliente_id"] = cliente_id
    if estado:
        condiciones.append("estado = :estado")
        binds["estado"] = estado
    where = (" WHERE " + " AND ".join(condiciones)) if condiciones else ""
    with engine.connect() as conn:
        filas = conn.execute(
            text(
                f"""
                SELECT uuid, cliente_id, tipo, estado,
                       fecha_solicitud, fecha_inicio, fecha_fin, error
                FROM OLT_API_JOBS{where}
                ORDER BY fecha_solicitud DESC
                LIMIT :limite
                """
            ),
            binds,
        ).mappings().all()
    return [dict(f) for f in filas]


def marcar_interrumpidos_al_iniciar(engine) -> int:
    """Todo job que quedó PENDIENTE o RUNNING es de un proceso anterior: el
    worker en memoria no sobrevive un reinicio y los jobs no se reanudan
    solos (ver CASOS_DE_USO.md CU-04/A1). Se marcan ERROR."""
    with engine.begin() as conn:
        resultado = conn.execute(
            text(
                """
                UPDATE OLT_API_JOBS
                SET estado='ERROR', fecha_fin=NOW(),
                    error='interrumpido por reinicio de la API'
                WHERE estado IN ('PENDIENTE','RUNNING')
                """
            )
        )
        return resultado.rowcount
