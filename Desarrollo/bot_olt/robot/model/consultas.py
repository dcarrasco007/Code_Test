"""Capa de acceso a datos (DAL) del bot OLT.

Toda query SQL del proyecto vive en este archivo. Ningun otro modulo
importa el engine ni escribe SQL.

Reglas de la capa:
  - Todas las funciones publicas son `async` y ejecutan el SQL con
    `asyncio.to_thread`, porque SQLAlchemy es sincrono y bloquearia el
    event loop de Telegram.
  - Nunca se interpola texto del usuario en el SQL: siempre parametros `:nombre`.
  - El manejo de errores va por el decorador `@_safe(default)`, no con
    try/except repetido en cada funcion.
"""

import asyncio
import functools
from typing import Optional

from sqlalchemy import text

from app.config import engine_aden
from utils.func import marker_errors

# =============================================================
# Helpers privados
# =============================================================


def _fetch(engine, sql, params=None) -> list:
    """Ejecuta una query y retorna una lista de diccionarios."""
    with engine.connect() as conn:
        result = conn.execute(sql, params or {})
        return [dict(fila) for fila in result.mappings()]


def _in_clause(valores, prefijo: str = "v") -> tuple:
    """Construye los parametros y la clausula IN para una lista de valores.

    Retorna (params, clausula), por ejemplo:
        ({"v0": "NORTE", "v1": "SUR"}, ":v0, :v1")
    """
    params = {f"{prefijo}{i}": valor for i, valor in enumerate(valores)}
    clausula = ", ".join(f":{nombre}" for nombre in params)
    return params, clausula


def _safe(default):
    """Loguea el error con traceback y retorna `default` en vez de propagar.

    Si `default` es callable (list, dict, set) se invoca en cada fallo, para
    no compartir un mutable entre llamadas.
    """

    def decorador(func):
        @functools.wraps(func)
        async def wrapper(*args, **kwargs):
            try:
                return await func(*args, **kwargs)
            except Exception as e:
                marker_errors(f"Error en {func.__name__}: {e}")
                return default() if callable(default) else default

        return wrapper

    return decorador


# =============================================================
# SQL — Autorizacion
# =============================================================

SQL_VERIFICAR_USUARIO = text(
    """
    SELECT
        OLT_BOT_USUARIOS.chat_id,
        OLT_BOT_USUARIOS.usuario,
        OLT_BOT_USUARIOS.nombre,
        OLT_BOT_USUARIOS.perfil,
        OLT_BOT_USUARIOS.activo
    FROM OLT_BOT_USUARIOS
    WHERE OLT_BOT_USUARIOS.chat_id = :chat_id
      AND OLT_BOT_USUARIOS.activo = 1
    """
)

# =============================================================
# SQL — Inventario de OLT
# =============================================================

SQL_INVENTARIO = text(
    """
    SELECT
        OLT_SERVER.`server`   AS olt,
        OLT_SERVER.ip         AS ip,
        OLT_SERVER.region     AS region,
        OLT_SERVER.modelo     AS modelo,
        OLT_SERVER.sw         AS sw,
        OLT_SERVER.tipo       AS tipo,
        OLT_SERVER.servicio   AS servicio,
        OLT_SERVER.pop        AS pop
    FROM OLT_SERVER
    GROUP BY OLT_SERVER.`server`
    ORDER BY OLT_SERVER.region, olt
    """
)

SQL_REGIONES = text(
    """
    SELECT DISTINCT OLT_SERVER.region AS region
    FROM OLT_SERVER
    WHERE OLT_SERVER.region IS NOT NULL AND OLT_SERVER.region <> ''
    ORDER BY region
    """
)

# =============================================================
# SQL — Puertas PON / ocupacion GPON
# =============================================================

# TODO: confirmar con el responsable las reglas de negocio de esta query.
#   1. "Puerto utilizado" se toma como up_mbps > 1 en la fecha consultada,
#      criterio heredado de Desarrollo/puertas pon/option_gpon.php.
#   2. Los uplinks se excluyen con marca IN (1, 2). El PHP original escribe
#      `WHERE marca = '2' OR '1'`, que en MySQL siempre evalua verdadero y por
#      lo tanto excluye TODOS los puertos; aqui se corrigio a IN (1, 2), pero
#      falta confirmar cuales marcas son realmente uplink.
#   3. `OLT_ONT_PCS.zs_comercial` se usa como total de puertas de la OLT.
# El conteo de puertos utilizados va en una tabla derivada y no en una
# subconsulta correlacionada: MySQL no admite funciones de agregacion
# (MAX) dentro del WHERE de una subconsulta, y ademas asi se recorre
# OLT_TRAFICOGPON una sola vez en lugar de una vez por OLT.
SQL_PUERTAS_PON = text(
    """
    SELECT
        olt.olt                        AS olt,
        olt.ip                         AS ip,
        olt.region                     AS region,
        olt.puertas_totales            AS puertas_totales,
        COALESCE(uso.puertas_utilizadas, 0) AS puertas_utilizadas
    FROM (
        SELECT
            OLT_SERVER.`server`           AS olt,
            MAX(OLT_SERVER.ip)            AS ip,
            MAX(OLT_SERVER.region)        AS region,
            MAX(OLT_ONT_PCS.zs_comercial) AS puertas_totales
        FROM OLT_SERVER
        LEFT JOIN OLT_ONT_PCS ON OLT_ONT_PCS.`server` = OLT_SERVER.`server`
        GROUP BY OLT_SERVER.`server`
    ) olt
    LEFT JOIN (
        SELECT
            OLT_TRAFICOGPON.ip_equipo            AS ip,
            COUNT(DISTINCT OLT_TRAFICOGPON.port) AS puertas_utilizadas
        FROM OLT_TRAFICOGPON
        WHERE OLT_TRAFICOGPON.fecha   = :fecha
          AND OLT_TRAFICOGPON.up_mbps > 1
          AND OLT_TRAFICOGPON.port NOT IN (
                SELECT DISTINCT OLT_PUERTOS_UPLINKS.puerto
                FROM OLT_PUERTOS_UPLINKS
                WHERE OLT_PUERTOS_UPLINKS.marca IN (1, 2)
          )
        GROUP BY OLT_TRAFICOGPON.ip_equipo
    ) uso ON uso.ip = olt.ip
    ORDER BY olt.region, olt.olt
    """
)

# =============================================================
# SQL — Alarmas criticas
# =============================================================

SQL_ALARMAS_CRITICAS = text(
    """
    SELECT
        OLT_ALARMA_CRITICAL_LOS.`server`       AS olt,
        OLT_ALARMA_CRITICAL_LOS.ip             AS ip,
        OLT_ALARMA_CRITICAL_LOS.ubicacion      AS ubicacion,
        OLT_ALARMA_CRITICAL_LOS.alarma         AS alarma,
        OLT_ALARMA_CRITICAL_LOS.fecha          AS fecha,
        OLT_ALARMA_CRITICAL_LOS.fecha_proceso  AS fecha_proceso,
        OLT_ALARMA_CRITICAL_LOS.estado         AS estado
    FROM OLT_ALARMA_CRITICAL_LOS
    ORDER BY OLT_ALARMA_CRITICAL_LOS.fecha DESC
    """
)

# =============================================================
# SQL — Trafico PON
# =============================================================

SQL_TRAFICO_PON = text(
    """
    SELECT
        OLT_TRAFICOGPON.ip_equipo  AS ip,
        OLT_TRAFICOGPON.port       AS puerto,
        OLT_TRAFICOGPON.up_mbps    AS up_mbps,
        OLT_TRAFICOGPON.down_mbps  AS down_mbps,
        OLT_TRAFICOGPON.fecha      AS fecha
    FROM OLT_TRAFICOGPON
    WHERE OLT_TRAFICOGPON.ip_equipo = :ip
      AND OLT_TRAFICOGPON.fecha     = :fecha
    ORDER BY OLT_TRAFICOGPON.up_mbps DESC
    """
)


# =============================================================
# Funciones publicas — Autorizacion
# =============================================================


@_safe(None)
async def verificar_usuario(chat_id: int) -> Optional[dict]:
    """Retorna los datos del usuario autorizado, o None si no lo esta."""
    filas = await asyncio.to_thread(
        _fetch, engine_aden, SQL_VERIFICAR_USUARIO, {"chat_id": chat_id}
    )
    return filas[0] if filas else None


# =============================================================
# Funciones publicas — Consultas
# =============================================================


@_safe(list)
async def consultar_inventario(regiones: Optional[list] = None) -> list:
    """Inventario de OLT. Si se pasan regiones, filtra por ellas."""
    if not regiones:
        return await asyncio.to_thread(_fetch, engine_aden, SQL_INVENTARIO)

    params, in_clause = _in_clause(regiones, "region")
    sql = text(
        f"""
        SELECT
            OLT_SERVER.`server` AS olt,
            OLT_SERVER.ip       AS ip,
            OLT_SERVER.region   AS region,
            OLT_SERVER.modelo   AS modelo,
            OLT_SERVER.sw       AS sw,
            OLT_SERVER.tipo     AS tipo,
            OLT_SERVER.servicio AS servicio,
            OLT_SERVER.pop      AS pop
        FROM OLT_SERVER
        WHERE OLT_SERVER.region IN ({in_clause})
        GROUP BY OLT_SERVER.`server`
        ORDER BY OLT_SERVER.region, olt
        """
    )
    return await asyncio.to_thread(_fetch, engine_aden, sql, params)


@_safe(list)
async def consultar_regiones() -> list:
    """Listado de regiones distintas, para armar filtros en el menu."""
    return await asyncio.to_thread(_fetch, engine_aden, SQL_REGIONES)


@_safe(list)
async def consultar_puertas_pon(fecha: str) -> list:
    """Puertas PON totales y utilizadas por OLT para la fecha indicada."""
    return await asyncio.to_thread(
        _fetch, engine_aden, SQL_PUERTAS_PON, {"fecha": fecha}
    )


@_safe(list)
async def consultar_alarmas_criticas() -> list:
    """Alarmas criticas / LOS vigentes."""
    return await asyncio.to_thread(_fetch, engine_aden, SQL_ALARMAS_CRITICAS)


@_safe(list)
async def consultar_trafico_pon(ip: str, fecha: str) -> list:
    """Trafico por puerto PON de una OLT en una fecha."""
    return await asyncio.to_thread(
        _fetch, engine_aden, SQL_TRAFICO_PON, {"ip": ip, "fecha": fecha}
    )


# =============================================================
# Escrituras (etapa futura)
# =============================================================
#
# El bot hoy es de solo lectura. Cuando se habiliten inserciones, las
# funciones van en esta seccion con el prefijo `registrar_*` y siguen el
# mismo patron: SQL con parametros :nombre, `async` + asyncio.to_thread,
# y @_safe(False) para retornar el resultado de la operacion.
#
# Plantilla de referencia:
#
#   SQL_REGISTRAR_ALGO = text(
#       "INSERT INTO TABLA (campo_a, campo_b) VALUES (:campo_a, :campo_b)"
#   )
#
#   def _execute(engine, sql, params=None) -> int:
#       with engine.begin() as conn:              # begin() hace commit solo
#           return conn.execute(sql, params or {}).rowcount
#
#   @_safe(False)
#   async def registrar_algo(campo_a, campo_b) -> bool:
#       filas = await asyncio.to_thread(
#           _execute, engine_aden, SQL_REGISTRAR_ALGO,
#           {"campo_a": campo_a, "campo_b": campo_b},
#       )
#       return filas > 0
#
# La tabla destino y sus campos deben existir antes: el DDL se entrega como
# archivo .sql en BD/ para que lo ejecute el responsable, no el bot ni el agente.
