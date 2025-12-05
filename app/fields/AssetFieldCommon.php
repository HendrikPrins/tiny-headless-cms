<?php
trait AssetFieldCommon {

    public function getSqlType(): string {
        return 'TEXT';
    }

    public function saveToDb(mixed $value): mixed {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string)$value;
    }

    public function serializeToJson(mixed $value): mixed {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        if ($value === null && ($this instanceof ImagesFieldType || $this instanceof AssetsFieldType)) {
            return [];
        }
        return $value;
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        $raw = $postData[$fieldName] ?? '';
        if ($raw === '') {
            return $this instanceof ImagesFieldType || $this instanceof AssetsFieldType ? [] : null;
        }
        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return $this instanceof ImagesFieldType || $this instanceof AssetsFieldType ? [] : null;
        }
        return $decoded;
    }

    protected function isMulti(): bool {
        return $this instanceof ImagesFieldType || $this instanceof AssetsFieldType;
    }

    protected function supportsAlt(): bool {
        return $this instanceof ImageFieldType || $this instanceof ImagesFieldType;
    }

    protected function labelForSingle(): string {
        if ($this instanceof ImageFieldType) return 'Image';
        if ($this instanceof AssetFieldType) return 'Asset';
        return 'Item';
    }

    protected function labelForMulti(): string {
        if ($this instanceof ImagesFieldType) return 'Images';
        if ($this instanceof AssetsFieldType) return 'Assets';
        return 'Items';
    }

    protected function normalizeSingle(mixed $value): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $value = null;
            }
        }
        if (!is_array($value)) {
            $value = null;
        }
        $data = [
            'assetId' => $value['assetId'] ?? null,
            'url' => $value['url'] ?? '',
            'filename' => $value['filename'] ?? '',
        ];
        if ($this->supportsAlt()) {
            $data['alt'] = $value['alt'] ?? '';
        }
        return $data;
    }

    protected function normalizeMulti(mixed $value): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }
        $items = [];
        foreach ($value as $v) {
            $item = [
                'assetId' => $v['assetId'] ?? null,
                'url' => $v['url'] ?? '',
                'filename' => $v['filename'] ?? '',
            ];
            if ($this->supportsAlt()) {
                $item['alt'] = $v['alt'] ?? '';
            }
            $items[] = $item;
        }
        return $items;
    }

    protected function renderSingleAssetAdmin(string $fieldName, mixed $value, string $baseIdPrefix, string $buttonLabelSelect, string $buttonLabelChange, ?string $defaultFilter = null, bool $showPreviewImage = false, bool $showAltInput = false): string {
        $data = $this->normalizeSingle($value);
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fieldIdBase = $baseIdPrefix . '_' . md5($fieldName);
        $hiddenId = $fieldIdBase . '_hidden';
        $buttonId = $fieldIdBase . '_btn';
        $clearId = $fieldIdBase . '_clear';
        $previewId = $fieldIdBase . '_preview';
        $altId = $fieldIdBase . '_alt';

        ob_start();
        ?>
        <div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field" data-<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field>
            <input type="hidden"
                   name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                   id="<?= htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') ?>"
                   value='<?= htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8') ?>'>
            <button type="button"
                    class="btn-secondary <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-pick-btn"
                    id="<?= htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8') ?>">
                <?= $data['url'] ? $buttonLabelChange : $buttonLabelSelect ?>
            </button>
            <button type="button"
                    class="btn-secondary <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-clear-btn"
                    id="<?= htmlspecialchars($clearId, ENT_QUOTES, 'UTF-8') ?>">
                Clear
            </button>
            <div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-preview" id="<?= htmlspecialchars($previewId, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($data['url']): ?>
                    <div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-preview-inner">
                        <?php if ($showPreviewImage): ?>
                            <img src="<?= htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($data['alt'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                 class="asset-single-preview-img">
                        <?php endif; ?>
                        <div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-meta">
                            <small><?= htmlspecialchars($data['filename'] ?: basename($data['url']), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-placeholder asset-field-placeholder">No <?= $this->labelForSingle() ?> selected.</span>
                <?php endif; ?>
            </div>
            <?php if ($showAltInput): ?>
            <div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-alt-wrapper asset-alt-wrapper">
                <label class="asset-alt-label">
                    Alt text
                    <input type="text"
                           id="<?= htmlspecialchars($altId, ENT_QUOTES, 'UTF-8') ?>"
                           class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-alt-input asset-alt-input"
                           value="<?= htmlspecialchars($data['alt'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </label>
            </div>
            <?php endif; ?>
        </div>
        <script>
        (function() {
            const hiddenId = <?= json_encode($hiddenId) ?>;
            const buttonId = <?= json_encode($buttonId) ?>;
            const clearId = <?= json_encode($clearId) ?>;
            const previewId = <?= json_encode($previewId) ?>;
            const altId = <?= json_encode($altId) ?>;
            const hiddenInput = document.getElementById(hiddenId);
            const button = document.getElementById(buttonId);
            const clearBtn = document.getElementById(clearId);
            const preview = document.getElementById(previewId);
            const altInput = <?= $showAltInput ? 'document.getElementById(altId)' : 'null' ?>;
            if (!hiddenInput || !button || !preview) return;

            function parseValue() {
                try { return hiddenInput.value ? JSON.parse(hiddenInput.value) : {}; } catch (e) { return {}; }
            }
            function escapeHtml(str) {
                return (str || '').replace(/[&<>"']/g, function(c){
                    return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c] || c;
                });
            }
            function updateHidden(data) {
                hiddenInput.value = JSON.stringify(data || {});
            }
            function updatePreview(data) {
                if (!data || !data.url) {
                    preview.innerHTML = '<span class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-placeholder asset-field-placeholder">No <?= $this->labelForSingle() ?> selected.</span>';
                    button.textContent = <?= json_encode($buttonLabelSelect) ?>;
                    if (clearBtn) clearBtn.disabled = true;
                    return;
                }
                const filename = data.filename || (function(){ try { return data.url.split('/').pop(); } catch(e){ return ''; } })();
                const alt = data.alt || '';
                let html = '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-preview-inner">';
                <?php if ($showPreviewImage): ?>
                html += '<img src="' + escapeHtml(data.url) + '" alt="' + escapeHtml(alt) + '" class="asset-single-preview-img">';
                <?php endif; ?>
                html += '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-meta"><small>' + escapeHtml(filename) + '</small></div>';
                html += '</div>';
                preview.innerHTML = html;
                button.textContent = <?= json_encode($buttonLabelChange) ?>;
                if (clearBtn) clearBtn.disabled = false;
            }
            function openPicker() {
                if (!window.CMSImageAssetPicker || !window.CMSImageAssetPicker.openPicker) return;
                window.CMSImageAssetPicker.openPicker(function(asset) {
                    const current = parseValue();
                    const data = {
                        assetId: asset.id || null,
                        url: asset.url || '',
                        filename: asset.filename || ''
                    };
                    if (altInput) {
                        data.alt = current.alt || '';
                    }
                    updateHidden(data);
                    if (altInput) altInput.value = data.alt || '';
                    updatePreview(data);
                }, { manual: true, sourceButton: button<?= $defaultFilter ? ', defaultFilter: ' . json_encode($defaultFilter) : '' ?> });
            }
            function clearSelection() {
                // clear to empty state
                hiddenInput.value = '';
                if (altInput) altInput.value = '';
                updatePreview({});
            }

            const initial = parseValue();
            if (altInput && initial.alt) {
                altInput.value = initial.alt;
            }
            updatePreview(initial);
            if (clearBtn) {
                clearBtn.addEventListener('click', function(){ clearSelection(); });
            }
            button.addEventListener('mousedown', function(e){ if (e.button !== 0 || e.target !== button) return; openPicker(); });
            button.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openPicker(); }});
            if (altInput) {
                altInput.addEventListener('input', function(){
                    const data = parseValue();
                    data.alt = altInput.value || '';
                    updateHidden(data);
                    updatePreview(data);
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    protected function renderMultiAssetAdmin(string $fieldName, mixed $value, string $baseIdPrefix, string $buttonLabel, ?string $defaultFilter = null, bool $supportsAlt = false, bool $sortable = true): string {
        $items = $this->normalizeMulti($value);
        $jsonData = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fieldIdBase = $baseIdPrefix . '_' . md5($fieldName);
        $hiddenId = $fieldIdBase . '_hidden';
        $buttonId = $fieldIdBase . '_btn';
        $clearAllId = $fieldIdBase . '_clear';
        $listId = $fieldIdBase . '_list';

        ob_start();
        ?>
        <div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field" data-<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field>
            <input type="hidden"
                   name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                   id="<?= htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') ?>"
                   value='<?= htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8') ?>'>
            <button type="button"
                    class="btn-secondary <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-pick-btn"
                    id="<?= htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8') ?>">
                <?= $buttonLabel ?>
            </button>
            <button type="button"
                    class="btn-secondary <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-clear-all-btn"
                    id="<?= htmlspecialchars($clearAllId, ENT_QUOTES, 'UTF-8') ?>">
                Clear all
            </button>
            <div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-list asset-multi-list" id="<?= htmlspecialchars($listId, ENT_QUOTES, 'UTF-8') ?>"></div>
        </div>
        <script>
        (function(){
            const hiddenId = <?= json_encode($hiddenId) ?>;
            const buttonId = <?= json_encode($buttonId) ?>;
            const clearAllId = <?= json_encode($clearAllId) ?>;
            const listId = <?= json_encode($listId) ?>;
            const hiddenInput = document.getElementById(hiddenId);
            const button = document.getElementById(buttonId);
            const clearAllBtn = document.getElementById(clearAllId);
            const listEl = document.getElementById(listId);
            if (!hiddenInput || !button || !listEl) return;

            function parseValue(){ try { return hiddenInput.value ? JSON.parse(hiddenInput.value) : []; } catch(e) { return []; } }
            function escapeHtml(str){ return (str || '').replace(/[&<>"']/g, function(c){ return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c] || c; }); }
            function save(items){ hiddenInput.value = JSON.stringify(items || []); }
            function render(items){
                listEl.innerHTML = '';
                if (!items.length){ listEl.innerHTML = '<span class="asset-field-placeholder">No <?= $this->labelForMulti() ?> selected.</span>'; if (clearAllBtn) clearAllBtn.disabled = true; return; }
                if (clearAllBtn) clearAllBtn.disabled = false;
                items.forEach(function(item, idx){
                    const row = document.createElement('div');
                    row.className = '<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-item asset-multi-item';
                    row.dataset.index = String(idx);
                    <?php if ($supportsAlt): ?>
                    const thumb = item.url ? '<img src="' + escapeHtml(item.url) + '" alt="" class="asset-multi-thumb">' : '';
                    const altVal = item.alt || '';
                    row.innerHTML = '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-thumb">'+ thumb +'</div>'
                        + '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-body">'
                        +   '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-filename"><small>' + escapeHtml(item.filename || (item.url||'').split('/').pop() || '') + '</small></div>'
                        +   '<label class="asset-alt-label">Alt text'
                        +     '<input type="text" class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-alt asset-alt-input" value="' + escapeHtml(altVal) + '">'
                        +   '</label>'
                        + '</div>'
                        + '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-controls asset-multi-controls">'
                        <?php if ($sortable): ?>
                        +   '<button type="button" class="btn-primary btn-icon <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-move-up" title="Move up"><?= ICON_CHEVRON_UP ?></button>'
                        +   '<button type="button" class="btn-primary btn-icon <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-move-down" title="Move down"><?= ICON_CHEVRON_DOWN ?></button>'
                        <?php endif; ?>
                        +   '<button type="button" class="btn-danger btn-icon <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-remove" title="Remove"><?= ICON_TRASH ?></button>'
                        + '</div>';
                    <?php else: ?>
                    const name = item.filename || (item.url||'').split('/').pop() || '';
                    row.innerHTML = '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-name"><small>' + escapeHtml(name) + '</small></div>'
                        + '<div class="<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-controls asset-multi-controls">'
                        <?php if ($sortable): ?>
                        +   '<button type="button" class="btn-primary btn-icon <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-move-up" title="Move up"><?= ICON_CHEVRON_UP ?></button>'
                        +   '<button type="button" class="btn-primary btn-icon <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-move-down" title="Move down"><?= ICON_CHEVRON_DOWN ?></button>'
                        <?php endif; ?>
                        +   '<button type="button" class="btn-danger btn-icon <?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-remove" title="Remove"><?= ICON_TRASH ?></button>'
                        + '</div>';
                    <?php endif; ?>
                    listEl.appendChild(row);
                });
            }
            function openPicker(){
                if (!window.CMSImageAssetPicker || !window.CMSImageAssetPicker.openPicker) return;
                window.CMSImageAssetPicker.openPicker(function(selected){
                    let items = parseValue();
                    const addItems = Array.isArray(selected) ? selected : [selected];
                    addItems.forEach(function(asset){
                        if (!asset || !asset.url) return;
                        const obj = {
                            assetId: asset.id || null,
                            url: asset.url || '',
                            filename: asset.filename || ''
                        };
                        <?php if ($supportsAlt): ?>
                        obj.alt = '';
                        <?php endif; ?>
                        items.push(obj);
                    });
                    save(items);
                    render(items);
                }, { manual: true, sourceButton: button, multiple: true<?= $defaultFilter ? ', defaultFilter: ' . json_encode($defaultFilter) : '' ?> });
            }
            render(parseValue());
            button.addEventListener('click', function(){ openPicker(); });
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function(){
                    save([]);
                    render([]);
                });
            }
            listEl.addEventListener('click', function(e){
                const row = e.target.closest('.<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-item');
                if (!row) return;

                const btn = e.target.closest('button');
                if (!btn || !row.contains(btn)) return;

                const idx = parseInt(row.dataset.index, 10);
                let items = parseValue();
                if (Number.isNaN(idx) || idx < 0 || idx >= items.length) return;

                if (btn.classList.contains('<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-remove')) {
                    items.splice(idx, 1);
                <?php if ($sortable): ?>
                } else if (btn.classList.contains('<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-move-up')) {
                    if (idx > 0) { const tmp = items[idx-1]; items[idx-1] = items[idx]; items[idx] = tmp; }
                } else if (btn.classList.contains('<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-move-down')) {
                    if (idx < items.length - 1) { const tmp = items[idx+1]; items[idx+1] = items[idx]; items[idx] = tmp; }
                <?php endif; ?>
                } else {
                    return;
                }
                save(items);
                render(items);
            });
            <?php if ($supportsAlt): ?>
            listEl.addEventListener('input', function(e){
                if (!e.target.classList.contains('<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-alt')) return;
                const row = e.target.closest('.<?= htmlspecialchars($baseIdPrefix, ENT_QUOTES, 'UTF-8') ?>-field-item');
                if (!row) return;
                const idx = parseInt(row.dataset.index, 10);
                let items = parseValue();
                if (Number.isNaN(idx) || idx < 0 || idx >= items.length) return;
                items[idx].alt = e.target.value || '';
                save(items);
            });
            <?php endif; ?>
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    protected function renderSinglePreview(mixed $value, bool $showImage): string {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($value) || empty($value['url'])) {
            return '<span class="asset-field-placeholder">-</span>';
        }
        if ($showImage) {
            $url = htmlspecialchars($value['url'], ENT_QUOTES, 'UTF-8');
            $alt = htmlspecialchars($value['alt'] ?? '', ENT_QUOTES, 'UTF-8');
            return '<img src="' . $url . '" alt="' . $alt . '" class="asset-preview-img">';
        }
        $name = htmlspecialchars($value['filename'] ?? basename($value['url']), ENT_QUOTES, 'UTF-8');
        return '<span>' . $name . '</span>';
    }

    protected function renderMultiPreview(mixed $value, bool $showImages, int $maxItems = 5): string {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value) || empty($value)) {
            return '<span class="asset-field-placeholder">-</span>';
        }
        if ($showImages) {
            $parts = [];
            foreach ($value as $v) {
                if (empty($v['url'])) continue;
                $url = htmlspecialchars($v['url'], ENT_QUOTES, 'UTF-8');
                $alt = htmlspecialchars($v['alt'] ?? '', ENT_QUOTES, 'UTF-8');
                $parts[] = '<img src="' . $url . '" alt="' . $alt . '" class="asset-preview-img asset-preview-img-small">';
                if (count($parts) >= $maxItems) break;
            }
            if (!$parts) {
                return '<span class="asset-field-placeholder">-</span>';
            }
            return implode('', $parts);
        }
        $names = [];
        foreach ($value as $v) {
            if (empty($v['url'])) continue;
            $names[] = htmlspecialchars($v['filename'] ?? basename($v['url']), ENT_QUOTES, 'UTF-8');
            if (count($names) >= $maxItems) break;
        }
        if (!$names) {
            return '<span class="asset-field-placeholder">-</span>';
        }
        $out = implode(', ', $names);
        if (count($value) > count($names)) {
            $out .= ' +' . (count($value) - count($names));
        }
        return '<span>' . $out . '</span>';
    }
}
