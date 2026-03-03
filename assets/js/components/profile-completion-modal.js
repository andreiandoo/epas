/**
 * Profile Completion Modal — Sequential Interest Profiling
 *
 * Two flows:
 * 1. Profile Completion (personal + location) — shown when < 80% complete
 * 2. Sequential Interest Modals — 4 separate modals shown over time:
 *    a) Event Types → b) Music Genres (filtered) → c) Cities → d) Venues (filtered)
 *
 * Each interest modal shown individually, with min 24h gap between them.
 * Smart suggestions from order history are shown for cities/venues.
 */
const ProfileCompletionModal = {
    isOpen: false,
    currentStep: 0,
    steps: [],
    customer: null,
    mode: 'profile', // 'profile' | 'interest_event_types' | 'interest_genres' | 'interest_cities' | 'interest_venues'

    // Profile completion
    _selectedGender: null,

    // Interest data
    eventTypes: [],
    eventGenres: [],
    smartSuggestions: null,
    selectedEventTypes: [],
    selectedEventGenres: [],
    selectedCities: [],
    selectedVenues: [],
    allVenues: [],

    // ═══════════════════════════════════════════════════════
    //  INIT
    // ═══════════════════════════════════════════════════════

    async init() {
        if (!AmbiletAuth?.isAuthenticated()) return;

        try {
            const res = await AmbiletAPI.customer.getProfile();
            if (!res.success || !res.data?.customer) return;
            this.customer = res.data.customer;
        } catch { return; }

        const completion = this.customer.profile_completion;
        if (!completion) return;

        // Priority 1: Profile completion (<80%) — personal + location
        if (completion.percentage < 80) {
            if (sessionStorage.getItem('pcm_dismissed')) return;
            this.mode = 'profile';
            this.buildProfileSteps();
            if (this.steps.length > 0) {
                setTimeout(() => this.show(), 5000);
                return;
            }
        }

        // Priority 2: Sequential interest modals
        this.tryShowInterestModal();
    },

    /**
     * Try to show the next pending interest modal
     */
    async tryShowInterestModal(forceBypass = false) {
        const nextStep = this.getNextInterestStep();
        if (!nextStep) return; // All done

        // Timing check (unless bypassed by post-purchase)
        if (!forceBypass && !this.canShowInterestModal()) return;

        // Session check — max one interest modal per session
        if (sessionStorage.getItem('pcm_interest_shown')) return;

        this.mode = 'interest_' + nextStep;
        this.steps = [this.getInterestStepConfig(nextStep)];
        this.currentStep = 0;

        const delay = forceBypass ? 3000 : 8000;
        setTimeout(() => {
            sessionStorage.setItem('pcm_interest_shown', '1');
            this.show();
        }, delay);
    },

    /**
     * Determine next interest step based on server-side profiling state
     */
    getNextInterestStep() {
        const profiling = this.customer.settings?.profiling || {};
        const completed = profiling.completed_steps || [];
        const interests = this.customer.settings?.interests || {};

        const steps = [
            { key: 'event_types', field: 'event_types' },
            { key: 'event_genres', field: 'event_genres' },
            { key: 'preferred_cities', field: 'preferred_cities' },
            { key: 'preferred_venues', field: 'preferred_venues' },
        ];

        for (const step of steps) {
            // Skip if already completed OR if data already exists
            if (completed.includes(step.key)) continue;
            const data = interests[step.field];
            if (data && Array.isArray(data) && data.length > 0) continue;
            return step.key;
        }
        return null;
    },

    /**
     * Check timing constraint — min 24h between interest modals
     */
    canShowInterestModal() {
        const profiling = this.customer.settings?.profiling || {};
        const lastModal = profiling.last_modal_at;
        if (!lastModal) return true;
        const hoursSince = (Date.now() - new Date(lastModal).getTime()) / (1000 * 60 * 60);
        return hoursSince >= 24;
    },

    /**
     * Get step configuration for an interest modal
     */
    getInterestStepConfig(step) {
        const configs = {
            event_types: {
                id: 'event_types',
                title: 'Ce tip de evenimente preferi?',
                subtitle: 'Selectează tipurile de evenimente care te interesează',
                icon: '🎭',
            },
            event_genres: {
                id: 'event_genres',
                title: 'Ce genuri muzicale îți plac?',
                subtitle: 'Bazat pe tipurile de evenimente alese, iată genurile disponibile',
                icon: '🎵',
            },
            preferred_cities: {
                id: 'preferred_cities',
                title: 'În ce orașe mergi la evenimente?',
                subtitle: 'Selectează orașele în care participi sau vrei să participi la evenimente',
                icon: '📍',
            },
            preferred_venues: {
                id: 'preferred_venues',
                title: 'Care sunt locațiile tale preferate?',
                subtitle: 'Alege locațiile din orașele tale unde mergi cel mai des',
                icon: '🏟️',
            },
        };
        return configs[step];
    },

    // ═══════════════════════════════════════════════════════
    //  POST-PURCHASE TRIGGER
    // ═══════════════════════════════════════════════════════

    triggerAfterPurchase() {
        sessionStorage.removeItem('pcm_dismissed');
        sessionStorage.removeItem('pcm_interest_shown');
        setTimeout(async () => {
            if (!AmbiletAuth?.isAuthenticated()) return;
            try {
                const res = await AmbiletAPI.customer.getProfile();
                if (!res.success || !res.data?.customer) return;
                this.customer = res.data.customer;

                const completion = this.customer.profile_completion;
                if (!completion) return;

                if (completion.percentage < 80) {
                    this.mode = 'profile';
                    this.buildProfileSteps();
                    if (this.steps.length > 0) {
                        this.show();
                        return;
                    }
                }

                this.tryShowInterestModal(true);
            } catch {}
        }, 3000);
    },

    // ═══════════════════════════════════════════════════════
    //  PROFILE COMPLETION STEPS (personal + location)
    // ═══════════════════════════════════════════════════════

    buildProfileSteps() {
        this.steps = [];
        const fields = this.customer.profile_completion?.fields || {};

        if (!fields.birth_date || !fields.gender || !fields.phone) {
            this.steps.push({
                id: 'personal',
                title: 'Despre tine',
                subtitle: 'Ajută-ne să îți personalizăm experiența',
                icon: '👤',
            });
        }

        if (!fields.city || !fields.state) {
            this.steps.push({
                id: 'location',
                title: 'De unde ești?',
                subtitle: 'Îți vom recomanda evenimente din zona ta',
                icon: '📍',
            });
        }
    },

    // ═══════════════════════════════════════════════════════
    //  SHOW / HIDE
    // ═══════════════════════════════════════════════════════

    show() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.currentStep = 0;

        // Preload data based on mode
        if (this.mode.startsWith('interest_')) {
            this.loadInterestData();
        }

        this.render();
        document.body.style.overflow = 'hidden';
    },

    dismiss() {
        this.isOpen = false;
        if (this.mode === 'profile') {
            sessionStorage.setItem('pcm_dismissed', '1');
        }
        const el = document.getElementById('pcm-overlay');
        if (el) el.remove();
        document.body.style.overflow = '';
    },

    // ═══════════════════════════════════════════════════════
    //  DATA LOADING
    // ═══════════════════════════════════════════════════════

    async loadInterestData() {
        try {
            if (this.mode === 'interest_event_types') {
                const res = await AmbiletAPI.getEventTypes();
                if (res.success && res.data?.event_types) {
                    this.eventTypes = res.data.event_types;
                    this.reRenderContent();
                }
            } else if (this.mode === 'interest_event_genres') {
                // Get genres filtered by previously selected event types
                const selectedTypes = this.customer.settings?.interests?.event_types || [];
                // Get event type IDs from slugs
                if (selectedTypes.length > 0) {
                    const typesRes = await AmbiletAPI.getEventTypes();
                    if (typesRes.success && typesRes.data?.event_types) {
                        const allTypes = typesRes.data.event_types;
                        const flatTypes = [];
                        allTypes.forEach(t => {
                            flatTypes.push(t);
                            if (t.children) flatTypes.push(...t.children);
                        });
                        const typeIds = flatTypes.filter(t => selectedTypes.includes(t.slug)).map(t => t.id);
                        const res = await AmbiletAPI.getEventGenres(typeIds);
                        if (res.success && res.data?.genres) {
                            this.eventGenres = res.data.genres;
                            this.reRenderContent();
                        }
                    }
                } else {
                    // No event types selected — show all genres
                    const res = await AmbiletAPI.getEventGenres();
                    if (res.success && res.data?.genres) {
                        this.eventGenres = res.data.genres;
                        this.reRenderContent();
                    }
                }
            } else if (this.mode === 'interest_preferred_cities' || this.mode === 'interest_preferred_venues') {
                const res = await AmbiletAPI.customer.getSmartSuggestions();
                if (res.success && res.data) {
                    this.smartSuggestions = res.data;
                    if (this.mode === 'interest_preferred_venues' && this.smartSuggestions.suggested_venues) {
                        this.allVenues = this.smartSuggestions.suggested_venues;
                    }
                    this.reRenderContent();
                }
            }
        } catch (e) {
            console.error('Failed to load interest data:', e);
        }
    },

    reRenderContent() {
        const content = document.getElementById('pcm-step-content');
        if (content) {
            content.innerHTML = this.renderStepContent(this.steps[this.currentStep]);
        }
    },

    // ═══════════════════════════════════════════════════════
    //  RENDER
    // ═══════════════════════════════════════════════════════

    render() {
        const existing = document.getElementById('pcm-overlay');
        if (existing) existing.remove();

        const step = this.steps[this.currentStep];
        const totalSteps = this.steps.length;
        const isInterest = this.mode.startsWith('interest_');

        const overlay = document.createElement('div');
        overlay.id = 'pcm-overlay';
        overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center p-4';
        overlay.style.cssText = 'background:rgba(0,0,0,0.6);animation:pcmFadeIn 0.3s ease';
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.dismiss();
        });

        const headerGradient = isInterest
            ? '#4F46E5 0%,#7C3AED 100%'
            : '#A51C30 0%,#8B1728 100%';

        const incentiveBadge = isInterest
            ? `<div style="display:flex;align-items:center;gap:0.375rem;margin-top:0.75rem;padding:0.375rem 0.75rem;background:rgba(255,255,255,0.15);border-radius:2rem;width:fit-content;font-size:0.8125rem">
                    <span>🎯</span>
                    <span>Descoperă <strong>recomandări personalizate</strong></span>
               </div>`
            : `<div style="display:flex;align-items:center;gap:0.375rem;margin-top:0.75rem;padding:0.375rem 0.75rem;background:rgba(255,255,255,0.15);border-radius:2rem;width:fit-content;font-size:0.8125rem">
                    <span>⚡</span>
                    <span>Completează pentru <strong>+20 XP</strong></span>
               </div>`;

        overlay.innerHTML = `
            <style>
                @keyframes pcmFadeIn { from { opacity: 0; } to { opacity: 1; } }
                @keyframes pcmSlideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
                .pcm-card { animation: pcmSlideUp 0.3s ease; max-width: 480px; width: 100%; background: white; border-radius: 1.25rem; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
                .pcm-chip { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1rem; border-radius: 2rem; border: 2px solid #E2E8F0; background: white; cursor: pointer; font-size: 0.875rem; transition: all 0.2s; user-select: none; }
                .pcm-chip:hover { border-color: #A51C30; }
                .pcm-chip.selected { border-color: #A51C30; background: rgba(165,28,48,0.08); color: #A51C30; font-weight: 600; }
                .pcm-pill-btn { padding: 0.625rem 1.25rem; border-radius: 0.75rem; border: 2px solid #E2E8F0; background: white; cursor: pointer; font-size: 0.875rem; font-weight: 500; transition: all 0.2s; }
                .pcm-pill-btn:hover { border-color: #A51C30; }
                .pcm-pill-btn.selected { border-color: #A51C30; background: #A51C30; color: white; }
                .pcm-input { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #E2E8F0; border-radius: 0.75rem; font-size: 0.875rem; transition: all 0.2s; outline: none; }
                .pcm-input:focus { border-color: #A51C30; box-shadow: 0 0 0 3px rgba(165,28,48,0.1); }
                .pcm-select { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #E2E8F0; border-radius: 0.75rem; font-size: 0.875rem; background: white; outline: none; }
                .pcm-select:focus { border-color: #A51C30; box-shadow: 0 0 0 3px rgba(165,28,48,0.1); }
                .pcm-suggestion { background: linear-gradient(135deg, #F0F9FF, #E0F2FE); border: 1px solid #BAE6FD; border-radius: 0.75rem; padding: 0.75rem 1rem; margin-bottom: 1rem; }
                .pcm-suggestion-text { font-size: 0.8125rem; color: #0369A1; }
                .pcm-suggestion strong { color: #0C4A6E; }
                .pcm-venue-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.875rem; border: 2px solid #E2E8F0; border-radius: 0.75rem; cursor: pointer; transition: all 0.2s; margin-bottom: 0.5rem; }
                .pcm-venue-item:hover { border-color: #A51C30; }
                .pcm-venue-item.selected { border-color: #A51C30; background: rgba(165,28,48,0.05); }
            </style>
            <div class="pcm-card">
                <div style="background:linear-gradient(135deg,${headerGradient});padding:1.25rem 1.5rem;color:white;position:relative">
                    <button onclick="ProfileCompletionModal.dismiss()" style="position:absolute;top:0.75rem;right:0.75rem;background:rgba(255,255,255,0.2);border:none;color:white;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem">&times;</button>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem">
                        <span style="font-size:1.25rem">${step.icon}</span>
                        <span style="font-weight:700;font-size:1.125rem">${step.title}</span>
                    </div>
                    <p style="font-size:0.8125rem;opacity:0.9">${step.subtitle}</p>
                    ${incentiveBadge}
                </div>

                ${totalSteps > 1 ? `
                <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;padding:1rem 1.5rem 0">
                    ${this.steps.map((s, i) => `
                        <div style="width:${i === this.currentStep ? '2rem' : '0.5rem'};height:0.5rem;border-radius:0.25rem;background:${i <= this.currentStep ? '#A51C30' : '#E2E8F0'};transition:all 0.3s"></div>
                    `).join('')}
                    <span style="font-size:0.75rem;color:#94A3B8;margin-left:0.25rem">${this.currentStep + 1}/${totalSteps}</span>
                </div>` : ''}

                <div style="padding:1.25rem 1.5rem;max-height:50vh;overflow-y:auto" id="pcm-step-content">
                    ${this.renderStepContent(step)}
                </div>

                <div style="padding:0 1.5rem 1.25rem;display:flex;gap:0.75rem;justify-content:flex-end">
                    <button onclick="ProfileCompletionModal.skip()" style="padding:0.625rem 1.25rem;border-radius:0.75rem;border:none;background:#F1F5F9;color:#64748B;cursor:pointer;font-size:0.875rem;font-weight:500">Omite</button>
                    <button onclick="ProfileCompletionModal.next()" id="pcm-next-btn" style="padding:0.625rem 1.5rem;border-radius:0.75rem;border:none;background:${isInterest ? '#4F46E5' : '#A51C30'};color:white;cursor:pointer;font-size:0.875rem;font-weight:600">${this.currentStep === totalSteps - 1 ? 'Salvează' : 'Continuă'}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
    },

    // ═══════════════════════════════════════════════════════
    //  STEP CONTENT RENDERERS
    // ═══════════════════════════════════════════════════════

    renderStepContent(step) {
        switch (step.id) {
            case 'personal': return this.renderPersonalStep();
            case 'location': return this.renderLocationStep();
            case 'event_types': return this.renderEventTypesStep();
            case 'event_genres': return this.renderEventGenresStep();
            case 'preferred_cities': return this.renderCitiesStep();
            case 'preferred_venues': return this.renderVenuesStep();
            default: return '';
        }
    },

    renderPersonalStep() {
        const c = this.customer;
        const fields = c.profile_completion?.fields || {};
        let html = '<div style="display:flex;flex-direction:column;gap:1rem">';

        if (!fields.birth_date) {
            html += `<div>
                <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Data nașterii</label>
                <input type="date" id="pcm-birth-date" class="pcm-input" value="${c.birth_date || ''}">
            </div>`;
        }
        if (!fields.gender) {
            html += `<div>
                <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Sex</label>
                <div style="display:flex;gap:0.5rem">
                    <button type="button" class="pcm-pill-btn ${c.gender === 'male' ? 'selected' : ''}" onclick="ProfileCompletionModal.selectGender(this, 'male')">Masculin</button>
                    <button type="button" class="pcm-pill-btn ${c.gender === 'female' ? 'selected' : ''}" onclick="ProfileCompletionModal.selectGender(this, 'female')">Feminin</button>
                    <button type="button" class="pcm-pill-btn ${c.gender === 'other' ? 'selected' : ''}" onclick="ProfileCompletionModal.selectGender(this, 'other')">Altul</button>
                </div>
            </div>`;
        }
        if (!fields.phone) {
            html += `<div>
                <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Telefon</label>
                <input type="tel" id="pcm-phone" class="pcm-input" placeholder="+40 7XX XXX XXX" value="${c.phone || ''}">
            </div>`;
        }

        html += '</div>';
        return html;
    },

    renderLocationStep() {
        const c = this.customer;
        const billing = c.settings?.billing_address || {};
        const judete = [
            'Alba','Arad','Arges','Bacau','Bihor','Bistrita-Nasaud','Botosani','Braila','Brasov',
            'Bucuresti','Buzau','Calarasi','Caras-Severin','Cluj','Constanta','Covasna','Dambovita',
            'Dolj','Galati','Giurgiu','Gorj','Harghita','Hunedoara','Ialomita','Iasi','Ilfov',
            'Maramures','Mehedinti','Mures','Neamt','Olt','Prahova','Salaj','Satu Mare',
            'Sibiu','Suceava','Teleorman','Timis','Tulcea','Valcea','Vaslui','Vrancea'
        ];
        const currentState = c.state || billing.state || '';
        const currentCity = c.city || billing.city || '';

        return `
            <div style="display:flex;flex-direction:column;gap:1rem">
                <div>
                    <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Județul</label>
                    <select id="pcm-state" class="pcm-select">
                        <option value="">Selectează județul</option>
                        ${judete.map(j => `<option value="${j}" ${currentState === j ? 'selected' : ''}>${j}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Orașul</label>
                    <input type="text" id="pcm-city" class="pcm-input" placeholder="Ex: București" value="${currentCity}">
                </div>
            </div>
        `;
    },

    renderEventTypesStep() {
        const existing = this.customer.settings?.interests?.event_types || [];
        this.selectedEventTypes = [...existing];

        if (this.eventTypes.length === 0) {
            return '<p style="text-align:center;color:#94A3B8;padding:1rem 0">Se încarcă tipurile de evenimente...</p>';
        }

        let html = '<div style="display:flex;flex-wrap:wrap;gap:0.5rem">';
        const renderType = (type) => {
            const isSelected = this.selectedEventTypes.includes(type.slug);
            html += `<div class="pcm-chip ${isSelected ? 'selected' : ''}" onclick="ProfileCompletionModal.toggleEventType(this, '${type.slug}')">${type.name}</div>`;
        };

        this.eventTypes.forEach(type => {
            renderType(type);
            if (type.children) {
                type.children.forEach(child => renderType(child));
            }
        });

        html += '</div>';
        return html;
    },

    renderEventGenresStep() {
        const existing = this.customer.settings?.interests?.event_genres || this.customer.settings?.interests?.music_genres || [];
        this.selectedEventGenres = [...existing];

        if (this.eventGenres.length === 0) {
            return '<p style="text-align:center;color:#94A3B8;padding:1rem 0">Se încarcă genurile muzicale...</p>';
        }

        let html = '<div style="display:flex;flex-wrap:wrap;gap:0.5rem">';
        this.eventGenres.forEach(genre => {
            const name = typeof genre.name === 'object' ? (genre.name.ro || genre.name.en || Object.values(genre.name)[0]) : genre.name;
            const slug = genre.slug || name;
            const isSelected = this.selectedEventGenres.includes(slug);
            html += `<div class="pcm-chip ${isSelected ? 'selected' : ''}" onclick="ProfileCompletionModal.toggleEventGenre(this, '${slug}')">${name}</div>`;
        });
        html += '</div>';
        return html;
    },

    renderCitiesStep() {
        const existing = this.customer.settings?.interests?.preferred_cities || [];
        this.selectedCities = [...existing];

        let html = '';

        // Smart suggestion
        if (this.smartSuggestions?.suggested_cities?.length > 0) {
            const topCities = this.smartSuggestions.suggested_cities.slice(0, 3);
            if (topCities.length === 1) {
                html += `<div class="pcm-suggestion">
                    <p class="pcm-suggestion-text">💡 Din ce știm până acum, bănuim că orașul tău preferat este <strong>${topCities[0].name}</strong></p>
                </div>`;
            } else {
                const cityNames = topCities.map(c => `<strong>${c.name}</strong>`).join(', ');
                html += `<div class="pcm-suggestion">
                    <p class="pcm-suggestion-text">💡 Din ce știm până acum, orașele tale preferate par a fi ${cityNames}</p>
                </div>`;
            }
        } else if (this.customer.city) {
            html += `<div class="pcm-suggestion">
                <p class="pcm-suggestion-text">💡 Din profilul tău, orașul tău este <strong>${this.customer.city}</strong>. Mergi la evenimente și în alte orașe?</p>
            </div>`;
        }

        // Romanian cities commonly hosting events
        const popularCities = [
            'București', 'Cluj-Napoca', 'Timișoara', 'Iași', 'Brașov', 'Constanța',
            'Sibiu', 'Oradea', 'Craiova', 'Galați', 'Arad', 'Ploiești',
            'Pitești', 'Târgu Mureș', 'Baia Mare', 'Alba Iulia', 'Suceava', 'Bacău'
        ];

        // Merge suggested cities with popular ones, deduplicate
        const suggestedNames = (this.smartSuggestions?.suggested_cities || []).map(c => c.name);
        const allCities = [...new Set([...suggestedNames, ...popularCities])];

        html += '<div style="display:flex;flex-wrap:wrap;gap:0.5rem">';
        allCities.forEach(city => {
            const isSelected = this.selectedCities.includes(city);
            const isSuggested = suggestedNames.includes(city);
            html += `<div class="pcm-chip ${isSelected ? 'selected' : ''}" onclick="ProfileCompletionModal.toggleCity(this, '${city.replace(/'/g, "\\'")}')">${isSuggested ? '⭐ ' : ''}${city}</div>`;
        });
        html += '</div>';

        return html;
    },

    renderVenuesStep() {
        const existing = this.customer.settings?.interests?.preferred_venues || [];
        this.selectedVenues = [...existing];

        let html = '';

        // Smart suggestion
        if (this.smartSuggestions?.suggested_venues?.length > 0) {
            const topVenues = this.smartSuggestions.suggested_venues.slice(0, 2);
            const venueNames = topVenues.map(v => `<strong>${v.name}</strong> (${v.city})`).join(' și ');
            html += `<div class="pcm-suggestion">
                <p class="pcm-suggestion-text">💡 Ai mai fost la ${venueNames}. Adaugă-le la favorite?</p>
            </div>`;
        }

        // Filter venues by selected cities
        const selectedCities = this.customer.settings?.interests?.preferred_cities || [];
        let venues = this.allVenues;
        if (selectedCities.length > 0) {
            venues = venues.filter(v => selectedCities.includes(v.city));
        }

        if (venues.length === 0 && !this.smartSuggestions) {
            return '<p style="text-align:center;color:#94A3B8;padding:1rem 0">Se încarcă locațiile...</p>';
        }

        if (venues.length === 0) {
            return `<div style="text-align:center;color:#94A3B8;padding:1rem 0">
                <p>Nu am găsit locații din orașele selectate în istoricul tău.</p>
                <p style="font-size:0.8125rem;margin-top:0.5rem">Poți sări acest pas — vom învăța din achizițiile tale viitoare.</p>
            </div>`;
        }

        html += '<div style="display:flex;flex-direction:column;gap:0.5rem">';
        venues.forEach(venue => {
            const isSelected = this.selectedVenues.includes(venue.id);
            html += `<div class="pcm-venue-item ${isSelected ? 'selected' : ''}" onclick="ProfileCompletionModal.toggleVenue(this, ${venue.id})">
                <div style="flex:1">
                    <div style="font-weight:600;font-size:0.875rem;color:#1E293B">${venue.name}</div>
                    <div style="font-size:0.75rem;color:#64748B">${venue.city}</div>
                </div>
                <div style="width:24px;height:24px;border-radius:50%;border:2px solid ${isSelected ? '#A51C30' : '#E2E8F0'};display:flex;align-items:center;justify-content:center;background:${isSelected ? '#A51C30' : 'white'}">
                    ${isSelected ? '<span style="color:white;font-size:0.75rem">✓</span>' : ''}
                </div>
            </div>`;
        });
        html += '</div>';

        return html;
    },

    // ═══════════════════════════════════════════════════════
    //  SELECTION HANDLERS
    // ═══════════════════════════════════════════════════════

    selectGender(el, value) {
        this._selectedGender = value;
        el.parentElement.querySelectorAll('.pcm-pill-btn').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
    },

    toggleEventType(el, slug) {
        const idx = this.selectedEventTypes.indexOf(slug);
        if (idx >= 0) {
            this.selectedEventTypes.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            this.selectedEventTypes.push(slug);
            el.classList.add('selected');
        }
    },

    toggleEventGenre(el, slug) {
        const idx = this.selectedEventGenres.indexOf(slug);
        if (idx >= 0) {
            this.selectedEventGenres.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            this.selectedEventGenres.push(slug);
            el.classList.add('selected');
        }
    },

    toggleCity(el, name) {
        const idx = this.selectedCities.indexOf(name);
        if (idx >= 0) {
            this.selectedCities.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            this.selectedCities.push(name);
            el.classList.add('selected');
        }
    },

    toggleVenue(el, id) {
        const idx = this.selectedVenues.indexOf(id);
        if (idx >= 0) {
            this.selectedVenues.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            this.selectedVenues.push(id);
            el.classList.add('selected');
        }
    },

    // ═══════════════════════════════════════════════════════
    //  SAVE + NAVIGATE
    // ═══════════════════════════════════════════════════════

    async next() {
        const btn = document.getElementById('pcm-next-btn');
        const originalText = btn.textContent;
        btn.textContent = 'Se salvează...';
        btn.disabled = true;

        try {
            await this.saveCurrentStep();

            if (this.currentStep < this.steps.length - 1) {
                this.currentStep++;
                this.render();
            } else {
                this.showSuccess();
            }
        } catch (error) {
            console.error('Step save error:', error);
            btn.textContent = originalText;
            btn.disabled = false;
        }
    },

    skip() {
        if (this.currentStep < this.steps.length - 1) {
            this.currentStep++;
            this.render();
        } else {
            this.dismiss();
        }
    },

    async saveCurrentStep() {
        const step = this.steps[this.currentStep];

        switch (step.id) {
            case 'personal': {
                const data = {};
                const birthEl = document.getElementById('pcm-birth-date');
                const phoneEl = document.getElementById('pcm-phone');
                if (birthEl && birthEl.value) data.birth_date = birthEl.value;
                if (phoneEl && phoneEl.value) data.phone = phoneEl.value;
                if (this._selectedGender) data.gender = this._selectedGender;
                if (Object.keys(data).length > 0) {
                    await AmbiletAPI.customer.updateProfile(data);
                }
                break;
            }
            case 'location': {
                const state = document.getElementById('pcm-state')?.value;
                const city = document.getElementById('pcm-city')?.value;
                const profileData = {};
                const settingsData = {};
                if (state) {
                    profileData.state = state;
                    settingsData.billing_address = { state };
                }
                if (city) {
                    profileData.city = city;
                    settingsData.billing_address = { ...(settingsData.billing_address || {}), city };
                }
                if (Object.keys(profileData).length > 0) {
                    await AmbiletAPI.customer.updateProfile(profileData);
                }
                if (Object.keys(settingsData).length > 0) {
                    await AmbiletAPI.put('/customer/settings', settingsData);
                }
                break;
            }
            case 'event_types': {
                if (this.selectedEventTypes.length > 0) {
                    await AmbiletAPI.put('/customer/settings', {
                        interests: { event_types: this.selectedEventTypes },
                        profiling: {
                            completed_steps: [...(this.customer.settings?.profiling?.completed_steps || []), 'event_types'],
                            last_modal_at: new Date().toISOString(),
                        },
                    });
                }
                break;
            }
            case 'event_genres': {
                if (this.selectedEventGenres.length > 0) {
                    await AmbiletAPI.put('/customer/settings', {
                        interests: { event_genres: this.selectedEventGenres },
                        profiling: {
                            completed_steps: [...(this.customer.settings?.profiling?.completed_steps || []), 'event_genres'],
                            last_modal_at: new Date().toISOString(),
                        },
                    });
                }
                break;
            }
            case 'preferred_cities': {
                if (this.selectedCities.length > 0) {
                    await AmbiletAPI.put('/customer/settings', {
                        interests: { preferred_cities: this.selectedCities },
                        profiling: {
                            completed_steps: [...(this.customer.settings?.profiling?.completed_steps || []), 'preferred_cities'],
                            last_modal_at: new Date().toISOString(),
                        },
                    });
                }
                break;
            }
            case 'preferred_venues': {
                if (this.selectedVenues.length > 0) {
                    await AmbiletAPI.put('/customer/settings', {
                        interests: { preferred_venues: this.selectedVenues },
                        profiling: {
                            completed_steps: [...(this.customer.settings?.profiling?.completed_steps || []), 'preferred_venues'],
                            last_modal_at: new Date().toISOString(),
                        },
                    });
                }
                break;
            }
        }
    },

    // ═══════════════════════════════════════════════════════
    //  SUCCESS SCREEN
    // ═══════════════════════════════════════════════════════

    showSuccess() {
        const overlay = document.getElementById('pcm-overlay');
        if (!overlay) return;
        const card = overlay.querySelector('.pcm-card');
        const isInterest = this.mode.startsWith('interest_');

        if (isInterest) {
            card.innerHTML = `
                <div style="padding:2.5rem 1.5rem;text-align:center">
                    <div style="font-size:3rem;margin-bottom:0.75rem">✨</div>
                    <h3 style="font-size:1.25rem;font-weight:700;color:#1E293B;margin-bottom:0.5rem">Mulțumim!</h3>
                    <p style="color:#64748B;font-size:0.9375rem;margin-bottom:1rem">Preferințele tale au fost salvate.</p>
                    <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.75rem 1.5rem;background:linear-gradient(135deg,#4F46E5,#7C3AED);color:white;border-radius:2rem;font-weight:600;font-size:0.9375rem;margin-bottom:1.5rem">
                        <span>🎯</span> Recomandări mai bune activate
                    </div>
                    <p style="color:#94A3B8;font-size:0.8125rem;margin-bottom:1.5rem">Vom folosi aceste preferințe pentru a-ți arăta evenimentele potrivite!</p>
                    <button onclick="ProfileCompletionModal.dismiss()" style="padding:0.75rem 2rem;border-radius:0.75rem;border:none;background:#4F46E5;color:white;cursor:pointer;font-size:0.9375rem;font-weight:600;width:100%">Super!</button>
                </div>
            `;
        } else {
            card.innerHTML = `
                <div style="padding:2.5rem 1.5rem;text-align:center">
                    <div style="font-size:3rem;margin-bottom:0.75rem">🎉</div>
                    <h3 style="font-size:1.25rem;font-weight:700;color:#1E293B;margin-bottom:0.5rem">Felicitări!</h3>
                    <p style="color:#64748B;font-size:0.9375rem;margin-bottom:1rem">Profilul tău a fost actualizat cu succes.</p>
                    <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.75rem 1.5rem;background:linear-gradient(135deg,#A51C30,#E8336D);color:white;border-radius:2rem;font-weight:700;font-size:1.125rem;margin-bottom:1.5rem">
                        <span>⚡</span> +20 XP
                    </div>
                    <p style="color:#94A3B8;font-size:0.8125rem;margin-bottom:1.5rem">Vei primi recomandări mai bune de acum înainte!</p>
                    <button onclick="ProfileCompletionModal.dismiss()" style="padding:0.75rem 2rem;border-radius:0.75rem;border:none;background:#A51C30;color:white;cursor:pointer;font-size:0.9375rem;font-weight:600;width:100%">Gata!</button>
                </div>
            `;
        }

        setTimeout(() => this.dismiss(), 8000);
    }
};

// Auto-init on page load
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(() => ProfileCompletionModal.init(), 500);
});
