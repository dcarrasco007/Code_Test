<?php
/*
 * Eliminar archivos csv / xls / xlsx del portal OLT.
 * Se carga dentro de menu.php (jQuery y Bootstrap 3 ya disponibles).
 * Menú: ../Eliminar_Archivos/index_eliminar_archivos.php
 */
require_once __DIR__ . '/lib/ea_funciones.php';

$usuario = ea_usuario();
$db = $usuario != '' ? ea_conectar() : null;
if ($usuario == '') {
    die('<center><h4>Tu sesión expiró. Vuelve a ingresar al portal.</h4></center>');
}
if (!$db) {
    die('<center><h4>No se pudo conectar a la base de datos.</h4></center>');
}
$nivel = ea_nivel_acceso($usuario);
if ($nivel == '') {
    die('<center><h4>Usuario sin acceso a este item.</h4></center>');
}
// 'total' ve el mantenedor de rutas y el log; 'eliminar' solo la lista de archivos.
$total = $nivel == 'total';
$token = ea_token();
?>
<style>
    #ea .ea-ruta { font-family: Consolas, monospace; font-size: 12px; word-break: break-all; }
    #ea .ea-gris { color: #888; }
    #ea .ea-tabla td, #ea .ea-tabla th { vertical-align: middle; font-size: 12px; }
    #ea .ea-error { color: #a94442; font-size: 12px; min-height: 16px; margin-top: 4px; }
    #ea .ea-num { font-size: 22px; font-weight: bold; }
    #ea .ea-inactiva td { color: #999; }
</style>

<div id="ea" class="container-fluid">
    <div class="row">
        <div class="col-md-12 text-center"><h3>Eliminar archivos csv / Excel</h3></div>
    </div>

    <div class="row" style="margin-bottom:10px;">
        <div class="col-sm-3 col-xs-6"><div class="well well-sm text-center">Archivos<br><span class="ea-num" id="ea_m_total">-</span></div></div>
        <div class="col-sm-3 col-xs-6"><div class="well well-sm text-center">Espacio ocupado<br><span class="ea-num" id="ea_m_bytes">-</span></div></div>
        <div class="col-sm-3 col-xs-6"><div class="well well-sm text-center">Rutas activas<br><span class="ea-num" id="ea_m_rutas">-</span></div></div>
        <div class="col-sm-3 col-xs-6"><div class="well well-sm text-center">Eliminados en esta sesión<br><span class="ea-num" id="ea_m_elim">0</span></div></div>
    </div>

    <?php if ($total) { ?>
    <!-- Mantenedor de rutas -->
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Rutas permitidas</strong>
            <span class="ea-gris">&nbsp;· relativas a la raíz del portal; incluyen todas sus subcarpetas</span>
        </div>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-4"><input type="text" class="form-control input-sm" id="ea_n_ruta" placeholder="ONNET/Reporte_Splitter/Archivos"></div>
                <div class="col-md-3"><input type="text" class="form-control input-sm" id="ea_n_desc" maxlength="150" placeholder="Descripción"></div>
                <div class="col-md-3"><input type="text" class="form-control input-sm" id="ea_n_nota" maxlength="255" placeholder="Aviso al eliminar (opcional)"></div>
                <div class="col-md-2"><button type="button" class="btn btn-primary btn-sm btn-block" id="ea_agregar">Agregar</button></div>
            </div>
            <div class="ea-error" id="ea_e_ruta"></div>
            <div class="table-responsive">
                <table class="table table-condensed table-bordered ea-tabla" style="margin-bottom:0;">
                    <thead><tr><th>Ruta</th><th>Descripción</th><th>Aviso al eliminar</th><th style="width:80px;">Archivos</th><th style="width:90px;">Tamaño</th><th style="width:80px;">Estado</th><th style="width:150px;"></th></tr></thead>
                    <tbody id="ea_t_rutas"><tr><td colspan="7" class="text-center ea-gris">Cargando…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- Archivos -->
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Archivos encontrados</strong> <span class="ea-gris" id="ea_cuenta"></span>
            <button type="button" class="btn btn-default btn-xs pull-right" id="ea_recargar">Actualizar</button>
        </div>
        <div class="panel-body">
            <div class="row" style="margin-bottom:8px;">
                <div class="col-md-4"><select class="form-control input-sm" id="ea_f_ruta"><option value="">Ruta: todas</option></select></div>
                <div class="col-md-2"><select class="form-control input-sm" id="ea_f_ext"><option value="">Tipo: todos</option><option>csv</option><option>xls</option><option>xlsx</option></select></div>
                <div class="col-md-2"><select class="form-control input-sm" id="ea_f_orden">
                    <option value="ruta">Orden: ubicación</option><option value="fecha_asc">Más antiguos primero</option>
                    <option value="fecha_desc">Más recientes primero</option><option value="tamano">Más pesados primero</option></select></div>
                <div class="col-md-4"><input type="text" class="form-control input-sm" id="ea_f_texto" placeholder="Buscar nombre o carpeta"></div>
            </div>
            <div class="alert alert-warning" id="ea_truncado" style="display:none; padding:6px 10px;"></div>
            <div class="table-responsive">
                <table class="table table-condensed table-striped table-bordered ea-tabla" style="margin-bottom:6px;">
                    <thead><tr><th>Archivo</th><th>Ubicación</th><th style="width:90px;">Tamaño</th><th style="width:120px;">Modificado</th><th style="width:90px;"></th></tr></thead>
                    <tbody id="ea_t_archivos"><tr><td colspan="5" class="text-center ea-gris">Cargando…</td></tr></tbody>
                </table>
            </div>
            <div class="text-center"><button type="button" class="btn btn-default btn-sm" id="ea_mas" style="display:none;">Mostrar más</button></div>
        </div>
    </div>

    <?php if ($total) { ?>
    <!-- Log -->
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Últimos movimientos</strong>
            <button type="button" class="btn btn-default btn-xs pull-right" id="ea_ver_log">Ver</button>
        </div>
        <div class="panel-body" id="ea_log" style="display:none;"></div>
    </div>
    <?php } ?>

    <!-- Confirmación -->
    <div class="modal fade" id="ea_modal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header"><h4 class="modal-title">¿Eliminar este archivo?</h4></div>
                <div class="modal-body">
                    <p><strong id="ea_c_nombre"></strong></p>
                    <p class="ea-ruta" id="ea_c_ruta"></p>
                    <p class="ea-gris" id="ea_c_info"></p>
                    <div class="alert alert-warning" id="ea_c_nota" style="display:none; padding:6px 10px;"></div>
                    <p class="text-danger" style="margin-bottom:0;">El archivo se borra del servidor y no se puede recuperar.</p>
                    <div class="ea-error" id="ea_c_error"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="ea_c_ok">Sí, eliminar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function () {
    var URL = '../Eliminar_Archivos/ajax_eliminar_archivos.php';
    var TOKEN = '<?php echo $token; ?>';
    var PAGINA = 200;
    var rutas = [], archivos = [], porId = {}, visibles = PAGINA, eliminados = 0, pendiente = null;

    // El modal va directo en <body> para que no quede detrás del fondo oscuro dentro de .contenido
    $('body > #ea_modal').remove();
    $('#ea_modal').appendTo('body');

    function esc(s) {
        return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
        });
    }
    function tam(b) {
        b = Number(b) || 0;
        if (b >= 1073741824) return (b / 1073741824).toFixed(2) + ' GB';
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024) return Math.round(b / 1024) + ' KB';
        return b + ' B';
    }
    function post(datos, ok, fallo) {
        datos.token = TOKEN;
        $.ajax({url: URL, type: 'POST', data: datos, dataType: 'json'})
            .done(function (r) {
                if (r && r.ok) { ok(r); } else { fallo(r && r.error ? r.error : 'Respuesta no válida del servidor.'); }
            })
            .fail(function (x) { fallo('Error de comunicación con el servidor (' + x.status + ').'); });
    }

    function cargar() {
        $('#ea_t_archivos').html('<tr><td colspan="5" class="text-center ea-gris">Buscando archivos…</td></tr>');
        post({accion: 'cargar'}, function (r) {
            rutas = r.rutas; archivos = r.archivos; porId = {};
            for (var i = 0; i < rutas.length; i++) { porId[rutas[i].ID] = rutas[i]; }
            $('#ea_truncado').toggle(!!r.truncado).text('Se muestran solo los primeros ' + r.max +
                ' archivos. Desactiva algunas rutas o elimina por partes para ver el resto.');
            dibujarRutas(); llenarFiltroRutas(); visibles = PAGINA; dibujarArchivos();
        }, function (e) {
            $('#ea_t_rutas').html('<tr><td colspan="7" class="text-danger">' + esc(e) + '</td></tr>');
            $('#ea_t_archivos').html('<tr><td colspan="5" class="text-danger">' + esc(e) + '</td></tr>');
        });
    }

    function dibujarRutas() {
        var h = '', activas = 0, totalBytes = 0;
        for (var i = 0; i < rutas.length; i++) {
            var r = rutas[i], res = r.RESUMEN, act = r.ACTIVA == 1;
            if (act) { activas++; }
            if (res) { totalBytes += Number(res.bytes); }
            h += '<tr class="' + (act ? '' : 'ea-inactiva') + '">' +
                '<td class="ea-ruta">' + esc(r.RUTA) + '/' + (res && res.error ? '<br><span class="text-danger">' + esc(res.error) + '</span>' : '') + '</td>' +
                '<td>' + esc(r.DESCRIPCION) + '</td>' +
                '<td class="ea-gris">' + esc(r.NOTA) + '</td>' +
                '<td>' + (act && res ? res.archivos : '-') + '</td>' +
                '<td>' + (act && res ? tam(res.bytes) : '-') + '</td>' +
                '<td><span class="label label-' + (act ? 'success' : 'default') + '">' + (act ? 'Activa' : 'Inactiva') + '</span></td>' +
                '<td><button type="button" class="btn btn-default btn-xs ea-estado" data-id="' + r.ID + '" data-activa="' + (act ? 0 : 1) + '">' + (act ? 'Desactivar' : 'Activar') + '</button> ' +
                '<button type="button" class="btn btn-default btn-xs ea-quitar" data-id="' + r.ID + '">Quitar</button></td></tr>';
        }
        $('#ea_t_rutas').html(h || '<tr><td colspan="7" class="text-center ea-gris">Agrega la primera ruta para empezar.</td></tr>');
        $('#ea_m_total').text(archivos.length);
        $('#ea_m_bytes').text(tam(totalBytes));
        $('#ea_m_rutas').text(activas);
    }

    function llenarFiltroRutas() {
        var sel = $('#ea_f_ruta'), actual = sel.val(), h = '<option value="">Ruta: todas</option>';
        for (var i = 0; i < rutas.length; i++) {
            if (rutas[i].ACTIVA == 1) { h += '<option value="' + rutas[i].ID + '">' + esc(rutas[i].DESCRIPCION) + '</option>'; }
        }
        sel.html(h);
        if (actual && porId[actual] && porId[actual].ACTIVA == 1) { sel.val(actual); }
    }

    function filtrados() {
        var fr = $('#ea_f_ruta').val(), fe = $('#ea_f_ext').val(), ft = $.trim($('#ea_f_texto').val()).toLowerCase(), o = $('#ea_f_orden').val();
        var l = [];
        for (var i = 0; i < archivos.length; i++) {
            var a = archivos[i];
            if (fr && String(a.r) !== fr) continue;
            if (fe && a.ext !== fe) continue;
            if (ft && (a.carpeta + a.nombre + ' ' + porId[a.r].RUTA).toLowerCase().indexOf(ft) < 0) continue;
            l.push(a);
        }
        l.sort(function (x, y) {
            if (o === 'fecha_asc') return x.fecha < y.fecha ? -1 : (x.fecha > y.fecha ? 1 : 0);
            if (o === 'fecha_desc') return x.fecha > y.fecha ? -1 : (x.fecha < y.fecha ? 1 : 0);
            if (o === 'tamano') return y.tamano - x.tamano;
            var kx = porId[x.r].RUTA + '/' + x.carpeta + x.nombre, ky = porId[y.r].RUTA + '/' + y.carpeta + y.nombre;
            return kx < ky ? -1 : (kx > ky ? 1 : 0);
        });
        return l;
    }

    function dibujarArchivos() {
        var l = filtrados(), h = '';
        for (var i = 0; i < l.length && i < visibles; i++) {
            var a = l[i], r = porId[a.r];
            h += '<tr><td>' + esc(a.nombre) + '<br><span class="ea-gris">' + esc(r.DESCRIPCION) + '</span></td>' +
                '<td class="ea-ruta"><span class="ea-gris">' + esc(r.RUTA) + '/</span>' + esc(a.carpeta) + '</td>' +
                '<td>' + tam(a.tamano) + '</td><td>' + esc(a.fecha) + '</td>' +
                '<td><button type="button" class="btn btn-danger btn-xs ea-eliminar" data-i="' + archivos.indexOf(a) + '">Eliminar</button></td></tr>';
        }
        $('#ea_t_archivos').html(h || '<tr><td colspan="5" class="text-center ea-gris">No hay archivos en las rutas activas con esos filtros.</td></tr>');
        $('#ea_cuenta').text('· ' + l.length + (l.length !== archivos.length ? ' de ' + archivos.length : ''));
        $('#ea_mas').toggle(l.length > visibles).text('Mostrar más (' + (l.length - visibles) + ' restantes)');
    }

    function verLog() {
        $('#ea_log').show().html('<span class="ea-gris">Cargando…</span>');
        post({accion: 'log'}, function (r) {
            var h = '<div class="table-responsive"><table class="table table-condensed table-bordered ea-tabla" style="margin-bottom:0;"><thead><tr><th>Fecha</th><th>Acción</th><th>Ruta / archivo</th><th>Tamaño</th><th>Usuario</th><th>Servidor</th></tr></thead><tbody>';
            for (var i = 0; i < r.log.length; i++) {
                var g = r.log[i];
                h += '<tr><td>' + esc(g.FECHA) + '</td><td>' + esc(g.ACCION) + '</td><td class="ea-ruta">' + esc(g.RUTA) + (g.ARCHIVO ? '/' + esc(g.ARCHIVO) : '') +
                    '</td><td>' + (g.TAMANO === null ? '' : tam(g.TAMANO)) + '</td><td>' + esc(g.USUARIO) + '</td><td>' + esc(g.SERVIDOR) + '</td></tr>';
            }
            $('#ea_log').html(h + '</tbody></table></div>' + (r.log.length ? '' : '<span class="ea-gris">Sin movimientos.</span>'));
        }, function (e) { $('#ea_log').html('<span class="text-danger">' + esc(e) + '</span>'); });
    }

    // Rutas
    $('#ea_agregar').on('click', function () {
        var btn = $(this), ruta = $.trim($('#ea_n_ruta').val());
        if (!ruta) { $('#ea_e_ruta').text('Escribe una ruta.'); return; }
        btn.prop('disabled', true);
        post({accion: 'agregar_ruta', ruta: ruta, descripcion: $('#ea_n_desc').val(), nota: $('#ea_n_nota').val()}, function () {
            btn.prop('disabled', false);
            $('#ea_e_ruta').text('');
            $('#ea_n_ruta, #ea_n_desc, #ea_n_nota').val('');
            cargar();
        }, function (e) { btn.prop('disabled', false); $('#ea_e_ruta').text(e); });
    });
    $('#ea_n_ruta, #ea_n_desc, #ea_n_nota').on('input', function () { $('#ea_e_ruta').text(''); });
    $('#ea_t_rutas').on('click', '.ea-estado', function () {
        post({accion: 'estado_ruta', id: $(this).data('id'), activa: $(this).data('activa')}, cargar, function (e) { $('#ea_e_ruta').text(e); });
    });
    $('#ea_t_rutas').on('click', '.ea-quitar', function () {
        var r = porId[$(this).data('id')];
        if (!confirm('¿Quitar la ruta ' + r.RUTA + '/ del mantenedor?\nNo se borra ningún archivo; solo deja de aparecer en esta ventana.')) return;
        post({accion: 'quitar_ruta', id: r.ID}, cargar, function (e) { $('#ea_e_ruta').text(e); });
    });

    // Archivos
    $('#ea_f_ruta, #ea_f_ext, #ea_f_orden').on('change', function () { visibles = PAGINA; dibujarArchivos(); });
    $('#ea_f_texto').on('input', function () { visibles = PAGINA; dibujarArchivos(); });
    $('#ea_mas').on('click', function () { visibles += PAGINA; dibujarArchivos(); });
    $('#ea_recargar').on('click', cargar);
    $('#ea_ver_log').on('click', verLog);

    $('#ea_t_archivos').on('click', '.ea-eliminar', function () {
        var a = archivos[$(this).data('i')], r = porId[a.r];
        pendiente = a;
        $('#ea_c_nombre').text(a.nombre);
        $('#ea_c_ruta').text(r.RUTA + '/' + a.carpeta + a.nombre);
        $('#ea_c_info').text(tam(a.tamano) + ' · modificado ' + a.fecha);
        $('#ea_c_nota').toggle(!!r.NOTA).text(r.NOTA || '');
        $('#ea_c_error').text('');
        $('#ea_c_ok').prop('disabled', false);
        $('#ea_modal').modal('show');
    });
    $('#ea_c_ok').on('click', function () {
        if (!pendiente) return;
        var btn = $(this), a = pendiente;
        btn.prop('disabled', true);
        post({accion: 'eliminar', id: a.r, k: a.k}, function () {
            var i = archivos.indexOf(a);
            if (i > -1) { archivos.splice(i, 1); }
            var res = porId[a.r].RESUMEN;
            if (res) { res.archivos--; res.bytes -= a.tamano; }
            eliminados++; $('#ea_m_elim').text(eliminados);
            pendiente = null;
            $('#ea_modal').modal('hide');
            dibujarRutas(); dibujarArchivos();
            if ($('#ea_log').is(':visible')) { verLog(); }
        }, function (e) { btn.prop('disabled', false); $('#ea_c_error').text(e); });
    });

    cargar();
})();
</script>
