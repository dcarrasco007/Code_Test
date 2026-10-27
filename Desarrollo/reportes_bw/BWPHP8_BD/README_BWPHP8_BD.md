# Reporte BW anual en BD (PHP 8.0) – Etapa 1

Nueva arquitectura del reporte BW semanal: el cron **no lee ni genera Excel**, solo trabaja con la BD.
El "Excel anual" vive en la tabla `OLT_REPORTE_BW_ANUAL` (una fila por OLT y día) y la ventana web
arma el Excel desde la BD **al presionar el botón de descarga**.

Reemplaza a la etapa 1 en disco (`Capacidad_BW_Excel/Archivos_Auto/Semana`). La etapa 2 (carga de
`OLT_CAPACIDAD_BW*`) se adaptará en una segunda fase.

---

## 1. Arquitectura

```
 SERVIDOR DE CRON (PHP 8.0, solo BD)          BD Aden                       SERVIDOR WEB (contingencia_v3, PHP 8)
 cron lunes 04:00                        OLT_REPORTE_BW_ANUAL              index_reporte_bw_semanal.php
  · lee tráfico peak y capacidad   ───▶  (1 fila por OLT y día)   ◀───      · selector de semana + alertas (SQL)
  · agrega los 7 días de la semana                                          descarga_reporte_bw.php
  · agrega OLTs nuevas                                                      · arma el Excel con la plantilla y lo descarga
  · calcula C de PE-com nuevos
```

## 2. Archivos

```
BWPHP8_BD/
├── sql/
│   ├── 01_crear_tabla_OLT_REPORTE_BW_ANUAL.sql   Crear la tabla (una vez)
│   └── 02_carga_inicial_2026_hasta_W42.sql       Carga inicial desde "Capacidades OLTs Octubre 2026 W-42.xlsx"
├── cron/                         → servidor de cron: /var/www/procesos/php/BWPHP8/
│   ├── cron_reporte_bw_anual.php
│   ├── lib/  bw_config.php bw_fechas.php bw_datos.php bw_anual.php
│   └── logs/
└── web/Capacidad_BW_Excel/       → servidor web: /var/www/html/Capacidad_BW_Excel/
    ├── index_reporte_bw_semanal.php     Ventana (selector de semana)
    ├── alertas_reporte_bw.php           Botones + alertas de la semana (fragmento)
    ├── descarga_reporte_bw.php          Arma y descarga el Excel
    ├── lib/  (los mismos 4 de cron/lib + bw_xlsx.php, bw_anual_excel.php, bw_web.php)
    └── plantilla/plantilla_reporte_bw.xlsx   Formato (W-42 con un solo día, 31 KB)
```

`bw_config.php`, `bw_fechas.php`, `bw_datos.php` y `bw_anual.php` son **idénticos** en cron y web:
si se modifica uno, copiarlo a ambos lados.

## 3. Tabla `OLT_REPORTE_BW_ANUAL`

| Campo | Contenido |
|---|---|
| `anio`, `semana`, `fecha` | Año y semana del reporte (semana 1 = la que contiene el 1 de enero) y día |
| `orden` | Posición de la OLT en el Excel (1 = fila 3) |
| `grupo`, `pe_com` | Grupo de PE-com (celdas A/B/C combinadas) y su nombre |
| `olt` | OLT (columna D) |
| `trafico`, `capacidad` | Mbps del día. `trafico` NULL = sin registro |
| `cap_pe` | Columna C del grupo (mismo valor en todas sus filas) |
| `alta` | Lunes de la semana en que la OLT se agregó (NULL = carga inicial) |

Clave única `(anio, olt, fecha)`. ~55.000 filas por año.

## 4. Despliegue

1. **BD:** ejecutar `sql/01_crear_tabla_OLT_REPORTE_BW_ANUAL.sql` y luego `sql/02_carga_inicial_2026_hasta_W42.sql`
   (44.100 filas: 150 OLTs × 294 días, 29-12-2025 al 18-10-2026).
2. **Cron:** copiar `cron/` a `/var/www/procesos/php/BWPHP8/`. Conexión: `/var/www/procesos/php/conexion/conexion_db.php`
   (convención de los procesos PHP 8; se cambia en `BW_CONEXION` de `cron_reporte_bw_anual.php`).
3. **Web:** copiar `web/Capacidad_BW_Excel/` a `/var/www/html/Capacidad_BW_Excel/`. Conexión:
   `/var/www/html/conexion/conexion_db.php` (`lib/bw_web.php`). Requiere extensiones `mysqli`, `zip`, `dom`.
   La web usa el primer archivo que exista entre: `módulo/../conexion/conexion_db.php`, `DOCUMENT_ROOT/conexion/`,
   `/var/www/html/conexion/` y `/var/www/conexion/`. Si no encuentra ninguno, el error indica la ruta probada.
   (Un error "Can't connect to local MySQL server through socket" significa que no se cargó el archivo de conexión.)
4. **Menú (v3):** `$(".contenido").load('../Capacidad_BW_Excel/index_reporte_bw_semanal.php');`
5. **Semanas faltantes:** si la carga inicial llega a W-42 y el cron parte después, la primera ejecución
   completa automáticamente las semanas que falten hasta la semana anterior.

## 5. Cron

```bash
0 4 * * 1 php /var/www/procesos/php/BWPHP8/cron_reporte_bw_anual.php > /var/www/procesos/php/BWPHP8/logs/cron.log 2>&1
```
Opciones:
```bash
php cron_reporte_bw_anual.php --anio=2026 --semana=43          # semana específica
php cron_reporte_bw_anual.php --anio=2026 --semana=43 --forzar # regenerar (borra desde esa semana y vuelve a cargar)
php cron_reporte_bw_anual.php --simular                        # ejecuta todo y deshace (no guarda)
php cron_reporte_bw_anual.php --datos=prueba.json              # datos de prueba sin BD de tráfico
```
No usar `php -f archivo --anio=...` (PHP toma `--anio` como opción propia). Log: `logs/log_reporte_bw_anual.log`.

## 6. Reglas (las mismas acordadas para el Excel en disco)

- **Semanas:** lunes a domingo; semana 1 = la que contiene el 1 de enero (2026: 52 semanas; 28-12-2026 = W-1 2027).
  Al iniciar un año nuevo se copia la estructura (OLTs, grupos, orden, C) del año anterior, sin datos.
- **Tráfico:** `OLT_TRAFICO_PEAK_DAY.peak_mb` (Mbps, entero).
- **Capacidad OLT:** `capacidad_total / 2` si es de 10G o más; 1G sin dividir. Si la BD no la trae, la última conocida.
- **Día sin registro:** `trafico` NULL (vacío en el Excel, alerta "sin registro").
  **Antes del primer registro histórico de la OLT:** 0.
- **OLT nueva** (con tráfico en la semana y no excluida): al final de su grupo de PE-com, o en un grupo nuevo al
  final; sus días anteriores del año quedan con tráfico 0 y la capacidad obtenida esa semana.
- **OLTs excluidas (laboratorio):** `bw_olts_excluidas()` en `lib/bw_config.php`.
- **Columna B:** se calcula al descargar = promedio diario de la suma del tráfico del grupo en la semana elegida.
- **Columna C (`cap_pe`):** se mantiene. Solo para un PE-com nuevo se calcula la suma de sus OLTs al domingo.
  Para cambiarla:
  ```sql
  -- ver el grupo:  SELECT DISTINCT grupo, pe_com, cap_pe FROM OLT_REPORTE_BW_ANUAL WHERE anio = 2026 ORDER BY grupo;
  UPDATE OLT_REPORTE_BW_ANUAL SET cap_pe = 40000 WHERE anio = 2026 AND grupo = 34;
  ```
  (Se usa `grupo` porque un mismo nombre de PE-com puede aparecer en dos grupos, ej. VITACURA.)

## 7. Ventana web y alertas

- Selector con las semanas completas (7 días) cargadas en la tabla.
- 2 botones con el mismo contenido: `Capacidades OLTs {Mes} {Año} W-{n}.xlsx` y `... Act - Act.xlsx`
  (mes = el del domingo). El Excel trae el año hasta el domingo de la semana elegida, así que se puede
  descargar cualquier semana pasada. Tarda ~3 segundos.
- Alertas calculadas con SQL al elegir la semana (no se guardan):
  % Uplink ≥ 99 % (`BW_UMBRAL_ALERTA`, con la celda del Excel), OLTs sin registro, cambio de capacidad
  respecto del domingo anterior y OLTs nuevas (`alta`).

## 8. Validación realizada (local, PHP 8.3 + MySQL 5.7)

- Carga inicial: 44.100 filas, 150 OLTs, 37 grupos.
- **Excel W-42 armado desde la BD vs el W-42 original:** iguales en valores, fórmulas, columnas B/C y las 354
  celdas combinadas. Única diferencia: 9 rótulos de inicios de marzo que en el original estaban con 2 dígitos
  ("Domingo 01 de Marzo").
- Cron W-43: simulación sin cambios en BD; carga real (+1.064 filas); OLT nueva en D. URZUA con historial en 0
  y su capacidad; PE-com nuevo con C calculada; OLT de laboratorio excluida; re-ejecución omitida; `--forzar`
  sin duplicar; semana faltante (W-44) completada al generar W-45; año nuevo 2027 con estructura copiada.
- Alertas W-43: 99,5 %, día sin registro, cambio de capacidad y 2 OLTs nuevas.
- Excel W-43 abierto en Excel: sin reparación, colores, combinaciones y fórmulas correctas.

## 9. Pendientes

- Etapa 2 (carga de `OLT_CAPACIDAD_BW`, `_MES`, `_YEAR` y hermanas) con la nueva arquitectura.
- En la carga inicial, 18 celdas (capacidad de DURZUA-13/14/15 del 12 al 14-10) quedaron en 0 como en el
  archivo "W-42"; el archivo "Act - Act" las tenía con su capacidad. Si se quiere esa versión:
  `UPDATE OLT_REPORTE_BW_ANUAL SET capacidad = 30000 WHERE anio = 2026 AND olt IN ('OLT-DURZUA-14','OLT-DURZUA-15') AND fecha BETWEEN '2026-10-12' AND '2026-10-14';`
  `UPDATE OLT_REPORTE_BW_ANUAL SET capacidad = 20000 WHERE anio = 2026 AND olt = 'OLT-DURZUA-13' AND fecha BETWEEN '2026-10-12' AND '2026-10-14';`
