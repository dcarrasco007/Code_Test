<?php
include ('../../../conexion/conexion_db.php');

$conn = mysql_connect($host144_geret,$user144_geret,$pass144_geret) or die(mysql_error());
$db = mysql_select_db("Aden") or die("error de conexion");
$year = $_POST["year"];
$tablaGantt   = 'OLT_GANTT_MANTENIMIENTO_'.$year;
$tablaDetalle = 'OLT_GANTT_MANTENIMIENTO_DETALLE_'.$year;

$query  = "SELECT * FROM ".$tablaGantt."";
$result = mysql_query($query) or die("error info.php $query");

// Leer TODAS las filas primero para poder procesar la cabecera dinámicamente
$rows = array();
while ($row = mysql_fetch_array($result)) {
    $rows[] = $row;
}

// -----------------------------------------------------------------------
// Fila 1 = meses (índice 0), Fila 2 = semanas (índice 1)
// Columnas 0=id, 1=nombre OLT, 2=estado -> datos de semanas empiezan en col 3
// -----------------------------------------------------------------------

$tabla = "<table border='2' class='table table-striped table-bordered' id='tabla1'>";

// --- CABECERA FILA 1: meses con colspan dinámico ---
if (isset($rows[0]) && isset($rows[1])) {
    $filasMeses   = $rows[0];  // fila con "Marzo", "Abril", etc.
    $filasSemanas = $rows[1];  // fila con "W10", "W11", etc.

    // Contar columnas totales de semanas (desde índice 3 en adelante)
    $totalCols = 0;
    for ($i = 3; isset($filasSemanas[$i]) && $filasSemanas[$i] != ''; $i++) {
        $totalCols++;
    }

    // Construir grupos mes -> cantidad de semanas leyendo fila 1
    // La fila de meses tiene el nombre del mes en la primera semana del mes,
    // las demás celdas del mismo mes están vacías.
    $meses = array(); // array de ['nombre' => 'Marzo', 'count' => 4]
    for ($i = 3; $i < 3 + $totalCols; $i++) {
        $valor = isset($filasMeses[$i]) ? trim($filasMeses[$i]) : '';
        if ($valor !== '') {
            // Nueva cabecera de mes
            $meses[] = array('nombre' => $valor, 'count' => 1);
        } else {
            // Semana adicional del mes anterior
            if (!empty($meses)) {
                $meses[count($meses) - 1]['count']++;
            }
        }
    }

    // Renderizar fila de meses
    $tabla .= "<thead><tr>";
    $tabla .= "<td>{$filasMeses[1]}</td>";  // columna id/OLT
    $tabla .= "<td>{$filasMeses[2]}</td>";  // columna estado
    foreach ($meses as $mes) {
        $tabla .= "<td colspan='{$mes['count']}'><center>{$mes['nombre']}</center></td>";
    }
    $tabla .= "</tr>";

    // --- CABECERA FILA 2: semanas individuales ---
    $tabla .= "<tr>";
    $tabla .= "<th>{$filasSemanas[1]}</th>";
    $tabla .= "<th>{$filasSemanas[2]}</th>";
    for ($i = 3; $i < 3 + $totalCols; $i++) {
        $tabla .= "<th><center>{$filasSemanas[$i]}</center></th>";
    }
    $tabla .= "</tr></thead>";
}

// --- FILAS DE DATOS (desde fila índice 2 en adelante) ---
$coloresHex = array(
    'FF92D050' => '#92d14f',
    'FF7891B0' => '#538dd5',
    'FFFF0000' => '#FF0000',
);

$tabla .= "<tbody>";
for ($r = 2; $r < count($rows); $r++) {
    $row = $rows[$r];
    $tabla .= "<tr>";
    $tabla .= "<td><center>{$row[1]}</center></td>";

    // Columna estado con color
    $estado = $row[2];
    if ($estado == 'REALIZADAS') {
        $tabla .= "<td bgcolor='#92d14f'><center>{$estado}</center></td>";
    } elseif ($estado == 'PENDIENTES') {
        $tabla .= "<td bgcolor='#538dd5'><center>{$estado}</center></td>";
    } elseif ($estado == 'POSPUESTAS') {
        $tabla .= "<td bgcolor='#FF0000'><center>{$estado}</center></td>";
    } else {
        $tabla .= "<td><center>{$estado}</center></td>";
    }

    // Celdas de semanas con color dinámico
    for ($i = 3; $i < 3 + $totalCols; $i++) {
        $val = isset($row[$i]) ? $row[$i] : '';
        if (isset($coloresHex[$val])) {
            $tabla .= "<td bgcolor='{$coloresHex[$val]}'></td>";
        } else {
            $tabla .= "<td><center>{$val}</center></td>";
        }
    }
    $tabla .= "</tr>";
}
$tabla .= "</tbody>";
$tabla .= "</table>";

mysql_close($conn);

$t = "<a href='../Mantencion_OLT/Archivos/Gantt_Mantenimiento_de_OLT_y_pruebas_de_alta_disponibilidad_OLT-$year.xlsx'>Exportar Archivo</a>";
echo $t;
echo $tabla;
?>