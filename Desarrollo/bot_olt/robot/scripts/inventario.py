"""Logica de negocio: inventario de OLT."""

from robot.model import consultas

TITULO = "Inventario OLT"


async def obtener() -> tuple:
    """Retorna (titulo, filas) con el inventario completo de OLT."""
    filas = await consultas.consultar_inventario()
    return TITULO, filas


async def obtener_por_region(regiones: list) -> tuple:
    """Retorna (titulo, filas) del inventario filtrado por region."""
    filas = await consultas.consultar_inventario(regiones)
    return f"{TITULO} - {', '.join(regiones)}", filas
