<?php
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
                $errors[] = ($e->getCode() === '23000') ? 'A content type with that name already exists.' : 'Failed to update name.';
            }
        } elseif ($action === 'save_all') {
            $fieldsJson = $_POST['fields_json'] ?? '';
            $decoded = json_decode($fieldsJson, true);
            if (!is_array($decoded)) {
                $errors[] = 'Invalid payload.';
            } else {
                try {


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
        }
    }
}

$fields = $contentType['schema']['fields'];
$jsFields = array_map(function ($f) {
    return [
            'id' => (int)$f['id'],
            'name' => $f['name'],
            'type' => $f['type'],
            'is_translatable' => (bool)$f['is_translatable']
    ];
}, $fields);

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

<form method="post" class="form form-inline">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="action" value="rename">
    <label for="new_name">Content Type Name</label>
    <input type="text" id="new_name" name="new_name" value="<?= htmlspecialchars($contentType['name'], ENT_QUOTES, 'UTF-8') ?>" maxlength="255" required style="min-width:260px;">
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
        <input type="hidden" name="fields_json" id="fields_json_input">
        <div class="form-buttons">
            <button type="button" id="save-all-btn" class="btn-primary">Save All</button>
            <button type="button" id="add-field-btn" class="btn-primary">Add Field</button>
            <a href="?page=content-type" class="btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<hr style="margin: 32px 0; border:none; border-top:1px solid #ccc;">

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
        const deletedFields = new Set();

        function getCurrentState() {
            const tableBody = document.querySelector('#fieldTable tbody');
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            return rows.map((row, idx) => {
                const id = row.getAttribute('data-field-id');
                return {
                    id: id && id !== 'null' ? parseInt(id) : 0,
                    name: row.querySelector('[data-column="field_name"]').value.trim(),
                    type: row.querySelector('[data-column="type"]').value,
                    is_translatable: row.querySelector('[data-column="is_translatable"]').checked,
                    order: idx
                };
            });
        }

        function findOriginal(id) {
            return initialFields.find(f => f.id === id);
        }

        function detectChanges() {
            const current = getCurrentState();
            const changes = [];

            // Check for deleted fields
            deletedFields.forEach(id => {
                const orig = findOriginal(id);
                if (orig) {
                    changes.push({
                        type: 'deleted',
                        id: id,
                        name: orig.name
                    });
                }
            });

            // Check current fields for changes
            current.forEach((field, idx) => {
                if (field.id > 0) {
                    const orig = findOriginal(field.id);
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
                            id: field.id,
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
                    item.innerHTML = `<strong>🗑️ Deleted:</strong> ${escapeHtml(change.name)} <button type="button" class="btn-secondary" style="margin-left:8px; padding:2px 8px; font-size:0.85em;" data-undelete="${change.id}">Undo Delete</button>`;
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
                    const id = parseInt(this.getAttribute('data-undelete'));
                    undeleteField(id);
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

            row.setAttribute("data-field-id", field.id || null);
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
                const id = row.getAttribute('data-field-id');
                if (id && id !== 'null') {
                    deletedFields.add(parseInt(id));
                }
                row.remove();
                updateSummary();
            });

            tableBody.appendChild(row);
        }

        function undeleteField(id) {
            deletedFields.delete(id);
            const orig = findOriginal(id);
            if (orig) {
                addField(orig);
            }
            updateSummary();
        }

        function saveAll() {
            const current = getCurrentState();
            const payload = [];

            // Add deleted fields to payload
            deletedFields.forEach(id => {
                payload.push({
                    id: id,
                    deleted: 1
                });
            });

            current.forEach((field, idx) => {
                payload.push({
                    name: field.name,
                    type: field.type,
                    is_translatable: field.is_translatable ? 1 : 0,
                    deleted: 0
                });
            });

            document.getElementById('fields_json_input').value = JSON.stringify(payload);
            document.getElementById('save-all-form').submit();
        }

        window.addEventListener('DOMContentLoaded', function () {
            initialFields.forEach(f => addField(f));

            document.getElementById("add-field-btn").addEventListener("click", function () {
                addField();
                updateSummary();
            });

            document.getElementById("save-all-btn").addEventListener("click", saveAll);

            updateSummary();
        });
    })();
</script>
