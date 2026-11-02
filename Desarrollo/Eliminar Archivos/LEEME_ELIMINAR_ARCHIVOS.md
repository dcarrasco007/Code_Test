# Eliminar archivos csv / Excel del portal OLT

Ventana con dos partes:

1. **Rutas permitidas**: un mantenedor donde se registran las carpetas desde las que se puede borrar.
2. **Archivos encontrados**: todos los csv, xls y xlsx de las rutas activas, incluidas sus subcarpetas. Cada uno se
   muestra con su ubicación y se elimina de a uno, con confirmación.

No se revisa cada archivo por separado. Lo que se puede borrar depende de las rutas que se registren.

## Archivos

```
sql/ESTRUCTURA_ELIMINAR_ARCHIVOS.sql          Tablas + rutas iniciales (O&M y Capacidad BW) + INSERT de menú comentado
120/Eliminar_Archivos/                        → servidor 120 (contingencia_v3): /var/www/html/Eliminar_Archivos
contingencia/OLT/Eliminar_Archivos/           → servidor 181 (contingencia): carpeta OLT/Eliminar_Archivos
    index_eliminar_archivos.php               Vista (se carga dentro de menu.php)
    ajax_eliminar_archivos.php                Endpoint POST/JSON: cargar, agregar_ruta, estado_ruta, quitar_ruta, eliminar, log
    lib/ea_config.php                         Raíz, conexión, extensiones, carpetas protegidas y usuarios/perfiles con acceso
    lib/ea_funciones.php                      Validación de rutas, escaneo, borrado y log
```

Las dos copias son iguales salvo `lib/ea_config.php`, que cambia la ruta de la conexión:
- 120: `EA_RAIZ/conexion/conexion_db.php`
- 181: `EA_RAIZ/../conexion/conexion_db.php`

Todo el código es compatible con PHP 5.3 y 5.5, y no necesita mbstring.

## Instalación

1. Ejecutar `sql/ESTRUCTURA_ELIMINAR_ARCHIVOS.sql` en Aden. Hay una sola BD, así que las rutas y el log quedan
   compartidos entre el 120 y el 181.
2. Copiar la carpeta `Eliminar_Archivos` a cada servidor. En el 120, como hay un volumen, se copia en la carpeta del
   host: `/var/www/Contenedor/portal-php53/www/Eliminar_Archivos`.
3. Crear la opción de menú con el INSERT comentado al final del SQL, completando el id, la posición y el menú padre.
   Luego dar acceso al perfil desde Administración > Perfiles.
4. Por defecto solo entra el usuario `admin`. Para dar acceso a otros perfiles, agregar su `id_perfil` en
   `ea_perfiles_permitidos()` de `lib/ea_config.php`. El endpoint vuelve a revisar el acceso en cada petición.
5. El usuario web (`www-data`) necesita permiso de escritura en las carpetas para poder borrar. Si no lo tiene, la
   ventana muestra "No se pudo eliminar el archivo (revisar permisos del usuario web)".

## Reglas de seguridad

**Al agregar una ruta:**
- Se normaliza: `\` pasa a `/` y se quitan las barras de los extremos.
- No puede contener `.`, `..` ni `:`.
- Debe tener al menos dos niveles, por ejemplo `ONNET/Reporte_X/Archivos`.
- Su primera carpeta no puede ser protegida: conexion, Lib, js, menu, perfiles, usuarios, etc.
- Tiene que existir en el servidor y quedar dentro de la raíz del portal (se verifica con `realpath`).
- No puede repetir otra ruta, quedar dentro de una ya registrada ni contener una ya registrada. Así cada archivo
  pertenece a una sola ruta.

**Al eliminar**, el navegador solo envía el id de la ruta y la ruta relativa del archivo en base64. El servidor
vuelve a revisar que:
- la ruta esté activa;
- el `realpath` del archivo quede dentro de la ruta;
- sea un archivo normal, no un acceso directo;
- su extensión sea csv, xls o xlsx.

Después ejecuta `unlink`. Nunca borra carpetas.

**Además:**
- Cada POST lleva un token de sesión.
- Todo queda en `OLT_ELIMINAR_ARCHIVOS_LOG`: borrados, altas, activaciones y bajas de rutas, con usuario, IP,
  servidor y fecha.
- Quitar una ruta no borra archivos; solo deja de mostrarlos.

## Rutas iniciales

El SQL deja activas las 24 carpetas `ONNET/<reporte>/Archivos` de los Reportes enfocados a O&M y
`Capacidad_BW_Excel/Archivos`. Esta última tiene un aviso en el campo NOTA: si el período sigue en BD, el Excel se
vuelve a crear cuando alguien lo descarga, y el cron del 120 crea la semana y el mes recién cerrados.

Intencionalmente **no** se incluyó `ONNET/Archivos`: ahí está el csv `Access_Service_Statistics_*` que lee
`ONNET/Procesos/proceso_lee_archivo.php`. Si se agrega, conviene ponerle una nota.

Si una ruta no existe en un servidor, aparece en rojo ("La carpeta no existe en este servidor") y no se lista nada
para ella.

## Pruebas realizadas (local, PHP 7.3 + MySQL 5.7 temporal)

- **Acceso:** sin sesión, usuario sin perfil y token incorrecto quedan rechazados.
- **Alta de rutas:** se rechazan la carpeta protegida, `..`, un solo nivel, una carpeta inexistente, una duplicada
  (también escrita con `/` sobrantes) y las anidadas en ambos sentidos.
- **Listado:** muestra csv, CSV, xls y xlsx en subcarpetas, también con nombres con ñ. No muestra `.php` ni archivos
  fuera de las rutas.
- **Borrado:** se rechazan `../../../conexion/secreto.csv`, un `.php`, un archivo de otra ruta, un base64 inválido,
  una ruta inactiva y un archivo ya borrado. El borrado válido funciona y queda en el log.
- **Vista:** probada en el navegador con jQuery 1.12 y Bootstrap 3 (modal, filtros, contadores y log).

Falta probarla en el 120 y el 181 con su PHP real.
