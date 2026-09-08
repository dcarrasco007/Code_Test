# ============================================================================
# tests/test_parsers_reales.py
# Proyecto: api_olt_consultas
# Descripción: F9 — corre cada parser de package/parsers/ sobre las capturas
#              REALES que haya en tests/transcripciones/ (ver el LEEME.md de
#              esa carpeta y docs/VALIDACION_F9.md §2).
#
#              HOY pasa con 0 capturas (no hay ninguna todavía). A medida que
#              la validación F9 vaya dejando archivos <codigo>__<OLT>__<fecha>.txt
#              ahí, este test empieza a validar de verdad que el parser
#              correspondiente no revienta y extrae algo.
#
#              Ejecutar: python -m tests.test_parsers_reales (desde la raíz).
# ============================================================================

from pathlib import Path

from package.parsers.registry import PARSERS, parsear

_DIR = Path(__file__).resolve().parent / "transcripciones"


def _capturas():
    if not _DIR.is_dir():
        return []
    return sorted(p for p in _DIR.glob("*.txt"))


def _codigo_de(nombre: str) -> str:
    # "<codigo>__<SERVER>__<fecha>.txt" -> "<codigo>"
    return nombre.split("__", 1)[0]


def _tiene_algo(parsed) -> bool:
    if not isinstance(parsed, dict):
        return bool(parsed)
    for v in parsed.values():
        if isinstance(v, (list, dict, str)) and len(v) > 0:
            return True
        if isinstance(v, (int, float)) and not isinstance(v, bool):
            return True
    return False


def test_capturas_reales_pasan_por_su_parser():
    capturas = _capturas()
    if not capturas:
        print("  (sin capturas en tests/transcripciones/ — F9 todavía no las generó)")
        return

    errores = []
    for archivo in capturas:
        codigo = _codigo_de(archivo.name)
        if codigo not in PARSERS:
            errores.append(f"{archivo.name}: prefijo '{codigo}' no es un parser conocido")
            continue
        texto = archivo.read_text(encoding="utf-8", errors="replace")
        try:
            parsed = parsear(codigo, texto)
        except Exception as exc:  # noqa: BLE001
            errores.append(f"{archivo.name}: el parser '{codigo}' REVENTÓ: {exc!r}")
            continue
        if parsed is None:
            errores.append(f"{archivo.name}: registry no tiene '{codigo}'")
        elif "vacio" not in archivo.name.lower() and not _tiene_algo(parsed):
            errores.append(
                f"{archivo.name}: el parser '{codigo}' devolvió vacío sobre una captura real "
                f"(ajustar el regex en package/parsers/{codigo}.py) -> {parsed!r}"
            )

    if errores:
        raise AssertionError("capturas reales con problemas:\n  - " + "\n  - ".join(errores))
    print(f"  {len(capturas)} captura(s) real(es) OK")


if __name__ == "__main__":
    test_capturas_reales_pasan_por_su_parser()
    print("tests/test_parsers_reales.py: OK (1/1)")
