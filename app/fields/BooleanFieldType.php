<?php
class BooleanFieldType extends FieldType {
    public function __construct() {
        parent::__construct("boolean");
    }

    public function getSqlType(): string {
        return 'TINYINT(1)';
    }

    public function saveToDb(mixed $value): string {
        return $value ? "1" : "0";
    }

    public function readFromDb(string $raw): mixed {
        return $raw === "1";
    }

    public function serializeToJson(mixed $value): mixed {
        return (bool)$value;
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        return isset($postData[$fieldName]) ? '1' : '0';
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        $checked = $value === '1' ? "checked" : "";
        return "<input type='checkbox' name='{$fieldName}' {$checked} />";
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        return $value ? '✓' : '✗';
    }
}