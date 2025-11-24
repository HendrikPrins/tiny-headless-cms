<?php
class ImagesFieldType extends FieldType {
    use AssetFieldCommon;
    public function __construct() {
        parent::__construct('images');
    }

    public function shouldWrapWithLabel(): bool {
        return false;
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        // Multiple images with alt and ordering, filtered to images
        return $this->renderMultiAssetAdmin(
            $fieldName,
            $value,
            'images',
            'Add Images',
            'images',
            true,
            true
        );
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        // Multiple small image previews
        return $this->renderMultiPreview($value, true, 5);
    }
}
