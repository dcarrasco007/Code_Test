# ============================================================================
# utils/auditoria.py
# Proyecto: api_olt_consultas
# Descripción: Inserta filas en OLT_API_AUDITORIA (toda petición autenticada o
#              rechazada — modelo: la tabla existente OLT_BOT_AUDITORIA). Lo
#              usan:
#                - utils/token.py            → AUTH_OK / AUTH_FAIL
#                - middleware_auditoria (main.py) → LECTURA / EJECUCION / RECHAZADO
#
# IMPORTANTE: esta función SÍ ejecuta un INSERT contra la base de datos real
# en tiempo de ejecución de la API — eso es operación normal de la aplicación,
# no una migración manual del agente (ver README_LIMITES.md sección 3, que
# limita al *agente de IA* ejecutando DDL/DML por su cuenta durante el
# desarrollo, no al código de la propia API sirviendo tráfico).
#
# La auditoría es "best effort": si falla el INSERT (ej. tabla aún no creada
# por el responsable, o BD caída) NO debe tumbar la petición del cliente; se
# registra el error con loguru y se continúa.
#
# Para modificar:
#   - Columnas de OLT_API_AUDITORIA → sql/01_tablas_api.sql (sección 3)
# ============================================================================

from __future__ import annotations

from typing import Optional

from loguru import logger
from sqlalchemy import text
from sqlalchemy.engine import Engine

_INSERT_SQL = text(
    """
    INSERT INTO OLT_API_AUDITORIA
        (cliente_id, ip_origen, accion, endpoint, olt, parametro, resultado, duracion_ms)
    VALUES
        (:cliente_id, :ip_origen, :accion, :endpoint, :olt, :parametro, :resultado, :duracion_ms)
    """
)

# Valores válidos documentados en sql/01_tablas_api.sql (columna `accion`).
ACCIONES_VALIDAS = {"AUTH_OK", "AUTH_FAIL", "LECTURA", "EJECUCION", "RECHAZADO"}


def registrar(
    engine: Engine,
    *,
    accion: str,
    ip_origen: str,
    endpoint: str,
    cliente_id: Optional[int] = None,
    olt: Optional[str] = None,
    parametro: Optional[str] = None,
    resultado: Optional[str] = None,
    duracion_ms: Optional[int] = None,
) -> None:
    """Inserta una fila de auditoría. Nunca propaga la excepción: un fallo al
    auditar no debe convertirse en un 500 para el cliente que solo quería
    hacer una consulta."""
    if accion not in ACCIONES_VALIDAS:
        # Programación defensiva: esto es un bug del llamador, no del cliente.
        logger.warning("utils.auditoria: accion desconocida '{}', se registra igual", accion)

    # parametro/endpoint truncados al largo de columna (255/150) por si el
    # llamador pasa algo más largo (ej. lista de comandos completa).
    parametro_trunc = parametro[:255] if parametro else None
    endpoint_trunc = endpoint[:150] if endpoint else endpoint

    try:
        with engine.begin() as conn:
            conn.execute(
                _INSERT_SQL,
                {
                    "cliente_id": cliente_id,
                    "ip_origen": ip_origen,
                    "accion": accion,
                    "endpoint": endpoint_trunc,
                    "olt": olt,
                    "parametro": parametro_trunc,
                    "resultado": resultado,
                    "duracion_ms": duracion_ms,
                },
            )
    except Exception as exc:  # noqa: BLE001 - best-effort, nunca debe romper la petición
        logger.error(
            "utils.auditoria: no se pudo insertar en OLT_API_AUDITORIA (accion={}, endpoint={}): {}",
            accion, endpoint_trunc, exc,
        )
