/**
 * Artist Account — Dashboard renderer
 * Waits for artist-cont-shared.js to enforce auth + populate the sidebar,
 * then fetches /artist/dashboard and populates KPIs, profile-completion
 * bar, next-event card, recent events, and account info.
 */
window.addEventListener('ambilet:artist-cont:ready', () => {
    AmbiletAPI.artist.getDashboard()
        .then((res) => {
            if (!res || !res.success || !res.data) return;
            renderDashboard(res.data);
        })
        .catch(() => {
            const list = document.getElementById('recent-events-list');
            if (list) list.innerHTML = '<p class="py-8 text-center text-sm text-red-500">Eroare la încărcarea datelor.</p>';
        });
});

const COMPLETION_LABELS = {
    main_image_url: 'Imagine principală',
    logo_url: 'Logo',
    portrait_url: 'Portret',
    bio_html: 'Biografie',
    country: 'Țară',
    city: 'Oraș',
    founded_year: 'An înființare',
    website: 'Website',
    facebook_url: 'Facebook',
    instagram_url: 'Instagram',
    email: 'Email contact',
    phone: 'Telefon',
    artist_types: 'Tipuri',
    artist_genres: 'Genuri',
};

const STATUS_LABELS = {
    active: { label: 'Activ', color: 'text-success' },
    pending: { label: 'În review', color: 'text-warning' },
    rejected: { label: 'Respins', color: 'text-error' },
    suspended: { label: 'Suspendat', color: 'text-muted' },
};

function renderDashboard(data) {
    // Greeting (desktop + mobile)
    const firstName = data.account?.first_name || '';
    const greeting = firstName ? `Bun venit, ${firstName}!` : 'Bun venit!';
    setText('dashboard-greeting', greeting);
    setText('dashboard-greeting-mobile', greeting);

    // Public profile link
    if (data.artist?.slug) {
        const link = document.getElementById('public-profile-link');
        if (link) {
            link.href = '/artist/' + encodeURIComponent(data.artist.slug);
            link.classList.remove('hidden');
        }
    }

    // KPI cards
    setText('kpi-upcoming', data.stats?.upcoming_events ?? 0);
    setText('kpi-total-events', data.stats?.total_events ?? 0);
    setText('kpi-followers', formatCount(data.stats?.total_followers ?? 0));

    const status = data.account?.status ?? 'unknown';
    const statusInfo = STATUS_LABELS[status] || { label: status, color: 'text-secondary' };
    const statusEl = document.getElementById('kpi-status');
    if (statusEl) {
        statusEl.textContent = statusInfo.label;
        statusEl.className = 'text-2xl font-bold ' + statusInfo.color;
    }

    // Account info card
    setText('account-email', data.account?.email || '—');
    setText('account-email-verified', data.account?.is_email_verified ? 'Da' : 'Nu');
    setText('account-last-login', data.account?.last_login_at ? formatDateTime(data.account.last_login_at) : 'niciodată');
    setText('account-linked-artist', data.artist?.name || '—');

    // Unlinked notice
    if (!data.is_linked) {
        document.getElementById('unlinked-notice')?.classList.remove('hidden');
    }

    // Profile completion
    if (data.is_linked && data.profile_completion) {
        renderCompletion(data.profile_completion);
    } else {
        // Hide the completion card when there's no linked profile yet.
        document.getElementById('completion-card')?.classList.add('hidden');
    }

    // Next event (first upcoming from recent_events)
    renderNextEvent(data.recent_events || []);

    // Recent events list
    renderRecentEvents(data.recent_events || [], data.is_linked);
}

function renderCompletion(completion) {
    const pct = completion.percentage || 0;
    setText('completion-percentage', pct + '%');
    const bar = document.getElementById('completion-bar');
    if (bar) bar.style.width = pct + '%';

    const fields = completion.fields || {};
    const container = document.getElementById('completion-fields');
    if (!container) return;
    container.innerHTML = Object.entries(fields).map(([key, filled]) => {
        const label = COMPLETION_LABELS[key] || key;
        const icon = filled
            ? '<svg class="h-4 w-4 text-success" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
            : '<svg class="h-4 w-4 text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        return ''
            + '<div class="flex items-center gap-2">'
            + icon
            + '<span class="' + (filled ? 'text-secondary' : 'text-muted') + '">' + escapeHtml(label) + '</span>'
            + '</div>';
    }).join('');
}

function renderNextEvent(events) {
    const upcoming = events.find(e => e.is_upcoming);
    const card = document.getElementById('next-event-card');
    const placeholder = document.getElementById('no-next-event');

    if (!upcoming) {
        card?.classList.add('hidden');
        placeholder?.classList.remove('hidden');
        return;
    }

    card?.classList.remove('hidden');
    placeholder?.classList.add('hidden');

    const date = new Date(upcoming.event_date || upcoming.starts_at);
    setText('next-event-day', String(date.getDate()));
    setText('next-event-month', date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '').toLowerCase());
    setText('next-event-title', upcoming.title || 'Eveniment');
    setText('next-event-time', formatFullDate(upcoming.event_date || upcoming.starts_at));

    // KPI label "Următor: 12 mai"
    const label = document.getElementById('kpi-next-event-label');
    if (label) {
        label.textContent = 'Următor: ' + date.getDate() + ' ' + date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '');
        label.classList.remove('hidden');
    }
}

function renderRecentEvents(events, isLinked) {
    const list = document.getElementById('recent-events-list');
    if (!list) return;

    if (events.length === 0) {
        list.innerHTML = '<p class="py-8 text-center text-sm text-muted">'
            + (isLinked ? 'Nu există evenimente încă.' : 'Profilul nu este încă asociat.')
            + '</p>';
        return;
    }

    list.innerHTML = events.map(eventCardHtml).join('');
}

function eventCardHtml(event) {
    const date = new Date(event.event_date || event.starts_at);
    const day = date.getDate();
    const month = date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '').toLowerCase();
    const dateLabel = formatDate(event.event_date || event.starts_at);

    return ''
        + '<a href="/artist/cont/evenimente" class="group flex items-center gap-4 rounded-xl p-4 transition-colors hover:bg-surface">'
        + '<div class="flex h-16 w-16 flex-shrink-0 flex-col items-center justify-center rounded-xl bg-gradient-to-br from-primary/10 to-accent/10">'
        + '<span class="text-xl font-extrabold leading-none text-primary">' + day + '</span>'
        + '<span class="mt-1 text-xs uppercase text-muted">' + escapeHtml(month) + '</span>'
        + '</div>'
        + '<div class="min-w-0 flex-1">'
        + '<h4 class="truncate font-semibold text-secondary group-hover:text-primary">' + escapeHtml(event.title || 'Eveniment') + '</h4>'
        + '<p class="truncate text-sm text-muted">' + escapeHtml(dateLabel) + '</p>'
        + '</div>'
        + (event.is_upcoming
            ? '<span class="hidden flex-shrink-0 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700 sm:inline">Viitor</span>'
            : '<span class="hidden flex-shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 sm:inline">Trecut</span>')
        + '</a>';
}

// ============================================================================
// Helpers
// ============================================================================
function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
}

function formatCount(n) {
    if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.0', '') + 'M';
    if (n >= 1_000) return (n / 1_000).toFixed(1).replace('.0', '') + 'K';
    return String(n);
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) { return iso; }
}

function formatDateTime(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) { return iso; }
}

function formatFullDate(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        const dateStr = d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long' });
        const time = d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
        return dateStr + ' • ' + time;
    } catch (e) { return iso; }
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
