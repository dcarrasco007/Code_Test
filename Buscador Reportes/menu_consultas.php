<?php
/* $idPag = 'menu_principal';
$idPag2 = '310'; */
include_once('../perfiles/getPerfiles.php');
include_once ('../../conexion/conexion_db.php');
//checkAcc(getUser(),$idPag);
//checkAccV2(getUser(),$idPag2);
include('/var/www/html/contingencia/OLT/menu/session_check.php');
include('../perfiles/proceso.php');
include('style.php');
$user = getUser();

$mysqli = new mysqli($host144_geret,$user144_geret,$pass144_geret, 'Aden');
$mysqli ->  set_charset("utf8");
$idPerfil=getIdPerfil();
?>
<!DOCTYPE html>
<html>
    <head>
    	<title>Panel de Gestion Red Gpon</title>
        <meta charset="UTF-8">
        <script type="text/javascript" src="../../js/jquery/jquery-1.12.0.min.js"></script>
        <script src="../../js/tablesorter-2.22.5/js/jquery.tablesorter.js"></script>
        <script src="../../js/tablesorter-2.22.5/js/jquery.tablesorter.widgets.js"></script>

        <link rel="stylesheet" href="../../js/tablesorter-2.22.5/addons/pager/jquery.tablesorter.pager.css">
        <link rel="stylesheet" href="../ONNET/ContadoresEstadisticas/css/Graficos.css" type="text/css">
	    <script src="../../js/tablesorter-2.22.5/addons/pager/jquery.tablesorter.pager.js"></script>

        <script src="../../js/bootstrap/js/bootstrap.min.js"></script>
        <link rel="stylesheet" href="../../js/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="../../js/tablesorter-2.22.5/css/theme.blue.css">

        <!-- datepicker-->
        <script src="../../js/momentjs/moment-with-locales.js"></script>
        <script src="../../js/momentjs/momentjs-business.js"></script>
        <script src="../../js/bootstrap-datedimepicker/bootstrap-datetimepicker.js"></script>
        <link rel="stylesheet" href="../../js/bootstrap-datedimepicker/bootstrap-datetimepicker.css"/>

        <!--<script src="../Lib/Highcharts-9.3.1/code/highcharts.js"></script>
        <script src="../Lib/Highcharts-9.3.1/code/highcharts_export.js"></script>
        <script src="../Lib/Highcharts-9.3.1/code/highcharts-3d.js"></script>-->
        <!--sweetalert2-->
        <script src="../Lib/sweetalert2/sweetalert2.all.min.js"></script>
        <link href="../Lib/sweetalert2/sweetalert2.min.css" rel="stylesheet">

        <script src="../Lib/Highcharts-9.3.1/code/highcharts.js"></script>
        <script src="../Lib/Highcharts-9.3.1/code/modules/exporting.js"></script>
        <script src="../Lib/Highcharts-9.3.1/code/modules/offline-exporting.js"></script>

        <link href="../Select2/select2.min.css" rel="stylesheet" />
        <script src="../Select2/select2.min.js"></script>


        <!--<script src="../Highcharts-4.2.3/js/highcharts.js"></script>
        <script src="../Highcharts-4.2.3/js/highcharts-3d.js"></script>ssh://127/var/www/html/OLT/Lib/Highcharts-9.3.1
        <script src="../../php_lib/Highcharts-4.2.3/js/highcharts.js"></script>
        <script src="../../php_lib/Highcharts-4.2.3/js/highcharts-3d.js"></script>-->
    	<!--<script src="../../php_lib/Highcharts-4.2.3/js/modules/exporting.src.js"></script>-->

        <!--<script src="../../php_lib/Highcharts-4.2.3/js/modules/exporting2.src.js"></script>-->
        <script src="../Lib/Highcharts-9.3.1/code/modules/exporting.src.js"></script>

        <link rel="stylesheet" type="text/css" href="../../js/DataTables/datatables.min.css"/>
        <!-- <link rel="stylesheet" type="text/css" href="../../js/DataTables/dataTables13.min.css"/> -->
        <script type="text/javascript" src="../../js/DataTables/datatables.min.js"></script>
        <!-- <script type="text/javascript" src="../../js/DataTables/dataTables13.min.js"></script> -->

        <!-- [BUSCADOR] Estilos del buscador de reportes -->
        <style>
            #buscadorReportesBox { position: relative; }
            #buscadorReportes { min-width: 260px; }
            .resultados-buscador {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                z-index: 2000;
                width: 460px;
                max-width: 90vw;
                max-height: 70vh;
                overflow-y: auto;
                background: #fff;
                border: 1px solid #ccc;
                border-top: 3px solid #2a6dad;
                border-radius: 0 0 6px 6px;
                box-shadow: 0 6px 16px rgba(0,0,0,.2);
                margin-top: 2px;
                text-align: left;
            }
            .resultados-buscador .rb-header {
                padding: 6px 12px;
                font-size: 11px;
                color: #777;
                background: #f5f7fa;
                border-bottom: 1px solid #eee;
                position: sticky;
                top: 0;
            }
            .resultados-buscador .rb-item {
                display: block;
                padding: 8px 12px;
                border-bottom: 1px solid #f0f0f0;
                cursor: pointer;
                color: #222;
                text-decoration: none;
            }
            .resultados-buscador .rb-item:last-child { border-bottom: none; }
            .resultados-buscador .rb-item:hover,
            .resultados-buscador .rb-item.rb-active {
                background: #eef4fb;
            }
            .resultados-buscador .rb-nombre { font-weight: 600; font-size: 13px; }
            .resultados-buscador .rb-ruta {
                font-size: 11px;
                color: #8a8a8a;
                margin-top: 2px;
            }
            .resultados-buscador .rb-ruta .rb-sep { margin: 0 4px; color: #c0c0c0; }
            .resultados-buscador .rb-empty { padding: 14px 12px; color: #999; font-size: 13px; }
            .resultados-buscador mark {
                background: #ffe680;
                padding: 0 1px;
                border-radius: 2px;
            }

            /* [BUSCADOR CONSULTA] Buscador grande (dentro del modal IA) */
            .bg-textarea {
                font-size: 16px;
                resize: vertical;
                min-height: 90px;
            }
            .bg-acciones {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 4px;
            }
            .bg-acciones .btn { margin-right: 8px; }
            .bg-estado {
                font-size: 14px;
                margin-left: 6px;
                display: inline-block;
            }
            .bg-estado img { vertical-align: middle; }
            .bg-respuesta-wrap { margin-top: 16px; }
            .bg-resp-label { font-weight: 600; display: block; margin-bottom: 6px; }
            .bg-respuesta {
                border: 1px solid #cfd8e3;
                border-radius: 6px;
                background: #fbfdff;
                padding: 14px 16px;
                max-height: 420px;
                overflow: auto;
                font-size: 14px;
                line-height: 1.5;
                color: #222;
            }
            .bg-respuesta table { width: 100%; border-collapse: collapse; margin: 8px 0; }
            .bg-respuesta table td, .bg-respuesta table th { border: 1px solid #dde3ea; padding: 4px 8px; }
            .bg-respuesta img { max-width: 100%; height: auto; }

            /* Zona de espera + cronometro */
            .bg-espera {
                display: flex;
                align-items: center;
                gap: 14px;
                margin-top: 14px;
                padding: 12px 16px;
                background: #eef4fb;
                border: 1px solid #cfe0f3;
                border-radius: 8px;
            }
            .bg-espera .bg-gif { width: 46px; height: 46px; flex: 0 0 auto; }
            .bg-espera-info { display: flex; flex-direction: column; line-height: 1.3; }
            .bg-espera-txt { font-size: 15px; color: #2a6dad; font-weight: 600; }
            .bg-tiempo {
                font-size: 22px;
                font-weight: 700;
                color: #1b4f86;
                font-family: "Courier New", monospace;
                letter-spacing: 1px;
            }

            /* Boton IA del navbar (color distintivo) */
            .btn-ia {
                color: #fff;
                background: linear-gradient(135deg, #7b2ff7 0%, #2a9df4 50%, #16c79a 100%);
                border: none;
                font-weight: 600;
                border-radius: 22px;
                padding: 7px 16px;
                box-shadow: 0 2px 8px rgba(123,47,247,.35);
                transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
            }
            .btn-ia:hover, .btn-ia:focus, .btn-ia:active {
                color: #fff;
                filter: brightness(1.06);
                box-shadow: 0 4px 14px rgba(42,157,244,.45);
                transform: translateY(-1px);
            }
            .btn-ia .ia-logo { vertical-align: -4px; margin-right: 6px; animation: ia-pulse 2.4s ease-in-out infinite; }
            .btn-ia .ia-texto { vertical-align: middle; }
            @keyframes ia-pulse { 0%,100% { opacity: 1; } 50% { opacity: .55; } }

            /* Header del modal IA con el mismo degradado */
            .modal-ia-header {
                background: linear-gradient(135deg, #7b2ff7 0%, #2a9df4 50%, #16c79a 100%);
                color: #fff;
                border-top-left-radius: 6px;
                border-top-right-radius: 6px;
            }
            .modal-ia-header .modal-title { color: #fff; }
            .modal-ia-header .bg-sub { font-weight: normal; opacity: .9; font-size: 12px; }
            .modal-ia-header .close { color: #fff; opacity: .9; text-shadow: none; }
        </style>
    </head>
    <body>
        <input type="hidden" name="user_portal" id="user_portal" value="<?php echo $user; ?>"/>
        <input type="hidden" name="id_perfil" id="id_perfil" value="<?php echo $idPerfil; ?>"/>
    	<div class="row">
    		<div class="col-xs-12">
    			<div class="menu">
    				<nav class="navbar navbar-default" style="padding-top: 0%;">
    					<div class="container-fluid">
    						<div class="navbar-header">
    							<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#bs-example-navbar-collapse-1" aria-expanded="false">
    								<span class="sr-only">Toggle navigation</span>
    								<span class="icon-bar"></span>
    								<span class="icon-bar"></span>
    								<span class="icon-bar"></span>
    							</button>
    							<div>
    								<!-- <img id="logoentel" src="../../NEWFRONT/logonuevo.png" width="60"></img> -->
                                    <img id="logoentel" src="logonuevo.png" width="60"></img>
    	                        </div>
    						</div>
    						<div>
                                <a class="navbar-brand" href="menu_nuevo.php">Panel Gesti&oacute;n Red Gpon</a>
    						</div>
                            <?php
                            $sql_menu_1 = "SELECT
                                m1.id,
                                m1.nombre AS Menu_1,
                                m1.principal,
                                m1.url
                                
                            FROM
                                OLT_ACCESO_PERFIL_PRINCIPAL AS a1
                            LEFT JOIN OLT_MENU_1 AS m1 ON
                                a1.id_olt_menu_1 = m1.id
                            WHERE
                                a1.id_perfil = $idPerfil
                                GROUP BY Menu_1 ORDER BY posicion";
                            $result = $mysqli->query($sql_menu_1) or die("error $sql_menu_1");
                            $contador=0;
                            $menu1='';
                            $menu2='';
                            $menu3='';
                            $menu4='';
                            // [BUSCADOR] Indice plano de reportes (hojas con url real) con su ruta jerarquica.
                            // Se llena dentro de los mismos bucles que arman el menu, por lo que respeta los permisos del perfil.
                            $reportes=array();
                            $menu_principal.="<div class='collapse navbar-collapse' id='bs-example-navbar-collapse-1'>
    							            <ul class='nav navbar-nav'>";
                            while ($row = $result->fetch_array(MYSQLI_NUM)) {
                                   //menu 1
                                    $menu1=$row[1];
                                    $menu_principal.="<li class='dropdown'>
                                                <a href='$row[3]' class='dropdown-toggle' data-toggle='dropdown' role='button' aria-haspopup='true' aria-expanded='false'>$menu1<span class='caret'></span></a>
                                                <ul class='dropdown-menu' role='tablist'>";
                                    $sql_menu_2 = "SELECT
                                                    m2.id,
                                                    m2.nombre AS Menu_2,
                                                    m2.principal,
                                                    m2.url
                                                    
                                                FROM
                                                    OLT_ACCESO_PERFIL_PRINCIPAL AS a1
                                                LEFT JOIN OLT_MENU_1 AS m1 ON
                                                    a1.id_olt_menu_1 = m1.id
                                                LEFT JOIN OLT_MENU_2 AS m2 ON
                                                    a1.id_olt_menu_2 = m2.id
                                                WHERE
                                                    a1.id_perfil = $idPerfil
                                                    AND 
                                                    m2.id_olt_menu_1 = $row[0]
                                                    GROUP BY id ORDER BY m2.pocision";
                                            $result2 = $mysqli->query($sql_menu_2) or die("error $sql_menu_2"); 
                                while ($row2 = $result2->fetch_array(MYSQLI_NUM)) {
                                   // menu 2
                                    
                                    if($row2[3]!='#'){
                                        $menu2.='<li role="presentation"><a onclick="cambioPag(';
                                        $menu2.="'".$row2[3]."'";
                                        $menu2.=');" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">';
                                        $menu2.=$row2[1];
                                        $menu2.='</a></li>';
                                        // [BUSCADOR] Reporte hoja en nivel 2
                                        $reportes[]=array('nombre'=>$row2[1],'url'=>$row2[3],'ruta'=>array($menu1));

                                    }else{
                                        $menu2.="<li class='dropdown-submenu'>
                                        <a href='$row2[3]' class='dropdown-toggle' data-toggle='dropdown'>$row2[1]</b></a>
                                        <ul class='dropdown-menu'>";
                                        $sql_menu_3 = "SELECT
                                                        m3.id,
                                                        m3.nombre AS Menu_3,
                                                        m3.principal,
                                                        m3.url
                                                        
                                                    FROM
                                                        OLT_ACCESO_PERFIL_PRINCIPAL AS a1
                                                    LEFT JOIN OLT_MENU_1 AS m1 ON
                                                        a1.id_olt_menu_1 = m1.id
                                                    LEFT JOIN OLT_MENU_2 AS m2 ON
                                                        a1.id_olt_menu_2 = m2.id
                                                    LEFT JOIN OLT_MENU_3 AS m3 ON
                                                        a1.id_olt_menu_3 = m3.id
                                                    WHERE
                                                        a1.id_perfil = $idPerfil
                                                        AND 
                                                        m3.id_olt_menu_2 = $row2[0]
                                                        GROUP BY id ORDER BY m3.posicion";
                                                $result3 = $mysqli->query($sql_menu_3) or die("error $sql_menu_3");
                                        while ($row3 = $result3->fetch_array(MYSQLI_NUM)) {
                                            // menu 3

                                            if($row3[3]!='#'){
                                                $menu3.='<li role="presentation"><a onclick="cambioPag(';
                                                $menu3.="'".$row3[3]."'";
                                                $menu3.=');" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">';
                                                $menu3.=$row3[1];
                                                $menu3.='</a></li>';
                                                // [BUSCADOR] Reporte hoja en nivel 3
                                                $reportes[]=array('nombre'=>$row3[1],'url'=>$row3[3],'ruta'=>array($menu1,$row2[1]));
                                            }else{
                                                //$menu3.='</a></li>';
                                                        $menu3.="<li class='dropdown-submenu'>
                                                        <a href='$row3[3]' class='dropdown-toggle' data-toggle='dropdown'>$row3[1]</b></a>
                                                        <ul class='dropdown-menu'>";
                                                        $sql_menu_4 = "SELECT
                                                                m4.id,
                                                                m4.nombre AS Menu_4,
                                                                m4.principal,
                                                                m4.url
                                                                
                                                            FROM
                                                                OLT_ACCESO_PERFIL_PRINCIPAL AS a1
                                                            LEFT JOIN OLT_MENU_1 AS m1 ON
                                                                a1.id_olt_menu_1 = m1.id
                                                            LEFT JOIN OLT_MENU_2 AS m2 ON
                                                                a1.id_olt_menu_2 = m2.id
                                                            LEFT JOIN OLT_MENU_3 AS m3 ON
                                                                a1.id_olt_menu_3 = m3.id
                                                            LEFT JOIN OLT_MENU_4 AS m4 ON
                                                                a1.id_olt_menu_4 = m4.id
                                                            WHERE
                                                                a1.id_perfil = $idPerfil
                                                                AND 
                                                                m4.id_olt_menu_3 = $row3[0]
                                                                GROUP BY id ORDER BY m4.posicion";
                                                        $result4 = $mysqli->query($sql_menu_4) or die("error $sql_menu_4");
                                                while ($row4 = $result4->fetch_array(MYSQLI_NUM)) {
                                                    // menu 4
                                                        //$menu3.='</a></li>';
                                                        /* $menu4.="<li class='dropdown-submenu'>
                                                        <a href='$row4[3]' class='dropdown-toggle' data-toggle='dropdown'>$row4[1]</b></a></li>"; */
                                                        $menu4.='<li role="presentation"><a onclick="cambioPag(';
                                                        $menu4.="'".$row4[3]."'";
                                                        $menu4.=');" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false">';
                                                        $menu4.=$row4[1];
                                                        $menu4.='</a></li>';
                                                        // [BUSCADOR] Reporte hoja en nivel 4
                                                        $reportes[]=array('nombre'=>$row4[1],'url'=>$row4[3],'ruta'=>array($menu1,$row2[1],$row3[1]));
                                                }
                                                $menu3.=$menu4;
                                                $menu4='';
                                                $menu3.='</ul>';
                                            }
                                            
                                            
                                        }
                                        $menu2.=$menu3;
                                        $menu3='';
                                        $menu2.='</ul>';
                                    }
                                     

                                } 
                                $menu_principal.=$menu2;
                                $menu2='';
                                $menu_principal.='</li></ul>';
                            }   
                            $menu_principal.='</li></ul>';
                            mysqli_close($mysqli);
                            
                            echo $menu_principal;

                            // [BUSCADOR] Compatible con PHP 5.3.
                            // Asegura UTF-8 valido en un texto. En PHP 5.3 json_encode() devuelve false
                            // si recibe UN solo byte no-UTF8, lo que dejaria el indice vacio. Por eso saneamos.
                            if (!function_exists('bsc_utf8')) {
                                function bsc_utf8($s){
                                    if ($s === null) return '';
                                    if (function_exists('mb_check_encoding')) {
                                        if (mb_check_encoding($s, 'UTF-8')) return $s;            // ya es UTF-8 valido
                                        return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');    // venia en latin1
                                    }
                                    if (function_exists('iconv')) {
                                        $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $s);
                                        if ($clean !== false && $clean === $s) return $s;        // ya era UTF-8 valido
                                        $conv = @iconv('ISO-8859-1', 'UTF-8//IGNORE', $s);
                                        return ($conv !== false) ? $conv : (string)$clean;
                                    }
                                    return $s; // ultimo recurso
                                }
                            }
                            // Preparo el indice para JS: primero garantizo UTF-8, luego decodifico
                            // entidades HTML (&oacute; -> o con tilde) para mostrar/buscar correctamente.
                            $reportes_js=array();
                            foreach($reportes as $r){
                                $ruta=array();
                                foreach($r['ruta'] as $p){
                                    $ruta[]=html_entity_decode(bsc_utf8($p), ENT_QUOTES, 'UTF-8');
                                }
                                $reportes_js[]=array(
                                    'nombre'=>html_entity_decode(bsc_utf8($r['nombre']), ENT_QUOTES, 'UTF-8'),
                                    'url'=>bsc_utf8($r['url']),
                                    'ruta'=>$ruta
                                );
                            }
                            // En PHP 5.3 json_encode SIEMPRE escapa los acentos a \uXXXX (ASCII puro),
                            // por lo que funciona aunque la pagina se sirva como latin1/ISO-8859-1.
                            // Construyo flags sin asumir constantes nuevas (todas validadas con defined()).
                            $reportes_flags = 0;
                            if (defined('JSON_HEX_TAG'))                 { $reportes_flags |= JSON_HEX_TAG; }                 // evita que "</script>" rompa el bloque
                            if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) { $reportes_flags |= JSON_INVALID_UTF8_SUBSTITUTE; } // PHP 7.2+ (extra seguridad)
                            $reportes_json = json_encode($reportes_js, $reportes_flags);
                            if ($reportes_json === false || $reportes_json === null) { $reportes_json = '[]'; } // nunca dejar el JS vacio
                            ?>
                            <!-- [BUSCADOR] Buscador de reportes -->
                            <form class="navbar-form navbar-left form-search form-inline" role="search" onsubmit="return false;" autocomplete="off">
                                <div class="form-group input-group" id="buscadorReportesBox">
                                    <span class="input-group-addon"><span class="glyphicon glyphicon-search"></span></span>
                                    <input type="text" id="buscadorReportes" placeholder="Buscar reporte (ej: uplink, alarmas, ONT...)" class="form-control" size="32" autocomplete="off">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-default" id="btnLimpiarBuscador" title="Limpiar"><span class="glyphicon glyphicon-remove"></span></button>
                                    </span>
                                    <!-- Resultados -->
                                    <div id="resultadosBuscador" class="resultados-buscador"></div>
                                </div>
                            </form>
                            <script>
                                // [BUSCADOR] Indice completo de reportes accesibles para el perfil actual.
                                window.REPORTES_INDEX = <?php echo $reportes_json; ?>;
                            </script>

                            <!-- [BUSCADOR CONSULTA] Boton fijo de IA en el navbar (derecha) que abre el modal -->
                            <div class="navbar-form navbar-right" style="margin-right:6px;">
                                <button type="button" class="btn btn-ia" data-toggle="modal" data-target="#modalIA" title="Abrir Consulta IA">
                                    <svg class="ia-logo" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                                        <path d="M12 2l1.6 4.4L18 8l-4.4 1.6L12 14l-1.6-4.4L6 8l4.4-1.6L12 2z" fill="currentColor"/>
                                        <path d="M18.5 13l.9 2.4 2.4.9-2.4.9-.9 2.4-.9-2.4-2.4-.9 2.4-.9.9-2.4z" fill="currentColor" opacity=".85"/>
                                        <path d="M5 14l.7 1.8L7.5 16.5l-1.8.7L5 19l-.7-1.8L2.5 16.5l1.8-.7L5 14z" fill="currentColor" opacity=".7"/>
                                    </svg>
                                    <span class="ia-texto">Consulta IA</span>
                                </button>
                            </div>

                                <div>
    						                                   
        					
                                
                                <div class="pull-right" id="usuario">
                                    <div class="pull-right">
                                    
                                    
                                    <a class="navbar-brand" href="#"> Semana: <?php echo date("W");?></a>&nbsp;&nbsp;
                                    
                                        <img src="/contingencia/img/desarrollado_trans.png" style="width:85px;" />.
                                    </div>
                                </div>
    						</div>
    					</div>
    				</nav>
    			</div>
    		</div>
    	</div>
        <!-- [BUSCADOR CONSULTA] Modal con el buscador grande (se abre con el boton IA del navbar) -->
        <div class="modal fade" id="modalIA" role="dialog" aria-labelledby="modalIATitulo">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header modal-ia-header">
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                        <h4 class="modal-title" id="modalIATitulo">
                            <svg class="ia-logo" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" style="vertical-align:-4px;">
                                <path d="M12 2l1.6 4.4L18 8l-4.4 1.6L12 14l-1.6-4.4L6 8l4.4-1.6L12 2z" fill="currentColor"/>
                                <path d="M18.5 13l.9 2.4 2.4.9-2.4.9-.9 2.4-.9-2.4-2.4-.9 2.4-.9.9-2.4z" fill="currentColor" opacity=".85"/>
                                <path d="M5 14l.7 1.8L7.5 16.5l-1.8.7L5 19l-.7-1.8L2.5 16.5l1.8-.7L5 14z" fill="currentColor" opacity=".7"/>
                            </svg>
                            Consulta IA
                            <small class="bg-sub">&nbsp;Escribe tu consulta, env&iacute;ala y espera la respuesta.</small>
                        </h4>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <textarea id="bgConsulta" class="form-control bg-textarea" rows="4"
                                      placeholder="Escribe aqu&iacute; tu consulta... (Ctrl+Enter para enviar)"></textarea>
                        </div>
                        <div class="bg-acciones">
                            <button type="button" class="btn btn-primary btn-lg" id="bgEnviar">
                                <span class="glyphicon glyphicon-send"></span> Enviar consulta
                            </button>
                            <button type="button" class="btn btn-default btn-lg" id="bgLimpiar">Limpiar</button>
                            <span id="bgEstado" class="bg-estado"></span>
                        </div>

                        <!-- Zona de espera: gif de carga + cronometro de tiempo transcurrido -->
                        <div id="bgEspera" class="bg-espera" style="display:none;">
                            <img src="/contingencia/images/loadingBig.gif" class="bg-gif" alt="Cargando...">
                            <div class="bg-espera-info">
                                <span class="bg-espera-txt" id="bgEsperaTxt">Esperando respuesta...</span>
                                <span class="bg-tiempo" id="bgTiempo">00:00</span>
                            </div>
                        </div>

                        <div id="bgRespuestaWrap" class="bg-respuesta-wrap" style="display:none;">
                            <label class="bg-resp-label">Respuesta:</label>
                            <div id="bgRespuesta" class="bg-respuesta"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <div class="center-block">
                <div class="row">
                    <div class="col-xs-12" id="panelhome">
                        <div id="cont" class="contenido"></div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Modal -->
        <div class="modal fade" id="myModal" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio">
              <!-- Modal content-->
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><center>Uplinks</center></h4>
                </div>
                <div class="modal-body" id="contenedor_uplinks">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
              </div>

            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="myModalAlarmas" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio1">
              <!-- Modal content-->
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><center>Alarmas</center></h4>
                </div>
                <div class="modal-body" id="contenedor_alarmas">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
              </div>

            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="myModalTarjetas" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio2">
              <!-- Modal content-->
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><center>Detalle Tarjetas</center></h4>
                </div>
                <div class="modal-body" id="contenedor_tarjetas">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
              </div>

            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="myModalVlan" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio2">
              <!-- Modal content-->
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><center>Detalle Vlan</center></h4>
                </div>
                <div class="modal-body" id="contenedor_vlan">
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
              </div>

            </div>
        </div>

        <!-- Modal -->
        <div class="modal fade" id="myModalTipoAlarma" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio">
              <!-- Modal content-->
              <div class="modal-content">
                <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><center>Detalle Alarmas</center></h4>
                </div>
                <div class="modal-body" id="contenedor_tipo_alarma">
                </div>
                <div class="modal-footer">
            		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
            	</div>
              </div>

            </div>
        </div>

        <!-- Modal ALARMAS GENERAL -->
        <div class="modal fade" id="myModalAlarmaGeneral" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio1">
              <!-- Modal content-->
              <div class="modal-content">
              <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="contenedor_alarma_general">
                </div>
                <div class="modal-footer">
            		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
            	</div>
              </div>

            </div>
        </div>

        <!-- Modal TARJETAS GENERAL -->
        <div class="modal fade" id="myModalTarjetaGeneral" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio">
              <!-- Modal content-->
              <div class="modal-content">
              <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="contenedor_tarjeta_general">
                </div>
                <div class="modal-footer">
            		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
            	</div>
              </div>

            </div>
        </div>

        <!-- Modal ONT DETALLE -->
        <div class="modal fade" id="myModalOntDetalle" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio1">
              <!-- Modal content-->
              <div class="modal-content">
              <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="contenedor_ont_detalle">
                </div>
                <div class="modal-footer">
            		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
            	</div>
              </div>

            </div>
        </div>

                    <!-- Modal TEMPERATURA / CPU -->
        <div class="modal fade" id="myModalTemperaturaCpu" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio1">
              <!-- Modal content-->
              <div class="modal-content">
              <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body" id="contenedor_temperatura_cpu">
                </div>
                <div class="modal-footer">
            		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
            	</div>
              </div>

            </div>
        </div>
        
        <!-- Modal DETALLE UPTIME -->
        <div class="modal fade" id="myModalUptime" role="dialog">
            <div class="modal-dialog modal-lg"  id="mdialTamanio1">
              <!-- Modal content-->
              <div class="modal-content">
              <div class="modal-header">
                  <button type="button" class="close" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><center>Detalle Puerta Uptime</center></h4>
                </div>
                <div class="modal-body" id="contenedor_uptime">
                </div>
                <div class="modal-footer">
            		<button class="btn" data-dismiss="modal" aria-hidden="true">Close</button>
            	</div>
              </div>

            </div>
        </div>

       <!-- Modal Editar -->
        <div id="modalEditar" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>Edici&oacute;n de Informaci&oacute;n</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label class="col-xs-4">Equipo OLT</label>
                                    <div class="input-group col-xs-8">
                                        <input type="hidden" class="form-control" name="id_uplink" id="id_uplink" value="" required/>
                                        <input class="form-control" name="equipo" id="equipo" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Puerto</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="puerto" id="puerto" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Uplinks</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="uplinks" id="uplinks" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">ODF Sitio</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="odf_sitio" id="odf_sitio" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">ODF URA</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="odf_ura" id="odf_ura" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">C&oacute;digo de Servicio</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="co_servicio" id="co_servicio" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Gbs</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="Gbs" id="Gbs" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Gbs Total</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="Gbstot" id="Gbstot" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Estado</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="estado" id="estado" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqEditar">Guardar Cambios</button>
                                <!--button type="button" class="btn btn-danger" data-dismiss="modal" id="eqEliminar">Eliminar</button-->
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Editar -->
        <div id="modalEditarEquipos" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>Edici&oacute;n de Informaci&oacute;n</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label class="col-xs-4">Equipo OLT</label>
                                    <div class="input-group col-xs-8">
                                        <input type="hidden" class="form-control" name="id_equipo" id="id_equipo" value="" required/>
                                        <input class="form-control" name="equipo_edit" id="equipo_edit" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">POP</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="pop" id="pop" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Modelo</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="modelo" id="modelo" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Version</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="version" id="version" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">IP Equipo</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="ip_eq" id="ip_eq" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">SW</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="sw" id="sw" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">PATCH</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="patch" id="patch" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Region</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="region" id="region" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Comuna</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="comuna" id="comuna" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Migracion Red</label>
                                    <div class="input-group col-xs-8">
                                        <select class="form-control" id="migracion_red" name="migracion_red">
                                        <option value="SI" selected>SI</option><option value="NO">NO</option></select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Migracion Clientes</label>
                                    <div class="input-group col-xs-8">
                                        <select class="form-control" id="migracion_clientes" name="migracion_clientes">
                                        <option value="SI" selected>SI</option><option value="NO">NO</option></select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">TIPO</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="tipo" id="tipo" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Ubicaci&oacute;n</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="ubicacion" id="ubicacion" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Servicio</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="serv" id="serv" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Condicion de Acceso</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="acc" id="acc" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Conexion</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="conex" id="conex" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">ZONA LASER</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="laser" id="laser" value="" required/>
                                        <!--input type="hidden" class="form-control" name="ip_old" id="eqip_old" value="" required/-->
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqEditarEquipo">Guardar Cambios</button>
                                <!--button type="button" class="btn btn-danger" data-dismiss="modal" id="eqEliminar">Eliminar</button-->
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Modal Tarjeta -->
        <div id="modalEditarTarjeta" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>Edici&oacute;n de Informaci&oacute;n</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label class="col-xs-4">Nombre</label>
                                    <div class="input-group col-xs-8">
                                        <input type="hidden" class="form-control" name="id_tarjeta" id="id_tarjeta" value="" required/>
                                        <input class="form-control" name="nom_equipo" id="nom_equipo" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Tipo</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="tipo_equipo" id="tipo_equipo" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">C&oacute;digo SAP</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="cod_sap" id="cod_sap" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqEditarTarjeta">Guardar Cambios</button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal Tarjeta -->
        <!-- Modal IP_SAFE -->
        <div id="modalEditarIP" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>Edici&oacute;n de Informaci&oacute;n</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label class="col-xs-4">OLT</label>
                                    <div class="input-group col-xs-8">
                                        <input type="hidden" class="form-control" name="id_ip" id="id_ip" value="" required/>
                                        <input class="form-control" name="nom_equipo" id="id_olt" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">DIRECCION</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="direccion" id="direccion" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">COMUNA</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="comuna1" id="comuna1" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">IP SAFE</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="ip_safe" id="ip_safe" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">CONSOLA</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="consola" id="consola" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqEditarIPSAFE">Guardar Cambios</button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal Tarjeta -->
        <div id="modalEliminarTarjeta" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>ELIMINAR EQUIPAMIENTO</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                
                                        <input type="hidden" class="form-control" name="id_tarjeta" id="id_tarjeta" value="" required/>
                                
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <center>
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqEliminarTarjeta">  SI  </button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">CANCELAR</button>
                            </div></center>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal Tarjeta -->
        <div id="modalEliminarIP" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>ELIMINAR IP SAFE</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                
                                        <input type="hidden" class="form-control" name="id_ip_2" id="id_ip_2" value="" required/>
                                
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <center>
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqEliminarIPSAFE">  SI  </button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">CANCELAR</button>
                            </div></center>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal crear Tarjeta -->
        <div id="modalCrearTarjeta" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>Crear Registro Nuevo</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label class="col-xs-4">Nombre</label>
                                    <div class="input-group col-xs-8">
                                        <input type="hidden" class="form-control" name="id_tarjeta" id="id_tarjeta" value="" required/>
                                        <input class="form-control" name="nom_equipo" id="nom_equipo" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">Tipo</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="tipo_equipo" id="tipo_equipo" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">C&oacute;digo SAP</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="cod_sap" id="cod_sap" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqCreaTarjeta">Guardar Cambios</button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal crear IP_SAFE -->
        <div id="modalCrearIPSAFE" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>Crear Registro Nuevo</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3">
                            </div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label class="col-xs-4">OLT</label>
                                    <div class="input-group col-xs-8">
                                        <input type="hidden" class="form-control" name="id_tarjeta" id="id_tarjeta" value="" required/>
                                        <input class="form-control" name="olt" id="olt" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">DIRECCION</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="direccion" id="direccion" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">COMUNA</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="comuna2" id="comuna2" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">IP SAFE</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="ipsafe" id="ipsafe" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">CONSOLA</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="consola" id="consola" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-3">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqCreaIP">Guardar Cambios</button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal Editar -->
        <div id="modalEditarOcupacion" class="modal fade" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <!-- Modal content-->
                <form data-toggle="validator" role="form" id="modal_equipos_editar">
                    <div class="modal-content">
                        <div class="modal-header bg-primary">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h3 class="modal-title"><center>Edici&oacute;n de Informaci&oacute;n</center></h3>
                        </div>
                        <div class="modal-body row">
                            <div class="col-xs-3"></div>
                            <div class="col-xs-6">
                                <div class="form-group">
                                    <label class="col-xs-4">UPLINK</label>
                                    <div class="input-group col-xs-8">
                                        <input type="hidden" class="form-control" name="id_ocup" id="id_ocup" value="" required/>
                                        <input class="form-control" name="uplink" id="uplink" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">ONTs PCS Total</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="ontpcstotal" id="ontpcstotal" value="" required />
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">ONTs PCS Activas</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="ontpcsactiva" id="ontpcsactiva" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">N&deg; ZS Comercial</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="zscomercial" id="zscomercial" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="col-xs-4">N&deg; ZS PCS</label>
                                    <div class="input-group col-xs-8">
                                        <input class="form-control" name="zspcs" id="zspcs" value="" required/>
                                        <div class="help-block with-errors"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xs-3"></div>
                        </div>
                        <div class="modal-footer">
                            <div id="footAccion" class="show">
                                <button type="button" class="btn btn-info" data-dismiss="modal" id="eqEditarOcup">Guardar Cambios</button>
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <style>
            #mdialTamanio{
              width: 80% !important;
            }
            #mdialTamanio1{
              width: 90% !important;
            }
            #mdialTamanio2{
              width: 40% !important;
            }
            #mdialTamanio4{
              width: 100% !important;
            }
        </style>

        <script>
        (function($, window) {
            'use strict';

            var MultiModal = function(element) {
                this.$element = $(element);
                this.modalCount = 0;
            };

            MultiModal.BASE_ZINDEX = 1040;

            MultiModal.prototype.show = function(target) {
                var that = this;
                var $target = $(target);
                var modalIndex = that.modalCount++;

                $target.css('z-index', MultiModal.BASE_ZINDEX + (modalIndex * 20) + 10);

                // Bootstrap triggers the show event at the beginning of the show function and before
                // the modal backdrop element has been created. The timeout here allows the modal
                // show function to complete, after which the modal backdrop will have been created
                // and appended to the DOM.
                window.setTimeout(function() {
                    // we only want one backdrop; hide any extras
                    if(modalIndex > 0)
                        $('.modal-backdrop').not(':first').addClass('hidden');

                    that.adjustBackdrop();
                });
            };

            MultiModal.prototype.hidden = function(target) {
                this.modalCount--;

                if(this.modalCount) {
                   this.adjustBackdrop();
                    // bootstrap removes the modal-open class when a modal is closed; add it back
                    $('body').addClass('modal-open');
                }
            };

            MultiModal.prototype.adjustBackdrop = function() {
                var modalIndex = this.modalCount - 1;
                $('.modal-backdrop:first').css('z-index', MultiModal.BASE_ZINDEX + (modalIndex * 20));
            };

            function Plugin(method, target) {
                return this.each(function() {
                    var $this = $(this);
                    var data = $this.data('multi-modal-plugin');

                    if(!data)
                        $this.data('multi-modal-plugin', (data = new MultiModal(this)));

                    if(method)
                        data[method](target);
                });
            }

            $.fn.multiModal = Plugin;
            $.fn.multiModal.Constructor = MultiModal;

            /*$(document).on('show.bs.modal', function(e) {
                //$(document).multiModal('show', e.target);
            });

            $(document).on('hidden.bs.modal', function(e) {
                $(document).multiModal('hidden', e.target);
            });*/
        }(jQuery, window));

        function muestraUplinks(id,val){
            $.post( "../Uplinks/proceso_uplink.php", {
                op:1,
                id:id,
                val:val
                })
            .done(function(data){
                $("#contenedor_uplinks").html(data);
                $('#myModal').modal('show');
            });
        }
        function muestraUplinksV2(id,val){
            $.post( "../Uplinks/proceso_uplink.php", {
                op:3,
                id:id,
                val:val
                })
            .done(function(data){
                $("#contenedor_uplinks").html(data);
                $('#myModal').modal('show');
            });
        }

        function muestraAlarmas(id){
            $.post( "../Alarmas/muestra_alarmas.php", {
                id:id
                })
            .done(function(data){
                $("#contenedor_alarmas").html(data);
                $('#myModalAlarmas').modal('show');
            });
        }

        function muestraVlan(id){
            $.post( "../Vlan/detalle_vlan.php", {
                id:id
                })
            .done(function(data){
                $("#contenedor_vlan").html(data);
                $('#myModalVlan').modal('show');
            });
        }

        function muestraTarjetas(id){
            $.post( "../Tarjetas/detalle_tarjetas.php", {
                id:id
                })
            .done(function(data){
                $("#contenedor_tarjetas").html(data);
                $('#myModalTarjetas').modal('show');
            });
        }
        
        function muestraUptime(id){
            $.post( "../Uptime/detalle_uptime.php", {
                id:id
                })
            .done(function(data){
                $("#contenedor_uptime").html(data);
                $('#myModalUptime').modal('show');
            });
        }

        function muestraTipoAlarmas(id,tipo){
            $.post( "../Alarmas/detalle_alarmas.php", {
                id:id,
                tipo:tipo
                })
            .done(function(data){
                $("#contenedor_tipo_alarma").html(data);
                $('#myModalAlarmaGeneral').modal('hide');
                $('#myModalTipoAlarma').modal('show');
            });
        }

        function muestraAlarmasDetalle(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $(".contenido").load("../Alarmas/critical.php");
            }else if(tipo == 2){
                 $(".contenido").load("../Alarmas/major.php");
            }else if(tipo == 3){
                $(".contenido").load("../Alarmas/minor.php");
            }else if(tipo == 4){
                $(".contenido").load("../Alarmas/warning.php");
            }
        }
        function muestraAlarmasDetalleONNET(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $(".contenido").load("../Alarmas/critical_ONNET.php");
            }else if(tipo == 2){
                 $(".contenido").load("../Alarmas/major_ONNET.php");
            }else if(tipo == 3){
                $(".contenido").load("../Alarmas/minor_ONNET.php");
            }else if(tipo == 4){
                $(".contenido").load("../Alarmas/warning_ONNET.php");
            }
        }
        function muestraAlarmasDetalleEQUIFIBER(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $(".contenido").load("../Alarmas/critical_EQUIFIBER.php");
            }else if(tipo == 2){
                 $(".contenido").load("../Alarmas/major_EQUIFIBER.php");
            }else if(tipo == 3){
                $(".contenido").load("../Alarmas/minor_EQUIFIBER.php");
            }else if(tipo == 4){
                $(".contenido").load("../Alarmas/warning_EQUIFIBER.php");
            }
        }
        function muestraTarjetaDetalleTipo(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo.php?tipo="+tipo);
            }else if(tipo == 2){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo.php?tipo="+tipo);
            }else if(tipo == 3){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo.php?tipo="+tipo);
            }else if(tipo == 4){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo.php?tipo="+tipo);
            }else if(tipo == 5){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo.php?tipo="+tipo);
            }
        }
        function muestraTarjetaDetalleTipoONNET(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_ONNET.php?tipo="+tipo);
            }else if(tipo == 2){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_ONNET.php?tipo="+tipo);
            }else if(tipo == 3){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_ONNET.php?tipo="+tipo);
            }else if(tipo == 4){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_ONNET.php?tipo="+tipo);
            }else if(tipo == 5){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_ONNET.php?tipo="+tipo);
            }
        }
        function muestraTarjetaDetalleTipoEQUIFIBER(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_EQUIFIBER.php?tipo="+tipo);
            }else if(tipo == 2){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_EQUIFIBER.php?tipo="+tipo);
            }else if(tipo == 3){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_EQUIFIBER.php?tipo="+tipo);
            }else if(tipo == 4){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_EQUIFIBER.php?tipo="+tipo);
            }else if(tipo == 5){
                $(".contenido").load("../Tarjetas/tarjeta_general_tipo_EQUIFIBER.php?tipo="+tipo);
            }
        }

        function muestraOntCantidad(tipo){
            var tipo = tipo;
            if(tipo == 1){
                //$(".contenido").load("../ONT/ont_nombres_general.php");
            }else if(tipo == 2){
                $(".contenido").load("../ONT/ont_nombres_general.php");
            }else if(tipo == 3){
                //$(".contenido").load("../ONT/ont_nombres_general.php");
            }
        }
        function muestraOntCantidadONNET(tipo){
            var tipo = tipo;
            if(tipo == 1){
                //$(".contenido").load("../ONT/ont_nombres_general.php");
            }else if(tipo == 2){
                $(".contenido").load("../ONT/ont_nombres_general_ONNET.php");
            }else if(tipo == 3){
                //$(".contenido").load("../ONT/ont_nombres_general.php");
            }
        }
        function muestraOntCantidadEQUIFIBER(tipo){
            var tipo = tipo;
            if(tipo == 1){
                //$(".contenido").load("../ONT/ont_nombres_general.php");
            }else if(tipo == 2){
                $(".contenido").load("../ONT/ont_nombres_general_EQUIFIBER.php");
            }else if(tipo == 3){
                //$(".contenido").load("../ONT/ont_nombres_general.php");
            }
        }
        function muestraODFComuna(comuna){
            var comuna = comuna;
            var comuna = comuna.toString();
            var comuna = comuna.replace(/\s+/g, '_');
            //var comuna = comuna.replace(' ','_');
            //alert(comuna);
            var url='../ODF/ver_odf_comuna.php?comuna='+comuna;
            //alert(url);
             if(comuna){
                $(".contenido").load(url);
            }else{
                alert('Comuna no seleccionada');
            } 
        }
        function muestraODFComunaONNET(comuna){
            var comuna = comuna;
            var comuna = comuna.toString();
            var comuna = comuna.replace(/\s+/g, '_');
            //var comuna = comuna.replace(' ','_');
            //alert(comuna);
            var url='../ODF/ver_odf_comuna_ONNET.php?comuna='+comuna;
            //alert(url);
             if(comuna){
                $(".contenido").load(url);
            }else{
                alert('Comuna no seleccionada');
            } 
        }
        function muestraODFComunaEQUIFIBER(comuna){
            var comuna = comuna;
            var comuna = comuna.toString();
            var comuna = comuna.replace(/\s+/g, '_');
            //var comuna = comuna.replace(' ','_');
            //alert(comuna);
            var url='../ODF/ver_odf_comuna_EQUIFIBER.php?comuna='+comuna;
            //alert(url);
             if(comuna){
                $(".contenido").load(url);
            }else{
                alert('Comuna no seleccionada');
            } 
        }
        function seleccionarComunaODF(){
            
            var url='../ODF/odf_comuna.php';
            //alert(url);
                $(".contenido").load(url);
            
        }
        function seleccionarComunaODFONNET(){
            
            var url='../ODF/odf_comuna_ONNET.php';
            //alert(url);
                $(".contenido").load(url);
            
        }
        function seleccionarComunaODFEQUIFIBER(){
            
            var url='../ODF/odf_comuna_EQUIFIBER.php';
            //alert(url);
                $(".contenido").load(url);
            
        }
        function muestraTarjetaDetalle(tipo,op){
            var tipo = tipo;
            var op = op;
            //alert (tipo+'---'+op);
            $.post( "../Tarjetas/detalle_tipo.php", {
                tipo:tipo,
                op:op
                })
            .done(function(data){
                $("#contenedor_tarjeta_general").html(data);
                $('#myModalTarjetaGeneral').modal('show');
            });
        }
        function muestraTarjetaDetalleONNET(tipo,op){
            var tipo = tipo;
            var op = op;
            //alert (tipo+'---'+op);
            $.post( "../Tarjetas/detalle_tipo_ONNET.php", {
                tipo:tipo,
                op:op
                })
            .done(function(data){
                $("#contenedor_tarjeta_general").html(data);
                $('#myModalTarjetaGeneral').modal('show');
            });
        }
        function muestraTarjetaDetalleEQUIFIBER(tipo,op){
            var tipo = tipo;
            var op = op;
            //alert (tipo+'---'+op);
            $.post( "../Tarjetas/detalle_tipo_EQUIFIBER.php", {
                tipo:tipo,
                op:op
                })
            .done(function(data){
                $("#contenedor_tarjeta_general").html(data);
                $('#myModalTarjetaGeneral').modal('show');
            });
        }

        function muestraOntDetalle(op){

            var op = op;

            $.ajax({
                type:'POST',
                url:'../ONT/detalle_tipo.php',
                data : { op : op },
                dataType: 'html',
                beforeSend: function(){
                    // Muestra el modal
                    $('#myModalOntDetalle').modal('show');
                    // Carga el spinner en el modal
                    var $loader = $('<div class="spinner"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');
                    $("#contenedor_ont_detalle").html($loader);
                },
                success: function(data){
                    $("#contenedor_ont_detalle").html(data);
                }

            });
            // $(this).on('shown.bs.modal', function (e) {
            //   alert("Evento que se dispara cuando el modal está cargado!")
            // });
            // $.post( "../ONT/detalle_tipo.php", {
            //     op:op
            //     })
            // .done(function(data){
            //     $("#contenedor_ont_detalle").html(data);
            //     $('#myModalOntDetalle').modal('show');
            // });
        }
        function muestraOntDetalleONNET(op){

            var op = op;

            $.ajax({
                type:'POST',
                url:'../ONT/detalle_tipo_ONNET.php',
                data : { op : op },
                dataType: 'html',
                beforeSend: function(){
                    // Muestra el modal
                    $('#myModalOntDetalle').modal('show');
                    // Carga el spinner en el modal
                    var $loader = $('<div class="spinner"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');
                    $("#contenedor_ont_detalle").html($loader);
                },
                success: function(data){
                    $("#contenedor_ont_detalle").html(data);
                }

            });
            // $(this).on('shown.bs.modal', function (e) {
            //   alert("Evento que se dispara cuando el modal está cargado!")
            // });
            // $.post( "../ONT/detalle_tipo.php", {
            //     op:op
            //     })
            // .done(function(data){
            //     $("#contenedor_ont_detalle").html(data);
            //     $('#myModalOntDetalle').modal('show');
            // });
        }
        function muestraOntDetalleEQUIFIBER(op){

            var op = op;

            $.ajax({
                type:'POST',
                url:'../ONT/detalle_tipo_EQUIFIBER.php',
                data : { op : op },
                dataType: 'html',
                beforeSend: function(){
                    // Muestra el modal
                    $('#myModalOntDetalle').modal('show');
                    // Carga el spinner en el modal
                    var $loader = $('<div class="spinner"><div class="rect1"></div><div class="rect2"></div><div class="rect3"></div><div class="rect4"></div><div class="rect5"></div></div>');
                    $("#contenedor_ont_detalle").html($loader);
                },
                success: function(data){
                    $("#contenedor_ont_detalle").html(data);
                }

            });
            // $(this).on('shown.bs.modal', function (e) {
            //   alert("Evento que se dispara cuando el modal está cargado!")
            // });
            // $.post( "../ONT/detalle_tipo.php", {
            //     op:op
            //     })
            // .done(function(data){
            //     $("#contenedor_ont_detalle").html(data);
            //     $('#myModalOntDetalle').modal('show');
            // });
            }
        
        function muestraTemperaturaCpu(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $.post( "../Temp_Cpu/info_reporte.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 2){
                $.post( "../Temp_Cpu/info_reporte_verde.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 3){
                $.post( "../Temp_Cpu/info_reporte.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 4){
                $.post( "../Temp_Cpu/info_reporte_verde.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }
        }
        function muestraTemperaturaCpuONNET(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $.post( "../Temp_Cpu/info_reporte_ONNET.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 2){
                $.post( "../Temp_Cpu/info_reporte_verde_ONNET.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 3){
                $.post( "../Temp_Cpu/info_reporte_ONNET.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 4){
                $.post( "../Temp_Cpu/info_reporte_verde_ONNET.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }
        }
        function muestraTemperaturaCpuEQUIFIBER(tipo){
            var tipo = tipo;
            if(tipo == 1){
                $.post( "../Temp_Cpu/info_reporte_EQUIFIBER.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 2){
                $.post( "../Temp_Cpu/info_reporte_verde_EQUIFIBER.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 3){
                $.post( "../Temp_Cpu/info_reporte_EQUIFIBER.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }else if(tipo == 4){
                $.post( "../Temp_Cpu/info_reporte_verde_EQUIFIBER.php", {
                    tipo:tipo
                    })
                .done(function(data){
                    $("#contenedor_temperatura_cpu").html(data);
                    $('#myModalTemperaturaCpu').modal('show');
                });
            }
        }
        function cambioPag(url){
            $('.contenido').hide(); 
            $('.contenido').html('<div class="loading" ><center><img src="/contingencia/images/loadingBig.gif" alt="Cargando..." style="width: 100px; height: 100px;"></center></div>');
            $('.contenido').show();
            // Carga la URL y muestra su contenido
            $('.contenido').load(url, function() {
                // Muestra el contenido cargado y elimina el elemento de carga
                $('.contenido').show();
                $('.loading').remove();
            });
        }			
             
        /* ===================== [BUSCADOR] Buscador de reportes ===================== */
        (function(){
            var INDEX = window.REPORTES_INDEX || [];

            // Normaliza: minusculas y sin acentos, para una busqueda flexible.
            function norm(s){
                return (s || '')
                    .toString()
                    .toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            }

            // Pre-calcula el texto de busqueda de cada reporte (nombre + ruta completa).
            INDEX.forEach(function(r){
                r._buscar = norm(r.nombre + ' ' + (r.ruta ? r.ruta.join(' ') : ''));
            });

            function escapeHtml(s){
                return (s || '').replace(/[&<>"']/g, function(c){
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
                });
            }

            // Resaltado: marca coincidencias usando la cadena normalizada como guia
            // (asi resalta aunque el usuario escriba sin acentos).
            function marcar(texto, terminos){
                if(!terminos.length) return escapeHtml(texto);
                var n = norm(texto);
                var rangos = [];
                terminos.forEach(function(t){
                    if(!t) return;
                    var idx = 0;
                    while((idx = n.indexOf(t, idx)) !== -1){
                        rangos.push([idx, idx + t.length]);
                        idx += t.length;
                    }
                });
                if(!rangos.length) return escapeHtml(texto);
                rangos.sort(function(a,b){ return a[0]-b[0]; });
                var fus = [rangos[0]];
                for(var i=1;i<rangos.length;i++){
                    var ult = fus[fus.length-1];
                    if(rangos[i][0] <= ult[1]){ ult[1] = Math.max(ult[1], rangos[i][1]); }
                    else { fus.push(rangos[i]); }
                }
                var res = '', pos = 0;
                fus.forEach(function(r){
                    res += escapeHtml(texto.slice(pos, r[0]));
                    res += '<mark>' + escapeHtml(texto.slice(r[0], r[1])) + '</mark>';
                    pos = r[1];
                });
                res += escapeHtml(texto.slice(pos));
                return res;
            }

            var $input   = $('#buscadorReportes');
            var $box     = $('#resultadosBuscador');
            var $limpiar = $('#btnLimpiarBuscador');
            var activo   = -1;   // indice del resultado seleccionado por teclado
            var visibles = [];   // resultados actualmente mostrados

            function cerrar(){ $box.hide().empty(); activo = -1; visibles = []; }

            function buscar(q){
                var terminos = norm(q).split(/\s+/).filter(Boolean);
                if(!terminos.length){ cerrar(); return; }

                // Un reporte coincide si TODOS los terminos aparecen (en nombre o ruta).
                var res = INDEX.filter(function(r){
                    return terminos.every(function(t){ return r._buscar.indexOf(t) !== -1; });
                });

                visibles = res.slice(0, 80); // limita para no saturar la lista
                activo = -1;

                if(!res.length){
                    $box.html('<div class="rb-empty">Sin resultados para &laquo;' + escapeHtml(q) + '&raquo;</div>').show();
                    return;
                }

                var html = '<div class="rb-header">' + res.length + ' reporte(s) encontrado(s)'
                         + (res.length > visibles.length ? ' (mostrando ' + visibles.length + ')' : '') + '</div>';

                visibles.forEach(function(r, i){
                    var ruta = (r.ruta || []).map(function(p){ return marcar(p, terminos); })
                                             .join('<span class="rb-sep">&rsaquo;</span>');
                    html += '<a class="rb-item" data-i="' + i + '">'
                          + '<div class="rb-nombre">' + marcar(r.nombre, terminos) + '</div>'
                          + '<div class="rb-ruta">' + ruta + '</div>'
                          + '</a>';
                });

                $box.html(html).show();
            }

            function abrir(i){
                if(i < 0 || i >= visibles.length) return;
                cambioPag(visibles[i].url);   // carga el reporte en el body sin recargar
                cerrar();
                $input.val('').blur();
            }

            function setActivo(nuevo){
                var $items = $box.find('.rb-item');
                if(!$items.length) return;
                if(nuevo < 0) nuevo = $items.length - 1;
                if(nuevo >= $items.length) nuevo = 0;
                activo = nuevo;
                $items.removeClass('rb-active');
                var $a = $items.eq(activo).addClass('rb-active');
                var el = $a[0];
                if(el && el.scrollIntoView) el.scrollIntoView({block:'nearest'});
            }

            // --- Eventos ---
            $input.on('input', function(){ buscar(this.value); });
            $input.on('focus', function(){ if(this.value.trim()) buscar(this.value); });

            $input.on('keydown', function(e){
                if(e.key === 'ArrowDown'){ e.preventDefault(); setActivo(activo + 1); }
                else if(e.key === 'ArrowUp'){ e.preventDefault(); setActivo(activo - 1); }
                else if(e.key === 'Enter'){ e.preventDefault(); abrir(activo >= 0 ? activo : 0); }
                else if(e.key === 'Escape'){ cerrar(); }
            });

            $box.on('click', '.rb-item', function(){ abrir(parseInt($(this).data('i'), 10)); });
            $limpiar.on('click', function(){ $input.val(''); cerrar(); $input.focus(); });

            // Cierra al hacer clic fuera del buscador.
            $(document).on('mousedown', function(e){
                if(!$(e.target).closest('#buscadorReportesBox').length){ cerrar(); }
            });

            // Atajo: Ctrl+K para enfocar el buscador.
            $(document).on('keydown', function(e){
                if(e.ctrlKey && (e.key === 'k' || e.key === 'K')){
                    e.preventDefault(); $input.focus().select();
                }
            });
        })();
        /* =================== [BUSCADOR] Fin buscador de reportes =================== */

        /* ================ [BUSCADOR CONSULTA] Consulta asincrona via BD ================ */
        (function(){
            var POLL_INTERVAL = 3000;     // cada 3 segundos
            var POLL_TIMEOUT  = 120000;   // 2 minutos maximo de espera
            var timer    = null;          // intervalo de polling
            var cronTimer = null;         // intervalo del cronometro (1s)
            var started  = 0;             // marca de tiempo de inicio
            var currentId = null;

            var $consulta  = $('#bgConsulta');
            var $enviar    = $('#bgEnviar');
            var $limpiar   = $('#bgLimpiar');
            var $estado    = $('#bgEstado');
            var $respWrap  = $('#bgRespuestaWrap');
            var $resp      = $('#bgRespuesta');
            var $espera    = $('#bgEspera');
            var $esperaTxt = $('#bgEsperaTxt');
            var $tiempo    = $('#bgTiempo');

            function ahora(){ return (new Date()).getTime(); } // compatible navegadores antiguos

            function setEstado(html, clase){
                $estado.removeClass('text-info text-success text-danger text-warning');
                if(clase){ $estado.addClass(clase); }
                $estado.html(html || '');
            }

            // Formatea milisegundos transcurridos como mm:ss
            function formatoTiempo(ms){
                var totalSeg = Math.floor(ms / 1000);
                var min = Math.floor(totalSeg / 60);
                var seg = totalSeg % 60;
                return (min < 10 ? '0' : '') + min + ':' + (seg < 10 ? '0' : '') + seg;
            }

            // --- Cronometro de tiempo transcurrido ---
            function iniciarCronometro(){
                detenerCronometro();
                $tiempo.text('00:00');
                cronTimer = setInterval(function(){
                    $tiempo.text(formatoTiempo(ahora() - started));
                }, 1000);
            }
            function detenerCronometro(){ if(cronTimer){ clearInterval(cronTimer); cronTimer = null; } }

            function mostrarEspera(txt){ $esperaTxt.text(txt || 'Esperando respuesta...'); $espera.show(); }
            function ocultarEspera(){ $espera.hide(); }

            function detenerPolling(){ if(timer){ clearInterval(timer); timer = null; } }

            // Detiene todo (polling + cronometro) y reactiva el boton.
            function finalizar(){
                detenerPolling();
                detenerCronometro();
                $enviar.prop('disabled', false);
            }

            // Si el backend indica que no hay sesion/perfil, redirige al login.
            function manejarAuth(data){
                if(data && data.auth === false){
                    finalizar();
                    ocultarEspera();
                    setEstado('Sesion no iniciada. Redirigiendo al login...', 'text-danger');
                    window.location.href = (data.redirect || '../menu/login.php');
                    return true;
                }
                return false;
            }

            function enviar(){
                var q = $.trim($consulta.val());
                if(!q){ setEstado('Escribe una consulta antes de enviar.', 'text-danger'); return; }

                finalizar();
                currentId = null;
                $enviar.prop('disabled', true);
                $respWrap.hide();
                $resp.empty();
                setEstado('Enviando consulta...', 'text-info');

                $.post('buscador_guardar.php', { consulta: q }, function(data){
                    if(manejarAuth(data)){ return; }                    // sesion caida -> login
                    if(!data || !data.ok){
                        setEstado('Error: ' + ((data && data.error) || 'no se pudo guardar la consulta.'), 'text-danger');
                        $enviar.prop('disabled', false);
                        return;
                    }
                    currentId = data.id;
                    started = ahora();
                    setEstado('Consulta enviada (#' + currentId + ').', 'text-info');
                    mostrarEspera('Esperando respuesta...');
                    iniciarCronometro();
                    timer = setInterval(poll, POLL_INTERVAL);
                    poll(); // primer intento inmediato
                }, 'json').fail(function(){
                    setEstado('Error de red al enviar la consulta.', 'text-danger');
                    $enviar.prop('disabled', false);
                });
            }

            function poll(){
                if(!currentId){ return; }

                if(ahora() - started > POLL_TIMEOUT){
                    finalizar();
                    ocultarEspera();
                    setEstado('Se agoto el tiempo de espera. La respuesta aun no esta lista; intenta nuevamente.', 'text-warning');
                    return;
                }

                $.post('buscador_respuesta.php', { id: currentId }, function(data){
                    if(manejarAuth(data)){ return; }                    // sesion caida -> login
                    if(!data || !data.ok){ return; }                    // sigue intentando en el proximo ciclo

                    if(data.estado === 'listo'){
                        finalizar();
                        ocultarEspera();
                        $resp.html(data.respuesta || '<em>(respuesta vacia)</em>');  // se renderiza como HTML
                        $respWrap.show();
                        setEstado('Respuesta recibida en ' + formatoTiempo(ahora() - started) + '.', 'text-success');
                    } else if(data.estado === 'error'){
                        finalizar();
                        ocultarEspera();
                        setEstado('El proceso devolvio un error.', 'text-danger');
                        if(data.respuesta){ $resp.html(data.respuesta); $respWrap.show(); }
                    } else {
                        // pendiente / procesando -> actualiza el texto de espera (el cronometro corre solo)
                        mostrarEspera('Procesando (' + data.estado + ')...');
                    }
                }, 'json');
            }

            function limpiar(){
                finalizar();
                currentId = null;
                $consulta.val('');
                $resp.empty();
                $respWrap.hide();
                ocultarEspera();
                setEstado('');
                $consulta.focus();
            }

            $enviar.on('click', enviar);
            $limpiar.on('click', limpiar);

            // Ctrl+Enter (o Cmd+Enter) dentro del textarea envia la consulta.
            $consulta.on('keydown', function(e){
                if((e.ctrlKey || e.metaKey) && (e.key === 'Enter' || e.keyCode === 13)){
                    e.preventDefault();
                    enviar();
                }
            });

            // Al abrir el modal IA, enfoca el textarea para escribir de inmediato.
            $('#modalIA').on('shown.bs.modal', function(){ $consulta.focus(); });
        })();
        /* ============== [BUSCADOR CONSULTA] Fin consulta asincrona via BD ============== */

        function volver(){
            $(".contenido").load("../Tarjetas/tarjeta_general.php");
        }
        function volverONNET(){
            $(".contenido").load("../Tarjetas/tarjeta_general_ONNET.php");
        }
        function volverEQUIFIBER(){
            $(".contenido").load("../Tarjetas/tarjeta_general_EQUIFIBER.php");
        }
        function volver2(){
            $(".contenido").load("../Alarmas/alarmas_general.php");
        }
        function volver2_ONNET(){
            $(".contenido").load("../Alarmas/alarmas_general_ONNET.php");
        }
        function volver2_EQUIFIBER(){
            $(".contenido").load("../Alarmas/alarmas_general_EQUIFIBER.php");
        }
        function volver3(){
            $(".contenido").load("../ONT/ont_general.php");
        }
        function volver3_ONNET(){
            $(".contenido").load("../ONT/ont_general_ONNET_KPI.php");
        }
        function volver3_EQUIFIBER(){
            $(".contenido").load("../ONT/ont_general_EQUIFIBER_KPI.php");
        }

        function eliminar(id,id_e,id_f){
            var bool=confirm("Seguro de eliminar registro?");
            if(bool){
                $.post( "../Uplinks/proceso_uplink.php", {
                    op:2,
                    id:id,
                    id_e: id_e,
                    id_f: id_f
                    })
                .done(function(data){
                    if(data){
                        alert('Uplink eliminada.');
                        muestraUplinks(data);
                    }
                });
            }else{
                alert("cancelo la solicitud");
            }
        }


        $('#modalEditarEquipos').on('show.bs.modal', function (e) {

            if(e.relatedTarget){
                
                $button = e.relatedTarget;
                $row = $($button).closest('tr');
                var id = $($row).find('td:eq(0)').text();
                var equipo = $($row).find('td:eq(1)').text();
                var pop = $($row).find('td:eq(2)').text();
                var modelo = $($row).find('td:eq(3)').text();
                var version = $($row).find('td:eq(4)').text();
                var ip = $($row).find('td:eq(5)').text();
                var sw = $($row).find('td:eq(6)').text();
                var patch = $($row).find('td:eq(7)').text();
                var region = $($row).find('td:eq(8)').text();
                var comuna = $($row).find('td:eq(9)').text();
                var migracion_red = $($row).find('td:eq(10)').text();
                var migracion_clientes = $($row).find('td:eq(11)').text();
                var tipo = $($row).find('td:eq(13)').text();
                var serv = $($row).find('td:eq(15)').text();
                var condicion = $($row).find('td:eq(16)').text();
                var conexion = $($row).find('td:eq(17)').text();
                var laser = $($row).find('td:eq(12)').text();
                var ubicacion = $($row).find('td:eq(14)').text();
                


                $('#id_equipo').val(id);
                $('#equipo_edit').val(equipo);
                $('#modelo').val(modelo);
                $('#ip_eq').val(ip);
                $('#modelo').val(modelo);
                $('#sw').val(sw);
                $('#patch').val(patch);
                $('#region').val(region);
                $('#tipo').val(tipo);
                $('#pop').val(pop);
                $('#serv').val(serv);
                $('#acc').val(condicion);
                $('#conex').val(conexion);
                $('#comuna').val(comuna);
                $('#version').val(version);
                $('#laser').val(laser);
                $('#ubicacion').val(ubicacion);
                $('#migracion_red').val(migracion_red);
                $('#migracion_clientes').val(migracion_clientes);
            }
        });

        $('#modalEditarOcupacion').on('show.bs.modal', function (e) {

            if(e.relatedTarget){

                $button = e.relatedTarget;
                $row = $($button).closest('tr');
                var id = $($row).attr('id');
                var tot = $($row).find('td:eq(11)').text();
                var act = $($row).find('td:eq(12)').text();
                var com = $($row).find('td:eq(13)').text();
                var pcs = $($row).find('td:eq(14)').text();       
                var uplink = $($row).find('td:eq(8)').text();

                $('#id_ocup').val(id);
                $('#ontpcstotal').val(tot);
                $('#ontpcsactiva').val(act);
                $('#zscomercial').val(com);
                $('#zspcs').val(pcs);               
                $('#uplink').val(uplink);

            }
        });

        $('#eqEditarOcup').on('click', function(){
            var rowUpdate = $('#modalEditarOcupacion form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../Ocupacion/edita_ocupacion.php',
                data    : {editarInfo: rowUpdate},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos editados.');
                       $(".contenido").load('../Ocupacion/ocupacion_ont.php');
                    }else{
                       alert('Problemas al editar.');
                    }
                }
            });
        });

        $('#eqEditarEquipo').on('click', function(){
            var rowUpdate = $('#modalEditarEquipos form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../edita_server.php',
                data    : {editarInfo: rowUpdate, op : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data==1){
                       alert('Datos editados.');
                       $(".contenido").load('../server.php');
                    }else{
                       alert('Problemas al editar.');
                    }
                }
            });
        });

        $('#modalEditarTarjeta').on('show.bs.modal', function (e) {

            if(e.relatedTarget){

                $button = e.relatedTarget;
                $row = $($button).closest('tr');
                var id = $($row).find('td:eq(4)').text();
                var nombre = $($row).find('td:eq(1)').text();
                var tipo = $($row).find('td:eq(2)').text();
                var cod_sap = $($row).find('td:eq(3)').text();

                $('#id_tarjeta').val(id);
                $('#nom_equipo').val(nombre);
                $('#tipo_equipo').val(tipo);
                $('#cod_sap').val(cod_sap);
            }
        });
        $('#modalGrafico').on('show.bs.modal', function (e) {

        if(e.relatedTarget){

            $button = e.relatedTarget;
            $row = $($button).closest('tr');
            var id = $($row).find('td:eq(4)').text();
            var nombre = $($row).find('td:eq(1)').text();
            var tipo = $($row).find('td:eq(2)').text();
            var cod_sap = $($row).find('td:eq(3)').text();

            $('#id_tarjeta').val(id);
            $('#nom_equipo').val(nombre);
            $('#tipo_equipo').val(tipo);
            $('#cod_sap').val(cod_sap);
        }
        });
        $('#modalEditarIP').on('show.bs.modal', function (e) {

            if(e.relatedTarget){

                $button = e.relatedTarget;
                $row = $($button).closest('tr');
                var id = $($row).find('td:eq(6)').text();
                var nombre = $($row).find('td:eq(1)').text();
                var direccion = $($row).find('td:eq(2)').text();
                var comuna = $($row).find('td:eq(3)').text();
                var ip = $($row).find('td:eq(4)').text();
                var consola = $($row).find('td:eq(5)').text();
                console.log(comuna);
                $('#id_ip').val(id);
                $('#id_olt').val(nombre);
                $('#direccion').val(direccion);
                $('#comuna1').val(comuna);
                $('#ip_safe').val(ip);
                $('#consola').val(consola);
            }
        });
        $('#modalEliminarTarjeta').on('show.bs.modal', function (e) {

            if(e.relatedTarget){

                $button = e.relatedTarget;
                $row = $($button).closest('tr');
                var id = $($row).find('td:eq(4)').text();
                $('#id_tarjeta').val(id);
                
            }
        });
        
        $('#modalEliminarIP').on('show.bs.modal', function (e) {

            if(e.relatedTarget){

                $button = e.relatedTarget;
                $row = $($button).closest('tr');
                var id = $($row).find('td:eq(6)').text();
                console.log(id);
                $('#id_ip_2').val(id);
                
            }
            });
        $('#eqEditarTarjeta').on('click', function(){
            var rowUpdate = $('#modalEditarTarjeta form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../Tarjetas/edita_tarjeta.php',
                data    : {editarInfo: rowUpdate, opcion : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos editados.');
                       $(".contenido").load('../Tarjetas/mantenedor.php');
                    }else{
                       alert('Problemas al editar.');
                    }
                }
            });
        });
        
        $('#eqEditarIPSAFE').on('click', function(){
            var rowUpdate = $('#modalEditarIP form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../ip_safe/edita_ip.php',
                data    : {editarInfo: rowUpdate, opcion : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos editados.');
                       $(".contenido").load('../ip_safe/mantenedor.php');
                    }else{
                       alert('Problemas al editar.');
                    }
                }
            });
        });
        $('#eqEliminarTarjeta').on('click', function(){
            var rowUpdate = $('#modalEditarTarjeta form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../Tarjetas/elimina_tarjeta.php',
                data    : {editarInfo: rowUpdate, opcion : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos editados.');
                       $(".contenido").load('../Tarjetas/mantenedor.php');
                    }else{
                       alert('Problemas al eliminar.');
                    }
                }
            });
        });
        $('#eqEliminarIPSAFE').on('click', function(){
            var rowUpdate = $('#modalEliminarIP form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../ip_safe/elimina_ip.php',
                data    : {editarInfo: rowUpdate, opcion : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos Eliminados.');
                       $(".contenido").load('../ip_safe/mantenedor.php');
                    }else{
                       alert('Problemas al eliminar.');
                    }
                }
            });
        });
        $('#eqCreaTarjeta').on('click', function(){
            var rowUpdate = $('#modalCrearTarjeta form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../Tarjetas/crea_tarjeta.php',
                data    : {editarInfo: rowUpdate, opcion : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos editados.');
                       $(".contenido").load('../Tarjetas/mantenedor.php');
                    }else{
                       alert('Problemas al editar.');
                    }
                }
            });
        });
        $('#eqCreaIP').on('click', function(){
            var rowUpdate = $('#modalCrearIPSAFE form').serializeArray();
            console.log(rowUpdate);
            $.ajax({
                type    : 'POST',
                url     : '../ip_safe/crea_ip.php',
                data    : {editarInfo: rowUpdate, opcion : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos editados.');
                       $(".contenido").load('../ip_safe/mantenedor.php');
                    }else{
                       alert('Problemas al editar.');
                    }
                }
            });
        });
        function eliminarEquipo(id){
            var bool=confirm("Seguro de eliminar registro?");
            if(bool){
                $.post( "../edita_server.php", {
                    op:2,
                    id:id,
                    })
                .done(function(data){
                    if(data){
                        alert('Equipo eliminado.');
                        $(".contenido").load('../server.php');
                    }else{
                        alert('nadaaa');
                    }
                });
            }else{
                alert("cancelo la solicitud");
            }
        }


        $('#modalEditar').on('show.bs.modal', function (e) {

            if(e.relatedTarget){

                $button = e.relatedTarget;
                $row = $($button).closest('tr');
                var id = $($row).attr('id');
                var equipo = $($row).find('td:eq(0)').text();
                var puerto = $($row).find('td:eq(1)').text();
                var uplinks = $($row).find('td:eq(2)').text();
                var odf_sitio = $($row).find('td:eq(3)').text();
                var odf_ura = $($row).find('td:eq(4)').text();
                var cod_ser = $($row).find('td:eq(5)').text();
                var gbs = $($row).find('td:eq(6)').text();
                var gbstot = $($row).find('td:eq(7)').text();
                var estado = $($row).find('td:eq(8)').text();

                $('#id_uplink').val(id);
                $('#equipo').val(equipo);
                $('#puerto').val(puerto);
                $('#uplinks').val(uplinks);
                $('#odf_sitio').val(odf_sitio);
                $('#odf_ura').val(odf_ura);
                $('#co_servicio').val(cod_ser);
                $('#Gbs').val(gbs);
                $('#Gbstot').val(gbstot);
                $('#estado').val(estado);
            }
        });


        $('#eqEditar').on('click', function(){
            var rowUpdate = $('#modalEditar form').serializeArray();
            $.ajax({
                type    : 'POST',
                url     : '../Uplinks/editar_uplinks.php',
                data    : {editarInfo: rowUpdate, opcion : 1},
                dataType: 'json',
                async   : true,
                success : function(data){
                    if(data){
                       alert('Datos editados.');
                       $('#myModal').modal('hide');
                    }else{
                       alert('Problemas al editar.');
                    }
                }
            });
        });
        
        var usuario_portal = document.getElementById('user_portal').value;id_perfil
        var id_perfil = document.getElementById('id_perfil').value;
        if(usuario_portal == 'desa' || usuario_portal == 'admin'){
            if(id_perfil==10){
                $("#panelhome .contenido").load('../server_onnet.php');
            }else{
                $("#panelhome .contenido").load('../server.php');
            }
            
        }else{
            if(id_perfil==10 || id_perfil==12 || id_perfil==13 || id_perfil==14 || id_perfil==15){
                $("#panelhome .contenido").load('../server_onnet.php');
            }else if(id_perfil==20){
                
                $("#panelhome .contenido").load('../server_v2.php');
            }else{
                $("#panelhome .contenido").load('../server.php');
            }
            //$("#panelhome .contenido").load('../menu/inicio_paneles.php');
            //$("#panelhome .contenido").load('../Corte_Energia/vista_regional_corte.php');
        }
        
        
        $('#nodo').keypress(function(e){
            if(e.which == 13){
                e.preventDefault();
                busca_info();
            }
        });
        function busca_info(){
        	
            var nodo = document.getElementById("nodo").value;
            $(".contenido").empty();
            $(".contenido").load("../menu/buscar.php?nodo="+nodo);

        }
        $(function() {
    $('#tblData11').DataTable({
            
            "processing": true,
            "serverSide": true,
            "ajax":"../../OLT/ONT/ver_info_ont.php",

            "columnDefs": [
                
                {"orderable": false,  "targets": [ 0 ] },
                {"orderable": false,  "targets": [ 1 ] },
                {"orderable": false,  "targets": [ 2 ] },
                {"orderable": false,  "targets": [ 3 ] },
                {"orderable": false,  "targets": [ 4 ] },
                {"orderable": false,  "targets": [ 5 ] },
                {"orderable": false,  "targets": [ 6 ] },
                {"orderable": false,  "targets": [ 7 ] },
                {"orderable": false,  "targets": [ 8 ] }
            ]
    });
    $('#tblData12').DataTable({
            
            "processing": true,
            "serverSide": true,
            "ajax":"../../OLT/ONT/Informacion_ONT/ver_info_ont.php",

            "columnDefs": [
                
                {"orderable": false,  "targets": [ 0 ] },
                {"orderable": false,  "targets": [ 1 ] },
                {"orderable": false,  "targets": [ 2 ] },
                {"orderable": false,  "targets": [ 3 ] },
                {"orderable": false,  "targets": [ 4 ] },
                {"orderable": false,  "targets": [ 5 ] },
                {"orderable": false,  "targets": [ 6 ] },
                {"orderable": false,  "targets": [ 7 ] },
                {"orderable": false,  "targets": [ 8 ] },
                {"orderable": false,  "targets": [ 9 ] },
                {"orderable": false,  "targets": [ 10 ] }
            ]
    });
});
    
    

        </script>
    </body>
    <img src="/contingencia/img/entelin.png" alt="ENTELIN" class="easter-egg">
</html>
