---
tipo: pendiente
proyecto: GERET
estado: pendiente
equipo: externo
pendiente_desde: 2026-09
responsable: "Dueño de olitel_dfuen (172.26.134.114)"
creado: 2026-10-27
tags: [geret, mysql, pendiente, indices, concurrencia]
---

# 04 — olitel_dfuen (172.26.134.114)

> [!info] Otro equipo
> Pendiente de otro equipo: lo seguimos, pero no nos toca hacer cambios.

Volver a [[00 - Indice Bloqueos BD]]

## Problema

1. Se lanzan **copias en paralelo** del mismo reporte: varias consultas pesadas idénticas compitiendo.
2. El filtro `YEAR_ID*100+WEEK_ID` (pendiente desde septiembre) aplica una operación sobre las columnas → MySQL **no puede usar índice** y recorre la tabla completa en cada ejecución.

## Acción acordada

- No lanzar copias en paralelo del mismo reporte.
- Reescribir el filtro `YEAR_ID*100+WEEK_ID`.

## Propuesta de solución

### A. Evitar ejecuciones paralelas

Opción en la BD (sirve aunque se lance desde distintas máquinas):

```sql
SELECT GET_LOCK('reporte_olitel_dfuen', 0);   -- 1 = obtuvo el lock, 0 = ya hay otra corriendo → salir
-- ... ejecutar el reporte ...
SELECT RELEASE_LOCK('reporte_olitel_dfuen');
```

Opción en el host: `flock -n /tmp/reporte_olitel.lock comando` en el cron (Linux).

Si lo lanza un usuario desde una web: deshabilitar el botón mientras corre, o cachear el resultado por N minutos.

### B. Reescribir el filtro

Antes (no usa índice):

```sql
WHERE YEAR_ID*100 + WEEK_ID BETWEEN 202636 AND 202643
```

Después (rango sobre columnas limpias):

```sql
WHERE (YEAR_ID > 2026 OR (YEAR_ID = 2026 AND WEEK_ID >= 36))
  AND (YEAR_ID < 2026 OR (YEAR_ID = 2026 AND WEEK_ID <= 43))
```

Con índice compuesto:

```sql
ALTER TABLE <tabla> ADD INDEX idx_year_week (YEAR_ID, WEEK_ID);
```

Alternativa más simple de mantener: columna generada indexada (MySQL 5.7+):

```sql
ALTER TABLE <tabla>
  ADD COLUMN YEAR_WEEK INT AS (YEAR_ID*100 + WEEK_ID) STORED,
  ADD INDEX idx_year_week (YEAR_WEEK);
-- y en la consulta:
WHERE YEAR_WEEK BETWEEN 202636 AND 202643
```

> El `ALTER TABLE` en tabla grande también toma MDL: hacerlo fuera de horario y con `lock_wait_timeout` corto.

## Por confirmar

- [ ] Tabla(s) y consulta exacta del reporte.
- [ ] Cómo se lanza (cron, web, manual) → elegir mecanismo de A.
- [ ] Índices existentes (`SHOW INDEX FROM <tabla>`), y comparar `EXPLAIN` antes/después.
