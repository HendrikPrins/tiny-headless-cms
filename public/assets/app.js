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

    let state = Array.isArray(cfg.initial) ? cfg.initial.map(f => Object.assign({deleted:false}, f)) : [];

    const TYPES = [
        {value: 'string', label: 'String'},
        {value: 'text', label: 'Text'},
        {value: 'integer', label: 'Integer'},
        {value: 'decimal', label: 'Decimal'},
        {value: 'boolean', label: 'Boolean'},
    ];

    function render() {
        list.innerHTML = '';
        state.forEach((f, idx) => {
            const row = el('div', {class: 'ch-row', style: 'display:flex; gap:12px; align-items:center; margin-bottom:8px; border:1px solid #e0e0e0; padding:8px; border-radius:4px;'});
            if (f.deleted) row.style.opacity = '0.5';

            // name input
            const name = el('input', {type: 'text', value: f.name || '', placeholder: 'Field name', style: 'min-width:160px'});
            name.addEventListener('input', function(){ state[idx].name = this.value; });

            // type select
            const sel = createSelect(TYPES, f.field_type || 'string');
            sel.addEventListener('change', function(){ state[idx].field_type = this.value; });

            // required checkbox
            const reqLabel = el('label', {style: 'display:inline-flex; align-items:center; gap:6px;'}, 'Required');
            const req = el('input', {type: 'checkbox'});
            req.checked = !!f.is_required;
            req.addEventListener('change', function(){ state[idx].is_required = this.checked; });
            reqLabel.appendChild(req);

            // translatable checkbox
            const transLabel = el('label', {style: 'display:inline-flex; align-items:center; gap:6px;'}, 'Translatable');
            const trans = el('input', {type: 'checkbox'});
            trans.checked = !!f.is_translatable;
            trans.addEventListener('change', function(){ state[idx].is_translatable = this.checked; });
            transLabel.appendChild(trans);

            // order
            const order = el('input', {type: 'number', value: f.order || 0, style: 'width:80px'});
            order.addEventListener('input', function(){ state[idx].order = parseInt(this.value || '0', 10); });

            const left = el('div', {style: 'flex:1; display:flex; gap:8px; align-items:center;'});
            left.appendChild(name);
            left.appendChild(sel);
            left.appendChild(reqLabel);
            left.appendChild(transLabel);
            left.appendChild(order);

            const delBtn = el('button', {type:'button', class: 'btn-danger'}, f.deleted ? 'Undelete' : 'Delete');
            delBtn.addEventListener('click', function(){
                state[idx].deleted = !state[idx].deleted;
                render();
            });

            const right = el('div', {style: 'display:flex; gap:8px; align-items:center;'});
            right.appendChild(delBtn);

            row.appendChild(left);
            row.appendChild(right);
            list.appendChild(row);
        });
    }

    function addNew() {
        state.push({id:0, name:'', field_type:'string', is_required:false, is_translatable:false, order:0, deleted:false});
        render();
    }

    function submitAll() {
        // validation: ensure existing fields (id>0 and not deleted) have a name
        for (let i=0;i<state.length;i++) {
            const f = state[i];
            if (f.id && !f.deleted && (!f.name || String(f.name).trim() === '')) {
                alert('Existing fields must have a name. Please fill or delete them.');
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

        input.value = JSON.stringify(payload);
        form.submit();
    }

    // attach handlers
    if (addBtn) addBtn.addEventListener('click', addNew);
    if (saveBtn) saveBtn.addEventListener('click', submitAll);

    // initial render
    render();
};

