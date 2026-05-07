/**
 * Booking Request Modal — atașat la /artist/{slug} via artist-single.php.
 * Verifică listing-ul de booking al artistului; dacă e activ, afișează butonul
 * "Cere booking" și deschide modal-ul cu formular. Trimite cererea via proxy
 * la /api/marketplace-client/public/artist/{slug}/booking-request.
 */
(function () {
    'use strict';

    const BookingRequestModal = {
        slug: null,
        modalEl: null,
        ctaContainer: null,
        ctaBtn: null,
        subtitleEl: null,
        formEl: null,
        errorEl: null,
        successEl: null,
        submitBtn: null,

        init() {
            this.slug = (window.ARTIST_SLUG || '').trim();
            if (!this.slug) return;

            this.modalEl = document.getElementById('bookingRequestModal');
            this.ctaContainer = document.getElementById('bookingCtaContainer');
            this.ctaBtn = document.getElementById('bookingCtaBtn');
            this.subtitleEl = document.getElementById('bookingCtaSubtitle');
            this.formEl = document.getElementById('bookingRequestForm');
            this.errorEl = document.getElementById('bookingFormError');
            this.successEl = document.getElementById('bookingFormSuccess');
            this.submitBtn = document.getElementById('bookingFormSubmit');

            if (!this.modalEl || !this.ctaContainer || !this.formEl) return;

            this.fetchStatus();
            this.bindEvents();
        },

        async fetchStatus() {
            try {
                const r = await fetch('/api/proxy.php?action=public.booking.status&slug=' + encodeURIComponent(this.slug), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!r.ok) return;
                const data = await r.json();
                if (data && data.active) {
                    this.ctaContainer.classList.remove('hidden');
                    this.populateSubtitle(data);
                }
            } catch (e) {
                console.warn('booking status fetch failed', e);
            }
        },

        populateSubtitle(data) {
            const parts = [];
            if (data.response_target_hours) {
                parts.push('Răspuns în ~' + data.response_target_hours + 'h');
            }
            if (data.min_fee_ron && data.max_fee_ron) {
                const fmt = new Intl.NumberFormat('ro-RO');
                parts.push('Cachet ' + fmt.format(data.min_fee_ron) + '–' + fmt.format(data.max_fee_ron) + ' RON');
            }
            if (this.subtitleEl) this.subtitleEl.textContent = parts.join(' · ');
        },

        bindEvents() {
            const close = () => this.closeModal();
            this.ctaBtn.addEventListener('click', () => this.openModal());
            document.getElementById('bookingModalClose')?.addEventListener('click', close);
            document.getElementById('bookingFormCancel')?.addEventListener('click', close);
            this.modalEl.addEventListener('click', (e) => { if (e.target === this.modalEl) close(); });
            this.formEl.addEventListener('submit', (e) => this.onSubmit(e));
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !this.modalEl.classList.contains('hidden')) close();
            });
        },

        openModal() {
            this.errorEl.classList.add('hidden');
            this.successEl.classList.add('hidden');
            this.modalEl.classList.remove('hidden');
            this.modalEl.classList.add('flex');
            document.body.style.overflow = 'hidden';
            const today = new Date();
            today.setDate(today.getDate() + 14);
            const minDate = today.toISOString().slice(0, 10);
            const dateInput = this.formEl.querySelector('[name="event_date"]');
            if (dateInput && !dateInput.min) dateInput.min = minDate;
        },

        closeModal() {
            this.modalEl.classList.add('hidden');
            this.modalEl.classList.remove('flex');
            document.body.style.overflow = '';
        },

        async onSubmit(e) {
            e.preventDefault();
            this.errorEl.classList.add('hidden');
            this.successEl.classList.add('hidden');

            const fd = new FormData(this.formEl);
            const conditions = fd.getAll('conditions[]');
            const payload = {
                guest_name: fd.get('guest_name'),
                guest_email: fd.get('guest_email'),
                guest_phone: fd.get('guest_phone') || null,
                guest_company: fd.get('guest_company') || null,
                guest_company_type: fd.get('guest_company_type') || null,
                event_date: fd.get('event_date'),
                event_time: fd.get('event_time') || null,
                event_venue_name: fd.get('event_venue_name') || null,
                event_city: fd.get('event_city'),
                event_country: fd.get('event_country') || 'RO',
                event_type: fd.get('event_type'),
                audience_size: fd.get('audience_size') ? parseInt(fd.get('audience_size'), 10) : null,
                proposed_fee_ron: parseInt(fd.get('proposed_fee_ron'), 10),
                proposed_set_length_min: fd.get('proposed_set_length_min') ? parseInt(fd.get('proposed_set_length_min'), 10) : 60,
                conditions,
                initial_message: fd.get('initial_message'),
                consent: fd.get('consent') ? true : false,
            };

            this.submitBtn.disabled = true;
            const originalText = this.submitBtn.textContent;
            this.submitBtn.textContent = 'Se trimite...';

            try {
                const r = await fetch('/api/proxy.php?action=public.booking.submit&slug=' + encodeURIComponent(this.slug), {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const text = await r.text();
                let data = null;
                try { data = JSON.parse(text); } catch (_) {}

                if (!r.ok) {
                    let msg = (data && (data.error || data.message)) || ('Eroare ' + r.status);
                    if (data && data.errors) {
                        msg = Object.values(data.errors).flat().join(' ');
                    }
                    this.errorEl.textContent = msg;
                    this.errorEl.classList.remove('hidden');
                    return;
                }

                this.successEl.textContent = (data && data.message) || 'Cererea a fost trimisă cu succes. Verifică emailul pentru confirmare.';
                this.successEl.classList.remove('hidden');
                this.formEl.reset();
                setTimeout(() => this.closeModal(), 3000);
            } catch (err) {
                this.errorEl.textContent = 'Conexiune eșuată. Reîncearcă în câteva momente.';
                this.errorEl.classList.remove('hidden');
            } finally {
                this.submitBtn.disabled = false;
                this.submitBtn.textContent = originalText;
            }
        },
    };

    window.BookingRequestModal = BookingRequestModal;
})();
