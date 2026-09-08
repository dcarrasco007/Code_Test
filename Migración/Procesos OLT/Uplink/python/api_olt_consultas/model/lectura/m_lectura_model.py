# ============================================================================
# model/lectura/m_lectura_model.py
# Proyecto: api_olt_consultas
# Descripción: Lectura de los datos YA recolectados por los crons desde las
#              tablas OLT_* existentes. Es el modelo compartido por los 7
#              routers de lectura de F5 (olt, alarmas, uplink, equipo, vlan,
#              pon, ont): todos hacen lo mismo — "dame el último lote (o un
#              rango) de la tabla X para esta OLT" — así que la lógica vive en
#              un solo lugar en vez de duplicarse 20 veces.
#
# SEGURIDAD (ver PLAN_API_OLT.md → Seguridad → Inyección SQL):
#   - El nombre de tabla y de columna NUNCA vienen del request: se resuelven
#     desde _CATALOGO_LECTURA, un diccionario fijo en código indexado por el
#     `codigo` de consulta (que sí es un valor del path, pero se valida contra
#     las claves de este dict — si no está, el router responde 404).
#   - El valor de la OLT y las fechas van SIEMPRE como binds parametrizados.
#
# ALCANCE / LÍMITE CONOCIDO (F5): varias tablas OLT_* guardan la fecha del
#   lote en una columna VARCHAR (texto), no DATETIME — herencia de los PHP
#   originales. Para esas consultas:
#     - "último dato" SÍ funciona (se ordena por `id`, que es autoincremental
#       y por tanto monótono con el orden de inserción).
#     - el filtro ?desde&hasta NO se ofrece (comparar un rango contra texto de
#       formato no confirmado daría resultados silenciosamente incorrectos).
#       El router responde 422 con un mensaje claro.
#   Confirmar el formato real de esas columnas está en SOLICITUDES_PENDIENTES.txt.
# ============================================================================

from __future__ import annotations

from dataclasses import dataclass
from typing import Any, Dict, List, Optional, Tuple

from sqlalchemy import text

from app.config import settings


@dataclass(frozen=True)
class FuenteLectura:
    tabla: str            # tabla OLT_* poblada por el cron
    col_olt: str          # columna por la que se filtra la OLT
    filtro: str           # "server" (nombre) | "ip" (dirección IP de la OLT)
    col_fecha: str        # columna con la fecha/hora del lote
    fecha_es_datetime: bool  # True si col_fecha es DATETIME (permite ?desde&hasta)


# codigo de consulta (OLT_API_CONSULTAS.codigo) -> de dónde se lee su dato.
# Mismos códigos y mismas tablas que sql/02_seed_consultas_comandos.sql
# (columna tabla_dato). Ver PLAN_API_OLT.md, tabla "Análisis consolidado".
_CATALOGO_LECTURA: Dict[str, FuenteLectura] = {
    "uplink_trafico":         FuenteLectura("OLT_TRAFICO_UPLINK_HORA",              "server",     "server", "fecha",          True),
    "alarmas_activas":        FuenteLectura("OLT_ALARMAS",                          "equipo",     "server", "fecha",          False),
    "alarmas_detalle":        FuenteLectura("OLT_CANTIDAD_ALARMAS_DETALLE",         "equipo",     "server", "fecha",          False),
    "alarmas_critical_los":   FuenteLectura("OLT_ALARMA_CRITICAL_LOS",              "server",     "server", "fecha_proceso",  True),
    "alarmas_los_ont":        FuenteLectura("OLT_ALARMA_LOS_ONT",                   "server",     "server", "fecha_registro", True),
    "potencia_optica_uplink": FuenteLectura("OLT_POTENCIA_OPTICA_UPLINK",           "equipo",     "server", "fecha",          True),
    "tarjetas":               FuenteLectura("OLT_DETALLE_TARJETA",                  "equipo",     "server", "fecha",          False),
    "fan":                    FuenteLectura("OLT_FAN_ESTADO",                       "equipo",     "server", "fecha",          False),
    "vlan_cantidad":          FuenteLectura("OLT_CANTIDAD_VLAN",                    "equipo",     "server", "fecha",          False),
    "vlan_trafico":           FuenteLectura("OLT_VLAN_TRAFICO",                     "equipo",     "server", "fecha_hora",     True),
    "vlan_servicios":         FuenteLectura("OLT_VLAN_SERVICIO_CANTIDAD",           "server",     "server", "fecha",          True),
    "energia_alarma":         FuenteLectura("OLT_ALARMA_ENERGIA",                   "equipo",     "server", "fecha_registro", True),
    "energia_estado":         FuenteLectura("OLT_ESTADO_ENERGIA",                   "equipo",     "server", "fecha",          True),
    "pon_trafico":            FuenteLectura("OLT_TRAFICOGPON_HORA",                 "ip_equipo",  "ip",     "fecha",          False),
    "temperatura_cpu":        FuenteLectura("OLT_TEMP_CPU",                         "equipo",     "server", "fecha",          False),
    "uptime_gpon":            FuenteLectura("OLT_UPTIME",                           "equipo",     "server", "fecha",          False),
    "uplink_state":           FuenteLectura("OLT_UPLINKS_STATE",                    "equipo",     "server", "fecha",          False),
    "ont_detalle":            FuenteLectura("OLT_INFORMACION_ONT_DETALLE_COMPLETO", "equipo",     "server", "fecha_registo", True),
    "ont_reporte":            FuenteLectura("OLT_ONT_DETALLE_2",                    "equipo",     "server", "fecha",          False),
    "version":                FuenteLectura("OLT_VERSION_PARCHE_MODELO",            "equipo",     "server", "fecha",          False),
}

# Tope de filas devueltas en una consulta por rango, para no volcar millones de
# filas de las tablas grandes (vlan_trafico, pon_trafico...). Si se alcanza, el
# router lo informa en meta.motivo. Configurable en app/config/settings.py.
LIMITE_FILAS_RANGO = settings.LIMITE_FILAS_RANGO


class ConsultaNoLegible(Exception):
    """El `codigo` no está en _CATALOGO_LECTURA (no tiene tabla de lectura)."""


class RangoNoDisponible(Exception):
    """Se pidió ?desde&hasta sobre una consulta cuya columna de fecha es texto."""


def fuente_de(codigo: str) -> FuenteLectura:
    try:
        return _CATALOGO_LECTURA[codigo]
    except KeyError:
        raise ConsultaNoLegible(codigo)


def codigos_legibles() -> Tuple[str, ...]:
    return tuple(_CATALOGO_LECTURA.keys())


def valor_filtro(codigo: str, olt: Dict[str, Any]) -> Optional[str]:
    """Qué valor de la fila de OLT_SERVER se usa para filtrar la tabla del
    dato: el nombre (`server`) o la IP (`ip`, caso OLT_TRAFICOGPON_HORA, que
    no guarda el nombre de la OLT)."""
    fuente = fuente_de(codigo)
    return olt.get("server") if fuente.filtro == "server" else olt.get("ip")


# ─── Lectura del dato ───────────────────────────────────────────────────────

def leer_ultimo(engine, codigo: str, valor_olt: str) -> Tuple[List[Dict[str, Any]], Any]:
    """Devuelve (filas, fecha_dato) del último lote registrado para esa OLT.

    "Último lote" = todas las filas cuya `col_fecha` coincide con la de la
    fila de `id` más alto para esa OLT. Los crons insertan cada corrida con
    la misma fecha para todas sus filas, así que esto agrupa una corrida."""
    f = fuente_de(codigo)
    sql = text(
        f"""
        SELECT * FROM `{f.tabla}`
        WHERE `{f.col_olt}` = :olt
          AND `{f.col_fecha}` = (
              SELECT `{f.col_fecha}` FROM `{f.tabla}`
              WHERE `{f.col_olt}` = :olt
              ORDER BY `id` DESC LIMIT 1
          )
        ORDER BY `id`
        """
    )
    with engine.connect() as conn:
        filas = [dict(m) for m in conn.execute(sql, {"olt": valor_olt}).mappings().all()]
    fecha_dato = filas[0].get(f.col_fecha) if filas else None
    return filas, fecha_dato


def leer_rango(
    engine, codigo: str, valor_olt: str, desde_hasta: Tuple[Any, Any]
) -> Tuple[List[Dict[str, Any]], bool]:
    """Serie histórica entre dos fechas (inclusive). Devuelve (filas, truncado).

    Solo para consultas con `fecha_es_datetime=True`; el resto levanta
    RangoNoDisponible (el router lo traduce a 422)."""
    f = fuente_de(codigo)
    if not f.fecha_es_datetime:
        raise RangoNoDisponible(codigo)
    desde, hasta = desde_hasta
    sql = text(
        f"""
        SELECT * FROM `{f.tabla}`
        WHERE `{f.col_olt}` = :olt
          AND `{f.col_fecha}` BETWEEN :desde AND :hasta
        ORDER BY `{f.col_fecha}`, `id`
        LIMIT :limite
        """
    )
    with engine.connect() as conn:
        filas = [
            dict(m)
            for m in conn.execute(
                sql,
                {"olt": valor_olt, "desde": desde, "hasta": hasta, "limite": LIMITE_FILAS_RANGO + 1},
            ).mappings().all()
        ]
    truncado = len(filas) > LIMITE_FILAS_RANGO
    return filas[:LIMITE_FILAS_RANGO], truncado


# ─── Último log crudo de telnet para esa consulta/OLT (?con_log=true) ────────

def ultimo_log_telnet(engine, olt_server: str, codigo: str) -> Optional[Dict[str, Any]]:
    """Última fila de OLT_API_LOG_TELNET para esa OLT + consulta, o None.

    OLT_API_LOG_TELNET.olt siempre guarda el NOMBRE de la OLT (lo escribe la
    propia API en F4/F6), aunque la tabla del dato se filtre por IP."""
    sql = text(
        """
        SELECT id, fecha, comandos_enviados, log_crudo, duracion_ms, exito, error
        FROM OLT_API_LOG_TELNET
        WHERE olt = :olt AND consulta_codigo = :codigo
        ORDER BY fecha DESC, id DESC
        LIMIT 1
        """
    )
    with engine.connect() as conn:
        fila = conn.execute(sql, {"olt": olt_server, "codigo": codigo}).mappings().first()
    return dict(fila) if fila else None
