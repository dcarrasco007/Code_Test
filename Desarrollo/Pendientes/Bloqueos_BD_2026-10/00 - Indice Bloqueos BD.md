---
tipo: indice
proyecto: GERET
tema: bloqueos-bd
creado: 2026-10-27
tags: [geret, mysql, pendientes, bloqueos]
---

# Bloqueos BD — Pendientes (oct 2026)

Volver a [[00 - Pendientes GERET]]

Origen: revisión de esperas por *metadata lock* y tablas lentas en el MySQL compartido.
Detalle de qué tablas pasar de `TRUNCATE` a `DELETE`: `Inventario_TRUNCATE_2026-10-26.pdf` (aún no está en esta carpeta — **adjuntar**).

## Tiempos de espera observados

| Tabla | Origen | Máximo | Equipo |
|---|---|---|---|
| `planta_externa.crm_historico` | adminAder@192.168.66.140 | 1.108 s (en *Waiting for table metadata lock*) | otro |
| `Aden.PRIORIZACION_TMP_PSG_PCA` | adminAder@192.168.110.163 | 486 s | otro |
| `Aden.OLT_ALARMAS_CRITICAL` | bhaul-dev@192.168.66.121 | 420 s | ⭐ **nuestro** |
| `calidad_proyecto.jira_new` | adminAder@192.168.110.49 | 399 s | otro |
| `CCO.GPS_ESTADO_RIOS` | adminAder@192.168.110.163 | 342 s | otro |

## ⭐ Nuestro pendiente

| # | Acción | Nota | Estado |
|---|---|---|---|
| 3 | Proceso de alarmas OLT: los ~150 hijos solo hacen telnet y dejan un .json; el padre espera 3 min y revisa cada 1 min (máx. 8 min), luego hace un solo DELETE + INSERT en una transacción con lo que llegó (sin TRUNCATE, monitoreo comentado, `timeout 450` por hijo) | [[03 - bhaul-dev OLT_ALARMAS_CRITICAL]] · plan: [[03.1 - Plan implementacion etapa 1 alarmas OLT]] | 🟡 código listo, falta probar en el servidor |

## Pendientes de otros equipos (solo seguimiento)

| # | Para | Acción | Nota | Estado |
|---|---|---|---|---|
| 1 | Dueño del job de 192.168.110.163 | `GPS_ESTADO_RIOS`: TRUNCATE → DELETE+INSERT en transacción + `lock_wait_timeout = 10` | [[01 - GPS_ESTADO_RIOS (110.163)]] | 🔲 pendiente |
| 2 | Dueño de los jobs de 192.168.66.140 | Lo mismo para `calidad_reclamo.REPOSITORIO_TEMP` y `API_MAPA` | [[02 - Jobs 66.140 (REPOSITORIO_TEMP, API_MAPA)]] | 🔲 pendiente |
| 4 | Dueño de olitel_dfuen (172.26.134.114) | No lanzar copias en paralelo + reescribir filtro `YEAR_ID*100+WEEK_ID` | [[04 - olitel_dfuen reporte paralelo y filtro]] | 🔲 pendiente (desde septiembre) |
| 5 | App wsh | Sacar `CREATE TABLE IF NOT EXISTS` de cada request | [[05 - App wsh CREATE TABLE por request]] | 🔲 pendiente |

## Sin dueño asignado (otros equipos)

- `Aden.PRIORIZACION_TMP_PSG_PCA` (110.163, 486 s) — mismo origen que el #1; probablemente el mismo patrón TRUNCATE.
- `calidad_proyecto.jira_new` (110.49, 399 s) — no aparece en la lista de acciones.

## Referencia

- [[Conceptos - Metadata lock, TRUNCATE y lock_wait_timeout]]
- [[Consultas de diagnostico de bloqueos]]
