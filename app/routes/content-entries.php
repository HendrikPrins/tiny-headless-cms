<?php
$db = Database::getInstance();
$ctId = isset($_GET['ct']) ? (int)$_GET['ct'] : 0;
if ($ctId <= 0) { echo '<h1>Content type not found</h1>'; return; }
$contentType = $db->getContentType($ctId);
if (!$contentType) { echo '<h1>Content type not found</h1>'; return; }
$title = 'Entries for: ' . htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8');
$isSingleton = $contentType['is_singleton'];

// Handle delete (admins only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!isAdmin()) {
        http_response_code(403);
        echo '<div class="alert alert-danger">You do not have permission to delete entries.</div>';
    } elseif (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId > 0) {
            $db->deleteEntry($contentType, $entryId);
            header('Location: index.php?page=content-entries&ct=' . $ctId, true, 303);
            exit;
        }
    }
}

$previewLocale = $_GET['locale'] ?? '';
if (!in_array($previewLocale, CMS_LOCALES)) {
    $previewLocale = CMS_LOCALES[0];
}
$schema  = $contentType['schema'] ?? [];
$fields  = $schema['fields'] ?? [];
$preview = $schema['preview'] ?? [];

$previewFieldNames = $preview['fields'] ?? [];
$previewOrderBy    = $preview['order_field'] ?? '';
$previewOrderDir   = strtolower($preview['order_direction'] ?? '');

if (!empty($previewFieldNames)) {
    $previewFields = [];
    foreach ($previewFieldNames as $pfName) {
        if ($pfName === 'id') {
            $previewFields[] = ['name' => 'id', 'type' => 'number', 'is_translatable' => false];
            continue;
        }
        $matchedField = array_filter($fields, fn($f) => $f['name'] === $pfName);
        if (!empty($matchedField)) {
            $previewFields[] = reset($matchedField);
        }
    }
}
if (empty($previewFields)) {
    $previewFields = array_slice($fields, 0, 3);
    array_unshift($previewFields, ['name' => 'id', 'type' => 'number', 'is_translatable' => false]);
}

if (empty($previewOrderBy)) {
    $previewOrderBy = 'id';
    $previewOrderDir = 'desc';
    $previewOrderByIsLocalized = false;
} else {
    foreach ($fields as $f) {
        if ($f['name'] === $previewOrderBy) {
            $previewOrderByIsLocalized = !empty($f['is_translatable']);
            break;
        }
    }
}

if (!in_array($previewOrderDir, ['asc', 'desc'], true)) {
    $previewOrderDir = 'desc';
}

$entries = $db->getEntriesForContentType($contentType, $previewLocale, $previewOrderByIsLocalized, $previewOrderBy, $previewOrderDir);
$entryCount = count($entries);
?>
<div class="content-header">
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=content-type"><?= $isSingleton ? 'Singletons' : 'Collections' ?></a></li>
            <li aria-current="page">Entries: <?= htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>
    <h1>Entries for "<?= htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8') ?>"</h1>
</div>

<?php if ($contentType['is_singleton']): ?>
    <?php if ($entryCount === 0): ?>
        <p>No entry yet.</p>
        <?php if (isAdmin()): ?>
        <p><a class="btn btn-primary" href="?page=content-entry-edit&ct=<?= (int)$ctId ?>">Create Entry</a></p>
        <?php endif; ?>
    <?php else: ?>
        <?php $first = $entries[0]; ?>
        <p><a class="btn btn-primary" href="?page=content-entry-edit&ct=<?= (int)$ctId ?>&id=<?= (int)$first['id'] ?>">Edit Singleton</a></p>
    <?php endif; ?>
<?php else: ?>
    <?php if (isAdmin()): ?>
    <p><a class="btn btn-primary" href="?page=content-entry-edit&ct=<?= (int)$ctId ?>">New Entry</a></p>
    <?php endif; ?>
    <?php if (empty($entries)): ?>
        <p>No entries yet.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <?php foreach ($previewFields as $field): ?>
                            <th>
                                <?= htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($field['is_translatable']): ?>
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
                        <?php foreach ($previewFields as $field):
                            $value = $entry[$field['name']];
                            $fieldTypeObj = FieldRegistry::get($field['type']);
                            if ($fieldTypeObj) {
                                $displayValue = $fieldTypeObj->renderPreview($field['name'], $value);
                            } else {
                                $displayValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                            }
                        ?>
                        <td><?= $displayValue ?></td>
                        <?php endforeach; ?>
                        <td style="white-space:nowrap;">
                            <a class="btn btn-icon btn-primary" href="?page=content-entry-edit&ct=<?= $ctId ?>&id=<?= $entry['id'] ?>"><?=ICON_PENCIL?></a>
                            <?php if (isAdmin()): ?>
                            <form class="form-table-delete" method="post" onsubmit="return confirm('Delete this entry?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                <button type="submit" class="btn-icon btn-danger"><?=ICON_TRASH?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
