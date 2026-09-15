<?php
/**
 * Venue Owner Top Header Bar
 *
 * Mobile menu button, page title, account switcher (see
 * assets/js/components/account-switcher.js) and user menu.
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

        <div class="flex items-center gap-2">
            <div data-account-switcher="venue-owner" class="amb-acct amb-acct--dark" hidden></div>

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
(function () {
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value, days) {
        const maxAge = (days || 30) * 24 * 60 * 60;
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
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
                if (window.AmbiletMultiAuth && AmbiletMultiAuth.logoutAll) {
                    try { await AmbiletMultiAuth.logoutAll(); } catch (err) {}
                }
                setCookie('ambilet_venue_token', '', -1);
                setCookie('ambilet_active_role', '', -1);
                window.location.href = '/autentificare';
            });
        }
    }

    async function loadIdentity() {
        const token = getCookie('ambilet_venue_token');
        if (!token) {
            window.location.href = '/autentificare?redirect=' + encodeURIComponent(window.location.pathname);
            return null;
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
                return null;
            }
            if (!res.ok) return null;
            const data = await res.json();
            const user = data && data.data && data.data.venue_owner ? data.data.venue_owner : null;
            if (!user) return null;

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
            return user.tenant_name || user.name || '';
        } catch (e) {
            return null;
        }
    }

    async function detectOrganizerLink(venueName) {
        const multi = window.AmbiletMultiAuth;
        const token = getCookie('ambilet_venue_token');
        if (!multi || !token || typeof AmbiletVenueAPI === 'undefined' || multi.isImpersonating()) return;

        const current = { type: 'venue-owner', token: token, name: venueName || '' };
        if (multi.getAccounts(current).some((a) => a.type === 'organizer')) return;

        try {
            const res = await AmbiletVenueAPI.linkOrganizer();
            if (res && res.success && res.data && res.data.linked && res.data.token
                && multi.addLinkedAccount('organizer', res.data.token, res.data.display_name, current)
                && window.AmbiletAccountSwitcher) {
                AmbiletAccountSwitcher.init();
            }
        } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', () => {
        initUserMenu();
        loadIdentity().then(detectOrganizerLink);
    });
})();
</script>
