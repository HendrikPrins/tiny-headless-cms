<?php
requireAdmin();

$errors = [];
$messages = [];
$fieldTypes = FieldRegistry::getTypeNames();

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
$contentType = $db->getContentType($id);
if (!$contentType) {
    echo '<h1>Content type not found</h1>';
    return;
}
$title = 'Edit Content Type: ' . htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8');
$isSingleton = (bool)$contentType['is_singleton'];
$editorPermissionMode = $contentType['editor_permission_mode'] ?? 'read-only';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'delete_content_type') {
            try {
                $db->deleteContentType($id);
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Location: index.php?page=content-type', true, 303);
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Failed to delete content type.';
            }
        } elseif ($action === 'rename' && isset($_POST['new_name'])) {
            $newName = trim($_POST['new_name']);
            try {
                Database::getInstance()->updateContentTypeName($id, $newName);
                $_SESSION['flash_messages'] = ['Name updated.'];
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Location: index.php?page=content-type-edit&id=' . $id, true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                $errors[] = 'Failed to update name.';
            }
        } elseif ($action === 'preview') {
            $fieldsInput = trim($_POST['preview_fields'] ?? '');
            $fields = [];
            if ($fieldsInput !== '') {
                $fields = array_map('trim', explode(',', $fieldsInput));
            }
            $orderField = $_POST['preview_order_field'] ?? null;
            if ($orderField === '') {
                $orderField = null;
            }
            $orderDirection = $_POST['preview_order_direction'] ?? 'asc';
            try {
                Database::getInstance()->updateContentTypePreview($id, $fields, $orderField, $orderDirection);
                $_SESSION['flash_messages'] = ['Preview updated.'];
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Location: index.php?page=content-type-edit&id=' . $id, true, 303);
                exit;
            } catch (InvalidArgumentException $e) {
                $errors[] = $e->getMessage();
            } catch (PDOException $e) {
                $errors[] = 'Failed to update preview.';
            }
        } elseif ($action === 'save_all') {
            $fieldsJson = $_POST['fields'] ?? '';
            $decoded = json_decode($fieldsJson, true);
            if (!is_array($decoded)) {
                $errors[] = 'Invalid payload.';
            } else {
                try {
                    Database::getInstance()->setContentTypeSchema($id, $decoded);

                    $_SESSION['flash_messages'] = ['Fields saved.'];
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Location: index.php?page=content-type-edit&id=' . $id, true, 303);
                    exit;
                } catch (InvalidArgumentException $e) {
                    $errors[] = $e->getMessage();
                } catch (PDOException $e) {
                    $errors[] = 'Database error.';
                    $errors[] = $e->getMessage();
                }
            }
        } elseif ($action === 'update_editor_permissions') {
            $mode = $_POST['editor_permission_mode'] ?? '';
            $mode = trim($mode);
            $allowed = ['read-only', 'edit-only', 'full-access'];
            if (!in_array($mode, $allowed, true)) {
                $errors[] = 'Invalid editor permission mode.';
            } else {
                try {
                    $db->updateContentTypeEditorPermissionMode($id, $mode);
                    $_SESSION['flash_messages'] = ['Editor permissions updated.'];
                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }
                    header('Location: index.php?page=content-type-edit&id=' . $id, true, 303);
                    exit;
                } catch (PDOException $e) {
                    $errors[] = 'Failed to update editor permissions.';
                }
            }
        }
    }
}

$fields = $contentType['schema']['fields'];
$jsFields = array_map(function ($f) {
    return [
        'name' => $f['name'],
        'type' => $f['type'],
        'is_translatable' => (bool)$f['is_translatable']
    ];
}, $fields);

$currentPreview = [
    "fields" => $contentType['schema']['preview']['fields'] ?? [],
    "order_field" => $contentType['schema']['preview']['order_field'] ?? null,
    "order_direction" => $contentType['schema']['preview']['order_direction'] ?? 'asc',
];

$previewFields = implode(',', $currentPreview['fields']);

?>
<div class="content-header">
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=content-type"><?= $isSingleton ? 'Singletons' : 'Collections' ?></a></li>
            <li aria-current="page">Edit: <?= htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8') ?></li>
        </ol>
    </nav>
    <h1>Edit Content Type: <?= htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8') ?></h1>
</div>

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
<div id="editor-root">
    <template id="rowTemplate">
        <tr>
            <td>
                <input type="text" data-column="field_name" placeholder="Field name">
            </td>
            <td>
                <select data-column="type">
                    <?php foreach ($fieldTypes as $fieldType): ?>
                        <option value="<?= $fieldType ?>"><?= $fieldType ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td style="text-align:center;">
                <input type="checkbox" data-column="is_translatable">
            </td>
            <td style="white-space:nowrap;">
                <button type="button" data-column="btn_up" class="btn-primary btn-icon" title="Move up"><?= ICON_CHEVRON_UP ?></button>
                <button type="button" data-column="btn_down" class="btn-primary btn-icon" title="Move down"><?= ICON_CHEVRON_DOWN ?></button>
                <button type="button" data-column="btn_delete" class="btn-danger btn-icon" title="Delete"><?= ICON_TRASH ?></button>
            </td>
        </tr>
    </template>
    <div class="table-wrapper">
        <table class="rows-bordered" id="fieldTable">
            <thead>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th style="text-align:center;">Translatable</th>
                <th></th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <div id="changes-summary">
        <h3 style="margin-top:0; font-size:1.1em;">Changes Summary</h3>
        <div id="changes-list"></div>
    </div>

    <form id="save-all-form" method="post" class="form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="action" value="save_all">
        <input type="hidden" name="fields" id="fields_input">
        <div class="form-buttons">
            <button type="button" id="save-all-btn" class="btn-primary">Save All</button>
            <button type="button" id="add-field-btn" class="btn-primary">Add Field</button>
            <a href="?page=content-type" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<hr>

<h2>Preview</h2>
<form method="post" class="form form-inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="preview">

    <label for="preview_fields">Fields</label>
    <input type="text" id="preview_fields" name="preview_fields" value="<?= htmlspecialchars($previewFields, ENT_QUOTES, 'UTF-8') ?>" style="min-width:260px;">
    <label for="preview_order_field">Order by</label>
    <select id="preview_order_field" name="preview_order_field">
        <option value="" <?= $currentPreview['order_field'] === null ? 'selected' : '' ?>>ID</option>
        <?php foreach ($fields as $f): ?>
            <option value="<?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?>" <?= $currentPreview['order_field'] === $f['name'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8') ?></option>
        <?php endforeach; ?>
    </select>
    <label for="preview_order_direction">Order direction</label>
    <select id="preview_order_direction" name="preview_order_direction">
        <option value="asc" <?= $currentPreview['order_direction'] === 'asc' ? 'selected' : '' ?>>Ascending</option>
        <option value="desc" <?= $currentPreview['order_direction'] === 'desc' ? 'selected' : '' ?>>Descending</option>
    </select>

    <button type="submit" class="btn-primary">Save</button>
</form>

<hr>

<h2>Editor Permissions</h2>
<form method="post" class="form form-inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="update_editor_permissions">
    <label for="editor_permission_mode">Editors can</label>
    <select id="editor_permission_mode" name="editor_permission_mode">
        <option value="read-only" <?= $editorPermissionMode === 'read-only' ? 'selected' : '' ?>>Read only (no editing, creating, or deleting)</option>
        <option value="edit-only" <?= $editorPermissionMode === 'edit-only' ? 'selected' : '' ?>>Read and edit existing entries only</option>
        <option value="full-access" <?= $editorPermissionMode === 'full-access' ? 'selected' : '' ?>>Read, edit, create, and delete entries</option>
    </select>
    <button type="submit" class="btn-primary">Save</button>
</form>

<hr>

<h2>Rename</h2>
<form method="post" class="form form-inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="rename">
    <label for="new_name">Content Type Name</label>
    <input type="text" id="new_name" name="new_name" value="<?= htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required style="min-width:260px;">
    <button type="submit" class="btn-primary">Rename</button>
</form>

<hr>

<h2>Danger Zone</h2>
<form method="post" onsubmit="return confirm('⚠️ WARNING: This will permanently delete the content type &quot;<?= htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8') ?>&quot; and ALL associated entries, fields, and field values.\n\nThis action cannot be undone.\n\nAre you sure you want to continue?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="delete_content_type">
    <p style="margin-bottom:12px;">Deleting this content type will permanently remove all associated content entries, fields, and data.</p>
    <button type="submit" class="btn-danger">Delete Content Type</button>
</form>

<script>
    (function () {
        const initialFields = <?= json_encode($jsFields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        // Track deleted by original name (names are unique per content type)
        const deletedFields = new Set();

        function getCurrentState() {
            const tableBody = document.querySelector('#fieldTable tbody');
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            return rows.map((row, idx) => {
                return {
                    // originalName is what this row was created from (for existing fields)
                    originalName: row.getAttribute('data-original-name') || null,
                    name: row.querySelector('[data-column="field_name"]').value.trim(),
                    type: row.querySelector('[data-column="type"]').value,
                    is_translatable: row.querySelector('[data-column="is_translatable"]').checked,
                    order: idx
                };
            });
        }

        function findOriginalByName(name) {
            return initialFields.find(f => f.name === name) || null;
        }

        function detectChanges() {
            const current = getCurrentState();
            const changes = [];

            // Deleted fields (by original name)
            deletedFields.forEach(name => {
                const orig = findOriginalByName(name);
                if (orig) {
                    changes.push({
                        type: 'deleted',
                        originalName: name,
                        name: orig.name
                    });
                }
            });

            current.forEach(field => {
                // Existing row if it has an originalName from initial fields
                if (field.originalName) {
                    const orig = findOriginalByName(field.originalName);
                    if (!orig) return;

                    const fieldChanges = [];
                    if (field.name !== orig.name) {
                        fieldChanges.push(`name: "${orig.name}" → "${field.name}"`);
                    }
                    if (field.type !== orig.type) {
                        fieldChanges.push(`type: ${orig.type} → ${field.type}`);
                    }
                    if (field.is_translatable !== orig.is_translatable) {
                        fieldChanges.push(`translatable: ${orig.is_translatable ? 'yes' : 'no'} → ${field.is_translatable ? 'yes' : 'no'}`);
                    }

                    if (fieldChanges.length > 0) {
                        changes.push({
                            type: 'modified',
                            originalName: field.originalName,
                            name: field.name,
                            changes: fieldChanges
                        });
                    }
                } else if (field.name) {
                    // New field
                    changes.push({
                        type: 'new',
                        name: field.name,
                        type: field.type
                    });
                }
            });

            return changes;
        }

        function updateSummary() {
            const changes = detectChanges();
            const summaryDiv = document.getElementById('changes-summary');
            const changesList = document.getElementById('changes-list');

            if (changes.length === 0) {
                summaryDiv.style.display = 'none';
                return;
            }

            summaryDiv.style.display = 'block';
            changesList.innerHTML = '';

            changes.forEach(change => {
                const item = document.createElement('div');
                item.style.cssText = 'margin-bottom:8px; padding:8px; border-radius:4px; border-left:4px solid #007bff;';

                if (change.type === 'new') {
                    item.innerHTML = `<strong>➕ New field:</strong> ${escapeHtml(change.name)} (${change.type})`;
                    item.style.borderLeftColor = '#28a745';
                } else if (change.type === 'deleted') {
                    item.innerHTML = `<strong>🗑️ Deleted:</strong> ${escapeHtml(change.name)} <button type="button" class="btn-secondary" style="margin-left:8px; padding:2px 8px; font-size:0.85em;" data-undelete="${change.originalName}">Undo Delete</button>`;
                    item.style.borderLeftColor = '#dc3545';
                } else if (change.type === 'modified') {
                    item.innerHTML = `<strong>✏️ Modified:</strong> ${escapeHtml(change.name)}<ul style="margin:4px 0 0 20px; padding:0;">${change.changes.map(c => `<li>${escapeHtml(c)}</li>`).join('')}</ul>`;
                    item.style.borderLeftColor = '#ffc107';
                }

                changesList.appendChild(item);
            });

            // Add undelete handlers
            changesList.querySelectorAll('[data-undelete]').forEach(btn => {
                btn.addEventListener('click', function () {
                    const originalName = this.getAttribute('data-undelete');
                    undeleteField(originalName);
                });
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function addField(field) {
            if (!field) {
                field = {};
            }
            const tableBody = document.querySelector('#fieldTable tbody');
            const template = document.getElementById('rowTemplate');
            const row = template.content.cloneNode(true).querySelector('tr');

            // For existing fields, remember their original name; new fields have null originalName
            if (field.name) {
                row.setAttribute('data-original-name', field.name);
            }

            const nameInput = row.querySelector('[data-column="field_name"]');
            const typeSelect = row.querySelector('[data-column="type"]');
            const transCheck = row.querySelector('[data-column="is_translatable"]');

            nameInput.value = field.name || '';
            typeSelect.value = field.type || 'string';
            transCheck.checked = field.is_translatable || false;

            [nameInput, typeSelect, transCheck].forEach(el => {
                el.addEventListener('change', updateSummary);
                el.addEventListener('input', updateSummary);
            });

            row.querySelector('[data-column="btn_up"]').addEventListener('click', function () {
                const prev = row.previousElementSibling;
                if (prev) {
                    row.parentNode.insertBefore(row, prev);
                    updateSummary();
                }
            });

            row.querySelector('[data-column="btn_down"]').addEventListener('click', function () {
                const next = row.nextElementSibling;
                if (next) {
                    row.parentNode.insertBefore(next, row);
                    updateSummary();
                }
            });

            row.querySelector('[data-column="btn_delete"]').addEventListener('click', function () {
                const originalName = row.getAttribute('data-original-name');
                if (originalName) {
                    deletedFields.add(originalName);
                }
                row.remove();
                updateSummary();
            });

            tableBody.appendChild(row);
        }

        function undeleteField(originalName) {
            deletedFields.delete(originalName);
            const orig = findOriginalByName(originalName);
            if (orig) {
                addField(orig);
            }
            updateSummary();
        }

        function saveAll() {
            const current = getCurrentState();
            const payload = [];

            // Existing and new fields
            current.forEach(field => {
                const isExisting = !!field.originalName;
                const orig = isExisting ? findOriginalByName(field.originalName) : null;

                const originalName = orig ? orig.name : null;
                const originalType = orig ? orig.type : null;
                const originalTranslatable = orig ? !!orig.is_translatable : null;

                const entry = {
                    // original identifying name (null for new fields)
                    name: originalName,
                    type: originalType,
                    is_translatable: originalTranslatable,
                    $name: field.name,
                    $type: field.type,
                    $is_translatable: !!field.is_translatable
                };

                payload.push(entry);
            });

            // Deleted fields (existing only, new fields are never added to deletedFields)
            deletedFields.forEach(originalName => {
                const orig = findOriginalByName(originalName);
                if (!orig) {
                    return;
                }
                payload.push({
                    name: orig.name,
                    type: orig.type,
                    is_translatable: !!orig.is_translatable,
                    $name: null,
                    $type: null,
                    $is_translatable: null,
                    deleted: true
                });
            });

            document.getElementById('fields_input').value = JSON.stringify(payload);
            document.getElementById('save-all-form').submit();
        }

        window.addEventListener('DOMContentLoaded', function () {
            initialFields.forEach(f => addField(f));

            document.getElementById('add-field-btn').addEventListener('click', function () {
                addField();
                updateSummary();
            });

            document.getElementById('save-all-btn').addEventListener('click', saveAll);

            updateSummary();
        });
    })();
</script>
