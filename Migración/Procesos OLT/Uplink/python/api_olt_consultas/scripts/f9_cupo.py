#!/usr/bin/env python
# ============================================================================
# scripts/f9_cupo.py
# Proyecto: api_olt_consultas
# Descripción: Apoyo a la VALIDACIÓN F9 (ver docs/VALIDACION_F9.md §4). Dispara
#              N peticiones POST /ejecutar/consulta/<codigo> SIMULTÁNEAS contra
#              la MISMA OLT y reporta los códigos HTTP y tiempos. Sirve para
#              comprobar, mirando `display users` en el equipo, que la API
#              nunca abre una 4ª sesión del mismo usuario, y que las demás
#              esperan / reciben 503 sin 500 y sin dejar sesiones colgadas.
#
# Después consulta GET /admin/sesiones para ver si quedó algo ABIERTA.
# Stdlib únicamente.
#
# Uso:
#   scripts/f9_cupo.py --url http://10.x.x.x:5001 --key $KEY \
#       --server OLT-VITACURA-1 --codigo fan --n 5
# ============================================================================

import argparse
import json
import threading
import time
import urllib.error
import urllib.request


def _post(url, key, body):
    datos = json.dumps(body).encode()
    req = urllib.request.Request(url, data=datos, method="POST")
    req.add_header("X-API-Key", key)
    req.add_header("Content-Type", "application/json")
    t0 = time.monotonic()
    try:
        with urllib.request.urlopen(req, timeout=300) as resp:
            return resp.status, resp.headers.get("Retry-After"), time.monotonic() - t0
    except urllib.error.HTTPError as e:
        return e.code, e.headers.get("Retry-After"), time.monotonic() - t0
    except Exception as e:  # noqa: BLE001
        return f"ERR:{e}", None, time.monotonic() - t0


def _get(url, key):
    req = urllib.request.Request(url, method="GET")
    req.add_header("X-API-Key", key)
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            return resp.status, json.loads(resp.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        return e.code, None


def main():
    p = argparse.ArgumentParser(description="F9 — prueba de cupo de sesiones telnet concurrentes.")
    p.add_argument("--url", required=True)
    p.add_argument("--key", required=True, help="API key con ejecutar_consulta + admin")
    p.add_argument("--server", required=True)
    p.add_argument("--codigo", default="fan")
    p.add_argument("--n", type=int, default=5, help="peticiones simultáneas (default 5)")
    args = p.parse_args()

    base = args.url.rstrip("/")
    url = f"{base}/ejecutar/consulta/{args.codigo}"
    body = {"server": args.server}

    resultados = [None] * args.n

    def _worker(i):
        resultados[i] = _post(url, args.key, body)

    print(f"Disparando {args.n} POST simultáneos a {url}  {body}")
    print(">>> AHORA: en la OLT, correr 'display users' y contar sesiones del usuario de la API <<<")
    hilos = [threading.Thread(target=_worker, args=(i,)) for i in range(args.n)]
    t0 = time.monotonic()
    for h in hilos:
        h.start()
    for h in hilos:
        h.join()
    total = time.monotonic() - t0

    print(f"\nTerminadas en {total:.1f}s:")
    for i, (status, retry, dur) in enumerate(resultados):
        extra = f" Retry-After={retry}" if retry else ""
        print(f"  #{i}: HTTP {status}  ({dur:.1f}s){extra}")

    codigos = [str(r[0]) for r in resultados]
    print("\nResumen:", {c: codigos.count(c) for c in sorted(set(codigos))})
    if any(c == "500" for c in codigos):
        print("  ❌ hubo 500 — revisar logs/api.log")
    if not any(c == "200" for c in codigos):
        print("  ⚠️  ningún 200 — ¿circuit-breaker ya abierto? ¿credenciales telnet?")

    print("\nEsperando 30s y consultando /admin/sesiones ...")
    time.sleep(30)
    st, data = _get(f"{base}/admin/sesiones", args.key)
    if st == 200 and data:
        abiertas = [s for s in data.get("sesiones", []) if s.get("olt") == args.server]
        print(f"  cupo efectivo: {data.get('cupo', {}).get('por_olt_usuario')}")
        print(f"  sesiones ABIERTA para {args.server}: {len(abiertas)}")
        if abiertas:
            print("  ⚠️  quedaron sesiones abiertas — revisar motivo_cierre / logs")
            print(json.dumps(abiertas, indent=2, ensure_ascii=False))
        else:
            print("  ✅ sin sesiones colgadas")
    else:
        print(f"  no se pudo leer /admin/sesiones (HTTP {st}) — ¿la key tiene scope admin?")


if __name__ == "__main__":
    main()
