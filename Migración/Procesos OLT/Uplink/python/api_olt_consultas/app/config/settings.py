# ============================================================================
# app/config/settings.py
# Proyecto: api_olt_consultas
# Descripción: Lectura centralizada del .env. Ningún otro archivo lee variables
#              de entorno por su cuenta (convención GERET: credenciales solo en
#              .env, configuración centralizada en app/config/).
# Para modificar:
#   - Valores ajustables (cupos, timeouts, límites) → buscar [CONFIG]
#   - Credenciales → editar el .env, nunca este archivo
# ============================================================================

import os

from dotenv import load_dotenv

load_dotenv()

VERSION_API = "0.1.0"

# ─── Servidor HTTP ───────────────────────────────────────────────────────────
# [CONFIG] La API se expone SIN nginx/TLS (decisión del proyecto): solo red
#          interna. No usar 0.0.0.0 si el servidor tiene interfaz pública.
API_HOST = os.getenv("API_HOST", "127.0.0.1")
API_PORT = int(os.getenv("API_PORT", "5001"))
API_DOCS_HABILITADO = os.getenv("API_DOCS_HABILITADO", "false").lower() == "true"

# [CONFIG] Orígenes CORS explícitos. Nunca "*" en producción.
CORS_ORIGINS = [o.strip() for o in os.getenv("CORS_ORIGINS", "").split(",") if o.strip()]

# ─── Base de datos ───────────────────────────────────────────────────────────
CONECTION_ADEN = os.getenv("CONECTION_ADEN", "")

# ─── Credenciales telnet ─────────────────────────────────────────────────────
# [CONFIG] Usuario dedicado de la API: cupo propio de 3 sesiones por OLT.
OLT_TELNET_API_DEDICADO = os.getenv("OLT_TELNET_API_DEDICADO", "false").lower() == "true"
OLT_TELNET_API_USER = os.getenv("OLT_TELNET_API_USER", "")
OLT_TELNET_API_PASS = os.getenv("OLT_TELNET_API_PASS", "")

# Perfiles heredados de los procesos PHP (fallback sin usuario dedicado).
PERFILES_TELNET = {
    "DEFAULT": (os.getenv("OLT_TELNET_DEFAULT_USER", ""), os.getenv("OLT_TELNET_DEFAULT_PASS", "")),
    "ALARMAS": (os.getenv("OLT_TELNET_ALARMAS_USER", ""), os.getenv("OLT_TELNET_ALARMAS_PASS", "")),
    "ONT":     (os.getenv("OLT_TELNET_ONT_USER", ""),     os.getenv("OLT_TELNET_ONT_PASS", "")),
}


def credencial_telnet(perfil: str = "DEFAULT"):
    """(usuario, password) telnet a usar para una ejecución en vivo (F6).

    Regla (ver PLAN_API_OLT.md → Control de sesiones telnet):
      - Con usuario dedicado declarado y con clave → SIEMPRE ese
        (`geretapi`), sin importar el perfil de la consulta: la API tiene su
        propio cupo de 3 en cada OLT.
      - Sin usuario dedicado → el perfil heredado del PHP correspondiente
        (DEFAULT / ALARMAS / ONT), y el gobernador baja el cupo a 1.
    Devuelve ('', '') si no hay credencial configurada — el router responde
    503 en vez de intentar un login vacío."""
    if OLT_TELNET_API_DEDICADO and OLT_TELNET_API_USER and OLT_TELNET_API_PASS:
        return OLT_TELNET_API_USER, OLT_TELNET_API_PASS
    return PERFILES_TELNET.get(perfil, PERFILES_TELNET["DEFAULT"])

# ─── Control de sesiones telnet ──────────────────────────────────────────────
# Límite DURO del equipo: 3 sesiones simultáneas por (OLT, usuario). La cuarta
# es cerrada o bloquea al usuario, y solo se libera saliendo con 'quit'.
LIMITE_DURO_SESIONES_OLT = 3

_cupo_env = int(os.getenv("CUPO_API_POR_OLT_USUARIO", "3"))


def cupo_por_olt_usuario():
    """Cupo efectivo de sesiones telnet simultáneas por (OLT, usuario).

    Regla (ver PLAN_API_OLT.md — Control de sesiones telnet):
      - Con usuario dedicado  → hasta 3 (todo el cupo del equipo).
      - Sin usuario dedicado  → 1, para dejar 2 sesiones libres a los crons
        que usan los mismos usuarios (geret2016 / geretproceso / geretont).
    CUPO_API_POR_OLT_USUARIO solo puede BAJAR ese valor, nunca subirlo por
    encima del límite duro del equipo.
    """
    tope = LIMITE_DURO_SESIONES_OLT if OLT_TELNET_API_DEDICADO else 1
    return max(1, min(_cupo_env, tope))


# [CONFIG] Segundos que una petición espera en cola por un cupo libre (luego 503).
ESPERA_MAX_SESION_SEG = int(os.getenv("ESPERA_MAX_SESION_SEG", "60"))
# [CONFIG] Segundos sin reintentar una OLT tras rechazo por límite de sesiones.
BLOQUEO_OLT_SEG = int(os.getenv("BLOQUEO_OLT_SEG", "120"))
# [CONFIG] Sesiones telnet simultáneas en total (todas las OLT).
TELNET_MAX_CONCURRENTES = int(os.getenv("TELNET_MAX_CONCURRENTES", "20"))
# [CONFIG] Timeout por defecto de una sesión telnet; cada consulta puede tener el suyo.
TELNET_TIMEOUT_SEG = int(os.getenv("TELNET_TIMEOUT_SEG", "30"))

# ─── Seguridad de la API ─────────────────────────────────────────────────────
MAX_INTENTOS_AUTH = int(os.getenv("MAX_INTENTOS_AUTH", "5"))
BLOQUEO_AUTH_MINUTOS = int(os.getenv("BLOQUEO_AUTH_MINUTOS", "15"))
RATE_LIMIT_PETICIONES = int(os.getenv("RATE_LIMIT_PETICIONES", "60"))
RATE_LIMIT_VENTANA = int(os.getenv("RATE_LIMIT_VENTANA", "60"))

# ─── Límites de ejecución ────────────────────────────────────────────────────
MAX_OLTS_SYNC = int(os.getenv("MAX_OLTS_SYNC", "1"))
MAX_COMANDOS_REQUEST = int(os.getenv("MAX_COMANDOS_REQUEST", "30"))
MAX_OLTS_REQUEST = int(os.getenv("MAX_OLTS_REQUEST", "5"))

# [CONFIG] Margen (segundos) que se suma al timeout propio de una consulta
#          antes de cortar la ejecución sincrónica y responder 504.
EJECUCION_SYNC_MARGEN_SEG = int(os.getenv("EJECUCION_SYNC_MARGEN_SEG", "15"))
# [CONFIG] Timeout total de un job asíncrono (lote / ejecución larga) → TIMEOUT.
JOB_TIMEOUT_SEG = int(os.getenv("JOB_TIMEOUT_SEG", "900"))

# ─── Retención ───────────────────────────────────────────────────────────────
LOG_RETENCION_DIAS = int(os.getenv("LOG_RETENCION_DIAS", "30"))

# ─── Hardening HTTP (F8) ─────────────────────────────────────────────────────
# [CONFIG] Tamaño máximo del body de una petición (bytes). El único endpoint
#          con body es /ejecutar/*; 30 comandos × 200 chars ≈ 6 KB, así que
#          64 KiB deja margen de sobra y corta cualquier POST abusivo antes de
#          parsearlo. Se responde 413.
MAX_BODY_BYTES = int(os.getenv("MAX_BODY_BYTES", str(64 * 1024)))
# [CONFIG] Tope del texto crudo de telnet que se PERSISTE en OLT_API_LOG_TELNET
#          (la columna es LONGTEXT, pero un telnet que se vuelve loco no debe
#          poder escribir 200 MB por fila). El log devuelto al cliente en la
#          misma respuesta no se recorta; solo lo que va a BD.
MAX_LOG_CRUDO_BYTES = int(os.getenv("MAX_LOG_CRUDO_BYTES", str(1_000_000)))

# ─── Lectura desde BD (F5) ───────────────────────────────────────────────────
# [CONFIG] Tope de filas que devuelve un endpoint de lectura con ?desde&hasta
#          (protege a la API y al cliente de volcar tablas grandes como
#          OLT_VLAN_TRAFICO / OLT_TRAFICOGPON_HORA). El "último dato" (sin
#          rango) no está afectado por este límite.
LIMITE_FILAS_RANGO = int(os.getenv("LIMITE_FILAS_RANGO", "5000"))
