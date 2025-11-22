class ImageAssetTool {
    static get toolbox() {
        return { title: 'Image', icon: '<svg width="17" height="15" viewBox="0 0 17 15" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 0h14c.83 0 1.5.67 1.5 1.5v12c0 .83-.67 1.5-1.5 1.5h-14C.67 15 0 14.33 0 13.5v-12C0 .67.67 0 1.5 0zm0 1.5v9.78l3.2-3.2a1.5 1.5 0 012.12 0l2.9 2.9 3.95-3.95a1.5 1.5 0 012.13 0l.6.6V1.5h-14zM15.5 13.5v-4.09l-1.02-1.02-4.48 4.48a1.5 1.5 0 01-2.12 0l-2.9-2.9-3.48 3.48h14zM5.25 6A1.75 1.75 0 107.75 6 1.75 1.75 0 005.25 6z" fill="currentColor"/></svg>' };
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