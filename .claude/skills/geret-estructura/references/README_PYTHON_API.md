# Estructura Base Para Proyecto Python API

Este README describe la estructura general utilizada por el proyecto Python/API de GERET.

> **¿Es este el documento correcto?** Este README aplica solo si el proyecto **queda corriendo como servidor** y responde peticiones HTTP (FastAPI + `uvicorn`, con `router/` y endpoints). Si el proyecto en cambio se ejecuta, hace su trabajo y termina (por ejemplo programado por cron), no es este documento: usa `README_PYTHON_SCRIPT.md`. El nombre del proyecto no siempre lo deja claro — ante la duda, confirmalo con el usuario responsable.

Aplica principalmente a:

- `api_general_v2`

La API esta construida con FastAPI y organizada por archivos y carpetas principales: entrada de aplicacion, configuracion, routers, modelos, schemas, utilidades y dependencias.

## Estructura General

```text
api_general_v2/
|-- .env
|-- .gitignore
|-- main.py
|-- requirements.txt
|-- requirementsServer140.txt
|-- .venv/
|-- app/
|   `-- config/
|       `-- db.py
|-- router/
|   `-- nombre_modulo/
|       `-- nombreRouter.py
|-- model/
|   `-- nombre_modulo/
|       `-- m_nombre_model.py
|-- schema/
|   `-- nombre_schema.py
|-- utils/
|   |-- token.py
|   `-- func.py
|-- package/
`-- __pycache__/
```

## Archivos Raiz

### `main.py`

Archivo principal de la API.

Contenido esperado:

- instancia principal de FastAPI.
- configuracion de CORS.
- middleware global.
- importacion y registro de routers.
- configuracion general de arranque de la API.

Estructura concreta:

```python
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

app = FastAPI()

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

from router.nombre_modulo.nombreRouter import nombre_router

app.include_router(nombre_router, tags=["Nombre Modulo"])
```

### `.env`

Archivo de variables de entorno.

Contenido esperado:

- credenciales.
- host de base de datos.
- usuarios.
- claves.
- puertos.
- nombres de bases de datos.

No deberia contener logica de la aplicacion.

### `.gitignore`

Archivo para excluir carpetas o archivos que no deben subirse al repositorio.

Contenido esperado:

- `.venv/`
- `__pycache__/`
- archivos temporales.
- archivos locales de configuracion, si corresponde.

### `requirements.txt`

Archivo con dependencias necesarias para ejecutar el proyecto.

Contenido esperado:

- FastAPI.
- Uvicorn.
- SQLAlchemy u otras librerias de conexion.
- librerias auxiliares usadas por la API.


### `.venv/`

Entorno virtual del proyecto.

Contiene las dependencias instaladas localmente para ejecutar la API sin depender de la instalacion global de Python.

## `app/`

Carpeta de configuracion interna de la aplicacion.

Estructura esperada:

```text
app/
`-- config/
    `-- db.py
```

### `app/config/db.py`

Archivo de configuracion de conexiones a base de datos.

Contenido esperado:

- lectura de variables de entorno.
- construccion de conexiones o engines.
- definicion de conexiones disponibles para los modelos.
- configuracion centralizada para acceder a bases de datos.

Estructura concreta:

```python
from sqlalchemy import create_engine

engine_nombre = create_engine("mysql+pymysql://usuario:clave@host/base")
```

## `router/`

Carpeta donde se organizan las rutas de la API por modulo.

Estructura esperada:

```text
router/
`-- nombre_modulo/
    `-- nombreRouter.py
```

### `router/nombre_modulo/nombreRouter.py`

Archivo de rutas del modulo.

Contenido esperado:

- creacion de un `APIRouter`.
- endpoints del modulo.
- parametros recibidos por ruta, query, body o form.
- dependencias del endpoint, como validacion de token.
- llamada al modelo correspondiente.
- respuesta del endpoint.

Estructura concreta:

```python
from fastapi import APIRouter, Depends
from utils.token import verify_token
from model.nombre_modulo.m_nombre_model import obtener_datos_modelo

nombre_router = APIRouter()

@nombre_router.get("/nombre_modulo/{codigo}")
def obtener_datos(codigo: str, permisos=Depends(verify_token)):
    return obtener_datos_modelo(codigo)
```

## `model/`

Carpeta donde se organiza la logica de datos por modulo.

Estructura esperada:

```text
model/
`-- nombre_modulo/
    `-- m_nombre_model.py
```

### `model/nombre_modulo/m_nombre_model.py`

Archivo del modelo del modulo.

Contenido esperado:

- importacion de conexiones desde `app/config/db.py`.
- funciones de consulta.
- SQL o llamadas a datos.
- manejo de resultados.
- retorno de datos hacia el router.
- manejo de errores propios de la consulta.

Estructura concreta:

```python
from app.config.db import engine_nombre

def obtener_datos_modelo(codigo):
    try:
        with engine_nombre.connect() as conn:
            query = "SELECT campo_1, campo_2 FROM nombre_tabla WHERE codigo = %s"
            values = (codigo,)
            result = conn.execute(query, values)
            return result.fetchall()
    except Exception as error:
        return {"message": "Error al obtener datos: " + str(error)}
```

## `schema/`

Carpeta para schemas o estructuras de datos.

Estructura esperada:

```text
schema/
`-- nombre_schema.py
```

### `schema/nombre_schema.py`

Archivo de schema.

**Crear un schema solo cuando es necesario**, no para cada endpoint por sistema:

- Necesario cuando el endpoint recibe un **body** (POST/PUT/PATCH) con varios campos que requieren validacion estructurada, o cuando se define un `response_model` para estandarizar la respuesta.
- No necesario cuando el endpoint solo recibe parametros simples de **ruta o query** (`str`, `int`, etc.): FastAPI ya los valida con el type hint del parametro, como en el ejemplo de `router/nombre_modulo/nombreRouter.py` (`codigo: str`), que no requiere ningun schema.

Contenido esperado (cuando aplica):

- modelos Pydantic.
- estructura esperada de datos de entrada.
- definicion de campos requeridos.
- tipos de datos.

Estructura concreta:

```python
from pydantic import BaseModel

class NombreRequest(BaseModel):
    codigo: str
    estado: str
```

## `utils/`

Carpeta de utilidades reutilizables de la API.

Estructura esperada:

```text
utils/
|-- token.py
`-- func.py
```

### `utils/token.py`

Archivo para utilidades de token.

Contenido esperado:

- validacion de token.
- lectura o decodificacion de token.
- dependencias reutilizables por routers.

Estructura concreta:

```python
def verify_token():
    pass
```

### `utils/func.py`

Archivo para funciones auxiliares compartidas.

Contenido esperado:

- helpers de respuesta.
- manejo comun de errores.
- transformaciones reutilizables.
- funciones compartidas por distintos modulos.

## `package/`

Carpeta destinada a paquetes internos, componentes reutilizables o codigo propio que pueda ser compartido por distintas partes del proyecto.

## `__pycache__/`

Carpeta generada automaticamente por Python.

Contiene cache de archivos compilados y no deberia modificarse manualmente.

## Resumen De Estructura

```text
main.py
    archivo principal de FastAPI

app/config/db.py
    configuracion de conexiones

router/nombre_modulo/nombreRouter.py
    rutas y endpoints del modulo

model/nombre_modulo/m_nombre_model.py
    consultas y logica de datos del modulo

schema/nombre_schema.py
    estructura de datos y validaciones

utils/
    utilidades compartidas
```

Esta organizacion permite separar entrada principal, configuracion, rutas, modelos, schemas, utilidades y dependencias del proyecto.
