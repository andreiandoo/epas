/* competitie.tixello.ro — coș (acces general), cod de reducere, timer de rezervare, autentificare.
   Coșul ține bilete de la O singură competiție (o comandă = un eveniment):
   wukf_cart = { event:{id,slug,title,date,place,poster}, items:[{ticket_type_id,name,price,qty}],
                 expires_at:<ms>, coupon_code:<string|null> }
   wukf_auth = { token, user } */
(function () {
    var CART_KEY = 'wukf_cart', AUTH_KEY = 'wukf_auth', EXPIRED_KEY = 'wukf_cart_expired';
    var MAX_PER_TYPE = 10, HOLD_MS = 15 * 60 * 1000;

    function read(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } }
    function write(key, val) {
        try { val === null ? localStorage.removeItem(key) : localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }

    var Cart = {
        get: function () {
            var c = read(CART_KEY);
            if (!(c && c.event && Array.isArray(c.items) && c.items.length)) { return null; }
            if (c.expires_at && c.expires_at <= Date.now()) {
                // Rezervarea a expirat: golim coșul și ținem minte competiția, pentru mesaj.
                try { sessionStorage.setItem(EXPIRED_KEY, c.event.slug || '1'); } catch (e) {}
                write(CART_KEY, null);
                window.dispatchEvent(new CustomEvent('wukf:cart'));
                return null;
            }
            return c;
        },
        set: function (cart) {
            if (cart) {
                cart.items = cart.items.filter(function (i) { return i.qty > 0; });
                if (!cart.expires_at) { cart.expires_at = Date.now() + HOLD_MS; }
            }
            write(CART_KEY, cart && cart.items.length ? cart : null);
            window.dispatchEvent(new CustomEvent('wukf:cart'));
        },
        clear: function () { Cart.set(null); },
        count: function (cart) {
            cart = cart === undefined ? Cart.get() : cart;
            return cart ? cart.items.reduce(function (n, i) { return n + i.qty; }, 0) : 0;
        },
        subtotal: function (cart) {
            cart = cart === undefined ? Cart.get() : cart;
            return cart ? cart.items.reduce(function (n, i) { return n + i.qty * i.price; }, 0) : 0;
        },
        // Mesajul „rezervarea a expirat” se arată o singură dată
        takeExpired: function () {
            try { var v = sessionStorage.getItem(EXPIRED_KEY); sessionStorage.removeItem(EXPIRED_KEY); return v; } catch (e) { return null; }
        }
    };

    var Auth = {
        get: function () { var a = read(AUTH_KEY); return (a && a.token && a.user) ? a : null; },
        set: function (a) { write(AUTH_KEY, a); },
        name: function (u) {
            if (!u) { return ''; }
            return (u.name || ((u.first_name || '') + ' ' + (u.last_name || ''))).trim() || u.email || '';
        }
    };

    function lei(n) {
        n = Number(n) || 0;
        var s = Number.isInteger(n) ? String(n) : n.toFixed(2).replace('.', ',');
        return s.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' lei';
    }
    function ticketsLabel(n) { return n === 1 ? '1 bilet' : (n < 20 ? n + ' bilete' : n + ' de bilete'); }
    function toast(msg) { window.dispatchEvent(new CustomEvent('wukf:toast', { detail: msg })); }

    /* Sumele reale ale coșului, calculate pe server (reducere din cod + taxa de procesare).
       Dacă serverul nu răspunde, cade pe subtotalul local, ca pagina să rămână utilizabilă. */
    async function quote(cart, couponCode) {
        var local = { subtotal: Cart.subtotal(cart), discount: 0, coupon: null, coupon_error: null, processing_fee: 0, fee: null, total: Cart.subtotal(cart), offline: true };
        if (!cart) { return local; }
        try {
            var r = await fetch('/api/proxy.php?action=quote', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    event_id: cart.event.id,
                    items: cart.items.map(function (i) { return { ticket_type_id: i.ticket_type_id, quantity: i.qty }; }),
                    coupon_code: couponCode || null
                })
            });
            var d = await r.json().catch(function () { return {}; });
            if (r.ok && d.success && d.data) { return d.data; }
            if (d.error) { local.error = d.error; }
        } catch (e) {}
        return local;
    }

    window.WUKF = { Cart: Cart, Auth: Auth, lei: lei, toast: toast, quote: quote, ticketsLabel: ticketsLabel, MAX_PER_TYPE: MAX_PER_TYPE, HOLD_MS: HOLD_MS };

    /* Comportament comun coșului și paginii de plată: sume, cod de reducere. */
    function pricing() {
        return {
            q: null, quoting: false,
            couponOpen: false, couponInput: '', couponBusy: false, couponError: '',
            get subtotal() { return this.q ? this.q.subtotal : Cart.subtotal(this.cart); },
            get discount() { return this.q ? this.q.discount : 0; },
            get fee() { return this.q ? this.q.processing_fee : 0; },
            get total() { return this.q ? this.q.total : Cart.subtotal(this.cart); },
            get count() { return Cart.count(this.cart); },
            get coupon() { return this.q ? this.q.coupon : null; },
            get feeHint() {
                var f = this.q && this.q.fee;
                if (!f || !f.pass_to_customer) { return ''; }
                var parts = [];
                if (f.percent_rate) { parts.push(String(f.percent_rate).replace('.', ',') + '%'); }
                if (f.fixed) { parts.push(lei(f.fixed)); }
                return parts.join(' + ');
            },
            ticketsLabel: ticketsLabel,
            refreshQuote: async function () {
                if (!this.cart) { this.q = null; return; }
                this.quoting = true;
                var q = await quote(this.cart, this.cart.coupon_code);
                // Codul salvat nu mai e valabil (expirat, coș schimbat): îl scoatem și spunem de ce
                if (this.cart && this.cart.coupon_code && !q.coupon && !q.offline) {
                    this.couponError = q.coupon_error || 'Codul de reducere nu mai este valabil.';
                    this.couponOpen = true;
                    this.cart.coupon_code = null;
                    Cart.set(JSON.parse(JSON.stringify(this.cart)));
                }
                this.q = q;
                this.quoting = false;
            },
            applyCoupon: async function () {
                var code = this.couponInput.trim().toUpperCase();
                this.couponError = '';
                if (!code) { this.couponError = 'Scrie codul de reducere.'; return; }
                this.couponBusy = true;
                var q = await quote(this.cart, code);
                this.couponBusy = false;
                if (q.offline) { this.couponError = 'Codul nu a putut fi verificat. Încearcă din nou.'; return; }
                if (!q.coupon) { this.couponError = q.coupon_error || 'Codul de reducere nu este valid.'; return; }
                this.q = q;
                this.cart.coupon_code = q.coupon.code;
                Cart.set(JSON.parse(JSON.stringify(this.cart)));
                this.couponInput = '';
                toast('Cod aplicat: ai economisit ' + lei(q.discount) + '.');
            },
            removeCoupon: function () {
                this.cart.coupon_code = null;
                Cart.set(JSON.parse(JSON.stringify(this.cart)));
                this.couponError = '';
                this.refreshQuote();
            }
        };
    }

    // ---- Componente Alpine ----
    document.addEventListener('alpine:init', function () {
        var Alpine = window.Alpine;

        // Header: contor coș, utilizator, meniu mobil
        Alpine.data('siteHead', function () {
            return {
                open: false, count: 0, user: null, firstName: '', initials: '',
                init: function () {
                    var self = this;
                    this.sync();
                    window.addEventListener('wukf:cart', function () { self.sync(); });
                    window.addEventListener('storage', function () { self.sync(); });
                },
                sync: function () {
                    this.count = Cart.count();
                    var a = Auth.get();
                    this.user = a ? a.user : null;
                    var full = Auth.name(this.user);
                    this.firstName = full.split(/\s+/)[0] || '';
                    this.initials = (full.split(/\s+/).map(function (p) { return p[0] || ''; }).slice(0, 2).join('') || '?').toUpperCase();
                },
                logout: function () {
                    var a = Auth.get();
                    if (a) { fetch('/api/proxy.php?action=logout', { method: 'POST', headers: { 'Authorization': 'Bearer ' + a.token } }).catch(function () {}); }
                    Auth.set(null);
                    window.location.href = '/';
                }
            };
        });

        Alpine.data('toastHost', function () {
            return {
                msg: '', show: false, timer: null,
                init: function () {
                    var self = this;
                    window.addEventListener('wukf:toast', function (ev) {
                        self.msg = ev.detail; self.show = true;
                        clearTimeout(self.timer);
                        self.timer = setTimeout(function () { self.show = false; }, 3400);
                    });
                    if (Cart.takeExpired()) {
                        setTimeout(function () { toast('Timpul de rezervare a expirat. Alege din nou biletele.'); }, 600);
                    }
                }
            };
        });

        // Timerul de rezervare (15 minute de la primul bilet pus în coș)
        Alpine.data('cartTimer', function () {
            return {
                left: 0, total: HOLD_MS / 1000, tick: null, on: false,
                init: function () {
                    var self = this;
                    this.update();
                    this.tick = setInterval(function () { self.update(); }, 1000);
                    window.addEventListener('wukf:cart', function () { self.update(true); });
                },
                update: function (silent) {
                    var c = read(CART_KEY);
                    if (!(c && c.expires_at && c.items && c.items.length)) { this.on = false; return; }
                    this.on = true;
                    this.left = Math.max(0, Math.round((c.expires_at - Date.now()) / 1000));
                    if (this.left <= 0 && !silent) {
                        clearInterval(this.tick);
                        var slug = c.event && c.event.slug;
                        Cart.get(); // marchează expirarea și golește coșul
                        window.location.href = slug ? '/competitii/' + slug : '/competitii';
                    }
                },
                get clock() {
                    var m = Math.floor(this.left / 60), s = this.left % 60;
                    return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
                },
                get low() { return this.left <= 120; },
                // Lungimea arcului rămas (cerc cu r=19 → circumferință ≈ 119.4)
                get dash() { return 119.4 * (1 - Math.min(1, this.left / this.total)); }
            };
        });

        // Pagina competiției: selector de bilete
        Alpine.data('ticketPicker', function (event, types) {
            return {
                event: event, types: types, qty: {}, max: MAX_PER_TYPE,
                init: function () {
                    var self = this, cart = Cart.get();
                    types.forEach(function (t) { self.qty[t.id] = 0; });
                    // Reia cantitățile dacă vizitatorul are deja bilete la această competiție
                    if (cart && cart.event.id === event.id) {
                        cart.items.forEach(function (i) { if (i.ticket_type_id in self.qty) { self.qty[i.ticket_type_id] = i.qty; } });
                    }
                },
                limit: function (t) { return t.available > 0 ? Math.min(this.max, t.available) : this.max; },
                inc: function (t) { if (this.qty[t.id] < this.limit(t)) { this.qty[t.id]++; this.pop(); } },
                dec: function (t) { if (this.qty[t.id] > 0) { this.qty[t.id]--; this.pop(); } },
                pop: function () {
                    var el = this.$root.querySelector('[data-total]');
                    if (el && window.Motion && Motion.animate) { Motion.animate(el, { scale: [1, 1.12, 1] }, { duration: .28 }); }
                },
                get count() { var self = this; return types.reduce(function (n, t) { return n + self.qty[t.id]; }, 0); },
                get total() { var self = this; return types.reduce(function (n, t) { return n + self.qty[t.id] * t.price; }, 0); },
                lei: lei, ticketsLabel: ticketsLabel,
                save: function () {
                    var self = this;
                    if (!this.count) { toast('Alege cel puțin un bilet.'); return; }
                    var prev = Cart.get(), same = prev && prev.event.id === this.event.id;
                    Cart.set({
                        event: this.event,
                        items: types.filter(function (t) { return self.qty[t.id] > 0; }).map(function (t) {
                            return { ticket_type_id: t.id, name: t.name, price: t.price, qty: self.qty[t.id] };
                        }),
                        // La aceeași competiție păstrăm timerul și codul; la alta pornim de la zero
                        expires_at: same ? prev.expires_at : null,
                        coupon_code: same ? (prev.coupon_code || null) : null
                    });
                    window.location.href = '/cos';
                }
            };
        });

        // Coș
        Alpine.data('cartPage', function () {
            return Object.assign(pricing(), {
                cart: null, max: MAX_PER_TYPE, lei: lei, debounce: null,
                init: function () { this.cart = Cart.get(); this.refreshQuote(); },
                step: function (item, by) {
                    item.qty = Math.max(0, Math.min(this.max, item.qty + by));
                    this.persist();
                },
                remove: function (item) { item.qty = 0; this.persist(); },
                persist: function () {
                    var self = this;
                    Cart.set(JSON.parse(JSON.stringify(this.cart)));
                    this.cart = Cart.get();
                    clearTimeout(this.debounce);
                    this.debounce = setTimeout(function () { self.refreshQuote(); }, 350);
                }
            });
        });

        // Date și plată
        Alpine.data('checkoutPage', function () {
            return Object.assign(pricing(), {
                cart: null, lei: lei, busy: false, error: '', tried: false, pasteWarn: false,
                form: { first_name: '', last_name: '', email: '', email2: '', phone: '', terms: false, newsletter: false },
                init: function () {
                    this.cart = Cart.get();
                    this.refreshQuote();
                    var a = Auth.get();
                    if (a) {
                        var u = a.user, parts = Auth.name(u).split(/\s+/);
                        this.form.first_name = u.first_name || parts[0] || '';
                        this.form.last_name = u.last_name || parts.slice(1).join(' ') || '';
                        this.form.phone = u.phone || '';
                    }
                },
                emailOk: function () { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.form.email.trim()); },
                emailsMatch: function () {
                    return this.form.email2.trim() !== '' && this.form.email.trim().toLowerCase() === this.form.email2.trim().toLowerCase();
                },
                // Starea câmpului de confirmare: '' (încă nu se știe), 'good' sau 'bad'
                confirmState: function () {
                    var a = this.form.email.trim().toLowerCase(), b = this.form.email2.trim().toLowerCase();
                    if (!b) { return this.tried ? 'bad' : ''; }
                    if (a === b) { return this.emailOk() ? 'good' : ''; }
                    // Cât timp ce s-a scris e un început corect al adresei, nu certăm utilizatorul
                    return (a.indexOf(b) === 0 && !this.tried) ? '' : 'bad';
                },
                bad: function (field) {
                    if (!this.tried) { return false; }
                    if (field === 'email') { return !this.emailOk(); }
                    return !String(this.form[field]).trim();
                },
                // Adresa se scrie de mână în ambele câmpuri: fără lipire, fără tragere de text
                noPaste: function (e) {
                    e.preventDefault();
                    var self = this;
                    this.pasteWarn = true;
                    setTimeout(function () { self.pasteWarn = false; }, 4000);
                },
                pay: async function () {
                    this.tried = true; this.error = '';
                    if (this.bad('first_name') || this.bad('last_name')) { this.error = 'Completează prenumele și numele.'; return this.focusFirstBad(); }
                    if (!this.emailOk()) { this.error = 'Adresa de email nu pare corectă.'; return this.focusFirstBad(); }
                    if (!this.emailsMatch()) { this.error = 'Cele două adrese de email nu sunt identice. Verifică-le literă cu literă.'; return this.focusFirstBad(); }
                    if (!this.form.terms) { this.error = 'Trebuie să accepți termenii pentru a continua.'; return; }
                    this.busy = true;
                    try {
                        var r = await fetch('/api/proxy.php?action=checkout', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                event_id: this.cart.event.id,
                                customer: {
                                    first_name: this.form.first_name.trim(), last_name: this.form.last_name.trim(),
                                    email: this.form.email.trim(), phone: this.form.phone.trim() || null
                                },
                                items: this.cart.items.map(function (i) { return { ticket_type_id: i.ticket_type_id, quantity: i.qty }; }),
                                coupon_code: this.cart.coupon_code || null,
                                newsletter: this.form.newsletter,
                                payment_method: 'card',
                                success_url: window.location.origin + '/confirmare',
                                cancel_url: window.location.origin + '/finalizare?plata=anulata'
                            })
                        });
                        var d = await r.json().catch(function () { return {}; });
                        if (r.ok && d.success && d.redirect_url) { window.location.href = d.redirect_url; return; }
                        this.error = d.error || d.message || 'Comanda nu a putut fi plasată. Încearcă din nou.';
                    } catch (e) {
                        this.error = 'Conexiunea a eșuat. Verifică internetul și încearcă din nou.';
                    }
                    this.busy = false;
                },
                focusFirstBad: function () {
                    var self = this;
                    this.$nextTick(function () {
                        var el = self.$root.querySelector('input.is-bad');
                        if (el) { el.focus(); }
                    });
                }
            });
        });

        // Autentificare / cont nou
        Alpine.data('authForm', function (mode) {
            return {
                mode: mode, busy: false, error: '',
                form: { first_name: '', last_name: '', email: '', password: '' },
                submit: async function () {
                    this.error = ''; this.busy = true;
                    var body = mode === 'login'
                        ? { email: this.form.email.trim(), password: this.form.password }
                        : { first_name: this.form.first_name.trim(), last_name: this.form.last_name.trim(), email: this.form.email.trim(), password: this.form.password };
                    try {
                        var r = await fetch('/api/proxy.php?action=' + mode, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
                        var d = await r.json().catch(function () { return {}; });
                        if (r.ok && d.success && d.data && d.data.token) {
                            Auth.set({ token: d.data.token, user: d.data.user });
                            var next = new URLSearchParams(window.location.search).get('next') || '';
                            window.location.href = /^\/[^\/]/.test(next) ? next : '/biletele-mele';
                            return;
                        }
                        var first = d.errors ? Object.values(d.errors)[0] : null;
                        this.error = (first && first[0]) || d.message || (mode === 'login' ? 'Email sau parolă greșită.' : 'Contul nu a putut fi creat.');
                    } catch (e) {
                        this.error = 'Conexiunea a eșuat. Încearcă din nou.';
                    }
                    this.busy = false;
                }
            };
        });

        // Biletele mele (client autentificat)
        Alpine.data('myTickets', function () {
            return {
                loading: true, orders: [], error: '', lei: lei, ticketsLabel: ticketsLabel,
                init: async function () {
                    var a = Auth.get();
                    if (!a) { window.location.href = '/autentificare?next=/biletele-mele'; return; }
                    try {
                        var r = await fetch('/api/proxy.php?action=acc-orders', { headers: { 'Authorization': 'Bearer ' + a.token } });
                        if (r.status === 401) { Auth.set(null); window.location.href = '/autentificare?next=/biletele-mele'; return; }
                        var d = await r.json().catch(function () { return {}; });
                        this.orders = Array.isArray(d.data) ? d.data : [];
                    } catch (e) { this.error = 'Comenzile nu au putut fi încărcate.'; }
                    this.loading = false;
                },
                date: function (iso) {
                    if (!iso) { return ''; }
                    var d = new Date(iso);
                    return isNaN(d) ? '' : d.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
                },
                paid: function (o) { return ['paid', 'confirmed', 'completed'].indexOf(o.status) !== -1; }
            };
        });
    });
})();
