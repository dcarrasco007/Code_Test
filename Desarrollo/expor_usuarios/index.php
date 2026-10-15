<?php
//$idPag = 'gest_user';
$idPag2 = '843';
include_once('../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
//checkAccV2(getUser(),$idPag2);
include ("proceso.php");
include ('../../conexion/conexion_db.php');
$conn = mysqli_connect($host144_geret, $user144_geret, $pass144_geret,'Aden');

?>

<!DOCTYPE html>
<html>
  <head>
  <title></title>
  </head>
  <body>
   <div class="panel panel-primary">
      <div class="panel-heading">
        <h3 class="panel-title">Información</h3>
      </div>
      <div class="panel-body">
        Usuarios <span class='label label-danger'>Inactivo</span> no pueden ingresar al portal. <br>
        Activar Usuario: Se debe restablecer la contraseña al editar usuario.
      </div>
    </div>
  
  <br/>
  <input type="hidden" id="statEdit" value="" />
    <div id="container">
        <form class="form-inline">
            <div class="row">
              <div class="col-xs-1"></div>  
              <div class="col-xs-4">
              <h3>Gesti&oacute;n de usuarios</h3>
               <table class="table">
<tr>
	<td>Usuario:</td>
	<td><input type="text" id="user" placeholder="Usuario.." class="form-control" style="width:200px;" required /><div id="comentario"></div></td>
</tr>
<tr>
	<td>Nombre:</td>
	<td><input type="text" id="nombre" placeholder="Nombre.." class="form-control" style="width:200px;" required /><div id="comentario"></div></td>
</tr>
<tr>
	<td>Correo:</td>
	<td><input type="text" id="correo" placeholder="Correo.." class="form-control" style="width:200px;" required /><div id="comentario"></div></td>
</tr>
<tr>
	<td>Password:</td>
	<td> <input type="text" id="pass" placeholder="Password.." class="form-control" style="width:200px;" required /><div><a href="#" id="resetPassw">Reset</a></div></td>
</tr>
<tr>
	<td>Permisos:</td>
	<td><?php
    
    $query = "SELECT a.nombre, a.id_perfil FROM OLT_PERFILES a";
    $result = mysqli_query($conn,$query) or die(" -- Error1: ".mysqli_error($conn));
    $t = '<select class="form-control" id="muestraPerfil" style="width:200px;">';
    $t .= "<option value=''>Ingrese opcion</option>";
    while ($row = mysqli_fetch_array($result)) {
        $t .= "<option value='$row[1]'>$row[0]</option>";
    }
    $t .= '</select>';
    echo $t;
    ?></td>
</tr>
<tr>
	<td colspan="2"><br/><button type="button" id="addUser" class="btn btn-primary">Guardar</button>
                 <button type="button" id="cancelUser" class="btn btn-primary">Cancelar</button></td>
</tr>
</table>
              </div>
              <div class="col-xs-7" id="resultado" style="max-height:600px; overflow-x:auto;">
               <? echo muestraUsers(); ?>   
              </div>
            </div>
        </form>   
    </div>
    <script type="text/javascript">
  // code javascript
$(function(){
    hideBtnUser();
    hideResetPassw();
    $("#addUser").click(function(){
   
        
        if ($("#statEdit").val()) { // edita usuario
         if (!$("#user").val() || !$("#muestraPerfil").val()) { alert("Complete el campo de permisos!"); return false; }
            $.post("../usuarios/proceso.php", { opt: 'editaUser', nombre: $("#user").val(), permisos: $("#muestraPerfil").val(),nom_user: $("#nombre").val(),correo: $("#correo").val()})
                .done(function(data) {
                   $("#statEdit").val(""); 
                   alert(data);
                   limpiaform();
                   reloadUsersTable();
                   enableLabel();
                   hideBtnUser();
                   hideResetPassw();
            });
        } else {
             if (!$("#user").val() || !$("#nombre").val() || !$("#pass").val() || !$("#muestraPerfil").val()|| !$("#correo").val()) { alert("Rellene todos los campos!"); return false; }
            /// agrega nuevo usuario
            $.post("../usuarios/proceso.php", { opt: 'addUser', nombre: $("#user").val(), pass: $("#pass").val(), permisos: $("#muestraPerfil").val(),nom_user: $("#nombre").val(),correo: $("#correo").val() })
                .done(function(data) {
                alert(data);
                limpiaform();
                reloadUsersTable();
            });  
        } // end if
      });
      
    $("#resetPassw").click(function(){
        $.post("../usuarios/proceso.php", { opt: 'changePassw', nombre: $("#user").val() })
            .done(function(data) {
            alert(data);
            limpiaform();
            $("#statEdit").val("");
            hideResetPassw();
            hideBtnUser();
            enableLabel();
            reloadUsersTable();     
        });  
    });
        
    $("#cancelUser").click(function(){
        limpiaform();
        hideResetPassw();
        hideBtnUser();
        enableLabel();
        $("#statEdit").val("");    
    }); 
    }); // end function
    function limpiaform() {
        $("#user").val("");
        $("#pass").val("");
        $("#nombre").val("");
        $("#correo").val("");
        $("#muestraPerfil").val("");
      }
    function muestraUser(id) {
        $.post("../usuarios/proceso.php", { opt: 'loadInfoUser',id:id },
            function(data) {
            $("#statEdit").val("1");  
            disableLabel();
            $("#user").val(data[0].nombre);
            $("#muestraPerfil").val(data[0].perfil);
            $("#nombre").val(data[0].nom_user);
            $("#correo").val(data[0].correo);
            showBtnUser();
            showResetPassw();
        },"json");
    }
    function disableLabel() {
        $("#user").attr("disabled", true);
        $("#pass").attr("disabled", true);
    }
    function enableLabel() {
        $("#user").attr("disabled", false);
        $("#pass").attr("disabled", false);
    }
    function hideResetPassw() {
        $("#resetPassw").hide();
    }
    function showResetPassw() {
        $("#resetPassw").show();
    }
    function hideBtnUser() {
        $("#cancelUser").hide();
    }
    function showBtnUser() {
        $("#cancelUser").show();
    }
    function editaUsuario(id) {
        //alert(id);
        muestraUser(id);
        $.post("../usuarios/proceso.php", { opt: 'editaUser', id: id })
            .done(function(data) {
            //$("#resultado").html(data);
        });
    }
    function eliminaUsuario(id) {
        $.post("../usuarios/proceso.php", { opt: 'eliminaUser', id: id })
            .done(function(data) {
            alert(data);
            reloadUsersTable();
        });
    }
    function exportaUsuarios(tipo) {
        var form = $('<form method="post" action="../usuarios/export_usuarios.php"></form>');
        form.append($('<input type="hidden" name="tipo" />').val(tipo));
        if (tipo == 'filtro') {
            // filas que el filtro de tablesorter deja visibles
            var ids = [];
            $("#tblData1000 tbody tr").not(".filtered").each(function(){
                if ($(this).css("display") != "none") { ids.push($(this).data("id")); }
            });
            if (!ids.length) { alert("No hay registros para exportar con el filtro actual!"); return false; }
            form.append($('<input type="hidden" name="ids" />').val(ids.join(",")));
        }
        form.appendTo("body").submit().remove();
    }
    function reloadUsersTable() {
        $.post("../usuarios/proceso.php", { opt: 'muestraUser' })
            .done(function(data) {
            $("#resultado").html(data);
            
            $("#tblData1000").tablesorter({
            theme: 'blue',
            widgets: ["stickyHeaders" , "filter"], 
            })
        });
    }

$("#tblData1000").tablesorter({
  theme: 'blue',
  widgets: ["stickyHeaders" , "filter"], 
})

  </script>
  </body>
</html>
<?
mysqli_close($conn);
?>