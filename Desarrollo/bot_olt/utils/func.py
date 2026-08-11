"""Funciones auxiliares reutilizables: log de errores, formateo y exportacion.

Las funciones pesadas (pandas / openpyxl) se definen aqui como **sincronicas**
y se invocan desde los scripts con `asyncio.to_thread`, para no bloquear el
event loop de Telegram.
"""

from datetime import datetime, timedelta
from io import BytesIO
from typing import Optional

import pandas as pd
from loguru import logger


def marker_errors(msg: str) -> None:
    """Loguea un error incluyendo el traceback completo."""
    logger.opt(exception=True).error(msg)


def safe_ts(fecha=None) -> str:
    """Timestamp seguro para usar dentro de nombres de archivo."""
    fecha = fecha or datetime.now()
    return fecha.strftime("%Y%m%d_%H%M%S")


def fecha_ayer() -> str:
    """Fecha del dia anterior en formato YYYY-MM-DD.

    Los procesos de trafico GPON cargan la data del dia anterior, por eso las
    consultas de ocupacion usan esta fecha por defecto.
    """
    return (datetime.now() - timedelta(days=1)).strftime("%Y-%m-%d")


def a_dataframe(filas: list) -> pd.DataFrame:
    """Convierte la lista de dicts que entrega la capa de datos en DataFrame."""
    return pd.DataFrame(filas)


# =============================================================
# Exportacion — un formato por funcion, registradas en EXPORTADORES
# =============================================================
#
# Para agregar un formato nuevo basta escribir la funcion `_a_<formato>_bytes`
# y sumarla al diccionario EXPORTADORES: el resto del bot no cambia.


def _a_excel_bytes(df: pd.DataFrame, titulo: str) -> bytes:
    """Planilla Excel (.xlsx) con los anchos de columna ajustados."""
    buffer = BytesIO()
    hoja = (titulo[:31] or "Datos")  # Excel limita el nombre de la hoja a 31
    with pd.ExcelWriter(buffer, engine="openpyxl") as writer:
        df.to_excel(writer, index=False, sheet_name=hoja)
        worksheet = writer.sheets[hoja]
        for i, columna in enumerate(df.columns, start=1):
            ancho = max(len(str(columna)), *(df[columna].astype(str).map(len).tolist() or [0]))
            worksheet.column_dimensions[worksheet.cell(1, i).column_letter].width = min(ancho + 4, 50)
    return buffer.getvalue()


def _a_csv_bytes(df: pd.DataFrame, titulo: str) -> bytes:
    """CSV separado por punto y coma y con BOM, para que Excel lo abra bien."""
    return df.to_csv(index=False, sep=";").encode("utf-8-sig")


def _a_txt_bytes(df: pd.DataFrame, titulo: str) -> bytes:
    """Texto plano con las columnas alineadas."""
    encabezado = f"{titulo}\nGenerado: {datetime.now():%Y-%m-%d %H:%M:%S}\n\n"
    return (encabezado + df.to_string(index=False)).encode("utf-8")


EXPORTADORES: dict = {
    "excel": (_a_excel_bytes, "xlsx"),
    "csv": (_a_csv_bytes, "csv"),
    "txt": (_a_txt_bytes, "txt"),
}


def exportar(filas: list, formato: str, titulo: str) -> tuple:
    """Genera el archivo del formato pedido.

    Retorna (bytes, nombre_archivo). Se entregan **bytes puros**, no BytesIO,
    para poder enviar el mismo archivo a varios usuarios en paralelo sin
    race conditions en el `seek`.
    """
    if formato not in EXPORTADORES:
        raise ValueError(f"Formato no soportado: {formato}")

    generador, extension = EXPORTADORES[formato]
    df = a_dataframe(filas)
    contenido = generador(df, titulo)
    nombre = f"{titulo.lower().replace(' ', '_')}_{safe_ts()}.{extension}"
    return contenido, nombre


# =============================================================
# Presentacion en el chat
# =============================================================


def resumen_texto(filas: list, titulo: str, limite: int = 10) -> str:
    """Vista previa en texto para mostrar en el chat antes de descargar."""
    if not filas:
        return f"*{titulo}*\n\nLa consulta no devolvio resultados."

    df = a_dataframe(filas)
    total = len(df)
    vista = df.head(limite)

    lineas = [f"*{titulo}*", f"Registros: {total}", "", "```", vista.to_string(index=False), "```"]
    if total > limite:
        lineas.append(f"_Mostrando {limite} de {total}. Descarga el archivo para verlos todos._")
    return "\n".join(lineas)
