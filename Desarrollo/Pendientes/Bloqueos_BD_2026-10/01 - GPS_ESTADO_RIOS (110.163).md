---
tipo: pendiente
proyecto: GERET
estado: pendiente
equipo: externo
responsable: "Dueño del job de adminAder@192.168.110.163"
tablas: [CCO.GPS_ESTADO_RIOS]
creado: 2026-10-27
tags: [geret, mysql, pendiente, truncate]
---

# 01 — CCO.GPS_ESTADO_RIOS (adminAder@192.168.110.163)

> [!info] Otro equipo
> Pendiente de otro equipo: lo seguimos, pero no nos toca hacer cambios.

Volver a [[00 - Indice Bloqueos BD]] · Concepto: [[Conceptos - Metadata lock, TRUNCATE y lock_wait_timeout]]

## Problema

- Espera máxima observada: **342 s**.
- El job vacía la tabla con `TRUNCATE` y la vuelve a llenar. El `TRUNCATE` pide metadata lock exclusivo y encola a todos los lectores mientras espera.

## Acción acordada

1. Reemplazar `TRUNCATE` por `DELETE` + `INSERT` dentro de una transacción (la tabla es chica).
2. Al conectar: `SET SESSION lock_wait_timeout = 10`.

## Propuesta de solución

### Python (pymysql / mysql-connector)

```python
conn = conectar()                       # la conexión que ya usa el job
cur = conn.cursor()
cur.execute("SET SESSION lock_wait_timeout = 10")
try:
    conn.begin()
    cur.execute("DELETE FROM CCO.GPS_ESTADO_RIOS")
    cur.executemany(
        "INSERT INTO CCO.GPS_ESTADO_RIOS (col1, col2, ...) VALUES (%s, %s, ...)",
        filas,
    )
    conn.commit()
except Exception:
    conn.rollback()
    raise
finally:
    cur.close()
    conn.close()
```

### PHP (PDO)

```php
$pdo->exec("SET SESSION lock_wait_timeout = 10");
try {
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM CCO.GPS_ESTADO_RIOS");
    $stmt = $pdo->prepare("INSERT INTO CCO.GPS_ESTADO_RIOS (col1, col2) VALUES (?, ?)");
    foreach ($filas as $f) { $stmt->execute($f); }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

### Reintento ante timeout

Si salta el error 1205 (*Lock wait timeout exceeded*), reintentar 2–3 veces con espera de ~30 s antes de dar por fallida la corrida.

## Por confirmar

- [ ] Lenguaje y ubicación del job (¿cron en 110.163?).
- [ ] Cantidad de filas de la tabla (confirmar que es "chica").
- [ ] ¿`Aden.PRIORIZACION_TMP_PSG_PCA` (486 s, mismo origen) la llena el mismo job? Si usa TRUNCATE, aplicar lo mismo.
- [ ] Verificar en `Inventario_TRUNCATE_2026-10-26.pdf`.

## Verificación

- Tras el cambio, revisar que la tabla no aparezca en `sys.schema_table_lock_waits` ([[Consultas de diagnostico de bloqueos]]).
