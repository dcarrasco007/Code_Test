# Librerias Python Usadas en GERET

Este documento describe las librerias de Python que se usan en los proyectos de GERET y para que sirve cada una.

Se basa en los `requirements.txt` de los proyectos actuales:

- `api_general_v2/requirements.txt` — API FastAPI (entorno de desarrollo).
- `api_general_v2/requirementsServer140.txt` — la **misma API**, pero para el servidor `140` (otro ambiente, ver mas abajo).
- `SCRIPT_5G_ENIQ/extraccion_data/requirements.txt` — proceso/script batch de extraccion de datos.

> En cada bloque se marca con `API`, `140` o `SCRIPT` en que proyecto aparece la libreria.

---

## Resumen Rapido

| Categoria | Librerias principales |
|-----------|------------------------|
| Framework API / servidor web | `fastapi`, `uvicorn`, `starlette`, `python-multipart` |
| Validacion de datos | `pydantic`, `annotated-types` |
| Autenticacion y seguridad | `PyJWT`, `passlib`, `bcrypt`, `cryptography` |
| Conexion a base de datos | `SQLAlchemy`, `PyMySQL`, `mysql-connector-python`, `pyodbc`, `pymssql` |
| Procesamiento de datos | `polars` (y `numpy`) |
| Configuracion / entorno | `python-dotenv` |
| Logs | `loguru` |
| Cliente HTTP | `requests` |
| XML | `xmltodict` |
| Integracion Ericsson ENM | `enm-client-scripting` |

---

## Framework de API y Servidor Web

- **`fastapi`** `API` `140` — framework para construir la API y sus endpoints.
- **`uvicorn`** `API` `140` — servidor ASGI que ejecuta la aplicacion FastAPI.
- **`starlette`** `API` `140` — base sobre la que corre FastAPI (rutas, middleware, requests/responses).
- **`python-multipart`** `API` `140` — permite recibir datos de formularios y archivos (`multipart/form-data`).
- **`anyio` / `sniffio` / `h11` / `asgiref`** `API` `140` — capa asincrona y de protocolo HTTP que usan FastAPI/Uvicorn por debajo.

## Validacion de Datos

- **`pydantic`** `API` `140` — define los schemas y valida los datos de entrada/salida de la API (en estos proyectos se usa la version 1.x).
- **`annotated-types` / `pydantic_core`** `API` — dependencias internas de Pydantic.

## Autenticacion y Seguridad

- **`PyJWT`** `API` `140` — genera y valida tokens JWT (login y autorizacion de endpoints).
- **`passlib`** `API` `140` — manejo y verificacion de contrasenas (hashing).
- **`bcrypt`** `API` `140` — algoritmo de hashing usado por passlib para las contrasenas.
- **`cryptography`** `API` `140` — primitivas criptograficas usadas por otras librerias de seguridad.
- **`cffi` / `pycparser`** `API` `140` — dependencias de bajo nivel de `cryptography`.

## Conexion a Base de Datos

- **`SQLAlchemy`** `API` `140` `SCRIPT` — capa de acceso a base de datos; creacion de engines y ejecucion de consultas.
- **`PyMySQL`** `API` `140` `SCRIPT` — driver MySQL en Python puro (usado por SQLAlchemy para MySQL).
- **`mysql-connector-python`** `SCRIPT` — driver oficial de MySQL; en el script se usa para las inserciones masivas (`executemany`).
- **`pyodbc`** `SCRIPT` — conexion via ODBC/FreeTDS a los servidores **Sybase de ENIQ**.
- **`pymssql`** `SCRIPT` — conexion alternativa a SQL Server / Sybase.
- **`greenlet`** `API` `140` `SCRIPT` — dependencia interna de SQLAlchemy.

## Procesamiento de Datos

- **`polars`** `SCRIPT` — DataFrames de alto rendimiento; hace el cruce y la transformacion del trafico por ROP.
- **`numpy`** `SCRIPT` — calculo numerico (esta comentado en el `requirements.txt`, disponible si se necesita).

## Configuracion y Entorno

- **`python-dotenv`** `API` `140` `SCRIPT` — carga las variables del archivo `.env` (credenciales, hosts, conexiones).

## Logs

- **`loguru`** `API` `140` `SCRIPT` — registro de logs simple y legible (avance del proceso, errores).
- **`colorama`** `API` `140` — colores en consola (salida de logs en Windows).
- **`win32-setctime`** `API` — soporte de loguru en Windows.

## Cliente HTTP e Integraciones

- **`requests`** `API` `140` — cliente HTTP para consumir servicios externos.
- **`xmltodict`** `API` `140` — convierte XML a diccionarios de Python (util al integrar respuestas en XML).
- **`enm-client-scripting`** `API` `140` — cliente para integrarse con **Ericsson ENM** (gestion de red). Se instala desde un paquete local `.whl` en `package/` (en `140` desde `/tools/...`, en desarrollo desde `C:/GERET/...`).

## Utilidades y Dependencias Internas

Estas no se usan directamente; vienen como dependencia de las anteriores:

- `click`, `Werkzeug`, `MarkupSafe`, `idna`, `six`, `colorama`, `typing_extensions`.

---

## El Caso del Servidor `140` (otro ambiente)

`requirementsServer140.txt` es la **misma API**, pero preparada para el servidor de produccion `192.168.66.140`, que es un **ambiente distinto** al de desarrollo.

Diferencias clave respecto al `requirements.txt` normal:

1. **Version de Python mas antigua (3.6).** Por eso aparecen *backports* que en Python moderno ya no hacen falta:
   - `dataclasses==0.8` — `dataclasses` viene incluido desde Python 3.7.
   - `contextvars`, `aiocontextvars`, `immutables` — backport de variables de contexto.
   - `importlib-metadata`, `zipp`, `contextlib2` — backports de utilidades estandar.

2. **Sistema operativo Linux (no Windows).** No incluye paquetes especificos de Windows como `win32-setctime`.

3. **Ruta del paquete ENM distinta.** `enm-client-scripting` se instala desde `/tools/api_general_v2/package/...` (en desarrollo es una ruta de Windows `C:/GERET/...` y ademas esta comentada).

4. **Comando de arranque incluido** al final del archivo, atado a la IP del servidor:
   ```bash
   uvicorn main:app --host 192.168.66.140 --port 5000 --reload --log-level info --access-log
   ```

> En resumen: usar `requirements.txt` para desarrollar localmente y `requirementsServer140.txt` solo al desplegar en el servidor `140`. No mezclar ambos.

---

## Instalacion

```bash
# Desarrollo (API o script)
pip install -r requirements.txt

# Despliegue en el servidor 140 (solo API)
pip install -r requirementsServer140.txt
```

---

## Plantilla `requirements.example.txt`

En este repo hay un [`requirements.example.txt`](./requirements.example.txt): una plantilla comentada y agrupada por categoria. Sirve como punto de partida al crear un proyecto nuevo.

```bash
# Copiar la plantilla al proyecto nuevo y dejar solo lo que se use
cp requirements.example.txt /ruta/al/proyecto/requirements.txt
```

---

## Generar y Mantener el `requirements.txt`

### Generar desde el entorno actual

Con el entorno virtual **activado** y solo las librerias necesarias instaladas:

```bash
python3.9 -m venv env          # crear entorno (una vez)
source env/bin/activate        # activar (Linux/Mac)  |  env\Scripts\activate en Windows

pip install fastapi uvicorn ... # instalar lo que se necesite
pip freeze > requirements.txt   # congelar versiones exactas
```

> `pip freeze` vuelca **todo** lo instalado en el entorno (incluidas dependencias transitivas). Por eso conviene partir de un entorno limpio y dedicado al proyecto.

### Agregar una libreria nueva

```bash
pip install <libreria>
pip freeze > requirements.txt   # regenerar para dejar la version fijada
```

### Actualizar una libreria

```bash
pip install --upgrade <libreria>
pip freeze > requirements.txt
```

### Quitar una libreria

```bash
pip uninstall <libreria>
pip freeze > requirements.txt
```

### Reproducir el entorno en otra maquina / servidor

```bash
python -m venv env && source env/bin/activate
pip install -r requirements.txt
```

### Verificar diferencias / paquetes desactualizados

```bash
pip list --outdated            # ver que librerias tienen version mas nueva
pip check                      # detectar incompatibilidades entre versiones
```

### Recomendaciones

- Mantener **un entorno virtual por proyecto** (`env/` o `.venv/`), nunca instalar global.
- Versionar siempre el `requirements.txt`; **no** versionar el `env/` (va en `.gitignore`).
- Para el servidor `140`, mantener su archivo aparte (`requirementsServer140.txt`) y no sobrescribirlo con un `pip freeze` hecho en desarrollo: el ambiente y la version de Python son distintos.
