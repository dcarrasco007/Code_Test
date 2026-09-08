# ============================================================================
# utils/token.py
# Proyecto: api_olt_consultas
# Descripción: Autenticación por API key (header X-API-Key) + control de
#              scopes + anti fuerza bruta. Convención GERET (README_PYTHON_API):
#              los routers usan `Depends(verify_api_key)` o
#              `Depends(requerir_scopes("scope1", "scope2"))`.
#
# Flujo de verify_api_key (ver PLAN_API_OLT.md → Seguridad):
#   1. IP bloqueada por intentos previos (OLT_API_INTENTOS_AUTH) → 401 genérico.
#   2. Falta el header X-API-Key                                 → 401 genérico.
#   3. Prefijo no encontrado en OLT_API_CLIENTES, o hash no verifica,
#      o cliente inactivo, o IP no está en ips_permitidas          → 401 genérico
#      (nunca se distingue el motivo en la respuesta al cliente).
#   4. Todo OK → resetea el contador de fallos, audita AUTH_OK, devuelve Cliente.
#
# Todo fallo: se registra el intento (OLT_API_INTENTOS_AUTH), se audita
# AUTH_FAIL (OLT_API_AUDITORIA) y se aplica un retardo fijo antes de responder
# (mitiga fuerza bruta por temporización / por volumen de intentos por segundo).
#
# Para modificar:
#   - Umbral/ventana de bloqueo → app/config/settings.py (MAX_INTENTOS_AUTH,
#     BLOQUEO_AUTH_MINUTOS), no hardcodeado aquí.
#   - Formato de scopes → OLT_API_CLIENTES.scopes es CSV plano (ver _parse_scopes).
# ============================================================================

from __future__ import annotations

import asyncio
import ipaddress
from dataclasses import dataclass, field
from typing import Optional

from fastapi import Depends, HTTPException, Request
from fastapi.concurrency import run_in_threadpool
from fastapi.security import APIKeyHeader
from loguru import logger
from sqlalchemy import text

from app.config import settings
from app.config.db import get_engine
from utils import auditoria, seguridad

_HEADER_NOMBRE = "X-API-Key"
_api_key_header = APIKeyHeader(name=_HEADER_NOMBRE, auto_error=False)

# Retardo fijo aplicado en todo fallo de autenticación (segundos). Deliberado:
# ralentiza intentos automatizados sin afectar perceptiblemente a un cliente
# legítimo que se equivocó una vez.
_RETARDO_FALLO_SEG = 0.3

# Hash "señuelo" con formato válido, usado cuando no se encuentra el cliente,
# para que utils.seguridad.verificar() tarde un tiempo comparable al de un
# hash real y no delate por temporización si el prefijo existe o no.
_HASH_SENUELO = seguridad.hashear("clave-que-nunca-coincide-con-nada")

_MENSAJE_401 = "Credenciales inválidas o cuenta no autorizada."


@dataclass
class Cliente:
    """Cliente autenticado (fila de OLT_API_CLIENTES ya validada)."""

    id: int
    nombre: str
    scopes: frozenset = field(default_factory=frozenset)
    rate_limit_peticiones: int = 60
    rate_limit_ventana_seg: int = 60
    max_ejecuciones_hora: int = 60

    def tiene_scope(self, scope: str) -> bool:
        return scope in self.scopes


def _parse_scopes(csv: Optional[str]) -> frozenset:
    if not csv:
        return frozenset()
    return frozenset(s.strip() for s in csv.split(",") if s.strip())


def _ip_cliente(request: Request) -> str:
    return request.client.host if request.client else "desconocida"


def _ip_permitida(ip: str, ips_permitidas_csv: Optional[str]) -> bool:
    """Sin ips_permitidas configuradas → NUNCA se permite (decisión del
    proyecto: la API va sin TLS, por lo que la whitelist de IP es obligatoria,
    ver PLAN_API_OLT.md → Seguridad → Transporte)."""
    if not ips_permitidas_csv or not ips_permitidas_csv.strip():
        return False
    try:
        ip_obj = ipaddress.ip_address(ip)
    except ValueError:
        return False
    for entrada in ips_permitidas_csv.split(","):
        entrada = entrada.strip()
        if not entrada:
            continue
        try:
            if "/" in entrada:
                if ip_obj in ipaddress.ip_network(entrada, strict=False):
                    return True
            elif ip_obj == ipaddress.ip_address(entrada):
                return True
        except ValueError:
            # Entrada mal escrita en BD: se ignora esa entrada, no se cae la petición.
            logger.warning("utils.token: entrada inválida en ips_permitidas: '{}'", entrada)
            continue
    return False


# ─── Acceso a datos (aislado en funciones cortas para poder simularlas en tests) ──

def _buscar_cliente_por_prefijo(engine, prefijo: str) -> Optional[dict]:
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                """
                SELECT id, nombre, api_key_hash, scopes, ips_permitidas,
                       rate_limit_peticiones, rate_limit_ventana_seg,
                       max_ejecuciones_hora, activo
                FROM OLT_API_CLIENTES
                WHERE prefijo_key = :prefijo
                """
            ),
            {"prefijo": prefijo},
        ).mappings().first()
    return dict(fila) if fila else None


def _estado_bloqueo_ip(engine, ip: str) -> Optional[dict]:
    with engine.connect() as conn:
        fila = conn.execute(
            text(
                """
                SELECT intentos_fallidos, bloqueado_hasta,
                       (bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()) AS bloqueada
                FROM OLT_API_INTENTOS_AUTH WHERE ip = :ip
                """
            ),
            {"ip": ip},
        ).mappings().first()
    return dict(fila) if fila else None


def _registrar_intento_fallido(engine, ip: str, prefijo: Optional[str]) -> None:
    """UPSERT del contador de fallos por IP. Si supera MAX_INTENTOS_AUTH,
    fija bloqueado_hasta = ahora + BLOQUEO_AUTH_MINUTOS.

    Si el bloqueo anterior ya venció (bloqueado_hasta <= NOW()), este fallo
    empieza un contador nuevo desde 1 en vez de seguir sumando sobre el
    historial viejo — si no, una IP castigada hace mucho nunca "sanaría" del
    todo (bastaría un solo fallo más, meses después, para re-bloquearla).
    La misma expresión se repite en ambas columnas a propósito: MySQL evalúa
    cada asignación de UPDATE contra los valores ANTERIORES de la fila, no
    contra lo que otra columna del mismo UPDATE recién calculó.
    """
    intentos_nuevos_expr = (
        "IF(bloqueado_hasta IS NOT NULL AND bloqueado_hasta <= NOW(), 1, intentos_fallidos + 1)"
    )
    with engine.begin() as conn:
        conn.execute(
            text(
                f"""
                INSERT INTO OLT_API_INTENTOS_AUTH (ip, prefijo_key, intentos_fallidos, ultima_fecha)
                VALUES (:ip, :prefijo, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    prefijo_key = :prefijo,
                    intentos_fallidos = {intentos_nuevos_expr},
                    bloqueado_hasta = IF(
                        {intentos_nuevos_expr} >= :umbral,
                        DATE_ADD(NOW(), INTERVAL :minutos MINUTE),
                        NULL
                    ),
                    ultima_fecha = NOW()
                """
            ),
            {
                "ip": ip,
                "prefijo": prefijo,
                "umbral": settings.MAX_INTENTOS_AUTH,
                "minutos": settings.BLOQUEO_AUTH_MINUTOS,
            },
        )


def _limpiar_bloqueo(engine, ip: str) -> None:
    with engine.begin() as conn:
        conn.execute(
            text(
                "UPDATE OLT_API_INTENTOS_AUTH SET intentos_fallidos = 0, bloqueado_hasta = NULL "
                "WHERE ip = :ip"
            ),
            {"ip": ip},
        )


# ─── Dependencia principal ───────────────────────────────────────────────────

async def verify_api_key(
    request: Request,
    api_key: Optional[str] = Depends(_api_key_header),
) -> Cliente:
    engine = get_engine()
    ip = _ip_cliente(request)

    async def _fallar(prefijo: Optional[str], motivo: str) -> None:
        await run_in_threadpool(_registrar_intento_fallido, engine, ip, prefijo)
        auditoria.registrar(
            engine, accion="AUTH_FAIL", ip_origen=ip,
            endpoint=str(request.url.path), resultado="RECHAZADO", parametro=motivo,
        )
        await asyncio.sleep(_RETARDO_FALLO_SEG)
        raise HTTPException(status_code=401, detail=_MENSAJE_401)

    bloqueo = await run_in_threadpool(_estado_bloqueo_ip, engine, ip)
    if bloqueo and bloqueo.get("bloqueada"):
        # Bloqueo vigente (bloqueado_hasta > NOW(), calculado por MySQL, no
        # por este proceso — evita problemas de reloj entre servidores).
        # No se toca el contador de nuevo mientras siga vigente.
        auditoria.registrar(
            engine, accion="AUTH_FAIL", ip_origen=ip,
            endpoint=str(request.url.path), resultado="RECHAZADO",
            parametro="ip bloqueada por intentos previos",
        )
        await asyncio.sleep(_RETARDO_FALLO_SEG)
        raise HTTPException(
            status_code=401,
            detail=_MENSAJE_401,
            headers={"Retry-After": str(settings.BLOQUEO_AUTH_MINUTOS * 60)},
        )

    if not api_key:
        await _fallar(None, "sin X-API-Key")

    prefijo = seguridad.prefijo(api_key)
    fila = await run_in_threadpool(_buscar_cliente_por_prefijo, engine, prefijo)

    # Timing: se llama a verificar() SIEMPRE (con el hash real o el señuelo),
    # para que la respuesta tarde lo mismo exista o no el prefijo.
    hash_a_comparar = fila["api_key_hash"] if fila else _HASH_SENUELO
    coincide = await run_in_threadpool(seguridad.verificar, api_key, hash_a_comparar)

    if not fila or not coincide:
        await _fallar(prefijo, "prefijo inexistente" if not fila else "hash no coincide")

    if not fila.get("activo"):
        await _fallar(prefijo, "cliente inactivo")

    if not _ip_permitida(ip, fila.get("ips_permitidas")):
        await _fallar(prefijo, "ip no autorizada para este cliente")

    await run_in_threadpool(_limpiar_bloqueo, engine, ip)
    cliente = Cliente(
        id=fila["id"],
        nombre=fila["nombre"],
        scopes=_parse_scopes(fila.get("scopes")),
        rate_limit_peticiones=fila.get("rate_limit_peticiones") or 60,
        rate_limit_ventana_seg=fila.get("rate_limit_ventana_seg") or 60,
        max_ejecuciones_hora=fila.get("max_ejecuciones_hora") or 60,
    )
    request.state.cliente = cliente
    auditoria.registrar(
        engine, accion="AUTH_OK", ip_origen=ip, endpoint=str(request.url.path),
        cliente_id=cliente.id, resultado="OK",
    )
    return cliente


def requerir_scopes(*scopes_requeridos: str):
    """Fábrica de dependencia: exige que el cliente autenticado tenga TODOS
    los scopes indicados. Uso:
        @router.post(..., dependencies=[Depends(requerir_scopes("admin"))])
    o bien recibiendo el cliente:
        def endpoint(cliente: Cliente = Depends(requerir_scopes("lectura"))): ...
    """

    async def _dependencia(cliente: Cliente = Depends(verify_api_key)) -> Cliente:
        faltantes = [s for s in scopes_requeridos if not cliente.tiene_scope(s)]
        if faltantes:
            raise HTTPException(
                status_code=403,
                detail=f"El cliente no tiene el/los scope(s) requerido(s): {', '.join(faltantes)}",
            )
        return cliente

    return _dependencia
