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

    // ========================= FACTURA FISCALA =========================

    /**
     * Construieste buffer-ul ESC/POS pentru o factura fiscala 80mm.
     *
     * Layout (conform cerinta client Sf. Ana):
     *   FACTURA FISCALA                  (mare, bold)
     *   ──────────────────────
     *   [Numele firmei emitente]         (bold)
     *   CUI: ...  Reg: ...               (small)
     *   Adresa, Oras, Judet              (small, wrap 2 linii)
     *
     *   Factura tiparita in doua exemplare   (small italic-like)
     *   Copia
     *
     *   Seria: P1-2026/00000080          (FOARTE MARE, bold)
     *
     *   CUMPARATOR                       (small uppercase bold)
     *   [Nume client]
     *   [Email · Telefon]
     *   [Firma cumparator (daca exista):
     *    Denumire | CUI | Reg | Adresa]
     *
     *   Data emiterii: DD.MM.YYYY HH:MM
     *
     *   PRODUSE                          (small uppercase bold)
     *   [Nume produs 1]
     *      qty x pret           total RON
     *   [Nume produs 2]
     *      ...
     *   ──────────────────────
     *   TOTAL:                  XX.XX RON   (bold)
     *
     *   Achitat cu bon fiscal            (centered)
     *   [cut]
     *
     * invoice: {
     *   issuer (object cu name/tax_id/registration/address/city/county),
     *   series, customer (nume/email/phone), buyer_company (nume/cui/reg/adresa),
     *   issued_at (Date or ISO string), items ([{ name, qty, unit_price, total }]),
     *   total, currency
     * }
     */
    /**
     * Construieste o SINGURA copie a facturii (un exemplar). Chemata de 2 ori din
     * buildInvoiceCommands ca sa printam factura in dublu exemplar.
     */
    function buildInvoiceSingleCopy(inv) {
        const parts = [INIT, ALIGN_CENTER];

        // ===== TITLU =====
        parts.push(SIZE_2X2, BOLD_ON, lineOf('FACTURA FISCALA'), BOLD_OFF, SIZE_NORMAL);
        parts.push(lineOf('--------------------------------'));

        // ===== EMITENT =====
        const issuer = inv.issuer || {};
        const issuerName = (issuer.name || issuer.company_name || 'AMBILET.RO').toString().trim();
        parts.push(BOLD_ON, lineOf(issuerName.toUpperCase()), BOLD_OFF);
        const issuerCui = issuer.tax_id || issuer.cui || '';
        const issuerReg = issuer.registration || issuer.reg_com || '';
        const idLine = [];
        if (issuerCui) idLine.push('CUI: ' + issuerCui);
        if (issuerReg) idLine.push('Reg: ' + issuerReg);
        if (idLine.length) parts.push(SIZE_SMALL, lineOf(idLine.join('  ')), SIZE_NORMAL);
        const addrParts = [];
        if (issuer.address) addrParts.push(issuer.address);
        if (issuer.city && !String(issuer.address || '').toLowerCase().includes(String(issuer.city).toLowerCase())) addrParts.push(issuer.city);
        if (issuer.county) addrParts.push(issuer.county);
        if (addrParts.length) {
            const fullAddr = addrParts.join(', ').replace(/\s+/g, ' ').trim();
            const lines = wrapText(fullAddr, 56).slice(0, 2);
            parts.push(SIZE_SMALL);
            for (const ln of lines) parts.push(lineOf(ln));
            parts.push(SIZE_NORMAL);
        }

        // ===== EXEMPLAR =====
        // Doar mesajul "in doua exemplare" (fara "Copia" — nu mai facem distinctie
        // originalul vs copia, ambele sunt exemplare identice).
        parts.push(FEED_N(1));
        parts.push(SIZE_SMALL, lineOf('Factura tiparita in doua exemplare'), SIZE_NORMAL);

        // ===== SERIA (MARE) =====
        // Serie pastreaza case-ul asa cum a fost setat de organizator (ex: SZAMEC-000002).
        // Backend uppercased-o deja la save. Aici o afisam ca atare.
        parts.push(FEED_N(1));
        const series = inv.series || 'P1-' + new Date().getFullYear() + '/00000000';
        parts.push(SIZE_2X2, BOLD_ON, lineOf('Seria:'), lineOf(series), BOLD_OFF, SIZE_NORMAL);
        parts.push(FEED_N(1));

        // ===== CUMPARATOR: doar firma cumparator (daca exista) =====
        // Vechea logica afisa datele client (POS vanzare on-site + pos@ambilet.ro)
        // ca antet chiar cand operatorul nu introducea nume real. User a cerut ca
        // aceste date sa dispara complet; datele reale (daca sunt) ajung sub semnatura Preluat.
        parts.push(ALIGN_LEFT);
        const buyer = inv.buyer_company || null;
        if (buyer && (buyer.name || buyer.cui)) {
            parts.push(BOLD_ON, SIZE_SMALL, lineOf('CUMPARATOR (FIRMA)'), SIZE_NORMAL, BOLD_OFF);
            parts.push(lineOf('--------------------------------'));
            if (buyer.name) parts.push(lineOf(buyer.name));
            const buyerIds = [];
            if (buyer.cui || buyer.tax_id) buyerIds.push('CUI: ' + (buyer.cui || buyer.tax_id));
            if (buyer.reg_no || buyer.registration) buyerIds.push('Reg: ' + (buyer.reg_no || buyer.registration));
            if (buyerIds.length) parts.push(SIZE_SMALL, lineOf(buyerIds.join('  ')), SIZE_NORMAL);
            if (buyer.address) {
                const ba = wrapText(String(buyer.address), 56).slice(0, 2);
                parts.push(SIZE_SMALL);
                for (const l of ba) parts.push(lineOf(l));
                parts.push(SIZE_NORMAL);
            }
        }

        // ===== DATA EMITERII =====
        parts.push(FEED_N(1));
        const issuedDate = (() => {
            const d = inv.issued_at instanceof Date ? inv.issued_at : (inv.issued_at ? new Date(inv.issued_at) : new Date());
            const pad = n => String(n).padStart(2, '0');
            return pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        })();
        parts.push(lineOf('Data emiterii: ' + issuedDate));

        // ===== PRODUSE =====
        parts.push(FEED_N(1));
        parts.push(BOLD_ON, SIZE_SMALL, lineOf('PRODUSE'), SIZE_NORMAL, BOLD_OFF);
        parts.push(lineOf('--------------------------------'));
        const items = Array.isArray(inv.items) ? inv.items : [];
        const currency = inv.currency || 'RON';
        for (const it of items) {
            const name = String(it.name || 'Produs').trim();
            const qty = parseFloat(it.qty || 1);
            const unitPrice = parseFloat(it.unit_price || 0);
            const total = parseFloat(it.total ?? (qty * unitPrice));
            const nameLines = wrapText(name, 48);
            for (const nl of nameLines) parts.push(lineOf(nl));
            const detail = '  ' + qty + ' x ' + unitPrice.toFixed(2);
            const totalStr = total.toFixed(2) + ' ' + currency;
            const pad = Math.max(1, 48 - detail.length - totalStr.length);
            parts.push(SIZE_SMALL, lineOf(detail + ' '.repeat(pad) + totalStr), SIZE_NORMAL);
        }

        // ===== SUBTOTAL + TVA + TOTAL =====
        parts.push(lineOf('--------------------------------'));
        const totalAmount = parseFloat(inv.total ?? items.reduce((s, i) => s + parseFloat(i.total ?? ((i.qty||1) * (i.unit_price||0))), 0));
        const vatRate = parseFloat(inv.vat_rate || 0);
        const vatPayer = !!inv.vat_payer;
        // Suma TVA inclusa (cand vat_payer=true, pretul de vanzare include deja TVA):
        // total = neta + tva  =>  tva = total * rate / (100 + rate)
        // Cand vat_payer=false, vatAmount = 0.
        const vatAmount = (vatPayer && vatRate > 0)
            ? Math.round((totalAmount * vatRate / (100 + vatRate)) * 100) / 100
            : 0;

        // Randul TVA e OBLIGATORIU pe factura (chiar 0). Etichetat cu rate-ul setat.
        const vatLabel = 'TVA (' + (vatRate ? vatRate.toFixed(0) + '%' : '0%') + '):';
        const vatRight = vatAmount.toFixed(2) + ' ' + currency;
        const vatPad = Math.max(1, 32 - vatLabel.length - vatRight.length);
        parts.push(SIZE_SMALL, lineOf(vatLabel + ' '.repeat(vatPad) + vatRight), SIZE_NORMAL);

        // TOTAL bold
        const totalLine = 'TOTAL:';
        const totalRight = totalAmount.toFixed(2) + ' ' + currency;
        const totalPad = Math.max(1, 32 - totalLine.length - totalRight.length);
        parts.push(BOLD_ON, lineOf(totalLine + ' '.repeat(totalPad) + totalRight), BOLD_OFF);

        // ===== SEMNATURI (Emitent / Preluat) =====
        // 3cm spatiu gol pentru semnaturi + jos labelurile. La imprimante termice
        // 80mm cu font A ~ 21 dots/linie -> 3cm = ~120 dots -> ~7 linii goale.
        parts.push(FEED_N(1));
        parts.push(lineOf('--------------------------------'));
        // Header rand: "Emitent" stanga, "Preluat" dreapta pe acelasi rand
        const emitLabel = 'Emitent';
        const preluatLabel = 'Preluat';
        const sigPad = Math.max(1, 32 - emitLabel.length - preluatLabel.length);
        parts.push(BOLD_ON, SIZE_SMALL, lineOf(emitLabel + ' '.repeat(sigPad) + preluatLabel), SIZE_NORMAL, BOLD_OFF);
        // Spatiu ~3cm pentru semnaturi (7 linii goale)
        parts.push(FEED_N(7));

        // Numele "Preluat" (sub semnatura din dreapta):
        //  - daca operatorul a introdus customer.name → afisam numele
        //  - altfel → fallback "ECOCENTRU" + "info@szentanna-to.ro" (Sf. Ana)
        const customer = inv.customer || {};
        const custName = (customer.name || '').trim();
        // Emailurile default POS ('pos@ambilet.ro') si numele placeholder
        // 'POS — vânzare on-site' sunt tratate ca EMPTY.
        const isRealName = custName && !custName.toLowerCase().includes('pos') && !custName.toLowerCase().includes('vanzare');
        const custEmail = (customer.email || '').trim();
        const isRealEmail = custEmail && !custEmail.toLowerCase().startsWith('pos@');

        if (isRealName || isRealEmail) {
            if (isRealName) parts.push(SIZE_SMALL, lineOf('  ' + '  '.repeat(8) + custName), SIZE_NORMAL);
            if (isRealEmail && !isRealName) parts.push(SIZE_SMALL, lineOf('  ' + '  '.repeat(8) + custEmail), SIZE_NORMAL);
        } else {
            parts.push(SIZE_SMALL, lineOf('  ' + '  '.repeat(8) + 'ECOCENTRU'), SIZE_NORMAL);
            parts.push(SIZE_SMALL, lineOf('  ' + '  '.repeat(8) + 'info@szentanna-to.ro'), SIZE_NORMAL);
        }

        // ===== FOOTER =====
        parts.push(FEED_N(1));
        parts.push(ALIGN_CENTER);
        parts.push(SIZE_SMALL, lineOf('Achitat cu bon fiscal'), SIZE_NORMAL);

        // Footer note opțional (ex: avertisment cand exista produse SC2 nefacturate)
        if (inv.footer_note) {
            parts.push(FEED_N(1));
            parts.push(SIZE_SMALL);
            const noteLines = wrapText(ascii(inv.footer_note), 56);
            for (const nl of noteLines) parts.push(lineOf(nl));
            parts.push(SIZE_NORMAL);
        }

        // Cut la finalul unui exemplar. Imprimanta taie hartia; urmatorul exemplar
        // porneste cu INIT si e complet independent.
        parts.push(CUT_PARTIAL_FEED(0));

        return parts;
    }

    /**
     * Construieste buffer-ul ESC/POS pentru factura fiscala 80mm - DUBLU EXEMPLAR.
     * Regulatie fiscala RO: factura tiparita in 2 exemplare (originalul pentru
     * cumparator, copia pentru emitent). Cut intre cele 2 pentru rupere usoara.
     *
     * invoice: {
     *   issuer (name/tax_id/registration/address/city/county/vat_payer/vat_rate),
     *   series, customer (nume/email/phone), buyer_company (nume/cui/reg/adresa),
     *   issued_at (Date or ISO string), items ([{ name, qty, unit_price, total }]),
     *   total, currency, vat_payer, vat_rate, footer_note
     * }
     */
    function buildInvoiceCommands(invoice) {
        const inv = invoice || {};
        // Emitem 2 exemplare identice, cut intre ele. Copy #1 pentru cumparator,
        // copy #2 pentru arhiva emitentului.
        const parts1 = buildInvoiceSingleCopy(inv);
        const parts2 = buildInvoiceSingleCopy(inv);
        return concat([...parts1, ...parts2]);
    }

    /**
     * Reda vizual (HTML) UN SINGUR exemplar al facturii, oglindind layout-ul din
     * buildInvoiceSingleCopy. Text-based, 80mm width, monospace. NU foloseste
     * ESC/POS commands — util pentru preview in browser fara imprimanta.
     *
     * Modificari majore fata de ESC/POS:
     *  - toUpperCase pastrat pentru compatibilitate vizuala cu imprimanta termica
     *    (font-A cu case-uri visibile)
     *  - diacriticele NU sunt normalizate (ascii()) — browserul le reda nativ
     *  - line width max 32 chars (mai apropiat de font-A pe hartie termica 80mm)
     */
    function buildInvoiceHtml(invoice) {
        const inv = invoice || {};
        const esc = (s) => String(s == null ? '' : s).replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]));
        const sep = '<div class="sep"></div>';
        let out = '<div class="paper">';

        // ===== TITLU =====
        out += '<h2>FACTURA FISCALA</h2>';
        out += sep;

        // ===== EMITENT =====
        const issuer = inv.issuer || {};
        const issuerName = (issuer.name || issuer.company_name || 'AMBILET.RO').toString().trim();
        out += '<div class="center bold">' + esc(issuerName.toUpperCase()) + '</div>';
        const issuerCui = issuer.tax_id || issuer.cui || '';
        const issuerReg = issuer.registration || issuer.reg_com || '';
        const idLine = [];
        if (issuerCui) idLine.push('CUI: ' + issuerCui);
        if (issuerReg) idLine.push('Reg: ' + issuerReg);
        if (idLine.length) out += '<div class="center small">' + esc(idLine.join('  ')) + '</div>';
        const addrParts = [];
        if (issuer.address) addrParts.push(issuer.address);
        if (issuer.city && !String(issuer.address || '').toLowerCase().includes(String(issuer.city).toLowerCase())) addrParts.push(issuer.city);
        if (issuer.county) addrParts.push(issuer.county);
        if (addrParts.length) {
            out += '<div class="center small">' + esc(addrParts.join(', ').replace(/\s+/g, ' ').trim()) + '</div>';
        }

        // ===== EXEMPLAR (fara Copia) =====
        out += '<div style="height: 3mm;"></div>';
        out += '<div class="center small">Factura tiparita in doua exemplare</div>';

        // ===== SERIA (MARE) =====
        const series = inv.series || 'P1-' + new Date().getFullYear() + '/00000000';
        out += '<div class="large">Seria:<br>' + esc(series) + '</div>';

        // ===== CUMPARATOR (firma) — doar cand exista date =====
        const buyer = inv.buyer_company || null;
        if (buyer && (buyer.name || buyer.cui)) {
            out += '<div class="bold small">CUMPARATOR (FIRMA)</div>';
            out += sep;
            if (buyer.name) out += '<div>' + esc(buyer.name) + '</div>';
            const buyerIds = [];
            if (buyer.cui || buyer.tax_id) buyerIds.push('CUI: ' + (buyer.cui || buyer.tax_id));
            if (buyer.reg_no || buyer.registration) buyerIds.push('Reg: ' + (buyer.reg_no || buyer.registration));
            if (buyerIds.length) out += '<div class="small">' + esc(buyerIds.join('  ')) + '</div>';
            if (buyer.address) out += '<div class="small">' + esc(buyer.address) + '</div>';
        }

        // ===== DATA EMITERII =====
        out += '<div style="height: 3mm;"></div>';
        const issuedDate = (() => {
            const d = inv.issued_at instanceof Date ? inv.issued_at : (inv.issued_at ? new Date(inv.issued_at) : new Date());
            const pad = n => String(n).padStart(2, '0');
            return pad(d.getDate()) + '.' + pad(d.getMonth()+1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        })();
        out += '<div>Data emiterii: ' + esc(issuedDate) + '</div>';

        // ===== PRODUSE =====
        out += '<div style="height: 3mm;"></div>';
        out += '<div class="bold small">PRODUSE</div>';
        out += sep;
        const items = Array.isArray(inv.items) ? inv.items : [];
        const currency = inv.currency || 'RON';
        for (const it of items) {
            const name = String(it.name || 'Produs').trim();
            const qty = parseFloat(it.qty || 1);
            const unitPrice = parseFloat(it.unit_price || 0);
            const total = parseFloat(it.total != null ? it.total : (qty * unitPrice));
            out += '<div>' + esc(name) + '</div>';
            out += '<div class="row small"><span>' + qty + ' x ' + unitPrice.toFixed(2) + '</span><span>' + total.toFixed(2) + ' ' + esc(currency) + '</span></div>';
        }

        // ===== TVA + TOTAL =====
        out += sep;
        const totalAmount = parseFloat(inv.total != null ? inv.total : items.reduce((s, i) => s + parseFloat(i.total != null ? i.total : ((i.qty || 1) * (i.unit_price || 0))), 0));
        const vatRate = parseFloat(inv.vat_rate || 0);
        const vatPayer = !!inv.vat_payer;
        const vatAmount = (vatPayer && vatRate > 0)
            ? Math.round((totalAmount * vatRate / (100 + vatRate)) * 100) / 100
            : 0;
        const vatLabel = 'TVA (' + (vatRate ? vatRate.toFixed(0) + '%' : '0%') + '):';
        out += '<div class="row small"><span>' + vatLabel + '</span><span>' + vatAmount.toFixed(2) + ' ' + esc(currency) + '</span></div>';
        out += '<div class="row bold"><span>TOTAL:</span><span>' + totalAmount.toFixed(2) + ' ' + esc(currency) + '</span></div>';

        // ===== SEMNATURI =====
        out += '<div style="height: 3mm;"></div>';
        out += sep;
        out += '<div class="row bold small"><span>Emitent</span><span>Preluat</span></div>';
        // 3cm blank space simulat (60px @ ~5px/mm)
        out += '<div class="signature-box"></div>';

        // Nume Preluat (fallback ECOCENTRU cand nu are date reale)
        const customer = inv.customer || {};
        const custName = (customer.name || '').trim();
        const isRealName = custName && !custName.toLowerCase().includes('pos') && !custName.toLowerCase().includes('vanzare');
        const custEmail = (customer.email || '').trim();
        const isRealEmail = custEmail && !custEmail.toLowerCase().startsWith('pos@');
        out += '<div class="row small"><span></span><span style="text-align:right;">';
        if (isRealName || isRealEmail) {
            if (isRealName) out += esc(custName);
            if (isRealEmail && !isRealName) out += esc(custEmail);
        } else {
            out += 'ECOCENTRU<br>info@szentanna-to.ro';
        }
        out += '</span></div>';

        // ===== FOOTER =====
        out += '<div style="height: 3mm;"></div>';
        out += '<div class="center small">Achitat cu bon fiscal</div>';

        if (inv.footer_note) {
            out += '<div class="center small" style="margin-top:2mm; font-style: italic;">' + esc(inv.footer_note) + '</div>';
        }

        out += '</div>';
        return out;
    }

    // ========================= WebUSB connection management =========================

    let _device = null;
    let _endpoint = null;
    let _inEndpoint = null;   // bulk IN endpoint pentru citire status (DLE EOT etc.)

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
        // Caută interfaţa cu un endpoint OUT bulk (+ IN dacă există pentru status)
        const cfg = device.configuration;
        for (const iface of cfg.interfaces) {
            for (const alt of iface.alternates) {
                const outEp = alt.endpoints.find(e => e.direction === 'out' && e.type === 'bulk');
                if (outEp) {
                    try {
                        if (!iface.claimed) await device.claimInterface(iface.interfaceNumber);
                    } catch (e) {
                        console.warn('[pos-printer] claimInterface failed:', e.message);
                    }
                    _device = device;
                    _endpoint = outEp.endpointNumber;
                    // IN endpoint e opțional — folosit doar pentru status query (paper-out etc.).
                    // Multe imprimante POS nu expun unul (mai ales generice Chinese).
                    const inEp = alt.endpoints.find(e => e.direction === 'in' && e.type === 'bulk');
                    _inEndpoint = inEp ? inEp.endpointNumber : null;
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
            _inEndpoint = null;
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

        /**
         * Citeşte status-ul imprimantei prin comanda ESC/POS realtime
         * DLE EOT n (0x10 0x04 n):
         *   n=1 → status general (cover open, drawer)
         *   n=2 → off-line status
         *   n=3 → error status (cutter, mechanical)
         *   n=4 → paper sensor status (paper out / near-end)
         *
         * Returnează: { paper: 'ok' | 'near_end' | 'out' | 'unknown',
         *                cover_open: bool, error: bool, online: bool }
         *
         * Necesită un endpoint IN. Multe imprimante POS nu îl expun (în special
         * generice Chinese) — în acel caz întoarcem { paper: 'unknown' }
         * fără să blocăm UI-ul. Timeout 800ms ca să nu blocheze auto-print
         * dacă imprimanta nu răspunde imediat.
         */
        async getStatus() {
            const d = await ensureConnected();
            if (!d || !_endpoint) return { paper: 'unknown', online: false, error: false, cover_open: false };
            if (!_inEndpoint) return { paper: 'unknown', online: true, error: false, cover_open: false };

            const query = async (n) => {
                try {
                    await d.transferOut(_endpoint, bytes(0x10, 0x04, n));
                    const racePromise = Promise.race([
                        d.transferIn(_inEndpoint, 64),
                        new Promise((_, reject) => setTimeout(() => reject(new Error('status_timeout')), 800)),
                    ]);
                    const result = await racePromise;
                    if (result && result.data && result.data.byteLength > 0) {
                        return new Uint8Array(result.data.buffer)[0];
                    }
                } catch (_) { /* timeout sau imprimanta nu raspunde */ }
                return null;
            };

            // Status paper sensor (DLE EOT 4): bits 5-6 = paper out, bits 2-3 = near-end
            const paperByte = await query(4);
            let paper = 'unknown';
            if (paperByte !== null) {
                const paperOut = (paperByte & 0x60) === 0x60;
                const nearEnd = (paperByte & 0x0C) === 0x0C;
                paper = paperOut ? 'out' : (nearEnd ? 'near_end' : 'ok');
            }

            // Status general (DLE EOT 1): bit 2 = drawer kick-out, bit 3 = offline,
            // bit 5 = cover open, bit 6 = paper feed by switch
            const generalByte = await query(1);
            let cover_open = false, online = true;
            if (generalByte !== null) {
                cover_open = (generalByte & 0x20) !== 0;
                online = (generalByte & 0x08) === 0;
            }

            // Status erori (DLE EOT 3): bit 5 = autocutter error, bit 6 = unrecoverable
            const errorByte = await query(3);
            const error = (errorByte !== null) ? ((errorByte & 0x60) !== 0) : false;

            return { paper, cover_open, online, error };
        },

        /** Printează un bilet din datele structurate. */
        async printTicket(ticket) {
            return await this.printRaw(buildTicketCommands(ticket));
        },

        /** Printează o factură fiscală formată conform specificaţiei Sf. Ana. */
        async printInvoice(invoice) {
            return await this.printRaw(buildInvoiceCommands(invoice));
        },

        /**
         * Preview HTML pentru o factura — reda exact continutul textual care ar iesi
         * pe imprimanta termica 80mm, dar in browser (fara imprimanta). Util pentru
         * debug + preview inainte de vanzare, fara sa consumi rola de hartie.
         *
         * Returneaza HTML string cu 2 exemplare separate vizual (regulatie fiscala RO).
         * Layout: 80mm width = ~303px, font monospace, spatiere identica cu ESC/POS.
         */
        renderInvoiceHtml(invoice) {
            return buildInvoiceHtml(invoice);
        },

        /**
         * Deschide preview-ul intr-o fereastra noua a browser-ului. Handy pentru
         * operator sa vada exact ce s-ar tipari inainte de a apasa Finalizeaza.
         */
        previewInvoice(invoice) {
            const html = buildInvoiceHtml(invoice);
            const w = window.open('', 'invoice-preview', 'width=420,height=800,scrollbars=yes,resizable=yes');
            if (!w) {
                alert('Popup blocat. Permite popup-uri pentru preview factura.');
                return;
            }
            w.document.open();
            w.document.write('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Preview factura</title>'
                + '<style>'
                + '  body { margin: 0; padding: 12px; background: #e5e7eb; font-family: system-ui, sans-serif; }'
                + '  .header { text-align: center; margin-bottom: 12px; color: #64748b; font-size: 12px; }'
                + '  .paper { width: 80mm; margin: 0 auto 12px; background: white; padding: 4mm; box-shadow: 0 2px 8px rgba(0,0,0,0.15); font-family: "Courier New", ui-monospace, monospace; font-size: 11px; line-height: 1.35; color: #000; white-space: pre-wrap; word-break: break-word; }'
                + '  .paper h2 { font-size: 14px; text-align: center; margin: 0 0 4mm; letter-spacing: 1px; }'
                + '  .center { text-align: center; }'
                + '  .bold { font-weight: bold; }'
                + '  .small { font-size: 9px; }'
                + '  .large { font-size: 16px; font-weight: bold; text-align: center; margin: 4mm 0; }'
                + '  .sep { border-top: 1px dashed #000; margin: 2mm 0; }'
                + '  .row { display: flex; justify-content: space-between; }'
                + '  .signature-box { min-height: 30mm; border-bottom: 1px solid #000; margin-top: 2mm; }'
                + '  .exemplar-badge { text-align: center; background: #fef3c7; color: #92400e; padding: 8px; font-size: 11px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }'
                + '  .noprint { padding: 8px 12px; text-align: center; }'
                + '  .noprint button { padding: 8px 16px; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }'
                + '  @media print { body { background: white; padding: 0; } .noprint, .header, .exemplar-badge { display: none; } .paper { box-shadow: none; margin: 0; } }'
                + '</style></head><body>'
                + '<div class="header">🖨️ Preview factura — 80mm width · ' + new Date().toLocaleString('ro-RO') + '</div>'
                + '<div class="exemplar-badge">Exemplar 1 (Cumparator)</div>'
                + html
                + '<div class="exemplar-badge">Exemplar 2 (Emitent / Arhiva)</div>'
                + html
                + '<div class="noprint"><button onclick="window.print()">🖨️ Print (opțional)</button> <button onclick="window.close()" style="background:#64748b">Închide</button></div>'
                + '</body></html>');
            w.document.close();
        },

        /** Test factură — date dummy reprezentative pentru calibrarea layout-ului. */
        async printTestInvoice(overrides) {
            const now = new Date();
            const sample = Object.assign({
                issuer: {
                    name: 'AMBILET TICKETING SRL',
                    tax_id: 'RO12345678',
                    registration: 'J40/1234/2025',
                    address: 'Str. Test Nr. 1',
                    city: 'Bucuresti',
                    county: 'Bucuresti',
                },
                series: 'P1-' + now.getFullYear() + '/00000080',
                customer: {
                    name: 'Ion Popescu',
                    email: 'ion.popescu@example.ro',
                    phone: '0712345678',
                },
                buyer_company: {
                    name: 'CSOMADCOM SRL',
                    cui: 'RO28151402',
                    reg_no: 'J14/123/2011',
                    address: 'Str. Centrală Nr. 12, Lăzărești, Harghita',
                },
                issued_at: now,
                items: [
                    { name: 'Bilet Adult', qty: 2, unit_price: 25.00, total: 50.00 },
                    { name: 'Bilet Copil', qty: 1, unit_price: 15.00, total: 15.00 },
                    { name: 'Ghidaj 09:00-11:00', qty: 1, unit_price: 80.00, total: 80.00 },
                ],
                total: 145.00,
                currency: 'RON',
            }, overrides || {});
            return await this.printInvoice(sample);
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
                    _inEndpoint = null;
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
