<?php $headLead = 'An on-screen ruler. Calibrate it once with a credit card for real-size measurements.'; require ROOT_DIR . '/views/app/_head.php'; ?>
<div class="page-body">
    <div class="a-container">
        <div class="panel">
            <div class="form-actions" style="margin:0 0 18px;align-items:center">
                <div class="seg" role="radiogroup" aria-label="Unit">
                    <label><input type="radio" name="unit" value="cm" checked><span>cm</span></label>
                    <label><input type="radio" name="unit" value="in"><span>inches</span></label>
                    <label><input type="radio" name="unit" value="mm"><span>mm</span></label>
                </div>
                <button class="btn btn-sm btn-outline" type="button" id="calibrate">Calibrate</button>
                <span id="reading" style="font:400 1.6rem var(--serif);color:var(--navy);margin-left:auto">Drag on the ruler to measure</span>
            </div>
            <div class="tool-stage" style="min-height:260px;cursor:crosshair;touch-action:none" id="stage"><canvas id="ruler" style="width:100%;height:260px"></canvas></div>
            <div id="calib" hidden class="notice"><?= icon('ruler') ?><div>Hold a standard bank card (85.6 mm wide) against the screen and drag the slider until the blue bar matches its width.<br>
                <input type="range" id="calib-range" min="200" max="600" step="1" style="width:100%;margin-top:10px"><div id="calib-bar" style="height:14px;background:var(--blue);margin-top:8px"></div>
                <button class="btn btn-sm" type="button" id="calib-done" style="margin-top:10px">Done</button></div></div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('ruler'), ctx = canvas.getContext('2d');
    var KEY = 'fpd_ruler_px_per_mm';
    var dpr = window.devicePixelRatio || 1;
    var pxPerMm = parseFloat(localStorage.getItem(KEY)) || (96 / 25.4); // CSS px per mm
    var start = null, end = null;
    var unit = function () { return document.querySelector('input[name=unit]:checked').value; };
    function draw() {
        var w = canvas.clientWidth, h = canvas.clientHeight;
        canvas.width = w * dpr; canvas.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, w, h); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, 70);
        ctx.strokeStyle = '#001d36'; ctx.fillStyle = '#001d36'; ctx.font = '11px Mulish, sans-serif';
        var inch = unit() === 'in';
        var step = inch ? pxPerMm * 25.4 / 16 : pxPerMm; // tick spacing
        for (var i = 0, x = 0; x < w; i++, x = i * step) {
            var major = inch ? i % 16 === 0 : i % 10 === 0, mid = inch ? i % 8 === 0 : i % 5 === 0;
            var len = major ? 34 : mid ? 22 : 12;
            ctx.beginPath(); ctx.moveTo(x + .5, 0); ctx.lineTo(x + .5, len); ctx.stroke();
            if (major) ctx.fillText(String(inch ? i / 16 : unit() === 'mm' ? i : i / 10), x + 3, 48);
        }
        if (start && end) {
            ctx.fillStyle = 'rgba(33,150,243,.18)'; ctx.fillRect(Math.min(start, end), 70, Math.abs(end - start), h - 70);
            ctx.fillStyle = '#2196f3'; ctx.fillRect(start - 1, 0, 2, h); ctx.fillRect(end - 1, 0, 2, h);
            var mm = Math.abs(end - start) / pxPerMm, u = unit();
            var txt = u === 'in' ? (mm / 25.4).toFixed(2) + ' in' : u === 'mm' ? mm.toFixed(0) + ' mm' : (mm / 10).toFixed(1) + ' cm';
            document.getElementById('reading').textContent = txt;
        }
    }
    var pos = function (ev) { var r = canvas.getBoundingClientRect(); return (ev.touches ? ev.touches[0].clientX : ev.clientX) - r.left; };
    canvas.addEventListener('pointerdown', function (ev) { start = end = pos(ev); canvas.setPointerCapture(ev.pointerId); draw(); });
    canvas.addEventListener('pointermove', function (ev) { if (start !== null && ev.buttons) { end = pos(ev); draw(); } });
    document.querySelectorAll('input[name=unit]').forEach(function (r) { r.addEventListener('change', draw); });
    window.addEventListener('resize', draw);
    var calib = document.getElementById('calib'), range = document.getElementById('calib-range'), bar = document.getElementById('calib-bar');
    document.getElementById('calibrate').addEventListener('click', function () { calib.hidden = false; range.value = Math.round(pxPerMm * 85.6); bar.style.width = range.value + 'px'; });
    range.addEventListener('input', function () { bar.style.width = range.value + 'px'; });
    document.getElementById('calib-done').addEventListener('click', function () { pxPerMm = range.value / 85.6; try { localStorage.setItem(KEY, pxPerMm); } catch (e) {} calib.hidden = true; draw(); });
    draw();
});
</script>
