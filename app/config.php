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

if (!defined('CMS_DB_HOST')) define('CMS_DB_HOST', 'localhost');
if (!defined('CMS_DB_PORT')) define('CMS_DB_PORT', '3306');
if (!defined('CMS_DB_USER')) define('CMS_DB_USER', 'root');
if (!defined('CMS_DB_PASS')) define('CMS_DB_PASS', '');
if (!defined('CMS_DB_NAME')) define('CMS_DB_NAME', 'cms_database');
if (!defined('CMS_UPLOAD_DIR')) define('CMS_UPLOAD_DIR', __DIR__ . '/../uploads');

const CMS_LOCALES = ['en', 'nl'];
const CMS_MAX_UPLOAD_SIZE = 1024 * 1020; // Default 1MB with a small buffer
const CMS_ASSETS_TMP_DIR = 'temp';

require __DIR__ . '/database.php';
require __DIR__ . '/icons.php';
require __DIR__ . '/fields/FieldRegistry.php';
