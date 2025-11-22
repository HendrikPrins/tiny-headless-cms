<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars(!empty($title) ? ($title . ' - Tiny Headless CMS') : 'Tiny Headless CMS'); ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<main class="full">
    <h1>Tiny Headless CMS</h1>
    <?php echo $body ?? ''; ?>
</main>
</body>
</html>