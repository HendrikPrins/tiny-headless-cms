<?php
$db = Database::getInstance();
$ctId = isset($_GET['ct']) ? (int)$_GET['ct'] : 0;
$entryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ctId <= 0) { echo '<h1>Content type not found</h1>'; return; }
$ct = $db->getCollectionById($ctId);
if (!$ct) { echo '<h1>Content type not found</h1>'; return; }
$fields = $db->getFieldsForCollection($ctId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        // Singleton guard: only one entry allowed
        if ($ct['is_singleton'] && $entryId === 0 && $db->getEntryCountForContentType($ctId) > 0) {
            echo '<div class="alert alert-danger">Singleton already has an entry.</div>';
        } else {
            if ($entryId === 0) {
                $entryId = $db->createEntry($ctId);
            }
            // Collect values by field id
            $values = [];
            foreach ($fields as $f) {
                $fid = (int)$f['id'];
                $key = 'field_' . $fid;
                $raw = $_POST[$key] ?? '';
                switch ($f['field_type']) {
                    case 'integer':
                        $values[$fid] = ($raw === '') ? null : (string)(int)$raw; break;
                    case 'decimal':
                        $values[$fid] = ($raw === '') ? null : (string)(float)$raw; break;
                    case 'boolean':
                        $values[$fid] = isset($_POST[$key]) ? '1' : '0'; break;
                    case 'text':
                    case 'string':
                    default:
                        $values[$fid] = $raw; break;
                }
            }
            $db->saveEntryValues($entryId, $values);
            header('Location: admin.php?page=content-entries&ct=' . $ctId, true, 303);
            exit;
        }
    }
}

$entry = null;
$values = [];
if ($entryId > 0) {
    $entry = $db->getEntryById($entryId);
    if (!$entry || (int)$entry['content_type_id'] !== $ctId) { echo '<h1>Entry not found</h1>'; return; }
    $values = $db->getFieldValuesForEntry($entryId);
}

?>
<h1><?= $entryId ? 'Edit' : 'Create' ?> Entry: <?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?></h1>
<?php if (!$ct['is_singleton']): ?>
<p><a href="?page=content-entries&ct=<?= (int)$ctId ?>">← Back to entries</a></p>
<?php endif; ?>

<form method="post" style="display:flex; flex-direction:column; gap:12px; max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <?php foreach ($fields as $f): $fid = (int)$f['id']; $name = htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); $ft = $f['field_type']; $val = $values[$fid] ?? ''; ?>
        <label style="display:flex; flex-direction:column; gap:6px;">
            <span><?= $name ?><?= $f['is_required'] ? ' *' : '' ?></span>
            <?php if ($ft === 'text'): ?>
                <textarea name="field_<?= $fid ?>" rows="4"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></textarea>
            <?php elseif ($ft === 'integer'): ?>
                <input type="number" step="1" name="field_<?= $fid ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
            <?php elseif ($ft === 'decimal'): ?>
                <input type="number" step="any" name="field_<?= $fid ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
            <?php elseif ($ft === 'boolean'): ?>
                <input type="checkbox" name="field_<?= $fid ?>" value="1" <?= ($val === '1') ? 'checked' : '' ?>>
            <?php else: ?>
                <input type="text" name="field_<?= $fid ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
            <?php endif; ?>
        </label>
    <?php endforeach; ?>
    <div style="display:flex; gap:8px; align-items:center;">
        <button type="submit" class="btn-primary">Save</button>
        <?php if (!$ct['is_singleton']): ?>
        <a href="?page=content-entries&ct=<?= (int)$ctId ?>" class="btn-secondary">Cancel</a>
        <?php else: ?>
        <a href="?page=content-type" class="btn-secondary">Cancel</a>
        <?php endif; ?>
    </div>
</form>

