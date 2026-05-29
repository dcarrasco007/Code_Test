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
                &nbsp;
                <button type="button" id="btn-aplicar-filtro" class="btn btn-primary">
                    <span class="glyphicon glyphicon-search"></span> Filtrar
                </button>
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

    /* Control del AJAX de detalle del modal */
    var xhrDetalle        = null;   // Referencia al request activo (para poder abortarlo)
    var modalDatosDetalle = null;   // Datos recibidos del endpoint antes de que el modal abra

    /* Año seleccionado en los filtros Semana y Mes */
    var anioFiltro          = new Date().getFullYear();
    var ANIO_INICIO_FILTROS = 2025; // Primer año disponible en todos los selectores

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
     * Maximo de semanas disponibles para un año dado.
     * Años pasados → 52; año actual → semana anterior a hoy.
     * ---------------------------------------------------------- */
    function getMaxSemana(anio) {
        var hoy = new Date();
        if (anio < hoy.getFullYear()) { return 52; }
        return getNumeroSemanaISO(hoy) - 1;
    }

    /* ----------------------------------------------------------
     * Maximo de meses disponibles para un año dado (1-indexed).
     * Años pasados → 12; año actual → mes anterior a hoy.
     * ---------------------------------------------------------- */
    function getMaxMes(anio) {
        var hoy = new Date();
        if (anio < hoy.getFullYear()) { return 12; }
        return hoy.getMonth(); // getMonth() base-0 = mes anterior en base-1
    }

    /* ----------------------------------------------------------
     * Reconstruye las opciones de #select-valor al cambiar el año
     * en Semana o Mes, y reactiva el evento change.
     * ---------------------------------------------------------- */
    function actualizarSelectSecundario(filtro, anio) {
        var html   = '';
        var maxVal = (filtro === 'semana') ? getMaxSemana(anio) : getMaxMes(anio);

        if (maxVal < 1) {
            html        = '<option value="">Sin opciones disponibles</option>';
            valorActual = null;
        } else if (filtro === 'semana') {
            for (var s = 1; s <= maxVal; s++) {
                html += '<option value="' + s + '">Semana ' + s + '</option>';
            }
            valorActual = maxVal;
        } else {
            for (var m = 1; m <= maxVal; m++) {
                html += '<option value="' + m + '">' + NOMBRES_MESES[m - 1] + '</option>';
            }
            valorActual = maxVal;
        }

        $('#select-valor').html(html);
        if (valorActual !== null) {
            $('#select-valor').val(valorActual);
            $('#select-valor').off('change').on('change', function() {
                valorActual = parseInt($(this).val(), 10);
            });
        }
    }

    /* ----------------------------------------------------------
     * Construye el selector segun el filtro activo
     * ---------------------------------------------------------- */
    function actualizarSelectorFiltro(filtro) {
        var hoy  = new Date();
        var html = '';

        /* Reiniciar al año actual cada vez que se elige un tipo de filtro */
        anioFiltro = hoy.getFullYear();

        if (filtro === 'semana') {

            var maxSem = getMaxSemana(anioFiltro);

            /* Selector de año */
            html = '<select class="form-control input-sm" id="select-anio"'
                 + ' style="display:inline-block;width:auto;min-width:90px;margin-right:6px;">';
            for (var a = ANIO_INICIO_FILTROS; a <= hoy.getFullYear(); a++) {
                html += '<option value="' + a + '">' + a + '</option>';
            }
            html += '</select>';

            /* Selector de semana */
            html += '<select class="form-control input-sm" id="select-valor"'
                  + ' style="display:inline-block;width:auto;min-width:155px;">';
            if (maxSem < 1) {
                html        += '<option value="">Sin semanas disponibles</option>';
                valorActual  = null;
            } else {
                for (var s = 1; s <= maxSem; s++) {
                    html += '<option value="' + s + '">Semana ' + s + '</option>';
                }
                valorActual = maxSem;
            }
            html += '</select>';

        } else if (filtro === 'mes') {

            var maxMes = getMaxMes(anioFiltro);

            /* Selector de año */
            html = '<select class="form-control input-sm" id="select-anio"'
                 + ' style="display:inline-block;width:auto;min-width:90px;margin-right:6px;">';
            for (var a = ANIO_INICIO_FILTROS; a <= hoy.getFullYear(); a++) {
                html += '<option value="' + a + '">' + a + '</option>';
            }
            html += '</select>';

            /* Selector de mes */
            html += '<select class="form-control input-sm" id="select-valor"'
                  + ' style="display:inline-block;width:auto;min-width:155px;">';
            if (maxMes < 1) {
                html        += '<option value="">Sin meses disponibles</option>';
                valorActual  = null;
            } else {
                for (var m = 1; m <= maxMes; m++) {
                    html += '<option value="' + m + '">' + NOMBRES_MESES[m - 1] + '</option>';
                }
                valorActual = maxMes;
            }
            html += '</select>';

        } else { // anual

            html = '<select class="form-control input-sm" id="select-valor"'
                 + ' style="display:inline-block;width:auto;min-width:100px;">';
            for (var a = ANIO_INICIO_FILTROS; a <= hoy.getFullYear(); a++) {
                html += '<option value="' + a + '">' + a + '</option>';
            }
            html += '</select>';
            valorActual = hoy.getFullYear();

        }

        $('#filtro-selector').html(html);

        /* Establecer valores por defecto */
        if (filtro !== 'anual') { $('#select-anio').val(anioFiltro); }
        if (valorActual !== null) { $('#select-valor').val(valorActual); }

        /* Vincular cambio de año (solo Semana y Mes): reconstruye opciones, no recarga grafico */
        if (filtro === 'semana' || filtro === 'mes') {
            $('#select-anio').on('change', function() {
                anioFiltro = parseInt($(this).val(), 10);
                actualizarSelectSecundario(filtro, anioFiltro);
            });
        }

        /* Vincular cambio de semana / mes / año-anual: solo actualiza el estado interno */
        if (valorActual !== null) {
            $('#select-valor').on('change', function() {
                valorActual = parseInt($(this).val(), 10);
            });
        }
    }

    /* ----------------------------------------------------------
     * Titulo descriptivo segun filtro activo
     * ---------------------------------------------------------- */
    function obtenerTituloGrafico() {
        if (filtroActual === 'semana') {
            return 'Reporte &mdash; Semana ' + valorActual + ' del ' + anioFiltro;
        }
        if (filtroActual === 'mes') {
            return 'Reporte &mdash; ' + NOMBRES_MESES[valorActual - 1] + ' ' + anioFiltro;
        }
        return 'Reporte Anual &mdash; ' + valorActual;
    }

    /* Version sin HTML entities para Highcharts */
    function obtenerTituloGraficoPlain() {
        if (filtroActual === 'semana') {
            return 'Reporte — Semana ' + valorActual + ' del ' + anioFiltro;
        }
        if (filtroActual === 'mes') {
            return 'Reporte — ' + NOMBRES_MESES[valorActual - 1] + ' ' + anioFiltro;
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

        var paramsPrincipal = { filtro: filtroActual, valor: valorActual };
        if (filtroActual === 'semana' || filtroActual === 'mes') {
            paramsPrincipal.anio = anioFiltro;
        }

        $.ajax({
            url:      'datos.php',
            method:   'GET',
            data:     paramsPrincipal,
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
     * Abre el modal: muestra loading, lanza AJAX de detalle y
     * coordina el renderizado con la animacion de apertura.
     * ---------------------------------------------------------- */
    function abrirModal(nombre, cantidad) {
        puntoSeleccionado.nombre   = nombre;
        puntoSeleccionado.cantidad = cantidad;
        modalDatosDetalle          = null;

        /* Abortar cualquier peticion de detalle previa */
        if (xhrDetalle) {
            xhrDetalle.abort();
            xhrDetalle = null;
        }

        /* Destruir grafico anterior y mostrar spinner de carga */
        if (chartModal) { chartModal.destroy(); chartModal = null; }
        $('#grafico-modal').html(
            '<div style="display:-webkit-box;display:-ms-flexbox;display:flex;'
          + '-webkit-box-pack:center;-ms-flex-pack:center;justify-content:center;'
          + '-webkit-box-align:center;-ms-flex-align:center;align-items:center;height:380px;color:#999;font-size:16px;">'
          + '<span class="glyphicon glyphicon-refresh spin"></span>&nbsp;Cargando detalle...</div>'
        );

        /* Actualizar cabecera del modal */
        $('#modal-nombre').text(nombre);
        $('#modal-subtitulo').html(obtenerTituloGrafico());

        /* Abrir el modal (la animacion dura ~300 ms) */
        $('#modal-detalle').modal('show');

        /* Lanzar AJAX en paralelo con la animacion de apertura */
        var paramsDetalle = { filtro: filtroActual, valor: valorActual, nombre: nombre };
        if (filtroActual === 'semana' || filtroActual === 'mes') {
            paramsDetalle.anio = anioFiltro;
        }

        xhrDetalle = $.ajax({
            url:      'datos.php',
            method:   'GET',
            data:     paramsDetalle,
            dataType: 'json',
            success: function(data) {
                xhrDetalle = null;

                /* Validar estructura de respuesta */
                if (!data || !data.detalle || data.detalle.length === 0) {
                    $('#grafico-modal').html(
                        '<div class="alert alert-info" style="margin:20px;">'
                      + 'No hay detalle disponible para este elemento.</div>'
                    );
                    return;
                }

                modalDatosDetalle = data;

                /*
                 * Si el modal ya termino de abrirse (clase 'in' activa en Bootstrap 3)
                 * renderizar de inmediato; si no, shown.bs.modal lo hara al finalizar.
                 */
                if ($('#modal-detalle').hasClass('in')) {
                    renderizarGraficoModal(data);
                }
            },
            error: function(xhr, status) {
                if (status === 'abort') { return; }
                xhrDetalle = null;
                $('#grafico-modal').html(
                    '<div class="alert alert-danger" style="margin:20px;">'
                  + '<strong>Error:</strong> No se pudo cargar el detalle.</div>'
                );
            }
        });
    }

    /* ----------------------------------------------------------
     * Se dispara cuando el modal termino de abrirse.
     * Si los datos ya llegaron los renderiza; si no, el callback
     * AJAX los renderizara cuando lleguen.
     * ---------------------------------------------------------- */
    $('#modal-detalle').on('shown.bs.modal', function() {
        if (modalDatosDetalle) {
            renderizarGraficoModal(modalDatosDetalle);
        }
        /* Si modalDatosDetalle es null, el AJAX aun no respondio:
           el success callback verificara hasClass('in') y renderizara. */
    });

    /* ----------------------------------------------------------
     * Renderiza el grafico de detalle con las 3 sub-consultas.
     * data = { nombre, total, detalle: [{fuente, cantidad}, ...] }
     * ---------------------------------------------------------- */
    function renderizarGraficoModal(data) {
        if (chartModal) { chartModal.destroy(); chartModal = null; }

        /* Construir categorias y puntos desde el array de detalle */
        var categorias = [];
        var puntos     = [];
        var sumaVerif  = 0;

        for (var i = 0; i < data.detalle.length; i++) {
            categorias.push(data.detalle[i].fuente);
            puntos.push({
                y:    data.detalle[i].cantidad,
                name: data.detalle[i].fuente
            });
            sumaVerif += data.detalle[i].cantidad;
        }

        var totalMostrar = (typeof data.total !== 'undefined') ? data.total : sumaVerif;
        var subtitulo    = obtenerTituloGraficoPlain()
                         + '   |   Total: ' + totalMostrar;

        chartModal = Highcharts.chart('grafico-modal', {
            chart: {
                type:      'column',
                height:    380,
                animation: { duration: 450 },
                style:     { fontFamily: 'inherit' }
            },
            title: {
                text:  data.nombre,
                style: { fontSize: '20px' }
            },
            subtitle: {
                text:  subtitulo,
                style: { color: '#555', fontWeight: 'bold' }
            },
            xAxis: {
                categories: categorias,
                labels: {
                    style: { fontSize: '13px', fontWeight: 'bold' }
                }
            },
            yAxis: {
                title:         { text: 'Cantidad' },
                min:           0,
                allowDecimals: false,
                gridLineColor: '#eaeaea'
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
                    colorByPoint:  true,
                    borderRadius:  4,
                    pointPadding:  0.1,
                    groupPadding:  0.15,
                    dataLabels: {
                        enabled:   true,
                        format:    '{y}',
                        style: {
                            fontSize:    '18px',
                            fontWeight:  'bold',
                            textOutline: 'none'
                        },
                        y: -5
                    }
                }
            },
            series: [{
                name: data.nombre,
                data: puntos
            }],
            legend:  { enabled: false },
            credits: { enabled: false }
        });
    }

    /* Libera recursos al cerrar el modal */
    $('#modal-detalle').on('hidden.bs.modal', function() {
        /* Abortar AJAX si aun estuviera en vuelo */
        if (xhrDetalle) { xhrDetalle.abort(); xhrDetalle = null; }
        modalDatosDetalle = null;

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
    });

    /* ----------------------------------------------------------
     * Boton Filtrar: unico punto que dispara la carga del grafico
     * ---------------------------------------------------------- */
    $('#btn-aplicar-filtro').on('click', function() {
        cargarDatos();
    });

    /* ----------------------------------------------------------
     * Inicializacion: carga inicial al abrir la pagina
     * ---------------------------------------------------------- */
    $(document).ready(function() {
        actualizarSelectorFiltro(filtroActual);
        cargarDatos();
    });

</script>
</body>
</html>
