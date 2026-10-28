---
tipo: pendiente
proyecto: GERET
estado: pendiente
equipo: nuestro
origen: servidor 181 (cron eliminado)
destino: servidor 120 (portal en contenedor podman)
script: /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/respaldo_reporte_ont.php
creado: 2026-10-27
tags: [geret, cron, podman, migracion, pendiente, nuestro, onnet, ont]
---

# Migrar cron `respaldo_reporte_ont.php` del 181 al 120 (podman)

Volver a [[00 - Pendientes GERET]]

## Pendiente

El siguiente cron **fue eliminado del servidor 181** y hay que migrarlo al **servidor 120**:

```cron
50 14 * * * php -f /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/respaldo_reporte_ont.php > /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/respaldo_reporte_ont.log
```

En el 120 el portal (y el Portal OLT) corre **dentro de un contenedor podman**. Hay que revisar:
1. Si se puede agregar el cron en el servidor.
2. Que el cron actualice los archivos que ve el portal **sin botar el contenedor**.

## Qué hace el script

Copia local revisada: `C:\laragon\www\contingencia\OLT\ONNET\Reporte_ONNET_ONT\respaldo_reporte_ont.php`

- Corre **todos los días a las 14:50**.
- Se conecta a la BD `Aden` (`$host144_geret`, incluido desde `/var/www/html/contingencia/conexion/conexion_db.php`).
- Exporta **dos tablas completas** a CSV (separado por `;`):

| Tabla | Archivo generado |
|---|---|
| `Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO_ONNET` | `Archivos/ONNET/Export_Info_ONT_ONNET_YYYYMMDD_HHMMSS.csv` |
| `Aden.OLT_INFORMACION_ONT_DETALLE_COMPLETO_EQUIFIBER` | `Archivos/EQUIFIBER/Export_Info_ONT_EQUIFIBER_YYYYMMDD_HHMMSS.csv` |

- Último log en la copia local: **8 de enero de 2026** (ONNET 14:50:04, EQUIFIBER 14:50:06).

### Quién usa esos archivos

Las pantallas del portal **listan y descargan** los CSV desde esa misma carpeta, así que **el cron tiene que escribir donde el portal los lee** (dentro del contenedor):

| Pantalla | Carpeta que lee |
|---|---|
| `OLT/ONT/Respaldo_ONT/ONNET/index.php` y `descargar.php` | `Archivos/ONNET` |
| `OLT/ONT/Respaldo_ONT/EQUIFIBER/index.php` y `descargar.php` | `Archivos/EQUIFIBER` |
| `OLT/ONT/Respaldo_ONT/ENTEL/index.php` y `descargar.php` | las dos |
| `OLT/ONNET/Reporte_ONNET_ONT/unir_archivos.php` | las dos (las une) |

## Riesgos detectados en el script

| # | Riesgo | Impacto | Qué hacer |
|---|---|---|---|
| R1 | Usa `mysql_connect` / `mysql_query` (extensión **eliminada en PHP 7**). | Si el contenedor del 120 tiene PHP 7 u 8, el script **falla en la primera línea**. | Ver la versión de PHP del contenedor. Si es ≥ 7, pasarlo a `mysqli` (ver más abajo). |
| R2 | Arma todo el CSV en una variable (`memory_limit 2048M`). | Mucha memoria dentro del contenedor, compartida con el portal. | Escribir fila a fila con `fputcsv`. |
| R3 | El log se escribe **dentro de la carpeta web** con `>` (se pisa cada día). | El log queda accesible por URL y muestra rutas internas. | Dejar el log en el host, fuera del contenedor. |
| R4 | Los CSV se acumulan sin límite. | El disco del contenedor crece todos los días. | Definir retención (por ejemplo 90 días) y borrar los más antiguos. |
| R5 | Si `Archivos/` no es un volumen, los CSV viven en la capa del contenedor. | Se **pierden** si el contenedor se recrea (actualización de imagen, `podman rm`). | Montar `Archivos/` como volumen (ver opciones). |
| R6 | Zona horaria del contenedor. | Si está en UTC, el nombre del archivo queda con otra hora que la del cron. | Revisar `date` y `date.timezone` dentro del contenedor. |
| R7 | Conexión desde el 120 a la BD (`172.29.159.57`). | Si hay firewall, el script no conecta. | Probar la conexión desde dentro del contenedor. |

## Imagen vs. contenedor (por qué no hace falta botarlo)

- La **imagen** no se modifica: es la plantilla.
- El **contenedor** corriendo tiene su propia capa de escritura. `podman exec` ejecuta un comando **dentro del contenedor que ya está corriendo**: los archivos que escribe aparecen al instante para el portal, **sin reiniciar nada**.
- Lo que se escribe así se **pierde si el contenedor se recrea**, salvo que esa carpeta sea un **volumen** (un directorio del host montado en el contenedor).
- A un contenedor que ya está corriendo **no se le puede agregar un volumen**: requiere recrearlo una vez, en una ventana programada.

## Opciones

| Opción | Cómo | Bota el contenedor | Comentario |
|---|---|---|---|
| **A. Cron del host + `podman exec`** (recomendada) | El crontab del servidor 120 ejecuta el script **dentro** del contenedor con `podman exec`. | No | Usa el PHP y la conexión del contenedor, y escribe donde el portal lee. Es lo más parecido a como estaba en el 181. |
| B. Timer de systemd + `podman exec` | Lo mismo que A, pero con un `.timer` de systemd en vez de cron. | No | Útil si el 120 no tiene `cron` instalado o si podman es rootless (systemd `--user` con `loginctl enable-linger`). Deja el historial en `journalctl`. |
| C. Cron dentro del contenedor | Instalar y levantar `crond` dentro del contenedor. | Sí (para que quede permanente hay que cambiar la imagen) | No recomendado: un contenedor debería correr un solo proceso (el servidor web). |
| D. PHP del host sobre un volumen | El host corre `php` contra la carpeta montada. | Solo si hay que crear el volumen | Requiere PHP con mysqli instalado en el host. Duplica el entorno. |

### Persistencia de los CSV (complemento de A o B)

- **Fase 1 (sin botar el contenedor):** cron con `podman exec`; los CSV quedan en la capa del contenedor. Opcional: copiarlos también al host como respaldo con `podman cp`.
- **Fase 2 (en una ventana programada):** recrear el contenedor montando `.../Reporte_ONNET_ONT/Archivos` como volumen del host, para que los CSV sobrevivan a cualquier actualización. Si `/var/www/html` ya es un volumen, esta fase no hace falta.

## Plan paso a paso

### Paso 0 — Revisar el servidor 120 (solo lectura)

- [ ] **0.1** Identificar el contenedor y el usuario que lo corre:
  ```bash
  whoami
  podman ps --format "{{.Names}}  {{.Image}}  {{.Status}}"
  podman info --format '{{.Host.Security.Rootless}}'   # true = rootless (corre con un usuario normal)
  ```
- [ ] **0.2** Ver qué carpetas son volúmenes (si `/var/www/html` ya está montada desde el host, los archivos persisten):
  ```bash
  podman inspect <contenedor> --format '{{range .Mounts}}{{.Type}}  {{.Source}} -> {{.Destination}}{{"\n"}}{{end}}'
  ```
- [ ] **0.3** Confirmar que el código y las carpetas existen dentro del contenedor:
  ```bash
  podman exec <contenedor> ls -l /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/
  podman exec <contenedor> ls -ld /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/Archivos/ONNET \
                                  /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/Archivos/EQUIFIBER
  podman exec <contenedor> ls -l /var/www/html/contingencia/conexion/conexion_db.php
  ```
- [ ] **0.4** PHP y extensiones dentro del contenedor (R1):
  ```bash
  podman exec <contenedor> php -v
  podman exec <contenedor> php -m | grep -i mysql
  ```
  Si no aparece `mysql` (solo `mysqli` / `pdo_mysql`), el script hay que adaptarlo (paso 1).
- [ ] **0.5** Zona horaria (R6):
  ```bash
  date; podman exec <contenedor> date
  podman exec <contenedor> php -r 'echo date_default_timezone_get(), PHP_EOL;'
  ```
- [ ] **0.6** ¿Hay cron en el host?
  ```bash
  which crontab; systemctl status crond   # o "cron" según la distribución
  crontab -l
  ```
- [ ] **0.7** ¿Quedaron CSV históricos en el 181? Si se quieren conservar, copiarlos al 120 (paso 4).

### Paso 1 — Adaptar el script (solo si PHP ≥ 7)

- [ ] **1.1** Respaldar el original: `respaldo_reporte_ont.php.bak_YYYYMMDD`.
- [ ] **1.2** Cambiar `mysql_*` por `mysqli` y escribir fila a fila con `fputcsv` (resuelve R1 y R2). Esquema:
  ```php
  include('/var/www/html/contingencia/conexion/conexion_db.php');
  $db = new mysqli($host144_geret, $user144_geret, $pass144_geret, 'Aden');
  $db->set_charset('utf8');

  function exportar(mysqli $db, string $tabla, string $carpeta, string $prefijo): void
  {
      $ruta = $carpeta . $prefijo . date('Ymd_His') . '.csv';
      $f = fopen($ruta, 'w') or die("No se pudo crear $ruta\n");
      $res = $db->query("SELECT equipo, ip, frame_id, slot_id, port_id, onu_id, estado, region, comuna, onu_name,
                                onu_alias, sn_mac, olt_rx_onu, tx_optical_power, rx_optical_power, voltaje, temperature,
                                last_up_time, last_down_time, distancia, line_profile_name, service_profile_name,
                                modelo, fecha_registo
                         FROM Aden.$tabla", MYSQLI_USE_RESULT);       // sin cargar todo en memoria
      fputcsv($f, ['EQUIPO','IP','FRAME_ID','SLOT_ID','PORT_ID','ONU_ID','ESTADO','REGION','COMUNA','ONU_NAME',
                   'ONU_ALIAS','SN_MAC','OLT_RX_ONU','TX_OPTICAL_POWER','RX_OPTICAL_POWER','VOLTAJE','TEMPERATURE',
                   'LAST_UP_TIME','LAST_DOWN_TIME','DISTANCIA','LINE_PROFILE_NAME','SERVICE_PROFILE_NAME',
                   'MODELO','FECHA_REGISTRO'], ';');
      while ($row = $res->fetch_row()) {
          fputcsv($f, $row, ';');
      }
      $res->free();
      fclose($f);
      echo date('Y-m-d H:i:s') . " Archivo guardado: $ruta\n";
  }

  $base = '/var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/Archivos/';
  exportar($db, 'OLT_INFORMACION_ONT_DETALLE_COMPLETO_ONNET',     $base . 'ONNET/',     'Export_Info_ONT_ONNET_');
  exportar($db, 'OLT_INFORMACION_ONT_DETALLE_COMPLETO_EQUIFIBER', $base . 'EQUIFIBER/', 'Export_Info_ONT_EQUIFIBER_');
  $db->close();
  ```
  > `fputcsv` agrega comillas cuando un campo trae `;` o comillas. Revisar que `unir_archivos.php` y quien abra el CSV lo lean bien.
- [ ] **1.3** (R4) Agregar al final la limpieza de archivos antiguos, si se aprueba la retención:
  ```php
  foreach (glob($base . '{ONNET,EQUIFIBER}/Export_Info_ONT_*.csv', GLOB_BRACE) as $a) {
      if (filemtime($a) < strtotime('-90 days')) unlink($a);
  }
  ```
- [ ] **1.4** Subir el script al contenedor **sin reiniciarlo** (si `/var/www/html` no es volumen):
  ```bash
  podman cp respaldo_reporte_ont.php <contenedor>:/var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/respaldo_reporte_ont.php
  ```

### Paso 2 — Prueba manual

- [ ] **2.1** Ejecutar una vez a mano:
  ```bash
  podman exec <contenedor> php -f /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/respaldo_reporte_ont.php
  ```
- [ ] **2.2** Verificar que se crearon los 2 CSV con datos y que aparecen en las pantallas `Respaldo_ONT` (ONNET, EQUIFIBER, ENTEL).
- [ ] **2.3** Revisar cuánto tarda y la memoria usada, para que no afecte al portal.

### Paso 3 — Programar (opción A)

- [ ] **3.1** Crear la carpeta de logs en el host, fuera de la web (R3): `mkdir -p ~/logs/geret` (o `/var/log/geret` si corre como root).
- [ ] **3.2** Agregar al crontab **del mismo usuario que corre el contenedor** (`crontab -e`):
  ```cron
  # GERET — Respaldo reporte ONT ONNET/EQUIFIBER (migrado desde el 181 el AAAA-MM-DD)
  50 14 * * * /usr/bin/podman exec <contenedor> php -f /var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/respaldo_reporte_ont.php >> $HOME/logs/geret/respaldo_reporte_ont.log 2>&1
  ```
  - Usar la **ruta completa** de `podman` (`which podman`): cron corre con un `PATH` mínimo.
  - **Si podman es rootless**, cron no carga el entorno de la sesión. Agregar al inicio del crontab:
    ```cron
    XDG_RUNTIME_DIR=/run/user/<uid>
    ```
    y verificar `loginctl enable-linger <usuario>`, para que el contenedor y el usuario sigan activos sin una sesión abierta.
- [ ] **3.3** Alternativa (opción B) si no hay cron: un `respaldo-reporte-ont.service` + `.timer` con `OnCalendar=*-*-* 14:50:00` que ejecute el mismo `podman exec`.

### Paso 4 — Históricos (opcional)

- [ ] **4.1** Si en el 181 quedaron CSV de días anteriores, copiarlos al host del 120 y luego al contenedor:
  ```bash
  podman cp ./ONNET/.     <contenedor>:/var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/Archivos/ONNET/
  podman cp ./EQUIFIBER/. <contenedor>:/var/www/html/contingencia/OLT/ONNET/Reporte_ONNET_ONT/Archivos/EQUIFIBER/
  ```

### Paso 5 — Verificación

- [ ] **5.1** Al día siguiente, después de las 14:50: revisar el log del host y que existan los CSV del día.
- [ ] **5.2** Descargar un archivo desde el portal y abrirlo.

### Paso 6 — Persistencia (fase 2, en ventana programada)

- [ ] **6.1** Si `Archivos/` no es volumen: planificar con el responsable del 120 recrear el contenedor montando `Archivos/` desde el host. **Antes**, copiar fuera los CSV con `podman cp`.
- [ ] **6.2** Anotar en esta nota cómo se levanta el contenedor (comando `podman run`, `podman-compose` o `quadlet`), para no perder la configuración.

## Vuelta atrás

- Quitar la línea del crontab (o deshabilitar el timer).
- Restaurar el `.bak` del script con `podman cp` si se había modificado.
- No se reinicia nada en ningún paso, así que no hay impacto en el portal.

## Decisiones pendientes

- [ ] ¿Quién tiene acceso para editar el crontab del 120 y con qué usuario corre podman?
- [ ] Retención de los CSV (¿90 días?).
- [ ] ¿Se copian los históricos del 181?
- [ ] ¿Se programa la fase 2 (volumen para `Archivos/`)?
- [ ] ¿Hay otros crons del 181 que también deban pasar al 120? Revisar el crontab completo del 181 contra el del 120.
