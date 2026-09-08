# bot_olt — Bot de Telegram para consultas OLT

Bot de consulta de datos de OLT sobre el esquema `Aden`. Sigue las convenciones
de `README_PYTHON_BOT.md` del repositorio de estructura de GERET.

Las consultas son de solo lectura. La unica escritura es el **reseteo de
contrasena** de usuarios del portal, restringido por perfil.

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
|       |-- trafico.py      logica: trafico por puerto PON
|       |-- usuarios.py     logica: reseteo de contrasena (escritura)
|       |-- sesion.py       logica: login, clave del bot y bloqueo
|       `-- auditoria.py    registro de eventos en OLT_BOT_AUDITORIA
|-- utils/
|   |-- func.py             validadores, formateo y exportacion (excel/csv/txt)
|   |-- limites.py          cache de autorizacion, rate limit y silenciado
|   |-- seguridad.py        hash PBKDF2 de la clave del bot
|   `-- logger.py           configuracion de loguru
|-- BD/
|   |-- OLT_BOT_USUARIOS.sql     DDL: usuarios autorizados
|   |-- OLT_BOT_USUARIOS_v2.sql  ALTER: clave del bot y sesion
|   `-- OLT_BOT_AUDITORIA.sql    DDL: registro de auditoria
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

### Opciones privilegiadas

| Opcion | Tabla | Modulo |
|---|---|---|
| 🔑 Resetear contrasena | `OLT_USUARIOS` (escritura) | `usuarios.py` |
| 🆕 Asignar clave del bot | `OLT_BOT_USUARIOS` (escritura) | `sesion.py` |

Ademas, todo usuario autenticado tiene **Cambiar mi clave** y **Cerrar sesion**.

Solo la ven los perfiles listados en `PERFILES_ADMIN` del `.env`. Replica el
`case "changePassw"` del portal: deja la clave en `123456`, actualiza
`last_connection` y pone `estado = 1`.

Flujo: pide el nombre de usuario exacto → si no existe, avisa y cancela → si
devuelve mas de una coincidencia, cancela por seguridad → si hay exactamente
una, muestra usuario y estado actual y pide confirmacion con botones. Cada
reseteo ejecutado queda registrado en `log/bot.log` con quien lo solicito.

## Acceso

Son dos capas. Primero, el `chat_id` debe estar registrado y activo en
`OLT_BOT_USUARIOS`; el alta la hace el responsable con el `.sql` de `BD/`.
Segundo, la persona debe autenticarse con su **clave propia del bot**.

### Clave del bot

Es **independiente de la del portal**: la clave del portal nunca viaja por
Telegram, y si la del bot se filtra, la cuenta del portal no se ve afectada.
Por eso tampoco usa MD5 sino PBKDF2-SHA256 con salt por usuario
(`utils/seguridad.py`, solo libreria estandar).

El login pide unicamente la clave — el `chat_id` ya identifica a la persona —
y **el mensaje que la contiene se borra del chat apenas se procesa**.

| Situacion | Que hace el bot |
|---|---|
| Sin clave asignada | Pide solicitarla a un administrador |
| Sin sesion vigente | Pide la clave |
| Clave temporal | Obliga a cambiarla antes de mostrar el menu |
| Clave incorrecta | Avisa cuantos intentos quedan |
| Intentos agotados | Bloquea la cuenta por `BLOQUEO_LOGIN_MINUTOS` |

La sesion dura `SESION_HORAS` y se guarda en la base, asi que sobrevive a un
reinicio del bot. El contador de intentos fallidos tambien vive en la base:
reiniciar el proceso o cambiar de chat no lo reinicia.

### Permisos

El `perfil` sale de **`OLT_USUARIOS`** (el del portal), no de la tabla del bot,
para que los permisos queden siempre alineados con los de la web. En el portal
el perfil `1` es administrador, de ahi `PERFILES_ADMIN=1`.

Si esa variable esta vacia, nadie ve las opciones privilegiadas: el default es
el mas restrictivo, para que un `.env` mal configurado no abra permisos.

## Proteccion de la base

El bot es publico: cualquiera que conozca su username puede escribirle. Como
consulta una base de produccion, `utils/limites.py` filtra cada mensaje antes
de que llegue a la base, en este orden:

1. **Silenciados** — a quien insistio sin estar autorizado se le deja de
   responder por un rato. No cuesta nada evaluarlo.
2. **Limite de peticiones** — ventana deslizante por `chat_id`. Se aplica
   *antes* de la verificacion, que es la que puede terminar en una query.
3. **Autorizacion cacheada** — se cachea tambien el resultado negativo; sin
   eso, un no autorizado generaria una consulta por cada mensaje enviado.

Todo el estado vive en memoria del proceso y se pierde al reiniciar, que es lo
buscado. Los valores se ajustan por `.env` (ver `.env.example`).

Las escrituras privilegiadas revalidan el permiso contra la base ignorando el
cache, para no depender de un permiso que pudo revocarse dentro del TTL.

**Entradas del usuario**: todas las queries usan parametros ligados
(`:usuario`, `:fecha`, `:ip`) — nunca interpolacion de texto. Ademas, cada dato
pasa por un validador de `utils/func.py` antes de llegar a la capa de datos.

## Auditoria

Todo evento queda en `OLT_BOT_AUDITORIA` (DDL y consultas de auditoria en
`BD/OLT_BOT_AUDITORIA.sql`), con quien, que y cuando:

| Accion | Cuando se registra |
|---|---|
| `INICIO_SESION` | El usuario abre el bot con `/start` o `/menu` |
| `CONSULTA` | Se elige una opcion del menu, y de nuevo al enviar su parametro |
| `DESCARGA` | Se descarga un Excel, CSV o TXT |
| `RESETEO_PASSWORD` | Se confirma un reseteo (guarda a quien) |
| `ACCESO_DENEGADO` | Escribe un `chat_id` no autorizado |
| `LOGIN_OK` / `LOGIN_FALLIDO` | Resultado de cada intento de login |
| `CUENTA_BLOQUEADA` | Se agotaron los intentos permitidos |
| `LOGOUT` | El usuario cierra su sesion |
| `CAMBIO_CLAVE_BOT` | El usuario cambia su propia clave |
| `ASIGNACION_CLAVE_BOT` | Un admin asigna la clave inicial a alguien |

**Es transversal por construccion.** Las consultas se auditan en el punto de
despacho de `handle_text`, no dentro de cada handler: una opcion nueva en
`OPCIONES` queda auditada sin escribir una linea extra.

La escritura es sincronica —se espera antes de responder— pero `registrar` va
decorada con `@_safe(0)`: si el INSERT falla, el evento se vuelca a
`log/bot.log` con el prefijo `AUDITORIA NO REGISTRADA` y la operacion del
usuario continua. Un problema en la tabla de auditoria no deja el bot inutil.

La tabla no se purga automaticamente. La sentencia de purga esta documentada
al final del `.sql` para ejecutarla cuando corresponda.

## Pruebas

```bash
python -m tests.test_validadores    # validadores y limites, sin base de datos
python -m tests.test_conexion       # conexion, consultas y exportacion
```

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
