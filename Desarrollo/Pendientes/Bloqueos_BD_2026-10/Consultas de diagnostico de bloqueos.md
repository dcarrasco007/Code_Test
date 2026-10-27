---
tipo: referencia
proyecto: GERET
tags: [geret, mysql, diagnostico, bloqueos]
---

# Consultas de diagnóstico de bloqueos

Volver a [[00 - Indice Bloqueos BD]]

## Quién está esperando y cuánto

```sql
SELECT id, user, host, db, command, time, state, LEFT(info, 120) AS query
FROM information_schema.PROCESSLIST
WHERE command <> 'Sleep'
ORDER BY time DESC;
```

## Quién bloquea a quién por metadata lock (MySQL 8 / 5.7 con sys)

```sql
SELECT object_schema, object_name,
       waiting_pid, waiting_query, waiting_account,
       blocking_pid, blocking_account, sql_kill_blocking_query
FROM sys.schema_table_lock_waits;
```

Si viene vacía, verificar que esté activo el instrumento:

```sql
UPDATE performance_schema.setup_instruments
SET enabled = 'YES', timed = 'YES'
WHERE name = 'wait/lock/metadata/sql/mdl';
```

## Transacciones abiertas hace rato (suelen ser las que retienen el MDL)

```sql
SELECT trx_mysql_thread_id AS pid, trx_started,
       TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS segundos,
       LEFT(trx_query, 120) AS query
FROM information_schema.INNODB_TRX
ORDER BY trx_started;
```

## Conexiones por host (para el caso bhaul-dev)

```sql
SELECT SUBSTRING_INDEX(host, ':', 1) AS ip, user, COUNT(*) AS conexiones
FROM information_schema.PROCESSLIST
GROUP BY ip, user
ORDER BY conexiones DESC;
```

## Valores actuales

```sql
SHOW VARIABLES WHERE Variable_name IN
  ('lock_wait_timeout', 'innodb_lock_wait_timeout', 'max_connections', 'max_allowed_packet', 'wait_timeout');
```
