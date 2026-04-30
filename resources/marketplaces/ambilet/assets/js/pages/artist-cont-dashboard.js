/**
 * Artist Account — Dashboard renderer
 * Waits for the shared bootstrap to finish auth + sidebar render, then
 * fetches /artist/dashboard and populates the KPIs, completion bar, and
 * recent-events list.
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

function renderDashboard(data) {
    // Greeting line
    const greetingEl = document.getElementById('dashboard-greeting');
    if (greetingEl && data.account) {
        const firstName = data.account.first_name || '';
        greetingEl.textContent = firstName ? `Bun venit, ${firstName}!` : 'Bun venit!';
    }

    // KPI cards
    setText('kpi-upcoming', data.stats?.upcoming_events ?? 0);
    setText('kpi-total-events', data.stats?.total_events ?? 0);
    setText('kpi-followers', formatCount(data.stats?.total_followers ?? 0));
    setText('kpi-status', renderStatus(data.account?.status ?? 'unknown'));

    // Completion banner — only when we have a linked profile
    if (data.is_linked && data.profile_completion) {
        document.getElementById('completion-banner')?.classList.remove('hidden');
        const pct = data.profile_completion.percentage || 0;
        setText('completion-percentage', pct + '%');
        const bar = document.getElementById('completion-bar');
        if (bar) bar.style.width = pct + '%';
    } else if (!data.is_linked) {
        document.getElementById('unlinked-notice')?.classList.remove('hidden');
    }

    // Recent events
    const list = document.getElementById('recent-events-list');
    if (!list) return;

    const events = data.recent_events || [];
    if (events.length === 0) {
        list.innerHTML = '<p class="py-8 text-center text-sm text-muted">' + (data.is_linked ? 'Nu există evenimente încă.' : 'Profilul nu este încă asociat.') + '</p>';
        return;
    }

    list.innerHTML = events.map(eventCardHtml).join('');
}

function eventCardHtml(event) {
    const date = formatDate(event.event_date || event.starts_at);
    const upcoming = !!event.is_upcoming;
    const badge = upcoming
        ? '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700">Viitor</span>'
        : '<span class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-600">Trecut</span>';

    return ''
        + '<div class="flex items-center gap-4 p-4 transition-colors border rounded-xl border-border hover:bg-surface">'
        + '<div class="flex-1 min-w-0">'
        + '<div class="flex items-center gap-2 mb-1">'
        + badge
        + '<span class="text-xs text-muted">' + escapeHtml(date) + '</span>'
        + '</div>'
        + '<p class="font-medium truncate text-secondary">' + escapeHtml(event.title || 'Eveniment') + '</p>'
        + '</div>'
        + '</div>';
}

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
        const d = new Date(iso);
        return d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' });
    } catch (e) {
        return iso;
    }
}

function renderStatus(status) {
    return {
        active: 'Activ',
        pending: 'În review',
        rejected: 'Respins',
        suspended: 'Suspendat',
    }[status] || status;
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}
