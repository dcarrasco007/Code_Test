# ============================================================================
# utils/ratelimit.py
# Proyecto: api_olt_consultas
# Descripción: Límite de peticiones por cliente (ventana deslizante) y cuota
#              de ejecuciones telnet en vivo por hora. Se usa como dependencia
#              FastAPI DESPUÉS de verify_api_key (necesita el Cliente ya
#              autenticado y sus límites propios: rate_limit_peticiones,
#              rate_limit_ventana_seg, max_ejecuciones_hora).
#
# Uso tipico en un router:
#   from utils.token import verify_api_key
#   from utils.ratelimit import verificar_rate_limit
#
#   @router.get(...)
#   def endpoint(cliente: Cliente = Depends(verify_api_key)):
#       verificar_rate_limit(cliente)
#       ...
#
# LÍMITE CONOCIDO (documentado también en el manual de desarrollador, F8):
# el contador vive en memoria del proceso. Es correcto mientras la API corra
# como UN SOLO proceso uvicorn (sin --workers), decisión ya tomada para el
# gobernador de sesiones telnet (ver PLAN_API_OLT.md). Si en el futuro se
# escala a varios procesos/workers, este límite deja de ser efectivo entre
# procesos y habría que moverlo a BD o Redis.
# ============================================================================

from __future__ import annotations

import threading
import time
from collections import defaultdict, deque
from typing import Deque, Dict

from fastapi import HTTPException

_lock = threading.Lock()

# cliente_id -> timestamps (segundos, time.monotonic()) de peticiones recientes.
_peticiones: Dict[int, Deque[float]] = defaultdict(deque)

# cliente_id -> timestamps de ejecuciones telnet en vivo (cuota por hora).
_ejecuciones: Dict[int, Deque[float]] = defaultdict(deque)

_SEGUNDOS_HORA = 3600


def _purgar(cola: Deque[float], ventana_seg: float, ahora: float) -> None:
    while cola and (ahora - cola[0]) > ventana_seg:
        cola.popleft()


def verificar_rate_limit(cliente) -> None:
    """Ventana deslizante genérica: máximo `cliente.rate_limit_peticiones`
    peticiones cada `cliente.rate_limit_ventana_seg` segundos. Lanza 429 con
    `Retry-After` si se excede; si no, registra la petición actual."""
    ahora = time.monotonic()
    ventana = cliente.rate_limit_ventana_seg
    tope = cliente.rate_limit_peticiones
    with _lock:
        cola = _peticiones[cliente.id]
        _purgar(cola, ventana, ahora)
        if len(cola) >= tope:
            retry_after = max(1, int(ventana - (ahora - cola[0])))
            raise HTTPException(
                status_code=429,
                detail=f"Límite de {tope} peticiones cada {ventana}s excedido para este cliente.",
                headers={"Retry-After": str(retry_after)},
            )
        cola.append(ahora)


def verificar_cuota_ejecucion(cliente) -> None:
    """Cuota de ejecuciones telnet EN VIVO por hora (protege a las OLT de
    saturación, independiente del rate-limit general de peticiones). La usan
    los endpoints de /ejecutar/* (F6) antes de abrir cualquier sesión telnet.
    NO consume la cuota por sí sola: llamar a `registrar_ejecucion` una vez
    que la ejecución efectivamente arranca."""
    ahora = time.monotonic()
    tope = cliente.max_ejecuciones_hora
    with _lock:
        cola = _ejecuciones[cliente.id]
        _purgar(cola, _SEGUNDOS_HORA, ahora)
        if len(cola) >= tope:
            retry_after = max(1, int(_SEGUNDOS_HORA - (ahora - cola[0])))
            raise HTTPException(
                status_code=429,
                detail=f"Cuota de {tope} ejecuciones/hora excedida para este cliente.",
                headers={"Retry-After": str(retry_after)},
            )


def registrar_ejecucion(cliente) -> None:
    """Consume una unidad de la cuota horaria de ejecuciones. Llamar solo
    cuando la ejecución telnet efectivamente se inicia (no en la sola
    validación), para no gastar cuota en peticiones rechazadas antes."""
    with _lock:
        _ejecuciones[cliente.id].append(time.monotonic())


def _reset_total_para_tests() -> None:
    """Solo para tests: limpia todo el estado en memoria entre casos."""
    with _lock:
        _peticiones.clear()
        _ejecuciones.clear()
