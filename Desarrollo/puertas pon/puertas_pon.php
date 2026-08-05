<?php
include ('../../conexion/conexion_db.php');
//$idPag = 'reporte_ocupacion_ont';
$idPag2 = '41920';
include_once('../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
//checkAccV2(getUser(),$idPag2);
include('../perfiles/proceso.php');
require_once '/var/www/html/contingencia/js/PHPExcel/Classes/PHPExcel.php';
$user = getUser();
$mysqli1 = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
    $mysqli1 ->  set_charset("utf8");
    $query1 = "SELECT 
	OLT_USUARIOS.perfil s
	FROM
	OLT_USUARIOS
	WHERE 
	OLT_USUARIOS.usuario = '$user'";
    $result = $mysqli1->query($query1) or die("error $query1");
    $row = $result->fetch_array(MYSQLI_NUM);
    $perfil=$row[0];

$conn = mysql_connect($host144_geret,$user144_geret,$pass144_geret) or die(mysql_error());
$db = mysql_select_db("Aden") or die("error de conexion");

$query = "SELECT
        OLT_SERVER.`server`,
        OLT_SERVER.region,
        OLT_SERVER.tipo,
        OLT_SERVER.servicio,
        OLT_SERVER.pop,
        OLT_SERVER.formula,
        OLT_SERVER.zona_laser
        FROM
        OLT_SERVER
        GROUP BY OLT_SERVER.`server`
        ORDER BY tipo,region";
$result = mysql_query($query) or die ("error ocupacion_ont.php $query");

$titulo = "<h3><center>Informe Ocupaci&oacute;n ONT por OLT.</center></h3>";
echo $titulo;

$objPHPExcel = new PHPExcel();
$objPHPExcel->setActiveSheetIndex(0);
$sheet = $objPHPExcel->getActiveSheet();
$objPHPExcel->getActiveSheet()->getColumnDimension('A')->setWidth(30);
$objPHPExcel->getActiveSheet()->getColumnDimension('B')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('C')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('D')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('E')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('F')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('G')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('H')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('I')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('J')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('K')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('L')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('M')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('N')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('O')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('P')->setWidth(20);
$objPHPExcel->getActiveSheet()->getColumnDimension('Q')->setWidth(20);

$sheet->setCellValue('A4', 'N°');
$sheet->setCellValue('B4', 'Tipo');
$sheet->setCellValue('C4', 'RegioN');
$sheet->setCellValue('D4', 'OLT');
$sheet->setCellValue('E4', 'Zona Laser');
$sheet->setCellValue('F4', 'POP');
$sheet->setCellValue('G4', 'Servicio 3Play-Empresa');
$sheet->setCellValue('H4', 'Servicio 3Play-Persona');
$sheet->setCellValue('I4', 'Uplink(1G,10G,100G)');
$sheet->setCellValue('J4', 'ONTs Com. Total');
$sheet->setCellValue('K4', 'ONTs Com. Activa');
$sheet->setCellValue('L4', 'ONTs PCS Total');
$sheet->setCellValue('M4', 'ONTs PCS Act');
$sheet->setCellValue('N4', 'N° ZS Comercial');
$sheet->setCellValue('O4', 'N° ZS PCS');
$sheet->setCellValue('P4', 'Ocupacion ZS Comercial %');
$sheet->setCellValue('Q4', 'Ocupacion ZS PCS %');

// Configuración de la imagen
$objDrawing = new PHPExcel_Worksheet_Drawing();
$objDrawing->setName('Logo Entel');
$objDrawing->setDescription('Logo de la compañía');
$objDrawing->setPath('/var/www/html/contingencia/OLT/menu/logonuevo.png'); // Ruta correcta
$objDrawing->setHeight(50); // Ajuste de altura
$objDrawing->setCoordinates('A1'); // Posición en celda A1
$objDrawing->setOffsetX(10); // Ajusta según necesidad
$objDrawing->setOffsetY(5);
$objDrawing->setWorksheet($sheet);


$contador_excel=5;

$tabla .= "<table align='center' id='tblData' class ='table table-bordered table-striped' border='1'>
            <thead class = 'bg-primary'><tr>
            <th bgcolor='#0566FC'><h5><center>N&deg;</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Tipo</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Regi&oacute;n</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>OLT</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Zona Laser</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>POP</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Servicio 3Play-Empresa</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Servicio 3Play-Persona</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Uplink(1G,10G,100G)</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>ONTs Com. Total</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>ONTs Com. Activa</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>ONTs PCS Total</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>ONTs PCS Act</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>N&deg;ZS Comercial</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>N&deg;ZS PCS</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Ocupacion ZS Comercial %</center></h5></th>
            <th bgcolor='#0566FC'><h5><center>Ocupacion ZS PCS %</center></h5></th>";
if($perfil==1){            
    $tabla .= "<th bgcolor='#0566FC'><h5><center>Editar</center></h5></th>";
}
    
$tabla .= "</tr></thead>";
            
$i = 1;
while ($row = mysql_fetch_array($result)){
    
    /* $query1 = "SELECT
            OLT_ONT_EQUIPOS3.`server`,
            OLT_ONT_PCS.uplink,
            Sum(OLT_ONT_EQUIPOS3.ont_total) AS ont_total,
            Sum(OLT_ONT_EQUIPOS3.ont_online) AS ont_online_total,
            OLT_ONT_PCS.ont_pcs_act,
        	OLT_ONT_PCS.ont_pcs_total,
            OLT_ONT_PCS.zs_comercial,
        	OLT_ONT_PCS.zs_pcs,
            OLT_ONT_PCS.id
            FROM
            OLT_ONT_EQUIPOS3
            INNER JOIN OLT_ONT_PCS ON OLT_ONT_EQUIPOS3.`server` = OLT_ONT_PCS.`server`
            WHERE 
            OLT_ONT_PCS.`server` = '$row[0]'"; */

    $query1 = "SELECT
            OLT_ONT_PCS.`server`,
            OLT_ONT_PCS.uplink,
            (
                SELECT COUNT(*) 
                FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO 
                WHERE equipo = '$row[0]'
            ) AS total,
            (
                SELECT SUM(CASE WHEN estado = 'online' THEN 1 ELSE 0 END) 
                FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO 
                WHERE equipo = '$row[0]'
            ) AS online,
            OLT_ONT_PCS.ont_pcs_act,
            OLT_ONT_PCS.ont_pcs_total,
            OLT_ONT_PCS.zs_comercial,
            OLT_ONT_PCS.zs_pcs,
            OLT_ONT_PCS.id
        FROM
            OLT_ONT_PCS
        WHERE
            OLT_ONT_PCS.`server` = '$row[0]'";
            
    $result1 = mysql_query($query1) or die ("error ocupacion_ont.php $query");
    $row1 = mysql_fetch_array($result1);
    $query2 = "SELECT
    SUBSTRING_INDEX(MAX(capacidad), ',', -1) as capacidad
FROM (
    SELECT
        GROUP_CONCAT(OLT_PUERTAS_UPLINKS_GB.capacidad ORDER BY OLT_PUERTAS_UPLINKS_GB.capacidad DESC) as capacidad
    FROM
        OLT_PUERTAS_UPLINKS_GB
    WHERE 
        OLT_PUERTAS_UPLINKS_GB.olt ='$row[0]'
) as subquery;";
            
    $result2 = mysql_query($query2) or die ("error ocupacion_ont.php $query2");
    $row2 = mysql_fetch_array($result2);
    $tabla .= "<tr id='$row1[8]'>
                <td><h5><center>$i</center></h5></td>
                <td><h5><center>$row[2]</center></h5></td>
                <td><h5><center>$row[1]</center></h5></td>
                <td><h5><center>$row[0]</center></h5></td>
                <td><h5><center>$row[6]</center></h5></td>
                <td><h5><center>$row[3]</center></h5></td>";
                                $sheet->setCellValue('A'.$contador_excel, $i);
                                $sheet->setCellValue('B'.$contador_excel, $row[2]);
                                $sheet->setCellValue('C'.$contador_excel, $row[1]);
                                $sheet->setCellValue('D'.$contador_excel, $row[0]);
                                $sheet->setCellValue('E'.$contador_excel, $row[6]);
                                $sheet->setCellValue('F'.$contador_excel, $row[3]);
                                //echo "zona laser".$row[6]."<br>";
    if($row[4] == 'No Tiene'){            
        $tabla .= "<td><h5><center>No</center></h5></td>
                  <td><h5><center>No</center></h5></td>";
                  $sheet->setCellValue('G'.$contador_excel, "No");
                  $sheet->setCellValue('H'.$contador_excel, "No");
    }elseif($row[4] == '3Play Empresas/Personas'){
        $tabla .= "<td><h5><center>Si</center></h5></td>
                  <td><h5><center>Si</center></h5></td>";
                  $sheet->setCellValue('G'.$contador_excel, "Si");
                  $sheet->setCellValue('H'.$contador_excel, "Si");
    }elseif($row[4] == '3Play Empresas'){
        $tabla .= "<td><h5><center>Si</center></h5></td>
                  <td><h5><center>No</center></h5></td>";
                  $sheet->setCellValue('G'.$contador_excel, "Si");
                  $sheet->setCellValue('H'.$contador_excel, "No");
    }else{
        $tabla .= "<td><h5><center>No</center></h5></td>
                  <td><h5><center>Si</center></h5></td>";
                  $sheet->setCellValue('G'.$contador_excel, "No");
                  $sheet->setCellValue('H'.$contador_excel, "Si");
    }
    
    
    $tabla .= "<td><h5><center>$row2[0]</center></h5></td>";
                $sheet->setCellValue('I'.$contador_excel, $row2[0]);
    
    if($row2[0] == ''){
        $tabla .= "<td><h5><center>0</center></h5></td>
                    <td><h5><center>0</center></h5></td>
                    <td><h5><center>0</center></h5></td>
                    <td><h5><center>0</center></h5></td>";
                  $sheet->setCellValue('J'.$contador_excel, "0");
                  $sheet->setCellValue('K'.$contador_excel, "0");
                  $sheet->setCellValue('L'.$contador_excel, "0");
                  $sheet->setCellValue('M'.$contador_excel, "0");
    }else{   
        
        $resta_com = $row1[2] - $row1[5];
        $resta_pcs = $row1[3] - $row1[4];
        
        $tabla .= "<td><h5><center>$resta_com</center></h5></td>
                    <td><h5><center>$resta_pcs</center></h5></td>
                    <td><h5><center>$row1[5]</center></h5></td>
                    <td><h5><center>$row1[4]</center></h5></td>";
                  $sheet->setCellValue('J'.$contador_excel, $resta_com);
                  $sheet->setCellValue('K'.$contador_excel, $resta_pcs);
                  $sheet->setCellValue('L'.$contador_excel, $row1[5]);
                  $sheet->setCellValue('M'.$contador_excel, $row1[4]);
    }
    
    if($row[0] == 'OLT-IQUIQUE-1'){            
        $tabla .= "<td><h5><center>4</center></h5></td>
                    <td><h5><center>1</center></h5></td>
                    <td style='background-color:#33FF46'><h5><center>0%</center></h5></td>
                    <td style='background-color:#33FF46'><h5><center>0%</center></h5></td>";
                  $sheet->setCellValue('N'.$contador_excel, "4");
                  $sheet->setCellValue('O'.$contador_excel, "1");
                  $sheet->setCellValue('P'.$contador_excel, "0%");
                  $sheet->setCellValue('Q'.$contador_excel, "0%");
        if($perfil==1){         
            $tabla .= '<td>
                    <center>
                        <button type="button" class="btn btn-sm btn-info center-block  pull-left" id="btnEditocup" title="Editar" data-toggle="modal" data-target="#modalEditarOcupacion"><span class="glyphicon glyphicon-edit"></span></button>
                    </center>
                </td>';  
        }
        $tabla .= "</tr>";
    }else{
        $tabla .= "<td><h5><center>$row1[6]</center></h5></td>
                    <td><h5><center>$row1[7]</center></h5></td>";
                  $sheet->setCellValue('N'.$contador_excel, $row1[6]);
                  $sheet->setCellValue('O'.$contador_excel, $row1[7]);
                    
        $oc_com = round(($row1[3]/($row1[6]*$row[5]))*100,2);   
        $oc_pcs = round(($row1[4]/($row1[7]*8))*100,2);  
        
       if ($oc_com < 69) {
            $tabla .= "<td style='background-color:#33FF46'><h5><center>$oc_com%</center></h5></td>";
            $sheet->setCellValue('P' . $contador_excel, $oc_com . "%");
            /* $sheet->getStyle('P' . $contador_excel)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $sheet->getStyle('P' . $contador_excel)->getFill()->getStartColor()->setRGB('33FF46'); */
        } elseif ($oc_com < 80) {
            $tabla .= "<td style='background-color:#FFFC33'><h5><center>$oc_com%</center></h5></td>";
            $sheet->setCellValue('P' . $contador_excel, $oc_com . "%");
            /* $sheet->getStyle('P' . $contador_excel)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $sheet->getStyle('P' . $contador_excel)->getFill()->getStartColor()->setRGB('FFFC33'); */
        } else {
            $tabla .= "<td style='background-color:#FF5533'><h5><center>$oc_com%</center></h5></td>";
            $sheet->setCellValue('P' . $contador_excel, $oc_com . "%");
            /* $sheet->getStyle('P' . $contador_excel)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $sheet->getStyle('P' . $contador_excel)->getFill()->getStartColor()->setRGB('FF5533'); */
        }
        
        if ($oc_pcs < 69) {
            $tabla .= "<td style='background-color:#33FF46'><h5><center>$oc_pcs%</center></h5></td>";
            $sheet->setCellValue('Q' . $contador_excel, $oc_pcs . "%");
            /* $sheet->getStyle('Q' . $contador_excel)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $sheet->getStyle('Q' . $contador_excel)->getFill()->getStartColor()->setRGB('33FF46'); */
        } elseif ($oc_pcs < 80) {
            $tabla .= "<td style='background-color:#FFFC33'><h5><center>$oc_pcs%</center></h5></td>";
            $sheet->setCellValue('Q' . $contador_excel, $oc_pcs . "%");
            /* $sheet->getStyle('Q' . $contador_excel)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $sheet->getStyle('Q' . $contador_excel)->getFill()->getStartColor()->setRGB('FFFC33'); */
        } else {
            $tabla .= "<td style='background-color:#FF5533'><h5><center>$oc_pcs%</center></h5></td>";
            $sheet->setCellValue('Q' . $contador_excel, $oc_pcs . "%");
            /* $sheet->getStyle('Q' . $contador_excel)->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID);
            $sheet->getStyle('Q' . $contador_excel)->getFill()->getStartColor()->setRGB('FF5533'); */
        }

        
        if($perfil==1){         
            $tabla .= '<td>
                    <center>
                        <button type="button" class="btn btn-sm btn-info center-block  pull-left" id="btnEditocup" title="Editar" data-toggle="modal" data-target="#modalEditarOcupacion"><span class="glyphicon glyphicon-edit"></span></button>
                    </center>
                </td>';  
        }
        
        $tabla .= "</tr>";
    }

    $i++;
    $contador_excel++;
} 

$tabla .= "</table>";
/*
$sfile = "Ocupacion_ONT_por_equipo.xls"; // Ruta del archivo a generar
$fp = fopen($sfile, "w");
fwrite($fp, $tabla);
fclose($fp);
//echo"llego final";*/

$carpetaDestino = '../Ocupacion/Archivos/'; // Cambia esto por tu carpeta específica

$nombreArchivo = 'Ocupacion_ONT_por_equipo.xls';

// Guardar el archivo en la carpeta especificada
$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
$rutaCompleta = $carpetaDestino . $nombreArchivo;
$objWriter->save($rutaCompleta);

mysql_close($conn);

$t2 = "<img src='/contingencia/OLT/menu/logonuevo.png' alt='Imagen fija' class='left-fixed-image'>";
$t = "<a href='../Ocupacion/Archivos/Ocupacion_ONT_por_equipo.xls'>Exportar Archivo</a>";
echo $t2;
echo $t;
echo $tabla;


?>

<script>
$("#tblData").tablesorter({
        theme: 'blue',
	widgets: ["zebra" , "stickyHeaders" , "filter"],
});
</script>
