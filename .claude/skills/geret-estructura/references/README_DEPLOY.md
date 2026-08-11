# Guia de Despliegue (Deploy) — GERET

Este documento es **transversal**: aplica a los proyectos que siguen las convenciones de esta documentacion (`README_PHP_WEB.md`, `README_PYTHON_API.md`, `README_PYTHON_SCRIPT.md`) cuando se suben o actualizan en un servidor. El repositorio `estructura` en si no se despliega, no tiene nada ejecutable.

> ⚠️ **Un agente de IA no debe ejecutar los pasos de despliegue a produccion de este documento por su cuenta.** Ver [`README_LIMITES.md`](./README_LIMITES.md). Estos comandos son para que los ejecute (o autorice explicitamente) la persona responsable del proyecto.

---

## 1. Antes de empezar

- Acceso SSH al servidor y `git` instalado.
- URL del repositorio remoto (Bitbucket/GitHub/GitLab/servidor interno).
- El archivo `.env` de cada servidor **no viaja por git** (esta en `.gitignore`): se crea y configura a mano en cada ambiente.
- Backups: antes de un despliegue en un proyecto productivo, confirmar que hay forma de volver atras (commit/tag conocido, backup de base de datos si la tarea incluye migraciones).

---

## 2. Primera vez en el servidor (proyecto nuevo)

**Opcion A — clonar un repositorio que ya existe:**

```bash
cd /ruta/al/proyecto
git clone <url-del-repositorio> .
```

**Opcion B — iniciar el repositorio desde el servidor (proyecto que aun no tiene remoto):**

```bash
cd /ruta/al/proyecto
git init
git remote add origin <url-del-repositorio>
git add .
git commit -m "Version inicial"
git branch -M main
git push -u origin main
```

Despues de traer el codigo, cada tipo de proyecto tiene su propio paso de puesta en marcha (ver `README_PYTHON_API.md` / `README_PYTHON_SCRIPT.md` / `README_PHP_WEB.md`), por ejemplo crear `.venv`, instalar dependencias y configurar `.env`.

---

## 3. Despliegue normal (actualizar el servidor)

```bash
cd /ruta/al/proyecto
git status                            # confirmar que no hay cambios locales sin commitear
git fetch origin
git log HEAD..origin/main --oneline   # ver que commits van a llegar antes de aplicarlos
git pull origin main
```

Pasos posteriores segun el tipo de proyecto:

- **PHP Web**: normalmente no requiere reinicio; si hay cache de archivos estaticos (`assets/build`, `assets/dist`), regenerarla si corresponde.
- **Python API**: reinstalar dependencias si cambiaron y reiniciar el proceso.
  ```bash
  source .venv/bin/activate
  pip install -r requirements.txt      # o requirementsServer140.txt en el servidor 140
  # reiniciar el proceso segun como este corriendo (systemd, supervisor, pm2, screen, etc.)
  ```
- **Python Script**: no requiere reinicio de servicio; el cron ejecuta la version actualizada en su proxima corrida. Revisar `logs/` tras la siguiente ejecucion.

---

## 4. Ramas y entornos

- `main` (o `master`) representa lo que esta en produccion.
- Cambios se desarrollan en ramas separadas y se integran a `main` via merge/PR antes de desplegar; evitar trabajar directo sobre `main` en un proyecto productivo.
- El `.env` de cada ambiente (desarrollo, servidor 140, produccion) se gestiona por separado y nunca se pisa con el pull.

---

## 5. Reversion / Rollback

### a) Descartar cambios locales que aun no se commitearon

```bash
git status
git diff                    # revisar que se perderia antes de descartar
git restore <archivo>       # descartar cambios de un archivo puntual
git restore .               # descartar TODOS los cambios no commiteados (no reversible)
```

### b) Deshacer el ultimo commit local (todavia no pusheado)

```bash
git reset --soft HEAD~1     # deshace el commit, deja los cambios en staging
git reset --mixed HEAD~1    # deshace el commit y el staging, conserva los cambios en el working dir
git reset --hard HEAD~1     # DESTRUCTIVO: elimina el commit y los cambios por completo
```

### c) Revertir un commit que ya se pusheo a produccion (forma recomendada)

No reescribe el historial, por lo que es segura incluso si otros ya bajaron esos commits:

```bash
git log --oneline                 # localizar el hash del commit problematico
git revert <hash>
git push origin main
```

### d) Volver el servidor a una version anterior conocida (rollback rapido)

```bash
git fetch origin
git checkout <hash-o-tag-anterior> -- .
git commit -m "Rollback a version estable <hash>"
git push origin main
```

Para solo **inspeccionar** una version anterior sin dejar el servidor en ese estado (diagnostico puntual, no dejar asi corriendo produccion):

```bash
git checkout <hash>
# ... revisar ...
git checkout main
```

### e) Recuperar commits "perdidos" (por ejemplo tras un `reset --hard` accidental)

```bash
git reflog
git checkout <hash-recuperado>
git branch recuperada <hash-recuperado>
```

---

## 6. Comandos de diagnostico

```bash
git status
git log --oneline -10
git diff origin/main
git branch -vv
```

---

## 7. Buenas practicas y advertencias

- **Nunca** `git push --force` a `main`/rama de produccion sin autorizacion explicita del responsable del proyecto.
- **Nunca** `git reset --hard`, `git clean -fd` u otras operaciones destructivas en un servidor con datos sin confirmar antes con el responsable.
- Antes de un `pull` en un ambiente productivo, avisar/confirmar con el responsable — puede haber cambios pendientes de otra persona.
- Las credenciales y el `.env` no se versionan; si un despliegue requiere una variable nueva, coordinarla aparte (no asumirla ni inventarla).
- Un agente de IA solo ejecuta los pasos de este documento cuando el usuario responsable lo autoriza explicitamente para esa tarea puntual; ver [`README_LIMITES.md`](./README_LIMITES.md).

---

## 8. Resumen rapido (cheatsheet)

| Quiero... | Comando |
|-----------|---------|
| Traer un proyecto nuevo al servidor | `git clone <url> .` |
| Actualizar el servidor con lo ultimo | `git fetch origin && git pull origin main` |
| Ver que va a cambiar antes de actualizar | `git log HEAD..origin/main --oneline` |
| Descartar cambios locales sin commitear | `git restore .` |
| Deshacer el ultimo commit local | `git reset --soft HEAD~1` |
| Revertir un commit ya pusheado (seguro) | `git revert <hash>` |
| Recuperar algo "perdido" | `git reflog` |
