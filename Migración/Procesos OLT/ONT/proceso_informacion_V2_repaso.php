<?php
include ('/u01/crontab127/conexion/conexion_db.php');
echo 'Inicio del Script...';
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

//mysqli_query($mysqli,"TRUNCATE TABLE OLT_INFORMACION_ONT_DETALLE_COMPLETO");


$fecha = date("Y-m-d");
echo $fecha."\n";
$contador=0;
$Tabla1=array();
$Tabla2=array();
$sql_ip = "SELECT equipo, fn, sn,pn,onu
FROM OLT_INFO_ONT_PRUEBA";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");

while ($row = $result->fetch_array(MYSQLI_NUM)) {
    $clave = implode('|', $row);
    $Tabla1[$clave] = $row;
}
$sql_ip = "SELECT equipo,frame_id,slot_id,port_id,onu_id
    FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO";
$result = $mysqli->query($sql_ip) or die("error $sql_ip");

while ($row = $result->fetch_array(MYSQLI_NUM)) {
    //$Tabla2[] = implode('|', $row); 
    $clave = implode('|', $row);
    $Tabla2[$clave] = $row;
}
$diferencia=array_diff_key($Tabla1,$Tabla2);
echo count($diferencia)."\n";
// Agrupar por campo1 (c1)
$agrupadas = [];
foreach ($diferencia as $registro) {
    $c1 = $registro[0];
    $agrupadas[$c1][] = $registro;
}

// Mostrar agrupadas

//print_r($agrupadas);
$puerta='';
foreach ($agrupadas as $c1 => $registros) {
    $puerta=[];
    $PuertaBusqueda='';
    echo "Grupo: $c1\n";
    foreach ($registros as $r) {
        //echo "- {$r[0]}: {$r[1]}/{$r[2]}/{$r[3]}  {$r[4]}\n";
        $pu=$r[1]."/".$r[2]."/".$r[3];
        
        if (!in_array($pu, $puerta)) {
            $puerta[]=$pu;
        }
    }
    foreach ($puerta as $key => $value) {
        $PuertaBusqueda.=$value."|";
    }
    $comando = "nohup php -f /u01/crontab127/OLT/ONT/proceso_data_V2_repaso.php " . escapeshellarg($c1) . " " . escapeshellarg($PuertaBusqueda) . " > /u01/crontab127/OLT/ONT/logs/informacion_ont_repaso/{$c1}.log &";
    shell_exec($comando);

    //shell_exec("nohup php -f /u01/crontab127/OLT/ONT/proceso_data_V2_repaso.php  $c1 $PuertaBusqueda > /u01/crontab127/OLT/ONT/logs/informacion_ont_repaso/$c1.log &");

    echo "Puertas: ".$PuertaBusqueda;
    echo "\n";
}
//print_r($diferencia);
mysqli_close($mysqli);
echo 'Fin Script.';   
?>