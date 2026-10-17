/* Rezervarea unui produs de agrement (template leisure): ziua din calendarul
   real (/leisure/availability), intervalul orar (/leisure/slots), durata și
   numărul de persoane, apoi linia în coșul comun (TS.Cart, commerce.js).

   Folosire într-o componentă Alpine:
   cfg = { ticketTypeId, name, category, priceCents, variants:[{duration_minutes,label,price_cents}],
           event:{id,slug,title,place,poster,url} }
   Cu getteri în restul componentei: LVBooking.with(cfg, { ... }).
   Prețul afișat e orientativ; prețul real îl calculează core la /cos (quote). */
(function () {
    var MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
    var DAYS = ['Dum', 'Lun', 'Mar', 'Mie', 'Joi', 'Vin', 'Sâm'];
    var OPEN = ['available', 'limited'];

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function iso(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
    function ym(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1); }
    function parse(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +(p[2] || 1)); }
    function lei(cents) {
        var n = (Number(cents) || 0) / 100;
        return window.TS ? window.TS.lei(n) : n + ' lei';
    }
    async function getJson(url) {
        try {
            var r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            var j = await r.json();
            return (j && j.success) ? (j.data || {}) : null;
        } catch (e) { return null; }
    }

    window.LVBooking = function (cfg) {
        var today = new Date(); today.setHours(0, 0, 0, 0);
        var variants = (cfg.variants || []).filter(function (v) { return v && v.duration_minutes; });
        return {
            lv: cfg,
            lvVariants: variants,
            lvVariant: 0,
            lvMonth: ym(today),
            lvDays: {},              // 'YYYY-MM-DD' → {status, remaining, min_price_cents, slot_count}
            lvLoaded: {},            // lunile deja cerute
            lvLoadingMonth: false,
            lvDate: null,
            lvSlots: [],
            lvLoadingSlots: false,
            lvSlotId: null,
            lvQty: 1,
            lvError: '',

            async lvInit() {
                await this.lvLoadMonth(this.lvMonth);
                // Dacă luna curentă e închisă, încarcă și luna următoare pentru banda de zile
                if (this.lvStrip.length < 7) {
                    var n = parse(this.lvMonth); n.setMonth(n.getMonth() + 1);
                    await this.lvLoadMonth(ym(n), true);
                }
                var first = this.lvStrip[0];
                if (first) { this.lvPickDate(first.date); }
            },
            async lvLoadMonth(month, keepView) {
                if (!keepView) { this.lvMonth = month; }
                if (this.lvLoaded[month]) { return; }
                this.lvLoadingMonth = true;
                var data = await getJson('/api/proxy.php?action=leisure-availability&ticket_type=' + encodeURIComponent(this.lv.ticketTypeId) + '&month=' + month);
                this.lvLoaded[month] = true;
                if (data && data.dates) { this.lvDays = Object.assign({}, this.lvDays, data.dates); }
                this.lvLoadingMonth = false;
            },
            lvShiftMonth(delta) {
                var d = parse(this.lvMonth); d.setMonth(d.getMonth() + delta);
                if (delta < 0 && ym(d) < ym(today)) { return; }
                this.lvLoadMonth(ym(d));
            },
            get lvCanPrev() { return this.lvMonth > ym(today); },
            get lvMonthLabel() { var d = parse(this.lvMonth); return MONTHS[d.getMonth()] + ' ' + d.getFullYear(); },
            lvIsOpen(date) {
                var d = this.lvDays[date];
                return !!d && OPEN.indexOf(d.status) !== -1 && date >= iso(today);
            },
            // Grila lunii (luni ca prima zi a săptămânii)
            get lvCells() {
                var first = parse(this.lvMonth), cells = [];
                var lead = (first.getDay() + 6) % 7;
                for (var i = 0; i < lead; i++) { cells.push({ key: 'e' + i, empty: true }); }
                var d = new Date(first);
                while (d.getMonth() === first.getMonth()) {
                    var k = iso(d), info = this.lvDays[k] || null;
                    cells.push({
                        key: k, date: k, day: d.getDate(), past: k < iso(today),
                        open: this.lvIsOpen(k), status: info ? info.status : 'none',
                        price: info && info.min_price_cents != null ? lei(info.min_price_cents) : ''
                    });
                    d.setDate(d.getDate() + 1);
                }
                return cells;
            },
            // Următoarele zile deschise, pentru banda orizontală
            get lvStrip() {
                var self = this;
                return Object.keys(this.lvDays).sort().filter(function (k) { return self.lvIsOpen(k); }).slice(0, 14).map(function (k, i) {
                    var d = parse(k), info = self.lvDays[k];
                    var label = k === iso(today) ? 'azi' : (i === 0 || d.getDate() === 1 ? MONTHS[d.getMonth()].slice(0, 3) : '');
                    return { date: k, day: DAYS[d.getDay()], number: d.getDate(), label: label, limited: info.status === 'limited' };
                });
            },
            async lvPickDate(date) {
                if (!this.lvIsOpen(date)) { return; }
                this.lvDate = date;
                this.lvSlotId = null;
                this.lvSlots = [];
                this.lvError = '';
                this.lvLoadingSlots = true;
                var data = await getJson('/api/proxy.php?action=leisure-slots&ticket_type=' + encodeURIComponent(this.lv.ticketTypeId) + '&date=' + date);
                if (this.lvDate !== date) { return; }
                this.lvSlots = (data && data.slots) ? data.slots.map(function (s) {
                    return Object.assign({}, s, { open: OPEN.indexOf(s.status) !== -1 && s.remaining > 0 });
                }) : [];
                this.lvLoadingSlots = false;
                var open = this.lvSlots.filter(function (s) { return s.open; });
                // Accesul pe toată ziua are un singur rând fără oră: îl alegem direct
                if (this.lvWholeDay && open.length) { this.lvSlotId = open[0].id; }
                if (this.lvQty > this.lvMaxQty) { this.lvQty = Math.max(1, this.lvMaxQty); }
            },
            get lvWholeDay() { return this.lvSlots.length > 0 && this.lvSlots.every(function (s) { return !s.time_slot_start; }); },
            get lvTimed() { return this.lvSlots.filter(function (s) { return !!s.time_slot_start; }); },
            get lvSlot() { var id = this.lvSlotId; return this.lvSlots.find(function (s) { return s.id === id; }) || null; },
            lvPickSlot(slot) { if (slot.open) { this.lvSlotId = slot.id; if (this.lvQty > this.lvMaxQty) { this.lvQty = Math.max(1, this.lvMaxQty); } } },
            get lvMaxQty() {
                var cap = window.TS ? window.TS.MAX_PER_TYPE : 10;
                return this.lvSlot ? Math.min(cap, this.lvSlot.remaining) : cap;
            },
            lvInc() { if (this.lvQty < this.lvMaxQty) { this.lvQty++; } },
            lvDec() { if (this.lvQty > 1) { this.lvQty--; } },
            get lvUnitCents() {
                var v = this.lvVariants[this.lvVariant];
                if (v) { return v.price_cents; }
                if (this.lvSlot) { return this.lvSlot.price_cents; }
                return this.lv.priceCents;
            },
            get lvUnit() { return lei(this.lvUnitCents); },
            get lvTotal() { return lei(this.lvUnitCents * this.lvQty); },
            get lvDateLabel() {
                if (!this.lvDate) { return ''; }
                var d = parse(this.lvDate);
                return DAYS[d.getDay()] + ', ' + d.getDate() + ' ' + MONTHS[d.getMonth()];
            },
            get lvWhen() {
                if (!this.lvDate) { return ''; }
                var s = this.lvSlot, label = this.lvDateLabel;
                if (s && s.time_slot_start) { label += ' · ' + s.time_slot_start + (s.time_slot_end ? '–' + s.time_slot_end : ''); }
                var v = this.lvVariants[this.lvVariant];
                if (v) { label += ' · ' + (v.label || v.duration_minutes + ' min'); }
                return label;
            },
            get lvReady() { return !!(this.lvDate && this.lvSlot && this.lvQty > 0); },
            get lvNoDates() { return !this.lvLoadingMonth && Object.keys(this.lvLoaded).length > 0 && this.lvStrip.length === 0; },
            lvAddToCart(goToCart) {
                if (!this.lvDate) { this.lvError = 'Alege ziua vizitei.'; return false; }
                if (!this.lvSlot) { this.lvError = this.lvWholeDay ? 'Nu mai sunt locuri în ziua aleasă.' : 'Alege intervalul orar.'; return false; }
                if (!window.TS) { return false; }
                var TS = window.TS, v = this.lvVariants[this.lvVariant], s = this.lvSlot;
                var line = {
                    ticket_type_id: this.lv.ticketTypeId,
                    name: this.lv.name + (v ? ' · ' + (v.label || v.duration_minutes + ' min') : ''),
                    price: this.lvUnitCents / 100,
                    qty: this.lvQty,
                    visit_date: this.lvDate,
                    slot_time: s.time_slot_start || null,
                    capacity_id: s.id,
                    duration_minutes: v ? v.duration_minutes : null,
                    when: this.lvWhen
                };
                var prev = TS.Cart.get(), same = prev && prev.event && prev.event.id === this.lv.event.id && !(prev.seats || []).length;
                var items = same ? prev.items.slice() : [];
                var key = TS.Cart.lineKey(line), found = items.find(function (i) { return TS.Cart.lineKey(i) === key && i.duration_minutes == line.duration_minutes; });
                if (found) { found.qty = Math.min(found.qty + line.qty, this.lvMaxQty); } else { items.push(line); }
                TS.Cart.set({
                    event: Object.assign({ date: this.lvDate }, this.lv.event),
                    items: items,
                    expires_at: same ? prev.expires_at : null,
                    coupon_code: same ? (prev.coupon_code || null) : null
                });
                this.lvError = '';
                if (goToCart !== false) { window.location.href = '/cos'; }
                else { TS.toast(this.lv.name + ' a fost adăugat în coș.'); }
                return true;
            },
            // Schimbă produsul rezervat (sertarul de pe pagina de start)
            lvSetProduct(next) {
                this.lv = next;
                this.lvVariants = (next.variants || []).filter(function (v) { return v && v.duration_minutes; });
                this.lvVariant = 0;
                this.lvDays = {}; this.lvLoaded = {}; this.lvMonth = ym(today);
                this.lvDate = null; this.lvSlots = []; this.lvSlotId = null; this.lvQty = 1; this.lvError = '';
                return this.lvInit();
            },
            lvLei: lei
        };
    };

    // Lipește restul componentei peste rezervare păstrând getterii (Object.assign i-ar evalua)
    window.LVBooking.with = function (cfg, page) {
        var o = window.LVBooking(cfg);
        Object.defineProperties(o, Object.getOwnPropertyDescriptors(page || {}));
        return o;
    };
})();
