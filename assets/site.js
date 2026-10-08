/* competitie.tixello.ro — coș (acces general) + autentificare client.
   Coșul ține bilete de la O singură competiție (o comandă = un eveniment):
   wukf_cart = { event:{id,slug,title,date,place,poster}, items:[{ticket_type_id,name,price,qty}] }
   wukf_auth = { token, user } */
(function () {
    var CART_KEY = 'wukf_cart', AUTH_KEY = 'wukf_auth', MAX_PER_TYPE = 10;

    function read(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } }
    function write(key, val) {
        try { val === null ? localStorage.removeItem(key) : localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }

    var Cart = {
        get: function () {
            var c = read(CART_KEY);
            return (c && c.event && Array.isArray(c.items) && c.items.length) ? c : null;
        },
        set: function (cart) {
            if (cart) { cart.items = cart.items.filter(function (i) { return i.qty > 0; }); }
            write(CART_KEY, cart && cart.items.length ? cart : null);
            window.dispatchEvent(new CustomEvent('wukf:cart'));
        },
        clear: function () { Cart.set(null); },
        count: function () {
            var c = Cart.get();
            return c ? c.items.reduce(function (n, i) { return n + i.qty; }, 0) : 0;
        },
        total: function (cart) {
            cart = cart || Cart.get();
            return cart ? cart.items.reduce(function (n, i) { return n + i.qty * i.price; }, 0) : 0;
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

    function toast(msg) { window.dispatchEvent(new CustomEvent('wukf:toast', { detail: msg })); }

    window.WUKF = { Cart: Cart, Auth: Auth, lei: lei, toast: toast, MAX_PER_TYPE: MAX_PER_TYPE };

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
                        self.timer = setTimeout(function () { self.show = false; }, 2800);
                    });
                }
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
                inc: function (t) { if (this.qty[t.id] < this.limit(t)) { this.qty[t.id]++; } },
                dec: function (t) { if (this.qty[t.id] > 0) { this.qty[t.id]--; } },
                get count() { var self = this; return types.reduce(function (n, t) { return n + self.qty[t.id]; }, 0); },
                get total() { var self = this; return types.reduce(function (n, t) { return n + self.qty[t.id] * t.price; }, 0); },
                lei: lei,
                save: function () {
                    var self = this;
                    if (!this.count) { toast('Alege cel puțin un bilet.'); return; }
                    Cart.set({
                        event: this.event,
                        items: types.filter(function (t) { return self.qty[t.id] > 0; }).map(function (t) {
                            return { ticket_type_id: t.id, name: t.name, price: t.price, qty: self.qty[t.id] };
                        })
                    });
                    window.location.href = '/cos';
                }
            };
        });

        // Coș
        Alpine.data('cartPage', function () {
            return {
                cart: null, max: MAX_PER_TYPE, lei: lei,
                init: function () { this.cart = Cart.get(); },
                get total() { return Cart.total(this.cart); },
                get count() { return this.cart ? this.cart.items.reduce(function (n, i) { return n + i.qty; }, 0) : 0; },
                step: function (item, by) {
                    item.qty = Math.max(0, Math.min(this.max, item.qty + by));
                    this.persist();
                },
                remove: function (item) { item.qty = 0; this.persist(); },
                persist: function () {
                    Cart.set(JSON.parse(JSON.stringify(this.cart)));
                    this.cart = Cart.get();
                }
            };
        });

        // Finalizare comandă
        Alpine.data('checkoutPage', function () {
            return {
                cart: null, lei: lei, busy: false, error: '', tried: false,
                form: { first_name: '', last_name: '', email: '', phone: '', terms: false, newsletter: false },
                init: function () {
                    this.cart = Cart.get();
                    var a = Auth.get();
                    if (a) {
                        var u = a.user, parts = Auth.name(u).split(/\s+/);
                        this.form.first_name = u.first_name || parts[0] || '';
                        this.form.last_name = u.last_name || parts.slice(1).join(' ') || '';
                        this.form.email = u.email || '';
                        this.form.phone = u.phone || '';
                    }
                },
                get total() { return Cart.total(this.cart); },
                get count() { return this.cart ? this.cart.items.reduce(function (n, i) { return n + i.qty; }, 0) : 0; },
                emailOk: function () { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email.trim()); },
                bad: function (field) {
                    if (!this.tried) { return false; }
                    if (field === 'email') { return !this.emailOk(); }
                    return !String(this.form[field]).trim();
                },
                pay: async function () {
                    this.tried = true; this.error = '';
                    if (this.bad('first_name') || this.bad('last_name') || this.bad('email')) { this.error = 'Completează numele și o adresă de email validă.'; return; }
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
                }
            };
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
                loading: true, orders: [], error: '', lei: lei,
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
