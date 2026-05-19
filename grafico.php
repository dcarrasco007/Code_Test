<?php
/**
 * grafico.php - PHP 5.3 compatible
 * Grafico de barras con Highcharts 9.3, filtros Semana/Mes/Anual y modal de detalle.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Cantidades</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f4f6f9;
        }
        .panel-grafico {
            background: #fff;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
        }
        #grafico-principal {
            height: 560px;
        }
        #grafico-modal {
            height: 380px;
        }
        .btn-filtro.active {
            background-color: #337ab7;
            color: #fff;
            border-color: #2e6da4;
        }
        .loading-chart {
            display: -webkit-box;
            display: -ms-flexbox;
            display: flex;
            -webkit-box-pack: center;
                -ms-flex-pack: center;
                    justify-content: center;
            -webkit-box-align: center;
                -ms-flex-align: center;
                    align-items: center;
            height: 560px;
            color: #999;
            font-size: 16px;
        }
        .filtro-row {
            margin-bottom: 20px;
        }
        /* Animacion del icono de carga */
        .spin {
            -webkit-animation: spin 1s linear infinite;
                    animation: spin 1s linear infinite;
            display: inline-block;
        }
        @-webkit-keyframes spin { from { -webkit-transform: rotate(0deg); } to { -webkit-transform: rotate(360deg); } }
        @keyframes spin        { from {         transform: rotate(0deg); } to {         transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="container-fluid" style="padding: 25px;">

    <div class="row">
        <div class="col-xs-12">
            <h2 style="margin-top:0;">
                Reporte de Cantidades
                <small>Haz clic en una barra para ver el detalle</small>
            </h2>
        </div>
    </div>

    <!-- ================================================================
         FILTROS
    ================================================================ -->
    <div class="row filtro-row">
        <div class="col-xs-12">
            <div class="form-inline">
                <label class="control-label" style="margin-right:10px;font-weight:bold;">
                    Filtrar por:
                </label>

                <!-- Botones de tipo de filtro -->
                <div class="btn-group" role="group" id="grupo-filtros" style="margin-right:12px;">
                    <button type="button" class="btn btn-default btn-filtro active" data-filtro="semana">
                        <span class="glyphicon glyphicon-calendar"></span> Semana
                    </button>
                    <button type="button" class="btn btn-default btn-filtro" data-filtro="mes">
                        <span class="glyphicon glyphicon-th"></span> Mes
                    </button>
                    <button type="button" class="btn btn-default btn-filtro" data-filtro="anual">
                        <span class="glyphicon glyphicon-stats"></span> Anual
                    </button>
                </div>

                <!-- Select dinamico (semanas / meses) o etiqueta de anio -->
                <span id="filtro-selector"></span>
            </div>
        </div>
    </div>

    <!-- ================================================================
         GRAFICO PRINCIPAL
    ================================================================ -->
    <div class="row">
        <div class="col-xs-12">
            <div class="panel-grafico">
                <div id="grafico-principal">
                    <div class="loading-chart">
                        <span class="glyphicon glyphicon-refresh spin"></span>&nbsp;Cargando datos...
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /container-fluid -->


<!-- ================================================================
     MODAL DETALLE
================================================================ -->
<div class="modal fade" id="modal-detalle" tabindex="-1" role="dialog" aria-labelledby="modal-titulo-label">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="modal-titulo-label">
                    Detalle: <span id="modal-nombre"></span>
                </h4>
                <p id="modal-subtitulo" class="text-muted" style="margin:4px 0 0;font-size:13px;"></p>
            </div>

            <div class="modal-body">
                <div id="grafico-modal"></div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>

        </div>
    </div>
</div>


<!-- ================================================================
     SCRIPTS
================================================================ -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script src="https://code.highcharts.com/9.3/highcharts.js"></script>

<script type="text/javascript">

    /* ----------------------------------------------------------
     * Configuracion global de Highcharts (Espanol)
     * ---------------------------------------------------------- */
    Highcharts.setOptions({
        lang: {
            loading:      'Cargando...',
            downloadPNG:  'Descargar PNG',
            downloadSVG:  'Descargar SVG',
            printChart:   'Imprimir grafico',
            resetZoom:    'Restablecer zoom',
            decimalPoint: ',',
            thousandsSep: '.',
            numericSymbols: null
        }
    });

    /* ----------------------------------------------------------
     * Estado global
     * ---------------------------------------------------------- */
    var chartPrincipal    = null;
    var chartModal        = null;
    var filtroActual      = 'semana';
    var valorActual       = null;
    var puntoSeleccionado = { nombre: '', cantidad: 0 };

    var NOMBRES_MESES = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    /* ----------------------------------------------------------
     * Calcula el numero de semana ISO del anio para una fecha
     * ---------------------------------------------------------- */
    function getNumeroSemanaISO(fecha) {
        var d = new Date(Date.UTC(fecha.getFullYear(), fecha.getMonth(), fecha.getDate()));
        d.setUTCDate(d.getUTCDate() + 4 - (d.getUTCDay() || 7));
        var inicioAnio = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil(((d - inicioAnio) / 86400000 + 1) / 7);
    }

    /* ----------------------------------------------------------
     * Construye el selector segun el filtro activo
     * ---------------------------------------------------------- */
    function actualizarSelectorFiltro(filtro) {
        var hoy  = new Date();
        var html = '';

        if (filtro === 'semana') {

            var semanaActual      = getNumeroSemanaISO(hoy);
            var semanasDisponibles = semanaActual - 1;

            if (semanasDisponibles < 1) {
                html = '<span class="label label-warning" style="font-size:13px;padding:6px 10px;">'
                     + 'No hay semanas anteriores disponibles este a&ntilde;o</span>';
                valorActual = null;
            } else {
                html = '<select class="form-control input-sm" id="select-valor"'
                     + ' style="display:inline-block;width:auto;min-width:155px;">';
                for (var s = 1; s <= semanasDisponibles; s++) {
                    html += '<option value="' + s + '">Semana ' + s + '</option>';
                }
                html += '</select>';
                valorActual = semanasDisponibles; // semana mas reciente
            }

        } else if (filtro === 'mes') {

            var mesActual = hoy.getMonth(); // 0 = Enero ... 11 = Diciembre

            if (mesActual === 0) {
                html = '<span class="label label-warning" style="font-size:13px;padding:6px 10px;">'
                     + 'No hay meses anteriores disponibles este a&ntilde;o</span>';
                valorActual = null;
            } else {
                html = '<select class="form-control input-sm" id="select-valor"'
                     + ' style="display:inline-block;width:auto;min-width:155px;">';
                for (var m = 0; m < mesActual; m++) {
                    html += '<option value="' + (m + 1) + '">' + NOMBRES_MESES[m] + '</option>';
                }
                html += '</select>';
                valorActual = mesActual; // mes mas reciente (1-indexed)
            }

        } else { // anual

            var anioActual = hoy.getFullYear();
            html = '<span class="label label-info"'
                 + ' style="font-size:15px;padding:7px 14px;">' + anioActual + '</span>';
            valorActual = anioActual;

        }

        $('#filtro-selector').html(html);

        /* Seleccionar el valor mas reciente en el select y vincular evento change */
        if (filtro !== 'anual' && valorActual !== null) {
            $('#select-valor').val(valorActual);
            $('#select-valor').on('change', function() {
                valorActual = parseInt($(this).val(), 10);
                cargarDatos();
            });
        }
    }

    /* ----------------------------------------------------------
     * Titulo descriptivo segun filtro activo
     * ---------------------------------------------------------- */
    function obtenerTituloGrafico() {
        var hoy = new Date();
        if (filtroActual === 'semana') {
            return 'Reporte &mdash; Semana ' + valorActual + ' del ' + hoy.getFullYear();
        }
        if (filtroActual === 'mes') {
            return 'Reporte &mdash; ' + NOMBRES_MESES[valorActual - 1] + ' ' + hoy.getFullYear();
        }
        return 'Reporte Anual &mdash; ' + valorActual;
    }

    /* Version sin HTML entities para Highcharts */
    function obtenerTituloGraficoPlain() {
        var hoy = new Date();
        if (filtroActual === 'semana') {
            return 'Reporte — Semana ' + valorActual + ' del ' + hoy.getFullYear();
        }
        if (filtroActual === 'mes') {
            return 'Reporte — ' + NOMBRES_MESES[valorActual - 1] + ' ' + hoy.getFullYear();
        }
        return 'Reporte Anual — ' + valorActual;
    }

    /* ----------------------------------------------------------
     * Carga datos desde el endpoint via AJAX
     * ---------------------------------------------------------- */
    function cargarDatos() {

        if (valorActual === null) {
            if (chartPrincipal) { chartPrincipal.destroy(); chartPrincipal = null; }
            $('#grafico-principal').html(
                '<div class="alert alert-warning" style="margin:20px;">'
              + '<strong>Aviso:</strong> Seleccione un per&iacute;odo v&aacute;lido.</div>'
            );
            return;
        }

        $.ajax({
            url:      'datos.php',
            method:   'GET',
            data:     { filtro: filtroActual, valor: valorActual },
            dataType: 'json',
            beforeSend: function() {
                if (chartPrincipal) { chartPrincipal.destroy(); chartPrincipal = null; }
                $('#grafico-principal').html(
                    '<div class="loading-chart">'
                  + '<span class="glyphicon glyphicon-refresh spin"></span>&nbsp;Cargando...</div>'
                );
            },
            success: function(data) {
                if (!data || data.length === 0) {
                    $('#grafico-principal').html(
                        '<div class="alert alert-info" style="margin:20px;">'
                      + 'No hay datos para el per&iacute;odo seleccionado.</div>'
                    );
                    return;
                }
                renderizarGraficoPrincipal(data);
            },
            error: function(xhr, status, err) {
                $('#grafico-principal').html(
                    '<div class="alert alert-danger" style="margin:20px;">'
                  + '<strong>Error:</strong> No se pudo cargar la informaci&oacute;n. ' + err + '</div>'
                );
            }
        });
    }

    /* ----------------------------------------------------------
     * Renderiza el grafico principal con los datos recibidos
     * ---------------------------------------------------------- */
    function renderizarGraficoPrincipal(data) {
        var categorias = [];
        var valores    = [];

        for (var i = 0; i < data.length; i++) {
            categorias.push(data[i].nombre);
            valores.push({
                y:    data[i].cantidad,
                name: data[i].nombre
            });
        }

        chartPrincipal = Highcharts.chart('grafico-principal', {
            chart: {
                type:      'column',
                height:    560,
                animation: { duration: 400 },
                style:     { fontFamily: 'inherit' }
            },
            title: {
                text:  obtenerTituloGraficoPlain(),
                style: { fontSize: '16px' }
            },
            subtitle: {
                text: 'Haz clic en una barra para ver el detalle',
                style: { color: '#999' }
            },
            xAxis: {
                categories: categorias,
                crosshair:  true,
                labels: {
                    rotation: -90,
                    step:     1,
                    style:    { fontSize: '9px' }
                }
            },
            yAxis: {
                title:          { text: 'Cantidad' },
                min:            0,
                allowDecimals:  false,
                gridLineColor:  '#eaeaea'
            },
            tooltip: {
                headerFormat: '',
                pointFormat:  '<span style="font-weight:bold;">{point.name}</span><br/>Cantidad: <b>{point.y}</b>',
                backgroundColor: 'rgba(30,30,30,0.82)',
                style:           { color: '#fff' },
                borderWidth:     0,
                borderRadius:    4
            },
            plotOptions: {
                column: {
                    cursor:       'pointer',
                    colorByPoint: true,
                    point: {
                        events: {
                            click: function() {
                                abrirModal(this.name, this.y);
                            }
                        }
                    }
                }
            },
            series: [{
                name: 'Cantidad',
                data: valores
            }],
            legend:  { enabled: false },
            credits: { enabled: false }
        });
    }

    /* ----------------------------------------------------------
     * Abre el modal con el punto seleccionado
     * ---------------------------------------------------------- */
    function abrirModal(nombre, cantidad) {
        puntoSeleccionado.nombre   = nombre;
        puntoSeleccionado.cantidad = cantidad;

        $('#modal-nombre').text(nombre);
        $('#modal-subtitulo').html(obtenerTituloGrafico());
        $('#modal-detalle').modal('show');
    }

    /* ----------------------------------------------------------
     * Renderiza el grafico de detalle dentro del modal
     * (se dispara despues de que el modal termina de abrirse)
     * ---------------------------------------------------------- */
    $('#modal-detalle').on('shown.bs.modal', function() {
        if (chartModal) { chartModal.destroy(); chartModal = null; }

        chartModal = Highcharts.chart('grafico-modal', {
            chart: {
                type:      'column',
                height:    380,
                animation: { duration: 500 },
                style:     { fontFamily: 'inherit' }
            },
            title: {
                text:  puntoSeleccionado.nombre,
                style: { fontSize: '20px' }
            },
            subtitle: {
                text:  obtenerTituloGraficoPlain(),
                style: { color: '#999' }
            },
            xAxis: {
                categories: [ puntoSeleccionado.nombre ],
                labels:     { enabled: false }
            },
            yAxis: {
                title:         { text: 'Cantidad' },
                min:           0,
                allowDecimals: false
            },
            tooltip: {
                headerFormat: '',
                pointFormat:  'Cantidad: <b>{point.y}</b>',
                backgroundColor: 'rgba(30,30,30,0.82)',
                style:           { color: '#fff' },
                borderWidth:     0
            },
            plotOptions: {
                column: {
                    pointWidth:   140,
                    borderRadius: 5,
                    dataLabels: {
                        enabled:   true,
                        format:    '{y}',
                        style:     { fontSize: '34px', fontWeight: 'bold', textOutline: 'none' },
                        y:         -8,
                        color:     '#fff'
                    }
                }
            },
            series: [{
                name:  puntoSeleccionado.nombre,
                data:  [ puntoSeleccionado.cantidad ],
                color: '#337ab7'
            }],
            legend:  { enabled: false },
            credits: { enabled: false }
        });
    });

    /* Libera memoria al cerrar el modal */
    $('#modal-detalle').on('hidden.bs.modal', function() {
        if (chartModal) { chartModal.destroy(); chartModal = null; }
        $('#grafico-modal').empty();
    });

    /* ----------------------------------------------------------
     * Cambio de filtro (Semana / Mes / Anual)
     * ---------------------------------------------------------- */
    $('#grupo-filtros').on('click', '.btn-filtro', function() {
        var $btn = $(this);
        if ($btn.hasClass('active')) { return; }

        $('.btn-filtro').removeClass('active');
        $btn.addClass('active');

        filtroActual = $btn.data('filtro');
        actualizarSelectorFiltro(filtroActual);
        cargarDatos();
    });

    /* ----------------------------------------------------------
     * Inicializacion
     * ---------------------------------------------------------- */
    $(document).ready(function() {
        actualizarSelectorFiltro(filtroActual);
        cargarDatos();
    });

</script>
</body>
</html>
