const ThankYouPage = {
    order: null,

    async init() {
        await this.loadOrderData();
    },

    isPaymentFailed() {
        if (!this.order) return false;
        const status = this.order.status;
        const paymentStatus = this.order.payment_status;
        return status === 'failed' || status === 'cancelled' || status === 'expired'
            || paymentStatus === 'failed' || paymentStatus === 'declined' || paymentStatus === 'expired';
    },

    isPending() {
        if (!this.order) return false;
        return this.order.status === 'pending' || this.order.payment_status === 'pending';
    },

    createConfetti() {
        const container = document.getElementById('confetti');
        const colors = ['#A51C30', '#10B981', '#E67E22', '#3B82F6', '#8B5CF6', '#EC4899'];

        for (let i = 0; i < 80; i++) {
            setTimeout(() => {
                const confetti = document.createElement('div');
                confetti.className = 'confetti';
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.animationDelay = Math.random() * 2 + 's';
                confetti.style.animationDuration = (Math.random() * 2 + 3) + 's';

                if (Math.random() > 0.5) {
                    confetti.style.borderRadius = '0';
                    confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
                }

                container.appendChild(confetti);
                setTimeout(() => confetti.remove(), 6000);
            }, i * 50);
        }
    },

    async loadOrderData() {
        const urlParams = new URLSearchParams(window.location.search);
        // Read from 'order' param (our param) or 'orderId' (Netopia adds this on redirect)
        const orderRef = urlParams.get('order') || urlParams.get('orderId');

        if (!orderRef) {
            this.renderOrderNotFound();
            return;
        }

        // Clean up duplicate URL params (keep only ?order=)
        if (urlParams.get('orderId') || urlParams.has('orderId')) {
            const cleanUrl = window.location.pathname + '?order=' + encodeURIComponent(orderRef);
            history.replaceState(null, '', cleanUrl);
        }

        try {
            const response = await AmbiletAPI.get(`/order-confirmation/${orderRef}`);
            if (response.success && response.data?.order) {
                this.order = response.data.order;
                if (this.isPaymentFailed()) {
                    this.renderFailedPayment();
                } else if (this.isPending()) {
                    this.renderPendingPayment();
                } else {
                    this.createConfetti();
                    this.renderOrderData();
                }
            } else {
                console.warn('Order data not found in response:', response);
                this.renderOrderNotFound();
            }
        } catch (error) {
            console.error('Failed to load order:', error);
            this.renderOrderNotFound();
        }
    },

    renderOrderNotFound() {
        // Change progress steps - last step shows error
        const steps = document.querySelectorAll('.bg-green-500');
        const lastStep = steps[steps.length - 1];
        if (lastStep) {
            lastStep.classList.remove('bg-green-500');
            lastStep.classList.add('bg-red-500');
            lastStep.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            const label = lastStep.nextElementSibling;
            if (label) { label.classList.remove('text-green-600'); label.classList.add('text-red-600'); }
        }
        const connectors = document.querySelectorAll('.h-px.bg-green-500');
        if (connectors.length > 0) {
            connectors[connectors.length - 1].classList.remove('bg-green-500');
            connectors[connectors.length - 1].classList.add('bg-red-500');
        }

        // Change main icon to error
        const successIcon = document.querySelector('.bg-success\\/20');
        if (successIcon) {
            successIcon.classList.remove('bg-success/20');
            successIcon.classList.add('bg-red-100');
            successIcon.innerHTML = '<svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
        }

        const heading = document.querySelector('h1');
        if (heading) heading.textContent = 'Comanda nu a fost gasita';

        const printingText = document.getElementById('printingText');
        if (printingText) {
            printingText.textContent = 'Nu am putut gasi informatii despre aceasta comanda. Verifica numarul comenzii sau contacteaza suportul.';
            printingText.classList.remove('text-lg');
            printingText.classList.add('text-base');
        }

        // Hide printer, tickets, email, actions, share
        const printerSection = document.querySelector('.printer-section');
        if (printerSection) printerSection.style.display = 'none';
        const ticketsCarousel = document.getElementById('ticketsCarousel');
        if (ticketsCarousel) ticketsCarousel.style.display = 'none';
        const emailSection = document.querySelector('.email-animation');
        if (emailSection) emailSection.style.display = 'none';
        const orderDetails = document.getElementById('orderDetails');
        if (orderDetails) orderDetails.style.display = 'none';
        const actionsGrid = document.querySelector('.content-section.delay-2');
        if (actionsGrid) actionsGrid.style.display = 'none';
        const shareSection = document.querySelector('.content-section.delay-3');
        if (shareSection) shareSection.style.display = 'none';

        // Replace back button with useful links
        const backSection = document.querySelector('.content-section.delay-4');
        if (backSection) {
            backSection.style.opacity = '1';
            backSection.style.animation = 'none';
            backSection.innerHTML = `
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                    <a href="/cont/comenzi" class="inline-flex items-center gap-2 px-8 py-4 text-lg font-bold text-white bg-primary btn-primary rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        Comenzile mele
                    </a>
                    <a href="/" class="inline-flex items-center gap-2 px-8 py-4 text-lg font-bold border text-secondary rounded-xl border-border hover:bg-surface">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Pagina principala
                    </a>
                </div>
            `;
        }
    },

    renderFailedPayment() {
        // Update progress steps to show failure on last step
        const steps = document.querySelectorAll('.bg-green-500');
        const lastStep = steps[steps.length - 1];
        if (lastStep) {
            lastStep.classList.remove('bg-green-500');
            lastStep.classList.add('bg-red-500');
            lastStep.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            const label = lastStep.nextElementSibling;
            if (label) { label.classList.remove('text-green-600'); label.classList.add('text-red-600'); }
        }
        const lastLine = document.querySelectorAll('.bg-green-500');
        // Change connector line before last step to red
        const connectors = document.querySelectorAll('.h-px.bg-green-500');
        if (connectors.length > 0) {
            connectors[connectors.length - 1].classList.remove('bg-green-500');
            connectors[connectors.length - 1].classList.add('bg-red-500');
        }

        // Change main icon and message
        const successIcon = document.querySelector('.bg-success\\/20');
        if (successIcon) {
            successIcon.classList.remove('bg-success/20');
            successIcon.classList.add('bg-red-100');
            successIcon.innerHTML = '<svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>';
        }

        const heading = document.querySelector('h1');
        if (heading) heading.textContent = 'Plata nu a fost procesată';

        const printingText = document.getElementById('printingText');
        if (printingText) {
            printingText.textContent = 'Din păcate, plata nu a putut fi finalizată. Te rugăm să verifici datele cardului și să încerci din nou.';
            printingText.classList.remove('text-lg');
            printingText.classList.add('text-base');
        }

        // Hide printer, tickets carousel, email confirmation
        const printerSection = document.querySelector('.printer-section');
        if (printerSection) printerSection.style.display = 'none';

        const ticketsCarousel = document.getElementById('ticketsCarousel');
        if (ticketsCarousel) ticketsCarousel.style.display = 'none';

        const emailSection = document.querySelector('.email-animation');
        if (emailSection) emailSection.style.display = 'none';

        // Hide download/calendar actions
        const actionsGrid = document.querySelector('.content-section.delay-2');
        if (actionsGrid) actionsGrid.style.display = 'none';

        // Hide share section
        const shareSection = document.querySelector('.content-section.delay-3');
        if (shareSection) shareSection.style.display = 'none';

        // Show order details (but change status badge to failed)
        const statusBadge = document.querySelector('.bg-success\\/10.text-success');
        if (statusBadge) {
            statusBadge.classList.remove('bg-success/10', 'text-success');
            statusBadge.classList.add('bg-red-100', 'text-red-600');
            statusBadge.textContent = 'Eșuată';
        }

        // Still render order details (event info, payment info) so user sees what they tried to buy
        this.renderOrderDetails();

        // Add retry button
        const backSection = document.querySelector('.content-section.delay-4');
        if (backSection) {
            backSection.style.opacity = '1';
            backSection.style.animation = 'none';
            const eventSlug = this.order?.event?.slug;
            const retryUrl = eventSlug ? '/bilete/' + eventSlug : '/';
            backSection.innerHTML = `
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                    <a href="${retryUrl}" class="inline-flex items-center gap-2 px-8 py-4 text-lg font-bold text-white bg-primary btn-primary rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        Încearcă din nou
                    </a>
                    <a href="/" class="inline-flex items-center gap-2 px-8 py-4 text-lg font-bold border text-secondary rounded-xl border-border hover:bg-surface">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                        Pagina principală
                    </a>
                </div>
            `;
        }
    },

    renderPendingPayment() {
        // Change main icon to pending/waiting
        const successIcon = document.querySelector('.bg-success\\/20');
        if (successIcon) {
            successIcon.classList.remove('bg-success/20');
            successIcon.classList.add('bg-yellow-100');
            successIcon.innerHTML = '<svg class="w-10 h-10 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        }

        const heading = document.querySelector('h1');
        if (heading) heading.textContent = 'Plata este în procesare';

        const printingText = document.getElementById('printingText');
        if (printingText) printingText.textContent = 'Plata ta este în curs de verificare. Vei primi biletele pe email imediat ce plata este confirmată.';

        // Hide printer and tickets carousel
        const printerSection = document.querySelector('.printer-section');
        if (printerSection) printerSection.style.display = 'none';

        const ticketsCarousel = document.getElementById('ticketsCarousel');
        if (ticketsCarousel) ticketsCarousel.style.display = 'none';

        // Hide download/calendar actions
        const actionsGrid = document.querySelector('.content-section.delay-2');
        if (actionsGrid) actionsGrid.style.display = 'none';

        // Update email section text
        const emailSection = document.querySelector('.email-animation');
        if (emailSection) {
            const emailText = emailSection.querySelector('.font-semibold');
            if (emailText) emailText.textContent = 'Vei primi biletele pe email după confirmarea plății';
            const checkIcon = emailSection.querySelector('.text-success.flex-shrink-0');
            if (checkIcon) {
                checkIcon.classList.remove('text-success');
                checkIcon.classList.add('text-yellow-500');
                checkIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>';
            }
        }

        // Change status badge to pending
        const statusBadge = document.querySelector('.bg-success\\/10.text-success');
        if (statusBadge) {
            statusBadge.classList.remove('bg-success/10', 'text-success');
            statusBadge.classList.add('bg-yellow-100', 'text-yellow-700');
            statusBadge.textContent = 'În așteptare';
        }

        // Render order details
        this.renderOrderDetails();
    },

    renderOrderDetails() {
        const order = this.order;
        if (!order) return;

        // Event info
        const eventInfo = document.getElementById('eventInfo');
        const event = order.event;
        if (event && eventInfo) {
            const eventTitle = event.name || event.title || 'Eveniment';
            const eventDate = event.date ? AmbiletUtils.formatDate(event.date) : '';
            const eventTime = event.doors_open || (event.date ? new Date(event.date).toLocaleTimeString('ro-RO', {hour: '2-digit', minute: '2-digit'}) : '');
            const venue = (typeof event.venue === 'object' && event.venue !== null) ? (event.venue.ro || event.venue.en || Object.values(event.venue)[0] || '') : (event.venue || '');
            eventInfo.innerHTML = `
                <img src="${getStorageUrl(event.image)}" alt="${eventTitle}" class="object-cover w-20 h-20 rounded-xl" loading="lazy" onerror="this.style.display='none'">
                <div>
                    <h3 class="font-bold text-secondary">${eventTitle}</h3>
                    <p class="mt-1 text-sm text-muted">${eventDate}${eventTime ? ' • ' + eventTime : ''}</p>
                    <p class="text-sm text-muted">${venue}${event.city ? ', ' + event.city : ''}</p>
                </div>
            `;
        }

        // Ticket summary
        const ticketsSummary = document.getElementById('ticketsSummary');
        if (order.items && order.items.length > 0 && ticketsSummary) {
            let html = `<h4 class="mb-3 font-semibold text-secondary">Bilete</h4>`;
            html += order.items.map(item => `
                <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                    <div>
                        <span class="font-medium text-secondary">${item.name}</span>
                        <span class="ml-1 text-sm text-muted">× ${item.quantity}</span>
                    </div>
                    <span class="font-semibold">${AmbiletUtils.formatCurrency(item.total)}</span>
                </div>
            `).join('');
            ticketsSummary.innerHTML = html;
        }

        // Payment summary
        const subtotal = parseFloat(order.subtotal) || 0;
        const total = parseFloat(order.total) || 0;
        const discount = parseFloat(order.discount) || 0;
        const serviceFee = parseFloat(order.service_fee) || 0;
        const insuranceAmount = parseFloat(order.insurance_amount) || 0;

        document.getElementById('paymentSummary').innerHTML = `
            <h4 class="mb-3 font-semibold text-secondary">Sumar plată</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-muted">Subtotal</span>
                    <span>${AmbiletUtils.formatCurrency(subtotal)}</span>
                </div>
                ${serviceFee > 0 ? `
                <div class="flex justify-between">
                    <span class="text-muted">Comision serviciu</span>
                    <span>${AmbiletUtils.formatCurrency(serviceFee)}</span>
                </div>` : ''}
                ${insuranceAmount > 0 ? `
                <div class="flex justify-between">
                    <span class="text-muted">Taxa de retur</span>
                    <span>${AmbiletUtils.formatCurrency(insuranceAmount)}</span>
                </div>` : ''}
                ${discount > 0 ? `
                <div class="flex justify-between text-success">
                    <span>Reducere</span>
                    <span>-${AmbiletUtils.formatCurrency(discount)}</span>
                </div>` : ''}
                <div class="flex justify-between pt-2 text-lg font-bold border-t border-border">
                    <span>Total</span>
                    <span class="text-primary">${AmbiletUtils.formatCurrency(total)}</span>
                </div>
            </div>
        `;

        // Payment method
        if (order.payment_method) {
            const cardEl = document.getElementById('cardNumber');
            if (cardEl) cardEl.textContent = order.payment_method;
        }

        // Hide points section
        const pointsEl = document.getElementById('pointsEarned');
        if (pointsEl) pointsEl.style.display = 'none';

        // Make order details visible immediately (no animation delay)
        const orderDetails = document.getElementById('orderDetails');
        if (orderDetails) {
            orderDetails.style.opacity = '1';
            orderDetails.style.animation = 'none';
        }
    },

    renderOrderData() {
        const order = this.order;

        // Update texts
        document.getElementById('printingText').textContent = 'Biletele sunt gata!';
        document.getElementById('buyerEmail').textContent = order.customer_email || 'Email-ul tău';

        // Event info
        const eventInfo = document.getElementById('eventInfo');
        const event = order.event;
        if (event) {
            const eventTitle = event.name || event.title || 'Eveniment';
            const eventDate = event.date ? AmbiletUtils.formatDate(event.date) : '';
            const eventTime = event.doors_open || (event.date ? new Date(event.date).toLocaleTimeString('ro-RO', {hour: '2-digit', minute: '2-digit'}) : '');
            // Venue may be string or translatable object {en: "...", ro: "..."}
            const venue = (typeof event.venue === 'object' && event.venue !== null) ? (event.venue.ro || event.venue.en || Object.values(event.venue)[0] || '') : (event.venue || '');
            eventInfo.innerHTML = `
                <img src="${getStorageUrl(event.image)}" alt="${eventTitle}" class="object-cover w-20 h-20 rounded-xl" loading="lazy" onerror="this.style.display='none'">
                <div>
                    <h3 class="font-bold text-secondary">${eventTitle}</h3>
                    <p class="mt-1 text-sm text-muted">${eventDate}${eventTime ? ' • ' + eventTime : ''}</p>
                    <p class="text-sm text-muted">${venue}${event.city ? ', ' + event.city : ''}</p>
                </div>
            `;
        }

        // Tickets
        const tickets = order.tickets || [];
        this.renderTickets(Array.isArray(tickets) ? tickets : Object.values(tickets));

        // Ticket summary (items grouped by type + individual seat assignments)
        const ticketsSummary = document.getElementById('ticketsSummary');
        const seatedTickets = (order.tickets || []).filter(t => t.seat);
        if (order.items && order.items.length > 0) {
            let html = `<h4 class="mb-3 font-semibold text-secondary">Bilete achiziționate</h4>`;
            html += order.items.map(item => `
                <div class="flex items-center justify-between py-2 border-b border-border last:border-0">
                    <div>
                        <span class="font-medium text-secondary">${item.name}</span>
                        <span class="ml-1 text-sm text-muted">× ${item.quantity}</span>
                    </div>
                    <span class="font-semibold">${AmbiletUtils.formatCurrency(item.total)}</span>
                </div>
            `).join('');
            // Show insurance info if any ticket has insurance
            const insuredTickets = (order.tickets || []).filter(t => t.has_insurance);
            if (insuredTickets.length > 0) {
                html += `<div class="pt-3 mt-3 border-t border-border">
                    <div class="flex items-center gap-2 text-sm text-green-700">
                        <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        <span class="font-medium">${insuredTickets.length} bilet${insuredTickets.length > 1 ? 'e' : ''} asigurat${insuredTickets.length > 1 ? 'e' : ''} cu taxa de retur</span>
                    </div>
                </div>`;
            }
            // Show individual seat assignments if tickets have seats
            if (seatedTickets.length > 0) {
                html += `<div class="pt-3 mt-3 border-t border-border">
                    <p class="mb-2 text-xs font-medium tracking-wide uppercase text-muted">Locuri atribuite</p>
                    ${seatedTickets.map(t => `
                        <div class="flex items-center gap-2 py-1 text-sm">
                            <span class="text-muted">${t.type || 'Bilet'}</span>
                            <span class="font-medium text-secondary">
                                ${[t.seat.section_name, t.seat.row_label ? 'Rând ' + t.seat.row_label : '', t.seat.seat_number ? 'Loc ' + t.seat.seat_number : ''].filter(Boolean).join(', ')}
                            </span>
                        </div>
                    `).join('')}
                </div>`;
            }
            ticketsSummary.innerHTML = html;
        }

        // Payment summary
        const subtotal = parseFloat(order.subtotal) || 0;
        const total = parseFloat(order.total) || 0;
        const discount = parseFloat(order.discount) || 0;
        const serviceFee = parseFloat(order.service_fee) || 0;
        const insuranceAmount = parseFloat(order.insurance_amount) || 0;
        const currency = order.currency || 'RON';

        document.getElementById('paymentSummary').innerHTML = `
            <h4 class="mb-3 font-semibold text-secondary">Sumar plată</h4>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-muted">Subtotal</span>
                    <span>${AmbiletUtils.formatCurrency(subtotal)}</span>
                </div>
                ${serviceFee > 0 ? `
                <div class="flex justify-between">
                    <span class="text-muted">Comision serviciu</span>
                    <span>${AmbiletUtils.formatCurrency(serviceFee)}</span>
                </div>
                ` : ''}
                ${insuranceAmount > 0 ? `
                <div class="flex justify-between">
                    <span class="text-muted">Taxa de retur</span>
                    <span>${AmbiletUtils.formatCurrency(insuranceAmount)}</span>
                </div>
                ` : ''}
                ${discount > 0 ? `
                <div class="flex justify-between text-success">
                    <span>Reducere</span>
                    <span>-${AmbiletUtils.formatCurrency(discount)}</span>
                </div>
                ` : ''}
                <div class="flex justify-between pt-2 text-lg font-bold border-t border-border">
                    <span>Total plătit</span>
                    <span class="text-primary">${AmbiletUtils.formatCurrency(total)}</span>
                </div>
            </div>
        `;

        // Payment method
        if (order.payment_method) {
            const cardEl = document.getElementById('cardNumber');
            if (cardEl) {
                cardEl.textContent = order.payment_method;
            }
        }

        // Hide points section if no points data
        const pointsEl = document.getElementById('pointsEarned');
        if (pointsEl) {
            pointsEl.style.display = 'none';
        }

        // Wire up download button
        const downloadBtn = document.getElementById('downloadBtn');
        if (downloadBtn && order.can_download_tickets) {
            downloadBtn.href = '#';
            downloadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.downloadTickets();
            });
        }

        // Wire up calendar button
        const calendarBtn = document.getElementById('calendarBtn');
        if (calendarBtn && order.event) {
            calendarBtn.href = '#';
            calendarBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.addToCalendar();
            });
        }

        // Wire up share buttons
        const eventUrl = window.location.origin + '/bilete/' + (order.event?.slug || 'event');
        const eventTitle = order.event?.name || order.event?.title || 'Eveniment';
        const shareFb = document.getElementById('shareFb');
        if (shareFb) {
            shareFb.href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(eventUrl);
            shareFb.target = '_blank';
        }
        const shareWa = document.getElementById('shareWa');
        if (shareWa) {
            shareWa.href = 'https://wa.me/?text=' + encodeURIComponent(eventTitle + ' - ' + eventUrl);
            shareWa.target = '_blank';
        }
    },

    downloadTickets() {
        const order = this.order;
        if (!order || !order.order_number) return;

        // Use the backend PDF endpoint which renders with the event's assigned
        // ticket template (falling back to the marketplace default). The old
        // client-side window.print() path ignored the template entirely.
        window.location.href = '/api/proxy.php?action=order.download-tickets-pdf&order='
            + encodeURIComponent(order.order_number);
    },

    downloadTicketsLegacyPrint() {
        const order = this.order;
        if (!order || !order.tickets) return;

        const event = order.event;
        const eventTitle = event?.name || event?.title || 'Eveniment';
        const eventDate = event?.date ? AmbiletUtils.formatDate(event.date) : '';
        const venue = typeof event?.venue === 'object' ? (event.venue?.ro || event.venue?.en || '') : (event?.venue || '');
        const siteName = window.AMBILET?.siteName || 'bilete.online';

        const ticketsHtml = order.tickets.map((ticket, idx) => {
            const seatInfo = ticket.seat ? [
                ticket.seat.section_name,
                ticket.seat.row_label ? 'Rând ' + ticket.seat.row_label : '',
                ticket.seat.seat_number ? 'Loc ' + ticket.seat.seat_number : ''
            ].filter(Boolean).join(' | ') : '';

            return `
                <div style="page-break-inside: avoid; border: 2px solid #1E293B; border-radius: 12px; padding: 24px; margin-bottom: 24px; max-width: 500px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 2px dashed #E2E8F0;">
                        <div>
                            <div style="font-size: 11px; color: #64748B; text-transform: uppercase;">${siteName}</div>
                            <div style="font-size: 18px; font-weight: 700;">${ticket.type || 'Bilet'}</div>
                        </div>
                        <div style="text-align: right; font-size: 12px; color: #64748B;">${idx + 1} / ${order.tickets.length}</div>
                    </div>
                    <div style="margin-bottom: 12px;">
                        <div style="font-size: 11px; color: #64748B;">EVENIMENT</div>
                        <div style="font-size: 16px; font-weight: 600;">${eventTitle}</div>
                    </div>
                    <div style="display: flex; gap: 24px; margin-bottom: 12px;">
                        <div><div style="font-size: 11px; color: #64748B;">DATA</div><div style="font-weight: 600;">${eventDate}</div></div>
                        <div><div style="font-size: 11px; color: #64748B;">LOCAȚIE</div><div style="font-weight: 600;">${venue}${event?.city ? ', ' + event.city : ''}</div></div>
                    </div>
                    ${seatInfo ? `<div style="margin-bottom: 12px; padding: 8px 12px; background: #F1F5F9; border-radius: 8px; font-weight: 600;">${seatInfo}</div>` : ''}
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div><div style="font-size: 11px; color: #64748B;">PARTICIPANT</div><div style="font-weight: 500;">${ticket.attendee_name || order.customer_name || ''}</div></div>
                        <div style="text-align: right;"><div style="font-size: 11px; color: #64748B;">PREȚ</div><div style="font-weight: 700; color: #A51C30;">${AmbiletUtils.formatCurrency(ticket.price)}</div></div>
                    </div>
                    <div style="text-align: center; padding-top: 12px; border-top: 1px solid #E2E8F0;">
                        ${(ticket.code || ticket.barcode) ? `<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(ticket.code || ticket.barcode)}" style="width: 150px; height: 150px;" onerror="this.style.display='none';this.nextElementSibling.style.display='block'" />
                        <div style="display:none;padding:10px;border:2px solid #1E293B;border-radius:8px;font-family:monospace;font-size:14px;font-weight:bold;word-break:break-all">${ticket.code || ticket.barcode}</div>` : '<div style="padding:10px;color:#94A3B8;font-size:12px;">Cod indisponibil</div>'}
                        <div style="font-family: monospace; font-size: 11px; color: #64748B; margin-top: 6px;">${ticket.code || ticket.barcode || ''}</div>
                        ${ticket.ticket_series ? `<div style="font-family: monospace; font-size: 10px; color: #94A3B8; margin-top: 2px;">Serie: ${ticket.ticket_series}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`<!DOCTYPE html><html><head><title>Bilete - ${order.order_number}</title>
            <style>body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 20px; color: #1E293B; }
            @media print { body { padding: 0; } }</style></head>
            <body><div style="max-width: 500px; margin: 0 auto;">${ticketsHtml}</div>
            <scr` + `ipt>
            // Wait for QR code images to load before printing
            function waitForImages() {
                var imgs = document.querySelectorAll('img');
                var loaded = 0;
                var total = imgs.length;
                if (total === 0) { window.print(); return; }
                imgs.forEach(function(img) {
                    if (img.complete) { loaded++; if (loaded >= total) window.print(); }
                    else {
                        img.onload = function() { loaded++; if (loaded >= total) window.print(); };
                        img.onerror = function() { loaded++; if (loaded >= total) window.print(); };
                    }
                });
                // Fallback: print after 3 seconds even if images haven't loaded
                setTimeout(function() { window.print(); }, 3000);
            }
            waitForImages();
            <\/scr` + `ipt></body></html>`);
        printWindow.document.close();
    },

    addToCalendar() {
        const event = this.order?.event;
        if (!event) return;

        const title = event.name || event.title || 'Eveniment';
        const venue = typeof event.venue === 'object' ? (event.venue?.ro || event.venue?.en || '') : (event.venue || '');
        const location = venue + (event.city ? ', ' + event.city : '');
        const startDate = event.date ? new Date(event.date) : null;

        if (!startDate) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.info('Data evenimentului nu este disponibilă.');
            }
            return;
        }

        // Format dates for Google Calendar (YYYYMMDDTHHmmssZ)
        const formatGCal = (d) => d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
        const endDate = new Date(startDate.getTime() + 3 * 60 * 60 * 1000); // +3 hours default

        const gcalUrl = 'https://www.google.com/calendar/render?action=TEMPLATE'
            + '&text=' + encodeURIComponent(title)
            + '&dates=' + formatGCal(startDate) + '/' + formatGCal(endDate)
            + '&location=' + encodeURIComponent(location)
            + '&details=' + encodeURIComponent('Bilete achiziționate pe ' + (window.AMBILET?.siteName || 'bilete.online'));

        window.open(gcalUrl, '_blank');
    },

    renderTickets(tickets) {
        const container = document.getElementById('ticketsScroll');
        const indicators = document.getElementById('scrollIndicators');
        const total = tickets.length;

        if (total === 0) {
            document.getElementById('ticketsCount').textContent = 'Nu există bilete';
            return;
        }

        document.getElementById('ticketsCount').textContent = `${total} bilet${total > 1 ? 'e' : ''} pentru ${this.order?.event?.name || this.order?.event?.title || 'eveniment'}`;

        container.innerHTML = tickets.map((ticket, idx) => this.renderTicketCard(ticket, idx, total)).join('');

        // Scroll indicators
        indicators.innerHTML = tickets.map((_, idx) =>
            `<div class="scroll-dot ${idx === 0 ? 'active' : ''}" data-index="${idx}"></div>`
        ).join('');

        // Scroll event listener
        container.addEventListener('scroll', () => {
            const cardWidth = container.querySelector('.ticket-card')?.offsetWidth + 16 || 296;
            const activeIndex = Math.round(container.scrollLeft / cardWidth);

            indicators.querySelectorAll('.scroll-dot').forEach((dot, i) => {
                dot.classList.toggle('active', i === activeIndex);
            });
        });

        // Click on indicators
        indicators.querySelectorAll('.scroll-dot').forEach(dot => {
            dot.addEventListener('click', function() {
                const index = parseInt(this.dataset.index);
                const cardWidth = container.querySelector('.ticket-card')?.offsetWidth + 16 || 296;
                container.scrollTo({ left: index * cardWidth, behavior: 'smooth' });
            });
        });

        // Mouse drag support for desktop
        let isDragging = false, startX = 0, scrollStart = 0;
        container.addEventListener('mousedown', (e) => {
            isDragging = true;
            startX = e.pageX;
            scrollStart = container.scrollLeft;
            container.classList.add('dragging');
        });
        document.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            e.preventDefault();
            container.scrollLeft = scrollStart - (e.pageX - startX);
        });
        document.addEventListener('mouseup', () => {
            if (!isDragging) return;
            isDragging = false;
            container.classList.remove('dragging');
        });

        // Keyboard arrow support
        container.setAttribute('tabindex', '0');
        container.addEventListener('keydown', (e) => {
            const cardWidth = container.querySelector('.ticket-card')?.offsetWidth + 16 || 296;
            if (e.key === 'ArrowRight') { e.preventDefault(); container.scrollBy({ left: cardWidth, behavior: 'smooth' }); }
            if (e.key === 'ArrowLeft') { e.preventDefault(); container.scrollBy({ left: -cardWidth, behavior: 'smooth' }); }
        });
    },

    renderTicketCard(ticket, idx, total) {
        const barcodeLines = Array(15).fill(0).map(() =>
            `<div class="barcode-line" style="height: ${20 + Math.random() * 15}px;"></div>`
        ).join('');

        const event = this.order?.event;
        const eventTitle = event?.name || event?.title || 'Eveniment';
        const eventDate = event?.date ? AmbiletUtils.formatDate(event.date, 'medium') : '';
        const eventTime = event?.doors_open || (event?.date ? new Date(event.date).toLocaleTimeString('ro-RO', {hour: '2-digit', minute: '2-digit'}) : '');
        const eventVenue = event?.venue ? (typeof event.venue === 'object' ? (event.venue.ro || event.venue.en || Object.values(event.venue)[0] || '') : event.venue) : '';
        const siteName = window.AMBILET?.siteName || 'Ambilet';

        return `
            <div class="ticket-card" data-index="${idx}">
                <div class="ticket-card-header">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs opacity-70">${siteName} TICKET</span>
                        <span class="text-xs font-bold bg-white/20 px-2 py-0.5 rounded">${idx + 1} / ${total}</span>
                    </div>
                    <h3 class="text-lg font-bold">${ticket.type || ticket.type_name || 'Bilet'}</h3>
                </div>
                <div class="ticket-card-body">
                    <div class="ticket-dashed-line"></div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <p class="text-xs tracking-wide uppercase text-muted">Eveniment</p>
                            <p class="font-bold text-secondary">${eventTitle}</p>
                        </div>
                        <div class="flex gap-4">
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Data</p>
                                <p class="font-semibold text-secondary">${eventDate}</p>
                            </div>
                            ${eventTime ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Ora</p>
                                <p class="font-semibold text-secondary">${eventTime}</p>
                            </div>
                            ` : ''}
                        </div>
                        <div>
                            <p class="text-xs tracking-wide uppercase text-muted">Locație</p>
                            <p class="font-semibold text-secondary">${eventVenue}${event?.city ? ', ' + event.city : ''}</p>
                        </div>
                        ${ticket.seat ? `
                        <div class="flex flex-wrap gap-4">
                            ${ticket.seat.section_name ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Secțiune</p>
                                <p class="font-semibold text-secondary">${ticket.seat.section_name}</p>
                            </div>` : ''}
                            ${ticket.seat.row_label ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Rând</p>
                                <p class="font-semibold text-secondary">${ticket.seat.row_label}</p>
                            </div>` : ''}
                            ${ticket.seat.seat_number ? `
                            <div>
                                <p class="text-xs tracking-wide uppercase text-muted">Loc</p>
                                <p class="font-semibold text-secondary">${ticket.seat.seat_number}</p>
                            </div>` : ''}
                        </div>
                        ` : ''}
                        <div class="flex items-center justify-between pt-2">
                            <div>
                                <p class="text-xs text-muted">Participant</p>
                                <p class="text-sm font-semibold text-secondary">${ticket.attendee_name || this.order?.billing_address || 'Participant'}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-muted">Preț</p>
                                <p class="font-bold text-primary">${AmbiletUtils.formatCurrency(ticket.price)}</p>
                            </div>
                        </div>
                        ${ticket.has_insurance ? `
                        <div class="inline-flex items-center gap-1 px-2 py-1 mt-2 text-xs font-medium text-green-700 rounded-full bg-green-50">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            Asigurat - Taxa de retur
                        </div>
                        ` : ''}
                    </div>

                    <div class="ticket-barcode">${barcodeLines}</div>
                    <p class="text-center text-[10px] text-muted mt-2">${ticket.code || ticket.barcode || ''}</p>
                    ${ticket.ticket_series ? `<p class="text-center text-[10px] text-muted">Serie: ${ticket.ticket_series}</p>` : ''}
                </div>
            </div>
        `;
    },

    copyLink() {
        const url = window.location.origin + '/bilete/' + (this.order?.event?.slug || 'event');
        navigator.clipboard.writeText(url).then(() => {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.success('Link copiat în clipboard!');
            } else {
                alert('Link copiat în clipboard!');
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => ThankYouPage.init());
