# ============================================================================
# model/admin/m_admin_model.py
# Proyecto: api_olt_consultas
# Descripción: Acceso a datos para el router de administración (scope admin):
#              catálogo de consultas, auditoría, sesiones telnet abiertas y la
#              ficha del propio cliente. Ver CASOS_DE_USO.md CU-05.
#
# Convención GERET: solo SQL parametrizado.
# ============================================================================

from __future__ import annotations

import datetime as _dt
from typing import Any, Dict, List, Optional

from sqlalchemy import text


def listar_consultas(engine) -> List[Dict[str, Any]]:
    """Catálogo de OLT_API_CONSULTAS + cuántas filas de comandos tiene cada una."""
    with engine.connect() as conn:
        filas = conn.execute(
            text(
                """
                SELECT c.id, c.codigo, c.descripcion, c.tabla_dato, c.timeout_seg,
                       c.perfil_credencial, c.modo_sync_max_olts, c.activo,
                       COUNT(cc.id) AS comandos
                FROM OLT_API_CONSULTAS c
                LEFT JOIN OLT_API_CONSULTA_COMANDOS cc
                       ON cc.consulta_id = c.id AND cc.activo = 1
                GROUP BY c.id
                ORDER BY c.codigo
                """
            )
        ).mappings().all()
    return [dict(f) for f in filas]


def listar_auditoria(
    engine,
    *,
    accion: Optional[str] = None,
    cliente_id: Optional[int] = None,
    olt: Optional[str] = None,
    desde: Optional[_dt.datetime] = None,
    hasta: Optional[_dt.datetime] = None,
    limite: int = 100,
) -> List[Dict[str, Any]]:
    condiciones = []
    binds: Dict[str, Any] = {"limite": max(1, min(limite, 500))}
    if accion:
        condiciones.append("accion = :accion")
        binds["accion"] = accion
    if cliente_id is not None:
        condiciones.append("cliente_id = :cliente_id")
        binds["cliente_id"] = cliente_id
    if olt:
        condiciones.append("olt = :olt")
        binds["olt"] = olt
    if desde:
        condiciones.append("fecha >= :desde")
        binds["desde"] = desde
    if hasta:
        condiciones.append("fecha <= :hasta")
        binds["hasta"] = hasta
    where = (" WHERE " + " AND ".join(condiciones)) if condiciones else ""
    with engine.connect() as conn:
        filas = conn.execute(
            text(
                f"""
                SELECT id, fecha, cliente_id, ip_origen, accion, endpoint,
                       olt, parametro, resultado, duracion_ms
                FROM OLT_API_AUDITORIA{where}
                ORDER BY fecha DESC, id DESC
                LIMIT :limite
                """
            ),
            binds,
        ).mappings().all()
    return [dict(f) for f in filas]


def sesiones_abiertas(engine) -> List[Dict[str, Any]]:
    with engine.connect() as conn:
        filas = conn.execute(
            text(
                """
                SELECT id, olt, ip, usuario_telnet, origen, job_uuid, cliente_id,
                       fecha_apertura, ultimo_latido, estado
                FROM OLT_API_SESIONES_TELNET
                WHERE estado = 'ABIERTA'
                ORDER BY fecha_apertura
                """
            )
        ).mappings().all()
    return [dict(f) for f in filas]


def cliente_por_id(engine, cliente_id: int) -> Optional[Dict[str, Any]]:
    """Ficha del cliente SIN el hash de la key (no se expone nunca)."""
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                """
                SELECT id, nombre, prefijo_key, scopes, ips_permitidas,
                       rate_limit_peticiones, rate_limit_ventana_seg,
                       max_ejecuciones_hora, activo, fecha_alta, fecha_baja
                FROM OLT_API_CLIENTES WHERE id = :id
                """
            ),
            {"id": cliente_id},
        ).mappings().first()
    return dict(fila) if fila else None
