# Bitácora de revisión de procesos legacy MMOO

Objetivo: documentar qué hace cada proceso legacy (cron) de MMOO, **por qué existe**, qué tablas toca y qué
problemas tiene, como insumo para **migrarlos**. Cada proceso revisado queda aquí para no volver a analizarlo
desde cero.

## Cómo usar esta bitácora

1. Antes de revisar un proceso, buscarlo en el [índice](#índice-de-procesos-revisados). Si ya está, partir de esa ficha.
2. Para uno nuevo, copiar [`_plantilla_proceso.md`](_plantilla_proceso.md) a `procesos/<nombre_script>.md`.
3. Actualizar siempre:
   - el índice de este README,
   - [`tablas.md`](tablas.md) (quién escribe / quién lee cada tabla),
   - [`hallazgos.md`](hallazgos.md) (bugs y riesgos, con ID `H-xx`),
   - el [registro de revisiones](#registro-de-revisiones) (al final).
4. Marcar con **(inferido)** lo que se deduce y no se verificó en el servidor o la BD.
5. **No copiar contraseñas** en estos archivos. Indicar solo en qué archivo están.

## Fuente de código revisada

| Copia | Ruta local | Fecha archivos | Uso |
|---|---|---|---|
| **Vigente (126)** | `C:\laragon\www\126\126_procesos\MMOO\procesos\` | 2026-01-27 | **Fuente de verdad**. Coincide con `crontab.txt` |
| Variante 127 | `C:\laragon\www\126\126_procesos\MMOO\procesos_127\` | 2026-01-27 | Solo `cargaDatosNodo` reducido (info + remota) |
| Antigua | `C:\laragon\www\panel_mmoo\MMOO\procesos\` | 2024-12-12 | No usar. Sirve solo para buscar los consumidores (pantallas) |

En el servidor la ruta es `/var/www/html/MMOO/procesos/` (los scripts también usan `/u01/MMOO/procesos/`,
que parece ser la misma ruta montada o un symlink **(inferido)**).

## Infraestructura común

- **Servidor de procesos**: 192.168.66.126 (Apache + PHP + cron). Los procesos "hilos" se llaman a sí mismos por HTTP
  (`http://192.168.66.126/MMOO/procesos/...`) para paralelizar con `curl_multi`.
- **Servidor .140**: corre un script Python que carga `MMOO_POTENCIA_RX` (Ericsson). **No está revisado aún.**
- **BD**: MySQL, esquema `mmoo`. Las credenciales están en `../../conexion/conexiones.php` y en `conexion_db.php`
  (no versionados aquí).
- **Acceso a equipos**:
  - Ericsson MINI-LINK TN: SNMPv3 (`snmpget`/`snmpwalk`, usuario en `infoNodo.php`/`magazine.php`) y Telnet (`alarmas/alarmasFisicasTN.sh`).
  - Huawei RTN: SSH vía *expect* (`huawei_/rtnNiveles.sh`).
- **Panel que consume los datos**: `panel_mmoo/MMOO/Vistas/*` y `panel_mmoo/MMOO/graficos/*`.

### Cron (extracto de `crontab.txt`)

```
10 */1 * * * /var/www/html/MMOO/procesos/cargaPotencia.sh
30 */1 * * * /var/www/html/MMOO/procesos/cargaPotencia_2.sh
00 21  * * * /var/www/html/MMOO/procesos/cargaDatosNodo.sh
```

## Índice de procesos revisados

| Proceso | Frecuencia | Qué hace (1 línea) | Complejidad | Ficha |
|---|---|---|---|---|
| `cargaPotencia.sh` | cada hora (:10) | Alarmas por nivel RX (Ericsson y Huawei) + alarmas físicas TN | Alta | [ficha](procesos/cargaPotencia.md) |
| `cargaPotencia_2.sh` | cada hora (:30) | Segunda medición de niveles RSL Huawei en `HUA_POTENCIA_RX` | Media | [ficha](procesos/cargaPotencia_2.md) |
| `cargaDatosNodo.sh` | diario 21:00 | Inventario Ericsson por SNMP (info, IP remota, tarjetas, conteo MMU) | Media | [ficha](procesos/cargaDatosNodo.md) |

### Pendientes detectados (todavía sin revisar)

- Script Python en .140 que carga `MMOO_POTENCIA_RX` (reemplazó a `hilosPotencia.php`).
- Origen de `MMOO_IP`, `HUA_IPS`, `HUA_MAGAZINE`, `HUA_CONTEO` y `MMOO_MIBSLOT` (las usan todos los procesos, pero estos no las cargan).
  Candidatos: `carga_ips_mmoo.php`, `huawei/hilosEstructura*.php`, `huawei/rtnEstructura.sh`.
- Resto del crontab: `alarmasFisicas.sh`, `alarmasFisicasPY.sh`, `grooming.sh`, `ericsson/medicionesWan.sh`, `tp/*`, `Alarmas_NCE/*`, etc.

## Registro de revisiones

| Fecha | Qué se revisó | Fuente | Resultado |
|---|---|---|---|
| 2026-10-21 | `cargaPotencia.sh`, `cargaPotencia_2.sh`, `cargaDatosNodo.sh` y todos los PHP/expect que llaman | Copia 126 (2026-01-27), logs locales y `crontab.txt` | Fichas creadas, catálogo de tablas, hallazgos H-01…H-15 |
