<?php $headLead = 'Uses your phone’s motion sensor. Lay the phone flat on the surface to check it.'; require ROOT_DIR . '/views/app/_head.php'; ?>
<div class="page-body">
    <div class="a-container" style="max-width:640px;text-align:center">
        <div class="tool-stage" style="border-radius:50%;width:300px;height:300px;min-height:0;margin:0 auto;background:radial-gradient(circle,#fff 0,#f1f3f5 70%)">
            <div style="position:absolute;inset:50% auto auto 50%;width:60px;height:60px;margin:-30px 0 0 -30px;border:2px solid var(--navy);border-radius:50%"></div>
            <div id="bubble" style="position:absolute;left:50%;top:50%;width:46px;height:46px;margin:-23px 0 0 -23px;border-radius:50%;background:radial-gradient(circle at 35% 35%,#bbdefb,#2196f3);box-shadow:0 4px 10px rgba(0,29,54,.3);transition:transform .08s linear"></div>
        </div>
        <p style="font:400 2rem var(--serif);color:var(--navy);margin:20px 0 4px"><span id="lx">0.0</span>° &nbsp;·&nbsp; <span id="ly">0.0</span>°</p>
        <p class="kicker" id="status">Waiting for sensor…</p>
        <button class="btn" type="button" id="enable" hidden>Enable motion sensors</button>
        <div class="notice" id="nosensor" hidden><?= icon('device') ?><div>No motion sensor found. Open this page on your phone or tablet.</div></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var got = false, bubble = document.getElementById('bubble');
    function onMotion(e) {
        var g = e.accelerationIncludingGravity; if (!g || (g.x === null && g.y === null)) return;
        got = true; document.getElementById('nosensor').hidden = true; document.getElementById('enable').hidden = true;
        var ax = Math.atan2(g.x, Math.hypot(g.y, g.z)) * 180 / Math.PI, ay = Math.atan2(g.y, Math.hypot(g.x, g.z)) * 180 / Math.PI;
        var clamp = function (v) { return Math.max(-120, Math.min(120, v)); };
        bubble.style.transform = 'translate(' + clamp(-ax * 6) + 'px,' + clamp(ay * 6) + 'px)';
        document.getElementById('lx').textContent = Math.abs(ax).toFixed(1);
        document.getElementById('ly').textContent = Math.abs(ay).toFixed(1);
        document.getElementById('status').textContent = Math.abs(ax) < 1 && Math.abs(ay) < 1 ? 'Level' : 'Not level';
    }
    function listen() { window.addEventListener('devicemotion', onMotion); setTimeout(function () { if (!got) { document.getElementById('nosensor').hidden = false; document.getElementById('status').textContent = 'No sensor data'; } }, 2500); }
    if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission === 'function') {
        var b = document.getElementById('enable'); b.hidden = false;
        b.addEventListener('click', function () { DeviceMotionEvent.requestPermission().then(function (r) { if (r === 'granted') listen(); }).catch(function () {}); });
    } else listen();
});
</script>
