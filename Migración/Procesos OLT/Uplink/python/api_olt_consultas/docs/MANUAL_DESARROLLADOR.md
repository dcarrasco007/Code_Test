# Manual de desarrollador — `api_olt_consultas`

Para quien mantiene o extiende la API. Para consumirla, ver
[`MANUAL_USUARIO.md`](MANUAL_USUARIO.md). El diseño por fases y el porqué de cada decisión
están en [`../PLAN_API_OLT.md`](../PLAN_API_OLT.md).

---

## 1. Puesta en marcha (dev)

```bash
cd python/api_olt_consultas
python -m venv .venv                     # Python 3.12
.venv/Scripts/pip install -r requirements.txt   # (Linux: .venv/bin/pip)
cp .env.example .env                      # y completar CONECTION_ADEN si se quiere BD
.venv/Scripts/python -m uvicorn main:app --reload
```

Sin `CONECTION_ADEN` la API **igual levanta** (para no bloquear el trabajo): `/health`
responde y el housekeeping de arranque se omite con un warning. Cualquier endpoint que
toque BD fallará hasta configurarla.

`API_DOCS_HABILITADO=true` en `.env` expone `/docs`, `/redoc` y `/openapi.json`. En
producción va en `false` (default).

### Tests

No hay `pytest` ni `httpx`: cada archivo de `tests/` es un script que se corre solo y
usa fakes en memoria (nunca una OLT ni una BD reales).

```bash
for t in test_token test_ratelimit test_validador_comandos test_telnet_olt \
         test_sesiones_telnet test_ejecutor test_parsers test_lectura \
         test_ejecucion test_hardening test_f9 test_parsers_reales; do
  .venv/Scripts/python -m tests.$t
done
```

Estado esperado hoy: **114 tests** en verde (F2–F8, más los 2 de apoyo a F9).

`tests/test_parsers_reales.py` corre los parsers sobre las capturas reales de
`tests/transcripciones/` (hoy vacío → pasa con 0 casos; se llena en F9, ver
`VALIDACION_F9.md` y el `LEEME.md` de esa carpeta).

### El CLI Huawei falso (`tests/fake_olt_cli.py`)

Servidor TCP asyncio en `localhost` que imita el CLI Huawei: login, límite configurable de
sesiones por usuario (rechaza la 4ª con el mensaje real), enable/config/interface, `More`,
confirmaciones, logout. Es lo que permite probar el gobernador de cupo y el cliente telnet
sin equipos. `tests/test_telnet_olt.py` y `tests/test_sesiones_telnet.py` lo usan.

---

## 2. Estructura y flujo

Convención GERET `README_PYTHON_API.md`. Etiquetas greppables en el código:
`[CONFIG]`, `[RUTA]`, `[SQL]`, `[ROUTER]`, `[SALUD]`, `[SEGURIDAD]`, `[PARIDAD-PHP]`, `[F8]`.

```
main.py                    FastAPI, middlewares (hardening → auditoría → CORS), routers, lifespan
app/config/
  settings.py              ÚNICO lector del .env. Nada más lee os.getenv.
  db.py                    engine SQLAlchemy singleton (mysql+pymysql)
router/<mod>/<mod>Router.py  endpoints; validan entrada, llaman a model/ o utils/ejecutor, arman respuesta
model/<mod>/m_<mod>_model.py SOLO SQL parametrizado (text() + binds)
schema/                    Pydantic v2 para los bodies de /ejecutar/*
utils/
  token.py                 verify_api_key (X-API-Key) + requerir_scopes + anti fuerza bruta
  seguridad.py             hash/verify de API key (PBKDF2-SHA256, stdlib)
  ratelimit.py             ventana deslizante por cliente + cuota de ejecuciones/hora (en memoria)
  validador_comandos.py    display / template aprobado / deny-list / sanitización
  telnet_olt.py            cliente Telnet asyncio puro (IAC, More, contextos, logout garantizado)
  sesiones_telnet.py       GOBERNADOR: cupo por (OLT, usuario) + tope global + circuit-breaker + registro BD
  ejecutor.py              consulta→comandos (BD) → expande slots/puertos → telnet → persiste log
  jobs.py                  cola asyncio + 1 worker → OLT_API_JOBS
  auditoria.py             INSERT best-effort en OLT_API_AUDITORIA
  func.py                  contrato de respuesta, dependencia de lectura, serialización, atender_lectura()
package/parsers/<codigo>.py  1 parser por consulta: parsear(texto_crudo) -> dict ; registry.py mapea
sql/                       DDL + seeds para el RESPONSABLE (el agente/API no ejecuta DDL)
```

### Flujo de una **lectura** (`GET /equipo/fan/OLT-X`)

```
cliente → middleware_hardening (request-id, límite de body)
        → middleware_auditoria (marca, mide)
        → verify_api_key (Depends): X-API-Key → OLT_API_CLIENTES, IP, bloqueo
        → cliente_lectura (Depends): scope 'lectura' + verificar_rate_limit
        → router.equipo.fan → utils.func.atender_lectura("fan", "OLT-X", …)
              → model.olt.obtener_olt  (404 si no existe)
              → model.lectura.leer_ultimo  (último lote de OLT_FAN_ESTADO)
              → model.lectura.ultimo_log_telnet  (si ?con_log=true)
              → func.respuesta_dato(...)
        → middleware_auditoria: INSERT LECTURA OK
        → middleware_hardening: cabeceras de seguridad
```

### Flujo de una **ejecución** (`POST /ejecutar/consulta/fan`)

```
… verify_api_key → requerir_scopes("ejecutar_consulta")
  → router.ejecutar: consulta_meta (404), _resolver_olt (404/422), verificar_cuota_ejecucion (429)
  → si forzar_async: gestor.encolar → 202 {job}
  → si no: registrar_ejecucion → _correr_consulta():
        asyncio.wait_for(timeout = consulta.timeout_seg + MARGEN):
          ejecutor.ejecutar_consulta():
            _consulta_por_codigo + _filas_de_comandos (OLT_API_CONSULTA_COMANDOS, prioridad server>modelo>general)
            gobernador.adquirir(olt,usuario):  semáforo global → semáforo (olt,usuario) → INSERT sesión ABIERTA
              telnet: conectar → (enable/config según contexto) → expandir slots/puertos → enviar_comando…
              finally: cliente.cerrar()  (logout garantizado)
            _persistir_log → OLT_API_LOG_TELNET (recorta a MAX_LOG_CRUDO_BYTES)
        registry.parsear(codigo, log_crudo) → dato   (None → dato:null + meta.motivo, log completo igual)
  → 200 contrato con fuente:"telnet"
```

Errores de `ejecutor`/gobernador → `_map_error()` → 404/409/503/504.

---

## 3. Cómo agregar…

### …una consulta nueva (lectura + ejecución)

1. **Catálogo (BD, lo corre el responsable):** una fila en `OLT_API_CONSULTAS` y sus filas
   en `OLT_API_CONSULTA_COMANDOS`. Añádelas a `sql/02_seed_consultas_comandos.sql` con el
   mismo formato (ver la cabecera de ese archivo: `contexto`, `repetir_por`, `fuente_lista`,
   `lista_fija`, prioridad `server`>`modelo`>general).
2. **Lectura (F5):** una entrada en `_CATALOGO_LECTURA` de
   `model/lectura/m_lectura_model.py` → `(tabla, col_olt, "server"|"ip", col_fecha,
   fecha_es_datetime)`. Añade el endpoint en el router del módulo que corresponda
   (`router/<mod>/<mod>Router.py`) copiando el patrón de 4 líneas.
3. **Parser (F4/F6):** `package/parsers/<codigo>.py` con `parsear(texto_crudo) -> dict`,
   regístralo en `package/parsers/registry.py`.
4. **Tests:** caso en `tests/test_parsers.py` (transcripción sintética) y, si toca lógica
   nueva de lectura, en `tests/test_lectura.py`.
5. `docs/MANUAL_USUARIO.md` tabla de la sección 3.

### …un endpoint de lectura sobre una consulta que ya está en el catálogo

Solo el paso 2 de arriba (entrada en `_CATALOGO_LECTURA` si falta + endpoint en el router).

### …un comando de configuración aprobado para `/ejecutar/comandos`

`OLT_API_COMANDOS_APROBADOS` **nace vacía** (decisión confirmada). Para habilitar uno:

```sql
INSERT INTO OLT_API_COMANDOS_APROBADOS (template, descripcion, scope_requerido, aprobado_por)
VALUES ('^display service-port [0-9]{1,6}$', 'consulta de service-port por id', 'config_aprobada', 'Fulano');
```

- `template` es un **regex anclado** (`^…$`). Un template mal formado no tumba la API
  (`matches_aprobado` lo ignora), pero el cliente igual necesita el scope `config_aprobada`.
- La deny-list absoluta (`undo|reset|reboot|save|delete|…`) se evalúa **siempre**, aunque el
  comando matchee un template. Ver `utils/validador_comandos.py`.

### …un router nuevo

`router/<mod>/<mod>Router.py` con `APIRouter(prefix="/…")`, e inclúyelo en `main.py`
(bloque `[ROUTER]`) con su `tags=[...]`.

### …una `fuente_lista` para expandir slots/puertos

Añade el resolvedor a `RESOLVEDORES_LISTA` en `utils/ejecutor.py`. Firma:
`(engine, *, lista_fija, server, modelo, slot_actual) -> List[str]`.

---

## 4. Seguridad — revisión OWASP API Security Top-10 (2023)

| Riesgo | Estado en `api_olt_consultas` |
|---|---|
| **API1 Broken Object Level Auth** | Los objetos son OLT (públicas dentro de la red) y jobs. `GET /jobs/{uuid}` comprueba `cliente_id` (403 si es de otro y no es admin). Logs/auditoría/sesiones: solo scope `admin`. |
| **API2 Broken Authentication** | API key 32 B, hash PBKDF2-SHA256 + `compare_digest`; 401 genérico sin distinguir motivo; retardo fijo en fallo; bloqueo por IP tras `MAX_INTENTOS_AUTH` persistido en BD; **una key correcta no levanta el bloqueo** (a propósito). `verificar()` se llama siempre (hash señuelo) para no filtrar por timing si el prefijo existe. |
| **API3 Broken Object Property Level Auth** | Respuestas armadas campo a campo (`serializar_fila` oculta `id` salvo donde hace falta; `/admin/clientes/me` nunca devuelve `api_key_hash`). |
| **API4 Unrestricted Resource Consumption** | Rate-limit por cliente + cuota de ejecuciones/hora; `MAX_BODY_BYTES` (413); `MAX_COMANDOS_REQUEST`/`MAX_OLTS_REQUEST` (422); `LIMITE_FILAS_RANGO` en lecturas; `MAX_LOG_CRUDO_BYTES` al persistir; cupo de sesiones telnet + tope global + circuit-breaker; timeouts por consulta y por job. |
| **API5 Broken Function Level Auth** | `requerir_scopes(...)` en cada router; scopes en CSV por cliente. Sin escalada: los scopes solo se leen de BD. |
| **API6 Unrestricted Access to Sensitive Business Flows** | Los flujos sensibles (telnet en vivo) exigen scope propio + cuota/hora + auditoría con severidad; la config solo por lista blanca en BD revisada por humano. |
| **API7 SSRF** | La API no toma URLs del cliente. Las IP de las OLT salen de `OLT_SERVER` (BD), nunca del request. |
| **API8 Security Misconfiguration** | `/docs` off en prod; CORS explícito desde `.env` (nunca `*`); cabeceras `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, `CSP: default-src 'none'`; handler global de excepciones que no filtra trazas; `.env` `chmod 600`. **Riesgo aceptado y documentado:** sin TLS (solo red interna) → mitigado con `ips_permitidas` obligatorio + firewall al puerto. |
| **API9 Improper Inventory Management** | `GET /admin/consultas` lista el catálogo; `python/INVENTARIO_PROCESOS_OLT.xlsx` (F10); una sola versión desplegada (sin `--workers`). |
| **API10 Unsafe Consumption of 3rd-party APIs** | No consume APIs de terceros. El "tercero" es el CLI de la OLT: toda respuesta pasa por parsers con regex anclados + se devuelve el crudo para auditar; los comandos salen de catálogo/lista blanca, nunca concatenados. |

### Inyección

- **SQL:** 100 % `sqlalchemy.text()` con binds. Nombres de tabla/columna **nunca** vienen
  del request: salen de diccionarios fijos en código (`_CATALOGO_LECTURA`, `_COLUMNAS_OLT`,
  …). `server`/`slot`/`codigo` tipados por Pydantic con regex/rango.
- **Comandos telnet:** `utils/validador_comandos.py` — largo ≤ 200, ASCII, sin
  `\r \n ; | &`; debe ser `^display\s[a-z0-9\s\-/]+$` **o** matchear un template aprobado
  (con scope); deny-list absoluta siempre. La navegación la genera la API desde parámetros
  tipados.
- **ReDoS:** los regex de parsers están en código (revisables, con tests). Los templates de
  `OLT_API_COMANDOS_APROBADOS` los revisa un humano antes del INSERT y `matches_aprobado`
  captura `re.error`. Riesgo residual bajo; si se abre esa tabla a muchos templates,
  considerar un límite de longitud/complejidad.

### Límites conocidos (heredados del diseño, no bugs)

- Rate-limit, cuota, cupo de sesiones y cola de jobs viven en **memoria de un proceso**
  uvicorn (sin `--workers`). Escalar a varios procesos exige mover ese estado a BD/Redis.
- Un `EOF` inmediato tras el login se interpreta como "límite de sesiones" aunque podría ser
  credencial inválida — aceptable porque el usuario telnet es fijo por `.env`.
- Los parsers y varias `fuente_lista` son de confianza media/baja hasta F9 (validación con
  transcripciones reales) — ver `SOLICITUDES_PENDIENTES.txt`.

---

## 5. Runbook de errores

Dónde mirar primero:
`logs/api.log` (loguru, con `request_id`) · `OLT_API_AUDITORIA` (toda petición) ·
`OLT_API_LOG_TELNET` (cada sesión telnet) · `OLT_API_SESIONES_TELNET` (sesiones y su estado) ·
`OLT_API_JOBS` (asíncronos) · `GET /health` y `GET /admin/sesiones`.

| Síntoma | Causa probable | Diagnóstico | Solución |
|---|---|---|---|
| **Todo da `401`** aun con key correcta | IP no está en `ips_permitidas`, o IP bloqueada por fuerza bruta | `OLT_API_INTENTOS_AUTH` (fila de esa IP, `bloqueado_hasta`); `OLT_API_AUDITORIA` `accion=AUTH_FAIL` `parametro` | Corregir `ips_permitidas` del cliente. El bloqueo se limpia solo al vencer `bloqueado_hasta` o con `UPDATE … SET bloqueado_hasta=NULL, intentos_fallidos=0`. |
| **`503` en toda ejecución** | Credenciales telnet no configuradas | `GET /health` → `telnet.usuario_dedicado`; `.env` `OLT_TELNET_*` | Completar `OLT_TELNET_API_USER/PASS` (dedicado) o `OLT_TELNET_DEFAULT_USER/PASS`. |
| **`503` intermitente en una OLT** | Circuit-breaker activo tras rechazo por límite de sesiones | `logs/api.log` "circuito ABIERTO para (OLT, usuario)"; `OLT_API_AUDITORIA` | Esperar `BLOQUEO_OLT_SEG` (120 s). Si es crónico: crear el usuario dedicado `geretapi` en esa OLT (deja de competir con los crons). |
| **`503` "sin cupo"** aunque la OLT esté libre | Sesiones `ABIERTA` que quedaron de un proceso caído | `GET /admin/sesiones`; `OLT_API_SESIONES_TELNET WHERE estado='ABIERTA'` | Al reiniciar la API se marcan `HUERFANA` solas. Manual: `UPDATE … SET estado='HUERFANA' WHERE estado='ABIERTA'`. El `EVENT` de `sql/03` las cierra tras 1 día. |
| **`504`** | La OLT no responde a tiempo | `OLT_API_LOG_TELNET` (`error`, `log_crudo` parcial), ping a la OLT | Reintentar; si persiste, problema de red/equipo, no de la API. |
| **`200` con `dato: null`** en `/ejecutar/consulta` | El parser no reconoció la salida (cambio de firmware, prompt nuevo) | `log.crudo` de la respuesta (viene completo); comparar con la regex del parser en `package/parsers/<codigo>.py` | Ajustar el regex del parser + su test. La arquitectura no cambia. |
| **`409`** | `OLT_API_CONSULTA_COMANDOS` no tiene filas para ese modelo | `SELECT * FROM OLT_API_CONSULTA_COMANDOS cc JOIN OLT_API_CONSULTAS c … WHERE c.codigo=…` | Añadir la fila del modelo en `sql/02` y cargarla. |
| **La API no levanta** | Falta `CONECTION_ADEN` (solo si un endpoint la necesita) o `.env` mal | stderr al arrancar (`db.py` imprime el formato esperado) | Completar `.env`. `/health` no necesita BD; el resto sí. |
| **Auditoría vacía / warnings "no se pudo insertar en OLT_API_AUDITORIA"** | Tablas `OLT_API_*` no creadas | `SHOW TABLES LIKE 'OLT_API_%'` | El responsable corre `sql/01_tablas_api.sql` → `02` → `03`. |
| **Jobs quedan `PENDIENTE` para siempre** | El worker no arrancó (BD caída al iniciar) | `logs/api.log` "worker de jobs" ; `GET /health` | Reiniciar la API con la BD arriba. Los `PENDIENTE`/`RUNNING` viejos pasan a `ERROR` al reiniciar. |
| **`event_scheduler` OFF → las tablas no se purgan** | MySQL sin scheduler | `SHOW VARIABLES LIKE 'event_scheduler'` | `SET GLOBAL event_scheduler = ON;` (coordinar con DBA, afecta a todo el servidor). |

---

## 6. Despliegue (resumen — detalle en F10 / `README_DEPLOY.md`)

`systemd` corre `uvicorn main:app --host <IP interna> --port 5001` (sin `--reload`, sin
`--workers`). Firewall limita el puerto a las IP consumidoras. `.env` `chmod 600`. Cron/EVENT
de `sql/03_purga_logs.sql`. Alta de clientes con `scripts/crear_cliente.py`. Ningún paso lo
ejecuta el agente sin autorización puntual.
