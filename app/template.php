<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title><?php echo htmlspecialchars($title ?? 'Tiny Headless CMS'); ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header>
    <div class="content">
        <span class="project-title">Tiny Headless CMS</span>
        <nav>
            <a href="">Collections</a>
            <a href="">Singletons</a>
            <a href="">Assets</a>
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