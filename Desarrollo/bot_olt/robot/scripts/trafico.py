"""Logica de negocio: trafico por puerto PON de una OLT."""

from typing import Optional

from robot.model import consultas
from utils.func import fecha_ayer

TITULO = "Trafico PON"


async def obtener(ip: str, fecha: Optional[str] = None) -> tuple:
    """Retorna (titulo, filas) con el trafico por puerto de la OLT indicada."""
    fecha = fecha or fecha_ayer()
    filas = await consultas.consultar_trafico_pon(ip, fecha)
    return f"{TITULO} {ip} ({fecha})", filas
