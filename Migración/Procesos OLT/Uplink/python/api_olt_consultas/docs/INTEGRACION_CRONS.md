# Integración con los crons Python — `api_olt_consultas`

> **Fase F7.** Cómo los procesos batch (`python/uplink_trafico*`, y a futuro el resto de
> los crons OLT) se integran con esta API. Dos niveles, independientes entre sí:
> **(A) log compartido** (implementado) y **(B) llamar a `/ejecutar/consulta`** (propuesta).

---

## Contexto

La API **no reemplaza** a los crons: lee de las tablas `OLT_*` que ellos ya escriben
(F5) y, cuando se le pide explícitamente, abre su propia sesión telnet (F6). El punto
débil de las lecturas (F5) es que **no tienen log**: `GET /uplink/trafico/OLT-X?con_log=true`
solo puede adjuntar un `OLT_API_LOG_TELNET` si *alguien* lo escribió, y hasta F6 el único
que escribe ahí es la propia API cuando ejecuta en vivo.

F7 cierra ese hueco: que el cron que ya hizo el telnet **deje su log crudo** en la misma
tabla, marcado `origen='cron'`.

---

## A. Log compartido (`OLT_API_LOG_TELNET`) — implementado

### Qué hace

`python/uplink_trafico_15m` (los 3 procesos: MA5800-X15, MA5600T, "otros") escribe una
fila en `OLT_API_LOG_TELNET` por cada sesión telnet de tráfico, con:

| Columna | Valor |
|---|---|
| `olt` | nombre del server (`OLT_SERVER.server`) |
| `ip` | IP de la OLT |
| `consulta_codigo` | `'uplink_trafico'` |
| `origen` | `'cron'` |
| `job_uuid`, `cliente_id` | `NULL` |
| `comandos_enviados` | comandos reconstruidos (`interface giu 0/17` + `display port traffic N`…) |
| `log_crudo` | texto CRUDO devuelto por el equipo (la fuente de verdad) |
| `duracion_ms`, `exito`, `error` | métricas de la sesión |

A partir de ahí, `GET /uplink/trafico/{server}?con_log=true` devuelve ese log en el
campo `log` del contrato (la lectura del dato sigue viniendo de `OLT_TRAFICO_UPLINK_HORA`;
el log solo se **adjunta**).

### Cómo se activa

Es **opt-in y best-effort**. En `python/uplink_trafico_15m/.env`:

```
LOG_API_TELNET=true
```

- Default `false` → el cron no toca `OLT_API_LOG_TELNET` en absoluto.
- Requiere que las tablas `OLT_API_*` existan (`api_olt_consultas/sql/01_tablas_api.sql`,
  lo corre el responsable).
- Si la tabla no existe o el `INSERT` falla, el proceso de tráfico **sigue igual**: el
  log del cron se salta esa corrida y avisa una vez. Nunca revierte un `INSERT` de
  tráfico (usa su propia transacción — ver `uplink_trafico_15m/utils/log_api.py`).

### Retención

La misma que para los logs de la API: el `EVENT` de `sql/03_purga_logs.sql` borra
`OLT_API_LOG_TELNET` con más de `LOG_RETENCION_DIAS` (30) — no distingue `origen`.

### Cómo lo adopta otro cron

1. Copiar `uplink_trafico_15m/utils/log_api.py` al proyecto (o replicar el `INSERT`).
2. Añadir `LOG_API_TELNET` a su `.env` / settings.
3. Tras su sesión telnet, llamar a `registrar_log_telnet(engine, olt=…, ip=…,
   log_crudo=…, duracion_ms=…, exito=…, comandos=…)` con el `consulta_codigo`
   correspondiente (por defecto `'uplink_trafico'`; parametrizarlo si el cron cubre
   otra consulta del catálogo `OLT_API_CONSULTAS`).

---

## B. Llamar a `/ejecutar/consulta` desde un cron — propuesta (no implementado)

En vez de que cada cron mantenga su propio autómata telnet, a futuro podría **delegar**
la sesión en la API:

```
POST /ejecutar/consulta/uplink_trafico
X-API-Key: <key del cron>
{ "server": "OLT-VITACURA-1" }
```

y recibir `{ dato, log }` ya parseado + persistido.

### Qué haría falta

| Punto | Detalle |
|---|---|
| **API key del cron** | `scripts/crear_cliente.py --nombre "cron uplink_trafico" --scopes lectura,ejecutar_consulta --ips <IP del server de crons>`. El responsable ejecuta el `INSERT`. |
| **Cupo de sesiones** | Hoy la API comparte los usuarios telnet de los crons (`OLT_TELNET_API_DEDICADO=false` → cupo 1). Si los crons empiezan a llamar a la API *además* de hacer su propio telnet, hay que crear el usuario dedicado `geretapi` (ya en `SOLICITUDES_PENDIENTES.txt`) para no competir por el cupo de 3. |
| **Paridad de datos** | Los parsers de la API (`package/parsers/`) todavía no están validados contra transcripciones reales (F9). Migrar un cron a `/ejecutar/consulta` debe esperar a que F9 confirme que el `dato` de la API coincide con el que hoy escribe el cron. |
| **Modo asíncrono** | Un cron que procesa muchas OLT usaría `POST /ejecutar/lote` (→ `job_uuid`, `GET /jobs/{uuid}`), no 20 llamadas sincrónicas. |

### Recomendación

No migrar ningún cron a B todavía. El orden natural es: **F7-A** (ya) → **F9** (validar
paridad de parsers con datos reales) → recién ahí evaluar mover crons concretos a
`/ejecutar/consulta`. Mientras tanto, F7-A ya le da a las lecturas de F5 el log crudo
que les faltaba.
