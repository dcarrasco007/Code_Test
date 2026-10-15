<?php
include ('../../conexion/conexion_db.php');
$conn = mysql_connect($host144_geret, $user144_geret, $pass144_geret);
$conn = mysql_select_db('Aden');

$opt = addslashes(trim($_POST['opt']));
switch($opt) {
    case "addUser":
        $nombre = addslashes(trim($_POST['nombre']));
        $pass = md5($_POST['pass']);
        $perfil = $_POST['permisos'];
        $nombre_user = $_POST['nom_user'];
        $correo = $_POST['correo'];
        $result = mysql_query("SELECT a.usuario FROM OLT_USUARIOS a WHERE a.usuario = '$nombre'") or die("Error ". mysql_error($conn));
        if (!mysql_num_rows($result)) {
            $query = "INSERT INTO OLT_USUARIOS (usuario,pass,perfil,nombre_user,estado,last_connection,correo) values ('$nombre','$pass','$perfil','$nombre_user',1,NOW(),'$correo')";
            mysql_query($query);
            echo "Usuario ingresado!";
        } else {
            echo "Usuario ya existe";
        }
    break;
    case "muestraUser":
        echo muestraUsers();
    break;
    case "editaUser":
        //echo 'edita';
        $nombre = addslashes(trim($_POST['nombre']));
        $perfil = $_POST['permisos'];
        $nom_user = $_POST['nom_user'];
        $correo = $_POST['correo'];
        mysql_query("UPDATE OLT_USUARIOS SET perfil='$perfil',nombre_user='$nom_user', correo='$correo' where usuario='$nombre'");
        echo "Perfil Modificado!";
    break;
    case "changePassw":
        $nombre = addslashes(trim($_POST['nombre']));
        $pass = md5('123456');
        mysql_query("UPDATE OLT_USUARIOS SET pass='$pass' , last_connection=NOW() , estado=1 where usuario='$nombre'");
        echo "Password ha sido cambiada a: 123456";
    break;
    case 'loadInfoUser':
        $id = $_POST['id'];
        $r = mysql_query("SELECT
        OLT_USUARIOS.id_usuario,
        OLT_USUARIOS.usuario,
        OLT_USUARIOS.pass,
        OLT_PERFILES.nombre,
        OLT_PERFILES.id_perfil,
        OLT_USUARIOS.nombre_user,
        OLT_USUARIOS.correo
        FROM
        OLT_USUARIOS
        LEFT JOIN OLT_PERFILES ON OLT_USUARIOS.perfil = OLT_PERFILES.id_perfil WHERE
        OLT_USUARIOS.id_usuario = '$id'");
        $row = mysql_fetch_array($r,MYSQL_NUM);
        $data[] = array(
        "nombre" => $row[1],
        "perfil" => $row[4],
        "nom_user" => $row[5],
        "correo" => $row[6]
        );
        echo json_encode($data);
    break;
    case "eliminaUser":
        $id = $_POST['id'];
        mysql_query("DELETE FROM OLT_USUARIOS WHERE id_usuario='$id'");
        echo "Usuario eliminado!";
    break;
}


function muestraUsers() {
  
    global $conn;
    $query = "SELECT
    OLT_USUARIOS.usuario,
    OLT_PERFILES.nombre,
    OLT_USUARIOS.id_usuario,
    OLT_USUARIOS.nombre_user,
    OLT_USUARIOS.correo,
    OLT_USUARIOS.estado
    FROM
    OLT_USUARIOS
    LEFT JOIN OLT_PERFILES ON OLT_USUARIOS.perfil = OLT_PERFILES.id_perfil ORDER BY OLT_USUARIOS.id_usuario DESC";
    $result = mysql_query($query) or die("Error ". mysql_error($conn));
    $t = '<h3>Lista de usuarios</h3>
            <div style="margin-bottom:10px;">
              <button type="button" class="btn btn-success" onclick="exportaUsuarios(\'todo\')">Exportar Excel (Todo)</button>
              <button type="button" class="btn btn-info" onclick="exportaUsuarios(\'filtro\')">Exportar Excel (Filtro)</button>
            </div>
            <table id="tblData1000" class="table table-striped tablesorter table-bordered text-center">
            <thead class = "bg-primary">
              <tr>
              <th><center>Usuario</center></th>
              <th><center>Nombre</center></th>
              <th><center>Perfil</center></th>
              <th><center>Correo</center></th>
              <th><center>Estado</center></th>
              <th><center>Editar</center></th>
              <th><center>Eliminar</center></th></tr>
              </thead>';
    while ($row = mysql_fetch_array($result,MYSQL_NUM)) {
            $t .= "<tr data-id='$row[2]'><td>$row[0]</td><td>$row[3]</td><td>$row[1]</td><td>$row[4]</td>";
            if($row[5]==1){
                $t .= "<td><span class='label label-success'>Activo</span>";
            }else{
                $t .= "<td><span class='label label-danger'>Inactivo</span>";
            }
            $t .= "<td><a href='#' onclick=editaUsuario('$row[2]')>Editar</a></td>
            <td><a href='#' onclick=eliminaUsuario('$row[2]')>Eliminar</a></td>
            </tr>";
    }
    $t .= '</table>
            </div>'; 
    return $t;
}
mysql_close($conn);
?>