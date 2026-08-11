# AGENTS.md

Guia para agentes de IA (y desarrolladores que usan agentes) que trabajan sobre proyectos de GERET.

Este repositorio (`estructura`) es la **documentacion oficial de estructura y convenciones** de GERET. No contiene una aplicacion: define como deben organizarse los proyectos. Antes de crear o modificar codigo en cualquier proyecto de GERET, **lee primero el documento que corresponda aqui** y sigue sus convenciones en vez de inventar una estructura nueva.

> Nota: existe tambien un `CLAUDE.md` con el mismo proposito para Claude Code. `AGENTS.md` y `CLAUDE.md` describen lo mismo; manten ambos sincronizados si actualizas uno.

> ⚠️ **Lectura obligatoria antes de aplicar cualquier convencion sobre un proyecto real**: [`README_LIMITES.md`](./README_LIMITES.md). Resume, entre otras cosas, que ningun README de aqui se aplica solo — se necesita confirmacion del usuario responsable —, que ningun dato faltante se inventa (se registra en `SOLICITUDES_PENDIENTES.txt`) y que ningun paso de despliegue (`README_DEPLOY.md`) se ejecuta sin autorizacion explicita.
>
> Existe tambien una skill de Claude Code en [`.claude/skills/geret-estructura/SKILL.md`](./.claude/skills/geret-estructura/SKILL.md) que aplica estas reglas paso a paso; copiala al proyecto real donde se vaya a generar codigo si ese proyecto usa Claude Code.

---

## Como elegir el documento correcto

Identifica el **tipo de tarea** y abre el documento indicado **antes de empezar**:

| Si vas a... | Tipo de proyecto | Lee este documento |
|-------------|------------------|--------------------|
| Crear/modificar una **API** (endpoints, FastAPI) | Python / API | [`README_PYTHON_API.md`](./README_PYTHON_API.md) |
| Crear/modificar un **script o proceso batch** (cron, ETL, extraccion de datos) | Python / Script | [`README_PYTHON_SCRIPT.md`](./README_PYTHON_SCRIPT.md) |
| Crear/modificar un **bot de Telegram** (handlers, menus, envios automaticos) | Python / Bot | [`README_PYTHON_BOT.md`](./README_PYTHON_BOT.md) |
| Crear/modificar una **web o pantalla** (vistas, modulos, PHP) | PHP Web | [`README_PHP_WEB.md`](./README_PHP_WEB.md) |
| Crear/modificar una **aplicacion React** (SPA, frontend) | React Web | [`README_REACT_WEB.md`](./README_REACT_WEB.md) |
| Agregar/instalar **librerias** o entender para que sirve cada una | Transversal | [`README_LIBRERIAS.md`](./README_LIBRERIAS.md) |
| Crear el `requirements.txt` de un proyecto nuevo | Transversal | [`requirements.example.txt`](./requirements.example.txt) + [`README_LIBRERIAS.md`](./README_LIBRERIAS.md) |
| **Desplegar/actualizar/revertir** un proyecto en un servidor | Transversal | [`README_DEPLOY.md`](./README_DEPLOY.md) |
| Saber que puede y que **no puede** hacer el agente (produccion, datos faltantes) | Transversal | [`README_LIMITES.md`](./README_LIMITES.md) |
| Entender el repo de documentacion en si | Indice | [`README.md`](./README.md) |

### Como distinguir API vs Script vs Bot (la duda mas comun)

- **API** → algo que **queda corriendo** y responde peticiones HTTP (endpoints, rutas, tokens). Hay servidor (`uvicorn`), `router/`, `schema/`. Base: `README_PYTHON_API.md`.
- **Script** → algo que **se ejecuta, hace su trabajo y termina**, normalmente programado por cron. No hay endpoints; recibe parametros por `sys.argv`, lee de unas bases e inserta en otras. Hay scripts raiz, `config/`, `model/`, `ejecutar.sh`. Base: `README_PYTHON_SCRIPT.md`.
- **Bot** → algo que **queda corriendo** pero no atiende HTTP: escucha mensajes de Telegram (long polling) y ademas dispara tareas periodicas propias. Hay `bot.py`, handlers, `python-telegram-bot`, PM2. Base: `README_PYTHON_BOT.md`.

**No lo infieras solo por el nombre del proyecto o de la carpeta** (un nombre no siempre deja claro si es API o Script). Si tras leer la tarea no es evidente cual de los dos criterios de arriba aplica, **pregunta directamente al usuario** ("¿esto es una API con endpoints o un script/proceso batch?") antes de elegir el documento y empezar a generar archivos.

---

## Reglas al generar codigo

1. **Sigue la estructura del documento elegido** (carpetas, nombres de archivo y flujo). No introduzcas un layout distinto sin justificarlo.
2. **Respeta los nombres y prefijos** definidos: `nombre_modulo`, modelos con `m_` (Python) o `f_`/`s_` (PHP), routers `nombreRouter.py`, componentes en PascalCase y hooks `use*` (React), etc.
3. **Conexiones centralizadas**: en Python van en `config/` (o `app/config/`), en PHP en `app/config/conexion.php`. Ningun archivo abre conexiones por su cuenta.
4. **Consultas separadas de la logica**: las queries viven en `model/` (Python) o en `functions/` (PHP).
5. **Credenciales solo en `.env`**, nunca escritas en el codigo. Si encuentras credenciales hardcodeadas en un proyecto existente, sugierelo como mejora pero no las repitas en archivos nuevos.
6. **Dependencias**: parte de `requirements.example.txt` y consulta `README_LIBRERIAS.md`. Para el servidor `140` usa su archivo aparte (`requirementsServer140.txt`); es otro ambiente (Python 3.6, Linux) y no se sobrescribe con un `pip freeze` de desarrollo.
7. **Idioma**: la documentacion y los nombres estan en espanol. Mantenlo al generar archivos para estos proyectos.

---

## Flujo recomendado para el agente

1. Detecta el tipo de tarea (API / Script / PHP Web / React Web / dependencias).
2. Abre y lee el documento correspondiente de la tabla.
3. Replica la estructura y el flujo que describe.
4. Si la tarea necesita algo no cubierto por la documentacion, primero propon actualizar el documento de convenciones y luego implementa.
5. Si agregas un nuevo tipo de proyecto o documento, registralo en la tabla de [`README.md`](./README.md), en este `AGENTS.md` y en `CLAUDE.md`.

---

## Mapa rapido de los proyectos reales

- **API** → `api_general_v2` (sigue `README_PYTHON_API.md`).
- **Script/Proceso** → `SCRIPT_5G_ENIQ` (sigue `README_PYTHON_SCRIPT.md`).
- **Bot (Telegram)** → `bot_crm` (sigue `README_PYTHON_BOT.md`).
- **Web (PHP)** → proyectos PHP de GERET (siguen `README_PHP_WEB.md`).
- **Web (React)** → SPAs/frontend de GERET (siguen `README_REACT_WEB.md`).
