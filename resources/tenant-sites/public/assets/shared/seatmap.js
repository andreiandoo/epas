/* Harta de locuri comună tuturor template-urilor: desenează sala din geometria publicată în
   core (secțiuni, rânduri, locuri, mese), cu zoom (rotiță, butoane, două degete) și deplasare,
   blochează locul pe server la click și ține coșul sincronizat (TS.Cart.merge).

     TS.SeatMap.open(event)          ecran întreg, peste pagină (din lista de bilete)
     TS.SeatMap.mount(el, event)     în pagină (sala e alegerea principală, ex. teatru)

   event = evenimentul în forma coșului: {id, slug, title, date, place, poster, url}.
   Fără Alpine; stilurile în chrome.css (.tsx-sm). */
(function () {
    'use strict';
    var TS = window.TS;
    if (!TS) { return; }
    var t = TS.t, lei = TS.lei;
    var MAX = 10, NS = 'http://www.w3.org/2000/svg';
    var PALETTE = ['#E4572E', '#F3A712', '#2EC4B6', '#5C7AEA', '#C86BFA', '#4CAF50', '#EC407A', '#90A4AE'];
    var ICON_X = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function num(v, d) { v = parseFloat(v); return isFinite(v) ? v : (d || 0); }
    function rot(px, py, cx, cy, deg) {
        if (!deg) { return [px, py]; }
        var a = deg * Math.PI / 180, c = Math.cos(a), s = Math.sin(a), dx = px - cx, dy = py - cy;
        return [cx + dx * c - dy * s, cy + dx * s + dy * c];
    }
    async function getJson(url) {
        var r = await fetch(url, { headers: { Accept: 'application/json' } });
        if (!r.ok) { throw new Error('HTTP ' + r.status); }
        return r.json();
    }

    function SeatMap(event, host, modal) {
        this.event = event; this.host = host; this.modal = modal;
        this.selected = []; this.busy = false; this.seats = {}; this.els = {};
        this.view = null; this.home = null; this.pointers = {}; this.moved = false;
        this.build();
        this.load();
    }

    SeatMap.prototype.build = function () {
        var ev = this.event;
        this.host.classList.add('tsx-sm', this.modal ? 'tsx-sm--modal' : 'tsx-sm--inline');
        this.host.innerHTML =
            (this.modal ? '<div class="tsx-sm__top"><h2>' + esc(t('Alege locurile')) + '<small>' + esc([ev.title, ev.date, ev.place].filter(Boolean).join(' · ')) + '</small></h2>' +
                '<button type="button" class="tsx-x" data-sm-close aria-label="' + esc(t('Închide')) + '">' + ICON_X + '</button></div>' : '') +
            '<div class="tsx-sm__wrap">' +
                '<div class="tsx-sm__stage" role="application" aria-label="' + esc(t('Harta sălii')) + '">' +
                    '<div class="tsx-sm__state"><div><div class="tsx-sm__spin"></div>' + esc(t('Se încarcă harta sălii…')) + '</div></div>' +
                '</div>' +
                '<div class="tsx-sm__side">' +
                    '<div class="tsx-sm__sel" aria-live="polite"></div>' +
                    '<div class="tsx-sm__foot"><button type="button" class="tsx-btn" data-sm-done disabled>' + esc(t('Alege un loc de pe hartă')) + '</button></div>' +
                '</div>' +
            '</div>';
        this.stage = this.host.querySelector('.tsx-sm__stage');
        this.sel = this.host.querySelector('.tsx-sm__sel');
        this.done = this.host.querySelector('[data-sm-done]');
        var self = this;
        this.host.addEventListener('click', function (e) {
            if (e.target.closest('[data-sm-close]')) { self.close(); }
            var rm = e.target.closest('[data-sm-rm]');
            if (rm) { self.release(rm.getAttribute('data-sm-rm')); }
            var z = e.target.closest('[data-sm-zoom]');
            if (z) { var k = z.getAttribute('data-sm-zoom'); k === 'reset' ? self.reset() : self.zoomBy(k === 'in' ? 0.7 : 1 / 0.7); }
        });
        this.done.addEventListener('click', function () {
            if (!self.selected.length) { return; }
            if (self.modal) { self.close(); }
            if (TS.Drawer) { TS.Drawer.open(); } else { location.href = '/cos'; }
        });
        if (this.modal) {
            this.onKey = function (e) { if (e.key === 'Escape') { self.close(); } };
            document.addEventListener('keydown', this.onKey);
        }
        this.renderSel();
    };

    SeatMap.prototype.load = async function () {
        var ev = this.event;
        try {
            var meta = await getJson('/api/proxy.php?action=seating&event=' + ev.id);
            meta = meta && meta.data && !meta.sections ? meta.data : meta;
            if (!meta || !meta.event_seating_id || !Array.isArray(meta.sections)) { throw new Error('fără hartă'); }
            this.eventSeatingId = meta.event_seating_id;
            var tiers = {};
            (meta.price_tiers || []).forEach(function (x) { tiers[x.id] = x; });
            this.tiers = tiers;
            // Culoarea fiecărei secțiuni din desen, pentru prețurile fără culoare de categorie
            var secColor = {};
            meta.sections.forEach(function (x) { if (x && x.name && (x.color || x.color_hex)) { secColor[x.name] = x.color || x.color_hex; } });
            this.secColor = secColor;

            var resp = await getJson('/api/proxy.php?action=seats&event=' + ev.id);
            var list = resp.seats || resp.data || (Array.isArray(resp) ? resp : []);
            if (!list.length) { throw new Error('fără locuri'); }

            // Locurile deja în coșul acestui vizitator (blocate de el) apar selectate, nu ocupate
            var cart = TS.Cart.get(), mine = {};
            if (cart && cart.event.id === ev.id) {
                this.selected = (cart.seats || []).slice();
                this.selected.forEach(function (x) { mine[x.seat_uid] = true; });
            }
            var self = this;
            list.forEach(function (s) {
                var tier = tiers[s.price_tier_id];
                var cents = s.price_cents != null ? s.price_cents : (tier ? tier.price_cents : null);
                self.seats[s.seat_uid] = {
                    uid: s.seat_uid, section: s.section_name || '', row: String(s.row_label || ''), seat: String(s.seat_label || ''),
                    price: cents != null ? cents / 100 : num(s.price), tier: s.price_tier_id,
                    status: mine[s.seat_uid] ? 'available' : (s.status || 'available')
                };
            });
            this.colors();
            this.draw(meta);
            this.renderSel();
            this.emit(true);
            // Locurile scoase din coș în altă parte (sertar, pagina coșului, alt tab) dispar și de pe hartă
            var sync = function () {
                var c = TS.Cart.get(), keep = {};
                ((c && c.event && c.event.id === ev.id) ? c.seats : []).forEach(function (x) { keep[x.seat_uid] = true; });
                var gone = self.selected.filter(function (x) { return !keep[x.seat_uid]; });
                if (!gone.length) { return; }
                self.selected = self.selected.filter(function (x) { return keep[x.seat_uid]; });
                gone.forEach(function (x) { self.paint(x.seat_uid); });
                self.renderSel();
            };
            window.addEventListener('site:cart', sync);
            window.addEventListener('storage', function (e) { if (e.key === 'site_cart') { sync(); } });
        } catch (e) {
            this.emit(false);
            this.stage.innerHTML = '<div class="tsx-sm__state"><div>' + esc(t('Harta sălii nu poate fi afișată acum. Încearcă din nou în câteva minute.')) + '</div></div>';
        }
    };

    // O culoare pe nivel de preț (a categoriei din core, dacă are), de la scump la ieftin
    SeatMap.prototype.colors = function () {
        var prices = {}, sectionOf = {}, self = this;
        Object.keys(this.seats).forEach(function (k) {
            var s = self.seats[k];
            if (s.price > 0) { prices[s.price] = s.tier; if (!sectionOf[s.price]) { sectionOf[s.price] = s.section; } }
        });
        this.levels = Object.keys(prices).map(Number).sort(function (a, b) { return b - a; }).map(function (p, i) {
            var tier = self.tiers[prices[p]];
            var color = (tier && (tier.color_hex || tier.color)) || self.secColor[sectionOf[p]] || PALETTE[i % PALETTE.length];
            return { price: p, color: color, name: tier ? tier.name : '' };
        });
        // Două niveluri cu aceeași culoare de secțiune (ex. „Parter” cu două prețuri) se deosebesc din paletă
        var used = {};
        this.levels.forEach(function (l, i) { if (used[l.color]) { l.color = PALETTE[i % PALETTE.length]; } used[l.color] = 1; });
        this.colorOf = {};
        this.levels.forEach(function (l) { self.colorOf[l.price] = l.color; });
    };

    SeatMap.prototype.draw = function (meta) {
        var self = this, svgParts = [], minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
        function grow(x, y, pad) { pad = pad || 0; minX = Math.min(minX, x - pad); minY = Math.min(minY, y - pad); maxX = Math.max(maxX, x + pad); maxY = Math.max(maxY, y + pad); }

        meta.sections.forEach(function (sec) {
            var sx = num(sec.x), sy = num(sec.y), sw = num(sec.width), sh = num(sec.height), r = num(sec.rotation);
            var cx = sx + sw / 2, cy = sy + sh / 2, md = sec.metadata || {};
            var size = parseInt(md.seat_size, 10) || 15, rad = size / 2;
            var rows = Array.isArray(sec.rows) ? sec.rows : [];
            var g = ['<g' + (r ? ' transform="rotate(' + r + ' ' + cx + ' ' + cy + ')"' : '') + '>'];
            var hasSeats = rows.some(function (row) { return (row.seats || []).length; });
            var color = sec.color || sec.color_hex || '#888';

            if (!hasSeats && !rows.some(function (row) { return row.is_table; })) {
                // Zonă fără locuri (scena, ringul, accesul general): doar un contur cu nume
                g.push('<rect x="' + sx + '" y="' + sy + '" width="' + sw + '" height="' + sh + '" rx="10" fill="' + color + '" fill-opacity=".14" stroke="' + color + '" stroke-opacity=".7" stroke-width="2" stroke-dasharray="7 5"/>');
                g.push('<text x="' + cx + '" y="' + (cy + 6) + '" text-anchor="middle" font-size="' + Math.max(14, Math.min(28, sh / 4)) + '" font-weight="700" fill="currentColor" fill-opacity=".75" style="pointer-events:none">' + esc(sec.name) + '</text>');
                [[sx, sy], [sx + sw, sy], [sx, sy + sh], [sx + sw, sy + sh]].forEach(function (p) { var q = rot(p[0], p[1], cx, cy, r); grow(q[0], q[1], 4); });
                g.push('</g>');
                svgParts.push(g.join(''));
                return;
            }

            var lx = Infinity, ly = Infinity, hx = -Infinity;
            rows.forEach(function (row) {
                if (row.is_table) {
                    var tx = sx + num(row.center_x), ty = sy + num(row.center_y);
                    if (row.table_type === 'round') {
                        g.push('<circle cx="' + tx + '" cy="' + ty + '" r="' + num(row.radius, 30) + '" fill="currentColor" fill-opacity=".12" stroke="currentColor" stroke-opacity=".3"/>');
                    } else {
                        var tw = num(row.table_width, 80), th = num(row.table_height, 30);
                        g.push('<rect x="' + (tx - tw / 2) + '" y="' + (ty - th / 2) + '" width="' + tw + '" height="' + th + '" rx="4" fill="currentColor" fill-opacity=".12" stroke="currentColor" stroke-opacity=".3"/>');
                    }
                    g.push('<text x="' + tx + '" y="' + (ty + 4) + '" text-anchor="middle" font-size="10" font-weight="700" fill="currentColor" fill-opacity=".6" style="pointer-events:none">' + esc(row.label) + '</text>');
                    var q0 = rot(tx, ty, cx, cy, r); grow(q0[0], q0[1], num(row.radius, 40));
                }
                var seats = row.seats || [];
                if (seats.length && !row.is_table) {
                    var first = seats.reduce(function (a, b) { return num(a.x) <= num(b.x) ? a : b; });
                    g.push('<text x="' + (sx + num(first.x) - rad * 2.2) + '" y="' + (sy + num(first.y) + rad * 0.45) + '" text-anchor="end" font-size="' + Math.max(9, rad * 1.05) + '" font-weight="600" fill="currentColor" fill-opacity=".5" style="pointer-events:none">' + esc(row.label) + '</text>');
                }
                seats.forEach(function (st) {
                    var x = sx + num(st.x), y = sy + num(st.y), info = self.seats[st.seat_uid];
                    lx = Math.min(lx, x); ly = Math.min(ly, y); hx = Math.max(hx, x);
                    var q = rot(x, y, cx, cy, r); grow(q[0], q[1], rad + 4);
                    if (!info) {
                        // Loc din desen fără inventar pe eveniment (blocat sau scos din vânzare)
                        g.push('<circle cx="' + x + '" cy="' + y + '" r="' + rad + '" fill="currentColor" fill-opacity=".08"/>');
                        return;
                    }
                    g.push('<circle class="tsx-seat" data-uid="' + esc(st.seat_uid) + '" cx="' + x + '" cy="' + y + '" r="' + rad + '"/>');
                });
            });
            if (lx < Infinity) {
                var tx2 = (lx + hx) / 2, ty2 = ly - rad * 2.6;
                g.push('<text x="' + tx2 + '" y="' + ty2 + '" text-anchor="middle" font-size="' + Math.max(13, rad * 1.5) + '" font-weight="700" letter-spacing=".04em" fill="currentColor" fill-opacity=".8" style="pointer-events:none">' + esc(sec.name) + '</text>');
                var q2 = rot(tx2, ty2, cx, cy, r); grow(q2[0], q2[1] - 18, 6);
            }
            g.push('</g>');
            svgParts.push(g.join(''));
        });

        if (minX === Infinity) { throw new Error('hartă goală'); }
        var pad = 40;
        this.home = { x: minX - pad, y: minY - pad, w: (maxX - minX) + pad * 2, h: (maxY - minY) + pad * 2 };
        this.view = Object.assign({}, this.home);
        var legend = this.levels.map(function (l) { return '<span><i style="background:' + esc(l.color) + '"></i>' + esc((l.name ? l.name + ' · ' : '') + lei(l.price)) + '</span>'; }).join('') +
            '<span><i style="background:rgb(var(--c-fg));box-shadow:0 0 0 2px rgb(var(--c-primary))"></i>' + esc(t('Ales de tine')) + '</span>' +
            '<span><i style="background:rgb(var(--c-fg) / .18)"></i>' + esc(t('Ocupat')) + '</span>';
        this.stage.innerHTML =
            '<svg xmlns="' + NS + '" preserveAspectRatio="xMidYMid meet" style="color:rgb(var(--c-fg))">' + svgParts.join('') + '</svg>' +
            '<div class="tsx-sm__zoom"><button type="button" data-sm-zoom="in" aria-label="' + esc(t('Mărește')) + '">+</button>' +
            '<button type="button" data-sm-zoom="out" aria-label="' + esc(t('Micșorează')) + '">−</button>' +
            '<button type="button" data-sm-zoom="reset" aria-label="' + esc(t('Toată sala')) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg></button></div>' +
            '<div class="tsx-sm__legend">' + legend + '</div>' +
            (!this.modal ? '<div class="tsx-sm__stagehint">' + esc(t('Ctrl + rotița pentru zoom')) + '</div>' : '');
        this.svg = this.stage.querySelector('svg');
        var hint = this.stage.querySelector('.tsx-sm__stagehint');
        if (hint) { setTimeout(function () { hint.style.transition = 'opacity .6s'; hint.style.opacity = '0'; }, 3500); }
        this.svg.querySelectorAll('.tsx-seat').forEach(function (el) {
            var uid = el.getAttribute('data-uid');
            self.els[uid] = el;
            self.paint(uid);
        });
        this.apply();
        this.bindPanZoom();
    };

    // Pagina află dacă evenimentul are hartă (ex. teatrul arată biletele simple când nu are)
    SeatMap.prototype.emit = function (ok) {
        this.host.dispatchEvent(new CustomEvent('ts-seatmap', { bubbles: true, detail: { ok: ok } }));
    };

    SeatMap.prototype.isSel = function (uid) { return this.selected.some(function (x) { return x.seat_uid === uid; }); };
    SeatMap.prototype.paint = function (uid) {
        var el = this.els[uid], s = this.seats[uid];
        if (!el || !s) { return; }
        var on = this.isSel(uid), taken = !on && s.status !== 'available';
        // Prin style: variabilele temei (var(--c-…)) nu merg în atributele SVG
        el.style.fill = on ? 'rgb(var(--c-fg))' : (taken ? 'rgb(var(--c-fg) / .16)' : (this.colorOf[s.price] || '#999'));
        el.style.stroke = on ? 'rgb(var(--c-primary))' : 'none';
        el.style.strokeWidth = on ? '3' : '0';
        el.setAttribute('class', 'tsx-seat' + (taken ? ' is-taken' : '') + (on ? ' is-on' : ''));
        el.setAttribute('tabindex', taken ? '-1' : '0');
        el.setAttribute('role', 'button');
        el.setAttribute('aria-pressed', on ? 'true' : 'false');
        el.setAttribute('aria-label', t('{section}, rândul {row}, locul {seat}, {price}', { section: s.section, row: s.row, seat: s.seat, price: lei(s.price) }) +
            (on ? ', ' + t('selectat') : (taken ? ', ' + t('ocupat') : '')));
    };

    /* ---------- zoom și deplasare (viewBox) */
    SeatMap.prototype.apply = function () {
        var v = this.view;
        this.svg.setAttribute('viewBox', v.x + ' ' + v.y + ' ' + v.w + ' ' + v.h);
    };
    SeatMap.prototype.reset = function () { this.view = Object.assign({}, this.home); this.apply(); };
    SeatMap.prototype.toSvg = function (clientX, clientY) {
        var r = this.svg.getBoundingClientRect(), v = this.view;
        // „meet”: scara e dată de latura mai strânsă, conținutul e centrat pe cealaltă
        var k = Math.min(r.width / v.w, r.height / v.h), ox = (r.width - v.w * k) / 2, oy = (r.height - v.h * k) / 2;
        return { x: v.x + (clientX - r.left - ox) / k, y: v.y + (clientY - r.top - oy) / k, k: k };
    };
    SeatMap.prototype.zoomBy = function (f, clientX, clientY) {
        var v = this.view, home = this.home;
        var w = Math.max(home.w / 10, Math.min(home.w * 1.4, v.w * f));
        f = w / v.w;
        var r = this.svg.getBoundingClientRect();
        var p = clientX == null ? this.toSvg(r.left + r.width / 2, r.top + r.height / 2) : this.toSvg(clientX, clientY);
        this.view = { x: p.x - (p.x - v.x) * f, y: p.y - (p.y - v.y) * f, w: v.w * f, h: v.h * f };
        this.apply();
    };
    SeatMap.prototype.bindPanZoom = function () {
        var self = this, st = this.stage;
        st.addEventListener('wheel', function (e) {
            if (!self.modal && !(e.ctrlKey || e.metaKey)) { return; }
            e.preventDefault();
            self.zoomBy(Math.exp(e.deltaY * 0.0015), e.clientX, e.clientY);
        }, { passive: false });
        st.addEventListener('pointerdown', function (e) {
            if (e.target.closest('button')) { return; }
            self.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            self.moved = false;
            self.startView = Object.assign({}, self.view);
            self.startPts = JSON.parse(JSON.stringify(self.pointers));
            st.setPointerCapture(e.pointerId);
        });
        st.addEventListener('pointermove', function (e) {
            if (!self.pointers[e.pointerId]) { return; }
            self.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            var ids = Object.keys(self.pointers);
            if (ids.length === 1) {
                var a = self.startPts[ids[0]], dx = e.clientX - a.x, dy = e.clientY - a.y;
                if (!self.moved && Math.abs(dx) + Math.abs(dy) < 6) { return; }
                self.moved = true; st.classList.add('is-pan');
                var k = self.toSvg(0, 0).k;
                self.view = { x: self.startView.x - dx / k, y: self.startView.y - dy / k, w: self.startView.w, h: self.startView.h };
                self.apply();
            } else if (ids.length === 2 && self.startPts[ids[0]] && self.startPts[ids[1]]) {
                self.moved = true;
                var p0 = self.startPts[ids[0]], p1 = self.startPts[ids[1]], c0 = self.pointers[ids[0]], c1 = self.pointers[ids[1]];
                var d0 = Math.hypot(p0.x - p1.x, p0.y - p1.y), d1 = Math.hypot(c0.x - c1.x, c0.y - c1.y);
                if (d0 > 0 && d1 > 0) {
                    self.view = Object.assign({}, self.startView);
                    self.zoomBy(d0 / d1, (c0.x + c1.x) / 2, (c0.y + c1.y) / 2);
                }
            }
        });
        function up(e) {
            var wasMove = self.moved;
            delete self.pointers[e.pointerId];
            st.classList.remove('is-pan');
            if (Object.keys(self.pointers).length) { self.startView = Object.assign({}, self.view); self.startPts = JSON.parse(JSON.stringify(self.pointers)); return; }
            if (!wasMove && e.type === 'pointerup') {
                var hit = document.elementFromPoint(e.clientX, e.clientY);
                var seat = hit && hit.closest && hit.closest('.tsx-seat');
                if (seat && st.contains(seat)) { self.toggle(seat.getAttribute('data-uid')); }
            }
        }
        st.addEventListener('pointerup', up);
        st.addEventListener('pointercancel', up);
        st.addEventListener('keydown', function (e) {
            var seat = e.target.closest && e.target.closest('.tsx-seat');
            if (seat && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); self.toggle(seat.getAttribute('data-uid')); }
        });
    };

    /* ---------- selecție: blocare pe server + coș */
    SeatMap.prototype.toggle = async function (uid) {
        var s = this.seats[uid];
        if (!s || this.busy) { return; }
        this.error = '';
        if (this.isSel(uid)) { return this.release(uid); }
        if (s.status !== 'available') { return; }
        if (this.selected.length >= MAX) { this.error = t('Poți alege cel mult {n} locuri pe comandă.', { n: MAX }); return this.renderSel(); }
        this.busy = true;
        if (this.els[uid]) { this.els[uid].style.opacity = '.45'; }
        try {
            var r = await fetch('/api/proxy.php?action=hold', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ event_seating_id: this.eventSeatingId, seat_uids: [uid] })
            });
            var d = await r.json().catch(function () { return {}; });
            var failed = !r.ok || d.success === false || (Array.isArray(d.failed) && d.failed.length);
            if (failed) {
                var why = Array.isArray(d.failed) && d.failed[0] ? d.failed[0].reason : '';
                if (why === 'max_holds_exceeded') { this.error = t('Ai atins numărul maxim de locuri pentru o comandă.'); }
                else { s.status = 'held'; this.error = t('Locul tocmai a fost luat de altcineva. Alege altul.'); }
            } else {
                this.selected.push({
                    seat_uid: uid, section: s.section, row: s.row, seat: s.seat, price: s.price,
                    label: t('{section} · Rând {row} · Loc {seat}', { section: s.section, row: s.row, seat: s.seat })
                });
                this.persist();
            }
        } catch (e) { this.error = t('Conexiunea a eșuat. Încearcă din nou.'); }
        if (this.els[uid]) { this.els[uid].style.opacity = ''; }
        this.busy = false;
        this.paint(uid);
        this.renderSel();
    };
    SeatMap.prototype.release = function (uid) {
        this.selected = this.selected.filter(function (x) { return x.seat_uid !== uid; });
        TS.Cart.releaseSeats({ event_seating_id: this.eventSeatingId }, [uid]);
        this.persist();
        this.paint(uid);
        this.renderSel();
    };
    SeatMap.prototype.persist = function () {
        TS.Cart.merge(this.event, { seats: this.selected.slice(), event_seating_id: this.eventSeatingId });
    };
    SeatMap.prototype.renderSel = function () {
        var html = '<h3>' + esc(t('Locurile tale')) + '</h3>';
        if (!this.selected.length) {
            html += '<p class="tsx-sm__hint">' + esc(t('Atinge un loc liber de pe hartă ca să-l rezervi. Îl ținem pentru tine 15 minute, cât termini comanda.')) + '</p>';
        } else {
            html += '<ul>' + this.selected.map(function (x) {
                return '<li><span>' + esc(x.section) + '<small>' + esc(t('Rând {row} · Loc {seat}', { row: x.row, seat: x.seat })) + '</small></span><b>' + esc(lei(x.price)) + '</b>' +
                    '<button type="button" data-sm-rm="' + esc(x.seat_uid) + '" aria-label="' + esc(t('Scoate locul')) + '">' + ICON_X + '</button></li>';
            }).join('') + '</ul>';
        }
        if (this.error) { html += '<p class="tsx-sm__err" role="alert">' + esc(this.error) + '</p>'; }
        this.sel.innerHTML = html;
        var total = this.selected.reduce(function (n, x) { return n + x.price; }, 0);
        this.done.disabled = !this.selected.length;
        this.done.textContent = this.selected.length
            ? t('Continuă cu {n} · {sum}', { n: TS.ticketsLabel(this.selected.length), sum: lei(total) })
            : t('Alege un loc de pe hartă');
    };

    SeatMap.prototype.close = function () {
        if (!this.modal) { return; }
        this.host.hidden = true;
        document.documentElement.style.overflow = '';
        if (window.tsLenis) { window.tsLenis.start(); }
        document.removeEventListener('keydown', this.onKey);
        if (this.returnFocus && this.returnFocus.focus) { this.returnFocus.focus(); }
        this.host.remove();
    };

    TS.SeatMap = {
        open: function (event) {
            var host = document.createElement('div');
            host.setAttribute('role', 'dialog');
            host.setAttribute('aria-modal', 'true');
            host.setAttribute('aria-label', t('Alege locurile'));
            document.body.appendChild(host);
            document.documentElement.style.overflow = 'hidden';
            if (window.tsLenis) { window.tsLenis.stop(); }
            var m = new SeatMap(event, host, true);
            m.returnFocus = document.activeElement;
            var x = host.querySelector('[data-sm-close]');
            if (x) { x.focus(); }
            return m;
        },
        mount: function (el, event) { return new SeatMap(event, el, false); }
    };

    // <button data-ts-seatmap='{"id":…}'> deschide harta fără cod în template
    document.addEventListener('click', function (e) {
        var b = e.target.closest('[data-ts-seatmap]');
        if (!b) { return; }
        e.preventDefault();
        try { TS.SeatMap.open(JSON.parse(b.getAttribute('data-ts-seatmap'))); } catch (err) {}
    });
    function autoMount() {
        document.querySelectorAll('[data-ts-seatmap-inline]').forEach(function (el) {
            if (el.__tsm) { return; }
            try { el.__tsm = TS.SeatMap.mount(el, JSON.parse(el.getAttribute('data-ts-seatmap-inline'))); } catch (err) {}
        });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', autoMount); } else { autoMount(); }
})();
