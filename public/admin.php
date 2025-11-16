<?php
session_start();
require __DIR__ . '/../app/config.php';

$isLoggedIn = isset($_SESSION['user_id']);

$page = $_GET['page'] ?? ($isLoggedIn ? 'dashboard' : 'login');

ob_start();
$hasAdminUser = Database::getInstance()->hasAdminUser();
if (!$hasAdminUser) {
    include __DIR__ . '/../app/routes/register.php';
} else {
    switch ($page) {
        case 'login' || !$isLoggedIn:
            include __DIR__ . '/../app/routes/login.php';
            break;
        case 'editor':include __DIR__ . '/../app/editor.php';
            break;
        case 'dashboard':
            include __DIR__ . '/../app/dashboard.php';
            break;
        default:
            http_response_code(404);
            $title = "404 Not Found";
            echo "<h1>Page not found</h1>";
    }
}
$body = ob_get_clean();
if (isset($useFullTemplate) && $useFullTemplate){
    require __DIR__ . '/../app/template-full.php';
} else {
    require __DIR__ . '/../app/template.php';
}