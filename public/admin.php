<?php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require __DIR__ . '/../app/config.php';

$isLoggedIn = isset($_SESSION['user_id']);

$page = $_GET['page'] ?? 'dashboard';
$routes = [
    "dashboard",
    "content-type",
    "content-type-create",
    "content-type-edit",
    "content-entries",
    "content-entry-edit",
    "collections-create",
    "collections-edit",
    "assets",
    "asset-upload",
    "assets-json",
    "users",
];

ob_start();

if (!Database::getInstance()->hasSchema()) {
    include __DIR__ . '/../app/routes/noschema.php';
} elseif (!Database::getInstance()->hasAdminUser()) {
    include __DIR__ . '/../app/routes/register.php';
} elseif ($page == 'logout') {
    session_destroy();
    header('Location: admin.php', true, 303);
    exit;
} elseif (!$isLoggedIn) {
    include __DIR__ . '/../app/routes/login.php';
} elseif (in_array($page, $routes)) {
    include __DIR__ . "/../app/routes/{$page}.php";
} else {
    http_response_code(404);
    $title = "404 Not Found";
    echo "<h1>Page not found</h1>";
}
$body = ob_get_clean();
if (isset($useFullTemplate) && $useFullTemplate){
    require __DIR__ . '/../app/template-full.php';
} else {
    require __DIR__ . '/../app/template.php';
}