# Estructura Base Para Bots de Telegram (Python)

Este README describe la estructura general utilizada por los proyectos tipo **bot de Telegram** en Python de GERET.

> **¿Es este el documento correcto?** Este README aplica si el proceso **queda corriendo permanentemente** escuchando mensajes de Telegram (long polling) y ademas ejecuta tareas periodicas internas. Si el proyecto se ejecuta, hace su trabajo y termina, usa `README_PYTHON_SCRIPT.md`. Si expone endpoints HTTP, usa `README_PYTHON_API.md`.

Aplica a bots que consultan bases de datos, generan reportes (imagenes, Excel) y los envian a usuarios suscritos, con menus interactivos y envios automaticos por horario. Por ejemplo:

- `bot_crm`

A diferencia del script batch, el bot no termina: arranca una vez, mantiene un event loop `asyncio` y responde a eventos (mensajes del usuario o tareas programadas).

## Estructura General

```text
bot_nombre/
|-- .env
|-- .gitignore
|-- requirements.txt
|-- ecosystem.config.js
|-- bot.py
|-- env/
|-- app/
|   `-- config.py
|-- robot/
|   |-- model/
|   |   `-- consultas.py
|   `-- scripts/
|       |-- cmd.py
|       |-- alertas.py
|       `-- nombre_modulo.py
|-- utils/
|   |-- func.py
|   `-- logger.py
|-- tests/
|   `-- test_nombre.py
`-- log/
```

## Stack

| Componente | Libreria |
|---|---|
| Framework del bot | `python-telegram-bot` (rama 20.x, async) |
| Base de datos | `SQLAlchemy` + `PyMySQL` (MySQL) |
| Transformacion de datos | `pandas` |
| Generacion de imagenes | `matplotlib` (backend `Agg`) + `pillow` |
| Exportacion a Excel | `openpyxl` |
| Logging | `loguru` |
| Variables de entorno | `python-dotenv` |
| Ejecucion en servidor | PM2 (`ecosystem.config.js`) |

Version de Python segun el servidor donde corra (ej. 3.9). Ojo con la sintaxis de tipos: en Python 3.9 no se usa `X | None`, sino `Optional[X]` de `typing`.

## Archivos Raiz

### `bot.py`

Punto de entrada del bot. Es el unico archivo que arranca la aplicacion.

Contenido esperado:

- carga del `.env` (`load_dotenv`) y configuracion del logging (`setup_logging`).
- lectura del token (`BOT_TOKEN`) y del proxy opcional (`PROXY`).
- construccion de la aplicacion con `ApplicationBuilder` y sus timeouts.
- registro de handlers (delegado a `robot/scripts/cmd.py`).
- registro de tareas periodicas en `post_init`.
- `application.run_polling()`.

Estructura concreta:

```python
import asyncio, os, time
from dotenv import load_dotenv
from loguru import logger
from telegram.ext import ApplicationBuilder, Application

from robot.scripts import cmd
from robot.scripts.alertas import enviar_alertas
from utils.func import marker_errors
from utils.logger import setup_logging


async def periodic_alerts(app: Application):
    """Se despierta en cada multiplo de 5 minutos del reloj."""
    while True:
        await asyncio.sleep(300 - time.time() % 300)
        try:
            await enviar_alertas(app.bot)
        except Exception as e:
            marker_errors(f"Error en envio periodico: {e}")


async def on_startup(app: Application):
    app.create_task(periodic_alerts(app))


def start_bot():
    load_dotenv()
    setup_logging()

    token = os.getenv("BOT_TOKEN")
    if not token:
        raise RuntimeError("No se encontro BOT_TOKEN en el .env")

    builder = (
        ApplicationBuilder().token(token).post_init(on_startup)
        .connect_timeout(30).read_timeout(60).write_timeout(300)
        .pool_timeout(60).connection_pool_size(100)
    )
    proxy = os.getenv("PROXY")
    if proxy:
        builder = builder.proxy(proxy).get_updates_proxy(proxy)

    application = builder.build()
    cmd.register_handlers(application)
    application.run_polling()


if __name__ == "__main__":
    start_bot()
```

Los timeouts amplios no son adorno: subir imagenes o Excel grandes a traves de proxy supera los 20s por defecto, y el pool grande evita `pool_timeout` al enviar a muchos usuarios en paralelo con `asyncio.gather`.

### `ecosystem.config.js`

Configuracion de PM2 para mantener el bot corriendo en el servidor.

Contenido esperado:

- `cwd` con la ruta del proyecto e `interpreter` apuntando al Python del entorno virtual.
- `autorestart: true` y `max_memory_restart`.
- `PYTHONUNBUFFERED: "1"` para que los logs de `loguru` salgan al instante.
- `error_file` / `out_file` dentro de `log/`.

Estructura concreta:

```javascript
module.exports = {
    apps: [{
        name: "bot_nombre",
        cwd: "/var/www/bot_nombre",
        script: "bot.py",
        interpreter: "/var/www/bot_nombre/env/bin/python3",
        instances: 1,
        autorestart: true,
        max_memory_restart: "500M",
        env: { ENVIRONMENT: "production", PYTHONUNBUFFERED: "1" },
        error_file: "/var/www/bot_nombre/log/pm2-err.log",
        out_file: "/var/www/bot_nombre/log/pm2-out.log",
        log_date_format: "YYYY-MM-DD HH:mm:ss"
    }]
}
```

### `.env`

Variables de entorno del bot.

Contenido esperado:

- `BOT_TOKEN` (produccion) y `BOT_TOKEN_TEST` (desarrollo).
- una cadena de conexion por base (`CONECTION_ADEN`, `CONECTION_CCO`, `CONECTION_RASP`).
- `PROXY` si el servidor sale a internet por proxy.

Las credenciales nunca se escriben dentro del codigo.

### `.gitignore`

Excluye `env/`, `__pycache__/`, `*.pyc`, `*.log`, `.env`, `.claude/`.

### `requirements.txt`

Dependencias fijadas por version. Nucleo minimo:

```text
python-telegram-bot==20.7   # rama 20.x async
SQLAlchemy==2.0.36
PyMySQL==1.1.1
pandas==2.1.4
matplotlib==3.8.4
openpyxl==3.1.5             # necesario para to_excel()
pillow==11.3.0
loguru==0.7.3
python-dotenv==1.0.0
```

## `app/config.py`

Definicion centralizada de las conexiones a base de datos. Ningun otro archivo crea engines.

Contenido esperado:

- lectura de las cadenas de conexion desde `.env`.
- un engine por base, aunque hoy apunten al mismo servidor: si una base se muda, solo cambia su variable en el `.env`.
- pool configurado con `pool_pre_ping=True` (una conexion idle cerrada por el servidor se reemplaza sola en vez de tirar error) y `pool_recycle`.

Estructura concreta:

```python
import os
from dotenv import load_dotenv
from loguru import logger
from sqlalchemy import create_engine

try:
    load_dotenv()

    CONECTION_ADEN = os.getenv("CONECTION_ADEN")
    CONECTION_CCO  = os.getenv("CONECTION_CCO")
    if not CONECTION_ADEN or not CONECTION_CCO:
        raise RuntimeError("No se encontro alguna de las conexiones en el .env")

    _engine = lambda url: create_engine(
        url, pool_size=20, max_overflow=5,
        pool_timeout=300, pool_recycle=1800, pool_pre_ping=True,
    )

    engine_aden = _engine(CONECTION_ADEN)
    engine_cco  = _engine(CONECTION_CCO)

except Exception as e:
    logger.critical(f"Error al configurar la base de datos: {e}")
    raise
```

El engine se elige por la tabla principal del `FROM`. Las tablas de otra base referenciadas en joins van con su prefijo (`CCO.`, `RASP.`); el engine elegido solo necesita permisos para leerlas.

## `robot/model/consultas.py`

Capa de acceso a datos (DAL). **Toda query SQL vive aqui**, en ningun otro archivo.

Contenido esperado:

- constantes SQL declaradas al inicio con `text()`, con parametros `:nombre` (nunca f-strings con datos del usuario).
- funciones publicas `async` que ejecutan la query en un thread pool.
- helpers privados para no repetir codigo.
- manejo de errores centralizado por decorador, no con `try/except` en cada funcion.

### Helpers privados

```python
def _fetch(engine, sql, params=None) -> list:   # ejecuta y retorna lista de dicts
def _in_clause(valores) -> tuple:               # construye params + clausula IN
def _safe(default):                             # decorador de manejo de errores
```

### Patron obligatorio: `async` + `asyncio.to_thread`

SQLAlchemy es sincrono. Si se llama directo, bloquea el event loop de Telegram y el bot deja de responder a los demas usuarios mientras corre la query.

```python
# CORRECTO — no bloquea
@_safe(list)
async def consultar_algo() -> list:
    return await asyncio.to_thread(_fetch, engine_aden, SQL_ALGO)

# INCORRECTO — bloquea el event loop
def consultar_algo():
    with engine_aden.connect() as conn: ...
```

### Manejo de errores con `@_safe(default)`

El decorador loguea con `marker_errors` (error + traceback) y retorna `default` en vez de propagar al handler. Si `default` es callable (`list`, `dict`, `set`) se invoca en cada fallo, asi nunca se comparte un mutable entre llamadas.

```python
@_safe(list)    # [] si falla;  @_safe(None) / @_safe(dict) / @_safe(set)
async def consultar_detalle(zonas: list) -> list:
    if not zonas:
        return []
    params, in_clause = _in_clause(zonas)
    sql = text(f"SELECT ... WHERE ZONA IN ({in_clause})")
    return await asyncio.to_thread(_fetch, engine_aden, sql, params)
```

Fuera de la capa de datos (handlers, jobs) si se usa `marker_errors` directo en el `except`.

El prefijo de la capa es el nombre del archivo (`consultas.py`), no un prefijo por funcion; las funciones se nombran `consultar_*`, `registrar_*`, `verificar_*`.

## `robot/scripts/`

Capa de logica de negocio y de interaccion con Telegram. Un archivo por dominio funcional.

```text
robot/scripts/
|-- cmd.py            <- handlers y menu (routing)
|-- alertas.py        <- reportes y envio programado
`-- nombre_modulo.py  <- un modulo por dominio (clima, sitios, cortes, ...)
```

### `robot/scripts/cmd.py`

Handlers de Telegram y armado del menu. Es la unica capa que conoce comandos y teclados.

Contenido esperado:

- `register_handlers(application)` con `CommandHandler`, `MessageHandler` y `CallbackQueryHandler`.
- diccionarios de opciones del menu: el boton se dibuja solo a partir del dict.
- submenus como diccionarios aparte.
- opciones restringidas por usuario cuando corresponda.

```python
OPCIONES_EXTRA: dict = {
    "📋 Informes": mostrar_menu_informes,
    "☁️ Clima": mostrar_menu_clima,
    "🔍 POP": iniciar_busqueda_sitio,
}
```

Para agregar una opcion al menu basta definir la funcion `async` y registrarla en el dict: no se toca `handle_text` ni el armado del teclado.

### `robot/scripts/alertas.py`

Logica de negocio: arma el reporte y lo envia.

Contenido esperado:

- helpers privados (`_imagen_bytes`, `_excel_bytes`, `_enviar_a_usuario`) reutilizados por el envio manual y el automatico.
- listas de horarios de envio automatico.
- `asyncio.gather` para lanzar queries y envios independientes en paralelo.
- generacion de imagenes y Excel dentro de `asyncio.to_thread` (matplotlib y pandas son sincronicos y pesados).

```python
HORARIOS_SIN_FILTRO = ["08:00", "16:30"]
HORARIOS_CON_FILTRO = [f"{h:02d}:00" for h in range(24)
                       if f"{h:02d}:00" not in HORARIOS_SIN_FILTRO]

filas_c, filas_p, filas_s = await asyncio.gather(
    consultar_detalle_comunas(fecha, zonas),
    consultar_detalle_pop(fecha, zonas),
    consultar_detalle_sitios(fecha, zonas),
)
```

Los archivos generados se pasan como **bytes puros**, no como `BytesIO`: permite enviar el mismo archivo a N usuarios en paralelo sin race conditions en el `seek`.

Los horarios deben caer en el intervalo del job periodico (si el job corre cada 5 minutos, los horarios van en multiplos de 5).

## `utils/`

Utilidades transversales, sin logica de negocio ni queries.

### `utils/logger.py`

Configuracion central de `loguru`. Se llama **una sola vez** al arrancar el bot.

Contenido esperado:

- `setup_logging(log_file)` que crea la carpeta de logs, agrega salida a consola (INFO, con color) y a archivo (DEBUG, con `rotation` y `retention`).
- clase `_InterceptHandler(logging.Handler)` que redirige los logs de librerias que usan el `logging` estandar hacia `loguru`.
- silenciado de librerias ruidosas (`httpx`, `httpcore`, `telegram`, `asyncio`, `urllib3`) a nivel `WARNING`.

```python
class _InterceptHandler(logging.Handler):
    """Redirige logs de librerias (stdlib logging) hacia loguru."""
    def emit(self, record: logging.LogRecord) -> None:
        ...

def setup_logging(log_file: str = "log/bot.log") -> None:
    Path(log_file).parent.mkdir(parents=True, exist_ok=True)
    logger.remove()
    logger.add(sys.stderr, level="INFO", colorize=True, format="...")
    logger.add(log_file, level="DEBUG", rotation="10 MB", retention="7 days",
               encoding="utf-8", format="...")
    logging.basicConfig(handlers=[_InterceptHandler()], level=0, force=True)
```

### `utils/func.py`

Funciones auxiliares reutilizables: log de errores, formateo, generacion de imagenes y planillas.

```python
def marker_errors(msg: str) -> None:
    logger.opt(exception=True).error(msg)

def safe_ts(fecha) -> str:              # timestamp seguro para nombres de archivo
def wrap_col(df, col, width=15):        # ajuste de texto en columnas de DataFrame
def tablas_zona_a_imagen(...) -> BytesIO:  # PNG con matplotlib — llamar via to_thread
```

Las funciones pesadas de `matplotlib`/`pandas` se definen aqui como **sincronicas** y se llaman desde los scripts con `asyncio.to_thread`. El backend de matplotlib debe fijarse en `Agg` antes de importar `pyplot`, porque el servidor no tiene entorno grafico:

```python
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
```

## `log/`

Carpeta donde `loguru` y PM2 escriben los registros de ejecucion. Rota por tamano y se conserva por dias (`rotation="10 MB"`, `retention="7 days"`). Va en el `.gitignore`.

## `tests/`

Pruebas simples: conexion a las bases, validacion de consultas o de los helpers de `utils/`. Sirve para verificar el entorno antes de levantar el bot completo.

## Flujo

### Flujo de usuario (a demanda)

```text
Usuario escribe / toca un boton
    -> cmd.py (handler correspondiente segun el dict de opciones)
        -> nombre_modulo.py / alertas.py (logica de negocio)
            -> consultas.py (queries async via to_thread)
                -> app/config.py (engine de la base)
        <- imagen PNG / Excel / texto de vuelta al usuario
```

### Flujo automatico (periodico)

```text
bot.py -> on_startup -> app.create_task(periodic_alerts)
    -> loop asyncio: espera hasta el proximo multiplo de N minutos
        -> alertas.py: revisa si la hora actual esta en HORARIOS_*
            -> consultas.py (datos + suscripciones)
            -> envio a los usuarios que correspondan
```

## Convenciones Generales

- `bot.py` es el unico punto de entrada; no hay otros scripts ejecutables.
- Los engines se definen solo en `app/config.py`; las queries solo en `robot/model/consultas.py`.
- Todas las funciones de la capa de datos son `async` y ejecutan SQL con `asyncio.to_thread`.
- El manejo de errores de la capa de datos va por decorador (`@_safe`), no con `try/except` repetido.
- Nada de f-strings con datos del usuario dentro del SQL: siempre parametros `:nombre`.
- Las credenciales viven en `.env`, nunca dentro del codigo.
- El logging se hace con `loguru`, configurado una sola vez en `utils/logger.py`.
- El menu se extiende agregando entradas al diccionario de opciones, no editando el routing.
- Trabajo pesado y sincronico (matplotlib, pandas, Excel) siempre dentro de `asyncio.to_thread`.
- Respetar la version de Python del servidor (en 3.9: `Optional[X]`, no `X | None`).

## Como Ejecutar

```bash
python -m venv env && source env/bin/activate
pip install -r requirements.txt
python bot.py
```

En el servidor, gestionado por PM2:

```bash
pm2 start ecosystem.config.js
pm2 logs bot_nombre
pm2 restart bot_nombre
```

## Resumen De Estructura

```text
bot.py
    punto de entrada: arranca la app de Telegram y las tareas periodicas

app/config.py
    engines de base de datos (uno por base), con pool y pre-ping

robot/model/consultas.py
    capa de datos: todas las queries SQL, async + to_thread + @_safe

robot/scripts/cmd.py
    handlers de Telegram y menu (routing por diccionario)

robot/scripts/alertas.py
robot/scripts/nombre_modulo.py
    logica de negocio: arma el reporte y lo envia

utils/logger.py
utils/func.py
    configuracion de loguru y utilidades (errores, imagenes, Excel)

ecosystem.config.js
    ejecucion permanente en el servidor con PM2

.env
    token del bot, cadenas de conexion y proxy
```

Esta organizacion separa punto de entrada, conexiones, consultas, logica de negocio, interaccion con Telegram y utilidades, de modo que agregar una opcion al menu, una query o un envio programado toque un solo archivo.
