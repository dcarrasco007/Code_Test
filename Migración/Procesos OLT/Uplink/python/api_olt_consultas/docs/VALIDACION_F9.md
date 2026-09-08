# F9 — Validación en producción (checklist)

> **Esta fase la ejecuta el responsable del proyecto**, no el agente de IA: requiere
> acceso a la BD Aden real, a las OLT por telnet, y a los equipos para correr
> `display users`. El agente ya dejó listo: el código (F1–F8), dos scripts de apoyo
> (`scripts/f9_paridad.py`, `scripts/f9_cupo.py`) y este checklist.
>
> **Criterio de salida:** no pasar a F10 (despliegue) hasta que TODOS los ítems de §5
> estén ✅ o justificados por escrito.

---

## 1. Prerrequisitos

| # | Ítem | Cómo se verifica |
|---|---|---|
| 1 | Las 9 tablas `OLT_API_*` existen (`sql/01`), el catálogo está cargado (`sql/02`, 20 consultas), el EVENT de purga creado (`sql/03`). | `SHOW TABLES LIKE 'OLT_API_%';` → 9 · `SELECT COUNT(*) FROM OLT_API_CONSULTAS;` → 20 |
| 2 | La API levanta contra la BD real. | `curl -s http://<ip>:5001/health` → `200`, `fase` F8/F9 |
| 3 | Al menos un cliente con scopes `lectura,ejecutar_consulta,ejecutar_comandos,admin` para las pruebas (crear con `scripts/crear_cliente.py`, IP = la del operador). | `GET /admin/clientes/me` con esa key → `200` |
| 4 | Credenciales telnet en `.env`: idealmente el usuario dedicado `geretapi` ya creado en las OLT de prueba (`OLT_TELNET_API_DEDICADO=true`); si no, los `OLT_TELNET_DEFAULT/ALARMAS/ONT_*`. | `GET /health` → `telnet.usuario_dedicado` y `telnet.cupo_por_olt_usuario` |
| 5 | 1 OLT elegida **por cada modelo** para la validación. | ver §2 |
| 6 | Ventana de mantenimiento acordada (las pruebas abren telnet real y una prueba satura el cupo a propósito). | — |

### OLT recomendadas por modelo (ajustar a la realidad de la red)

| Modelo | OLT sugerida | Notas |
|---|---|---|
| MA5800-X15 | una "normal" + `OLT-CONCEPCION-3` | CONCEPCION-3 usa solo `mpu` (excepción del seed) |
| MA5600T | una "normal" + `OLT-LAFLORIDA-1` | LAFLORIDA-1 usa slots 0/9-0/10 (excepción) |
| MA5603T | cualquiera | — |
| MA5680T | cualquiera | — |
| Excepción de prompts | `OLT-DURZUA-4` (10.99.24.68) | prompts exactos; confirmar que los regex genéricos de `telnet_olt.py` bastan (F3 dice que sí) |

---

## 2. Captura de transcripciones reales (antes de tocar nada más)

Objetivo: reemplazar las transcripciones **sintéticas** de `tests/test_parsers.py` por
capturas reales, una por consulta y modelo.

1. Para cada `(codigo, OLT)` de la matriz, ejecutar en vivo:
   ```bash
   curl -s -X POST -H "X-API-Key: $KEY" -H "Content-Type: application/json" \
     -d '{"server": "OLT-XXX"}' \
     "http://<ip>:5001/ejecutar/consulta/<codigo>" | tee /tmp/<codigo>__OLT-XXX.json
   ```
2. Sacar el `log.crudo` de la respuesta (o de `GET /logs/telnet/{id}`) y guardarlo como
   texto plano en:
   ```
   tests/transcripciones/<codigo>__<OLT>__<YYYYMMDD>.txt
   ```
   (usar `scripts/f9_paridad.py --guardar-transcripcion` hace los dos pasos de una).
3. `tests/test_parsers_reales.py` descubre esos `.txt` automáticamente y corre el parser
   correspondiente sobre cada uno. Hoy pasa con 0 casos; a medida que se agreguen capturas,
   empieza a validar de verdad. Si un parser revienta o devuelve vacío sobre una captura
   real → ajustar su regex en `package/parsers/<codigo>.py` y volver a correr.

> Las transcripciones NO se versionan si contienen datos sensibles de clientes
> (SN de ONT, nombres). Revisar antes de hacer commit; si hay dudas, dejarlas solo local
> y quedarse con el test.

### Consultas con confianza BAJA/MEDIA — prioridad de captura

De `SOLICITUDES_PENDIENTES.txt` y las cabeceras de `package/parsers/`:

- **BAJA:** `vlan_trafico`, `ont_detalle`, `uplink_state` — sin referencia previa a un
  formato exacto. Capturar primero.
- **MEDIA:** `alarmas_*`, `tarjetas`, `energia_*`, `fan`, `version`, `vlan_*`,
  `temperatura_cpu`, `uptime_gpon`, `ont_reporte`.
- **ALTA (confirmar, no re-derivar):** `uplink_trafico`, `pon_trafico` (port directo de
  `uplink_trafico_15m`).

---

## 3. Paridad: dato de la API vs tabla del cron

Para cada `(codigo, OLT)`:

```bash
scripts/f9_paridad.py --url http://<ip>:5001 --key $KEY --server OLT-XXX --codigo fan
```

El script:
1. `POST /ejecutar/consulta/<codigo>` `{server}` → `dato` recién parseado del telnet.
2. `GET /<endpoint-de-lectura>/<server>` → `dato` que dejó el cron en la tabla `OLT_*`.
3. Imprime ambos lado a lado, compara **cantidades** y los **campos numéricos** comparables
   dentro de una tolerancia, y marca lo que no coincide.

**Qué es "pasa":**
- Mismo número de elementos (puertos, alarmas, ventiladores, tarjetas, VLAN…), salvo
  diferencia explicable por el tiempo transcurrido entre la corrida del cron y la prueba.
- Valores numéricos (tráfico, potencia TX/RX, temperatura, %CPU) dentro de ±5 % o del
  margen que tenga sentido para esa métrica.
- Los campos de texto (estados, nombres) coinciden semánticamente.

**Qué hacer si NO pasa:**
- Diferencia de **estructura** (faltan campos, orden distinto) → ajustar el parser.
- Diferencia de **valor** grande y estable → revisar el offset/regex del parser contra la
  transcripción real (§2); comparar con el PHP original si hace falta.
- La tabla del cron está **vacía** para esa OLT → no es fallo de la API; anotar y seguir.

Registrar el resultado por consulta en la matriz de §5.

---

## 4. Prueba del límite de sesiones (requisito explícito del proyecto)

Objetivo: comprobar que la API **nunca** abre una 4ª sesión del mismo usuario en una OLT y
que no deja sesiones colgadas.

1. (Opcional pero ideal) Lanzar en paralelo un cron que use la misma OLT y usuario.
2. Correr:
   ```bash
   scripts/f9_cupo.py --url http://<ip>:5001 --key $KEY --server OLT-XXX --codigo fan --n 5
   ```
   Dispara 5 `POST /ejecutar/consulta` simultáneos.
3. **En la OLT**, durante la prueba: `display users` (o el equivalente del modelo) →
   contar sesiones del usuario telnet de la API. **Nunca > 3** (o `> cupo_por_olt_usuario`
   si el `.env` lo bajó, o `> 1` sin usuario dedicado).
4. El script reporta los códigos HTTP: se esperan algunos `200` y el resto `503`
   (`Retry-After`) o esperas largas — ninguno `500`.
5. Al terminar, el script consulta `GET /admin/sesiones` y `OLT_API_SESIONES_TELNET`:
   **0 filas `ABIERTA`** para esa OLT/usuario pasados ~30 s. Si queda alguna `ABIERTA`
   mucho rato → hay una sesión que no cerró bien (revisar `motivo_cierre`, `logs/api.log`).
6. Repetir forzando un rechazo real: abrir 3 sesiones telnet manuales al equipo con el
   usuario de la API y lanzar el script → la API debe detectar el rechazo, **abrir el
   circuit-breaker** (`logs/api.log` "circuito ABIERTO"), y responder `503` sin insistir.
   **Anotar el texto EXACTO** que devolvió cada modelo de OLT al rechazar
   (`SOLICITUDES_PENDIENTES.txt`, entrada de `utils/telnet_olt.py`): si no coincide con
   `_PATRONES_RECHAZO_SESIONES`, agregarlo ahí.

---

## 5. Matriz de resultados (rellenar y archivar)

| Consulta | MA5800-X15 | MA5600T | MA5603T | MA5680T | Excepción (server) | Transcripción real | Parser ajustado |
|---|---|---|---|---|---|---|---|
| uplink_trafico | | | | | X15 mpu / 5600T scu | ☐ | ☐ |
| pon_trafico | | | | | — | ☐ | ☐ |
| alarmas_activas | | | | | DURZUA ×2 | ☐ | ☐ |
| alarmas_detalle | | | | | — | ☐ | ☐ |
| alarmas_critical_los | | | | | por modelo | ☐ | ☐ |
| alarmas_los_ont | | | | | — | ☐ | ☐ |
| potencia_optica_uplink | | | | | CONCEPCION-3 | ☐ | ☐ |
| tarjetas | | | | | DURZUA | ☐ | ☐ |
| fan | | | | | — | ☐ | ☐ |
| vlan_cantidad | | | | | DURZUA | ☐ | ☐ |
| vlan_trafico | | | | | — | ☐ | ☐ |
| vlan_servicios | | | | | — | ☐ | ☐ |
| energia_alarma | | | | | por modelo | ☐ | ☐ |
| energia_estado | | | | | LAFLORIDA-1 | ☐ | ☐ |
| temperatura_cpu | | | | | LAFLORIDA-1 / X15 \n | ☐ | ☐ |
| uptime_gpon | | | | | DURZUA | ☐ | ☐ |
| ont_detalle | | | | | cred ONT, 0-15 vs 0-7 | ☐ | ☐ |
| ont_reporte | | | | | DURZUA | ☐ | ☐ |
| version | | | | | prompt backplane | ☐ | ☐ |
| uplink_state | *(desactivada — activo=0; validar solo si se reactiva)* | | | | | ☐ | ☐ |

Celda: ✅ paridad OK · ⚠️ diferencia menor justificada · ❌ bloqueante · — no aplica.

### Otros chequeos transversales

- [ ] `OLT_API_LOG_TELNET` guarda una fila por ejecución (con `comandos_enviados`,
      `log_crudo`, `duracion_ms`, `exito`).
- [ ] `OLT_API_AUDITORIA` registra `EJECUCION`/`LECTURA`/`AUTH_*` de todas las pruebas.
- [ ] `OLT_API_JOBS`: un `/ejecutar/lote` de 3 OLT termina `OK` con `resultado` por OLT.
- [ ] Reiniciar la API con un job `RUNNING` → queda `ERROR: "interrumpido por reinicio"`.
- [ ] `GET /uplink/trafico/OLT-X?con_log=true` devuelve log si se activó `LOG_API_TELNET`
      en `uplink_trafico_15m` (F7).
- [ ] 6 fallos de auth desde una IP → `401` + bloqueo; una key correcta sigue dando `401`
      hasta que vence.
- [ ] `?desde&hasta` sobre una consulta de columna `DATETIME` devuelve serie; sobre una de
      texto → `422` con mensaje claro.
- [ ] Un `reboot` / `undo …` a `/ejecutar/comandos` → `422`, no se ejecuta, queda en
      auditoría como `RECHAZADO`.

### 3 `fuente_lista` a confirmar con un SELECT real (SOLICITUDES_PENDIENTES.txt)

- [ ] `OLT_PUERTAS_UPLINKS_GB` → `SELECT puerta FROM OLT_PUERTAS_UPLINKS_GB WHERE olt=:server`
      devuelve las puertas de uplink esperadas.
- [ ] `OLT_VLAN_SERVICIOS` → el catálogo completo es lo correcto para `vlan_trafico`.
- [ ] `OLT_VOLTAJE_TARJETA.tarjetas` → es un CSV de slots reutilizable como fuente de slots
      GPON para `pon_trafico` / `ont_detalle`.
  Ajustar los resolvedores de `utils/ejecutor.py` si el criterio real difiere (la firma no
  cambia).

---

## 6. Cierre de F9

Cuando la matriz esté completa:
1. Commit de las transcripciones reales (las que no tengan datos sensibles) y de los
   parsers ajustados, con `tests/test_parsers_reales.py` en verde.
2. Actualizar `PLAN_API_OLT.md` (tracker F9 → ✅) y cerrar las entradas resueltas de
   `SOLICITUDES_PENDIENTES.txt`.
3. Recién entonces: **F10 — despliegue**.
