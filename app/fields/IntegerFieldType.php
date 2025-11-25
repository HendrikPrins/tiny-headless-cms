<?php
class IntegerFieldType extends FieldType {
    public function __construct() {
        parent::__construct("integer");
    }

    public function getSqlType(): string {
        return 'INT';
    }

    public function saveToDb(mixed $value): string {
        return (string)intval($value);
    }

    public function readFromDb(string $raw): mixed {
        return intval($raw);
    }

    public function serializeToJson(mixed $value): mixed {
        return intval($value);
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        return isset($postData[$fieldName]) ? intval($postData[$fieldName]) : 0;
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        return "<input type='number' name='{$fieldName}' value='" . htmlspecialchars((string)$value) . "' />";
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
