<?php
date_default_timezone_set('America/Santiago');
//include ('/var/www/html/conexion/conexion_db.php');
include ('/var/www/procesos/php/conexion/conexion_db.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

if(!$mysqli){ 
    echo "Error en conectar BD ADEN!"; 
}
    
    $fecha1=date('Y-m-d');
    echo 'fecha Inicial: '.$fecha1;
    $fecha2=strtotime($fecha1."- 1 days");
    $fecha=date("Y-m-d",$fecha2);
    //$fecha='2024-03-25';
    /*  for ($i=8; $i <=20 ; $i++) { 
        if($i<=9){
            $dia='0'.$i;
        }else{
            $dia=$i;
        }
        $fecha='2025-07-'.$dia;   */
    
        echo "fecha Inicial: ".$fecha."\n";
        #---comantada qry por tiempod e ejecución superior a los 30 min 21-07-2025
        /* $query = "SELECT
                    OLT_SERVER.`server`,
                    OLT_TRAFICOGPON2.ip_equipo,
                    OLT_SERVER.modelo,
                    OLT_TRAFICOGPON2.`port`,
                    FORMAT(OLT_TRAFICOGPON2.down_mbps,2) AS bajada,
                    OLT_TRAFICOGPON2.fecha
                    FROM
                        OLT_SERVER
                    INNER JOIN OLT_TRAFICOGPON2 ON OLT_SERVER.ip = OLT_TRAFICOGPON2.ip_equipo
                    INNER JOIN OLT_PUERTOS_GPON ON OLT_SERVER.marca_gpon = OLT_PUERTOS_GPON.marca
                    AND OLT_TRAFICOGPON2.`port` = OLT_PUERTOS_GPON.puerto
                    WHERE
                        OLT_TRAFICOGPON2.fecha = '$fecha'
                    AND
                        OLT_TRAFICOGPON2.up_mbps <> '0.00'
                    ORDER BY OLT_TRAFICOGPON2.ip_equipo,OLT_TRAFICOGPON2.`port`"; */
        $query="SELECT
                    OLT_SERVER.`server`,
                    OLT_TRAFICOGPON2.ip_equipo,
                    OLT_SERVER.modelo,
                    OLT_TRAFICOGPON2.`port`,
                    FORMAT(OLT_TRAFICOGPON2.down_mbps,2) AS bajada,
                    OLT_TRAFICOGPON2.fecha
                    FROM
                        OLT_SERVER
                    INNER JOIN OLT_TRAFICOGPON2 ON OLT_SERVER.ip = OLT_TRAFICOGPON2.ip_equipo
                    WHERE
                        OLT_TRAFICOGPON2.fecha = '$fecha'
                    AND
                        OLT_TRAFICOGPON2.up_mbps <> '0.00'
					AND `port` NOT IN ('0/17/4','0/17/3','0/17/2','0/17/1','0/17/0','0/18/0','0/18/1','0/18/2','0/18/3','0/18/4','0/16/0','0/16/1','0/16/2','0/16/3','0/16/4','0/8/0',
					'0/8/1','0/8/2','0/8/3','0/8/4','0/9/0','0/9/1','0/9/2','0/9/3','0/9/4')";            
        $resp = $mysqli->query($query) or die("error 2 $query");                    
        $mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
        $mysqli ->  set_charset("utf8");
        while($row = $resp->fetch_array(MYSQLI_NUM)){
            
            $porc = round(($row[4]*100)/2500,2);
            
            $query2 = "INSERT INTO OLT_PEAKS_TRAFICO_GPON
                    (equipo,ip,modelo,puerta,capacidad,diario,pdiario,fecha_diario) 
                    VALUES
                    ('$row[0]','$row[1]','$row[2]','$row[3]','2500','$row[4]','$porc','$row[5]')";
            $mysqli->query($query2) or die("error 2 $query2");
            
        }
    //}

?>
