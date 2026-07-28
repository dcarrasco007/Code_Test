<?php
//$idPag = 'reporte_firmware_Patch';
$idPag2 = '631';
include_once('../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
checkAccV2(getUser(),$idPag2);
include('../perfiles/proceso.php');
include ('../../conexion/conexion_db.php');

$conn = mysql_connect($host144_geret,$user144_geret,$pass144_geret) or die(mysql_error());
$db = mysql_select_db("Aden") or die("error de conexion");
mysql_set_charset('utf8');

$query = "SELECT equipo,ip,region,version,parche,modelo,fecha FROM OLT_VERSION_PARCHE_MODELO ORDER BY region";
$result = mysql_query($query) or die ("error muestra_data.php 1 $query");

$titulo = "<h3><center>Informe de Versi&oacute;n/Patch/Modelo Equipos OLT</center></h3>";
echo $titulo;

$tabla_version .= "<table align='center' id='tblversion' class ='table table-bordered table-striped' border='1'>
            <thead class = 'bg-primary'><tr>
            <th bgcolor='#0566FC'><center>N&deg;</center></th>
            <th bgcolor='#0566FC'><center>EQUIPO OLT</center></th>
            <th bgcolor='#0566FC'><center>IP EQUIPO</center></th>
            <th bgcolor='#0566FC'><center>REGION</center></th>
            <th bgcolor='#0566FC'><center>VERSION</center></th>
            <th bgcolor='#0566FC'><center>PATCH</center></th>
            <th bgcolor='#0566FC'><center>MODELO</center></th>
            <th bgcolor='#0566FC'><center>FECHA</center></th>
            </tr></thead>";

$cont = 1;
$cont_problema = 0;
while ($row = mysql_fetch_array($result)){
    
    $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
    $cont++;

/*
    if($row[5] == 'MA5600T'){
        if($row[3] != 'MA5600V800R013C00' && $row[4] != 'SPH211'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[4] != 'SPH211'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[3] != 'MA5600V800R013C00'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }else{
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
        }        
    }elseif($row[5] == 'MA5603T'){
        if($row[3] != 'MA5600V800R013C00' && $row[4] != 'SPH211'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[3] != 'MA5600V800R013C00'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[4] != 'SPH211'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }else{
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
        }
    }elseif($row[5] == 'MA5680T'){
        if($row[3] != 'MA5600V800R013C00' && $row[4] != 'SPH211'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[3] != 'MA5600V800R013C00'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[4] != 'SPH211'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }else{
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
        }
    }elseif($row[5] == 'MA5800-X15'){
        if($row[3] != 'MA5800V100R017C00' && $row[4] != 'SPC202'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[3] != 'MA5800V100R017C00'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[2].'</b></font></center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }elseif($row[4] != 'SPC202'){
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center><font color ="#f00"><b>'.$row[4].'</b></font></center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
            $cont_problema++;
        }else{
            $tabla_version .= '<tr>
                <td><center>'.$cont.'</center></td>
                <td><center>'.$row[0].'</center></td>
                <td><center>'.$row[1].'</center></td>
                <td><center>'.$row[2].'</center></td>
                <td><center>'.$row[3].'</center></td>
                <td><center>'.$row[4].'</center></td>
                <td><center>'.$row[5].'</center></td>
                <td><center>'.$row[6].'</center></td>
                </tr>';
            $cont++;
        }
    }
    
*/
}

$sfile = "../Reporte_Vesion_Patch_Modelo.xls"; // Ruta del archivo a generar
$fp = fopen($sfile, "w");
fwrite($fp, $tabla_version);
fclose($fp);


$t = "<a href='../Reporte_Vesion_Patch_Modelo.xls'>Exportar Archivo</a>";
echo $t;

/*echo '<center>
        <button type="button" class="btn btn-danger">EQUIPOS CON PROBLEMAS: <span class="badge">'.$cont_problema.'</span></button>
      <center>';
*/
$tabla_version .= "</table>";
echo $tabla_version;

?>

<script>
    $("#tblversion").tablesorter({
        theme: 'blue',
    	widgets: ["zebra" , "stickyHeaders" , "filter"],
    });
</script>