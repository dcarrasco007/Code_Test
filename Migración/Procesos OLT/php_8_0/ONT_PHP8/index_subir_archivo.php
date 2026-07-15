<?php
//$idPag = 'subir_archivo_ONT';
$idPag2 = '313';
//include_once('../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
//checkAccV2(getUser(),$idPag2);
//include('../perfiles/proceso.php');

include ('../../conexion/conexion_db.php');
$conn = mysqli_connect($host144_geret,$user144_geret,$pass144_geret,"Aden") or die("error de conexion: ".mysqli_connect_error()); // Migración PHP 8.0: mysql_* eliminado en PHP 7
?>
<style>
    input[type="file"] {
        display: inline-block !important;
    }
</style>
<script src="../js/jquery-1.10.1.min.js"></script>
<script src="../js/upload/ajaxfileupload.js"></script>

<script type="text/javascript">

    function ajaxFileUpload()
    {
        //alert("entro");
        document.getElementById("envio").disabled = true;
        $("#result_busqueda").html("");
        $("#text_find_title").val("");
        $('#pop').val("");
        $("#loading")
        .ajaxStart(function(){
            $(this).show();
        })
        .ajaxComplete(function(){
            $(this).hide();
        });

        $.ajaxFileUpload
        (
            {
                url:'../js/upload/doajaxfileupload10.php',
                secureuri:false,
                fileElementId:'fileToUpload',
                dataType: 'json',
                data:{name:'logan', id:'id'},
                success: function (data, status)
                {
                    if(typeof(data.error) != 'undefined')
                    {
                        if(data.error != '')
                        {
                            alert(data.error);
                        }else
                        {
                            var nom_doc = data.msg;
                            // alert(nom_doc);
                            nom_doc = nom_doc.split('||');
                            console.log(nom_doc)
                            $.post("../../OLT/ONT/submit_ONT.php", { name_xls: nom_doc[1], 
                            beforeSend: function () {
                                $("#result_busqueda").html('</br></br></br><center><img src="../../images/loadingBig.gif" width="70" height="70" /></center>');
                            } })
                            .done(function(data) {
                                if(data=='OK'){
                                    $("#result_busqueda").html('</br></br>Cambios Realizados.</center>');
                                    
                                }else{
                                     alert(data);
                                    $("#result_busqueda").html('</br></br>Error en el archivo.</center>');
                                }
                                $().reset();
                                
                            });
                        }
                    }
                },
                error: function (data, status, e)
                {
                    alert(e);
                }
            }
        );
       
    }

</script> 
       
<body>
    <center><h1>Subir Archivo ONT</h1></center></br>  
    <form name='frmArchivo' id="frmArchivo" align='center'>  	
        <label>Archivo:</label>               
        
            <img id="loading" src="../../js/upload/loading.gif" style="display:none;" />  
            <center><input id="fileToUpload" type="file" size="45" name="fileToUpload" class="input" accept=".xlsx" /> 			       
            <input type="button" id="envio" value="Subir y actualizar" onclick="ajaxFileUpload()"><br /><br /> 	
            <div class="col-md-12 text-center"><h4>Tiempo de espera desde 15 Minutos.</h4></div>
     <div class="col-md-12 text-center"><h4>No cerrar la ventana mientras carga el Excel.</h4></div></center>
     </form> 
    <center><div id="result_busqueda"></div></center>      
</body>
<!-- <center>En desarrollo...</center>
<center><img src="../ODF/trabajando.gif"></center> -->
