// /autentificare — unified login page.
//
// Attempts /auth/multi-login first (detects customer + organizer +
// venue-owner roles for the same email in a single round-trip). Behaves
// exactly like the pre-existing customer-only flow when only a customer
// role is detected. When multiple roles come back, shows an inline role
// picker so the user chooses which panel to enter — tokens for all roles
// are persisted at that point so a future "switch role" header action
// (Faza 3) doesn't require re-authentication.
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

            // Persist all detected roles' tokens up-front so the header
            // switcher (Faza 3) can flip without another login round-trip.
            const active = AmbiletMultiAuth.persistAllRoles(roles, primary);

            if (roles.length === 1) {
                AmbiletNotifications.success('Conectare reusita!');
                const redirect = AmbiletUtils.getUrlParam('redirect')
                    || AmbiletMultiAuth.redirectFor(active ? active.type : roles[0].type);
                setTimeout(() => window.location.href = redirect, 500);
                return;
            }

            // Multiple roles — user chooses.
            showRolePicker(roles, primary);
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
 * Render an inline role picker under the login form. Keeps the same
 * page (no navigation flash) — user clicks their preferred role and we
 * redirect to the matching panel.
 */
function showRolePicker(roles, primary) {
    const container = document.getElementById('multi-role-picker')
        || createRolePickerContainer();

    const labels = {
        'customer':    { icon: '🎫', title: 'Cont Client',        subtitle: 'Bilete cumparate, favorite, comenzi' },
        'organizer':   { icon: '🎪', title: 'Cont Organizator',   subtitle: 'Evenimente proprii, vanzari, deconturi' },
        'venue-owner': { icon: '🏛️', title: 'Cont Locatie',       subtitle: 'Evenimente gazduite, analytics locatie' },
    };

    const roleCards = roles.map(role => {
        const meta = labels[role.type] || { icon: '👤', title: role.type, subtitle: '' };
        const isPrimary = role.type === primary ? 'ambilet-role-card--primary' : '';
        return `
            <button type="button"
                    class="ambilet-role-card ${isPrimary}"
                    data-role-type="${role.type}">
                <span class="ambilet-role-card__icon">${meta.icon}</span>
                <span class="ambilet-role-card__text">
                    <strong>${meta.title}</strong>
                    <small>${meta.subtitle}</small>
                    <em>${role.display_name || ''}</em>
                </span>
            </button>
        `;
    }).join('');

    container.innerHTML = `
        <div class="ambilet-role-picker">
            <h3>Alege contul cu care vrei sa continui</h3>
            <p class="ambilet-role-picker__hint">Poti schimba oricand din meniul contului tau.</p>
            <div class="ambilet-role-picker__grid">${roleCards}</div>
        </div>
    `;

    container.style.display = 'block';

    // Hide the login form while the picker is active so it's not visually
    // competing with the cards.
    const form = document.getElementById('login-form');
    if (form) form.style.display = 'none';

    container.querySelectorAll('[data-role-type]').forEach(btn => {
        btn.addEventListener('click', () => {
            const type = btn.getAttribute('data-role-type');
            document.cookie = `ambilet_active_role=${type}; Path=/; Max-Age=${60 * 60 * 24 * 30}; SameSite=Lax${window.location.protocol === 'https:' ? '; Secure' : ''}`;
            const redirect = AmbiletUtils.getUrlParam('redirect')
                || AmbiletMultiAuth.redirectFor(type);
            window.location.href = redirect;
        });
    });
}

function createRolePickerContainer() {
    injectRolePickerStyles();
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
        .ambilet-role-picker { padding: 8px 0; }
        .ambilet-role-picker h3 { font-size: 20px; font-weight: 700; margin: 0 0 8px 0; color: var(--color-secondary, #1a1a2e); text-align: center; }
        .ambilet-role-picker__hint { font-size: 13px; color: var(--color-muted, #6b7280); text-align: center; margin: 0 0 24px 0; }
        .ambilet-role-picker__grid { display: flex; flex-direction: column; gap: 12px; }
        .ambilet-role-card { display: flex; align-items: center; gap: 14px; width: 100%; padding: 16px; background: #fff; border: 1.5px solid rgba(0,0,0,0.08); border-radius: 14px; cursor: pointer; text-align: left; transition: all 0.15s ease; }
        .ambilet-role-card:hover { border-color: var(--color-primary, #e05c44); background: rgba(224,92,68,0.04); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .ambilet-role-card--primary { border-color: var(--color-primary, #e05c44); background: rgba(224,92,68,0.03); }
        .ambilet-role-card--primary::after { content: 'Recomandat'; display: inline-block; padding: 3px 8px; font-size: 10px; font-weight: 700; background: var(--color-primary, #e05c44); color: #fff; border-radius: 6px; margin-left: auto; letter-spacing: 0.3px; text-transform: uppercase; }
        .ambilet-role-card__icon { font-size: 30px; line-height: 1; flex-shrink: 0; }
        .ambilet-role-card__text { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
        .ambilet-role-card__text strong { font-size: 15px; font-weight: 700; color: var(--color-secondary, #1a1a2e); }
        .ambilet-role-card__text small { font-size: 12px; color: var(--color-muted, #6b7280); }
        .ambilet-role-card__text em { font-size: 11px; color: var(--color-primary, #e05c44); font-style: normal; font-weight: 500; margin-top: 2px; }
    `;
    document.head.appendChild(style);
}
