<?php

$errors = [];
$messages = [];

// Load flash messages (PRG) if present
if (!empty($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages'])) {
    $messages = $_SESSION['flash_messages'];
    unset($_SESSION['flash_messages']);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<h1>Content type not found</h1>';
    return;
}

$db = Database::getInstance();
$collection = $db->getCollectionById($id);
if (!$collection) {
    echo '<h1>Content type not found</h1>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete_content_type') {
            try {
                $db->deleteContentType($id);
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Location: admin.php?page=content-type', true, 303);
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Failed to delete content type.';
            }
        } elseif ($action === 'rename' && isset($_POST['new_name'])) {
            $newName = trim($_POST['new_name']);
            try {
                Database::getInstance()->updateContentTypeName($id, $newName);
                $_SESSION['flash_messages'] = ['Name updated.'];
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Location: admin.php?page=content-type-edit&id=' . $id, true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                $errors[] = ($e->getCode() === '23000') ? 'A content type with that name already exists.' : 'Failed to update name.';
            }
        } elseif ($action === 'save_all') {
            $fieldsJson = $_POST['fields_json'] ?? '';
            $decoded = json_decode($fieldsJson, true);
            if (!is_array($decoded)) {
                $errors[] = 'Invalid payload.';
            } else {
                try {
                    // Allowed types
                    $allowed = ['string', 'text', 'integer', 'decimal', 'boolean'];
                    foreach ($decoded as $item) {
                        // Normalize input
                        $fid = isset($item['id']) ? (int)$item['id'] : 0;
                        $deleted = !empty($item['deleted']);
                        $name = isset($item['name']) ? trim((string)$item['name']) : '';
                        $type = isset($item['field_type']) ? trim((string)$item['field_type']) : '';
                        $is_required = !empty($item['is_required']);
                        $is_translatable = !empty($item['is_translatable']);
                        $order = isset($item['order']) ? (int)$item['order'] : 0;

                        if ($fid > 0 && $deleted) {
                            // delete existing field
                            $db->deleteField($fid);
                            continue;
                        }

                        if ($fid > 0) {
                            // update existing
                            if ($name === '') {
                                throw new InvalidArgumentException('Field name is required for updates');
                            }
                            if (!in_array($type, $allowed, true)) {
                                throw new InvalidArgumentException('Invalid field type for updates');
                            }
                            $db->updateField($fid, $name, $type, $is_required, $is_translatable, $order);
                        } else {
                            // create new (skip if marked deleted or missing name)
                            if ($deleted) {
                                continue;
                            }
                            if ($name === '') {
                                // skip empty new rows silently
                                continue;
                            }
                            if (!in_array($type, $allowed, true)) {
                                throw new InvalidArgumentException('Invalid field type for create');
                            }
                            $db->createField($id, $name, $type, $is_required, $is_translatable, $order);
                        }
                    }

                    // Success - PRG
                    $_SESSION['flash_messages'] = ['Fields saved.'];
                    while (ob_get_level() > 0) { ob_end_clean(); }
                    header('Location: admin.php?page=content-type-edit&id=' . $id, true, 303);
                    exit;
                } catch (InvalidArgumentException $e) {
                    $errors[] = $e->getMessage();
                } catch (PDOException $e) {
                    $errors[] = 'Database error.';
                }
            }
        }
    }
}

$fields = $db->getFieldsForCollection($id);

// Prepare fields for JS - ensure booleans
$jsFields = array_map(function($f){
    return [
        'id' => (int)$f['id'],
        'name' => $f['name'],
        'field_type' => $f['field_type'],
        'is_required' => (bool)$f['is_required'],
        'is_translatable' => (bool)$f['is_translatable'],
        'order' => (int)$f['order']
    ];
}, $fields);

?>

<h1>Edit Content Type: <?= htmlspecialchars($collection['name'], ENT_QUOTES, 'UTF-8') ?></h1>
<p><a href="?page=content-type">← Back to content types</a></p>
<form method="post" style="margin-bottom:12px; display:flex; gap:8px; align-items:flex-end; flex-wrap:wrap;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="rename">
    <label style="display:flex; flex-direction:column; gap:4px;">
        <span>Content Type Name</span>
        <input type="text" name="new_name" value="<?= htmlspecialchars($collection['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required style="min-width:260px;">
    </label>
    <button type="submit" class="btn-primary">Rename</button>
</form>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (!empty($messages)): ?>
    <div class="alert alert-success">
        <?php foreach ($messages as $m): ?>
            <div><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2>Fields</h2>
<div id="collections-editor-root">
    <div id="collections-fields-list"></div>
    <form id="save-all-form" method="post" style="margin-top:16px; display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="save_all">
        <input type="hidden" name="fields_json" id="fields_json_input">
        <button type="button" id="save-all-btn" class="btn-primary">Save All</button>
        <button type="button" id="add-field-btn" class="btn-primary">Add Field</button>
        <a href="?page=content-type" class="btn-secondary">Cancel</a>
    </form>
</div>

<hr style="margin: 32px 0; border:none; border-top:1px solid #ccc;">

<h2>Danger Zone</h2>
<form method="post" onsubmit="return confirm('⚠️ WARNING: This will permanently delete the content type &quot;<?= htmlspecialchars($collection['name'], ENT_QUOTES, 'UTF-8') ?>&quot; and ALL associated entries, fields, and field values.\n\nThis action cannot be undone.\n\nAre you sure you want to continue?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="delete_content_type">
    <p style="margin-bottom:12px;">Deleting this content type will permanently remove all associated content entries, fields, and data.</p>
    <button type="submit" class="btn-danger">Delete Content Type</button>
</form>

<script>
    window.__collectionsInitial = <?= json_encode($jsFields, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    window.addEventListener('DOMContentLoaded', function(){
        if (typeof initCollectionsEditor === 'function') {
            initCollectionsEditor({
                rootId: 'collections-editor-root',
                listId: 'collections-fields-list',
                addBtnId: 'add-field-btn',
                saveBtnId: 'save-all-btn',
                formId: 'save-all-form',
                inputId: 'fields_json_input',
                initial: window.__collectionsInitial || []
            });
        }
    });
</script>
