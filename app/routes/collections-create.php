<?php
$error = null;
$name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if ($name === '') {
        $error = 'Name is required.';
    } elseif (strlen($name) > 255) {
        $error = 'Name must be 255 characters or fewer.';
    } else {
        try {
            $id = Database::getInstance()->createCollectionType($name);
            header('Location: admin.php?page=collections-edit&id=' . $id, true, 303);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = ($e->getCode() === '23000')
                ? 'A content type with that name already exists.'
                : 'Failed to create collection.';
        }
    }
}
?>
<h1>Create Collection</h1>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<form method="post">
    <label for="name">Name</label><br>
    <input
        type="text"
        id="name"
        name="name"
        maxlength="255"
        required
        value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
    >
    <br><br>
    <button type="submit" class="btn-primary">Create</button>
</form>

