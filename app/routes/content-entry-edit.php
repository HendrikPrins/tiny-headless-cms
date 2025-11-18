<?php
$db = Database::getInstance();
$ctId = isset($_GET['ct']) ? (int)$_GET['ct'] : 0;
$entryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ctId <= 0) { echo '<h1>Content type not found</h1>'; return; }
$ct = $db->getContentType($ctId);
if (!$ct) { echo '<h1>Content type not found</h1>'; return; }
$fields = $db->getFieldsForContentType($ctId);
$isSingleton = $ct['is_singleton'];

// Get available locales
$locales = CMS_LOCALES;

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

            // Save translatable fields for all locales (process all, even if not present in POST)
            foreach ($locales as $locale) {
                $translatableValues = [];
                foreach ($fields as $f) {
                    $fid = (int)$f['id'];
                    $isTranslatable = (bool)$f['is_translatable'];
                    if (!$isTranslatable) { continue; }
                    $key = 'field_' . $fid . '_' . $locale;
                    $raw = $_POST[$key] ?? '';
                    switch ($f['field_type']) {
                        case 'integer':
                            $translatableValues[$fid] = ($raw === '') ? null : (string)(int)$raw; break;
                        case 'decimal':
                            $translatableValues[$fid] = ($raw === '') ? null : (string)(float)$raw; break;
                        case 'boolean':
                            // checkbox posts only when checked
                            $translatableValues[$fid] = isset($_POST[$key]) ? '1' : '0'; break;
                        case 'text':
                        case 'string':
                        default:
                            $translatableValues[$fid] = $raw; break;
                    }
                }
                // Save translatable values for this locale
                if (!empty($translatableValues)) {
                    $db->saveEntryValues($entryId, $translatableValues, $locale);
                }
            }

            // Save non-translatable fields with empty locale (process all, even if not present in POST)
            $nonTranslatableValues = [];
            foreach ($fields as $f) {
                $fid = (int)$f['id'];
                if (!(bool)$f['is_translatable']) {
                    $key = 'field_' . $fid;
                    $raw = $_POST[$key] ?? '';
                    switch ($f['field_type']) {
                        case 'integer':
                            $nonTranslatableValues[$fid] = ($raw === '') ? null : (string)(int)$raw; break;
                        case 'decimal':
                            $nonTranslatableValues[$fid] = ($raw === '') ? null : (string)(float)$raw; break;
                        case 'boolean':
                            $nonTranslatableValues[$fid] = isset($_POST[$key]) ? '1' : '0'; break;
                        case 'text':
                        case 'string':
                        default:
                            $nonTranslatableValues[$fid] = $raw; break;
                    }
                }
            }
            if (!empty($nonTranslatableValues)) {
                $db->saveEntryValues($entryId, $nonTranslatableValues, '');
            }

            if ($ct['is_singleton']) {
                header('Location: admin.php?page=content-type', true, 303);
            } else {
                header('Location: admin.php?page=content-entries&ct=' . $ctId, true, 303);
            }
            exit;
        }
    }
}

$entry = null;
$valuesByLocale = [];
if ($entryId > 0) {
    $entry = $db->getEntryById($entryId);
    if (!$entry || (int)$entry['content_type_id'] !== $ctId) { echo '<h1>Entry not found</h1>'; return; }

    // Load values for all locales
    foreach ($locales as $locale) {
        $valuesByLocale[$locale] = $db->getFieldValuesForEntry($entryId, $locale);
    }
    // Load non-translatable values (empty locale)
    $valuesByLocale[''] = $db->getFieldValuesForEntry($entryId, '');
}

?>
<div class="content-header">
    <nav class="breadcrumb" aria-label="breadcrumb">
        <ol>
            <li><a href="?page=content-type"><?= $isSingleton ? 'Singletons' : 'Collections' ?></a></li>
            <?php if ($ct['is_singleton']): ?>
                <li aria-current="page"><?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?></li>
            <?php else: ?>
                <li><a href="?page=content-entries&ct=<?= $ctId ?>"><?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?></a></li>
                <li aria-current="page"><?= $entryId ? 'Edit Entry' : 'New Entry' ?></li>
            <?php endif; ?>
        </ol>
    </nav>
    <h1><?= $entryId ? 'Edit' : 'Create' ?> Entry: <?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?></h1>
</div>

<!-- Multi-toggle locale/global buttons -->
<div id="locale-toggle-bar" style="display:flex; gap:8px; margin-bottom:24px; border-bottom:2px solid #ddd; padding-bottom:8px; flex-wrap:wrap;">
    <button type="button" data-locale-toggle="__global" class="locale-toggle active" style="padding:8px 14px; border:none; cursor:pointer; border-radius:4px; background:#007bff; color:#fff; font-weight:bold;">GLOBAL</button>
    <?php foreach ($locales as $loc): ?>
        <button type="button" data-locale-toggle="<?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?>" class="locale-toggle<?= $loc === $currentLocale ? ' active' : '' ?>" style="padding:8px 14px; border:none; cursor:pointer; border-radius:4px; background:<?= $loc === $currentLocale ? '#007bff' : '#f0f0f0' ?>; color:<?= $loc === $currentLocale ? '#fff' : '#333' ?>; font-weight:<?= $loc === $currentLocale ? 'bold' : 'normal' ?>;">
            <?= strtoupper(htmlspecialchars($loc, ENT_QUOTES, 'UTF-8')) ?>
        </button>
    <?php endforeach; ?>
</div>

<form method="post" style="display:flex; flex-direction:column; gap:14px; max-width:960px;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach ($fields as $f): $fid=(int)$f['id']; $name = htmlspecialchars($f['name'], ENT_QUOTES, 'UTF-8'); $ft=$f['field_type']; $isTranslatable=(bool)$f['is_translatable']; ?>
        <?php if ($isTranslatable): ?>
            <?php foreach ($locales as $loc): $val = $valuesByLocale[$loc][$fid] ?? ''; $inputName='field_'.$fid.'_'.$loc; ?>
                <label data-locale-field="<?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?>" style="display:flex; flex-direction:column; gap:6px;">
                    <span><?= $name ?><?= $f['is_required'] ? ' *' : '' ?> <span style="font-size:0.75em; color:#666; font-weight:normal;">[<?= strtoupper(htmlspecialchars($loc, ENT_QUOTES, 'UTF-8')) ?>]</span></span>
                    <?php if ($ft === 'text'): ?>
                        <textarea name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" rows="4" style="resize:vertical;"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></textarea>
                    <?php elseif ($ft === 'integer'): ?>
                        <input type="number" step="1" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                    <?php elseif ($ft === 'decimal'): ?>
                        <input type="number" step="any" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                    <?php elseif ($ft === 'boolean'): ?>
                        <input type="checkbox" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= ($val === '1') ? 'checked' : '' ?>>
                    <?php else: ?>
                        <input type="text" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        <?php else: $val = $valuesByLocale[''][$fid] ?? ''; $inputName='field_'.$fid; ?>
            <label data-locale-field="__global" style="display:flex; flex-direction:column; gap:6px;">
                <span><?= $name ?><?= $f['is_required'] ? ' *' : '' ?></span>
                <?php if ($ft === 'text'): ?>
                    <textarea name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" rows="4" style="resize:vertical;"><?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?></textarea>
                <?php elseif ($ft === 'integer'): ?>
                    <input type="number" step="1" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                <?php elseif ($ft === 'decimal'): ?>
                    <input type="number" step="any" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                <?php elseif ($ft === 'boolean'): ?>
                    <input type="checkbox" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="1" <?= ($val === '1') ? 'checked' : '' ?>>
                <?php else: ?>
                    <input type="text" name="<?= htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8') ?>" value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>
            </label>
        <?php endif; ?>
    <?php endforeach; ?>

    <div style="display:flex; gap:8px; align-items:center; margin-top:8px;">
        <button type="submit" class="btn-primary">Save</button>
        <?php if (!$ct['is_singleton']): ?>
        <a href="?page=content-entries&ct=<?= (int)$ctId ?>" class="btn-secondary">Cancel</a>
        <?php else: ?>
        <a href="?page=content-type" class="btn-secondary">Cancel</a>
        <?php endif; ?>
    </div>
</form>

<script>
    window.__entryLocales = <?= json_encode($locales, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    window.addEventListener('DOMContentLoaded', function(){
        if (typeof initEntryLocaleMultiToggle === 'function') {
            initEntryLocaleMultiToggle({
                locales: window.__entryLocales || [],
            });
        }
    });
</script>
