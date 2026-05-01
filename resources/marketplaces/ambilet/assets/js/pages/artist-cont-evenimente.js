/**
 * Artist Account — Events list
 *
 * Strategy: fetch ALL events for the artist (filter=all, per_page=100) on
 * load. The 4 stats counters and the search/city/sort filters all run
 * client-side over that set. For artists with >100 events we'd need to
 * fall back to server-side pagination, but realistically that's a rare
 * upper bound for this page.
 */

const State = {
    events: [],
    statusFilter: 'upcoming',
    search: '',
    cityFilter: '',
    sort: 'date_asc',
};

window.addEventListener('ambilet:artist-cont:ready', () => {
    fetchAllEvents();
    wireFilters();
});

async function fetchAllEvents() {
    try {
        const res = await AmbiletAPI.artist.getEvents({ filter: 'all', per_page: 100 });
        if (!res.success) {
            renderError('Eroare la încărcare.');
            return;
        }
        State.events = res.data || [];
        renderStats();
        renderCityOptions();
        renderEvents();
    } catch (err) {
        renderError(err.message || 'Eroare la încărcare.');
    }
}

function wireFilters() {
    document.querySelectorAll('.filter-status').forEach(btn => {
        btn.addEventListener('click', () => {
            const newFilter = btn.dataset.filterStatus;
            const filterChanged = State.statusFilter !== newFilter;
            State.statusFilter = newFilter;
            // Past events read more naturally newest-first (cele mai recent
            // încheiate primele); upcoming events read better closest-first.
            // Auto-pick the natural sort when the filter changes — the user
            // can still override via the sort dropdown after.
            if (filterChanged) {
                State.sort = (newFilter === 'past') ? 'date_desc' : 'date_asc';
                const sortEl = document.getElementById('filter-sort');
                if (sortEl) sortEl.value = State.sort;
            }
            updateStatusButtons();
            renderEvents();
        });
    });

    document.getElementById('filter-search')?.addEventListener('input', e => {
        State.search = e.target.value;
        renderEvents();
    });

    document.getElementById('filter-city')?.addEventListener('change', e => {
        State.cityFilter = e.target.value;
        renderEvents();
    });

    document.getElementById('filter-sort')?.addEventListener('change', e => {
        State.sort = e.target.value;
        renderEvents();
    });

    document.getElementById('reset-filters-btn')?.addEventListener('click', () => {
        State.statusFilter = 'all';
        State.search = '';
        State.cityFilter = '';
        State.sort = 'date_asc';
        document.getElementById('filter-search').value = '';
        document.getElementById('filter-city').value = '';
        document.getElementById('filter-sort').value = 'date_asc';
        updateStatusButtons();
        renderEvents();
    });
}

function updateStatusButtons() {
    document.querySelectorAll('.filter-status').forEach(btn => {
        const active = btn.dataset.filterStatus === State.statusFilter;
        btn.classList.toggle('bg-primary', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('shadow-sm', active);
        btn.classList.toggle('text-muted', !active);
        btn.classList.toggle('hover:text-secondary', !active);
        // Pill count background
        btn.querySelector('.filter-count')?.classList.toggle('bg-white/20', active);
        btn.querySelector('.filter-count')?.classList.toggle('bg-border', !active);
    });
}

// ============================================================================
// Stats summary
// ============================================================================
function renderStats() {
    const upcoming = State.events.filter(e => e.is_upcoming).length;
    const past = State.events.length - upcoming;
    const cities = new Set(State.events.map(e => e.city).filter(Boolean));

    setText('stat-total', State.events.length);
    setText('stat-upcoming', upcoming);
    setText('stat-past', past);
    setText('stat-cities', cities.size);

    // Update counts on filter pills
    const upBtn = document.querySelector('.filter-status[data-filter-status="upcoming"] .filter-count');
    const pastBtn = document.querySelector('.filter-status[data-filter-status="past"] .filter-count');
    if (upBtn) upBtn.textContent = upcoming;
    if (pastBtn) pastBtn.textContent = past;
}

function renderCityOptions() {
    const select = document.getElementById('filter-city');
    if (!select) return;
    const cities = [...new Set(State.events.map(e => e.city).filter(Boolean))].sort();
    cities.forEach(city => {
        const opt = document.createElement('option');
        opt.value = city;
        opt.textContent = city;
        select.appendChild(opt);
    });
}

// ============================================================================
// Render filtered list
// ============================================================================
function renderEvents() {
    const list = document.getElementById('events-list');
    const emptyFiltered = document.getElementById('empty-filtered');
    const emptyAll = document.getElementById('empty-all');
    if (!list) return;

    // No events at all
    if (State.events.length === 0) {
        list.innerHTML = '';
        emptyFiltered.classList.add('hidden');
        emptyAll.classList.remove('hidden');
        return;
    }

    let filtered = [...State.events];

    if (State.statusFilter === 'upcoming') {
        filtered = filtered.filter(e => e.is_upcoming);
    } else if (State.statusFilter === 'past') {
        filtered = filtered.filter(e => !e.is_upcoming);
    }

    if (State.cityFilter) {
        filtered = filtered.filter(e => e.city === State.cityFilter);
    }

    if (State.search.trim()) {
        const q = State.search.toLowerCase().trim();
        filtered = filtered.filter(e =>
            (e.title || '').toLowerCase().includes(q)
            || (e.venue_name || '').toLowerCase().includes(q)
            || (e.city || '').toLowerCase().includes(q)
            || (e.organizer_name || '').toLowerCase().includes(q)
            || (e.short_description || '').toLowerCase().includes(q)
        );
    }

    filtered.sort((a, b) => {
        const da = new Date(a.event_date || a.starts_at).getTime();
        const db = new Date(b.event_date || b.starts_at).getTime();
        return State.sort === 'date_desc' ? db - da : da - db;
    });

    if (filtered.length === 0) {
        list.innerHTML = '';
        emptyFiltered.classList.remove('hidden');
        emptyAll.classList.add('hidden');
        return;
    }

    emptyFiltered.classList.add('hidden');
    emptyAll.classList.add('hidden');
    list.innerHTML = filtered.map(eventCardHtml).join('');
}

function eventCardHtml(event) {
    // Prefer starts_at (full ISO datetime with TZ) over event_date (date-only,
    // which JS would parse as midnight UTC and render as 03:00 in Bucharest).
    const dateIso = event.starts_at || event.event_date;
    const date = dateIso ? new Date(dateIso) : null;
    const day = date ? date.getDate() : '—';
    const month = date ? date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '').toLowerCase() : '';
    const fullDate = formatEventDate(event);

    // Ambilet event URL pattern: /bilete/{slug}, NOT /event/{slug}.
    const eventUrl = event.slug ? '/bilete/' + encodeURIComponent(event.slug) : '#';
    const posterUrl = event.poster_url ? resolveStorageUrl(event.poster_url) : '';

    const statusBadge = event.is_upcoming
        ? ''
        : '<span class="inline-flex items-center rounded-full bg-secondary/90 px-2 py-1 text-xs font-semibold text-white">Încheiat</span>';

    // Ticket-progress block — only render if we have meaningful capacity data.
    const hasTickets = event.tickets_total && event.tickets_total > 0;
    const sold = event.tickets_sold || 0;
    const total = event.tickets_total || 0;
    const pct = hasTickets ? Math.min(100, Math.round((sold / total) * 100)) : 0;
    const pctColor = pct >= 90 ? 'bg-success' : (pct >= 50 ? 'bg-accent' : 'bg-primary');
    const pctLabelColor = pct >= 90 ? 'text-success' : (pct >= 50 ? 'text-accent' : 'text-muted');

    const ticketBlock = hasTickets
        ? '<div class="flex-1 min-w-0 sm:max-w-md">'
            + '<div class="mb-1.5 flex items-baseline justify-between gap-2">'
            + '<span class="text-xs font-semibold uppercase tracking-wider text-muted">Bilete vândute</span>'
            + '<span class="text-sm font-bold text-secondary">'
            + sold.toLocaleString('ro-RO')
            + '<span class="font-normal text-muted"> / ' + total.toLocaleString('ro-RO') + '</span>'
            + '<span class="ml-2 text-xs font-semibold ' + pctLabelColor + '">(' + pct + '%)</span>'
            + '</span>'
            + '</div>'
            + '<div class="h-2 overflow-hidden rounded-full bg-surface">'
            + '<div class="h-full rounded-full transition-all duration-500 ' + pctColor + '" style="width: ' + pct + '%"></div>'
            + '</div>'
            + '</div>'
        : '<div class="flex-1"></div>';

    // Footer location info: venue_name • city + organizer
    const venueRow = (event.venue_name || event.city)
        ? '<span class="flex items-center gap-1.5">'
            + '<svg class="h-4 w-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
            + (event.venue_name ? '<span class="font-medium text-secondary">' + escapeHtml(event.venue_name) + '</span>' : '')
            + (event.venue_name && event.city ? '<span class="text-muted">•</span>' : '')
            + (event.city ? '<span>' + escapeHtml(event.city) + '</span>' : '')
        + '</span>'
        : '';

    const organizerRow = event.organizer_name
        ? '<span class="flex items-center gap-1.5">'
            + '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'
            + 'organizat de <span class="font-medium text-secondary">' + escapeHtml(event.organizer_name) + '</span>'
        + '</span>'
        : '';

    return ''
        + '<article class="overflow-hidden rounded-2xl border border-border bg-white transition-colors hover:border-primary/30">'
        + '<div class="flex flex-col md:flex-row">'
        // Poster
        + '<a href="' + escapeAttr(eventUrl) + '" target="_blank" rel="noopener" class="group relative flex-shrink-0 md:w-48">'
        + '<div class="aspect-[4/3] overflow-hidden bg-gradient-to-br from-secondary to-primary-dark md:h-full md:aspect-auto">'
        + (posterUrl
            ? '<img src="' + escapeAttr(posterUrl) + '" alt="" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">'
            : '<div class="flex h-full w-full items-center justify-center text-white/30"><svg class="h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg></div>')
        + '</div>'
        // Date badge
        + '<div class="absolute left-3 top-3 min-w-[3rem] rounded-xl bg-white px-2.5 py-1.5 text-center shadow-lg">'
        + '<p class="text-lg font-extrabold leading-none text-primary">' + day + '</p>'
        + '<p class="mt-0.5 text-[10px] uppercase tracking-wider text-muted">' + escapeHtml(month) + '</p>'
        + '</div>'
        // Status badge (top right)
        + (statusBadge ? '<div class="absolute right-3 top-3">' + statusBadge + '</div>' : '')
        + '</a>'
        // Body
        + '<div class="flex flex-1 flex-col p-5 lg:p-6">'
        + '<div class="flex-1">'
        + '<div class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-muted">'
        + '<svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
        + '<span>' + escapeHtml(fullDate) + '</span>'
        + '</div>'
        + '<h3 class="mb-2 text-lg font-bold leading-tight text-secondary lg:text-xl">'
        + '<a href="' + escapeAttr(eventUrl) + '" target="_blank" rel="noopener" class="hover:text-primary transition-colors">' + escapeHtml(event.title || 'Eveniment') + '</a>'
        + '</h3>'
        + (event.short_description
            ? '<p class="mb-3 line-clamp-2 text-sm text-muted">' + escapeHtml(event.short_description) + '</p>'
            : '')
        + (venueRow || organizerRow
            ? '<div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">' + venueRow + organizerRow + '</div>'
            : '')
        + '</div>'
        // Footer: ticket progress (when known) + view-event CTA
        + '<div class="mt-4 flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between">'
        + ticketBlock
        + '<a href="' + escapeAttr(eventUrl) + '" target="_blank" rel="noopener" class="btn btn-primary btn-sm whitespace-nowrap self-start sm:self-auto">'
        + '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>'
        + 'Vezi pagina eveniment'
        + '</a>'
        + '</div>'
        + '</div>'
        + '</div>'
        + '</article>';
}

/**
 * Format an event's date for the card header. Uses starts_at when we
 * have proper time info; falls back to date-only when not.
 */
function formatEventDate(event) {
    if (!event.event_date && !event.starts_at) return '—';
    try {
        if (event.starts_at && event.has_time) {
            const d = new Date(event.starts_at);
            const day = d.toLocaleDateString('ro-RO', { weekday: 'long' });
            const dateStr = d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
            const time = d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
            return day + ', ' + dateStr + ' • ' + time;
        }
        // Date-only fallback — `event_date` is "YYYY-MM-DD"; build a
        // local-tz Date so toLocaleDateString doesn't shift the day.
        const [y, m, d] = (event.event_date || '').split('-').map(Number);
        if (!y || !m || !d) return event.event_date || event.starts_at || '—';
        const local = new Date(y, m - 1, d);
        const day = local.toLocaleDateString('ro-RO', { weekday: 'long' });
        const dateStr = local.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
        return day + ', ' + dateStr;
    } catch (e) { return event.event_date || event.starts_at || '—'; }
}

function renderError(msg) {
    const list = document.getElementById('events-list');
    if (!list) return;
    list.innerHTML = '<div class="rounded-2xl border border-border bg-white p-12 text-center"><p class="text-red-600">' + escapeHtml(msg) + '</p></div>';
}

// ============================================================================
// Helpers
// ============================================================================
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function formatFullDate(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        const day = d.toLocaleDateString('ro-RO', { weekday: 'long' });
        const dateStr = d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
        const time = d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
        return day + ', ' + dateStr + ' • ' + time;
    } catch (e) { return iso; }
}

function resolveStorageUrl(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;
    const base = (typeof window.AMBILET !== 'undefined' && window.AMBILET.storageUrl) || 'https://core.tixello.com/storage';
    return base.replace(/\/$/, '') + '/' + path.replace(/^\/+/, '');
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
function escapeAttr(s) { return escapeHtml(s); }
