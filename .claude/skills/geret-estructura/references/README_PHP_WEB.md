# Estructura Base Para Proyectos PHP Web

Este README describe la estructura general utilizada por los proyectos PHP web de GERET.

## Estructura General

```text
proyecto_php/
|-- app/
|   |-- config/
|   |   `-- conexion.php
|   `-- model/
|       `-- nombre_model/
|           |-- scripts/
|           |   `-- s_nombre_script.php
|           `-- functions/
|               `-- f_nombre_model.php
|-- assets/
|   |-- archives/
|   |-- build/
|   |-- dist/
|   |-- img/
|   |-- lib/
|   `-- plugins/
|-- pages/
|   |-- nombre_vista.php
|   |-- general/
|   |   |-- head.php
|   |   |-- sidebar.php
|   |   |-- navbar.php
|   |   |-- footer.php
|   |   `-- script.php
|   `-- nombre_modulo/
|       |-- components/
|       |   `-- modelo.php
|       |-- css/
|       |   `-- style.css
|       |-- js/
|       |   `-- index.js
|       `-- content.php
|-- php_lib/
`-- elements/
```

## Flujo General De Una Vista

En estos proyectos normalmente existe un archivo PHP directo dentro de `pages/`, por ejemplo:

```text
pages/dashboard_general.php
pages/vista_llaves.php
pages/login.php
pages/nombre_vista.php
```

Este archivo funciona como plantilla principal de la pantalla.

Su responsabilidad es cargar el layout general del proyecto y luego importar el contenido del modulo.

Ejemplo de flujo:

```php
<?php include 'general/head.php'; ?>
<?php include 'general/sidebar.php'; ?>
<?php include 'general/navbar.php'; ?>

<main>
    <?php include 'nombre_modulo/content.php'; ?>
</main>

<?php include 'general/footer.php'; ?>
<?php include 'general/script.php'; ?>
```

## `pages/general/`

Contiene los elementos generales de cada proyecto.

Aqui van los componentes compartidos por varias pantallas, por ejemplo:

- `head.php`: etiquetas base del documento, CSS global, librerias y configuraciones iniciales.
- `sidebar.php`: menu lateral.
- `navbar.php` o `header.php`: barra superior.
- `footer.php`: pie de pagina.
- `script.php`: scripts globales, librerias JS y configuraciones finales.
- archivos de configuracion visual o layout, segun el proyecto.

Esta carpeta es clave porque evita repetir el sidebar, navbar, footer y scripts en cada modulo.

## `pages/nombre_modulo/`

Cada modulo del sistema debe tener su propia carpeta dentro de `pages/`.

Ejemplo:

```text
pages/nombre_modulo/
|-- components/
|   `-- modelo.php
|-- css/
|   `-- style.css
|-- js/
|   `-- index.js
`-- content.php
```

### `pages/nombre_modulo/content.php`

Archivo principal del contenido del modulo.

Aqui se importa y ordena todo lo necesario para mostrar la parte central de la pantalla:

- HTML/PHP propio del modulo.
- componentes de `components/`.
- estructuras visuales especificas.
- tablas, formularios, modales o tarjetas.
- referencias necesarias para conectar con JS o scripts del modelo.

El archivo `content.php` no reemplaza a `pages/nombre_vista.php`.

La diferencia es:

- `pages/nombre_vista.php`: arma la pantalla completa con layout general.
- `pages/nombre_modulo/content.php`: contiene el cuerpo del modulo que se muestra dentro de esa pantalla.

### `pages/nombre_modulo/components/modelo.php`

Contiene componentes PHP propios del modulo.

Se usa para separar fragmentos visuales, bloques reutilizables, modales, tablas o secciones especificas de la pantalla.

### `pages/nombre_modulo/css/style.css`

Contiene los estilos especificos del modulo.

Sirve para evitar mezclar estilos propios de una pantalla con estilos globales del proyecto.

### `pages/nombre_modulo/js/index.js`

Contiene la logica JavaScript propia del modulo.

Aqui normalmente se manejan:

- eventos de botones.
- llamadas AJAX.
- validaciones frontend.
- renderizado de tablas o graficos.
- consumo de scripts ubicados en `app/model/nombre_model/scripts/`.

## `app/config/conexion.php`

Archivo principal de conexion del proyecto.

Aqui se centralizan las conexiones a base de datos y funciones base para obtener la conexion que luego usan los modelos.

Ejemplos comunes:

```php
conectar_incidencias();
conectar_combustible();
```

## `app/model/nombre_model/`

Contiene la logica de datos del modulo o modelo.

La estructura general es:

```text
app/model/nombre_model/
|-- scripts/
|   `-- s_nombre_script.php
`-- functions/
    `-- f_nombre_model.php
```

### `app/model/nombre_model/scripts/s_nombre_script.php`

Archivo de ejecucion del modelo.

Aqui normalmente va la logica que recibe y prepara los datos que vienen desde la vista o desde JavaScript.

Responsabilidades comunes:

- incluir `app/config/conexion.php`.
- incluir el archivo de funciones del modelo.
- recibir variables por `POST`, `GET` o sesion.
- limpiar o preparar filtros.
- definir variables que se usaran en las consultas.
- llamar funciones ubicadas en `functions/f_nombre_model.php`.
- devolver respuestas, normalmente en JSON.

Ejemplo conceptual:

```php
<?php
include '../../../config/conexion.php';
include '../functions/f_nombre_model.php';

$filtro = isset($_POST['filtro']) ? trim($_POST['filtro']) : '';

$datos = obtenerDatos($filtro);

echo json_encode($datos);
```

### `app/model/nombre_model/functions/f_nombre_model.php`

Archivo de funciones del modelo.

Aqui van las consultas a la base de datos y las funciones reutilizables del modelo.

Responsabilidades comunes:

- abrir conexion usando funciones de `conexion.php`.
- construir consultas SQL.
- aplicar filtros recibidos desde el script.
- ejecutar consultas.
- transformar resultados.
- retornar arreglos, objetos o respuestas listas para el script.
- cerrar conexion cuando corresponda.

Ejemplo conceptual:

```php
<?php
function obtenerDatos($filtro) {
    $conn = conectar_base();

    $sql = "SELECT * FROM tabla WHERE campo LIKE '%$filtro%'";
    $resultado = mysqli_query($conn, $sql);

    $data = array();
    while ($row = mysqli_fetch_assoc($resultado)) {
        $data[] = $row;
    }

    mysqli_close($conn);
    return $data;
}
```

## `assets/`

Contiene recursos publicos y librerias usadas por la interfaz.

Normalmente incluye:

- `archives/`: archivos cargados, generados o usados por el sistema.
- `build/`: recursos construidos o compilados.
- `dist/`: archivos distribuidos para frontend.
- `img/`: imagenes, logos e iconos.
- `lib/`: librerias frontend o utilidades externas.
- `plugins/`: plugins usados por la interfaz.

## `php_lib/`

Contiene librerias PHP reutilizables, funciones compartidas o componentes auxiliares usados por distintas partes del sistema.


## Resumen Del Flujo

```text
pages/nombre_vista.php
    carga pages/general/head.php
    carga pages/general/sidebar.php
    carga pages/general/navbar.php o header.php
    carga pages/nombre_modulo/content.php
    carga pages/general/footer.php
    carga pages/general/script.php

pages/nombre_modulo/js/index.js
    llama por AJAX a app/model/nombre_model/scripts/s_nombre_script.php

app/model/nombre_model/scripts/s_nombre_script.php
    recibe variables, prepara datos y llama funciones del modelo

app/model/nombre_model/functions/f_nombre_model.php
    contiene las consultas SQL y retorna los datos al script
```

Esta organizacion permite separar layout general, contenido por modulo, logica JavaScript, preparacion de datos y consultas a base de datos.
