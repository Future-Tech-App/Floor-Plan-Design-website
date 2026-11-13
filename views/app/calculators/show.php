<?php /** One calculator: form (GET) + server-side results. @var array $calc  @var array $result */
$headLead = $calc['intro'] ?? null;
require ROOT_DIR . '/views/app/_head.php';
$values = $result['values'] ?? [];
$errors = $result['errors'] ?? [];
$submitted = !empty($result['submitted']);
$self = url('/app/calculators/' . $calc['id']);
$live = ($calc['tab'] ?? '') === 'converter';
$unitLabel = static function (array $in) use ($calc, $values): string {
    if (!empty($in['unit_from'])) {
        foreach ($calc['inputs'] as $o) {
            if ($o['name'] === $in['unit_from']) {
                $sel = (string) ($values[$o['name']] ?? $o['default']);
                return (string) ($o['options'][$sel] ?? $sel);
            }
        }
    }
    return (string) ($in['unit'] ?? '');
};
$fmtVal = static fn ($v): string => is_float($v) || is_int($v) ? rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') : (string) $v;
?>
<div class="page-body">
    <div class="a-container">
        <p style="margin:0 0 20px"><a href="<?= e(url('/app/calculators')) ?>#<?= e((string) ($calc['tab'] ?? '')) ?>">← All calculators</a></p>
        <div class="calc-layout">
            <form class="panel" method="get" action="<?= e($self) ?>" id="calc-form" novalidate<?= $live ? ' data-live' : '' ?>>
                <h2><?= e($calc['title']) ?></h2>
                <input type="hidden" name="calc" value="1">
                <div class="form-grid">
                    <?php foreach ($calc['inputs'] as $in):
                        $name = $in['name'];
                        $val = $values[$name] ?? ($in['default'] ?? '');
                        $wide = in_array($in['type'], ['radio'], true);
                        $showIf = $in['show_if'] ?? null;
                        $attrs = $showIf ? ' data-show-if="' . e(json_encode($showIf)) . '"' : '';
                        if (!Calculators::isVisible($in, $values + array_column($calc['inputs'], 'default', 'name'))) {
                            $attrs .= ' hidden';
                        }
                        $unit = $unitLabel($in);
                    ?>
                        <div class="field"<?= $attrs ?><?= $wide ? ' style="grid-column:1/-1"' : '' ?>>
                            <?php if ($in['type'] === 'radio'): ?>
                                <span class="flabel"><?= e($in['label']) ?></span>
                                <div class="seg" role="radiogroup" aria-label="<?= e($in['label']) ?>">
                                    <?php foreach ($in['options'] as $optVal => $optLabel): ?>
                                        <label><input type="radio" name="<?= e($name) ?>" value="<?= e((string) $optVal) ?>"<?= (string) $val === (string) $optVal ? ' checked' : '' ?>><span><?= e($optLabel) ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            <?php elseif ($in['type'] === 'select'): ?>
                                <label for="f-<?= e($name) ?>"><?= e($in['label']) ?></label>
                                <select id="f-<?= e($name) ?>" name="<?= e($name) ?>">
                                    <?php foreach ($in['options'] as $optVal => $optLabel): ?>
                                        <option value="<?= e((string) $optVal) ?>"<?= (string) $val === (string) $optVal ? ' selected' : '' ?>><?= e($optLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <label for="f-<?= e($name) ?>"><?= e($in['label']) ?><span<?= !empty($in['unit_from']) ? ' data-unit-from="' . e($in['unit_from']) . '"' : '' ?>><?= $unit !== '' ? ' (' . e($unit) . ')' : '' ?></span></label>
                                <input id="f-<?= e($name) ?>" type="number" inputmode="decimal" name="<?= e($name) ?>"
                                       value="<?= e($fmtVal($val)) ?>"
                                       step="<?= e((string) ($in['step'] ?? 'any')) ?>"<?= isset($in['min']) ? ' min="' . e((string) $in['min']) . '"' : '' ?>
                                       <?= isset($errors[$name]) ? 'aria-invalid="true" aria-describedby="err-' . e($name) . '"' : '' ?>>
                            <?php endif; ?>
                            <?php if (!empty($in['hint'])): ?><p class="hint"><?= e($in['hint']) ?></p><?php endif; ?>
                            <?php if (isset($errors[$name])): ?><p class="field-error" id="err-<?= e($name) ?>"><?= e($errors[$name]) ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!empty($calc['currency'])): ?>
                        <div class="field">
                            <label for="f-currency">Currency</label>
                            <select id="f-currency" name="currency">
                                <?php foreach (Calculators::currencies() as $code => $label): ?>
                                    <option value="<?= e($code) ?>"<?= ($values['currency'] ?? 'USD') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn"><?= icon('calculator') ?> Calculate</button>
                    <a class="btn btn-outline" href="<?= e($self) ?>">Reset</a>
                </div>
            </form>

            <section class="results" aria-live="polite" id="results">
                <h2>Results</h2>
                <?php if (!$submitted): ?>
                    <p>Fill in the form and press <strong style="color:#fff">Calculate</strong> to see the estimate.</p>
                <?php elseif ($errors): ?>
                    <p>Please fix the highlighted fields.</p>
                <?php elseif (empty($result['rows'])): ?>
                    <p>No result for these values.</p>
                <?php else: ?>
                    <dl class="result-rows">
                        <?php foreach ($result['rows'] as $row): ?>
                            <div><dt><?= e($row['label']) ?></dt><dd><?= e((string) $row['value']) ?><?php if (!empty($row['unit'])): ?><small><?= e($row['unit']) ?></small><?php endif; ?></dd></div>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if ($submitted && !$errors && !empty($result['note'])): ?><p class="result-note"><?= e($result['note']) ?></p><?php endif; ?>
                <?php if ($submitted && !$errors && !empty($result['rows'])): ?>
                    <div class="form-actions"><button type="button" class="btn btn-ghost-light" onclick="window.print()"><?= icon('download') ?> Print / Save PDF</button></div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
<script>
// Show or hide fields that depend on another choice (works without JS too: all fields are then shown).
(function () {
    var form = document.getElementById('calc-form');
    function current(name) {
        var el = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('select[name="' + name + '"]');
        return el ? el.value : null;
    }
    function update() {
        form.querySelectorAll('[data-show-if]').forEach(function (f) {
            var rule = JSON.parse(f.getAttribute('data-show-if')), ok = true;
            Object.keys(rule).forEach(function (k) {
                var want = rule[k], have = current(k);
                if (Array.isArray(want) ? want.map(String).indexOf(have) === -1 : String(want) !== have) ok = false;
            });
            f.hidden = !ok;
            f.querySelectorAll('input,select').forEach(function (i) { i.disabled = !ok; });
        });
        form.querySelectorAll('[data-unit-from]').forEach(function (u) {
            var src = form.querySelector('[name="' + u.getAttribute('data-unit-from') + '"]:checked') || form.querySelector('select[name="' + u.getAttribute('data-unit-from') + '"]');
            if (!src) return;
            var text = src.tagName === 'SELECT' ? src.options[src.selectedIndex].text : src.nextElementSibling.textContent;
            u.textContent = ' (' + text + ')';
        });
    }
    form.addEventListener('change', function () {
        update();
        // Converters update straight away, like the app.
        if (form.hasAttribute('data-live')) form.submit();
    });
    update();
    <?php if ($submitted): ?>if (window.matchMedia('(max-width: 900px)').matches) document.getElementById('results').scrollIntoView({ block: 'start' });<?php endif; ?>
})();
</script>
