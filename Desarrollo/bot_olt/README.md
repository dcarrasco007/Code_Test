# bot_olt — Bot de Telegram para consultas OLT

Bot de consulta de datos de OLT sobre el esquema `Aden`. Sigue las convenciones
de `README_PYTHON_BOT.md` del repositorio de estructura de GERET.

**Etapa actual: solo lectura.** El bot unicamente ejecuta `SELECT`. La estructura
esta preparada para agregar escrituras mas adelante (ver "Extender" abajo).

## Estructura

```text
bot_olt/
|-- .env.example            plantilla de variables (el .env real no se versiona)
|-- .gitignore
|-- requirements.txt
|-- ecosystem.config.js     ejecucion permanente con PM2
|-- bot.py                  punto de entrada
|-- app/
|   `-- config.py           engine de la base (unico lugar que los crea)
|-- robot/
|   |-- model/
|   |   `-- consultas.py    todas las queries SQL
|   `-- scripts/
|       |-- cmd.py          handlers, menu y descargas
|       |-- inventario.py   logica: inventario de OLT
|       |-- gpon.py         logica: puertas PON / ocupacion
|       |-- alarmas.py      logica: alarmas criticas
|       `-- trafico.py      logica: trafico por puerto PON
|-- utils/
|   |-- func.py             errores, formateo y exportacion (excel/csv/txt)
|   `-- logger.py           configuracion de loguru
|-- BD/
|   `-- OLT_BOT_USUARIOS.sql   DDL para que lo ejecute el responsable
|-- tests/
|   `-- test_conexion.py
|-- log/
`-- SOLICITUDES_PENDIENTES.txt  datos que faltan por confirmar
```

## Consultas disponibles

| Opcion del menu | Tablas | Modulo |
|---|---|---|
| 🗄 Inventario OLT | `OLT_SERVER` | `inventario.py` |
| 🔌 Puertas PON | `OLT_SERVER`, `OLT_ONT_PCS`, `OLT_TRAFICOGPON`, `OLT_PUERTOS_UPLINKS` | `gpon.py` |
| ↳ pide fecha | `OLT_TRAFICOGPON` es grande: la consulta siempre se acota por fecha | |
| 🚨 Alarmas criticas | `OLT_ALARMA_CRITICAL_LOS` | `alarmas.py` |
| 📈 Trafico PON | `OLT_TRAFICOGPON` | `trafico.py` |

Cada resultado se muestra como vista previa en el chat y se puede descargar en
**Excel, CSV o TXT** con los botones que acompanan la respuesta.

## Acceso

El bot solo responde a los `chat_id` registrados y activos en `OLT_BOT_USUARIOS`.
Si alguien escribe sin estar autorizado, el bot le responde con su `chat_id` para
que lo solicite. El alta la hace el responsable con el `.sql` de `BD/`.

## Puesta en marcha

```bash
python -m venv env && source env/bin/activate
pip install -r requirements.txt
cp .env.example .env      # completar BOT_TOKEN y CONECTION_ADEN
python -m tests.test_conexion
python bot.py
```

En el servidor, gestionado por PM2:

```bash
pm2 start ecosystem.config.js
pm2 logs bot_olt
```

## Extender

**Agregar una consulta nueva**

1. Escribir la query en `robot/model/consultas.py` (con parametros `:nombre`,
   `async` + `asyncio.to_thread`, decorada con `@_safe`).
2. Crear el modulo en `robot/scripts/` con una funcion `obtener()` que retorne
   `(titulo, filas)`.
3. Registrar la entrada en el diccionario `OPCIONES` de `cmd.py`.

El teclado y el routing se arman solos a partir de ese diccionario.

**Agregar un formato de descarga**

Escribir `_a_<formato>_bytes(df, titulo)` en `utils/func.py` y sumarlo al
diccionario `EXPORTADORES`. Los botones se dibujan a partir de `FORMATOS` en
`cmd.py`.

**Habilitar escrituras (INSERT)**

`consultas.py` tiene al final la seccion y la plantilla del patron
`registrar_*` con `engine.begin()`. El DDL de la tabla destino se entrega como
archivo `.sql` en `BD/` para que lo ejecute el responsable: ni el bot ni el
agente de IA ejecutan DDL contra la base.

**Agregar envio automatico por horario**

Definir la tarea periodica en `bot.py` (`post_init` + `app.create_task`) y las
listas de horarios en el modulo correspondiente, como describe
`README_PYTHON_BOT.md`.

## Pendientes

Ver `SOLICITUDES_PENDIENTES.txt`: faltan credenciales, la creacion de la tabla
de usuarios y confirmar las reglas de negocio de la ocupacion de puertas PON.
