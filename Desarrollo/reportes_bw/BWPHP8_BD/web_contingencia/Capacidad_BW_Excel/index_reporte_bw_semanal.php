<?php
/**
 * Ventana del reporte BW semanal: selector de semana, botones de descarga (el Excel se arma desde
 * OLT_REPORTE_BW_ANUAL al presionarlos) y alertas de la semana.
 * Se carga desde el menú: $(".contenido").load('../Capacidad_BW_Excel/index_reporte_bw_semanal.php');
 * Portal OLT de contingencia (PHP 5.3+).
 */
//$idPag = 'informe_capacidad_bw';
if (file_exists(__DIR__ . '/../perfiles/getPerfiles.php')) {
    include_once(__DIR__ . '/../perfiles/getPerfiles.php');
    include(__DIR__ . '/../perfiles/proceso.php');
}
require_once __DIR__ . '/lib_bd/bw_web.php';

$semanas = array();
$error = '';
try {
    $mysqli = bw_conectar_bd();
    $semanas = bw_anual_semanas($mysqli);
    $mysqli->close();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<style>
    /* Botones de descarga */
    .bw-descargas { display: flex; flex-wrap: wrap; justify-content: center; gap: 14px; margin: 6px auto 22px auto; max-width: 900px; }
    .bw-dl { position: relative; overflow: hidden; display: flex; align-items: center; gap: 14px; min-width: 300px; padding: 14px 20px 14px 14px;
             background: #fff; border: 1px solid #e3e8ef; border-radius: 14px; box-shadow: 0 1px 2px rgba(16,24,40,.06), 0 4px 14px rgba(16,24,40,.06);
             color: #1d2939; text-decoration: none; transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, opacity .2s ease; }
    .bw-dl:hover, .bw-dl:focus { color: #1d2939; text-decoration: none; transform: translateY(-2px); border-color: #b7dfc6;
             box-shadow: 0 2px 4px rgba(16,24,40,.06), 0 10px 24px rgba(16,24,40,.10); outline: none; }
    .bw-dl-icono { position: relative; flex: 0 0 44px; width: 44px; height: 44px; border-radius: 12px; background: #e8f6ee; color: #1d7a46;
             display: flex; align-items: center; justify-content: center; transition: background .2s ease; }
    .bw-dl-icono svg { width: 26px; height: 26px; }
    .bw-dl-texto { display: flex; flex-direction: column; text-align: left; line-height: 1.3; }
    .bw-dl-nombre { font-weight: 600; font-size: 14px; }
    .bw-dl-estado { font-size: 12px; color: #667085; }
    .bw-dl-nota { flex-basis: 100%; text-align: center; font-size: 12px; color: #98a2b3; }
    .bw-dl-error { flex-basis: 100%; max-width: 620px; padding: 10px 14px; border-radius: 10px; background: #fef3f2; border: 1px solid #fecdca;
             color: #b42318; font-size: 13px; text-align: center; }
    .bw-spinner, .bw-ico-ok { display: none; }
    .bw-spinner { width: 24px; height: 24px; border-radius: 50%; border: 3px solid #b7dfc6; border-top-color: #1d7a46; animation: bw-giro .8s linear infinite; }
    .bw-dl-barra { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: #e8f6ee; opacity: 0; transition: opacity .2s ease; }
    .bw-dl-barra::after { content: ''; position: absolute; top: 0; bottom: 0; width: 35%; border-radius: 3px;
             background: linear-gradient(90deg, #34c27a, #1d7a46); animation: bw-barra 1.2s ease-in-out infinite; }
    /* Estados */
    .bw-dl.bw-generando { cursor: progress; border-color: #b7dfc6; transform: none; }
    .bw-dl.bw-generando .bw-ico-xls { display: none; }
    .bw-dl.bw-generando .bw-spinner { display: block; }
    .bw-dl.bw-generando .bw-dl-barra { opacity: 1; }
    .bw-dl.bw-generando .bw-dl-estado { color: #1d7a46; }
    .bw-dl.bw-listo .bw-ico-xls { display: none; }
    .bw-dl.bw-listo .bw-ico-ok { display: block; animation: bw-pop .3s ease; }
    .bw-dl.bw-listo .bw-dl-icono { background: #1d7a46; color: #fff; }
    .bw-dl.bw-bloqueado { pointer-events: none; opacity: .45; filter: grayscale(.4); }
    #bw_semana:disabled { cursor: not-allowed; opacity: .6; }
    @keyframes bw-giro { to { transform: rotate(360deg); } }
    @keyframes bw-barra { 0% { left: -35%; } 100% { left: 100%; } }
    @keyframes bw-pop { 0% { transform: scale(.4); } 70% { transform: scale(1.15); } 100% { transform: scale(1); } }
    @media (prefers-reduced-motion: reduce) { .bw-spinner, .bw-dl-barra::after { animation-duration: 2.4s; } }
    /* Alertas */
    .bw-alertas { max-width: 1100px; margin: 0 auto 30px auto; text-align: left; color: #1d2939; }
    .bw-card { background: #fff; border: 1px solid #e3e8ef; border-radius: 14px; overflow: hidden;
             box-shadow: 0 1px 2px rgba(16,24,40,.06), 0 4px 14px rgba(16,24,40,.06); }
    .bw-card-cab { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 16px 20px; border-bottom: 1px solid #eef1f5; }
    .bw-card-icono { flex: 0 0 40px; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .bw-card-icono svg { width: 22px; height: 22px; }
    .bw-card-titulo { flex: 1 1 auto; line-height: 1.3; }
    .bw-card-titulo div { font-weight: 600; font-size: 15px; }
    .bw-card-titulo small { font-size: 12px; color: #667085; }
    .bw-chips { display: flex; flex-wrap: wrap; gap: 6px; }
    .bw-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px;
             background: #f2f4f7; color: #667085; }
    .bw-chip b { min-width: 18px; padding: 0 5px; border-radius: 999px; background: rgba(255,255,255,.8); text-align: center; font-weight: 600; }
    .bw-rojo  { background: #fef3f2; color: #b42318; }
    .bw-verde { background: #e8f6ee; color: #1d7a46; }
    .bw-ambar { background: #fffaeb; color: #b54708; }
    .bw-azul  { background: #eff4ff; color: #3538cd; }
    .bw-vacio { padding: 18px 20px; font-size: 13px; color: #1d7a46; }
    .bw-seccion { padding: 16px 20px 6px 20px; }
    .bw-seccion + .bw-seccion { border-top: 1px solid #eef1f5; }
    .bw-seccion h4 { display: flex; align-items: center; gap: 8px; margin: 0 0 10px 0; font-size: 13px; font-weight: 600;
             text-transform: uppercase; letter-spacing: .03em; color: #475467; }
    .bw-punto { width: 8px; height: 8px; border-radius: 50%; }
    .bw-punto.bw-rojo { background: #f04438; } .bw-punto.bw-ambar { background: #f79009; } .bw-punto.bw-azul { background: #444ce7; }
    .bw-tabla-scroll { overflow-x: auto; margin-bottom: 10px; }
    .bw-tabla { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .bw-tabla th { padding: 8px 12px; background: #f9fafb; color: #667085; font-weight: 600; font-size: 11.5px; white-space: nowrap;
             border-bottom: 1px solid #eaecf0; }
    .bw-tabla th:first-child { border-top-left-radius: 8px; } .bw-tabla th:last-child { border-top-right-radius: 8px; }
    .bw-tabla td { padding: 8px 12px; border-bottom: 1px solid #f2f4f7; vertical-align: middle; }
    .bw-tabla tbody tr:last-child td { border-bottom: 0; }
    .bw-tabla tbody tr:hover td { background: #fafbfc; }
    .bw-num { text-align: right; font-variant-numeric: tabular-nums; }
    .bw-olt { font-weight: 600; }
    .bw-pct { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #fef3f2; color: #b42318; font-weight: 600; }
    .bw-celda { display: inline-block; padding: 1px 7px; border-radius: 6px; background: #f2f4f7; color: #475467;
             font-family: Consolas, 'SFMono-Regular', monospace; font-size: 11.5px; }
    .bw-nuevas { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
    .bw-nueva { display: flex; flex-direction: column; gap: 2px; padding: 10px 14px; border-radius: 10px; border: 1px solid #e0e7ff;
             background: #f8f9ff; min-width: 220px; }
    .bw-nueva-pe { font-size: 12px; color: #3538cd; }
    .bw-nueva small { font-size: 11.5px; color: #667085; }
</style>
<body>
    <center><h1>Reporte BW Semanal</h1></center></br>
<?php if ($error != '') { ?>
    <center>Error al leer la BD: <?php echo bw_h($error); ?></center>
<?php } elseif (count($semanas) == 0) { ?>
    <center>Aún no hay semanas cargadas en <?php echo BW_TABLA_ANUAL; ?>.</center>
<?php } else { ?>
    <div class="form-group text-center">
        <label for="bw_semana">Semana:</label>
        <select id="bw_semana" class="form-control" style="display:inline-block; width:auto;" onchange="bwCargarSemana(this.value)">
<?php foreach ($semanas as $s) {
    $desde = date('d-m-Y', bw_ts_dia($s[0], ($s[1] - 1) * 7));
    $hasta = date('d-m-Y', bw_ts_dia($s[0], ($s[1] - 1) * 7 + 6)); ?>
            <option value="<?php echo $s[0] . '-' . $s[1]; ?>">W-<?php echo $s[1]; ?> <?php echo $s[0]; ?> (<?php echo $desde; ?> al <?php echo $hasta; ?>)</option>
<?php } ?>
        </select>
    </div>
    <div id="bw_detalle"><center>Cargando...</center></div>
    <script type="text/javascript">
        function bwCargarSemana(valor) {
            var p = valor.split('-');
            var div = document.getElementById('bw_detalle');
            div.innerHTML = '<center>Cargando...</center>';
            fetch('<?php echo BW_URL_MODULO; ?>alertas_reporte_bw.php?anio=' + p[0] + '&semana=' + p[1])
                .then(function (r) { return r.text(); })
                .then(function (html) { div.innerHTML = html; })
                .catch(function () { div.innerHTML = '<center>No se pudieron cargar las alertas.</center>'; });
        }
        // Descarga: se pide el Excel por fetch para bloquear los botones y mostrar el avance hasta que llega el archivo
        var bwDescargando = false;
        document.getElementById('bw_detalle').addEventListener('click', function (ev) {
            var btn = ev.target.closest ? ev.target.closest('.bw-dl') : null;
            if (!btn) {
                return;
            }
            ev.preventDefault();
            if (bwDescargando) {
                return;
            }
            bwDescargar(btn);
        });

        function bwDescargar(btn) {
            var div = document.getElementById('bw_detalle');
            var select = document.getElementById('bw_semana');
            var botones = div.querySelectorAll('.bw-dl');
            var estado = btn.querySelector('.bw-dl-estado');
            var error = div.querySelector('.bw-dl-error');
            var inicio = Date.now();
            var reloj;

            bwDescargando = true;
            select.disabled = true;
            error.style.display = 'none';
            for (var i = 0; i < botones.length; i++) {
                botones[i].classList.remove('bw-listo');
                botones[i].classList.add(botones[i] === btn ? 'bw-generando' : 'bw-bloqueado');
                botones[i].setAttribute('aria-disabled', 'true');
            }
            estado.textContent = 'Generando Excel\u2026';
            reloj = setInterval(function () {
                estado.textContent = 'Generando Excel\u2026 ' + Math.round((Date.now() - inicio) / 1000) + ' s';
            }, 1000);

            function terminar() {
                clearInterval(reloj);
                bwDescargando = false;
                select.disabled = false;
                for (var i = 0; i < botones.length; i++) {
                    botones[i].classList.remove('bw-generando', 'bw-bloqueado');
                    botones[i].removeAttribute('aria-disabled');
                }
            }

            fetch(btn.getAttribute('href'), { cache: 'no-store' })
                .then(function (r) {
                    if (!r.ok) {
                        return r.text().then(function (t) { throw new Error(t || ('Error HTTP ' + r.status)); });
                    }
                    return r.blob();
                })
                .then(function (blob) {
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = btn.getAttribute('data-nombre');
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(function () { URL.revokeObjectURL(url); }, 10000);
                    terminar();
                    btn.classList.add('bw-listo');
                    estado.textContent = 'Descargado';
                    setTimeout(function () {
                        btn.classList.remove('bw-listo');
                        estado.textContent = 'Descargar Excel';
                    }, 3000);
                })
                .catch(function (e) {
                    terminar();
                    estado.textContent = 'Descargar Excel';
                    error.textContent = e.message || 'No se pudo generar el Excel.';
                    error.style.display = 'block';
                });
        }

        bwCargarSemana(document.getElementById('bw_semana').value);
    </script>
<?php } ?>
</body>
