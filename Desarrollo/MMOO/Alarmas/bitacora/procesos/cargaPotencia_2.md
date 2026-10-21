# `cargaPotencia_2.sh`

| Campo | Valor |
|---|---|
| Ruta servidor | `/var/www/html/MMOO/procesos/cargaPotencia_2.sh` (hace `cd /u01/MMOO/procesos/huawei_`) |
| Ruta local revisada | `C:\laragon\www\126\126_procesos\MMOO\procesos\huawei_\` (archivos del 2026-01-27) |
| Cron | `30 */1 * * *` (minuto 30 de cada hora) |
| Duración observada | ~7 min (log 2026-01-27 09:33 → 09:40) |
| Complejidad | **Media** |
| Estado | Funciona (inserta), pero **ninguna pantalla lee su tabla** |
| Fecha revisión | 2026-10-21 |

## Por qué existe
Segunda medición horaria del **RSL de los ODU Huawei RTN**, que guarda en una tabla nueva (`HUA_POTENCIA_RX`) con la
`fecha` **redondeada a la hora**. Así queda con el mismo formato que la de Ericsson (`MMOO_POTENCIA_RX`).

**(inferido)** Es la versión nueva o en desarrollo de `hilosNiveles.php`: pensada para un panel o una migración que todavía
no la usa. No se encontró ningún consumidor de `HUA_POTENCIA_RX` en `panel_mmoo`, `126` ni `Pruebas`.

## Flujo

```
cargaPotencia_2.sh
└─ hilosNiveles_2.php ──HTTP×N──► procesoNiveles_2.php ──exec──► rtnNiveles.sh (expect + SSH)
                                                        → HUA_POTENCIA_RX / HUA_POTENCIAS_LOG_2
```

Es casi idéntico a `hilosNiveles.php` / `procesoNiveles.php` (ver [cargaPotencia.md](cargaPotencia.md), paso 5).
Diferencias:

| Aspecto | `hilosNiveles.php` (cargaPotencia) | `hilosNiveles_2.php` (este) |
|---|---|---|
| IPs por llamada HTTP | 6 | **10** |
| Espera antes de leer los .txt | `sleep(5)` | **`sleep(30)`** + `sleep(6)` por IP |
| Ruta de `rtnNiveles.sh` | relativa `./` | absoluta `/var/www/html/MMOO/procesos/huawei_` |
| Tabla destino | `HUA_POTENCIAS` (fecha según el default de la BD) | **`HUA_POTENCIA_RX`** con `fecha = DATE_FORMAT(NOW(),'%Y-%m-%d %H:00:00')` |
| Tabla de log | `HUA_POTENCIAS_LOG` | **`HUA_POTENCIAS_LOG_2`** |
| Carpeta de .txt | `./niveles_logs/` | **la misma** `./niveles_logs/` (H-03) |
| `fopen` con error | `or exit()` | sin control |

## Tablas

| Tabla | Operación | Datos |
|---|---|---|
| `HUA_MAGAZINE`, `HUA_IPS` | SELECT | IPs con ODU activa y su tipo de equipo |
| `HUA_POTENCIA_RX` | INSERT (solo agrega) | `ip`, `tipo`, `fecha` (hora cerrada), una columna por ODU con el RSL actual |
| `HUA_POTENCIAS_LOG_2` | TRUNCATE + INSERT | Respuesta cruda de los equipos sin datos de `rsl_cur` |

## Reglas de negocio
- Solo mide; no clasifica alarmas.
- Toma la fila `rsl_cur` del comando `:per-get-curdata-byboard:15m,<odu>` (columna 5 = nivel, columna 9 = ODU).

## Dependencias
- Mismo inventario que cargaPotencia: `HUA_MAGAZINE`, `HUA_IPS`.
- Corre 20 min después de `cargaPotencia.sh` y borra la misma carpeta de .txt (H-03).
- Abre por SSH **los mismos equipos** que cargaPotencia: dos sesiones por equipo cada hora (H-11).

## Hallazgos
H-03, H-05, H-07, H-10, H-11, H-15 (ver [hallazgos.md](../hallazgos.md)).

## Notas para la migración
- Confirmar con el dueño del panel si `HUA_POTENCIA_RX` reemplaza a `HUA_POTENCIAS`. Si es así, migrar **solo uno**
  de los dos procesos y apuntar `procesoAlarmas.php` y los gráficos RSL a la tabla elegida.
- El formato con `fecha` a la hora cerrada es el más fácil de comparar con Ericsson. Conviene mantenerlo.
