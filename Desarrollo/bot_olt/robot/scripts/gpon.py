"""Logica de negocio: ocupacion GPON / puertas PON por OLT."""

from typing import Optional

from robot.model import consultas
from utils.func import a_entero, fecha_ayer

TITULO = "Puertas PON por OLT"


async def obtener(fecha: Optional[str] = None) -> tuple:
    """Retorna (titulo, filas) con puertas totales, utilizadas y disponibles.

    Si no se indica fecha se usa el dia anterior, que es la data que dejan
    cargada los procesos de trafico GPON.
    """
    fecha = fecha or fecha_ayer()
    filas = await consultas.consultar_puertas_pon(fecha)

    for fila in filas:
        # zs_comercial viene como varchar desde MySQL: hay que normalizar
        # ambos valores a entero antes de operar con ellos.
        totales = a_entero(fila.get("puertas_totales"))
        utilizadas = a_entero(fila.get("puertas_utilizadas"))

        fila["puertas_totales"] = totales
        fila["puertas_utilizadas"] = utilizadas
        fila["puertas_disponibles"] = totales - utilizadas
        fila["ocupacion_pct"] = round(utilizadas * 100 / totales, 2) if totales else 0

    return f"{TITULO} ({fecha})", filas
