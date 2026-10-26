/* Comerțul comun al site-urilor de tenant (resources/tenant-sites): coș, cod de
   reducere, timer de rezervare, alegere bilete / locuri, pagina de plată.
   Portat din demo-ul competitie (resources/tenant-demos/competitie/assets/site.js).
   Toate template-urile îl folosesc; diferă doar aspectul, din temă.

   site_cart = { event:{id,slug,title,date,place,poster,url},
                 items:[{ticket_type_id,name,price,qty, visit_date?,slot_time?,capacity_id?,duration_minutes?,when?}],
                 seats:[{seat_uid,label,section,row,seat,price}], event_seating_id:<int|null>,
                 expires_at:<ms>, coupon_code:<string|null> }
   La evenimentele cu locuri numerotate coșul ține `seats` (blocate pe server), nu `items`.
   site_auth = { token, user }
   Configurare din pagină: window.TS_CFG = { api, host, eventsUrl } */
(function () {
    var CART_KEY = 'site_cart', AUTH_KEY = 'site_auth', EXPIRED_KEY = 'site_cart_expired';
    var MAX_PER_TYPE = 10, MAX_SEATS = 10, HOLD_MS = 15 * 60 * 1000;
    // Locurile numerotate sunt blocate pe server tot 15 minute (seating.hold_ttl_seconds = 900)
    var SEAT_HOLD_MS = 15 * 60 * 1000;

    function read(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } }
    function write(key, val) {
        try { val === null ? localStorage.removeItem(key) : localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }

    var Cart = {
        get: function () {
            var c = read(CART_KEY);
            if (!(c && c.event)) { return null; }
            c.items = Array.isArray(c.items) ? c.items : [];
            c.seats = Array.isArray(c.seats) ? c.seats : [];
            if (!c.items.length && !c.seats.length) { return null; }
            if (c.expires_at && c.expires_at <= Date.now()) {
                // Rezervarea a expirat: golim coșul și ținem minte competiția, pentru mesaj.
                try { sessionStorage.setItem(EXPIRED_KEY, c.event.slug || '1'); } catch (e) {}
                Cart.releaseSeats(c, c.seats.map(function (x) { return x.seat_uid; }));
                write(CART_KEY, null);
                window.dispatchEvent(new CustomEvent('site:cart'));
                return null;
            }
            return c;
        },
        set: function (cart) {
            if (cart) {
                cart.items = (cart.items || []).filter(function (i) { return i.qty > 0; });
                cart.seats = cart.seats || [];
                if (!cart.expires_at) { cart.expires_at = Date.now() + (cart.seats.length ? SEAT_HOLD_MS : HOLD_MS); }
            }
            write(CART_KEY, cart && (cart.items.length || cart.seats.length) ? cart : null);
            window.dispatchEvent(new CustomEvent('site:cart'));
        },
        clear: function () { Cart.set(null); },
        /* Pune în coș biletele fără loc (part.items) sau locurile (part.seats + event_seating_id) ale
           unui eveniment, păstrând cealaltă parte, timerul și codul de reducere dacă e același eveniment. */
        merge: function (event, part) {
            var prev = Cart.get(), same = prev && prev.event && prev.event.id === event.id;
            var next = {
                event: event,
                items: 'items' in part ? part.items : (same ? prev.items : []),
                seats: 'seats' in part ? part.seats : (same ? prev.seats : []),
                event_seating_id: part.event_seating_id || (same ? prev.event_seating_id : null) || null,
                expires_at: same ? prev.expires_at : null,
                coupon_code: same ? (prev.coupon_code || null) : null
            };
            // Primul loc blocat pe server pornește timerul de la zero, chiar dacă biletele erau deja în coș
            if (same && part.seats && part.seats.length && !(prev.seats || []).length) { next.expires_at = Date.now() + SEAT_HOLD_MS; }
            // Locurile din alt eveniment rămân blocate degeaba: le eliberăm
            if (prev && !same && (prev.seats || []).length) { Cart.releaseSeats(prev, prev.seats.map(function (x) { return x.seat_uid; })); }
            Cart.set(next);
        },
        count: function (cart) {
            cart = cart === undefined ? Cart.get() : cart;
            return cart ? cart.items.reduce(function (n, i) { return n + i.qty; }, 0) + (cart.seats || []).length : 0;
        },
        subtotal: function (cart) {
            cart = cart === undefined ? Cart.get() : cart;
            if (!cart) { return 0; }
            return cart.items.reduce(function (n, i) { return n + i.qty * i.price; }, 0)
                + (cart.seats || []).reduce(function (n, x) { return n + (Number(x.price) || 0); }, 0);
        },
        // Forma în care coșul pleacă spre server (calcul de preț și comandă)
        // Cheia unei linii din coș: același tip de bilet poate apărea pe zile / intervale diferite
        lineKey: function (i) { return [i.ticket_type_id, i.visit_date || '', i.slot_time || '', i.capacity_id || ''].join('|'); },
        payload: function (cart) {
            // Locurile numerotate și biletele fără loc (peluză, acces general) pot sta în aceeași comandă
            var out = { event_id: cart.event.id };
            if (cart.seats && cart.seats.length) {
                out.event_seating_id = cart.event_seating_id;
                out.seats = cart.seats.map(function (x) { return { seat_uid: x.seat_uid, price: x.price, label: x.label }; });
            }
            if (cart.items && cart.items.length) {
                // Rezervările (leisure) trimit și ziua, intervalul și capacitatea; core le verifică și le rezervă
                out.items = cart.items.map(function (i) {
                    var it = { ticket_type_id: i.ticket_type_id, quantity: i.qty };
                    ['capacity_id', 'visit_date', 'slot_time', 'duration_minutes'].forEach(function (k) { if (i[k] != null && i[k] !== '') { it[k] = i[k]; } });
                    return it;
                });
            }
            return out;
        },
        // Eliberează pe server locurile blocate (la scoaterea din coș sau la expirare)
        releaseSeats: function (cart, uids) {
            if (!cart || !cart.event_seating_id || !uids || !uids.length) { return Promise.resolve(); }
            return fetch('/api/proxy.php?action=release', {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, keepalive: true,
                body: JSON.stringify({ event_seating_id: cart.event_seating_id, seat_uids: uids })
            }).catch(function () {});
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

    // Limba paginii (TS_CFG.locale) și traducerile textelor (TS_I18N: textul românesc → traducere)
    function locale() { return (window.TS_CFG || {}).locale || 'ro'; }
    function t(s, params) {
        var d = window.TS_I18N || {};
        var r = Object.prototype.hasOwnProperty.call(d, s) ? d[s] : s;
        if (params) { Object.keys(params).forEach(function (k) { r = r.split('{' + k + '}').join(params[k]); }); }
        return r;
    }
    function lei(n) {
        n = Number(n) || 0;
        var en = locale() === 'en';
        var s = Number.isInteger(n) ? String(n) : n.toFixed(2).replace('.', en ? '.' : ',');
        return s.replace(/\B(?=(\d{3})+(?!\d))/g, en ? ',' : '.') + (en ? ' RON' : ' lei');
    }
    function ticketsLabel(n) {
        if (locale() === 'en') { return n === 1 ? '1 ticket' : n + ' tickets'; }
        return n === 1 ? '1 bilet' : (n < 20 ? n + ' bilete' : n + ' de bilete');
    }
    function toast(msg) { window.dispatchEvent(new CustomEvent('site:toast', { detail: msg })); }

    // „14–15 noiembrie 2026” / „3 aprilie 2027” / „30 ianuarie – 1 februarie 2027”, din două date ISO
    function dateRange(startIso, endIso) {
        if (!startIso) { return ''; }
        var day = function (iso) { var p = String(iso).slice(0, 10).split('-'); return new Date(+p[0], +p[1] - 1, +p[2]); };
        var a = day(startIso), b = endIso ? day(endIso) : a;
        var full = { day: 'numeric', month: 'long', year: 'numeric' }, loc = locale() === 'en' ? 'en-GB' : 'ro-RO';
        if (a.getTime() === b.getTime()) { return a.toLocaleDateString(loc, full); }
        if (a.getMonth() === b.getMonth() && a.getFullYear() === b.getFullYear()) {
            return a.getDate() + '–' + b.toLocaleDateString(loc, full);
        }
        return a.toLocaleDateString(loc, { day: 'numeric', month: 'long' }) + ' – ' + b.toLocaleDateString(loc, full);
    }

    /* Tokenurile comenzilor plasate din acest browser: cu ele se descarcă biletele după plată,
       fără cont. Se păstrează ultimele 20. */
    var Orders = {
        KEY: 'site_orders',
        all: function () { var o = read(Orders.KEY); return (o && typeof o === 'object') ? o : {}; },
        remember: function (id, token) {
            if (!id || !token) { return; }
            var o = Orders.all(); o[id] = token;
            var keys = Object.keys(o);
            keys.slice(0, Math.max(0, keys.length - 20)).forEach(function (k) { delete o[k]; });
            write(Orders.KEY, o);
        },
        token: function (id) { return Orders.all()[id] || null; }
    };
    // Adresa PDF-ului cu biletele unei comenzi (toate, sau doar cel cu codul dat)
    function pdfUrl(orderId, token, code) {
        var cfg = window.TS_CFG || {};
        var q = 'hostname=' + encodeURIComponent(cfg.host || location.hostname) + '&order=' + encodeURIComponent(orderId) + '&token=' + encodeURIComponent(token);
        if (code) { q += '&code=' + encodeURIComponent(code); }
        return (cfg.api || '') + '/tenant-client/storefront/tickets.pdf?' + q;
    }

    /* Sumele reale ale coșului, calculate pe server (reducere din cod + taxa de procesare).
       Dacă serverul nu răspunde, cade pe subtotalul local, ca pagina să rămână utilizabilă. */
    async function quote(cart, couponCode) {
        var local = { subtotal: Cart.subtotal(cart), discount: 0, coupon: null, coupon_error: null, processing_fee: 0, fee: null, total: Cart.subtotal(cart), offline: true };
        if (!cart) { return local; }
        try {
            var r = await fetch('/api/proxy.php?action=quote', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign(Cart.payload(cart), { coupon_code: couponCode || null }))
            });
            var d = await r.json().catch(function () { return {}; });
            if (r.ok && d.success && d.data) { return d.data; }
            if (d.error) { local.error = d.error; }
        } catch (e) {}
        return local;
    }

    window.TS = { t: t, locale: locale, Orders: Orders, pdfUrl: pdfUrl, Cart: Cart, Auth: Auth, lei: lei, toast: toast, quote: quote, ticketsLabel: ticketsLabel, MAX_PER_TYPE: MAX_PER_TYPE, HOLD_MS: HOLD_MS, SEAT_HOLD_MS: SEAT_HOLD_MS };

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
                    this.couponError = q.coupon_error || t('Codul de reducere nu mai este valabil.');
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
                if (!code) { this.couponError = t('Scrie codul de reducere.'); return; }
                this.couponBusy = true;
                var q = await quote(this.cart, code);
                this.couponBusy = false;
                if (q.offline) { this.couponError = t('Codul nu a putut fi verificat. Încearcă din nou.'); return; }
                if (!q.coupon) { this.couponError = q.coupon_error || t('Codul de reducere nu este valid.'); return; }
                this.q = q;
                this.cart.coupon_code = q.coupon.code;
                Cart.set(JSON.parse(JSON.stringify(this.cart)));
                this.couponInput = '';
                toast(t('Cod aplicat: ai economisit {sum}.', { sum: lei(q.discount) }));
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
                    window.addEventListener('site:cart', function () { self.sync(); });
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
                    window.addEventListener('site:toast', function (ev) {
                        self.msg = ev.detail; self.show = true;
                        clearTimeout(self.timer);
                        self.timer = setTimeout(function () { self.show = false; }, 3400);
                    });
                    if (Cart.takeExpired()) {
                        setTimeout(function () { toast(t('Timpul de rezervare a expirat. Alege din nou biletele.')); }, 600);
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
                    window.addEventListener('site:cart', function () { self.update(true); });
                },
                update: function (silent) {
                    var c = read(CART_KEY);
                    var n = c ? ((c.items || []).length + (c.seats || []).length) : 0;
                    if (!(c && c.expires_at && n)) { this.on = false; return; }
                    this.on = true;
                    this.total = ((c.seats || []).length ? SEAT_HOLD_MS : HOLD_MS) / 1000;
                    this.left = Math.max(0, Math.round((c.expires_at - Date.now()) / 1000));
                    if (this.left <= 0 && !silent) {
                        clearInterval(this.tick);
                        var back = (c.event && c.event.url) || ((window.TS_CFG || {}).eventsUrl) || '/';
                        Cart.get(); // marchează expirarea și golește coșul
                        window.location.href = back;
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
                    if (el && window.gsap) { window.gsap.fromTo(el, { scale: 1.12 }, { scale: 1, duration: .28, ease: 'power2.out' }); }
                },
                get count() { var self = this; return types.reduce(function (n, t) { return n + self.qty[t.id]; }, 0); },
                get total() { var self = this; return types.reduce(function (n, t) { return n + self.qty[t.id] * t.price; }, 0); },
                lei: lei, ticketsLabel: ticketsLabel,
                save: function () {
                    var self = this;
                    if (!this.count) { toast(t('Alege cel puțin un bilet.')); return; }
                    // La aceeași competiție păstrăm locurile, timerul și codul; la alta pornim de la zero
                    Cart.merge(this.event, {
                        items: types.filter(function (t) { return self.qty[t.id] > 0; }).map(function (t) {
                            return { ticket_type_id: t.id, name: t.name, price: t.price, qty: self.qty[t.id] };
                        })
                    });
                    // Sertarul coșului arată timerul de rezervare și drumul spre plată
                    if (TS.Drawer) { TS.Drawer.open(); } else { window.location.href = '/cos'; }
                }
            };
        });

        // Pagina competiției cu locuri numerotate: alegi tribuna, apoi locul
        Alpine.data('seatPicker', function (event) {
            return {
                event: event, loading: true, failed: false, eventSeatingId: null,
                sections: [], active: null, selected: [], busy: false, error: '', max: MAX_SEATS,
                lei: lei, ticketsLabel: ticketsLabel,
                init: async function () {
                    try {
                        var meta = await (await fetch('/api/proxy.php?action=seating&event=' + event.id)).json();
                        this.eventSeatingId = meta && (meta.event_seating_id || (meta.data && meta.data.event_seating_id));
                        if (!this.eventSeatingId) { throw new Error('fără hartă'); }
                        var tiers = {};
                        ((meta.price_tiers || (meta.data && meta.data.price_tiers)) || []).forEach(function (t) { tiers[t.id] = t; });

                        var resp = await (await fetch('/api/proxy.php?action=seats&event=' + event.id)).json();
                        var seats = resp.data || resp.seats || resp || [];
                        if (!Array.isArray(seats) || !seats.length) { throw new Error('fără locuri'); }

                        // Locurile deja în coșul acestui vizitator (blocate de el) apar ca selectate, nu ca ocupate
                        var cart = Cart.get(), mine = {};
                        if (cart && cart.event.id === event.id) {
                            this.selected = cart.seats.slice();
                            cart.seats.forEach(function (x) { mine[x.seat_uid] = true; });
                        }

                        var map = {};
                        seats.forEach(function (st) {
                            var tier = tiers[st.price_tier_id];
                            var cents = st.price_cents != null ? st.price_cents : (tier ? tier.price_cents : null);
                            var price = cents != null ? cents / 100 : (st.price != null ? Number(st.price) : 0);
                            var name = st.section_name || t('Sală'), row = String(st.row_label || '');
                            var sec = map[name] || (map[name] = { name: name, rows: {} });
                            (sec.rows[row] || (sec.rows[row] = [])).push({
                                seat_uid: st.seat_uid, seat: String(st.seat_label || ''), row: row, price: price,
                                status: mine[st.seat_uid] ? 'available' : st.status
                            });
                        });
                        // „vest” înaintea lui „est”: altfel „Peluza Vest” s-ar potrivi cu „est”
                        var cardinal = [['nord', 'n'], ['sud', 's'], ['vest', 'w'], ['est', 'e']];
                        this.sections = Object.keys(map).map(function (name) {
                            var sec = map[name], lower = name.toLowerCase(), pos = '';
                            cardinal.forEach(function (c) { if (!pos && lower.indexOf(c[0]) !== -1) { pos = c[1]; } });
                            var rows = Object.keys(sec.rows).sort().map(function (label) {
                                return { label: label, seats: sec.rows[label].sort(function (a, b) { return (parseInt(a.seat, 10) || 0) - (parseInt(b.seat, 10) || 0); }) };
                            });
                            var all = [].concat.apply([], rows.map(function (r) { return r.seats; }));
                            var prices = all.map(function (x) { return x.price; }).filter(function (x) { return x > 0; });
                            return { name: name, pos: pos, rows: rows, total: all.length, from: prices.length ? Math.min.apply(null, prices) : 0 };
                        });
                        // Tribunele așezate în jurul suprafeței; dacă numele nu spun unde stau, rămân o listă simplă
                        var placed = this.sections.filter(function (x) { return x.pos; });
                        this.arena = placed.length === this.sections.length && placed.length >= 2;
                        // Se deschide pe tribuna cea mai mare
                        this.active = this.sections.slice().sort(function (a, b) { return b.total - a.total; })[0].name;
                    } catch (e) {
                        this.failed = true;
                    }
                    this.loading = false;
                },
                arena: false,
                get current() { var self = this; return this.sections.filter(function (x) { return x.name === self.active; })[0] || null; },
                // Rândurile se afișează cu cel mai apropiat de suprafața de concurs jos
                get currentRows() { return this.current ? this.current.rows.slice().reverse() : []; },
                free: function (sec) {
                    var n = 0;
                    sec.rows.forEach(function (r) { r.seats.forEach(function (x) { if (x.status === 'available') { n++; } }); });
                    return n;
                },
                priceLevels: function () {
                    var seen = {}, out = [];
                    this.sections.forEach(function (sec) { sec.rows.forEach(function (r) { r.seats.forEach(function (x) { if (x.price > 0 && !seen[x.price]) { seen[x.price] = 1; out.push(x.price); } }); }); });
                    return out.sort(function (a, b) { return b - a; });
                },
                isSelected: function (seat) { return this.selected.some(function (x) { return x.seat_uid === seat.seat_uid; }); },
                isTaken: function (seat) { return seat.status !== 'available' && !this.isSelected(seat); },
                seatClass: function (seat) {
                    if (this.isSelected(seat)) { return 'is-on'; }
                    if (this.isTaken(seat)) { return 'is-taken'; }
                    return seat.price >= (this.priceLevels()[0] || 0) && this.priceLevels().length > 1 ? 'is-top' : '';
                },
                seatLabel: function (seat) {
                    return t('{section}, rândul {row}, locul {seat}, {price}', { section: this.active, row: seat.row, seat: seat.seat, price: lei(seat.price) })
                        + (this.isSelected(seat) ? ', ' + t('selectat') : (this.isTaken(seat) ? ', ' + t('ocupat') : ''));
                },
                get total() { return this.selected.reduce(function (n, x) { return n + x.price; }, 0); },
                toggle: async function (seat) {
                    if (this.busy || this.isTaken(seat)) { return; }
                    this.error = '';
                    if (this.isSelected(seat)) { return this.remove(seat.seat_uid); }
                    if (this.selected.length >= this.max) { this.error = t('Poți alege cel mult {n} locuri pe comandă.', { n: this.max }); return; }
                    this.busy = true;
                    try {
                        var r = await fetch('/api/proxy.php?action=hold', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ event_seating_id: this.eventSeatingId, seat_uids: [seat.seat_uid] })
                        });
                        var d = await r.json().catch(function () { return {}; });
                        var failed = !r.ok || d.success === false || (Array.isArray(d.failed) && d.failed.length);
                        if (failed) {
                            var why = Array.isArray(d.failed) && d.failed[0] ? d.failed[0].reason : '';
                            if (why === 'max_holds_exceeded') {
                                this.error = t('Ai atins numărul maxim de locuri pentru o comandă.');
                            } else {
                                seat.status = 'held';
                                this.error = t('Locul tocmai a fost luat de altcineva. Alege altul.');
                            }
                        } else {
                            this.selected.push({
                                seat_uid: seat.seat_uid, section: this.active, row: seat.row, seat: seat.seat, price: seat.price,
                                label: t('{section} · Rând {row} · Loc {seat}', { section: this.active, row: seat.row, seat: seat.seat })
                            });
                            this.persist();
                        }
                    } catch (e) { this.error = t('Conexiunea a eșuat. Încearcă din nou.'); }
                    this.busy = false;
                },
                remove: function (uid) {
                    this.selected = this.selected.filter(function (x) { return x.seat_uid !== uid; });
                    Cart.releaseSeats({ event_seating_id: this.eventSeatingId }, [uid]);
                    this.persist();
                },
                // Coșul urmărește selecția în timp real, ca locurile blocate să nu se piardă la un refresh
                persist: function () {
                    Cart.merge(this.event, { seats: this.selected.slice(), event_seating_id: this.eventSeatingId });
                },
                save: function () {
                    if (!this.selected.length) { toast(t('Alege cel puțin un loc.')); return; }
                    this.persist();
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
                removeSeat: function (seat) {
                    Cart.releaseSeats(this.cart, [seat.seat_uid]);
                    this.cart.seats = this.cart.seats.filter(function (x) { return x.seat_uid !== seat.seat_uid; });
                    this.persist();
                },
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
                cart: null, lei: lei, busy: false, error: '', tried: false, pasteWarn: false, payMethod: 'card',
                user: null, accountEmail: '',
                form: { first_name: '', last_name: '', email: '', email2: '', phone: '', terms: false, newsletter: false, tixello_account: false, tixello_marketing: false, create_account: false, password: '' },
                init: function () {
                    this.cart = Cart.get();
                    this.refreshQuote();
                    var a = Auth.get();
                    if (a) {
                        var u = a.user, parts = Auth.name(u).split(/\s+/);
                        this.user = u;
                        this.form.first_name = u.first_name || parts[0] || '';
                        this.form.last_name = u.last_name || parts.slice(1).join(' ') || '';
                        this.form.phone = u.phone || '';
                        // Adresa contului e deja verificată: nu mai cerem s-o scrie de două ori
                        this.accountEmail = (u.email || '').trim().toLowerCase();
                        this.form.email = u.email || '';
                    }
                },
                // Biletele merg pe adresa contului cât timp cumpărătorul nu o schimbă
                usesAccountEmail: function () {
                    return this.accountEmail !== '' && this.form.email.trim().toLowerCase() === this.accountEmail;
                },
                emailOk: function () { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.form.email.trim()); },
                emailsMatch: function () {
                    if (this.usesAccountEmail()) { return true; }
                    return this.form.email2.trim() !== '' && this.form.email.trim().toLowerCase() === this.form.email2.trim().toLowerCase();
                },
                passwordOk: function () { return !this.form.create_account || this.form.password.length >= 8; },
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
                    if (this.bad('first_name') || this.bad('last_name')) { this.error = t('Completează prenumele și numele.'); return this.focusFirstBad(); }
                    if (!this.emailOk()) { this.error = t('Adresa de email nu pare corectă.'); return this.focusFirstBad(); }
                    if (!this.emailsMatch()) { this.error = t('Cele două adrese de email nu sunt identice. Verifică-le literă cu literă.'); return this.focusFirstBad(); }
                    if (!this.passwordOk()) { this.error = t('Parola contului trebuie să aibă cel puțin 8 caractere.'); return this.focusFirstBad(); }
                    if (!this.form.terms) { this.error = t('Trebuie să accepți termenii pentru a continua.'); return; }
                    this.busy = true;
                    try {
                        var r = await fetch('/api/proxy.php?action=checkout', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(Object.assign(Cart.payload(this.cart), {
                                customer: {
                                    first_name: this.form.first_name.trim(), last_name: this.form.last_name.trim(),
                                    email: this.form.email.trim(), phone: this.form.phone.trim() || null
                                },
                                coupon_code: this.cart.coupon_code || null,
                                create_account: !this.user && this.form.create_account,
                                password: (!this.user && this.form.create_account) ? this.form.password : null,
                                newsletter: this.form.newsletter,
                                tixello_account: this.form.tixello_account,
                                tixello_marketing: this.form.tixello_marketing,
                                payment_method: this.payMethod || 'card',
                                locale: locale(),
                                success_url: window.location.origin + '/confirmare',
                                cancel_url: window.location.origin + '/finalizare?plata=anulata'
                            }))
                        });
                        var d = await r.json().catch(function () { return {}; });
                        if (r.ok && d.success && d.redirect_url) {
                            Orders.remember(d.order_id, d.access_token);
                            if (d.account) { try { sessionStorage.setItem('site_account_note', d.account); } catch (e) {} }
                            window.location.href = d.redirect_url;
                            return;
                        }
                        this.error = d.error || d.message || t('Comanda nu a putut fi plasată. Încearcă din nou.');
                    } catch (e) {
                        this.error = t('Conexiunea a eșuat. Verifică internetul și încearcă din nou.');
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

        // Formularul de newsletter (comun): abonare cu acord explicit, în limba paginii.
        // Markup-ul îl pune template-ul (ex. secțiunea newsletter a template-ului artist).
        Alpine.data('newsletterForm', function () {
            return {
                email: '', first_name: '', consent: false, busy: false, done: false, error: '',
                submit: async function () {
                    this.error = '';
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(this.email.trim())) { this.error = t('Adresa de email nu pare corectă.'); return; }
                    if (!this.consent) { this.error = t('Bifează acordul ca să te putem abona.'); return; }
                    this.busy = true;
                    try {
                        var r = await fetch('/api/proxy.php?action=newsletter-subscribe', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ email: this.email.trim(), first_name: this.first_name.trim() || null, locale: locale(), consent: true })
                        });
                        var d = await r.json().catch(function () { return {}; });
                        if (r.ok && d.success) { this.done = true; }
                        else { this.error = r.status === 429 ? t('Prea multe încercări. Mai încearcă peste un minut.') : t('Nu te-am putut abona. Încearcă din nou.'); }
                    } catch (e) { this.error = t('Conexiunea a eșuat. Încearcă din nou.'); }
                    this.busy = false;
                }
            };
        });

        // Bara de cookies (comună): alegerea se ține în browser și se transmite prin Consent Mode.
        // Markup-ul îl pune template-ul; „Setări cookies” trimite evenimentul site:cookie-settings.
        Alpine.data('cookieBar', function () {
            return {
                open: false, details: false, analytics: false, marketing: false,
                init: function () {
                    var c = read('site_consent');
                    if (c && c.v === 1) { this.analytics = !!c.analytics; this.marketing = !!c.marketing; }
                    else { this.open = true; }
                },
                reopen: function () { this.details = true; this.open = true; },
                choose: function (all) { this.analytics = all; this.marketing = all; this.save(); },
                save: function () {
                    var c = { v: 1, analytics: !!this.analytics, marketing: !!this.marketing, ts: new Date().toISOString() };
                    write('site_consent', c);
                    var yes = function (on) { return on ? 'granted' : 'denied'; };
                    if (typeof window.gtag === 'function') {
                        window.gtag('consent', 'update', {
                            ad_storage: yes(c.marketing), ad_user_data: yes(c.marketing), ad_personalization: yes(c.marketing),
                            analytics_storage: yes(c.analytics)
                        });
                    }
                    window.dispatchEvent(new CustomEvent('site:consent', { detail: c }));
                    this.open = false; this.details = false;
                }
            };
        });
    });
})();
