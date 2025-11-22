<?php
abstract class FieldType {
    protected string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    public function getName(): string {
        return $this->name;
    }

    abstract public function saveToDb(mixed $value): string;
    abstract public function readFromDb(string $raw): mixed;

    abstract public function serializeToJson(mixed $value): mixed;
    abstract public function deserializeFromPost(array $postData, string $fieldName): mixed;

    public function renderAdminImports(): string {
        return '';
    }
    abstract public function renderAdminForm(string $fieldName, mixed $value): string;
    abstract public function renderPreview(string $fieldName, mixed $value): string;
}
