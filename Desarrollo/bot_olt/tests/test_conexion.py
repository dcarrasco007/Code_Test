"""Pruebas basicas de entorno: verifica conexion y consultas antes de levantar el bot.

Uso:
    python -m tests.test_conexion

No ejecuta escrituras: solo SELECT de verificacion.
"""

import asyncio

from dotenv import load_dotenv
from sqlalchemy import text

from utils.logger import setup_logging


async def main():
    load_dotenv()
    setup_logging("log/test.log")

    from app.config import engine_aden
    from robot.model import consultas

    print("1. Conexion al esquema Aden...")
    with engine_aden.connect() as conn:
        conn.execute(text("SELECT 1"))
    print("   OK")

    print("2. Inventario de OLT...")
    filas = await consultas.consultar_inventario()
    print(f"   {len(filas)} OLT encontradas")
    if filas:
        print(f"   Ejemplo: {filas[0]}")

    print("3. Regiones...")
    regiones = await consultas.consultar_regiones()
    print(f"   {[r['region'] for r in regiones]}")

    print("4. Exportacion a los tres formatos...")
    from utils.func import exportar

    for formato in ("excel", "csv", "txt"):
        contenido, nombre = exportar(filas, formato, "Inventario OLT")
        print(f"   {formato}: {nombre} ({len(contenido)} bytes)")

    print("5. Tablas propias del bot...")
    for tabla in ("OLT_BOT_USUARIOS", "OLT_BOT_AUDITORIA"):
        try:
            with engine_aden.connect() as conn:
                total = conn.execute(text(f"SELECT COUNT(*) FROM {tabla}")).scalar()
            print(f"   {tabla}: OK ({total} filas)")
        except Exception as e:
            print(f"   {tabla}: FALTA — ejecuta BD/{tabla}.sql  ({type(e).__name__})")

    print("\nEntorno verificado.")


if __name__ == "__main__":
    asyncio.run(main())
