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

    // Public profile link — reveal by adding inline-flex when removing
    // hidden, so the two display utilities never coexist on the element
    // (the IDE flags hidden+inline-flex together).
    if (data.artist?.slug) {
        const link = document.getElementById('public-profile-link');
        if (link) {
            link.href = '/artist/' + encodeURIComponent(data.artist.slug);
            link.classList.remove('hidden');
            link.classList.add('inline-flex');
        }
    }

    // KPI cards
    setText('kpi-upcoming', data.stats?.upcoming_events ?? 0);
    setText('kpi-total-events', data.stats?.total_events ?? 0);
    // "Fani pe Ambilet" — count from marketplace_customer_favorites,
    // a different number from the social-platform total below.
    setText('kpi-local-fans', formatCount(data.stats?.local_fans ?? 0));

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

    // Per-platform social-stats grid. Hidden if the artist has no
    // trackable IDs at all yet.
    renderSocialStats(data.social_stats);

    // Next event card uses the FIRST upcoming event (nearest-future).
    renderNextEvent(data.upcoming_events || []);

    // Two separate lists, distinct sections on the page:
    //   - upcoming-events-list (ASC by date, nearest first)
    //   - past-events-list (DESC by date, most recently ended first)
    renderEventList('upcoming-events-list', data.upcoming_events || [], data.is_linked, true);
    renderEventList('past-events-list', data.past_events || [], data.is_linked, false);
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

/**
 * Per-platform social stats grid. Each card has a `data-social-card`
 * key matching the payload's `platforms.<key>` shape; we look up the
 * per-card slots via [data-social-followers], [data-social-secondary],
 * etc. so the markup can be rearranged without JS changes.
 *
 * Visibility:
 *  - The whole section stays hidden if the artist has no platform IDs
 *    AND no synced numbers (likely a never-synced fresh account).
 *  - A card with `has_id=true` but `followers=0` shows "—" plus the
 *    "Adaugă … pentru a sincroniza" hint, so the artist understands
 *    the field is empty because of missing data, not because they're
 *    actually at zero.
 */
function renderSocialStats(stats) {
    const section = document.getElementById('social-stats-section');
    if (!section) return;
    if (!stats || !stats.platforms) return;

    const platforms = stats.platforms;
    const anyConfigured = Object.values(platforms).some(p => p?.has_id);
    const anyValue = Object.values(platforms).some(p => (p?.followers || 0) > 0);

    // No IDs anywhere AND no values — hide the whole block.
    if (!anyConfigured && !anyValue) {
        section.classList.add('hidden');
        return;
    }
    section.classList.remove('hidden');

    if (stats.updated_at) {
        const d = new Date(stats.updated_at);
        const updatedLabel = document.getElementById('social-stats-updated');
        if (updatedLabel) {
            updatedLabel.textContent = 'Ultima sincronizare: ' + d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
        }
    }

    // Spotify card — primary: followers, secondary: monthly_listeners
    paintSocialCard('spotify', platforms.spotify, p => ({
        primary: p.followers,
        secondary: p.monthly_listeners,
    }));

    // YouTube — primary: subscribers, secondary: total views
    paintSocialCard('youtube', platforms.youtube, p => ({
        primary: p.followers,
        secondary: p.total_views,
    }));

    // Facebook / Instagram / TikTok — only followers
    paintSocialCard('facebook', platforms.facebook);
    paintSocialCard('instagram', platforms.instagram);
    paintSocialCard('tiktok', platforms.tiktok);
}

/**
 * Populate one social-card. `metricsFn` is optional; when supplied, it
 * returns { primary, secondary } numbers. Default: primary=followers.
 */
function paintSocialCard(key, data, metricsFn) {
    const card = document.querySelector('[data-social-card="' + key + '"]');
    if (!card || !data) return;

    const metrics = metricsFn ? metricsFn(data) : { primary: data.followers, secondary: null };
    const followersEl = card.querySelector('[data-social-followers]');
    const secondaryWrap = card.querySelector('[data-social-secondary]');
    const secondaryValue = card.querySelector('[data-social-secondary-value]');
    const emptyHint = card.querySelector('[data-social-empty]');

    if (followersEl) {
        followersEl.textContent = metrics.primary > 0 ? formatCount(metrics.primary) : '—';
    }

    if (secondaryWrap && secondaryValue) {
        if (metrics.secondary > 0) {
            secondaryValue.textContent = formatCount(metrics.secondary);
            secondaryWrap.classList.remove('hidden');
        } else {
            secondaryWrap.classList.add('hidden');
        }
    }

    // Empty hint shows when the platform has no ID configured at all
    // OR when ID is set but no followers were synced yet.
    if (emptyHint) {
        if (!data.has_id || metrics.primary === 0) {
            emptyHint.classList.remove('hidden');
        } else {
            emptyHint.classList.add('hidden');
        }
    }

    // Dim the card slightly when nothing is configured, to push the
    // user's attention toward the platforms that ARE tracked.
    if (!data.has_id) {
        card.classList.add('opacity-60');
    } else {
        card.classList.remove('opacity-60');
    }
}

function renderNextEvent(events) {
    // events is now `upcoming_events` (already ASC by date), so the FIRST
    // entry is the nearest-future event. (Previously we received a mixed
    // ASC/DESC list and `find(is_upcoming)` could pick the wrong one.)
    const upcoming = events[0];
    const card = document.getElementById('next-event-card');
    const placeholder = document.getElementById('no-next-event');

    if (!upcoming) {
        card?.classList.add('hidden');
        placeholder?.classList.remove('hidden');
        return;
    }

    card?.classList.remove('hidden');
    placeholder?.classList.add('hidden');

    // Same date-handling logic as eventCardHtml — prefer starts_at;
    // build local-tz Date from event_date if no time.
    let date = null;
    if (upcoming.starts_at) {
        date = new Date(upcoming.starts_at);
    } else if (upcoming.event_date) {
        const [y, m, d] = upcoming.event_date.split('-').map(Number);
        if (y && m && d) date = new Date(y, m - 1, d);
    }

    if (date) {
        setText('next-event-day', String(date.getDate()));
        setText('next-event-month', date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '').toLowerCase());
    }
    setText('next-event-title', upcoming.title || 'Eveniment');

    const timeLabel = date && upcoming.has_time
        ? date.toLocaleString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : (date ? date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' }) : '—');
    setText('next-event-time', timeLabel);

    // KPI label "Următor: 12 mai"
    const label = document.getElementById('kpi-next-event-label');
    if (label && date) {
        label.textContent = 'Următor: ' + date.getDate() + ' ' + date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '');
        label.classList.remove('hidden');
    }
}

/**
 * Render an event list into the given DOM container. Used twice on the
 * dashboard:
 *   - upcoming-events-list  (events.is_upcoming === true)
 *   - past-events-list      (events.is_upcoming === false)
 *
 * `isUpcoming` is the section's flavor — used only to pick a friendlier
 * empty-state copy ("Niciun eveniment viitor" vs "Niciun eveniment trecut").
 */
function renderEventList(containerId, events, isLinked, isUpcoming) {
    const list = document.getElementById(containerId);
    if (!list) return;

    if (events.length === 0) {
        const msg = !isLinked
            ? 'Profilul nu este încă asociat.'
            : (isUpcoming ? 'Niciun eveniment viitor încă.' : 'Niciun eveniment trecut încă.');
        list.innerHTML = '<p class="py-8 text-center text-sm text-muted">' + escapeHtml(msg) + '</p>';
        return;
    }

    list.innerHTML = events.map(eventCardHtml).join('');
}

function eventCardHtml(event) {
    // Same date-handling logic as the events page: prefer starts_at when
    // we have proper time; build a local-tz date when only event_date is
    // available (to avoid the "midnight UTC → 03:00 Bucharest" bug).
    let date = null;
    if (event.starts_at) {
        date = new Date(event.starts_at);
    } else if (event.event_date) {
        const [y, m, d] = event.event_date.split('-').map(Number);
        if (y && m && d) date = new Date(y, m - 1, d);
    }

    const day = date ? date.getDate() : '—';
    const month = date ? date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '').toLowerCase() : '';
    const dateLabel = date
        ? (event.has_time
            ? date.toLocaleString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
            : date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }))
        : '—';

    // Ambilet event URL is /bilete/{slug}, NOT /event/{slug}.
    const eventUrl = event.slug ? '/bilete/' + encodeURIComponent(event.slug) : '/artist/cont/evenimente';

    return ''
        + '<a href="' + escapeAttr(eventUrl) + '" target="_blank" rel="noopener" class="group flex items-center gap-4 rounded-xl p-4 transition-colors hover:bg-surface">'
        + '<div class="flex h-16 w-16 flex-shrink-0 flex-col items-center justify-center rounded-xl bg-gradient-to-br from-primary/10 to-accent/10">'
        + '<span class="text-xl font-extrabold leading-none text-primary">' + day + '</span>'
        + '<span class="mt-1 text-xs uppercase text-muted">' + escapeHtml(month) + '</span>'
        + '</div>'
        + '<div class="min-w-0 flex-1">'
        + '<h4 class="truncate font-semibold text-secondary group-hover:text-primary">' + escapeHtml(event.title || 'Eveniment') + '</h4>'
        + '<p class="truncate text-sm text-muted">' + escapeHtml(dateLabel)
        + (event.venue_name ? ' • ' + escapeHtml(event.venue_name) : '')
        + (event.city ? ', ' + escapeHtml(event.city) : '')
        + '</p>'
        + '</div>'
        + (event.is_upcoming
            ? '<span class="hidden flex-shrink-0 rounded-full bg-success/10 px-2 py-1 text-xs font-medium text-success sm:inline">Viitor</span>'
            : '<span class="hidden flex-shrink-0 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 sm:inline">Trecut</span>')
        + '</a>';
}

function escapeAttr(s) { return escapeHtml(s); }

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
