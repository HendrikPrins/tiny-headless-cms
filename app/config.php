<?php
function loadConfig()
{
    $keys = ['CMS_DB_HOST', 'CMS_DB_USER', 'CMS_DB_PASS', 'CMS_DB_NAME', 'CMS_UPLOAD_DIR'];
    if (file_exists(__DIR__ . '/../.env')) {
        $contents = file_get_contents(__DIR__ . '/../.env');
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                if (in_array($key, $keys) && !defined($key)) {
                    define($key, $value);
                }
            }
        }
    }
    foreach ($keys as $key) {
        if (isset($_ENV[$key]) && !defined($key)) {
            define($key, $_ENV[$key]);
        }
    }

    // Validate required DB variables and throw if any are missing or empty
    $required = ['CMS_DB_HOST', 'CMS_DB_USER', 'CMS_DB_PASS', 'CMS_DB_NAME'];
    $missing = [];
    foreach ($required as $req) {
        if (!defined($req) || trim(constant($req)) === '') {
            $missing[] = $req;
        }
    }
    if (!empty($missing)) {
        throw new RuntimeException('Missing required config variables: ' . implode(', ', $missing));
    }
}

loadConfig();

require __DIR__ . '/database.php';
