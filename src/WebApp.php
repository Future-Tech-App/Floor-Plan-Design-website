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
                ['view3d', '3D View', 'Walk through any saved plan in 3D', 'cube', '/app/plans'],
                ['templates', 'Templates', 'Ready-made 1, 2 & 3 bedroom layouts', 'home', '/app/templates'],
                ['plans', 'Saved Plans', 'Open, rename, duplicate or download plans', 'layers', '/app/plans'],
                ['notes', 'Notes', 'Project notes and annotations', 'note', '/app/notes'],
                ['export', 'Export', 'Download your plans as PDF or image', 'download', '/app/plans'],
            ],
            'measure' => [
                ['construction', 'Construction Calculator', 'Bricks, concrete, paint, tiles & costs', 'calculator', '/app/calculators'],
                ['area', 'Area Calculator', 'Areas and perimeters of common room shapes', 'ruler', '/app/calculators/area'],
                ['converters', 'Unit Converters', 'Length, area, volume, weight and more', 'globe', '/app/calculators#converter'],
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
        if ($sub === '/3d') {
            render('app/viewer3d', $base + ['title' => '3D View — Floor Plan Designer', 'nav' => 'editor'], 'app_3d');
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
        if ($sub === '/ai' || $sub === '/ai/analyzer') {
            self::page($sub === '/ai' ? 'app/ai_generator' : 'app/ai_analyzer', $sub === '/ai' ? 'AI Floor Plans' : 'AI Analyzer', 'ai', $base);
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

    /**
     * Interior photos (public/assets/img/interior) from Unsplash, free under the Unsplash License.
     * file => [photographer, Unsplash username, photo id, description]
     */
    public static function photos(): array
    {
        return [
            'hero-living.jpg'    => ['Spacejoy', 'spacejoy', '9M66C_w_ToM', 'Living room with a grey sofa and leather ottomans'],
            'hero-open-plan.jpg' => ['Clay Banks', 'claybanks', 'Hnec2oEbbxk', 'Open-plan living room and kitchen with wood accents'],
            'hero-dining.jpg'    => ['Caroline Badran', '___atmos', 'UW1WJJ0bkX0', 'Dining room with a large window'],
            'banner-lounge.jpg'  => ['Kam Idris', 'ka_idris', 'U39FPHKfDu0', 'Minimal lounge with a sofa and table'],
            'living-light.jpg'   => ['Minh Pham', 'minhphamdesign', 'OtXADkUh3-I', 'Bright living room with a large window'],
            'kitchen-wood.jpg'   => ['Kam Idris', 'ka_idris', 'hYb7kbu4x7E', 'Kitchen with wood cabinets and teal stools'],
            'dining-green.jpg'   => ['Spacejoy', 'spacejoy', 'YI2YkyaREHk', 'Wooden dining table with chairs'],
            'sofa-green.jpg'     => ['Jason Wang', 'coohom', '8J49mtYWu7E', 'Lounge with a green sofa'],
            'tools/draw.jpg'         => ['Daniel McCullough', 'd_mccullough', 'HtBlQdxfG9k', 'Architect drafting with a pencil and ruler'],
            'tools/analyzer.jpg'     => ['Jonathan Borba', 'jonathanborba', 'rn00OVh0gEI', 'Blueprints and a laptop on a marble desk'],
            'tools/plans.jpg'        => ['Marina Zvada', 'zvada_photo', 'fNxmdlYHRm8', 'Architectural blueprints spread out'],
            'tools/export.jpg'       => ['Pedro Miranda', 'mirandanef', '3QzMBrvCeyQ', 'People reviewing blueprints on a desk'],
            'tools/notes.jpg'        => ['Clay Banks', 'claybanks', 'n9AaeihA9HI', 'Open notebook with pens on a desk'],
            'tools/construction.jpg' => ['Matt W Newman', 'cannedstreet', 'TYiD9PaAHos', 'Stack of orange bricks'],
            'tools/area.jpg'         => ['William Warby', 'wwarby', 'M779tNeoSKc', 'Tape measure'],
            'tools/converters.jpg'   => ['William Warby', 'wwarby', 'WahfNoqbYnM', 'Rulers and measuring tapes'],
            'template-1bed.jpg'  => ['Brian Zajac', 'brianzajac', '15MYwJE3mlA', 'Bright bedroom with a sitting area'],
            'template-2bed.jpg'  => ['curaits', 'curaits', 'p_xTs-dajHk', 'Sunlit living room with a glass partition'],
            'template-3bed.jpg'  => ['Brian Zajac', 'brianzajac', 'g6LLnC-rn0E', 'Single-storey house at dusk'],
            'banner-plan.jpg'    => ['Brian Zajac', 'brianzajac', '9lB0cUTt9IQ', 'Floor plan of a three-bedroom house'],
        ];
    }

    /** Template images shipped with the app (public/assets/app/templates). */
    public static function templates(): array
    {
        $dir = ROOT_DIR . '/public/assets/app/templates';
        $names = ['1bed' => '1 Bed Apartment', '2bed' => '2 Bed Apartment', '3bed' => '3 Bed Apartment'];
        $out = [];
        foreach (glob($dir . '/*') ?: [] as $file) { // GLOB_BRACE is missing on some hosts
            if (!preg_match('/^(\dbed)-(\d+)\.(jpe?g|png|webp)$/', basename($file), $m)) {
                continue;
            }
            $out[] = ['cat' => $m[1], 'num' => (int) $m[2], 'file' => basename($file), 'name' => $names[$m[1]] . ' - Plan ' . $m[2]];
        }
        usort($out, static fn ($a, $b) => [$a['cat'], $a['num']] <=> [$b['cat'], $b['num']]);
        return $out;
    }
}
