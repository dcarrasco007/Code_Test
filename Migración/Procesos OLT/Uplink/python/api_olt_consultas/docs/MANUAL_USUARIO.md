# Manual de usuario — `api_olt_consultas`

API REST para consultar datos de las OLT Huawei (alarmas, tráfico, potencia óptica, ONT,
VLAN, tarjetas, versión, …) y para ejecutar comandos de lectura por telnet de forma
controlada. Este manual es para **sistemas consumidores** (Portal PHP, crons, bot). Para
mantener o extender la API, ver [`MANUAL_DESARROLLADOR.md`](MANUAL_DESARROLLADOR.md).

---

## 1. Antes de empezar

| Dato | Cómo se obtiene |
|---|---|
| **URL base** | La da el responsable. En la red interna es `http://<ip-servidor>:5001`. Sin TLS: solo red interna. |
| **API key** | La genera el responsable con `scripts/crear_cliente.py` y te la entrega **una sola vez**. Guárdala en un lugar seguro (variable de entorno / secreto), nunca en el código. |
| **IP de origen** | Tu API key solo funciona desde las IP que el responsable registró (`ips_permitidas`). Si cambias de servidor, avísale. |

Toda petición lleva la cabecera:

```
X-API-Key: <tu-api-key>
```

`/health` es la única ruta sin API key.

### Scopes

Tu key tiene uno o más scopes. Si te falta uno para un endpoint, recibes `403`.

| Scope | Permite |
|---|---|
| `lectura` | Todos los `GET` de datos (sección 3). |
| `ejecutar_consulta` | `POST /ejecutar/consulta/{codigo}`, `POST /ejecutar/lote`, y consultar jobs. |
| `ejecutar_comandos` | `POST /ejecutar/comandos` (lista de `display …`). |
| `config_aprobada` | Que `/ejecutar/comandos` acepte además comandos de configuración aprobados (hoy no hay ninguno). |
| `admin` | `GET /logs/telnet`, `GET /admin/*`, y ver los jobs de todos los clientes. |

---

## 2. Contrato de respuesta

Los endpoints de **dato** (lectura y `/ejecutar/consulta`) devuelven siempre la misma forma:

```json
{
  "ok": true,
  "consulta": "fan",
  "olt": { "server": "OLT-VITACURA-1", "ip": "10.99.x.x", "modelo": "MA5600T" },
  "fuente": "bd",                     // "bd" (lectura) | "telnet" (ejecución)
  "fecha_dato": "2026-09-08 10:00:00",
  "dato": { "registros": [ … ], "cantidad": 12 },   // null si no hay datos
  "log": null,                        // ver ?con_log
  "meta": { "motivo": "…" },          // presente solo si hay algo que aclarar
  "job": null                         // { "uuid", "estado" } en respuestas 202
}
```

- `dato: null` **no es un error**: significa que el cron todavía no cargó datos para esa
  OLT (`meta.motivo = "sin registros"`).
- `X-Request-ID` viene en la cabecera de toda respuesta: cítalo si reportas un problema.

---

## 3. Endpoints de lectura (`GET`, scope `lectura`)

Todos aceptan:

| Parámetro | Qué hace |
|---|---|
| `?con_log=true` | Adjunta en `log` el último log crudo de telnet de esa consulta/OLT (si algún cron lo dejó — ver [`INTEGRACION_CRONS.md`](INTEGRACION_CRONS.md)). |
| `?desde=YYYY-MM-DD&hasta=YYYY-MM-DD` | Serie histórica en vez del último dato. Solo en consultas cuya columna de fecha es `DATETIME`; en el resto responde `422` (usa el endpoint sin rango). |

### Catálogo de OLT

| Endpoint | Devuelve |
|---|---|
| `GET /olt` | Lista de OLT (`?region=` `?modelo=` opcionales). |
| `GET /olt/{server}` | Ficha de una OLT (modelo, ip, región, …). |

### Datos por OLT

| Endpoint | Tabla de origen | Rango histórico |
|---|---|---|
| `GET /uplink/trafico/{server}` | `OLT_TRAFICO_UPLINK_HORA` | ✅ |
| `GET /uplink/potencia-optica/{server}` | `OLT_POTENCIA_OPTICA_UPLINK` | ✅ |
| `GET /uplink/state/{server}` | `OLT_UPLINKS_STATE` (consulta desactivada; solo histórico) | ❌ |
| `GET /alarmas/activas/{server}` | `OLT_ALARMAS` | ❌ |
| `GET /alarmas/detalle/{server}` | `OLT_CANTIDAD_ALARMAS_DETALLE` | ❌ |
| `GET /alarmas/critical-los/{server}` | `OLT_ALARMA_CRITICAL_LOS` | ✅ |
| `GET /alarmas/los-ont/{server}` | `OLT_ALARMA_LOS_ONT` | ✅ |
| `GET /equipo/tarjetas/{server}` | `OLT_DETALLE_TARJETA` | ❌ |
| `GET /equipo/fan/{server}` | `OLT_FAN_ESTADO` | ❌ |
| `GET /equipo/energia/alarma/{server}` | `OLT_ALARMA_ENERGIA` | ✅ |
| `GET /equipo/energia/estado/{server}` | `OLT_ESTADO_ENERGIA` | ✅ |
| `GET /equipo/temperatura-cpu/{server}` | `OLT_TEMP_CPU` | ❌ |
| `GET /equipo/version/{server}` | `OLT_VERSION_PARCHE_MODELO` | ❌ |
| `GET /equipo/uptime/{server}` | `OLT_UPTIME` | ❌ |
| `GET /vlan/cantidad/{server}` | `OLT_CANTIDAD_VLAN` | ❌ |
| `GET /vlan/trafico/{server}` | `OLT_VLAN_TRAFICO` | ✅ |
| `GET /vlan/servicios/{server}` | `OLT_VLAN_SERVICIO_CANTIDAD` | ✅ |
| `GET /pon/trafico/{server}` | `OLT_TRAFICOGPON_HORA` | ❌ |
| `GET /ont/detalle/{server}` | `OLT_INFORMACION_ONT_DETALLE_COMPLETO` | ✅ |
| `GET /ont/reporte/{server}` | `OLT_ONT_DETALLE_2` | ❌ |

### Ejemplos

```bash
# Último tráfico de uplink, con el log crudo del cron si existe
curl -s -H "X-API-Key: $API_KEY" \
  "http://10.x.x.x:5001/uplink/trafico/OLT-VITACURA-1?con_log=true"

# Serie de potencia óptica de una semana
curl -s -H "X-API-Key: $API_KEY" \
  "http://10.x.x.x:5001/uplink/potencia-optica/OLT-VITACURA-1?desde=2026-09-01&hasta=2026-09-07"

# Estado de ventiladores
curl -s -H "X-API-Key: $API_KEY" \
  "http://10.x.x.x:5001/equipo/fan/OLT-VITACURA-1"
```

---

## 4. Ejecución en vivo (telnet)

Abre una sesión telnet real a la OLT. Respeta el límite de **3 sesiones simultáneas por
(OLT, usuario)** del equipo; si no hay cupo, espera y luego responde `503`.

### 4.1 `POST /ejecutar/consulta/{codigo}` — scope `ejecutar_consulta`

Corre la secuencia de comandos predefinida de una consulta contra **una** OLT.

```bash
curl -s -X POST -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" \
  -d '{"server": "OLT-VITACURA-1"}' \
  "http://10.x.x.x:5001/ejecutar/consulta/fan"
```

- Respuesta `200`: contrato de dato con `fuente: "telnet"`, `dato` parseado y
  `log.crudo` completo.
- `{"server": "...", "forzar_async": true}` → responde `202` con `job` (ver 4.4).
- Si el parser no reconoce la salida: `200` con `dato: null` pero **`log.crudo` completo**
  (útil tras un cambio de firmware).

Códigos: `{codigo}` es uno de los de la tabla de la sección 3 (`fan`, `uplink_trafico`,
`alarmas_activas`, …). `GET /admin/consultas` (scope admin) lista el catálogo completo.

### 4.2 `POST /ejecutar/comandos` — scope `ejecutar_comandos`

Lista de comandos con el orden que tú definas. **Solo `display …`** (la lista blanca de
configuración nace vacía). La navegación la genera la API.

```bash
curl -s -X POST -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" -d '{
  "server": "OLT-VITACURA-1",
  "navegacion": { "tipo": "gpon", "slot": 1 },
  "comandos": ["display port state 0", "display ont info summary 0"]
}' "http://10.x.x.x:5001/ejecutar/comandos"
```

- `navegacion` es opcional; si va, `tipo` ∈ `eth|giu|scu|mpu|gpon` y `slot` 0–20.
- Un comando que no sea `display …` (o que caiga en la deny-list:
  `undo|reset|reboot|save|delete|…`) → `422` con la lista de rechazos, **sin ejecutar nada**.
- Respuesta `200`: `dato.registros` trae la salida separada por comando + `log.crudo`.

### 4.3 `POST /ejecutar/lote` — scope `ejecutar_consulta`

Una consulta contra varias OLT. **Siempre asíncrono** → `202` con `job`.

```bash
curl -s -X POST -H "X-API-Key: $API_KEY" -H "Content-Type: application/json" -d '{
  "codigo": "fan",
  "servers": ["OLT-VITACURA-1", "OLT-LASCONDES-1"]
}' "http://10.x.x.x:5001/ejecutar/lote"
```

Máximo 5 OLT por lote.

### 4.4 Jobs — `GET /jobs/{uuid}`, `GET /jobs` (scope `ejecutar_consulta`)

```bash
curl -s -H "X-API-Key: $API_KEY" "http://10.x.x.x:5001/jobs/<uuid>"
```

Estados: `PENDIENTE` → `RUNNING` → `OK` (con `resultado`) | `ERROR` | `TIMEOUT`.
Consulta cada pocos segundos. Solo ves tus jobs (admin ve todos). Tras un reinicio de la
API, los jobs en curso quedan `ERROR: "interrumpido por reinicio de la API"`.

---

## 5. Endpoints de administración (scope `admin`)

| Endpoint | Para qué |
|---|---|
| `GET /admin/consultas` | Catálogo de consultas y cuántos comandos tiene cada una. |
| `GET /admin/auditoria?accion=&cliente_id=&server=&desde=&hasta=&limite=` | Quién pidió qué y cuándo. |
| `GET /admin/sesiones` | Sesiones telnet abiertas ahora + cupo efectivo por OLT. |
| `GET /admin/clientes/me` | Ficha de tu propio cliente (scopes, límites, IP permitidas). |
| `GET /logs/telnet?server=&consulta=&desde=&hasta=&exito=&limite=` | Historial de sesiones (sin el texto crudo). |
| `GET /logs/telnet/{id}` | Una sesión con el `log_crudo` completo. |

---

## 6. Códigos de error

| Código | Significado | Qué hacer |
|---|---|---|
| `401` | Key inválida / ausente / IP no autorizada / IP bloqueada por intentos fallidos. | Revisa la key y desde qué IP llamas. Tras 5 fallos, la IP se bloquea 15 min (`Retry-After`). |
| `403` | Te falta un scope. | Pide al responsable ampliar los scopes de tu key. |
| `404` | OLT o consulta inexistente. | Verifica el `server` con `GET /olt` y el `codigo` con `GET /admin/consultas`. |
| `409` | La consulta no tiene comandos para el modelo de esa OLT. | Reporta: falta catalogar ese modelo. |
| `413` | Body demasiado grande. | Reduce la lista de comandos. |
| `422` | Validación: `server`/`slot` con formato inválido, comando rechazado, `?desde&hasta` no disponible para esa consulta, más de 30 comandos o 5 OLT. | El detalle dice qué. |
| `429` | Superaste tu rate-limit (peticiones) o tu cuota de ejecuciones/hora. | Respeta `Retry-After`. |
| `503` | Sin cupo de sesiones telnet, o circuit-breaker activo (la OLT rechazó por límite), o credenciales telnet no configuradas en el servidor. | Reintenta según `Retry-After`. Si persiste, avisa al responsable. |
| `504` | La sesión telnet superó el timeout. | Reintenta más tarde; puede ser la OLT lenta o inalcanzable. |
| `500` | Error interno. | Guarda el `request_id` de la respuesta y repórtalo. |

---

## 7. Límites y cuotas

| Límite | Valor por defecto | Nota |
|---|---|---|
| Peticiones por cliente | 60 cada 60 s | Configurable por cliente en BD. `429` + `Retry-After`. |
| Ejecuciones telnet/hora | 60 | Protege a las OLT. `429`. |
| Comandos por `/ejecutar/comandos` | 30 | `422`. |
| OLT por `/ejecutar/lote` | 5 | `422`. |
| Body de la petición | 64 KiB | `413`. |
| Espera por cupo de sesión | 60 s | Luego `503`. |

---

## 8. FAQ

**¿El dato viene siempre de la BD?**
En los `GET` sí (`fuente: "bd"`), lo cargan los crons. En `/ejecutar/*` la API abre telnet
(`fuente: "telnet"`).

**Pedí `?con_log=true` y `log` vino `null`.**
Nadie dejó un log de telnet para esa consulta/OLT todavía. Los `GET` no abren telnet; el
log lo tiene que haber escrito un cron (hoy solo `uplink_trafico`, si está activado) o una
ejecución previa de `/ejecutar/consulta`.

**`dato: null` con `200`.**
No hay registros para esa OLT en la tabla de origen. No es error.

**Me bloqueó la IP.**
5 fallos de autenticación seguidos → 15 min de bloqueo. Una key correcta **no** desbloquea
antes de tiempo (es a propósito). Espera el `Retry-After`.

**¿Puedo mandar `enable` / `config` / `interface …` en `/ejecutar/comandos`?**
No. Esos los genera la API desde `navegacion`. Van en la deny-list y dan `422`.

**Rotación de key:** el responsable da de alta la nueva, actualizas tu configuración, y
recién ahí se desactiva la vieja. Nunca se borra (trazabilidad de auditoría).
