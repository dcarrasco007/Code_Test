# Plan de Migración — Proceso Uplink Tráfico Diario (PHP 5.3 → Python 3.12)

> **Documento de diseño.** Este `.md` describe **TODO** lo que hay que hacer para migrar los
> procesos PHP a Python siguiendo el manual *Estructuración de Proyectos – Scripts en Python*.
> Está dividido en **fases independientes**: cada fase se puede completar, validar y pausar
> sin bloquear el reinicio del trabajo en otra sesión (o si se agotan tokens).
>
> **En esta etapa NO se escribe código todavía** — sólo el diseño. La implementación de cada
> fase se hará después, una a una, marcando el progreso en el tracker de abajo.

---

## 0. Cómo usar este documento (instrucciones para la IA que continúe)

1. Lee la sección **1. Contexto** y **2. Análisis del origen** para entender qué hace el código PHP.
2. Revisa **3. Decisiones tomadas** (ya están confirmadas con el usuario — no las cambies sin preguntar).
3. Mira el **Tracker de progreso** (sección 4) para saber en qué fase vamos.
4. Ejecuta **una fase a la vez**, en orden. Al terminar cada fase:
   - Marca sus casillas `[ ]` → `[x]`.
   - Actualiza el estado de la fase en el tracker (`Pendiente` → `En curso` → `Completada`).
   - Verifica los **Criterios de aceptación** antes de avanzar.
5. Si una fase requiere una credencial, ruta o dato que no tienes, **detente y pregunta** —
   no inventes valores de conexión a base de datos.

---

## 1. Contexto

Se migran dos scripts PHP 5.3 que generan los promedios y picos **diarios** de tráfico de los
uplinks de las OLT modelo `MA5800-X15`. Hoy corren como cron en Linux (`/u01/crontab127/...`).

| PHP origen | Rol | Cómo se ejecuta hoy |
|---|---|---|
| `proceso_uplink_trafico_diario_conexped.php` | **Orquestador**. Lista las OLT y lanza un proceso por cada una. | Cron diario. |
| `proceso_uplink_trafico_diario_exped.php` | **Worker**. Procesa **una** OLT (recibe `server ip fecha`). | Lo lanza el orquestador con `nohup php -f ... &` (paralelo, 1 por OLT). |

Objetivo: reproducir el **mismo comportamiento y los mismos INSERT** en Python 3.12, pero con la
estructura de proyecto del manual y buenas prácticas (consultas parametrizadas, configuración por
entorno, logging).

---

## 2. Análisis del origen (qué hace exactamente cada PHP)

### 2.1 `conexped.php` (orquestador)
1. `include` de `/u01/crontab127/conexion/conexion_db.php` (define `$host144_geret`, `$user144_geret`, `$pass144_geret`).
2. Conecta a MySQL, base de datos **`Aden`**, charset `utf8`.
3. Calcula `$fecha = ayer` (`date('Y-m-d', strtotime('-1 day'))`).
4. Query: lista OLT del catálogo:
   ```sql
   SELECT `server`, ip, modelo FROM OLT_SERVER WHERE modelo = 'MA5800-X15'
   ```
5. Por cada fila lanza en **background** un proceso por OLT:
   ```
   nohup php -f .../proceso_uplink_trafico_diario_exped.php $server $ip $fecha \
        > .../logs/MA5800-x15/log$ip.log &
   ```
   → procesamiento **paralelo**, un log por IP.

### 2.2 `exped.php` (worker, procesa una OLT)
Entradas: `argv[1]=server`, `argv[2]=ip`, `argv[3]=fecha`.

1. Obtiene los **puertos distintos** con datos ese día:
   ```sql
   SELECT DISTINCT(puerto) FROM OLT_TRAFICO_UPLINK_MA5800_X15
   WHERE server='$server' AND DATE(fecha)='$fecha'
   ```
2. Para **cada puerto**:
   - **Promedios** del día:
     ```sql
     SELECT server, ip, week, AVG(trafico), AVG(trafico_up)
     FROM OLT_TRAFICO_UPLINK_MA5800_X15
     WHERE server='$server' AND DATE(fecha)='$fecha' AND puerto='$puerto'
     ```
   - Acumula `promedios += AVG(trafico)`, `promediosUP += AVG(trafico_up)`, guarda `week`.
   - Inserta el promedio del día por puerta (valores en **Mbps** = `AVG/1024`, `round(,2)`):
     ```sql
     INSERT INTO Aden.OLT_TRAFICO_DIA_PUERTA
       (equipo, ip, puerta, promedio_trafico_down, fecha, week, promedio_trafico_up)
     VALUES (server, ip, puerto, round(avg/1024,2), fecha, week, round(avg_up/1024,2))
     ```
   - Inserta en **dos** tablas de ocupación (down/up formateados con `number_format(,2)`):
     ```sql
     INSERT INTO OLT_TRAFICOGPON2 (ip_equipo, port, up_mbps, down_mbps, fecha) VALUES (...)
     INSERT INTO OLT_TRAFICOGPON  (ip_equipo, port, up_mbps, down_mbps, fecha) VALUES (...)
     ```
   - **Picos** del día:
     ```sql
     SELECT server, ip, week, MAX(trafico), MAX(trafico_up)
     FROM OLT_TRAFICO_UPLINK_MA5800_X15
     WHERE server='$server' AND DATE(fecha)='$fecha' AND puerto='$puerto'
     ```
   - Acumula `peak += MAX(trafico)`, `peakUP += MAX(trafico_up)`.
3. Al final, si `promedios > 0`, inserta el **resumen diario de la OLT**:
   ```sql
   INSERT INTO OLT_TRAFICO_UPLINK_MA5800_X15_DAY
     (server, ip, promedio_trafico, peack_trafico, fecha, week, promedio_trafico_up, peack_trafico_up)
   VALUES (server, ip, round(promedios,1), round(peak,1), fecha, week, promediosUP, peakUP)
   ```
   Si no hay datos: imprime `'sin datos para insertar'`.

### 2.3 Tablas involucradas
| Tabla | Uso |
|---|---|
| `OLT_SERVER` | Lectura: catálogo de OLT (filtra `modelo='MA5800-X15'`). |
| `OLT_TRAFICO_UPLINK_MA5800_X15` | Lectura: muestras de tráfico (origen de AVG/MAX por puerto). |
| `OLT_TRAFICO_DIA_PUERTA` | Escritura: promedio diario por puerta. |
| `OLT_TRAFICOGPON` / `OLT_TRAFICOGPON2` | Escritura: ocupación (up/down en Mbps formateado). |
| `OLT_TRAFICO_UPLINK_MA5800_X15_DAY` | Escritura: resumen diario por OLT. |

### 2.4 Detalles de fidelidad a vigilar (¡importantes para que los datos coincidan!)
- **División /1024** y **`round(,2)`** para pasar a Mbps. Mantener idéntico.
- **`number_format($v,2)`** de PHP genera string con **coma de miles** y 2 decimales
  (ej. `1234.5` → `"1,234.50"`). Si las columnas `up_mbps`/`down_mbps` son `VARCHAR`, hay que
  replicar ese formato exacto; si son numéricas, decidir con el usuario. **→ Verificar el tipo
  de columna en Fase 6.**
- Redondeos finales del resumen: `promedio_trafico=round(,1)`, `peack_trafico=round(,1)`,
  pero `promedio_trafico_up` y `peack_trafico_up` se insertan **sin redondear**. Mantener igual.
- `week` proviene de la columna `week` de la tabla origen (no de `date('W')` de PHP, que sólo se
  calcula pero no se usa en el INSERT final). Conservar el comportamiento real (usa `$row3[2]`).
- `fecha = ayer` por defecto. Permitir override por argumento (como ya lo permite el worker PHP).
- Charset `utf8`.
- **Inyección SQL**: el PHP concatena variables en el SQL. En Python se migrará a **consultas
  parametrizadas** (mejora de seguridad sin cambiar resultados).

---

## 3. Decisiones tomadas (confirmadas con el usuario)

| Tema | Decisión |
|---|---|
| **Librería BD** | **SQLAlchemy** (engine + `text()` con parámetros). |
| **Paralelismo** | **Un `subprocess` por OLT** (réplica fiel de `nohup php -f ... &`), con su log por IP. |
| **SO destino (producción)** | **Linux** (igual que hoy: cron + nohup). Windows/laragon sólo para desarrollar. |
| **Carpeta del proyecto** | `./python/uplink_trafico/` (dentro de la carpeta `Uplink` actual). |
| **Escalabilidad** | El proyecto es un **contenedor de varios procesos** que pueden correr a la misma hora. Cada proceso es un **módulo con nombre descriptivo**; `main.py` selecciona cuál ejecutar (`--proceso`) o todos (ver §5.1 y §9). |
| **Nombres descriptivos** | Mismos nombres consistentes en `model/`, `scripts/` y `logs/`. El primer proceso se llama **`uplink_trafico_diario_ma5800x15`** (no `uplink_trafico` a secas), para distinguirlo de futuros procesos. |
| **Rutas centralizadas** | Todas las rutas y parámetros configurables viven en **`config/settings.py`** (+ `.env`). Nunca se hardcodean dentro de la lógica (ver §5.3). |
| **Comentarios para mantenimiento** | Todo archivo lleva comentarios, en especial en **rutas** y en la **invocación de subprocesos/comandos**, con etiquetas greppables (ver §5.2). |

---

## 4. Tracker de progreso

> Actualiza esta tabla al iniciar/terminar cada fase.

| Fase | Nombre | Estado |
|---|---|---|
| 0 | Andamiaje del proyecto (estructura + entorno) | ✅ Completada |
| 1 | Configuración de conexión (`app/db.py` + `.env`) | ✅ Completada |
| 2 | Capa Model (queries) | ✅ Completada |
| 3 | Script worker (equivalente a `exped.php`) | ✅ Completada |
| 4 | Script orquestador + subprocess (equivalente a `conexped.php`) | ✅ Completada |
| 5 | `main.py` (CLI de entrada) + logging | ✅ Completada |
| 6 | Validación y paridad con el PHP | ☐ Pendiente |
| 7 | Empaquetado y despliegue en Linux (cron) | ☐ Pendiente |

Leyenda de estado: ☐ Pendiente · 🔄 En curso · ✅ Completada

---

## 5. Arquitectura destino (según el manual — tipo *Script*)

El proyecto es un **contenedor de procesos**. Cada proceso (hoy uno, mañana varios que corren a la
misma hora) es un **módulo con nombre descriptivo** replicado en `model/`, `scripts/` y `logs/`.

```
python/uplink_trafico/                         # Proyecto contenedor de procesos OLT
├── app/
│   └── db.py                                  # Conexión: crea el engine SQLAlchemy desde .env
├── config/
│   └── settings.py                            # ⚙️ RUTAS y parámetros centralizados (ver §5.3)
├── env/                                       # Entorno virtual (auto-oculto, NO se versiona)
├── model/
│   └── uplink_trafico_diario_ma5800x15/       # ← nombre descriptivo del proceso
│       └── uplink_trafico_diario_ma5800x15_model.py   # Model: TODAS las queries del proceso
├── scripts/
│   └── uplink_trafico_diario_ma5800x15/
│       ├── orquestador.py                     # Lógica de 'conexped': lista OLTs y lanza subprocess
│       └── worker.py                          # Lógica de 'exped': procesa UNA OLT
├── logs/
│   └── uplink_trafico_diario_ma5800x15/
│       └── MA5800-x15/                        # Un log por IP (log<ip>.log), igual que el PHP
├── .env                                       # Credenciales (auto-oculto, NO se versiona)
├── .env.example                               # Plantilla de credenciales (sí se versiona)
├── .gitignore                                 # Ignora env/, .env, logs/, __pycache__/
├── main.py                                    # Entrada CLI: --proceso <nombre> [--worker ...]
├── requirements.txt                           # Dependencias
└── PLAN_MIGRACION.md                          # Este documento
```

> **Nota:** para agregar un nuevo proceso que corra a la misma hora **NO se crea otro proyecto** —
> se añade un módulo nuevo aquí dentro y se registra en `main.py`. Ver **§9. Cómo agregar un proceso**.

**Flujo de ejecución (réplica del PHP):**
```
cron → python main.py --proceso uplink_trafico_diario_ma5800x15           (modo orquestador)
          └─ por cada OLT: subprocess →
                python main.py --proceso uplink_trafico_diario_ma5800x15 --worker \
                       --server S --ip I --fecha F                         (modo worker, 1 OLT)
                  └─ procesa una OLT y escribe en BD
```

`main.py` decide qué hacer según los argumentos:
- **`--proceso <nombre>`** (sin `--worker`) → ejecuta el `orquestador.py` de ese proceso.
- **`--proceso <nombre> --worker --server --ip --fecha`** → ejecuta el `worker.py` de ese proceso para esa OLT.
- **(opcional) sin `--proceso`** → ejecuta **todos** los procesos registrados (útil para el cron único de la "misma hora"). Confirmar con el usuario en Fase 5.

Esto reproduce que `conexped.php` invoque a `exped.php`, pero con un único ejecutable y un nombre de
proceso explícito, de modo que el mismo `main.py` sirve para todos los procesos futuros.

---

### 5.1 Convención de nombres (obligatoria)

Para que cada proceso sea identificable y se puedan agregar más sin colisiones:

- **Nombre del proceso** = `<area>_<que_mide>_<frecuencia>_<modelo/alcance>`
  → ejemplo actual: **`uplink_trafico_diario_ma5800x15`**.
- Ese **mismo string** se usa para:
  - la carpeta en `model/` y el sufijo del archivo Model (`..._model.py`),
  - la carpeta en `scripts/`,
  - la carpeta de logs en `logs/`,
  - la clave de registro en `main.py` (§9).
- Archivos internos con rol fijo y nombre estable: `orquestador.py`, `worker.py`, `*_model.py`.
- Funciones del Model: verbo + entidad descriptiva (`get_olts_ma5800x15`, `insert_resumen_dia_olt`, …).

### 5.2 Estándar de comentarios (obligatorio en TODO el código)

Cada archivo `.py` debe incluir comentarios pensados para quien lo mantenga después. Se usan
**etiquetas greppables** para localizar rápido qué tocar:

| Etiqueta | Para qué | Dónde aplicarla |
|---|---|---|
| `# [CONFIG]` | Valor ajustable (constantes, límites, flags). | Cualquier parámetro que se cambie con frecuencia. |
| `# [RUTA]` | Línea que usa o construye una **ruta** de archivo/log/ejecutable. | Logs, ruta de `python`, ruta del proyecto. |
| `# [PROCESO]` | Punto donde se **invoca/lanza** un subproceso o comando. | El `subprocess.Popen(...)` del orquestador. |
| `# [SQL]` | Explica qué hace una consulta y a qué tabla afecta. | Encima de cada query del Model. |
| `# [PARIDAD-PHP]` | Detalle que debe coincidir con el PHP original (redondeos, `number_format`, etc.). | Cálculos sensibles (ver §2.4). |

Además, **cabecera obligatoria** al inicio de cada archivo:
```python
# ============================================================================
# <ruta/archivo>
# Proceso: uplink_trafico_diario_ma5800x15
# Equivalente PHP: proceso_uplink_trafico_diario_exped.php  (o conexped, según el caso)
# Descripción: <qué hace este archivo en una línea>
# Para modificar: <qué etiquetas buscar — p.ej. "buscar [RUTA] para cambiar rutas">
# ============================================================================
```

> Regla práctica: si alguien necesita **cambiar una ruta o un proceso**, debe poder hacerlo
> buscando `[RUTA]` o `[PROCESO]` sin leer toda la lógica.

### 5.3 Rutas y parámetros centralizados (`config/settings.py`)

Para no hardcodear rutas (hoy hay paths Linux `/u01/...` en el PHP) y poder moverlas fácil:

- `config/settings.py` expone **todas** las rutas y parámetros, leídos de `.env` cuando aplique,
  con valores por defecto y comentados con `# [RUTA]` / `# [CONFIG]`. Como mínimo:
  - `BASE_DIR` — raíz del proyecto (calculada, no hardcodeada).
  - `LOGS_DIR` — base de logs (`<BASE_DIR>/logs` por defecto; override por `.env`).
  - `PYTHON_BIN` — ejecutable de Python para los subprocess (por defecto el del `env/` actual).
  - `MODELO_OLT` — `'MA5800-X15'` (filtro del catálogo). `# [CONFIG]`
  - parámetros de comportamiento (p. ej. si el orquestador espera o no a los workers).
- El resto del código **importa de `settings.py`**; ninguna ruta se escribe a mano en la lógica.

---

## 6. Fases de implementación

### Fase 0 — Andamiaje del proyecto
**Objetivo:** crear estructura de carpetas, entorno virtual y dependencias.

- [x] Crear el árbol de carpetas de la sección 5 (vacías con `__init__.py` donde aplique para imports),
      usando el **nombre descriptivo** `uplink_trafico_diario_ma5800x15` en `model/`, `scripts/` y `logs/`.
- [x] Crear `config/settings.py` con las rutas/parámetros centralizados de §5.3 (etiquetados `# [RUTA]`/`# [CONFIG]`).
- [x] Crear `env/` con `py -3.12 -m venv env` y activarlo.
- [x] Crear `requirements.txt` con versiones fijadas: SQLAlchemy==2.0.51, mysql-connector-python==9.7.0, python-dotenv==1.2.2.
- [x] `pip install -r requirements.txt`.
- [x] Crear `.gitignore` (ignora `env/`, `.env`, `logs/`, `__pycache__/`, `*.pyc`).
- [x] Crear `.env.example` con las claves vacías (ver Fase 1).
- [x] Aplicar en todos los archivos el **estándar de comentarios** de §5.2 (cabecera + etiquetas).

**Criterios de aceptación:** el entorno se activa, `pip list` muestra SQLAlchemy y el driver,
`python -c "import sqlalchemy"` no falla.

---

### Fase 1 — Configuración de conexión (`app/db.py`)
**Objetivo:** conexión a MySQL `Aden` mediante engine SQLAlchemy leyendo credenciales de `.env`.

- [x] Credenciales dejadas vacías en `.env` para rellenar en producción.
      ⚠️ **Pendiente de producción:** completar `DB_HOST`, `DB_USER`, `DB_PASS`
      con los valores de `/u01/crontab127/conexion/conexion_db.php`.
- [x] Definido en `.env` / `.env.example`: `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME=Aden`, `DB_CHARSET=utf8`.
- [x] `app/db.py`:
  - [x] Carga `.env` con `python-dotenv`.
  - [x] Construye el engine: `create_engine("mysql+mysqlconnector://...")`.
  - [x] Expone `get_engine()` (singleton, reutilizable).
  - [x] `pool_pre_ping=True` para conexiones robustas.
  - [x] `_validar_credenciales()`: detecta `.env` sin rellenar y da mensaje claro al usuario.

**Criterios de aceptación:** prueba de conectividad (`SELECT 1`) pendiente para Fase 6 en entorno
destino, una vez se rellenen las credenciales en producción.

---

### Fase 2 — Capa Model (queries)
**Objetivo:** centralizar **todas** las consultas en
`model/uplink_trafico_diario_ma5800x15/uplink_trafico_diario_ma5800x15_model.py`,
parametrizadas con `text()`. Cada query lleva su comentario `# [SQL]` (§5.2).

- [ ] `get_olts_ma5800x15(conn)` → `SELECT server, ip, modelo FROM OLT_SERVER WHERE modelo=:modelo` (modelo desde `settings.MODELO_OLT`).
- [ ] `get_puertos(conn, server, fecha)` → `SELECT DISTINCT(puerto) ... WHERE server=:server AND DATE(fecha)=:fecha`.
- [ ] `get_promedios_puerto(conn, server, fecha, puerto)` → `SELECT server, ip, week, AVG(trafico), AVG(trafico_up) ...`.
- [ ] `get_max_puerto(conn, server, fecha, puerto)` → `SELECT server, ip, week, MAX(trafico), MAX(trafico_up) ...`.
- [ ] `insert_dia_puerta(conn, ...)` → INSERT en `OLT_TRAFICO_DIA_PUERTA`.
- [ ] `insert_ocupacion(conn, tabla, ...)` → INSERT reutilizable para `OLT_TRAFICOGPON` y `OLT_TRAFICOGPON2`.
- [ ] `insert_resumen_dia(conn, ...)` → INSERT en `OLT_TRAFICO_UPLINK_MA5800_X15_DAY`.
- [ ] Todas con **parámetros enlazados** (`:nombre`), nunca concatenación de strings.

**Criterios de aceptación:** cada función devuelve/ejecuta el SQL equivalente al PHP; revisión
manual lado a lado con la sección 2.2 confirma columnas y orden idénticos.

---

### Fase 3 — Script worker (`scripts/uplink_trafico_diario_ma5800x15/worker.py`)
**Objetivo:** replicar `exped.php` — procesar UNA OLT.
Marcar los cálculos sensibles con `# [PARIDAD-PHP]` (§2.4).

- [ ] Firma: `procesar_olt(server, ip, fecha)`.
- [ ] Abrir conexión/transacción (usar transacción por OLT; **decidir con usuario** si se quiere
      commit por puerto o uno solo al final — el PHP hace autocommit por sentencia).
- [ ] Obtener puertos (`get_puertos`).
- [ ] Inicializar acumuladores: `promedios, promediosUP, peak, peakUP, week = 0`.
- [ ] Bucle por puerto:
  - [ ] AVG → acumular `promedios`, `promediosUP`, guardar `week`.
  - [ ] Calcular `valorDown_1 = round(avg/1024, 2)`, `valorUp_1 = round(avg_up/1024, 2)` → `insert_dia_puerta`.
  - [ ] Calcular `valorDown`/`valorUP` con **formato `number_format(,2)`** (replicar coma de miles
        de PHP — ver 2.4) → `insert_ocupacion` en `OLT_TRAFICOGPON2` y `OLT_TRAFICOGPON`.
  - [ ] MAX → acumular `peak`, `peakUP`.
- [ ] Al final: si `promedios > 0` → `insert_resumen_dia` con
      `round(promedios,1)`, `round(peak,1)`, `week`, `promediosUP` (sin redondear), `peakUP` (sin redondear).
      Si no → log `'sin datos para insertar'`.
- [ ] Imprimir/loguear timestamp inicio y fin (`Y-m-d H:i:s`) como el PHP.

**Criterios de aceptación:** ejecutado para una OLT con datos conocidos, produce los mismos
registros que el PHP (comparar en Fase 6).

---

### Fase 4 — Script orquestador (`scripts/uplink_trafico_diario_ma5800x15/orquestador.py`)
**Objetivo:** replicar `conexped.php` — listar OLT y lanzar un subprocess por cada una.
La construcción de rutas usa `config/settings.py`; el lanzamiento lleva `# [PROCESO]` y `# [RUTA]`.

- [ ] Calcular `fecha = ayer` (`datetime.now() - timedelta(days=1)`, formato `%Y-%m-%d`),
      permitiendo override por argumento.
- [ ] `get_olts_ma5800x15` para obtener la lista.
- [ ] Por cada OLT lanzar en background (usando `PYTHON_BIN` y `LOGS_DIR` de `settings.py`):
      `subprocess.Popen([PYTHON_BIN, main.py, "--proceso", NOMBRE, "--worker", "--server", S, "--ip", I, "--fecha", F], stdout=log, stderr=log)`
      con `log = LOGS_DIR/uplink_trafico_diario_ma5800x15/MA5800-x15/log<ip>.log`.  `# [PROCESO]`
- [ ] Asegurar que la carpeta de logs existe (crear si falta).
- [ ] Equivalente a `nohup ... &`: en Linux usar `start_new_session=True` para desacoplar del padre.
- [ ] **Decidir con usuario**: ¿el orquestador espera a que terminen los workers o termina enseguida?
      (El PHP no espera). Por defecto: no esperar, salvo que se pida un límite de concurrencia.

**Criterios de aceptación:** ejecutar el orquestador genera N subprocesos (uno por OLT) y N logs;
cada log refleja el procesamiento de su OLT.

---

### Fase 5 — `main.py` (entrada CLI) + logging
**Objetivo:** punto de entrada único, **registro de procesos**, y configuración de logging.

- [ ] Definir un **registro de procesos** (dict) que mapea `nombre_proceso → {orquestador, worker}`.
      Agregar un proceso nuevo = añadir una entrada aquí (ver §9). Comentar con `# [PROCESO]`.
- [ ] Parsear args con `argparse`: `--proceso`, `--worker`, `--server`, `--ip`, `--fecha`.
- [ ] `--proceso N` (sin `--worker`) → llamar al `orquestador.run(fecha)` del proceso N.
- [ ] `--proceso N --worker ...` → llamar `worker.procesar_olt(server, ip, fecha)` del proceso N.
- [ ] **(opcional, confirmar)** sin `--proceso` → recorrer el registro y lanzar **todos** los
      orquestadores (un solo cron para la "misma hora"). Decidir comportamiento con el usuario.
- [ ] Validar que `--proceso` existe en el registro; si no, error claro listando los disponibles.
- [ ] Configurar `logging` (formato con timestamp; salida a stdout para que el subprocess la
      capture en el log por IP).
- [ ] Manejo de errores: capturar excepciones, loguear y salir con código != 0 (sin tumbar el
      resto de subprocesos).

**Criterios de aceptación:**
`python main.py --proceso uplink_trafico_diario_ma5800x15 --worker --server X --ip Y --fecha 2025-08-02`
procesa una OLT; `python main.py --proceso uplink_trafico_diario_ma5800x15` lanza el lote completo;
un `--proceso` inexistente da error con la lista de procesos válidos.

---

### Fase 6 — Validación y paridad con el PHP
**Objetivo:** confirmar que el Python produce **exactamente** los mismos datos que el PHP.

- [ ] Verificar tipos de columna de `up_mbps`/`down_mbps` (VARCHAR vs numérico) para decidir el
      formato `number_format` (ver 2.4). Ajustar Fase 3 si hace falta.
- [ ] Elegir una fecha y una OLT con datos. Ejecutar el PHP (entorno actual) y el Python contra una
      **BD de pruebas / copia** y comparar fila a fila las 4 tablas de escritura.
- [ ] Validar redondeos, separadores de miles, `week`, y el caso `'sin datos'`.
- [ ] Validar el lote completo (orquestador) con varias OLT y revisar los logs por IP.
- [ ] Documentar cualquier diferencia y su resolución.

**Criterios de aceptación:** diff de las tablas de salida PHP vs Python = 0 diferencias
(o diferencias justificadas y aprobadas por el usuario).

---

### Fase 7 — Empaquetado y despliegue en Linux (cron)
**Objetivo:** dejarlo listo para producción en el servidor Linux.

- [ ] Congelar dependencias: `pip freeze > requirements.txt`.
- [ ] Documentar instalación en el servidor: clonar/copiar, `python3.12 -m venv env`,
      `pip install -r requirements.txt`, crear `.env` real (NO versionar).
- [ ] Definir rutas de producción (logs, etc.) por `.env`/config — no hardcodear `/u01/...`.
- [ ] Crear entrada de cron equivalente al job actual (diario), apuntando a
      `env/bin/python main.py --proceso uplink_trafico_diario_ma5800x15`
      (o `env/bin/python main.py` sin `--proceso` si se habilitó el modo "todos los procesos").
- [ ] Verificar permisos de la carpeta `logs/` y que `start_new_session` se comporta bien bajo cron.
- [ ] Plan de rollback: mantener el PHP operativo hasta validar N días de paridad.

**Criterios de aceptación:** el cron ejecuta el proceso en el servidor, genera logs y escribe en BD
igual que el PHP durante el periodo de validación acordado.

---

## 7. Riesgos y notas

- **Acceso a BD desde desarrollo:** si el entorno laragon/Windows no alcanza la BD `Aden`, las
  pruebas de conectividad (Fases 1 y 6) deben hacerse en el servidor destino o vía túnel.
- **`number_format` con coma de miles:** principal fuente de diferencias de datos. Resolver en Fase 6
  según el tipo real de columna.
- **Concurrencia:** `nohup ... &` no limita el número de procesos en paralelo. Si hay muchas OLT,
  evaluar (con el usuario) un límite de workers en una iteración futura.
- **Credenciales:** nunca se versionan (`.env` en `.gitignore`). Sólo `.env.example` con claves vacías.
- **Zona horaria / fecha:** `fecha = ayer` depende de la TZ del servidor. Asegurar que el Python use
  la misma TZ que el cron actual.

---

## 8. Dependencias / datos que faltan (pedir al usuario cuando se necesiten)

- [ ] Contenido de `/u01/crontab127/conexion/conexion_db.php` (host, usuario, password reales) — **Fase 1**.
- [ ] Tipo de las columnas `up_mbps` / `down_mbps` en `OLT_TRAFICOGPON` y `OLT_TRAFICOGPON2` — **Fase 6**.
- [ ] Acceso (o copia/dump) de la BD `Aden` para pruebas de paridad — **Fase 6**.
- [ ] Ruta de producción y formato de la entrada de cron actual — **Fase 7**.

---

## 9. Cómo agregar un proceso nuevo (escalabilidad)

Cuando haya que migrar/añadir **otro proceso** que corra a la misma hora, **no se crea otro
proyecto**: se reutiliza esta misma estructura. Pasos:

1. **Elegir el nombre descriptivo** según §5.1, p. ej. `uplink_ocupacion_diario_ma5600t`.
2. **Crear las carpetas** con ese nombre:
   - `model/<nombre>/` + `<nombre>_model.py` (las queries del nuevo proceso).
   - `scripts/<nombre>/` + `orquestador.py` y `worker.py` (si aplica el patrón orquestador→worker;
     si el proceso es de un solo paso, basta un `script.py`).
   - `logs/<nombre>/...`.
3. **Reutilizar** `app/db.py` y `config/settings.py` (añadir ahí cualquier ruta/parámetro nuevo,
   etiquetado `# [RUTA]` / `# [CONFIG]`).
4. **Registrar el proceso** en `main.py` añadiendo una entrada al registro de procesos
   (`nombre → {orquestador, worker}`).  `# [PROCESO]`
5. **Aplicar el estándar de comentarios** (§5.2) en todos los archivos nuevos.
6. **Cron**: añadir `... main.py --proceso <nombre>` a la misma franja horaria, o —si se habilitó el
   modo "todos"— el nuevo proceso se ejecutará automáticamente con el cron único existente.

> Resultado: agregar un proceso = **carpetas + 1 línea en el registro de `main.py`**, sin tocar la
> lógica de los procesos existentes ni la conexión a BD.
