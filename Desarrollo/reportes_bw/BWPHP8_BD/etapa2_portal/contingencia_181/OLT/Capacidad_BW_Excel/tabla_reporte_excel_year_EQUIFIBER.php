<?php
include ('../../conexion/conexion_db.php');
include ('../Vlan/Vlan_Servicio/funciones.php');
$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");

$anio=$_POST['anio'];
//$anio='2022';

$sql="SELECT `1`, `2`, `3`, `4`, `5`, `6`, `7`, `8`, `9`, `10`, `11`, `12`, `13`, `14`, `15`, `16`, `17`, `18`, `19`, `20`, `21`, `22`, `23`, `24`, `25`, `26`, `27`, `28`, `29`, `30`, `31`, `32`, `33`, `34`, `35`, `36`, `37`, `38`, `39`, `40`, `41`, `42`, `43`, `44`, `45`, `46`, `47`, `48`, `49`, `50`, `51`, `52`, `53`, `54`, `55`, `56`, `57`, `58`, `59`, `60`, `61`, `62`, `63`, `64`, `65`, `66`, `67`, `68`, `69`, `70`, `71`, `72`, `73`, `74`, `75`, `76`, `77`, `78`, `79`, `80`, `81`, `82`, `83`, `84`, `85`, `86`, `87`, `88`, `89`, `90`, `91`, `92`, `93`, `94`, `95`, `96`, `97`, `98`, `99`, `100`, `101`, `102`, `103`, `104`, `105`, `106`, `107`, `108`, `109`, `110`, `111`, `112`, `113`, `114`, `115`, `116`, `117`, `118`, `119`, `120`, `121`, `122`, `123`, `124`, `125`, `126`, `127`, `128`, `129`, `130`, `131`, `132`, `133`, `134`, `135`, `136`, `137`, `138`, `139`, `140`, `141`, `142`, `143`, `144`, `145`, `146`, `147`, `148`, `149`, `150`, `151`, `152`, `153`, `154`, `155`, `156`, `157`, `158`, `159`, `160`, `161`, `162`
FROM OLT_CAPACIDAD_BW_YEAR_EQUIFIBER 
WHERE 
OLT_CAPACIDAD_BW_YEAR_EQUIFIBER.`year` = '$anio'";

$resultd1 = $mysqli->query($sql) or die("error 1 $sql");
$resultd2 = $mysqli->query($sql) or die("error 2 $sql");
$resultd3 = $resultd2;
$resultd3 = $resultd2;
$numero =  mysqli_num_rows($resultd3);
if($numero!=0){
$equipo='';
$cont = 1;

while($row2 = $resultd2->fetch_array(MYSQLI_NUM)){
    if($cont==1){
        $tabla_ont .= "<table align='center' id='tblvlan' class ='table table-bordered table-striped' border='1'>
                    <thead class = 'bg-primary'><tr rowspan='2'>";
        $cont++;
    }
    $tabla_ont .= "<th bgcolor='#0566FC'><center>$row2[0]</center></th>";
}   


$tabla_ont .= "</tr></thead>";

$tabla_ont .= "</tr>";

//$data = $resultd1->fetch_all(MYSQLI_ASSOC);
//$column_names = array_keys($data[1]);

// Obteniendo los resultados
$data = array();
while ($row = $resultd1->fetch_assoc()) {
    $data[] = $row;
}

// Obteniendo los nombres de las columnas
$column_names = array();
if (!empty($data)) {
    $first_row = reset($data);
    $column_names = array_keys($first_row);
}

for ($i = 1; $i < count($column_names); $i++) {
    //$tabla_ont .="<tr><td>" . $column_names[$i] . "</td>"; // Mostrar el nombre de la columna
   
    // Mostrar los datos de cada fila (basados en las columnas de la base de datos)
        $cantidad1=0;
        $cantidad=0;
        $cantidad2=0;
        $cantidad3=0;
        foreach ($data as $row) {
            if($i==1){
                if($cantidad==4){
                    $tabla_ont .=  "<td COLSPAN=3><center>" . $row[$column_names[$i]] . "</center></td>";
                    
                    
                }elseif($cantidad<=3){$tabla_ont .=  "<td>" . $row[$column_names[$i]] . "</td>";
                }else{
                    if($row[$column_names[$i]]!=''){
                        $tabla_ont .=  "<td COLSPAN=3><center>" . $row[$column_names[$i]] . "</center></td>";
                    }   
                } 
            }else{
                if($cantidad1==6){
                    if($row[$column_names[$i]]!=''){
                        $tabla_ont .=  "<td><center>" . number_format($row[$column_names[$i]]*100, 2, ',', ' ') . "%</center></td>";
                    }else{
                        $tabla_ont .=  "<td><center></center></td>";
                    }
                    $cantidad2=0;                
                }elseif($cantidad2==2){
                    if($cantidad3>3){
                        if($row[$column_names[$i]]!=''){
                            $tabla_ont .=  "<td><center>" . number_format($row[$column_names[$i]]*100, 2, ',', ' ') . "%</center></td>";     
                        }else{
                            $tabla_ont .=  "<td><center></center></td>";
                        }           
                        $cantidad2=0;
                    }else{
                        $tabla_ont .=  "<td>" . $row[$column_names[$i]] . "</td>";
                    }
                    $cantidad3++;

                }else{
                    $tabla_ont .=  "<td>" . $row[$column_names[$i]] . "</td>";
                    $cantidad2++;
                }
                
            }
            $cantidad++;  
            $cantidad1++;  
            
        }
        $tabla_ont .=  "</tr>";
    
}
$tabla_ont .='</tabla>'; 
/* 

echo $tabla_ont;
$sfile = "../Reporte_ONT_Diario/Reporte_ONT_Diario_Equipo.xls"; // Ruta del archivo a generar
$fp = fopen($sfile, "w");
fwrite($fp, $tabla_ont);
fclose($fp); */
$nombre_file_nuevo='Capacidades_OLTs_'.$anio.'.xlsx';
// Excel en Archivos/ del servidor o, si no está, armado desde BD (descargar_excel_bw.php)
$t = "&nbsp;&nbsp;&nbsp;&nbsp;<a href='../Capacidad_BW_Excel/descargar_excel_bw.php?tipo=anio&red=equifiber&anio=" . urlencode($anio) . "'>Descargar Archivo</a>";
echo $t;
echo $tabla_ont;
}else{
    echo '<center>Sin registros para el año selecionado...</center>';
}
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