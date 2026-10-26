# Reporte BW semanal y carga automática de capacidades OLT

Documentación del desarrollo que genera de forma automática el Excel anual de capacidades BW de las OLT,
muestra alertas de la semana y carga las tablas `OLT_CAPACIDAD_BW*` sin subir archivos a mano.

- **Versión:** PHP 8.0, estructura **contingencia_v3** (carpeta `BWPHP8`). Ver `_LEEME_MIGRACION_PHP8.txt`.
  La versión PHP 5.3 sigue en `Pruebas/Desarrollo/reportes_bw/Capacidad_BW_Excel/` (panel_olt / contingencia).
- **Ubicación en el servidor:** `/var/www/html/Capacidad_BW_Excel/` (módulo en la raíz del sitio, sin `OLT/`).
  Conexión en `/var/www/html/conexion/conexion_db.php`, perfiles en `/var/www/html/perfiles/`.
- **Requisitos PHP 8:** extensiones `mysqli`, `zip`, `dom`/`xml`, `json`.
- **Fecha de desarrollo:** octubre 2026 (puesta en marcha con la W-41 / W-42 de 2026).

---

## 1. Resumen

| Etapa | Qué hace | Script | Cuándo |
|---|---|---|---|
| 1 | Genera el Excel anual "Capacidades OLTs {Mes} {Año} W-{n}.xlsx" y "... Act - Act.xlsx" con la semana anterior, más sus alertas | `cron_reporte_bw_semanal.php` | Lunes 04:00 |
| 1 | Ventana web para descargar los 2 Excel y ver alertas | `index_reporte_bw_semanal.php` | — |
| 2 | Guarda el Excel del período en su carpeta y carga `OLT_CAPACIDAD_BW` / `_EQUIFIBER` / `_ONNET` | `cron_carga_bw_bd.php --tipo=semana` | Lunes 04:30 |
| 2 | Ídem para `OLT_CAPACIDAD_BW_MES*` | `cron_carga_bw_bd.php --tipo=mes` | Día 1 de cada mes |
| 2 | Ídem para `OLT_CAPACIDAD_BW_YEAR*` | `cron_carga_bw_bd.php --tipo=anio` | Lunes (solo actúa al cerrar el año) |

---

## 2. Estructura de archivos

```
Capacidad_BW_Excel/
├── cron_reporte_bw_semanal.php     Etapa 1 (CLI): Excel anual + alertas
├── cron_carga_bw_bd.php            Etapa 2 (CLI): Excel del período + carga a BD
├── index_reporte_bw_semanal.php    Ventana web: descargas y alertas
├── README_REPORTE_BW.md            Este documento
├── lib/
│   ├── bw_config.php       Rutas, umbral, OLTs excluidas, lista ONNET, formato por red
│   ├── bw_fechas.php       Semanas del reporte, nombres de días/meses, nombres de archivo
│   ├── bw_datos.php        Consultas a BD (OLTs, capacidad, tráfico peak)
│   ├── bw_xlsx.php         Clase BwLibroXlsx: edición del .xlsx (ZipArchive + DOM)
│   ├── bw_reporte.php      Lógica de la etapa 1
│   └── bw_carga_bd.php     Lógica de la etapa 2
├── Archivos_Auto/
│   └── Semana/             ETAPA 1: Excel anuales, Act - Act, alertas_*.json, log
└── Archivos/               ETAPA 2 (mismas carpetas que la subida manual)
    ├── Semana/  Semana_EQUIFIBER/  Semana_ONNET/
    ├── Mes/     Mes_EQUIFIBER/     Mes_ONNET/
    └── Year/    Year_EQUIFIBER/    Year_ONNET/
```

Las carpetas de la etapa 1 (`Archivos_Auto`) y la etapa 2 (`Archivos`) se separaron para que no se mezclen
los Excel anuales con los Excel por período. Las carpetas que no existan se crean solas.

---

## 3. Crons

```bash
# Etapa 1: Excel anual de la semana anterior + alertas
0 4 * * 1  php /var/www/html/Capacidad_BW_Excel/cron_reporte_bw_semanal.php
# Etapa 2: semana anterior (después del Excel)
30 4 * * 1 php /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.php --tipo=semana > /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.log 2>&1
# Etapa 2: mes calendario anterior
30 4 1 * * php /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.php --tipo=mes
# Etapa 2: año cerrado (puede correr todos los lunes, solo carga cuando la semana anterior es la última del año)
45 4 * * 1 php /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.php --tipo=anio
```

> **Importante:** no usar `php -f archivo.php --tipo=...`. Con `-f`, PHP interpreta `--tipo` como opción propia
> y solo muestra la ayuda. Usar `php archivo.php --tipo=...` o `php -f archivo.php -- --tipo=...`.

---

## 4. Reglas de negocio (decisiones acordadas)

### 4.1 Semanas y año del reporte
- Semanas de **lunes a domingo**. La **semana 1 es la que contiene el 1 de enero** (no es ISO-8601).
  - 2026: parte el lunes 29-12-2025, tiene **52 semanas**; el lunes 28-12-2026 ya es la **W-1 de 2027**.
  - Un año tiene 53 semanas solo cuando corresponde (ej: 2028, que parte el 27-12-2027).
- El Excel anual **arranca vacío** cada año desde la W-1 y la hoja se llama "Reporte BW {año}".
- Funciones: `bw_ts_lunes_semana1()`, `bw_semana_de_fecha()`, `bw_semana_anterior()`, `bw_semanas_del_anio()`.

### 4.2 Nombres de archivo (etapa 1)
- `Capacidades OLTs {Mes} {Año} W-{n}.xlsx` y `Capacidades OLTs {Mes} {Año} Act - Act.xlsx` (mismo contenido).
- `{Mes}` = mes del **domingo** de la semana (siempre cae dentro del año del reporte).
- El "Act - Act" se sobrescribe cada semana del mes; en la web solo se ofrece para la última semana con ese nombre.

### 4.3 Datos (misma fuente que `OLT/Reporte_BW/reporte_bw_fecha_peak.php`)
- **Tráfico:** `OLT_TRAFICO_PEAK_DAY.peak_mb` por ip y fecha (Mbps, redondeado a entero).
  No se replicó la rama del PHP original que usa `peak_kb` cuando `peak_mb > 100000` (parecía un error).
- **Capacidad por OLT:** `OLT_PUERTAS_UPLINKS_GB.capacidad_total` (Gbps, se toma el mayor por OLT):
  - OLTs de **10G o más**: `capacidad_total / 2 × 1000` (suma de 2 uplinks redundantes; ej 200Gb → 100000),
    igual que `reporte_bw_fecha_peak_capacidad.php`.
  - OLTs de **1G**: `capacidad_total × 1000` (sin dividir).
  - Función: `bw_capacidad_mbps()`.
- Los nombres de OLT e ip se limpian con `trim()` (hay nombres con espacio al final en `OLT_PUERTAS_UPLINKS_GB`).

### 4.4 Columnas del Excel
| Col | Contenido | Regla |
|---|---|---|
| A | PE-com | Combinada por grupo |
| B | Tráfico (Gbps) del PE | **Promedio** de los días del período de la suma diaria del tráfico de sus OLTs |
| C | Capacidad (Gbps) del PE | **Se mantiene el valor que trae el Excel** (capacidad real del PE, mantenida a mano). Solo si está vacía (PE-com nuevo) se usa la suma de capacidades de sus OLTs |
| D | OLT | |
| E.. | Por día: Tráfico, Capacidad, % Uplink | `% Uplink = +Tráfico/Capacidad`; colores del archivo: verde < 60 %, amarillo 60–69,9 %, rojo ≥ 70 % |

Para cambiar la capacidad de un PE-com: editar la celda C en el último Excel de `Archivos_Auto/Semana/`;
se arrastra a las semanas siguientes y a la BD.

### 4.5 OLTs nuevas
- Una OLT que tenga tráfico en la semana y no esté en el Excel **se agrega automáticamente** en el grupo de su
  PE-com (o al final si el PE-com es nuevo), extendiendo las celdas combinadas.
- Sus **días anteriores** quedan con tráfico **0**, la capacidad obtenida esa semana y la fórmula de % Uplink.
  Los días de la semana previos a su primer registro también quedan en 0.
- Genera alerta "OLT nueva agregada".
- En la **etapa 2** (tablas armadas desde la BD: EQUIFIBER, ONNET y Nacional cuando no hay Excel) se aplica la
  misma regla: un día **anterior al primer registro histórico** de la OLT en `OLT_TRAFICO_PEAK_DAY` va en **0**
  (la OLT aún no existía). Si la OLT ya existía y le falta un día, queda **vacío** (falla real de datos).

### 4.6 OLTs excluidas (laboratorio)
En `bw_olts_excluidas()` de `lib/bw_config.php`. No se agregan al Excel, no generan alertas y no se cargan en BD.
Para volver a incluir una, se borra o comenta su línea:
```
OLT1-1-LABONNET-5800, OLT1-2-LABONNET-5600, OLT61-LASERII-HW5800, OLT02-LASERII-HW5600, OLT62-LASERII-HW5800T
```

### 4.7 Alertas (solo web, archivo `alertas_{año}_W{nn}.json`)
| Tipo | Condición |
|---|---|
| `uplink` | % Uplink **≥ 99 %** (`BW_UMBRAL_ALERTA`) con día, OLT, PE-com y celda del Excel |
| `sin_registro` | OLT del Excel sin tráfico algún día de la semana (o toda la semana) |
| `capacidad` | La capacidad de la OLT cambió respecto del último día del Excel anterior |
| `olt_nueva` | OLT agregada al Excel |

---

## 5. Etapa 1 – Excel anual (`cron_reporte_bw_semanal.php`)

### Funcionamiento
1. Calcula la semana anterior (o la indicada con `--anio`/`--semana`).
2. Busca la **plantilla**: el último Excel `... W-{n}.xlsx` del mismo año en `Archivos_Auto/Semana` anterior a la
   semana pedida; si no hay, el último del año anterior (en ese caso se vacían los días: año nuevo).
3. Agrega los días faltantes hasta el domingo pedido (si una semana falló, la siguiente ejecución rellena también esos días).
4. Inserta OLTs nuevas, recalcula B, completa C solo si está vacía, agrega formato condicional y guarda los 2 archivos.
5. Escribe `alertas_{año}_W{nn}.json` y el log.
6. Si el archivo de la semana ya existe, no hace nada salvo `--forzar`.

### Opciones
```bash
php cron_reporte_bw_semanal.php                              # semana anterior
php cron_reporte_bw_semanal.php --anio=2026 --semana=42      # semana específica
php cron_reporte_bw_semanal.php --anio=2026 --semana=42 --forzar
php cron_reporte_bw_semanal.php --plantilla=/ruta/archivo.xlsx   # base distinta a la automática
php cron_reporte_bw_semanal.php --datos=prueba.json              # datos de prueba sin BD
```

### Por qué no se usa PHPExcel para escribir
El Excel tiene ~480 bloques de formato condicional, fórmulas compartidas y cientos de celdas combinadas.
PHPExcel 1.8 separa el formato condicional celda por celda y descarta reglas superpuestas. Por eso se edita el
XML del `.xlsx` directamente con `ZipArchive` + `DOMDocument` (clase `BwLibroXlsx` en `lib/bw_xlsx.php`):
- `normalizarFormulas()`: convierte fórmulas compartidas a `+Xn/Yn` para poder mover filas.
- `truncarDias()`, `agregarDia()`, `insertarFila()`, `rellenarHistorial()`, `reconstruirFilas()`.
- `agregarFormatoCondicional()`: reutiliza los formatos (dxfId) de las reglas 0.7 / 0.6–0.7 / < 0.6 del archivo.
- `guardar()`: elimina `calcChain.xml` y activa `fullCalcOnLoad` para que Excel recalcule al abrir.

---

## 6. Ventana web (`index_reporte_bw_semanal.php`)

- Selector de semana (lee los `alertas_*.json` de `Archivos_Auto/Semana`).
- Botones de descarga de los 2 Excel. La URL se arma desde `BW_DIR_SEMANA`, así que sigue cualquier cambio de carpeta.
- Recuadro de alertas: % Uplink ≥ 99 %, OLTs sin registro, cambios de capacidad, OLTs nuevas.
- Es un fragmento como el resto del panel (incluye `../perfiles/...` si existe). **Falta agregarlo al menú.**

---

## 7. Etapa 2 – Carga a BD (`cron_carga_bw_bd.php`)

### Tablas y períodos
| `--tipo` | Tablas | Claves | Período |
|---|---|---|---|
| `semana` | `OLT_CAPACIDAD_BW`, `_EQUIFIBER`, `_ONNET` | `week` (`'41'`), `year` | 7 días → 25 filas |
| `mes` | `OLT_CAPACIDAD_BW_MES*` | `mes` (`'01'`..`'12'`), `year` | Mes calendario (28–31 días) |
| `anio` | `OLT_CAPACIDAD_BW_YEAR*` | `year` | Año del reporte completo (52/53 semanas → 1096 filas en 2026) |

### Formato en la tabla (igual que `model/carga_excel*.php`)
Cada **columna** del Excel es una **fila** de la tabla; las celdas 1..162 van en los campos `` `1` ``..`` `162` ``,
más las claves y `fecha = NOW()`. Antes de insertar se borra el período (DELETE + INSERT en una transacción).
Máximo 160 OLTs por red (2 filas de encabezado + 160 = 162 campos); si hay más se omiten y queda advertencia en el log.

### Origen de los datos
| Red | OLTs | Origen |
|---|---|---|
| Nacional | `OLT_SERVER` (menos excluidas) | El Excel anual de `Archivos_Auto/Semana` si contiene todos los días del período (queda igual a lo descargado); si no, la BD con el orden de filas del Excel |
| EQUIFIBER | `OLT_SERVER_V2` | BD |
| ONNET | `OLT_SERVER` filtrado por `bw_olts_onnet()` (105 OLTs, lista del reporte ONNET) | BD |

Orden de filas en EQUIFIBER/ONNET (como sus reportes): primero OLTs de 10G o más y luego las de 1G, cada bloque
agrupado por PE-com (id) y capacidad descendente. Solo OLTs con tráfico en el período.

### Excel por período (antes de cargar la BD)
Igual que la subida manual (`js/upload/doajaxfileupload13…30.php`):
| Tipo | Carpeta (+ `_EQUIFIBER` / `_ONNET`) | Nombre |
|---|---|---|
| semana | `Archivos/Semana` | `Capacidades_OLTs_W-41_2026.xlsx` |
| mes | `Archivos/Mes` | `Capacidades_OLTs_Septiembre_2026.xlsx` |
| anio | `Archivos/Year` | `Capacidades_OLTs_2026.xlsx` |

Usa como base de formato el último Excel anual. Si falla el Excel, se registra en el log y la carga a BD continúa.

### Formato histórico por red (`bw_redes()` en `lib/bw_config.php`)
Validado con `--comparar` contra las cargas manuales de W-39, W-40 y W-41 de 2026:
| Red | PE-com | Fecha fila 2 |
|---|---|---|
| Nacional | Con Ñ (`ÑUÑOA`) | `Lunes 5 de Octubre` |
| EQUIFIBER | Sin Ñ (`NUNOA`, `ANFIOMUNOZ`) | `Lunes 05 de Octubre`; doble espacio antes del número jueves, sábado y domingo (`Jueves  08 de Octubre`) |
| ONNET | Sin Ñ | `Lunes 05 de Octubre`; doble espacio después del número el domingo (`Domingo 11  de Octubre`) |

Opciones: `pe_sin_enie`, `dia_2_digitos`, `dias_doble_espacio`, `dias_doble_espacio_despues` (días 1 = lunes … 7 = domingo).

### Opciones
```bash
php cron_carga_bw_bd.php --tipo=semana                         # semana anterior
php cron_carga_bw_bd.php --tipo=semana --anio=2026 --semana=41
php cron_carga_bw_bd.php --tipo=mes --anio=2026 --mes=9
php cron_carga_bw_bd.php --tipo=anio --anio=2026               # fuerza el año aunque no haya cerrado
php cron_carga_bw_bd.php ... --red=nacional,onnet              # solo algunas redes
php cron_carga_bw_bd.php ... --comparar                        # compara con lo existente en BD, NO escribe
php cron_carga_bw_bd.php ... --simular --csv=/tmp              # no escribe; deja lo generado en .csv
php cron_carga_bw_bd.php ... --sinexcel                        # carga BD sin guardar el Excel
php cron_carga_bw_bd.php ... --fuente=bd                       # Nacional desde BD aunque exista el Excel
php cron_carga_bw_bd.php ... --datos=prueba.json               # datos de prueba sin BD
```

`--comparar` resume las diferencias por tipo de celda (A, B, C, D, encabezados, fecha, tráfico, capacidad, % Uplink)
con ejemplos. Resultado esperado hoy: solo **B** (antes era un valor fijo que no se actualizaba).

---

## 8. Configuración (`lib/bw_config.php`)

| Constante / función | Valor | Uso |
|---|---|---|
| `BW_DIR_SEMANA` | `Archivos_Auto/Semana` | Carpeta de la etapa 1 (Excel anual, alertas, log) |
| `BW_CONEXION` | `módulo/../conexion/conexion_db.php` (= `/var/www/html/conexion/conexion_db.php`) | Variables `$host144_geret`, `$user144_geret`, `$pass144_geret` |
| `BW_BD` | `Aden` | Base de datos |
| `BW_UMBRAL_ALERTA` | `99` | % Uplink para alerta |
| `BW_PREFIJO_ARCHIVO` | `Capacidades OLTs` | Nombre del Excel anual |
| `BW_LOG` | `Archivos_Auto/Semana/log_reporte_bw_semanal.log` | Log de ambas etapas (la etapa 2 con prefijo `[BD]`) |
| `BW_FILAS_TABLA` | `162` | Campos `1..162` de las tablas |
| `bw_olts_excluidas()` | 5 OLTs de laboratorio | Ver 4.6 |
| `bw_olts_onnet()` | 105 OLTs | Red ONNET |
| `bw_redes()` | nacional / equifiber / onnet | Tabla de servidores, sufijo de tabla y formato histórico |

`BW_DIR_SEMANA` y `BW_CONEXION` se pueden redefinir antes de incluir la configuración (útil para pruebas con
`php -d auto_prepend_file=...`).

---

## 9. Tareas frecuentes

| Necesidad | Cómo |
|---|---|
| Regenerar el Excel de una semana | `php cron_reporte_bw_semanal.php --anio=2026 --semana=42 --forzar` |
| Recargar una semana en BD | `php cron_carga_bw_bd.php --tipo=semana --anio=2026 --semana=42` (reemplaza, no duplica) |
| Revisar antes de cargar | Agregar `--comparar` o `--simular --csv=/tmp` |
| Cambiar capacidad de un PE-com | Editar la celda C en el último Excel de `Archivos_Auto/Semana/` |
| Excluir / incluir una OLT | Editar `bw_olts_excluidas()` |
| Agregar / quitar OLT de ONNET | Editar `bw_olts_onnet()` |
| Cambiar umbral de alerta | `BW_UMBRAL_ALERTA` |
| Ver qué pasó | `Archivos_Auto/Semana/log_reporte_bw_semanal.log` y `cron_carga_bw_bd.log` |

---

## 10. Validación realizada

- Excel generados abiertos en Excel real: sin reparación, colores, combinaciones, fórmulas y paneles correctos.
- Casos probados: semana normal, semana faltante (rellena), re-ejecución sin `--forzar`, cambio de año, OLT nueva
  (en grupo existente y PE-com nuevo), OLT excluida, cambio de capacidad.
- Etapa 2 probada en MySQL 5.7 local con la estructura de `ESTRUCTURA_OLT.sql` (carga, re-carga sin duplicar,
  acentos en latin1) y en producción con `--comparar` W-39/W-40/W-41 2026: tráfico, capacidad por OLT, % Uplink,
  nombres y orden coinciden en las 3 redes.

## 11. Pendientes / observaciones

- Agregar `index_reporte_bw_semanal.php` al menú del panel.
- **C141 PE6-IQUIQUE:** el Excel tiene 30000 y la carga manual en BD tenía 40000; confirmar el valor correcto y
  corregirlo en el Excel si corresponde.
- `tabla_reporte_excel_mes_ONNET.php` (código previo) enlaza la descarga a `Archivos/Mes/` en vez de
  `Archivos/Mes_ONNET/`.
- Errores de tipeo de las cargas manuales que no se replicaron: "Sepriembre" (ONNET, septiembre 2026).
- El `.gitignore` de `panel_olt` ignora `*.xlsx`: el Excel base de la etapa 1 debe existir en el servidor en
  `Archivos_Auto/Semana/` (se copia a mano la primera vez).
