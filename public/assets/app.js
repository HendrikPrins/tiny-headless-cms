function qs(id) { return document.getElementById(id); }
function el(tag, attrs = {}, ...children) {
    const d = document.createElement(tag);
    for (const k in attrs) {
        if (k === 'class') d.className = attrs[k];
        else if (k === 'style') d.style.cssText = attrs[k];
        else if (k.startsWith('data-')) d.setAttribute(k, attrs[k]);
        else d[k] = attrs[k];
    }
    for (const c of children) {
        if (typeof c === 'string') d.appendChild(document.createTextNode(c));
        else if (c instanceof Node) d.appendChild(c);
    }
    return d;
}

function createSelect(options, value) {
    const s = el('select');
    options.forEach(opt => {
        const o = el('option');
        o.value = opt.value;
        o.textContent = opt.label;
        if (opt.value === value) o.selected = true;
        s.appendChild(o);
    });
    return s;
}

function formatBool(v) { return v ? 1 : 0; }

window.initCollectionsEditor = function initCollectionsEditor(cfg) {
    const root = qs(cfg.rootId);
    if (!root) return;
    const list = qs(cfg.listId);
    const addBtn = qs(cfg.addBtnId);
    const saveBtn = qs(cfg.saveBtnId);
    const form = qs(cfg.formId);
    const input = qs(cfg.inputId);

    let state = Array.isArray(cfg.initial) ? cfg.initial.map(f => Object.assign({deleted:false, _error:null}, f)) : [];

    const TYPES = [
        {value: 'string', label: 'String'},
        {value: 'text', label: 'Text'},
        {value: 'integer', label: 'Integer'},
        {value: 'decimal', label: 'Decimal'},
        {value: 'boolean', label: 'Boolean'},
    ];

    // Global error container
    const globalError = el('div', {class: 'alert alert-danger', style: 'display:none; margin-bottom:10px;'});
    list.parentNode.insertBefore(globalError, list);

    function showGlobalError(msg) {
        globalError.textContent = msg || '';
        globalError.style.display = msg ? 'block' : 'none';
    }

    function validateRow(f) {
        // Only validate existing rows (id>0) that are not deleted for required name
        if (f.id && !f.deleted && (!f.name || String(f.name).trim() === '')) {
            return 'Name is required.';
        }
        return null;
    }

    function render() {
        list.innerHTML = '';
        state.forEach((f, idx) => {
            const row = el('div', {class: 'ch-row', style: 'display:flex; flex-direction:column; gap:6px; margin-bottom:10px; border:1px solid #e0e0e0; padding:8px; border-radius:4px;'});
            if (f.deleted) row.style.opacity = '0.5';

            const fieldsLine = el('div', {style: 'display:flex; gap:12px; align-items:center; flex-wrap:wrap;'});

            const name = el('input', {type: 'text', value: f.name || '', placeholder: 'Field name', style: 'min-width:160px'});
            name.addEventListener('input', function(){ state[idx].name = this.value; state[idx]._error = validateRow(state[idx]); updateRowError(); });

            const sel = createSelect(TYPES, f.field_type || 'string');
            sel.addEventListener('change', function(){ state[idx].field_type = this.value; });

            const reqLabel = el('label', {style: 'display:inline-flex; align-items:center; gap:6px;'}, 'Required');
            const req = el('input', {type: 'checkbox'});
            req.checked = !!f.is_required;
            req.addEventListener('change', function(){ state[idx].is_required = this.checked; });
            reqLabel.appendChild(req);

            const transLabel = el('label', {style: 'display:inline-flex; align-items:center; gap:6px;'}, 'Translatable');
            const trans = el('input', {type: 'checkbox'});
            trans.checked = !!f.is_translatable;
            trans.addEventListener('change', function(){ state[idx].is_translatable = this.checked; });
            transLabel.appendChild(trans);

            const order = el('input', {type: 'number', value: f.order || 0, style: 'width:80px'});
            order.addEventListener('input', function(){ state[idx].order = parseInt(this.value || '0', 10); });

            const delBtn = el('button', {type:'button', class: 'btn-danger'}, f.deleted ? 'Undelete' : 'Delete');
            delBtn.addEventListener('click', function(){
                state[idx].deleted = !state[idx].deleted;
                state[idx]._error = validateRow(state[idx]);
                render();
            });

            fieldsLine.appendChild(name);
            fieldsLine.appendChild(sel);
            fieldsLine.appendChild(reqLabel);
            fieldsLine.appendChild(transLabel);
            fieldsLine.appendChild(order);
            fieldsLine.appendChild(delBtn);

            const errorLine = el('div', {class: 'field-error', style: 'color:#ff6b6b; min-height:18px;'});

            function updateRowError(){
                errorLine.textContent = state[idx]._error || '';
            }
            state[idx]._error = validateRow(state[idx]);
            updateRowError();

            row.appendChild(fieldsLine);
            row.appendChild(errorLine);
            list.appendChild(row);
        });
    }

    function addNew() {
        state.push({id:0, name:'', field_type:'string', is_required:false, is_translatable:false, order:0, deleted:false, _error:null});
        render();
    }

    function setSaveDisabled(disabled) {
        if (saveBtn) {
            saveBtn.disabled = !!disabled;
            saveBtn.textContent = disabled ? 'Saving…' : 'Save All';
        }
    }

    function submitAll() {
        showGlobalError('');

        // Validate all rows and block on first error
        for (let i=0;i<state.length;i++) {
            state[i]._error = validateRow(state[i]);
            if (state[i]._error) {
                render();
                showGlobalError('Please fix validation errors before saving.');
                return;
            }
        }

        const payload = state.map(f => ({
            id: f.id || 0,
            name: (f.name || '').trim(),
            field_type: f.field_type || 'string',
            is_required: f.is_required ? 1 : 0,
            is_translatable: f.is_translatable ? 1 : 0,
            order: parseInt(f.order || 0, 10) || 0,
            deleted: f.deleted ? 1 : 0
        }));

        setSaveDisabled(true);
        try {
            input.value = JSON.stringify(payload);
            form.submit();
        } finally {
            // In PRG flow, page will navigate; this is a fallback if submit is prevented by the browser
            setTimeout(() => setSaveDisabled(false), 3000);
        }
    }

    // attach handlers
    if (addBtn) addBtn.addEventListener('click', addNew);
    if (saveBtn) saveBtn.addEventListener('click', submitAll);

    // initial render
    render();
};


window.initEntryLocaleMultiToggle = function initEntryLocaleMultiToggle(cfg){

    const bar = document.getElementById('locale-toggle-bar');
    if (!bar) return;
    const active = new Set(["__global", cfg.locales[0]]);
    function update(){
        const blocks = document.querySelectorAll('[data-locale-field]');
        blocks.forEach(b => {
            const loc = b.getAttribute('data-locale-field');
            b.style.display = active.has(loc) ? 'flex' : 'none';
        });
        bar.querySelectorAll('[data-locale-toggle]').forEach(btn => {
            const loc = btn.getAttribute('data-locale-toggle');
            const isOn = active.has(loc);
            btn.classList.toggle('active', isOn);
            btn.style.background = isOn ? '#007bff' : '#f0f0f0';
            btn.style.color = isOn ? '#fff' : '#333';
            btn.style.fontWeight = isOn ? 'bold' : 'normal';
        });
    }
    bar.addEventListener('click', e => {
        const btn = e.target.closest('[data-locale-toggle]');
        if (!btn) return;
        const loc = btn.getAttribute('data-locale-toggle');
        if (loc === '__global') {
            if (active.has('__global'))  {
                active.delete('__global');
            } else {
                active.add('__global');
            }
        } else if (!active.has(loc)) {
            const hasGlobal = active.has('__global');
            active.clear();
            if (hasGlobal) {
                active.add('__global');
            }
            active.add(loc);
        }
        if (active.size === 0) active.add('__global');
        update();
    });
    if (active.size === 0) active.add('__global');
    update();
};
