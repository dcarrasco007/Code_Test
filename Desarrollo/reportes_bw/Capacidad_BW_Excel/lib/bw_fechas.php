<?php
/**
 * Fechas del reporte. Semanas de lunes a domingo; la semana 1 de un año es la que
 * contiene el 1 de enero (ej: 2026 parte el lunes 29-12-2025 y 2027 el lunes 28-12-2026).
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

// Timestamp (12:00 para evitar problemas de horario de verano) del lunes de la semana 1 del año
function bw_ts_lunes_semana1($anio)
{
    $enero1 = mktime(12, 0, 0, 1, 1, (int)$anio);
    return mktime(12, 0, 0, 1, 1 - ((int)date('N', $enero1) - 1), (int)$anio);
}

// Timestamp del día $k del año del reporte (k = 0 es el lunes de la semana 1)
function bw_ts_dia($anio, $k)
{
    $l = bw_ts_lunes_semana1($anio);
    return mktime(12, 0, 0, (int)date('n', $l), (int)date('j', $l) + (int)$k, (int)date('Y', $l));
}

function bw_ts_lunes($anio, $semana)
{
    return bw_ts_dia($anio, ((int)$semana - 1) * 7);
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

// Días entre dos timestamps de mediodía
function bw_dias_entre($ts1, $ts2)
{
    return (int)round(($ts2 - $ts1) / 86400);
}

function bw_semanas_del_anio($anio)
{
    return (int)(bw_dias_entre(bw_ts_lunes_semana1($anio), bw_ts_lunes_semana1($anio + 1)) / 7);
}

// Año y semana del reporte a la que pertenece una fecha: array(anio, semana)
function bw_semana_de_fecha($ts)
{
    $dia = mktime(12, 0, 0, (int)date('n', $ts), (int)date('j', $ts), (int)date('Y', $ts));
    $anio = (int)date('Y', $dia);
    if ($dia >= bw_ts_lunes_semana1($anio + 1)) {
        $anio++;
    }
    return array($anio, (int)floor(bw_dias_entre(bw_ts_lunes_semana1($anio), $dia) / 7) + 1);
}

// Semana anterior a la fecha indicada (por defecto hoy): array(anio, semana)
function bw_semana_anterior($ts = null)
{
    if ($ts === null) {
        $ts = time();
    }
    return bw_semana_de_fecha($ts - 7 * 86400);
}

// Nombres de los 2 archivos. El mes es el del domingo de la semana (siempre cae en el año del reporte)
function bw_nombres_archivo($anio, $semana)
{
    $domingo = bw_ts_dia($anio, ((int)$semana - 1) * 7 + 6);
    $base = BW_PREFIJO_ARCHIVO . ' ' . bw_nombre_mes(date('n', $domingo)) . ' ' . $anio;
    return array(
        'semana' => $base . ' W-' . (int)$semana . '.xlsx',
        'act'    => $base . ' Act - Act.xlsx',
    );
}

function bw_nombre_alertas($anio, $semana)
{
    return 'alertas_' . $anio . '_W' . str_pad((int)$semana, 2, '0', STR_PAD_LEFT) . '.json';
}
