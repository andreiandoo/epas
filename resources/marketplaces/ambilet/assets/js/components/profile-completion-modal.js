/**
 * Profile Completion Modal
 * Smart multi-step modal that progressively collects profile data
 * Awards XP/points when profile reaches 80% completion
 */
const ProfileCompletionModal = {
    isOpen: false,
    currentStep: 0,
    steps: [],
    categories: [],
    genres: [],
    customer: null,
    selectedCategories: [],
    selectedGenres: [],
    mode: 'profile', // 'profile' = full completion, 'interests_only' = just interests

    /**
     * Initialize — auto-triggers after delay if conditions met
     */
    async init() {
        if (!AmbiletAuth?.isAuthenticated()) return;

        // Load customer data first
        try {
            const res = await AmbiletAPI.customer.getProfile();
            if (!res.success || !res.data?.customer) return;
            this.customer = res.data.customer;
        } catch { return; }

        const completion = this.customer.profile_completion;
        if (!completion) return;

        const hasInterests = !!completion.fields?.interests;

        // Fully complete — nothing to show
        if (completion.percentage >= 80 && hasInterests) return;

        if (completion.percentage < 80) {
            // Standard profile completion flow
            if (sessionStorage.getItem('pcm_dismissed')) return;
            this.mode = 'profile';
            this.buildSteps();
        } else {
            // Interests-only mode — profile is done but interests missing
            // Gentler: max once per 7 days, not every session
            const lastDismissed = localStorage.getItem('pcm_interests_dismissed_at');
            if (lastDismissed && (Date.now() - parseInt(lastDismissed)) < 7 * 24 * 60 * 60 * 1000) return;
            if (sessionStorage.getItem('pcm_interests_dismissed')) return;
            this.mode = 'interests_only';
            this.steps = [{
                id: 'interests',
                title: 'Personalizează-ți experiența',
                subtitle: 'Spune-ne ce te interesează și îți vom recomanda evenimentele potrivite',
                icon: '✨',
            }];
        }

        if (this.steps.length === 0) return;

        // Longer delay for interests-only (less urgent)
        const delay = this.mode === 'interests_only' ? 8000 : 5000;
        setTimeout(() => this.show(), delay);
    },

    /**
     * Trigger after purchase (bypasses session/localStorage flags, shorter delay)
     * Post-purchase = best moment to ask for interests (customer is engaged)
     */
    triggerAfterPurchase() {
        sessionStorage.removeItem('pcm_dismissed');
        sessionStorage.removeItem('pcm_interests_dismissed');
        localStorage.removeItem('pcm_interests_dismissed_at');
        setTimeout(async () => {
            if (!AmbiletAuth?.isAuthenticated()) return;
            try {
                const res = await AmbiletAPI.customer.getProfile();
                if (!res.success || !res.data?.customer) return;
                this.customer = res.data.customer;
                const completion = this.customer.profile_completion;
                if (!completion) return;
                const hasInterests = !!completion.fields?.interests;
                if (completion.percentage >= 80 && hasInterests) return;

                if (completion.percentage < 80) {
                    this.mode = 'profile';
                    this.buildSteps();
                } else {
                    this.mode = 'interests_only';
                    this.steps = [{
                        id: 'interests',
                        title: 'Personalizează-ți experiența',
                        subtitle: 'Tocmai ai cumpărat bilete! Spune-ne ce te mai interesează pentru recomandări mai bune.',
                        icon: '✨',
                    }];
                }

                if (this.steps.length === 0) return;
                this.show();
            } catch {}
        }, 3000);
    },

    /**
     * Build steps based on missing fields
     */
    buildSteps() {
        this.steps = [];
        const fields = this.customer.profile_completion?.fields || {};

        // Step 1: Personal info (if any missing)
        const personalMissing = !fields.birth_date || !fields.gender || !fields.phone;
        if (personalMissing) {
            this.steps.push({
                id: 'personal',
                title: 'Despre tine',
                subtitle: 'Ajută-ne să îți personalizăm experiența',
                icon: '👤',
            });
        }

        // Step 2: Location (if city/state missing)
        const locationMissing = !fields.city || !fields.state;
        if (locationMissing) {
            this.steps.push({
                id: 'location',
                title: 'De unde ești?',
                subtitle: 'Îți vom recomanda evenimente din zona ta',
                icon: '📍',
            });
        }

        // Step 3: Interests (always shown if no interests yet)
        if (!fields.interests) {
            this.steps.push({
                id: 'interests',
                title: 'Ce te pasionează?',
                subtitle: 'Selectează categoriile care te interesează',
                icon: '🎯',
            });
        }
    },

    /**
     * Show the modal
     */
    show() {
        if (this.isOpen) return;
        this.isOpen = true;
        this.currentStep = 0;

        // Preload categories and genres
        this.loadTaxonomies();

        this.render();
        document.body.style.overflow = 'hidden';
    },

    /**
     * Hide the modal
     */
    dismiss() {
        this.isOpen = false;
        if (this.mode === 'interests_only') {
            sessionStorage.setItem('pcm_interests_dismissed', '1');
            localStorage.setItem('pcm_interests_dismissed_at', Date.now().toString());
        } else {
            sessionStorage.setItem('pcm_dismissed', '1');
        }
        const el = document.getElementById('pcm-overlay');
        if (el) el.remove();
        document.body.style.overflow = '';
    },

    /**
     * Load categories and genres for interests step
     */
    async loadTaxonomies() {
        try {
            const [catRes, genreRes] = await Promise.all([
                AmbiletAPI.get('/marketplace-events/categories'),
                AmbiletAPI.get('/event-genres'),
            ]);
            if (catRes.success && catRes.data) {
                this.categories = Array.isArray(catRes.data) ? catRes.data : (catRes.data.categories || catRes.data.data || []);
            }
            if (genreRes.success && genreRes.data) {
                this.genres = Array.isArray(genreRes.data) ? genreRes.data : (genreRes.data.genres || genreRes.data.data || []);
            }
        } catch (e) {
            console.error('Failed to load taxonomies:', e);
        }
    },

    /**
     * Render the entire modal
     */
    render() {
        const existing = document.getElementById('pcm-overlay');
        if (existing) existing.remove();

        const step = this.steps[this.currentStep];
        const totalSteps = this.steps.length;
        const isInterestsOnly = this.mode === 'interests_only';

        const overlay = document.createElement('div');
        overlay.id = 'pcm-overlay';
        overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center p-4';
        overlay.style.cssText = 'background:rgba(0,0,0,0.6);animation:pcmFadeIn 0.3s ease';
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) this.dismiss();
        });

        // Different incentive badge based on mode
        const incentiveBadge = isInterestsOnly
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
                .pcm-card { animation: pcmSlideUp 0.3s ease; max-width: 440px; width: 100%; background: white; border-radius: 1.25rem; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
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
            </style>
            <div class="pcm-card">
                <!-- Header -->
                <div style="background:linear-gradient(135deg,${isInterestsOnly ? '#4F46E5 0%,#7C3AED 100%' : '#A51C30 0%,#8B1728 100%'});padding:1.25rem 1.5rem;color:white;position:relative">
                    <button onclick="ProfileCompletionModal.dismiss()" style="position:absolute;top:0.75rem;right:0.75rem;background:rgba(255,255,255,0.2);border:none;color:white;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1rem">&times;</button>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.25rem">
                        <span style="font-size:1.25rem">${step.icon}</span>
                        <span style="font-weight:700;font-size:1.125rem">${step.title}</span>
                    </div>
                    <p style="font-size:0.8125rem;opacity:0.9">${step.subtitle}</p>
                    ${incentiveBadge}
                </div>

                <!-- Progress dots -->
                <div style="display:flex;align-items:center;justify-content:center;gap:0.5rem;padding:1rem 1.5rem 0">
                    ${this.steps.map((s, i) => `
                        <div style="width:${i === this.currentStep ? '2rem' : '0.5rem'};height:0.5rem;border-radius:0.25rem;background:${i <= this.currentStep ? '#A51C30' : '#E2E8F0'};transition:all 0.3s"></div>
                    `).join('')}
                    <span style="font-size:0.75rem;color:#94A3B8;margin-left:0.25rem">${this.currentStep + 1}/${totalSteps}</span>
                </div>

                <!-- Step content -->
                <div style="padding:1.25rem 1.5rem" id="pcm-step-content">
                    ${this.renderStepContent(step)}
                </div>

                <!-- Actions -->
                <div style="padding:0 1.5rem 1.25rem;display:flex;gap:0.75rem;justify-content:flex-end">
                    <button onclick="ProfileCompletionModal.skip()" style="padding:0.625rem 1.25rem;border-radius:0.75rem;border:none;background:#F1F5F9;color:#64748B;cursor:pointer;font-size:0.875rem;font-weight:500">Omite</button>
                    <button onclick="ProfileCompletionModal.next()" id="pcm-next-btn" style="padding:0.625rem 1.5rem;border-radius:0.75rem;border:none;background:#A51C30;color:white;cursor:pointer;font-size:0.875rem;font-weight:600">${this.currentStep === totalSteps - 1 ? 'Finalizează' : 'Continuă'}</button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
    },

    /**
     * Render content for a specific step
     */
    renderStepContent(step) {
        switch (step.id) {
            case 'personal': return this.renderPersonalStep();
            case 'location': return this.renderLocationStep();
            case 'interests': return this.renderInterestsStep();
            default: return '';
        }
    },

    renderPersonalStep() {
        const c = this.customer;
        const fields = c.profile_completion?.fields || {};

        let html = '<div style="display:flex;flex-direction:column;gap:1rem">';

        if (!fields.birth_date) {
            html += `
                <div>
                    <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Data nașterii</label>
                    <input type="date" id="pcm-birth-date" class="pcm-input" value="${c.birth_date || ''}">
                </div>
            `;
        }

        if (!fields.gender) {
            html += `
                <div>
                    <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Sex</label>
                    <div style="display:flex;gap:0.5rem">
                        <button type="button" class="pcm-pill-btn ${c.gender === 'male' ? 'selected' : ''}" onclick="ProfileCompletionModal.selectGender(this, 'male')">Masculin</button>
                        <button type="button" class="pcm-pill-btn ${c.gender === 'female' ? 'selected' : ''}" onclick="ProfileCompletionModal.selectGender(this, 'female')">Feminin</button>
                        <button type="button" class="pcm-pill-btn ${c.gender === 'other' ? 'selected' : ''}" onclick="ProfileCompletionModal.selectGender(this, 'other')">Altul</button>
                    </div>
                </div>
            `;
        }

        if (!fields.phone) {
            html += `
                <div>
                    <label style="display:block;font-size:0.8125rem;font-weight:500;color:#475569;margin-bottom:0.375rem">Telefon</label>
                    <input type="tel" id="pcm-phone" class="pcm-input" placeholder="+40 7XX XXX XXX" value="${c.phone || ''}">
                </div>
            `;
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

    renderInterestsStep() {
        const existingCats = this.customer.settings?.interests?.event_categories || [];
        const existingGenres = this.customer.settings?.interests?.music_genres || [];
        this.selectedCategories = [...existingCats];
        this.selectedGenres = [...existingGenres];

        let html = '';

        // Event categories
        if (this.categories.length > 0) {
            html += '<div style="margin-bottom:1rem"><p style="font-size:0.8125rem;font-weight:600;color:#475569;margin-bottom:0.5rem">Tipuri de evenimente</p>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:0.5rem">';
            this.categories.forEach(cat => {
                const name = typeof cat.name === 'object' ? (cat.name.ro || cat.name.en || Object.values(cat.name)[0]) : cat.name;
                const slug = cat.slug || name;
                const emoji = cat.icon_emoji || '🎵';
                const isSelected = this.selectedCategories.includes(slug);
                html += `<div class="pcm-chip ${isSelected ? 'selected' : ''}" onclick="ProfileCompletionModal.toggleCategory(this, '${slug}')">${emoji} ${name}</div>`;
            });
            html += '</div></div>';
        }

        // Music genres
        if (this.genres.length > 0) {
            html += '<div><p style="font-size:0.8125rem;font-weight:600;color:#475569;margin-bottom:0.5rem">Genuri muzicale</p>';
            html += '<div style="display:flex;flex-wrap:wrap;gap:0.5rem">';
            this.genres.forEach(genre => {
                const name = typeof genre.name === 'object' ? (genre.name.ro || genre.name.en || Object.values(genre.name)[0]) : genre.name;
                const slug = genre.slug || name;
                const isSelected = this.selectedGenres.includes(slug);
                html += `<div class="pcm-chip ${isSelected ? 'selected' : ''}" onclick="ProfileCompletionModal.toggleGenre(this, '${slug}')">${name}</div>`;
            });
            html += '</div></div>';
        }

        if (!this.categories.length && !this.genres.length) {
            html = '<p style="text-align:center;color:#94A3B8;padding:1rem 0">Se încarcă categoriile...</p>';
            // Retry load
            setTimeout(async () => {
                await this.loadTaxonomies();
                const content = document.getElementById('pcm-step-content');
                if (content && this.steps[this.currentStep]?.id === 'interests') {
                    content.innerHTML = this.renderInterestsStep();
                }
            }, 1000);
        }

        return html;
    },

    // Selection helpers
    _selectedGender: null,

    selectGender(el, value) {
        this._selectedGender = value;
        el.parentElement.querySelectorAll('.pcm-pill-btn').forEach(b => b.classList.remove('selected'));
        el.classList.add('selected');
    },

    toggleCategory(el, slug) {
        const idx = this.selectedCategories.indexOf(slug);
        if (idx >= 0) {
            this.selectedCategories.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            this.selectedCategories.push(slug);
            el.classList.add('selected');
        }
    },

    toggleGenre(el, slug) {
        const idx = this.selectedGenres.indexOf(slug);
        if (idx >= 0) {
            this.selectedGenres.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            this.selectedGenres.push(slug);
            el.classList.add('selected');
        }
    },

    /**
     * Save current step data and advance
     */
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
                // All done — show success
                this.showSuccess();
            }
        } catch (error) {
            console.error('Step save error:', error);
            btn.textContent = originalText;
            btn.disabled = false;
        }
    },

    /**
     * Skip current step
     */
    skip() {
        if (this.currentStep < this.steps.length - 1) {
            this.currentStep++;
            this.render();
        } else {
            this.dismiss();
        }
    },

    /**
     * Save data for the current step
     */
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
                    settingsData.billing_address = {
                        ...(settingsData.billing_address || {}),
                        city
                    };
                }

                if (Object.keys(profileData).length > 0) {
                    await AmbiletAPI.customer.updateProfile(profileData);
                }
                if (Object.keys(settingsData).length > 0) {
                    await AmbiletAPI.put('/customer/settings', settingsData);
                }
                break;
            }
            case 'interests': {
                const interests = {};
                if (this.selectedCategories.length > 0) {
                    interests.event_categories = this.selectedCategories;
                }
                if (this.selectedGenres.length > 0) {
                    interests.music_genres = this.selectedGenres;
                }
                if (Object.keys(interests).length > 0) {
                    await AmbiletAPI.put('/customer/settings', { interests });
                }
                break;
            }
        }
    },

    /**
     * Show success screen — different for profile vs interests_only
     */
    showSuccess() {
        const overlay = document.getElementById('pcm-overlay');
        if (!overlay) return;

        const isInterestsOnly = this.mode === 'interests_only';
        const card = overlay.querySelector('.pcm-card');

        if (isInterestsOnly) {
            card.innerHTML = `
                <div style="padding:2.5rem 1.5rem;text-align:center">
                    <div style="font-size:3rem;margin-bottom:0.75rem">✨</div>
                    <h3 style="font-size:1.25rem;font-weight:700;color:#1E293B;margin-bottom:0.5rem">Mulțumim!</h3>
                    <p style="color:#64748B;font-size:0.9375rem;margin-bottom:1rem">Preferințele tale au fost salvate.</p>
                    <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.75rem 1.5rem;background:linear-gradient(135deg,#4F46E5,#7C3AED);color:white;border-radius:2rem;font-weight:600;font-size:0.9375rem;margin-bottom:1.5rem">
                        <span>🎯</span> Recomandări personalizate activate
                    </div>
                    <p style="color:#94A3B8;font-size:0.8125rem;margin-bottom:1.5rem">De acum înainte vei vedea evenimentele care ți se potrivesc cel mai bine!</p>
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

        // Auto dismiss after 8s
        setTimeout(() => this.dismiss(), 8000);
    }
};

// Auto-init on page load
document.addEventListener('DOMContentLoaded', () => {
    // 5s delay to not be intrusive
    setTimeout(() => ProfileCompletionModal.init(), 500);
});
