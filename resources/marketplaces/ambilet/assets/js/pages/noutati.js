/**
 * Noutăți (index) — hydrates the changelog grid + pagination.
 *
 * Called by /noutati (public page). Reads active category from the
 * grid container's data attribute (set PHP-side from ?cat=). Handles
 * pagination via ?page= without full reload.
 */
(function () {
    'use strict';

    const gridEl = document.getElementById('noutati-grid');
    const paginationEl = document.getElementById('noutati-pagination');
    const emptyEl = document.getElementById('noutati-empty');
    if (!gridEl) return;

    // Category colors mirror the Filament admin + PHP detail page palette.
    const CATEGORY_COLORS = {
        interfata:   'bg-sky-100 text-sky-700',
        organizator: 'bg-amber-100 text-amber-700',
        client:      'bg-emerald-100 text-emerald-700',
    };

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderCard(u) {
        const badgeCls = CATEGORY_COLORS[u.category] || 'bg-slate-100 text-slate-700';
        const img = u.featured_image
            ? `<img src="${escapeHtml(u.featured_image)}" alt="${escapeHtml(u.title)}"
                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                   loading="lazy" width="600" height="338">`
            : '<div class="w-full h-full bg-gradient-to-br from-slate-100 to-slate-200"></div>';

        return `
            <a href="${escapeHtml(u.url)}"
               class="group bg-white rounded-2xl overflow-hidden border border-slate-200 hover:shadow-[0_12px_40px_rgba(0,0,0,0.08)] hover:-translate-y-1 transition-all block">
                <div class="aspect-video overflow-hidden bg-slate-100">${img}</div>
                <div class="p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <span class="inline-flex px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide rounded-full ${badgeCls}">
                            ${escapeHtml(u.category_label || u.category)}
                        </span>
                        ${u.published_at_human
                            ? `<span class="text-xs text-slate-400">${escapeHtml(u.published_at_human)}</span>`
                            : ''}
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 leading-snug mb-2 group-hover:text-primary transition-colors line-clamp-2">
                        ${escapeHtml(u.title)}
                    </h3>
                    ${u.excerpt
                        ? `<p class="text-sm text-slate-500 leading-relaxed line-clamp-3">${escapeHtml(u.excerpt)}</p>`
                        : ''}
                </div>
            </a>`;
    }

    function renderPagination(meta) {
        if (!meta || meta.last_page <= 1) {
            paginationEl.innerHTML = '';
            return;
        }
        const current = meta.current_page;
        const last = meta.last_page;
        const baseParams = new URLSearchParams(window.location.search);
        const pageLink = (p) => {
            baseParams.set('page', p);
            return '?' + baseParams.toString();
        };

        const activeCls = 'bg-gradient-to-br from-primary to-red-600 text-white';
        const idleCls = 'bg-white border border-slate-200 text-slate-500 hover:border-primary hover:text-primary';
        const navCls = 'bg-slate-50 border border-slate-200 text-slate-500 hover:bg-primary hover:text-white hover:border-primary';

        let html = '';
        if (current > 1) {
            html += `<a href="${pageLink(current - 1)}" class="w-11 h-11 flex items-center justify-center rounded-xl transition-all ${navCls}">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>`;
        }
        // Show up to 5 pages centered on current.
        const start = Math.max(1, current - 2);
        const end = Math.min(last, start + 4);
        for (let p = start; p <= end; p++) {
            const cls = p === current ? activeCls : idleCls;
            html += `<a href="${pageLink(p)}" class="w-11 h-11 flex items-center justify-center rounded-xl text-sm font-semibold transition-all ${cls}">${p}</a>`;
        }
        if (current < last) {
            html += `<a href="${pageLink(current + 1)}" class="w-11 h-11 flex items-center justify-center rounded-xl transition-all ${navCls}">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
            </a>`;
        }
        paginationEl.innerHTML = html;
    }

    async function load() {
        const params = new URLSearchParams(window.location.search);
        const page = parseInt(params.get('page') || '1', 10);
        const cat = gridEl.dataset.activeCategory || params.get('cat') || '';

        const query = new URLSearchParams();
        query.set('page', String(page));
        query.set('per_page', '12');
        if (cat) query.set('category', cat);

        try {
            const response = await AmbiletAPI.get('/system-updates?' + query.toString());
            const items = (response && response.data) || [];
            const meta = (response && response.meta) || {};

            if (items.length === 0) {
                gridEl.innerHTML = '';
                emptyEl.classList.remove('hidden');
                paginationEl.innerHTML = '';
                return;
            }
            emptyEl.classList.add('hidden');
            gridEl.innerHTML = items.map(renderCard).join('');
            renderPagination(meta);
        } catch (e) {
            console.error('[noutati] failed to load:', e);
            gridEl.innerHTML = `<div class="col-span-full text-center py-12 text-slate-500">
                Nu am putut încărca noutățile. Te rugăm reîncearcă mai târziu.
            </div>`;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
