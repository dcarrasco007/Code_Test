# ============================================================================
# utils/log_api.py
# Proyecto: uplink_trafico_15m (compartido por los 3 procesos)
# Descripción: Integración OPCIONAL con api_olt_consultas (F7 de ese proyecto).
#              Deja el log crudo de cada sesión telnet de tráfico en la tabla
#              OLT_API_LOG_TELNET (origen='cron'), para que el endpoint
#              GET /uplink/trafico/{server}?con_log=true de la API pueda
#              devolver "qué respondió el equipo" sin que el API tenga que
#              abrir su propia sesión telnet.
#
# CONTRATO (mismas columnas que api_olt_consultas/sql/01_tablas_api.sql §7):
#   olt, ip, consulta_codigo='uplink_trafico', origen='cron', job_uuid=NULL,
#   cliente_id=NULL, comandos_enviados, log_crudo, duracion_ms, exito, error.
#
# GARANTÍAS (esto NO puede romper el proceso de tráfico):
#   - Si config.settings.LOG_API_TELNET no es 'true' → no hace absolutamente
#     nada (ni siquiera toca la BD).
#   - Usa su PROPIA transacción corta (engine.begin()), separada de la del
#     worker: un fallo aquí no revierte ningún INSERT real de tráfico.
#   - Nunca propaga una excepción. Si la tabla OLT_API_LOG_TELNET todavía no
#     existe (el responsable no corrió sql/01_tablas_api.sql), avisa UNA vez
#     por proceso y se queda callado el resto de la corrida.
#
# Para modificar:
#   - Activar/desactivar → .env  LOG_API_TELNET=true  (default false)
# ============================================================================

import logging

from sqlalchemy import text

from config.settings import LOG_API_TELNET

_CONSULTA_CODIGO = "uplink_trafico"

_INSERT = text(
    """
    INSERT INTO OLT_API_LOG_TELNET
        (olt, ip, consulta_codigo, origen, comandos_enviados,
         log_crudo, duracion_ms, exito, error)
    VALUES
        (:olt, :ip, :codigo, 'cron', :comandos,
         :crudo, :dur, :exito, :error)
    """
)

# Se pone en True tras el primer fallo para no llenar el log del proceso con
# el mismo error una vez por OLT.
_aviso_dado = False


def expandir_comandos(pares):
    """Convierte [(interface_cmd, n_puertos), ...] en la lista de comandos que
    realmente se enviaron: el 'interface ...' seguido de un 'display port
    traffic X' por cada puerto. Solo para dejarlo legible en
    comandos_enviados; la fuente de verdad es log_crudo.
    """
    salida = []
    for cmd, n in pares or []:
        salida.append(str(cmd))
        try:
            salida.extend(f"display port traffic {i}" for i in range(int(n or 0)))
        except (TypeError, ValueError):
            pass
    return salida


def registrar_log_telnet(engine, *, olt, ip, log_crudo, duracion_ms, exito,
                         comandos=None, error=None):
    """Best-effort. Ver garantías en la cabecera del archivo."""
    if not LOG_API_TELNET:
        return

    global _aviso_dado

    if isinstance(comandos, (list, tuple)):
        comandos_txt = "\n".join(str(c) for c in comandos)
    else:
        comandos_txt = str(comandos) if comandos else None

    try:
        with engine.begin() as conn:
            conn.execute(
                _INSERT,
                {
                    "olt": olt,
                    "ip": ip,
                    "codigo": _CONSULTA_CODIGO,
                    "comandos": comandos_txt,
                    "crudo": log_crudo or "",
                    "dur": int(duracion_ms or 0),
                    "exito": 1 if exito else 0,
                    "error": (str(error)[:255] if error else None),
                },
            )
    except Exception as exc:  # noqa: BLE001 — best-effort, nunca romper el proceso
        if not _aviso_dado:
            logging.warning(
                "log_api: no se pudo escribir en OLT_API_LOG_TELNET (%s). "
                "¿Se corrió sql/01_tablas_api.sql de api_olt_consultas? "
                "Se omite el resto de logs API en esta corrida.",
                exc,
            )
            _aviso_dado = True
