<?php
$title = 'Settings';
$types = Database::getInstance()->getContentTypes();

?>
<h1>Settings</h1>

<h2>Profile</h2>
Your username: <?php echo htmlspecialchars($_SESSION['user_username']); ?><br>
Your role: <?php echo htmlspecialchars($_SESSION['user_role']); ?><br>
<a href="admin.php?page=logout">Logout</a>

<h2>Users</h2>
User management.

<h2>Locales</h2>
Add/remove locales.

