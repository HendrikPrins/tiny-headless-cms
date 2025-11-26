<?php
class TextFieldType extends FieldType {
    public function __construct() {
        parent::__construct("text");
    }

    public function getSqlType(): string {
        return 'LONGTEXT';
    }

    public function saveToDb(mixed $value): mixed {
        return (string)$value;
    }

    public function serializeToJson(mixed $value): mixed {
        return (string)$value;
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        return $postData[$fieldName] ?? "";
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        return "<textarea name='{$fieldName}' rows='5'>" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</textarea>";
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        $str = (string)$value;
        if (mb_strlen($str) > 60) {
            return htmlspecialchars(mb_substr($str, 0, 60), ENT_QUOTES, 'UTF-8') . '...';
        }
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}
