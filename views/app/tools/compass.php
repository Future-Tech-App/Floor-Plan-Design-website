<?php $headLead = 'Shows magnetic heading on phones and tablets that provide compass data.'; require ROOT_DIR . '/views/app/_head.php'; ?>
<div class="page-body">
    <div class="a-container" style="max-width:640px;text-align:center">
        <div style="position:relative;width:300px;height:300px;margin:0 auto">
            <svg id="rose" viewBox="0 0 200 200" width="300" height="300" style="transition:transform .15s linear">
                <circle cx="100" cy="100" r="96" fill="#001d36"/><circle cx="100" cy="100" r="80" fill="none" stroke="#64b5f6" stroke-width="1"/>
                <g fill="#fff" font-family="Tinos, serif" font-size="18" text-anchor="middle"><text x="100" y="32">N</text><text x="176" y="106">E</text><text x="100" y="182">S</text><text x="24" y="106">W</text></g>
                <path d="M100 40 L110 100 L100 92 L90 100Z" fill="#ef5350"/><path d="M100 160 L110 100 L100 108 L90 100Z" fill="#fff"/>
            </svg>
        </div>
        <p style="font:400 2.4rem var(--serif);color:var(--navy);margin:16px 0 0"><span id="deg">0</span>° <span id="dir">N</span></p>
        <button class="btn" type="button" id="enable" hidden>Enable compass</button>
        <div class="notice" id="nosensor" hidden><?= icon('compass') ?><div>This browser does not provide compass data. Use a phone or the Floor Plan Designer mobile app.</div></div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var got = false, dirs = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];
    function onOrient(e) {
        var h = typeof e.webkitCompassHeading === 'number' ? e.webkitCompassHeading : (e.absolute && e.alpha !== null ? 360 - e.alpha : null);
        if (h === null || isNaN(h)) return;
        got = true; document.getElementById('nosensor').hidden = true; document.getElementById('enable').hidden = true;
        h = (h + 360) % 360;
        document.getElementById('rose').style.transform = 'rotate(' + (-h) + 'deg)';
        document.getElementById('deg').textContent = Math.round(h);
        document.getElementById('dir').textContent = dirs[Math.round(h / 45) % 8];
    }
    function listen() {
        window.addEventListener('deviceorientationabsolute', onOrient);
        window.addEventListener('deviceorientation', onOrient);
        setTimeout(function () { if (!got) document.getElementById('nosensor').hidden = false; }, 2500);
    }
    if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
        var b = document.getElementById('enable'); b.hidden = false;
        b.addEventListener('click', function () { DeviceOrientationEvent.requestPermission().then(function (r) { if (r === 'granted') listen(); }).catch(function () {}); });
    } else listen();
});
</script>
