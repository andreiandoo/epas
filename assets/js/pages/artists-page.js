/**
 * Artists Page Controller
 * Handles artists listing, filtering, sorting, and pagination
 */
const ArtistsPage = {
    filters: {
        genre: '',
        letter: '',
        sort: 'popular',
        page: 1,
        search: ''
    },
    totalArtists: 0,

    /**
     * Initialize the artists page
     * @param {Object} initialFilters - Initial filter values from PHP
     */
    async init(initialFilters = {}) {
        // Apply initial filters
        this.filters = { ...this.filters, ...initialFilters };

        // Load all data in parallel
        await Promise.all([
            this.loadFeaturedArtists(),
            this.loadTrendingArtists(),
            this.loadArtists(),
            this.loadGenreCounts()
        ]);

        this.bindEvents();
    },

    /**
     * Load featured artists section
     */
    async loadFeaturedArtists() {
        const container = document.getElementById('featuredGrid');
        if (!container) return;

        try {
            const response = await AmbiletAPI.get('/artists/featured?limit=4');
            const artists = response.data?.artists || response.data || [];

            if (artists.length > 0) {
                container.innerHTML = artists.map(artist => this.renderFeaturedCard(artist)).join('');
            }
        } catch (e) {
            console.warn('Failed to load featured artists:', e);
            container.innerHTML = '<p class="col-span-4 py-8 text-center text-muted">Nu am putut încărca artiștii populari</p>';
        }
    },

    /**
     * Load trending artists section
     */
    async loadTrendingArtists() {
        const container = document.getElementById('trendingList');
        if (!container) return;

        try {
            const response = await AmbiletAPI.get('/artists/trending?limit=4');
            const artists = response.data?.artists || response.data || [];

            if (artists.length > 0) {
                container.innerHTML = artists.map((artist, index) =>
                    this.renderTrendingItem(artist, index + 1)
                ).join('');
            }
        } catch (e) {
            console.warn('Failed to load trending artists:', e);
        }
    },

    /**
     * Load main artists grid
     */
    async loadArtists() {
        const container = document.getElementById('artistsGrid');
        if (!container) return;

        try {
            const params = new URLSearchParams({
                page: this.filters.page,
                per_page: 12,
                sort: this.filters.sort
            });

            if (this.filters.genre) params.append('genre', this.filters.genre);
            if (this.filters.letter) params.append('letter', this.filters.letter);
            if (this.filters.search) params.append('search', this.filters.search);

            const response = await AmbiletAPI.get('/artists?' + params.toString());

            if (response.data) {
                const artists = response.data;
                const meta = response.meta || {};
                this.totalArtists = meta.total || artists.length;

                // Update results count
                const resultsEl = document.getElementById('resultsCount');
                if (resultsEl) {
                    resultsEl.textContent = this.totalArtists;
                }

                if (artists.length > 0) {
                    container.innerHTML = artists.map(artist =>
                        this.renderArtistCard(artist)
                    ).join('');
                } else {
                    container.innerHTML = this.getEmptyState();
                }

                this.renderPagination(meta);
            }
        } catch (e) {
            console.error('Failed to load artists:', e);
            container.innerHTML = '<p class="py-8 text-center col-span-full text-error">Eroare la încărcarea artiștilor</p>';
        }
    },

    /**
     * Load genre counts for filter tabs
     */
    async loadGenreCounts() {
        try {
            // Get total artists count
            const totalResponse = await AmbiletAPI.get('/artists?per_page=1');
            const totalArtists = totalResponse.meta?.total || 0;

            const countAllEl = document.getElementById('countAll');
            if (countAllEl) {
                countAllEl.textContent = totalArtists || '--';
            }

            // Get genre-specific counts
            const response = await AmbiletAPI.get('/artists/genre-counts');
            const genres = response.data?.genres || [];

            const genreMap = {};
            genres.forEach(g => {
                genreMap[g.slug] = g.count;
            });

            // Update genre count badges
            const genreCountMap = {
                'countPop': 'pop',
                'countRock': 'rock',
                'countHipHop': 'hip-hop',
                'countElectronic': 'electronic',
                'countStandup': 'stand-up',
                'countDJ': 'dj'
            };

            Object.entries(genreCountMap).forEach(([elementId, slug]) => {
                const el = document.getElementById(elementId);
                if (el) {
                    el.textContent = genreMap[slug] || '--';
                }
            });
        } catch (e) {
            console.warn('Failed to load genre counts:', e);
        }
    },

    /**
     * Render featured artist card
     */
    renderFeaturedCard(artist) {
        const verifiedBadge = artist.is_verified ?
            '<span class="inline-flex items-center justify-center w-5 h-5 ml-1 bg-blue-500 rounded-full">' +
                '<svg class="w-3 h-3 text-white" fill="currentColor" viewBox="0 0 24 24">' +
                    '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>' +
                '</svg>' +
            '</span>' : '';

        const genre = artist.genres?.[0]?.name || 'Artist';
        const followers = this.formatFollowers(this.calculateTotalFollowers(artist));
        const eventsCount = artist.upcoming_events_count || 0;
        const artistImage = artist.portrait || artist.logo || artist.image || '/assets/images/default-artist.png';

        return '<a href="/artist/' + this.escapeHtml(artist.slug) + '" class="relative overflow-hidden group rounded-2xl aspect-[3/4]">' +
            '<img src="' + this.escapeHtml(artistImage) + '" alt="' + this.escapeHtml(artist.name) + '" ' +
                'class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110" loading="lazy">' +
            '<div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent"></div>' +
            '<div class="absolute bottom-0 left-0 right-0 p-6">' +
                '<span class="inline-block px-3 py-1 mb-3 text-xs font-semibold text-white uppercase rounded-full bg-white/15 backdrop-blur-sm">' +
                    this.escapeHtml(genre) + '</span>' +
                '<h3 class="mb-2 text-xl font-extrabold leading-tight text-white">' +
                    this.escapeHtml(artist.name) + verifiedBadge + '</h3>' +
                '<div class="flex items-center gap-4 text-sm text-white/80">' +
                    '<span class="flex items-center gap-1.5">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>' +
                            '<line x1="16" y1="2" x2="16" y2="6"/>' +
                            '<line x1="8" y1="2" x2="8" y2="6"/>' +
                            '<line x1="3" y1="10" x2="21" y2="10"/>' +
                        '</svg>' +
                        (eventsCount === 0 ? 'Fără evenimente' : (eventsCount === 1 ? '1 eveniment' : eventsCount + ' evenimente')) +
                    '</span>' +
                    '<span class="flex items-center gap-1.5">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>' +
                        '</svg>' +
                        followers +
                    '</span>' +
                '</div>' +
            '</div>' +
        '</a>';
    },

    /**
     * Render trending artist item
     */
    renderTrendingItem(artist, rank) {
        const rankClass = rank === 1 ? 'bg-gradient-to-br from-yellow-400 to-yellow-600' :
                         rank === 2 ? 'bg-gradient-to-br from-gray-400 to-gray-500' :
                         rank === 3 ? 'bg-gradient-to-br from-amber-600 to-amber-700' : 'bg-secondary';

        const verifiedBadge = artist.is_verified ?
            '<span class="inline-flex items-center justify-center w-4 h-4 ml-1 bg-blue-500 rounded-full">' +
                '<svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 24 24">' +
                    '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>' +
                '</svg>' +
            '</span>' : '';

        const changeClass = (artist.change || 0) >= 0 ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600';
        const changeIcon = (artist.change || 0) >= 0 ?
            '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="18 15 12 9 6 15"/></svg>' :
            '<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>';

        const changeText = ((artist.change || 0) >= 0 ? '+' : '') + (artist.change || 0) + '%';
        const artistImage = artist.portrait || artist.logo || artist.image || '/assets/images/default-artist.png';

        return '<a href="/artist/' + this.escapeHtml(artist.slug) + '" class="flex items-center gap-4 p-4 transition-colors rounded-2xl bg-surface hover:bg-border/50">' +
            '<div class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-sm font-bold text-white rounded-lg ' + rankClass + '">' + rank + '</div>' +
            '<div class="flex-shrink-0 overflow-hidden w-14 h-14 rounded-xl">' +
                '<img src="' + this.escapeHtml(artistImage) + '" alt="' + this.escapeHtml(artist.name) + '" class="object-cover w-full h-full">' +
            '</div>' +
            '<div class="flex-1 min-w-0">' +
                '<div class="flex items-center text-sm font-bold text-secondary">' + this.escapeHtml(artist.name) + verifiedBadge + '</div>' +
                '<div class="text-xs text-muted">' + (artist.tickets_sold || 0) + ' bilete vândute săptămâna aceasta</div>' +
            '</div>' +
            '<div class="flex items-center gap-1 px-2.5 py-1 text-xs font-semibold rounded-md ' + changeClass + '">' +
                changeIcon + changeText +
            '</div>' +
        '</a>';
    },

    /**
     * Render artist card for main grid
     */
    renderArtistCard(artist) {
        const verifiedBadge = artist.is_verified ?
            '<span class="inline-flex items-center justify-center w-4 h-4 ml-1 bg-blue-500 rounded-full">' +
                '<svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 24 24">' +
                    '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>' +
                '</svg>' +
            '</span>' : '';

        const genre = artist.genres?.[0]?.name || 'Artist';
        const followers = this.formatFollowers(this.calculateTotalFollowers(artist));
        const eventsCount = artist.upcoming_events_count || 0;
        const artistImage = artist.portrait || artist.logo || artist.image || '/assets/images/default-artist.png';

        const eventsInfo = eventsCount > 0 ?
            '<span class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-primary/10 text-primary">' +
                '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>' +
                    '<line x1="16" y1="2" x2="16" y2="6"/>' +
                    '<line x1="8" y1="2" x2="8" y2="6"/>' +
                    '<line x1="3" y1="10" x2="21" y2="10"/>' +
                '</svg>' +
                (eventsCount === 1 ? '1 eveniment viitor' : eventsCount + ' evenimente viitoare') +
            '</span>' :
            '<span class="text-xs text-muted">Fără evenimente programate</span>';

        return '<a href="/artist/' + this.escapeHtml(artist.slug) + '" ' +
            'class="overflow-hidden transition-all bg-white border group rounded-2xl border-border hover:-translate-y-1 hover:shadow-xl hover:border-primary">' +
            '<div class="relative overflow-hidden aspect-square">' +
                '<img src="' + this.escapeHtml(artistImage) + '" alt="' + this.escapeHtml(artist.name) + '" ' +
                    'class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" loading="lazy">' +
                '<span class="absolute px-3 py-1.5 text-xs font-semibold text-white uppercase rounded-full top-3 left-3 bg-black/60 backdrop-blur-sm">' +
                    this.escapeHtml(genre) + '</span>' +
            '</div>' +
            '<div class="p-5 text-center">' +
                '<h3 class="flex items-center justify-center mb-1 text-base font-bold text-secondary">' +
                    this.escapeHtml(artist.name) + verifiedBadge + '</h3>' +
                '<p class="mb-3 text-sm text-muted">' + followers + ' urmăritori</p>' +
                eventsInfo +
            '</div>' +
        '</a>';
    },

    /**
     * Get empty state HTML
     */
    getEmptyState() {
        return '<div class="py-16 text-center col-span-full">' +
            '<svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>' +
            '</svg>' +
            '<h3 class="mb-2 text-lg font-semibold text-secondary">Nu am găsit artiști</h3>' +
            '<p class="text-muted">Încearcă să modifici filtrele sau să cauți altceva</p>' +
        '</div>';
    },

    /**
     * Render pagination
     */
    renderPagination(meta) {
        const container = document.getElementById('pagination');
        if (!container) return;

        if (!meta || meta.last_page <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        // Previous button
        if (meta.current_page > 1) {
            html += '<button onclick="ArtistsPage.goToPage(' + (meta.current_page - 1) + ')" ' +
                'class="flex items-center justify-center w-10 h-10 transition-colors bg-white border rounded-xl border-border hover:bg-surface">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<polyline points="15 18 9 12 15 6"/>' +
                '</svg>' +
            '</button>';
        }

        // Page numbers
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === meta.current_page) {
                html += '<button class="w-10 h-10 font-bold text-white rounded-xl bg-primary">' + i + '</button>';
            } else if (i === 1 || i === meta.last_page || Math.abs(i - meta.current_page) <= 2) {
                html += '<button onclick="ArtistsPage.goToPage(' + i + ')" ' +
                    'class="w-10 h-10 font-medium transition-colors bg-white border rounded-xl border-border hover:bg-surface">' +
                    i + '</button>';
            } else if (Math.abs(i - meta.current_page) === 3) {
                html += '<span class="px-2 text-muted">...</span>';
            }
        }

        // Next button
        if (meta.current_page < meta.last_page) {
            html += '<button onclick="ArtistsPage.goToPage(' + (meta.current_page + 1) + ')" ' +
                'class="flex items-center justify-center w-10 h-10 transition-colors bg-white border rounded-xl border-border hover:bg-surface">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                    '<polyline points="9 18 15 12 9 6"/>' +
                '</svg>' +
            '</button>';
        }

        container.innerHTML = html;
    },

    /**
     * Navigate to a specific page
     */
    goToPage(page) {
        this.filters.page = page;
        this.loadArtists();
        window.scrollTo({ top: 400, behavior: 'smooth' });
    },

    /**
     * Set genre filter
     */
    setGenre(genre) {
        this.filters.genre = genre;
        this.filters.page = 1;

        // Update UI
        document.querySelectorAll('.genre-tab').forEach(tab => {
            const isActive = tab.dataset.genre === genre;
            tab.classList.toggle('bg-primary', isActive);
            tab.classList.toggle('text-white', isActive);
            tab.classList.toggle('bg-white', !isActive);
            tab.classList.toggle('border', !isActive);
            tab.classList.toggle('border-border', !isActive);
            tab.classList.toggle('text-muted', !isActive);
        });

        this.loadArtists();
    },

    /**
     * Set letter filter
     */
    setLetter(letter) {
        this.filters.letter = letter || '';
        this.filters.page = 1;

        // Update UI
        document.querySelectorAll('.alphabet-link').forEach(link => {
            const isActive = link.dataset.letter === letter;
            link.classList.toggle('bg-primary', isActive);
            link.classList.toggle('text-white', isActive);
            link.classList.toggle('bg-surface', !isActive);
            link.classList.toggle('text-muted', !isActive);
        });

        this.loadArtists();
    },

    /**
     * Perform search
     */
    search() {
        const query = document.getElementById('artistSearch')?.value?.trim();
        if (query && query.length >= 2) {
            this.filters.search = query;
            this.filters.page = 1;
            this.loadArtists();
        }
    },

    /**
     * Clear search
     */
    clearSearch() {
        this.filters.search = '';
        const searchInput = document.getElementById('artistSearch');
        if (searchInput) {
            searchInput.value = '';
        }
        this.filters.page = 1;
        this.loadArtists();
    },

    /**
     * Subscribe to newsletter
     */
    subscribeNewsletter(event) {
        event.preventDefault();
        const emailInput = document.getElementById('newsletterEmail');
        const email = emailInput?.value;

        if (email) {
            if (typeof AmbiletNotifications !== 'undefined') {
                AmbiletNotifications.success('Te-ai abonat cu succes!');
            }
            if (emailInput) {
                emailInput.value = '';
            }
        }
        return false;
    },

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Genre tabs
        document.querySelectorAll('.genre-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                this.setGenre(e.currentTarget.dataset.genre);
            });
        });

        // Sort select
        const sortSelect = document.getElementById('sortSelect');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.filters.sort = e.target.value;
                this.filters.page = 1;
                this.loadArtists();
            });
        }

        // Search on enter
        const searchInput = document.getElementById('artistSearch');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    this.search();
                }
            });
        }

        // Alphabet links
        document.querySelectorAll('.alphabet-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.setLetter(e.currentTarget.dataset.letter);
            });
        });
    },

    /**
     * Calculate total followers across platforms
     */
    calculateTotalFollowers(artist) {
        const stats = artist.stats || {};
        return (stats.spotify_listeners || 0) +
               (stats.instagram_followers || 0) +
               (stats.facebook_followers || 0) +
               (stats.youtube_subscribers || 0) +
               (stats.tiktok_followers || 0);
    },

    /**
     * Format followers number
     */
    formatFollowers(count) {
        if (!count || count === 0) return '0';
        if (count >= 1000000) {
            return (count / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (count >= 1000) {
            return (count / 1000).toFixed(1).replace(/\.0$/, '') + 'K';
        }
        return count.toString();
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Register globally
window.ArtistsPage = ArtistsPage;
