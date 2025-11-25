<?php
requireAdmin();

$title = 'Create User';
$db = Database::getInstance();
$error = null;
$username = '';
$role = 'editor';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $role = $_POST['role'] ?? 'editor';
        $password = $_POST['password'] ?? '';
        if ($username === '') {
            $error = 'Username is required.';
        } elseif (strlen($username) > 50) {
            $error = 'Username too long.';
        } elseif ($password === '') {
            $error = 'Password is required.';
        } elseif (!in_array($role, ['admin', 'editor'], true)) {
            $error = 'Invalid role.';
        } elseif ($db->getUserByUsername($username)) {
            $error = 'Username already exists.';
        } else {
            $db->createUser($username, $password, $role);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Location: index.php?page=settings', true, 303);
            exit;
        }
    }
}
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<div class="content-header">
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=settings">Settings</a></li>
            <li aria-current="page">new user</li>
        </ol>
    </nav>
    <h1><?= $title ?></h1>
</div>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post" class="form form-limited">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <label class="field">
        <span>Username</span>
        <input type="text" name="username" required maxlength="50" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="field">
        <span>Password</span>
        <input type="password" name="password" required>
    </label>
    <label class="field">
        <span>Role</span>
        <select name="role">
            <option value="editor" <?= $role === 'editor' ? 'selected' : ''; ?>>Editor</option>
            <option value="admin" <?= $role === 'admin' ? 'selected' : ''; ?>>Admin</option>
        </select>
    </label>
    <div class="form-buttons">
        <button type="submit" class="btn-primary">Create</button>
        <a href="index.php?page=settings" class="btn-secondary">Cancel</a>
    </div>
</form>
