<?php
include ('/u01/crontab127/conexion/conexion_db.php');
echo "+-------------------------------+\n";
echo "|        Centralizador          |\n";
echo "+-------------------------------+\n";
echo "+-------------------------------+\n";
echo "|         By El Danni           |\n";
echo "+-------------------------------+\n";
echo "\n";
echo "Obtención: ".date('H:i:s d-m-Y')."\n";
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if ($mysqli->connect_errno) {
    die("Error conexión: " . $mysqli->connect_error);
}
//---------------------------------------------------------
//------------BUSCA PROCESOS CON MAS DE 1 HORA EN EJECUCION
$qry="UPDATE MONITOREO_PROCESOS_EJECUCIONES
SET 
    estado = 'ERROR',
    fecha_fin = NOW(),
	mensaje = CONCAT(IFNULL(mensaje,''), ' Error, Timeout > 1 hora'),
    duracion = TIMESTAMPDIFF(SECOND, fecha_inicio, NOW())
WHERE 
estado = 'RUNNING'
AND fecha_inicio < NOW() - INTERVAL 1 HOUR;";
$res = $mysqli->query($qry);
//----------------------------------------

// ----------------------------------------
// 1. Buscar procesos padre recientes
// ----------------------------------------

$sql = "
SELECT p.id, p.proceso_id,l.sub_procesos,p.estado,p.fecha_fin,l.id AS IDPADRE
FROM MONITOREO_PROCESOS_EJECUCIONES p
INNER JOIN MONITOREO_PROCESO l
		ON p.proceso_id=l.id
LEFT JOIN MONITOREO_PROCESOS_ESTADO e 
    ON e.ejecucion_id = p.id
WHERE p.parent_id IS NULL
AND (e.estado='RUNNING' OR e.ejecucion_id IS NULL)
AND p.fecha_inicio >= NOW() - INTERVAL 1 DAY
";

$res = $mysqli->query($sql);

while ($padre = $res->fetch_assoc()) {

    $lote_id = intval($padre['id']);
    $proceso_id = intval($padre['proceso_id']);

    // ----------------------------------------
    // 2. Obtener métricas del lote (hijos)
    // ----------------------------------------
    if($padre['sub_procesos']=='No'){
        //proceso no tiene subprocesos
        $estado=$padre['estado'];
        $ultima_fin=$padre['fecha_fin'];
        $proceso_id=$padre['id'];
        $id_padre=$padre['IDPADRE'];
        $sqlDuracion = "
        SELECT TIMESTAMPDIFF(SECOND, fecha_inicio, fecha_fin) as duracion
        FROM MONITOREO_PROCESOS_EJECUCIONES
        WHERE id = $proceso_id
        ";

        $resDur = $mysqli->query($sqlDuracion);
        $durRow = $resDur->fetch_assoc();
        $duracion_lote = intval($durRow['duracion']);
        $sqlEstado = "
        INSERT INTO MONITOREO_PROCESOS_ESTADO
        (proceso_id, ultima_ejecucion, estado, duracion, mensaje, ejecucion_id)
        VALUES
        ($id_padre, '$ultima_fin', '$estado', $duracion_lote, 'Recalculado por cron',$proceso_id)
        ON DUPLICATE KEY UPDATE
        ejecucion_id = $proceso_id,
        ultima_ejecucion = '$ultima_fin',
        estado = '$estado',
        duracion = $duracion_lote,
        mensaje = 'Recalculado por cron'
        ";

        $mysqli->query($sqlEstado);

    }else{
        $id_padre=$padre['IDPADRE'];
        $sqlHijos = "
        SELECT 
            COUNT(*) as total,
            SUM(estado='OK') as ok,
            SUM(estado='ERROR') as error,
            SUM(estado='RUNNING') as running,
            MAX(fecha_fin) as ultima_fin
        FROM MONITOREO_PROCESOS_EJECUCIONES
        WHERE lote_id = $lote_id
        AND parent_id IS NOT NULL
        ";

        $resHijos = $mysqli->query($sqlHijos);
        $row = $resHijos->fetch_assoc();
        $ultima_fin = $row['ultima_fin'];

        // fallback si aún no termina ninguno
        if (!$ultima_fin) {
            $ultima_fin = date('Y-m-d H:i:s');
        }

        $total   = intval($row['total']);
        $ok      = intval($row['ok']);
        $error   = intval($row['error']);
        $running = intval($row['running']);

        // ----------------------------------------
        // 3. Determinar estado consolidado
        // ----------------------------------------

        if ($error > 0) {
            $estado = 'ERROR';
        } elseif ($running > 0) {
            $estado = 'RUNNING';
        } elseif ($total > 0 && $ok == $total) {
            $estado = 'OK';
        } else {
            $estado = 'RUNNING';
        }
        //-------------------------------------------------
        //--------duracion lote----------------------
        //---------------------------------------------------
        $duracion_lote = 0;

        if ($row['ultima_fin']) {
            $sqlDuracion = "
            SELECT TIMESTAMPDIFF(SECOND, fecha_inicio, '{$row['ultima_fin']}') as duracion
            FROM MONITOREO_PROCESOS_EJECUCIONES
            WHERE id = $lote_id
            ";

            $resDur = $mysqli->query($sqlDuracion);
            $durRow = $resDur->fetch_assoc();
            $duracion_lote = intval($durRow['duracion']);
        }
        // ----------------------------------------
        // 4. Actualizar procesos_estado
        // ----------------------------------------

        $sqlEstado = "
        INSERT INTO MONITOREO_PROCESOS_ESTADO
        (proceso_id, ultima_ejecucion, estado, duracion, mensaje, ejecucion_id)
        VALUES
        ($id_padre, '$ultima_fin', '$estado', $duracion_lote, 'Recalculado por cron con Subprocesos',$lote_id)
        ON DUPLICATE KEY UPDATE
        ejecucion_id = $lote_id,
        ultima_ejecucion = '$ultima_fin',
        estado = '$estado',
        duracion = $duracion_lote,
        mensaje = 'Recalculado por cron'
        ";

        $mysqli->query($sqlEstado);
    }

    
}
$numero=rand(1,10);
echo "Random es: ".$numero."\n";
if($numero>8){
    echo"\n Entro en proceso de eliminación de registros antuguos sobre 2 dias...\n";
    $qry_eliminacion="DELETE FROM MONITOREO_PROCESOS_EJECUCIONES 
    WHERE fecha_registro < NOW() - INTERVAL 2 DAY;";

    $mysqli->query($qry_eliminacion);
    $qry_eliminacion="DELETE FROM MONITOREO_PROCESOS_ESTADO 
    WHERE ultima_ejecucion < NOW() - INTERVAL 2 DAY;";

    $mysqli->query($qry_eliminacion);
    $mysqli->close();
}else{
    $mysqli->close();
    echo"\nNo entra a eliminación de registros...\n";
}
?>
