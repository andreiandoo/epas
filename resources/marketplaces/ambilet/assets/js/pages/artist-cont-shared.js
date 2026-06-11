/**
 * Shared bootstrap for /artist/cont/* pages.
 * - Enforces artist auth (redirects to /artist/login otherwise)
 * - Populates the sidebar avatar / account name / linked artist info
 * - Wires the logout button
 *
 * Each page-specific script can wait for `ambilet:artist-cont:ready`
 * before kicking off its own data fetches, so the sidebar is always
 * populated before the page renders.
 */
(function () {
    function init() {
        // Auth gate. If we're not logged in as an artist, kick to login.
        if (typeof AmbiletAuth === 'undefined' || !AmbiletAuth.isArtist || !AmbiletAuth.isArtist()) {
            const here = window.location.pathname + window.location.search;
            if (typeof AmbiletAuth !== 'undefined') {
                AmbiletAuth.setRedirectAfterLogin(here);
            }
            window.location.href = '/artist/login?redirect=' + encodeURIComponent(here);
            return;
        }

        // Render whatever data we already have cached.
        renderSidebar(AmbiletAuth.getArtistData());

        // Refresh from server (handles status changes like rejection or
        // suspension that happened since the last login).
        AmbiletAPI.artist.getProfile().catch(() => null); // primes cache silently
        AmbiletAPI.artist.getAccount()
            .then((res) => {
                if (res && res.success && res.data && res.data.account) {
                    AmbiletAuth.updateArtistData(res.data.account);
                    renderSidebar(res.data.account);
                    // If the account was suspended/rejected since login, log out.
                    const status = res.data.account.status;
                    if (status === 'suspended' || status === 'rejected') {
                        AmbiletNotifications.error('Contul tău a fost ' + (status === 'suspended' ? 'suspendat' : 'respins') + '. Vei fi deconectat.');
                        setTimeout(() => AmbiletAuth.logoutArtist(), 1500);
                    }
                }
            })
            .catch((err) => {
                // 401 means the token is bad — force re-login.
                if (err && err.status === 401) {
                    AmbiletAuth.clearArtistSession();
                    window.location.href = '/artist/login';
                }
            })
            .finally(() => {
                window.dispatchEvent(new CustomEvent('ambilet:artist-cont:ready'));
            });

        // Logout button
        const logoutBtn = document.getElementById('artist-logout-btn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', (e) => {
                e.preventDefault();
                AmbiletAuth.logoutArtist();
            });
        }
    }

    function renderSidebar(account) {
        if (!account) return;

        const fullName = (account.first_name || '') + ' ' + (account.last_name || '');
        const linked = account.artist;

        const nameEl = document.getElementById('artist-account-name');
        if (nameEl) nameEl.textContent = fullName.trim() || account.email || '—';

        const linkedEl = document.getElementById('artist-linked-name');
        if (linkedEl) {
            linkedEl.textContent = linked && linked.name ? linked.name : 'Profil neasociat';
        }

        // Avatar: prefer linked artist's logo, otherwise initials.
        const avatarEl = document.getElementById('artist-avatar');
        const initialsEl = document.getElementById('artist-avatar-initials');
        if (avatarEl) {
            if (linked && linked.logo_url) {
                const url = linked.logo_url.startsWith('http') ? linked.logo_url : (window.AMBILET?.storageUrl ? window.AMBILET.storageUrl + '/' + linked.logo_url : '/storage/' + linked.logo_url);
                avatarEl.style.backgroundImage = 'url(' + url + ')';
                avatarEl.style.backgroundSize = 'cover';
                avatarEl.style.backgroundPosition = 'center';
                avatarEl.style.backgroundColor = 'transparent';
                if (initialsEl) initialsEl.textContent = '';
            } else {
                avatarEl.style.backgroundImage = '';
                if (initialsEl) {
                    initialsEl.textContent = (account.first_name?.[0] || '') + (account.last_name?.[0] || '') || (account.email?.[0] || '?').toUpperCase();
                }
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
