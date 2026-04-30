/**
 * Artist Account — Events list
 * Two filter tabs (upcoming | past), paginated. Re-fetches when the
 * tab changes or when the user navigates to a different page.
 */
const EventsState = {
    filter: 'upcoming',
    page: 1,
};

window.addEventListener('ambilet:artist-cont:ready', () => {
    wireFilterTabs();
    fetchEvents();
});

function wireFilterTabs() {
    document.querySelectorAll('.filter-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            const filter = tab.dataset.filter;
            if (!filter || filter === EventsState.filter) return;

            EventsState.filter = filter;
            EventsState.page = 1;

            // Update active state
            document.querySelectorAll('.filter-tab').forEach((t) => {
                if (t === tab) {
                    t.classList.add('bg-primary', 'text-white');
                    t.classList.remove('text-secondary', 'hover:bg-surface');
                } else {
                    t.classList.remove('bg-primary', 'text-white');
                    t.classList.add('text-secondary', 'hover:bg-surface');
                }
            });

            fetchEvents();
        });
    });
}

async function fetchEvents() {
    const container = document.getElementById('events-container');
    if (!container) return;

    container.innerHTML = '<div class="p-12 text-center bg-white border rounded-2xl border-border"><p class="text-muted">Se încarcă…</p></div>';

    try {
        const res = await AmbiletAPI.artist.getEvents({
            filter: EventsState.filter,
            page: EventsState.page,
            per_page: 20,
        });

        if (!res.success) {
            container.innerHTML = errorEmpty('Eroare la încărcare.');
            return;
        }

        const events = res.data || [];
        if (events.length === 0) {
            container.innerHTML = errorEmpty(EventsState.filter === 'upcoming' ? 'Nu ai evenimente viitoare.' : 'Nu ai evenimente trecute.');
            return;
        }

        container.innerHTML = events.map(eventCardHtml).join('');
        renderPagination(res.meta || {});
    } catch (err) {
        container.innerHTML = errorEmpty(err.message || 'Eroare la încărcare.');
    }
}

function eventCardHtml(event) {
    const date = formatDate(event.event_date || event.starts_at);
    const upcoming = !!event.is_upcoming;
    const badge = upcoming
        ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Viitor</span>'
        : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Trecut</span>';

    return ''
        + '<div class="flex items-center gap-4 p-5 bg-white border rounded-2xl border-border">'
        + '<div class="flex-1 min-w-0">'
        + '<div class="flex items-center gap-2 mb-2">'
        + badge
        + '<span class="text-sm text-muted">' + escapeHtml(date) + '</span>'
        + '</div>'
        + '<p class="font-semibold truncate text-secondary">' + escapeHtml(event.title || 'Eveniment') + '</p>'
        + '</div>'
        + '</div>';
}

function renderPagination(meta) {
    const el = document.getElementById('events-pagination');
    if (!el) return;

    const { current_page = 1, last_page = 1, total = 0 } = meta;
    if (last_page <= 1) {
        el.classList.add('hidden');
        return;
    }

    el.classList.remove('hidden');
    el.classList.add('flex');
    el.innerHTML = ''
        + '<p class="text-sm text-muted">Pagina ' + current_page + ' din ' + last_page + ' (' + total + ' evenimente)</p>'
        + '<div class="flex gap-2">'
        + (current_page > 1 ? '<button data-page-action="prev" class="btn">← Anterior</button>' : '')
        + (current_page < last_page ? '<button data-page-action="next" class="btn">Următor →</button>' : '')
        + '</div>';

    el.querySelectorAll('[data-page-action]').forEach((btn) => {
        btn.addEventListener('click', () => {
            EventsState.page = btn.dataset.pageAction === 'next' ? current_page + 1 : current_page - 1;
            fetchEvents();
        });
    });
}

function errorEmpty(msg) {
    return '<div class="p-12 text-center bg-white border rounded-2xl border-border"><p class="text-muted">' + escapeHtml(msg) + '</p></div>';
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
    } catch (e) {
        return iso;
    }
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
