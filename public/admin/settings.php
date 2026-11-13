<?php
declare(strict_types=1);

/**
 * Admin: version gate, store links and public version shown on the site.
 * These values are served by /api/v1/version.php.
 */
require dirname(__DIR__, 2) . '/src/bootstrap.php';
Auth::require();

function valid_version(string $v): bool
{
    return $v === '' || (bool) preg_match('/^\d+(\.\d+){0,2}$/', $v);
}

$errors = [];

if (is_post()) {
    csrf_verify();

    $values = [
        'latest_version'                => input('latest_version'),
        'force_update_enabled'          => isset($_POST['force_update_enabled']) ? '1' : '0',
        'force_update_min_version'      => input('force_update_min_version'),
        'optional_update_version'       => input('optional_update_version'),
        'force_update_blocked_versions' => implode(',', array_filter(array_map(
            'trim',
            explode(',', input('force_update_blocked_versions'))
        ))),
    ];

    foreach (['latest_version', 'force_update_min_version', 'optional_update_version'] as $key) {
        if (!valid_version($values[$key])) {
            $errors[$key] = 'Use a version like 3.0.2 (numbers and dots only).';
        }
    }
    foreach (explode(',', $values['force_update_blocked_versions']) as $v) {
        if ($v !== '' && !preg_match('/^\d+(\.\d+){0,2}(\+\d+)?$/', $v)) {
            $errors['force_update_blocked_versions'] = 'Blocked versions must be comma-separated, e.g. 3.0.0,3.0.1+31';
        }
    }

    if ($errors === []) {
        foreach ($values as $key => $value) {
            Repository::setSetting($key, $value);
        }
        flash('success', 'Settings saved. The version API reflects them immediately.');
        redirect('/admin/settings.php');
    }
    flash('error', 'Settings were not saved. Please fix the highlighted fields.');
}

$stored = Repository::allSettings();
$current = [
    'latest_version'                => $stored['latest_version'] ?? (string) config('app_version'),
    'force_update_enabled'          => $stored['force_update_enabled'] ?? '1',
    'force_update_min_version'      => $stored['force_update_min_version'] ?? '0.0.0',
    'optional_update_version'       => $stored['optional_update_version'] ?? '0.0.0',
    'force_update_blocked_versions' => $stored['force_update_blocked_versions'] ?? '',
];
if (is_post()) {
    // Re-show what the admin typed when validation failed
    foreach (array_keys($current) as $key) {
        if ($key === 'force_update_enabled') {
            $current[$key] = isset($_POST[$key]) ? '1' : '0';
        } elseif (isset($_POST[$key])) {
            $current[$key] = input($key);
        }
    }
}

render('admin/settings', [
    'title'   => 'Version & settings — Admin',
    'values'  => $current,
    'errors'  => $errors,
], 'admin');
