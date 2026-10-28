<?php
/**
 * Etapa 2 (servidor de cron, solo BD): carga de capacidades BW en OLT_CAPACIDAD_BW, _MES y _YEAR
 * (y sus hermanas _EQUIFIBER y _ONNET). No lee ni genera Excel.
 *
 * Mismo formato que dejaba model/carga_excel*.php: cada columna del Excel es una fila de
 * la tabla y sus celdas 1..162 van en los campos `1`..`162`:
 *   A: PE-com | B: Tráfico PE (promedio del período) | C: Capacidad PE | D: OLT
 *   desde E, por día: Tráfico (fila 2 = "Lunes 12 de Octubre"), Capacidad, % Uplink (fracción)
 *
 * Origen de los datos:
 *   Nacional: OLT_REPORTE_BW_ANUAL (etapa 1), con su orden de filas, grupos y columna C (cap_pe).
 *             Los días del período que aún no estén en la tabla (ej: carga del mes el día 1, antes de que
 *             la etapa 1 cierre la semana) se completan desde OLT_TRAFICO_PEAK_DAY con las mismas reglas.
 *   EQUIFIBER / ONNET: OLT_TRAFICO_PEAK_DAY, con las reglas de sus reportes (OLT_SERVER_V2 / lista ONNET).
 *
 * Compatible con PHP 5.3+ y 8.0 (121 procesos: 8.0, 120 contenedor v3: 5.5, 181 contingencia: 5.3).
 */
require_once __DIR__ . '/bw_anual.php';

// Cantidad de filas del Excel que caben en las tablas OLT_CAPACIDAD_BW* (campos `1`..`162`)
define('BW_FILAS_TABLA', 162);

/**
 * OLTs de ONNET (misma lista que OLT/Reporte_BW/reporte_bw_fecha_peak_capacidad_onnet.php).
 * Para agregar o quitar una OLT de ONNET basta con editar esta lista.
 */
function bw_olts_onnet()
{
    return array(
        'OLT-QUILICURA-1', 'OLT-HUECHURABA-2', 'OLT-ZOFRI-1', 'OLT-PUERTOVARAS-1', 'OLT-IQUIQUE-2',
        'OLT-CALLEJONDELOSPERROS2PCS-1', 'OLT-CALLELIRCAYPCS-1', 'OLT-LOSANGELES-1', 'OLT-OSORNO-1',
        'OLT-ANFIONMUNOZ-1', 'OLT-CARDONAL-1', 'OLT-CLUBHIPICO-1', 'OLT-LAUTARO-1', 'OLT-NVASANANTONIO-1',
        'OLT-SANTAJUANAPCS-1', 'OLT-VALDIVIA-1', 'OLT-COPIAPO-1', 'OLT-CURICO-1', 'OLT-MALLTOBALABAPCS-1',
        'OLT-3PONIENTEPCS-1', 'OLT-LASPERDICESPCS-1', 'OLT-ROJASMAGALLANESPCS-1', 'OLT-NUNOA-3',
        'OLT-LOSJARDINESPCS-1', 'OLT-MCCDLVALLEPCS-1', 'OLT-RECREO-3', 'OLT-NVAARLEGUI-1', 'OLT-NVA13NORTE-3',
        'OLT-LAFLORIDA-2', 'OLT-GRANAVENIDAPCS-2', 'OLT-TEMPLOVOTIVOPCS-2', 'OLT-AVPDTEBATLLEPCS-2',
        'OLT-GRANAVENIDAPCS-1', 'OLT-QUILICURA2PCS-1', 'OLT-SANJOSEDELAESTRELLAPCS-1', 'OLT-MAIPU-3',
        'OLT-ROJASMAGALLANESPCS-2', 'OLT-CHICUREO3PCS-1', 'OLT-LOSQUILLAYESPCS-1', 'OLT-QUILICURA-2',
        'OLT-RENACANORTEPCS-1', 'OLT-GRALBONILLAPCS-2', 'OLT-TEMPLOVOTIVOPCS-3', 'OLT-CVELBOSQUEPCS-1',
        'OLT-LAFARFANAPCS-2', 'OLT-QUILPUEPCS-1', 'OLT-ELENCUENTROPCS-2', 'OLT-DRRENEANZIETAPCS-2',
        'OLT-VILLAALEMANA-1', 'OLT-VILLAALEMANA-2', 'OLT-QUILPUEPCS-2', 'OLT-QUILPUEPCS-3', 'OLT-TEMUCO-2',
        'OLT-NVACURAUMA-1', 'OLT-NUNOA-4', 'OLT-CLAMONTANAPCS-1', 'OLT-ANTOFAGASTA-2', 'OLT-CHILLAN-2',
        'OLT-LOSJARDINESPCS-2', 'OLT-GRANAVENIDAPCS-3', 'OLT-COLON-2', 'OLT-PUENTEALTO-2', 'OLT-LASERENA-1',
        'OLT-SANTAELENA-2', 'OLT-SANTAELENA-3', 'OLT-CONCEPCION-3', 'OLT-CONCEPCION-4', 'OLT-BOMBEROSCOLLAO-1',
        'OLT-HERNANCIUDADPCS-2', 'OLT-HERNANCIUDADPCS-3', 'OLT-RANCAGUA-3', 'OLT-RANCAGUA-4', 'OLT-COQUIMBO-2',
        'OLT-ALTOPENUELAS-2', 'OLT-ESTRELLADELSUR-1', 'OLT-PADREHURTADOPCS-1', 'OLT-GRALBONILLAPCS-3',
        'OLT-CINCOPINOSPCS-1', 'OLT-TEMUCOPONIENTEPCS-1', 'OLT-CVELBOSQUEPCS-2', 'OLT-TALCASWPCS-1',
        'OLT-TALCASWPCS-2', 'OLT-NUNOA-5', 'OLT-AVPDTEBATLLEPCS-3', 'OLT-VALPARAISO-2', 'OLT-AVDELMARPCS-1',
        'OLT-AVDELMARPCS-2', 'OLT-SSBERNARDOPCS-1', 'OLT-LCARRERA-3', 'OLT-BUIN-1', 'OLT-INDEPENDENCIA-1',
        'OLT-NVATALCAHUANO-1', 'OLT-LOSLIRIOSPCS-1', 'OLT-LOSLIRIOSPCS-2', 'OLT-TOME2PCS-2', 'OLT-AVDACOLONPCS-1',
        'OLT-PENCOPCS-2', 'OLT-TALCAORIENTEPCS-1', 'OLT-COQUIMBO-3', 'OLT-SANPEDRO3PCS-2', 'OLT-NVA13NORTE-4',
        'OLT-PAINE-1', 'OLT-AORIENTALNORPCS-1', 'OLT-LAFARFANAPCS-3', 'OLT-IQUIQUE-1',
    );
}

/**
 * Tablas destino y origen de OLTs por red, con el formato histórico de cada una:
 *   pe_sin_enie: PE-com sin Ñ (ej: NUNOA, ANFIOMUNOZ), como en las cargas manuales de EQUIFIBER y ONNET
 *   dia_2_digitos: día con 2 dígitos en la fila 2 (ej: "Lunes 05 de Octubre"), como en EQUIFIBER y ONNET
 *   dias_doble_espacio: días de la semana (1 = lunes ... 7 = domingo) con 2 espacios entre nombre y número,
 *                       como en EQUIFIBER ("Jueves  08 de Octubre", "Sábado  10 ...", "Domingo  11 ...")
 *   dias_doble_espacio_despues: días con 2 espacios entre el número y "de", como en ONNET ("Domingo 11  de Octubre")
 */
function bw_redes()
{
    return array(
        'nacional'  => array('nombre' => 'Nacional', 'sufijo' => '', 'tabla_server' => 'OLT_SERVER',
            'pe_sin_enie' => false, 'dia_2_digitos' => false, 'dias_doble_espacio' => array(),
            'dias_doble_espacio_despues' => array()),
        'equifiber' => array('nombre' => 'EQUIFIBER', 'sufijo' => '_EQUIFIBER', 'tabla_server' => 'OLT_SERVER_V2',
            'pe_sin_enie' => true, 'dia_2_digitos' => true, 'dias_doble_espacio' => array(4, 6, 7),
            'dias_doble_espacio_despues' => array()),
        'onnet'     => array('nombre' => 'ONNET', 'sufijo' => '_ONNET', 'tabla_server' => 'OLT_SERVER',
            'pe_sin_enie' => true, 'dia_2_digitos' => true, 'dias_doble_espacio' => array(),
            'dias_doble_espacio_despues' => array(7)),
    );
}

// ---------- períodos ----------

/**
 * $tipo: 'semana' (anio + semana del reporte), 'mes' (anio + mes calendario) o 'anio' (año del reporte)
 */
function bw_periodo($tipo, $anio, $num = null)
{
    $anio = (int)$anio;
    $fechas = array();
    if ($tipo == 'semana') {
        $num = (int)$num;
        if ($num < 1 || $num > bw_semanas_del_anio($anio)) {
            throw new Exception("La semana $num no existe en $anio");
        }
        for ($k = ($num - 1) * 7; $k < $num * 7; $k++) {
            $fechas[] = bw_fecha_dia($anio, $k);
        }
        $claves = array('week' => (string)$num, 'year' => (string)$anio);
        $nombre = "semana W-$num $anio";
    } elseif ($tipo == 'mes') {
        $num = (int)$num;
        if ($num < 1 || $num > 12) {
            throw new Exception("Mes inválido: $num");
        }
        $dias = (int)date('t', mktime(12, 0, 0, $num, 1, $anio));
        for ($d = 1; $d <= $dias; $d++) {
            $fechas[] = date('Y-m-d', mktime(12, 0, 0, $num, $d, $anio));
        }
        $claves = array('mes' => sprintf('%02d', $num), 'year' => (string)$anio);
        $nombre = 'mes ' . bw_nombre_mes($num) . " $anio";
    } elseif ($tipo == 'anio') {
        for ($k = 0; $k < bw_semanas_del_anio($anio) * 7; $k++) {
            $fechas[] = bw_fecha_dia($anio, $k);
        }
        $claves = array('year' => (string)$anio);
        $nombre = "año $anio";
    } else {
        throw new Exception("Tipo de carga inválido: $tipo");
    }
    return array('tipo' => $tipo, 'anio' => $anio, 'num' => $num, 'fechas' => $fechas, 'claves' => $claves, 'nombre' => $nombre);
}

function bw_tabla_destino($tipo, $red)
{
    $redes = bw_redes();
    $base = array('semana' => 'OLT_CAPACIDAD_BW', 'mes' => 'OLT_CAPACIDAD_BW_MES', 'anio' => 'OLT_CAPACIDAD_BW_YEAR');
    return $base[$tipo] . $redes[$red]['sufijo'];
}

// ---------- grilla (filas de OLT con sus valores por fecha) ----------
// array('filas' => array(array('pe','olt','grupo','c_pe','valores' => array(fecha => array(traf, cap)))))

/**
 * Nacional desde OLT_REPORTE_BW_ANUAL. Orden de filas, grupos y C del año del reporte del último día del
 * período (solo OLTs ya agregadas a esa fecha). Retorna array(grilla, fechas que faltan en la tabla).
 */
function bw_grilla_desde_anual($mysqli, $fechas)
{
    $t = BW_TABLA_ANUAL;
    $desde = $fechas[0];
    $hasta = end($fechas);
    $anioRep = bw_semana_de_fecha(strtotime($hasta . ' 12:00:00'));
    $anioRep = (int)$anioRep[0];
    $filas = array();
    $indice = array();
    // Si el año del último día aún no tiene filas (ej: diciembre cargado el 1 de enero, con 28 al 31 ya en
    // la W-1 del año siguiente), se usa la estructura del año anterior
    for ($a = $anioRep; $a >= $anioRep - 1 && count($filas) == 0; $a--) {
        $res = bw_anual_q($mysqli, "SELECT olt, MAX(orden) AS orden, MAX(grupo) AS grupo, MAX(pe_com) AS pe_com, MAX(cap_pe) AS cap_pe
                                    FROM `$t` WHERE anio = $a AND (alta IS NULL OR alta <= " . bw_anual_esc($mysqli, $hasta) . ")
                                    GROUP BY olt ORDER BY orden");
        while ($r = $res->fetch_assoc()) {
            $indice[bw_clave_olt($r['olt'])] = count($filas);
            $filas[] = array('pe' => $r['pe_com'], 'olt' => $r['olt'], 'grupo' => (int)$r['grupo'],
                'c_pe' => $r['cap_pe'] === null ? null : (float)$r['cap_pe'], 'valores' => array());
        }
    }

    // Valores del período (un día pertenece a un solo año de reporte, así que se busca solo por fecha)
    $presentes = array();
    $res = bw_anual_q($mysqli, "SELECT olt, fecha, trafico, capacidad FROM `$t`
                                WHERE fecha BETWEEN " . bw_anual_esc($mysqli, $desde) . ' AND ' . bw_anual_esc($mysqli, $hasta));
    while ($r = $res->fetch_row()) {
        $presentes[$r[1]] = true;
        $c = bw_clave_olt($r[0]);
        if (isset($indice[$c])) {
            $filas[$indice[$c]]['valores'][$r[1]] = array($r[2] === null ? null : (float)$r[2], $r[3] === null ? null : (float)$r[3]);
        }
    }
    $faltan = array();
    foreach ($fechas as $f) {
        if (!isset($presentes[$f])) {
            $faltan[] = $f;
        }
    }
    foreach ($filas as $i => $fila) {
        foreach ($fechas as $f) {
            if (!isset($fila['valores'][$f])) {
                $filas[$i]['valores'][$f] = array(null, null);
            }
        }
    }
    return array(array('filas' => $filas), $faltan);
}

/**
 * Completa en la grilla de Nacional los días que faltan en la tabla anual, desde OLT_TRAFICO_PEAK_DAY
 * (mismas reglas de la etapa 1). Las OLTs con tráfico que no estén en la grilla van al final.
 */
function bw_completar_desde_bd($grilla, $faltan, $olts, $trafico, $primeros, $fechas)
{
    $enGrilla = array();
    foreach ($grilla['filas'] as $i => $f) {
        $enGrilla[bw_clave_olt($f['olt'])] = $i;
    }
    // Última capacidad conocida en la tabla, por si la BD no la trae
    $capTabla = array();
    foreach ($grilla['filas'] as $i => $f) {
        foreach ($fechas as $fe) {
            if ($f['valores'][$fe][1] !== null && $f['valores'][$fe][1] > 0) {
                $capTabla[$i] = $f['valores'][$fe][1];
            }
        }
    }
    foreach ($grilla['filas'] as $i => $f) {
        $o = isset($olts[bw_clave_olt($f['olt'])]) ? $olts[bw_clave_olt($f['olt'])] : null;
        foreach ($faltan as $fe) {
            $grilla['filas'][$i]['valores'][$fe] = bw_valor_dia($o, $fe, $trafico, $primeros, isset($capTabla[$i]) ? $capTabla[$i] : null);
        }
    }
    $nuevas = array();
    foreach ($olts as $clave => $o) {
        if (isset($enGrilla[$clave])) {
            continue;
        }
        foreach ($faltan as $fe) {
            if (isset($trafico[$o['ip']][$fe])) {
                $nuevas[$clave] = $o;
                break;
            }
        }
    }
    if (count($nuevas) > 0) {
        $extra = bw_grilla_desde_bd($nuevas, $trafico, $fechas, $primeros);
        $base = count($grilla['filas']) > 0 ? max(array_map(function ($f) { return $f['grupo']; }, $grilla['filas'])) + 1 : 0;
        foreach ($extra['filas'] as $f) {
            $f['grupo'] += $base;
            $grilla['filas'][] = $f;
        }
    }
    return $grilla;
}

// Tráfico y capacidad de un día desde la BD: sin registro = vacío; antes del primer registro histórico = 0
function bw_valor_dia($o, $fecha, $trafico, $primeros, $capDefecto = null)
{
    if ($o === null) {
        return array(null, $capDefecto);
    }
    $t = isset($trafico[$o['ip']][$fecha]) ? round($trafico[$o['ip']][$fecha], 0) : null;
    if ($t === null && isset($primeros[$o['ip']]) && $fecha < $primeros[$o['ip']]) {
        $t = 0;
    }
    return array($t, $o['cap'] !== null ? $o['cap'] : $capDefecto);
}

function bw_ordenar_olts($a, $b)
{
    if ($a['seccion'] != $b['seccion']) {
        return $a['seccion'] - $b['seccion'];
    }
    if ($a['pe_id'] != $b['pe_id']) {
        return $a['pe_id'] - $b['pe_id'];
    }
    if ($a['cap_gb'] != $b['cap_gb']) {
        return $b['cap_gb'] - $a['cap_gb'];
    }
    return strcmp($a['olt'], $b['olt']);
}

/**
 * Grilla desde la BD (EQUIFIBER / ONNET y OLTs nuevas de Nacional): OLTs con tráfico en el período, como en
 * sus reportes: primero las de 10G o más y luego las de 1G, cada bloque por PE-com (id) y capacidad descendente.
 */
function bw_grilla_desde_bd($olts, $trafico, $fechas, $primeros)
{
    $resto = array();
    foreach ($olts as $o) {
        foreach ($fechas as $f) {
            if (isset($trafico[$o['ip']][$f])) {
                $o['seccion'] = ($o['cap_gb'] !== null && $o['cap_gb'] >= 10) ? 0 : 1;
                $o['cap_gb'] = (int)$o['cap_gb'];
                $resto[] = $o;
                break;
            }
        }
    }
    usort($resto, 'bw_ordenar_olts');
    $filas = array();
    $ultimo = null;
    $grupo = -1;
    foreach ($resto as $o) {
        $id = $o['seccion'] . '|' . $o['pe_id'];
        if ($id !== $ultimo) {
            $grupo++;
            $ultimo = $id;
        }
        $valores = array();
        foreach ($fechas as $f) {
            $valores[$f] = bw_valor_dia($o, $f, $trafico, $primeros);
        }
        $filas[] = array('pe' => $o['pe_com'], 'olt' => $o['olt'], 'grupo' => $grupo, 'c_pe' => null, 'valores' => $valores);
    }
    return array('filas' => $filas);
}

// ---------- matriz (columnas del Excel => filas de la tabla) ----------

// Número como texto, igual que lo guardaba la carga desde Excel ("25452", "0.873456")
function bw_txt($v)
{
    if ($v === null || $v === '') {
        return '';
    }
    if (!is_numeric($v)) {
        return (string)$v;
    }
    $n = (float)$v;
    if (floor($n) == $n && abs($n) < 1e15) {
        return sprintf('%.0F', $n);
    }
    $s = rtrim(rtrim(sprintf('%.15F', $n), '0'), '.');
    return $s === '' || $s === '-' ? '0' : $s;
}

/**
 * Retorna array de columnas; cada columna es array(1..BW_FILAS_TABLA => texto)
 */
function bw_matriz_tabla($grilla, $fechas, &$avisos, $red = 'nacional')
{
    $redes = bw_redes();
    $formato = $redes[$red];
    $filas = $grilla['filas'];
    $max = BW_FILAS_TABLA - 2;
    if (count($filas) > $max) {
        $avisos[] = 'Hay ' . count($filas) . " OLTs y la tabla admite $max: se omiten las últimas";
        $filas = array_slice($filas, 0, $max);
    }
    $vacia = array();
    for ($r = 1; $r <= BW_FILAS_TABLA; $r++) {
        $vacia[$r] = '';
    }
    $colA = $colB = $colC = $colD = $vacia;
    $colA[1] = 'PE-com';
    $colB[1] = 'Tráfico (Gbps)';
    $colC[1] = 'Capacidad (Gbps)';
    $colD[1] = 'OLT';
    $ultimaFecha = end($fechas);

    // Totales por grupo de PE: B = promedio diario del tráfico del PE, C = la del reporte o suma de sus OLTs
    $grupos = array();
    foreach ($filas as $i => $f) {
        $grupos[$f['grupo']][] = $i;
    }
    foreach ($grupos as $indices) {
        $r0 = 3 + $indices[0];
        $suma = 0;
        $dias = 0;
        foreach ($fechas as $fe) {
            $sumaDia = 0;
            $hay = false;
            foreach ($indices as $i) {
                if ($filas[$i]['valores'][$fe][0] !== null) {
                    $sumaDia += $filas[$i]['valores'][$fe][0];
                    $hay = true;
                }
            }
            if ($hay) {
                $suma += $sumaDia;
                $dias++;
            }
        }
        $cap = 0;
        foreach ($indices as $i) {
            $cap += (float)$filas[$i]['valores'][$ultimaFecha][1];
        }
        $pe = $filas[$indices[0]]['pe'];
        $colA[$r0] = $formato['pe_sin_enie'] ? str_replace(array('Ñ', 'ñ'), array('N', 'n'), $pe) : $pe;
        $colB[$r0] = $dias > 0 ? bw_txt(round($suma / $dias, 0)) : '';
        $cPe = $filas[$indices[0]]['c_pe'];
        if ($cPe !== null && $cPe > 0) {
            $colC[$r0] = bw_txt($cPe);
        } else {
            $colC[$r0] = $cap > 0 ? bw_txt($cap) : '';
        }
    }
    foreach ($filas as $i => $f) {
        $colD[3 + $i] = $f['olt'];
    }

    $columnas = array($colA, $colB, $colC, $colD);
    foreach ($fechas as $fe) {
        $t = $c = $p = $vacia;
        $t[1] = 'Tráfico Gbps';
        $t[2] = bw_etiqueta_fecha($fe, $formato['dia_2_digitos'], $formato['dias_doble_espacio'],
            $formato['dias_doble_espacio_despues']);
        $c[1] = 'Capacidad Gbps';
        $p[1] = '% Uplink';
        foreach ($filas as $i => $f) {
            list($traf, $cap) = $f['valores'][$fe];
            $t[3 + $i] = bw_txt($traf);
            $c[3 + $i] = bw_txt($cap);
            // Igual que la fórmula =+Tráfico/Capacidad del Excel (tráfico vacío cuenta como 0)
            $p[3 + $i] = ($cap !== null && $cap > 0) ? bw_txt(($traf === null ? 0 : $traf) / $cap) : '';
        }
        $columnas[] = $t;
        $columnas[] = $c;
        $columnas[] = $p;
    }
    return $columnas;
}

// ---------- escritura / comparación ----------

function bw_campos_tabla()
{
    $campos = array();
    for ($i = 1; $i <= BW_FILAS_TABLA; $i++) {
        $campos[] = '`' . $i . '`';
    }
    return $campos;
}

function bw_where_claves($mysqli, $claves)
{
    $w = array();
    foreach ($claves as $campo => $valor) {
        $w[] = '`' . $campo . "` = '" . $mysqli->real_escape_string($valor) . "'";
    }
    return implode(' AND ', $w);
}

// Reemplaza el período en la tabla (DELETE + INSERT en una transacción)
function bw_guardar_tabla($mysqli, $tabla, $claves, $columnas)
{
    $campos = bw_campos_tabla();
    foreach ($claves as $campo => $valor) {
        $campos[] = '`' . $campo . '`';
    }
    $campos[] = '`fecha`';
    $mysqli->autocommit(false);
    try {
        if (!$mysqli->query("DELETE FROM `$tabla` WHERE " . bw_where_claves($mysqli, $claves))) {
            throw new Exception("Error al borrar $tabla: " . $mysqli->error);
        }
        $lote = array();
        $total = count($columnas);
        foreach ($columnas as $n => $col) {
            $valores = array();
            foreach ($col as $v) {
                $valores[] = "'" . $mysqli->real_escape_string($v) . "'";
            }
            foreach ($claves as $valor) {
                $valores[] = "'" . $mysqli->real_escape_string($valor) . "'";
            }
            $valores[] = 'NOW()';
            $lote[] = '(' . implode(', ', $valores) . ')';
            if (count($lote) == 50 || $n == $total - 1) {
                $sql = "INSERT INTO `$tabla` (" . implode(', ', $campos) . ') VALUES ' . implode(', ', $lote);
                if (!$mysqli->query($sql)) {
                    throw new Exception("Error al insertar en $tabla: " . $mysqli->error);
                }
                $lote = array();
            }
        }
        $mysqli->commit();
    } catch (Exception $e) {
        $mysqli->rollback();
        $mysqli->autocommit(true);
        throw $e;
    }
    $mysqli->autocommit(true);
}

// Tipo de celda para resumir diferencias
function bw_tipo_celda($i, $r)
{
    $fijas = array('A (PE-com)', 'B (Tráfico PE)', 'C (Capacidad PE)', 'D (OLT)');
    if ($i < 4) {
        return $r <= 2 ? 'encabezados' : $fijas[$i];
    }
    if ($r == 1) {
        return 'encabezados';
    }
    if ($r == 2) {
        return 'fecha (fila 2)';
    }
    $tipos = array('tráfico diario', 'capacidad diaria', '% Uplink');
    return $tipos[($i - 4) % 3];
}

// Compara lo generado con lo que hay en la tabla para el período (no modifica nada)
function bw_comparar_tabla($mysqli, $tabla, $claves, $columnas, $ejemplosPorTipo = 3)
{
    $sql = 'SELECT ' . implode(', ', bw_campos_tabla()) . " FROM `$tabla` WHERE " . bw_where_claves($mysqli, $claves) . ' ORDER BY id';
    $res = $mysqli->query($sql);
    if (!$res) {
        throw new Exception("Error al leer $tabla: " . $mysqli->error);
    }
    $bd = array();
    while ($row = $res->fetch_row()) {
        $bd[] = $row;
    }
    $difs = 0;
    $porTipo = array();   // tipo => array('n' => , 'ejemplos' => array())
    $n = max(count($bd), count($columnas));
    for ($i = 0; $i < $n; $i++) {
        for ($r = 1; $r <= BW_FILAS_TABLA; $r++) {
            $a = isset($bd[$i]) ? (string)$bd[$i][$r - 1] : '(sin fila)';
            $b = isset($columnas[$i]) ? $columnas[$i][$r] : '(sin fila)';
            $iguales = ($a === $b) || (is_numeric($a) && is_numeric($b) && abs($a - $b) < 0.000001);
            if (!$iguales) {
                $difs++;
                $tipo = bw_tipo_celda($i, $r);
                if (!isset($porTipo[$tipo])) {
                    $porTipo[$tipo] = array('n' => 0, 'ejemplos' => array());
                }
                $porTipo[$tipo]['n']++;
                if (count($porTipo[$tipo]['ejemplos']) < $ejemplosPorTipo) {
                    $porTipo[$tipo]['ejemplos'][] = bw_anual_col_letra($i + 1) . "$r BD='$a' generado='$b'";
                }
            }
        }
    }
    $detalle = array();
    foreach ($porTipo as $tipo => $d) {
        $detalle[] = "$tipo: $d[n] distintas (ej: " . implode(' | ', $d['ejemplos']) . ')';
    }
    return array('filas_bd' => count($bd), 'filas_generadas' => count($columnas), 'diferencias' => $difs, 'detalle' => $detalle);
}

function bw_guardar_csv($ruta, $columnas)
{
    $fp = fopen($ruta, 'w');
    foreach ($columnas as $col) {
        fputcsv($fp, array_values($col), ';');
    }
    fclose($fp);
}

// ---------- proceso ----------

/**
 * Carga el período en las tablas de las redes indicadas.
 * $opc: simular (no escribe), comparar (compara con lo existente), csv (carpeta para dejar lo generado),
 *       datos (.json de prueba en vez de OLT_SERVER / OLT_TRAFICO_PEAK_DAY)
 */
function bw_cargar_bd($tipo, $anio, $num, $redes, $opc = array())
{
    $periodo = bw_periodo($tipo, $anio, $num);
    $fechas = $periodo['fechas'];
    $desde = $fechas[0];
    $hasta = end($fechas);
    $modo = !empty($opc['comparar']) ? 'comparar' : (!empty($opc['simular']) ? 'simular' : 'cargar');
    bw_log("[BD] $modo $periodo[nombre] ($desde a $hasta) redes: " . implode(', ', $redes));

    $config = bw_redes();
    $json = null;
    if (!empty($opc['datos'])) {
        $json = json_decode(file_get_contents($opc['datos']), true);
        if (!is_array($json) || !isset($json['olts']) || !isset($json['trafico'])) {
            throw new Exception('Archivo de datos inválido: ' . $opc['datos']);
        }
    }
    $mysqli = bw_conectar_bd();
    $trafico = null;
    $primeros = null;
    $resultado = array();
    foreach ($redes as $red) {
        if (!isset($config[$red])) {
            throw new Exception("Red desconocida: $red");
        }
        $tabla = bw_tabla_destino($tipo, $red);
        $avisos = array();
        $origen = 'BD';
        $faltan = $fechas;
        $grilla = null;

        if ($red == 'nacional') {
            list($grilla, $faltan) = bw_grilla_desde_anual($mysqli, $fechas);
            $origen = BW_TABLA_ANUAL;
            if (count($grilla['filas']) == 0) {
                $faltan = $fechas;
            }
        }
        if (count($faltan) > 0) {
            // Datos desde OLT_TRAFICO_PEAK_DAY (EQUIFIBER / ONNET, o días que aún no están en la tabla anual)
            if ($trafico === null) {
                $trafico = $json !== null ? $json['trafico'] : bw_obtener_trafico($mysqli, $desde, $hasta);
                $primeros = $json !== null ? bw_primeros_desde_json($json) : bw_obtener_primer_registro($mysqli, array_keys($trafico));
            }
            $olts = $json !== null ? bw_olts_desde_json($json, $red)
                : bw_obtener_olts($mysqli, $config[$red]['tabla_server'], $red == 'onnet' ? bw_olts_onnet() : null);
            if ($grilla !== null && count($grilla['filas']) > 0) {
                $grilla = bw_completar_desde_bd($grilla, $faltan, $olts, $trafico, $primeros, $fechas);
                $origen .= ' + OLT_TRAFICO_PEAK_DAY (' . count($faltan) . ' días: ' . $faltan[0] . ' a ' . end($faltan) . ')';
                $avisos[] = count($faltan) . " días del período aún no están en " . BW_TABLA_ANUAL . ', se tomaron de OLT_TRAFICO_PEAK_DAY';
            } else {
                $grilla = bw_grilla_desde_bd($olts, $trafico, $fechas, $primeros);
                $origen = 'OLT_TRAFICO_PEAK_DAY';
            }
        }
        if (count($grilla['filas']) == 0) {
            bw_log('[BD] ' . $config[$red]['nombre'] . ": sin OLTs con datos para $periodo[nombre], no se carga $tabla");
            continue;
        }
        $columnas = bw_matriz_tabla($grilla, $fechas, $avisos, $red);
        foreach ($avisos as $a) {
            bw_log("[BD] ADVERTENCIA $tabla: $a");
        }
        $info = count($grilla['filas']) . ' OLTs, ' . count($columnas) . " filas, origen $origen";

        if (!empty($opc['csv'])) {
            $csv = rtrim($opc['csv'], '/\\') . '/' . $tabla . '_' . implode('_', $periodo['claves']) . '.csv';
            bw_guardar_csv($csv, $columnas);
            $info .= ', csv ' . $csv;
        }
        if ($modo == 'comparar') {
            $c = bw_comparar_tabla($mysqli, $tabla, $periodo['claves'], $columnas);
            bw_log("[BD] $tabla comparación: BD $c[filas_bd] filas, generado $c[filas_generadas] filas, $c[diferencias] celdas distintas ($info)");
            foreach ($c['detalle'] as $d) {
                bw_log("[BD]    $d");
            }
            $resultado[$red] = $c;
        } elseif ($modo == 'simular') {
            bw_log("[BD] $tabla simulación: $info");
            $resultado[$red] = $columnas;
        } else {
            // Portal con carpeta Archivos/ (cron dentro del contenedor del portal): primero se deja el Excel,
            // igual que la subida manual. Si falla se continúa con la BD (la web lo arma desde la tabla).
            if (defined('BW_DIR_ARCHIVOS') && empty($opc['sinexcel'])) {
                try {
                    require_once __DIR__ . '/bw_excel_periodo.php';
                    $archivo = bw_excel_guardar_periodo(BW_DIR_ARCHIVOS, $tipo, $red, $periodo['anio'], $periodo['num'], $columnas);
                    bw_log("[BD] Excel guardado: $archivo");
                } catch (Exception $e) {
                    bw_log("[BD] ERROR al guardar el Excel de $tabla (se continúa con la carga): " . $e->getMessage());
                }
            }
            bw_guardar_tabla($mysqli, $tabla, $periodo['claves'], $columnas);
            bw_log("[BD] $tabla cargada: $info");
            $resultado[$red] = count($columnas);
        }
    }
    $mysqli->close();
    return $resultado;
}

// ---------- datos de prueba ----------
// {"olts": [{olt, ip, pe_id, pe_com, cap[, cap_gb]}], "trafico": {ip: {fecha: mbps}}, "equifiber": [nombres]}

function bw_primeros_desde_json($json)
{
    $primeros = array();
    foreach ($json['trafico'] as $ip => $dias) {
        $f = array_keys($dias);
        sort($f);
        $primeros[$ip] = $f[0];
    }
    if (isset($json['primeros'])) {
        $primeros = array_merge($primeros, $json['primeros']);
    }
    return $primeros;
}

// Nacional usa todas las OLTs, ONNET las de bw_olts_onnet() y EQUIFIBER las de la lista "equifiber"
function bw_olts_desde_json($json, $red)
{
    $filtro = null;
    if ($red == 'onnet') {
        $filtro = bw_olts_onnet();
    } elseif ($red == 'equifiber') {
        $filtro = isset($json['equifiber']) ? $json['equifiber'] : array();
    }
    $permitidas = null;
    if ($filtro !== null) {
        $permitidas = array();
        foreach ($filtro as $o) {
            $permitidas[bw_clave_olt($o)] = true;
        }
    }
    $olts = array();
    foreach ($json['olts'] as $o) {
        $clave = bw_clave_olt($o['olt']);
        if (bw_olt_excluida($o['olt']) || ($permitidas !== null && !isset($permitidas[$clave]))) {
            continue;
        }
        if (!isset($o['cap_gb'])) {
            $o['cap_gb'] = $o['cap'] >= 10000 ? (int)($o['cap'] * 2 / 1000) : (int)($o['cap'] / 1000);
        }
        $olts[$clave] = $o;
    }
    return $olts;
}
