<?php
class AssetsFieldType extends FieldType {
    public function __construct() {
        parent::__construct('assets');
    }

    public function saveToDb(mixed $value): string {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string)$value;
    }

    public function readFromDb(string $raw): mixed {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function serializeToJson(mixed $value): mixed {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $value ?? [];
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        $raw = $postData[$fieldName] ?? '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }
        $items = [];
        foreach ($value as $v) {
            $items[] = [
                'assetId' => $v['assetId'] ?? null,
                'url' => $v['url'] ?? '',
                'filename' => $v['filename'] ?? '',
            ];
        }
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fieldIdBase = 'assets_' . md5($fieldName);
        $hiddenId = $fieldIdBase . '_hidden';
        $buttonId = $fieldIdBase . '_btn';
        $listId = $fieldIdBase . '_list';

        ob_start();
        ?>
        <div class="assets-field" data-assets-field>
            <input type="hidden"
                   name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                   id="<?= htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') ?>"
                   value='<?= htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8') ?>'>
            <button type="button"
                    class="btn-secondary assets-field-pick-btn"
                    id="<?= htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8') ?>">
                Add Assets
            </button>
            <div class="assets-field-list" id="<?= htmlspecialchars($listId, ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
        <script>
        (function(){
            const hiddenId = <?= json_encode($hiddenId) ?>;
            const buttonId = <?= json_encode($buttonId) ?>;
            const listId = <?= json_encode($listId) ?>;
            const hiddenInput = document.getElementById(hiddenId);
            const button = document.getElementById(buttonId);
            const listEl = document.getElementById(listId);
            if (!hiddenInput || !button || !listEl) return;

            function parseValue(){ try { return hiddenInput.value ? JSON.parse(hiddenInput.value) : []; } catch(e) { return []; } }
            function escapeHtml(str){ return (str || '').replace(/[&<>"']/g, function(c){ return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c] || c; }); }
            function save(items){ hiddenInput.value = JSON.stringify(items || []); }
            function render(items){
                listEl.innerHTML = '';
                if (!items.length){ listEl.innerHTML = '<span style="color:#888; font-size:0.9em;">No assets selected.</span>'; return; }
                items.forEach(function(item, idx){
                    const row = document.createElement('div');
                    row.className = 'assets-field-item';
                    row.dataset.index = String(idx);
                    const name = item.filename || (item.url||'').split('/').pop() || '';
                    row.innerHTML = '<div class="assets-field-name"><small>' + escapeHtml(name) + '</small></div>'
                        + '<div class="assets-field-controls">'
                        +   '<button type="button" class="btn-primary btn-icon assets-move-up" title="Move up">&#9650;</button>'
                        +   '<button type="button" class="btn-primary btn-icon assets-move-down" title="Move down">&#9660;</button>'
                        +   '<button type="button" class="btn-danger btn-icon assets-remove" title="Remove">&times;</button>'
                        + '</div>';
                    listEl.appendChild(row);
                });
            }
            function openPicker(){
                if (!window.CMSImageAssetPicker || !window.CMSImageAssetPicker.openPicker) return;
                const currentItems = parseValue();
                window.CMSImageAssetPicker.openPicker(function(selected){
                    const items = parseValue();
                    const addItems = Array.isArray(selected) ? selected : [selected];
                    addItems.forEach(function(asset){
                        if (!asset || !asset.url) return;
                        items.push({ assetId: asset.id || null, url: asset.url || '', filename: asset.filename || '' });
                    });
                    save(items);
                    render(items);
                }, { manual: true, sourceButton: button, multiple: true });
            }
            render(parseValue());
            button.addEventListener('click', function(){ openPicker(); });
            listEl.addEventListener('click', function(e){
                const row = e.target.closest('.assets-field-item');
                if (!row) return;
                const idx = parseInt(row.dataset.index, 10);
                let items = parseValue();
                if (Number.isNaN(idx) || idx < 0 || idx >= items.length) return;
                if (e.target.classList.contains('assets-remove')) {
                    items.splice(idx, 1);
                } else if (e.target.classList.contains('assets-move-up')) {
                    if (idx > 0) { const tmp = items[idx-1]; items[idx-1] = items[idx]; items[idx] = tmp; }
                } else if (e.target.classList.contains('assets-move-down')) {
                    if (idx < items.length - 1) { const tmp = items[idx+1]; items[idx+1] = items[idx]; items[idx] = tmp; }
                } else {
                    return;
                }
                save(items);
                render(items);
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value) || empty($value)) {
            return '<span style="color:#999;">-</span>';
        }
        $names = [];
        foreach ($value as $v) {
            if (empty($v['url'])) continue;
            $names[] = htmlspecialchars($v['filename'] ?? basename($v['url']), ENT_QUOTES, 'UTF-8');
            if (count($names) >= 3) break;
        }
        if (!$names) {
            return '<span style="color:#999;">-</span>';
        }
        $out = implode(', ', $names);
        if (count($value) > count($names)) {
            $out .= ' +'.(count($value)-count($names));
        }
        return '<span>' . $out . '</span>';
    }
}

