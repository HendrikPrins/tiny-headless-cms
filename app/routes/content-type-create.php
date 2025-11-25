<?php
requireAdmin();

$error = null;
$name = '';
$is_singleton = isset($_GET['singleton']) && $_GET['singleton'] === 'true' || false;
$title = $is_singleton ? 'Create Singleton' : 'Create Collection';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $is_singleton = !empty($_POST['is_singleton']);
        if ($name === '') {
            $error = 'Name is required.';
        } elseif (strlen($name) > 255) {
            $error = 'Name must be 255 characters or fewer.';
        } else {
            try {
                $id = Database::getInstance()->createContentType($name, $is_singleton);
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Location: index.php?page=content-type-edit&id=' . $id, true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            } catch (PDOException $e) {
                $error = 'Failed to create content type.';
            }
        }
    }
}
?>
<div class="content-header">
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=content-type"><?= $is_singleton ? 'Singletons' : 'Collections' ?></a></li>
            <li aria-current="page">new</li>
        </ol>
    </nav>
    <h1><?= $title ?></h1>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" class="form form-limited">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="is_singleton" value="<?= $is_singleton ? '1' : '0' ?>">
    <label for="name">Name</label>
    <input
        type="text"
        id="name"
        name="name"
        maxlength="255"
        required
        value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
    >
    <div class="form-buttons">
        <button type="submit" class="btn-primary">Create</button>
        <a href="?page=content-type" class="btn-secondary">Cancel</a>
    </div>
</form>
