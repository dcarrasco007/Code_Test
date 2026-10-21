# `cargaDatosNodo.sh`

| Campo | Valor |
|---|---|
| Ruta servidor | `/var/www/html/MMOO/procesos/cargaDatosNodo.sh` (hace `cd /u01/MMOO/procesos`) |
| Ruta local revisada | `C:\laragon\www\126\126_procesos\MMOO\procesos\` (archivos del 2026-01-27) |
| Cron | `00 21 * * *` (diario 21:00) |
| Duración observada | ~26 min (log 2025-09-04 21:00:01 → 21:25:58): info 4 min, remota 13 min, magazine 9 min |
| Complejidad | **Media** |
| Estado | `hilosInfo` parcial ("en revisión"), `hilos` (remota) **no funciona**, `hilosMagazine` y `count_mmu` OK |
| Fecha revisión | 2026-10-21 |

Existe una variante en `procesos_127/cargaDatosNodo.sh`: solo corre `hilosInfo.php` y `hilos.php`, y tiene magazine y
count_mmu comentados. **(inferido)** Corre en el servidor .127.

## Por qué existe
Arma una vez al día el **inventario de los nodos Ericsson MINI-LINK**, que el panel muestra en la ficha de cada nodo:
- **Datos del nodo** (nombre, contacto, ubicación, modelo, NPU, uptime): `MMOO_INFO_IP`. Lo leen `Vistas/vistaNodo*.php`,
  `vistaDetalle.php`, `datosBusqueda*.php`, `panelAlarmas_data.php`, `procesoExpAlarmas*.php`, `revision_ticket/*`, `mantenedor/procesar.php`.
- **IP del extremo remoto** de cada enlace: `MMOO_IP_REMOTA`. Lo leen `vistaNodo*.php`, `vistaNodoHUA.php`,
  `cargaIpRemota.php`, `cargaDatosRemoto.php`, `nodo_linea/vistaRemota.php`.
- **Tarjeta por slot** (MMU, NPU, LTU, ETU, PFU): `MMOO_MAGAZINE`. Lo leen `vistaNodo*.php`, `procesosMMU.php`,
  `equiposProcesos.php`, `cargaDatosRemoto*.php`.
- **Totales por región** (MMU y nodos): `MMOO_COUNTMMU` tipos `MMU` / `MAGAZINE`. Son la base de los % de `count_alarma.php`.

## Flujo

```
cargaDatosNodo.sh
│  (carga_ips_mmoo.php → comentado, "falta revisar")
├─ 1. hilosInfo.php    ──HTTP (200 IPs c/u)──► infoNodo.php  → infoIp()   (snmpget v3)  → MMOO_INFO_IP
├─ 2. hilos.php        ──HTTP (200 IPs c/u)──► ipRemota.php  → remota()   (snmpwalk v3) → MMOO_IP_REMOTA
├─ 3. hilosMagazine.php──HTTP (200 IPs c/u)──► magazine.php  → magazine() (snmpwalk v3) → MMOO_MAGAZINE
└─ 4. count_mmu.php                                                                   → MMOO_COUNTMMU
```

Las funciones `infoIp()`, `remota()` y `magazine()` están en `procesos/funciones.php`. Ahí hay una segunda `remota()`,
pero está dentro de un comentario `/* */` (líneas 88-120), así que no causa error.
Cada hilo trae su propia copia de `multihilos()` (`curl_multi`, `CONNECTTIMEOUT 10`).

### Detalle por archivo

**1. `hilosInfo.php` → `infoNodo.php`**
- `TRUNCATE MMOO_INFO_IP`.
- IPs: `MMOO_IP WHERE PING='OK' AND DATE(IPINGRESO) >= '2017-01-24' OR IPINGRESO IS NULL`, excluyendo `10.248.248.61` (H-06).
- `snmpget` de: sysContact, sysName, sysLocation, entPhysicalDescr.1 (modelo), NPU antiguo
  (`...193.81.2.7.1.2.1.3.2`), NPU nuevo / release (`...193.81.2.7.1.6.1.4.1954840575.1`) y sysUpTime.
- Inserta `(IP, NOMBRE, CONTACTO, UBICACION, MODELO, NPU_OLD, NPU_NEW, TIME)` en lotes de 100, con `or die`.
- Comentario del autor: "en revisión porque retorna la información de los magazines en nulo". La función tiene la
  nota "la consulta snmp devuelve respuesta snmpv2 y no v3", y por eso compara con nombres `SNMPv2-MIB::...`.

**2. `hilos.php` → `ipRemota.php`**
- `TRUNCATE MMOO_IP_REMOTA`.
- IPs: `MMOO_IP WHERE DATE(IPINGRESO) >= '2017-01-24' OR IPINGRESO IS NULL` (sin filtro de ping).
- `snmpwalk ...193.81.3.4.1.1.14.1.16` devuelve la tabla de IPs de los vecinos. Separa la IP propia de la remota y
  arma `(IP, IP_REMOTA, ORDEN_APAR)`.
- Comentario del autor: "No funciona, falta revisar". El log devuelve arrays vacíos → la tabla queda **vacía** (H-12).

**3. `hilosMagazine.php` → `magazine.php`**
- `TRUNCATE MMOO_MAGAZINE`.
- Toma de `MMOO_MIBSLOT (TIPO='SLOT')` el mapeo entre índice SNMP y número de slot.
- `snmpwalk ...193.81.2.1.1.2.1.1` devuelve el nombre de la tarjeta por slot, por ejemplo `"MMU2 C"`, `"NPU3 C"`, `"LTU 16x2"`.
- Inserta `(IP, SLOT, VALOR)` en lotes de 50.

**4. `count_mmu.php`**
- `TRUNCATE MMOO_COUNTMMU` (**la tabla completa**, H-01).
- Inserta `tipo='MMU'`: `COUNT(MMOO_MAGAZINE.SLOT)` con `VALOR LIKE 'MMU%'` por región.
- Inserta `tipo='MAGAZINE'`: `COUNT(IP)` de `MMOO_IP` por región.

## Tablas

| Tabla | Operación | Datos |
|---|---|---|
| `MMOO_IP` | SELECT | Maestro de IPs Ericsson (PING, IPINGRESO, REGION) |
| `MMOO_MIBSLOT` | SELECT | Mapeo índice SNMP → slot |
| `MMOO_INFO_IP` | TRUNCATE + INSERT | Nombre, contacto, ubicación, modelo, NPU, uptime |
| `MMOO_IP_REMOTA` | TRUNCATE + INSERT | IP local, IP remota, orden |
| `MMOO_MAGAZINE` | TRUNCATE + INSERT | Tarjeta instalada por slot |
| `MMOO_COUNTMMU` | TRUNCATE + INSERT | Totales `MMU` y `MAGAZINE` por región |

## Dependencias
- `MMOO_IP` y `MMOO_MIBSLOT` las carga otro proceso (pendiente de revisar; candidato: `carga_ips_mmoo.php`).
- `count_alarma.php` (cargaPotencia, cada hora a las :10) usa `MMOO_COUNTMMU` tipo `MMU` y `MMOO_INFO_IP`.
  El TRUNCATE de las 21:00-21:26 deja esos datos vacíos o incompletos hasta la corrida de las 22:10 (H-01, H-02).
- Ninguna tabla de este proceso registra fecha de carga, así que no se puede saber qué tan vigentes están los datos.

## Hallazgos
H-01, H-02, H-05, H-06, H-07, H-12, H-15 (ver [hallazgos.md](../hallazgos.md)).

## Notas para la migración
- Arreglar o descartar la búsqueda de IP remota antes de migrar. Hoy la tabla está vacía, pero ~10 pantallas la leen.
- Unificar las tres pasadas SNMP en **una sola por equipo** (get + 2 walks): baja de ~26 min a una fracción.
- Agregar `fecha_carga` y una carga por staging/swap para no dejar las tablas vacías.
- `count_mmu` no debería vaciar toda la tabla: que borre solo `tipo IN ('MMU','MAGAZINE')`, o mejor, separar
  los totales de inventario de los contadores de alarmas.
