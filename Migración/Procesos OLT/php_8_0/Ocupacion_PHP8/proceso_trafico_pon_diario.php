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
    //echo 'fecha Inicial: '.$fecha1;
    $fecha2=strtotime($fecha1."- 1 days");
    $fecha=date("Y-m-d",$fecha2);
    //$fecha='2024-03-11';
    /* for ($i=21; $i <=26 ; $i++) { 
        if($i<=9){
            $dia='0'.$i;
        }else{
            $dia=$i;
        }
        $fecha='2023-12-'.$dia;  */
    
        echo "fecha Inicial: ".$fecha."\n";
        $query = "SELECT
        OLT_SERVER.ip
        FROM 
        OLT_SERVER";
        $resp = $mysqli->query($query) or die("error 1 $query");  

        
        while($row = $resp->fetch_array(MYSQLI_NUM)){
            $ips[]=$row[0];
        }
        foreach ($ips as $key => $value) {

            echo "IP: ".$value."\n";
            $query2 = "SELECT
                        OLT_TRAFICOGPON_HORA.port,
                        FORMAT(AVG(OLT_TRAFICOGPON_HORA.up_mbps),3) AS subida,
                        FORMAT(AVG(OLT_TRAFICOGPON_HORA.down_mbps),3) AS bajada
                    FROM
                        OLT_TRAFICOGPON_HORA
                    WHERE
                        OLT_TRAFICOGPON_HORA.fecha = '$fecha'
                        AND OLT_TRAFICOGPON_HORA.ip_equipo = '$value'
                    GROUP BY
                        OLT_TRAFICOGPON_HORA.port";
            $resp2 = $mysqli->query($query2) or die("error 2 $query2");                    
            
            while($row2 = $resp2->fetch_array(MYSQLI_NUM)){
                
                $sql_trafico = "INSERT INTO OLT_TRAFICOGPON2 (ip_equipo,port,up_mbps,down_mbps,fecha) 
                            VALUES ('$value','$row2[0]','$row2[1]','$row2[2]','$fecha')";
                
                $resp = $mysqli->query($sql_trafico) or die("error 3 $sql_trafico");
                
                $sql_trafico1 = "INSERT INTO OLT_TRAFICOGPON (ip_equipo,port,up_mbps,down_mbps,fecha) 
                                VALUES ('$value','$row2[0]','$row2[1]','$row2[2]','$fecha')";
                $resp = $mysqli->query($sql_trafico1) or die("error 4 $sql_trafico1");
                
            }
        }
    //}

?>