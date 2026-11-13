<?php
declare(strict_types=1);

/**
 * Server-side version gate.
 *
 * Mirrors the rules of the Flutter app's VersionGateService so the same policy can
 * be managed from the admin panel and served over the API:
 *
 *   force_update_enabled            "1" / "0"   master switch
 *   force_update_min_version        "x.y.z"     below this -> update_required
 *   force_update_blocked_versions   "a,b,c"     exact versions -> blocked
 *   optional_update_version         "x.y.z"     below this (but >= min) -> optional_update
 *   latest_version                  "x.y.z"     shown on the site / download page
 *
 * Fails OPEN: an unparseable installed version is always treated as "ok".
 */
final class VersionGate
{
    public const OK = 'ok';
    public const OPTIONAL_UPDATE = 'optional_update';
    public const UPDATE_REQUIRED = 'update_required';
    public const BLOCKED = 'blocked';

    /** Parse "3.0.2+32", "3.0.2" or "3.0" into [major, minor, patch]. */
    public static function parse(string $version): ?array
    {
        $core = trim(explode('+', trim($version), 2)[0]);
        if ($core === '' || !preg_match('/^\d+(\.\d+){0,2}$/', $core)) {
            return null;
        }
        $parts = array_map('intval', explode('.', $core));
        while (count($parts) < 3) {
            $parts[] = 0;
        }
        return $parts;
    }

    public static function compare(string $a, string $b): ?int
    {
        $pa = self::parse($a);
        $pb = self::parse($b);
        if ($pa === null || $pb === null) {
            return null;
        }
        return $pa <=> $pb;
    }

    /**
     * @param array<string, string> $settings values from app_settings
     * @return array{status: string, installed_version: string, minimum_version: string,
     *               optional_version: string, latest_version: string, message: string}
     */
    public static function evaluate(string $installed, array $settings): array
    {
        $enabled  = ($settings['force_update_enabled'] ?? '1') === '1';
        $minimum  = $settings['force_update_min_version'] ?? '0.0.0';
        $optional = $settings['optional_update_version'] ?? '0.0.0';
        $latest   = $settings['latest_version'] ?? (string) config('app_version');
        $blocked  = array_filter(array_map('trim', explode(',', $settings['force_update_blocked_versions'] ?? '')));

        $result = [
            'status'            => self::OK,
            'installed_version' => $installed,
            'minimum_version'   => $minimum,
            'optional_version'  => $optional,
            'latest_version'    => $latest,
            'message'           => '',
        ];

        if (!$enabled) {
            return $result;
        }

        // Blocklist matches the exact string the app reports (e.g. "3.0.1+31" or "3.0.1").
        $core = trim(explode('+', $installed, 2)[0]);
        if (in_array($installed, $blocked, true) || in_array($core, $blocked, true)) {
            $result['status'] = self::BLOCKED;
            $result['message'] = 'This version is no longer supported. Please update to continue.';
            return $result;
        }

        $belowMin = self::compare($installed, $minimum);
        if ($belowMin !== null && $belowMin < 0) {
            $result['status'] = self::UPDATE_REQUIRED;
            $result['message'] = 'A required update is available. Please update to continue.';
            return $result;
        }

        $belowOptional = self::compare($installed, $optional);
        if ($belowOptional !== null && $belowOptional < 0) {
            $result['status'] = self::OPTIONAL_UPDATE;
            $result['message'] = 'A new version is available with improvements.';
        }

        return $result;
    }
}
