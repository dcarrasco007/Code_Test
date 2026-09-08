# `tests/transcripciones/` — capturas reales del CLI de las OLT (F9)

Cada archivo es el **texto crudo** que devolvió una OLT real para una consulta, capturado
en la validación F9 (ver `docs/VALIDACION_F9.md` §2). `tests/test_parsers_reales.py` los
descubre solo y corre el parser correspondiente sobre cada uno.

## Nombre de archivo

```
<codigo>__<SERVER>__<YYYYMMDD>.txt
```

- `<codigo>` debe ser uno de los 20 de `package/parsers/registry.py` (`fan`,
  `uplink_trafico`, `alarmas_activas`, …). Es lo que decide qué parser se aplica.
- El resto del nombre es libre (sirve para no pisar capturas).

Ejemplos: `fan__OLT-VITACURA-1__20260915.txt`, `vlan_trafico__OLT-CONCEPCION-1__20260915.txt`

## Cómo capturar

```bash
scripts/f9_paridad.py --url http://<ip>:5001 --key $KEY \
    --server OLT-XXX --codigo fan --guardar-transcripcion
```

(o copiar a mano el `log.crudo` de una respuesta de `POST /ejecutar/consulta/...`).

## Qué valida el test

Para cada `.txt`: que el parser **no reviente** y que devuelva **algo** (no un dict vacío
ni todas las listas vacías). No compara contra un valor esperado byte a byte — eso es
trabajo del humano en el checklist F9 (§3, paridad con la tabla del cron). Si un parser
falla sobre una captura real → ajustar su regex en `package/parsers/<codigo>.py`.

## Datos sensibles

Si una captura trae SN de ONT, nombres de cliente u otra info sensible: **no** la
versiones. Déjala solo local, o anonimiza antes de hacer commit. El test funciona igual
con las que sí se versionen.
