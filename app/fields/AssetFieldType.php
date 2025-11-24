<?php
class AssetFieldType extends FieldType {
    public function __construct() {
        parent::__construct('asset');
    }

    public function saveToDb(mixed $value): string {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
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
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($value)) {
            $value = null;
        }
        $data = [
            'assetId' => $value['assetId'] ?? null,
            'url' => $value['url'] ?? '',
            'filename' => $value['filename'] ?? '',
        ];
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $fieldIdBase = 'asset_' . md5($fieldName);
        $hiddenId = $fieldIdBase . '_hidden';
        $buttonId = $fieldIdBase . '_btn';
        $previewId = $fieldIdBase . '_preview';

        ob_start();
        ?>
        <div class="asset-field" data-asset-field>
            <input type="hidden"
                   name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>"
                   id="<?= htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') ?>"
                   value='<?= htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8') ?>'>
            <button type="button"
                    class="btn-secondary asset-field-pick-btn"
                    id="<?= htmlspecialchars($buttonId, ENT_QUOTES, 'UTF-8') ?>">
                <?= $data['url'] ? 'Change Asset' : 'Select Asset' ?>
            </button>
            <div class="asset-field-preview" id="<?= htmlspecialchars($previewId, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($data['url']): ?>
                    <div class="asset-field-preview-inner">
                        <span class="asset-field-name"><small><?= htmlspecialchars($data['filename'] ?: basename($data['url']), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </div>
                <?php else: ?>
                    <span class="asset-field-placeholder" style="color:#888; font-size:0.9em;">No asset selected.</span>
                <?php endif; ?>
            </div>
        </div>
        <script>
        (function(){
            const hiddenId = <?= json_encode($hiddenId) ?>;
            const buttonId = <?= json_encode($buttonId) ?>;
            const previewId = <?= json_encode($previewId) ?>;
            const hiddenInput = document.getElementById(hiddenId);
            const button = document.getElementById(buttonId);
            const preview = document.getElementById(previewId);
            if (!hiddenInput || !button || !preview) return;

            function parseValue(){ try { return hiddenInput.value ? JSON.parse(hiddenInput.value) : {}; } catch(e) { return {}; } }
            function escapeHtml(str){ return (str || '').replace(/[&<>"']/g, function(c){ return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","'":"&#039;"}[c] || c; }); }
            function updateHidden(data){ hiddenInput.value = JSON.stringify(data || {}); }
            function updatePreview(data){
                if (!data || !data.url) {
                    preview.innerHTML = '<span class="asset-field-placeholder" style="color:#888; font-size:0.9em;">No asset selected.</span>';
                    button.textContent = 'Select Asset';
                    return;
                }
                const name = data.filename || (function(){ try { return data.url.split('/').pop(); } catch(e){ return ''; } })();
                preview.innerHTML = '<div class="asset-field-preview-inner"><span class="asset-field-name"><small>' + escapeHtml(name) + '</small></span></div>';
                button.textContent = 'Change Asset';
            }
            function openPicker(){
                if (!window.CMSImageAssetPicker || !window.CMSImageAssetPicker.openPicker) return;
                window.CMSImageAssetPicker.openPicker(function(asset){
                    const data = { assetId: asset.id || null, url: asset.url || '', filename: asset.filename || '' };
                    updateHidden(data);
                    updatePreview(data);
                }, { manual: true, sourceButton: button });
            }
            updatePreview(parseValue());
            button.addEventListener('mousedown', function(e){ if (e.button !== 0 || e.target !== button) return; openPicker(); });
            button.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' '){ e.preventDefault(); openPicker(); }});
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }
        if (!is_array($value) || empty($value['url'])) {
            return '<span style="color:#999;">-</span>';
        }
        $name = htmlspecialchars($value['filename'] ?? basename($value['url']), ENT_QUOTES, 'UTF-8');
        return '<span>' . $name . '</span>';
    }
}

