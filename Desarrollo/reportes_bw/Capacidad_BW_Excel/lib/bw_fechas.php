<?php
/**
 * Fechas del reporte: semanas ISO-8601 (la semana 1 parte el lunes de la semana
 * que contiene el primer jueves del año, ej: lunes 29-12-2025 para 2026).
 * Compatible con PHP 5.3.
 */

function bw_nombre_mes($mes)
{
    $meses = array(1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio',
        'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
    return $meses[(int)$mes];
}

// Mismo texto que usa el Excel (Miercoles sin tilde, Sábado con tilde)
function bw_nombre_dia($n)
{
    $dias = array(1 => 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo');
    return $dias[(int)$n];
}

// Timestamp (12:00 para evitar problemas de horario de verano) del lunes de la semana ISO
function bw_ts_lunes($anio, $semana)
{
    $d = new DateTime();
    $d->setISODate((int)$anio, (int)$semana, 1);
    return mktime(12, 0, 0, (int)$d->format('n'), (int)$d->format('j'), (int)$d->format('Y'));
}

// Timestamp del día $k del año ISO (k = 0 es el lunes de la semana 1)
function bw_ts_dia($anio, $k)
{
    $l = bw_ts_lunes($anio, 1);
    return mktime(12, 0, 0, (int)date('n', $l), (int)date('j', $l) + (int)$k, (int)date('Y', $l));
}

function bw_fecha_dia($anio, $k)
{
    return date('Y-m-d', bw_ts_dia($anio, $k));
}

// "Lunes 12 de Octubre"
function bw_etiqueta_dia($anio, $k)
{
    $ts = bw_ts_dia($anio, $k);
    return bw_nombre_dia(date('N', $ts)) . ' ' . date('j', $ts) . ' de ' . bw_nombre_mes(date('n', $ts));
}

// Semana ISO anterior a la fecha indicada (por defecto hoy): array(anio, semana)
function bw_semana_anterior($ts = null)
{
    if ($ts === null) {
        $ts = time();
    }
    $t = $ts - 7 * 86400;
    return array((int)date('o', $t), (int)date('W', $t));
}

function bw_semanas_del_anio($anio)
{
    $d = new DateTime();
    $d->setISODate((int)$anio, 53, 1);
    return ((int)$d->format('o') == (int)$anio) ? 53 : 52;
}

// Nombres de los 2 archivos. El mes es el del jueves de la semana (regla ISO)
function bw_nombres_archivo($anio, $semana)
{
    $jueves = bw_ts_lunes($anio, $semana) + 3 * 86400;
    $base = BW_PREFIJO_ARCHIVO . ' ' . bw_nombre_mes(date('n', $jueves)) . ' ' . $anio;
    return array(
        'semana' => $base . ' W-' . (int)$semana . '.xlsx',
        'act'    => $base . ' Act - Act.xlsx',
    );
}

function bw_nombre_alertas($anio, $semana)
{
    return 'alertas_' . $anio . '_W' . str_pad((int)$semana, 2, '0', STR_PAD_LEFT) . '.json';
}
