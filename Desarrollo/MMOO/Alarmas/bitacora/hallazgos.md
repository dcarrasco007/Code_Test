# Hallazgos (bugs, riesgos y deuda técnica)

Numeración correlativa `H-xx`. No se reutilizan los números. Si un hallazgo se resuelve o se descarta, marcarlo en "Estado" en vez de borrarlo.

| ID | Severidad | Proceso / archivo | Hallazgo | Estado |
|---|---|---|---|---|
| H-01 | Alta | cargaDatosNodo → `count_mmu.php` | Hace `TRUNCATE MMOO_COUNTMMU` (la tabla completa) a las ~21:26. Borra los contadores y % que deja `count_alarma.php`, que no vuelven hasta las 22:10, y el panel queda sin datos ~45 min al día. Además `count_alarma` necesita la fila `MMU` de `count_mmu`: es una dependencia cruzada. | Abierto |
| H-02 | Media | Todos (`TRUNCATE` + recarga) | `MMOO_INFO_IP`, `MMOO_MAGAZINE`, `MMOO_IP_REMOTA`, `MMOO_IP_ALERTA`, `HUA_ALARMAS_NEW`, `HUA_CONTEO_ALARMAS` quedan vacías o incompletas mientras corre la carga. `count_alarma` hace INNER JOIN con `MMOO_INFO_IP`. | Abierto |
| H-03 | Media | `huawei_/hilosNiveles.php` y `hilosNiveles_2.php` | Usan la misma carpeta `./niveles_logs/` y ambos hacen `rm -rf niveles_logs/*` al iniciar. Si el de :10 se atrasa y se cruza con el de :30, cada uno borra los .txt del otro. | Abierto |
| H-04 | Media | `alarmas/alarmasFisicasTN.php` | `MMOO_ALARMAS_TN` **nunca se limpia**: cada hora agrega todas las alarmas activas de nuevo (duplicados y crecimiento sin fin). `MMOO_ALARMAS_TN_LOG` se vacía pero nunca se llena (el insert está comentado). | Abierto |
| H-05 | Alta (seguridad) | `huawei_/rtnNiveles.sh`, `alarmas/alarmasFisicasTN.sh`, `infoNodo.php`, `ipRemota.php`, `magazine.php` | Credenciales de equipos (SSH, Telnet, SNMPv3) en texto plano dentro de los scripts. Telnet sin cifrar. | Abierto |
| H-06 | Baja | `hilosInfo.php`, `hilosMagazine.php` | Error de precedencia: `PING='OK' AND fecha>=… OR IPINGRESO IS NULL` también incluye las IPs con `IPINGRESO` nulo aunque no respondan ping. | Abierto |
| H-07 | Media | Todos | Los SQL se arman concatenando texto, sin escapar. Una comilla en sysLocation o en un log rompe el lote. En `infoNodo.php` el `or die` corta los 200 equipos del grupo. También es un riesgo de inyección si un equipo devuelve texto malicioso. | Abierto |
| H-08 | Baja | Ericsson (`MMOO_POTENCIA_RX`) | `DATE_ID` queda 1 h adelantado respecto a la hora de ejecución (corre a las 14:10 y guarda 15:00). Probable diferencia de zona horaria con el Python de .140. Hay que verificarlo antes de migrar. | Por verificar |
| H-09 | Alta | `count_alarma.php` | Hace N+1 consultas (16 regiones × 3 rangos × IPs alarmadas, cada una sobre 30 días de `MMOO_POTENCIA_RX`). El log local muestra `COMIENZA` y nunca `FIN`: posible caída o timeout. Usa el tag corto `<?` y lee `$_POST` en CLI. | Por verificar en servidor |
| H-10 | Media | `procesoNiveles(_2).php`, `alarmasFisicasTN.php` | Esperan con `sleep` fijos y después leen los .txt. Si el equipo responde lento, el archivo queda incompleto y el dato se pierde **sin aviso**. En `alarmasFisicasTN` el `exec` va con `&` (corre en segundo plano). En `procesoNiveles` va sin `&` (los SSH son secuenciales) y además hay `sleep(6)` por IP. | Abierto |
| H-11 | Media | `cargaPotencia_2.sh` | Duplica las sesiones SSH a todos los RTN Huawei cada hora. Su tabla `HUA_POTENCIA_RX` no la lee ninguna pantalla encontrada. | Por confirmar con negocio |
| H-12 | Alta (funcional) | `hilos.php` / `ipRemota.php` | Marcado "No funciona". El log devuelve arrays vacíos, así que `MMOO_IP_REMOTA` queda vacía cada noche, pero ~10 pantallas la leen. | Abierto |
| H-13 | Baja | `slotAlarmadosDias.php` | Si no hay alarmas 1h, `$ips` queda vacío y el SQL termina en `IN ()` (error). Las filas 1D/4D se insertan sin `DATE_ID`. | Abierto |
| H-14 | Baja | `huawei_/procesoAlarmas.php` | No genera alarmas `4D`, aunque `conteoAlarmas.php` filtra por `'4D'`. No hay lista de alarmas revisadas para Huawei (`$arrIPRev` vacío). | Abierto |
| H-15 | Media | `func_miltihilo.php` y copias locales de `multihilos()` | Bucle `do { curl_multi_exec } while ($active)` sin `curl_multi_select`: espera ocupando CPU. `CONNECTTIMEOUT 9000000` en la versión compartida y sin timeout total, así que una llamada colgada bloquea el cron. | Abierto |
