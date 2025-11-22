<?php
class RichTextFieldType extends FieldType {
    public function __construct() {
        parent::__construct('richtext');
    }

    public function saveToDb(mixed $value): string {
        // Store as JSON string
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        // Assume already JSON or empty
        return (string)$value;
    }

    public function readFromDb(string $raw): mixed {
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return $decoded === null ? [] : $decoded;
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
        // Expect JSON string from hidden input
        $raw = $postData[$fieldName] ?? '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return $decoded === null ? [] : $decoded;
    }

    public function renderAdminImports(): string {
        ob_start();
        ?>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/code@latest"></script>
        <script src="/assets/editorjs-image-tool.js"></script>
        <script src="/assets/image-asset-picker.js"></script>
        <?php
        return ob_get_clean();
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        // $value may be array (decoded) or JSON string. Normalize to array.
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = $decoded !== null ? $decoded : [];
        }
        $jsonData = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $editorId = 'editorjs_' . md5($fieldName);
        $hiddenId = 'hidden_' . md5($fieldName);

        ob_start();
        ?>
        <div>
            <div id="<?= htmlspecialchars($editorId, ENT_QUOTES, 'UTF-8') ?>" style="border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px; min-height: 200px;"></div>
            <input type="hidden" name="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($hiddenId, ENT_QUOTES, 'UTF-8') ?>" value='<?= htmlspecialchars($jsonData, ENT_QUOTES, 'UTF-8') ?>'>
        </div>
        <script>
        (function() {
            const holderId = <?= json_encode($editorId) ?>;
            const hiddenId = <?= json_encode($hiddenId) ?>;
            const hiddenInput = document.getElementById(hiddenId);
            if (!hiddenInput) return;
            let initialData = {};
            try { if (hiddenInput.value) { initialData = JSON.parse(hiddenInput.value); } } catch (e) { initialData = {}; }
            setTimeout(function() {
                const editor = new EditorJS({
                    holder: holderId,
                    data: initialData,
                    autofocus: false,
                    placeholder: 'Start typing your content here...',
                    tools: {
                        header: Header,
                        list: { class: EditorjsList, inlineToolbar: true, config: { defaultStyle: 'unordered' } },
                        quote: Quote,
                        delimiter: Delimiter,
                        code: CodeTool,
                        imageAsset: { class: ImageAssetTool, inlineToolbar: false, config: { onDataChange: function(){ editor.save().then(function(outputData){ hiddenInput.value = JSON.stringify(outputData); }).catch(function(err){ console.error('Immediate save error', err); }); } } }
                    },
                    onChange: function() {
                        editor.save().then(function(outputData) { hiddenInput.value = JSON.stringify(outputData); }).catch(function(error) { console.error('EditorJS save error', error); });
                    }
                });
            }, 100);
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        // Build HTML preview including imageAsset figures
        $blocks = [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $blocks = $decoded['blocks'] ?? [];
            }
        } elseif (is_array($value)) {
            $blocks = $value['blocks'] ?? [];
        }
        if (empty($blocks)) {
            return '<span style="color:#999;">-</span>';
        }
        $htmlParts = [];
        foreach ($blocks as $block) {
            if (!isset($block['type'], $block['data'])) continue;
            $type = $block['type'];
            $data = $block['data'];
            switch ($type) {
                case 'header':
                    if (isset($data['text'])) {
                        $htmlParts[] = '<h3>' . htmlspecialchars(strip_tags($data['text']), ENT_QUOTES, 'UTF-8') . '</h3>';
                    }
                    break;
                case 'paragraph':
                    if (isset($data['text'])) {
                        $htmlParts[] = '<p>' . htmlspecialchars(strip_tags($data['text']), ENT_QUOTES, 'UTF-8') . '</p>';
                    }
                    break;
                case 'list':
                    if (!empty($data['items']) && is_array($data['items'])) {
                        $items = '';
                        foreach ($data['items'] as $item) {
                            $items .= '<li>' . htmlspecialchars(strip_tags($item), ENT_QUOTES, 'UTF-8') . '</li>';
                        }
                        $htmlParts[] = '<ul>' . $items . '</ul>';
                    }
                    break;
                case 'quote':
                    $quoteText = isset($data['text']) ? htmlspecialchars(strip_tags($data['text']), ENT_QUOTES, 'UTF-8') : '';
                    $caption = isset($data['caption']) ? htmlspecialchars(strip_tags($data['caption']), ENT_QUOTES, 'UTF-8') : '';
                    $htmlParts[] = '<blockquote>' . $quoteText . ($caption ? '<footer>' . $caption . '</footer>' : '') . '</blockquote>';
                    break;
                case 'code':
                    if (isset($data['code'])) {
                        $htmlParts[] = '<pre><code>' . htmlspecialchars($data['code'], ENT_QUOTES, 'UTF-8') . '</code></pre>';
                    }
                    break;
                case 'imageAsset':
                    $alt = htmlspecialchars($data['alt'] ?? '', ENT_QUOTES, 'UTF-8');
                    $htmlParts[] = "&lt;img $alt&gt;";
                    break;
                default:
                    // ignore unsupported block types
                    break;
            }
            // Limit preview length for admin listing to avoid huge HTML
            if (count($htmlParts) > 12) break;
        }
        if (empty($htmlParts)) {
            return '<span style="color:#999;">-</span>';
        }
        return implode("\n", $htmlParts);
    }
}
