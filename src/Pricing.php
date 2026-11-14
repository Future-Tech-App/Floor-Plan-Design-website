<?php
declare(strict_types=1);

/**
 * Premium plans shown on /premium. Prices and badges are set in Admin → Pricing
 * (stored in app_settings); purchases happen in the Android/iOS apps.
 */
final class Pricing
{
    public const PLANS = [
        'weekly'  => ['Weekly', 'week', 7],
        'monthly' => ['Monthly', 'month', 30],
        'yearly'  => ['Yearly', 'year', 365],
    ];
    public const BADGES = ['' => 'None', 'popular' => 'Most popular', 'best' => 'Best value'];

    /** Free vs Premium comparison rows: [feature, free, premium]. true = included. */
    public const FEATURES = [
        ['Floor plan editor, furniture library & 3D view', true, true],
        ['Construction, area & unit calculators', true, true],
        ['Plan templates', true, true],
        ['Saved plans', 'Limited', 'Unlimited'],
        ['Export', 'Basic', 'PNG, PDF, SVG & more'],
        ['AI floor plan generator & analyzer', 'Limited', 'Full access'],
        ['Ads', 'With ads', 'No ads'],
        ['Platforms', 'Android & iOS', 'Android, iOS & macOS'],
    ];

    /** Admin values with defaults. */
    public static function settings(): array
    {
        $s = Repository::allSettings();
        $out = ['currency' => $s['price_currency'] ?? '$'];
        foreach (array_keys(self::PLANS) as $k) {
            $out[$k] = [
                'on'    => ($s['price_' . $k . '_on'] ?? '1') === '1',
                'price' => $s['price_' . $k] ?? '',
                'badge' => $s['price_' . $k . '_badge'] ?? ($k === 'yearly' ? 'best' : ($k === 'monthly' ? 'popular' : '')),
            ];
        }
        return $out;
    }

    /** Plans for the public page, with per-week cost and savings when prices are set. */
    public static function plans(): array
    {
        $s = self::settings();
        $weekly = is_numeric($s['weekly']['price']) ? (float) $s['weekly']['price'] : null;
        $plans = [];
        foreach (self::PLANS as $k => [$label, $per, $days]) {
            if (!$s[$k]['on']) {
                continue;
            }
            $price = is_numeric($s[$k]['price']) ? (float) $s[$k]['price'] : null;
            $perWeek = $price !== null ? $price / ($days / 7) : null;
            $save = ($price !== null && $weekly && $k !== 'weekly') ? (int) round((1 - $perWeek / $weekly) * 100) : null;
            $plans[] = [
                'key' => $k, 'label' => $label, 'per' => $per, 'price' => $price,
                'priceText' => $price !== null ? $s['currency'] . number_format($price, 2) : null,
                'perWeekText' => ($perWeek !== null && $k !== 'weekly') ? $s['currency'] . number_format($perWeek, 2) . ' / week' : null,
                'save' => $save !== null && $save > 0 ? $save : null,
                'badge' => self::BADGES[$s[$k]['badge']] ?? '', 'badgeKey' => $s[$k]['badge'],
            ];
        }
        return $plans;
    }
}
