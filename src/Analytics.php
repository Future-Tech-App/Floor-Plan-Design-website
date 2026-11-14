<?php
declare(strict_types=1);

/**
 * Privacy-friendly page view counting for Admin → Analytics.
 * Stores only a per-day count for each path: no IP, cookie or visitor id.
 */
final class Analytics
{
    /** Count one view of a public HTML page (skips bots, admin, API and non-GET requests). */
    public static function hit(string $path): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || PHP_SAPI === 'cli') {
            return;
        }
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless/', $ua)) {
            return;
        }
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/api')) {
            return;
        }
        // Group detail pages so the table stays small.
        $path = preg_replace('#^/app/calculators/.+$#', '/app/calculators/*', $path) ?? $path;
        $path = substr($path, 0, 120);
        try {
            Database::pdo()->prepare(
                "INSERT INTO page_views (day, path, views) VALUES (date('now'), :path, 1)
                 ON CONFLICT(day, path) DO UPDATE SET views = views + 1"
            )->execute(['path' => $path]);
        } catch (Throwable $e) {
            // Never break a page because of statistics.
            error_log('Analytics: ' . $e->getMessage());
        }
    }
}
