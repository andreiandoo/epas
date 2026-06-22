/**
 * PosPrinter — WebUSB ESC/POS driver pentru imprimante termice 80mm.
 *
 * Testat pe Bixolon SRP-350plusIII (VID 0x1504), funcţionează cu orice
 * imprimantă ESC/POS standard care răspunde la GS ( k pentru QR Code
 * (Epson TM-T20, Star TSP143, Citizen CT-S310, generice Chinese ZJ-80).
 *
 * Cerinţe runtime:
 *   - Chrome / Edge cu suport WebUSB (Firefox + Safari NU au)
 *   - HTTPS sau localhost
 *   - Pe Windows: driver-ul stock al imprimantei trebuie înlocuit cu
 *     WinUSB folosind Zadig (one-time setup, ~3min per stație)
 *
 * Browser cache-uieşte permission per origin — după primul connect()
 * (popup care îl întreabă pe operator să aleagă imprimanta), apelurile
 * ulterioare folosesc getDevices() fără prompt.
 */
(function (global) {
    'use strict';

    const STORAGE_KEY_AUTO = 'pos_printer_auto_print';

    // Filtru WebUSB: prioritizăm VIDs cunoscute pentru imprimante termice POS.
    // navigator.usb.requestDevice() afișează popup-ul cu device-uri care
    // matchuiesc CEL PUŢIN unul din filtre. Lista e generoasă ca să acoperim
    // hardware diferit; operatorul vede totuşi doar imprimantele conectate.
    const PRINTER_FILTERS = [
        { vendorId: 0x1504 }, // Bixolon
        { vendorId: 0x04b8 }, // Epson
        { vendorId: 0x0519 }, // Star Micronics
        { vendorId: 0x1d90 }, // Citizen
        { vendorId: 0x0fe6 }, // ICS Advent (Zjiang clones)
        { vendorId: 0x0416 }, // Winbond (cheap Chinese POS)
        { vendorId: 0x6868 }, // CSN-A2 generic
        { classCode: 7 },     // USB Printer class — catch-all pentru orice imprimantă standard
    ];

    // ========================= ESC/POS LOW-LEVEL =========================

    const ESC = 0x1B;
    const GS = 0x1D;
    const LF = 0x0A;

    // Helper: concat mixed args (Uint8Array | number[] | number | string) → Uint8Array
    function bytes(...parts) {
        const flat = [];
        for (const p of parts) {
            if (p instanceof Uint8Array) {
                for (let i = 0; i < p.length; i++) flat.push(p[i]);
            } else if (Array.isArray(p)) {
                for (const b of p) flat.push(b);
            } else if (typeof p === 'string') {
                for (let i = 0; i < p.length; i++) flat.push(p.charCodeAt(i) & 0xff);
            } else if (typeof p === 'number') {
                flat.push(p & 0xff);
            }
        }
        return new Uint8Array(flat);
    }

    // Concatenare eficienta a unui array de Uint8Array
    function concat(parts) {
        let total = 0;
        for (const p of parts) total += p.length;
        const out = new Uint8Array(total);
        let i = 0;
        for (const p of parts) { out.set(p, i); i += p.length; }
        return out;
    }

    // Strip diacritice: imprimantele termice generice nu au tabel de caractere
    // care să acopere ăâîșțĂÂÎȘȚ + maghiar (őűÖÜ). Decât să configurăm codepage
    // diferit per imprimantă (fragil), facem ASCII-fold — operatorul vede textul
    // citibil indiferent de hardware. RO/HU/EN sunt toate acoperite.
    //
    // ATENȚIE: în Romanian modern se folosește comma-below pentru ș (U+0219) și
    // ț (U+021B), care sunt în Latin Extended-B (U+0180-U+024F). Versiunile
    // cedilla `ş` (U+015F) și `ţ` (U+0163) sunt în Latin Extended-A. Ambele
    // forme sunt acoperite explicit.
    const DIACRITIC_MAP = {
        // Comma-below (Romanian modern, U+0218..U+021B)
        'Ș': 'S', 'ș': 's', 'Ț': 'T', 'ț': 't',
        // Cedilla forms (Romanian legacy)
        'ş': 's', 'ţ': 't', 'Ş': 'S', 'Ţ': 'T',
        // Romanian breve & circumflex
        'ă': 'a', 'â': 'a', 'î': 'i', 'Ă': 'A', 'Â': 'A', 'Î': 'I',
        // Hungarian
        'ö': 'o', 'ü': 'u', 'Ö': 'O', 'Ü': 'U',
        'ő': 'o', 'ű': 'u', 'Ő': 'O', 'Ű': 'U',
        'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u',
        'Á': 'A', 'É': 'E', 'Í': 'I', 'Ó': 'O', 'Ú': 'U',
        // Spanish / generic
        'ñ': 'n', 'Ñ': 'N',
    };
    function ascii(s) {
        if (s == null) return '';
        // Pas 1: NFD normalize — separă base char + combining marks (acoperă
        // și caracterele introduse prin tastatură ca base+combining, nu ca
        // precomposed). Apoi elimină marks-urile.
        let out = String(s).normalize('NFD').replace(/[̀-ͯ]/g, '');
        // Pas 2: înlocuiește caracterele precomposed care au scăpat de NFD
        // (ex: ș la unele encoding-uri rămâne ca atare). Range-ul acoperă
        // Latin-1 Supplement + Latin Extended-A + Latin Extended-B.
        out = out.replace(/[À-ɏ]/g, (c) => DIACRITIC_MAP[c] || '?');
        return out;
    }

    // Comenzi atomice ESC/POS
    const INIT          = bytes(ESC, 0x40);                    // ESC @
    const ALIGN_LEFT    = bytes(ESC, 0x61, 0);
    const ALIGN_CENTER  = bytes(ESC, 0x61, 1);
    const ALIGN_RIGHT   = bytes(ESC, 0x61, 2);
    const BOLD_ON       = bytes(ESC, 0x45, 1);
    const BOLD_OFF      = bytes(ESC, 0x45, 0);
    const SIZE_NORMAL   = bytes(GS, 0x21, 0x00);
    const SIZE_2X2      = bytes(GS, 0x21, 0x11);               // 2× width + 2× height
    const SIZE_2H       = bytes(GS, 0x21, 0x01);               // 1× width + 2× height
    const SIZE_SMALL    = bytes(ESC, 0x21, 0x01);              // Font B (mic, 9×17 dots)
    // Cut with feed: GS V 66 n — avanseaza n dots PESTE cutting position (gap-ul
    // printhead→lama, ~18mm fizic pe Bixolon). n=0 înseamnă cut imediat la
    // cutting position — gap-ul fizic de 18mm dintre ultima linie text și
    // marginea biletului rămâne ca un padding INEVITABIL dat de mecanism.
    // Valori n > 0 ADAUGĂ spațiu gol peste cei 18mm fizici.
    const CUT_PARTIAL_FEED = (n) => bytes(GS, 0x56, 66, n & 0xff);
    const CUT_PARTIAL   = bytes(GS, 0x56, 0x01);
    const CUT_FULL      = bytes(GS, 0x56, 0x00);
    const FEED_N        = (n) => bytes(ESC, 0x64, n & 0xff);    // ESC d n (n line feeds)
    const FEED_DOTS     = (n) => bytes(ESC, 0x4A, n & 0xff);    // ESC J n (n dots ≈ n/8 mm)
    const LINE          = bytes(LF);

    function text(s)  { return bytes(ascii(s)); }
    function lineOf(s){ return bytes(ascii(s), LF); }

    // Word-wrap simplu pe lățime fixă (caractere/linie). Folosit pentru adresa
    // emitentului care poate fi lungă (Str. … Nr. … Bloc … Sc. … Oraș, Județ).
    function wrapText(input, maxChars) {
        const words = String(input || '').split(/\s+/).filter(Boolean);
        const lines = [];
        let current = '';
        for (const w of words) {
            if (!current) { current = w; continue; }
            if ((current.length + 1 + w.length) <= maxChars) {
                current += ' ' + w;
            } else {
                lines.push(current);
                current = w;
            }
        }
        if (current) lines.push(current);
        return lines;
    }

    // QR Code prin comenzi standard ESC/POS 2D barcode (GS ( k).
    // Documentat în Epson ESC/POS spec, suportat de Bixolon SRP-350plusIII +
    // toate imprimantele termice moderne.
    function qrCode(data, opts) {
        const size = (opts && opts.size) || 6;     // 1-16; 6 ≈ 24mm la 180dpi
        const ec   = (opts && opts.ec)   || 49;    // 48=L, 49=M, 50=Q, 51=H
        const payload = new TextEncoder().encode(data || '');
        const storeLen = payload.length + 3;
        const pL = storeLen & 0xff;
        const pH = (storeLen >> 8) & 0xff;
        return concat([
            // Select QR model: cn=49, fn=65, n1=50 (model 2), n2=0
            bytes(GS, 0x28, 0x6B, 4, 0, 49, 65, 50, 0),
            // Set module size: cn=49, fn=67, n=size
            bytes(GS, 0x28, 0x6B, 3, 0, 49, 67, size),
            // Set error correction: cn=49, fn=69, n=ec
            bytes(GS, 0x28, 0x6B, 3, 0, 49, 69, ec),
            // Store data: cn=49, fn=80, m=48, then payload
            bytes(GS, 0x28, 0x6B, pL, pH, 49, 80, 48),
            payload,
            // Print: cn=49, fn=81, m=48
            bytes(GS, 0x28, 0x6B, 3, 0, 49, 81, 48),
        ]);
    }

    // ========================= TEMPLATE BILET 80×55mm =========================

    /**
     * Construieste buffer-ul ESC/POS pentru un bilet.
     *
     * Layout 80mm (576 dots width) cu sectiuni:
     *   [Numele firmei emitente]                     — bold
     *   [CUI + Reg. Com.]                            — small, optional
     *   [Adresa]                                     — small, optional
     *   [Eveniment]                                  — normal
     *   ──────────────────
     *   [TIP BILET]                                  — 2× width+height, bold
     *   [Variantă]                                   — normal
     *   [QR CODE ~38mm]                              — size 10
     *   [Cod text]                                   — bold
     *   [Vizita · Vandut la]                         — normal
     *   [@ POS / cashier]                            — normal
     *   ──────────────────
     *   [Ticketing prin ambilet.ro]                  — small
     *   [feed 150 dots + partial cut]
     *
     * ticket: { issuer (object cu name/cui/reg_com/address), event_name,
     *           ticket_type_name, variant_label, code, qr_data,
     *           visit_date, sold_at, pos_name }
     */
    function buildTicketCommands(ticket) {
        const t = ticket || {};
        const parts = [INIT, ALIGN_CENTER];

        // ===== HEADER: societatea emitentă =====
        // `issuer` poate fi obiect intors de getIssuerData() (name/tax_id/registration/
        // address/city/county) sau forma simpla custom (name/cui/reg_com/address).
        // Backward compat: t.issuer_name string ca fallback final.
        const issuer = t.issuer || {};
        const issuerName = (issuer.name || issuer.company_name || t.issuer_name || '').toString().trim();
        if (issuerName) {
            parts.push(BOLD_ON, lineOf(issuerName.toUpperCase()), BOLD_OFF);
        }
        // CUI + Reg. Com. pe acelasi rand, font mic
        const issuerCui = issuer.tax_id || issuer.cui || '';
        const issuerReg = issuer.registration || issuer.reg_com || '';
        const idLine = [];
        if (issuerCui) idLine.push('CUI: ' + issuerCui);
        if (issuerReg) idLine.push('Reg: ' + issuerReg);
        if (idLine.length) {
            parts.push(SIZE_SMALL, lineOf(idLine.join('  ')), SIZE_NORMAL);
        }
        // Adresa: dacă există city/county le concatenam la address.
        // Wrap pe MAX 2 linii cu Font B (cca 56 chars/linie pe 80mm) ca să nu pierdem
        // județul când adresa e lungă. ascii() rulează după wrap pentru consistență.
        const addrParts = [];
        if (issuer.address) addrParts.push(issuer.address);
        if (issuer.city && !String(issuer.address || '').toLowerCase().includes(String(issuer.city).toLowerCase())) {
            addrParts.push(issuer.city);
        }
        if (issuer.county) addrParts.push(issuer.county);
        if (addrParts.length) {
            const fullAddr = addrParts.join(', ').replace(/\s+/g, ' ').trim();
            const lines = wrapText(fullAddr, 56).slice(0, 2);
            parts.push(SIZE_SMALL);
            for (const ln of lines) parts.push(lineOf(ln));
            parts.push(SIZE_NORMAL);
        }

        // Linie informativă fixă — branding ticketing platform.
        // Anterior afișam event_name aici; eliminat la cererea utilizatorului
        // (numele evenimentului apărea ca "Test Eveniment" la print test și
        // duplicate cu titlul biletului). Înlocuit cu un identifier constant.
        parts.push(SIZE_SMALL, lineOf('Ticketing prin AmBilet.ro'), SIZE_NORMAL);
        parts.push(lineOf('--------------------------------'));

        // ===== TIP BILET =====
        if (t.ticket_type_name) {
            parts.push(SIZE_2X2, BOLD_ON, lineOf(t.ticket_type_name.toUpperCase()), BOLD_OFF, SIZE_NORMAL);
        }
        if (t.variant_label) {
            parts.push(lineOf(t.variant_label));
        }

        // ===== QR CODE =====
        // Bixolon SRP-350plusIII firmware acceptă module size 2-8 (NU 1-16 cum
        // zice ESC/POS spec generic). Valorile > 8 se rezolvă la default 3 →
        // QR mult mai mic decât așteptat. Folosim size 8 (max safe Bixolon) +
        // EC Q (error correction ~25%) — Q forțează QR-ul la o versiune cu mai
        // multe module pentru același payload, deci fizic mai mare.
        //
        // Pentru un payload tipic "https://ambilet.ro/v/CODE" (~32 chars):
        //   EC M, size 8 → versiune 2 (25×25) → 200 dots = 25mm
        //   EC Q, size 8 → versiune 3-4 (29-33×29-33) → 232-264 dots = 29-33mm
        //
        // Adăugăm și un suffix UTM la payload ca să forțăm versiunea spre 4+
        // (chars suplimentare → versiune mai mare → fizic mai mare).
        if (t.qr_data) {
            let qrPayload = t.qr_data.startsWith('http')
                ? t.qr_data
                : ('https://ambilet.ro/v/' + t.qr_data);
            // Suffix neutru care nu schimba destinatia (ambilet.ro/v handler il ignora)
            // dar mareste payload-ul cu ~10 chars → versiune QR mai mare → fizic mai mare.
            if (!qrPayload.includes('?')) qrPayload += '?p=pos';
            parts.push(FEED_N(1));
            parts.push(qrCode(qrPayload, { size: 8, ec: 50 })); // size 8 = max Bixolon; ec 50 = Q
            parts.push(LINE);
        }

        // Cod text (bold, sub QR)
        if (t.code) {
            parts.push(BOLD_ON, lineOf(t.code), BOLD_OFF);
        }

        // ===== FOOTER VANZARE (compact) =====
        const visitLine = [];
        if (t.visit_date) visitLine.push('Vizita: ' + t.visit_date);
        if (t.sold_at) visitLine.push('Vandut: ' + t.sold_at);
        if (visitLine.length) {
            parts.push(SIZE_SMALL, lineOf(visitLine.join('  ')), SIZE_NORMAL);
        }
        // POS / cashier pe ultima linie (ambilet.ro e deja în header). Bixolon are
        // ~18mm gap fizic printhead→lamă; tot ce e după ultima linie devine
        // spațiu gol obligatoriu. Compactăm cât putem.
        if (t.pos_name) {
            parts.push(SIZE_SMALL, lineOf('@ ' + t.pos_name), SIZE_NORMAL);
        }

        // ===== CUT =====
        // n=0 → cut imediat la cutting position. Gap-ul fizic ~18mm dintre
        // ultima linie printată și marginea biletului rămâne (mecanism Bixolon).
        // Dacă vrei și mai puțin spațiu gol, vezi varianta GS V 1 (legacy
        // partial cut) — unele firmware-uri Bixolon avansează doar 12mm.
        parts.push(CUT_PARTIAL_FEED(0));

        return concat(parts);
    }

    // ========================= WebUSB connection management =========================

    let _device = null;
    let _endpoint = null;

    async function getAuthorizedDevice() {
        if (!navigator.usb) return null;
        const devices = await navigator.usb.getDevices();
        // Multiple devices autorizate? alegem prima imprimanta (vendor cunoscut sau
        // class 7). În practică operatorul are 1 singură imprimantă conectată.
        if (!devices.length) return null;
        const knownVids = [0x1504, 0x04b8, 0x0519, 0x1d90, 0x0fe6, 0x0416, 0x6868];
        return devices.find(d => knownVids.includes(d.vendorId)) || devices[0];
    }

    async function openAndClaim(device) {
        if (!device) return null;
        if (!device.opened) {
            await device.open();
        }
        if (device.configuration === null) {
            await device.selectConfiguration(1);
        }
        // Caută interfaţa cu un endpoint OUT bulk
        const cfg = device.configuration;
        for (const iface of cfg.interfaces) {
            for (const alt of iface.alternates) {
                const ep = alt.endpoints.find(e => e.direction === 'out' && e.type === 'bulk');
                if (ep) {
                    try {
                        if (!iface.claimed) await device.claimInterface(iface.interfaceNumber);
                    } catch (e) {
                        // E posibil ca un alt driver să fi luat deja interface-ul.
                        // Pe Windows asta înseamnă că driver-ul stock nu a fost
                        // înlocuit cu WinUSB (vezi setup Zadig).
                        console.warn('[pos-printer] claimInterface failed:', e.message);
                    }
                    _device = device;
                    _endpoint = ep.endpointNumber;
                    return device;
                }
            }
        }
        throw new Error('Nu s-a găsit endpoint OUT pe imprimantă');
    }

    async function ensureConnected() {
        if (_device && _device.opened && _endpoint) return _device;
        const dev = await getAuthorizedDevice();
        if (!dev) return null;
        try {
            return await openAndClaim(dev);
        } catch (e) {
            console.warn('[pos-printer] reconnect failed:', e.message);
            return null;
        }
    }

    // ========================= PUBLIC API =========================

    const PosPrinter = {
        VERSION: '1.0.0',

        /** True dacă browser-ul suportă WebUSB. */
        isSupported() {
            return typeof navigator !== 'undefined' && !!navigator.usb;
        },

        /** True dacă există un device autorizat ŞI conectat fizic ŞI deschis. */
        async isReady() {
            try { return !!(await ensureConnected()); }
            catch (_) { return false; }
        },

        /**
         * Afişează popup browser-ul pentru a alege imprimanta. Cere autorizare
         * doar o singură dată per origin per device (Chrome cache-uieşte).
         * Returneaza info despre device sau aruncă dacă utilizatorul a anulat.
         */
        async connect() {
            if (!navigator.usb) {
                throw new Error('WebUSB nu e suportat — foloseşte Chrome sau Edge.');
            }
            const device = await navigator.usb.requestDevice({ filters: PRINTER_FILTERS });
            await openAndClaim(device);
            return {
                vendorId: device.vendorId,
                productId: device.productId,
                productName: device.productName || 'Imprimantă ESC/POS',
                manufacturerName: device.manufacturerName || '',
                serialNumber: device.serialNumber || '',
            };
        },

        /** Închide conexiunea (nu revocă autorizarea — folosit la cleanup). */
        async disconnect() {
            if (_device && _device.opened) {
                try { await _device.close(); } catch (_) {}
            }
            _device = null;
            _endpoint = null;
        },

        /** Returnează metadata imprimantei conectate. */
        async deviceInfo() {
            const d = await ensureConnected();
            if (!d) return null;
            return {
                vendorId: d.vendorId,
                productId: d.productId,
                productName: d.productName || '',
                manufacturerName: d.manufacturerName || '',
            };
        },

        /** Trimite un buffer brut la imprimantă. */
        async printRaw(buffer) {
            const d = await ensureConnected();
            if (!d || !_endpoint) throw new Error('Imprimantă neconectată');
            return await d.transferOut(_endpoint, buffer);
        },

        /** Printează un bilet din datele structurate. */
        async printTicket(ticket) {
            return await this.printRaw(buildTicketCommands(ticket));
        },

        /** Printează un bilet de test cu date dummy — pentru calibrare hardware. */
        async printTestTicket(overrides) {
            const now = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const dateStr = pad(now.getDate()) + '.' + pad(now.getMonth() + 1) + '.' + now.getFullYear();
            const timeStr = pad(now.getHours()) + ':' + pad(now.getMinutes());
            // NOTĂ: issuer e LĂSAT GOL în default — UI-ul (leisure-pos) îl
            // injectează prin overrides cu datele reale ale organizatorului
            // încărcate din /leisure/config. Dacă apelezi fără overrides,
            // header-ul firmă va lipsi (acceptabil pentru un smoke test).
            const sample = Object.assign({
                event_name: 'Test bilet',
                ticket_type_name: 'Bilet Adult',
                code: 'TEST-X9K3PQ',
                qr_data: 'TEST-X9K3PQ',
                visit_date: dateStr,
                sold_at: dateStr + ' ' + timeStr,
                pos_name: 'POS Test',
            }, overrides || {});
            return await this.printTicket(sample);
        },

        /** Expune buildTicketCommands ca să poată fi inspectat (debug / preview). */
        buildTicketBuffer(ticket) {
            return buildTicketCommands(ticket);
        },

        // ===== Auto-print toggle (persistat în localStorage) =====
        getAutoPrintEnabled() {
            return localStorage.getItem(STORAGE_KEY_AUTO) === 'true';
        },
        setAutoPrintEnabled(enabled) {
            localStorage.setItem(STORAGE_KEY_AUTO, enabled ? 'true' : 'false');
        },

        // ===== Event listeners pentru USB hot-plug =====
        init() {
            if (!navigator.usb) return;
            navigator.usb.addEventListener('disconnect', (e) => {
                if (_device && e.device === _device) {
                    _device = null;
                    _endpoint = null;
                    window.dispatchEvent(new CustomEvent('posprinter:disconnected', { detail: e.device }));
                }
            });
            navigator.usb.addEventListener('connect', (e) => {
                window.dispatchEvent(new CustomEvent('posprinter:connected', { detail: e.device }));
            });
        },
    };

    global.PosPrinter = PosPrinter;
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => PosPrinter.init());
        } else {
            PosPrinter.init();
        }
    }
})(typeof window !== 'undefined' ? window : this);
