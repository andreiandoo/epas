<script>
    function signup() {
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

        return {
            state: @json($state),
            tenantTypes: @json($tenantTypes),
            counties: @json(array_values($counties)),
            templates: @json($templates),
            processors: @json($processors),
            microservices: @json($microservices),
            baseDomain: @json($baseDomain),
            paymentTermsDays: @json($paymentTermsDays),
            customSite: @json($customSite),
            preselectedModel: @json($preselectedModel),

            steps: [
                { key: 'account', label: 'Cont' }, { key: 'organizer', label: 'Organizator' }, { key: 'billing', label: 'Facturare' },
                { key: 'site', label: 'Site' }, { key: 'template', label: 'Aspect' }, { key: 'payments', label: 'Plăți' },
                { key: 'extras', label: 'Funcții' }, { key: 'review', label: 'Trimite' },
            ],
            workMethods: [
                { value: 'exclusive', fee: '1%', label: 'Doar prin Tixello', text: 'Toate biletele se vând prin noi: online, la casă și la intrare.' },
                { value: 'mixed', fee: '2%', label: 'Prin Tixello și în alte locuri', text: 'Pe lângă Tixello, vinzi și prin alte platforme sau agenții.' },
            ],
            entities: [
                { value: 'srl', label: 'SRL', text: 'Ai o firmă', icon: 'ph-buildings' },
                { value: 'pfa', label: 'PFA', text: 'Persoană fizică autorizată', icon: 'ph-briefcase' },
                { value: 'pf', label: 'Persoană fizică', text: 'Fără firmă', icon: 'ph-user' },
            ],
            processorText: {
                netopia: 'Procesator românesc, folosit de multe magazine online din țară.',
                stripe: 'Firmă internațională. Contul se face online, în câteva minute.',
                euplatesc: 'Procesator românesc. Contul se face prin bancă.',
                payu: 'Procesator internațional, prezent și în România.',
            },
            // De ce întrebăm, pe fiecare pas
            whyContent: {
                account: { title: 'De ce îți cerem datele astea', items: [
                    { icon: 'ph-envelope-simple', lead: 'Emailul.', text: 'Pe el primești codul de confirmare, contractul și facturile.' },
                    { icon: 'ph-phone', lead: 'Telefonul.', text: 'Te sunăm doar dacă avem o întrebare când îți verificăm contul. Nu-l dăm nimănui.' },
                    { icon: 'ph-lock-simple', lead: 'Parola.', text: 'Cu ea intri în panoul de organizator. O păstrăm criptată.' },
                    { icon: 'ph-scales', lead: 'Acordul.', text: 'Păstrăm data și ora la care ai acceptat termenii, cum cere legea.' },
                ] },
                verify: { title: 'De ce un cod', items: [
                    { icon: 'ph-shield-check', lead: 'Ne asigurăm că adresa e a ta.', text: 'Altfel, contractul și facturile ar putea ajunge la altcineva.' },
                    { icon: 'ph-timer', lead: 'Codul ține 30 de minute.', text: 'Dacă a expirat, cere altul de sub buton.' },
                ] },
                organizer: { title: 'Ce facem cu răspunsurile', items: [
                    { icon: 'ph-squares-four', lead: 'Tipul de evenimente.', text: 'Pornim panoul cu ce-ți trebuie. Un parc de agrement are nevoie de alte lucruri decât un teatru.' },
                    { icon: 'ph-ticket', lead: 'Numele public.', text: 'Apare pe bilete, pe site și în emailurile primite de cumpărători.' },
                    { icon: 'ph-percent', lead: 'Unde vinzi.', text: 'De aici iese comisionul. Dacă vinzi doar prin Tixello, plătești mai puțin.' },
                    { icon: 'ph-chart-line-up', lead: 'Estimarea.', text: 'Nu te obligă la nimic. Ne ajută să știm de ce ai nevoie la început.' },
                ] },
                billing: { title: 'Unde folosim datele', items: [
                    { icon: 'ph-file-text', lead: 'În contract.', text: 'Îl facem pe firma ta sau pe numele tău.' },
                    { icon: 'ph-receipt', lead: 'Pe facturile de comision.', text: 'Le emitem pe aceleași date, ca să nu fie diferențe.' },
                    { icon: 'ph-buildings', lead: 'Pentru firme,', text: 'datele vin de la ANAF. Nu scrii nimic de mână, deci nu apar greșeli.' },
                    { icon: 'ph-lock-key', lead: 'Pentru persoane fizice,', text: 'CNP-ul și buletinul apar doar în contract. Le păstrăm criptat și le văd doar colegii care îți verifică contul.' },
                ] },
                site: { title: 'Ce înseamnă fiecare variantă', items: [
                    { icon: 'ph-cloud-check', lead: 'Găzduit de noi.', text: 'Ne ocupăm noi de server, de certificatul de securitate și de viteză. Tu doar vinzi.' },
                    { icon: 'ph-globe-hemisphere-east', lead: 'Domeniul tău.', text: 'Îți trimitem pe email ce setări pui la firma de la care ai luat domeniul.' },
                    { icon: 'ph-clock-counter-clockwise', lead: 'Nu știi încă?', text: 'Alegi adresa și mai târziu, din panou.' },
                ] },
                payments: { title: 'Cum circulă banii', items: [
                    { icon: 'ph-credit-card', lead: 'Procesatorul', text: 'încasează plata cu cardul și îți virează banii în contul din bancă.' },
                    { icon: 'ph-bank', lead: 'Banii din bilete nu trec prin noi.', text: 'Ajung la tine prin procesator.' },
                    { icon: 'ph-handshake', lead: 'Nu ai procesator?', text: 'Îți spunem ce variante ai și ce costuri are fiecare.' },
                ] },
                review: { title: 'Ce urmează', items: [
                    { icon: 'ph-magnifying-glass', lead: 'Verificăm datele.', text: 'Ne uităm peste firmă și peste ce ai ales.' },
                    { icon: 'ph-envelope-simple', lead: 'Îți scriem pe email.', text: 'Primești aprobarea și contractul.' },
                    { icon: 'ph-ticket', lead: 'Pornești vânzarea.', text: 'Adaugi evenimentul, iar biletele se pot cumpăra.' },
                    { icon: 'ph-hourglass', lead: 'Până atunci,', text: 'poți intra în panou și pregăti totul.' },
                ] },
            },

            account: { first_name: '', last_name: '', email: '', phone: '', password: '', password_confirmation: '', accept_terms: false },
            organizer: { tenant_type: '', public_name: '', work_method: 'mixed', estimated_monthly_tickets: '' },
            billing: { legal_entity_type: '', cui: '', cnp: '', id_series: '', id_number: '', address: '', city: '', state: '' },
            site: { mode: 'subdomain', subdomain: '', domain: '' },
            template: null,
            tplCategory: 'Toate',
            processor: null,
            extras: { microservices: [] },
            showAllMicroservices: false,
            msSearch: '',
            msCategory: 'Toate',
            detail: null,
            company: null,
            code: '',
            errors: {},
            flash: '',
            notice: '',
            busy: false,
            pending: false,
            cuiBusy: false,
            showPassword: false,
            subdomainStatus: '',
            resendIn: 0,
            showIntro: false,

            init() {
                document.getElementById('ob-boot')?.remove();
                this.showIntro = this.state.step === 'account' && !new URLSearchParams(location.search).has('start');
                this.hydrate();
                if (this.state.step === 'verify') this.startResendTimer();
                window.addEventListener('beforeunload', (e) => {
                    if (!this.showIntro && this.state.step === 'account' && !this.busy && (this.account.email || this.account.first_name)) e.preventDefault();
                });
                this.$watch('state.step', () => this.$nextTick(() => { this.animateStep(); this.refreshHScroll(); }));
                this.$watch('showAllMicroservices', () => this.$nextTick(() => this.refreshHScroll()));
                this.$nextTick(() => {
                    this.overlayScrollbar();
                    this.refreshHScroll();
                    if (this.showIntro) this.animateIntro();
                });
            },

            // Câmpurile pornesc de la ce e salvat pe server
            hydrate() {
                const s = this.state;
                if (s.tenant_type) this.organizer.tenant_type = s.tenant_type;
                if (s.public_name) this.organizer.public_name = s.public_name;
                if (s.work_method) this.organizer.work_method = s.work_method === 'reseller' ? 'mixed' : s.work_method;
                if (s.estimated_monthly_tickets) this.organizer.estimated_monthly_tickets = s.estimated_monthly_tickets;
                if (s.legal_entity_type) this.billing.legal_entity_type = s.legal_entity_type;
                if (s.cui) this.billing.cui = s.cui;
                ['address', 'city'].forEach(k => { if (s[k]) this.billing[k] = s[k]; });
                if (s.county) this.billing.state = s.county;
                if (s.company_name) this.company = { company_name: s.company_name, reg_com: s.reg_com, vat_payer: s.vat_payer, address: s.address, city: s.city, county: s.county, postal_code: s.postal_code };
                if (s.site) Object.assign(this.site, s.site.mode === 'later' ? { mode: 'subdomain' } : s.site);
                if (!this.site.subdomain && s.suggested_subdomain) { this.site.subdomain = s.suggested_subdomain; this.subdomainStatus = 'free'; }
                if (s.template) this.template = s.template;
                else if (s.template_custom) this.template = '__custom';
                else if (this.preselectedModel && this.templates.some(t => t.slug === this.preselectedModel)) this.template = this.preselectedModel;
                if (s.processor) this.processor = s.processor;
                else if (s.step === 'review') this.processor = 'none';
                if (s.extras) this.extras = { microservices: (s.extras.microservices || []).map(Number) };
            },

            startSignup() {
                this.showIntro = false;
                window.scrollTo({ top: 0 });
                this.$nextTick(() => { this.animateStep(); this.$refs.firstField?.focus(); });
            },

            stepIndex() {
                const key = this.state.step === 'verify' ? 'account' : this.state.step;
                return this.steps.findIndex(s => s.key === key);
            },

            why() { return this.whyContent[this.state.step] || null; },
            showAside() { return !['template', 'submitted', 'rejected'].includes(this.state.step); },
            gridClass() { return this.showAside() ? 'lg:grid-cols-[minmax(0,1fr)_340px]' : ''; },

            async request(url, body) {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify(body),
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));
                return { ok: res.ok, status: res.status, data };
            },

            async send(url, body) {
                if (this.busy) return;
                this.busy = true; this.errors = {}; this.flash = '';
                // Scheletul apare doar dacă răspunsul întârzie; o clipire scurtă ar arăta a eroare
                const slow = setTimeout(() => { this.pending = true; }, 300);
                try {
                    const { ok, status, data } = await this.request(url, body);
                    if (ok && data.state) {
                        // După primul pas sesiunea e nouă: luăm tokenul CSRF proaspăt
                        if (data.csrf) document.querySelector('meta[name=csrf-token]').content = data.csrf;
                        this.state = data.state;
                        this.hydrate();
                        if (this.state.step === 'verify') { this.startResendTimer(); this.$nextTick(() => this.$refs.code?.focus()); }
                        window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
                        return;
                    }
                    this.showErrors(status, data);
                } catch (e) {
                    this.flash = 'Nu am putut contacta serverul. Verifică internetul și încearcă din nou.';
                } finally {
                    clearTimeout(slow);
                    this.pending = false;
                    this.busy = false;
                }
            },

            showErrors(status, data) {
                if (status === 422 && data.errors) {
                    this.errors = Object.fromEntries(Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
                    this.$nextTick(() => document.querySelector('[aria-invalid="true"]')?.focus());
                } else if (status === 419) {
                    this.flash = 'Sesiunea a expirat. Reîncarcă pagina; ce ai salvat rămâne.';
                } else if (status === 429) {
                    this.flash = 'Prea multe încercări într-un timp scurt. Mai încearcă peste un minut.';
                } else {
                    this.flash = data.message || 'Ceva n-a mers. Mai încearcă o dată.';
                }
            },

            async verify() {
                if (this.code.length !== 6) { this.errors = { code: 'Codul are 6 cifre.' }; return; }
                await this.send('/inregistrare/verificare', { code: this.code });
                if (this.errors.code) { this.code = ''; this.$refs.code.focus(); }
            },

            startResendTimer() {
                this.resendIn = 60;
                clearInterval(this._resendTimer);
                this._resendTimer = setInterval(() => { this.resendIn--; if (this.resendIn <= 0) { this.resendIn = 0; clearInterval(this._resendTimer); } }, 1000);
            },

            async resend() {
                const { data } = await this.request('/inregistrare/verificare/retrimite', {});
                this.notice = data.message || '';
                this.startResendTimer();
            },

            async lookupCui() {
                if (!this.billing.cui.trim()) { this.errors = { cui: 'Scrie CUI-ul firmei.' }; return; }
                this.cuiBusy = true; this.errors = {}; this.company = null;
                try {
                    const { ok, status, data } = await this.request('/inregistrare/cui', { cui: this.billing.cui });
                    if (ok) {
                        this.company = data.company;
                        this.billing.cui = data.company.cui;
                        // Ce are ANAF înlocuiește ce era în formular
                        if (data.company.address) this.billing.address = data.company.address;
                        if (data.company.city) this.billing.city = data.company.city;
                        if (data.company.county) this.billing.state = data.company.county;
                    } else {
                        this.showErrors(status, data);
                    }
                } catch (e) {
                    this.flash = 'ANAF nu răspunde acum. Mai încearcă peste câteva minute.';
                } finally {
                    this.cuiBusy = false;
                }
            },

            // La firmă cerem adresa doar dacă ANAF nu ne-a dat-o completă
            needsAddress() {
                const t = this.billing.legal_entity_type;
                if (t === 'pf') return true;
                if (t !== 'srl' && t !== 'pfa') return false;
                return !!this.company && (!this.company.address || !this.company.city || !this.company.county);
            },

            async checkSubdomain() {
                const v = this.site.subdomain = (this.site.subdomain || '').toLowerCase().trim();
                if (v.length < 3) { this.subdomainStatus = v ? 'taken' : ''; return; }
                this.subdomainStatus = 'checking';
                const { data } = await this.request('/inregistrare/subdomeniu', { subdomain: v });
                if (this.site.subdomain === v) this.subdomainStatus = data.available ? 'free' : 'taken';
            },

            saveSite() {
                const body = { mode: this.site.mode };
                if (this.site.mode === 'subdomain') body.subdomain = this.site.subdomain;
                if (this.site.mode === 'own') body.domain = this.site.domain;
                this.send('/inregistrare/site', body);
            },

            templateCategories() { return [...new Set(this.templates.map(t => t.category))]; },

            saveTemplate() {
                if (this.template === '__custom') return this.send('/inregistrare/template', { slug: null, custom: true });
                if (this.template) return this.send('/inregistrare/template', { slug: this.template });
                this.flash = 'Alege un model sau apasă „Aleg mai târziu”.';
            },

            // Înapoi doar vizual: pașii salvați se pot modifica și trimite din nou
            back() {
                const order = ['organizer', 'billing', 'site', 'template', 'payments', 'extras', 'review'];
                const i = order.indexOf(this.state.step);
                if (i > 0) this.goTo(order[i - 1]);
            },

            goTo(step) {
                this.errors = {}; this.flash = '';
                this.state = { ...this.state, step };
                window.scrollTo({ top: 0 });
            },

            // ---------- Funcții în plus
            isChosen(m) { return !!m && this.extras.microservices.includes(m.id); },
            toggleMicroservice(m) {
                const i = this.extras.microservices.indexOf(m.id);
                i === -1 ? this.extras.microservices.push(m.id) : this.extras.microservices.splice(i, 1);
            },
            openDetails(m) { this.detail = m; this.$nextTick(() => this.$refs.sheet.showModal()); },
            msCategories() { return [...new Set(this.microservices.map(m => m.category))].sort((a, b) => a.localeCompare(b, 'ro')); },
            visibleMicroservices() {
                if (!this.showAllMicroservices) return this.microservices.filter(m => m.featured || this.isChosen(m));
                const q = this.msSearch.trim().toLowerCase();
                return this.microservices.filter(m =>
                    (this.msCategory === 'Toate' || m.category === this.msCategory) &&
                    (!q || (m.name + ' ' + m.text + ' ' + m.features.join(' ')).toLowerCase().includes(q)));
            },
            categoryIcon(cat) {
                return { 'Vânzare': 'ph-storefront', 'Marketing': 'ph-megaphone', 'Comunicare': 'ph-chat-circle-text', 'Plăți': 'ph-credit-card',
                    'Contabilitate': 'ph-calculator', 'Rapoarte': 'ph-chart-line-up', 'Integrări': 'ph-plugs-connected', 'Operațiuni': 'ph-gear-six',
                    'Design': 'ph-paint-brush', 'Video': 'ph-video-camera', 'Agrement': 'ph-tree' }[cat] || 'ph-puzzle-piece';
            },
            money(value, currency) {
                return value.toLocaleString('ro-RO', { maximumFractionDigits: 2 }) + ' ' + (currency === 'RON' ? 'lei' : currency === 'EUR' ? '€' : currency);
            },
            hosted() { return this.state.site?.mode === 'subdomain'; },
            priceLabel(m) {
                // Cu site găzduit de noi, punctul în plus e deja plătit: funcțiile marcate nu mai costă nimic
                if (m.raises_commission) return this.hosted() ? 'Inclus, plătești deja +1 punct' : '+1 punct la comision';
                if (!m.price) return 'Gratuit';
                return this.money(m.price, m.currency) + ({ monthly: ' / lună', one_time: ' o singură dată', usage: ' / utilizare' }[m.cycle] || '');
            },
            baseCommission() { return this.state.work_method === 'exclusive' ? 1 : 2; },
            commissionMicroservices() { return this.microservices.filter(m => m.raises_commission && this.isChosen(m)).map(m => m.name); },
            commissionPreview() { return this.baseCommission() + (this.hosted() || this.commissionMicroservices().length ? 1 : 0); },
            commissionReason() {
                const names = this.commissionMicroservices();
                const list = names.length > 1 ? names.slice(0, -1).join(', ') + ' și ' + names[names.length - 1] : names[0];
                const why = this.hosted()
                    ? 'ai ales ca site-ul tău să fie găzduit de noi' + (names.length ? ' și ' + list : '')
                    : 'ai ales ' + list;
                return 'Comisionul e ' + this.commissionPreview() + '% în loc de ' + this.baseCommission() + '% pentru că ' + why + '. '
                    + 'Pentru asta nu plătești abonament: costul e un singur punct în plus, oricâte ai alege.'
                    + (names.length && !this.hosted() ? ' Dacă renunți la ele, revii la ' + this.baseCommission() + '%.' : '');
            },
            paidChosen(cycle) {
                const items = this.microservices.filter(m => !m.raises_commission && m.price > 0 && m.cycle === cycle && this.isChosen(m));
                if (cycle === 'one_time' && this.state.template_custom) {
                    items.unshift({ id: 'custom-site', name: 'Site făcut de noi', price: this.customSite.price, currency: this.customSite.currency, cycle: 'one_time' });
                }
                return items;
            },
            // Totalul pe monede: unele prețuri sunt în lei, altele în euro
            sumLabel(cycle, withoutCustomSite = false) {
                const totals = {};
                this.paidChosen(cycle).filter(m => !(withoutCustomSite && m.id === 'custom-site')).forEach(m => { totals[m.currency] = (totals[m.currency] || 0) + m.price; });
                return Object.entries(totals).map(([c, v]) => this.money(v, c)).join(' + ');
            },
            extrasSummary() {
                const names = this.microservices.filter(m => (this.state.extras?.microservices || []).map(Number).includes(m.id)).map(m => m.name);
                return names.length ? names.join(', ') : 'Niciuna';
            },

            reviewRows() {
                const s = this.state;
                const entity = { srl: 'SRL', pfa: 'PFA', pf: 'Persoană fizică' }[s.legal_entity_type] || '—';
                const site = !s.site || s.site.mode === 'later' ? 'Hotărăsc mai târziu'
                    : s.site.mode === 'subdomain' ? s.site.subdomain + '.' + this.baseDomain + ' (găzduit de noi)' : s.site.domain;
                const tpl = this.templates.find(t => t.slug === s.template);
                const proc = this.processors.find(p => p.value === s.processor);
                const method = this.workMethods.find(m => m.value === s.work_method);
                return [
                    { label: 'Organizator', value: (s.public_name || '—') + (s.tenant_type_label ? ', ' + s.tenant_type_label.toLowerCase() : ''), step: 'organizer' },
                    { label: 'Comision', value: (s.commission_rate ? s.commission_rate.toLocaleString('ro-RO') + '% pe bilet' : '—') + (method ? ', ' + method.label.toLowerCase() : ''), step: 'organizer' },
                    { label: 'Contract pe', value: entity + (s.company_name ? ', ' + s.company_name : ''), step: 'billing' },
                    { label: 'Site', value: site, step: 'site' },
                    { label: 'Aspect', value: tpl ? tpl.name : (s.template_custom ? 'Făcut de noi de la zero (' + this.money(this.customSite.price, this.customSite.currency) + ')' : 'Aleg mai târziu'), step: 'template' },
                    { label: 'Plăți', value: proc ? proc.label : 'Încă nu am procesator', step: 'payments' },
                    { label: 'Funcții', value: this.extrasSummary(), step: 'extras' },
                    ...(this.paidChosen('monthly').length ? [{ label: 'Abonamente', value: this.sumLabel('monthly') + ' / lună, din panou', step: 'extras' }] : []),
                    ...(this.paidChosen('one_time').some(m => m.id !== 'custom-site') ? [{ label: 'Plăți unice', value: this.sumLabel('one_time', true) + ', din panou', step: 'extras' }] : []),
                ];
            },

            // ---------- Mișcare (GSAP), doar dacă vizitatorul nu a cerut mișcare redusă
            animateIntro() {
                // Intrările sunt animații CSS (.ob-enter): conținutul nu depinde de bucla JS
                if (this._introAnimated) return;
                this._introAnimated = true;
                document.querySelectorAll('.ob-in, .ob-shot').forEach((el, i) => { el.style.setProperty('--d', (i * 60) + 'ms'); el.classList.add('ob-enter'); });
                if (reduceMotion) return;
                if (window.gsap) {
                    // Plutirea merge pe margin, ca să nu se bată cu tilt-ul (transform)
                    gsap.to('.ob-shot', { marginTop: -10, duration: 3.2, ease: 'sine.inOut', yoyo: true, repeat: -1, stagger: .7, delay: 1.2 });
                    document.querySelectorAll('[data-count]').forEach((el) => {
                        const target = Number(el.dataset.count), o = { v: 0 };
                        // Numărătoarea pornește de la cifra reală dacă animația nu rulează
                        gsap.fromTo(o, { v: target * .6 }, { v: target, duration: 1.4, ease: 'power2.out', delay: .3, onUpdate: () => { el.textContent = Math.round(o.v).toLocaleString('ro-RO'); }, onComplete: () => { el.textContent = target.toLocaleString('ro-RO'); } });
                    });
                }
                if (finePointer && window.VanillaTilt) VanillaTilt.init(document.querySelectorAll('[data-tilt]'), { max: 5, speed: 700, scale: 1.01, gyroscope: false });
            },
            animateStep() {
                const el = this.$refs.stepBody;
                if (!el) return;
                el.classList.remove('ob-enter'); void el.offsetWidth; el.classList.add('ob-enter');
            },

            // ---------- Bara de derulare peste conținut (doar mouse; pe touch rămâne cea nativă)
            overlayScrollbar() {
                if (!finePointer) return;
                const root = document.documentElement;
                root.classList.add('ob-sb');
                const bar = document.createElement('div');
                bar.className = 'ob-sbar'; bar.setAttribute('aria-hidden', 'true');
                bar.innerHTML = '<div class="ob-sbar__thumb"></div>';
                document.body.appendChild(bar);
                const thumb = bar.firstChild;
                let hideT = null, drag = null, raf = 0, thumbH = 0;
                const metrics = () => { const vh = innerHeight, sh = Math.max(root.scrollHeight, document.body.scrollHeight); return { vh, sh, max: Math.max(0, sh - vh) }; };
                const place = () => {
                    raf = 0; const m = metrics();
                    if (m.max <= 2) { bar.style.display = 'none'; return; }
                    bar.style.display = '';
                    thumbH = Math.max(40, Math.round(m.vh * m.vh / m.sh));
                    thumb.style.height = thumbH + 'px';
                    thumb.style.transform = 'translateY(' + (Math.round((scrollY / m.max) * (m.vh - thumbH - 8)) + 4) + 'px)';
                };
                const show = () => { bar.classList.add('is-on'); clearTimeout(hideT); hideT = setTimeout(() => { if (!drag) bar.classList.remove('is-on'); }, 1100); };
                const schedule = () => { if (!raf) raf = requestAnimationFrame(place); };
                addEventListener('scroll', () => { schedule(); show(); }, { passive: true });
                addEventListener('resize', schedule, { passive: true });
                if (window.ResizeObserver) new ResizeObserver(schedule).observe(document.body);
                addEventListener('mousemove', (e) => { if (innerWidth - e.clientX < 16) show(); }, { passive: true });
                thumb.addEventListener('pointerdown', (e) => { e.preventDefault(); thumb.setPointerCapture(e.pointerId); drag = { y: e.clientY, start: scrollY }; bar.classList.add('is-drag', 'is-on'); root.classList.add('ob-dragging'); });
                thumb.addEventListener('pointermove', (e) => { if (!drag) return; const m = metrics(), track = m.vh - thumbH - 8; scrollTo(0, drag.start + (e.clientY - drag.y) * (track > 0 ? m.max / track : 0)); });
                const end = () => { if (!drag) return; drag = null; bar.classList.remove('is-drag'); root.classList.remove('ob-dragging'); show(); };
                thumb.addEventListener('pointerup', end); thumb.addEventListener('pointercancel', end);
                place();
            },
            // Derulare orizontală (categorii): banda apare jos doar cât derulezi
            hscroll(e) {
                const track = e.target, wrap = track.parentElement, thumb = wrap.querySelector('.hscroll__thumb');
                this.placeHThumb(track, thumb);
                wrap.classList.add('is-on');
                clearTimeout(wrap._t); wrap._t = setTimeout(() => wrap.classList.remove('is-on'), 900);
            },
            placeHThumb(track, thumb) {
                if (!thumb) return;
                const ratio = track.clientWidth / track.scrollWidth;
                if (ratio >= 1) { thumb.style.display = 'none'; return; }
                thumb.style.display = '';
                const w = Math.max(30, track.clientWidth * ratio);
                thumb.style.width = w + 'px';
                thumb.style.transform = 'translateX(' + (track.scrollLeft / (track.scrollWidth - track.clientWidth)) * (track.clientWidth - w) + 'px)';
            },
            refreshHScroll() {
                document.querySelectorAll('.hscroll').forEach((wrap) => {
                    const track = wrap.querySelector('.hscroll__track');
                    if (!track) return;
                    this.placeHThumb(track, wrap.querySelector('.hscroll__thumb'));
                    // Rotița mouse-ului derulează și pe orizontală
                    if (!track._wheel) {
                        track._wheel = true;
                        track.addEventListener('wheel', (e) => { if (Math.abs(e.deltaY) > Math.abs(e.deltaX) && track.scrollWidth > track.clientWidth) { track.scrollLeft += e.deltaY; e.preventDefault(); } }, { passive: false });
                    }
                });
            },
        };
    }
</script>
