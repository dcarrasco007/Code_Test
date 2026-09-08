# ============================================================================
# package/parsers/temperatura_cpu.py
# Consulta: temperatura_cpu — comandos: 'display temperature 0/{slot}' x2 +
#           'display cpu 0/{slot}' x2
# CONFIANZA: MEDIA — parser genérico de pares "Etiqueta : valor" (ambos
#            comandos Huawei devuelven ese formato); se buscan las etiquetas
#            más comunes para temperatura y uso de CPU. Confirmar en F9.
# ============================================================================

from package.parsers._base import parsear_pares_clave_valor


def parsear(texto_crudo: str) -> dict:
    pares = parsear_pares_clave_valor(texto_crudo)
    temperatura = None
    cpu = None
    for etiqueta, valor in pares.items():
        etiqueta_norm = etiqueta.lower()
        if temperatura is None and "temperature" in etiqueta_norm:
            temperatura = valor
        if cpu is None and ("cpu" in etiqueta_norm and ("usage" in etiqueta_norm or "%" in valor)):
            cpu = valor
    return {"temperatura": temperatura, "uso_cpu": cpu, "pares": pares}
