<?php
class ImageFieldType extends FieldType {
    public function __construct() {
        parent::__construct('image');
    }

    public function saveToDb(mixed $value): string {
        // Store as JSON string; allow empty
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        // Assume already JSON or scalar string
        return (string)$value;
    }

    public function readFromDb(string $raw): mixed {
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return $decoded === null ? null : $decoded;
    }

    public function serializeToJson(mixed $value): mixed {
        // For API output, keep structured data (array) if possible
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        return $value;
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        $raw = $postData[$fieldName] ?? '';
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        return $decoded === null ? null : $decoded;
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        // $value may be array (decoded), JSON string, or null.
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
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
            'alt' => $value['alt'] ?? '',
        ];

        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fieldIdBase = 'image_' . md5($fieldName);
        $hiddenId = $fieldIdBase . '_hidden';
        $buttonId = $fieldIdBase . '_btn';
        $previewId = $fieldIdBase . '_preview';
        $altId = $fieldIdBase . '_alt';

        ob_start();
        ?>
        <div class="image-field" data-image-field>
            <input type="hidden"
                   name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                   id="<?= htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') ?>"
                   value='<?= htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8') ?>'>
            <button type="button"
                    class="btn-secondary image-field-pick-btn"
                    id="<?= htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8') ?>">
                <?= $data['url'] ? 'Change Image' : 'Select Image' ?>
            </button>
            <div class="image-field-preview" id="<?= htmlspecialchars($previewId, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($data['url']): ?>
                    <div class="image-field-preview-inner">
                        <img src="<?= htmlspecialchars($data['url'], ENT_QUOTES, 'UTF-8') ?>"
                             alt="<?= htmlspecialchars($data['alt'], ENT_QUOTES, 'UTF-8') ?>"
                             style="max-width:150px; max-height:150px; display:block; object-fit:cover; border-radius:4px;">
                        <div class="image-field-meta">
                            <small><?= htmlspecialchars($data['filename'] ?: basename($data['url']), ENT_QUOTES, 'UTF-8') ?></small>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="image-field-placeholder" style="color:#888; font-size:0.9em;">No image selected.</span>
                <?php endif; ?>
            </div>
            <div class="image-field-alt-wrapper" style="margin-top:6px;">
                <label style="font-size:0.85em; color:#555; display:block;">
                    Alt text
                    <input type="text"
                           id="<?= htmlspecialchars($altId, ENT_QUOTES, 'UTF-8') ?>"
                           class="image-field-alt-input"
                           style="display:block; width:100%; max-width:320px; margin-top:2px;"
                           value="<?= htmlspecialchars($data['alt'], ENT_QUOTES, 'UTF-8') ?>">
                </label>
            </div>
        </div>
        <script>
        (function() {
            const hiddenId = <?= json_encode($hiddenId) ?>;
            const buttonId = <?= json_encode($buttonId) ?>;
            const previewId = <?= json_encode($previewId) ?>;
            const altId = <?= json_encode($altId) ?>;
            const hiddenInput = document.getElementById(hiddenId);
            const button = document.getElementById(buttonId);
            const preview = document.getElementById(previewId);
            const altInput = document.getElementById(altId);
            if (!hiddenInput || !button || !preview || !altInput) return;

            function parseValue() {
                try {
                    return hiddenInput.value ? JSON.parse(hiddenInput.value) : {};
                } catch (e) {
                    return {};
                }
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
                    preview.innerHTML = '<span class="image-field-placeholder" style="color:#888; font-size:0.9em;">No image selected.</span>';
                    button.textContent = 'Select Image';
                    return;
                }
                const filename = data.filename || (function(){ try { return data.url.split('/').pop(); } catch(e){ return ''; } })();
                const alt = data.alt || '';
                preview.innerHTML = '<div class="image-field-preview-inner">'
                    + '<img src="' + escapeHtml(data.url) + '" alt="' + escapeHtml(alt) + '" style="max-width:150px; max-height:150px; display:block; object-fit:cover; border-radius:4px;">'
                    + '<div class="image-field-meta"><small>' + escapeHtml(filename) + '</small></div>'
                    + '</div>';
                button.textContent = 'Change Image';
            }

            function openPicker() {
                if (!window.CMSImageAssetPicker || !window.CMSImageAssetPicker.openPicker) {
                    console.error('CMSImageAssetPicker is not available');
                    return;
                }
                window.CMSImageAssetPicker.openPicker(function(asset) {
                    const current = parseValue();
                    const data = {
                        assetId: asset.id || null,
                        url: asset.url || '',
                        filename: asset.filename || '',
                        alt: current.alt || '',
                    };
                    updateHidden(data);
                    altInput.value = data.alt || '';
                    updatePreview(data);
                }, { manual: true, sourceButton: button, defaultFilter: 'images' });
            }

            // Initialize preview and alt from current value
            const initial = parseValue();
            if (initial.alt) {
                altInput.value = initial.alt;
            }
            updatePreview(initial);

            button.addEventListener('mousedown', function(e) {
                if (e.button !== 0) return; // only left click
                if (e.target !== button) return;
                openPicker();
            });
            button.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openPicker();
                }
            });

            altInput.addEventListener('input', function() {
                const data = parseValue();
                data.alt = altInput.value || '';
                updateHidden(data);
                updatePreview(data);
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        // Preview for list views
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                $value = $decoded;
            } else {
                $value = null;
            }
        }
        if (!is_array($value) || empty($value['url'])) {
            return '<span style="color:#999;">-</span>';
        }
        $url = htmlspecialchars($value['url'], ENT_QUOTES, 'UTF-8');
        $alt = htmlspecialchars($value['alt'] ?? '', ENT_QUOTES, 'UTF-8');
        return '<img src="' . $url . '" alt="' . $alt . '" style="max-width:80px; max-height:80px; object-fit:cover; border-radius:3px;">';
    }
}
