<?php
/**
 * Generación del Excel semanal de capacidades BW construyendo sobre el archivo anterior.
 * Compatible con PHP 5.3.
 */
require_once dirname(__FILE__) . '/bw_config.php';
require_once dirname(__FILE__) . '/bw_fechas.php';
require_once dirname(__FILE__) . '/bw_datos.php';
require_once dirname(__FILE__) . '/bw_xlsx.php';

// Archivos "... {anio} W-{n}.xlsx" de la carpeta: array(semana => ruta)
function bw_archivos_semana($dir, $anio)
{
    $lista = array();
    $archivos = glob($dir . '/' . BW_PREFIJO_ARCHIVO . ' * ' . $anio . ' W-*.xlsx');
    if ($archivos) {
        foreach ($archivos as $a) {
            if (preg_match('/ ' . $anio . ' W-(\d+)\.xlsx$/', $a, $m)) {
                $lista[(int)$m[1]] = $a;
            }
        }
    }
    ksort($lista);
    return $lista;
}

/**
 * Plantilla: el último archivo del mismo año anterior a la semana pedida; si no hay,
 * el de la misma semana (regeneración); si no, el último del año anterior.
 * Retorna array(ruta, anio) o null
 */
function bw_buscar_plantilla($dir, $anio, $semana)
{
    $mismoAnio = bw_archivos_semana($dir, $anio);
    $elegida = null;
    foreach ($mismoAnio as $w => $ruta) {
        if ($w < $semana) {
            $elegida = $ruta;
        }
    }
    if ($elegida === null && isset($mismoAnio[$semana])) {
        $elegida = $mismoAnio[$semana];
    }
    if ($elegida !== null) {
        return array($elegida, $anio);
    }
    $anterior = bw_archivos_semana($dir, $anio - 1);
    if (count($anterior) > 0) {
        return array(end($anterior), $anio - 1);
    }
    return null;
}

/**
 * Genera los 2 Excel y el .json de alertas de la semana indicada (lunes a domingo, semana 1 = la que contiene el 1 de enero).
 * $opc: forzar (bool), datos (ruta .json de prueba en vez de BD), plantilla (ruta)
 */
function bw_generar_reporte_semanal($anio, $semana, $opc = array())
{
    $anio = (int)$anio;
    $semana = (int)$semana;
    $forzar = !empty($opc['forzar']);
    if ($semana < 1 || $semana > bw_semanas_del_anio($anio)) {
        throw new Exception("La semana $semana no existe en $anio");
    }
    if (!is_dir(BW_DIR_SEMANA)) {
        throw new Exception('No existe la carpeta ' . BW_DIR_SEMANA);
    }

    $nombres = bw_nombres_archivo($anio, $semana);
    $destSemana = BW_DIR_SEMANA . '/' . $nombres['semana'];
    $destAct = BW_DIR_SEMANA . '/' . $nombres['act'];
    if (file_exists($destSemana) && !$forzar) {
        bw_log("W-$semana $anio ya existe ($nombres[semana]), se omite. Use --forzar para regenerar.");
        return array('estado' => 'existe', 'archivo' => $nombres['semana']);
    }

    // ---------- plantilla ----------
    if (!empty($opc['plantilla'])) {
        $anioPl = preg_match('/ (\d{4}) (W-\d+|Act - Act)\.xlsx$/', $opc['plantilla'], $m) ? (int)$m[1] : $anio;
        $plantilla = array($opc['plantilla'], $anioPl);
    } else {
        $plantilla = bw_buscar_plantilla(BW_DIR_SEMANA, $anio, $semana);
    }
    if ($plantilla === null) {
        throw new Exception('No hay Excel anterior en ' . BW_DIR_SEMANA . ' para usar como base');
    }
    list($rutaPl, $anioPl) = $plantilla;
    bw_log("Generando W-$semana $anio con base en " . basename($rutaPl));

    $libro = new BwLibroXlsx();
    $libro->abrir($rutaPl);
    $libro->normalizarFormulas();
    $nPl = $libro->numDias();
    if ($nPl < 1) {
        throw new Exception('La plantilla no tiene días cargados');
    }
    if ($libro->dxf === null) {
        bw_log('ADVERTENCIA: no se encontró el formato condicional estándar (60/70) en la plantilla');
    }
    $proto = $libro->capturarPrototipo($nPl - 1);

    $diaIni = ($semana - 1) * 7;          // lunes de la semana pedida
    $diaFin = $diaIni + 6;                // domingo
    $k0 = ($anioPl == $anio) ? min($nPl, $diaIni) : 0;   // primer día a (re)escribir
    $kCap = ($k0 > 0) ? $k0 - 1 : $nPl - 1;              // día del que se toma la última capacidad conocida

    // OLTs del Excel y su última capacidad conocida (por nombre)
    $capPrevia = array();
    $colCap = bw_col_dia($kCap) + 1;
    for ($r = 3; $r <= $libro->ultimaFila(); $r++) {
        $olt = trim((string)$libro->valor($r, 4));
        if ($olt != '') {
            $v = $libro->valor($r, $colCap);
            $capPrevia[bw_clave_olt($olt)] = is_numeric($v) && $v > 0 ? (float)$v : null;
        }
    }

    $libro->truncarDias($k0);

    // ---------- datos ----------
    $desde = bw_fecha_dia($anio, $k0);
    $hasta = bw_fecha_dia($anio, $diaFin);
    if (!empty($opc['datos'])) {
        list($olts, $trafico) = bw_datos_desde_json($opc['datos']);
    } else {
        $mysqli = bw_conectar_bd();
        $olts = bw_obtener_olts($mysqli);
        $trafico = bw_obtener_trafico($mysqli, $desde, $hasta);
        $mysqli->close();
    }
    bw_log('Datos: ' . count($olts) . " OLTs, tráfico de $desde a $hasta para " . count($trafico) . ' ips');

    // ---------- OLTs nuevas (con tráfico en el período y que no estén en el Excel) ----------
    $alertas = array();
    $nuevas = array();
    $agregadas = array();
    foreach ($olts as $clave => $o) {
        if (array_key_exists($clave, $capPrevia) || $o['cap'] === null) {
            continue;
        }
        $conTrafico = false;
        if (isset($trafico[$o['ip']])) {
            foreach ($trafico[$o['ip']] as $f => $v) {
                if ($f >= bw_fecha_dia($anio, $diaIni) && $f <= $hasta) {
                    $conTrafico = true;
                }
            }
        }
        if ($conTrafico) {
            $nuevas[] = $o;
        }
    }
    foreach ($nuevas as $o) {
        // Buscar el grupo de PE-com de otra OLT del Excel con el mismo pe_id
        $grupo = null;
        $grupos = $libro->gruposPe();
        foreach ($grupos as $g) {
            for ($r = $g[0]; $r <= $g[1]; $r++) {
                $c = bw_clave_olt((string)$libro->valor($r, 4));
                if (isset($olts[$c]) && $olts[$c]['pe_id'] == $o['pe_id']) {
                    $grupo = $g;
                }
            }
        }
        if ($grupo !== null) {
            $pos = $grupo[1] + 1;
            $modelo = $grupo[1];
        } else {
            $pos = $libro->ultimaFila() + 1;
            $modelo = $libro->ultimaFila();
        }
        $libro->insertarFila($pos, $modelo);
        $proto['filas'] = bw_proto_insertar($proto['filas'], $pos, $modelo);
        $celdaD = $libro->celda($pos, 4);
        $libro->ponerCelda($pos, $libro->celdaTexto('D' . $pos, $celdaD ? $celdaD->getAttribute('s') : null, $o['olt']));
        if ($grupo !== null) {
            $libro->extenderGrupo($grupo[0], $pos);
        } else {
            $celdaA = $libro->celda($pos, 1);
            $libro->ponerCelda($pos, $libro->celdaTexto('A' . $pos, $celdaA ? $celdaA->getAttribute('s') : null, $o['pe_com']));
        }
        $capPrevia[bw_clave_olt($o['olt'])] = null;
        $agregadas[bw_clave_olt($o['olt'])] = true;
        $alertas[] = array('tipo' => 'olt_nueva', 'olt' => $o['olt'], 'pe_com' => $o['pe_com'], 'fila' => $pos,
            'detalle' => 'OLT agregada al Excel en la fila ' . $pos . ($grupo === null ? ' (PE-com nuevo al final)' : ''));
        bw_log("OLT nueva agregada: $o[olt] ($o[pe_com]) en fila $pos");
    }

    // ---------- filas de OLT ----------
    $filasOlt = array();   // fila => array(clave, olt, pe)
    foreach ($libro->gruposPe() as $g) {
        $pe = trim((string)$libro->valor($g[0], 1));
        for ($r = $g[0]; $r <= $g[1]; $r++) {
            $olt = trim((string)$libro->valor($r, 4));
            if ($olt != '') {
                $clave = bw_clave_olt($olt);
                $filasOlt[$r] = array('clave' => $clave, 'olt' => $olt,
                    'pe' => (isset($olts[$clave]) && $olts[$clave]['pe_com'] != '') ? $olts[$clave]['pe_com'] : $pe);
            }
        }
    }

    // ---------- cambios de capacidad respecto del último día del Excel anterior ----------
    foreach ($filasOlt as $r => $info) {
        $o = isset($olts[$info['clave']]) ? $olts[$info['clave']] : null;
        $antes = isset($capPrevia[$info['clave']]) ? $capPrevia[$info['clave']] : null;
        if ($o && $o['cap'] !== null && $antes !== null && (int)$o['cap'] != (int)$antes) {
            $alertas[] = array('tipo' => 'capacidad', 'olt' => $info['olt'], 'pe_com' => $info['pe'], 'fila' => $r,
                'anterior' => $antes, 'nueva' => $o['cap'],
                'detalle' => 'Capacidad cambia de ' . number_format($antes, 0, ',', '.') . ' a '
                    . number_format($o['cap'], 0, ',', '.') . ' Mbps');
        }
    }

    // ---------- agregar días ----------
    $colsPct = array();
    $valoresSemana = array();   // k => fila => array(traf, cap)
    for ($k = $k0; $k <= $diaFin; $k++) {
        $fecha = bw_fecha_dia($anio, $k);
        $valores = array();
        foreach ($filasOlt as $r => $info) {
            $o = isset($olts[$info['clave']]) ? $olts[$info['clave']] : null;
            $traf = null;
            if ($o && isset($trafico[$o['ip']][$fecha])) {
                $traf = round($trafico[$o['ip']][$fecha], 0);
            }
            $cap = ($o && $o['cap'] !== null && $o['cap'] > 0) ? $o['cap'] : $capPrevia[$info['clave']];
            $valores[$r] = array($traf, $cap);
        }
        $colsPct[] = $libro->agregarDia($k, bw_etiqueta_dia($anio, $k), $proto, $valores);
        if ($k >= $diaIni) {
            $valoresSemana[$k] = $valores;
        }
    }
    $libro->agregarFormatoCondicional($colsPct, 3, $libro->ultimaFila());

    // ---------- B (promedio semanal del tráfico del PE) y C (capacidad del PE al domingo) ----------
    foreach ($libro->gruposPe() as $g) {
        $sumaDias = 0;
        $diasConDatos = 0;
        $capPe = 0;
        foreach ($valoresSemana as $k => $valores) {
            $sumaDia = 0;
            $hay = false;
            for ($r = $g[0]; $r <= $g[1]; $r++) {
                if (isset($valores[$r]) && $valores[$r][0] !== null) {
                    $sumaDia += $valores[$r][0];
                    $hay = true;
                }
                if ($k == $diaFin && isset($valores[$r]) && $valores[$r][1] !== null) {
                    $capPe += $valores[$r][1];
                }
            }
            if ($hay) {
                $sumaDias += $sumaDia;
                $diasConDatos++;
            }
        }
        if ($diasConDatos > 0) {
            $libro->fijarValor($g[0], 2, (float)round($sumaDias / $diasConDatos, 0));
        }
        if ($capPe > 0) {
            $libro->fijarValor($g[0], 3, (float)$capPe);
        }
    }

    $ultimaCol = bw_col_dia($diaFin) + 2;
    $libro->ajustarVista($ultimaCol, bw_col_dia($diaIni));

    // ---------- alertas de la semana ----------
    $sinRegistro = array();
    foreach ($valoresSemana as $k => $valores) {
        $fecha = bw_fecha_dia($anio, $k);
        $colPct = bw_col_dia($k) + 2;
        foreach ($filasOlt as $r => $info) {
            $traf = $valores[$r][0];
            $cap = $valores[$r][1];
            if ($traf === null) {
                $sinRegistro[$r][] = $fecha;
                continue;
            }
            if ($cap > 0) {
                $pct = $traf * 100 / $cap;
                if ($pct >= BW_UMBRAL_ALERTA) {
                    $alertas[] = array('tipo' => 'uplink', 'fecha' => $fecha, 'dia' => bw_etiqueta_dia($anio, $k),
                        'olt' => $info['olt'], 'pe_com' => $info['pe'], 'porcentaje' => round($pct, 2),
                        'trafico' => $traf, 'capacidad' => $cap, 'celda' => bw_col_letra($colPct) . $r);
                }
            }
        }
    }
    foreach ($sinRegistro as $r => $fechas) {
        if (isset($agregadas[$filasOlt[$r]['clave']])) {
            continue;   // OLT recién agregada: los días previos a su primer registro no son falla
        }
        $dias = array();
        foreach ($fechas as $f) {
            $dias[] = bw_etiqueta_dia($anio, (int)round((strtotime($f . ' 12:00:00') - bw_ts_dia($anio, 0)) / 86400));
        }
        $alertas[] = array('tipo' => 'sin_registro', 'olt' => $filasOlt[$r]['olt'], 'pe_com' => $filasOlt[$r]['pe'],
            'fechas' => $fechas, 'dias' => $dias, 'fila' => $r,
            'detalle' => count($fechas) == 7 ? 'Sin registros toda la semana' : 'Sin registro: ' . implode(', ', $dias));
    }

    // ---------- guardar ----------
    $libro->guardar($destSemana, $anio);
    if (!copy($destSemana, $destAct)) {
        throw new Exception("No se pudo crear $destAct");
    }
    $resumen = array(
        'anio' => $anio,
        'semana' => $semana,
        'desde' => bw_fecha_dia($anio, $diaIni),
        'hasta' => bw_fecha_dia($anio, $diaFin),
        'generado' => date('Y-m-d H:i:s'),
        'umbral' => BW_UMBRAL_ALERTA,
        'archivos' => array('semana' => $nombres['semana'], 'act' => $nombres['act']),
        'base' => basename($rutaPl),
        'alertas' => $alertas,
    );
    file_put_contents(BW_DIR_SEMANA . '/' . bw_nombre_alertas($anio, $semana), json_encode($resumen));
    bw_log("OK W-$semana $anio: $nombres[semana] / $nombres[act], " . count($alertas) . ' alertas');
    return array('estado' => 'ok', 'resumen' => $resumen);
}

// Ajusta el prototipo de estilos por fila luego de insertar una fila en $pos
function bw_proto_insertar($filas, $pos, $modelo)
{
    $nuevo = array();
    foreach ($filas as $r => $est) {
        $nuevo[$r >= $pos ? $r + 1 : $r] = $est;
    }
    $nuevo[$pos] = isset($filas[$modelo]) ? $filas[$modelo] : reset($filas);
    ksort($nuevo);
    return $nuevo;
}
