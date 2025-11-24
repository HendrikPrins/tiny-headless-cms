<?php
class ImageFieldType extends FieldType {
    use AssetFieldCommon;
    public function __construct() {
        parent::__construct('image');
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        // Single image with preview and alt, filtered to images
        return $this->renderSingleAssetAdmin(
            $fieldName,
            $value,
            'image',
            'Select Image',
            'Change Image',
            'images',
            true,
            true
        );
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        // Single image thumbnail preview
        return $this->renderSinglePreview($value, true);
    }
}
