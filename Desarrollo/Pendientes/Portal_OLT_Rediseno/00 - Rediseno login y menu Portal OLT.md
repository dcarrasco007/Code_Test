---
tipo: pendiente
proyecto: GERET
sistema: Portal OLT (Panel de Gestión Red GPON)
estado: en pausa
propuesta: B
retomar: "Cuando se priorice el rediseño: partir por 'Para implementar después' y 'Decisiones pendientes'"
equipo: nuestro
creado: 2026-10-27
tags: [geret, portal-olt, frontend, diseño, pendiente, nuestro]
---

# Rediseño del login y del menú superior — Portal OLT

Volver a [[00 - Pendientes GERET]]

## Pendiente

Modernizar el **login** del Portal OLT ("Panel de Gestión Red Gpon") con un diseño actual y con algo de movimiento. El **menú superior** del portal se actualiza junto con el login para que los dos combinen y el portal deje de verse como Bootstrap 3.

> [!pause] En pausa (2026-10-27) — documentado para retomar
> La propuesta B quedó elegida y ajustada. Para retomar:
> 1. Abrir `propuesta_B_red.html` (doble clic) para recordar la visual.
> 2. Resolver las [[#Decisiones pendientes]].
> 3. Seguir el checklist [[#Para implementar después]].

> [!success] Decisión (2026-10-27): **Propuesta B — "Red"**
> Cambios pedidos por seguridad, ya aplicados en `propuesta_B_red.html`:
> - Se **eliminó "Recordarme"** del login.
> - El login **no muestra datos del portal** (antes mostraba cantidad de OLTs y de ONT). En su lugar hay texto fijo con las secciones del portal. Los indicadores solo se ven **después de autenticarse**.
> - Se agregó el aviso "Acceso exclusivo para personal autorizado. Los accesos quedan registrados."
> - Se agregó un **mensaje de error genérico** ("Usuario o contraseña incorrectos") que no revela si falló el usuario o la contraseña. Al fallar, se limpia la contraseña.
>
> Ver [[#Seguridad del login]].

## Estado actual

**Login**
- Imagen de fondo de racks con una tarjeta azul chica al centro: dos campos y un botón naranja "Aceptar".
- El placeholder de la contraseña sale con mal encoding (`ContraseÃ±a`) → la página no está en UTF-8.
- Sin estados: no avisa Bloq Mayús, no muestra cargando y no tiene opción de ver la contraseña.

**Menú** (`panel_olt/OLT/menu/menu_nuevo.php`; `menu.php` redirige a ese)
- Bootstrap 3 (`navbar navbar-default`) con jQuery 1.12.
- Se **arma desde la BD según el perfil** del usuario, con hasta 3–4 niveles (`dropdown-submenu` en cascada hacia el costado).
- 8 menús principales: Reportes, ONNET, Equipos, Tráfico, ODF, KPIs, Mantención OLT, Administración.
- A la derecha: "Semana N" y el logo "desarrollado por". El buscador "Nombre o IP Equipo OLT" está comentado en `menu_nuevo.php`.
- Los submenús en cascada son el punto más débil: con 3 niveles se salen de la pantalla y se cierran si el mouse se desvía.

## Propuestas

Las dos son maquetas HTML que se abren directo en el navegador (doble clic), sin servidor ni internet. En las dos, **"Ingresar" entra a una vista del portal** para ver el login y el menú juntos. El menú usa la estructura real de `menu.php`.

### Propuesta A — "Fibra" (oscura) → `propuesta_A_fibra.html`

| | |
|---|---|
| **Login** | Fondo azul noche con **fibras ópticas animadas** y pulsos de luz que viajan por ellas. Tarjeta de vidrio esmerilado con un **borde de luz naranja que gira**, etiquetas flotantes, botón naranja con brillo al pasar el mouse, ver/ocultar contraseña, aviso de Bloq Mayús, estado "Servicios operativos" y hora. |
| **Transición** | La tarjeta se desenfoca y se aleja; entra el portal y los indicadores cuentan hacia arriba. |
| **Menú** | Barra superior de vidrio fija. Cada opción abre un **mega-menú por columnas**: cada submenú es una columna con su título, y el 3er nivel queda indentado debajo. **Buscador Ctrl+K** que busca en todo el menú y ofrece "Buscar equipo OLT: …". Chip "Semana N" y avatar. |
| **A favor** | Muy moderna, con identidad "red/fibra". El mega-menú muestra todo un menú de una vez, sin cascadas. El Ctrl+K resuelve el menú tan grande. |
| **En contra** | Tema oscuro: las pantallas internas (tablas, Highcharts) también tendrían que pasar a oscuro para que combine, o quedar como una isla clara. Más trabajo. |

### Propuesta B — "Red" (clara) → `propuesta_B_red.html`

| | |
|---|---|
| **Login** | Pantalla dividida. Izquierda: azul marino con la **topología GPON animada** (OLT → splitters → ONT, con el tráfico corriendo por la fibra y los LED de las ONT parpadeando), frase y contadores (OLTs, ONT, semana). Derecha: formulario blanco limpio, ver/ocultar contraseña, aviso de Bloq Mayús, **sacudida en rojo si falta un campo** y barra de carga naranja en el botón. |
| **Transición** | El panel azul sube como una cortina y entra el portal. |
| **Menú** | Barra blanca con **íconos** por opción y una **línea naranja que se desliza** bajo la opción activa. El desplegable navega **por niveles**: al entrar a un submenú el panel avanza, con flecha para volver, en vez de abrir cascadas al costado. Buscador que se ensancha al hacer foco, chip de semana, usuario y **migas de pan** con la ruta del reporte abierto. |
| **A favor** | Combina con las pantallas actuales (fondo claro, tablas, gráficos) sin tener que rehacerlas. La navegación por niveles soporta los 3–4 niveles del menú dinámico sin salirse de la pantalla. |
| **En contra** | Menos llamativa que la A. Para llegar a un 3er nivel hay que hacer más clics. |

### Se pueden mezclar

Las piezas son independientes. Por ejemplo, el login A con el menú B, o el menú B con el buscador Ctrl+K de la A.

## Seguridad del login

### Regla: nada del portal antes de autenticarse

La página de login debe ser **estática**: no consulta la BD, no llama a ningún endpoint del portal y no carga los scripts internos (`perfiles`, menú, Highcharts, DataTables). Lo único que hace es enviar el formulario.

| Elemento | ¿Va en el login? | Motivo |
|---|---|---|
| Cantidad de OLTs, ONT, alarmas, etc. | ❌ No | Viene de la BD y expone información de la red a cualquiera que abra la URL. |
| Animación de la topología GPON | ✅ Sí | Es un dibujo fijo (SVG), no tiene datos reales. |
| Semana actual | ❌ Quitada del login | No es sensible (se calcula en el navegador), pero no aporta antes de entrar. Se muestra en el menú después del login. |
| "Recordarme" | ❌ No | Deja la sesión abierta en equipos compartidos. Se reemplaza por un cierre de sesión por inactividad. |

### Ideas recomendadas (para el lado PHP, al implementar)

Las más importantes primero:

1. **Mensaje de error genérico**: siempre "Usuario o contraseña incorrectos", y con el mismo tiempo de respuesta, para no revelar qué usuarios existen. *(Ya en la maqueta.)*
2. **Límite de intentos**: por ejemplo 5 intentos fallidos por usuario/IP → bloqueo de 15 minutos o espera creciente. Registrar los intentos en una tabla de auditoría.
3. **Sesión segura al entrar**: `session_regenerate_id(true)` después de un login correcto (evita la fijación de sesión). Cookie de sesión con `HttpOnly`, `Secure` y `SameSite=Strict`.
4. **Cierre por inactividad** en vez de "Recordarme": por ejemplo 30 minutos sin uso. Avisar 1–2 minutos antes ("Tu sesión se cerrará…") con botón para seguir.
5. **Token CSRF** en el formulario de login y en los formularios del portal.
6. **Solo POST y HTTPS**: el usuario y la contraseña nunca en la URL (GET). Redirigir HTTP → HTTPS.
7. **Proteger cada página interna**: que `checkAcc()` (o el control que se use) corra en todas las páginas y en las respuestas AJAX, no solo en el menú. Así nadie entra a un reporte pegando la URL directa.
8. **Encabezados de seguridad**: `Content-Security-Policy`, `X-Frame-Options: DENY` (evita que el login se meta en un iframe ajeno), `X-Content-Type-Options: nosniff`. La maqueta B no usa nada externo, así que admite una CSP estricta.
9. **Auditoría**: registrar logins correctos y fallidos (usuario, IP, fecha y hora) y el cierre de sesión. El aviso "Los accesos quedan registrados" debe ser cierto.
10. **Sin pistas del sistema**: no mostrar versiones, rutas ni errores de PHP/MySQL en pantalla (`display_errors = Off` en producción).

Opcionales, según la política de la empresa:
- Botón "Mostrar contraseña": se dejó porque ayuda a escribir bien la contraseña, pero algunas políticas piden quitarlo.
- Si existe directorio corporativo (LDAP/AD) o SSO, autenticar contra él en vez de una tabla propia de usuarios.

## Para implementar después

- [x] Elegir propuesta → **B**.
- [ ] Ajustar la paleta. Hoy usa el azul y naranja corporativos aproximados; ajustar a los colores oficiales.
- [ ] Reemplazar el logo provisorio por `../../NEWFRONT/logonuevo.png`.
- [ ] **Login**: rehacer solo la vista; mantener los mismos `name` de los campos y el mismo `action` para no tocar la validación PHP. Guardar el archivo en UTF-8 (arregla `ContraseÃ±a`).
- [ ] **Menú**: mantener la generación desde la BD por perfil en `menu_nuevo.php` y cambiar solo el HTML que arma (`$menu_principal`, `$menu2`…`$menu4`) y el CSS. Los enlaces siguen llamando a `cambioPag(n)`.
- [ ] Bootstrap 3 se puede **dejar cargado** para las pantallas internas y aislar el menú nuevo con sus propias clases (sin `navbar`/`dropdown`), para no romper nada. Retirar Bootstrap 3 sería un proyecto aparte.
- [ ] Reactivar el buscador "Nombre o IP Equipo OLT" (`busca_info()`) en el lugar nuevo.
- [ ] Respetar `prefers-reduced-motion` (las maquetas ya lo hacen) y probar en el navegador que usan en la operación (versión de Chrome/Edge corporativo).
- [ ] Probar el menú con distintos perfiles (usuario con pocas opciones vs. administrador).
- [ ] Revisar en pantallas chicas: las dos maquetas pasan a menú hamburguesa bajo ~1100 px.

- [ ] Aplicar las ideas de [[#Seguridad del login]] que se aprueben (como mínimo: error genérico, límite de intentos, `session_regenerate_id`, cookies seguras y cierre por inactividad).

## Decisiones pendientes

- [x] ¿Propuesta A, B o mezcla? → **B** (2026-10-27).
- [ ] ¿Se agrega a la B el buscador Ctrl+K de la propuesta A?
- [ ] Tiempo de cierre por inactividad (¿30 min?).
- [ ] ¿Se mantiene el botón "Mostrar contraseña"?
- [ ] ¿Hay LDAP/AD o SSO corporativo para autenticar?
