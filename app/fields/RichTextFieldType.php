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
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/quote@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/delimiter@latest"></script>
        <script src="https://cdn.jsdelivr.net/npm/@editorjs/code@latest"></script>
        <script>
        (function() {
            const holderId = <?= json_encode($editorId) ?>;
            const hiddenId = <?= json_encode($hiddenId) ?>;
            const hiddenInput = document.getElementById(hiddenId);
            if (!hiddenInput) return;

            let initialData = {};
            try {
                if (hiddenInput.value) {
                    initialData = JSON.parse(hiddenInput.value);
                }
            } catch (e) {
                initialData = {};
            }

            // Wait for all scripts to load
            setTimeout(function() {
                const editor = new EditorJS({
                    holder: holderId,
                    data: initialData,
                    autofocus: false,
                    placeholder: 'Start typing your content here...',
                    tools: {
                        header: Header,
                        list: {
                            class: EditorjsList,
                            inlineToolbar: true,
                            config: {
                                defaultStyle: 'unordered'
                            },
                        },
                        quote: Quote,
                        delimiter: Delimiter,
                        code: CodeTool
                    },
                    onChange: function() {
                        editor.save().then(function(outputData) {
                            hiddenInput.value = JSON.stringify(outputData);
                        }).catch(function(error) {
                            console.error('EditorJS save error', error);
                        });
                    }
                });
            }, 100);
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        // Show a simple text snippet extracted from blocks
        $blocks = [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $blocks = $decoded['blocks'] ?? [];
            }
        } elseif (is_array($value)) {
            $blocks = $value['blocks'] ?? [];
        }

        $text = '';
        foreach ($blocks as $block) {
            if (!isset($block['type'], $block['data'])) continue;
            $data = $block['data'];
            switch ($block['type']) {
                case 'header':
                    if (isset($data['text'])) $text .= ' ' . strip_tags($data['text']);
                    break;
                case 'paragraph':
                    if (isset($data['text'])) $text .= ' ' . strip_tags($data['text']);
                    break;
                case 'list':
                    if (!empty($data['items']) && is_array($data['items'])) {
                        foreach ($data['items'] as $item) {
                            $text .= ' ' . strip_tags($item);
                        }
                    }
                    break;
                case 'quote':
                    if (isset($data['text'])) $text .= ' ' . strip_tags($data['text']);
                    if (isset($data['caption'])) $text .= ' — ' . strip_tags($data['caption']);
                    break;
                case 'code':
                    if (isset($data['code'])) $text .= ' [code]';
                    break;
                default:
                    // ignore other block types for now
                    break;
            }
            if (mb_strlen($text) > 200) break;
        }

        $text = trim($text);
        if ($text === '') {
            return '<span style="color:#999;">-</span>';
        }
        if (mb_strlen($text) > 120) {
            $text = mb_substr($text, 0, 120) . '...';
        }
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

