<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(!empty($title) ? ($title . ' - Tiny Headless CMS') : 'Tiny Headless CMS'); ?></title>
    <link rel="stylesheet" href="/assets/style.css">
    <script defer src="/assets/app.js"></script>
</head>
<body>
<header>
    <div class="content">
        <span class="project-title">Tiny Headless CMS</span>
        <nav>
            <a href="admin.php?page=content-type">Content Types</a>
            <a href="admin.php?page=assets">Assets</a>
            <a href="admin.php?page=users">Users</a>
            <a href="admin.php?page=logout">Logout</a>
        </nav>
    </div>
</header>
<main>
    <div class="content">
        <?php echo $body ?? ''; ?>
    </div>
</main>
</body>
</html>