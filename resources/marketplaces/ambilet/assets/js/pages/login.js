// /autentificare — unified login page.
//
// Attempts /auth/multi-login first (detects customer + organizer +
// venue-owner roles for the same email in a single round-trip). Behaves
// exactly like the pre-existing customer-only flow when only a customer
// role is detected. When multiple roles come back, the login card turns
// into an account picker so the user chooses which panel to enter.
//
// Safe fallback: if the multi-login endpoint is unavailable (deploy
// mid-flight, 404, network hiccup), the code falls through to the
// original AmbiletAuth.login() flow so no user is ever stranded on
// the login page while backend catches up.

document.getElementById('login-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const remember = document.getElementById('remember').checked;

    try {
        // ── Try unified multi-realm login first ────────────────
        let multiResult = null;
        try {
            multiResult = await AmbiletMultiAuth.login(email, password);
        } catch (err) {
            // Silent — fall back to the classic customer login below.
        }

        if (multiResult && multiResult.success
            && multiResult.data
            && Array.isArray(multiResult.data.roles)
            && multiResult.data.roles.length > 0) {

            const roles = multiResult.data.roles;
            const primary = multiResult.data.primary;

            // Customer-only 2FA path — same UX as the classic flow.
            if (roles.length === 1 && roles[0].type === 'customer' && roles[0].requires_2fa) {
                AmbiletNotifications.success('Introdu codul de verificare.');
                const challenge = encodeURIComponent(roles[0].challenge || '');
                setTimeout(() => window.location.href = '/verificare-2fa?challenge=' + challenge, 400);
                return;
            }

            // Keep every role's token in cookies so a later "switch role"
            // doesn't need another login round-trip.
            const active = AmbiletMultiAuth.persistAllRoles(roles, primary);

            if (roles.length === 1) {
                const role = active || roles[0];
                await activateRoleSession(role);
                AmbiletNotifications.success('Conectare reusita!');
                setTimeout(() => window.location.href = redirectForRole(role.type), 500);
                return;
            }

            // Multiple roles — user chooses.
            showRolePicker(roles, primary, email);
            return;
        }

        // ── Fallback: classic customer-only login ─────────────────
        // Reached when multi-login returned no roles (invalid creds) or
        // the endpoint isn't deployed yet. The classic call gives the
        // same "Invalid credentials" surface.
        const result = await AmbiletAuth.login(email, password, remember);
        if (result.success) {
            AmbiletNotifications.success('Conectare reusita!');
            const redirect = AmbiletUtils.getUrlParam('redirect') || '/user/dashboard';
            setTimeout(() => window.location.href = redirect, 500);
        } else {
            AmbiletNotifications.error(result.message || 'Email sau parola incorecta');
        }
    } catch (error) {
        AmbiletNotifications.error('Eroare la conectare. Incearca din nou.');
    }
});

/**
 * Opens the chosen account's session (localStorage for client and organizer
 * pages, cookie for the venue shell). Shared with the header account switcher.
 */
async function activateRoleSession(role) {
    await AmbiletMultiAuth.openSession(role);
}

/**
 * ?redirect= is honoured only when it leads into the chosen account's area
 * (e.g. a customer never lands on /organizator/...).
 */
function redirectForRole(type) {
    const requested = AmbiletUtils.getUrlParam('redirect');
    if (requested && requested.startsWith('/') && !requested.startsWith('//')) {
        const path = requested.split('?')[0];
        const isOrganizerArea = path.startsWith('/organizator');
        const isVenueArea = path.startsWith('/venue');
        const isArtistArea = path.startsWith('/artist');
        const fits = type === 'organizer' ? isOrganizerArea
            : type === 'venue-owner' ? isVenueArea
            : type === 'artist' ? isArtistArea
            : !isOrganizerArea && !isVenueArea && !isArtistArea;
        if (fits) return requested;
    }
    return AmbiletMultiAuth.redirectFor(type);
}

const ROLE_ICON = (path) =>
    `<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${path}"/></svg>`;

const ROLE_META = {
    'customer': {
        title: 'Cont client',
        description: 'Biletele tale, comenzi, favorite și puncte',
        icon: ROLE_ICON('M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'),
    },
    'organizer': {
        title: 'Cont organizator',
        description: 'Evenimente, vânzări, participanți și deconturi',
        icon: ROLE_ICON('M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'),
    },
    'artist': {
        title: 'Cont artist',
        description: 'Profilul tău de artist, evenimente și statistici',
        icon: ROLE_ICON('M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3'),
    },
    'venue-owner': {
        title: 'Cont locație',
        description: 'Evenimentele găzduite și statisticile locației',
        icon: ROLE_ICON('M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'),
    },
};

function escapeHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Turn the login card into an account picker: everything that belongs to
 * the sign-in step (title, form, sign-up link, organizer CTA) is hidden so
 * only the authenticated accounts remain.
 */
function showRolePicker(roles, primary, email) {
    injectRolePickerStyles();

    ['customerCta', 'loginHeader', 'signupLink', 'organizerCta', 'login-form'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    const card = document.getElementById('loginCardForm');
    if (card) card.style.display = '';

    const container = document.getElementById('multi-role-picker') || createRolePickerContainer();
    const ordered = [...roles].sort((a, b) => (b.type === primary) - (a.type === primary));

    const cards = ordered.map((role) => {
        const meta = ROLE_META[role.type] || { title: role.type, description: '', icon: ROLE_ICON('M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z') };
        return `
            <button type="button" class="amb-rp__card" data-role-type="${escapeHtml(role.type)}">
                <span class="amb-rp__icon amb-rp__icon--${escapeHtml(role.type)}">${meta.icon}</span>
                <span class="amb-rp__body">
                    <span class="amb-rp__name">${escapeHtml(meta.title)}</span>
                    <span class="amb-rp__desc">${escapeHtml(meta.description)}</span>
                    ${role.display_name ? `<span class="amb-rp__who">${escapeHtml(role.display_name)}</span>` : ''}
                </span>
                <span class="amb-rp__go">${ROLE_ICON('M9 5l7 7-7 7')}</span>
            </button>
        `;
    }).join('');

    container.innerHTML = `
        <div class="amb-rp">
            <div class="amb-rp__head">
                <div class="amb-rp__badge">${ROLE_ICON('M5 13l4 4L19 7')}</div>
                <h2 class="amb-rp__title">Alege contul</h2>
                <p class="amb-rp__subtitle">${email
                    ? `Adresa <strong>${escapeHtml(email)}</strong> are acces la mai multe conturi. Unde vrei să intri?`
                    : 'Ai acces la mai multe conturi. Unde vrei să intri?'}</p>
            </div>
            <div class="amb-rp__list">${cards}</div>
            <button type="button" class="amb-rp__back">Nu ești tu? Folosește alt cont</button>
        </div>
    `;
    container.style.display = 'block';

    container.querySelectorAll('[data-role-type]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            if (container.dataset.busy === '1') return;
            const role = roles.find((r) => r.type === btn.getAttribute('data-role-type'));
            if (!role) return;

            if (role.requires_2fa) {
                window.location.href = '/verificare-2fa?challenge=' + encodeURIComponent(role.challenge || '');
                return;
            }

            container.dataset.busy = '1';
            container.querySelectorAll('.amb-rp__card').forEach((c) => { c.disabled = true; });
            btn.classList.add('is-loading');
            const go = btn.querySelector('.amb-rp__go');
            if (go) go.innerHTML = '<span class="amb-rp__spinner" aria-hidden="true"></span>';

            AmbiletMultiAuth.activateRole(role);
            await activateRoleSession(role);
            window.location.href = redirectForRole(role.type);
        });
    });

    const back = container.querySelector('.amb-rp__back');
    if (back) {
        back.addEventListener('click', () => {
            window.location.assign(window.location.pathname + window.location.search);
        });
    }
}

function createRolePickerContainer() {
    const c = document.createElement('div');
    c.id = 'multi-role-picker';
    c.style.display = 'none';
    const form = document.getElementById('login-form');
    if (form && form.parentNode) {
        form.parentNode.insertBefore(c, form.nextSibling);
    } else {
        document.body.appendChild(c);
    }
    return c;
}

let _rolePickerStylesInjected = false;
function injectRolePickerStyles() {
    if (_rolePickerStylesInjected) return;
    _rolePickerStylesInjected = true;
    const style = document.createElement('style');
    style.textContent = `
        .amb-rp { padding: 4px 0; }
        .amb-rp__head { text-align: center; margin-bottom: 24px; }
        .amb-rp__badge { width: 48px; height: 48px; margin: 0 auto 14px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.12); color: #059669; }
        .amb-rp__badge svg { width: 24px; height: 24px; }
        .amb-rp__title { margin: 0; font-size: 22px; font-weight: 800; color: var(--color-secondary, #1E293B); }
        .amb-rp__subtitle { margin: 6px 0 0; font-size: 14px; line-height: 1.5; color: var(--color-muted, #64748B); }
        .amb-rp__subtitle strong { font-weight: 600; color: var(--color-secondary, #1E293B); word-break: break-all; }
        .amb-rp__list { display: flex; flex-direction: column; gap: 12px; }
        .amb-rp__card { display: flex; align-items: center; gap: 14px; width: 100%; padding: 16px; text-align: left; cursor: pointer; background: #fff; border: 1px solid var(--color-border, #E2E8F0); border-radius: 16px; transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease; }
        .amb-rp__card:hover:not(:disabled), .amb-rp__card:focus-visible { border-color: var(--color-primary, #A51C30); box-shadow: 0 10px 24px -14px rgba(165, 28, 48, 0.45); transform: translateY(-1px); outline: none; }
        .amb-rp__card:disabled { cursor: default; opacity: .5; }
        .amb-rp__card.is-loading { opacity: 1; border-color: var(--color-primary, #A51C30); }
        .amb-rp__icon { flex-shrink: 0; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
        .amb-rp__icon svg { width: 24px; height: 24px; }
        .amb-rp__icon--customer { background: rgba(165, 28, 48, 0.08); color: var(--color-primary, #A51C30); }
        .amb-rp__icon--organizer { background: var(--color-secondary, #1E293B); color: #fff; }
        .amb-rp__icon--artist { background: rgba(139, 92, 246, 0.14); color: #6D28D9; }
        .amb-rp__icon--venue-owner { background: rgba(230, 126, 34, 0.14); color: #C2410C; }
        .amb-rp__body { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
        .amb-rp__name { font-size: 15px; font-weight: 700; color: var(--color-secondary, #1E293B); }
        .amb-rp__desc { font-size: 13px; line-height: 1.4; color: var(--color-muted, #64748B); }
        .amb-rp__who { margin-top: 2px; font-size: 12px; font-weight: 600; color: var(--color-primary, #A51C30); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .amb-rp__go { flex-shrink: 0; width: 32px; height: 32px; border-radius: 9999px; display: flex; align-items: center; justify-content: center; background: var(--color-surface, #F8FAFC); color: var(--color-secondary, #1E293B); transition: background .15s ease, color .15s ease; }
        .amb-rp__go svg { width: 16px; height: 16px; }
        .amb-rp__card:hover:not(:disabled) .amb-rp__go, .amb-rp__card.is-loading .amb-rp__go { background: var(--color-primary, #A51C30); color: #fff; }
        .amb-rp__spinner { width: 16px; height: 16px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px; animation: amb-rp-spin .7s linear infinite; }
        @keyframes amb-rp-spin { to { transform: rotate(360deg); } }
        .amb-rp__back { display: block; margin: 20px auto 0; padding: 6px 10px; font-size: 14px; color: var(--color-muted, #64748B); background: none; border: 0; cursor: pointer; }
        .amb-rp__back:hover { color: var(--color-primary, #A51C30); text-decoration: underline; }
    `;
    document.head.appendChild(style);
}
