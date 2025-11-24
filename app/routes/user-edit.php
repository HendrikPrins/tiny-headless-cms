<?php
$title = 'Edit User';
$db = Database::getInstance();
$error = null; $notice = null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user = $id > 0 ? $db->getUserById($id) : null;
if (!$user) { echo '<div class="alert alert-danger">User not found.</div>'; return; }

$isCurrent = $user['id'] == $_SESSION['user_id'];
$username = $user['username'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? 'update';
        if ($action === 'delete') {
            if ($isCurrent) {
                $error = 'You cannot delete your own user.';
            } else {
                $db->deleteUser($user['id']);
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Location: index.php?page=settings', true, 303);
                exit;
            }
        } else { // update
            $newUsername = trim($_POST['username'] ?? '');
            $newPassword = $_POST['password'] ?? '';
            $newRole = $_POST['role'] ?? $role;
            if ($newUsername === '') {
                $error = 'Username is required.';
            } elseif (strlen($newUsername) > 50) {
                $error = 'Username too long.';
            } elseif ($newUsername !== $username && $db->getUserByUsername($newUsername)) {
                $error = 'Username already exists.';
            } elseif (!in_array($newRole, ['admin','editor'], true)) {
                $error = 'Invalid role.';
            } else {
                try {
                    $db->updateUser($user['id'], $newUsername, $newPassword !== '' ? $newPassword : null, $newRole, !$isCurrent);
                    $username = $newUsername;
                    if (!$isCurrent) { $role = $newRole; }
                    $notice = 'User updated.';
                } catch (Exception $e) {
                    $error = 'Failed to update user.';
                }
            }
        }
    }
}
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<div class="content-header">
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=settings">Settings</a></li>
            <li aria-current="page">edit user</li>
        </ol>
    </nav>
    <h1><?= $title ?></h1>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES) ?></div><?php endif; ?>
<?php if ($notice): ?><div class="alert alert-success"><?= htmlspecialchars($notice, ENT_QUOTES) ?></div><?php endif; ?>
<form method="post" class="form form-limited">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <label class="field"><span>Username</span>
        <input type="text" name="username" required maxlength="50" value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">
    </label>
    <label class="field"><span>New Password <span class="text-secondary">(leave empty to keep current)</span></span>
        <input type="password" name="password" autocomplete="new-password">
    </label>
    <label class="field"><span>Role</span>
        <select name="role" <?= $isCurrent ? 'disabled' : '' ?>>
            <option value="editor" <?= $role==='editor'?'selected':''; ?>>Editor</option>
            <option value="admin" <?= $role==='admin'?'selected':''; ?>>Admin</option>
        </select>
    </label>
    <div class="form-buttons">
        <button type="submit" class="btn-primary">Save</button>
        <a href="index.php?page=settings" class="btn-secondary">Cancel</a>
    </div>
</form>

