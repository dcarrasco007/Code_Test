# Plan de Migración — Procesos Uplink Tráfico 15 min (telnet) PHP 5.3 → Python 3.12

> **Documento de diseño por fases** del proyecto `uplink_trafico_15m`. Migra los 3 procesos que
> corren en cron cada 15 minutos y recolectan tráfico de uplinks leyendo el **CLI de las OLT Huawei
> por telnet**. Cada fase es pausable: se puede completar, validar y retomar sin bloquear el reinicio.
>
> Estándar idéntico al proyecto `uplink_trafico` (diario): cabeceras por archivo, etiquetas
> greppables, rutas centralizadas en `config/settings.py`.

---

## 0. Cómo usar este documento (para la IA que continúe)

1. Lee **1. Contexto** y **2. Análisis del origen**.
2. Respeta **3. Decisiones** (confirmadas con el usuario).
3. Mira el **Tracker (4)** para saber en qué fase vamos.
4. Ejecuta **una fase a la vez**; al terminar marca sus casillas y actualiza el tracker.
5. Si falta una credencial/ruta/dato, **detente y pregunta** (sobre todo credenciales BD y telnet).

---

## 1. Contexto

3 crons cada 15 min recolectan el tráfico de uplinks de las OLT Huawei. Cada proceso hace:
`ping` → contar puertos uplink → **telnet** al equipo (login → enable → config →
`interface <tipo> 0/N` → `display port traffic X` → paginado `More` → logout) → parsear la salida →
INSERT del detalle por puerto + INSERT del peak → registrar en MONITOREO.

Los 3 comparten ~80% del código, por eso viven en un mismo proyecto con `utils/` compartido.

```
*/15 * * * * php -f Uplink/proceso_uplink_trafico_603_680.php   > log_Uplink_603_680.log
*/15 * * * * php -f Uplink/proceso_uplink_trafico_MA5600T.php   > ...
*/15 * * * * php -f Uplink/proceso_uplink_trafico.sh            (→ Procesos/proceso_uplink_trafico.php)
```

---

## 2. Análisis del origen (5 PHP = 3 entrypoints + 2 workers)

| # | Entrypoint | proceso_id | Filtro modelo | Worker | Puertos desde | → módulo Python |
|---|---|---|---|---|---|---|
| 1 | `proceso_uplink_trafico_603_680.php` (monolítico) | 5 | ≠ MA5800-X15 y ≠ MA5600T (MA5603T + OLT-CONCEPCION-1 / OLT-LASCONDES-1) | — | `OLT_PUERTAS_UPLINKS_GB` | `uplink_trafico_15m_otros` |
| 2 | `proceso_uplink_trafico_MA5600T.php` | 3 | = MA5600T | `..._MA5600T_exped.php` (id=4) | `OLT_PUERTAS_UPLINKS_GB` | `uplink_trafico_15m_ma5600t` |
| 3 | `proceso_uplink_trafico.sh` → `proceso_uplink_trafico.php` | 1 | = MA5800-X15 | `proceso_uplink_trafico_exped.php` (id=2) | `OLT_SERVER.pto1..pto12` | `uplink_trafico_15m_ma5800x15` |

### 2.1 Comando telnet por modelo/server (tabla de paridad CLAVE)
| Origen | Función PHP | `interface` | Slots |
|---|---|---|---|
| MA5800-X15 | estado_equipo | `eth 0/N` | 0/16, 0/17, 0/18 |
| MA5800-X15 (IPs especiales) | estado_equipo3 | `mpu 0/N` | 0/8, 0/9 |
| MA5600T (general) | estado_equipo | `giu 0/N` | 0/17, 0/18 |
| MA5600T (ALTOPENUELAS/CNT-2/VALPARAISO/VITACURA) | estado_equipo4 | `scu 0/N` | 0/7, 0/8 |
| MA5600T (LAFLORIDA) | estado_equipo3 | `giu 0/N` | 0/19, 0/20 |
| otros · CONCEPCION | estado_equipo | `scu 0/N` | 0/7, 0/8 |
| otros · MA5603T | estado_equipo4 | `giu 0/N` | 0/7, 0/8, 0/9 |
| otros · LASCONDES | estado_equipo3 | `giu 0/N` | 0/17, 0/18 |

### 2.2 Parseo de la salida telnet
- Se normaliza cada línea quitando espacios (`preg_replace('/\s+/','')`).
- Bajada: línea que contiene `Thereceivedtrafficofthisport(kbits/s)=` → valor tras el `=`.
- Subida: la línea **+7** respecto a la de bajada, valor tras el `=`. `# [PARIDAD-PHP]`
- Peak = suma de todos los tráficos de bajada de la OLT.

### 2.3 Tablas
| Tabla | Uso |
|---|---|
| `OLT_SERVER` (incl. `pto1..pto12`) | Lectura: catálogo + puertos MA5800-X15 |
| `OLT_PUERTAS_UPLINKS_GB` | Lectura: puertos uplink (MA5600T y otros) |
| `OLT_TRAFICO_UPLINK_MA5800_X15` | Escritura: detalle por puerto (MA5800-X15) |
| `OLT_TRAFICO_UPLINK_MA5600T` | Escritura: detalle por puerto (MA5600T y otros) |
| `OLT_TRAFICO_UPLINK_HORA` | Escritura: peak por OLT |
| `MONITOREO_PROCESOS_EJECUCIONES` | Escritura: control de ejecución (RUNNING→OK) |

### 2.4 Detalles de fidelidad / correcciones
- `ping_ip`: `ping -c 3`, se toma el % de "packet loss"; OLT procesable si `< 100`.
- Ventanas de `$hora` con offsets raros (`+9 hour`, `+4 hour +20 minute`, `-2 hour -30 minute`)
  para el campo `fecha`/`hora`: **replicar exactamente** por proceso. `# [PARIDAD-PHP]`
- Credenciales telnet hardcodeadas → van a `.env`.
- **Bugs a corregir** (marcar `# [FIX-PHP]`, documentar aquí):
  - `otros · estado_equipo3`: `for(... $x<$puerto08 ...)` debe ser `$puerto18`; hay un `break;`
    que deja código muerto tras él.
  - Variable `$b` (control de `enable`) usada sin inicializar.
  - Offset fijo `+7` para la subida: documentar y robustecer si la transcripción lo permite.

---

## 3. Decisiones (confirmadas)

| Tema | Decisión |
|---|---|
| Librería telnet | **pexpect** (spawn `telnet host` + `expect()`; fiel a `expect_popen`/`expect_expectl`) |
| Organización | **Proyecto nuevo** `python/uplink_trafico_15m/` con `utils/` compartido |
| Bugs del PHP | **Corregir los evidentes**, marcados `# [FIX-PHP]` y documentados |
| Pruebas telnet | **Solo en producción** (OLT inalcanzables desde dev). En dev se valida el parser con transcripciones reales |
| Credenciales telnet | `.env`: `OLT_TELNET_USER` / `OLT_TELNET_PASS` |
| Driver BD | SQLAlchemy + mysql-connector (igual que proyecto diario) |

---

## 4. Tracker de progreso

| Fase | Nombre | Estado |
|---|---|---|
| 0 | Andamiaje + entornos | ✅ Completada |
| 1 | Config + BD + MONITOREO | ✅ Completada |
| 2 | utils: ping + parser | ✅ Completada |
| 3 | utils: telnet (pexpect) | ✅ Completada |
| 4 | Capa Model (queries de los 3 procesos) | ✅ Completada |
| 5 | Proceso MA5800-X15 (orquestador + worker) | ✅ Completada |
| 6 | Proceso MA5600T (orquestador + worker) | ✅ Completada |
| 7 | Proceso "otros" / 603_680 (monolítico) | ✅ Completada |
| 8 | main.py + integración | ✅ Completada |
| 9 | Validación y paridad | ☐ Pendiente |
| 10 | Despliegue Linux (cron */15) + inventario | ☐ Pendiente |

Leyenda: ☐ Pendiente · 🔄 En curso · ✅ Completada

---

## 5. Arquitectura (según manual — tipo *Script* + `utils/` compartido)

```
python/uplink_trafico_15m/
├── app/db.py                       # engine SQLAlchemy desde .env
├── config/settings.py              # ⚙️ rutas, credenciales telnet, timeouts, listas de servers [RUTA][CONFIG]
├── env/                            # venv 3.12
├── utils/                          # ← núcleo compartido por los 3 procesos
│   ├── telnet_olt.py               # autómata pexpect parametrizable
│   ├── ping.py                     # ping_ip
│   ├── parser_trafico.py           # parseo "received traffic...=" + línea +7
│   └── monitoreo.py                # MONITOREO insert RUNNING / update OK
├── model/<proceso>/<proceso>_model.py   # queries por proceso
├── scripts/
│   ├── uplink_trafico_15m_ma5800x15/{orquestador.py, worker.py}
│   ├── uplink_trafico_15m_ma5600t/{orquestador.py, worker.py}
│   └── uplink_trafico_15m_otros/script.py     # monolítico (sin worker)
├── logs/<proceso>/…
├── .env / .env.example / .gitignore
├── main.py                         # registro con kind: orquestador_worker | script
├── requirements.txt                # SQLAlchemy, mysql-connector-python, python-dotenv, pexpect
└── PLAN_MIGRACION_15M.md
```

### 5.1 Estándar de comentarios (etiquetas greppables)
| Etiqueta | Para qué |
|---|---|
| `# [CONFIG]` | Valor ajustable (timeouts, listas de servers, IDs). |
| `# [RUTA]` | Rutas de archivos/logs/ejecutable. |
| `# [PROCESO]` | Invocación de subprocess / telnet. |
| `# [SQL]` | Query y tabla afectada. |
| `# [PARIDAD-PHP]` | Detalle que debe coincidir con el PHP. |
| `# [FIX-PHP]` | Corrección intencional de un bug del PHP original. |

Cabecera obligatoria en cada archivo (qué hace, equivalente PHP, qué etiqueta buscar).

### 5.2 Flujo de ejecución
```
cron */15 → main.py --proceso <nombre>
   ├─ ma5800x15 / ma5600t (orquestador): lista OLT → subprocess worker por OLT
   │      subprocess → main.py --proceso <nombre> --worker --server S --ip I
   └─ otros (script): recorre OLT y procesa cada una en el mismo proceso
```

---

## 6. Fases de implementación

### Fase 0 — Andamiaje + entornos ✅
- [x] Estructura de carpetas con nombres descriptivos (`_ma5800x15`, `_ma5600t`, `_otros`).
- [x] `venv` 3.12 + `requirements.txt` (SQLAlchemy 2.0.51, mysql-connector 9.7.0, python-dotenv, **pexpect 4.9.0**).
- [x] `.env.example` (BD + telnet), `.env` (a rellenar en prod), `.gitignore`.
- [x] Stubs con cabeceras (utils, model, scripts) y `main.py` con registro (kind orquestador_worker/script).
- [x] Smoke test del CLI (`--list`, despacho de los 3 procesos, rechazo de `--worker` en el monolítico).

### Fase 1 — Config + BD + MONITOREO ✅
- [x] `config/settings.py`: `BASE_DIR`, `LOGS_DIR` (+ por proceso), `PYTHON_BIN`, `MAIN_PY`,
      `TELNET_TIMEOUT`, credenciales telnet, `PROCESO_ID_*` (1..5), listas de servers/IPs
      especiales por proceso (IPs mpu MA5800-X15, servers scu MA5600T, LAFLORIDA, CONCEPCION,
      LASCONDES, MA5603T).
- [x] `app/db.py`: engine SQLAlchemy + `_validar_credenciales()` (mismo patrón del proyecto diario).
- [x] `.env` real creado (vacío, a rellenar en producción) + `.env.example` ya existente.
- [x] `utils/monitoreo.py`: `iniciar()` (INSERT RUNNING, patrón padre y patrón hijo con
      parent_id/lote_id) y `finalizar()` (UPDATE OK + duración, con/sin cantidad_subprocesos).

**Criterios verificados:**
- `config/settings.py` importa y expone los 9 IPs especiales, 4 servers SCU, IDs 1-5.
- `app/db.py` con `.env` vacío da el mismo mensaje claro de credenciales faltantes.
- `utils/monitoreo.py` importa; firmas: `iniciar(conn, proceso_id, mensaje, parent_id, lote_id)`,
  `finalizar(conn, registro_id, estado, cantidad_subprocesos)`.

### Fase 2 — utils: ping + parser ✅ (validable en dev)
- [x] `utils/ping.py`: `ping_ip(ip)` — comando `ping -c N` (Linux) / `-n N` (Windows dev),
      `_extraer_packet_loss()` replica el parseo PHP (`explode(',')` + buscar "packet loss" +
      `explode(' ')[0]`). Verificado con 4 muestras reales de formato `ping` Linux (0%, 100%,
      66%, sin coincidencia) — todas OK.
- [x] `utils/parser_trafico.py`: `parsear_trafico(texto)` — separa por `\r` (como
      `explode(chr(13),...)`), limpia `\n`/`\r`/`|` residual, quita todo espacio y busca
      `MARCADOR_BAJADA`; para la subida usa el offset fijo `+7` (`# [PARIDAD-PHP]`).
      Diseño: **no** asigna puerto/slot — devuelve una lista ordenada de tuplas
      `(bajada, subida)`; el etiquetado de a qué puerto corresponde cada una lo hace el
      worker (Fases 5/6/7), que es quien conoce el orden de los comandos telnet emitidos.
      Mejora sobre el PHP: si el buffer se corta antes del offset +7, `subida=None` en vez
      de indexar fuera de rango.
- [x] `tests/test_ping.py` (4/4 OK) y `tests/test_parser_trafico.py` (5/5 OK), ejecutables con
      `python -m tests.test_ping` / `python -m tests.test_parser_trafico`.

⚠️ **Pendiente antes de Fase 9:** `tests/test_parser_trafico.py` usa una transcripción
**sintética** (construida a partir de la lógica del PHP, no de una captura real). Falta
pedir al usuario 1-2 transcripciones reales de `display port traffic` para confirmar el
offset `+7` y el formato exacto contra el hardware Huawei real.

### Fase 3 — utils: telnet (pexpect) ✅
- [x] `utils/telnet_olt.py`: `leer_trafico_puertos(ip, comandos, timeout, comando_conexion)` —
      autómata pexpect: login → enable → config → por cada `(interface_cmd, n_puertos)`,
      para cada índice: `interface` + `display port traffic X` + blanco + `quit`, capturando
      el bloque completo (maneja paginado `More` y prompts de confirmación `{<cr>...}`/
      `{lock...}`) → logout final (`quit` ×2 + confirmación `y`).
- [x] `respuesta_valida(texto_crudo)` — equivalente a `verifica_equipo()` del PHP (reutiliza
      `normalizar_lineas`/`quitar_espacios` de `parser_trafico.py`, ahora públicas).
- [x] **Decisión de diseño documentada:** el autómata NO replica línea-a-línea el mecanismo
      "fire-and-forget + catch-all" del PHP (envía comandos sin leer, acumula vía patrón
      `.*\n` en sucesivas iteraciones) — en su lugar usa el modelo bloqueante natural de
      pexpect (enviar 4 comandos, esperar una vez el prompt de config), que produce el
      mismo resultado observable (mismos comandos a la OLT, mismo orden, mismo texto
      acumulado) de forma mucho más verificable. `# [PARIDAD-PHP]`
- [x] **`# [FIX-PHP]` aplicados:** (1) eliminado el `case` de patrón duplicado/inalcanzable
      del PHP (`SHELL_CONFIG` con la misma regex que `SHELL_CONFIG1`, nunca se ejecutaba);
      (2) escapado el `?` sin escapar en el patrón de confirmación de logout del PHP
      (regex más correcta, incidencia solo teórica).
- [x] **Multiplataforma para poder probar en dev:** `pexpect.spawn` (pty real) en Linux/Mac
      (producción, igual que `expect_popen`); `pexpect.popen_spawn.PopenSpawn` en Windows (dev).
- [x] `tests/fake_olt_cli.py` — CLI Huawei simulada (login/enable/config/ciclo de puerto/logout
      + modo `--con-error`) para ejercitar el autómata sin una OLT real.
- [x] `tests/test_telnet_olt.py` (4/4 OK): un puerto, dos puertos mismo slot, dos slots en
      orden, y detección de error de CLI vía `respuesta_valida()`.

**Criterios verificados:** el autómata completa el ciclo login→enable→config→puerto(s)→logout
contra el CLI simulado; `parsear_trafico()` sobre el texto capturado devuelve exactamente los
valores esperados; `respuesta_valida()` distingue sesión OK vs. con error.

⚠️ **Importante — sigue pendiente para Fase 9:** esto valida el FLUJO/CONTROL del autómata
contra un CLI simulado por mí, NO el comportamiento real de una OLT Huawei (prompts exactos,
paginación real, tiempos de respuesta). La validación real solo es posible en producción.

### Fase 4 — Capa Model ✅
- [x] **MA5800-X15**: `get_olts(conn, modelo)`, `get_puertos_pto1_12(conn, modelo, server)`
      (lee `pto1..pto12` de `OLT_SERVER`), `insert_detalle(...)` → `OLT_TRAFICO_UPLINK_MA5800_X15`
      (fecha=`NOW()`), `insert_hora(...)` → `OLT_TRAFICO_UPLINK_HORA` (fecha=`NOW()`).
- [x] **MA5600T**: `get_olts(conn, modelo)`, `get_puertos_gb(conn, modelo, server)` (JOIN
      `OLT_SERVER`+`OLT_PUERTAS_UPLINKS_GB`), `insert_detalle(...)` → `OLT_TRAFICO_UPLINK_MA5600T`
      (fecha=`NOW()`), `insert_hora(...)` → `OLT_TRAFICO_UPLINK_HORA` (fecha=`NOW()`; el worker
      también la usa para el marcador de fallo `modelo='MA5600T2', peak='0'`).
- [x] **otros**: `get_olts(conn)` (modelo ≠ MA5800-X15 y ≠ MA5600T, sin parámetro — filtro fijo),
      `get_puertos_gb(conn, server)`, `insert_detalle(...)` → `OLT_TRAFICO_UPLINK_MA5600T`
      (modelo = el real de la OLT, ej. `MA5603T`, no fijo), `insert_hora(...)` → **única función
      que recibe `fecha` como parámetro explícito** (el PHP usa `ahora + 9 horas` en vez de
      `NOW()` — asimetría real preservada, ver `# [PARIDAD-PHP]` en el código).
- [x] Todas parametrizadas con `text()`. `# [SQL]` en cada query.

**Criterio verificado:** los 3 módulos importan sin error; firmas confirmadas por inspección
(`inspect.signature`); `main.py --list` y la suite completa de tests (ping, parser, telnet)
siguen pasando tras el cambio.

### Fase 5 — Proceso MA5800-X15 ✅
- [x] `orquestador.py`: MONITOREO id=1 (padre), `get_olts('MA5800-X15')`, lanza un subprocess
      worker por OLT con `--server --ip --lote` (+ `--fecha` si se pasó), log por IP en
      `logs/uplink_trafico_15m_ma5800x15/log<ip>.log`, `finalizar()` con `cantidad_subprocesos`.
- [x] `worker.py`: MONITOREO id=2 (hijo, vinculado a `--lote`), `get_puertos_pto1_12()` →
      `_contar_puertos()` (clasifica pto1..12 por slot) → `_seleccionar_slots(ip)` (eth
      0/16-18, o mpu 0/8-9 si la IP está en `IPS_MA5800X15_MPU_8_9`) → hasta 3 intentos de
      `leer_trafico_puertos()` + `respuesta_valida()` → `parsear_trafico()` → `insert_detalle()`
      por puerto + `insert_hora()` con el peak acumulado.

**Simplificaciones de diseño respecto al PHP (documentadas en el código):**
- El orquestador **ya no pasa** `puerto16/17/18/08/09` ni `hora` por argv al worker — el PHP
  calculaba `$hora` (offset −2h30m) pero **nunca la usaba** (el insert usa `NOW()`), y el
  conteo de puertos es trivial de recalcular en el propio worker. `# [FIX-PHP]`: se elimina
  ese cálculo muerto y la duplicación de la lógica de conteo.
- Se omite la llamada a `ping_ip()` del PHP: su resultado nunca se usaba (el `if` que lo
  comprobaba estaba comentado en el código fuente), o sea que era una llamada sin efecto.
- Se agregó `--lote` (genérico, reutilizable por MA5600T en Fase 6) a `main.py` para vincular
  el registro MONITOREO del worker al de su padre — esto **sí** es dato real que el PHP usaba
  (`parent_id`/`lote_id`), a diferencia de lo anterior.

**Criterios verificados:**
- `tests/test_worker_ma5800x15.py` (6/6 OK): conteo de puertos por slot (eth y mpu), fila
  vacía, slots desconocidos ignorados, selección de slots por IP especial vs. normal.
- CLI dispatch: orquestador y worker llegan correctamente hasta la validación de credenciales
  BD (`.env` vacío → mensaje claro), igual que en fases anteriores.
- Los stubs de MA5600T y "otros" (Fases 6/7, aún no implementados) siguen respondiendo bien
  tras el cambio de firma (`--lote` añadido a `main.py`).
- Suite completa de tests (ping, parser, telnet_olt, worker_ma5800x15) sigue pasando.

⚠️ **Pendiente para Fase 9:** `procesar_olt()` completo (BD + telnet reales) no es testeable
en dev — la lógica pura (conteo/selección) sí quedó validada; el resto depende de producción.

### Fase 6 — Proceso MA5600T ✅
- [x] `orquestador.py`: MONITOREO id=3, `get_olts('MA5600T')`, lanza un worker por OLT con
      `--server --ip --lote`, log **por SERVER** (no por IP, a diferencia de MA5800-X15 —
      así lo hacía el PHP: `logs/MA5600T_15M/$server.log`). Sin simplificaciones: el PHP
      original ya delegaba todo el conteo de puertos al worker.
- [x] `worker.py`: MONITOREO id=4 (hijo). **Gate de ping real** (`_ping_ok`): si la OLT no
      responde o packet-loss≥100%, el proceso NO hace telnet ni inserts — solo cierra
      MONITOREO (a diferencia de MA5800-X15, donde el ping era código muerto). Luego:
      `get_puertos_gb()` → `_contar_puertos()` (con el "duplicado" condicional del PHP:
      si la primera fila de un slot tiene `parte2=='1'`, se cuenta 2 veces — aplica a
      17/18/7/8, no a 19/20; el slot `0/16` se omite por ser código muerto en el PHP
      original) → `_ejecutar_con_reintentos()`, árbol de decisión por grupo de server:

      | Grupo | 1er intento | Si falla |
      |---|---|---|
      | `OLT-LAFLORIDA-1` | giu 0/19-20 | hasta 3 reintentos con giu 0/17-18 |
      | scu (`SERVERS_MA5600T_SCU_7_8`, incl. VITACURA) | scu 0/7-8 | **si está en `SERVERS_MA5600T_REINTENTO_SCU_7_8` (sin VITACURA)**: 1 reintento con scu 0/7-8; si no: hasta 3 reintentos con giu 0/17-18 |
      | resto | giu 0/17-18 | hasta 3 reintentos con giu 0/17-18 |

      Si el fallo es definitivo: inserta marcador `modelo='MA5600T2', peak=0` **y además**
      sigue con el insert normal (`modelo='MA5600T', peak=0`, ya que el texto queda vacío)
      — dos filas en `OLT_TRAFICO_UPLINK_HORA`, tal cual el PHP. Luego parseo + inserción
      de detalle en orden `17,18,19,20,7,8` + peak acumulado.

**Simplificaciones/decisiones documentadas (`[PARIDAD-PHP]`/`[FIX-PHP]`):**
- `estado_equipo2` (variante del PHP con una línea en blanco extra antes del primer
  `interface giu 0/17`) se trata como equivalente a `estado_equipo` — diferencia cosmética,
  no replicada; los 2 reintentos que en el PHP alternaban entre ambas variantes se colapsan
  en 3 llamadas idénticas a `giu 0/17-18`.
- Se omite el cálculo de `$hora` (+4h+20min) del PHP: nunca se usaba (el insert real usa
  `NOW()`), igual que en Fase 5.
- Se omiten los `sleep()` de pacing del PHP (no afectan corrección de datos).

⚠️ **Discrepancia del PHP detectada y NO corregida (marcada para decisión del usuario):**
la lista de reintento `SERVERS_MA5600T_REINTENTO_SCU_7_8` **no incluye `OLT-VITACURA-1`**,
aunque ese server sí participa del primer intento con `scu 0/7-8`. Si su primer intento
falla, VITACURA cae al grupo de reintento "general" (`giu 0/17-18`) en vez de reintentar
con `scu 0/7-8` de nuevo. Podría ser un descuido del autor original del PHP — se replicó
tal cual (sin "corregir" una posible intención de negocio que no puedo confirmar).

**Criterios verificados:**
- `tests/test_worker_ma5600t.py` (13/13 OK): conteo de puertos (con y sin duplicado, slot
  16 ignorado), gate de ping (0%/100%/vacío), y las 6 combinaciones del árbol de reintentos
  (LAFLORIDA éxito/fallo, ALTOPENUELAS un solo reintento, VITACURA cae a giu, éxito en
  reintento intermedio detiene el ciclo) — todo mockeando `leer_trafico_puertos`/
  `respuesta_valida` (sin telnet real).
- CLI dispatch: orquestador y worker llegan hasta la validación de credenciales BD.
- Suite completa (32 tests: ping, parser, telnet_olt, worker_ma5800x15, worker_ma5600t)
  sigue pasando.

### Fase 7 — Proceso "otros" (603_680, monolítico) ✅
- [x] `script.py`: MONITOREO id=5 (sin hijos, es monolítico). `get_olts()` (≠MA5800-X15,
      ≠MA5600T) → por cada OLT: gate de ping real (compartido con MA5600T vía nueva
      `utils.ping.es_alcanzable()`) → `get_puertos_gb()` → `_contar_puertos()` (con
      "duplicado" condicional en 17/18/7/8/9) → `_comandos_dispatch()` (MA5603T → giu
      7/8/9; `OLT-CONCEPCION-1` → scu 7/8; `OLT-LASCONDES-1` → giu 17/18; sin regla →
      `None`) → `_ejecutar_con_reintento()` (1 solo reintento, más simple que MA5600T) →
      parseo + inserción de detalle (orden 17,18,7,8,9) + peak. `$semana` y `$hora`
      (ahora+9h) se calculan **una sola vez** para todo el run, no por OLT.

**`# [FIX-PHP]` aplicado (el bug que motivó esta fase):** en `estado_equipo3` (usada para
`OLT-LASCONDES-1`), el PHP original tenía `for($x=0;$x<$puerto08;$x++)` en el bucle de
`puerto18` — un límite equivocado (`$puerto08` en vez de `$puerto18`) seguido de un
`break` que dejaba código muerto. Corregido: `_comandos_dispatch` usa `conteo["0/18"]`
como límite correcto. Verificado explícitamente en el test
`test_dispatch_lascondes_usa_giu_17_18_con_limite_correcto` (conteo 0/18=2 → genera
`("interface giu 0/18", 2)`, no el valor de 0/8).

**Otras decisiones documentadas:**
- Slots `0/16`, `0/19`, `0/20` se cuentan en el PHP pero ninguna de las 3 funciones de
  dispatch de este proceso los usa jamás — dead code, no replicado (mismo criterio usado
  para `0/16` en Fases 5 y 6).
- Sin regla de dispatch conocida (modelo/server no coincide con ninguna de las 3): el PHP
  no hace telnet pero **sí** inserta `peak=0` al final — replicado tal cual.
- Caso de fallo definitivo: a diferencia de MA5600T, **no hay marcador de fallo** — el PHP
  simplemente sigue parseando lo que haya en `$texto` (que, inválido, no producirá detalle).
- Se extrajo `es_alcanzable()` a `utils/ping.py` (compartida con MA5600T) para no duplicar
  la lógica de "¿packet-loss < 100%?" una tercera vez — refactor sin romper los tests
  existentes de MA5600T.

**Criterios verificados:**
- `tests/test_script_otros.py` (11/11 OK): conteo de puertos (con duplicado, slots inertes
  ignorados), dispatch por modelo/server (incl. prioridad MA5603T sobre server, y el fix
  de LASCONDES), reintento único (éxito directo / fallo+reintento fallido / éxito en
  reintento) — todo mockeado, sin telnet real.
- CLI: `--proceso uplink_trafico_15m_otros` llega hasta la validación de credenciales BD;
  sigue rechazando `--worker` (monolítico).
- Suite completa (43 tests) sigue pasando tras el refactor de `es_alcanzable`.

### Fase 8 — main.py + integración ✅
- [x] Verificado el registro completo (3 procesos), validaciones CLI y logging.

**Bug de integración encontrado y corregido:** el `except Exception` del loop principal
de `main.py` NO capturaba `SystemExit` (usado por `app/db.py._validar_credenciales()` para
salir limpiamente con mensaje claro cuando falta el `.env`). Como `SystemExit` hereda de
`BaseException`, no de `Exception`, el modo **"todos"** (sin `--proceso`) se abortaba
completo apenas fallaba el PRIMER proceso, sin llegar a intentar los otros dos. Corregido
ampliando a `except (Exception, SystemExit)`. Sin efecto en el uso real en producción (un
solo `--proceso` por cron): el exit code final seguía siendo `!=0` en ambos casos; el fix
solo restaura el aislamiento de fallos por-target en el modo de conveniencia "todos".

**Documentación agregada (sin cambiar comportamiento):**
- `--fecha` es un parámetro **vestigial en los 3 procesos** (ninguno lo usa: todos operan
  sobre "ahora" — ping/telnet/`NOW()` — igual que el PHP original, que tampoco aceptaba
  override de fecha). Se documentó explícitamente en el help de `main.py` y en cada
  `run()`/`procesar_olt()` que lo recibe, para que no confunda a un futuro mantenedor.
  Se decidió **no eliminarlo** (bajo riesgo de tocar 6 archivos sin beneficio real; se
  mantiene por consistencia de forma con el proyecto `uplink_trafico` diario).
- Aclarado en la cabecera de `main.py` que el modo **"todos"** es una conveniencia para
  pruebas manuales — en producción (Fase 10) cada proceso va en su propia entrada de cron
  con `--proceso` explícito, igual que los 3 archivos PHP originales.

**Criterios verificados:**
- `--help`, `--list` legibles y completos.
- Modo "todos" ahora sí itera los 3 procesos en secuencia tras el fix (confirmado:
  ma5800x15 → ma5600t → otros, cada uno logueado individualmente, exit code final = 1).
- Despacho individual de cada proceso sigue funcionando igual que en fases anteriores.
- Las 4 validaciones de CLI (proceso inválido, `--worker` sin `--proceso`, `--worker` sin
  `--server`/`--ip`, `--worker` sobre el proceso monolítico) siguen respondiendo correcto.
- Suite completa (43 tests) sigue pasando sin regresiones.

### Fase 9 — Validación y paridad

> Todo lo de esta fase requiere **acceso al servidor Linux de producción** (BD real y OLT
> reales por telnet). No es ejecutable desde el entorno de desarrollo Windows. Ir marcando
> cada punto según se vaya validando; si algo falla, **no seguir** a Fase 10 sin resolverlo.

#### 9.1 Prerrequisitos de acceso
- [ ] Obtener credenciales reales de BD (`/u01/crontab127/conexion/conexion_db.php`) y
      completarlas en `.env` (`DB_HOST`, `DB_USER`, `DB_PASS`).
- [ ] Obtener credenciales telnet reales (`geret2016` / la clave real vigente) y completarlas
      en `.env` (`OLT_TELNET_USER`, `OLT_TELNET_PASS`) — **no** las que están hardcodeadas en
      el PHP si ya rotaron.
- [ ] Confirmar que el usuario que ejecutará los procesos tiene permiso de `telnet` saliente
      hacia la red de OLT (10.99.x.x) desde el servidor Linux.
- [ ] Copiar (o clonar) `python/uplink_trafico_15m/` al servidor, crear `env/` allí
      (`python3.12 -m venv env`) e instalar `requirements.txt`.
- [ ] Confirmar que el binario `telnet` está instalado en el servidor Linux (`which telnet`);
      si no, instalarlo (`apt install telnet` / `yum install telnet`).

#### 9.2 Validar el parser con una transcripción REAL (antes de tocar producción)
- [ ] Capturar manualmente la salida de un `display port traffic X` real (login telnet manual
      a una OLT, copiar el bloque completo de texto tal cual aparece, con saltos de línea).
- [ ] Confirmar el **offset +7** entre la línea de bajada y la de subida
      (`utils/parser_trafico.py::OFFSET_LINEA_SUBIDA`) — contar líneas reales entre
      "The received traffic..." y "The transmitted traffic..." en la captura.
- [ ] Si el offset difiere, ajustar la constante y volver a correr
      `python -m tests.test_parser_trafico` con un fixture basado en la captura real.
- [ ] Reemplazar/complementar la transcripción sintética de
      `tests/test_parser_trafico.py` con la real (mínimo 1 caso).

#### 9.3 Validar el autómata telnet contra una OLT real (una por proceso)
- [ ] Elegir 1 OLT MA5800-X15, 1 OLT MA5600T y 1 OLT "otros" (idealmente MA5603T,
      `OLT-CONCEPCION-1` y `OLT-LASCONDES-1` para cubrir los 3 caminos de dispatch) de bajo
      riesgo (fuera de horario punta) para las primeras pruebas.
- [ ] Ejecutar el **worker en modo manual** (sin cron) para cada una:
      `env/bin/python main.py --proceso <nombre> --worker --server S --ip I --lote 0`
      y revisar el log generado en `logs/<proceso>/...`.
- [ ] Confirmar que el login/enable/config/logout ocurre sin quedarse colgado
      (los prompts `_PROMPT_*` de `utils/telnet_olt.py` coinciden con el CLI real).
- [ ] Confirmar el manejo de paginado `More` si alguna OLT devuelve más de una pantalla.
- [ ] Para la OLT "otros" con modelo/server **no reconocido** por `_comandos_dispatch`
      (si existe alguna en el catálogo real): confirmar que el proceso solo inserta
      `peak=0` sin intentar telnet, tal como está documentado.

#### 9.4 Comparar datos: Python vs PHP (paridad fila a fila)
Para cada uno de los 3 procesos, en una ventana de 15 min donde AMBOS (PHP y Python) corran
sobre el mismo conjunto de OLT (usar una BD de pruebas/copia si es posible; si debe ser sobre
producción, coordinar para no duplicar filas reales):
- [ ] **MA5800-X15**: comparar `OLT_TRAFICO_UPLINK_MA5800_X15` (detalle) y
      `OLT_TRAFICO_UPLINK_HORA` (peak) — mismos puertos, mismos valores de tráfico,
      mismo `week`.
- [ ] **MA5600T**: ídem sobre `OLT_TRAFICO_UPLINK_MA5600T` y `OLT_TRAFICO_UPLINK_HORA`.
      Verificar especialmente el caso de fallo (marcador `MA5600T2`/`peak=0` + el insert
      normal con `peak=0` — deben aparecer **dos filas**, no una).
- [ ] **otros**: ídem, prestando atención a que `fecha` en `OLT_TRAFICO_UPLINK_HORA` sea
      `ahora + 9h` (no `NOW()`), y que el detalle de `OLT-LASCONDES-1` ahora refleje el
      **fix del bug** (`conteo["0/18"]` en vez del `puerto08` erróneo del PHP) — es decir,
      revisar si esto cambia la cantidad de puertos reportados para LASCONDES respecto al
      histórico PHP, y que el cambio sea el esperado (más correcto, no menos datos).
- [ ] Revisar `MONITOREO_PROCESOS_EJECUCIONES` de las 3 corridas: estados `RUNNING`→`OK`,
      `parent_id`/`lote_id` bien vinculados entre padre e hijos, `cantidad_subprocesos`
      correcto.
- [ ] Documentar cualquier diferencia encontrada y su causa (formato numérico, redondeo,
      timing) antes de decidir si es aceptable.

#### 9.5 Decisiones de negocio pendientes (confirmar con el usuario/áreas de red)
- [ ] **VITACURA-1** (`config/settings.py::SERVERS_MA5600T_REINTENTO_SCU_7_8`): confirmar si
      la ausencia de VITACURA-1 en la lista de reintento es intencional o un descuido del
      PHP original. Se replicó tal cual por decisión explícita del usuario — dejar constancia
      aquí de que sigue así tras la validación en producción, o corregir si se decide lo
      contrario.
- [ ] Confirmar que las **9 IPs especiales** de `IPS_MA5800X15_MPU_8_9` siguen siendo las
      correctas (podrían haber cambiado equipos de esa lista desde que se escribió el PHP).
- [ ] Confirmar que `SERVER_MA5600T_GIU_19_20` (`OLT-LAFLORIDA-1`) y los servers de
      `SERVERS_MA5600T_SCU_7_8` siguen vigentes (mismo server, misma configuración física).

#### 9.6 Criterio de salida de Fase 9
- [ ] Los 3 procesos corrieron manualmente sin error contra OLT reales.
- [ ] La comparación de datos (9.4) no mostró diferencias inexplicadas.
- [ ] Las decisiones de negocio (9.5) quedaron confirmadas o corregidas.
- [ ] Los fixtures de test (`tests/test_parser_trafico.py`) ya usan datos reales, no solo sintéticos.

---

### Fase 10 — Despliegue Linux + inventario

> Ejecutar **solo después de** que Fase 9 haya validado los 3 procesos sin diferencias.

#### 10.1 Preparación del entorno
- [ ] `env/bin/pip freeze > requirements.txt` en el servidor (fijar versiones exactas de
      producción, pueden diferir levemente de las de dev/Windows).
- [ ] `.env` de producción completo y con permisos restringidos (`chmod 600 .env`).
- [ ] Confirmar rutas en `config/settings.py` (`BASE_DIR`, `LOGS_DIR`, `PYTHON_BIN`) apuntan
      correctamente dentro de la ruta real de despliegue (ej.
      `/u01/crontab127/OLT/Uplink/python/uplink_trafico_15m/`).
- [ ] Crear la carpeta `logs/` (y subcarpetas por proceso) con permisos de escritura para el
      usuario que ejecuta el cron.

#### 10.2 Cron (3 entradas separadas, igual que el PHP original)
- [ ] Reemplazar las 3 líneas de cron PHP por sus equivalentes Python, **manteniendo
      `*/15`** y el usuario/entorno de cron actual:
      ```
      */15 * * * * cd /ruta/uplink_trafico_15m && env/bin/python main.py --proceso uplink_trafico_15m_ma5800x15 >> logs/cron_ma5800x15.log 2>&1
      */15 * * * * cd /ruta/uplink_trafico_15m && env/bin/python main.py --proceso uplink_trafico_15m_ma5600t   >> logs/cron_ma5600t.log 2>&1
      */15 * * * * cd /ruta/uplink_trafico_15m && env/bin/python main.py --proceso uplink_trafico_15m_otros     >> logs/cron_otros.log 2>&1
      ```
- [ ] **No usar el modo "todos"** (sin `--proceso`) en cron — ver nota `[PARIDAD-PHP]` en
      `main.py`: cada proceso va en su propia línea, igual que los 3 archivos PHP originales.
- [ ] Verificar que `start_new_session=True` (usado por los orquestadores MA5800-X15/MA5600T
      al lanzar sus workers) se comporta bien bajo cron (los workers no deben quedar
      huérfanos ni bloquear la siguiente corrida del cron a los 15 min).
- [ ] Confirmar que **no se solapan** corridas de 15 min si algún proceso tarda más de ese
      intervalo (revisar logs de duración en `MONITOREO_PROCESOS_EJECUCIONES.duracion`).

#### 10.3 Corte desde PHP (transición controlada, con posibilidad de rollback)
- [ ] Definir una **ventana de convivencia**: dejar el PHP y el Python corriendo en paralelo
      unos días, insertando en **tablas de prueba/staging** (o comparando sin duplicar en
      real) antes de desactivar el cron PHP definitivamente. Confirmar con el usuario cuántos
      días de convivencia se requieren.
- [ ] Una vez validado: **comentar/eliminar** las 3 líneas de cron PHP (no borrar los
      archivos PHP todavía — mantenerlos como referencia/rollback por un tiempo).
- [ ] Documentar el plan de rollback: cómo volver a activar el cron PHP si algo falla en
      producción con el Python (reactivar las 3 líneas comentadas).

#### 10.4 Actualizar inventario y documentación
- [ ] Agregar los 3 procesos nuevos a `python/INVENTARIO_PROCESOS_OLT.xlsx` (mismo formato
      que el proceso diario ya cargado): nombre, PHP origen, descripción, cron `*/15 * * * *`,
      tablas de lectura/escritura, estado, fecha de migración, ruta de producción, notas
      (mencionar el fix de LASCONDES y la discrepancia de VITACURA).
- [ ] Marcar el Tracker de este `PLAN_MIGRACION_15M.md` (sección 4) con Fases 9 y 10 como
      ✅ Completadas, y actualizar la fecha.

#### 10.5 Cierre
- [ ] Confirmar con el usuario que los 3 procesos llevan al menos 24-48h corriendo en
      producción sin errores en `MONITOREO_PROCESOS_EJECUCIONES` antes de dar el proyecto
      por cerrado.
- [ ] Archivar (no borrar) los 5 archivos PHP originales con una nota de qué los reemplazó
      y desde cuándo.

---

## 7. Cómo agregar un proceso nuevo
Mismo procedimiento que el proyecto diario: crear `model/<nombre>/`, `scripts/<nombre>/`, `logs/<nombre>/`,
reutilizar `utils/` y `app/db.py`, y **registrar en `main.py`** (dict `PROCESOS`, indicando
`kind="orquestador_worker"` o `kind="script"`).

---

## 8. Datos que faltan (pedir cuando se necesiten)

Todo lo que sigue bloquea el arranque de **Fase 9** — sin esto, esa fase no puede empezar:

- [ ] Credenciales BD reales (`conexion_db.php`) → completar en `.env` — ver Fase 9.1.
- [ ] Credenciales telnet reales (`OLT_TELNET_USER`/`PASS`) → completar en `.env` — ver Fase 9.1.
- [ ] Acceso al servidor Linux de producción (o uno de staging equivalente) con conectividad
      real a la red de OLT (10.99.x.x) — ver Fase 9.1.
- [ ] 1-2 transcripciones reales de `display port traffic` de una OLT Huawei (para confirmar
      el offset `+7` del parser) — ver Fase 9.2.
- [ ] Acceso/dump de BD `Aden` (o entorno de staging) para comparar filas Python vs PHP sin
      duplicar datos en producción — ver Fase 9.4.
- [ ] Confirmación de negocio sobre las 3 decisiones pendientes: VITACURA-1 fuera de la lista
      de reintento MA5600T, vigencia de las 9 IPs especiales de MA5800-X15, vigencia de
      LAFLORIDA/servers SCU de MA5600T — ver Fase 9.5.

Bloquea el arranque de **Fase 10**:
- [ ] Definición de cuántos días de convivencia PHP+Python se requieren antes de apagar el
      cron PHP — ver Fase 10.3.
