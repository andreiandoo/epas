<?php
/**
 * Organizer Dashboard Top Header Bar
 */

// Flag to skip JS component loading (header.js/footer.js) in scripts.php
$skipJsComponents = true;
?>

<?php if (defined('USE_STAGE_API') && USE_STAGE_API): ?>
<div class="py-1 text-xs font-bold tracking-wide text-center text-black bg-amber-500">
    STAGE API — Datele sunt de test
    <a href="?use_stage=0" class="ml-3 underline hover:no-underline">Dezactiveaza</a>
</div>
<?php endif; ?>

<!-- Top Header -->
<header class="sticky top-0 z-50 border-b border-slate-700 bg-slate-900">
    <div class="flex items-center justify-between h-16 px-4 lg:px-8">
        <!-- Mobile menu button -->
        <button onclick="toggleSidebar()" class="p-2 -ml-2 transition-colors rounded-lg lg:hidden hover:bg-surface">
            <svg class="w-6 h-6 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <!-- Search -->
        <div class="items-center flex-1 hidden max-w-md md:flex">
            <div class="relative w-full" id="header-search-container">
                <svg class="absolute w-5 h-5 -translate-y-1/2 text-muted left-3 top-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="header-search-input" placeholder="Cauta evenimente, bilete, participanti..."
                    class="w-full pl-10 pr-4 py-2.5 bg-surface border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all"
                    autocomplete="off">
                <div id="header-search-results" class="absolute left-0 right-0 z-50 hidden mt-1 overflow-hidden overflow-y-auto bg-white border shadow-xl top-full rounded-xl border-border max-h-80"></div>
            </div>
        </div>

        <!-- Right Actions -->
        <div class="flex items-center gap-2">
            <!-- Quick Create -->
            <a href="/organizator/events?action=create" class="items-center hidden gap-2 px-4 py-2 text-sm font-semibold text-white btn btn-primary bg-primary sm:flex rounded-xl mobile:flex">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Eveniment nou
            </a>

            <!-- Notifications -->
            <div class="relative dropdown">
                <button onclick="this.parentElement.classList.toggle('active')" class="relative p-2 transition-colors rounded-xl hover:bg-surface">
                    <svg class="w-6 h-6 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span id="notification-badge" class="hidden absolute top-1 right-1 w-2.5 h-2.5 bg-primary rounded-full badge-pulse"></span>
                </button>
                <div class="absolute right-0 z-50 mt-2 overflow-hidden bg-white border shadow-xl dropdown-menu top-full w-80 rounded-2xl border-border">
                    <div class="flex items-center justify-between p-4 border-b border-border">
                        <h3 class="font-semibold text-secondary">Notificari</h3>
                        <span id="notification-count" class="text-xs font-medium text-primary">0 noi</span>
                    </div>
                    <div id="notifications-list" class="overflow-y-auto max-h-80">
                        <div class="p-6 text-sm text-center text-muted">
                            Nu ai notificari noi
                        </div>
                    </div>
                    <div class="p-3 text-center border-t border-border">
                        <a href="/organizator/notifications" class="text-sm font-medium text-primary">Vezi toate notificarile</a>
                    </div>
                </div>
            </div>

            <!-- User Menu -->
            <div class="relative dropdown">
                <button onclick="this.parentElement.classList.toggle('active')" class="flex items-center gap-2 p-1.5 transition-colors">
                    <div class="flex items-center justify-center bg-white rounded-full w-9 h-9">
                        <span id="topbar-org-initials" class="text-sm font-bold text-primary">--</span>
                    </div>
                    <svg class="hidden w-4 h-4 text-muted sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="absolute right-0 z-50 w-56 py-2 mt-2 overflow-hidden bg-white border shadow-lg dropdown-menu top-full rounded-xl border-border">
                    <div class="px-4 py-3 border-b border-border">
                        <p id="topbar-org-name" class="font-semibold text-secondary">Organizator</p>
                        <p id="topbar-org-email" class="text-xs text-muted">email@example.com</p>
                    </div>
                    <a href="/organizator/settings" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Setari cont
                    </a>
                    <a href="/organizator/help" class="flex items-center gap-2 px-4 py-2 text-sm text-secondary hover:bg-surface">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Ajutor & suport
                    </a>
                    <hr class="my-2 border-border">
                    <button onclick="AmbiletAuth.logoutOrganizer()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-error hover:bg-surface">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Deconectare
                    </button>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Pending Account Banner -->
<div id="pending-account-banner" class="hidden border-b bg-warning/10 border-warning/30">
    <div class="flex items-center justify-between gap-4 px-4 py-3 lg:px-8">
        <div class="flex items-center gap-3">
            <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-full bg-warning/20">
                <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <p class="text-sm font-semibold text-secondary">Contul tau este in asteptare</p>
                <p class="text-xs text-muted">Pentru a activa contul, completeaza profilul si incarca documentele necesare (CI/CUI).</p>
            </div>
        </div>
        <a href="/organizator/setari#contract" class="px-4 py-2 text-sm rounded-lg btn btn-warning whitespace-nowrap">
            Completeaza acum
        </a>
    </div>
</div>

<script>
// Populate topbar with logged-in organizer data
(function() {
    try {
        const orgData = JSON.parse(localStorage.getItem('ambilet_organizer_data') || 'null');
        if (orgData) {
            const nameEl = document.getElementById('topbar-org-name');
            const emailEl = document.getElementById('topbar-org-email');
            const initialsEl = document.getElementById('topbar-org-initials');
            const pendingBanner = document.getElementById('pending-account-banner');

            const name = orgData.name || orgData.company_name || 'Organizator';
            const email = orgData.email || '';

            if (nameEl) nameEl.textContent = name;
            if (emailEl) emailEl.textContent = email;
            if (initialsEl) {
                const parts = name.trim().split(/\s+/);
                const initials = parts.length > 1
                    ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
                    : name.substring(0, 2).toUpperCase();
                initialsEl.textContent = initials;
            }

            // Show pending account banner if status is not active
            if (pendingBanner && orgData.status && orgData.status !== 'active') {
                pendingBanner.classList.remove('hidden');
            }
        }
    } catch (e) {}
})();

// Header instant search functionality
(function() {
    let headerSearchCache = null;
    let searchDebounceTimer = null;

    window.addEventListener('load', function() {
        const searchInput = document.getElementById('header-search-input');
        const searchResults = document.getElementById('header-search-results');
        if (!searchInput || !searchResults) return;

        // Load events for search
        async function loadSearchData() {
            if (headerSearchCache) return headerSearchCache;
            try {
                if (typeof AmbiletAPI === 'undefined') return [];
                const response = await AmbiletAPI.get('/organizer/events');
                headerSearchCache = response.data || [];
                return headerSearchCache;
            } catch (e) {
                console.error('Failed to load search data:', e);
                return [];
            }
        }

        // Search and render results
        async function performSearch(query) {
            if (query.length < 3) {
                searchResults.classList.add('hidden');
                return;
            }

            const events = await loadSearchData();
            const q = query.toLowerCase();

            const results = events.filter(event => {
                const name = (event.name || event.title || '').toLowerCase();
                const venue = (event.venue_name || '').toLowerCase();
                const city = (event.venue_city || '').toLowerCase();
                return name.includes(q) || venue.includes(q) || city.includes(q);
            }).slice(0, 8); // Limit to 8 results

            if (results.length === 0) {
                searchResults.innerHTML = '<div class="p-4 text-sm text-center text-muted">Niciun rezultat gasit</div>';
                searchResults.classList.remove('hidden');
                return;
            }

            const fmtSearchDate = (d) => {
                if (!d) return '';
                const dt = new Date(d);
                if (isNaN(dt)) return '';
                const months = ['ian','feb','mar','apr','mai','iun','iul','aug','sep','oct','nov','dec'];
                return `${dt.getDate()} ${months[dt.getMonth()]} ${dt.getFullYear()}`;
            };

            searchResults.innerHTML = results.map(event => {
                const statusColors = { published: 'success', draft: 'warning', ended: 'muted', pending_review: 'info', cancelled: 'error' };
                const statusLabels = { published: 'Publicat', draft: 'Ciornă', ended: 'Încheiat', pending_review: 'În așteptare', cancelled: 'Anulat' };
                const status = event.status || 'draft';
                const dateStr = fmtSearchDate(event.starts_at || event.start_date);
                const locationStr = [event.venue_name, event.venue_city].filter(Boolean).join(', ');
                const subLine = [dateStr, locationStr].filter(Boolean).join(' · ');
                return `
                    <a href="/organizator/event/${event.id}?action=edit" class="flex items-center gap-3 p-3 transition-colors border-b hover:bg-surface border-border last:border-0">
                        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 rounded-lg bg-primary/10">
                            <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate text-secondary">${event.name || event.title}</p>
                            <p class="text-xs truncate text-muted">${subLine}</p>
                        </div>
                        <span class="badge badge-${statusColors[status] || 'secondary'} text-xs">${statusLabels[status] || status}</span>
                    </a>
                `;
            }).join('');

            searchResults.classList.remove('hidden');
        }

        // Debounced search
        searchInput.addEventListener('input', function() {
            clearTimeout(searchDebounceTimer);
            const query = this.value.trim();
            searchDebounceTimer = setTimeout(() => performSearch(query), 200);
        });

        // Close results on click outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#header-search-container')) {
                searchResults.classList.add('hidden');
            }
        });

        // Show results again on focus if there's a query
        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length >= 3) {
                performSearch(this.value.trim());
            }
        });
    });
})();
</script>
