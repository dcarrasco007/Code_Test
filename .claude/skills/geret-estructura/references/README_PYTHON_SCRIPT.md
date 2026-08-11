# Estructura Base Para Scripts/Procesos Python

Este README describe la estructura general utilizada por los proyectos tipo **script de proceso** (batch) en Python de GERET.

> **¿Es este el documento correcto?** Este README aplica solo si el proceso **se ejecuta, hace su trabajo y termina** (batch/cron/ETL), sin servidor ni endpoints. Si el proyecto en cambio queda corriendo y responde peticiones HTTP, no es este documento: usa `README_PYTHON_API.md`. El nombre del proyecto no siempre lo deja claro — ante la duda, confirmalo con el usuario responsable.

Aplica a procesos que se ejecutan por linea de comandos, normalmente de forma programada (cron), para extraer, transformar e insertar datos entre distintas bases de datos. Por ejemplo:

- `SCRIPT_5G_ENIQ`

A diferencia del proyecto API (FastAPI), aqui no hay servidor ni endpoints: cada script es un punto de entrada ejecutable que orquesta conexiones, consultas y carga de datos.

## Estructura General

```text
proceso_python/
|-- .env
|-- .gitignore
|-- requirements.txt
|-- ejecutar.sh
|-- nombre_script.py
|-- env/
|-- config/
|   |-- db_engine.py
|   `-- db_class.py
|-- model/
|   `-- m_nombre_model.py
|-- test/
|   `-- test_nombre.py
`-- logs/
```

## Archivos Raiz

### `nombre_script.py`

Punto de entrada ejecutable del proceso.

Cada script representa una etapa o un proceso completo (por ejemplo: extraer, completar, sincronizar).

Contenido esperado:

- funcion `main()` con la logica del proceso.
- lectura de parametros por `sys.argv` (fecha, rango de horas, flags como `--run`).
- importacion de conexiones desde `config/`.
- importacion de consultas desde `model/`.
- transformacion de datos (por ejemplo con `polars` o `pandas`).
- insercion o actualizacion por lotes en la base destino.
- registro de avance con `loguru`.

Estructura concreta:

```python
from loguru import logger as log
from config.db_engine import engine_cco
from model.m_nombre_model import obtener_datos
import sys

def main():
    fecha = sys.argv[1]
    log.info(f"Procesando fecha: {fecha}")

    datos = obtener_datos(fecha)
    # transformar e insertar...

if __name__ == "__main__":
    main()
```

### `ejecutar.sh`

Script orquestador para ejecucion programada (cron).

Contenido esperado:

- activacion del entorno virtual.
- llamada a uno o varios scripts en orden.
- paso de parametros (por ejemplo la fecha del dia anterior).
- redireccion de la salida a archivos en `logs/`.

Estructura concreta:

```bash
#!/bin/bash
AYER=$(date -d "yesterday" +%F)
source env/bin/activate
python nombre_script.py $AYER 0 24 > logs/ejecucion_${AYER}.log
deactivate
```

### `.env`

Archivo de variables de entorno.

Contenido esperado:

- cadenas de conexion a bases de datos.
- host, puerto, usuario, clave y nombre de base por cada conexion.
- no debe contener logica del proceso.

Las credenciales no deben quedar escritas dentro de los scripts; deben leerse desde aqui.

### `.gitignore`

Excluye lo que no debe subirse al repositorio:

- `env/` o `.venv/`
- `__pycache__/`
- `.env`
- archivos de `logs/` y temporales.

### `requirements.txt`

Dependencias necesarias para ejecutar el proceso.

Suelen incluir:

- librerias de datos: `polars`, `pandas`.
- conexion a base de datos: `SQLAlchemy`, `PyMySQL`, `mysql-connector-python`, `pyodbc`, `pymssql`.
- utilidades: `loguru`, `python-dotenv`.

### `env/`

Entorno virtual del proyecto.

Contiene las dependencias instaladas localmente para ejecutar el proceso sin depender de la instalacion global de Python.

## `config/`

Carpeta de definicion centralizada de conexiones a base de datos.

La idea es que ningun script abra conexiones por su cuenta: todas se definen aqui y se importan.

### `config/db_engine.py`

Conexiones tipo engine (SQLAlchemy), normalmente para MySQL.

Contenido esperado:

- lectura de variables de entorno con `dotenv`.
- creacion de engines reutilizables.
- configuracion de pool de conexiones.

Estructura concreta:

```python
from sqlalchemy import create_engine
import os
from dotenv import load_dotenv

load_dotenv()

def get_engine(conn_str_env: str):
    return create_engine(os.getenv(conn_str_env), pool_pre_ping=True)

engine_rasp = get_engine("CONECTION_RASP")
engine_cco  = get_engine("CONECTION_CCO")
```

### `config/db_class.py`

Conexiones por clase (por ejemplo `pyodbc` / FreeTDS para Sybase u otros motores).

Contenido esperado:

- clase que encapsula la conexion y un metodo para ejecutar consultas.
- lectura de credenciales por prefijo desde `.env`.
- instancias listas para importar.

Estructura concreta:

```python
import os, pyodbc
from dotenv import load_dotenv

class DatabaseConnection:
    def __init__(self, prefix):
        load_dotenv()
        self.conn = pyodbc.connect(
            f"DRIVER={{FreeTDS}};SERVER={os.getenv(f'HOST_{prefix}')};"
            f"PORT={os.getenv(f'PORT_{prefix}')};DATABASE={os.getenv(f'DATABASE_{prefix}')};"
            f"UID={os.getenv(f'USER_{prefix}')};PWD={os.getenv(f'PASSWORD_{prefix}')};TDS_Version=5.0;"
        )

    def obtener_datos(self, sql):
        cursor = self.conn.cursor()
        cursor.execute(sql)
        return cursor.fetchall()

conn_origen = DatabaseConnection("PREFIJO")
```

## `model/`

Carpeta donde se organizan las consultas por fuente o dominio de datos.

Estructura esperada:

```text
model/
`-- m_nombre_model.py
```

### `model/m_nombre_model.py`

Archivo de consultas de una fuente.

Contenido esperado:

- importacion de las conexiones desde `config/`.
- funciones que ejecutan SQL y retornan los datos crudos.
- transformacion minima a estructuras utiles (diccionarios, listas, tuplas).
- manejo de errores de la consulta.

Estructura concreta:

```python
from config.db_engine import engine_cco
from sqlalchemy import text
from loguru import logger as log

def obtener_datos(fecha):
    try:
        with engine_cco.connect() as conn:
            query = text("SELECT campo_1, campo_2 FROM tabla WHERE FECHA = :fecha")
            return conn.execute(query, {"fecha": fecha}).mappings().all()
    except Exception as e:
        log.error(f"Error al obtener datos: {e}")
        return None
```

El prefijo `m_` identifica los archivos de modelo/consulta.

## `test/`

Carpeta de pruebas simples del proceso.

Normalmente contiene pruebas de conexion a las bases o de validacion de consultas, para verificar que el entorno esta correctamente configurado antes de correr el proceso completo.

## `logs/`

Carpeta donde se guardan los registros de ejecucion.

El orquestador (`ejecutar.sh`) redirige aqui la salida de cada corrida. Util para revisar avance, tiempos y errores de procesos programados.

## Convenciones Generales

- Cada script raiz es un punto de entrada independiente con `main()` y `if __name__ == "__main__"`.
- Los parametros se reciben por `sys.argv` (fecha, rango de horas, flags).
- Las conexiones se definen solo en `config/`; las consultas solo en `model/`.
- Las credenciales viven en `.env`, nunca dentro del codigo.
- Las inserciones grandes se hacen por lotes (`executemany`) para no superar limites de la base.
- El logging se hace con `loguru`.

## Resumen De Estructura

```text
nombre_script.py
    punto de entrada del proceso (orquesta config + model)

config/db_engine.py
config/db_class.py
    conexiones centralizadas a las bases de datos

model/m_nombre_model.py
    consultas SQL por fuente y retorno de datos

ejecutar.sh
    orquestacion programada (cron) y redireccion a logs/

.env
    credenciales y cadenas de conexion
```

Esta organizacion permite separar punto de entrada, conexiones, consultas, orquestacion y configuracion de cada proceso batch.
