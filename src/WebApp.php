<?php
declare(strict_types=1);

/**
 * The Floor Plan Designer web app (/app/…), written in PHP + HTML/CSS/JS.
 *
 * PHP renders every page and does the server-side work (calculators, AI proxy,
 * contact). JavaScript is used only for what has to run in the browser: the
 * drawing editor, saved plans (stored in the browser, no accounts) and the
 * device-sensor tools.
 */
final class WebApp
{
    /** Tools shown on the dashboard, in the same order and groups as the app. */
    public static function tools(): array
    {
        return [
            'design' => [
                ['draw', 'Draw', 'Walls, rooms, doors, windows & furniture', 'pencil', '/app/editor'],
                ['ai', 'AI Floor Plans', 'Generate a layout from a short description', 'sparkles', '/app/ai'],
                ['analyzer', 'AI Analyzer', 'Detect rooms in a photo of a floor plan', 'image', '/app/ai/analyzer'],
                ['templates', 'Templates', 'Ready-made 1, 2 & 3 bedroom layouts', 'home', '/app/templates'],
                ['plans', 'Saved Plans', 'Open, rename, duplicate or download plans', 'layers', '/app/plans'],
                ['notes', 'Notes', 'Project notes and annotations', 'note', '/app/notes'],
                ['export', 'Export', 'Download your plans as PDF or image', 'download', '/app/plans'],
            ],
            'measure' => [
                ['construction', 'Construction Calculator', 'Bricks, concrete, paint, tiles & costs', 'calculator', '/app/calculators'],
                ['area', 'Area Calculator', 'Areas and volumes of common shapes', 'ruler', '/app/calculators/area'],
                ['measure', 'Measure', 'On-screen ruler in cm, inches & more', 'ruler', '/app/tools/measure'],
                ['level', 'Bubble Level', 'Check surfaces with your phone’s sensors', 'level', '/app/tools/level'],
                ['compass', 'Compass', 'Magnetic heading (phones with a compass)', 'compass', '/app/tools/compass'],
            ],
        ];
    }

    /** Route /app/… requests. $sub is the path after "/app" ("/" for the dashboard). */
    public static function dispatch(string $sub): void
    {
        $sub = '/' . trim($sub, '/');
        $base = ['site_section' => 'app'];

        if ($sub === '/') {
            self::page('app/dashboard', 'Floor Plan Designer', 'dashboard', $base + ['tools' => self::tools()]);
            return;
        }
        if ($sub === '/editor') {
            render('app/editor', $base + ['title' => 'Editor — Floor Plan Designer', 'nav' => 'editor'], 'app_editor');
            return;
        }
        if ($sub === '/templates') {
            self::page('app/templates', 'Templates', 'templates', $base + ['templates' => self::templates()]);
            return;
        }
        if ($sub === '/plans') {
            self::page('app/plans', 'Saved Plans', 'plans', $base);
            return;
        }
        if ($sub === '/notes') {
            self::page('app/notes', 'Notes', 'notes', $base);
            return;
        }
        if ($sub === '/about') {
            self::page('app/about', 'About', 'about', $base);
            return;
        }
        if ($sub === '/settings') {
            self::page('app/settings', 'Settings', 'settings', $base);
            return;
        }
        if ($sub === '/ai' || $sub === '/ai/analyzer') {
            self::page('app/ai', $sub === '/ai' ? 'AI Floor Plans' : 'AI Analyzer', 'ai', $base + ['mode' => $sub === '/ai' ? 'generator' : 'analyzer']);
            return;
        }
        if (preg_match('#^/tools/(measure|level|compass)$#', $sub, $m)) {
            $titles = ['measure' => 'Measure', 'level' => 'Bubble Level', 'compass' => 'Compass'];
            self::page('app/tools/' . $m[1], $titles[$m[1]], 'tools', $base);
            return;
        }
        if ($sub === '/calculators') {
            self::page('app/calculators/index', 'Construction Calculator', 'calculators', $base + ['hub' => Calculators::hub()]);
            return;
        }
        if (preg_match('#^/calculators/([a-z0-9_]+)$#', $sub, $m)) {
            $calc = Calculators::find($m[1]);
            if ($calc === null) {
                abort(404, 'That calculator does not exist.');
            }
            $result = Calculators::compute($calc, $_GET);
            self::page('app/calculators/show', $calc['title'], 'calculators', $base + ['calc' => $calc, 'result' => $result]);
            return;
        }
        abort(404, 'The page you are looking for does not exist.');
    }

    private static function page(string $view, string $title, string $nav, array $data): void
    {
        render($view, $data + [
            'title'       => $title === 'Floor Plan Designer' ? 'Floor Plan Designer — Web App' : $title . ' — Floor Plan Designer',
            'pageTitle'   => $title,
            'nav'         => $nav,
            'description' => 'Floor Plan Designer web app: draw floor plans, calculate materials and export professional plans in your browser.',
        ], 'app');
    }

    /** Template images shipped with the app (public/assets/app/templates). */
    public static function templates(): array
    {
        $dir = ROOT_DIR . '/public/assets/app/templates';
        $names = ['1bed' => '1 Bed Apartment', '2bed' => '2 Bed Apartment', '3bed' => '3 Bed Apartment'];
        $out = [];
        foreach (glob($dir . '/*.jpg') ?: [] as $file) {
            if (!preg_match('/^(\dbed)-(\d+)\.jpg$/', basename($file), $m)) {
                continue;
            }
            $out[] = ['cat' => $m[1], 'num' => (int) $m[2], 'file' => basename($file), 'name' => $names[$m[1]] . ' - Plan ' . $m[2]];
        }
        usort($out, static fn ($a, $b) => [$a['cat'], $a['num']] <=> [$b['cat'], $b['num']]);
        return $out;
    }
}
