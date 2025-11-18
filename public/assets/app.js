window.initEntryLocaleMultiToggle = function initEntryLocaleMultiToggle(cfg){
    const bar = document.getElementById('locale-toggle-bar');
    if (!bar) return;
    const active = new Set();
    function update(){
        if (active.size === 0) {
            active.add('__global');
            if (cfg.locales.length > 0) {
                active.add(cfg.locales[0]);
            }
        }
        const blocks = document.querySelectorAll('[data-locale-field]');
        blocks.forEach(b => {
            const loc = b.getAttribute('data-locale-field');
            b.classList.toggle("field-hidden", !active.has(loc));
        });
        bar.querySelectorAll('[data-locale-toggle]').forEach(btn => {
            const loc = btn.getAttribute('data-locale-toggle');
            btn.classList.toggle('btn-primary', active.has(loc));
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
            if (e.ctrlKey) {
                // ctrl+click adds to selection
                active.add(loc);
            } else {
                // normal click clears other selections
                const hasGlobal = active.has('__global');
                active.clear();
                if (hasGlobal) {
                    active.add('__global');
                }
                active.add(loc);
            }
        } else {
            active.delete(loc);
        }
        update();
    });
    update();
};
