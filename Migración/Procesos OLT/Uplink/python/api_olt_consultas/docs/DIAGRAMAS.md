# Diagramas — `api_olt_consultas`

Diagramas Mermaid (renderizan en GitHub). **Revisados en F8**: reflejan el código
implementado (F1–F7). Lo único pendiente es F9 (validación con equipos reales) y F10
(despliegue), que no cambian la arquitectura. Para el detalle de cada componente ver
[`MANUAL_DESARROLLADOR.md`](MANUAL_DESARROLLADOR.md).

> Middlewares (orden real, de más externo a más interno):
> `hardening` (request-id, límite de body, cabeceras de seguridad) →
> `auditoría` (LECTURA/EJECUCION/RECHAZADO) → `CORS`. Un error no controlado lo
> captura el `exception_handler` global → `500` genérico sin traza.

---

## 1. Contexto: de dónde viene esto

Hoy cada familia de procesos PHP abre su propio telnet, parsea y escribe en BD. La API
no reemplaza a los crons: **lee lo que ellos ya escribieron** y, cuando se le pide
explícitamente, abre su propia sesión telnet controlada.

```mermaid
flowchart LR
    subgraph HOY["Hoy (php_8_0, en produccion)"]
        CRON["~15 familias de crons PHP<br/>alarmas, ONT, potencia, VLAN"]
        CRON -->|telnet, secuencia fija| OLT1[(OLT Huawei)]
        CRON -->|INSERT| BD[(BD Aden<br/>tablas OLT_*)]
        CRON -.->|log crudo| FILE["archivos .log por IP<br/>sin historico"]
    end

    subgraph NUEVO["Nuevo: api_olt_consultas"]
        API[FastAPI]
        API -->|SELECT dato + log| BD
        API -->|telnet controlado<br/>bajo demanda| OLT1
        API -->|INSERT log crudo| BD
    end

    PORTAL[Portal web OLT PHP] -->|X-API-Key| API
    CRONPY[Crons Python<br/>uplink_trafico] -->|X-API-Key| API
    BOT[bot_olt Telegram<br/>fase futura] -.->|X-API-Key| API
```

---

## 2. Componentes internos

```mermaid
flowchart TB
    CLIENTE[Cliente HTTP<br/>X-API-Key] --> MW

    subgraph APP["api_olt_consultas"]
        MW["Middleware<br/>auth · rate-limit · auditoria"]
        MW --> R_LECT["router lectura<br/>olt, alarmas, uplink,<br/>equipo, vlan, pon, ont"]
        MW --> R_EJEC["router ejecutar<br/>+ jobs + logs + admin"]

        R_LECT --> MODEL["model<br/>SQL parametrizado"]
        R_EJEC --> EJECUTOR["utils/ejecutor.py"]

        EJECUTOR -->|comandos fijos| MODEL
        EJECUTOR --> VALID["utils/validador_comandos.py<br/>whitelist display + aprobados"]
        EJECUTOR --> GOB["utils/sesiones_telnet.py<br/>GOBERNADOR de cupo"]
        GOB --> TELNET["utils/telnet_olt.py<br/>asyncio · IAC · More · logout"]
        EJECUTOR --> PARSERS["package/parsers<br/>1 por consulta"]

        MODEL --> DB[(BD Aden)]
        EJECUTOR -->|log crudo| DB
    end

    TELNET -->|puerto 23| OLT[(OLT Huawei)]
```

---

## 3. Modelo de datos nuevo (tablas `OLT_API_*`)

Las 248 tablas `OLT_*` existentes no se modifican: solo se leen.

```mermaid
erDiagram
    OLT_API_CLIENTES ||--o{ OLT_API_AUDITORIA : genera
    OLT_API_CLIENTES ||--o{ OLT_API_JOBS : solicita
    OLT_API_CLIENTES ||--o{ OLT_API_LOG_TELNET : origina
    OLT_API_CONSULTAS ||--o{ OLT_API_CONSULTA_COMANDOS : tiene_comandos
    OLT_API_CONSULTAS ||--o{ OLT_API_LOG_TELNET : se_registra
    OLT_API_JOBS ||--o{ OLT_API_LOG_TELNET : agrupa
    OLT_API_JOBS ||--o{ OLT_API_SESIONES_TELNET : abre

    OLT_API_CLIENTES {
        int id PK
        string nombre
        string prefijo_key
        string api_key_hash
        string scopes
        string ips_permitidas
        int rate_limit_peticiones
        int max_ejecuciones_hora
        bool activo
    }
    OLT_API_CONSULTAS {
        int id PK
        string codigo
        string tabla_dato
        int timeout_seg
        string perfil_credencial
        int modo_sync_max_olts
    }
    OLT_API_CONSULTA_COMANDOS {
        int id PK
        int consulta_id FK
        string modelo
        string server
        int orden
        string contexto
        string comando_template
        string repetir_por
        string fuente_lista
    }
    OLT_API_COMANDOS_APROBADOS {
        int id PK
        string template
        string scope_requerido
        bool activo
    }
    OLT_API_LOG_TELNET {
        bigint id PK
        datetime fecha
        string olt
        string consulta_codigo
        string origen
        text comandos_enviados
        longtext log_crudo
        int duracion_ms
        bool exito
    }
    OLT_API_SESIONES_TELNET {
        bigint id PK
        string olt
        string usuario_telnet
        string estado
        datetime fecha_apertura
        datetime ultimo_latido
    }
    OLT_API_JOBS {
        string uuid PK
        string estado
        json parametros
        longtext resultado
    }
    OLT_API_AUDITORIA {
        bigint id PK
        datetime fecha
        string accion
        string endpoint
        string ip_origen
        int duracion_ms
    }
    OLT_API_INTENTOS_AUTH {
        string ip PK
        int intentos_fallidos
        datetime bloqueado_hasta
    }
```

---

## 4. Secuencia: lectura de un dato (sin telnet)

```mermaid
sequenceDiagram
    participant C as Cliente
    participant A as API
    participant D as BD Aden

    C->>A: GET /alarmas/activas/OLT-X con X-API-Key
    A->>D: valida key (hash + prefijo) e IP permitida
    alt key invalida o IP no permitida
        A->>D: INSERT auditoria AUTH_FAIL + intentos
        A-->>C: 401 mensaje generico
    else key valida
        A->>A: rate-limit por cliente
        A->>D: SELECT dato en OLT_ALARMAS (parametrizado)
        A->>D: SELECT ultimo OLT_API_LOG_TELNET de la consulta
        A->>D: INSERT auditoria LECTURA
        A-->>C: 200 dato + log crudo + fuente bd
    end
```

---

## 5. Secuencia: ejecución en vivo respetando el límite de 3 sesiones

El punto crítico del proyecto: la OLT cierra o bloquea la cuarta sesión del mismo usuario.

```mermaid
sequenceDiagram
    participant C as Cliente
    participant A as API
    participant G as Gobernador
    participant T as telnet_olt
    participant O as OLT
    participant D as BD

    C->>A: POST /ejecutar/consulta/fan con server
    A->>A: auth + scope ejecutar_consulta + rate-limit
    A->>D: SELECT comandos de la consulta por modelo/server
    A->>G: pedir cupo para (OLT, usuario_telnet)

    alt cupo libre
        G->>D: INSERT sesion ABIERTA
        G-->>A: cupo concedido
        A->>T: abrir telnet
        T->>O: login, enable, config
        T->>O: comandos display + espacio en More
        O-->>T: salida CLI
        T->>O: quit por niveles + y (en finally)
        T->>G: liberar cupo
        G->>D: UPDATE sesion CERRADA
        A->>D: INSERT OLT_API_LOG_TELNET + auditoria
        A-->>C: 200 dato parseado + log crudo
    else sin cupo, espera en cola
        G-->>A: timeout ESPERA_MAX_SESION_SEG
        A-->>C: 503 con Retry-After
    else OLT rechaza por limite
        O-->>T: users has reached the upper limit
        T->>G: activar circuit-breaker BLOQUEO_OLT_SEG
        G->>D: sesion CERRADA + auditoria
        A-->>C: 503 OLT saturada
    end
```

---

## 6. Estados de una sesión telnet

```mermaid
stateDiagram-v2
    [*] --> EnCola: peticion pide cupo
    EnCola --> Abierta: cupo concedido
    EnCola --> Rechazada: timeout de espera, responde 503
    Abierta --> Cerrada: quit por niveles + y + socket cerrado
    Abierta --> Huerfana: el equipo no responde al quit
    Huerfana --> Cerrada: expira idle timeout de la OLT
    Cerrada --> [*]
    Rechazada --> [*]
```

---

## 7. Estados de un job (ejecución asíncrona)

```mermaid
stateDiagram-v2
    [*] --> PENDIENTE: responde 202 con job_id
    PENDIENTE --> RUNNING: worker toma el job
    RUNNING --> OK: todas las OLT respondieron
    RUNNING --> ERROR: fallo no recuperable
    RUNNING --> TIMEOUT: excede el timeout del job
    OK --> [*]
    ERROR --> [*]
    TIMEOUT --> [*]
```

---

## 8. Contextos del CLI Huawei (lo que recorre el autómata telnet)

```mermaid
stateDiagram-v2
    [*] --> Login: conectar puerto 23
    Login --> Usuario: User name / User password
    Usuario --> Enable: enable
    Enable --> Config: config
    Config --> Interface: interface eth/giu/scu/mpu/gpon 0/S
    Interface --> Config: quit
    Config --> Enable: quit
    Enable --> Usuario: quit
    Usuario --> [*]: y en Are you sure to log out

    note right of Config
        display alarm, emu, power,
        board, version, vlan, traffic
    end note
    note right of Interface
        display port traffic,
        ddm-info, state, ont info
    end note
```
