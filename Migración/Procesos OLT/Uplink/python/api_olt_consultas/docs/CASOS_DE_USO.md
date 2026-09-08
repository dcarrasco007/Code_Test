# Casos de uso — `api_olt_consultas`

**Revisado en F8**: los casos CU-01 a CU-08 están implementados (F5/F6/F7). Cada caso
indica actor, precondiciones, flujo normal, flujos alternos y errores esperados. Sigue
siendo el contrato para las pruebas de F9 (validación con equipos reales). Los ejemplos
`curl` concretos están en [`MANUAL_USUARIO.md`](MANUAL_USUARIO.md); el runbook de errores,
en [`MANUAL_DESARROLLADOR.md`](MANUAL_DESARROLLADOR.md).

---

## Actores

| Actor | Quién es | Scopes | Estado |
|---|---|---|---|
| **Portal web OLT (PHP)** | El portal que hoy muestra los datos de las tablas `OLT_*` | `lectura`, `ejecutar_consulta` | Alta en F1 |
| **Crons Python** (`uplink_trafico*`) | Procesos batch ya migrados | `lectura`, `ejecutar_consulta`, `log_cron` | Alta en F1 |
| **Administrador** | Responsable del proyecto / operador | `admin` + todos | Alta en F1 |
| **bot_olt (Telegram)** | Bot de consultas | `lectura` (+ `ejecutar_consulta` opcional) | Fase futura |
| **Sistema interno adicional** | Sin nombre confirmado | por definir | `SOLICITUDES_PENDIENTES.txt` |

Convención transversal: toda petición lleva `X-API-Key`; toda petición queda en
`OLT_API_AUDITORIA`; ningún endpoint acepta comandos de navegación (`enable`, `config`,
`interface`, `quit`) desde el cliente — los genera la API.

---

## CU-01 · Consultar un dato ya recolectado (sin tocar la OLT)

**Actor:** Portal web · **Scope:** `lectura` · **Endpoints:** los `GET` de F5.

**Precondición:** el cron correspondiente ya escribió en su tabla `OLT_*`.

**Flujo normal**
1. El actor llama `GET /alarmas/activas/OLT-VITACURA-1`.
2. La API valida API key, IP permitida y rate-limit.
3. Consulta la tabla del dato (`OLT_ALARMAS`) con SQL parametrizado.
4. Si se pidió `?con_log=true`, adjunta el último `OLT_API_LOG_TELNET` de esa consulta/OLT.
5. Responde `200` con `{dato, log, fuente:"bd", fecha_dato}`.

**Alternos**
- **A1 — sin log disponible:** la consulta la alimenta un cron que aún no escribe en
  `OLT_API_LOG_TELNET` (pendiente F7) → `log: null` y `meta.motivo_log: "sin log de telnet…"`.
- **A2 — sin datos para esa OLT:** `200` con `dato: null` y `meta.motivo: "sin registros"`
  (no es error: puede que el cron aún no haya corrido).
- **A3 — rango de fechas:** `?desde&hasta` devuelve la serie histórica en vez del último valor
  (solo consultas cuya columna de fecha es `DATETIME`; el resto responde `422` — ver
  PLAN_API_OLT.md F5, "Límite conocido").
- **A4 — `?con_log=true`:** adjunta el último `OLT_API_LOG_TELNET` de esa consulta/OLT en `log`.

**Errores:** `401` key inválida/IP no permitida · `403` scope insuficiente ·
`404` OLT inexistente en `OLT_SERVER` · `429` rate-limit.

---

## CU-02 · Ejecutar una consulta predefinida contra la OLT (telnet en vivo)

**Actor:** Portal web / crons · **Scope:** `ejecutar_consulta` ·
**Endpoint:** `POST /ejecutar/consulta/{codigo}`.

**Precondición:** la consulta existe y está activa en `OLT_API_CONSULTAS`, con sus comandos
en `OLT_API_CONSULTA_COMANDOS` para el modelo de esa OLT.

**Flujo normal**
1. `POST /ejecutar/consulta/fan` con `{"server": "OLT-VITACURA-1"}`.
2. La API resuelve modelo/IP desde `OLT_SERVER` y arma la secuencia de comandos desde BD
   (expandiendo slots/puertos según `fuente_lista`).
3. Pide cupo al gobernador para `(OLT, usuario_telnet)`.
4. Abre **una sola** sesión telnet, ejecuta la secuencia, sale con `quit` + `y`.
5. Parsea la salida con el parser de esa consulta.
6. Persiste `OLT_API_LOG_TELNET` (log crudo + comandos) y auditoría.
7. Responde `200` con `{dato, log{crudo, comandos, duracion_ms}, fuente:"telnet"}`.

**Alternos**
- **A1 — sin cupo libre:** espera hasta `ESPERA_MAX_SESION_SEG`; si expira → `503` con
  `Retry-After`. El cliente reintenta más tarde.
- **A2 — varias OLT o consulta larga:** supera `modo_sync_max_olts`/timeout → `202` con
  `job_id`; continúa en **CU-04**.
- **A3 — la OLT no responde al `quit`:** la sesión se marca `HUERFANA`, se cierra el socket
  igual y se respeta un periodo de gracia antes de reusar ese cupo.
- **A4 — parser sin coincidencias:** `200` con `dato: null` pero **log crudo completo**, para
  que el operador vea qué respondió el equipo (caso típico de cambio de firmware).

**Errores:** `404` consulta o OLT inexistente · `409` la consulta no tiene comandos para ese
modelo · `503` OLT saturada (circuit-breaker activo) o inalcanzable · `504` timeout telnet.

---

## CU-03 · Ejecutar comandos con orden definido por el cliente

**Actor:** Portal web (usuario avanzado) / Administrador ·
**Scope:** `ejecutar_comandos` (+ `config_aprobada` si hay comandos de configuración) ·
**Endpoint:** `POST /ejecutar/comandos`.

**Precondición:** cada comando pasa la validación de `utils/validador_comandos.py`.

**Flujo normal**
1. `POST /ejecutar/comandos` con:
   ```json
   {"server": "OLT-VITACURA-1",
    "contexto": {"tipo": "gpon", "slot": 1},
    "comandos": ["display port state 0", "display ont info summary 0"]}
   ```
2. La API valida cada comando: debe ser `display …` **o** coincidir con un template activo de
   `OLT_API_COMANDOS_APROBADOS`; además ninguno puede caer en la deny-list.
3. La navegación (`enable` → `config` → `interface gpon 0/1` → `quit`) la **genera la API** a
   partir de `contexto`, con tipos y rangos validados.
4. Ejecuta en una sola sesión respetando el cupo (igual que CU-02).
5. Responde `200` con el **log crudo completo** y la salida separada por comando.

**Alternos**
- **A1 — comando de configuración aprobado:** requiere scope `config_aprobada`; queda en
  auditoría con severidad reforzada.
- **A2 — más de `MAX_OLTS_SYNC` OLT:** pasa a job (CU-04).

**Errores:** `422` comando rechazado (no es `display`, no está aprobado, o cae en deny-list:
`undo|reset|reboot|save|delete|erase|format|load|shutdown|…`) · `422` más de
`MAX_COMANDOS_REQUEST` comandos o `MAX_OLTS_REQUEST` OLT · `403` sin scope.

> **Día 1 la lista blanca de configuración está vacía:** solo se aceptan comandos `display`.
> Cada comando de configuración se habilita con un `INSERT` revisado por el responsable.

---

## CU-04 · Seguir una ejecución asíncrona (job)

**Actor:** cualquiera con `ejecutar_consulta` · **Endpoints:** `GET /jobs/{uuid}`, `GET /jobs`.

**Flujo normal**
1. CU-02/CU-03 devolvió `202 {job:{uuid, estado:"PENDIENTE"}}`.
2. El cliente consulta `GET /jobs/{uuid}` periódicamente.
3. Estados: `PENDIENTE` → `RUNNING` → `OK` (con `resultado`) o `ERROR`/`TIMEOUT`.

**Alternos**
- **A1 — reinicio de la API con jobs `RUNNING`:** al arrancar se marcan `ERROR` con
  `error: "interrumpido por reinicio"` (no se reanudan solos).

**Errores:** `404` uuid inexistente · `403` job de otro cliente (solo `admin` ve todos).

---

## CU-05 · Auditar qué se ejecutó y qué respondió el equipo

**Actor:** Administrador · **Scope:** `admin` ·
**Endpoints:** `GET /logs/telnet`, `GET /admin/auditoria`, `GET /admin/sesiones`.

**Flujo normal**
1. `GET /logs/telnet?olt=OLT-X&consulta=fan&desde=…&hasta=…` → historial de sesiones con log crudo.
2. `GET /admin/auditoria?accion=EJECUCION&cliente=portal` → quién pidió qué y cuándo.
3. `GET /admin/sesiones` → sesiones telnet abiertas ahora, con su cupo efectivo por OLT.

**Uso típico:** un operador reporta un dato raro → se busca el log crudo de esa consulta/OLT
y se compara con lo que parseó la API.

**Errores:** `403` sin scope `admin`.

---

## CU-06 · Alta y rotación de una API key

**Actor:** Administrador (fuera de la API, por consola).

**Flujo normal**
1. `python scripts/crear_cliente.py --nombre "Portal OLT" --scopes lectura,ejecutar_consulta --ips 10.x.x.x`
2. El script genera la key, **la muestra una sola vez** e imprime el `INSERT` correspondiente.
3. El responsable ejecuta ese `INSERT` (el agente nunca lo hace: `README_LIMITES.md`).
4. Rotación: se da de alta la nueva key, se actualiza el consumidor, se desactiva la anterior
   (`activo=0`); nunca se borra, para conservar la trazabilidad de la auditoría.

**Errores:** si el cliente queda sin `ips_permitidas`, la API rechaza sus peticiones con `401`
(decisión de diseño por exponerse sin TLS).

---

## CU-07 · Un cliente supera sus límites o intenta abusar

**Actor:** cualquiera (incluye escenario de ataque).

| Escenario | Respuesta de la API |
|---|---|
| Key inexistente o incorrecta | `401` genérico (no distingue el motivo) + retardo fijo + contador por IP |
| `MAX_INTENTOS_AUTH` fallos | Bloqueo de la IP por `BLOQUEO_AUTH_MINUTOS` en `OLT_API_INTENTOS_AUTH` |
| Supera `rate_limit_peticiones` | `429` con `Retry-After` |
| Supera `max_ejecuciones_hora` (telnet) | `429`, protege a las OLT |
| Comando fuera de la whitelist | `422` sin ejecutar nada; queda en auditoría como `RECHAZADO` |
| Intento de inyección SQL en `server` | `422` por validación de patrón; la query siempre usa binds |
| Petición desde IP no autorizada | `401`, aunque la key sea válida |

---

## CU-08 · Los crons y la API compiten por el cupo de la OLT

**Actor:** sistema (concurrencia real en producción).

**Escenario:** el cron de 15 min ya tiene sesiones abiertas cuando llega una petición a la API.

**Flujo**
1. Con `OLT_TELNET_API_DEDICADO=false`, la API se autolimita a **1** sesión por
   `(OLT, usuario)` y deja 2 libres para los crons.
2. Si la OLT igual rechaza por límite, se activa el circuit-breaker `BLOQUEO_OLT_SEG` y las
   siguientes peticiones a esa OLT reciben `503` sin intentar conectarse.
3. Con el usuario dedicado `geretapi` creado (ver `SOLICITUDES_PENDIENTES.txt`), la API pasa a
   cupo **3** y deja de competir con los crons.

**Verificación (F9):** lanzar 5 ejecuciones simultáneas a la misma OLT mientras corre un cron
y comprobar con `display users` en el equipo que nunca hay más de 3 sesiones del mismo usuario,
y que al terminar no queda ninguna colgada.
