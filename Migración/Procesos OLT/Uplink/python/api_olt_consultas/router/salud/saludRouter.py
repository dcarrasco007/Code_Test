# ============================================================================
# router/salud/saludRouter.py
# Proyecto: api_olt_consultas
# Descripción: Endpoint de salud. NO requiere API key (es el chequeo que usan
#              systemd/monitoreo para saber si el proceso está vivo).
#              Expone además el cupo efectivo de sesiones telnet, para poder
#              verificar en producción que la API respeta el límite de 3
#              sesiones por (OLT, usuario) — ver PLAN_API_OLT.md.
# Para modificar: agregar campos de diagnóstico → buscar [SALUD]
# ============================================================================

from fastapi import APIRouter

from app.config import settings

salud_router = APIRouter()


@salud_router.get("/health", summary="Estado de la API")
def health():
    """Estado del proceso y configuración efectiva de sesiones telnet.

    No consulta la base de datos ni las OLT: responde aunque la BD esté caída,
    para poder distinguir 'proceso muerto' de 'dependencia caída'.
    """
    # [SALUD] Campos pensados para diagnóstico rápido en producción.
    return {
        "ok": True,
        "servicio": "api_olt_consultas",
        "version": settings.VERSION_API,
        "fase": "F9 — validación en producción en curso (código F1-F8 completo); falta F10 despliegue",
        "telnet": {
            "usuario_dedicado": settings.OLT_TELNET_API_DEDICADO,
            "cupo_por_olt_usuario": settings.cupo_por_olt_usuario(),
            "limite_duro_equipo": settings.LIMITE_DURO_SESIONES_OLT,
            "max_concurrentes_global": settings.TELNET_MAX_CONCURRENTES,
        },
    }
