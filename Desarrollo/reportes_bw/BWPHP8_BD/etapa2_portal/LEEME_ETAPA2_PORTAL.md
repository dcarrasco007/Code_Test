# Etapa 2 en los portales: cron en el 120 (podman) y "Descargar Archivo" en el 120 y el 181

La carga de `OLT_CAPACIDAD_BW`, `_MES`, `_YEAR` (y `_EQUIFIBER` / `_ONNET`) corre en el **servidor 120**, dentro del
contenedor podman del portal OLT. Antes de cada carga deja el Excel del período en `Capacidad_BW_Excel/Archivos/`
del 120, igual que la subida manual. El **181** no recibe ese archivo: su link "Descargar Archivo" lo busca en su
carpeta y, si no está, lo arma desde la BD y lo guarda, así los dos servidores terminan con los Excel.

| Servidor | Qué corre | PHP |
|---|---|---|
| **121** (procesos, sin contenedor) | Etapa 1: `cron_reporte_bw_anual.php` (llena `OLT_REPORTE_BW_ANUAL`) | 8.0 |
| **120** (contenedor podman) | Portal **contingencia_v3** (`Capacidad_BW_Excel/` en la raíz del sitio) + **etapa 2** (3 crons) | 5.5 |
| **181** | Portal **contingencia** (`OLT/Capacidad_BW_Excel/`) | 5.3 |

Los archivos de cada carpeta van a su servidor; `descargar_excel_bw.php` es el mismo en los dos portales. Todo el
código es compatible con PHP 5.3+ (sin sintaxis ni funciones de PHP 7) y se probó con PHP 7.3 y 8.3.

## Archivos

```
etapa2_portal/
├── v3_120/Capacidad_BW_Excel/            → servidor 120 (dentro del contenedor)
│   ├── cron_carga_bw_bd.php              Cron etapa 2 (carga BD + deja el Excel en Archivos/)
│   ├── descargar_excel_bw.php            Link "Descargar Archivo"
│   ├── tabla_reporte_excel*.php (9)      Solo cambia la línea del link
│   └── lib/  bw_carga_bd.php  bw_excel_periodo.php   (se suman a lib/ de la etapa 1)
│             bw_web.php                              (reemplaza al de la etapa 1: dirname() compatible con PHP 5.5)
└── contingencia_181/OLT/Capacidad_BW_Excel/   → servidor 181
    ├── descargar_excel_bw.php
    ├── tabla_reporte_excel*.php (9)
    └── lib_bd/  bw_excel_periodo.php                 (se suma a lib_bd/ de la etapa 1)
```

Requisito: que en el módulo ya esté desplegada la ventana de la etapa 1 (`lib/` en v3, `lib_bd/` en contingencia,
y `plantilla/plantilla_reporte_bw.xlsx`), porque se reutilizan su conexión (`bw_web.php`), `bw_xlsx.php` y la plantilla.

Fuentes de las librerías (si se modifican, copiarlas aquí): `BWPHP8_BD/cron/lib/bw_carga_bd.php` y
`BWPHP8_BD/cron/lib/bw_excel_periodo.php`.

## Link "Descargar Archivo" (`descargar_excel_bw.php`)

`descargar_excel_bw.php?tipo=semana|mes|anio&red=nacional|equifiber|onnet&anio=2026&num=42`

1. Si `Archivos/{Semana|Mes|Year}{_EQUIFIBER|_ONNET}/{nombre}.xlsx` existe y no es anterior a la última carga del
   período en BD (campo `fecha`, con 1 hora de margen), entrega ese archivo (incluye los subidos a mano).
2. Si no existe o quedó desactualizado (ej: se volvió a cargar el período), lo arma desde la tabla
   `OLT_CAPACIDAD_BW*` (mismo filtro que la página: `week`/`mes` y `year`), lo guarda en la carpeta y lo entrega.
3. Si no hay archivo ni datos en BD, muestra el mensaje y un link para volver.

Nombres iguales a la subida manual: `Capacidades_OLTs_W-42_2026.xlsx`, `Capacidades_OLTs_Septiembre_2026.xlsx`,
`Capacidades_OLTs_2026.xlsx`. Se corrigió de paso el link de mes ONNET, que apuntaba a `Archivos/Mes` en vez de
`Archivos/Mes_ONNET`.

## Cron en el 120 (podman)

El portal corre dentro de un contenedor, así que el crontab **del host 120** ejecuta el PHP **dentro del contenedor**
con `podman exec` (no se reinicia nada y el Excel queda donde el portal lo lee). El log queda en el host, fuera de la web.

### 1. Revisar el contenedor (solo lectura)

```bash
podman ps --format "{{.Names}}  {{.Image}}  {{.Status}}"
podman info --format '{{.Host.Security.Rootless}}'          # true = rootless
podman exec <contenedor> php -v
podman exec <contenedor> php -m | grep -iE 'mysqli|zip|dom'
podman exec <contenedor> sh -c 'ls -d /var/www/Capacidad_BW_Excel /var/www/html/Capacidad_BW_Excel 2>/dev/null'
podman exec <contenedor> ps aux | grep -E 'apache|httpd|nginx|php-fpm' | head -3   # usuario web
podman inspect <contenedor> --format '{{range .Mounts}}{{.Source}} -> {{.Destination}}{{"\n"}}{{end}}'
```

### 2. Probar a mano (no escribe)

```bash
podman exec <contenedor> php <modulo>/cron_carga_bw_bd.php --tipo=semana --anio=2026 --semana=42 --comparar
```

### 3. Crontab del host (mismo usuario que corre podman)

```cron
# GERET - Capacidad BW etapa 2 (carga OLT_CAPACIDAD_BW* + Excel en Archivos/)
30 4 * * 1 /usr/bin/podman exec --user <usuario_web> <contenedor> php <modulo>/cron_carga_bw_bd.php --tipo=semana >> /var/log/geret/carga_bw_semana.log 2>&1
45 4 * * 1 /usr/bin/podman exec --user <usuario_web> <contenedor> php <modulo>/cron_carga_bw_bd.php --tipo=anio   >> /var/log/geret/carga_bw_anio.log 2>&1
0 5 1 * *  /usr/bin/podman exec --user <usuario_web> <contenedor> php <modulo>/cron_carga_bw_bd.php --tipo=mes    >> /var/log/geret/carga_bw_mes.log 2>&1
```

- `<modulo>`: ruta del módulo dentro del contenedor (paso 1), ej. `/var/www/Capacidad_BW_Excel`.
- `--user <usuario_web>` (ej. `www-data` o `apache`): los Excel quedan con el mismo dueño que la web, que así puede
  reemplazarlos. Si se omite, el cron corre como el usuario por defecto del contenedor; igual se dejan con permiso 0666.
- Usar la ruta completa de podman (`which podman`). Si es **rootless**, agregar al inicio del crontab
  `XDG_RUNTIME_DIR=/run/user/<uid>` y verificar `loginctl enable-linger <usuario>`.
- Crear antes la carpeta de logs del host (`mkdir -p /var/log/geret`, o `~/logs/geret` si no es root).
- La semana (04:30) debe correr **después** de la etapa 1 (`cron_reporte_bw_anual.php`, 04:00), que llena
  `OLT_REPORTE_BW_ANUAL`. El mes del día 1 completa desde `OLT_TRAFICO_PEAK_DAY` los días que la etapa 1 aún no cerró.
- Si `Archivos/` no es un volumen del host, los Excel viven en la capa del contenedor y se pierden si se recrea;
  aun así el link "Descargar Archivo" los vuelve a armar desde la BD.

### 4. Apagar los otros crons de la etapa 2

Solo debe quedar uno cargando las tablas:
- 181: `contingencia/OLT/Capacidad_BW_Excel/cron_carga_bw_bd.php` (versión antigua desde Excel en disco).
- Servidor de procesos: `/var/www/procesos/php/BWPHP8/cron_carga_bw_bd.php` (versión solo BD, si se había instalado).

## Validación local

- Cron (PHP 8.3, estructura v3): W-42 y septiembre cargados en BD y Excel guardados en `Archivos/Semana` y
  `Archivos/Mes`; carga anual omitida porque la semana anterior no cierra el año.
- Link en el 181 (PHP 7.3, estructura contingencia): sin archivo → lo armó desde BD (0,15 s), lo guardó y lo entregó;
  segunda descarga → archivo del disco; archivo anterior a la carga en BD → se volvió a armar; ONNET sin carpeta →
  creó `Archivos/Semana_ONNET`; sin datos → mensaje; parámetros inválidos → mensaje.
- El Excel del cron del 120 y el armado por el 181 tienen el **mismo contenido** (las 25 partes del .xlsx).
- Abiertos en Excel sin reparación; todas las celdas iguales a la tabla en BD, salvo `% Uplink` de las OLTs con
  capacidad 0 (DURZUA-13/14/15): en BD queda vacío y en el Excel se muestra 0 % en vez de `#¡DIV/0!`.
