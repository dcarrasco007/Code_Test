---
tipo: concepto
proyecto: GERET
tags: [geret, mysql, concepto, bloqueos]
---

# Metadata lock, TRUNCATE y lock_wait_timeout

Volver a [[00 - Indice Bloqueos BD]]

## Por qué un TRUNCATE frena a todos

- `TRUNCATE` es **DDL**, no DML: internamente hace drop + create de la tabla.
- Necesita un **metadata lock (MDL) exclusivo**. Para obtenerlo espera a que termine *toda* transacción o consulta abierta que haya tocado la tabla (incluido un `SELECT` largo de un reporte).
- Mientras espera, el MDL queda **en cola**, y toda consulta nueva sobre esa tabla (incluso `SELECT`) se pone detrás → aparece en el processlist como `Waiting for table metadata lock`. Así se forma la pila de 300–1.100 s.
- Hace commit implícito y no se puede deshacer: si el INSERT posterior falla, la tabla queda vacía.

## DELETE + INSERT en transacción

```sql
START TRANSACTION;
DELETE FROM tabla;           -- bloqueos de fila, no MDL exclusivo
INSERT INTO tabla (...) VALUES (...), (...);
COMMIT;
```

- Los lectores (InnoDB, MVCC) siguen viendo los datos anteriores hasta el `COMMIT`: el cambio es atómico.
- Si algo falla → `ROLLBACK` y la tabla queda como estaba.
- Costo: `DELETE` genera undo/binlog por fila. Sirve para **tablas chicas** (miles a pocas centenas de miles de filas). Para tablas grandes ver alternativa abajo.

### Alternativa para tablas grandes: tabla espejo + RENAME

```sql
CREATE TABLE tabla_new LIKE tabla;      -- una sola vez, o al inicio
INSERT INTO tabla_new ...;              -- carga sin molestar a nadie
SET SESSION lock_wait_timeout = 10;
RENAME TABLE tabla TO tabla_old, tabla_new TO tabla;  -- swap atómico, MDL muy breve
DROP TABLE tabla_old;
```

El `RENAME` también pide MDL, pero dura milisegundos; con `lock_wait_timeout` corto falla rápido en vez de encolar a todos.

## lock_wait_timeout

- Controla cuánto espera una sesión por un **metadata lock**. Default MySQL: `31536000` s (**1 año**).
- `SET SESSION lock_wait_timeout = 10;` al conectar: si el job no obtiene el lock en 10 s, falla con error 1205 en vez de bloquear la cola. El job debe **reintentar** (p. ej. 3 intentos con espera de 30 s).
- No confundir con `innodb_lock_wait_timeout` (bloqueos de **fila**, default 50 s).

## CREATE TABLE IF NOT EXISTS

Aunque la tabla exista, la sentencia es DDL: toma MDL y hace commit implícito. Ejecutarla en cada request mete un DDL en el camino caliente → ver [[05 - App wsh CREATE TABLE por request]].
