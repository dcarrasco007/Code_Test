<?php
/**
 * Etapa 2: carga de capacidades BW en las tablas OLT_CAPACIDAD_BW, _MES y _YEAR
 * (y sus hermanas _EQUIFIBER y _ONNET) sin pasar por un Excel subido a mano.
 *
 * Mismo formato que dejaba model/carga_excel*.php: cada columna del Excel es una fila de
 * la tabla y sus celdas 1..162 van en los campos `1`..`162`:
 *   A: PE-com | B: Tráfico PE (promedio del período) | C: Capacidad PE (la del Excel; si no hay, suma de sus OLTs) | D: OLT
 *   desde E, por día: Tráfico (fila 2 = "Lunes 12 de Octubre"), Capacidad, % Uplink (fracción)
 *
 * Origen de los datos:
 *   Nacional: el Excel anual generado por la etapa 1 si contiene todos los días del período
 *             (así la BD queda igual a lo que se descarga); si no, la BD con el orden de filas del Excel.
 *   EQUIFIBER / ONNET: la BD, con las mismas reglas de sus reportes (OLT_SERVER_V2 / lista ONNET).
 *
 * Compatible con PHP 5.3.
 */
require_once dirname(__FILE__) . '/bw_reporte.php';

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

/**
 * Carpeta y nombre del Excel del período, igual que la subida manual (js/upload/doajaxfileupload*.php):
 *   Archivos/Semana{_EQUIFIBER|_ONNET}/Capacidades_OLTs_W-{semana}_{año}.xlsx
 *   Archivos/Mes{_EQUIFIBER|_ONNET}/Capacidades_OLTs_{Mes}_{año}.xlsx
 *   Archivos/Year{_EQUIFIBER|_ONNET}/Capacidades_OLTs_{año}.xlsx
 */
function bw_archivo_periodo($periodo, $red)
{
    $redes = bw_redes();
    $sufijo = $redes[$red]['sufijo'];
    if ($periodo['tipo'] == 'semana') {
        $carpeta = 'Semana' . $sufijo;
        $nombre = 'Capacidades_OLTs_W-' . (int)$periodo['num'] . '_' . $periodo['anio'] . '.xlsx';
    } elseif ($periodo['tipo'] == 'mes') {
        $carpeta = 'Mes' . $sufijo;
        $nombre = 'Capacidades_OLTs_' . bw_nombre_mes($periodo['num']) . '_' . $periodo['anio'] . '.xlsx';
    } else {
        $carpeta = 'Year' . $sufijo;
        $nombre = 'Capacidades_OLTs_' . $periodo['anio'] . '.xlsx';
    }
    return array(BW_DIR_MODULO . '/Archivos/' . $carpeta, $nombre);
}

/**
 * Genera el Excel del período (mismo formato que el Excel anual: encabezados, colores y
 * combinaciones) y lo deja en su carpeta. Usa como base de formato el último Excel anual.
 */
function bw_guardar_excel_periodo($periodo, $red, $grilla, $columnas)
{
    $base = bw_ultimo_excel_anual((int)date('Y') + 1);
    if ($base === null) {
        throw new Exception('No hay Excel anual en ' . BW_DIR_SEMANA . ' para usar como formato');
    }
    list($carpeta, $nombre) = bw_archivo_periodo($periodo, $red);
    if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true)) {
        throw new Exception("No se pudo crear la carpeta $carpeta");
    }

    $libro = new BwLibroXlsx();
    $libro->abrir($base[0]);
    $proto = $libro->capturarPrototipo($libro->numDias() - 1);
    $proto['filas'] = array(3 => reset($proto['filas']));
    $libro->truncarDias(0);

    // Grupos de PE con los totales B / C ya calculados en la matriz
    $filas = array_slice($grilla['filas'], 0, BW_FILAS_TABLA - 2);
    $grupos = array();
    $actual = null;
    foreach ($filas as $i => $f) {
        if ($actual === null || $f['grupo'] !== $actual) {
            $r0 = 3 + $i;
            $grupos[] = array('pe' => $columnas[0][$r0], 'olts' => array(),
                'b' => $columnas[1][$r0] === '' ? null : (float)$columnas[1][$r0],
                'c' => $columnas[2][$r0] === '' ? null : (float)$columnas[2][$r0]);
            $actual = $f['grupo'];
        }
        $grupos[count($grupos) - 1]['olts'][] = $f['olt'];
    }
    $libro->reconstruirFilas($grupos);

    $colsPct = array();
    foreach ($periodo['fechas'] as $k => $fecha) {
        $valores = array();
        foreach ($filas as $i => $f) {
            $valores[3 + $i] = $f['valores'][$fecha];
        }
        $colsPct[] = $libro->agregarDia($k, $columnas[4 + 3 * $k][2], $proto, $valores);
    }
    $libro->agregarFormatoCondicional($colsPct, 3, $libro->ultimaFila());
    $libro->ajustarVista(end($colsPct), 5);
    $libro->guardar($carpeta . '/' . $nombre, $periodo['anio']);
    return $carpeta . '/' . $nombre;
}

function bw_tabla_destino($tipo, $red)
{
    $redes = bw_redes();
    $base = array('semana' => 'OLT_CAPACIDAD_BW', 'mes' => 'OLT_CAPACIDAD_BW_MES', 'anio' => 'OLT_CAPACIDAD_BW_YEAR');
    return $base[$tipo] . $redes[$red]['sufijo'];
}

// ---------- grilla (filas de OLT con sus valores por fecha) ----------
// array('filas' => array(array('pe','olt','grupo','valores' => array(fecha => array(traf, cap)))))

// Último Excel anual generado (para el orden de filas de Nacional): ruta o null
function bw_ultimo_excel_anual($anio)
{
    for ($a = (int)$anio; $a >= (int)$anio - 1; $a--) {
        $lista = bw_archivos_semana(BW_DIR_SEMANA, $a);
        if (count($lista) > 0) {
            return array(end($lista), $a);
        }
    }
    return null;
}

// Grupos de PE y OLTs del Excel: array(array('pe' => , 'olts' => array(nombres)))
function bw_layout_excel($libro)
{
    $layout = array();
    foreach ($libro->gruposPe() as $g) {
        $olts = array();
        for ($r = $g[0]; $r <= $g[1]; $r++) {
            $olt = trim((string)$libro->valor($r, 4));
            if ($olt != '') {
                $olts[] = array('olt' => $olt, 'fila' => $r);
            }
        }
        if (count($olts) > 0) {
            $c = $libro->valor($g[0], 3);
            $layout[] = array('pe' => trim((string)$libro->valor($g[0], 1)), 'olts' => $olts,
                'c' => (is_numeric($c) && $c > 0) ? (float)$c : null);
        }
    }
    return $layout;
}

/**
 * Grilla desde el Excel anual de Nacional, solo si el archivo tiene todos los días del período.
 * Retorna array(grilla, nombreArchivo) o null.
 */
function bw_grilla_desde_excel($fechas)
{
    $anioIni = bw_semana_de_fecha(strtotime($fechas[0] . ' 12:00:00'));
    $anioFin = bw_semana_de_fecha(strtotime(end($fechas) . ' 12:00:00'));
    if ($anioIni[0] != $anioFin[0]) {
        return null;   // el período cruza dos Excel anuales
    }
    $anio = $anioIni[0];
    $lista = bw_archivos_semana(BW_DIR_SEMANA, $anio);
    if (count($lista) == 0) {
        return null;
    }
    $ruta = end($lista);
    $libro = new BwLibroXlsx();
    $libro->abrir($ruta);
    $nDias = $libro->numDias();
    $lunes1 = bw_ts_lunes_semana1($anio);
    $cols = array();
    foreach ($fechas as $f) {
        $k = bw_dias_entre($lunes1, strtotime($f . ' 12:00:00'));
        if ($k < 0 || $k >= $nDias) {
            return null;   // el Excel aún no tiene ese día
        }
        $cols[$f] = bw_col_dia($k);
    }
    $filas = array();
    foreach (bw_layout_excel($libro) as $i => $g) {
        foreach ($g['olts'] as $o) {
            $celdas = $libro->celdasFila($o['fila']);
            $valores = array();
            foreach ($cols as $f => $col) {
                $t = $libro->valorCelda(isset($celdas[$col]) ? $celdas[$col] : null);
                $c = $libro->valorCelda(isset($celdas[$col + 1]) ? $celdas[$col + 1] : null);
                $valores[$f] = array(is_numeric($t) ? (float)$t : null, is_numeric($c) ? (float)$c : null);
            }
            $filas[] = array('pe' => $g['pe'], 'olt' => $o['olt'], 'grupo' => $i, 'c_pe' => $g['c'], 'valores' => $valores);
        }
    }
    return array(array('filas' => $filas), basename($ruta));
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
 * Grilla desde la BD.
 * Sin $layout (EQUIFIBER / ONNET): OLTs con tráfico en el período, como en sus reportes:
 *   primero las de 10G o más y luego las de 1G, cada bloque por PE-com (id) y capacidad descendente.
 * Con $layout (Nacional): mismo orden de filas que el Excel; OLTs con tráfico que no estén en él van al final.
 */
function bw_grilla_desde_bd($olts, $trafico, $fechas, $layout = null, $primeros = array())
{
    $conTrafico = array();
    foreach ($olts as $clave => $o) {
        foreach ($fechas as $f) {
            if (isset($trafico[$o['ip']][$f])) {
                $conTrafico[$clave] = true;
                break;
            }
        }
    }

    $grupos = array();   // array(array('pe', 'claves' => array(), 'nombres' => array()))
    $usadas = array();
    if ($layout !== null) {
        foreach ($layout as $g) {
            $grupo = array('pe' => $g['pe'], 'c' => $g['c'], 'olts' => array());
            foreach ($g['olts'] as $o) {
                $grupo['olts'][] = $o['olt'];
                $usadas[bw_clave_olt($o['olt'])] = true;
            }
            $grupos[] = $grupo;
        }
    }
    $resto = array();
    foreach ($olts as $clave => $o) {
        if (isset($conTrafico[$clave]) && !isset($usadas[$clave])) {
            $o['seccion'] = ($o['cap_gb'] !== null && $o['cap_gb'] >= 10) ? 0 : 1;
            $o['cap_gb'] = (int)$o['cap_gb'];
            $resto[] = $o;
        }
    }
    usort($resto, 'bw_ordenar_olts');
    $ultimo = null;
    foreach ($resto as $o) {
        $id = $o['seccion'] . '|' . $o['pe_id'];
        if ($id !== $ultimo) {
            $grupos[] = array('pe' => $o['pe_com'], 'c' => null, 'olts' => array());
            $ultimo = $id;
        }
        $grupos[count($grupos) - 1]['olts'][] = $o['olt'];
    }

    $filas = array();
    foreach ($grupos as $i => $g) {
        foreach ($g['olts'] as $nombre) {
            $clave = bw_clave_olt($nombre);
            $o = isset($olts[$clave]) ? $olts[$clave] : null;
            $valores = array();
            foreach ($fechas as $f) {
                $t = ($o && isset($trafico[$o['ip']][$f])) ? round($trafico[$o['ip']][$f], 0) : null;
                // OLT nueva: los días anteriores a su primer registro histórico quedan en 0 (no vacíos)
                if ($t === null && $o && isset($primeros[$o['ip']]) && $f < $primeros[$o['ip']]) {
                    $t = 0;
                }
                $valores[$f] = array($t, $o ? $o['cap'] : null);
            }
            $filas[] = array('pe' => $g['pe'], 'olt' => $nombre, 'grupo' => $i, 'c_pe' => $g['c'], 'valores' => $valores);
        }
    }
    return array('filas' => $filas);
}

// ---------- matriz (columnas del Excel => filas de la tabla) ----------

function bw_txt($v)
{
    if ($v === null || $v === '') {
        return '';
    }
    return is_numeric($v) ? bw_num_xml((float)$v) : (string)$v;
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

    // Totales por grupo de PE: B = promedio diario del tráfico del PE, C = capacidad al último día
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
        // C: la capacidad del PE que trae el Excel (Nacional); si no hay, la suma de sus OLTs al último día
        $cPe = isset($filas[$indices[0]]['c_pe']) ? $filas[$indices[0]]['c_pe'] : null;
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
                    $porTipo[$tipo]['ejemplos'][] = bw_col_letra($i + 1) . "$r BD='$a' generado='$b'";
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
 *       datos (.json de prueba en vez de BD), fuente ('bd' para no usar el Excel en Nacional)
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
    $mysqli = null;
    $json = null;
    $trafico = null;
    if (!empty($opc['datos'])) {
        $json = json_decode(file_get_contents($opc['datos']), true);
        $trafico = $json['trafico'];
    }
    if ($modo != 'simular' || $json === null) {
        $mysqli = bw_conectar_bd();
    }
    $primeros = null;
    $resultado = array();
    foreach ($redes as $red) {
        if (!isset($config[$red])) {
            throw new Exception("Red desconocida: $red");
        }
        $tabla = bw_tabla_destino($tipo, $red);
        $avisos = array();
        $grilla = null;
        $origen = 'BD';

        if ($red == 'nacional' && (!isset($opc['fuente']) || $opc['fuente'] != 'bd')) {
            $desdeExcel = bw_grilla_desde_excel($fechas);
            if ($desdeExcel !== null) {
                $grilla = $desdeExcel[0];
                $origen = 'Excel ' . $desdeExcel[1];
            }
        }
        if ($grilla === null) {
            $solo = ($red == 'onnet') ? bw_olts_onnet() : null;
            if ($json !== null) {
                $olts = bw_olts_desde_json($json, $red);
            } else {
                $olts = bw_obtener_olts($mysqli, $config[$red]['tabla_server'], $solo);
            }
            if ($trafico === null) {
                $trafico = bw_obtener_trafico($mysqli, $desde, $hasta);
            }
            $layout = null;
            if ($red == 'nacional') {
                $ultimo = bw_ultimo_excel_anual(substr($hasta, 0, 4));
                if ($ultimo !== null) {
                    $libro = new BwLibroXlsx();
                    $libro->abrir($ultimo[0]);
                    $layout = bw_layout_excel($libro);
                    unset($libro);
                }
            }
            if ($primeros === null) {
                $primeros = bw_primeros_registros($mysqli, $json, $trafico);
            }
            $grilla = bw_grilla_desde_bd($olts, $trafico, $fechas, $layout, $primeros);
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
            // Primero se deja el Excel en su carpeta (como la subida manual) y luego se carga la BD
            if (empty($opc['sinexcel'])) {
                try {
                    $archivo = bw_guardar_excel_periodo($periodo, $red, $grilla, $columnas);
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
    if ($mysqli) {
        $mysqli->close();
    }
    return $resultado;
}

// Primer registro histórico por ip (desde la BD o, en pruebas, desde el .json)
function bw_primeros_registros($mysqli, $json, $trafico)
{
    if ($json !== null) {
        $primeros = array();
        foreach ($json['trafico'] as $ip => $dias) {
            $fechas = array_keys($dias);
            sort($fechas);
            $primeros[$ip] = $fechas[0];
        }
        return $primeros;
    }
    return bw_obtener_primer_registro($mysqli, array_keys($trafico));
}

/**
 * Datos de prueba: {"olts": [...], "trafico": {...}, "equifiber": [nombres]}
 * Nacional usa todas las OLTs, ONNET las de bw_olts_onnet() y EQUIFIBER las de la lista "equifiber".
 */
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
