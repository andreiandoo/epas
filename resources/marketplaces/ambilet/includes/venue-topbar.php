<?php
/**
 * Venue Owner Top Header Bar
 *
 * Minimal shell — mobile menu button, page title placeholder, and a
 * multi-role switcher that appears when the user's session carries
 * tokens for more than one realm (client/organizer/venue-owner). The
 * switcher reads the cookies set by AmbiletMultiAuth.persistAllRoles()
 * at login so a user with multiple accounts can flip panels without
 * re-authenticating.
 *
 * Skip auto-loading of header.js / footer.js — venue shell coordinates
 * its own JS bundle via the page-level script include.
 */
$skipJsComponents = true;
?>

<!-- Top Header -->
<header class="sticky top-0 z-40 border-b border-slate-700 bg-slate-900">
    <div class="flex items-center justify-between h-16 px-4 lg:px-8">
        <!-- Mobile menu button -->
        <button onclick="toggleSidebar()" class="p-2 -ml-2 transition-colors rounded-lg lg:hidden bg-slate-800 hover:bg-slate-700">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>

        <!-- Page title (populated per page) -->
        <div class="flex-1 hidden lg:block">
            <h1 id="venue-page-title" class="text-lg font-semibold text-white"><?= isset($venuePageTitle) ? htmlspecialchars($venuePageTitle) : '' ?></h1>
        </div>

        <!-- Right cluster: role switcher + user menu -->
        <div class="flex items-center gap-2">
            <!-- Role switcher — hidden by default; venue-topbar.js unhides -->
            <!-- it once the cookie audit finds >1 authenticated realm.   -->
            <div id="venue-role-switcher" class="relative hidden">
                <button type="button" id="venue-role-switcher-btn"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white transition-colors bg-slate-800 rounded-lg hover:bg-slate-700">
                    <span class="hidden text-xs uppercase tracking-wider text-slate-400 sm:inline">Vezi ca</span>
                    <span id="venue-active-role-label" class="font-semibold">Locație</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <!-- Dropdown -->
                <div id="venue-role-switcher-menu"
                     class="absolute right-0 hidden w-56 mt-2 overflow-hidden bg-white border rounded-xl shadow-xl border-slate-200">
                    <div class="px-4 py-2 text-[10px] font-bold tracking-widest uppercase text-slate-500 border-b bg-slate-50">
                        Comută în alt cont
                    </div>
                    <div id="venue-role-switcher-items"></div>
                </div>
            </div>

            <!-- User menu -->
            <div class="relative">
                <button type="button" id="venue-user-menu-btn"
                        class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-white transition-colors rounded-lg hover:bg-slate-800">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg font-bold text-white"
                         style="background:linear-gradient(135deg, #3b82f6, #1e40af);"
                         id="venue-user-avatar">?</div>
                    <span class="hidden sm:inline" id="venue-user-name">Cont</span>
                </button>
                <div id="venue-user-menu"
                     class="absolute right-0 z-50 hidden w-56 mt-2 overflow-hidden bg-white border rounded-xl shadow-xl border-slate-200">
                    <div class="px-4 py-3 border-b bg-slate-50">
                        <p class="text-xs text-slate-500">Autentificat ca</p>
                        <p class="text-sm font-semibold text-slate-900 truncate" id="venue-user-email">—</p>
                    </div>
                    <a href="/venue/setari" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Setări</a>
                    <a href="#" id="venue-logout-btn" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Ieși din cont</a>
                </div>
            </div>
        </div>
    </div>
</header>

<script>
// Role switcher wiring. Reads AmbiletMultiAuth cookies to figure out
// which realms the user is authenticated in, populates the dropdown,
// and handles switching by flipping `ambilet_active_role` before
// redirecting to the target panel. No API round-trip needed — all
// tokens are already persisted from the login step.
(function () {
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, days) {
        const maxAge = (days || 30) * 24 * 60 * 60;
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
    }

    const REALM_META = {
        'customer':    { label: 'Client',       redirect: '/cont',              badge: '🎫' },
        'organizer':   { label: 'Organizator',  redirect: '/organizator/panou', badge: '🎪' },
        'venue-owner': { label: 'Locație',      redirect: '/venue/panou',       badge: '🏛️' },
    };

    function detectAvailableRoles() {
        const roles = [];
        if (getCookie('ambilet_token'))            roles.push('customer');
        if (getCookie('ambilet_organizer_token'))  roles.push('organizer');
        if (getCookie('ambilet_venue_token'))      roles.push('venue-owner');
        return roles;
    }

    function initSwitcher() {
        const roles = detectAvailableRoles();
        if (roles.length <= 1) return; // Nothing to switch to.

        const container = document.getElementById('venue-role-switcher');
        const items = document.getElementById('venue-role-switcher-items');
        if (!container || !items) return;

        // Populate — skip venue-owner (already here).
        items.innerHTML = roles
            .filter(r => r !== 'venue-owner')
            .map(r => {
                const m = REALM_META[r];
                return `<a href="#" data-target-role="${r}" class="flex items-center gap-3 px-4 py-3 text-sm text-slate-700 hover:bg-slate-50 border-b last:border-b-0">
                    <span class="text-xl">${m.badge}</span>
                    <span><strong>${m.label}</strong><br><small class="text-slate-500">Comută pe acest cont</small></span>
                </a>`;
            }).join('');

        container.classList.remove('hidden');

        const btn = document.getElementById('venue-role-switcher-btn');
        const menu = document.getElementById('venue-role-switcher-menu');
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
        document.addEventListener('click', () => menu.classList.add('hidden'));

        items.querySelectorAll('[data-target-role]').forEach(a => {
            a.addEventListener('click', (e) => {
                e.preventDefault();
                const target = a.getAttribute('data-target-role');
                setCookie('ambilet_active_role', target, 30);
                window.location.href = REALM_META[target].redirect;
            });
        });
    }

    function initUserMenu() {
        const btn = document.getElementById('venue-user-menu-btn');
        const menu = document.getElementById('venue-user-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
        document.addEventListener('click', () => menu.classList.add('hidden'));

        const logout = document.getElementById('venue-logout-btn');
        if (logout) {
            logout.addEventListener('click', async (e) => {
                e.preventDefault();
                // Clear the venue-owner cookie + active-role marker.
                setCookie('ambilet_venue_token', '', -1);
                setCookie('ambilet_active_role', '', -1);
                window.location.href = '/autentificare';
            });
        }
    }

    /**
     * Populate the topbar's identity (avatar + name + email) from
     * /venue-owner/me. Silent-fails on 401 by redirecting to the login
     * page — the shell can't work without a live token.
     */
    async function loadIdentity() {
        const token = getCookie('ambilet_venue_token');
        if (!token) {
            window.location.href = '/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            return;
        }

        try {
            const baseUrl = (window.AmbiletAPI && AmbiletAPI.getApiUrl) ? AmbiletAPI.getApiUrl() : '/api/proxy.php';
            const res = await fetch(baseUrl + '?action=venue-owner.me', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': 'Bearer ' + token,
                },
            });
            if (res.status === 401) {
                setCookie('ambilet_venue_token', '', -1);
                window.location.href = '/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
                return;
            }
            if (!res.ok) return;
            const data = await res.json();
            const user = data && data.data && data.data.venue_owner ? data.data.venue_owner : null;
            if (!user) return;

            const nameEl   = document.getElementById('venue-user-name');
            const emailEl  = document.getElementById('venue-user-email');
            const avatarEl = document.getElementById('venue-user-avatar');

            const displayName = user.name || user.tenant_name || user.email || '—';
            if (nameEl)  nameEl.textContent = displayName;
            if (emailEl) emailEl.textContent = user.email || '—';
            if (avatarEl) {
                const initial = (displayName.charAt(0) || '?').toUpperCase();
                avatarEl.textContent = initial;
            }
        } catch (e) {
            // Non-fatal — keep the placeholder.
        }
    }

    /**
     * If the venue owner shares an email with an organizer account on
     * this marketplace, ask the backend for a matching organizer token
     * and drop it into the ambilet_organizer_token cookie. That lets
     * the same header switcher (already wired above) show a "Vezi ca
     * Organizator" option without the user having to log in twice.
     */
    async function detectOrganizerLink() {
        // Skip if already linked (cookie already there).
        if (getCookie('ambilet_organizer_token')) return;
        if (typeof AmbiletVenueAPI === 'undefined') return;

        try {
            const res = await AmbiletVenueAPI.linkOrganizer();
            if (res && res.success && res.data && res.data.linked && res.data.token) {
                setCookie('ambilet_organizer_token', res.data.token, 30);
                // Re-run the switcher init so the new dropdown row appears
                // without a page reload.
                initSwitcher();
            }
        } catch (e) {
            // Silent — not being linked is the common case.
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initSwitcher();
        initUserMenu();
        loadIdentity();
        detectOrganizerLink();
    });
})();
</script>
