<?php
class DateFieldType extends FieldType {
    public function __construct() {
        parent::__construct("date");
    }

    public function getSqlType(): string {
        return 'DATE';
    }

    public function saveToDb(mixed $value): string {
        // Store as YYYY-MM-DD format
        if (empty($value)) {
            return '';
        }

        // If already in correct format, return as-is
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // Try to parse and convert to YYYY-MM-DD
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d');
        } catch (Exception $e) {
            return '';
        }
    }

    public function readFromDb(string $raw): mixed {
        // Return as YYYY-MM-DD string for consistency
        return $raw;
    }

    public function serializeToJson(mixed $value): mixed {
        // Return as ISO 8601 date string (YYYY-MM-DD)
        return $value ?: null;
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        return $postData[$fieldName] ?? '';
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        return "<input type='date' name='{$fieldName}' value='" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "' />";
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        if ($value === '' || $value === null) {
            return '<span style="color:#999;">-</span>';
        }
        try {
            $date = new DateTime($value);
            return $date->format('M d, Y');
        } catch (Exception $e) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }
}
