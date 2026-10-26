<?php
/**
 * Reporte BW anual en BD (tabla OLT_REPORTE_BW_ANUAL): una fila por OLT y día.
 *
 * Cron (solo BD): bw_anual_generar_semana() agrega los 7 días de la semana (y los que falten),
 *   inserta OLTs nuevas en su grupo de PE-com y calcula la columna C de PE-com nuevos.
 * Web: bw_anual_semanas(), bw_anual_alertas() y bw_anual_datos_excel() para la ventana y la descarga.
 *
 * Reglas (las mismas del Excel en disco):
 *   - Semanas lunes a domingo; la semana 1 es la que contiene el 1 de enero (lib/bw_fechas.php).
 *   - Capacidad OLT: capacidad_total / 2 si es de 10G o más, 1G sin dividir (bw_capacidad_mbps).
 *   - Día sin registro de una OLT que ya existía: tráfico NULL (vacío en el Excel).
 *   - Días anteriores al primer registro histórico de la OLT: tráfico 0 con su capacidad.
 *   - OLT nueva: se agrega al final de su grupo de PE-com (o en un grupo nuevo al final);
 *     sus días anteriores del año quedan en 0 con la capacidad obtenida esa semana.
 *   - Columna C (cap_pe): se mantiene; solo para un PE-com nuevo se calcula la suma de sus OLTs.
 * PHP 8.0.
 */
require_once __DIR__ . '/bw_config.php';
require_once __DIR__ . '/bw_fechas.php';
require_once __DIR__ . '/bw_datos.php';

function bw_anual_q($mysqli, $sql)
{
    $res = $mysqli->query($sql);
    if ($res === false) {
        throw new Exception('Error SQL: ' . $mysqli->error . ' | ' . substr($sql, 0, 300));
    }
    return $res;
}

function bw_anual_esc($mysqli, $v)
{
    return "'" . $mysqli->real_escape_string((string)$v) . "'";
}

function bw_anual_num($v)
{
    return ($v === null || $v === '') ? 'NULL' : (string)(int)round($v);
}

/**
 * Estructura del año: array ordenado por "orden" de
 *   clave => array('olt','orden','grupo','pe_com','cap_pe','alta','cap' => última capacidad conocida)
 */
function bw_anual_layout($mysqli, $anio)
{
    $t = BW_TABLA_ANUAL;
    $anio = (int)$anio;
    $res = bw_anual_q($mysqli, "SELECT olt, MAX(orden) AS orden, MAX(grupo) AS grupo, MAX(pe_com) AS pe_com,
                                       MAX(cap_pe) AS cap_pe, MAX(alta) AS alta
                                FROM `$t` WHERE anio = $anio GROUP BY olt ORDER BY orden");
    $layout = array();
    while ($r = $res->fetch_assoc()) {
        $layout[bw_clave_olt($r['olt'])] = array(
            'olt' => $r['olt'], 'orden' => (int)$r['orden'], 'grupo' => (int)$r['grupo'], 'pe_com' => $r['pe_com'],
            'cap_pe' => $r['cap_pe'] === null ? null : (int)$r['cap_pe'], 'alta' => $r['alta'], 'cap' => null,
        );
    }
    // Última capacidad conocida de cada OLT
    $res = bw_anual_q($mysqli, "SELECT a.olt, a.capacidad FROM `$t` a
                                INNER JOIN (SELECT olt, MAX(fecha) AS f FROM `$t`
                                            WHERE anio = $anio AND capacidad IS NOT NULL AND capacidad > 0 GROUP BY olt) m
                                    ON a.olt = m.olt AND a.fecha = m.f
                                WHERE a.anio = $anio");
    while ($r = $res->fetch_assoc()) {
        $c = bw_clave_olt($r['olt']);
        if (isset($layout[$c])) {
            $layout[$c]['cap'] = (int)$r['capacidad'];
        }
    }
    return $layout;
}

function bw_anual_ultima_fecha($mysqli, $anio)
{
    $t = BW_TABLA_ANUAL;
    $r = bw_anual_q($mysqli, "SELECT MAX(fecha) FROM `$t` WHERE anio = " . (int)$anio)->fetch_row();
    return $r[0];
}

// Inserta filas en lotes. $filas: array de arrays con las columnas de la tabla
function bw_anual_insertar($mysqli, $filas)
{
    $t = BW_TABLA_ANUAL;
    $cols = '(anio, semana, fecha, orden, grupo, pe_com, olt, trafico, capacidad, cap_pe, alta, actualizado)';
    foreach (array_chunk($filas, 500) as $lote) {
        $vals = array();
        foreach ($lote as $f) {
            $vals[] = '(' . (int)$f['anio'] . ', ' . (int)$f['semana'] . ', ' . bw_anual_esc($mysqli, $f['fecha']) . ', '
                . (int)$f['orden'] . ', ' . (int)$f['grupo'] . ', ' . bw_anual_esc($mysqli, $f['pe_com']) . ', '
                . bw_anual_esc($mysqli, $f['olt']) . ', ' . bw_anual_num($f['trafico']) . ', ' . bw_anual_num($f['capacidad']) . ', '
                . bw_anual_num($f['cap_pe']) . ', ' . ($f['alta'] ? bw_anual_esc($mysqli, $f['alta']) : 'NULL') . ', NOW())';
        }
        bw_anual_q($mysqli, "INSERT INTO `$t` $cols VALUES " . implode(', ', $vals));
    }
}

/**
 * Cron: agrega la semana $semana del año $anio a OLT_REPORTE_BW_ANUAL.
 * $opc: forzar (regenera la semana), simular (todo en una transacción que se deshace al final),
 *       datos (.json de prueba en vez de la BD de tráfico)
 */
function bw_anual_generar_semana($anio, $semana, $opc = array())
{
    $anio = (int)$anio;
    $semana = (int)$semana;
    if ($semana < 1 || $semana > bw_semanas_del_anio($anio)) {
        throw new Exception("La semana $semana no existe en $anio");
    }
    $forzar = !empty($opc['forzar']);
    $simular = !empty($opc['simular']);
    $t = BW_TABLA_ANUAL;
    $mysqli = bw_conectar_bd();

    $diaIni = ($semana - 1) * 7;
    $diaFin = $diaIni + 6;
    $fIni = bw_fecha_dia($anio, $diaIni);
    $fFin = bw_fecha_dia($anio, $diaFin);
    $existe = bw_anual_q($mysqli, "SELECT COUNT(*) FROM `$t` WHERE anio = $anio AND semana = $semana")->fetch_row();
    if ($existe[0] > 0 && !$forzar) {
        bw_log("W-$semana $anio ya está en $t, se omite. Use --forzar para regenerarla.");
        $mysqli->close();
        return array('estado' => 'existe');
    }

    // ---------- estructura del año y primer día a escribir ----------
    $layout = bw_anual_layout($mysqli, $anio);
    if (count($layout) == 0) {
        // Año nuevo: misma estructura del año anterior, sin datos
        $layout = bw_anual_layout($mysqli, $anio - 1);
        if (count($layout) == 0) {
            throw new Exception("No hay datos de $anio ni de " . ($anio - 1) . " en $t (falta la carga inicial)");
        }
        foreach ($layout as $c => $o) {
            $layout[$c]['alta'] = null;
        }
        $k0 = 0;
        bw_log("Año nuevo $anio: estructura copiada de " . ($anio - 1) . ' (' . count($layout) . ' OLTs)');
    } else {
        $ultima = bw_anual_ultima_fecha($mysqli, $anio);
        $kSig = bw_dias_entre(bw_ts_lunes_semana1($anio), strtotime($ultima . ' 12:00:00')) + 1;
        $k0 = min($kSig, $diaIni);   // si faltan semanas anteriores, también se completan
    }
    $desde = bw_fecha_dia($anio, $k0);
    bw_log("Generando W-$semana $anio ($fIni a $fFin) en $t, días desde $desde" . ($simular ? ' [SIMULACIÓN]' : ''));

    // ---------- datos de tráfico y capacidad ----------
    if (!empty($opc['datos'])) {
        list($olts, $trafico) = bw_datos_desde_json($opc['datos']);
        $primeros = array();
        foreach ($trafico as $ip => $dias) {
            $fs = array_keys($dias);
            sort($fs);
            $primeros[$ip] = $fs[0];
        }
    } else {
        $olts = bw_obtener_olts($mysqli);
        $trafico = bw_obtener_trafico($mysqli, $desde, $fFin);
        $primeros = bw_obtener_primer_registro($mysqli, array_keys($trafico));
    }

    $mysqli->autocommit(false);
    try {
        if ($forzar) {
            bw_anual_q($mysqli, "DELETE FROM `$t` WHERE anio = $anio AND fecha >= " . bw_anual_esc($mysqli, $desde));
        }

        // ---------- OLTs nuevas (con tráfico en la semana y sin fila en el reporte) ----------
        $nuevas = array();
        foreach ($olts as $clave => $o) {
            if (isset($layout[$clave]) || $o['cap'] === null || bw_olt_excluida($o['olt'])) {
                continue;
            }
            for ($k = $diaIni; $k <= $diaFin; $k++) {
                if (isset($trafico[$o['ip']][bw_fecha_dia($anio, $k)])) {
                    $nuevas[] = $o;
                    break;
                }
            }
        }
        $gruposNuevos = array();
        foreach ($nuevas as $o) {
            // Grupo de otra OLT del reporte con el mismo PE-com (pe_id)
            $grupo = null;
            $pos = 0;
            $maxOrden = 0;
            $maxGrupo = 0;
            foreach ($layout as $c => $l) {
                $maxOrden = max($maxOrden, $l['orden']);
                $maxGrupo = max($maxGrupo, $l['grupo']);
                if (isset($olts[$c]) && $olts[$c]['pe_id'] == $o['pe_id']) {
                    $grupo = $l['grupo'];
                }
            }
            if ($grupo !== null) {
                foreach ($layout as $l) {
                    if ($l['grupo'] == $grupo) {
                        $pos = max($pos, $l['orden'] + 1);
                        $pe = $l['pe_com'];
                        $capPe = $l['cap_pe'];
                    }
                }
            } else {
                $grupo = $maxGrupo + 1;
                $pos = $maxOrden + 1;
                $pe = $o['pe_com'];
                $capPe = null;
                $gruposNuevos[$grupo] = true;
            }
            // Correr hacia abajo las OLTs desde la posición y los grupos posteriores
            bw_anual_q($mysqli, "UPDATE `$t` SET orden = orden + 1 WHERE anio = $anio AND orden >= $pos");
            foreach ($layout as $c => $l) {
                if ($l['orden'] >= $pos) {
                    $layout[$c]['orden']++;
                }
            }
            $clave = bw_clave_olt($o['olt']);
            $layout[$clave] = array('olt' => $o['olt'], 'orden' => $pos, 'grupo' => $grupo, 'pe_com' => $pe,
                'cap_pe' => $capPe, 'alta' => $fIni, 'cap' => $o['cap']);
            // Días anteriores ya existentes del año: tráfico 0 con la capacidad obtenida esta semana
            $previos = array();
            for ($k = 0; $k < $k0; $k++) {
                $previos[] = array('anio' => $anio, 'semana' => (int)floor($k / 7) + 1, 'fecha' => bw_fecha_dia($anio, $k),
                    'orden' => $pos, 'grupo' => $grupo, 'pe_com' => $pe, 'olt' => $o['olt'], 'trafico' => 0,
                    'capacidad' => $o['cap'], 'cap_pe' => $capPe, 'alta' => $fIni);
            }
            bw_anual_insertar($mysqli, $previos);
            bw_log("OLT nueva: $o[olt] ($pe) en posición $pos" . (isset($gruposNuevos[$grupo]) ? ' (PE-com nuevo)' : ''));
        }
        uasort($layout, function ($a, $b) {
            return $a['orden'] - $b['orden'];
        });

        // ---------- días nuevos ----------
        $filas = array();
        $capDomingo = array();   // grupo => suma de capacidades al domingo (para PE-com nuevos)
        for ($k = $k0; $k <= $diaFin; $k++) {
            $fecha = bw_fecha_dia($anio, $k);
            foreach ($layout as $clave => $l) {
                $o = isset($olts[$clave]) ? $olts[$clave] : null;
                $traf = null;
                if ($o && isset($trafico[$o['ip']][$fecha])) {
                    $traf = round($trafico[$o['ip']][$fecha], 0);
                } elseif ($o && isset($primeros[$o['ip']]) && $fecha < $primeros[$o['ip']]) {
                    $traf = 0;   // la OLT aún no existía
                }
                $cap = ($o && $o['cap'] !== null && $o['cap'] > 0) ? $o['cap'] : $l['cap'];
                if ($k == $diaFin && $cap) {
                    $capDomingo[$l['grupo']] = (isset($capDomingo[$l['grupo']]) ? $capDomingo[$l['grupo']] : 0) + $cap;
                }
                $filas[] = array('anio' => $anio, 'semana' => (int)floor($k / 7) + 1, 'fecha' => $fecha,
                    'orden' => $l['orden'], 'grupo' => $l['grupo'], 'pe_com' => $l['pe_com'], 'olt' => $l['olt'],
                    'trafico' => $traf, 'capacidad' => $cap, 'cap_pe' => $l['cap_pe'], 'alta' => $l['alta']);
            }
        }
        bw_anual_insertar($mysqli, $filas);

        // ---------- columna C de PE-com nuevos o sin valor ----------
        $gruposSinC = array();
        foreach ($layout as $l) {
            if ($l['cap_pe'] === null || $l['cap_pe'] <= 0) {
                $gruposSinC[$l['grupo']] = true;
            }
        }
        foreach (array_keys($gruposSinC) as $g) {
            if (!empty($capDomingo[$g])) {
                bw_anual_q($mysqli, "UPDATE `$t` SET cap_pe = " . (int)$capDomingo[$g] . " WHERE anio = $anio AND grupo = " . (int)$g);
                bw_log("Columna C del grupo $g calculada: " . (int)$capDomingo[$g]);
            }
        }

        if ($simular) {
            $mysqli->rollback();
        } else {
            $mysqli->commit();
        }
    } catch (Exception $e) {
        $mysqli->rollback();
        $mysqli->autocommit(true);
        $mysqli->close();
        throw $e;
    }
    $mysqli->autocommit(true);
    $mysqli->close();
    bw_log("OK W-$semana $anio: " . count($filas) . ' filas (' . count($layout) . ' OLTs), ' . count($nuevas) . ' OLTs nuevas'
        . ($simular ? ' [SIMULACIÓN: no se guardó]' : ''));
    return array('estado' => 'ok', 'filas' => count($filas), 'nuevas' => count($nuevas));
}

// ======================================================================
// Consultas para la ventana web
// ======================================================================

// Semanas completas (7 días) disponibles: array de array(anio, semana), más reciente primero
function bw_anual_semanas($mysqli)
{
    $t = BW_TABLA_ANUAL;
    $res = bw_anual_q($mysqli, "SELECT anio, semana FROM `$t` GROUP BY anio, semana
                                HAVING COUNT(DISTINCT fecha) = 7 ORDER BY anio DESC, semana DESC");
    $lista = array();
    while ($r = $res->fetch_row()) {
        $lista[] = array((int)$r[0], (int)$r[1]);
    }
    return $lista;
}

// Alertas de la semana, calculadas desde la tabla
function bw_anual_alertas($mysqli, $anio, $semana)
{
    $t = BW_TABLA_ANUAL;
    $anio = (int)$anio;
    $semana = (int)$semana;
    $lunes1 = bw_ts_lunes_semana1($anio);
    $fIni = bw_fecha_dia($anio, ($semana - 1) * 7);
    $fFin = bw_fecha_dia($anio, ($semana - 1) * 7 + 6);
    $fPrev = bw_fecha_dia($anio, ($semana - 1) * 7 - 1);
    $al = array('uplink' => array(), 'sin_registro' => array(), 'olt_nueva' => array(), 'capacidad' => array());

    $res = bw_anual_q($mysqli, "SELECT fecha, pe_com, olt, orden, trafico, capacidad FROM `$t`
                                WHERE anio = $anio AND semana = $semana AND capacidad > 0
                                  AND trafico * 100 / capacidad >= " . (float)BW_UMBRAL_ALERTA . " ORDER BY fecha, orden");
    while ($r = $res->fetch_assoc()) {
        $k = bw_dias_entre($lunes1, strtotime($r['fecha'] . ' 12:00:00'));
        $al['uplink'][] = array('fecha' => $r['fecha'], 'dia' => bw_etiqueta_fecha($r['fecha']), 'pe_com' => $r['pe_com'],
            'olt' => $r['olt'], 'trafico' => (int)$r['trafico'], 'capacidad' => (int)$r['capacidad'],
            'porcentaje' => round($r['trafico'] * 100 / $r['capacidad'], 2),
            'celda' => bw_anual_col_letra(5 + 3 * $k + 2) . ($r['orden'] + 2));
    }

    $res = bw_anual_q($mysqli, "SELECT olt, MAX(pe_com) AS pe_com, MAX(orden) AS orden, GROUP_CONCAT(fecha ORDER BY fecha) AS fechas
                                FROM `$t` WHERE anio = $anio AND semana = $semana AND trafico IS NULL
                                GROUP BY olt ORDER BY orden");
    while ($r = $res->fetch_assoc()) {
        $fechas = explode(',', $r['fechas']);
        $dias = array();
        foreach ($fechas as $f) {
            $dias[] = bw_etiqueta_fecha($f);
        }
        $al['sin_registro'][] = array('olt' => $r['olt'], 'pe_com' => $r['pe_com'], 'fila' => $r['orden'] + 2,
            'detalle' => count($fechas) == 7 ? 'Sin registros toda la semana' : 'Sin registro: ' . implode(', ', $dias));
    }

    $res = bw_anual_q($mysqli, "SELECT olt, MAX(pe_com) AS pe_com, MAX(orden) AS orden FROM `$t`
                                WHERE anio = $anio AND alta = " . bw_anual_esc($mysqli, $fIni) . " GROUP BY olt ORDER BY orden");
    while ($r = $res->fetch_assoc()) {
        $al['olt_nueva'][] = array('olt' => $r['olt'], 'pe_com' => $r['pe_com'], 'fila' => $r['orden'] + 2,
            'detalle' => 'OLT agregada al reporte en la fila ' . ($r['orden'] + 2));
    }

    // Capacidad del domingo distinta a la del domingo anterior (sin contar OLTs recién agregadas)
    $res = bw_anual_q($mysqli, "SELECT a.olt, a.pe_com, a.orden, p.capacidad AS anterior, a.capacidad AS nueva
                                FROM `$t` a INNER JOIN `$t` p ON p.olt = a.olt AND p.fecha = " . bw_anual_esc($mysqli, $fPrev) . "
                                WHERE a.anio = $anio AND a.fecha = " . bw_anual_esc($mysqli, $fFin) . "
                                  AND a.capacidad IS NOT NULL AND p.capacidad IS NOT NULL AND p.capacidad > 0
                                  AND a.capacidad <> p.capacidad
                                  AND (a.alta IS NULL OR a.alta <> " . bw_anual_esc($mysqli, $fIni) . ")
                                ORDER BY a.orden");
    while ($r = $res->fetch_assoc()) {
        $al['capacidad'][] = array('olt' => $r['olt'], 'pe_com' => $r['pe_com'], 'fila' => $r['orden'] + 2,
            'anterior' => (int)$r['anterior'], 'nueva' => (int)$r['nueva']);
    }
    return $al;
}

function bw_anual_col_letra($n)
{
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = (int)(($n - $m - 1) / 26);
    }
    return $s;
}

/**
 * Datos para armar el Excel del año $anio hasta el domingo de la semana $semana.
 * Retorna array('fechas' => array(k => 'Y-m-d'), 'grupos' => array(array('pe','b','c','olts' => array(nombres))),
 *               'valores' => array(fila_excel => array(fecha => array(trafico, capacidad))))
 * B = promedio diario (días con datos) de la suma del tráfico del grupo en la semana pedida.
 */
function bw_anual_datos_excel($mysqli, $anio, $semana)
{
    $t = BW_TABLA_ANUAL;
    $anio = (int)$anio;
    $semana = (int)$semana;
    $diaFin = ($semana - 1) * 7 + 6;
    $fFin = bw_fecha_dia($anio, $diaFin);
    $fechas = array();
    for ($k = 0; $k <= $diaFin; $k++) {
        $fechas[$k] = bw_fecha_dia($anio, $k);
    }
    $res = bw_anual_q($mysqli, "SELECT olt, MAX(orden) AS orden, MAX(grupo) AS grupo, MAX(pe_com) AS pe_com, MAX(cap_pe) AS cap_pe
                                FROM `$t` WHERE anio = $anio AND fecha <= " . bw_anual_esc($mysqli, $fFin) . "
                                GROUP BY olt ORDER BY orden");
    $grupos = array();
    $filaDe = array();
    $grupoDeFila = array();
    $fila = 3;
    $ultimoGrupo = null;
    while ($r = $res->fetch_assoc()) {
        if ($r['grupo'] !== $ultimoGrupo) {
            $grupos[] = array('pe' => $r['pe_com'], 'b' => null, 'c' => $r['cap_pe'] === null ? null : (float)$r['cap_pe'],
                'olts' => array());
            $ultimoGrupo = $r['grupo'];
        }
        $grupos[count($grupos) - 1]['olts'][] = $r['olt'];
        $filaDe[$r['olt']] = $fila;
        $grupoDeFila[$fila] = count($grupos) - 1;
        $fila++;
    }

    $valores = array();
    foreach ($filaDe as $olt => $f) {
        $valores[$f] = array();
    }
    $res = bw_anual_q($mysqli, "SELECT olt, fecha, trafico, capacidad FROM `$t`
                                WHERE anio = $anio AND fecha <= " . bw_anual_esc($mysqli, $fFin));
    while ($r = $res->fetch_row()) {
        $valores[$filaDe[$r[0]]][$r[1]] = array($r[2] === null ? null : (float)$r[2], $r[3] === null ? null : (float)$r[3]);
    }

    // Columna B: promedio de la semana pedida
    $sumas = array();
    for ($k = $diaFin - 6; $k <= $diaFin; $k++) {
        $fe = $fechas[$k];
        foreach ($valores as $f => $dias) {
            if (isset($dias[$fe]) && $dias[$fe][0] !== null) {
                $g = $grupoDeFila[$f];
                $sumas[$g][$fe] = (isset($sumas[$g][$fe]) ? $sumas[$g][$fe] : 0) + $dias[$fe][0];
            }
        }
    }
    foreach ($sumas as $g => $porDia) {
        $grupos[$g]['b'] = (float)round(array_sum($porDia) / count($porDia), 0);
    }
    return array('fechas' => $fechas, 'grupos' => $grupos, 'valores' => $valores);
}
