/**
 * Noutăți (index) — hydrates featured card + grid + pagination.
 *
 * Called by /noutati (public page). Reads active category from the
 * grid container's data attribute (set PHP-side from ?cat=). On the
 * first (unfiltered) page the first item renders as a big featured
 * hero card; the rest go into a 3-col grid. Filter tabs are PHP links
 * (?cat=X) so category switches are full-page navigation — keeps the
 * URL clean and cacheable.
 */
(function () {
    'use strict';

    const gridEl = document.getElementById('noutati-grid');
    const featuredEl = document.getElementById('noutati-featured');
    const gridHeadingEl = document.getElementById('noutati-grid-heading');
    const countEl = document.getElementById('noutati-count');
    const paginationEl = document.getElementById('noutati-pagination');
    const emptyEl = document.getElementById('noutati-empty');
    if (!gridEl) return;

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Category → short label shown on cards. Uses "Pentru X" phrasing
    // matching the detail page — no coloured pills.
    function categoryLabel(cat) {
        return {
            interfata:   'Interfață',
            organizator: 'Pentru Organizatori',
            client:      'Pentru Clienți',
        }[cat] || cat;
    }

    function isRecent(publishedAtIso) {
        if (!publishedAtIso) return false;
        const dt = new Date(publishedAtIso);
        if (isNaN(dt.getTime())) return false;
        const sevenDays = 7 * 24 * 60 * 60 * 1000;
        return (Date.now() - dt.getTime()) < sevenDays;
    }

    function renderFeatured(u) {
        const img = u.featured_image
            ? `<img src="${escapeHtml(u.featured_image)}" alt="${escapeHtml(u.title)}" loading="eager" width="800" height="600">`
            : '<div class="w-full h-full bg-gradient-to-br from-slate-800 via-slate-700 to-slate-900 flex items-center justify-center"><svg class="w-16 h-16 text-white/20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></div>';
        const newBadge = isRecent(u.published_at) ? '<div class="noutati-new-badge">NOU</div>' : '';

        return `
            <a href="${escapeHtml(u.url)}" class="noutati-featured-card block">
                <div class="noutati-featured-image">${newBadge}${img}</div>
                <div class="noutati-featured-content">
                    <div class="inline-flex items-center gap-2 text-primary mb-3">
                        <span class="text-xs font-bold uppercase tracking-widest">${escapeHtml(categoryLabel(u.category))}</span>
                        ${u.published_at_human ? `<span class="text-slate-300">·</span><span class="text-xs text-slate-500 font-medium">${escapeHtml(u.published_at_human)}</span>` : ''}
                    </div>
                    <h2 class="text-2xl md:text-3xl lg:text-4xl font-black text-slate-900 leading-tight mb-4 tracking-tight">
                        ${escapeHtml(u.title)}
                    </h2>
                    ${u.excerpt ? `<p class="text-base md:text-lg text-slate-600 leading-relaxed mb-5 line-clamp-3">${escapeHtml(u.excerpt)}</p>` : ''}
                    <div class="inline-flex items-center gap-2 text-primary font-bold">
                        Citește tot
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                    </div>
                </div>
            </a>`;
    }

    function renderCard(u) {
        const img = u.featured_image
            ? `<img src="${escapeHtml(u.featured_image)}" alt="${escapeHtml(u.title)}"
                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                   loading="lazy" width="600" height="338">`
            : '<div class="w-full h-full bg-gradient-to-br from-slate-100 via-slate-50 to-slate-100 flex items-center justify-center"><svg class="w-12 h-12 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg></div>';
        const newBadge = isRecent(u.published_at) ? '<div class="noutati-new-badge">NOU</div>' : '';

        return `
            <a href="${escapeHtml(u.url)}"
               class="group relative bg-white rounded-2xl overflow-hidden border border-slate-200 hover:shadow-[0_16px_48px_rgba(0,0,0,0.1)] hover:-translate-y-1 hover:border-primary/40 transition-all block">
                <div class="relative aspect-video overflow-hidden bg-slate-100">${newBadge}${img}</div>
                <div class="p-6">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="text-[11px] font-bold text-primary uppercase tracking-wider">
                            ${escapeHtml(categoryLabel(u.category))}
                        </span>
                        ${u.published_at_human ? `<span class="text-slate-300">·</span><span class="text-xs text-slate-400">${escapeHtml(u.published_at_human)}</span>` : ''}
                    </div>
                    <h3 class="text-lg font-bold text-slate-800 leading-snug mb-2 group-hover:text-primary transition-colors line-clamp-2">
                        ${escapeHtml(u.title)}
                    </h3>
                    ${u.excerpt ? `<p class="text-sm text-slate-500 leading-relaxed line-clamp-3">${escapeHtml(u.excerpt)}</p>` : ''}
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

        const activeCls = 'bg-gradient-to-br from-primary to-red-600 text-white shadow-md';
        const idleCls = 'bg-white border border-slate-200 text-slate-500 hover:border-primary hover:text-primary';
        const navCls = 'bg-slate-50 border border-slate-200 text-slate-500 hover:bg-primary hover:text-white hover:border-primary';

        let html = '';
        if (current > 1) {
            html += `<a href="${pageLink(current - 1)}" class="w-11 h-11 flex items-center justify-center rounded-xl transition-all ${navCls}">
                <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>`;
        }
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
                featuredEl.classList.add('hidden');
                gridHeadingEl.classList.add('hidden');
                emptyEl.classList.remove('hidden');
                paginationEl.innerHTML = '';
                return;
            }
            emptyEl.classList.add('hidden');

            // First page: promote the newest update to a big featured
            // card at the top. Subsequent pages just show the grid so
            // pagination doesn't lose the "featured slot" visually.
            let gridItems = items;
            if (page === 1 && items.length > 0) {
                featuredEl.innerHTML = renderFeatured(items[0]);
                featuredEl.classList.remove('hidden');
                gridItems = items.slice(1);
            } else {
                featuredEl.classList.add('hidden');
            }

            if (gridItems.length > 0) {
                gridEl.innerHTML = gridItems.map(renderCard).join('');
                gridHeadingEl.classList.remove('hidden');
                gridHeadingEl.classList.add('flex');
                if (countEl && meta.total) {
                    countEl.textContent = meta.total + (meta.total === 1 ? ' noutate în total' : ' noutăți în total');
                }
            } else {
                gridEl.innerHTML = '';
                gridHeadingEl.classList.add('hidden');
                gridHeadingEl.classList.remove('flex');
            }

            renderPagination(meta);
        } catch (e) {
            console.error('[noutati] failed to load:', e);
            gridEl.innerHTML = `<div class="col-span-full text-center py-12 text-slate-500">
                Nu am putut încărca noutățile. Te rugăm reîncearcă mai târziu.
            </div>`;
            featuredEl.classList.add('hidden');
            gridHeadingEl.classList.add('hidden');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', load);
    } else {
        load();
    }
})();
