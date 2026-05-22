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
                    this.populateDetails(data);
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
                parts.push('Buget ' + fmt.format(data.min_fee_ron) + '–' + fmt.format(data.max_fee_ron) + ' RON');
            }
            if (this.subtitleEl) this.subtitleEl.textContent = parts.join(' · ');
        },

        populateDetails(data) {
            const EVENT_TYPE_LABELS = {
                concert: 'Concert', festival: 'Festival', private: 'Eveniment privat',
                corporate: 'Corporate', wedding: 'Nuntă', club: 'Club / lounge',
                show: 'Show TV / online', charity: 'Caritate',
            };
            const fmt = new Intl.NumberFormat('ro-RO');

            // Description
            const descEl = document.getElementById('bookingDescriptionEl');
            if (descEl) {
                if (data.description && String(data.description).trim()) {
                    descEl.textContent = data.description;
                    descEl.classList.remove('hidden');
                } else {
                    descEl.classList.add('hidden');
                }
            }

            // Event types
            const typesRow = document.getElementById('bookingEventTypesRow');
            const typesList = document.getElementById('bookingEventTypesList');
            if (typesRow && typesList) {
                const types = Array.isArray(data.event_types) ? data.event_types : [];
                if (types.length) {
                    typesList.innerHTML = types.map(t => {
                        const label = EVENT_TYPE_LABELS[t] || t;
                        return '<span class="px-2.5 py-1 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-full">' + this.escapeHtml(label) + '</span>';
                    }).join('');
                    typesRow.classList.remove('hidden');
                } else {
                    typesRow.classList.add('hidden');
                }
            }

            // Facts list
            const facts = [];
            if (data.standard_set_length_min) {
                facts.push({ label: 'Set standard', value: data.standard_set_length_min + ' min' });
            }
            if (data.standard_min_audience || data.standard_max_audience) {
                const audience = [data.standard_min_audience, data.standard_max_audience].filter(Boolean);
                if (audience.length === 2) {
                    facts.push({ label: 'Audiență', value: fmt.format(audience[0]) + '–' + fmt.format(audience[1]) });
                } else if (audience.length === 1) {
                    facts.push({ label: 'Audiență', value: fmt.format(audience[0]) + '+' });
                }
            }
            if (data.max_distance_km) {
                facts.push({ label: 'Distanță maximă', value: fmt.format(data.max_distance_km) + ' km' });
            }
            if (data.show_fee_publicly && data.min_fee_ron && data.max_fee_ron) {
                facts.push({ label: 'Buget acceptat', value: fmt.format(data.min_fee_ron) + '–' + fmt.format(data.max_fee_ron) + ' RON' });
            }
            const factsEl = document.getElementById('bookingFactsList');
            if (factsEl) {
                factsEl.innerHTML = facts.map(f =>
                    '<div class="flex items-center justify-between gap-3 py-1.5 border-b border-gray-100 last:border-0">' +
                        '<dt class="text-gray-500">' + this.escapeHtml(f.label) + '</dt>' +
                        '<dd class="font-semibold text-gray-900">' + this.escapeHtml(f.value) + '</dd>' +
                    '</div>'
                ).join('');
            }

            // Conditions
            const condRow = document.getElementById('bookingConditionsRow');
            const condList = document.getElementById('bookingConditionsList');
            if (condRow && condList) {
                const conditions = [];
                if (data.requires_soundcheck) {
                    conditions.push('Soundcheck' + (data.soundcheck_min_minutes ? ' (min ' + data.soundcheck_min_minutes + ' min)' : ''));
                }
                if (data.requires_backline) conditions.push('Backline asigurat de organizator');
                if (data.requires_catering) conditions.push('Catering / masă');
                if (data.requires_accommodation) conditions.push('Cazare');
                if (data.requires_transport) conditions.push('Transport');

                if (conditions.length) {
                    condList.innerHTML = conditions.map(c =>
                        '<li class="flex items-start gap-1.5">' +
                            '<svg class="w-3.5 h-3.5 text-emerald-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>' +
                            '<span>' + this.escapeHtml(c) + '</span>' +
                        '</li>'
                    ).join('');
                    condRow.classList.remove('hidden');
                } else {
                    condRow.classList.add('hidden');
                }
            }
        },

        escapeHtml(s) {
            if (s === null || s === undefined) return '';
            return String(s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
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
                // Honeypot — hidden field bots auto-fill. Server drops
                // submissions silently when it carries a value.
                website_url: fd.get('website_url') || '',
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
