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

### Datos del contenedor (revisados el 2026-10-28)

| Dato | Valor |
|---|---|
| Host / usuario | `web-pgpon` / `reyt_dcarrasco` |
| Contenedor | `portal-php53` (imagen `localhost/portal-php53_php53:latest`, puerto 8080 → 80) |
| Podman | **rootless** (corre con el usuario `reyt_dcarrasco`) |
| PHP del contenedor | 5.5.9 (Ubuntu), Apache 2 |
| Usuario web | `www-data` |
| Volumen | host `/var/www/Contenedor/portal-php53/www` → contenedor `/var/www/html` |
| Módulo (contenedor) | `/var/www/html/Capacidad_BW_Excel` |
| Módulo (host) | `/var/www/Contenedor/portal-php53/www/Capacidad_BW_Excel` |
| Conexión BD | `/var/www/html/conexion/conexion_db.php` (la encuentra `lib/bw_web.php`) |

Como `/var/www/html` es un **volumen**, los Excel que deja el cron en `Archivos/` persisten aunque se recree el
contenedor, y los archivos se despliegan copiándolos en la carpeta del host (no hace falta `podman cp`).

### 1. Revisar antes de programar (solo lectura)

```bash
which podman                     # ruta completa para el crontab (normalmente /usr/bin/podman)
id -u                            # uid de reyt_dcarrasco, para XDG_RUNTIME_DIR
loginctl show-user reyt_dcarrasco --property=Linger    # debe decir Linger=yes
podman exec portal-php53 php -m | grep -iE 'mysqli|zip|dom'
podman exec portal-php53 ls -l /var/www/html/conexion/conexion_db.php /var/www/html/Capacidad_BW_Excel/Archivos
crontab -l
```

- Si `Linger=no`, el contenedor rootless y el cron dependen de que haya una sesión abierta: pedir
  `loginctl enable-linger reyt_dcarrasco` (lo hace root).
- Si en `php -m` falta `mysqli`, `zip` o `dom`, el cron no puede correr (la ventana de la etapa 1 ya los usa vía Apache,
  pero el PHP de consola puede tener otra configuración).

### 2. Desplegar y probar a mano (no escribe)

Copiar `etapa2_portal/v3_120/Capacidad_BW_Excel/` sobre `/var/www/Contenedor/portal-php53/www/Capacidad_BW_Excel/`.

```bash
podman exec --user www-data portal-php53 php /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.php --tipo=semana --anio=2026 --semana=42 --comparar
```

### 3. Crontab del host (`crontab -e` con el usuario `reyt_dcarrasco`)

```bash
mkdir -p ~/logs/geret
```

```cron
# Podman rootless: cron no carga el entorno de la sesión (reemplazar 1000 por el resultado de "id -u")
XDG_RUNTIME_DIR=/run/user/1000

# GERET - Capacidad BW etapa 2 (carga OLT_CAPACIDAD_BW* + Excel en Archivos/)
30 4 * * 1 /usr/bin/podman exec --user www-data portal-php53 php /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.php --tipo=semana >> $HOME/logs/geret/carga_bw_semana.log 2>&1
45 4 * * 1 /usr/bin/podman exec --user www-data portal-php53 php /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.php --tipo=anio   >> $HOME/logs/geret/carga_bw_anio.log 2>&1
0 5 1 * *  /usr/bin/podman exec --user www-data portal-php53 php /var/www/html/Capacidad_BW_Excel/cron_carga_bw_bd.php --tipo=mes    >> $HOME/logs/geret/carga_bw_mes.log 2>&1
```

- `--user www-data`: los Excel quedan con el mismo dueño que Apache, que así puede reemplazarlos.
- No usar `php -f archivo --tipo=...` (PHP toma `--tipo` como opción propia).
- La semana (04:30) debe correr **después** de la etapa 1 en el 121 (`cron_reporte_bw_anual.php`, 04:00), que llena
  `OLT_REPORTE_BW_ANUAL`. El mes del día 1 completa desde `OLT_TRAFICO_PEAK_DAY` los días que la etapa 1 aún no cerró.
- Revisar el resultado: `tail ~/logs/geret/carga_bw_semana.log` y
  `ls -l /var/www/Contenedor/portal-php53/www/Capacidad_BW_Excel/Archivos/Semana`.

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
