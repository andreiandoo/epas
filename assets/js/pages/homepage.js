// 3D Poster Stack Carousel Module
const HeroSlider = {
    events: [],
    currentIndex: 0,
    autoplayInterval: null,
    autoplayDelay: 2000, // 2 seconds

    async init() {
        try {
            const response = await AmbiletAPI.get('/events/featured?type=homepage&limit=12');
            if (response.data && response.data.events && response.data.events.length > 0) {
                this.events = response.data.events;
                this.render();
                this.renderDots();
                this.updatePositions();
                this.bindEvents();
                this.startAutoplay();
            } else {
                const section = document.getElementById('heroSlider');
                if (section) section.style.display = 'none';
            }
        } catch (error) {
            console.warn('Failed to load hero events:', error);
            const section = document.getElementById('heroSlider');
            if (section) section.style.display = 'none';
        }
    },

    render() {
        const container = document.getElementById('heroCarousel');
        if (!container || this.events.length === 0) return;

        container.innerHTML = this.events.map((event, index) => {
            const image = getStorageUrl(event.homepage_featured_image || event.featured_image || event.hero_image_url || event.image);
            const title = this.escapeHtml(event.title || event.name || 'Eveniment');
            const city = event.venue_city || event.city || '';
            const venue = event.venue_name || (event.venue ? event.venue.name : '') || '';
            const locationText = city ? (venue ? city + ', ' + venue : city) : venue;
            const priceFrom = event.price_from ? 'De la ' + event.price_from + ' Lei' : '';

            return '<div class="hero-item" data-index="' + index + '" style="--r: ' + (index - this.currentIndex) + ';">' +
                '<a href="/bilete/' + (event.slug || '') + '" class="hero-item-inner">' +
                    '<img src="' + image + '" alt="' + title + '" loading="' + (index < 5 ? 'eager' : 'lazy') + '">' +
                    '<div class="hero-item-overlay"></div>' +

                    '<div class="hero-item-content">' +
                        '<h3 class="hero-item-title">' + title + '</h3>' +
                        '<div class="hero-item-meta">' +
                            (locationText ? '<span class="hero-item-location"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + this.escapeHtml(locationText) + '</span>' : '') +
                            (priceFrom ? '<span class="hero-item-price">' + priceFrom + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                '</a>' +
            '</div>';
        }).join('');
    },

    renderDots() {
        const dotsContainer = document.getElementById('heroDots');
        if (!dotsContainer) return;

        dotsContainer.innerHTML = this.events.map((_, index) =>
            '<button class="hero-dot' + (index === 0 ? ' active' : '') + '" data-index="' + index + '"></button>'
        ).join('');
    },

    updatePositions() {
        const items = document.querySelectorAll('.hero-item');
        const total = this.events.length;

        items.forEach((item, index) => {
            let r = index - this.currentIndex;
            // Circular wrapping
            if (r > total / 2) r -= total;
            if (r < -total / 2) r += total;

            const abs = Math.abs(r);
            item.style.setProperty('--r', r);
            item.style.setProperty('--abs', abs);
            item.style.zIndex = 10 - abs;
            item.style.opacity = abs > 2 ? 0 : 1;
            item.style.filter = 'brightness(' + (1 - 0.15 * abs) + ')';
            item.style.pointerEvents = abs > 2 ? 'none' : 'auto';
        });

        // Update dots
        const dots = document.querySelectorAll('.hero-dot');
        dots.forEach((dot, index) => {
            dot.classList.toggle('active', index === this.currentIndex);
        });
    },

    goToSlide(index) {
        const total = this.events.length;
        if (index < 0) index = total - 1;
        if (index >= total) index = 0;
        this.currentIndex = index;
        this.updatePositions();
    },

    next() {
        this.goToSlide(this.currentIndex + 1);
    },

    prev() {
        this.goToSlide(this.currentIndex - 1);
    },

    startAutoplay() {
        this.stopAutoplay();
        if (this.events.length > 1) {
            this.autoplayInterval = setInterval(() => this.next(), this.autoplayDelay);
        }
    },

    stopAutoplay() {
        if (this.autoplayInterval) {
            clearInterval(this.autoplayInterval);
            this.autoplayInterval = null;
        }
    },

    bindEvents() {
        const heroSection = document.getElementById('heroSlider');
        const carousel = document.getElementById('heroCarousel');
        const dotsContainer = document.getElementById('heroDots');

        // Dot clicks
        if (dotsContainer) {
            dotsContainer.addEventListener('click', (e) => {
                const dot = e.target.closest('.hero-dot');
                if (dot) {
                    this.goToSlide(parseInt(dot.dataset.index, 10));
                    this.startAutoplay();
                }
            });
        }

        // Click side slides to navigate
        if (carousel) {
            carousel.addEventListener('click', (e) => {
                const item = e.target.closest('.hero-item');
                if (!item) return;
                const r = parseFloat(item.style.getPropertyValue('--r'));
                if (r !== 0) {
                    e.preventDefault();
                    this.goToSlide(parseInt(item.dataset.index, 10));
                    this.startAutoplay();
                }
            });
        }

        // Pause on hover
        if (heroSection) {
            heroSection.addEventListener('mouseenter', () => this.stopAutoplay());
            heroSection.addEventListener('mouseleave', () => this.startAutoplay());
        }

        // Touch/swipe support
        let touchStartX = 0;
        if (carousel) {
            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.touches[0].clientX;
                this.stopAutoplay();
            }, { passive: true });

            carousel.addEventListener('touchend', (e) => {
                const diff = touchStartX - e.changedTouches[0].clientX;
                if (Math.abs(diff) > 50) {
                    if (diff > 0) this.next();
                    else this.prev();
                }
                this.startAutoplay();
            }, { passive: true });
        }

        // Keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { this.prev(); this.startAutoplay(); }
            else if (e.key === 'ArrowRight') { this.next(); this.startAutoplay(); }
        });
    },

    escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }
};

// City Events Filter Module
const CityEventsFilter = {
    allEvents: [],
    cities: [],
    selectedCity: '',

    async init() {
        // Load events first, then extract cities from them
        await this.loadEvents();
        this.extractCitiesFromEvents();
        this.renderCityButtons();
        this.renderEvents();
    },

    async loadEvents() {
        try {
            const response = await AmbiletAPI.get('/marketplace-events?sort=latest&limit=30');
            if (response.data) {
                this.allEvents = Array.isArray(response.data) ? response.data : (response.data.events || response.data.data || []);
            }
        } catch (error) {
            console.warn('Failed to load events:', error);
        }
    },

    extractCitiesFromEvents() {
        // Extract unique cities from loaded events
        const cityMap = new Map();

        this.allEvents.forEach(event => {
            // Get city from venue_city (API response) or venue.city
            const cityName = event.venue_city || event.venue?.city || '';
            if (!cityName) return;

            // Normalize city name for deduplication
            const normalizedKey = cityName.toLowerCase().trim();

            if (!cityMap.has(normalizedKey)) {
                cityMap.set(normalizedKey, {
                    name: cityName,
                    slug: normalizedKey.replace(/\s+/g, '-'),
                    count: 1
                });
            } else {
                cityMap.get(normalizedKey).count++;
            }
        });

        // Sort by event count (most events first) and take top 10
        this.cities = Array.from(cityMap.values())
            .sort((a, b) => b.count - a.count)
            .slice(0, 10);
    },

    renderCityButtons() {
        const container = document.getElementById('cityFilterButtons');
        if (!container) return;

        // Keep "Toate" button, add city buttons
        const cityButtons = this.cities.map(city =>
            '<button class="px-4 py-2 text-sm font-semibold transition-all rounded-full city-filter-btn" data-city="' + city.name + '">' +
                city.name +
            '</button>'
        ).join('');

        container.innerHTML =
            '<button class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-full city-filter-btn active bg-primary" data-city="">' +
                'Toate' +
            '</button>' +
            cityButtons;

        // Bind click events
        container.querySelectorAll('.city-filter-btn').forEach(btn => {
            btn.addEventListener('click', () => this.filterByCity(btn.dataset.city));
        });
    },

    filterByCity(cityName) {
        this.selectedCity = cityName;

        // Update active button
        document.querySelectorAll('.city-filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.city === cityName);
        });

        this.renderEvents();
    },

    renderEvents() {
        const container = document.getElementById('cityEventsGrid');
        if (!container) return;

        // Filter events by city if selected
        let events = this.allEvents;
        if (this.selectedCity) {
            const selectedCityLower = this.selectedCity.toLowerCase().trim();
            events = this.allEvents.filter(event => {
                const eventCity = (event.venue_city || event.venue?.city || '').toLowerCase().trim();
                return eventCity === selectedCityLower;
            });
        }

        if (!events || events.length === 0) {
            container.innerHTML = '<p class="py-8 text-center text-muted col-span-full">Nu sunt evenimente disponibile pentru acest oraș.</p>';
            return;
        }

        // Use AmbiletEventCard component for rendering
        if (typeof AmbiletEventCard !== 'undefined') {
            container.innerHTML = AmbiletEventCard.renderMany(events.slice(0, 25), {
                urlPrefix: '/bilete/',
                showCategory: true,
                showPrice: true,
                showVenue: true
            });
        } else {
            // Fallback - shouldn't happen
            container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
        }

        // Trigger reveal animation
        setTimeout(() => {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
        }, 100);
    }
};

// Promoted Events Module
const PromotedEvents = {
    async init() {
        try {
            // Load promoted (paid) and recommended events
            const [promotedRes, recommendedRes] = await Promise.all([
                AmbiletAPI.get('/marketplace-events?promoted=1&limit=12').catch(() => ({ data: [] })),
                AmbiletAPI.get('/marketplace-events?recommended=1&limit=12').catch(() => ({ data: [] }))
            ]);

            const promoted = this.extractEvents(promotedRes);
            const recommended = this.extractEvents(recommendedRes);

            // Combine: promoted first (priority), then recommended (deduped)
            const combined = [...promoted];
            recommended.forEach(event => {
                if (!combined.find(e => e.id === event.id)) {
                    combined.push(event);
                }
            });

            // Limit to 12 and shuffle randomly
            const limited = combined.slice(0, 12);
            this.shuffle(limited);

            this.render(limited);
        } catch (error) {
            console.warn('Failed to load promoted events:', error);
        }
    },

    extractEvents(response) {
        if (!response || !response.data) return [];
        if (Array.isArray(response.data)) return response.data;
        return response.data.events || response.data.data || [];
    },

    shuffle(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [array[i], array[j]] = [array[j], array[i]];
        }
    },

    render(events) {
        const container = document.getElementById('promotedEventsGrid');
        if (!container) return;

        if (!events || events.length === 0) {
            container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
            return;
        }

        // Use poster-style promoted cards
        if (typeof AmbiletEventCard !== 'undefined' && typeof AmbiletEventCard.renderManyPromoted === 'function') {
            container.innerHTML = AmbiletEventCard.renderManyPromoted(events);
        } else {
            container.innerHTML = '<p class="text-muted col-span-full">Nu sunt evenimente disponibile.</p>';
        }

        // Trigger reveal animation
        setTimeout(() => {
            document.querySelectorAll('.reveal').forEach(el => el.classList.add('active'));
        }, 100);
    }
};

// Lazy Loading Module using Intersection Observer
const LazyLoader = {
    observer: null,
    loadedSections: new Set(),

    init() {
        // Create observer with rootMargin for early loading
        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const section = entry.target;
                    const loadType = section.dataset.lazyLoad;

                    if (!this.loadedSections.has(loadType)) {
                        this.loadedSections.add(loadType);
                        this.loadSection(loadType, section);
                    }
                }
            });
        }, {
            rootMargin: '100px 0px', // Start loading 100px before visible
            threshold: 0.01
        });

        // Observe all lazy sections
        document.querySelectorAll('[data-lazy-load]').forEach(section => {
            this.observer.observe(section);
        });
    },

    async loadSection(type, section) {
        try {
            switch (type) {
                case 'categories':
                    await loadCategories();
                    break;
                case 'cityEvents':
                    await CityEventsFilter.init();
                    break;
                case 'static':
                    // Static sections just need the animation
                    break;
            }
        } catch (error) {
            console.warn('Failed to load section: ' + type, error);
        }

        // Add loaded class for CSS animation
        section.classList.add('loaded');

        // Stop observing this section
        this.observer.unobserve(section);
    }
};

// Load categories
async function loadCategories() {
    try {
        const response = await AmbiletAPI.getCategories();
        if (response.data && response.data.categories) {
            renderCategories(response.data.categories);
        }
    } catch (error) {
        console.warn('Failed to load categories:', error);
    }
}

function renderCategories(categories) {
    var container = document.getElementById('categoriesGrid');
    var icons = {
        'concerte': '🎸',
        'festivaluri': '🎪',
        'teatru': '🎭',
        'stand-up': '😂',
        'copii': '👶',
        'sport': '⚽',
        'moto': '🏍️',
        'expozitii': '🖼️',
        'conferinte': '💼'
    };

    if (!categories || categories.length === 0) {
        container.innerHTML = '<p class="text-muted col-span-full">Nu sunt categorii disponibile.</p>';
        return;
    }

    container.innerHTML = categories.slice(0, 6).map(function(cat) {
        return '<a href="/' + cat.slug + '" class="flex items-center gap-3 px-3 py-2 bg-white border rounded-lg category-pill border-border group">' +
            '<div class="flex items-center justify-center w-8 h-8 transition-colors rounded-lg bg-primary/10 group-hover:bg-white/20">' +
                '<span class="text-lg">' + (icons[cat.slug] || '🎫') + '</span>' +
            '</div>' +
            '<span class="text-sm font-semibold transition-colors md:text-base text-secondary group-hover:text-white">' + cat.name + '</span>' +
        '</a>';
    }).join('');
}

// Homepage initialization
document.addEventListener('DOMContentLoaded', async function() {
    // Initialize lazy loading first
    LazyLoader.init();

    // Load above-the-fold content immediately (hero + promoted)
    await Promise.all([
        HeroSlider.init(),
        PromotedEvents.init(),
        FeaturedCarousel.init()
    ]);

    // Below-fold content is loaded by LazyLoader when visible
});
