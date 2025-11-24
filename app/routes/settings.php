<?php
$title = 'Settings';
$types = Database::getInstance()->getContentTypes();
$users = Database::getInstance()->getAllUsers();
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<h1>Settings</h1>

<h2>Profile</h2>
Your username: <b><?php echo htmlspecialchars($_SESSION['user_username']); ?></b><br>
Your role: <b><?php echo htmlspecialchars($_SESSION['user_role']); ?></b><br>
<br>
<button type="button" class="btn-secondary" id="logout-btn">Logout</button>
<script>
document.getElementById('logout-btn').addEventListener('click', function() {
    if (confirm('Are you sure you want to log out?')) {
        window.location.href = 'index.php?page=logout';
    }
});
</script>

<h2>Users</h2>
<div style="margin-bottom:12px;">
    <a href="index.php?page=user-add" class="btn-primary">Create User</a>
</div>
<?php if (empty($users)): ?>
    <p>No users found.</p>
<?php else: ?>
<div class="table-wrapper">
    <table class="striped bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Role</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td>
                    <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ((int)$u['id'] === (int)$_SESSION['user_id']): ?>
                    <span class="text-secondary">(you)</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['role'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="table-nowrap">
                    <a href="index.php?page=user-edit&id=<?= (int)$u['id'] ?>" class="btn btn-icon btn-primary" title="Edit"><?=ICON_PENCIL?></a>
                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                    <form method="post" action="index.php?page=user-edit&id=<?= (int)$u['id'] ?>" class="d-inline" onsubmit="return confirm('Delete this user? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn-icon btn-danger" title="Delete"><?=ICON_TRASH?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<h2>Locales</h2>
Add/remove locales.
