---
name: geret-estructura
description: Use this skill when creating, scaffolding, or reviewing files for a GERET project (PHP web, React SPA, Python FastAPI API, Python batch/cron script, or Python Telegram bot) that must follow the conventions defined in the "estructura" reference repository. It applies the layout from README_PHP_WEB.md / README_REACT_WEB.md / README_PYTHON_API.md / README_PYTHON_SCRIPT.md / README_PYTHON_BOT.md, but only after confirming scope with the user, never invents missing data, and never performs deployment/production actions. Triggers on requests like "crea el modulo", "genera el router/script/modelo/handler", "agrega una opcion al menu del bot", "aplica la estructura/convencion de GERET", "scaffolding de un proyecto GERET", "sube esto a produccion" (to redirect to confirmation, not to execute).
---

# Skill: Aplicar la estructura GERET

Esta skill encapsula como un agente debe generar o revisar codigo para un proyecto de GERET siguiendo las convenciones del repositorio `estructura`, sin saltarse los limites definidos en `references/README_LIMITES.md`.

Los documentos de convenciones estan copiados en la carpeta `references/` de esta skill (origen: https://github.com/DevGeret/estructura). Son material de referencia, no codigo ejecutable.

## Paso 1 — Identificar el tipo de proyecto

Usa la tabla de `references/AGENTS.md` para decidir cual convencion aplica:

| Tarea | Documento a leer |
|-------|-------------------|
| API / endpoints (FastAPI) | `references/README_PYTHON_API.md` |
| Script/proceso batch (cron, ETL) | `references/README_PYTHON_SCRIPT.md` |
| Bot de Telegram (handlers, menus, envios) | `references/README_PYTHON_BOT.md` |
| Vista o modulo web (PHP) | `references/README_PHP_WEB.md` |
| Aplicacion React (SPA/frontend) | `references/README_REACT_WEB.md` |
| Librerias / `requirements.txt` | `references/README_LIBRERIAS.md` + `references/requirements.example.txt` |
| Despliegue a servidor | `references/README_DEPLOY.md` (ver limites abajo) |

Si la tarea es ambigua (por ejemplo, API vs Script), usa la seccion "Como distinguir API vs Script" de `references/AGENTS.md` antes de asumir un tipo. No lo infieras solo por el nombre del proyecto o carpeta: si aun asi no queda claro, pregunta directamente al usuario si es una API o un Script antes de elegir el documento.

## Paso 2 — Leer el documento correspondiente antes de escribir codigo

No inventes una estructura distinta a la documentada. Replica nombres, prefijos (`nombre_modulo`, `s_`, `f_`, `m_`) y el flujo descrito (vista → script → funciones/model → SQL, o router → model → engine).

## Paso 3 — Confirmar el alcance antes de crear archivos

Antes de generar la estructura completa de un modulo o proyecto nuevo:

1. Presenta un resumen breve de las carpetas y archivos que vas a crear y que convencion aplicas.
2. Espera confirmacion del usuario, salvo que ya haya autorizado explicitamente ese alcance en la conversacion actual.

Esto aplica incluso si el usuario ya aprobo una tarea similar antes: una autorizacion puntual no se extiende automaticamente a otros modulos o proyectos.

## Paso 4 — Si falta informacion, no inventar

Si necesitas un dato que no esta en la conversacion ni en el codigo existente (nombre de conexion, tabla, endpoint, credencial, regla de negocio):

1. Deja un `TODO` explicito en el archivo generado indicando que falta y a quien preguntarle.
2. Crea o agrega una entrada en `SOLICITUDES_PENDIENTES.txt` en la raiz del proyecto, con el formato:
   ```
   [YYYY-MM-DD] Modulo/archivo: <ruta>
   Falta: <dato o decision faltante>
   Motivo: <para que se necesita>
   Responsable sugerido: <si se conoce, o "sin definir">
   ```
3. Menciona en tu respuesta al usuario que quedo una solicitud pendiente registrada ahi.

Detalle completo de esta regla en `references/README_LIMITES.md`.

## Paso 5 — Nunca ejecutar pasos de despliegue/produccion por iniciativa propia

Si la tarea implica subir cambios a un servidor, reiniciar un servicio, hacer `git push`/`pull` sobre una rama de produccion o correr migraciones:

- No lo ejecutes automaticamente. Señala que ese paso existe en `references/README_DEPLOY.md` y que requiere autorizacion explicita y puntual del responsable del proyecto.
- Si el usuario ya dio esa autorizacion explicita para esta tarea concreta, procede siguiendo exactamente los comandos de `references/README_DEPLOY.md`.

## Paso 6 — Idioma y nombres

Genera nombres, comentarios y archivos en español, siguiendo las convenciones de nomenclatura ya usadas en el proyecto (`nombre_modulo`, `s_`, `f_`, `m_`, etc.), tal como indica `references/AGENTS.md`.

## Referencia rapida de documentos

Todos dentro de `references/`:

- Convenciones de estructura: `README_PHP_WEB.md`, `README_REACT_WEB.md`, `README_PYTHON_API.md`, `README_PYTHON_SCRIPT.md`, `README_PYTHON_BOT.md`.
- Librerias: `README_LIBRERIAS.md`.
- Despliegue: `README_DEPLOY.md`.
- Limites del agente: `README_LIMITES.md`.
- Indice general de tipos de proyecto: `AGENTS.md`.
