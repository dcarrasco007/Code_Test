# Limites del Agente de IA — README_LIMITES.md

Este documento define que puede y que **no puede** hacer un agente de IA (Claude Code u otro) cuando trabaja con esta documentacion o aplica sus convenciones sobre un proyecto real de GERET. Aplica junto con `AGENTS.md` / `CLAUDE.md`.

---

## 1. Naturaleza de este repositorio

- Este repositorio (`estructura`) y sus documentos (`README_PHP_WEB.md`, `README_PYTHON_API.md`, `README_PYTHON_SCRIPT.md`, `README_LIBRERIAS.md`, `README_DEPLOY.md`) son **material de referencia**, no un proyecto ejecutable ni una plantilla que se aplica sola.
- Ningun documento de esta carpeta autoriza por si mismo a un agente a modificar, desplegar o reiniciar un proyecto real. Toda aplicacion de estas convenciones sobre un proyecto real requiere **confirmacion del usuario responsable**.

## 2. El agente NO puede, sin confirmacion explicita

1. **No pasar a produccion**: no ejecutar `git push`/`git pull` sobre una rama de produccion, no reiniciar servicios (`systemctl restart`, supervisor, pm2), no correr migraciones de base de datos. Ver `README_DEPLOY.md` — esos comandos los ejecuta o autoriza puntualmente el responsable, no el agente por iniciativa propia.
2. **No aplicar la estructura automaticamente**: no crear carpetas/archivos de `README_PHP_WEB.md`, `README_PYTHON_API.md` o `README_PYTHON_SCRIPT.md` sobre un proyecto real sin que el usuario confirme que corresponde hacerlo en ese momento y con ese alcance.
3. **No inventar datos**: nombres de conexiones, tablas, campos, endpoints, credenciales o reglas de negocio no documentadas no se adivinan (ver seccion 4).
4. **No reescribir codigo existente** para "alinearlo" a la convencion sin avisar y pedir confirmacion primero.
5. **No commitear ni exponer secretos**: credenciales, `.env`, tokens u otra informacion sensible nunca se escriben en archivos versionados.

## 3. Base de Datos — reglas estrictas (sin excepciones)

Estas reglas son de la empresa, aplican a **todo proyecto GERET** y a **todo agente de IA**, y estan por encima de cualquier configuracion de modo automatico/autonomo:

1. **Prohibido al 100%** ejecutar `TRUNCATE`, `DELETE`, `UPDATE`, `INSERT`, `ALTER`, `DROP`, `CREATE`, `REPLACE` o cualquier migracion. El agente **nunca** ejecuta estas queries, ni siquiera con autorizacion del usuario en el chat: si son necesarias, se documentan en un archivo `.sql` (que hace, por que, riesgos, query, rollback si aplica) para que el responsable del proyecto la revise y la ejecute manualmente.
2. **`SELECT`, `SHOW`, `DESCRIBE`** (y demas queries de solo lectura) requieren **permiso explicito del usuario antes de cada ejecucion**, sin excepcion — incluso en modo automatico/autonomo. El agente no asume autorizacion previa ni la extiende de una query a otra: se pide permiso cada vez.
3. El agente **jamas ejecuta ninguna query directamente contra una base de datos real** por iniciativa propia, sea de lectura o de escritura. Toda ejecucion la realiza el responsable del proyecto, o el agente solo tras permiso explicito y puntual para esa query concreta (aplica unicamente a lectura, ver punto 2).

## 4. Que hacer cuando falta informacion

Si al aplicar una convencion (por ejemplo, generar un modulo PHP o un router de la API) falta un dato necesario — nombre de conexion, tabla, endpoint, credencial, decision de negocio —, el agente **no adivina**. En su lugar:

1. Deja un marcador explicito en el codigo generado:
   ```php
   // TODO: confirmar con el responsable — nombre de la conexion a usar
   ```
   ```python
   # TODO: confirmar con el responsable — nombre de la tabla de origen
   ```
2. Registra la solicitud en un archivo de texto plano en la raiz del proyecto: **`SOLICITUDES_PENDIENTES.txt`** (se crea si no existe). Cada entrada sigue este formato:
   ```
   [YYYY-MM-DD] Modulo/archivo: <ruta del archivo o modulo afectado>
   Falta: <que dato o decision falta>
   Motivo: <para que se necesita, que bloquea>
   Responsable sugerido: <si se conoce, o "sin definir">
   ```
3. Avisa al usuario en la misma respuesta que quedo una solicitud pendiente registrada en ese archivo, sin asumir que se resolvera despues por su cuenta.

## 5. Confirmar antes de aplicar estructura

Antes de generar la estructura completa de un modulo o proyecto nuevo, el agente presenta primero un resumen de lo que va a crear (carpetas, archivos, convenciones que aplica) y espera confirmacion, salvo que el usuario ya haya autorizado explicitamente ese alcance en la conversacion.

## 6. Alcance de la autorizacion

- Una autorizacion puntual (por ejemplo, "crea el modulo X") no se extiende a desplegar, borrar datos, tocar otros modulos o repetirse en otros proyectos.
- Ninguna autorizacion, puntual o de modo automatico/autonomo, habilita a ejecutar `TRUNCATE`, `DELETE`, `UPDATE`, `INSERT` u otra query de escritura (ver seccion 3) — esa prohibicion no tiene excepcion posible.
- Las reglas generales sobre acciones reversibles/irreversibles del `CLAUDE.md` del proyecto en el que se trabaja siguen aplicando por encima de este documento.

## 7. Checklist rapido para el agente

- [ ] ¿Es un cambio solo sobre esta documentacion de referencia? → proceder.
- [ ] ¿Es aplicar la estructura sobre un proyecto real? → confirmar alcance con el usuario antes de crear archivos.
- [ ] ¿Falta un dato para completar algo? → no inventar: dejar `TODO` + registrar en `SOLICITUDES_PENDIENTES.txt`.
- [ ] ¿Es un paso de despliegue/produccion (`README_DEPLOY.md`)? → nunca ejecutarlo sin autorizacion explicita y puntual del responsable.
- [ ] ¿Es una query contra la base de datos? → si es `TRUNCATE`/`DELETE`/`UPDATE`/`INSERT`/`ALTER`/`DROP`/`CREATE`/`REPLACE` o migracion: **nunca** se ejecuta, se documenta en `.sql` para el responsable. Si es `SELECT`/`SHOW`/`DESCRIBE`: pedir permiso explicito antes de cada ejecucion, sin excepcion.
