# ============================================================================
# router/jobs/jobsRouter.py
# Proyecto: api_olt_consultas
# Descripción: Seguimiento de las ejecuciones asíncronas (F6). Ver
#              CASOS_DE_USO.md CU-04.
#
#   GET /jobs/{uuid}   estado + resultado de un job
#   GET /jobs          lista de jobs (los propios; con scope admin, todos)
#
# Scope: 'ejecutar_consulta' (quien puede lanzar jobs puede consultarlos).
# Un cliente solo ve SUS jobs, salvo que tenga scope 'admin'.
# ============================================================================

from __future__ import annotations

import json
from typing import Any, Dict, Optional

from fastapi import APIRouter, Depends, HTTPException, Query

from app.config.db import get_engine
from model.jobs import m_jobs_model
from utils.func import serializar_valor
from utils.token import Cliente, requerir_scopes

jobs_router = APIRouter(prefix="/jobs")


def _serializar_job(fila: Dict[str, Any], *, incluir_resultado: bool) -> Dict[str, Any]:
    salida = {
        "uuid": fila["uuid"],
        "tipo": fila.get("tipo"),
        "estado": fila["estado"],
        "cliente_id": fila.get("cliente_id"),
        "fecha_solicitud": serializar_valor(fila.get("fecha_solicitud")),
        "fecha_inicio": serializar_valor(fila.get("fecha_inicio")),
        "fecha_fin": serializar_valor(fila.get("fecha_fin")),
        "error": fila.get("error"),
    }
    if incluir_resultado:
        for campo in ("parametros", "resultado"):
            valor = fila.get(campo)
            if isinstance(valor, str) and valor:
                try:
                    valor = json.loads(valor)
                except ValueError:
                    pass
            salida[campo] = valor
    return salida


@jobs_router.get("/{uuid}", summary="Estado y resultado de un job")
def obtener_job(uuid: str, cliente: Cliente = Depends(requerir_scopes("ejecutar_consulta"))):
    fila = m_jobs_model.obtener(get_engine(), uuid)
    if fila is None:
        raise HTTPException(status_code=404, detail=f"job '{uuid}' no encontrado.")
    if fila.get("cliente_id") != cliente.id and not cliente.tiene_scope("admin"):
        raise HTTPException(status_code=403, detail="este job pertenece a otro cliente.")
    return {"ok": True, "job": _serializar_job(fila, incluir_resultado=True)}


@jobs_router.get("", summary="Lista de jobs")
def listar_jobs(
    estado: Optional[str] = Query(None, description="PENDIENTE|RUNNING|OK|ERROR|TIMEOUT"),
    limite: int = Query(50, ge=1, le=200),
    cliente: Cliente = Depends(requerir_scopes("ejecutar_consulta")),
):
    # admin ve todos; el resto, solo los suyos.
    cliente_id = None if cliente.tiene_scope("admin") else cliente.id
    filas = m_jobs_model.listar(get_engine(), cliente_id=cliente_id, estado=estado, limite=limite)
    return {
        "ok": True,
        "cantidad": len(filas),
        "jobs": [_serializar_job(f, incluir_resultado=False) for f in filas],
    }
