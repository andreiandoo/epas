/**
 * Noutăți (index) — month-grouped, infinite-scroll changelog.
 *
 * Fetches pages via AmbiletAPI.get('/system-updates?...') and appends
 * items into per-month sections. IntersectionObserver on a sentinel
 * ~400px above the page bottom triggers the next page as the visitor
 * approaches the fold. No pagination — scroll IS the pagination.
 *
 * Section-per-month structure lets months split across API pages
 * merge transparently: the Map cache keyed by "YYYY-MM" ensures a
 * second batch of the same month appends to the existing grid instead
 * of creating a duplicate heading.
 */
(function () {
    'use strict';

    const groupsEl = document.getElementById('noutati-groups');
    const initialSkeletonEl = document.getElementById('noutati-initial-skeleton');
    const loadingEl = document.getElementById('noutati-loading');
    const endEl = document.getElementById('noutati-end');
    const errorEl = document.getElementById('noutati-error');
    const retryBtn = document.getElementById('noutati-retry');
    const emptyEl = document.getElementById('noutati-empty');
    const sentinel = document.getElementById('noutati-sentinel');
    if (!groupsEl) return;

    // Romanian month names for section headings.
    const RO_MONTHS = [
        'IANUARIE', 'FEBRUARIE', 'MARTIE', 'APRILIE', 'MAI', 'IUNIE',
        'IULIE', 'AUGUST', 'SEPTEMBRIE', 'OCTOMBRIE', 'NOIEMBRIE', 'DECEMBRIE',
    ];

    // State — resets when the page (re)loads (e.g. category change).
    let currentPage = 0;                 // last successfully-loaded page
    let hasMorePages = true;
    let isLoading = false;
    const monthSections = new Map();     // "YYYY-MM" → wrapper element

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Category → short label for cards. Matches the detail page's
    // editorial phrasing so the whole surface uses one voice.
    function categoryLabel(cat) {
        return ({
            interfata:   'Interfață',
            organizator: 'Pentru Organizatori',
            client:      'Pentru Clienți',
        })[cat] || cat;
    }

    function isRecent(publishedAtIso) {
        if (!publishedAtIso) return false;
        const dt = new Date(publishedAtIso);
        if (isNaN(dt.getTime())) return false;
        return (Date.now() - dt.getTime()) < 7 * 24 * 60 * 60 * 1000;
    }

    function monthKey(iso) {
        const dt = new Date(iso);
        if (isNaN(dt.getTime())) return 'unknown';
        return `${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}`;
    }

    function monthLabelFor(iso) {
        const dt = new Date(iso);
        if (isNaN(dt.getTime())) return 'FĂRĂ DATĂ';
        return `${RO_MONTHS[dt.getMonth()]} ${dt.getFullYear()}`;
    }

    /**
     * Return the grid element for a given month, creating the section
     * on first use. Sections are appended in insertion order, which
     * matches the API's DESC ordering — no manual sort needed.
     */
    function getOrCreateMonthGrid(key, iso) {
        if (monthSections.has(key)) {
            return monthSections.get(key).querySelector('.noutati-month-grid');
        }

        const section = document.createElement('section');
        section.className = 'noutati-month-section';
        section.dataset.month = key;
        section.innerHTML = `
            <div class="flex items-center gap-4 mb-8">
                <div class="h-px bg-slate-200 flex-1"></div>
                <h2 class="text-xs md:text-sm font-black text-slate-400 tracking-[0.2em]">${escapeHtml(monthLabelFor(iso))}</h2>
                <div class="h-px bg-slate-200 flex-1"></div>
            </div>
            <div class="noutati-month-grid grid md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8"></div>
        `;
        groupsEl.appendChild(section);
        monthSections.set(key, section);
        return section.querySelector('.noutati-month-grid');
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

    async function loadPage(page) {
        if (isLoading || !hasMorePages) return;
        isLoading = true;
        errorEl.classList.add('hidden');

        // Show the spinner only for subsequent pages — the initial page
        // is covered by the PHP-rendered skeleton.
        if (page > 1) loadingEl.classList.remove('hidden');

        const cat = groupsEl.dataset.activeCategory || '';
        const query = new URLSearchParams();
        query.set('page', String(page));
        query.set('per_page', '12');
        if (cat) query.set('category', cat);

        try {
            const response = await AmbiletAPI.get('/system-updates?' + query.toString());
            const items = (response && response.data) || [];
            const meta = (response && response.meta) || {};

            // Page 1 special-casing: clear the initial skeleton the FIRST
            // time real data lands. If page 1 is empty AND we're at the
            // start, show the empty state.
            if (page === 1) {
                if (initialSkeletonEl) initialSkeletonEl.remove();
                if (items.length === 0) {
                    emptyEl.classList.remove('hidden');
                    endEl.classList.add('hidden');
                    hasMorePages = false;
                    return;
                }
                emptyEl.classList.add('hidden');
            }

            // Group + append. `insertAdjacentHTML('beforeend', ...)` is
            // ~3× faster than `innerHTML +=` because it doesn't reparse
            // the existing siblings.
            items.forEach(item => {
                const key = monthKey(item.published_at);
                const grid = getOrCreateMonthGrid(key, item.published_at);
                grid.insertAdjacentHTML('beforeend', renderCard(item));
            });

            currentPage = meta.current_page || page;
            hasMorePages = (meta.current_page || page) < (meta.last_page || page);

            if (!hasMorePages) {
                loadingEl.classList.add('hidden');
                // Only show "you've seen everything" if we actually
                // rendered at least one item.
                if (monthSections.size > 0) endEl.classList.remove('hidden');
            }
        } catch (e) {
            console.error('[noutati] failed to load page ' + page + ':', e);
            loadingEl.classList.add('hidden');
            errorEl.classList.remove('hidden');
            // Don't hardcode hasMorePages=false — visitor may retry.
        } finally {
            isLoading = false;
            if (!hasMorePages || errorEl.classList.contains('hidden') === false) {
                loadingEl.classList.add('hidden');
            }
        }
    }

    // IntersectionObserver on the sentinel — kicks off next page as
    // the visitor scrolls into a 400px buffer above the end.
    function initInfiniteScroll() {
        if (!('IntersectionObserver' in window) || !sentinel) {
            // Fallback: a scroll listener that checks manually. Rare
            // (all major browsers support IO since 2018).
            window.addEventListener('scroll', () => {
                const nearBottom = (window.innerHeight + window.scrollY)
                    >= (document.body.offsetHeight - 400);
                if (nearBottom && !isLoading && hasMorePages) {
                    loadPage(currentPage + 1);
                }
            }, { passive: true });
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !isLoading && hasMorePages) {
                    loadPage(currentPage + 1);
                }
            });
        }, { rootMargin: '400px 0px' });
        observer.observe(sentinel);
    }

    if (retryBtn) {
        retryBtn.addEventListener('click', () => {
            errorEl.classList.add('hidden');
            loadPage(currentPage + 1);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            loadPage(1);
            initInfiniteScroll();
        });
    } else {
        loadPage(1);
        initInfiniteScroll();
    }
})();
