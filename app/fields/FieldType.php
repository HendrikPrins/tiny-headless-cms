<?php
abstract class FieldType {
    protected string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    public function getName(): string {
        return $this->name;
    }

    abstract public function getSqlType(): string;

    abstract public function saveToDb(mixed $value): mixed;

    abstract public function serializeToJson(mixed $value): mixed;
    abstract public function deserializeFromPost(array $postData, string $fieldName): mixed;

    public function renderAdminImports(): string {
        return '';
    }

    /**
     * Whether the admin form renderer should wrap this field in an outer <label class="field">.
     * Complex widgets like asset pickers or rich text editors can override to return false
     * so they are wrapped in a neutral container instead.
     */
    public function shouldWrapWithLabel(): bool {
        return true;
    }

    abstract public function renderAdminForm(string $fieldName, mixed $value): string;
    abstract public function renderPreview(string $fieldName, mixed $value): string;
}
