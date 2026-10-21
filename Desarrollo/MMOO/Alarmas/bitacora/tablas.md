# Catálogo de tablas (esquema `mmoo`)

Quién escribe y quién lee cada tabla. Se actualiza con cada proceso revisado.
Los consumidores se buscaron en `C:\laragon\www\panel_mmoo\MMOO\` (copia 2024-12; verificar contra la versión productiva del panel).

Leyenda de operación: **T** = TRUNCATE, **D** = DELETE parcial, **I** = INSERT, **S** = SELECT.

| Tabla | Escribe (proceso → archivo) | Op | Lee (procesos) | Lee (panel) |
|---|---|---|---|---|
| `MMOO_POTENCIA_RX` | Python en .140 (**no revisado**) | I | slotAlarmados, slotAlarmadosDias, count_alarma | — |
| `MMOO_ALARM_REVISADAS` | ¿panel? (no revisado) | — | slotAlarmados | — |
| `MMOO_IP_ALERTA` | cargaPotencia → slotAlarmados (1h), slotAlarmadosDias (1D/4D) | T+I | reportAlarmHora, count_alarma | panel.php, vistaDetalle.php, procesosMMU.php, equipos_Procesos.php, procesoExpAlarmas*.php |
| `MMOO_IP_ALERTA_OBSERVACION` | panel (comentarios) | — | count_alarma | — |
| `MMOO_HIST_ALARM_HORA` | cargaPotencia → reportAlarmHora | I (solo agrega) | — | graficos/graficosReporteHist{Hora,Mes,Anual}.php |
| `MMOO_COUNTMMU` | cargaPotencia → count_alarma (tipos de alarma y %), cargaDatosNodo → count_mmu (`MMU`, `MAGAZINE`) | D+I / **T**+I | count_alarma (tipo `MMU`) | panelAlarmas.php, equipos.php |
| `MMOO_IP` | no revisado (¿`carga_ips_mmoo.php`?) | — | todos los procesos Ericsson | varios |
| `MMOO_REGIONES` | maestro | — | count_alarma | varios |
| `MMOO_INFO_IP` | cargaDatosNodo → hilosInfo/infoNodo | T+I | count_alarma (INNER JOIN) | vistaNodo*.php, vistaDetalle*.php, datosBusqueda*.php, panelAlarmas_data.php, procesoExp*.php, revision_ticket/*, mantenedor/procesar.php |
| `MMOO_IP_REMOTA` | cargaDatosNodo → hilos/ipRemota (**no funciona**) | T+I | — | vistaNodo*.php, vistaNodoHUA.php, cargaIpRemota.php, cargaDatosRemoto.php, nodo_linea/vistaRemota.php |
| `MMOO_MAGAZINE` | cargaDatosNodo → hilosMagazine/magazine | T+I | count_mmu | vistaNodo*.php, procesosMMU.php, equiposProcesos.php, cargaDatosRemoto*.php, PanelBandaE/vistaNodo.php |
| `MMOO_MIBSLOT` | maestro | — | magazine | — |
| `MMOO_ALARMAS_TN` | cargaPotencia → hilosAlarmasFisicasTN/alarmasFisicasTN | I (**nunca se limpia**) | — | panelAlarmas_data.php |
| `MMOO_ALARMAS_TN_LOG` | cargaPotencia → hilosAlarmasFisicasTN | T (no se inserta) | — | — |
| `HUA_IPS` | no revisado (¿`huawei/hilosEstructura*`?) | — | hilosNiveles(_2), conteoAlarmas | — |
| `HUA_MAGAZINE` | no revisado (¿`huawei/procesoEstructura*`?) | — | hilosNiveles(_2) | — |
| `HUA_CONTEO` | no revisado | — | conteoAlarmas | — |
| `HUA_POTENCIAS` | cargaPotencia → hilosNiveles/procesoNiveles | I (solo agrega) | procesoAlarmas | graficoRSL{Dia,Semanal,Mensual,Anual}HUA.php, huawei/alarmas/detallePorRegion.php, procesoExpAlarmasHUA.php, PanelGeneral/procesoExpAlarmas.php |
| `HUA_POTENCIAS_LOG` | cargaPotencia → procesoNiveles | T+I | — | — |
| `HUA_POTENCIA_RX` | cargaPotencia_2 → procesoNiveles_2 | I (solo agrega) | — | **ninguno encontrado** |
| `HUA_POTENCIAS_LOG_2` | cargaPotencia_2 → procesoNiveles_2 | T+I | — | — |
| `HUA_ALARMAS_NEW` | cargaPotencia → huawei_/procesoAlarmas | T+I | conteoAlarmas | huawei/alarmas/detallePorRegion.php, procesoExpAlarmasHUA.php |
| `HUA_CONTEO_ALARMAS` | cargaPotencia → huawei/conteoAlarmas | T+I | — | huawei/alarmas/alarmasActuales.php |
