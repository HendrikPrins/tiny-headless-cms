(function () {
    function createModal() {
        const existing = document.getElementById('image-asset-picker-modal');
        if (existing) return existing;
        const wrap = document.createElement('div');
        wrap.id = 'image-asset-picker-modal';
        wrap.className = 'image-asset-picker-modal';
        wrap.hidden = true;
        wrap.innerHTML = `
      <div class="iap-overlay" data-iap-close></div>
      <div class="iap-dialog" role="dialog" aria-modal="true" aria-labelledby="iap-title">
        <div class="iap-header">
          <h2 id="iap-title">Select Image</h2>
          <button type="button" class="iap-close" data-iap-close aria-label="Close picker">&times;</button>
        </div>
        <div class="iap-search-bar">
          <select id="iap-filter" class="iap-filter" aria-label="Filter by type">
            <option value="all">All files</option>
            <option value="images">Images only</option>
            <option value="other">Other files</option>
          </select>
          <input type="text" id="iap-search" placeholder="Search filename..." autocomplete="off" aria-label="Search filename">
          <button type="button" id="iap-search-btn" class="btn-secondary" aria-label="Search">Search</button>
        </div>
        <div class="iap-breadcrumb" id="iap-breadcrumb"></div>
        <div class="directory-grid" id="iap-dir-grid"></div>
        <div class="iap-results" id="iap-results" aria-live="polite"></div>
        <div class="iap-footer">
          <button type="button" id="iap-load-more" class="btn-secondary" hidden>Load More</button>
        </div>
      </div>`;
        document.body.appendChild(wrap);
        return wrap;
    }

    function init() {
        const modal = createModal();
        const resultsEl = modal.querySelector('#iap-results');
        const dirGridEl = modal.querySelector('#iap-dir-grid');
        const breadcrumbEl = modal.querySelector('#iap-breadcrumb');
        const searchInput = modal.querySelector('#iap-search');
        const searchBtn = modal.querySelector('#iap-search-btn');
        const loadMoreBtn = modal.querySelector('#iap-load-more');
        const filterSelect = modal.querySelector('#iap-filter');
        let offset = 0, limit = 40, total = 0, currentDir = '', currentQuery = '', currentFilter = 'all', loading = false, onSelect = null;

        function resetState() {
            offset = 0;
            total = 0;
            currentDir = '';
            currentQuery = '';
            searchInput.value = '';
            resultsEl.innerHTML = '';
            dirGridEl.innerHTML = '';
            breadcrumbEl.innerHTML = '';
            loadMoreBtn.hidden = true;
            // Don't reset filter - it may be set by caller
        }

        function show() {
            modal.hidden = false;
            document.body.classList.add('iap-open');
        }

        function close() {
            modal.hidden = true;
            document.body.classList.remove('iap-open');
        }

        function fetchBatch(initial) {
            if (loading) return;
            loading = true;
            const q = searchInput.value.trim();
            const filter = filterSelect.value;
            currentFilter = filter;
            if (initial) {
                offset = 0;
                resultsEl.innerHTML = '';
                dirGridEl.innerHTML = '';
                breadcrumbEl.innerHTML = '';
            }
            if (offset === 0) {
                resultsEl.innerHTML = '<div class="iap-loading">Loading...</div>';
            }
            const url = `index.php?page=assets-json&limit=${limit}&offset=${offset}&dir=${encodeURIComponent(currentDir)}&q=${encodeURIComponent(q)}&filter=${encodeURIComponent(filter)}`;
            fetch(url).then(r => r.json()).then(d => {
                loading = false;
                currentQuery = q;
                renderAll(d, initial);
            }).catch(err => {
                loading = false;
                resultsEl.innerHTML = '<div class="iap-empty">Failed to load assets.</div>';
            });
        }

        function renderAll(d, initial) {
            if (initial) {
                dirGridEl.innerHTML = '';
                resultsEl.innerHTML = '';
            }
            renderBreadcrumb(d.directory);
            if (currentQuery === '') {
                renderDirectories(d.subDirectories);
            } else {
                dirGridEl.innerHTML = '';
            }
            renderItems(d.items, initial);
            total = d.total;
            offset += d.items.length;
            loadMoreBtn.hidden = offset >= total;
        }

        function renderDirectories(sub) {
            dirGridEl.innerHTML = '';
            if (!sub || !sub.length) {
                dirGridEl.classList.add('hidden');
                return;
            }
            dirGridEl.classList.remove('hidden');
            sub.forEach(sd => {
                const name = sd.split('/').pop();
                const el = document.createElement('a');
                el.href = '#';
                el.className = 'directory-tile';
                el.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg><span class="directory-name">${escapeHtml(name)}</span>`;
                el.addEventListener('click', (e) => {
                    e.preventDefault();
                    currentDir = sd;
                    offset = 0;
                    fetchBatch(true);
                });
                dirGridEl.appendChild(el);
            });
        }

        function renderBreadcrumb(dir) {
            const segs = dir ? dir.split('/') : [];
            const parts = ["<a href='#' data-bc=''>Root</a>"];
            segs.forEach((seg, i) => {
                const path = segs.slice(0, i + 1).join('/');
                parts.push(`<a href='#' data-bc='${path}'>${seg}</a>`);
            });
            breadcrumbEl.innerHTML = parts.join('<span>/</span>');
            breadcrumbEl.querySelectorAll('a[data-bc]').forEach(a => {
                a.addEventListener('click', e => {
                    e.preventDefault();
                    currentDir = a.getAttribute('data-bc');
                    offset = 0;
                    fetchBatch(true);
                });
            });
        }

        function renderItems(items, replace) {
            if (replace) resultsEl.innerHTML = '';
            if (!items || !items.length) {
                if (offset === 0) {
                    resultsEl.innerHTML = '<div class="iap-empty">No assets found.</div>';
                }
                return;
            }
            items.forEach(item => {
                const div = document.createElement('div');
                div.className = 'iap-item';
                const isImage = item.mime && item.mime.startsWith('image/');
                const thumbHtml = isImage
                    ? `<img src='${item.url}' alt='' class='asset-thumb'>`
                    : '<span class="file-icon">📄</span>';
                div.innerHTML = `<div class="iap-thumb">${thumbHtml}</div><div class="iap-meta"><div class="iap-fn">${escapeHtml(item.filename)}</div><div class="iap-size">${formatSize(item.size)}</div></div>`;
                div.addEventListener('click', () => {
                    try {
                        if (onSelect) {
                            onSelect(item);
                        }
                        window.dispatchEvent(new CustomEvent('image-asset-selected', {detail: item}));
                    } catch (e) {
                        console.error('Image select error', e);
                    } finally {
                        close();
                    }
                });
                resultsEl.appendChild(div);
            });
        }

        function escapeHtml(str) {
            return (str || '').replace(/[&<>"']/g, c => ({"&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;"}[c]));
        }

        function formatSize(bytes) {
            if (!bytes) return '';
            const units = ['B', 'KB', 'MB', 'GB'];
            let u = 0, b = bytes;
            while (b > 1000 && u < units.length - 1) {
                b /= 1000;
                u++;
            }
            return b.toFixed(1) + units[u];
        }

        modal.addEventListener('click', e => {
            if (e.target.matches('[data-iap-close]')) close();
        });
        searchBtn.addEventListener('click', () => {
            currentDir = '';
            offset = 0;
            fetchBatch(true);
        });
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Enter') {
                currentDir = '';
                offset = 0;
                fetchBatch(true);
            }
        });
        loadMoreBtn.addEventListener('click', () => fetchBatch(false));
        filterSelect.addEventListener('change', () => {
            offset = 0;
            fetchBatch(true);
        });
        function openInternal(cb){ onSelect = cb; resetState(); show(); fetchBatch(true); }
        function openPicker(cb, options){
            options = options || {};
            if (!options.manual) return;
            if (!options.sourceButton) return;
            // Set filter if provided
            if (options.defaultFilter) {
                filterSelect.value = options.defaultFilter;
                currentFilter = options.defaultFilter;
            }
            openInternal(cb);
        }
        window.CMSImageAssetPicker = { openPicker };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
