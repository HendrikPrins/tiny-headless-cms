<?php
$db = Database::getInstance();
$ctId = isset($_GET['ct']) ? (int)$_GET['ct'] : 0;
if ($ctId <= 0) { echo '<h1>Content type not found</h1>'; return; }
$ct = $db->getCollectionById($ctId);
if (!$ct) { echo '<h1>Content type not found</h1>'; return; }

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId > 0) {
            $db->deleteEntry($entryId);
            header('Location: admin.php?page=content-entries&ct=' . $ctId, true, 303);
            exit;
        }
    }
}

$entries = $db->getEntriesForContentType($ctId);
$entryCount = count($entries);
?>
<h1>Content: <?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?></h1>
<p><a href="?page=content-type">← Back to content types</a></p>

<?php if ($ct['is_singleton']): ?>
    <?php if ($entryCount === 0): ?>
        <p>No entry yet.</p>
        <p><a class="btn btn-primary" href="?page=content-entry-edit&ct=<?= (int)$ctId ?>">Create Entry</a></p>
    <?php else: ?>
        <?php $first = $entries[0]; ?>
        <p><a class="btn btn-primary" href="?page=content-entry-edit&ct=<?= (int)$ctId ?>&id=<?= (int)$first['id'] ?>">Edit Singleton</a></p>
    <?php endif; ?>
<?php else: ?>
    <p><a class="btn btn-primary" href="?page=content-entry-edit&ct=<?= (int)$ctId ?>">New Entry</a></p>
    <?php if (empty($entries)): ?>
        <p>No entries yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>ID</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $e): ?>
                    <tr>
                        <td>#<?= (int)$e['id'] ?></td>
                        <td>
                            <a href="?page=content-entry-edit&ct=<?= (int)$ctId ?>&id=<?= (int)$e['id'] ?>">Edit</a>
                            <form method="post" style="display:inline-block; margin-left:8px;" onsubmit="return confirm('Delete this entry?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
                                <button type="submit" class="btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

