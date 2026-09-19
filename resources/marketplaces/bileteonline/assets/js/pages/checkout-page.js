/**
 * bilete.online — checkout page (/finalizare).
 *
 * Runs the form scaffolded by checkout.php (v2 markup; styles in assets/v2/css/cart.css + checkout.css): buyer
 * details, beneficiaries, optional ticket insurance, payment method, terms and the order summary. Creates the order
 * through the checkout API, then hands off to the payment processor (a redirect, or a POST form for e.g. Netopia).
 */
const CheckoutPage = {
    items: [],
    taxes: [],
    insurance: null,
    insuranceSelected: false,
    culturalCardSurchargeRate: 0, // % extra for cultural card transactions (loaded from API)
    culturalCardEnabled: false,
    totals: { subtotal: 0, tax: 0, discount: 0, insurance: 0, culturalCardSurcharge: 0, total: 0, savings: 0 },
    timerInterval: null,
    endTime: null,
    submitting: false,   // true from a valid submit until the redirect (or an error): blocks a second order
    modalOpener: null,

    async init() {
        this.items = BileteOnlineCart.getItems();
        this.loadTaxes();

        if (this.items.length === 0) {
            document.getElementById('checkout-loading').classList.add('hidden');
            document.getElementById('empty-cart').classList.remove('hidden');
            return;
        }

        // CAPI InitiateCheckout (Layer B bridge). Fires once when the checkout page loads with at least one item.
        try {
            if (window.EPASTracking && typeof EPASTracking.trackBeginCheckout === 'function') {
                const totalValue = this.items.reduce(
                    (sum, item) => sum + ((item.ticketType?.price || 0) * (item.quantity || 0)),
                    0
                );
                const firstEventId = this.items[0]?.eventId || null;
                if (firstEventId) {
                    EPASTracking.trackBeginCheckout(firstEventId, totalValue, 'RON', {
                        marketplace_event_id: firstEventId,
                        num_items: this.items.reduce((s, i) => s + (i.quantity || 0), 0),
                    });
                }
            }
        } catch (e) {
            // Tracking must never break checkout
        }

        // Load checkout features (insurance, cultural card) and the loyalty points rules
        await Promise.all([this.loadCheckoutFeatures(), BileteOnlineCart.loadLoyaltyConfig()]);
        this.loyalty = BileteOnlineCart.getLoyaltyConfig();
        await this.loadPointsBalance();

        this.setupTimer();
        this.setupPaymentOptions();
        this.setupTermsCheckbox();
        this.setupInsuranceCheckbox();
        this.setupPoints();
        this.prefillBuyerInfo();
        this.renderBeneficiaries();
        this.renderSummary();

        document.getElementById('checkout-loading').classList.add('hidden');
        document.getElementById('checkout-form').classList.remove('hidden');
        document.getElementById('summary-section').classList.remove('hidden');
    },

    // ==================== LOYALTY POINTS ====================
    // Rules from /checkout/features (BileteOnlineCart mirrors core's MarketplaceLoyaltyService): a logged-in customer
    // pays part of the tickets with points (up to the programme's % and per-order cap), on the email of the account.
    // The discount comes off before the processing fee, exactly as the server does it.

    loyalty: null,
    pointsBalance: 0,
    pointsEmail: '',
    usePoints: false,

    async loadPointsBalance() {
        this.pointsBalance = 0;
        this.pointsEmail = '';
        if (!this.loyalty || typeof BileteOnlineAuth === 'undefined' || !BileteOnlineAuth.isCustomer()) return;
        try {
            // fresh balance every time (a cached one could offer points already spent in another tab)
            const r = await BileteOnlineAPI.request('/customer/rewards', { method: 'GET', noCache: true });
            const p = r && r.data && r.data.points;
            this.pointsBalance = Math.max(0, Math.floor(Number(p && (p.balance != null ? p.balance : p.current_balance)) || 0));
            const user = BileteOnlineAuth.getUser();
            this.pointsEmail = (user && user.email ? String(user.email) : '').trim().toLowerCase();
        } catch (e) {
            this.pointsBalance = 0;
        }
    },

    setupPoints() {
        const box = document.getElementById('use-points');
        if (box) box.addEventListener('change', () => { this.usePoints = box.checked; this.renderSummary(); });
        const login = document.getElementById('points-login');
        if (login) login.addEventListener('click', () => this.showLoginModal());
        // the points belong to the account's email: re-check when the buyer's email changes
        const email = document.getElementById('buyer-email');
        if (email) email.addEventListener('input', () => { if (this.loyalty && this.pointsBalance) this.renderSummary(); });
    },

    pointsWord(n) {
        n = Math.floor(n);
        if (n === 1) return '1 punct';
        const rem = n % 100;
        return new Intl.NumberFormat('ro-RO').format(n) + (n >= 20 && !(rem >= 1 && rem <= 19) ? ' de puncte' : ' puncte');
    },

    /** How many points this order uses (0 when off), and what the box says. */
    pointsState(ticketValue) {
        const state = { show: false, usable: 0, used: 0, note: '', login: false, canUse: false };
        const c = this.loyalty;
        if (!c) return state;
        const loggedIn = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.isCustomer();
        if (!loggedIn) {
            state.show = ticketValue > 0;
            state.login = true;
            state.note = 'Ai puncte bilete.online? Intră în cont ca să plătești o parte din bilete cu ele.';
            return state;
        }
        const min = Math.floor(Number(c.min_redeem_points) || 0);
        if (this.pointsBalance <= 0) return state;
        state.show = ticketValue > 0;
        if (this.pointsBalance < min) {
            state.note = 'Ai ' + this.pointsWord(this.pointsBalance) + '. Le poți folosi de la ' + this.pointsWord(min) + '.';
            return state;
        }
        const buyer = (document.getElementById('buyer-email')?.value || '').trim().toLowerCase();
        if (this.pointsEmail && buyer && buyer !== this.pointsEmail) {
            state.note = 'Punctele se pot folosi doar la comenzile făcute cu adresa de email a contului tău.';
            return state;
        }
        state.usable = BileteOnlineCart.maxRedeemablePoints(ticketValue, this.pointsBalance);
        if (state.usable <= 0) { state.show = false; return state; }
        state.canUse = true;
        state.used = this.usePoints ? state.usable : 0;
        return state;
    },

    renderPoints(state, earnBase) {
        const c = this.loyalty;
        const box = document.getElementById('points-box');
        const reward = document.getElementById('points-reward');
        if (!box || !reward) return;

        box.classList.toggle('hidden', !state.show);
        const row = document.getElementById('points-use-row');
        const input = document.getElementById('use-points');
        row.hidden = !(state.canUse || (state.show && !state.login && state.note));
        input.disabled = !state.canUse;
        if (!state.canUse) this.usePoints = false;
        input.checked = this.usePoints; // the box always says what the total uses
        if (state.canUse) {
            document.getElementById('use-points-title').textContent = 'Folosește ' + this.pointsWord(state.usable);
            document.getElementById('use-points-sub').textContent = '−' + this.money(BileteOnlineCart.pointsValue(state.usable)) +
                ' din bilete · ai ' + this.pointsWord(this.pointsBalance);
        } else if (!state.login) {
            document.getElementById('use-points-title').textContent = 'Folosește punctele';
            document.getElementById('use-points-sub').textContent = 'ai ' + this.pointsWord(this.pointsBalance);
        }
        const note = document.getElementById('points-note');
        note.hidden = !state.note;
        note.textContent = state.note;
        document.getElementById('points-login').hidden = !state.login;

        const pointsRow = document.getElementById('points-row');
        if (state.used > 0) {
            pointsRow.classList.remove('hidden');
            document.getElementById('points-row-label').textContent = 'Plătit cu ' + this.pointsWord(state.used);
            document.getElementById('points-row-amount').textContent = '-' + this.money(BileteOnlineCart.pointsValue(state.used));
        } else {
            pointsRow.classList.add('hidden');
        }

        // what the order earns: on the ticket value paid in money, credited after the activity
        const earned = c ? BileteOnlineCart.estimatePoints(earnBase) : 0;
        reward.classList.toggle('hidden', !c || earned <= 0);
        if (c && earned > 0) {
            document.getElementById('points-rule').textContent = (c.earn_rate_label ? c.earn_rate_label.charAt(0).toUpperCase() + c.earn_rate_label.slice(1) + ', ' : '') + 'în cont după activitate';
            document.getElementById('points-earned').textContent = this.pointsWord(earned);
        }
    },

    // ==================== HELPERS ====================

    esc(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },

    icon(name) {
        return '<svg class="ic" aria-hidden="true"><use href="#i-' + name + '"/></svg>';
    },

    money(value) {
        return BileteOnlineUtils.formatCurrency(value);
    },

    /** Romanian counting: 1 bilet, 5 bilete, 20 de bilete, 101 bilete. */
    ticketsWord(n) {
        if (n === 1) return 'bilet';
        const rest = n % 100;
        return n !== 0 && (rest === 0 || rest >= 20) ? 'de bilete' : 'bilete';
    },

    /** A bare YYYY-MM-DD parses as UTC midnight; adding a local time keeps it on the booked day in any time zone. */
    localDate(value) {
        return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value + 'T00:00:00' : value;
    },

    formatDay(value, format) {
        if (!value) return '';
        try {
            const out = BileteOnlineUtils.formatDate(this.localDate(value), format);
            return out && out !== 'Invalid Date' ? out : String(value);
        } catch (e) {
            return String(value);
        }
    },

    storageUrl(path) {
        if (!path) return '';
        return typeof getStorageUrl === 'function' ? getStorageUrl(path) : path;
    },

    /** Activity lines count participants (participants_count; quantity is its alias), event lines count tickets. */
    itemQuantity(item) {
        return item.type === 'activity' ? (item.participants_count || item.quantity || 1) : (item.quantity || 1);
    },

    itemName(item) {
        return item.type === 'activity'
            ? (item.variant?.name || 'Rezervare')
            : (item.ticketType?.name || item.ticket_type_name || 'Bilet');
    },

    itemTitle(item) {
        return item.type === 'activity'
            ? (item.activity?.title || item.activity?.name || 'Activitate')
            : (item.event?.title || item.event?.name || item.event_title || 'Eveniment');
    },

    /** Brand-line placeholder behind a photo (same segments as v2_fallback() in PHP). */
    fallback(seed) {
        const segs = [['1060 585 220 310', '220 / 310'], ['1455 585 290 310', '290 / 310'], ['2170 625 340 270', '340 / 270'], ['2665 625 250 270', '250 / 270']];
        let sum = 0;
        for (const ch of String(seed || '')) sum += ch.codePointAt(0);
        const seg = segs[sum % segs.length];
        return '<span class="fb"><svg viewBox="' + seg[0] + '" style="aspect-ratio:' + seg[1] + '"><use href="#drum-g"/></svg></span>';
    },

    notify(type, message) {
        if (typeof BileteOnlineNotifications !== 'undefined') {
            BileteOnlineNotifications[type](message);
        }
    },

    focusField(el) {
        if (!el) return;
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        el.focus({ preventScroll: true });
    },

    // ==================== FEATURES: CULTURAL CARD, INSURANCE ====================

    async loadCheckoutFeatures() {
        try {
            const response = await BileteOnlineAPI.get('/checkout.features');
            if (response.success && response.data) {
                // Handle cultural card settings
                if (response.data.cultural_card && response.data.cultural_card.enabled) {
                    this.culturalCardEnabled = true;
                    this.culturalCardSurchargeRate = response.data.cultural_card.surcharge_percent || 4;
                    this.showCulturalCardOption();
                }

                // Handle ticket insurance
                if (response.data.ticket_insurance && response.data.ticket_insurance.enabled && response.data.ticket_insurance.show_in_checkout) {
                    this.insurance = response.data.ticket_insurance;

                    // Determine eligible items based on apply_to setting
                    const applyTo = this.insurance.apply_to || 'all';
                    let eligibleItems, ineligibleItems;

                    if (applyTo === 'refundable_only') {
                        eligibleItems = this.items.filter(item => item.ticketType?.is_refundable);
                        ineligibleItems = this.items.filter(item => !item.ticketType?.is_refundable);
                    } else {
                        eligibleItems = [...this.items];
                        ineligibleItems = [];
                    }

                    const hasEligible = eligibleItems.length > 0;
                    const isMixed = hasEligible && ineligibleItems.length > 0;

                    // Only show insurance if cart has at least one eligible ticket
                    if (!hasEligible) {
                        this.insurance = null;
                        return;
                    }

                    this.insurance._refundableItems = eligibleItems;
                    this.insurance._isMixed = isMixed;

                    this.setupInsuranceUI();
                }
            }
        } catch (error) {
            console.log('Could not load checkout features:', error);
        }
    },

    showCulturalCardOption() {
        const culturalOption = document.getElementById('cultural-card-option');
        if (culturalOption) {
            culturalOption.classList.remove('hidden');
        }
        const surchargeText = document.getElementById('cultural-card-surcharge-text');
        if (surchargeText) {
            surchargeText.innerHTML = `Tranzacțiile cu card cultural au un comision de procesare suplimentar de <strong>${this.esc(this.culturalCardSurchargeRate)}%</strong> din valoarea totală, datorat costurilor mai mari de procesare pentru acest tip de card.`;
        }
        const surchargeLabel = document.getElementById('cultural-card-surcharge-label');
        if (surchargeLabel) {
            surchargeLabel.textContent = `Comision card cultural (${this.culturalCardSurchargeRate}%)`;
        }
    },

    setupInsuranceUI() {
        if (!this.insurance) return;

        const section = document.getElementById('insurance-section');
        if (!section) return;

        section.classList.remove('hidden');

        document.getElementById('insurance-label').textContent = this.insurance.label || 'Taxa de retur';
        document.getElementById('insurance-title').textContent = this.insurance.label || 'Protecție returnare bilete';
        document.getElementById('insurance-description').textContent = this.insurance.description || '';

        // Insurance always applies only to eligible (refundable) tickets
        const isMixed = this.insurance._isMixed;
        const refundableItems = this.insurance._refundableItems || [];
        const applicableTickets = refundableItems.reduce((sum, item) => sum + (item.quantity || 1), 0);

        if (this.insurance.price_type === 'fixed') {
            const pricePerTicket = this.insurance.price || 0;
            document.getElementById('insurance-price').textContent = this.money(pricePerTicket) + '/bilet';
        } else {
            document.getElementById('insurance-price').textContent = this.insurance.price_percentage + '% din total';
        }

        // Show partial note for mixed carts (some eligible, some not)
        const partialNote = document.getElementById('insurance-partial-note');
        if (partialNote && isMixed) {
            const eligibleNames = refundableItems.map(item => {
                const ticketName = item.ticketType?.name || 'Bilet';
                const eventName = item.event?.title || item.event?.name || item.event_title || '';
                const eventDate = item.event?.date || item.event_date || '';
                const city = item.event?.city?.name || item.event?.city || item.event?.venue?.city || '';
                const details = [eventName, eventDate ? this.formatDay(eventDate, 'medium') : '', city].filter(Boolean).join(' · ');
                return details ? ticketName + ' (' + details + ')' : ticketName;
            }).join(', ');
            partialNote.textContent = 'Se aplică doar pentru biletele returnabile: ' + eligibleNames;
            partialNote.classList.remove('hidden');
        }

        if (this.insurance.terms_url) {
            const termsLink = document.getElementById('insurance-terms-link');
            termsLink.href = this.insurance.terms_url;
            termsLink.classList.remove('hidden');
        }

        const checkbox = document.getElementById('insuranceCheckbox');
        if (this.insurance.pre_checked) {
            checkbox.checked = true;
            this.insuranceSelected = true;
        }

        document.getElementById('insurance-row-label').textContent =
            (this.insurance.label || 'Taxa de retur') + ' (' + applicableTickets + ' ' + this.ticketsWord(applicableTickets) + ')';
    },

    setupInsuranceCheckbox() {
        const checkbox = document.getElementById('insuranceCheckbox');
        if (!checkbox) return;
        const option = document.getElementById('insurance-option');

        checkbox.addEventListener('change', () => {
            this.insuranceSelected = checkbox.checked;
            if (option) option.classList.toggle('is-on', this.insuranceSelected);
            this.renderSummary();
        });

        if (this.insuranceSelected && option) {
            option.classList.add('is-on');
        }
    },

    calculateInsuranceAmount() {
        if (!this.insurance) return 0;

        const applicableItems = this.insurance._refundableItems || [];
        if (applicableItems.length === 0) return 0;

        const applicableTickets = applicableItems.reduce((sum, item) => sum + (item.quantity || 1), 0);

        if (this.insurance.price_type === 'percentage') {
            // Based on the subtotal of applicable items only
            const subtotal = applicableItems.reduce((sum, item) => {
                const price = item.ticketType?.price || item.price || 0;
                return sum + (price * (item.quantity || 1));
            }, 0);
            return Math.round(subtotal * (this.insurance.price_percentage / 100) * 100) / 100;
        }

        // Fixed price per ticket x number of applicable tickets
        const pricePerTicket = this.insurance.price || 0;
        return Math.round(pricePerTicket * applicableTickets * 100) / 100;
    },

    // ==================== TIMER ====================

    setupTimer() {
        const savedEndTime = localStorage.getItem('cart_end_time');
        const timerBar = document.getElementById('timer-bar');

        if (savedEndTime && parseInt(savedEndTime) > Date.now()) {
            this.endTime = parseInt(savedEndTime);
            timerBar.classList.remove('hidden');
            this.updateCountdown();
            this.timerInterval = setInterval(() => this.updateCountdown(), 1000);
        } else if (this.items.length > 0) {
            // Start a new timer if cart has items but no saved time
            this.endTime = Date.now() + (15 * 60 * 1000); // 15 minutes
            localStorage.setItem('cart_end_time', this.endTime);
            timerBar.classList.remove('hidden');
            this.updateCountdown();
            this.timerInterval = setInterval(() => this.updateCountdown(), 1000);
        }
    },

    updateCountdown() {
        const remaining = Math.max(0, this.endTime - Date.now());
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);

        const countdownEl = document.getElementById('countdown');
        const timerBar = document.getElementById('timer-bar');
        if (!countdownEl) return;

        countdownEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        if (remaining <= 0) {
            clearInterval(this.timerInterval);
            countdownEl.textContent = '00:00';
            if (timerBar) {
                timerBar.classList.remove('is-warn', 'is-urgent');
                timerBar.classList.add('is-expired');
            }
            BileteOnlineCart.clear();
            localStorage.removeItem('cart_end_time');
            this.notify('warning', 'Timpul de rezervare a expirat. Biletele au fost eliberate.');
            // Redirect to cart page after short delay
            setTimeout(() => {
                window.location.href = '/cos';
            }, 2000);
        } else if (remaining < 60000) {
            if (timerBar) {
                timerBar.classList.remove('is-warn');
                timerBar.classList.add('is-urgent');
            }
        } else if (remaining <= 5 * 60 * 1000) {
            if (timerBar) timerBar.classList.add('is-warn');
        }
    },

    // ==================== FORM ====================

    setupPaymentOptions() {
        const select = (option) => {
            document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
            option.classList.add('selected');
            const input = option.querySelector('input[type="radio"]');
            input.checked = true;

            // Toggled with the class, not style.display: base.css hides .hidden with !important
            document.getElementById('cardForm').classList.toggle('hidden', input.value !== 'card');
            document.getElementById('culturalCardForm').classList.toggle('hidden', input.value !== 'card_cultural');

            // Re-render summary to update cultural card surcharge
            this.renderSummary();
        };
        // The radios are visually hidden but focusable: a click on the card and the arrow keys both land here
        document.querySelectorAll('.payment-option').forEach(option => {
            option.querySelector('input[type="radio"]').addEventListener('change', () => select(option));
        });
    },

    setupTermsCheckbox() {
        document.getElementById('termsCheckbox').addEventListener('change', function() {
            if (!CheckoutPage.submitting) {
                document.getElementById('payBtn').disabled = !this.checked;
            }
        });
    },

    loadTaxes() {
        // Load ALL taxes from cart items (both included in price and added on top)
        if (this.items.length > 0 && this.items[0].event?.taxes?.length > 0) {
            this.taxes = this.items[0].event.taxes.filter(t => t.is_active !== false);
        } else {
            this.taxes = [];
        }
    },

    prefillBuyerInfo() {
        const user = typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth.getUser() : null;
        const loginBtn = document.getElementById('guest-login-btn');
        const createAccountRow = document.getElementById('create-account-row');

        if (user) {
            this.fillBuyer(user);
            if (loginBtn) loginBtn.classList.add('hidden');
            if (createAccountRow) createAccountRow.classList.add('hidden');
        } else {
            if (loginBtn) loginBtn.classList.remove('hidden');
            if (createAccountRow) createAccountRow.classList.remove('hidden');
        }

        // Email confirmation: check on blur, and clear the error as soon as the addresses match again
        const email = document.getElementById('buyer-email');
        const emailConfirm = document.getElementById('buyer-email-confirm');
        emailConfirm.addEventListener('blur', () => this.validateEmailMatch());
        email.addEventListener('blur', () => this.validateEmailMatch());
        [email, emailConfirm].forEach((input) => input.addEventListener('input', () => {
            if (emailConfirm.classList.contains('is-invalid')) this.validateEmailMatch();
        }));
    },

    fillBuyer(user) {
        document.getElementById('buyer-first-name').value = user.first_name || '';
        document.getElementById('buyer-last-name').value = user.last_name || user.name || '';
        document.getElementById('buyer-email').value = user.email || '';
        document.getElementById('buyer-email-confirm').value = user.email || '';
        document.getElementById('buyer-phone').value = user.phone || '';
    },

    firstEmptyBuyerField() {
        return ['buyer-last-name', 'buyer-first-name', 'buyer-email', 'buyer-email-confirm', 'buyer-phone']
            .map(id => document.getElementById(id))
            .find(el => el && !el.value.trim()) || null;
    },

    showLoginModal() {
        const modal = document.getElementById('login-modal');
        if (!modal) return;
        this.modalOpener = document.activeElement;
        modal.classList.remove('hidden');
        document.documentElement.classList.add('ck-lock');
        document.getElementById('login-email').focus();

        if (!this._modalBound) {
            this._modalBound = true;
            // Dialog behaviour: Esc and a click on the backdrop close it, Tab stays inside it
            modal.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this.hideLoginModal();
                    return;
                }
                if (e.key !== 'Tab') return;
                const focusable = [...modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])')].filter(el => el.offsetParent !== null);
                if (!focusable.length) return;
                const first = focusable[0], last = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            });
            modal.addEventListener('click', (e) => {
                if (e.target === modal) this.hideLoginModal();
            });
        }
    },

    hideLoginModal() {
        const modal = document.getElementById('login-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        document.documentElement.classList.remove('ck-lock');
        const opener = this.modalOpener;
        this.modalOpener = null;
        if (opener && document.contains(opener) && opener.offsetParent !== null) opener.focus();
    },

    async handleLogin(event) {
        event.preventDefault();

        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const submitBtn = document.getElementById('login-submit-btn');
        const btnText = document.getElementById('login-btn-text');

        submitBtn.disabled = true;
        btnText.innerHTML = '<span class="spin" aria-hidden="true"></span>Se conectează...';

        try {
            const result = await BileteOnlineAuth.login(email, password, true);
            if (result.success) {
                this.notify('success', 'Conectare reușită!');
                this.hideLoginModal();

                const user = BileteOnlineAuth.getUser();
                if (user) this.fillBuyer(user);
                await this.loadPointsBalance();
                this.renderSummary();

                const loginBtn = document.getElementById('guest-login-btn');
                if (loginBtn) loginBtn.classList.add('hidden');
                const createAccountRow = document.getElementById('create-account-row');
                if (createAccountRow) createAccountRow.classList.add('hidden');

                // The login button is gone: continue from the first field still empty, or the terms
                this.focusField(this.firstEmptyBuyerField() || document.getElementById('termsCheckbox'));
            } else {
                this.notify('error', result.message || 'Email sau parola incorectă');
            }
        } catch (error) {
            this.notify('error', 'Eroare la conectare. Încearcă din nou.');
        } finally {
            submitBtn.disabled = false;
            btnText.textContent = 'Conectează-te';
        }

        return false;
    },

    validateEmailMatch() {
        const email = document.getElementById('buyer-email').value.trim();
        const emailConfirm = document.getElementById('buyer-email-confirm').value.trim();
        const errorEl = document.getElementById('email-mismatch-error');
        const confirmInput = document.getElementById('buyer-email-confirm');

        if (emailConfirm && email !== emailConfirm) {
            errorEl.classList.remove('hidden');
            confirmInput.classList.add('is-invalid');
            confirmInput.setAttribute('aria-invalid', 'true');
            return false;
        }
        errorEl.classList.add('hidden');
        confirmInput.classList.remove('is-invalid');
        confirmInput.removeAttribute('aria-invalid');
        return true;
    },

    renderBeneficiaries() {
        const container = document.getElementById('beneficiariesList');
        let html = '';
        let ticketNum = 0;

        this.items.forEach((item, itemIndex) => {
            const qty = this.itemQuantity(item);
            const ticketTypeName = this.esc(this.itemName(item));
            const eventTitle = this.esc(this.itemTitle(item));
            for (let i = 0; i < qty; i++) {
                ticketNum++;
                const id = `bene-${itemIndex}-${i}`;
                html += `
                    <fieldset class="ck-bene-card">
                        <legend class="sr">Beneficiar bilet ${ticketNum}</legend>
                        <div class="ck-bene-head">
                            <span class="ck-bene-num" aria-hidden="true">${ticketNum}</span>
                            <div><b>${ticketTypeName}</b><small>${eventTitle}</small></div>
                        </div>
                        <div class="ck-grid">
                            <div class="ck-field">
                                <label for="${id}-name">Nume beneficiar *</label>
                                <input type="text" id="${id}-name" placeholder="Nume complet" autocomplete="off" class="beneficiary-input beneficiary-name" data-item="${itemIndex}" data-index="${i}">
                            </div>
                            <div class="ck-field">
                                <label for="${id}-email">Email beneficiar *</label>
                                <input type="email" id="${id}-email" placeholder="email@exemplu.com" autocomplete="off" inputmode="email" class="beneficiary-input beneficiary-email" data-item="${itemIndex}" data-index="${i}">
                            </div>
                        </div>
                    </fieldset>
                `;
            }
        });

        container.innerHTML = html;
        document.getElementById('beneficiaries-count').textContent = `${ticketNum} ${this.ticketsWord(ticketNum)}`;
    },

    toggleBeneficiaries() {
        const checkbox = document.getElementById('differentBeneficiaries');
        const beneficiariesList = document.getElementById('beneficiariesList');
        const allTicketsToEmail = document.getElementById('allTicketsToEmail');

        if (checkbox.checked) {
            beneficiariesList.classList.remove('hidden');
            allTicketsToEmail.classList.add('hidden');
        } else {
            // Use buyer data for all
            beneficiariesList.classList.add('hidden');
            allTicketsToEmail.classList.remove('hidden');
        }
    },

    // ==================== SUMMARY ====================

    renderSummary() {
        const eventGroups = {};
        let baseSubtotal = 0;
        let totalCommission = 0;
        let savings = 0;
        let totalQty = 0;
        let hasAddedOnTopCommission = false;

        this.items.forEach(item => {
            // Activities use a different shape than event tickets (activity + variant + participants_count)
            const isActivity = item.type === 'activity';

            const eventId    = isActivity
                ? ('activity-' + (item.activity?.id || item.activity_id || 'unknown'))
                : (item.eventId || item.event?.id || 'unknown');
            const eventTitle = this.itemTitle(item);
            const eventImage = isActivity
                ? (item.activity?.image || '')
                : (item.event?.image || item.event_image || '');
            const eventDate  = isActivity
                ? (item.booking_date || '')
                : (item.event?.performance_date || item.event?.date || item.event_date || '');
            const eventTime  = isActivity
                ? (item.slot_start_time || '').substring(0, 5)
                : (item.event?.performance_time || item.event?.time || '').substring(0, 5);
            const venueName  = isActivity
                ? (item.activity?.venue || '')
                : (item.event?.venue?.name || (typeof item.event?.venue === 'string' ? item.event.venue : '') || item.venue_name || '');
            const cityName   = isActivity
                ? (item.activity?.city || '')
                : (item.event?.city?.name || (typeof item.event?.city === 'string' ? item.event.city : '') || item.event?.venue?.city || '');

            if (!eventGroups[eventId]) {
                eventGroups[eventId] = {
                    title: eventTitle,
                    image: eventImage,
                    date: eventDate,
                    time: eventTime,
                    venue: venueName,
                    city: cityName,
                    tickets: [],
                    subtotal: 0,
                    commission: 0,
                    isActivity: isActivity
                };
            }

            const price = isActivity
                ? ((typeof item.variant?.price === 'number' ? item.variant.price : item.price) || 0)
                : (item.ticketType?.price || item.price || 0);
            const originalPrice = isActivity
                ? (item.variant?.originalPrice || item.original_price || 0)
                : (item.ticketType?.originalPrice || item.original_price || 0);
            const qty = this.itemQuantity(item);

            // Per-ticket commission from the cart helper
            const commission = BileteOnlineCart.calculateItemCommission(item);
            let itemCommission = 0;
            if (commission.mode === 'added_on_top') {
                itemCommission = commission.amount;
                hasAddedOnTopCommission = true;
            }

            const itemTotal = price * qty;
            const commissionTotal = itemCommission * qty;

            baseSubtotal += itemTotal;
            totalCommission += commissionTotal;
            totalQty += qty;
            eventGroups[eventId].subtotal += itemTotal;
            eventGroups[eventId].commission += commissionTotal;

            const hasDiscount = originalPrice && originalPrice > price;
            if (hasDiscount) {
                savings += (originalPrice - price) * qty;
            }

            eventGroups[eventId].tickets.push({
                name: this.itemName(item),
                qty: qty,
                price: price,
                lineTotal: itemTotal,
                hasDiscount: hasDiscount,
                originalPrice: originalPrice,
                visitDate: item.meta?.visit_date || item.event?.visit_date || null,
                vehicleInfo: item.meta?.vehicle_info || null,
                isParking: item.ticketType?.is_parking || false,
                requiresVehicleInfo: item.ticketType?.requires_vehicle_info || false,
            });
        });

        const eventIds = Object.keys(eventGroups);
        const hasMultipleEvents = eventIds.length > 1;

        // Event info - only for a single event or activity
        const eventInfo = document.getElementById('event-info');
        if (hasMultipleEvents) {
            eventInfo.style.display = 'none';
        } else {
            eventInfo.style.display = '';
            const group = eventGroups[eventIds[0]];
            const img = this.storageUrl(group.image);
            const when = [this.formatDay(group.date, 'medium'), group.time].filter(Boolean).join(' · ');
            const where = [group.venue, group.city].filter(Boolean).join(', ');
            eventInfo.innerHTML =
                '<span class="ck-event-media" aria-hidden="true">' + this.fallback(group.title) +
                    (img ? '<img src="' + this.esc(img) + '" alt="" loading="lazy" onerror="this.remove()">' : '') +
                '</span>' +
                '<div>' +
                    '<h3>' + this.esc(group.title) + '</h3>' +
                    (when ? '<p>' + this.icon('calendar-blank') + '<span>' + this.esc(when) + '</span></p>' : '') +
                    (where ? '<p>' + this.icon('map-pin') + '<span>' + this.esc(where) + '</span></p>' : '') +
                '</div>';
        }

        // Items summary - grouped by event
        const itemsSummary = document.getElementById('items-summary');
        let itemsHtml = '';

        eventIds.forEach((eventId, eventIndex) => {
            const group = eventGroups[eventId];

            if (hasMultipleEvents) {
                if (eventIndex > 0) {
                    itemsHtml += '<hr class="cs-sep">';
                }
                const eventDetails = [];
                if (group.date) eventDetails.push(this.formatDay(group.date, 'short'));
                if (group.venue) eventDetails.push(group.venue);
                if (group.city && group.city !== group.venue) eventDetails.push(group.city);
                itemsHtml += '<p class="cs-group">' + this.esc(group.title) +
                    (eventDetails.length > 0 ? ' <span>(' + this.esc(eventDetails.join(', ')) + ')</span>' : '') + '</p>';
            }

            group.tickets.forEach(ticket => {
                const visitDateHtml = ticket.visitDate ? '<small>Data vizită: ' + this.esc(this.formatDay(ticket.visitDate, 'medium')) + '</small>' : '';
                const vehiclePlates = ticket.vehicleInfo?.license_plates?.filter(p => p)?.join(', ') || '';
                const vehicleHtml = vehiclePlates ? '<small>Nr. înmatriculare: ' + this.esc(vehiclePlates) + '</small>' : '';
                itemsHtml += '<div class="cs-line ck-line">' +
                    '<span>' + ticket.qty + ' × ' + this.esc(ticket.name) + visitDateHtml + vehicleHtml + '</span>' +
                    '<strong>' + (ticket.hasDiscount ? '<s>' + this.money(ticket.originalPrice * ticket.qty) + '</s> ' : '') + this.money(ticket.lineTotal) + '</strong>' +
                '</div>';
            });
        });

        itemsSummary.innerHTML = itemsHtml;

        // Insurance if selected
        let insuranceAmount = 0;
        if (this.insuranceSelected && this.insurance) {
            insuranceAmount = this.calculateInsuranceAmount();
        }

        // Promo code discount
        const promoDiscount = BileteOnlineCart.getPromoDiscount();

        // Loyalty points: capped on the ticket value after the promo code
        const ticketValue = Math.max(0, baseSubtotal - promoDiscount);
        const pointsState = this.pointsState(ticketValue);
        const pointsDiscount = BileteOnlineCart.pointsValue(pointsState.used);

        // Total = base prices + commission + insurance - discount - points
        const subtotalWithCommission = baseSubtotal + totalCommission;
        const baseTotal = Math.max(0, subtotalWithCommission + insuranceAmount - promoDiscount - pointsDiscount);

        // Cultural card surcharge (applied on entire total including insurance)
        const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'card';
        let culturalCardSurcharge = 0;
        if (paymentMethod === 'card_cultural') {
            culturalCardSurcharge = Math.round(baseTotal * (this.culturalCardSurchargeRate / 100) * 100) / 100;
        }

        // Payment processing fee preview. Mirrors the server-side ProcessingFeeCalculator: applied AFTER all other
        // adjustments so the customer sees the exact amount the processor will pull. Skipped for the cultural card,
        // which has its own surcharge above.
        let processingFee = { amount: 0, percent_rate: 0, fixed: 0, provider: null, label: '', pass_to_customer: false };
        try {
            if (paymentMethod !== 'card_cultural'
                && typeof BileteOnlineCart !== 'undefined'
                && typeof BileteOnlineCart.computeProcessingFee === 'function') {
                processingFee = BileteOnlineCart.computeProcessingFee(baseTotal + culturalCardSurcharge);
                // Lazy-load config the first time + render again once it is available
                if (!BileteOnlineCart.getPaymentFeeConfig() && BileteOnlineCart.loadPaymentFeeConfig) {
                    if (!this._feeConfigReloadScheduled) {
                        this._feeConfigReloadScheduled = true;
                        BileteOnlineCart.loadPaymentFeeConfig().then(cfg => {
                            if (cfg) this.renderSummary();
                        });
                    }
                }
            }
        } catch (e) {}

        const total = baseTotal + culturalCardSurcharge + processingFee.amount;

        this.totals = {
            subtotal: subtotalWithCommission,
            tax: 0,
            discount: promoDiscount,
            insurance: insuranceAmount,
            culturalCardSurcharge,
            processingFee: processingFee.amount,
            pointsUsed: pointsState.used,
            pointsDiscount,
            total,
            savings,
        };

        // Subtotal shows base prices only; the platform commission has its own row
        document.getElementById('summary-items').textContent = totalQty;
        document.querySelectorAll('[data-items-word]').forEach((el) => { el.textContent = this.ticketsWord(totalQty); });
        document.getElementById('summary-subtotal').textContent = this.money(baseSubtotal);

        const commRow = document.getElementById('platform-commission-row');
        if (commRow) {
            if (hasAddedOnTopCommission && totalCommission > 0) {
                commRow.classList.remove('hidden');
                document.getElementById('platform-commission-amount').textContent = this.money(totalCommission);
                const lbl = document.getElementById('platform-commission-label');
                if (lbl) {
                    const ratePct = baseSubtotal > 0
                        ? (totalCommission / baseSubtotal * 100).toFixed(1).replace(/\.0$/, '').replace('.', ',')
                        : '';
                    lbl.textContent = 'Comision ticketing' + (ratePct ? ' (' + ratePct + '%)' : '');
                }
            } else {
                commRow.classList.add('hidden');
            }
        }

        // Legacy taxes container stays empty — commission has its own row now
        const taxesContainer = document.getElementById('taxes-container');
        if (taxesContainer) {
            taxesContainer.innerHTML = '';
        }

        const insuranceRow = document.getElementById('insurance-row');
        if (insuranceRow) {
            if (this.insuranceSelected && insuranceAmount > 0) {
                insuranceRow.classList.remove('hidden');
                document.getElementById('insurance-row-amount').textContent = '+' + this.money(insuranceAmount);
            } else {
                insuranceRow.classList.add('hidden');
            }
        }

        const culturalCardRow = document.getElementById('cultural-card-row');
        if (culturalCardRow) {
            if (culturalCardSurcharge > 0) {
                culturalCardRow.classList.remove('hidden');
                document.getElementById('cultural-card-amount').textContent = '+' + this.money(culturalCardSurcharge);
            } else {
                culturalCardRow.classList.add('hidden');
            }
        }

        const discountRow = document.getElementById('discount-row');
        if (discountRow) {
            if (promoDiscount > 0) {
                const promo = BileteOnlineCart.getPromoCode();
                discountRow.classList.remove('hidden');
                document.getElementById('discount-label').textContent = 'Reducere' + (promo ? ' (' + promo.code + ')' : '');
                document.getElementById('discount-amount').textContent = '-' + this.money(promoDiscount);
            } else {
                discountRow.classList.add('hidden');
            }
        }

        // Processing fee row. Label stays simple — the rate is in the merchant agreement, not shown to the customer.
        const feeRow = document.getElementById('processing-fee-row');
        if (feeRow) {
            if (processingFee.amount > 0) {
                feeRow.classList.remove('hidden');
                document.getElementById('processing-fee-amount').textContent = this.money(processingFee.amount);
            } else {
                feeRow.classList.add('hidden');
            }
        }

        document.getElementById('summary-total').textContent = this.money(total);
        if (!this.submitting) {
            document.getElementById('pay-btn-text').textContent = `Plătește ${this.money(total)}`;
        }
        this.renderPoints(pointsState, Math.max(0, ticketValue - pointsDiscount));

        const savingsText = document.getElementById('savings-text');
        if (savings > 0) {
            savingsText.classList.remove('hidden');
            document.getElementById('savings-amount').textContent = `Economisești ${this.money(savings)}!`;
        } else {
            savingsText.classList.add('hidden');
        }
    },

    // ==================== SUBMIT ====================

    validateForm() {
        const emptyField = this.firstEmptyBuyerField();
        if (emptyField) {
            this.notify('error', 'Completează toate câmpurile obligatorii');
            this.focusField(emptyField);
            return false;
        }

        const emailInput = document.getElementById('buyer-email');
        if (!emailInput.checkValidity()) {
            this.notify('error', 'Adresa de email nu este validă');
            this.focusField(emailInput);
            return false;
        }

        if (!this.validateEmailMatch()) {
            this.notify('error', 'Adresele de email nu coincid');
            this.focusField(document.getElementById('buyer-email-confirm'));
            return false;
        }

        const terms = document.getElementById('termsCheckbox');
        if (!terms.checked) {
            this.notify('error', 'Trebuie să accepți termenii și condițiile');
            this.focusField(terms);
            return false;
        }

        return true;
    },

    getBeneficiaries() {
        const beneficiaries = [];
        const useDifferentBeneficiaries = document.getElementById('differentBeneficiaries').checked;
        const buyerFirstName = document.getElementById('buyer-first-name').value.trim();
        const buyerLastName = document.getElementById('buyer-last-name').value.trim();
        const buyerName = `${buyerLastName} ${buyerFirstName}`.trim();
        const buyerEmail = document.getElementById('buyer-email').value.trim();

        // One entry per ticket, in the order the backend creates them (activity lines: one per participant)
        this.items.forEach((item, itemIndex) => {
            const qty = this.itemQuantity(item);
            for (let i = 0; i < qty; i++) {
                if (useDifferentBeneficiaries) {
                    const nameInput = document.querySelector(`.beneficiary-name[data-item="${itemIndex}"][data-index="${i}"]`);
                    const emailInput = document.querySelector(`.beneficiary-email[data-item="${itemIndex}"][data-index="${i}"]`);
                    beneficiaries.push({
                        name: nameInput?.value.trim() || buyerName,
                        email: emailInput?.value.trim() || buyerEmail,
                        item_index: itemIndex,
                        ticket_index: i
                    });
                } else {
                    beneficiaries.push({
                        name: buyerName,
                        email: buyerEmail,
                        item_index: itemIndex,
                        ticket_index: i
                    });
                }
            }
        });

        return beneficiaries;
    },

    async submit() {
        if (this.submitting) return;
        if (!this.validateForm()) return;
        this.submitting = true;

        const payBtn = document.getElementById('payBtn');
        const payBtnText = document.getElementById('pay-btn-text');

        payBtn.disabled = true;
        payBtnText.innerHTML = '<span class="spin" aria-hidden="true"></span>Se procesează...';

        // Build customer data (backend expects 'customer' not 'buyer')
        const customer = {
            first_name: document.getElementById('buyer-first-name').value.trim(),
            last_name: document.getElementById('buyer-last-name').value.trim(),
            email: document.getElementById('buyer-email').value.trim(),
            phone: document.getElementById('buyer-phone').value.trim()
        };

        // Auto-create account if checkbox is checked (guest only)
        const createAccountCheckbox = document.getElementById('createAccountCheckbox');
        if (createAccountCheckbox && createAccountCheckbox.checked) {
            // Generate a random 12-char password
            const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!@#$';
            let password = '';
            for (let i = 0; i < 12; i++) password += chars.charAt(Math.floor(Math.random() * chars.length));
            customer.password = password;
        }

        const beneficiaries = this.getBeneficiaries();
        const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'card';
        const newsletter = document.getElementById('newsletterCheckbox').checked;
        const acceptTerms = document.getElementById('termsCheckbox').checked;

        try {
            // Step 1: Create order via checkout
            const checkoutData = {
                customer,
                beneficiaries,
                items: this.items,
                payment_method: paymentMethod,
                newsletter,
                accept_terms: acceptTerms
            };

            const promo = BileteOnlineCart.getPromoCode();
            if (promo && promo.code) {
                checkoutData.promo_code = promo.code;
            }

            // Loyalty points to pay with; the server checks them again against the account and its balance
            if (this.totals.pointsUsed > 0) {
                checkoutData.points_to_use = this.totals.pointsUsed;
            }
            // A friend's invite link (?ref=, kept by base.js): a first order through it earns both of them points
            try {
                const ref = localStorage.getItem('bileteonline_referral_code');
                if (ref) checkoutData.referral_code = String(ref).slice(0, 20);
            } catch (e) {}

            if (this.insuranceSelected && this.totals.insurance > 0) {
                checkoutData.ticket_insurance = true;
                checkoutData.ticket_insurance_amount = this.totals.insurance;
            }

            if (paymentMethod === 'card_cultural' && this.totals.culturalCardSurcharge > 0) {
                checkoutData.cultural_card_surcharge = this.totals.culturalCardSurcharge;
            }

            // Add preview token if any cart item has one (test order mode)
            const previewToken = this.items.find(i => i.event?.preview_token)?.event?.preview_token;
            if (previewToken) {
                checkoutData.preview_token = previewToken;
            }

            const response = await BileteOnlineAPI.post('/checkout', checkoutData);

            if (!response.success) {
                throw new Error(response.message || 'Eroare la procesarea comenzii');
            }

            const order = response.data.orders?.[0];
            if (!order) {
                throw new Error('Nu s-a putut crea comanda');
            }

            const thankYouUrl = window.location.origin + '/multumim?order=' + encodeURIComponent(order.order_number);

            // Step 2: Check if payment is required
            if (response.data.payment_required && order.total > 0) {
                payBtnText.innerHTML = '<span class="spin" aria-hidden="true"></span>Se redirecționează către plată...';

                const payResponse = await BileteOnlineAPI.post(`/orders/${order.id}/pay`, {
                    return_url: thankYouUrl,
                    // Back to this page if the customer cancels at the processor (/checkout has no route here)
                    cancel_url: window.location.origin + '/finalizare'
                });

                if (payResponse.success && payResponse.data.payment_url) {
                    BileteOnlineCart.clear({ skipRelease: true });
                    localStorage.removeItem('cart_end_time');

                    // Payment that needs a POST form submission (e.g., Netopia)
                    if (payResponse.data.method === 'POST' && payResponse.data.form_data) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = payResponse.data.payment_url;

                        for (const [key, value] of Object.entries(payResponse.data.form_data)) {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = key;
                            input.value = value;
                            form.appendChild(input);
                        }

                        document.body.appendChild(form);
                        form.submit();
                    } else {
                        // Standard redirect for other processors
                        window.location.href = payResponse.data.payment_url;
                    }
                } else {
                    throw new Error(payResponse.message || 'Nu s-a putut iniția plata');
                }
            } else {
                // No payment required (free tickets or zero total)
                BileteOnlineCart.clear({ skipRelease: true });
                localStorage.removeItem('cart_end_time');
                window.location.href = thankYouUrl;
            }
        } catch (error) {
            console.error('Checkout error:', error);
            // Points no longer valid (balance changed, other email…): drop them, show the fresh total
            if (error && error.data && error.data.errors && error.data.errors.code === 'points_invalid') {
                this.usePoints = false;
                await this.loadPointsBalance();
                this.renderSummary();
            }
            this.notify('error', error.message || 'Eroare la procesare. Încearcă din nou.');
            this.submitting = false;
            payBtn.disabled = !document.getElementById('termsCheckbox').checked;
            payBtnText.textContent = `Plătește ${this.money(this.totals.total)}`;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => CheckoutPage.init());
