<?php
$db = Database::getInstance();
$ctId = isset($_GET['ct']) ? (int)$_GET['ct'] : 0;
if ($ctId <= 0) { echo '<h1>Content type not found</h1>'; return; }
$ct = $db->getContentType($ctId);
if (!$ct) { echo '<h1>Content type not found</h1>'; return; }
$isSingleton = $ct['is_singleton'];

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
$fields = $db->getFieldsForContentType($ctId);
$previewLocale = $_GET['locale'] ?? '';
if (!in_array($previewLocale, CMS_LOCALES)) {
    $previewLocale = CMS_LOCALES[0];
}

// Determine which fields to show as preview (max 3)
$previewFields = array_slice($fields, 0, 3);

// Load field values for all entries (for preview locale)
$entriesWithValues = [];
foreach ($entries as $e) {
    $entryId = (int)$e['id'];
    // Get values for translatable fields in preview locale
    $translatableValues = $db->getFieldValuesForEntry($entryId, $previewLocale);
    // Get values for non-translatable fields (empty locale)
    $nonTranslatableValues = $db->getFieldValuesForEntry($entryId, '');

    $entriesWithValues[] = [
        'id' => $entryId,
        'translatable_values' => $translatableValues,
        'non_translatable_values' => $nonTranslatableValues,
    ];
}
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
                                    <span style="font-size:0.75em; color:#999; font-weight:normal;">(<?= strtoupper($previewLocale) ?>)</span>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entriesWithValues as $entry): ?>
                    <tr>
                        <td>#<?= $entry['id'] ?></td>
                        <?php foreach ($previewFields as $field):
                            $fid = (int)$field['id'];
                            $isTranslatable = (bool)$field['is_translatable'];

                            // Get value from appropriate source
                            $value = $isTranslatable
                                ? ($entry['translatable_values'][$fid] ?? '')
                                : ($entry['non_translatable_values'][$fid] ?? '');

                            // Format value based on field type
                            $displayValue = '';
                            if ($value === '' || $value === null) {
                                $displayValue = '<span style="color:#999;">-</span>';
                            } else {
                                switch ($field['field_type']) {
                                    case 'boolean':
                                        $displayValue = $value === '1' ? '✓' : '✗';
                                        break;
                                    case 'text':
                                        // Truncate long text
                                        if (strlen($value) > 60) {
                                            $displayValue = htmlspecialchars(substr($value, 0, 60), ENT_QUOTES, 'UTF-8') . '...';
                                        } else {
                                            $displayValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                                        }
                                        break;
                                    case 'string':
                                        // Truncate long strings
                                        if (strlen($value) > 40) {
                                            $displayValue = htmlspecialchars(substr($value, 0, 40), ENT_QUOTES, 'UTF-8') . '...';
                                        } else {
                                            $displayValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                                        }
                                        break;
                                    case 'integer':
                                    case 'decimal':
                                        $displayValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                                        break;
                                    default:
                                        $displayValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                                }
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

