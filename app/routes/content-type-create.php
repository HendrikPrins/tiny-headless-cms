<?php
$error = null;
$name = '';
$is_singleton = isset($_GET['singleton']) && $_GET['singleton'] === 'true' || false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            header('Location: admin.php?page=content-type-edit&id=' . $id, true, 303);
            exit;
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = ($e->getCode() === '23000')
                ? 'A content type with that name already exists.'
                : 'Failed to create content type.';
        }
    }
}
?>
<h1>Create Content Type</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post" style="display:flex; flex-direction:column; gap:12px; max-width:520px;">
    <label for="name">Name</label>
    <input
        type="text"
        id="name"
        name="name"
        maxlength="255"
        required
        value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
    >
    <label style="display:inline-flex; align-items:center; gap:8px;">
        <input type="checkbox" name="is_singleton" value="1" <?= $is_singleton ? 'checked' : '' ?>>
        <span>Singleton</span>
    </label>
    <div style="display:flex; gap:8px; align-items:center;">
        <button type="submit" class="btn-primary">Create</button>
        <a href="?page=content-type" class="btn-secondary">Cancel</a>
    </div>
</form>
