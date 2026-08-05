<?php
include ('../conexion/conexion_db.php');
$idPag = 'vista_grafico_gpon';
include_once('../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
include('../perfiles/proceso.php');

$conn = mysql_connect($host144_geret,$user144_geret,$pass144_geret) or die(mysql_error());
$db = mysql_select_db("Aden") or die("error de conexion");

$op = $_POST['option'];
$ip = $_POST['ip'];

/*
if(isset($_POST['option'])){
    $query = "SELECT OLT_TRAFICOGPON.port FROM OLT_TRAFICOGPON INNER JOIN OLT_PUERTOS_GPON ON OLT_PUERTOS_GPON.puerto = OLT_TRAFICOGPON.port WHERE OLT_TRAFICOGPON.ip_equipo = '$ip'
    AND OLT_TRAFICOGPON.fecha = date(date(now())-1) AND OLT_TRAFICOGPON.up_mbps>1 AND OLT_PUERTOS_GPON.marca = '$op' ORDER BY puerto";
}else{
    $query = "SELECT OLT_TRAFICOGPON.port FROM OLT_TRAFICOGPON WHERE OLT_TRAFICOGPON.ip_equipo = '$ip'
    AND OLT_TRAFICOGPON.fecha = date(date(now())-1) AND OLT_TRAFICOGPON.up_mbps>1";
}
$result = mysql_query($query) or die("Error $query" . mysql_error($conn));
$t = '<select class="form-control" id="puerto_olt" style="width:200px;">';
$t .= "<option value=''>Ingrese opci&oacute;n</option>";
while ($row = mysql_fetch_array($result, MYSQL_NUM)) {

        $t .= "<option>$row[0]</option>";
    
}
$t .= '</select>';
echo $t;
*/

if(isset($_POST['option'])){
    $fechahoy=date("Y-m-d");
    $diaanterior= date("Y-m-d",strtotime($fechahoy."- 1 Days")); 
    $query = "SELECT 
    OLT_TRAFICOGPON.up_mbps,OLT_TRAFICOGPON.down_mbps,OLT_TRAFICOGPON.fecha,OLT_TRAFICOGPON.port   
    FROM OLT_TRAFICOGPON
    WHERE 
    OLT_TRAFICOGPON.up_mbps>0 
    AND OLT_TRAFICOGPON.ip_equipo = '$ip'
    AND OLT_TRAFICOGPON.fecha = '$diaanterior'
    ORDER BY OLT_TRAFICOGPON.fecha DESC";
    $result = mysql_query($query) or die("Error $query" . mysql_error($conn));
    $t = '<select class="form-control" id="puerto_olt" style="width:200px;">';
    $t .= "<option value=''>Ingrese opci&oacute;n</option>";
    while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
        
        if($row[0] > '0'){
            $t .= "<option>$row[3]</option>";
        }
    }
    $t .= '</select>';
    echo $t;
}else{
    $query = "SELECT OLT_TRAFICOGPON.port FROM OLT_TRAFICOGPON WHERE OLT_TRAFICOGPON.ip_equipo = '$ip'
    AND OLT_TRAFICOGPON.fecha = date(date(now())-1) AND OLT_TRAFICOGPON.up_mbps>1
    AND port NOT IN(SELECT DISTINCT puerto FROM OLT_PUERTOS_UPLINKS WHERE marca = '2' OR '1')
    ";
    $result = mysql_query($query) or die("Error $query" . mysql_error($conn));
    $t = '<select class="form-control" id="puerto_olt" style="width:200px;">';
    $t .= "<option value=''>Ingrese opci&oacute;n</option>";
    while ($row = mysql_fetch_array($result, MYSQL_NUM)) {
        
            $t .= "<option>$row[0]</option>";
        
    }
    $t .= '</select>';
    echo $t;
}

?>