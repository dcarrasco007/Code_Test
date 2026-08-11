"""Logica de negocio: alarmas criticas / LOS de las OLT."""

from robot.model import consultas

TITULO = "Alarmas criticas OLT"


async def obtener() -> tuple:
    """Retorna (titulo, filas) con las alarmas criticas vigentes."""
    filas = await consultas.consultar_alarmas_criticas()
    return TITULO, filas
