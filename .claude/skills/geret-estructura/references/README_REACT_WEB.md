# Estructura Base Para Proyectos React Web

Este README describe la estructura general utilizada por los proyectos React web de GERET.

## Estructura General

```text
proyecto-react/
├── public/
│   └── storage/                   # Archivos estaticos publicos
├── src/
│   ├── assets/                    # Imagenes, fuentes, SVGs
│   ├── components/                # Componentes compartidos de toda la aplicacion
│   │   ├── Header.jsx
│   │   ├── Footer.jsx
│   │   └── ui/                    # Componentes genericos reutilizables
│   │       ├── form/              #   Componentes de formulario
│   │       │   ├── InputField.jsx
│   │       │   ├── SelectField.jsx
│   │       │   └── TextareaField.jsx
│   │       ├── Button.jsx
│   │       ├── ConfirmDialog.jsx
│   │       ├── Modal.jsx
│   │       └── Table.jsx
│   ├── config/                    # Configuracion centralizada del proyecto
│   │   ├── api.js                 # Cliente API, endpoints y funciones REST
│   │   ├── constants.js           # Constantes del proyecto
│   │   ├── roles.js               # Roles y permisos
│   │   └── validators.js          # Validaciones reutilizables
│   ├── hooks/                     # Custom hooks globales
│   │   └── useForm.js
│   ├── layouts/                   # Layouts reutilizables para rutas o paginas
│   │   ├── AppLayout.jsx
│   │   ├── AuthLayout.jsx
│   │   └── DashboardLayout.jsx
│   ├── pages/                     # Paginas principales asociadas a rutas
│   │   ├── Login.jsx
│   │   ├── Dashboard.jsx
│   │   └── Configuracion.jsx
│   ├── router/                    # Configuracion de rutas y guards
│   │   ├── index.jsx
│   │   └── guards.js
│   ├── sections/                  # Secciones y logica por dominio funcional
│   │   └── nombre_modulo/
│   │       ├── index.js
│   │       ├── ComponenteName.jsx
│   │       └── useNombre.js
│   ├── store/                     # Estado global
│   │   ├── useAuthStore.js
│   │   └── useConfirmStore.js
│   ├── styles/                    # Estilos globales o estilos compartidos
│   │   ├── GlobalStyle.js
│   │   ├── layout.css
│   │   └── variables.css
│   ├── themes/                    # Tokens de tema: colores, tamaños, sombras
│   │   └── theme.js
│   ├── utils/                     # Helpers, formatters, funciones utilitarias
│   │   ├── formatters.js
│   │   └── validators.js
│   ├── App.jsx                    # Rutas y composicion principal
│   └── main.jsx                   # Punto de entrada de React
├── e2e/                           # Tests end-to-end
├── scripts/                       # Scripts de automatizacion
├── .env
├── .env.example
├── .gitignore
├── AGENTS.md
├── eslint.config.js
├── index.html
├── package.json
├── playwright.config.js
└── vite.config.js
```

## `src/main.jsx`

Punto de entrada de la aplicacion. Monta el componente raiz en el DOM.

```jsx
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

createRoot(document.getElementById('root')).render(
  <StrictMode>
    <App />
  </StrictMode>
);
```

## `src/App.jsx`

Componente raiz que define las rutas principales y los layout que envuelven cada grupo de rutas.

```jsx
import { BrowserRouter, Routes, Route } from 'react-router-dom';
import AppLayout from './layouts/AppLayout';
import AuthLayout from './layouts/AuthLayout';
import Login from './pages/Login';
import Dashboard from './pages/Dashboard';

function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<AuthLayout />}>
          <Route path="/login" element={<Login />} />
        </Route>
        <Route element={<AppLayout />}>
          <Route path="/dashboard" element={<Dashboard />} />
        </Route>
      </Routes>
    </BrowserRouter>
  );
}

export default App;
```

## `src/router/`

Centraliza la configuracion de rutas y la logica de proteccion de rutas (guards).

- `index.jsx`: define el arreglo de rutas con sus layouts y guards.
- `guards.js`: contiene funciones como `requireAuth` que verifican sesion, roles o permisos antes de permitir el acceso a una ruta.

## `src/components/`

Contiene los componentes compartidos que pueden usarse en toda la aplicacion.

### `src/components/ui/`

Componentes atomicos y genericos, sin logica de negocio. Se organizan como archivos planos dentro de `ui/`. Ejemplos:

- `Button.jsx`
- `Modal.jsx`
- `Table.jsx`
- `ConfirmDialog.jsx`

Los componentes de formulario se agrupan en `ui/form/`:

- `InputField.jsx`
- `SelectField.jsx`
- `TextareaField.jsx`

### `src/components/Header.jsx`, `Footer.jsx`

Componentes compartidos de nivel de aplicacion que forman parte del layout global.

## `src/sections/`

Contiene la logica y los componentes agrupados por dominio funcional del negocio.

Cada carpeta dentro de `sections/` representa un dominio y debe incluir un `index.js` que re-exporte sus componentes y hooks (barrel export).

```text
sections/auth/
├── index.js
├── LoginForm.jsx
└── useLogin.js
```

```js
// sections/auth/index.js
export { default as LoginForm } from './LoginForm';
export { default as useLogin } from './useLogin';
```

Las secciones agrupan:

- Componentes visuales propios del dominio (`LoginForm.jsx`, `DashboardCards.jsx`).
- Hooks de logica propios del dominio (`useLogin.js`, `useDashboard.js`).

## `src/pages/`

Una pagina por cada ruta de la aplicacion.

Responsabilidades de una pagina:

- Importar y componer secciones de `sections/`.
- Manejar el estado de la pagina (cargando, error, vacio).
- NO debe llamar a `fetch` ni construir endpoints manualmente.
- NO debe contener logica de negocio compleja.

```jsx
import { LoginForm } from '../sections/auth';

function LoginPage() {
  return (
    <div>
      <h1>Iniciar Sesion</h1>
      <LoginForm />
    </div>
  );
}

export default LoginPage;
```

## `src/config/api.js`

Cliente HTTP centralizado para todas las llamadas a la API.

Aqui se definen:

- La URL base del servidor (desde `.env`).
- Funciones REST: `get`, `post`, `put`, `delete`.
- Headers por defecto (autenticacion, content-type).
- Manejo de errores global.

Ninguna pagina o seccion debe llamar a `fetch` directamente. Todo el consumo de API pasa por `api.js`.

```js
const API_BASE = import.meta.env.VITE_API_URL;

export async function get(endpoint) {
  const res = await fetch(`${API_BASE}${endpoint}`, {
    headers: { Authorization: `Bearer ${localStorage.getItem('token')}` }
  });
  if (!res.ok) throw new Error(`Error ${res.status}`);
  return res.json();
}

export async function post(endpoint, data) {
  const res = await fetch(`${API_BASE}${endpoint}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${localStorage.getItem('token')}`
    },
    body: JSON.stringify(data)
  });
  if (!res.ok) throw new Error(`Error ${res.status}`);
  return res.json();
}
```

## `src/hooks/`

Custom hooks globales y reutilizables que no pertenecen a un dominio especifico.

```text
hooks/
├── useForm.js          # Manejo de formularios
└── usePagination.js    # Logica de paginacion
```

Los hooks propios de un dominio van dentro de su carpeta en `sections/`.

## `src/store/`

Estado global de la aplicacion. La implementacion puede ser con `zustand`, `useContext` + `useReducer`, o `redux`.

Cada archivo representa un dominio de estado:

```text
store/
├── useAuthStore.js       # Sesion del usuario
└── useConfirmStore.js    # Dialogos de confirmacion globales
```

## `src/styles/`

Estilos globales del proyecto. Puede incluir tanto CSS-in-JS como archivos CSS tradicionales.

- `GlobalStyle.js`: estilos base con `createGlobalStyle` (styled-components / emotion).
- `layout.css`: estilos de estructura general.
- `variables.css`: variables CSS (colores, fuentes, espaciados).

## `src/themes/theme.js`

Definiciones de tema: paleta de colores, tamanos de fuente, espaciados, sombras y otros tokens de diseno. Se importa desde los componentes que usan CSS-in-JS.

## `src/assets/`

Recursos estaticos que se importan desde el codigo: imagenes, fuentes, SVGs, iconos.

## `src/utils/`

Funciones utilitarias sin dependencia de React: formateo de fechas, numeros, validaciones genericas.

## `src/router/guards.js`

Funciones de proteccion de rutas. Verifican la sesion, los roles o los permisos del usuario antes de permitir la navegacion.

```js
export function requireAuth() {
  const token = localStorage.getItem('token');
  if (!token) return { redirect: '/login' };
  return {};
}
```

## `e2e/`

Tests end-to-end con Playwright. Las pruebas replican flujos completos del usuario.

## `scripts/`

Scripts de automatizacion para tareas recurrentes: migraciones, seed de datos, generacion de componentes, despliegue.

## Archivos de configuracion raiz

| Archivo | Proposito |
|---------|-----------|
| `vite.config.js` | Configuracion del build tool |
| `eslint.config.js` | Reglas de linting (ESLint flat config) |
| `playwright.config.js` | Configuracion de E2E |
| `.env` | Variables de entorno locales |
| `.env.example` | Plantilla de variables de entorno |
| `AGENTS.md` | Guia para agentes de IA sobre este proyecto |

## Convenciones de Nomenclatura

| Elemento | Convencion | Ejemplo |
|----------|------------|---------|
| Componentes | PascalCase | `Button.jsx`, `LoginForm.jsx` |
| Hooks | camelCase con prefijo `use` | `useLogin.js`, `useForm.js` |
| Archivos de configuracion | camelCase | `api.js`, `constants.js` |
| Carpetas de secciones | kebab-case | `modulo-ejemplo/` |
| Archivos de pagina | PascalCase | `Dashboard.jsx` |
| Stores | camelCase con prefijo `use` | `useAuthStore.js` |

## Reglas Generales

1. Las paginas deben importar desde el indice de cada seccion: `import { LoginForm } from "../sections/auth"`.
2. Las llamadas API deben estar centralizadas en `src/config/api.js`. Las paginas no deben llamar directamente a `fetch` ni construir endpoints manualmente.
3. Los hooks de logica de negocio van en la seccion correspondiente (`sections/`), no en `hooks/`.
4. Los hooks globales y reutilizables van en `hooks/`.
5. Los componentes de `components/ui/` no deben contener logica de negocio ni llamadas API.
6. Separar la configuracion (`config/`) de la utileria (`utils/`): en `config/` va la configuracion del proyecto; en `utils/` van funciones genericas sin dependencia del proyecto.
7. Usar `.env` para credenciales y URLs; nunca escribir valores sensibles en el codigo.

## Resumen Del Flujo

```text
main.jsx
    renderiza App.jsx

App.jsx
    define rutas con BrowserRouter
    asigna layouts a grupos de rutas
    renderiza la pagina correspondiente

pages/NombrePage.jsx
    importa secciones desde sections/nombre_modulo/
    compone la interfaz de la pagina
    NO llama a fetch directamente

sections/nombre_modulo/
    contiene JSX del dominio (componentes visuales)
    contiene hooks del dominio (logica y estado)
    exporta via index.js (barrel export)

config/api.js
    centraliza todas las llamadas HTTP
    maneja tokens, headers y errores globalmente

store/useAuthStore.js
    estado global de sesion
    se consume desde componentes con el hook
```

Esta organizacion permite separar responsabilidades entre configuracion, componentes UI, logica de dominio, paginas y estado global, siguiendo las mismas convenciones que el resto de los proyectos GERET.
