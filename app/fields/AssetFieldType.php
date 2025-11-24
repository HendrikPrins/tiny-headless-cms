<?php
class AssetFieldType extends FieldType {
    use AssetFieldCommon;
    public function __construct() {
        parent::__construct('asset');
    }

    public function renderAdminForm(string $fieldName, mixed $value): string {
        // Single generic asset, no alt
        return $this->renderSingleAssetAdmin(
            $fieldName,
            $value,
            'asset',
            'Select Asset',
            'Change Asset',
            null,
            false,
            false
        );
    }

    public function renderPreview(string $fieldName, mixed $value): string {
        // Single asset name
        return $this->renderSinglePreview($value, false);
    }
}
