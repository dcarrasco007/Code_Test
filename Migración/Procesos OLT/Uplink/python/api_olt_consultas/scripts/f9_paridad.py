#!/usr/bin/env python
# ============================================================================
# scripts/f9_paridad.py
# Proyecto: api_olt_consultas
# Descripción: Apoyo a la VALIDACIÓN F9 (ver docs/VALIDACION_F9.md §3). Para
#              un (codigo, OLT):
#                1. POST /ejecutar/consulta/<codigo> {server}  -> dato del telnet EN VIVO
#                2. GET  /<endpoint-lectura>/<server>          -> dato que dejó el cron
#              Imprime ambos lado a lado y un resumen de diferencias (cantidades
#              + valores numéricos). Opcionalmente guarda el log crudo como
#              transcripción para los tests de parser reales.
#
# Solo hace peticiones HTTP a la API (no toca la BD ni las OLT directamente).
# Stdlib únicamente (no requiere requests/httpx).
#
# Uso:
#   scripts/f9_paridad.py --url http://10.x.x.x:5001 --key $KEY \
#       --server OLT-VITACURA-1 --codigo fan [--guardar-transcripcion]
# ============================================================================

import argparse
import datetime as dt
import json
import sys
import urllib.error
import urllib.request
from pathlib import Path

_RAIZ = Path(__file__).resolve().parent.parent

# codigo de consulta -> prefijo del endpoint de lectura (F5). El server se
# concatena como último segmento. Ver docs/MANUAL_USUARIO.md §3.
ENDPOINT_LECTURA = {
    "uplink_trafico": "/uplink/trafico",
    "potencia_optica_uplink": "/uplink/potencia-optica",
    "uplink_state": "/uplink/state",
    "alarmas_activas": "/alarmas/activas",
    "alarmas_detalle": "/alarmas/detalle",
    "alarmas_critical_los": "/alarmas/critical-los",
    "alarmas_los_ont": "/alarmas/los-ont",
    "tarjetas": "/equipo/tarjetas",
    "fan": "/equipo/fan",
    "energia_alarma": "/equipo/energia/alarma",
    "energia_estado": "/equipo/energia/estado",
    "temperatura_cpu": "/equipo/temperatura-cpu",
    "version": "/equipo/version",
    "uptime_gpon": "/equipo/uptime",
    "vlan_cantidad": "/vlan/cantidad",
    "vlan_trafico": "/vlan/trafico",
    "vlan_servicios": "/vlan/servicios",
    "pon_trafico": "/pon/trafico",
    "ont_detalle": "/ont/detalle",
    "ont_reporte": "/ont/reporte",
}


def http_json(method, url, key, body=None):
    datos = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(url, data=datos, method=method)
    req.add_header("X-API-Key", key)
    if datos is not None:
        req.add_header("Content-Type", "application/json")
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            return resp.status, json.loads(resp.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        cuerpo = e.read().decode()
        try:
            cuerpo = json.loads(cuerpo)
        except ValueError:
            pass
        return e.code, cuerpo


def _numeros(obj, acc):
    """Recolecta recursivamente todos los valores numéricos (o strings que
    parsean a número) de un dict/list."""
    if isinstance(obj, dict):
        for v in obj.values():
            _numeros(v, acc)
    elif isinstance(obj, (list, tuple)):
        for v in obj:
            _numeros(v, acc)
    elif isinstance(obj, bool):
        pass
    elif isinstance(obj, (int, float)):
        acc.append(float(obj))
    elif isinstance(obj, str):
        try:
            acc.append(float(obj.strip()))
        except (ValueError, AttributeError):
            pass


def comparar_datos(dato_telnet, dato_bd):
    """Resumen comparativo (no decide 'pasa/no pasa' — eso lo hace el humano
    con docs/VALIDACION_F9.md §3). Función pura, testeable."""
    def _cant(d):
        return d.get("cantidad") if isinstance(d, dict) else None

    n_t, n_b = _cant(dato_telnet), _cant(dato_bd)
    num_t, num_b = [], []
    _numeros(dato_telnet, num_t)
    _numeros(dato_bd, num_b)

    obs = []
    if n_t is None or n_b is None:
        obs.append("una de las dos fuentes no devolvió dato (dato: null)")
    elif n_t != n_b:
        obs.append(f"cantidad distinta: telnet={n_t} vs bd={n_b}")
    if num_t and num_b:
        st, sb = sum(num_t), sum(num_b)
        if sb and abs(st - sb) / max(abs(sb), 1e-9) > 0.05:
            obs.append(f"suma de valores numéricos difiere >5%: telnet={st:.3f} vs bd={sb:.3f}")
    return {
        "cantidad_telnet": n_t,
        "cantidad_bd": n_b,
        "numericos_telnet": sorted(num_t),
        "numericos_bd": sorted(num_b),
        "observaciones": obs or ["sin diferencias evidentes (revisar igual a ojo)"],
    }


def _guardar_transcripcion(codigo, server, log_crudo):
    destino = _RAIZ / "tests" / "transcripciones"
    destino.mkdir(parents=True, exist_ok=True)
    nombre = f"{codigo}__{server}__{dt.date.today():%Y%m%d}.txt"
    (destino / nombre).write_text(log_crudo or "", encoding="utf-8")
    return destino / nombre


def main():
    p = argparse.ArgumentParser(description="F9 — paridad dato API (telnet) vs tabla del cron.")
    p.add_argument("--url", required=True, help="URL base de la API, ej. http://10.x.x.x:5001")
    p.add_argument("--key", required=True, help="API key con scopes lectura + ejecutar_consulta")
    p.add_argument("--server", required=True)
    p.add_argument("--codigo", required=True, choices=sorted(ENDPOINT_LECTURA))
    p.add_argument("--guardar-transcripcion", action="store_true",
                   help="guarda log.crudo en tests/transcripciones/ para los tests de parser reales")
    args = p.parse_args()

    base = args.url.rstrip("/")

    print(f"== POST /ejecutar/consulta/{args.codigo}  {{server: {args.server}}} ==")
    st, ejec = http_json("POST", f"{base}/ejecutar/consulta/{args.codigo}", args.key,
                         {"server": args.server})
    print(f"HTTP {st}")
    if st != 200:
        print(json.dumps(ejec, indent=2, ensure_ascii=False))
        sys.exit(1)
    dato_telnet = ejec.get("dato")
    log_crudo = (ejec.get("log") or {}).get("crudo")

    print(f"\n== GET {ENDPOINT_LECTURA[args.codigo]}/{args.server} ==")
    st2, lect = http_json("GET", f"{base}{ENDPOINT_LECTURA[args.codigo]}/{args.server}", args.key)
    print(f"HTTP {st2}")
    dato_bd = lect.get("dato") if isinstance(lect, dict) else None

    print("\n-- dato TELNET (recién parseado) --")
    print(json.dumps(dato_telnet, indent=2, ensure_ascii=False))
    print("\n-- dato BD (lo dejó el cron) --")
    print(json.dumps(dato_bd, indent=2, ensure_ascii=False))

    print("\n-- comparación --")
    print(json.dumps(comparar_datos(dato_telnet, dato_bd), indent=2, ensure_ascii=False))

    if args.guardar_transcripcion:
        ruta = _guardar_transcripcion(args.codigo, args.server, log_crudo)
        print(f"\ntranscripción guardada: {ruta}")


if __name__ == "__main__":
    main()
