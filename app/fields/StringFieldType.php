<?php
class StringFieldType extends FieldType {
    public function __construct() {
        parent::__construct("string");
    }

    public function saveToDb(mixed $value): string {
        return (string)$value;
    }

    public function readFromDb(string $raw): mixed {
        return $raw;
    }

    public function serializeToJson(mixed $value): mixed {
        return (string)$value;
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        return $postData[$fieldName] ?? "";
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        return "<input type='text' name='{$fieldName}' value='" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "' />";
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        $str = (string)$value;
        if (mb_strlen($str) > 40) {
            return htmlspecialchars(mb_substr($str, 0, 40), ENT_QUOTES, 'UTF-8') . '...';
        }
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}
