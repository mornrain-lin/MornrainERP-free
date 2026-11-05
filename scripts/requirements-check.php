<?php

/**
 * 晨雨ERP - runtime requirement check.
 *
 * Called by setup.bat (Windows) and setup.sh (Linux/macOS) before
 * anything else runs. Keeping this logic in PHP instead of inline
 * `php -r "..."` avoids cmd.exe quote-escaping hell and gives us a
 * single implementation shared by both platforms.
 *
 * Exit code 0 = environment is fine, 1 = cannot continue.
 * All output is ASCII-only so it survives any OEM codepage.
 */

$minVersion = '8.2.0';

if (version_compare(PHP_VERSION, $minVersion, '<')) {
    fwrite(STDERR, "PHP >= {$minVersion} required, found " . PHP_VERSION . PHP_EOL);
    exit(1);
}

$required = ['pdo_sqlite', 'mbstring', 'openssl', 'fileinfo', 'curl', 'zip', 'intl'];
$missing = array_values(array_filter(
    $required,
    static fn (string $ext): bool => ! extension_loaded($ext)
));

if ($missing !== []) {
    fwrite(STDERR, 'Missing PHP extension(s): ' . implode(', ', $missing) . PHP_EOL);
    exit(1);
}

echo 'PHP ' . PHP_VERSION . ' ok' . PHP_EOL;

// Advisory only: Windows needs an explicit upload_tmp_dir, otherwise
// file uploads fail with "unable to create a temporary file".
$uploadTmp = ini_get('upload_tmp_dir');
if ($uploadTmp === '' || $uploadTmp === false) {
    echo 'note: upload_tmp_dir is empty (set it in php.ini if you use CSV import)' . PHP_EOL;
}

exit(0);
