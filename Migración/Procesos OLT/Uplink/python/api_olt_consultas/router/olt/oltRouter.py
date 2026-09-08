# ============================================================================
# router/olt/oltRouter.py
# Proyecto: api_olt_consultas
# Descripción: Catálogo de OLT conocidas (tabla OLT_SERVER). Es el módulo que
#              el consumidor usa para saber qué `server` puede pasar al resto
#              de endpoints y con qué modelo/IP/región está registrado.
#
#   GET /olt                 -> lista de OLT (filtros opcionales ?region= ?modelo=)
#   GET /olt/{server}        -> ficha de una OLT
#
# Scope requerido: 'lectura' (ver utils/func.cliente_lectura).
# ============================================================================

from __future__ import annotations

from typing import Optional

from fastapi import APIRouter, Depends, HTTPException

from app.config.db import get_engine
from model.olt.m_olt_model import listar_olts, obtener_olt
from utils.func import cliente_lectura
from utils.token import Cliente

olt_router = APIRouter(prefix="/olt")


@olt_router.get("", summary="Lista de OLT registradas")
def listar(
    region: Optional[str] = None,
    modelo: Optional[str] = None,
    cliente: Cliente = Depends(cliente_lectura),
):
    filas = listar_olts(get_engine(), region=region, modelo=modelo)
    return {"ok": True, "cantidad": len(filas), "olts": filas}


@olt_router.get("/{server}", summary="Ficha de una OLT")
def detalle(server: str, cliente: Cliente = Depends(cliente_lectura)):
    olt = obtener_olt(get_engine(), server)
    if olt is None:
        raise HTTPException(status_code=404, detail=f"OLT '{server}' no existe en OLT_SERVER.")
    return {"ok": True, "olt": olt}
