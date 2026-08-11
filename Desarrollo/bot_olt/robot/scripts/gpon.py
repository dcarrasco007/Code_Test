"""Logica de negocio: ocupacion GPON / puertas PON por OLT."""

from typing import Optional

from robot.model import consultas
from utils.func import fecha_ayer

TITULO = "Puertas PON por OLT"


async def obtener(fecha: Optional[str] = None) -> tuple:
    """Retorna (titulo, filas) con puertas totales, utilizadas y disponibles.

    Si no se indica fecha se usa el dia anterior, que es la data que dejan
    cargada los procesos de trafico GPON.
    """
    fecha = fecha or fecha_ayer()
    filas = await consultas.consultar_puertas_pon(fecha)

    for fila in filas:
        totales = fila.get("puertas_totales") or 0
        utilizadas = fila.get("puertas_utilizadas") or 0
        fila["puertas_disponibles"] = totales - utilizadas
        fila["ocupacion_pct"] = round(utilizadas * 100 / totales, 2) if totales else 0

    return f"{TITULO} ({fecha})", filas
