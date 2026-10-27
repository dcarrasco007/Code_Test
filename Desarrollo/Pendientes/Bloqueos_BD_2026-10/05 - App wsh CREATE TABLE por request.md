---
tipo: pendiente
proyecto: GERET
estado: pendiente
equipo: externo
responsable: "App wsh"
creado: 2026-10-27
tags: [geret, mysql, pendiente, ddl]
---

# 05 — App wsh: CREATE TABLE IF NOT EXISTS en cada request

> [!info] Otro equipo
> Pendiente de otro equipo: lo seguimos, pero no nos toca hacer cambios.

Volver a [[00 - Indice Bloqueos BD]] · Concepto: [[Conceptos - Metadata lock, TRUNCATE y lock_wait_timeout]]

## Problema

- La app ejecuta `CREATE TABLE IF NOT EXISTS …` en cada request.
- Aunque la tabla ya exista, es DDL: pide metadata lock y hace commit implícito. Con muchas requests y algún TRUNCATE/ALTER en curso, cada request queda en *Waiting for table metadata lock*.
- Es el **mismo bug que tuvo CEC**.

## Acción acordada

Sacar el `CREATE TABLE IF NOT EXISTS` del flujo de cada request.

## Propuesta de solución

1. Buscar en el código: `CREATE TABLE IF NOT EXISTS` (y también `CREATE TEMPORARY`, `ALTER TABLE`) dentro de controladores, modelos o el archivo de conexión.
2. Mover la creación a:
   - un script SQL de instalación/migración (`sql/` o `migrations/`) que se corre una vez en el deploy, o
   - si hace falta en runtime, un chequeo cacheado: hacerlo una vez por proceso, no por request.
3. Crear las tablas faltantes ahora a mano en la BD, para que al quitar la línea no falle nada.

```php
// Antes (en cada request)
$pdo->exec("CREATE TABLE IF NOT EXISTS log_wsh (...)");

// Después: se elimina de aquí; la tabla se crea en sql/001_log_wsh.sql
```

## Por confirmar

- [ ] Repo/ruta de la app wsh y archivo donde está la sentencia.
- [ ] Cómo se resolvió en CEC (reutilizar el mismo enfoque) — enlazar nota de CEC cuando exista: [[CEC - CREATE TABLE por request]].
- [ ] Si la tabla es temporal por usuario, evaluar `CREATE TEMPORARY TABLE` (no toma MDL global) o una tabla fija con columna de sesión.
