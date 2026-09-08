#!/usr/bin/env python
# ============================================================================
# scripts/crear_cliente.py
# Proyecto: api_olt_consultas
# Descripción: Genera una API key nueva y el INSERT para darla de alta en
#              OLT_API_CLIENTES. NO se conecta a la base de datos ni ejecuta
#              nada: el responsable del proyecto revisa el INSERT impreso y
#              lo ejecuta manualmente (README_LIMITES.md — ninguna herramienta
#              de este proyecto ejecuta INSERT/DDL contra Aden por su cuenta).
#
# Uso:
#   .venv/Scripts/python scripts/crear_cliente.py --nombre "Portal OLT" ^
#       --scopes lectura,ejecutar_consulta --ips 10.10.1.5,10.10.1.6
#
# IMPORTANTE: la API key se imprime UNA SOLA VEZ. Entregarla de forma segura
# al consumidor (no por email/chat en texto plano si se puede evitar); no
# queda registrada en ningún lado en texto plano, ni en este script ni en BD.
# ============================================================================

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from utils.seguridad import generar_api_key, hashear, prefijo  # noqa: E402


def main():
    parser = argparse.ArgumentParser(
        description="Genera una API key y el INSERT para OLT_API_CLIENTES (no ejecuta nada)."
    )
    parser.add_argument("--nombre", required=True, help="Nombre del sistema consumidor (ej. 'Portal OLT')")
    parser.add_argument(
        "--scopes", required=True,
        help="CSV: lectura,ejecutar_consulta,ejecutar_comandos,config_aprobada,admin,log_cron",
    )
    parser.add_argument(
        "--ips", default="",
        help="CSV de IPs/CIDR permitidas. En la práctica es OBLIGATORIO: sin IP, la "
             "API rechaza al cliente con 401 (ver PLAN_API_OLT.md - Seguridad - Transporte).",
    )
    parser.add_argument("--rate-limit-peticiones", type=int, default=60)
    parser.add_argument("--rate-limit-ventana", type=int, default=60)
    parser.add_argument("--max-ejecuciones-hora", type=int, default=60)
    args = parser.parse_args()

    if not args.ips.strip():
        print(
            "[ADVERTENCIA] No se indicó --ips. Sin lista de IPs permitidas, la API\n"
            "  rechazará a este cliente con 401 (decisión de diseño: se expone sin TLS).\n"
            "  Confirmar que es intencional antes de ejecutar el INSERT.\n",
            file=sys.stderr,
        )

    api_key = generar_api_key()
    pfx = prefijo(api_key)
    hash_almacenado = hashear(api_key)
    ips_sql = "NULL" if not args.ips.strip() else _sql_str(args.ips.strip())

    print("=" * 78)
    print(" API KEY GENERADA — se muestra UNA SOLA VEZ, guardarla ahora")
    print("=" * 78)
    print(f"Cliente : {args.nombre}")
    print(f"API key : {api_key}")
    print(f"Prefijo : {pfx}   (este SÍ queda visible en la tabla; no es secreto)")
    print("=" * 78)
    print()
    print("-- Revisar (ojo con comillas si el nombre trae apóstrofes) y ejecutar")
    print("-- MANUALMENTE contra la base de datos Aden:")
    print("INSERT INTO OLT_API_CLIENTES")
    print("    (nombre, prefijo_key, api_key_hash, scopes, ips_permitidas,")
    print("     rate_limit_peticiones, rate_limit_ventana_seg, max_ejecuciones_hora, activo)")
    print("VALUES")
    print(
        f"    ({_sql_str(args.nombre)}, {_sql_str(pfx)}, {_sql_str(hash_almacenado)}, "
        f"{_sql_str(args.scopes)}, {ips_sql},"
    )
    print(f"     {args.rate_limit_peticiones}, {args.rate_limit_ventana}, {args.max_ejecuciones_hora}, 1);")


def _sql_str(valor: str) -> str:
    """Escapa comillas simples para un literal SQL. Solo para impresión/revisión
    humana — no reemplaza el uso de binds en las queries reales de la API."""
    return "'" + valor.replace("'", "''") + "'"


if __name__ == "__main__":
    main()
