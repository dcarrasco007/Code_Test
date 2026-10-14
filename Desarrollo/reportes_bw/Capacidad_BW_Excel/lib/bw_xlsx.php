<?php
/**
 * Edición directa del XML de la hoja "Reporte BW" dentro del .xlsx (ZipArchive + DOM).
 * Se usa en vez de PHPExcel para no perder estilos, celdas combinadas ni el formato
 * condicional del archivo (PHPExcel lo separa celda por celda y descarta reglas).
 *
 * Estructura de la hoja:
 *   fila 1: encabezados, fila 2: nombre del día (combinado en 3 columnas), filas 3..N: OLTs
 *   A: PE-com, B: Tráfico PE, C: Capacidad PE, D: OLT (A/B/C combinadas por grupo de PE)
 *   desde E, 3 columnas por día: Tráfico, Capacidad, % Uplink (=+Tráfico/Capacidad)
 *
 * Compatible con PHP 5.3.
 */

function bw_col_num($letras)
{
    $n = 0;
    $letras = strtoupper($letras);
    for ($i = 0; $i < strlen($letras); $i++) {
        $n = $n * 26 + (ord($letras[$i]) - 64);
    }
    return $n;
}

function bw_col_letra($n)
{
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = (int)(($n - $m - 1) / 26);
    }
    return $s;
}

// "AB12" => array(28, 12)
function bw_ref_partes($ref)
{
    if (preg_match('/^\$?([A-Z]+)\$?(\d+)$/', $ref, $m)) {
        return array(bw_col_num($m[1]), (int)$m[2]);
    }
    return null;
}

// "A3:A12" => array(1, 3, 1, 12)  /  "J71" => array(10, 71, 10, 71)
function bw_rango_partes($rango)
{
    $p = explode(':', $rango);
    $a = bw_ref_partes($p[0]);
    $b = isset($p[1]) ? bw_ref_partes($p[1]) : $a;
    if ($a === null || $b === null) {
        return null;
    }
    return array($a[0], $a[1], $b[0], $b[1]);
}

function bw_rango_texto($c1, $r1, $c2, $r2)
{
    $t = bw_col_letra($c1) . $r1;
    if ($c1 != $c2 || $r1 != $r2) {
        $t .= ':' . bw_col_letra($c2) . $r2;
    }
    return $t;
}

// Primera columna (Tráfico) del día k
function bw_col_dia($k)
{
    return 5 + 3 * (int)$k;
}

function bw_es_col_pct($col)
{
    return $col >= 7 && (($col - 4) % 3) == 0;
}

function bw_formula_pct($col, $fila)
{
    return '+' . bw_col_letra($col - 2) . $fila . '/' . bw_col_letra($col - 1) . $fila;
}

// Número para <v>, independiente del locale
function bw_num_xml($n)
{
    if (is_int($n) || (is_float($n) && floor($n) == $n && abs($n) < 1e15)) {
        return sprintf('%.0F', $n);
    }
    $s = rtrim(rtrim(sprintf('%.15F', $n), '0'), '.');
    return $s === '' || $s === '-' ? '0' : $s;
}

class BwLibroXlsx
{
    var $ruta;
    var $hojaRuta;
    var $dom;
    var $raiz;
    var $sheetData;
    var $filas = array();          // num fila => DOMElement <row>
    var $compartidas = array();    // shared strings
    var $workbookXml;
    var $relsXml;
    var $ctXml;
    var $appXml;
    var $dxf = null;               // array('rojo','amarillo','verde') ids de formato diferencial

    function abrir($ruta)
    {
        $this->ruta = $ruta;
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new Exception("No se pudo abrir el Excel $ruta");
        }
        $this->workbookXml = $zip->getFromName('xl/workbook.xml');
        $this->relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $this->ctXml = $zip->getFromName('[Content_Types].xml');
        $this->appXml = $zip->getFromName('docProps/app.xml');

        // Ruta de la primera hoja
        if (!preg_match('/<sheet [^>]*r:id="([^"]+)"/', $this->workbookXml, $m)) {
            throw new Exception('No se encontró la hoja en workbook.xml');
        }
        if (!preg_match('/<Relationship [^>]*Id="' . preg_quote($m[1], '/') . '"[^>]*>/', $this->relsXml, $r)
            || !preg_match('/Target="([^"]+)"/', $r[0], $t)) {
            throw new Exception('No se encontró la relación de la hoja');
        }
        $this->hojaRuta = (substr($t[1], 0, 1) == '/') ? substr($t[1], 1) : 'xl/' . $t[1];

        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false && preg_match_all('/<si>(.*?)<\/si>/s', $ss, $sis)) {
            foreach ($sis[1] as $si) {
                $si = preg_replace('/<rPh.*?<\/rPh>/s', '', $si);
                preg_match_all('/<t[^>]*>(.*?)<\/t>/s', $si, $ts);
                $this->compartidas[] = html_entity_decode(implode('', $ts[1]), ENT_QUOTES, 'UTF-8');
            }
        }

        $xml = $zip->getFromName($this->hojaRuta);
        $zip->close();
        if ($xml === false) {
            throw new Exception('No se pudo leer ' . $this->hojaRuta);
        }
        $this->dom = new DOMDocument();
        $opc = defined('LIBXML_PARSEHUGE') ? LIBXML_PARSEHUGE : 0;
        if (!$this->dom->loadXML($xml, $opc)) {
            throw new Exception('XML de la hoja inválido');
        }
        unset($xml);
        $this->raiz = $this->dom->documentElement;
        $this->sheetData = $this->hijo($this->raiz, 'sheetData');
        $this->indexarFilas();
        $this->buscarDxf();
    }

    // ---------- utilidades DOM (se navega por nodeName para no depender de namespaces) ----------

    function hijo($padre, $nombre)
    {
        foreach ($padre->childNodes as $n) {
            if ($n->nodeType == XML_ELEMENT_NODE && $n->nodeName == $nombre) {
                return $n;
            }
        }
        return null;
    }

    function hijos($padre, $nombre)
    {
        $lista = array();
        foreach ($padre->childNodes as $n) {
            if ($n->nodeType == XML_ELEMENT_NODE && $n->nodeName == $nombre) {
                $lista[] = $n;
            }
        }
        return $lista;
    }

    function indexarFilas()
    {
        $this->filas = array();
        foreach ($this->hijos($this->sheetData, 'row') as $row) {
            $this->filas[(int)$row->getAttribute('r')] = $row;
        }
        ksort($this->filas);
    }

    // array(col => <c>)
    function celdasFila($fila)
    {
        $celdas = array();
        if (!isset($this->filas[$fila])) {
            return $celdas;
        }
        foreach ($this->hijos($this->filas[$fila], 'c') as $c) {
            $p = bw_ref_partes($c->getAttribute('r'));
            $celdas[$p[0]] = $c;
        }
        return $celdas;
    }

    function celda($fila, $col)
    {
        $celdas = $this->celdasFila($fila);
        return isset($celdas[$col]) ? $celdas[$col] : null;
    }

    function valor($fila, $col)
    {
        $c = $this->celda($fila, $col);
        if ($c === null) {
            return null;
        }
        $t = $c->getAttribute('t');
        if ($t == 'inlineStr') {
            $is = $this->hijo($c, 'is');
            return $is ? $is->textContent : '';
        }
        $v = $this->hijo($c, 'v');
        if ($v === null) {
            return null;
        }
        if ($t == 's') {
            $i = (int)$v->textContent;
            return isset($this->compartidas[$i]) ? $this->compartidas[$i] : '';
        }
        if ($t == 'str' || $t == 'e' || $t == 'b') {
            return $v->textContent;
        }
        return (float)$v->textContent;
    }

    function ultimaFila()
    {
        $claves = array_keys($this->filas);
        return (int)end($claves);
    }

    // Cantidad de días que tiene la hoja (según encabezados de la fila 1)
    function numDias()
    {
        $max = 4;
        foreach ($this->celdasFila(1) as $col => $c) {
            if ($col > $max) {
                $max = $col;
            }
        }
        return (int)floor(($max - 4) / 3);
    }

    function nuevaCelda($ref, $estilo)
    {
        $c = $this->dom->createElement('c');
        $c->setAttribute('r', $ref);
        if ($estilo !== null && $estilo !== '') {
            $c->setAttribute('s', $estilo);
        }
        return $c;
    }

    function celdaNumero($ref, $estilo, $num)
    {
        $c = $this->nuevaCelda($ref, $estilo);
        if ($num !== null) {
            $c->appendChild($this->dom->createElement('v', bw_num_xml($num)));
        }
        return $c;
    }

    function celdaTexto($ref, $estilo, $texto)
    {
        $c = $this->nuevaCelda($ref, $estilo);
        $c->setAttribute('t', 'inlineStr');
        $is = $this->dom->createElement('is');
        $t = $this->dom->createElement('t');
        $t->appendChild($this->dom->createTextNode($texto));
        $is->appendChild($t);
        $c->appendChild($is);
        return $c;
    }

    // Inserta la celda en su fila respetando el orden de columnas (reemplaza si existe)
    function ponerCelda($fila, $celdaNueva)
    {
        if (!isset($this->filas[$fila])) {
            $this->crearFilaVacia($fila);
        }
        $p = bw_ref_partes($celdaNueva->getAttribute('r'));
        $row = $this->filas[$fila];
        foreach ($this->hijos($row, 'c') as $c) {
            $q = bw_ref_partes($c->getAttribute('r'));
            if ($q[0] == $p[0]) {
                $row->replaceChild($celdaNueva, $c);
                return;
            }
            if ($q[0] > $p[0]) {
                $row->insertBefore($celdaNueva, $c);
                return;
            }
        }
        $row->appendChild($celdaNueva);
    }

    function crearFilaVacia($fila)
    {
        $row = $this->dom->createElement('row');
        $row->setAttribute('r', $fila);
        $siguiente = null;
        foreach ($this->filas as $n => $r) {
            if ($n > $fila) {
                $siguiente = $r;
                break;
            }
        }
        if ($siguiente) {
            $this->sheetData->insertBefore($row, $siguiente);
        } else {
            $this->sheetData->appendChild($row);
        }
        $this->indexarFilas();
        return $row;
    }

    // Deja un valor numérico (o texto) fijo en una celda existente, conservando su estilo
    function fijarValor($fila, $col, $valor)
    {
        $ref = bw_col_letra($col) . $fila;
        $c = $this->celda($fila, $col);
        $estilo = $c ? $c->getAttribute('s') : null;
        if (is_string($valor)) {
            $this->ponerCelda($fila, $this->celdaTexto($ref, $estilo, $valor));
        } else {
            $this->ponerCelda($fila, $this->celdaNumero($ref, $estilo, $valor));
        }
    }

    // ---------- fórmulas ----------

    // Reemplaza las fórmulas compartidas por fórmulas simples (+X/Y) para poder mover filas
    function normalizarFormulas()
    {
        foreach ($this->filas as $fila => $row) {
            foreach ($this->hijos($row, 'c') as $c) {
                $f = $this->hijo($c, 'f');
                if ($f === null) {
                    continue;
                }
                $p = bw_ref_partes($c->getAttribute('r'));
                if (bw_es_col_pct($p[0])) {
                    $c->replaceChild($this->dom->createElement('f', bw_formula_pct($p[0], $fila)), $f);
                }
            }
        }
    }

    // ---------- formato condicional ----------

    // Busca los formatos (dxfId) de las reglas estándar: >=0.7 rojo, 0.6-0.7 amarillo, <0.6 verde
    function buscarDxf()
    {
        $rojo = $amarillo = $verde = null;
        foreach ($this->hijos($this->raiz, 'conditionalFormatting') as $cf) {
            foreach ($this->hijos($cf, 'cfRule') as $regla) {
                $op = $regla->getAttribute('operator');
                $formulas = $this->hijos($regla, 'formula');
                $f1 = isset($formulas[0]) ? trim($formulas[0]->textContent) : '';
                if ($rojo === null && $op == 'greaterThanOrEqual' && $f1 == '0.7') {
                    $rojo = $regla->getAttribute('dxfId');
                } elseif ($amarillo === null && $op == 'between' && $f1 == '0.6') {
                    $amarillo = $regla->getAttribute('dxfId');
                } elseif ($verde === null && $op == 'lessThan' && substr($f1, 0, 4) == '0.59') {
                    $verde = $regla->getAttribute('dxfId');
                }
            }
            if ($rojo !== null && $amarillo !== null && $verde !== null) {
                $this->dxf = array('rojo' => $rojo, 'amarillo' => $amarillo, 'verde' => $verde);
                return;
            }
        }
    }

    function agregarFormatoCondicional($columnas, $filaIni, $filaFin)
    {
        if ($this->dxf === null || count($columnas) == 0) {
            return false;
        }
        $prioridad = 0;
        $ultimoCf = null;
        foreach ($this->hijos($this->raiz, 'conditionalFormatting') as $cf) {
            $ultimoCf = $cf;
            foreach ($this->hijos($cf, 'cfRule') as $regla) {
                $prioridad = max($prioridad, (int)$regla->getAttribute('priority'));
            }
        }
        $rangos = array();
        foreach ($columnas as $col) {
            $rangos[] = bw_rango_texto($col, $filaIni, $col, $filaFin);
        }
        $cf = $this->dom->createElement('conditionalFormatting');
        $cf->setAttribute('sqref', implode(' ', $rangos));
        $reglas = array(
            array($this->dxf['rojo'], 'greaterThanOrEqual', array('0.7')),
            array($this->dxf['amarillo'], 'between', array('0.6', '0.699999999999999')),
            array($this->dxf['verde'], 'lessThan', array('0.5999999999')),
        );
        foreach ($reglas as $r) {
            $prioridad++;
            $regla = $this->dom->createElement('cfRule');
            $regla->setAttribute('type', 'cellIs');
            $regla->setAttribute('dxfId', $r[0]);
            $regla->setAttribute('priority', $prioridad);
            $regla->setAttribute('operator', $r[1]);
            foreach ($r[2] as $f) {
                $regla->appendChild($this->dom->createElement('formula', $f));
            }
            $cf->appendChild($regla);
        }
        // Orden del esquema: sheetData ... mergeCells, phoneticPr, conditionalFormatting, dataValidations ...
        $ref = $ultimoCf;
        $previos = array('phoneticPr', 'mergeCells', 'dataConsolidate', 'sortState', 'autoFilter', 'scenarios',
            'protectedRanges', 'sheetProtection', 'sheetCalcPr', 'sheetData');
        foreach ($previos as $nombre) {
            if ($ref === null) {
                $ref = $this->hijo($this->raiz, $nombre);
            }
        }
        if ($ref->nextSibling) {
            $this->raiz->insertBefore($cf, $ref->nextSibling);
        } else {
            $this->raiz->appendChild($cf);
        }
        return true;
    }

    // ---------- celdas combinadas ----------

    function mergeCells()
    {
        $mc = $this->hijo($this->raiz, 'mergeCells');
        if ($mc === null) {
            $mc = $this->dom->createElement('mergeCells');
            if ($this->sheetData->nextSibling) {
                $this->raiz->insertBefore($mc, $this->sheetData->nextSibling);
            } else {
                $this->raiz->appendChild($mc);
            }
        }
        return $mc;
    }

    function combinar($c1, $r1, $c2, $r2)
    {
        $mc = $this->mergeCells();
        $m = $this->dom->createElement('mergeCell');
        $m->setAttribute('ref', bw_rango_texto($c1, $r1, $c2, $r2));
        $mc->appendChild($m);
        $mc->setAttribute('count', count($this->hijos($mc, 'mergeCell')));
    }

    // Grupos de PE-com: array(array(filaIni, filaFin)) según combinaciones de la columna A
    function gruposPe()
    {
        $grupos = array();
        $cubiertas = array();
        foreach ($this->hijos($this->mergeCells(), 'mergeCell') as $m) {
            $p = bw_rango_partes($m->getAttribute('ref'));
            if ($p && $p[0] == 1 && $p[2] == 1 && $p[1] >= 3) {
                $grupos[$p[1]] = array($p[1], $p[3]);
                for ($r = $p[1]; $r <= $p[3]; $r++) {
                    $cubiertas[$r] = true;
                }
            }
        }
        $ultima = $this->ultimaFila();
        for ($r = 3; $r <= $ultima; $r++) {
            if (!isset($cubiertas[$r]) && trim((string)$this->valor($r, 4)) != '') {
                $grupos[$r] = array($r, $r);
            }
        }
        ksort($grupos);
        return array_values($grupos);
    }

    // Extiende la combinación de A, B y C del grupo (filaIni) hasta filaFin
    function extenderGrupo($filaIni, $filaFin)
    {
        $mc = $this->mergeCells();
        $hechas = array();
        foreach ($this->hijos($mc, 'mergeCell') as $m) {
            $p = bw_rango_partes($m->getAttribute('ref'));
            if ($p && $p[0] == $p[2] && $p[0] <= 3 && $p[1] == $filaIni) {
                $m->setAttribute('ref', bw_rango_texto($p[0], $p[1], $p[2], $filaFin));
                $hechas[$p[0]] = true;
            }
        }
        for ($col = 1; $col <= 3; $col++) {
            if (!isset($hechas[$col])) {
                $this->combinar($col, $filaIni, $col, $filaFin);
            }
        }
    }

    // ---------- prototipo de estilos de un día ----------

    function capturarPrototipo($k)
    {
        $c0 = bw_col_dia($k);
        $proto = array('fila1' => array(), 'fila2' => array(), 'filas' => array(), 'cols' => array());
        foreach ($this->filas as $fila => $row) {
            $celdas = $this->celdasFila($fila);
            $estilos = array();
            for ($i = 0; $i < 3; $i++) {
                $c = isset($celdas[$c0 + $i]) ? $celdas[$c0 + $i] : null;
                if ($fila == 1) {
                    $proto['fila1'][$i] = $c ? $c->cloneNode(true) : null;
                }
                $estilos[$i] = $c ? $c->getAttribute('s') : null;
            }
            if ($fila == 2) {
                $proto['fila2'] = $estilos;
            } elseif ($fila >= 3) {
                $proto['filas'][$fila] = $estilos;
            }
        }
        $cols = $this->hijo($this->raiz, 'cols');
        if ($cols) {
            foreach ($this->hijos($cols, 'col') as $col) {
                for ($i = 0; $i < 3; $i++) {
                    if ((int)$col->getAttribute('min') <= $c0 + $i && (int)$col->getAttribute('max') >= $c0 + $i) {
                        $proto['cols'][$i] = $col->cloneNode(false);
                    }
                }
            }
        }
        return $proto;
    }

    // ---------- truncar / insertar ----------

    // Deja solo los primeros $k días (borra columnas de días posteriores)
    function truncarDias($k)
    {
        $maxCol = 4 + 3 * (int)$k;
        foreach ($this->filas as $fila => $row) {
            foreach ($this->hijos($row, 'c') as $c) {
                $p = bw_ref_partes($c->getAttribute('r'));
                if ($p[0] > $maxCol) {
                    $row->removeChild($c);
                }
            }
        }
        $mc = $this->mergeCells();
        foreach ($this->hijos($mc, 'mergeCell') as $m) {
            $p = bw_rango_partes($m->getAttribute('ref'));
            if ($p && $p[0] > $maxCol) {
                $mc->removeChild($m);
            }
        }
        $mc->setAttribute('count', count($this->hijos($mc, 'mergeCell')));
        foreach ($this->hijos($this->raiz, 'conditionalFormatting') as $cf) {
            $quedan = array();
            foreach (preg_split('/\s+/', trim($cf->getAttribute('sqref'))) as $tok) {
                $p = bw_rango_partes($tok);
                if ($p && $p[0] <= $maxCol) {
                    if ($p[2] > $maxCol) {
                        $tok = bw_rango_texto($p[0], $p[1], $maxCol, $p[3]);
                    }
                    $quedan[] = $tok;
                }
            }
            if (count($quedan) == 0) {
                $this->raiz->removeChild($cf);
            } else {
                $cf->setAttribute('sqref', implode(' ', $quedan));
            }
        }
        $cols = $this->hijo($this->raiz, 'cols');
        if ($cols) {
            foreach ($this->hijos($cols, 'col') as $col) {
                if ((int)$col->getAttribute('min') > $maxCol) {
                    $cols->removeChild($col);
                } elseif ((int)$col->getAttribute('max') > $maxCol) {
                    $col->setAttribute('max', $maxCol);
                }
            }
        }
    }

    function desplazarRango($tok, $desde)
    {
        $p = bw_rango_partes($tok);
        if ($p === null) {
            return $tok;
        }
        if ($p[1] >= $desde) {
            $p[1]++;
        }
        if ($p[3] >= $desde) {
            $p[3]++;
        }
        $simple = (strpos($tok, ':') === false);
        return $simple ? bw_col_letra($p[0]) . $p[1] : bw_rango_texto($p[0], $p[1], $p[2], $p[3]);
    }

    /**
     * Inserta una fila nueva en $pos copiando el formato de $filaModelo (sin valores).
     * Mueve hacia abajo filas, combinaciones y formato condicional.
     */
    function insertarFila($pos, $filaModelo)
    {
        $modelo = $this->filas[$filaModelo];
        $filas = $this->filas;
        krsort($filas);
        foreach ($filas as $fila => $row) {
            if ($fila < $pos) {
                break;
            }
            $nueva = $fila + 1;
            $row->setAttribute('r', $nueva);
            foreach ($this->hijos($row, 'c') as $c) {
                $p = bw_ref_partes($c->getAttribute('r'));
                $c->setAttribute('r', bw_col_letra($p[0]) . $nueva);
                $f = $this->hijo($c, 'f');
                if ($f !== null && bw_es_col_pct($p[0])) {
                    $c->replaceChild($this->dom->createElement('f', bw_formula_pct($p[0], $nueva)), $f);
                }
            }
        }
        foreach ($this->hijos($this->mergeCells(), 'mergeCell') as $m) {
            $m->setAttribute('ref', $this->desplazarRango($m->getAttribute('ref'), $pos));
        }
        foreach ($this->hijos($this->raiz, 'conditionalFormatting') as $cf) {
            $toks = array();
            foreach (preg_split('/\s+/', trim($cf->getAttribute('sqref'))) as $tok) {
                $toks[] = $this->desplazarRango($tok, $pos);
            }
            $cf->setAttribute('sqref', implode(' ', $toks));
        }

        $row = $modelo->cloneNode(false);
        $row->setAttribute('r', $pos);
        foreach ($this->hijos($modelo, 'c') as $c) {
            $p = bw_ref_partes($c->getAttribute('r'));
            $nc = $this->nuevaCelda(bw_col_letra($p[0]) . $pos, $c->getAttribute('s'));
            $row->appendChild($nc);
        }
        $siguiente = isset($this->filas[$pos]) ? $this->filas[$pos] : null;   // ya quedó en $pos+1
        if ($siguiente) {
            $this->sheetData->insertBefore($row, $siguiente);
        } else {
            $this->sheetData->appendChild($row);
        }
        $this->indexarFilas();
    }

    // ---------- agregar un día ----------

    /**
     * Agrega las 3 columnas del día $k.
     * $valores: array(fila => array(trafico|null, capacidad|null)) para filas de OLT.
     * Retorna la columna de % Uplink.
     */
    function agregarDia($k, $etiqueta, $proto, $valores)
    {
        $c0 = bw_col_dia($k);
        $ultima = $this->ultimaFila();

        // Fila 1: encabezados (copia de las celdas del prototipo)
        for ($i = 0; $i < 3; $i++) {
            if ($proto['fila1'][$i]) {
                $c = $proto['fila1'][$i]->cloneNode(true);
                $c->setAttribute('r', bw_col_letra($c0 + $i) . '1');
                $this->ponerCelda(1, $c);
            }
        }
        // Fila 2: nombre del día combinado en 3 columnas
        $this->ponerCelda(2, $this->celdaTexto(bw_col_letra($c0) . '2', $proto['fila2'][0], $etiqueta));
        $this->ponerCelda(2, $this->nuevaCelda(bw_col_letra($c0 + 1) . '2', $proto['fila2'][1]));
        $this->ponerCelda(2, $this->nuevaCelda(bw_col_letra($c0 + 2) . '2', $proto['fila2'][2]));
        $this->combinar($c0, 2, $c0 + 2, 2);

        // Filas de OLT
        $defecto = reset($proto['filas']);
        for ($fila = 3; $fila <= $ultima; $fila++) {
            $est = isset($proto['filas'][$fila]) ? $proto['filas'][$fila] : $defecto;
            $traf = null;
            $cap = null;
            if (isset($valores[$fila])) {
                $traf = $valores[$fila][0];
                $cap = $valores[$fila][1];
            }
            if (!isset($this->filas[$fila])) {
                $this->crearFilaVacia($fila);
            }
            $row = $this->filas[$fila];
            $row->appendChild($this->celdaNumero(bw_col_letra($c0) . $fila, $est[0], $traf));
            $row->appendChild($this->celdaNumero(bw_col_letra($c0 + 1) . $fila, $est[1], $cap));
            $cp = $this->nuevaCelda(bw_col_letra($c0 + 2) . $fila, $est[2]);
            if ($cap !== null && $cap > 0) {
                $cp->appendChild($this->dom->createElement('f', bw_formula_pct($c0 + 2, $fila)));
                $cp->appendChild($this->dom->createElement('v', bw_num_xml(($traf === null ? 0 : $traf) / $cap)));
            }
            $row->appendChild($cp);
        }

        // Ancho de columnas
        $cols = $this->hijo($this->raiz, 'cols');
        if ($cols) {
            for ($i = 0; $i < 3; $i++) {
                if (isset($proto['cols'][$i])) {
                    $col = $proto['cols'][$i]->cloneNode(false);
                    $col->setAttribute('min', $c0 + $i);
                    $col->setAttribute('max', $c0 + $i);
                    $cols->appendChild($col);
                }
            }
        }
        return $c0 + 2;
    }

    // ---------- vista ----------

    function ajustarVista($ultimaCol, $colInicioVista)
    {
        $ultima = $this->ultimaFila();
        $dim = $this->hijo($this->raiz, 'dimension');
        if ($dim) {
            $dim->setAttribute('ref', 'A1:' . bw_col_letra($ultimaCol) . $ultima);
        }
        // "spans" es opcional y quedaría desactualizado
        foreach ($this->filas as $row) {
            if ($row->hasAttribute('spans')) {
                $row->removeAttribute('spans');
            }
        }
        $views = $this->hijo($this->raiz, 'sheetViews');
        if ($views) {
            foreach ($this->hijos($views, 'sheetView') as $sv) {
                $pane = $this->hijo($sv, 'pane');
                if ($pane) {
                    $pane->setAttribute('topLeftCell', bw_col_letra($colInicioVista) . '3');
                }
                foreach ($this->hijos($sv, 'selection') as $sel) {
                    if ($sel->getAttribute('pane') == 'bottomRight') {
                        $ref = bw_col_letra($ultimaCol - 2) . '2';
                        $sel->setAttribute('activeCell', $ref);
                        $sel->setAttribute('sqref', $ref . ':' . bw_col_letra($ultimaCol) . '2');
                    }
                }
            }
        }
    }

    // ---------- guardar ----------

    function guardar($destino, $anioHoja)
    {
        $hoja = $this->dom->saveXML();

        $wb = $this->workbookXml;
        $wb = preg_replace('/(<sheet [^>]*name=")Reporte BW \d{4}(")/', '${1}Reporte BW ' . $anioHoja . '${2}', $wb, 1);
        if (strpos($wb, 'fullCalcOnLoad') === false) {
            $wb = preg_replace('/<calcPr([^>]*?)\s*\/>/', '<calcPr$1 fullCalcOnLoad="1"/>', $wb, 1);
        }
        $app = $this->appXml;
        if ($app !== false) {
            $app = preg_replace('/>Reporte BW \d{4}</', '>Reporte BW ' . $anioHoja . '<', $app);
        }
        // calcChain se elimina: Excel lo reconstruye al abrir
        $rels = preg_replace('/<Relationship [^>]*calcChain[^>]*\/>/', '', $this->relsXml);
        $ct = preg_replace('/<Override [^>]*calcChain[^>]*\/>/', '', $this->ctXml);

        $tmp = $destino . '.tmp';
        if (!copy($this->ruta, $tmp)) {
            throw new Exception("No se pudo copiar la plantilla a $tmp");
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new Exception("No se pudo abrir $tmp");
        }
        $zip->addFromString($this->hojaRuta, $hoja);
        $zip->addFromString('xl/workbook.xml', $wb);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('[Content_Types].xml', $ct);
        if ($app !== false) {
            $zip->addFromString('docProps/app.xml', $app);
        }
        if ($zip->locateName('xl/calcChain.xml') !== false) {
            $zip->deleteName('xl/calcChain.xml');
        }
        if (!$zip->close()) {
            throw new Exception("No se pudo escribir $tmp");
        }
        if (file_exists($destino)) {
            unlink($destino);
        }
        if (!rename($tmp, $destino)) {
            throw new Exception("No se pudo crear $destino");
        }
    }
}
