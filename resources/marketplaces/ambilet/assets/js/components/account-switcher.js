/**
 * Ambilet.ro - Header account switcher
 *
 * Shown when the signed-in person opened more than one account (client,
 * organizer, venue) at the same login. Mounts into every
 * [data-account-switcher] element; the attribute names the session the page
 * runs under: customer, organizer, venue-owner, or auto (public header).
 */
const AmbiletAccountSwitcher = {
    META: {
        'customer': {
            label: 'Client',
            title: 'Cont client',
            inline: 'contul de client',
            icon: 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z',
        },
        'organizer': {
            label: 'Organizator',
            title: 'Cont organizator',
            inline: 'contul de organizator',
            icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        },
        'venue-owner': {
            label: 'Locație',
            title: 'Cont locație',
            inline: 'contul locației',
            icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        },
    },

    _busy: false,
    _stylesInjected: false,
    _documentBound: false,

    init() {
        if (typeof AmbiletMultiAuth === 'undefined' || typeof AmbiletMultiAuth.getAccounts !== 'function') return;
        if (this._busy) return;
        this.injectStyles();
        document.querySelectorAll('[data-account-switcher]').forEach((el) => this.mount(el));
        this.bindDocument();
    },

    svg(path, className) {
        return `<svg class="${className || ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${path}"/></svg>`;
    },

    escape(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    },

    currentSession(el) {
        const context = el.getAttribute('data-account-switcher');
        if (context === 'venue-owner') {
            return { type: 'venue-owner', token: AmbiletMultiAuth._cookie(AmbiletMultiAuth.COOKIES['venue-owner']) };
        }
        if (typeof AmbiletAuth === 'undefined') return null;
        const type = AmbiletAuth.getUserType();
        if (type !== 'customer' && type !== 'organizer') return null;
        if (context !== 'auto' && context !== type) return null;
        return { type, token: AmbiletAuth.getToken() };
    },

    mount(el) {
        const accounts = AmbiletMultiAuth.getAccounts(this.currentSession(el));
        const current = accounts.find((a) => a.current);
        if (!current) {
            el.hidden = true;
            el.innerHTML = '';
            return;
        }

        const meta = this.META[current.type];
        el.classList.add('amb-acct');
        el.classList.remove('is-open');
        el.innerHTML = `
            <button type="button" class="amb-acct__btn" aria-haspopup="menu" aria-expanded="false" title="Schimbă contul">
                ${this.svg(meta.icon, 'amb-acct__btn-icon')}
                <span class="amb-acct__btn-label">${meta.label}</span>
                ${this.svg('M19 9l-7 7-7-7', 'amb-acct__chev')}
            </button>
            <div class="amb-acct__menu" role="menu" hidden>
                <div class="amb-acct__head">Conturile tale</div>
                ${accounts.map((a) => this.itemHtml(a)).join('')}
            </div>
        `;
        el.hidden = false;

        el.querySelector('.amb-acct__btn').addEventListener('click', (e) => {
            e.stopPropagation();
            if (this._busy) return;
            this.toggle(el, el.querySelector('.amb-acct__menu').hidden);
        });

        el.querySelectorAll('.amb-acct__item').forEach((item) => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                if (item.classList.contains('is-current')) {
                    this.toggle(el, false);
                    return;
                }
                this.switchTo(el, item);
            });
        });
    },

    itemHtml(account) {
        const meta = this.META[account.type];
        const name = account.name ? `<span class="amb-acct__name">${this.escape(account.name)}</span>` : '';
        const state = account.current ? this.svg('M5 13l4 4L19 7') : this.svg('M9 5l7 7-7 7', 'amb-acct__go');
        return `
            <button type="button" role="menuitem" class="amb-acct__item${account.current ? ' is-current' : ''}" data-type="${account.type}"${account.current ? ' aria-current="true"' : ''}>
                <span class="amb-acct__icon amb-acct__icon--${account.type}">${this.svg(meta.icon)}</span>
                <span class="amb-acct__text">
                    <span class="amb-acct__title">${meta.title}</span>
                    ${name}
                </span>
                <span class="amb-acct__state">${state}</span>
            </button>
        `;
    },

    toggle(el, open) {
        const menu = el.querySelector('.amb-acct__menu');
        const btn = el.querySelector('.amb-acct__btn');
        if (!menu || !btn) return;
        if (open) {
            document.querySelectorAll('.amb-acct.is-open').forEach((other) => {
                if (other !== el) this.toggle(other, false);
            });
            // On phones the menu spans the screen width just under the button.
            menu.style.top = window.innerWidth < 640 ? `${Math.round(btn.getBoundingClientRect().bottom + 8)}px` : '';
        }
        menu.hidden = !open;
        el.classList.toggle('is-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    },

    bindDocument() {
        if (this._documentBound) return;
        this._documentBound = true;

        document.addEventListener('click', (e) => {
            if (this._busy) return;
            document.querySelectorAll('.amb-acct.is-open').forEach((el) => {
                if (!el.contains(e.target)) this.toggle(el, false);
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape' || this._busy) return;
            document.querySelectorAll('.amb-acct.is-open').forEach((el) => this.toggle(el, false));
        });
        window.addEventListener('ambilet:auth:login', () => this.init());
        window.addEventListener('ambilet:auth:logout', () => this.init());
    },

    async switchTo(el, item) {
        if (this._busy) return;
        this._busy = true;
        const type = item.getAttribute('data-type');
        el.querySelectorAll('.amb-acct__item').forEach((i) => { i.disabled = true; });
        item.classList.add('is-loading');
        const state = item.querySelector('.amb-acct__state');
        if (state) state.innerHTML = '<span class="amb-acct__spinner" aria-hidden="true"></span>';

        let result;
        try {
            result = await AmbiletMultiAuth.switchTo(type);
        } catch (e) {
            result = { ok: false };
        }

        if (result.ok) {
            window.location.href = result.redirect;
            return;
        }

        this._busy = false;
        const meta = this.META[type];
        const message = result.expired && meta
            ? `Sesiunea pentru ${meta.inline} a expirat. Autentifică-te din nou ca să-l folosești.`
            : 'Nu am putut schimba contul. Încearcă din nou.';
        if (typeof AmbiletNotifications !== 'undefined') AmbiletNotifications.error(message);
        this.init();
    },

    injectStyles() {
        if (this._stylesInjected) return;
        this._stylesInjected = true;
        const style = document.createElement('style');
        style.textContent = `
            .amb-acct { position: relative; display: inline-flex; }
            .amb-acct[hidden], .amb-acct__menu[hidden] { display: none !important; }
            body[data-restricted-role] .amb-acct { display: none !important; }
            .amb-acct__btn { display: inline-flex; align-items: center; gap: 8px; height: 38px; padding: 0 12px; border: 1px solid #E2E8F0; border-radius: 9999px; background: #fff; color: #1E293B; font-size: 13px; font-weight: 600; line-height: 1; white-space: nowrap; cursor: pointer; transition: border-color .15s ease, background-color .15s ease, color .15s ease; }
            .amb-acct__btn svg { width: 16px; height: 16px; flex-shrink: 0; }
            .amb-acct__btn .amb-acct__chev { width: 14px; height: 14px; opacity: .6; transition: transform .15s ease; }
            .amb-acct__btn:hover, .amb-acct.is-open .amb-acct__btn { border-color: var(--color-primary, #A51C30); color: var(--color-primary, #A51C30); }
            .amb-acct.is-open .amb-acct__chev { transform: rotate(180deg); }
            .amb-acct--dark .amb-acct__btn { background: rgba(255, 255, 255, .06); border-color: rgba(255, 255, 255, .16); color: #fff; }
            .amb-acct--dark .amb-acct__btn:hover, .amb-acct--dark.is-open .amb-acct__btn { background: rgba(255, 255, 255, .12); border-color: rgba(255, 255, 255, .32); color: #fff; }
            #header.header-transparent .amb-acct__btn { background: rgba(255, 255, 255, .1); border-color: rgba(255, 255, 255, .3); color: #fff; }
            .amb-acct__menu { position: absolute; top: calc(100% + 8px); right: 0; z-index: 1100; width: 290px; padding: 6px; background: #fff; border: 1px solid #E2E8F0; border-radius: 16px; box-shadow: 0 20px 40px -16px rgba(15, 23, 42, .28); text-align: left; }
            .amb-acct__head { padding: 8px 10px 6px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #94A3B8; }
            .amb-acct__item { display: flex; align-items: center; gap: 12px; width: 100%; padding: 10px; border: 0; border-radius: 12px; background: transparent; color: #1E293B; text-align: left; cursor: pointer; transition: background-color .15s ease; }
            .amb-acct__item:hover:not(:disabled) { background: #F8FAFC; }
            .amb-acct__item.is-current { background: rgba(165, 28, 48, .05); cursor: default; }
            .amb-acct__item:disabled { opacity: .55; cursor: default; }
            .amb-acct__item.is-loading { opacity: 1; background: #F8FAFC; }
            .amb-acct__icon { flex-shrink: 0; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
            .amb-acct__icon svg { width: 18px; height: 18px; }
            .amb-acct__icon--customer { background: rgba(165, 28, 48, .08); color: var(--color-primary, #A51C30); }
            .amb-acct__icon--organizer { background: #1E293B; color: #fff; }
            .amb-acct__icon--venue-owner { background: rgba(230, 126, 34, .14); color: #C2410C; }
            .amb-acct__text { display: flex; flex-direction: column; gap: 2px; flex: 1; min-width: 0; }
            .amb-acct__title { font-size: 14px; font-weight: 600; line-height: 1.25; }
            .amb-acct__name { font-size: 12px; line-height: 1.3; color: #64748B; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
            .amb-acct__state { flex-shrink: 0; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; color: var(--color-primary, #A51C30); }
            .amb-acct__state svg { width: 18px; height: 18px; }
            .amb-acct__state .amb-acct__go { width: 16px; height: 16px; color: #94A3B8; }
            .amb-acct__item:hover:not(:disabled) .amb-acct__go { color: var(--color-primary, #A51C30); }
            .amb-acct__spinner { width: 16px; height: 16px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 9999px; animation: amb-acct-spin .7s linear infinite; }
            @keyframes amb-acct-spin { to { transform: rotate(360deg); } }
            @media (max-width: 639px) {
                .amb-acct--hide-mobile { display: none !important; }
                .amb-acct__btn { gap: 4px; padding: 0 10px; }
                .amb-acct__btn-label { display: none; }
                .amb-acct__menu { position: fixed; left: 16px; right: 16px; width: auto; }
            }
        `;
        document.head.appendChild(style);
    },
};

window.AmbiletAccountSwitcher = AmbiletAccountSwitcher;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => AmbiletAccountSwitcher.init());
} else {
    AmbiletAccountSwitcher.init();
}
