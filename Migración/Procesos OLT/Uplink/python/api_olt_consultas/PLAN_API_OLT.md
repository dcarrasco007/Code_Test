# PLAN_API_OLT.md — API de consultas a OLT Huawei (`api_olt_consultas`)

> **Documento maestro del proyecto.** Contiene el análisis de origen, el diseño y el
> plan por fases. Cada fase es pausable: se puede completar, validar y retomar sin
> perder contexto. Al terminar una fase, marcar su casilla y actualizar el tracker.
>
> Convenciones: GERET `README_PYTHON_API.md` (estructura), `README_LIBRERIAS.md`
> (dependencias), `README_LIMITES.md` (qué NO puede hacer el agente),
> `README_DEPLOY.md` (despliegue). Etiquetas greppables en el código:
> `[CONFIG]`, `[RUTA]`, `[SQL]`, `[ROUTER]`, `[SALUD]`, `[SEGURIDAD]`, `[PARIDAD-PHP]`.

---

## Tracker de fases

| Fase | Nombre | Estado |
|---|---|---|
| F0 | Andamiaje + diseño documentado | ✅ Completada |
| F1 | Base de datos (`db.py` + `sql/*.sql` para el responsable) | ✅ Completada |
| F2 | Seguridad (API keys, scopes, rate-limit, validador de comandos) | ✅ Completada |
| F3 | Telnet asyncio + gobernador de sesiones (límite 3/OLT) | ✅ Completada |
| F4 | Ejecutor + parsers de las 20 consultas | ✅ Completada |
| F5 | Routers de lectura (datos desde BD) | ✅ Completada |
| F6 | Routers de ejecución + jobs + logs + admin | ✅ Completada |
| F7 | Integración con los crons Python (log compartido) | ✅ Completada |
| F8 | Hardening + manuales (usuario y desarrollador) | ✅ Completada |
| F9 | Validación en producción (paridad + límite de sesiones) | 🔄 En curso (parte del agente lista; ejecuta el responsable) |
| F10 | Despliegue (systemd + firewall + purga + inventario) | ☐ Pendiente |

Leyenda: ☐ Pendiente · 🔄 En curso · ✅ Completada

### F0 — completada

- [x] Estructura GERET (`app/config`, `router/`, `model/`, `schema/`, `utils/`,
      `package/parsers`, `sql/`, `scripts/`, `tests/`, `docs/`, `logs/`).
- [x] `.venv` con Python 3.12 + `requirements.txt` congelado (solo librerías aprobadas).
- [x] `.env.example` + `.env` (vacío, a completar en producción) + `.gitignore`.
- [x] `app/config/settings.py`: lectura centralizada del `.env`, incluida la regla de
      cupo de sesiones telnet.
- [x] `main.py` + `router/salud/saludRouter.py` → `GET /health` (sin auth).
- [x] `docs/DIAGRAMAS.md` y `docs/CASOS_DE_USO.md` (versión de diseño).
- [x] `SOLICITUDES_PENDIENTES.txt` con lo que depende de terceros.
- [x] Smoke test: `uvicorn main:app` → `/health` 200, y el cupo efectivo reportado es
      **1** (no hay usuario dedicado todavía), confirmando la regla de seguridad.

**Desviación documentada respecto a `requirements.example.txt` de GERET:** ese archivo
fija `fastapi==0.75.2` / `pydantic==1.9.2` (versiones de `api_general_v2`, que corre en
el servidor 140 con Python 3.6). Este proyecto exige **Python 3.12**, donde esas
versiones no son instalables/soportadas, así que se usan las actuales
(`fastapi 0.141.1`, `pydantic 2.13.5`). Implica **Pydantic v2** (sintaxis de schemas
distinta a la de `api_general_v2`); queda anotado en `docs/MANUAL_DESARROLLADOR.md`.

---


### F1 — completada

- [x] `app/config/db.py`: engine SQLAlchemy singleton (mysql+pymysql), valida
      `CONECTION_ADEN` con mensaje claro (mismo patron de db.py en los proyectos
      `uplink_trafico`/`uplink_trafico_15m`).
- [x] `utils/seguridad.py`: `generar_api_key`, `hashear`, `verificar`, `prefijo`
      — PBKDF2-SHA256 via hashlib (stdlib), mismo patron que
      `Desarrollo/bot_olt/utils/seguridad.py`. F2 lo extendera con la
      dependencia FastAPI `verify_api_key`.
- [x] `sql/01_tablas_api.sql`: DDL de las 9 tablas nuevas `OLT_API_*`
      (CLIENTES, INTENTOS_AUTH, AUDITORIA, CONSULTAS, CONSULTA_COMANDOS,
      COMANDOS_APROBADOS, LOG_TELNET, JOBS, SESIONES_TELNET). Idempotente
      (`CREATE TABLE IF NOT EXISTS`), con verificacion y rollback documentados.
- [x] `sql/02_seed_consultas_comandos.sql`: catalogo completo de las 20
      consultas + sus comandos telnet por modelo/server, extraidos del
      analisis de origen (`php_8_0/`). `OLT_API_COMANDOS_APROBADOS` queda
      sin insertar nada (nace vacia, decision confirmada).
- [x] `sql/03_purga_logs.sql`: EVENT diario de retencion/purga para las 6
      tablas que acumulan filas (logs, auditoria, intentos, jobs, sesiones).
- [x] `scripts/crear_cliente.py`: genera API key + imprime el INSERT para
      `OLT_API_CLIENTES` (no se conecta a BD, no ejecuta nada).

**Verificado (sin conexion a BD, el agente no ejecuta DDL/DML — ver
README_LIMITES.md):**
- `db.py` con `.env` vacio falla limpio con mensaje claro.
- `seguridad.py`: hash/verify OK (key correcta, incorrecta, hash corrupto).
- `crear_cliente.py`: genera key + INSERT valido con y sin `--ips`.
- Los 3 `.sql` pasan chequeo lexico (parentesis, backticks y comillas
  balanceados) y las 20 variables `@id_*` del seed estan todas declaradas y
  usadas.

**Alcance documentado del seed (02):** cubre el camino GENERAL de cada
consulta por modelo con fidelidad razonable a partir del analisis de origen.
Las excepciones cosmeticas del PHP (prompts exactos de OLT-DURZUA-4, variantes
`estado_equipo2/3` que solo repiten un comando o agregan una linea en blanco)
NO se replican — mismo criterio ya aplicado en `uplink_trafico_15m`. Si F4
(parsers, con datos reales) revela que hace falta alguna excepcion puntual
para que el parser funcione, se agrega como fila `server`-scoped adicional;
el catalogo esta diseñado para extenderse asi sin rehacerse.

**Pendiente para el responsable (no bloquea seguir desarrollando):** ejecutar
`sql/01`, `sql/02`, `sql/03` en la BD Aden real, y confirmar
`event_scheduler = ON` en MySQL para que el EVENT de purga corra.

---

### F2 — completada

- [x] `utils/token.py`: dependencia FastAPI `verify_api_key` (header
      `X-API-Key`) + `requerir_scopes(*scopes)`. Flujo: IP bloqueada por
      fuerza bruta → falta el header → prefijo/hash no coincide → cliente
      inactivo → IP no en `ips_permitidas` → OK. Todo fallo usa el mismo
      mensaje 401 (no distingue motivo), aplica un retardo fijo, y siempre
      llama a `seguridad.verificar()` (con hash real o un hash "señuelo" si
      el prefijo no existe) para no delatar por temporización si el cliente
      existe o no.
- [x] Anti fuerza bruta persistido en `OLT_API_INTENTOS_AUTH`: contador por
      IP con `ON DUPLICATE KEY UPDATE`; al llegar a `MAX_INTENTOS_AUTH` fija
      `bloqueado_hasta = NOW() + BLOQUEO_AUTH_MINUTOS`; si el bloqueo anterior
      ya venció, el siguiente fallo reinicia el contador en 1 (no se queda
      bloqueada para siempre por un historial viejo). Un login correcto
      limpia el contador.
- [x] `utils/ratelimit.py`: ventana deslizante en memoria por cliente
      (`rate_limit_peticiones`/`rate_limit_ventana_seg` propios de cada fila
      de `OLT_API_CLIENTES`) → 429 con `Retry-After`. Cuota separada
      `verificar_cuota_ejecucion`/`registrar_ejecucion` para
      `max_ejecuciones_hora` (la usarán los endpoints de `/ejecutar/*` en
      F6). Límite conocido documentado en el propio archivo: en memoria de
      un solo proceso, igual que el gobernador de sesiones telnet de F3.
- [x] `utils/validador_comandos.py`: `validar_comando`/`validar_lista_comandos`
      — largo máximo 200, rechazo de `\r \n ; | &`, control y no-ASCII;
      lectura `^display\s[a-z0-9\s\-/]+$`; template aprobado (regex de
      `OLT_API_COMANDOS_APROBADOS`) solo si el cliente tiene el scope
      `config_aprobada`; deny-list absoluta (`undo|reset|reboot|save|delete|
      erase|format|load|shutdown|activate|deactivate|ont add|ont delete|
      board|patch|upgrade|terminal|user|password|quit|return`) evaluada
      SIEMPRE, incluso si el comando ya parecía válido. Un template roto en
      BD no tumba la validación (se ignora ese template puntual).
- [x] `utils/auditoria.py`: `registrar()` — INSERT en `OLT_API_AUDITORIA`,
      *best effort* (nunca propaga una excepción hacia el cliente).
- [x] `main.py`: middleware de auditoría — audita LECTURA/EJECUCION/RECHAZADO
      de toda petición que ya pasó `verify_api_key` (AUTH_OK/AUTH_FAIL los
      audita `token.py` directamente, que es quien tiene el motivo exacto).

**Verificado (sin conexión a BD real — las 4 funciones de acceso a datos de
`token.py` se reemplazan por un fake en memoria que reproduce la misma
semántica que `sql/01_tablas_api.sql`; ver README_LIMITES.md):**
- `tests/test_token.py` (9/9): key correcta autentica y expone scopes; key
  incorrecta / sin header / IP no autorizada / cliente inactivo → 401
  genérico; **fuerza bruta simulada**: `MAX_INTENTOS_AUTH` fallos bloquean la
  IP y, clave: **una key correcta después del bloqueo sigue dando 401** (el
  requisito explícito del usuario); un login correcto limpia el contador;
  `requerir_scopes` con semántica AND; `_ip_permitida` (IP exacta, CIDR,
  entradas inválidas ignoradas sin caerse).
- `tests/test_ratelimit.py` (4/4): tope por cliente, `Retry-After`, ventana
  que vence y libera cupo, aislamiento entre clientes, cuota de ejecución
  independiente del rate-limit general.
- `tests/test_validador_comandos.py` (10/10): **fuzz de comandos** (CRLF,
  `;`, `|`, `&`, NUL, no-ASCII, largo excesivo), **deny-list** (incluso con
  scope + template que "matchearía"), template aprobado + scope, lista
  completa (junta todos los errores), límites de cantidad de comandos/OLT.
- Smoke test: la API sigue levantando y `/health` sigue en 200 con F2 encima
  (no requiere auth, así que no pasa por `verify_api_key`).

**Pendiente para el responsable:** nada nuevo — F2 no agrega tablas ni
columnas (usa las de F1). Sigue pendiente la ejecución de `sql/01-03` y el
alta de `geretapi` ya registradas en F1/`SOLICITUDES_PENDIENTES.txt`.

---

### F3 — completada

- [x] `utils/telnet_olt.py`: `ClienteTelnetOLT` — cliente Telnet **asyncio
      puro** (sin pexpect ni binario externo), puerto a asyncio de
      `expect_compat.php` (negociación IAC, CRLF, `expect_expectl` →
      `_esperar()`) combinado con los prompts/secuencia de navegación ya
      validados en `uplink_trafico_15m/utils/telnet_olt.py` (pexpect):
      login → `enable()` → `config()` → `enviar_comando()` (maneja 'More' y
      los prompts de confirmación `{ ... }:` con un regex genérico) →
      `cerrar()`. `cerrar()` es **consciente del contexto** en el que quedó
      la sesión (config/enable/user) para mandar solo los `quit` que hagan
      falta, y nunca relanza una excepción (logout garantizado desde un
      `finally`, igual que `_desconectar()` del proyecto hermano). El texto
      devuelto por `enviar_comando()` ya pasó por `_scrub()` (la contraseña
      nunca queda en el log crudo).
- [x] **Detección de rechazo por límite de sesiones**
      (`RechazoPorLimiteSesiones`): mensajes conocidos del CLI Huawei
      ("number of users has reached the upper limit", "Reenter times...") o
      **desconexión inmediata (EOF)** tras enviar user/password. Documentado
      como límite conocido: un EOF también podría ser credencial inválida —
      no distinguible desde aquí, y aceptable porque el usuario telnet de la
      API (`geretapi`) es fijo por `.env`, no lo controla el cliente HTTP.
- [x] `utils/sesiones_telnet.py`: `GobernadorSesiones` — `asyncio.Semaphore`
      por clave `(olt, usuario_telnet)` con cupo `settings.cupo_por_olt_usuario()`
      (3 con `geretapi` dedicado, 1 si no); cola con `asyncio.wait_for(...,
      ESPERA_MAX_SESION_SEG)` → `CupoAgotadoError` si se agota (503 en F6);
      circuit-breaker (`abrir_circuito`/`CircuitoAbiertoError`) que corta
      reintentos a esa `(olt, usuario)` por `BLOQUEO_OLT_SEG` tras un
      rechazo; registro en `OLT_API_SESIONES_TELNET` (alta al adquirir,
      cierre siempre al liberar — éxito o excepción) vía `adquirir()`
      (`asynccontextmanager`); `latido()` para el heartbeat periódico (F6);
      `marcar_huerfanas_al_iniciar()` para limpiar filas `ABIERTA` que
      quedaron de un proceso anterior.
- [x] `main.py`: `lifespan` — llama a `marcar_huerfanas_al_iniciar()` al
      arrancar, **best-effort** (si `CONECTION_ADEN` no está configurada o
      la BD no responde, la API igual levanta — mismo criterio que `/health`).
- [x] `tests/fake_olt_cli.py`: servidor TCP asyncio en `localhost` (puerto
      efímero) que imita el CLI Huawei — login, límite configurable de
      sesiones simultáneas por usuario (rechaza con el mensaje real de
      arriba), enable/config/interface, 'More' opcional, confirmación
      opcional, logout. Sin esto no se puede probar el límite de 3 sesiones
      sin una OLT real.

**Verificado (TCP real en `127.0.0.1` contra `fake_olt_cli`, o con las 3
funciones de acceso a datos de `sesiones_telnet.py` reemplazadas por un fake
en memoria — nunca una OLT ni una BD reales):**
- `tests/test_telnet_olt.py` (8/8): login→enable→config→comando→logout
  completo; la contraseña nunca queda en el texto devuelto; paginado 'More'
  junta las páginas; prompt de confirmación se acepta con Enter; el hostname
  "cosmético" `OLT-DURZUA-4` no necesita caso especial (ya lo cubren los
  regex genéricos — por eso F1 no lo replicó como excepción); credenciales
  inválidas no cuelga ni rompe el cierre; **el límite de 3 sesiones
  simultáneas del mismo usuario**: la 4ta se rechaza, y al liberar una vuelve
  a haber cupo; `respuesta_valida` detecta el marcador de comando no
  reconocido.
- `tests/test_sesiones_telnet.py` (7/7): apertura/cierre registra en BD con
  los datos correctos; un error dentro del bloque libera el cupo igual;
  **cupo de 3 respetado, la 4ta agota la espera** (`CupoAgotadoError`); el
  cupo es independiente por `(olt, usuario)`; circuit-breaker bloquea solo la
  clave afectada; heartbeat llama al registro; huérfanas al iniciar cuenta
  filas afectadas.
- Smoke test: la API sigue levantando con el `lifespan` nuevo, con y sin
  `CONECTION_ADEN` configurada, y `/health` sigue en 200.

**Límites conocidos (documentar en el manual de desarrollador, F8):** tanto
el gobernador de sesiones como `utils/ratelimit.py` viven en memoria de UN
SOLO proceso uvicorn (sin `--workers`) — decisión ya tomada para todo el
proyecto; escalar a varios procesos exigiría mover el cupo a BD/Redis con
locking real.

**Pendiente para el responsable:** nada nuevo. Sigue abierta el alta de
`geretapi` en las OLT (bloquea usar cupo=3 en producción, no el desarrollo:
con el fallback `cupo=1` el gobernador funciona igual).

---

### F4 — completada

- [x] `utils/ejecutor.py`: `ejecutar_consulta()` — resuelve `codigo` →
      `OLT_API_CONSULTAS` + filas activas de `OLT_API_CONSULTA_COMANDOS`
      (prioridad server-específico > modelo-específico > general),
      adquiere cupo del gobernador (F3), navega enable→config, expande
      comandos outer (`repetir_por` en `slot`/`vlan`) e inner (`puerto`) vía
      `RESOLVEDORES_LISTA` (`fijo`, `OLT_SERVER.pto1_12` **— confirmado,
      mismo query que `uplink_trafico_15m`**; `OLT_PUERTAS_UPLINKS_GB`,
      `OLT_VLAN_SERVICIOS`, `OLT_VOLTAJE_TARJETA` — confianza media/baja,
      registrado en `SOLICITUDES_PENDIENTES.txt`), inserta un `quit`
      automático al cambiar de interfaz (nunca se guarda como fila de BD),
      persiste siempre en `OLT_API_LOG_TELNET` — **incluso si falla a mitad
      de la secuencia**, el log parcial (lo que sí se alcanzó a enviar) no
      se pierde.
- [x] `package/parsers/_base.py` + 20 módulos (`package/parsers/<codigo>.py`)
      con `parsear(texto_crudo) -> dict`, y `registry.py` (`codigo → función`).
      Confianza documentada por archivo: **ALTA** (`uplink_trafico.py`,
      `pon_trafico.py` — port directo del parser ya validado en
      `uplink_trafico_15m/utils/parser_trafico.py`, mismo comando exacto),
      **MEDIA** (la mayoría — tablas Huawei genéricas conocidas: alarmas,
      board/power, VLAN, fan, ddm-info, version), **BAJA** (`vlan_trafico.py`,
      `ont_detalle.py`, `uplink_state.py` — sin referencia previa a un
      formato exacto, ver `SOLICITUDES_PENDIENTES.txt`).

**Verificado (con fakes en memoria — nunca una OLT ni una BD reales; texto
crudo SINTÉTICO, no de un equipo real):**
- `tests/test_ejecutor.py` (7/7): expansión outer(slot)/inner(puerto) con el
  `quit` automático entre slots (caso `uplink_trafico`/MA5800-X15 completo:
  3 slots, conteos de puerto distintos); **un fallo a mitad de la secuencia
  persiste el log parcial** (lo enviado antes de fallar no se pierde);
  `RechazoPorLimiteSesiones` abre el circuito del gobernador; consulta
  desconocida / sin comandos para el modelo → error de negocio; expansión de
  `lista_fija` (CSV y rango); prioridad server > modelo > general.
- `tests/test_parsers.py` (24/24): las 20 consultas con una transcripción
  sintética cada una, más una prueba cruzada de que ningún parser revienta
  con texto vacío/basura, y que el registry cubre exactamente las 20
  consultas del seed.
- Suite completa del proyecto (F2-F4): **69/69 tests.**

**Alcance documentado (igual criterio que el seed de F1):** estos parsers y
resolvedores cubren el camino GENERAL con fidelidad razonable a partir del
análisis de origen y el conocimiento del CLI Huawei — **no** están validados
contra una transcripción real de un equipo. Eso es explícitamente tarea de
F9 (el propio plan lo dice: "sintéticas ahora, reales en F9"). Mientras
tanto, F5/F6 pueden exponer `con_log=true` junto al `dato` ya parseado para
que el consumidor compare y se ajusten los regex sin tener que rehacer la
arquitectura.

**Pendiente para el responsable:** 2 entradas nuevas en
`SOLICITUDES_PENDIENTES.txt` (confirmar 3 `fuente_lista` con datos reales;
transcripciones reales para los 20 parsers) — ninguna bloquea seguir con
F5/F6.

---

### F5 — completada

- [x] `utils/func.py`: contrato de respuesta uniforme (`respuesta_dato`:
      `{ok, consulta, olt{server,ip,modelo}, fuente, fecha_dato, dato{registros,
      cantidad}, log, meta, job}`), dependencia única de lectura
      `cliente_lectura` (auth `X-API-Key` + scope `lectura` + rate-limit por
      cliente en un solo `Depends`), serialización BD→JSON (`Decimal`→float,
      `datetime`→ISO, `bytes`→str, oculta la columna `id`), normalización de
      `?desde&hasta` (`rango_fechas`, fin de día inclusive, 422 si
      `hasta<desde`), y el **handler compartido `atender_lectura()`** que usan
      los 20 endpoints (resuelve OLT → 404 si no existe en `OLT_SERVER`; lee
      último lote o rango; adjunta `?con_log`).
- [x] `model/olt/m_olt_model.py`: `obtener_olt(server)` (resuelve
      `server`→`modelo`/`ip`/`region`…; `id DESC LIMIT 1` si hay filas
      duplicadas) y `listar_olts(region?, modelo?)`. Whitelist de columnas en
      código.
- [x] `model/lectura/m_lectura_model.py`: `_CATALOGO_LECTURA` — diccionario
      fijo `codigo → (tabla, col_olt, filtro server|ip, col_fecha,
      fecha_es_datetime)` para las 20 consultas del seed (mismas `tabla_dato`
      que `sql/02`). `leer_ultimo()` (agrupa el último lote por la fecha de la
      fila de `id` más alto), `leer_rango()` (solo columnas `DATETIME`; tope
      `LIMITE_FILAS_RANGO`=5000 configurable; `RangoNoDisponible`→422 si la
      columna de fecha es texto), `ultimo_log_telnet()` (última fila de
      `OLT_API_LOG_TELNET` para esa OLT+consulta). **El nombre de tabla/columna
      NUNCA viene del request** — solo del catálogo; el valor de OLT y las
      fechas van como binds.
- [x] 7 routers de lectura (`router/<modulo>/<modulo>Router.py`), 22 endpoints
      `GET` en total:
      - `olt`: `/olt`, `/olt/{server}`
      - `alarmas`: `/alarmas/{activas,detalle,critical-los,los-ont}/{server}`
      - `uplink`: `/uplink/{trafico,potencia-optica,state}/{server}`
      - `equipo`: `/equipo/{tarjetas,fan,energia/alarma,energia/estado,
        temperatura-cpu,version,uptime}/{server}`
      - `vlan`: `/vlan/{cantidad,trafico,servicios}/{server}`
      - `pon`: `/pon/trafico/{server}` (resuelve `server`→`ip`: la tabla
        `OLT_TRAFICOGPON_HORA` no guarda el nombre de la OLT)
      - `ont`: `/ont/{detalle,reporte}/{server}`
      Todos: `?con_log=true`, `?desde&hasta`, scope `lectura`. La auditoría
      `LECTURA` la hace el middleware de `main.py` (ya estaba).
- [x] `main.py`: registro de los 7 routers con sus tags. `saludRouter` reporta
      `fase: F5`.

**Desviación documentada respecto a `README_PYTHON_API.md`:** el README sugiere
`model/<modulo>/m_<modulo>_model.py` (uno por módulo). Aquí los 7 routers de
lectura hacen exactamente lo mismo (leer el último lote / un rango de una tabla
`OLT_*`), así que la lógica vive en **un** modelo compartido
(`model/lectura/m_lectura_model.py`) + el catálogo, en vez de 7 archivos casi
idénticos. `model/olt/` sí es propio (resuelve la OLT). Se prioriza no duplicar
el mismo SQL 20 veces (mismo criterio que el seed de F1 y los parsers de F4).

**Verificado (sin BD real — `FakeEngine` en memoria que captura el SQL y
devuelve filas canónicas; el resto monkey-patchea las funciones de modelo,
mismo estilo que `test_ejecutor.py`):**
- `tests/test_lectura.py` (18/18): serialización de tipos + columna `id`
  oculta; `?desde&hasta` (vacío, solo `desde`, invertido→422, fin de día);
  contrato de respuesta (con/sin registros → `meta.motivo`); `formatear_log`;
  `atender_lectura` → 404 OLT desconocida, último dato OK, `con_log` sin log →
  `meta.motivo_log`, rango sobre columna de texto → 422, `pon_trafico` sin IP
  en `OLT_SERVER` → 422; **el catálogo cubre exactamente las 20 consultas del
  seed**; `valor_filtro` server vs ip; `leer_ultimo` arma el SQL con
  tabla/columna del catálogo y bind `:olt`; `leer_rango` rechaza columnas de
  texto y trunca al límite.
- Suite completa del proyecto (F2-F5): **87/87 tests.**
- Smoke test: `main:app` importa y `app.openapi()` lista las 22 rutas nuevas +
  `/health`; `/health` sigue sin requerir auth.

**Límite conocido (documentar en el manual, F8):** varias tablas `OLT_*`
guardan la fecha del lote como `VARCHAR` (herencia de los PHP). Para esas
consultas el "último dato" funciona (orden por `id`), pero `?desde&hasta`
responde **422** en vez de arriesgar un `BETWEEN` contra texto de formato no
confirmado. Consultas afectadas: `alarmas_activas`, `alarmas_detalle`,
`tarjetas`, `fan`, `vlan_cantidad`, `pon_trafico`, `temperatura_cpu`,
`uptime_gpon`, `uplink_state`, `ont_reporte`, `version`.

**Pendiente para el responsable:** 1 entrada nueva en
`SOLICITUDES_PENDIENTES.txt` (confirmar, contra Aden real, el nombre de la
columna de OLT y el formato de la columna de fecha de las 20 tablas `tabla_dato`
— el catálogo se dedujo de `BD/ESTRUCTURA_OLT.sql`). No bloquea F6.

---

### F6 — completada

- [x] `utils/ejecutor.py` — ampliado:
      - **`ejecutar_comandos()`**: lista de comandos YA validados en 1 sesión.
        La navegación (`enable`→`config`→`interface {tipo} 0/{slot}`) la genera
        esta función desde `navegacion` (tipos/slot ya tipados por el schema);
        `salida_por_comando` separa la respuesta por comando; persiste
        `OLT_API_LOG_TELNET` con `consulta_codigo=NULL`.
      - **Fix de navegación por contexto**: `ejecutar_consulta` ya NO hace
        siempre `enable`+`config`. Ahora mira los `contexto` de las filas:
        `user` → no navega (caso `energia_estado`, que trabaja en `'>'`);
        `enable` → solo `enable`; `config`/`interface` → `enable`+`config`.
      - **Excepciones transparentes**: `CupoAgotadoError`,
        `CircuitoAbiertoError` y `RechazoPorLimiteSesiones` se propagan tal
        cual (no envueltas en `ErrorEjecucion`, sin persistir un log vacío)
        para que el router elija el 503 correcto.
- [x] `utils/sesiones_telnet.py`: el gobernador ahora respeta también un tope
      **global** `asyncio.Semaphore(TELNET_MAX_CONCURRENTES)` además del cupo
      por `(OLT, usuario)` — se adquiere después del cupo por clave y se libera
      siempre. Antes `TELNET_MAX_CONCURRENTES` estaba en `.env` pero sin uso.
- [x] `utils/jobs.py`: `GestorJobs` — cola `asyncio.Queue` + **un** worker de
      fondo (procesa jobs de a uno), estado en `OLT_API_JOBS`
      (`PENDIENTE`→`RUNNING`→`OK`|`ERROR`|`TIMEOUT`), timeout por job
      (`JOB_TIMEOUT_SEG`), handlers por tipo registrados por el router. Los
      jobs NO se reanudan tras reinicio (`marcar_interrumpidos_al_iniciar` →
      `ERROR`, CU-04/A1). `iniciar/detener` desde el `lifespan` de `main.py`.
- [x] `app/config/settings.py`: `credencial_telnet(perfil)` (usuario dedicado
      si está declarado y con clave, si no el perfil heredado DEFAULT/ALARMAS/
      ONT; `('','')` → el router responde 503), `EJECUCION_SYNC_MARGEN_SEG`,
      `JOB_TIMEOUT_SEG`.
- [x] `schema/ejecucion_schema.py` (Pydantic v2): `EjecutarConsultaRequest`
      (`server` con regex), `NavegacionComandos` (`tipo` ∈ eth/giu/scu/mpu/
      gpon, `slot` 0-20), `EjecutarComandosRequest` (comandos no vacíos),
      `EjecutarLoteRequest` (servers sin duplicados, `codigo` con regex).
- [x] Modelos (solo SQL parametrizado): `model/jobs/m_jobs_model.py`,
      `model/logs/m_logs_model.py`, `model/admin/m_admin_model.py`,
      `model/ejecutar/m_ejecutar_model.py` (`consulta_meta`,
      `templates_aprobados_activos` — hoy `[]`, la tabla nace vacía).
- [x] 4 routers, 9 endpoints:
      - `ejecutar`: `POST /ejecutar/consulta/{codigo}` (sync 200, o 202+job
        si `forzar_async`), `POST /ejecutar/comandos` (422 con la lista de
        comandos rechazados), `POST /ejecutar/lote` (siempre 202+job).
        Scopes `ejecutar_consulta` / `ejecutar_comandos`. Cuota
        `max_ejecuciones_hora` (429). Mapeo de errores → 404/409/503/504
        (`_map_error`).
      - `jobs`: `GET /jobs/{uuid}` (403 si es de otro cliente y no es admin),
        `GET /jobs`. Scope `ejecutar_consulta`; admin ve todos.
      - `logs`: `GET /logs/telnet` (lista sin el crudo), `GET /logs/telnet/{id}`
        (con `log_crudo`). Scope `admin`.
      - `admin`: `GET /admin/{consultas,auditoria,sesiones,clientes/me}`.
        Scope `admin`. `/admin/sesiones` expone el cupo efectivo.
- [x] `main.py`: registro de los 4 routers; `lifespan` arranca/detiene el
      worker de jobs y limpia jobs interrumpidos. `saludRouter` → `fase: F6`.

**Verificado (sin OLT ni BD reales — `FakeClienteTelnet` + funciones de
modelo/persistencia monkey-patcheadas, mismo estilo que `test_ejecutor.py`):**
- `tests/test_ejecucion.py` (14/14): navegación por contexto (`user` → sin
  enable/config; `config` → con); `ejecutar_comandos` con navegación +
  `salida_por_comando`, fallo parcial persiste log y relanza, `CupoAgotado`
  se propaga sin persistir; `credencial_telnet` (dedicado vs perfil vs
  fallback); schemas (server/nav/comandos/lote); cola de jobs (OK guarda
  resultado, handler que falla → ERROR, tipo desconocido → ValueError);
  `_map_error` (503/504/409/404/502).
- `tests/test_ejecutor.py` actualizado (7/7): el rechazo por límite ahora se
  espera como `RechazoPorLimiteSesiones`, no `ErrorEjecucion`.
- Suite completa del proyecto (F2-F6): **101/101 tests.**
- Smoke: `main:app` importa; `app.openapi()` lista 34 rutas (22 de F5 + 9 de
  F6 + `/health` + `/docs*`).

**Límites conocidos (manual de desarrollador, F8):**
- Cola de jobs, cupo de sesiones y rate-limit viven en memoria de UN proceso
  uvicorn (decisión de todo el proyecto).
- El campo `log` de una ejecución en vivo trae `comandos/crudo/duracion_ms/
  exito/error` pero no el `id` de la fila `OLT_API_LOG_TELNET` (el ejecutor
  no lo devuelve); para el `id` se usa `GET /logs/telnet`.
- `OLT_API_COMANDOS_APROBADOS` vacía → `/ejecutar/comandos` solo acepta
  `display …` (decisión confirmada).

**Pendiente para el responsable:** nada nuevo bloqueante. La ejecución real
contra equipos (paridad, textos de rechazo por modelo, credenciales
`geretapi`) ya está en `SOLICITUDES_PENDIENTES.txt` desde F1/F3 y es trabajo
de F9.

---

### F7 — completada

Alcance: nivel **A** (log compartido) implementado; nivel **B** (crons llamando
a `/ejecutar/consulta`) diseñado y explícitamente **diferido a después de F9**.
Todo documentado en `docs/INTEGRACION_CRONS.md`.

- [x] `python/uplink_trafico_15m/utils/log_api.py` (nuevo): `registrar_log_telnet()`
      — INSERT best-effort en `OLT_API_LOG_TELNET` con `origen='cron'`,
      `consulta_codigo='uplink_trafico'`. **Garantías**: no-op si
      `LOG_API_TELNET` ≠ `true`; transacción propia (otra conexión del pool) →
      nunca revierte un INSERT de tráfico; nunca propaga excepción; si la
      tabla no existe avisa UNA vez por proceso. `expandir_comandos()` para
      dejar `comandos_enviados` legible.
- [x] `uplink_trafico_15m/config/settings.py` + `.env.example`: flag
      `LOG_API_TELNET` (default `false` → la integración nace inerte hasta que
      el responsable corra `sql/01_tablas_api.sql` y active la variable).
- [x] Cableado en los 3 procesos de `uplink_trafico_15m` (MA5600T, MA5800-X15,
      "otros"): una llamada a `registrar_log_telnet()` tras la sesión telnet,
      con `time.monotonic()` alrededor para la duración. Cambio mínimo, sin
      tocar la lógica de paridad-PHP ni los INSERT de tráfico.
- [x] `api_olt_consultas/docs/INTEGRACION_CRONS.md` (nuevo): contrato de
      columnas, cómo se activa, retención, cómo lo adopta otro cron, y el
      diseño del nivel B con sus prerequisitos (API key de cron, usuario
      `geretapi`, paridad de parsers F9).
- [x] `utils/func.py`: `meta.motivo_log` de F5 actualizado (ya no dice
      "pendiente F7"; apunta a `docs/INTEGRACION_CRONS.md`).
- [x] `saludRouter` → `fase: F7`.

**Verificado (sin BD real — `FakeEngine` que captura el INSERT):**
- `uplink_trafico_15m/tests/test_log_api.py` (5/5): flag OFF no toca la BD;
  flag ON inserta con los campos correctos (`origen`/`consulta_codigo`/binds);
  un fallo del INSERT no propaga y avisa una vez; `exito=False`→0 y `error`
  truncado a 255; `expandir_comandos`.
- Suite de `uplink_trafico_15m` completa: **48/48** (los 3 workers importan y
  sus tests siguen verdes tras el cableado).
- `api_olt_consultas`: **101/101** (F7 no cambió código ejecutable del API,
  solo el texto de `motivo_log` y docs).

**Pendiente para el responsable:** 1 entrada nueva en
`SOLICITUDES_PENDIENTES.txt` (activar `LOG_API_TELNET=true` en el `.env` de
`uplink_trafico_15m` una vez creadas las tablas `OLT_API_*`; opcionalmente
adoptar el mismo `log_api.py` en los demás crons OLT).

---

### F8 — completada

**Hardening (código):**
- [x] `main.py` `middleware_hardening` (más externo que el de auditoría):
      `X-Request-ID` (propio o el que traiga el cliente) volcado al contexto de
      loguru; límite de tamaño del body (`MAX_BODY_BYTES`, 64 KiB → `413`);
      cabeceras `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`,
      `Referrer-Policy: no-referrer`, `Cache-Control: no-store`,
      `Content-Security-Policy: default-src 'none'`.
- [x] `main.py` `exception_handler(Exception)` global: `500` con
      `{ok:false, error, request_id}` — la traza va a `logs/api.log`, nunca al
      cliente. El middleware de auditoría ahora envuelve `call_next` y registra
      `RECHAZADO/ERROR` si un endpoint revienta.
- [x] `utils/ejecutor.py` `_recortar_log()`: tope `MAX_LOG_CRUDO_BYTES` (1 MB)
      al texto crudo que se **persiste** en `OLT_API_LOG_TELNET` (el que se
      devuelve en la misma respuesta no se recorta).
- [x] `app/config/settings.py` + `.env.example`: `MAX_BODY_BYTES`,
      `MAX_LOG_CRUDO_BYTES`.
- [x] Revisión OWASP API Security Top-10 (2023) documentada punto por punto en
      `docs/MANUAL_DESARROLLADOR.md` §4, junto con el detalle de inyección
      (SQL / comandos / ReDoS) y los límites conocidos del diseño.
- [x] `/docs`, `/redoc`, `/openapi.json` ya estaban gated por
      `API_DOCS_HABILITADO` (default `false`) desde F0 — se confirma.

**Manuales y diagramas:**
- [x] `docs/MANUAL_USUARIO.md`: obtener/usar la key, scopes, contrato de
      respuesta, los 20 `GET` con su tabla de origen y si soportan `?desde&hasta`,
      `/ejecutar/{consulta,comandos,lote}` con `curl`, jobs, `/admin/*`, tabla
      completa de códigos de error, límites/cuotas, FAQ.
- [x] `docs/MANUAL_DESARROLLADOR.md`: puesta en marcha, cómo correr los tests y
      el fake CLI, mapa de la estructura, flujo request→router→model/ejecutor→
      telnet (lectura y ejecución), cómo agregar consulta/endpoint/parser/
      comando aprobado/router/`fuente_lista`, revisión OWASP, **runbook de
      errores** (14 síntomas → causa → dónde mirar → solución), resumen de
      despliegue.
- [x] `docs/DIAGRAMAS.md` y `docs/CASOS_DE_USO.md`: encabezados actualizados
      (ya no son "versión de diseño F0"; reflejan F1–F7 implementado). Nota de
      la cadena de middlewares agregada a DIAGRAMAS.

**Verificado:**
- `tests/test_hardening.py` (6/6): `/health` con las 6 cabeceras + `X-Request-ID`;
  body sobre `MAX_BODY_BYTES` → `413`; `X-Request-ID` entrante se respeta;
  `_recortar_log` trunca con marcador; el handler global no filtra el mensaje
  de la excepción; el middleware de auditoría registra un `500`.
- Se conduce la app ASGI a mano (no hay `httpx`/`TestClient` en el proyecto).
- Suite completa (F2–F8): **107/107 tests.**
- `app.openapi()` sigue en 34 rutas; brute-force/rate-limit/fuzz de comandos ya
  cubiertos por los tests de F2 (referenciados en el manual).

**Pendiente para el responsable:** nada nuevo. F8 no agrega dependencias
externas ni tablas. El riesgo aceptado "sin TLS" sigue mitigado como en el
diseño (`ips_permitidas` obligatorio + firewall); la mejora futura "TLS vía
nginx" está descrita y solo cambia el bind a `127.0.0.1`.

---

### F9 — en curso (dividida: agente vs responsable)

F9 **la ejecuta el responsable** (requiere BD Aden real, telnet a las OLT y
`display users` en los equipos — fuera de lo que puede hacer el agente, ver
`README_LIMITES.md`). Lo que el agente dejó listo:

- [x] `docs/VALIDACION_F9.md`: checklist completo — prerrequisitos, OLT por
      modelo (con las excepciones DURZUA/LAFLORIDA/CONCEPCION-3), captura de
      transcripciones reales, comparación de paridad, prueba del límite de
      sesiones (5 concurrentes + `display users` + circuit-breaker), matriz de
      resultados de 20 consultas × 4 modelos, chequeos transversales
      (auditoría, jobs, `?desde&hasta`, deny-list, bloqueo de IP) y criterio
      de cierre.
- [x] `scripts/f9_paridad.py`: dado `(codigo, OLT)` hace
      `POST /ejecutar/consulta` (dato del telnet en vivo) + el `GET` de lectura
      (dato que dejó el cron), los imprime lado a lado y resume diferencias
      (cantidades + sumas numéricas con tolerancia 5 %). `--guardar-transcripcion`
      vuelca el `log.crudo` a `tests/transcripciones/`. Solo HTTP, stdlib.
- [x] `scripts/f9_cupo.py`: dispara N `POST /ejecutar/consulta` simultáneos a
      la misma OLT, reporta códigos HTTP/tiempos, y a los 30 s consulta
      `/admin/sesiones` para detectar sesiones colgadas. Stdlib.
- [x] `tests/transcripciones/` + `tests/test_parsers_reales.py`: descubre
      automáticamente las capturas `<codigo>__<OLT>__<fecha>.txt` que genere
      F9 y corre el parser correspondiente (no revienta + extrae algo). Hoy
      pasa con 0 capturas; se vuelve significativo a medida que el responsable
      las agrega.
- [x] `tests/test_f9.py` (6): la lógica pura de `f9_paridad` (recolección de
      números, resumen comparativo, mapa `codigo → endpoint`).
- [x] `saludRouter` → `fase: F9`.

**Verificado:** suite completa **114/114** (F2–F8 + los 2 nuevos de F9).

**Pendiente para el responsable (F9 propiamente dicha):**
- Ejecutar `docs/VALIDACION_F9.md` de principio a fin y archivar la matriz.
- Con eso se cierran varias entradas ya abiertas en
  `SOLICITUDES_PENDIENTES.txt`: transcripciones reales de los 20 parsers,
  texto exacto del rechazo por límite de sesiones por modelo, y las 3
  `fuente_lista` de confianza media/baja.
- Recién con la matriz sin ❌ → tracker F9 → ✅ y pasar a F10.

---

## Context

Hoy ~15 familias de procesos PHP 8 (`php_8_0/`, en producción en `/var/www/procesos/php/`) se conectan
por telnet a las OLT Huawei (MA5800-X15, MA5600T, MA5603T, MA5680T), envían secuencias fijas de
comandos CLI, parsean la salida con offsets frágiles e insertan en tablas `OLT_*` de la BD `Aden`.
Cada familia repite su propio autómata telnet (20+ copias de `estado_equipo*` con credenciales en
claro), guarda el log crudo solo en `.log` por IP y no ofrece forma de consultar "qué comando se
lanzó y qué respondió el equipo".

Se quiere una **API REST** (Python 3.12, FastAPI, convención GERET `README_PYTHON_API.md`) que:
1. Exponga **un endpoint por consulta/dato** (alarmas, tráfico uplink, potencia óptica, FAN, VLAN,
   ONT, versión, temperatura/CPU, uptime, energía, tarjetas…) que devuelva **el dato + el log
   completo** del telnet — leyendo de BD (poblada por los crons ya migrados/por migrar), sin tocar la
   OLT en cada llamada.
2. Exponga **endpoints de ejecución** que sí hagan telnet en vivo: (a) lanzar la secuencia fija de una
   consulta (comandos almacenados en BD) y (b) ejecutar una lista de comandos con orden, devolviendo
   el log — con whitelist estricta, auditoría, rate-limit y protección anti fuerza bruta / inyección.
3. Centralice en BD el **catálogo de comandos fijos por consulta y modelo** (hoy hardcodeado y
   duplicado en cada PHP) y el **log crudo** de cada sesión telnet.

Fuentes analizadas: 112 `.php` + 9 `.sh` + `_LEEME_MIGRACION_PHP8.txt` de `php_8_0/`,
`BD/ESTRUCTURA_OLT.sql` (248 tablas), `expect_compat.php` (telnet puro por socket en PHP 8),
convenciones GERET (`README_PYTHON_API.md`, `README_LIBRERIAS.md`, `README_LIMITES.md`,
`README_DEPLOY.md`), y los proyectos ya hechos `python/uplink_trafico*` y `Desarrollo/bot_olt`.

## Decisiones confirmadas con el usuario

| Tema | Decisión |
|---|---|
| Autenticación | **API Keys por sistema** (tabla nueva `OLT_API_CLIENTES` con scopes). Sin login humano. |
| Comandos libres (`/ejecutar/comandos`) | **Lectura (`display …`) + lista blanca de configuración aprobada** en BD (`OLT_API_COMANDOS_APROBADOS`), por scope. **La lista blanca nace vacía**: solo `display` hasta que el responsable apruebe comandos vía SQL. |
| Sync vs async | **Híbrido**: 1 OLT y ≤ N comandos → respuesta sincrónica con timeout; varias OLT o consultas largas → `job_id` + `GET /jobs/{id}`. |
| Log crudo | **Persistir en BD** (`OLT_API_LOG_TELNET`) cada sesión en vivo; proponer que los crons Python ya migrados escriban ahí también. Retención por antigüedad. |
| Telnet | Cliente **asyncio puro por socket** (port del `expect_compat.php`: negociación IAC, `\r\n`, paginado `More`). No `pexpect` (POSIX-only, spawnea `telnet`, no escala en una API). |
| Parsers | **En código** (`package/parsers/`), no en BD (evita ReDoS y permite tests). En BD solo los **comandos**. |
| BD | Estrictamente `README_LIMITES.md`: la API/agente **no ejecuta DDL**; todo CREATE/INSERT de catálogo va en `sql/*.sql` para que lo corra el responsable. Queries solo parametrizadas. |
| Usuario telnet de la API | **Dedicado: `geretapi`** (cupo propio de 3 sesiones/OLT, independiente de los crons). Su creación en todas las OLT la hace un administrador de red → `SOLICITUDES_PENDIENTES.txt`. |
| Cupo de sesiones de la API por OLT | **3 (todo el cupo) solo cuando el usuario es el dedicado**; si `.env` no declara el usuario dedicado, la API comparte `geret2016/geretproceso/geretont` y el gobernador **baja automáticamente a 1** por `(OLT, usuario)`. |
| Documentación | **Markdown + Mermaid en `docs/` del repo** (diagramas, casos de uso, manual de usuario y de desarrollador). |

## Análisis consolidado: catálogo de consultas telnet (lo que hoy hacen los PHP)

Contextos CLI: `user >` → `enable` → `#` → `config` → `(config)#` → `interface <tipo> 0/S` → `(config-if)#`.
Prompts transversales: `User name:`, `User password:`, `---- More ( Press 'Q' to break ) ----`→espacio,
`{ <cr>||<K> }:` / `{ <cr>|detail<K>|list<K> }:` / `{ lock<K>|unlock<K> }:` /
`{ <cr>|backplane<K>|frameid/slotid<S><Length 1-15> }:` → Enter (regex genérico `\{ .*\}:`),
`Are you sure to log out? (y/n)[n]:` → `y`, basura ANSI `[37D`/`\x1b[..D` a limpiar.
Excepciones de equipo: `OLT-DURZUA-4` (10.99.24.68, prompts exactos), MA5800-X15 requiere `\n` extra
tras algunos `display`, `OLT-LAFLORIDA-1`, `OLT-CONCEPCION-1/3`.
Credenciales telnet (3 usuarios, hoy en claro en el código): `geret2016` (mayoría), `geretproceso`
(alarmas), `geretont` (ONT V2+). → `.env` como perfiles de credencial.

| # | Consulta (código propuesto) | Comandos fijos (contexto) | Variante por modelo/server | Tabla dato (lectura API) | Timeout PHP |
|---|---|---|---|---|---|
| 1 | `uplink_trafico` | `interface {eth\|giu\|scu\|mpu} 0/S` → `display port traffic P` | slots por modelo/IP (ya en `uplink_trafico_15m/config/settings.py`) | `OLT_TRAFICO_UPLINK_MA5800_X15`, `_MA5600T`, `OLT_TRAFICO_UPLINK_HORA` | 2 s |
| 2 | `alarmas_activas` | `display alarm active all` (config) | DURZUA: prompts exactos, comando ×2 | `OLT_ALARMAS`, `OLT_CANTIDAD_ALARMAS` | 2 s |
| 3 | `alarmas_detalle` | `display alarm active all` (config), clasifica CRITICAL/MAJOR/MINOR/WARNING | idem | `OLT_ALARMAS_{CRITICAL,MAJOR,MINOR,WARNING}`, `OLT_CANTIDAD_ALARMAS_DETALLE` | 2 s |
| 4 | `alarmas_critical_los` | `display alarm active alarmlevel critical` + `display alarm history alarmlevel cleared alarmclass recovery detail` (X15) / `… alarmclass recovery alarmlevel critical detail` (resto) | por modelo | `OLT_ALARMA_CRITICAL_LOS`(+`_HISTORICO`) | 2 s |
| 5 | `alarmas_los_ont` | `display alarm active alarmlevel major list` | — | `OLT_ALARMA_LOS_ONT`(+`_HISTORICO`) | 2 s |
| 6 | `potencia_optica_uplink` | `interface X 0/S` → `display port ddm-info P` (por puerto de `OLT_PUERTAS_UPLINKS_GB`) | 5 juegos de slots por modelo + CONCEPCION-3 | `OLT_POTENCIA_OPTICA_UPLINK` | 2–60 s |
| 7 | `tarjetas` | `display power 0` + `display board 0` (config) | DURZUA | `OLT_VOLTAJE_TARJETA`, `OLT_TARJETA_CANTIDAD`, `OLT_DETALLE_TARJETA` | 2 s |
| 8 | `fan` | `display emu 0` (config) | — | `OLT_FAN_ESTADO` | 1 s |
| 9 | `vlan_cantidad` | `display port vlan <puerta>` (config; 1 sesión por puerta) | DURZUA | `OLT_CANTIDAD_VLAN` | 2 s |
| 10 | `vlan_trafico` | `display traffic vlan <vlan>` ×N (config) | — | `OLT_VLAN_TRAFICO`(`_YYYY`) | 2 s |
| 11 | `vlan_servicios` | `display vlan all` (config) | — | `OLT_VLAN_SERVICIO_CANTIDAD` | 2 s |
| 12 | `energia_alarma` | X15: `display alarm history alarmlevel minor detail` + `… cleared detail`; resto: `display alarm active alarmlevel major detail` + `display alarm history alarmlevel major detail` | por modelo; slots filtro por modelo | `OLT_ALARMA_ENERGIA` | 2 s |
| 13 | `energia_estado` | `display board 0/S` ×2 **en vista `user >` (sin enable)** | slots por modelo; X15 `\n` extra | `OLT_ESTADO_ENERGIA` | 2 s |
| 14 | `pon_trafico` | `interface gpon 0/S` → `display port traffic 0..15` (config-if) | slots PON desde `OLT_VOLTAJE_TARJETA` | `OLT_TRAFICOGPON_HORA` | 120 s |
| 15 | `temperatura_cpu` | `display temperature 0/S` ×2 + `display cpu 0/S` ×2 (config) | slots por modelo; LAFLORIDA 0/9-0/10; X15 `\n` extra | `OLT_TEMP_CPU` | 2 s |
| 16 | `uptime_gpon` | por slot 0..16: `interface gpon 0/S` → `display port state 0` | DURZUA | `OLT_UPTIME`(+`_HISTORICO`) | 2 s |
| 17 | `uplink_state` (**desactivado 24-06-2025**) | `interface X 0/S` → `display port state all` + `display port ddm-info 0/1` | por modelo | `OLT_UPLINKS_STATE` | 30 s |
| 18 | `ont_detalle` (V4 actual) | `display ont version 0 all` (config) + por slot PON: `interface gpon 0/S` → `display ont info summary P` / `display ont optical-info P all` / `display ont profile P all` (P 0..15 X15, 0..7 resto) | cred `geretont` | `OLT_INFORMACION_ONT_DETALLE_COMPLETO` | 2 s |
| 19 | `ont_reporte` | `display ont version 0 all` + `display ont info 0 all` (config) | DURZUA | `OLT_ONT_DETALLE_2`, `OLT_ONT_EQUIPOS3` | 2 s |
| 20 | `version` | `display version` (config) | prompt `{ <cr>|backplane<K>|… }:` | `OLT_VERSION_PARCHE_MODELO` | 2 s |
| — | Monitoreo (sin telnet) | — | — | `MONITOREO_PROCESO`, `MONITOREO_PROCESOS_EJECUCIONES` (padre/hijo `lote_id`), `_ESTADO`, `MONITOREO_CRON_JOBS` → se reutiliza el patrón para jobs de la API | — |

Bugs/rarezas relevantes para la API (no replicar): offsets fijos (`$data[$j+7]`, `+6`, `+10`), variables
sin reinicializar entre OLT (arrastran datos), `ArgumentCountError` latentes (ONT V3/V4,
uplinks_state X15), SQL por concatenación en todos, `count()` sobre int, 17 sesiones telnet por OLT en
uptime. La API usará **parsers con regex anclados + tests con transcripciones reales**, y devolverá
siempre el log crudo para que el consumidor pueda auditar.

## Arquitectura (convención GERET `README_PYTHON_API.md`, ampliada)

```
python/api_olt_consultas/                # en el servidor: /var/www/procesos/python_OLT/api_olt_consultas/
├── main.py                              # FastAPI, CORS, middlewares (auth, audit, rate-limit), include routers
├── .env / .env.example / .gitignore / requirements.txt
├── .venv/
├── app/config/db.py                     # engine SQLAlchemy (mysql+pymysql) desde .env (patrón bot_olt CONECTION_ADEN)
├── router/
│   ├── salud/saludRouter.py             # GET /health
│   ├── olt/oltRouter.py                 # GET /olt, /olt/{server}
│   ├── alarmas/alarmasRouter.py         # GET /alarmas/activas|detalle|critical-los|los-ont/{server}
│   ├── uplink/uplinkRouter.py           # GET /uplink/trafico|potencia-optica|state/{server}
│   ├── equipo/equipoRouter.py           # GET /tarjetas|fan|energia/alarma|energia/estado|temperatura-cpu|version|uptime/{server}
│   ├── vlan/vlanRouter.py               # GET /vlan/cantidad|trafico|servicios/{server}
│   ├── pon/ponRouter.py                 # GET /pon/trafico/{server}
│   ├── ont/ontRouter.py                 # GET /ont/detalle|reporte/{server}
│   ├── ejecutar/ejecutarRouter.py       # POST /ejecutar/consulta/{codigo}, /ejecutar/comandos, /ejecutar/lote
│   ├── jobs/jobsRouter.py               # GET /jobs/{uuid}, GET /jobs
│   ├── logs/logsRouter.py               # GET /logs/telnet?server&consulta&desde&hasta
│   └── admin/adminRouter.py             # GET /admin/consultas, /admin/auditoria, /admin/clientes/me  (scope admin)
├── model/<modulo>/m_<modulo>_model.py   # SOLO SQL parametrizado (text() + binds), uno por módulo
├── schema/                              # Pydantic: EjecutarComandosRequest, RespuestaDato, RespuestaEjecucion, Job…
├── utils/
│   ├── token.py                         # verify_api_key (Depends) → cliente, scopes; bloqueo por intentos
│   ├── seguridad.py                     # hash/verify API key (PBKDF2-SHA256 + compare_digest, igual que bot_olt)
│   ├── ratelimit.py                     # ventana deslizante por cliente (en memoria + persistencia BD)
│   ├── validador_comandos.py            # whitelist display / lista aprobada / deny-list / sanitización
│   ├── telnet_olt.py                    # cliente asyncio puro (IAC, prompts, More, contextos, timeouts, ANSI strip, logout garantizado)
│   ├── sesiones_telnet.py               # GOBERNADOR de sesiones: cupo por (OLT, usuario telnet), cola, circuit-breaker, registro en BD
│   ├── ejecutor.py                      # resuelve consulta→comandos (BD), expande slots/puertos, pide sesión al gobernador, corre telnet, persiste log
│   ├── jobs.py                          # cola asyncio + estado en OLT_API_JOBS
│   └── func.py                          # respuesta estándar {ok, dato, log, meta}, errores
├── package/parsers/<consulta>.py        # 1 parser por consulta (port de las regex PHP), puros y testeables
├── sql/                                 # DDL + seeds documentados para el RESPONSABLE (el agente NO los ejecuta)
│   ├── 01_tablas_api.sql
│   ├── 02_seed_consultas_comandos.sql   # catálogo de la tabla de arriba (20 consultas × modelo)
│   └── 03_purga_logs.sql
├── scripts/crear_cliente.py             # genera API key (se muestra una sola vez) e imprime el INSERT para el responsable
├── tests/                               # fake_olt_cli asyncio (reusa la idea de uplink_trafico_15m/tests/fake_olt_cli.py; simula el límite de 3 sesiones), parsers con transcripciones, validador, auth, gobernador
├── docs/
│   ├── DIAGRAMAS.md                     # Mermaid: arquitectura, componentes, ER, secuencias (auth, lectura, ejecución sync/async, sesiones telnet), estados de job
│   ├── CASOS_DE_USO.md                  # por actor: Portal PHP, crons, administrador, bot (futuro); flujo normal + alternos + errores
│   ├── MANUAL_USUARIO.md                # obtener/renovar API key, autenticación, catálogo de endpoints con ejemplos curl, códigos de error, límites, jobs
│   └── MANUAL_DESARROLLADOR.md          # estructura, cómo agregar consulta/endpoint/parser/comando aprobado, tests, runbook de errores (telnet, sesiones, BD, auth), logs
├── logs/
└── PLAN_API_OLT.md                      # este diseño por fases + tracker (mismo formato que PLAN_MIGRACION_15M.md)
```

Reutilizar: `uplink_trafico_15m/utils/parser_trafico.py` (`normalizar_lineas`, `quitar_espacios`,
offset +7), sus prompts y `tests/fake_olt_cli.py`; `bot_olt/utils/seguridad.py` (PBKDF2 +
`hmac.compare_digest`) y sus variables `.env` (`MAX_INTENTOS_LOGIN`, `BLOQUEO_LOGIN_MINUTOS`,
`RATE_LIMIT_PETICIONES`, `RATE_LIMIT_VENTANA`, `CONECTION_ADEN`); el modelo padre/hijo de
`MONITOREO_PROCESOS_EJECUCIONES` para jobs. Librerías solo del `README_LIBRERIAS.md`: `fastapi`,
`uvicorn`, `pydantic`, `SQLAlchemy`, `PyMySQL`, `python-dotenv`, `loguru` (sin dependencias nuevas
para telnet ni hashing: `asyncio` + `hashlib` stdlib).

## Modelo de datos nuevo (todo en `sql/01_tablas_api.sql`, ejecuta el responsable)

| Tabla | Propósito | Campos clave |
|---|---|---|
| `OLT_API_CLIENTES` | Consumidores (sistemas) | `id, nombre, prefijo_key(8), api_key_hash, scopes (CSV: lectura,ejecutar_consulta,ejecutar_comandos,config_aprobada,admin), ips_permitidas (CSV, NULL=todas), rate_limit_peticiones, rate_limit_ventana_seg, max_ejecuciones_hora, activo, fecha_alta, fecha_baja` |
| `OLT_API_INTENTOS_AUTH` | Anti fuerza bruta | `ip, prefijo_key, intentos_fallidos, bloqueado_hasta, ultima_fecha` (PK ip) |
| `OLT_API_AUDITORIA` | Todo request autenticado / rechazado (modelo `OLT_BOT_AUDITORIA`) | `fecha, cliente_id, ip_origen, accion (AUTH_OK\|AUTH_FAIL\|LECTURA\|EJECUCION\|RECHAZADO), endpoint, olt, parametro, resultado, duracion_ms` + índices fecha/cliente/accion |
| `OLT_API_CONSULTAS` | Catálogo de consultas | `codigo, descripcion, tabla_dato, timeout_seg, perfil_credencial (DEFAULT\|ALARMAS\|ONT), modo_sync_max_olts, activo` |
| `OLT_API_CONSULTA_COMANDOS` | Comandos fijos ordenados por consulta | `consulta_id, modelo (NULL=todos), server (NULL=todos; excepciones DURZUA/LAFLORIDA/CONCEPCION), orden, contexto (user\|enable\|config\|interface), interface_tipo, comando_template ('display port traffic {puerto}'), enter_extra, repetir_por (NULL\|slot\|puerto), fuente_lista (OLT_PUERTAS_UPLINKS_GB\|OLT_SERVER.pto1_12\|OLT_VOLTAJE_TARJETA\|fijo), lista_fija, activo` |
| `OLT_API_COMANDOS_APROBADOS` | Lista blanca de configuración (**nace vacía**) | `template (regex anclado con grupos tipados), descripcion, scope_requerido, activo` |
| `OLT_API_LOG_TELNET` | Log crudo por sesión | `fecha, olt, ip, consulta_codigo, origen (api\|cron), job_uuid, cliente_id, comandos_enviados TEXT, log_crudo LONGTEXT, duracion_ms, exito, error` + índice (olt, consulta_codigo, fecha) |
| `OLT_API_JOBS` | Ejecuciones asíncronas | `uuid, cliente_id, tipo, parametros JSON, estado (PENDIENTE\|RUNNING\|OK\|ERROR\|TIMEOUT), fecha_solicitud, fecha_inicio, fecha_fin, resultado LONGTEXT, error` |
| `OLT_API_SESIONES_TELNET` | Registro vivo de sesiones telnet abiertas por la API (observabilidad + recuperación tras caída) | `id, olt, ip, usuario_telnet, origen (api\|cron), job_uuid, cliente_id, fecha_apertura, ultimo_latido, estado (ABIERTA\|CERRADA\|HUERFANA), fecha_cierre, motivo_cierre` + índice (olt, usuario_telnet, estado) |

`sql/02_seed_consultas_comandos.sql`: 20 consultas + sus filas de comandos por modelo, extraídas del
catálogo de arriba (incluye excepciones DURZUA/LAFLORIDA/CONCEPCION y `enter_extra` para X15).
`sql/03_purga_logs.sql`: evento/cron para borrar `OLT_API_LOG_TELNET` > `LOG_RETENCION_DIAS`.

## Seguridad (respuesta explícita a inyección SQL y fuerza bruta)

- **API key**: 32 bytes `secrets.token_urlsafe`, se muestra una vez; en BD solo `prefijo_key` + hash
  PBKDF2-SHA256 (mismo `seguridad.py` del bot). Header `X-API-Key`. Comparación en tiempo constante.
- **Fuerza bruta**: por IP (y prefijo) — tras `MAX_INTENTOS_AUTH` fallos en `VENTANA`, bloqueo
  `BLOQUEO_MINUTOS` persistido en `OLT_API_INTENTOS_AUTH`; respuesta 401 genérica sin distinguir
  "key inexistente" de "key incorrecta"; retardo fijo en fallos; todo a `OLT_API_AUDITORIA`.
  `ips_permitidas` opcional por cliente. Rate-limit por cliente (ventana deslizante) → 429 con
  `Retry-After`; cuota `max_ejecuciones_hora` para telnet en vivo.
- **Inyección SQL**: 100 % `sqlalchemy.text()` con binds; nombres de tabla/columna jamás vienen del
  request (whitelist en código, como `_TABLAS_OCUPACION` del proyecto anterior); Pydantic tipa `server`
  (regex `^[A-Z0-9\-]+$`), `slot`/`puerto` (`int` con rangos), fechas.
- **Inyección de comandos telnet** (`/ejecutar/comandos`): Pydantic `constr(max_length=200)`; rechazo de
  `\r`, `\n`, `;`, `|`, `&`, caracteres de control y no-ASCII; máx 30 comandos y 5 OLT por request;
  cada comando debe cumplir **(a)** `^display\s[a-z0-9\s\-/]+$` **o (b)** matchear exactamente un
  template activo de `OLT_API_COMANDOS_APROBADOS` (scope `config_aprobada`); **deny-list absoluta**
  aunque pase lo anterior: `undo|reset|reboot|save|delete|erase|format|load|shutdown|activate|deactivate|
  ont add|ont delete|board|patch|upgrade|terminal|user|password|quit|return`. La **navegación**
  (`enable`, `config`, `interface X 0/S`, `quit`) la genera la API desde parámetros tipados (tipo ∈
  eth/giu/scu/mpu/gpon, slot 0–20, puerto 0–15): el cliente **nunca** envía comandos de navegación.
- **Protección de los equipos**: 1 sesión telnet concurrente por OLT (`asyncio.Lock` por IP) +
  semáforo global (`TELNET_MAX_CONCURRENTES`, p.ej. 20; las OLT Huawei limitan VTY); timeouts por
  consulta; logout limpio (`quit`+`y`) siempre en `finally`.
- **Secretos**: credenciales telnet solo en `.env` (`OLT_TELNET_DEFAULT_USER/PASS`, `…_ALARMAS_…`,
  `…_ONT_…`); el log crudo se **scrubbea** (nunca persiste la línea de password). `.env` `chmod 600`.
- **Transporte** (decisión del usuario: uvicorn expuesto directo, **sin nginx/TLS**, solo red interna):
  la API key viaja en claro por HTTP, así que se compensa con: `ips_permitidas` **obligatorio** para
  todo cliente (sin lista → 401), `uvicorn --host <IP interna del servidor>` (nunca `0.0.0.0` si hay
  interfaz pública), regla de firewall (`firewalld`/`iptables`) que limite el puerto a las IPs
  consumidoras, `allow_origins` explícitos desde `.env` (no `*`), y `/docs` deshabilitado en prod.
  Queda registrado como **riesgo aceptado** en `PLAN_API_OLT.md` con la mejora futura "TLS vía nginx"
  ya diseñada (solo cambia el bind a `127.0.0.1` y se antepone el proxy).
- **Observabilidad**: `loguru` a `logs/api.log` con rotación; request-id por petición; nada de datos
  sensibles en logs.

## Control de sesiones telnet — límite de 3 por OLT y usuario (requisito del usuario)

Hecho de red: cada OLT admite **máximo 3 sesiones telnet simultáneas por usuario**; la 4ª es
cerrada o **bloquea al usuario**. Una sesión solo se libera saliendo con `quit` (por niveles) y
cerrando el socket. Los crons PHP/Python usan los **mismos usuarios** (`geret2016`, `geretproceso`,
`geretont`) contra las mismas OLT, así que consumen ese cupo al mismo tiempo que la API.

Mecanismo (`utils/sesiones_telnet.py`, el "gobernador"):
- **Usuario telnet dedicado `geretapi`** (decisión confirmada): la API usa su propio usuario en todas
  las OLT, con cupo independiente de los crons. `.env`: `OLT_TELNET_API_USER=geretapi`,
  `OLT_TELNET_API_PASS=…`, `OLT_TELNET_API_DEDICADO=true`. Su alta en los equipos es tarea del
  administrador de red → entrada en `SOLICITUDES_PENDIENTES.txt` (bloquea el cupo 3, no el desarrollo).
- **Cupo por clave `(OLT, usuario_telnet)`**: `asyncio.Semaphore(cupo)` donde
  `cupo = 3` si `OLT_TELNET_API_DEDICADO=true`, y **`cupo = 1` en caso contrario** (la API comparte
  `geret2016/geretproceso/geretont` con los crons según `perfil_credencial` y deja 2 sesiones libres).
  Además `CUPO_API_POR_OLT_USUARIO` en `.env` puede bajar (nunca subir de 3) ese valor. Ninguna
  ejecución abre telnet sin adquirir el cupo; las demás **esperan en cola** (`ESPERA_MAX_SESION_SEG`,
  p.ej. 60 s; si expira → 503 `Retry-After` o el job queda `PENDIENTE`). El valor efectivo se expone en
  `/health` y en `/admin/sesiones` para verificarlo en producción.
- **Logout garantizado**: `telnet_olt.py` ejecuta en `finally` la secuencia `quit` (interface→config→
  enable→user) + `y` en la confirmación + cierre del socket, con un timeout propio; si el equipo no
  responde, se cierra el socket igual y la sesión se marca `HUERFANA` (la OLT la expira por su idle
  timeout; se registra para seguimiento).
- **Detección del rechazo**: patrones del CLI Huawei tipo `The number of users has reached the upper
  limit` / `Reenter times have reached the upper limit` / desconexión inmediata tras login →
  **circuit-breaker** por `(OLT, usuario)`: `BLOQUEO_OLT_SEG` (p.ej. 120 s) sin nuevos intentos, aviso
  en auditoría, reintento con backoff exponencial (1 reintento por defecto).
- **Registro en BD** (`OLT_API_SESIONES_TELNET`) con latido cada N s: permite ver sesiones abiertas
  desde `/admin/sesiones`, detectar huérfanas al reiniciar la API (se marcan `HUERFANA` y se respeta un
  periodo de gracia antes de volver a usar ese cupo) y auditar cuántas sesiones hubo por OLT/usuario.
- **Un solo proceso uvicorn** (sin `--workers`): los semáforos viven en memoria; escalar a varios
  procesos exigiría coordinar el cupo en BD (documentado como límite conocido en el manual de
  desarrollador).
- **Ejecuciones en lote**: el ejecutor recorre las OLT del lote respetando el cupo de cada una y el
  semáforo global; nunca abre 2 sesiones a la misma OLT con el mismo usuario dentro de un job.
- **Consultas que hoy abren muchas sesiones** (p.ej. `uptime_gpon`: 17 sesiones por OLT en el PHP,
  `vlan_cantidad`: 1 por puerta) se rediseñan a **1 sesión por OLT** recorriendo slots/puertas dentro
  de la misma sesión — menos presión sobre el cupo y sobre el equipo.
- **Tests**: `tests/fake_olt_cli` simula el límite (acepta 3 logins, rechaza el 4º) para probar cola,
  circuit-breaker y logout garantizado sin tocar equipos reales.

## Contrato de respuesta (uniforme)

```json
{ "ok": true, "consulta": "fan", "olt": {"server": "...", "ip": "...", "modelo": "..."},
  "fuente": "bd|telnet", "fecha_dato": "...", "dato": {...},
  "log": {"id": 123, "comandos": ["enable","config","display emu 0","quit"], "crudo": "...", "duracion_ms": 1830},
  "job": null }
```
Lecturas: `?con_log=true` adjunta el último `OLT_API_LOG_TELNET` de esa consulta/OLT; `?desde&hasta`.
Ejecuciones: si excede `modo_sync_max_olts`/timeout → `202 {job: {uuid, estado}}`.

## Fases (pausables, tracker en `PLAN_API_OLT.md` del proyecto)

- **F0 Andamiaje + diseño documentado**: estructura GERET, `.venv` 3.12, `requirements.txt` (solo
  aprobadas), `.env.example`, `main.py` + `/health`, `PLAN_API_OLT.md`, `SOLICITUDES_PENDIENTES.txt`,
  y **`docs/DIAGRAMAS.md` + `docs/CASOS_DE_USO.md`** (versión de diseño: arquitectura, ER, secuencias,
  estados de job/sesión, casos de uso por actor) para validar el diseño antes de codificar.
- **F1 BD**: `app/config/db.py`; `sql/01_tablas_api.sql`, `02_seed_consultas_comandos.sql` (catálogo
  completo desde este análisis), `03_purga_logs.sql`; `scripts/crear_cliente.py`. → entrega al
  responsable; el agente no ejecuta DDL.
- **F2 Seguridad**: `utils/seguridad.py`, `token.py` (`verify_api_key` + scopes + bloqueo),
  `ratelimit.py`, middleware de auditoría, `validador_comandos.py`; tests (fuerza bruta simulada,
  fuzz de comandos, deny-list, SQL binds).
- **F3 Telnet asyncio + gobernador de sesiones**: `utils/telnet_olt.py` (port de `expect_compat.php` +
  autómata de contextos de `uplink_trafico_15m`, logout garantizado en `finally`, detección de rechazo
  por límite), `utils/sesiones_telnet.py` (cupo por `(OLT, usuario)`, cola, circuit-breaker, latidos y
  registro en `OLT_API_SESIONES_TELNET`), scrub, persistencia en `OLT_API_LOG_TELNET`;
  `tests/fake_olt_cli` asyncio (login/enable/config/interface/More/logout, DURZUA, X15 `\n` extra,
  **límite de 3 sesiones**).
- **F4 Ejecutor + parsers**: `utils/ejecutor.py` (consulta→comandos desde BD, expansión de
  slots/puertos por `fuente_lista`, orden, timeouts), `package/parsers/*` para las 20 consultas
  (port de regex PHP con anclas), tests con transcripciones (sintéticas ahora, reales en F9).
- **F5 Routers de lectura**: 20 endpoints `GET` sobre tablas `OLT_*` existentes + `con_log`.
- **F6 Routers de ejecución + jobs**: `/ejecutar/consulta/{codigo}`, `/ejecutar/comandos`,
  `/ejecutar/lote`, `/jobs`, `/logs/telnet`, `/admin/*`; cola asyncio en proceso (un worker
  `BackgroundTasks`/tarea dedicada) con estado en `OLT_API_JOBS`.
- **F7 Integración crons (opcional, propuesta)**: que `uplink_trafico_15m` escriba `OLT_API_LOG_TELNET`
  (origen=`cron`) para que las lecturas tengan log; documentar cómo los demás crons podrían llamar
  `/ejecutar/consulta` en el futuro.
- **F8 Hardening + manuales**: revisión OWASP API Top-10, pruebas de fuerza bruta/rate-limit/ReDoS,
  límites de tamaño, cabeceras, OpenAPI (`/docs` protegido o deshabilitado en prod). Redacción de
  **`docs/MANUAL_USUARIO.md`** (API key, auth, todos los endpoints con `curl`, códigos de error y qué
  significan, límites/cuotas, jobs, FAQ) y **`docs/MANUAL_DESARROLLADOR.md`** (estructura, flujo
  request→router→model/ejecutor→telnet, cómo agregar una consulta/endpoint/parser/comando aprobado,
  cómo correr tests y el fake CLI, **runbook de errores**: telnet no conecta, límite de sesiones,
  sesión huérfana, prompt no reconocido, parser sin match, BD caída, key bloqueada — con dónde mirar
  en logs/auditoría y cómo resolver). Actualización final de `DIAGRAMAS.md` y `CASOS_DE_USO.md`.
- **F9 Validación en producción**: contra 1 OLT por modelo, paridad dato API vs tabla del cron, ajuste
  de prompts/offsets con transcripciones reales, DURZUA/LAFLORIDA/CONCEPCION.
- **F10 Despliegue** (`procesos-pgpon`, Python 3.12): unidad `systemd` que corre
  `uvicorn main:app --host <IP interna> --port 5001` (sin `--reload`), regla de firewall al puerto,
  `.env` (`chmod 600`), cron de `sql/03_purga_logs.sql`, alta de clientes con `scripts/crear_cliente.py`
  (Portal PHP y crons Python el día 1), actualizar `python/INVENTARIO_PROCESOS_OLT.xlsx` (hoja
  API/endpoints). Sigue `README_DEPLOY.md`: ningún paso lo ejecuta el agente sin autorización puntual.

## Verificación

- Dev: `pytest`/scripts en `tests/` (parsers, validador, auth, telnet contra fake CLI), `uvicorn main:app`
  + `/docs`, `curl` sin key → 401; con key → 200; 6 fallos → 429/bloqueo; comando `reboot` → 422.
- Prod (F9/F10): `/ejecutar/consulta/fan/{server}` vs fila de `OLT_FAN_ESTADO`; revisar
  `OLT_API_LOG_TELNET`, `OLT_API_AUDITORIA`, `OLT_API_JOBS`, `OLT_API_SESIONES_TELNET`; prueba de cupo:
  lanzar 5 ejecuciones simultáneas a la misma OLT mientras corre un cron → nunca más de 3 sesiones del
  mismo usuario en la OLT (`display users` en el equipo), las demás esperan/reciben 503, y al terminar
  no quedan sesiones colgadas.
- Documentación: los diagramas Mermaid renderizan en GitHub; una persona ajena al desarrollo puede
  obtener una key y consultar un endpoint siguiendo solo `MANUAL_USUARIO.md`; un desarrollador puede
  agregar una consulta nueva siguiendo solo `MANUAL_DESARROLLADOR.md`.

## Decisiones de despliegue y alcance inicial (confirmadas)

| Tema | Decisión |
|---|---|
| Servidor | `procesos-pgpon` — `/var/www/procesos/python_OLT/api_olt_consultas/`, Python 3.12 (ya presente). El `140` (Python 3.6) queda descartado. |
| Exposición | **uvicorn directo, sin nginx ni TLS**, puerto `5001`, solo red interna. Riesgo aceptado y mitigado (ver Seguridad → Transporte). |
| Nombre | `api_olt_consultas` |
| Clientes día 1 (`sql/02_seed…` vía `scripts/crear_cliente.py`) | **Portal web OLT (PHP)**: scopes `lectura, ejecutar_consulta`. **Crons Python (`uplink_trafico*`)**: scopes `lectura, ejecutar_consulta, log_cron`. Solo sistemas internos. |
| Clientes futuros (NO en el seed) | **`bot_olt` (Telegram)**: diseñado (scopes `lectura` y opcional `ejecutar_consulta`) pero se da de alta más adelante. "Otro sistema interno": sin nombre confirmado → entrada en `SOLICITUDES_PENDIENTES.txt`. Ninguna integración externa. |
| Pendientes externos al código (`SOLICITUDES_PENDIENTES.txt` desde F0) | (1) Crear usuario telnet `geretapi` en todas las OLT (admin de red) y entregar la clave para el `.env`. (2) Confirmar el texto exacto del rechazo por límite de sesiones en cada modelo (para el circuit-breaker; se obtiene en F9 abriendo una 4ª sesión manual). (3) Nombre del "otro sistema interno" consumidor, si existe. |
| Lista blanca de configuración | **Nace vacía**: día 1 solo `display …`; cada comando de configuración se aprueba con un `INSERT` revisado por el responsable (scope `config_aprobada`). |

## Al aprobar

Se ejecuta la **F0 (andamiaje + diseño documentado)**: crear `python/api_olt_consultas/` con la
estructura GERET, `.venv` 3.12, `requirements.txt` (librerías aprobadas), `.env.example`, `main.py` +
`/health`, `PLAN_API_OLT.md` (tracker por fases), `SOLICITUDES_PENDIENTES.txt` y la primera versión de
`docs/DIAGRAMAS.md` y `docs/CASOS_DE_USO.md`; smoke test de `uvicorn` local.
Se detiene para revisión antes de F1 (BD). Ninguna query se ejecuta contra `Aden`; los `.sql` se
entregan al responsable.
