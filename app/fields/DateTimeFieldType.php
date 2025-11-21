<?php
class DateTimeFieldType extends FieldType {
    public function __construct() {
        parent::__construct("datetime");
    }

    public function saveToDb(mixed $value): string {
        // Store as YYYY-MM-DD HH:MM:SS format
        if (empty($value)) {
            return '';
        }

        // If already in correct format, return as-is
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        // If HTML datetime-local format (YYYY-MM-DDTHH:MM), convert it
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return str_replace('T', ' ', $value) . ':00';
        }

        // Try to parse and convert to YYYY-MM-DD HH:MM:SS
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return '';
        }
    }

    public function readFromDb(string $raw): mixed {
        // Return as YYYY-MM-DD HH:MM:SS string
        return $raw;
    }

    public function serializeToJson(mixed $value): mixed {
        // Return as ISO 8601 datetime string
        if (empty($value)) {
            return null;
        }

        return $value;
    }

    public function deserializeFromPost(array $postData, string $fieldName): mixed {
        return $postData[$fieldName] ?? '';
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        // Convert YYYY-MM-DD HH:MM:SS to YYYY-MM-DDTHH:MM for datetime-local input
        $formattedValue = '';
        if (!empty($value)) {
            try {
                $date = new DateTime($value);
                $formattedValue = $date->format('Y-m-d\TH:i');
            } catch (Exception $e) {
                $formattedValue = '';
            }
        }

        return "<input type='datetime-local' name='{$fieldName}' value='" . htmlspecialchars($formattedValue, ENT_QUOTES, 'UTF-8') . "' />";
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        if ($value === '' || $value === null) {
            return '<span style="color:#999;">-</span>';
        }
        try {
            $date = new DateTime($value);
            return $date->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }
    }
}
