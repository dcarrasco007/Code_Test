<?php
include ('../../../conexion/conexion_db.php');
include ('../Vlan_Servicio/funciones.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$ip = $_POST['ip'];
$fecha = $_POST['fecha'];
$year=date('Y');

$sql="SELECT id, ip, server, vlan, clientes, fecha
FROM Aden.OLT_VLAN_SERVICIO_CANTIDAD WHERE ip='$ip' AND DATE(fecha)='$fecha'";

$resultd1 = $mysqli->query($sql) or die("error 1 $sql");
$resultd2 = $mysqli->query($sql) or die("error 1 $sql");

$internet=0;
$tv=0;
$voz=0;
$gestion=0;
$serviciosInternet=vlanInternet();
$serviciosVoz=vlanVoz();
$serviciosTV=vlanTV();
$serviciosGestion=vlanGestion();
$tabla_vlan .= "<table align='center' id='tblvlan' class ='table table-bordered table-striped' border='1'>
            <thead class = 'bg-primary'><tr>
            <th bgcolor='#0566FC'><center>EQUIPO</center></th>
            <th bgcolor='#0566FC'><center>IP </center></th>
            <th bgcolor='#0566FC' class='filter-select filter-onlyAvail'><center>VLAN</center></th>
            <th bgcolor='#0566FC' ><center>TOTAL CLIENTES</center></th>
            </tr></thead>";

$cont = 1;

while($row = $resultd1->fetch_array(MYSQLI_NUM)){
$equipo=$row[2];
    foreach ($serviciosInternet as $key => $value) {
        if($value==$row[3]){
            $internet=$internet+$row[4];
        }
    }
    foreach ($serviciosVoz as $key => $value) {
        if($value==$row[3]){
            $voz=$voz+$row[4];
        }
    }
    foreach ($serviciosTV as $key => $value) {
        if($value==$row[3]){
            $tv=$tv+$row[4];
        }
    }
    foreach ($serviciosGestion as $key => $value) {
        if($value==$row[3]){
            $gestion=$gestion+$row[4];
        }
    }
}
                            $tabla_vlan .= '<tr>
                                <td><center>'.$equipo.'</center></td>
                                <td><center>'.$ip.'</center></td>
                                <td><center>Vlan Internet</center></td>
                                <td><center>'.$internet.'</center></td>
                                </tr>
                                <tr>
                                <td><center>'.$equipo.'</center></td>
                                <td><center>'.$ip.'</center></td>
                                <td><center>Vlan TV</center></td>
                                <td><center>'.$tv.'</center></td>
                                </tr>
                                <tr>
                                <td><center>'.$equipo.'</center></td>
                                <td><center>'.$ip.'</center></td>
                                <td><center>Vlan Voz</center></td>
                                <td><center>'.$voz.'</center></td>
                                </tr>
                                <tr>
                                <td><center>'.$equipo.'</center></td>
                                <td><center>'.$ip.'</center></td>
                                <td><center>Vlan Gesti&oacute;n</center></td>
                                <td><center>'.$gestion.'</center></td>
                                </tr>';
$sfile = "../Vlan_Servicio/Reporte_servicios_vlan.xls"; // Ruta del archivo a generar
$fp = fopen($sfile, "w");
fwrite($fp, $tabla_vlan);
fclose($fp);


$t = "<a href='../Vlan/Vlan_Servicio/Reporte_servicios_vlan.xls'>Exportar Archivo</a>";
echo $t;

$tabla_vlan .= "</table>";

echo $tabla_vlan;
$tabla_vlan_detalle = "<div class='col-md-12 text-center'><h3>Vlan de Servicios por OLT Detalle</h3></div></br></br></br><div class='col-md-1'></div><div class='col-md-10 text-center'><table align='center' id='tblvlan2' class ='table table-bordered table-striped' border='1'>
            <thead class = 'bg-primary'><tr>
            <th bgcolor='#0566FC'><center>EQUIPO</center></th>
            <th bgcolor='#0566FC'><center>IP </center></th>
            <th bgcolor='#0566FC' class='filter-select filter-onlyAvail'><center>VLAN</center></th>
            <th bgcolor='#0566FC' ><center>CLIENTES</center></th>
            <th bgcolor='#0566FC' ><center>FECHA PROCESO</center></th>
            </tr></thead>";
while($row2 = $resultd2->fetch_array(MYSQLI_NUM)){

    $tabla_vlan_detalle .= '<tr>
                                <td><center>'.$row2[2].'</center></td>
                                <td><center>'.$row2[1].'</center></td>
                                <td><center>'.$row2[3].'</center></td>
                                <td><center>'.$row2[4].'</center></td>
                                <td><center>'.$row2[5].'</center></td>
                                </tr>';
}
$tabla_vlan_detalle .='</tabla></div>';
echo $tabla_vlan_detalle;
?>

<script>
    $("#tblvlan").tablesorter({
        theme: 'blue',
    	widgets: ["zebra" , "stickyHeaders" , "filter"],
    });
    $("#tblvlan2").tablesorter({
        theme: 'blue',
    	widgets: ["zebra" , "stickyHeaders" , "filter"],
    });
</script>