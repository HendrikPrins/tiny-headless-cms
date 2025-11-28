class ImageAssetTool {
    static get toolbox() {
        return { title: 'Image', icon: '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 8h.01" /><path d="M3 6a3 3 0 0 1 3 -3h12a3 3 0 0 1 3 3v12a3 3 0 0 1 -3 3h-12a3 3 0 0 1 -3 -3v-12z" /><path d="M3 16l5 -5c.928 -.893 2.072 -.893 3 0l5 5" /><path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l3 3" /></svg>' };
    }
    constructor({ data, api, config }) {
        this.data = data || { assetId: null, url: '', filename: '', alt: '', caption: '' };
        this.api = api;
        this.config = config || {};
        this.wrapper = null;
    }
    render() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'image-asset-block';
        this._renderInner();
        return this.wrapper;
    }
    _renderInner() {
        this.wrapper.innerHTML = '';
        const hasImage = !!this.data.url;
        const pickBtn = document.createElement('button');
        pickBtn.type = 'button';
        pickBtn.className = 'btn-secondary iab-pick-btn';
        pickBtn.textContent = hasImage ? 'Change Image' : 'Select Image';
        pickBtn.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return; // only left click
            if (e.target !== pickBtn) return; // ensure direct button click
            if (!window.CMSImageAssetPicker) return;
            window.CMSImageAssetPicker.openPicker((asset) => {
                this.data.assetId = asset.id;
                this.data.url = asset.url;
                this.data.filename = asset.filename;
                this._renderInner();
                this._notifyChange();
            }, { manual: true, sourceButton: pickBtn, defaultFilter: 'images' });
        });
        pickBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!window.CMSImageAssetPicker) return;
                window.CMSImageAssetPicker.openPicker((asset) => {
                    this.data.assetId = asset.id;
                    this.data.url = asset.url;
                    this.data.filename = asset.filename;
                    this._renderInner();
                    this._notifyChange();
                }, { manual: true, sourceButton: pickBtn, defaultFilter: 'images' });
            }
        });
        this.wrapper.appendChild(pickBtn);
        if (hasImage) {
            const preview = document.createElement('div');
            preview.className = 'iab-preview';
            preview.innerHTML = `<img src="${this.data.url}" alt="${this._escape(this.data.alt)}">`;
            this.wrapper.appendChild(preview);
            // Alt text
            const altLabel = document.createElement('label');
            altLabel.className = 'iab-alt-label';
            altLabel.innerHTML = '<span>Alt text</span>';
            const altInput = document.createElement('input');
            altInput.type = 'text';
            altInput.placeholder = 'Describe the image';
            altInput.value = this.data.alt || '';
            altInput.addEventListener('input', () => { this.data.alt = altInput.value; this._notifyChange(); });
            altLabel.appendChild(altInput);
            this.wrapper.appendChild(altLabel);
            // Caption
            const capLabel = document.createElement('label');
            capLabel.className = 'iab-caption-label';
            capLabel.innerHTML = '<span>Caption (optional)</span>';
            const capInput = document.createElement('input');
            capInput.type = 'text';
            capInput.placeholder = 'Caption';
            capInput.value = this.data.caption || '';
            capInput.addEventListener('input', () => { this.data.caption = capInput.value; this._notifyChange(); });
            capLabel.appendChild(capInput);
            this.wrapper.appendChild(capLabel);
        } else {
            const placeholder = document.createElement('div');
            placeholder.className = 'iab-placeholder';
            placeholder.textContent = 'No image selected.';
            this.wrapper.appendChild(placeholder);
        }
    }
    _notifyChange() {
        if (typeof this.config.onDataChange === 'function') {
            try { this.config.onDataChange(this.data); } catch (e) { console.error('onDataChange error', e); }
        }
    }
    _escape(str){ return (str||'').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
    save() {
        return this.data;
    }
    validate(savedData){
        // Always valid; optional asset
        return true;
    }
}