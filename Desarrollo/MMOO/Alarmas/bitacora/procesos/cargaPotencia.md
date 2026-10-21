# `cargaPotencia.sh`

| Campo | Valor |
|---|---|
| Ruta servidor | `/var/www/html/MMOO/procesos/cargaPotencia.sh` (hace `cd /u01/MMOO/procesos`) |
| Ruta local revisada | `C:\laragon\www\126\126_procesos\MMOO\procesos\` (archivos del 2026-01-27) |
| Cron | `10 */1 * * *` (minuto 10 de cada hora) |
| Duración observada | Ericsson ~1,5 min sin contar `count_alarma` (log 2025-09-05). Huawei niveles ~5 min, alarmas TN ~1 min (log 2026-01-27) |
| Complejidad | **Alta** |
| Estado | Funciona, salvo `count_alarma.php`: su log no llega a `FIN` (ver H-09) |
| Fecha revisión | 2026-10-21 |

## Por qué existe
Monitorea **cada hora** el nivel de recepción (RX/RSL) de los enlaces de microondas, para detectar enlaces
**degradados** o **cortados**:
- **Ericsson MINI-LINK TN**: por slot de módem (MMU2–8 y MMU13–21).
- **Huawei RTN**: por ODU (21–26 y 51–64).

También descarga las **alarmas físicas** de los equipos TN (`show alarm`).

Pantallas que consumen el resultado (`panel_mmoo/MMOO/...`):
- `MMOO_IP_ALERTA`: `Vistas/panel.php`, `vistaDetalle.php`, `procesosMMU.php`, `procesoExpAlarmas*.php` (exportaciones de los paneles General, BandaE y MNL6692).
- `MMOO_COUNTMMU`: `Vistas/panelAlarmas.php`, `Vistas/equipos.php` (contadores y % por región).
- `MMOO_HIST_ALARM_HORA`: `graficos/graficosReporteHist{Hora,Mes,Anual}.php`.
- `HUA_POTENCIAS`: `graficos/graficoRSL{Dia,Semanal,Mensual,Anual}HUA.php`, `Vistas/huawei/alarmas/detallePorRegion.php`.
- `HUA_ALARMAS_NEW`: `Vistas/huawei/alarmas/detallePorRegion.php`, `Vistas/procesoExpAlarmasHUA.php`.
- `HUA_CONTEO_ALARMAS`: `Vistas/huawei/alarmas/alarmasActuales.php`.
- `MMOO_ALARMAS_TN`: `Vistas/panelAlarmas_data.php`.

## Flujo

```
cargaPotencia.sh
├─ [Ericsson TN — solo BD; MMOO_POTENCIA_RX la carga un Python en .140]
│   (hilosPotencia.php → comentado, reemplazado)
│  1. slotAlarmados.php        → MMOO_IP_ALERTA (1h)
│  2. slotAlarmadosDias.php    → MMOO_IP_ALERTA (1D, 4D)
│  3. reportAlarmHora.php      → MMOO_HIST_ALARM_HORA
│  4. count_alarma.php         → MMOO_COUNTMMU
├─ [Huawei]  cd huawei_/
│  5. hilosNiveles.php ──HTTP×N──► procesoNiveles.php ──exec──► rtnNiveles.sh (expect + SSH)
│                                                              → HUA_POTENCIAS / HUA_POTENCIAS_LOG
│  6. procesoAlarmas.php       → HUA_ALARMAS_NEW (1H, 1D)
│     cd huawei/
│  7. conteoAlarmas.php        → HUA_CONTEO_ALARMAS
└─ [Alarmas físicas TN]  cd alarmas/
   8. hilosAlarmasFisicasTN.php ──HTTP×N──► alarmasFisicasTN.php ──exec &──► alarmasFisicasTN.sh (expect + Telnet)
                                                                   → MMOO_ALARMAS_TN
```

Los pasos corren **en secuencia**. Si uno se demora, todo lo que sigue se atrasa.

### Detalle por archivo

**1. `slotAlarmados.php`**
- `TRUNCATE MMOO_IP_ALERTA`.
- Toma `MAX(DATE_ID)` de `MMOO_POTENCIA_RX` y clasifica cada columna de slot (`1/2.1/1` … `1/21.1/1`).
- Excluye los pares IP-slot que están en `MMOO_ALARM_REVISADAS` (las alarmas que un operador ya marcó como revisadas).
- Inserta `(IP, ALARMA, TIPO='1h', SLOT='MMUx', DATE_ID)` en lotes de 200.

**2. `slotAlarmadosDias.php`**
- Lee las alarmas `1h` y revisa las muestras de esas IPs en `MMOO_POTENCIA_RX` de los últimos 4 días.
- `1D`: solo DEGRADADO. Exige **≥20 muestras** en las últimas 24 h y que **todas** tengan el mismo estado.
- `4D`: CORTE o DEGRADADO. Exige **≥80 muestras** en 4 días, todas con el mismo estado.
- Agrega estas filas a `MMOO_IP_ALERTA` **sin `DATE_ID`**.

**3. `reportAlarmHora.php`**
- Copia las alarmas `1h` a `MMOO_HIST_ALARM_HORA (IP, ALARMA, SLOT, REGION, FECHA)`, con la región tomada de `MMOO_IP`.
- La tabla solo crece: es el histórico de los gráficos.

**4. `count_alarma.php`**
- Recorre regiones 1–16 × rangos `4D`, `1D`, `1h`. Para cada IP alarmada (JOIN con `MMOO_IP`, `MMOO_REGIONES`,
  `MMOO_INFO_IP` y `MMOO_IP_ALERTA_OBSERVACION`) consulta 30 días de `MMOO_POTENCIA_RX` y confirma que la alarma
  sigue activa en toda la ventana "medible" (96 h / 24 h / 1 h).
- En `MMOO_COUNTMMU` borra y vuelve a insertar:
  - `DEGRADADO_1h`, `CORTE_1h`, `DEGRADADO_1D`, `DEGRADADO_4D`, `CORTE_4D` (cantidad por región),
  - `1h`, `1D`, `4D` (IPs malas por región),
  - `*_PORCENTAJE`, calculado contra la fila `tipo='MMU'` que deja `count_mmu.php` (de **cargaDatosNodo**).

**5. `huawei_/hilosNiveles.php` → `procesoNiveles.php` → `rtnNiveles.sh`**
- `TRUNCATE HUA_POTENCIAS_LOG`.
- Toma las IPs con ODU activa: `HUA_MAGAZINE (tipo='odu', estado='active')` JOIN `HUA_IPS`.
- Las reparte en grupos de **6** (~260 llamadas HTTP en paralelo según el comentario del código).
- Por cada IP, `rtnNiveles.sh` entra por SSH y ejecuta `:per-get-curdata-byboard:15m,<odu>` para cada ODU.
- Del bloque `PER-GET-CURDATA-BY-BOARD` toma la fila `rsl_cur` (nivel de recepción actual).
- Inserta en `HUA_POTENCIAS (ip, tipo, <col_odu>...)`, una columna por ODU. Si no hay datos, guarda la respuesta cruda en `HUA_POTENCIAS_LOG`.
- Los .txt se escriben en `huawei_/niveles_logs/`, y el proceso borra la carpeta al empezar.

**6. `huawei_/procesoAlarmas.php`**
- `TRUNCATE HUA_ALARMAS_NEW`.
- Sobre `MAX(fecha)` de `HUA_POTENCIAS` aplica los mismos umbrales que Ericsson → `1H` (CORTE o DEGRADADO por `ODUxx`).
- `1D`: DEGRADADO con **≥24 registros** en el último día.
- **No genera `4D`** y no hay lista de "revisadas" (`$arrIPRev` vacío).

**7. `huawei/conteoAlarmas.php`**
- `TRUNCATE HUA_CONTEO_ALARMAS`.
- Agrupa `HUA_ALARMAS_NEW` por región (`HUA_IPS`) y tipo.
- Calcula el % sobre `HUA_CONTEO.bids` (total de ODUs por región) y clasifica `CON_CORTES` o `DEGRADADOS`.

**8. `alarmas/hilosAlarmasFisicasTN.php` → `alarmasFisicasTN.php` → `alarmasFisicasTN.sh`**
- `TRUNCATE MMOO_ALARMAS_TN_LOG`, que después nunca se llena.
- IPs de `MMOO_IP` con `habilitado=1`, en grupos de 100.
- Por cada IP, Telnet y `show alarm`. Del texto saca: Alarm ID, Severity, Alarm Type, Alarm Time, Source y Specific Problem.
- `INSERT` en `MMOO_ALARMAS_TN (ip, idalarm, severity, type, time, source, problem)`. **Nunca se limpia** (H-04).

## Tablas

| Tabla | Operación | Datos |
|---|---|---|
| `MMOO_POTENCIA_RX` | SELECT | Nivel RX por IP, slot y hora (lo carga Python en .140) |
| `MMOO_ALARM_REVISADAS` | SELECT | IP + slot ya revisados (se excluyen) |
| `MMOO_IP_ALERTA` | TRUNCATE + INSERT | Alarmas vigentes 1h / 1D / 4D por IP y MMU |
| `MMOO_HIST_ALARM_HORA` | INSERT (solo agrega) | Histórico horario de alarmas 1h con región |
| `MMOO_IP`, `MMOO_REGIONES`, `MMOO_INFO_IP`, `MMOO_IP_ALERTA_OBSERVACION` | SELECT | Maestro de IPs, regiones, info SNMP, comentarios |
| `MMOO_COUNTMMU` | DELETE por tipo + INSERT | Contadores y % por región |
| `HUA_MAGAZINE`, `HUA_IPS`, `HUA_CONTEO` | SELECT | Inventario Huawei (ODU activas, región, total de ODUs) |
| `HUA_POTENCIAS` | INSERT (solo agrega) | RSL actual por IP y ODU (una columna por ODU) |
| `HUA_POTENCIAS_LOG` | TRUNCATE + INSERT | Respuesta cruda de los equipos sin datos |
| `HUA_ALARMAS_NEW` | TRUNCATE + INSERT | Alarmas Huawei 1H / 1D |
| `HUA_CONTEO_ALARMAS` | TRUNCATE + INSERT | Resumen Huawei por región |
| `MMOO_ALARMAS_TN` | INSERT (solo agrega) | Alarmas físicas TN |
| `MMOO_ALARMAS_TN_LOG` | TRUNCATE | (no se llena) |

## Reglas de negocio
- Umbrales (valores en décimas de dBm **(inferido)**, -555 = -55,5 dBm):
  - **DEGRADADO**: entre -699 y -555.
  - **CORTE**: entre -999 y -700.
  - Cualquier otro valor: **OK**.
- Ericsson 1D ≥ 20 muestras / 24 h y 4D ≥ 80 muestras / 4 días, en ambos casos sin cambios de estado.
- Huawei 1D ≥ 24 muestras degradadas / 24 h.
- `count_alarma`: una IP cuenta como mala solo si **todas** las muestras de la ventana medible están en error.

## Dependencias
- `MMOO_POTENCIA_RX` llega desde el Python de .140. Si ese script no corre, la parte Ericsson trabaja con la última hora disponible.
- `MMOO_COUNTMMU` tipo `MMU` lo crea `count_mmu.php` (cargaDatosNodo, 21:00) → H-01.
- `MMOO_INFO_IP` lo crea `hilosInfo.php` (cargaDatosNodo) y `count_alarma` hace INNER JOIN con ella → H-02.
- `HUA_MAGAZINE`, `HUA_IPS`, `HUA_CONTEO`, `MMOO_IP`, `MMOO_ALARM_REVISADAS`: su origen todavía no está revisado.
- Comparte la carpeta `huawei_/niveles_logs/` con `cargaPotencia_2.sh` → H-03.

## Hallazgos
H-01, H-02, H-03, H-04, H-05, H-07, H-08, H-09, H-10, H-13, H-14, H-15 (ver [hallazgos.md](../hallazgos.md)).

## Notas para la migración
- Separar en **tres jobs independientes**: Ericsson-alarmas, Huawei-niveles+alarmas y TN-alarmas-físicas. No comparten datos.
- Reemplazar TRUNCATE + INSERT por una carga a tabla staging y luego `RENAME`/swap, o por upsert con `fecha_proceso`.
- Llevar los umbrales a configuración. Hoy están repetidos 16-20 veces en cada SQL.
- Reescribir `count_alarma` como una sola consulta agregada o como cálculo en memoria (hoy es N+1).
- Pasar de HTTP-a-sí-mismo + `sleep` a un pool de workers (p. ej. Python `asyncio`/`concurrent.futures` con
  `asyncssh`/`pysnmp`) con timeout por equipo y registro de fallas.
- Revisar con negocio si `HUA_POTENCIAS` y `HUA_POTENCIA_RX` se pueden unificar (ver cargaPotencia_2).
