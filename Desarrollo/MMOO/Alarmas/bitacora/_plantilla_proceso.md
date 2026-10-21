# `<nombre_script>`

| Campo | Valor |
|---|---|
| Ruta servidor | `/var/www/html/...` |
| Ruta local revisada | `C:\laragon\www\...` (fecha archivos: AAAA-MM-DD) |
| Cron | `m h dom mon dow` |
| Duración observada | según logs (fecha del log) |
| Complejidad | Baja / Media / Alta |
| Estado | Funciona / Parcial / No funciona / Desconocido |
| Fecha revisión | AAAA-MM-DD |

## Por qué existe
Necesidad de negocio que cubre y qué pantalla o usuario consume el resultado.

## Flujo
Orden de ejecución, archivos que llama y cómo se conecta a los equipos.

## Tablas
| Tabla | Operación (TRUNCATE / INSERT / DELETE / SELECT) | Datos |
|---|---|---|

## Reglas de negocio
Umbrales, ventanas de tiempo, filtros.

## Dependencias
Tablas que debe cargar otro proceso, orden con otros cron, servidores externos.

## Hallazgos
Referencias a `hallazgos.md` (H-xx).

## Notas para la migración
Qué conservar, qué eliminar y qué rediseñar.
