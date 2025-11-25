<?php
$db = Database::getInstance();
$ctId = isset($_GET['ct']) ? (int)$_GET['ct'] : 0;
if ($ctId <= 0) { echo '<h1>Content type not found</h1>'; return; }
$ct = $db->getContentType($ctId);
if (!$ct) { echo '<h1>Content type not found</h1>'; return; }
$title = 'Entries for: ' . htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8');
$isSingleton = $ct['is_singleton'];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId > 0) {
            $db->deleteEntry($entryId);
            header('Location: index.php?page=content-entries&ct=' . $ctId, true, 303);
            exit;
        }
    }
}

$previewLocale = $_GET['locale'] ?? '';
if (!in_array($previewLocale, CMS_LOCALES)) {
    $previewLocale = CMS_LOCALES[0];
}
$entries = $db->getEntriesForContentType($ct, $previewLocale);
$entryCount = count($entries);
$fields = $ct["schema"]["fields"];

// Determine which fields to show as preview (max 3)
$previewFields = array_slice($fields, 0, 3);
?>
<div class="content-header">
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=content-type"><?= $isSingleton ? 'Singletons' : 'Collections' ?></a></li>
            <li aria-current="page">Entries: <?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>
    <h1>Entries for "<?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?>"</h1>
</div>

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
                    <tr>
                        <th>ID</th>
                        <?php foreach ($previewFields as $field): ?>
                            <th>
                                <?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ((bool)$field['is_translatable']): ?>
                                    <span class="text-secondary">(<?= strtoupper($previewLocale) ?>)</span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td>#<?= $entry['id'] ?></td>
                        <?php foreach ($previewFields as $field):
    $value = $entry[$field['name']];
    $fieldTypeObj = FieldRegistry::get($field['type']);
    if ($fieldTypeObj) {
        $converted = $fieldTypeObj->readFromDb((string)$value);
        $displayValue = $fieldTypeObj->renderPreview($field['name'], $converted);
    } else {
        $displayValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
?>
                            <td><?= $displayValue ?></td>
                        <?php endforeach; ?>
                        <td style="white-space:nowrap;">
                            <a class="btn btn-icon btn-primary" href="?page=content-entry-edit&ct=<?= $ctId ?>&id=<?= $entry['id'] ?>"><?=ICON_PENCIL?></a>
                            <form class="form-table-delete" method="post" onsubmit="return confirm('Delete this entry?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                <button type="submit" class="btn-icon btn-danger"><?=ICON_TRASH?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
