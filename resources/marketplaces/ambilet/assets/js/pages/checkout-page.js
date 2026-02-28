const CheckoutPage = {
    items: [],
    taxes: [],
    insurance: null,
    insuranceSelected: false,
    culturalCardSurchargeRate: 4, // % extra for cultural card transactions
    totals: { subtotal: 0, tax: 0, discount: 0, insurance: 0, culturalCardSurcharge: 0, total: 0, savings: 0 },
    timerInterval: null,
    endTime: null,

    async init() {
        this.items = AmbiletCart.getItems();
        this.loadTaxes();

        if (this.items.length === 0) {
            document.getElementById('checkout-loading').classList.add('hidden');
            document.getElementById('empty-cart').classList.remove('hidden');
            return;
        }

        // Load checkout features (insurance, etc.)
        await this.loadCheckoutFeatures();

        this.setupTimer();
        this.setupPaymentOptions();
        this.setupTermsCheckbox();
        this.setupInsuranceCheckbox();
        this.prefillBuyerInfo();
        this.renderBeneficiaries();
        this.renderSummary();

        document.getElementById('checkout-loading').classList.add('hidden');
        document.getElementById('checkout-form').classList.remove('hidden');
        document.getElementById('summary-section').classList.remove('hidden');
    },

    async loadCheckoutFeatures() {
        try {
            const response = await AmbiletAPI.get('/checkout.features');
            if (response.success && response.data) {
                // Handle ticket insurance
                if (response.data.ticket_insurance && response.data.ticket_insurance.enabled && response.data.ticket_insurance.show_in_checkout) {
                    this.insurance = response.data.ticket_insurance;

                    // Determine eligible items based on apply_to setting
                    const applyTo = this.insurance.apply_to || 'all';
                    let eligibleItems, ineligibleItems;

                    if (applyTo === 'refundable_only') {
                        // Only refundable tickets qualify
                        eligibleItems = this.items.filter(item => item.ticketType?.is_refundable);
                        ineligibleItems = this.items.filter(item => !item.ticketType?.is_refundable);
                    } else {
                        // All tickets qualify
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

                    // Store eligibility info for calculation
                    this.insurance._refundableItems = eligibleItems;
                    this.insurance._isMixed = isMixed;

                    this.setupInsuranceUI();
                }
            }
        } catch (error) {
            console.log('Could not load checkout features:', error);
        }
    },

    setupInsuranceUI() {
        if (!this.insurance) return;

        const section = document.getElementById('insurance-section');
        if (!section) return;

        // Show the insurance section
        section.classList.remove('hidden');

        // Update labels and content
        document.getElementById('insurance-label').textContent = this.insurance.label || 'Taxa de retur';
        document.getElementById('insurance-title').textContent = this.insurance.label || 'Protecție returnare bilete';
        document.getElementById('insurance-description').textContent = this.insurance.description || '';

        // Insurance always applies only to refundable tickets
        const isMixed = this.insurance._isMixed;
        const refundableItems = this.insurance._refundableItems || [];

        // Calculate applicable tickets count (only refundable)
        const applicableTickets = refundableItems.reduce((sum, item) => sum + (item.quantity || 1), 0);

        const insuranceAmount = this.calculateInsuranceAmount();

        // Show price per ticket info
        if (this.insurance.price_type === 'fixed') {
            const pricePerTicket = this.insurance.price || 0;
            document.getElementById('insurance-price').textContent = AmbiletUtils.formatCurrency(pricePerTicket) + '/bilet';
        } else {
            document.getElementById('insurance-price').textContent = this.insurance.price_percentage + '% din total';
        }

        // Show partial note for mixed carts (some eligible, some not)
        const partialNote = document.getElementById('insurance-partial-note');
        if (partialNote && isMixed) {
            const eligibleNames = refundableItems.map(item => item.ticketType?.name || 'Bilet').join(', ');
            partialNote.textContent = 'Se aplică doar pentru biletele returnabile: ' + eligibleNames;
            partialNote.classList.remove('hidden');
        }

        // Show terms link if available
        if (this.insurance.terms_url) {
            const termsLink = document.getElementById('insurance-terms-link');
            termsLink.href = this.insurance.terms_url;
            termsLink.classList.remove('hidden');
        }

        // Pre-check if configured
        const checkbox = document.getElementById('insuranceCheckbox');
        if (this.insurance.pre_checked) {
            checkbox.checked = true;
            this.insuranceSelected = true;
        }

        // Update row label in summary to show ticket count
        document.getElementById('insurance-row-label').textContent = (this.insurance.label || 'Taxa de retur') + ' (' + applicableTickets + ' bilete)';
    },

    setupInsuranceCheckbox() {
        const checkbox = document.getElementById('insuranceCheckbox');
        if (!checkbox) return;

        checkbox.addEventListener('change', () => {
            this.insuranceSelected = checkbox.checked;

            // Update option styling
            const option = document.getElementById('insurance-option');
            if (option) {
                if (this.insuranceSelected) {
                    option.classList.add('border-success', 'bg-success/5');
                    option.classList.remove('border-border');
                } else {
                    option.classList.remove('border-success', 'bg-success/5');
                    option.classList.add('border-border');
                }
            }

            this.renderSummary();
        });

        // Trigger initial state if pre-checked
        if (this.insuranceSelected) {
            const option = document.getElementById('insurance-option');
            if (option) {
                option.classList.add('border-success', 'bg-success/5');
                option.classList.remove('border-border');
            }
        }
    },

    calculateInsuranceAmount() {
        if (!this.insurance) return 0;

        // Insurance always applies only to refundable tickets
        const applicableItems = this.insurance._refundableItems || [];

        if (applicableItems.length === 0) return 0;

        // Calculate total number of applicable tickets
        const applicableTickets = applicableItems.reduce((sum, item) => sum + (item.quantity || 1), 0);

        if (this.insurance.price_type === 'percentage') {
            // Calculate based on subtotal of applicable items only
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
        if (!countdownEl) return;

        countdownEl.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        if (remaining <= 0) {
            clearInterval(this.timerInterval);
            countdownEl.textContent = '00:00';
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');
            AmbiletCart.clear();
            localStorage.removeItem('cart_end_time');
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.warning('Timpul de rezervare a expirat. Biletele au fost eliberate.');
            }
            // Redirect to cart page after short delay
            setTimeout(() => {
                window.location.href = '/cos';
            }, 2000);
        } else if (remaining < 60000) {
            // Less than 1 minute - make it red
            countdownEl.classList.remove('text-warning');
            countdownEl.classList.add('text-primary');
        }
    },

    setupPaymentOptions() {
        document.querySelectorAll('.payment-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.payment-option').forEach(o => o.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;

                const value = this.querySelector('input').value;
                const cardForm = document.getElementById('cardForm');
                const culturalCardForm = document.getElementById('culturalCardForm');
                cardForm.style.display = value === 'card' ? 'block' : 'none';
                culturalCardForm.style.display = value === 'card_cultural' ? 'block' : 'none';

                // Re-render summary to update cultural card surcharge
                CheckoutPage.renderSummary();
            });
        });
    },

    setupTermsCheckbox() {
        document.getElementById('termsCheckbox').addEventListener('change', function() {
            document.getElementById('payBtn').disabled = !this.checked;
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
        const user = typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getUser() : null;
        const loginBtn = document.getElementById('guest-login-btn');
        const createAccountRow = document.getElementById('create-account-row');

        if (user) {
            // User is logged in - prefill fields
            document.getElementById('buyer-first-name').value = user.first_name || '';
            document.getElementById('buyer-last-name').value = user.last_name || user.name || '';
            document.getElementById('buyer-email').value = user.email || '';
            document.getElementById('buyer-email-confirm').value = user.email || '';
            document.getElementById('buyer-phone').value = user.phone || '';
            // Hide login button and create account checkbox
            if (loginBtn) loginBtn.classList.add('hidden');
            loginBtn?.classList.remove('flex');
            if (createAccountRow) createAccountRow.classList.add('hidden');
        } else {
            // Guest - show login button and create account checkbox
            if (loginBtn) {
                loginBtn.classList.remove('hidden');
                loginBtn.classList.add('flex');
            }
            if (createAccountRow) createAccountRow.classList.remove('hidden');
        }

        // Add email confirmation validation on blur
        const emailConfirm = document.getElementById('buyer-email-confirm');
        emailConfirm.addEventListener('blur', () => this.validateEmailMatch());
        document.getElementById('buyer-email').addEventListener('blur', () => this.validateEmailMatch());
    },

    showLoginModal() {
        const modal = document.getElementById('login-modal');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('login-email').focus();
        }
    },

    hideLoginModal() {
        const modal = document.getElementById('login-modal');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    },

    async handleLogin(event) {
        event.preventDefault();

        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;
        const submitBtn = document.getElementById('login-submit-btn');
        const btnText = document.getElementById('login-btn-text');

        // Disable button and show loading
        submitBtn.disabled = true;
        btnText.innerHTML = '<svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Se conectează...';

        try {
            const result = await AmbiletAuth.login(email, password, true);
            if (result.success) {
                AmbiletNotifications.success('Conectare reușită!');
                this.hideLoginModal();

                // Prefill buyer info with new user data
                const user = AmbiletAuth.getUser();
                if (user) {
                    document.getElementById('buyer-first-name').value = user.first_name || '';
                    document.getElementById('buyer-last-name').value = user.last_name || user.name || '';
                    document.getElementById('buyer-email').value = user.email || '';
                    document.getElementById('buyer-email-confirm').value = user.email || '';
                    document.getElementById('buyer-phone').value = user.phone || '';
                }

                // Hide login button and create account checkbox
                const loginBtn = document.getElementById('guest-login-btn');
                if (loginBtn) {
                    loginBtn.classList.add('hidden');
                    loginBtn.classList.remove('flex');
                }
                const createAccountRow = document.getElementById('create-account-row');
                if (createAccountRow) createAccountRow.classList.add('hidden');
            } else {
                AmbiletNotifications.error(result.message || 'Email sau parola incorectă');
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la conectare. Încearcă din nou.');
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
            confirmInput.classList.add('border-primary');
            return false;
        } else {
            errorEl.classList.add('hidden');
            confirmInput.classList.remove('border-primary');
            return true;
        }
    },

    renderBeneficiaries() {
        const container = document.getElementById('beneficiariesList');
        let html = '';
        let ticketNum = 0;

        this.items.forEach((item, itemIndex) => {
            const qty = item.quantity || 1;
            for (let i = 0; i < qty; i++) {
                ticketNum++;
                // Handle both AmbiletCart format and legacy format
                const price = item.ticketType?.price || item.price || 0;
                const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
                const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
                const eventTitle = item.event?.title || item.event_title || 'Eveniment';
                const hasDiscount = originalPrice && originalPrice > price;
                const discountPercent = hasDiscount ? Math.round((1 - price / originalPrice) * 100) : 0;

                html += `
                    <div class="p-4 border-2 beneficiary-card border-border rounded-xl">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-10 h-10 font-bold text-white rounded-lg date-badge">${ticketNum}</div>
                                <div>
                                    <p class="font-semibold text-secondary">${ticketTypeName}</p>
                                    <p class="text-xs text-muted">${eventTitle}</p>
                                </div>
                            </div>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Nume beneficiar *</label>
                                <input type="text" placeholder="Nume complet" class="w-full px-4 py-3 border-2 beneficiary-input beneficiary-name input-field border-border rounded-xl focus:outline-none" data-item="${itemIndex}" data-index="${i}">
                            </div>
                            <div>
                                <label class="block mb-2 text-sm font-medium text-secondary">Email beneficiar *</label>
                                <input type="email" placeholder="email@exemplu.com" class="w-full px-4 py-3 border-2 beneficiary-input beneficiary-email input-field border-border rounded-xl focus:outline-none" data-item="${itemIndex}" data-index="${i}">
                            </div>
                        </div>
                    </div>
                `;
            }
        });

        container.innerHTML = html;
        document.getElementById('beneficiaries-count').textContent = `${ticketNum} bilete`;
    },

    toggleBeneficiaries() {
        const checkbox = document.getElementById('differentBeneficiaries');
        const beneficiariesList = document.getElementById('beneficiariesList');
        const allTicketsToEmail = document.getElementById('allTicketsToEmail');

        if (checkbox.checked) {
            // Show beneficiaries form
            beneficiariesList.classList.remove('hidden');
            allTicketsToEmail.classList.add('hidden');
        } else {
            // Hide beneficiaries form - use buyer data for all
            beneficiariesList.classList.add('hidden');
            allTicketsToEmail.classList.remove('hidden');
        }
    },

    renderSummary() {
        // Group items by event
        const eventGroups = {};
        let baseSubtotal = 0;
        let totalCommission = 0;
        let savings = 0;
        let totalQty = 0;
        let hasAddedOnTopCommission = false;

        this.items.forEach(item => {
            const eventId = item.eventId || item.event?.id || 'unknown';
            const eventTitle = item.event?.title || item.event?.name || item.event_title || 'Eveniment';
            const eventImage = item.event?.image || item.event_image || '/assets/images/default-event.png';
            const eventDate = item.event?.date || item.event_date || '';
            const venueName = item.event?.venue?.name || (typeof item.event?.venue === 'string' ? item.event.venue : '') || item.venue_name || '';

            const cityName = item.event?.city?.name || item.event?.city || item.event?.venue?.city || '';

            if (!eventGroups[eventId]) {
                eventGroups[eventId] = {
                    title: eventTitle,
                    image: eventImage,
                    date: eventDate,
                    venue: venueName,
                    city: cityName,
                    tickets: [],
                    subtotal: 0,
                    commission: 0
                };
            }

            const price = item.ticketType?.price || item.price || 0;
            const originalPrice = item.ticketType?.originalPrice || item.original_price || 0;
            const ticketTypeName = item.ticketType?.name || item.ticket_type_name || 'Bilet';
            const qty = item.quantity || 1;

            // Calculate per-ticket commission using cart helper
            const commission = AmbiletCart.calculateItemCommission(item);
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
                name: ticketTypeName,
                qty: qty,
                price: price,
                lineTotal: itemTotal,
                hasDiscount: hasDiscount,
                originalPrice: originalPrice
            });
        });

        const eventIds = Object.keys(eventGroups);
        const hasMultipleEvents = eventIds.length > 1;

        // Event info - only show for single event
        const eventInfo = document.getElementById('event-info');
        if (hasMultipleEvents) {
            eventInfo.style.display = 'none';
        } else {
            eventInfo.style.display = '';
            const firstGroup = eventGroups[eventIds[0]];
            eventInfo.innerHTML = `
                <img src="${firstGroup.image}" alt="Event" class="object-cover w-20 h-20 rounded-xl" loading="lazy">
                <div>
                    <h3 class="font-bold text-secondary">${firstGroup.title}</h3>
                    <p class="text-sm text-muted">${firstGroup.date ? AmbiletUtils.formatDate(firstGroup.date) : ''}</p>
                    <p class="text-sm text-muted">${firstGroup.venue}</p>
                </div>
            `;
        }

        // Items summary - grouped by event
        const itemsSummary = document.getElementById('items-summary');
        let itemsHtml = '';

        eventIds.forEach((eventId, eventIndex) => {
            const group = eventGroups[eventId];

            // Show event title as header if multiple events
            if (hasMultipleEvents) {
                if (eventIndex > 0) {
                    itemsHtml += '<div class="pt-3 mt-3 border-t border-border"></div>';
                }
                // Build event info string: title (date, venue, city)
                let eventDetails = [];
                if (group.date) eventDetails.push(AmbiletUtils.formatDate(group.date, 'short'));
                if (group.venue) eventDetails.push(group.venue);
                const city = group.city || '';
                if (city && city !== group.venue) eventDetails.push(city);
                const detailsStr = eventDetails.length > 0 ? ` <span class="font-normal text-muted">(${eventDetails.join(', ')})</span>` : '';
                itemsHtml += `<div class="mb-2 text-sm font-bold text-secondary">${group.title}${detailsStr}</div>`;
            }

            // Show tickets for this event
            group.tickets.forEach(ticket => {
                itemsHtml += `
                    <div class="flex justify-between text-sm">
                        <span class="text-muted">${ticket.qty}x ${ticket.name}</span>
                        <div class="text-right">
                            ${ticket.hasDiscount ? `<span class="mr-2 text-xs line-through text-muted">${AmbiletUtils.formatCurrency(ticket.originalPrice * ticket.qty)}</span>` : ''}
                            <span class="font-medium">${AmbiletUtils.formatCurrency(ticket.lineTotal)}</span>
                        </div>
                    </div>
                `;
            });
        });

        itemsSummary.innerHTML = itemsHtml;

        // Calculate insurance if selected
        let insuranceAmount = 0;
        if (this.insuranceSelected && this.insurance) {
            insuranceAmount = this.calculateInsuranceAmount();
        }

        // Promo code discount
        const promoDiscount = AmbiletCart.getPromoDiscount();

        // Total = base prices + commission + insurance - discount
        const subtotalWithCommission = baseSubtotal + totalCommission;
        const baseTotal = Math.max(0, subtotalWithCommission + insuranceAmount - promoDiscount);

        // Cultural card surcharge (applied on entire total including insurance)
        const paymentMethod = document.querySelector('input[name="payment"]:checked')?.value || 'card';
        let culturalCardSurcharge = 0;
        if (paymentMethod === 'card_cultural') {
            culturalCardSurcharge = Math.round(baseTotal * (this.culturalCardSurchargeRate / 100) * 100) / 100;
        }

        const total = baseTotal + culturalCardSurcharge;
        const points = Math.floor(total / 10);

        this.totals = { subtotal: subtotalWithCommission, tax: 0, discount: promoDiscount, insurance: insuranceAmount, culturalCardSurcharge, total, savings };

        // Update DOM
        document.getElementById('summary-items').textContent = totalQty;
        document.getElementById('summary-subtotal').textContent = AmbiletUtils.formatCurrency(subtotalWithCommission);

        // Render commission as "Taxe procesare" in taxes container
        const taxesContainer = document.getElementById('taxes-container');
        if (taxesContainer) {
            if (hasAddedOnTopCommission && totalCommission > 0) {
                taxesContainer.innerHTML = '<div class="flex justify-between text-sm">' +
                    '<span class="text-muted">Taxe procesare</span>' +
                    '<span class="font-medium">' + AmbiletUtils.formatCurrency(totalCommission) + '</span>' +
                '</div>';
            } else {
                taxesContainer.innerHTML = '';
            }
        }

        // Show/hide insurance row
        const insuranceRow = document.getElementById('insurance-row');
        if (insuranceRow) {
            if (this.insuranceSelected && insuranceAmount > 0) {
                insuranceRow.classList.remove('hidden');
                document.getElementById('insurance-row-amount').textContent = '+' + AmbiletUtils.formatCurrency(insuranceAmount);
            } else {
                insuranceRow.classList.add('hidden');
            }
        }

        // Show/hide cultural card surcharge row
        const culturalCardRow = document.getElementById('cultural-card-row');
        if (culturalCardRow) {
            if (culturalCardSurcharge > 0) {
                culturalCardRow.classList.remove('hidden');
                document.getElementById('cultural-card-amount').textContent = '+' + AmbiletUtils.formatCurrency(culturalCardSurcharge);
            } else {
                culturalCardRow.classList.add('hidden');
            }
        }

        // Show/hide discount row (promo code)
        const discountRow = document.getElementById('discount-row');
        if (discountRow) {
            if (promoDiscount > 0) {
                const promo = AmbiletCart.getPromoCode();
                discountRow.classList.remove('hidden');
                document.getElementById('discount-label').textContent = 'Reducere' + (promo ? ' (' + promo.code + ')' : '');
                document.getElementById('discount-amount').textContent = '-' + AmbiletUtils.formatCurrency(promoDiscount);
            } else {
                discountRow.classList.add('hidden');
            }
        }

        document.getElementById('summary-total').textContent = AmbiletUtils.formatCurrency(total);
        document.getElementById('pay-btn-text').textContent = `Plătește ${AmbiletUtils.formatCurrency(total)}`;
        document.getElementById('points-earned').textContent = `${points} puncte`;

        // Savings
        if (savings > 0) {
            document.getElementById('savings-text').classList.remove('hidden');
            document.getElementById('savings-amount').textContent = `Economisești ${AmbiletUtils.formatCurrency(savings)}!`;
        }
    },

    validateForm() {
        const buyerFirstName = document.getElementById('buyer-first-name').value.trim();
        const buyerLastName = document.getElementById('buyer-last-name').value.trim();
        const buyerEmail = document.getElementById('buyer-email').value.trim();
        const buyerEmailConfirm = document.getElementById('buyer-email-confirm').value.trim();
        const buyerPhone = document.getElementById('buyer-phone').value.trim();

        if (!buyerFirstName || !buyerLastName || !buyerEmail || !buyerEmailConfirm || !buyerPhone) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Completează toate câmpurile obligatorii');
            }
            return false;
        }

        // Validate email match
        if (buyerEmail !== buyerEmailConfirm) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Adresele de email nu coincid');
            }
            document.getElementById('buyer-email-confirm').focus();
            return false;
        }

        if (!document.getElementById('termsCheckbox').checked) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error('Trebuie să accepți termenii și condițiile');
            }
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

        // Count total tickets
        let ticketIndex = 0;
        this.items.forEach((item, itemIndex) => {
            const qty = item.quantity || 1;
            for (let i = 0; i < qty; i++) {
                if (useDifferentBeneficiaries) {
                    // Get values from beneficiary form
                    const nameInput = document.querySelector(`.beneficiary-name[data-item="${itemIndex}"][data-index="${i}"]`);
                    const emailInput = document.querySelector(`.beneficiary-email[data-item="${itemIndex}"][data-index="${i}"]`);
                    beneficiaries.push({
                        name: nameInput?.value.trim() || buyerName,
                        email: emailInput?.value.trim() || buyerEmail,
                        item_index: itemIndex,
                        ticket_index: i
                    });
                } else {
                    // Use buyer data for all tickets
                    beneficiaries.push({
                        name: buyerName,
                        email: buyerEmail,
                        item_index: itemIndex,
                        ticket_index: i
                    });
                }
                ticketIndex++;
            }
        });

        return beneficiaries;
    },

    async submit() {
        if (!this.validateForm()) return;

        const payBtn = document.getElementById('payBtn');
        const payBtnText = document.getElementById('pay-btn-text');

        payBtn.disabled = true;
        payBtnText.innerHTML = `
            <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Se procesează...
        `;

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

            // Add promo code if applied
            const promo = AmbiletCart.getPromoCode();
            if (promo && promo.code) {
                checkoutData.promo_code = promo.code;
            }

            // Add ticket insurance if selected
            if (this.insuranceSelected && this.totals.insurance > 0) {
                checkoutData.ticket_insurance = true;
                checkoutData.ticket_insurance_amount = this.totals.insurance;
            }

            // Add cultural card surcharge if applicable
            if (paymentMethod === 'card_cultural' && this.totals.culturalCardSurcharge > 0) {
                checkoutData.cultural_card_surcharge = this.totals.culturalCardSurcharge;
            }

            const response = await AmbiletAPI.post('/checkout', checkoutData);

            if (!response.success) {
                throw new Error(response.message || 'Eroare la procesarea comenzii');
            }

            // Get order from response
            const order = response.data.orders?.[0];
            if (!order) {
                throw new Error('Nu s-a putut crea comanda');
            }

            // Step 2: Check if payment is required
            if (response.data.payment_required && order.total > 0) {
                // Initiate payment
                payBtnText.innerHTML = `
                    <svg class="inline w-5 h-5 mr-2 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Se redirecționează către plată...
                `;

                const payResponse = await AmbiletAPI.post(`/orders/${order.id}/pay`, {
                    return_url: window.location.origin + '/multumim?order=' + order.order_number,
                    cancel_url: window.location.origin + '/checkout'
                });

                if (payResponse.success && payResponse.data.payment_url) {
                    AmbiletCart.clear();
                    localStorage.removeItem('cart_end_time');

                    // Check if payment requires POST form submission (e.g., Netopia)
                    if (payResponse.data.method === 'POST' && payResponse.data.form_data) {
                        // Create and submit a form
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
                AmbiletCart.clear();
                localStorage.removeItem('cart_end_time');
                window.location.href = '/multumim?order=' + order.order_number;
            }
        } catch (error) {
            console.error('Checkout error:', error);
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.error(error.message || 'Eroare la procesare. Încearcă din nou.');
            }
            payBtn.disabled = false;
            payBtnText.textContent = `Plătește ${AmbiletUtils.formatCurrency(this.totals.total)}`;
        }
    }
};

document.addEventListener('DOMContentLoaded', () => CheckoutPage.init());
