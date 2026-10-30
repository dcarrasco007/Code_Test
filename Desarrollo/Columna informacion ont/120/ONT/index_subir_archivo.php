<?php
//$idPag = 'subir_archivo_ONT';
$idPag2 = '313';
include_once(__DIR__ . '/../perfiles/getPerfiles.php');
//checkAcc(getUser(),$idPag);
//checkAccV2(getUser(),$idPag2);
include(__DIR__ . '/../perfiles/proceso.php');

include (__DIR__ . '/../conexion/conexion_db.php');
$conn = mysql_connect($host144_geret,$user144_geret,$pass144_geret) or die(mysql_error());
$db = mysql_select_db("Aden") or die("error de conexion");

// Resumen de la ultima carga (se pide por AJAX: index_subir_archivo.php?stats=1)
if (isset($_GET['stats'])) {
    $stats = array();
    $r = mysql_query("SELECT COUNT(*), COUNT(DISTINCT equipo) FROM OLT_INFO_ONT_PRUEBA");
    $row = mysql_fetch_array($r, MYSQL_NUM);
    $stats['ont_archivo'] = (int)$row[0];
    $stats['equipos_archivo'] = (int)$row[1];

    $r = mysql_query("SELECT COUNT(*), SUM(vas_profile IS NOT NULL AND vas_profile <> '--'), SUM(vas_profile = '--'), SUM(vas_profile IS NULL)
        FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO");
    $row = mysql_fetch_array($r, MYSQL_NUM);
    $stats['ont_detalle'] = (int)$row[0];
    $stats['con_vas'] = (int)$row[1];
    $stats['sin_match'] = (int)$row[2];
    $stats['pendientes'] = (int)$row[3];

    $stats['top_vas'] = array();
    $r = mysql_query("SELECT vas_profile, COUNT(*) AS cantidad FROM OLT_INFORMACION_ONT_DETALLE_COMPLETO
        WHERE vas_profile IS NOT NULL AND vas_profile <> '--'
        GROUP BY vas_profile ORDER BY cantidad DESC LIMIT 6");
    while ($row = mysql_fetch_array($r, MYSQL_NUM)) {
        $stats['top_vas'][] = array('nombre' => $row[0], 'cantidad' => (int)$row[1]);
    }

    $r = mysql_query("SELECT UPDATE_TIME FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'Aden' AND TABLE_NAME = 'OLT_INFO_ONT_PRUEBA'");
    $row = $r ? mysql_fetch_array($r, MYSQL_NUM) : null;
    $stats['ultima_carga'] = $row && $row[0] ? date('d-m-Y H:i', strtotime($row[0])) : null;

    header('Content-Type: application/json');
    echo json_encode($stats);
    exit;
}
?>
<style>
    .ozu { --ozu-bg:#f4f6f9; --ozu-card:#ffffff; --ozu-line:#e3e8ef; --ozu-text:#1f2a37; --ozu-muted:#6b7686;
           --ozu-accent:#1d5fd1; --ozu-accent-soft:#e8effc; --ozu-ok:#1a8f5a; --ozu-ok-soft:#e5f5ed;
           --ozu-err:#c8372d; --ozu-err-soft:#fbeae8; --ozu-warn:#b7791f;
           font-family: "Segoe UI", system-ui, -apple-system, Roboto, Arial, sans-serif; color: var(--ozu-text);
           background: var(--ozu-bg); padding: 28px 24px 36px; border-radius: 12px; }
    .ozu *, .ozu *::before, .ozu *::after { box-sizing: border-box; }
    .ozu-wrap { max-width: 1180px; margin: 0 auto; }
    .ozu-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 22px; }
    .ozu-head h1 { font-size: 24px; font-weight: 600; margin: 0 0 4px; letter-spacing: -.2px; color: var(--ozu-text); }
    .ozu-head p { margin: 0; color: var(--ozu-muted); font-size: 14px; }
    .ozu-pill { display: inline-flex; align-items: center; gap: 8px; background: var(--ozu-card); border: 1px solid var(--ozu-line);
                border-radius: 999px; padding: 6px 14px; font-size: 13px; color: var(--ozu-muted); }
    .ozu-pill b { color: var(--ozu-text); font-weight: 600; }
    .ozu-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ozu-ok); }

    .ozu-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: 20px; align-items: start; }
    .ozu-card { background: var(--ozu-card); border: 1px solid var(--ozu-line); border-radius: 12px; padding: 22px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
    .ozu-card + .ozu-card { margin-top: 20px; }
    .ozu-card h2 { font-size: 15px; font-weight: 600; margin: 0 0 16px; color: var(--ozu-text); display: flex; justify-content: space-between; align-items: center; }
    .ozu-card h2 small { font-size: 12px; font-weight: 400; color: var(--ozu-muted); }

    .ozu-drop { border: 2px dashed #c5d0de; border-radius: 10px; padding: 34px 20px; text-align: center; cursor: pointer;
                transition: border-color .15s, background .15s; background: #fafbfd; display: block; margin: 0; font-weight: 400; }
    .ozu-drop:hover, .ozu-drop.is-over { border-color: var(--ozu-accent); background: var(--ozu-accent-soft); }
    .ozu-drop.is-disabled { pointer-events: none; opacity: .55; }
    .ozu-drop svg { width: 40px; height: 40px; color: var(--ozu-accent); margin-bottom: 10px; }
    .ozu-drop strong { display: block; font-size: 15px; font-weight: 600; margin-bottom: 4px; }
    .ozu-drop span { font-size: 13px; color: var(--ozu-muted); }
    .ozu-drop input[type="file"] { display: none !important; }

    .ozu-file { display: none; align-items: center; gap: 12px; margin-top: 14px; padding: 12px 14px; border: 1px solid var(--ozu-line); border-radius: 10px; }
    .ozu-file.is-visible { display: flex; }
    .ozu-file-ico { width: 38px; height: 38px; border-radius: 8px; background: var(--ozu-accent-soft); color: var(--ozu-accent);
                    display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex: none; }
    .ozu-file-info { flex: 1; min-width: 0; }
    .ozu-file-name { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .ozu-file-meta { font-size: 12px; color: var(--ozu-muted); }
    .ozu-x { border: 0; background: transparent; color: var(--ozu-muted); font-size: 20px; line-height: 1; cursor: pointer; padding: 4px 8px; border-radius: 6px; }
    .ozu-x:hover { background: var(--ozu-bg); color: var(--ozu-text); }

    .ozu-actions { display: flex; align-items: center; gap: 14px; margin-top: 18px; flex-wrap: wrap; }
    .ozu-btn { appearance: none; border: 0; border-radius: 8px; background: var(--ozu-accent); color: #fff; font-size: 14px; font-weight: 600;
               padding: 11px 22px; cursor: pointer; transition: background .15s, opacity .15s; }
    .ozu-btn:hover { background: #174ea9; }
    .ozu-btn:disabled { opacity: .45; cursor: not-allowed; background: var(--ozu-accent); }
    .ozu-hint { font-size: 13px; color: var(--ozu-muted); }

    .ozu-steps { list-style: none; margin: 22px 0 0; padding: 0; border-top: 1px solid var(--ozu-line); }
    .ozu-step { display: grid; grid-template-columns: 30px 1fr auto; gap: 12px; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--ozu-line); }
    .ozu-step-n { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
                  font-size: 13px; font-weight: 600; background: var(--ozu-bg); color: var(--ozu-muted); border: 1px solid var(--ozu-line); }
    .ozu-step-t { font-size: 14px; font-weight: 600; }
    .ozu-step-d { font-size: 12px; color: var(--ozu-muted); margin-top: 2px; }
    .ozu-step-s { font-size: 12px; color: var(--ozu-muted); font-variant-numeric: tabular-nums; text-align: right; }
    .ozu-bar { height: 4px; background: var(--ozu-line); border-radius: 4px; margin-top: 8px; overflow: hidden; display: none; }
    .ozu-bar i { display: block; height: 100%; width: 0; background: var(--ozu-accent); transition: width .2s; }
    .ozu-bar.is-indeterminate i { width: 35%; animation: ozu-slide 1.3s ease-in-out infinite; }
    @keyframes ozu-slide { 0% { transform: translateX(-100%); } 100% { transform: translateX(300%); } }
    .ozu-step.is-active .ozu-step-n { background: var(--ozu-accent); border-color: var(--ozu-accent); color: #fff; }
    .ozu-step.is-active .ozu-bar { display: block; }
    .ozu-step.is-done .ozu-step-n { background: var(--ozu-ok); border-color: var(--ozu-ok); color: #fff; }
    .ozu-step.is-done .ozu-step-s { color: var(--ozu-ok); }
    .ozu-step.is-error .ozu-step-n { background: var(--ozu-err); border-color: var(--ozu-err); color: #fff; }
    .ozu-step.is-error .ozu-step-s { color: var(--ozu-err); }

    .ozu-alert { display: none; margin-top: 18px; padding: 14px 16px; border-radius: 10px; font-size: 14px; }
    .ozu-alert.is-ok { display: block; background: var(--ozu-ok-soft); color: #11603d; border: 1px solid #bfe5d1; }
    .ozu-alert.is-err { display: block; background: var(--ozu-err-soft); color: #8d241c; border: 1px solid #f2c7c2; }
    .ozu-alert pre { margin: 10px 0 0; padding: 10px; background: rgba(255,255,255,.6); border: 0; border-radius: 6px; font-size: 12px;
                     max-height: 160px; overflow: auto; white-space: pre-wrap; color: inherit; }

    .ozu-kpis { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .ozu-kpi { border: 1px solid var(--ozu-line); border-radius: 10px; padding: 14px; }
    .ozu-kpi-l { font-size: 12px; color: var(--ozu-muted); }
    .ozu-kpi-v { font-size: 22px; font-weight: 600; margin-top: 4px; font-variant-numeric: tabular-nums; }
    .ozu-kpi-v small { font-size: 12px; font-weight: 400; color: var(--ozu-muted); margin-left: 4px; }
    .ozu-cover { margin-top: 16px; }
    .ozu-cover-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
    .ozu-cover-track { display: flex; height: 10px; border-radius: 6px; overflow: hidden; background: var(--ozu-line); }
    .ozu-cover-track i { display: block; height: 100%; }
    .ozu-legend { display: flex; gap: 16px; flex-wrap: wrap; margin-top: 8px; font-size: 12px; color: var(--ozu-muted); }
    .ozu-legend span::before { content: ""; display: inline-block; width: 8px; height: 8px; border-radius: 2px; margin-right: 6px; background: var(--c); }

    .ozu-top { list-style: none; margin: 0; padding: 0; }
    .ozu-top li { margin-bottom: 10px; }
    .ozu-top-row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; margin-bottom: 4px; }
    .ozu-top-row span:first-child { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ozu-top-row span:last-child { color: var(--ozu-muted); font-variant-numeric: tabular-nums; }
    .ozu-top-bar { height: 6px; border-radius: 4px; background: var(--ozu-accent-soft); }
    .ozu-top-bar i { display: block; height: 100%; border-radius: 4px; background: var(--ozu-accent); }
    .ozu-empty { font-size: 13px; color: var(--ozu-muted); }

    .ozu-info { margin: 0; padding: 0; list-style: none; counter-reset: ozu; }
    .ozu-info li { position: relative; padding: 0 0 12px 34px; font-size: 13px; color: var(--ozu-muted); }
    .ozu-info li::before { counter-increment: ozu; content: counter(ozu); position: absolute; left: 0; top: -1px; width: 22px; height: 22px;
                           border-radius: 50%; background: var(--ozu-bg); border: 1px solid var(--ozu-line); font-size: 11px; font-weight: 600;
                           color: var(--ozu-text); display: flex; align-items: center; justify-content: center; }
    .ozu-info b { color: var(--ozu-text); font-weight: 600; }
    .ozu-info code { background: var(--ozu-bg); color: var(--ozu-text); padding: 1px 5px; border-radius: 4px; font-size: 12px; }

    .ozu-skel { display: inline-block; width: 60px; height: 18px; border-radius: 4px; background: linear-gradient(90deg, #eef1f5, #f7f8fa, #eef1f5);
                background-size: 200% 100%; animation: ozu-sk 1.2s linear infinite; vertical-align: middle; }
    @keyframes ozu-sk { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    @media (max-width: 900px) { .ozu-grid { grid-template-columns: 1fr; } .ozu { padding: 20px 16px 28px; } }
</style>

<div class="ozu" id="ozu">
  <div class="ozu-wrap">
    <div class="ozu-head">
        <div>
            <h1>Carga de archivo ONT</h1>
            <p>Actualiza el inventario de ONT desde el .zip exportado y completa el VAS Profile de la vista ONT Detalle.</p>
        </div>
        <div class="ozu-pill"><span class="ozu-dot"></span>&Uacute;ltima carga: <b id="ozu-last"><span class="ozu-skel"></span></b></div>
    </div>

    <div class="ozu-grid">
        <div>
            <div class="ozu-card">
                <h2>Subir archivo <small>Solo .zip</small></h2>

                <label class="ozu-drop" id="ozu-drop" for="fileToUpload">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 001 1h14a1 1 0 001-1v-3"/>
                    </svg>
                    <strong>Arrastra el archivo aqu&iacute; o haz clic para seleccionarlo</strong>
                    <span>Archivo .zip con los CSV de ONU GPON (por ejemplo All_GPON_ONU.csv)</span>
                    <input id="fileToUpload" type="file" name="fileToUpload" accept=".zip" />
                </label>

                <div class="ozu-file" id="ozu-file">
                    <div class="ozu-file-ico">ZIP</div>
                    <div class="ozu-file-info">
                        <div class="ozu-file-name" id="ozu-file-name"></div>
                        <div class="ozu-file-meta" id="ozu-file-meta"></div>
                    </div>
                    <button type="button" class="ozu-x" id="ozu-clear" title="Quitar archivo">&times;</button>
                </div>

                <div class="ozu-actions">
                    <button type="button" class="ozu-btn" id="envio" disabled>Subir y actualizar</button>
                    <span class="ozu-hint">El procesamiento toma desde 3 minutos. No cierres ni cambies de p&aacute;gina mientras corre.</span>
                </div>

                <ol class="ozu-steps">
                    <li class="ozu-step" data-step="1">
                        <div class="ozu-step-n">1</div>
                        <div>
                            <div class="ozu-step-t">Subida del archivo</div>
                            <div class="ozu-step-d">Transferencia del .zip al servidor</div>
                            <div class="ozu-bar"><i></i></div>
                        </div>
                        <div class="ozu-step-s">Pendiente</div>
                    </li>
                    <li class="ozu-step" data-step="2">
                        <div class="ozu-step-n">2</div>
                        <div>
                            <div class="ozu-step-t">Procesamiento en servidor</div>
                            <div class="ozu-step-d">Descompresi&oacute;n, carga de CSV y actualizaci&oacute;n de VAS Profile</div>
                            <div class="ozu-bar is-indeterminate"><i></i></div>
                        </div>
                        <div class="ozu-step-s">Pendiente</div>
                    </li>
                    <li class="ozu-step" data-step="3">
                        <div class="ozu-step-n">3</div>
                        <div>
                            <div class="ozu-step-t">Resultado</div>
                            <div class="ozu-step-d">Verificaci&oacute;n y resumen actualizado</div>
                        </div>
                        <div class="ozu-step-s">Pendiente</div>
                    </li>
                </ol>

                <div class="ozu-alert" id="result_busqueda"></div>
            </div>

            <div class="ozu-card">
                <h2>Qu&eacute; hace este proceso</h2>
                <ol class="ozu-info">
                    <li>Vac&iacute;a la tabla <code>OLT_INFO_ONT_PRUEBA</code> y descomprime el .zip en el servidor.</li>
                    <li>Lee cada CSV y carga <b>equipo, frame, slot, port, ONU, nombre, serie, line/service profile y VAS profile</b>.</li>
                    <li>Cruza por <b>equipo + frame + slot + port + ONU</b> contra la vista ONT Detalle y completa la columna <b>VAS Profile</b>. Si una ONT no est&aacute; en el archivo queda con <code>--</code>.</li>
                    <li>Elimina los archivos temporales del servidor.</li>
                </ol>
            </div>
        </div>

        <div>
            <div class="ozu-card">
                <h2>Estado actual <small id="ozu-refresh-at"></small></h2>
                <div class="ozu-kpis">
                    <div class="ozu-kpi"><div class="ozu-kpi-l">ONT en &uacute;ltimo archivo</div><div class="ozu-kpi-v" id="ozu-k-ont"><span class="ozu-skel"></span></div></div>
                    <div class="ozu-kpi"><div class="ozu-kpi-l">Equipos en archivo</div><div class="ozu-kpi-v" id="ozu-k-eq"><span class="ozu-skel"></span></div></div>
                    <div class="ozu-kpi"><div class="ozu-kpi-l">ONT en vista Detalle</div><div class="ozu-kpi-v" id="ozu-k-det"><span class="ozu-skel"></span></div></div>
                    <div class="ozu-kpi"><div class="ozu-kpi-l">Con VAS Profile</div><div class="ozu-kpi-v" id="ozu-k-vas"><span class="ozu-skel"></span></div></div>
                </div>
                <div class="ozu-cover">
                    <div class="ozu-cover-row"><span>Cobertura VAS Profile en ONT Detalle</span><b id="ozu-cov-pct">&ndash;</b></div>
                    <div class="ozu-cover-track"><i id="ozu-cov-ok" style="background:#1a8f5a;width:0"></i><i id="ozu-cov-nm" style="background:#b7791f;width:0"></i></div>
                    <div class="ozu-legend">
                        <span style="--c:#1a8f5a">Con VAS <b id="ozu-l-ok">&ndash;</b></span>
                        <span style="--c:#b7791f">Sin coincidencia (--) <b id="ozu-l-nm">&ndash;</b></span>
                        <span style="--c:#e3e8ef">Sin procesar <b id="ozu-l-pe">&ndash;</b></span>
                    </div>
                </div>
            </div>

            <div class="ozu-card">
                <h2>VAS Profile m&aacute;s frecuentes</h2>
                <ul class="ozu-top" id="ozu-top"><li class="ozu-empty">Cargando&hellip;</li></ul>
            </div>
        </div>
    </div>
  </div>
</div>

<script type="text/javascript">
(function () {
    var STATS_URL  = '../ONT/index_subir_archivo.php?stats=1';
    var UPLOAD_URL = '../js/upload/doajaxfileupload10.php';
    var SUBMIT_URL = '../ONT/submit_ONT_zip.php';

    var root   = document.getElementById('ozu');
    var input  = document.getElementById('fileToUpload');
    var drop   = document.getElementById('ozu-drop');
    var btn    = document.getElementById('envio');
    var alertB = document.getElementById('result_busqueda');
    var running = false, timer = null;

    function $id(id) { return document.getElementById(id); }
    function fmtN(n) { return (n || 0).toLocaleString('es-CL'); }
    function fmtSize(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function fmtTime(s) { var m = Math.floor(s / 60), r = s % 60; return m + ':' + (r < 10 ? '0' : '') + r; }
    function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function now() { var d = new Date(); return d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' }); }

    function step(n, state, text) {
        var el = root.querySelector('.ozu-step[data-step="' + n + '"]');
        el.className = 'ozu-step' + (state ? ' is-' + state : '');
        if (text !== undefined) el.querySelector('.ozu-step-s').textContent = text;
        if (state === 'done') el.querySelector('.ozu-step-n').innerHTML = '&#10003;';
        else if (state === 'error') el.querySelector('.ozu-step-n').innerHTML = '!';
        else el.querySelector('.ozu-step-n').textContent = n;
    }
    function resetSteps() { step(1, '', 'Pendiente'); step(2, '', 'Pendiente'); step(3, '', 'Pendiente'); setBar(0); }
    function setBar(p) { root.querySelector('.ozu-step[data-step="1"] .ozu-bar i').style.width = p + '%'; }
    function showAlert(type, html) { alertB.className = 'ozu-alert is-' + type; alertB.innerHTML = html; }
    function hideAlert() { alertB.className = 'ozu-alert'; alertB.innerHTML = ''; }

    function setFile(f) {
        hideAlert();
        if (f && !/\.zip$/i.test(f.name)) {
            showAlert('err', 'El archivo <b>' + esc(f.name) + '</b> no es un .zip.');
            f = null;
        }
        if (!f) { input.value = ''; }
        $id('ozu-file').className = 'ozu-file' + (f ? ' is-visible' : '');
        if (f) {
            $id('ozu-file-name').textContent = f.name;
            $id('ozu-file-meta').textContent = fmtSize(f.size) + ' · listo para subir';
        }
        btn.disabled = !f;
        resetSteps();
    }
    function lock(on) {
        running = on;
        btn.disabled = on || !input.files.length;
        btn.textContent = on ? 'Procesando…' : 'Subir y actualizar';
        drop.className = 'ozu-drop' + (on ? ' is-disabled' : '');
        $id('ozu-clear').style.visibility = on ? 'hidden' : 'visible';
    }

    input.addEventListener('change', function () { setFile(input.files[0]); });
    $id('ozu-clear').addEventListener('click', function () { setFile(null); });
    ['dragenter', 'dragover'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); if (!running) drop.classList.add('is-over'); });
    });
    ['dragleave', 'drop'].forEach(function (ev) {
        drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); });
    });
    drop.addEventListener('drop', function (e) {
        if (running || !e.dataTransfer.files.length) return;
        var f = e.dataTransfer.files[0];
        try { var dt = new DataTransfer(); dt.items.add(f); input.files = dt.files; } catch (err) {}
        setFile(f);
    });
    window.addEventListener('beforeunload', function (e) { if (running) { e.preventDefault(); e.returnValue = ''; } });

    btn.addEventListener('click', function () {
        var f = input.files[0];
        if (!f || running) return;
        lock(true); hideAlert(); resetSteps();
        step(1, 'active', '0%');

        var fd = new FormData();
        fd.append('fileToUpload', f);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', UPLOAD_URL);
        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) { var p = Math.round(e.loaded / e.total * 100); setBar(p); step(1, 'active', p + '%'); }
        };
        xhr.onload = function () {
            // doajaxfileupload10.php responde {error: '...', msg: 'subido correctamente || nombre.zip'}
            var txt = xhr.responseText || '';
            var err = (txt.match(/error:\s*'([^']*)'/) || [])[1] || '';
            var msg = (txt.match(/msg:\s*'([^']*)'/) || [])[1] || '';
            var parts = msg.split('||');
            if (xhr.status !== 200 || err || parts.length < 2) {
                return fail(1, 'No se pudo subir el archivo al servidor.', err || txt);
            }
            step(1, 'done', fmtSize(f.size));
            procesar(parts[1].trim(), f.size);
        };
        xhr.onerror = function () { fail(1, 'Error de red al subir el archivo.'); };
        xhr.send(fd);
    });

    function procesar(nombre, subido) {
        var t0 = Date.now();
        step(2, 'active', '0:00');
        timer = setInterval(function () { step(2, 'active', fmtTime(Math.round((Date.now() - t0) / 1000))); }, 1000);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', SUBMIT_URL);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        xhr.onload = function () {
            clearInterval(timer);
            var dur = fmtTime(Math.round((Date.now() - t0) / 1000));
            var txt = (xhr.responseText || '').trim();
            if (xhr.status === 200 && /OK$/.test(txt)) {
                setFile(null);
                step(1, 'done', fmtSize(subido)); step(2, 'done', dur); step(3, 'done', now());
                showAlert('ok', '<b>Carga completada.</b> El archivo <b>' + esc(nombre) + '</b> se proces&oacute; en ' + dur +
                    ' y se actualiz&oacute; el VAS Profile de la vista ONT Detalle.' +
                    (txt !== 'OK' ? '<pre>' + esc(txt.replace(/OK$/, '').trim()) + '</pre>' : ''));
                cargarStats();
                lock(false);
            } else {
                fail(2, 'El servidor no pudo procesar el archivo.', txt || ('HTTP ' + xhr.status));
            }
        };
        xhr.onerror = function () { clearInterval(timer); fail(2, 'Se perdi&oacute; la conexi&oacute;n mientras se procesaba el archivo. Revisa el resumen antes de volver a cargarlo.'); };
        xhr.send('name_xls=' + encodeURIComponent(nombre));
    }

    function fail(n, msg, detalle) {
        clearInterval(timer);
        step(n, 'error', 'Error');
        step(3, 'error', now());
        showAlert('err', '<b>' + msg + '</b>' + (detalle ? '<pre>' + esc(detalle) + '</pre>' : ''));
        lock(false);
    }

    function cargarStats() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', STATS_URL + '&_=' + Date.now());
        xhr.onload = function () {
            var s;
            try { s = JSON.parse(xhr.responseText); } catch (e) { return statsError(); }
            $id('ozu-last').textContent = s.ultima_carga || 'Sin registro';
            $id('ozu-k-ont').textContent = fmtN(s.ont_archivo);
            $id('ozu-k-eq').textContent = fmtN(s.equipos_archivo);
            $id('ozu-k-det').textContent = fmtN(s.ont_detalle);
            var pct = s.ont_detalle ? (s.con_vas / s.ont_detalle * 100) : 0;
            var pnm = s.ont_detalle ? (s.sin_match / s.ont_detalle * 100) : 0;
            $id('ozu-k-vas').innerHTML = fmtN(s.con_vas) + '<small>' + pct.toFixed(1) + '%</small>';
            $id('ozu-cov-pct').textContent = pct.toFixed(1) + '%';
            $id('ozu-cov-ok').style.width = pct + '%';
            $id('ozu-cov-nm').style.width = pnm + '%';
            $id('ozu-l-ok').textContent = fmtN(s.con_vas);
            $id('ozu-l-nm').textContent = fmtN(s.sin_match);
            $id('ozu-l-pe').textContent = fmtN(s.pendientes);
            $id('ozu-refresh-at').textContent = 'Actualizado ' + now();

            var ul = $id('ozu-top');
            if (!s.top_vas || !s.top_vas.length) { ul.innerHTML = '<li class="ozu-empty">Sin VAS Profile cargados todav&iacute;a.</li>'; return; }
            var max = s.top_vas[0].cantidad || 1;
            ul.innerHTML = s.top_vas.map(function (v) {
                return '<li><div class="ozu-top-row"><span title="' + esc(v.nombre) + '">' + esc(v.nombre) + '</span><span>' + fmtN(v.cantidad) + '</span></div>' +
                       '<div class="ozu-top-bar"><i style="width:' + (v.cantidad / max * 100) + '%"></i></div></li>';
            }).join('');
        };
        xhr.onerror = statsError;
        xhr.send();
    }
    function statsError() {
        ['ozu-last', 'ozu-k-ont', 'ozu-k-eq', 'ozu-k-det', 'ozu-k-vas'].forEach(function (id) { $id(id).textContent = '–'; });
        $id('ozu-top').innerHTML = '<li class="ozu-empty">No se pudo leer el resumen.</li>';
    }

    cargarStats();
})();
</script>
