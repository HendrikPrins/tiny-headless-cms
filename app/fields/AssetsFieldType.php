<?php
class AssetsFieldType extends FieldType {
    use AssetFieldCommon;
    public function __construct() {
        parent::__construct('assets');
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        // Multiple generic assets, ordered, no alt
        return $this->renderMultiAssetAdmin(
            $fieldName,
            $value,
            'assets',
            'Add Assets',
            null,
            false,
            true
        );
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        // Names of up to 3 assets, with +N suffix
        return $this->renderMultiPreview($value, false, 3);
    }
}
