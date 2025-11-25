<?php
class DecimalFieldType extends FieldType {
    public function __construct() {
        parent::__construct("decimal");
    }

    public function getSqlType(): string {
        return 'DECIMAL(10,2)';
    }

    public function saveToDb(mixed $value): string {
        return (string)floatval($value);
    }

    public function readFromDb(string $raw): mixed {
        return floatval($raw);
    }

    public function serializeToJson(mixed $value): mixed {
        return floatval($value);
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        return isset($postData[$fieldName]) ? floatval($postData[$fieldName]) : 0;
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        return "<input type='number' name='{$fieldName}' value='" . htmlspecialchars((string)$value) . "' />";
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}
