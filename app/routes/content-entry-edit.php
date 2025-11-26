<?php
$db = Database::getInstance();
$ctId = isset($_GET['ct']) ? (int)$_GET['ct'] : 0;
$entryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ctId <= 0) { echo '<h1>Content type not found</h1>'; return; }
$ct = $db->getContentType($ctId);
if (!$ct) { echo '<h1>Content type not found</h1>'; return; }
$title = ($entryId > 0 ? 'Edit Entry: ' : 'Create Entry: ') . htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8');
$fields = $ct['schema']['fields'];
$isSingleton = $ct['is_singleton'];

$permission = isAdmin() ? 'full-access' : $ct['editor_permission_mode'] ?? 'read-only';
echo $permission;

if ($entryId === 0 && $permission !== 'full-access') {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to delete entries.</div>';
    return;
} else if ($entryId > 0 && $permission !== 'full-access' && $permission !== 'edit-only') {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to edit entries.</div>';
    return;
}


// Get available locales
$locales = CMS_LOCALES;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        echo '<div class="alert alert-danger">Invalid request.</div>';
    } else {
        // Disallow editors from creating new entries
        if ($entryId === 0 && $permission !== 'full-access') {
            echo '<div class="alert alert-danger">You do not have permission to create entries.</div>';
        } else {
            // Singleton guard: only one entry allowed
            if ($ct['is_singleton'] && $entryId === 0 && $db->getEntryCountForContentType($ctId) > 0) {
                echo '<div class="alert alert-danger">Singleton already has an entry.</div>';
            } else {
                if ($entryId === 0) {
                    $entryId = $db->createEntry($ct);
                }

                // Save translatable fields per locale
                foreach ($locales as $locale) {
                    $translatableValues = [];
                    foreach ($fields as $f) {
                        $fieldName = $f['name'];
                        $isTranslatable = (bool)$f['is_translatable'];
                        if (!$isTranslatable) { continue; }
                        $key = 'field_' . $fieldName . '_' . $locale;
                        $fieldType = FieldRegistry::get($f['type']);
                        $translatableValues[$fieldName] = $fieldType->deserializeFromPost($_POST, $key);
                    }
                    if (!empty($translatableValues)) {
                        $db->saveEntryValues($ct, $entryId, $translatableValues, $locale);
                    }
                }

                // Save non-translatable fields with empty locale
                $nonTranslatableValues = [];
                foreach ($fields as $f) {
                    $fieldName = $f['name'];
                    if (!(bool)$f['is_translatable']) {
                        $key = 'field_' . $fieldName;
                        $fieldType = FieldRegistry::get($f['type']);
                        $nonTranslatableValues[$fieldName] = $fieldType->deserializeFromPost($_POST, $key);
                    }
                }
                if (!empty($nonTranslatableValues)) {
                    $db->saveEntryValues($ct, $entryId, $nonTranslatableValues, '');
                }

                if ($ct['is_singleton']) {
                    header('Location: index.php?page=content-type', true, 303);
                } else {
                    header('Location: index.php?page=content-entries&ct=' . $ctId, true, 303);
                }
                exit;
            }
        }
    }
}

$entry = null;
$valuesByLocale = [];
if ($entryId > 0) {
    $entry = $db->getEntryById($ct, $entryId);
    if (empty($entry)) {
        echo '<h1>Entry not found</h1>';
        return;
    }

    // Load values for all locales
    foreach ($locales as $locale) {
        $valuesByLocale[$locale] = $db->getFieldValuesForEntry($ct, $entryId, $locale);
    }
    // Load non-translatable values (empty locale)
    $valuesByLocale[''] = $db->getFieldValuesForEntry($ct, $entryId, '');
}

foreach (FieldRegistry::getAll() as $ft) {
    echo $ft->renderAdminImports();
}
?>
<script src="/assets/image-asset-picker.js"></script>
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


<div id="locale-toggle-bar" class="field-locale-toggle-bar">
    Show: <button type="button" data-locale-toggle="__global" class="btn">GLOBAL</button>
    <?php foreach ($locales as $loc): ?>
        <button type="button" data-locale-toggle="<?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?>" class="btn">
            <?= strtoupper(htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ) ?>
        </button>
    <?php endforeach; ?>
</div>

<form method="post" class="form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

    <?php foreach ($fields as $f): $fieldName = $f['name']; $name = htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8'); $ft=$f['type']; $isTranslatable=(bool)$f['is_translatable']; ?>
        <?php if ($isTranslatable): ?>
            <?php foreach ($locales as $loc): $val = $valuesByLocale[$loc][$fieldName] ?? ''; $inputName='field_'.$fieldName.'_'.$loc; $fieldType = FieldRegistry::get($ft); $wrap = $fieldType->shouldWrapWithLabel(); ?>
                <<?= $wrap ? 'label' : 'div' ?> class="field" data-locale-field="<?= htmlspecialchars($loc, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="label"><span><?= $name ?></span> <span class="field-locale">[<?= strtoupper(htmlspecialchars($loc, ENT_QUOTES, 'UTF-8')) ?>]</span><span><?= $ft ?></span></div>
                    <?php
                    echo $fieldType->renderAdminForm(htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8'), $val);
                    ?>
                </<?= $wrap ? 'label' : 'div' ?>>
            <?php endforeach; ?>
        <?php else: $val = $valuesByLocale[''][$fieldName] ?? ''; $inputName='field_'.$fieldName; $fieldType = FieldRegistry::get($ft); $wrap = $fieldType->shouldWrapWithLabel(); ?>
            <<?= $wrap ? 'label' : 'div' ?> class="field" data-locale-field="__global">
                <div class="label"><span><?= $name ?></span><span><?= $ft ?></span></div>
                <?php
                echo $fieldType->renderAdminForm(htmlspecialchars($inputName, ENT_QUOTES, 'UTF-8'), $val);
                ?>
            </<?= $wrap ? 'label' : 'div' ?>>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="form-buttons">
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
