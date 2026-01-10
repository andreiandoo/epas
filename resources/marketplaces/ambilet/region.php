<?php
/**
 * Region Page - Events filtered by region
 */
require_once __DIR__ . '/includes/config.php';

$regionSlug = $_GET['slug'] ?? '';

if (!$regionSlug) {
    header('Location: /orase');
    exit;
}

$pageTitle = 'Evenimente în regiune';
$pageDescription = 'Descoperă cele mai bune evenimente din această regiune.';
$currentPage = 'regions';
$transparentHeader = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative pt-40 pb-8 overflow-hidden bg-gradient-to-br from-slate-800 to-slate-900">
    <!-- Background Pattern -->
    <div class="absolute inset-0 opacity-20">
        <svg class="absolute right-0 h-full transform translate-x-1/4" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
            <path d="M50 200 L100 150 L150 180 L200 120 L250 160 L300 100 L350 140 L350 400 L50 400 Z" fill="#A51C30" fill-opacity="0.3"/>
            <path d="M0 250 L80 200 L140 230 L200 170 L280 210 L340 160 L400 190 L400 400 L0 400 Z" fill="#A51C30" fill-opacity="0.15"/>
        </svg>
    </div>

    <div class="relative z-10 px-4 mx-auto max-w-7xl">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 mb-6 text-sm">
            <a href="/" class="transition-colors text-slate-400 hover:text-white">Acasa</a>
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/orase" class="transition-colors text-slate-400 hover:text-white">Regiuni</a>
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-red-400" id="breadcrumbRegion">...</span>
        </nav>

        <!-- Title -->
        <h1 class="mb-4 text-4xl font-extrabold leading-tight text-white md:text-5xl">
            Evenimente in <span class="text-red-400" id="heroRegionName">...</span>
        </h1>

        <!-- Description -->
        <p class="max-w-2xl mb-8 text-lg leading-relaxed text-slate-400" id="heroDescription">
            Incarca...
        </p>

        <!-- Stats -->
        <div class="flex flex-wrap gap-8 pt-6 mt-4 border-t md:gap-12 border-white/10">
            <div>
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statEvents">-</div>
                <div class="text-sm text-slate-500">Evenimente active</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statCities">-</div>
                <div class="text-sm text-slate-500">Orase acoperite</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statVenues">-</div>
                <div class="text-sm text-slate-500">Locatii partenere</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-white md:text-4xl" id="statFestivals">-</div>
                <div class="text-sm text-slate-500">Festivaluri majore</div>
            </div>
        </div>
    </div>
</section>

<!-- Cities Navigation Tabs -->
<div class="bg-white border-b border-border">
    <div class="px-4 mx-auto overflow-x-auto max-w-7xl scrollbar-hide" id="citiesTabsContainer">
        <div class="flex" id="citiesTabs">
            <a href="#" class="flex items-center flex-shrink-0 gap-2 px-6 py-4 text-sm font-semibold transition-colors border-b-[3px] border-primary text-primary bg-red-50">
                Toate orasele <span class="px-2 py-0.5 text-xs font-bold text-white rounded-full bg-primary" id="tabAllCount">0</span>
            </a>
            <!-- Cities tabs will be populated dynamically -->
        </div>
    </div>
</div>

<!-- Main Content -->
<main class="px-4 py-12 mx-auto max-w-7xl">
    <!-- Cities Grid Section -->
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-secondary">Orase din <span id="sectionRegionName">regiune</span></h2>
            <button onclick="RegionPage.showAllCities()" id="showAllCitiesBtn" class="flex items-center gap-1 text-sm font-semibold text-primary">
                Vezi toate <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" id="citiesGrid">
            <!-- Loading skeletons -->
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-32 skeleton"></div>
                <div class="p-4">
                    <div class="w-3/4 mb-2 skeleton skeleton-title"></div>
                    <div class="w-1/2 skeleton skeleton-text"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    <!-- Festivals Section -->
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-secondary">Festivaluri din <span id="festivalsRegionName">regiune</span></h2>
            <a href="/festivaluri?regiune=<?= htmlspecialchars($regionSlug) ?>" class="flex items-center gap-1 text-sm font-semibold text-primary">
                Vezi toate <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="grid gap-6 md:grid-cols-2" id="festivalsGrid">
            <!-- Loading skeletons -->
            <?php for ($i = 0; $i < 2; $i++): ?>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="grid grid-cols-[200px_1fr]">
                    <div class="h-40 skeleton"></div>
                    <div class="p-5">
                        <div class="w-1/3 mb-2 skeleton skeleton-text"></div>
                        <div class="w-3/4 mb-2 skeleton skeleton-title"></div>
                        <div class="w-1/2 mb-4 skeleton skeleton-text"></div>
                        <div class="w-24 h-8 skeleton"></div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    <!-- Events Section -->
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-secondary">Toate evenimentele din <span id="eventsRegionName">regiune</span></h2>
            <a href="/evenimente?regiune=<?= htmlspecialchars($regionSlug) ?>" class="flex items-center gap-1 text-sm font-semibold text-primary">
                Vezi toate (<span id="eventsCount">0</span>) <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3" id="eventsGrid">
            <!-- Loading skeletons -->
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-44 skeleton"></div>
                <div class="p-5">
                    <div class="w-1/3 mb-2 skeleton skeleton-text"></div>
                    <div class="w-3/4 mb-2 skeleton skeleton-title"></div>
                    <div class="w-1/2 mb-4 skeleton skeleton-text"></div>
                    <div class="w-full h-8 skeleton"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    <!-- Region Info Section -->
    <section class="p-8 mb-12 bg-white border rounded-3xl border-border">
        <div class="grid gap-8 lg:grid-cols-2">
            <div>
                <h3 class="mb-4 text-xl font-bold text-secondary">Despre <span id="aboutRegionName">regiune</span></h3>
                <p class="mb-4 leading-relaxed text-muted" id="aboutDescription">
                    Incarca informatii despre regiune...
                </p>
                <div class="flex flex-wrap gap-2" id="regionHighlights">
                    <!-- Highlight tags will be populated dynamically -->
                </div>
            </div>
            <div class="flex items-center justify-center min-h-[200px] rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200">
                <svg class="w-32 h-32 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </div>
        </div>
    </section>

    <!-- Other Regions Section -->
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-secondary">Descopera si alte regiuni</h2>
        </div>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-5" id="otherRegionsGrid">
            <!-- Loading skeletons -->
            <?php for ($i = 0; $i < 5; $i++): ?>
            <div class="p-5 text-center bg-white border rounded-xl border-border">
                <div class="w-8 h-8 mx-auto mb-2 text-2xl rounded-full skeleton"></div>
                <div class="w-3/4 mx-auto mb-1 skeleton skeleton-title"></div>
                <div class="w-1/2 mx-auto skeleton skeleton-text"></div>
            </div>
            <?php endfor; ?>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$regionSlugJS = htmlspecialchars($regionSlug);
ob_start();
?>
<script>
const RegionPage = {
    slug: '<?= $regionSlugJS ?>',
    region: null,
    cities: [],
    otherRegions: [],

    async init() {
        await Promise.all([
            this.loadRegion(),
            this.loadAllRegions()
        ]);
    },

    async loadRegion() {
        try {
            const response = await AmbiletAPI.get('/api/proxy.php?action=locations.region&slug=' + this.slug);

            if (response.success && response.data) {
                this.region = response.data.region;
                this.cities = response.data.cities || [];
                this.renderRegion();
                this.renderCities();
                await this.loadEvents();
                await this.loadFestivals();
            } else {
                this.loadDemoData();
            }
        } catch (e) {
            console.error('Failed to load region:', e);
            this.loadDemoData();
        }
    },

    loadDemoData() {
        this.region = {
            id: 1,
            name: 'Transilvania',
            slug: 'transilvania',
            description: 'Inima Romaniei, cu orase pline de istorie si cultura. De la festivalurile legendare ale Clujului la spectacolele din cetatile medievale ale Sibiului si Brasovului, Transilvania te asteapta cu experiente de neuitat.'
        };
        this.cities = [
            { id: 2, name: 'Cluj-Napoca', slug: 'cluj-napoca', image: 'https://images.unsplash.com/photo-1587974928442-77dc3e0dba72?w=400&h=300&fit=crop', events_count: 156 },
            { id: 5, name: 'Brasov', slug: 'brasov', image: 'https://images.unsplash.com/photo-1565264216052-3c9012481015?w=400&h=300&fit=crop', events_count: 89 },
            { id: 16, name: 'Sibiu', slug: 'sibiu', image: 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=400&h=300&fit=crop', events_count: 72 },
            { id: 17, name: 'Targu Mures', slug: 'targu-mures', image: null, events_count: 45 }
        ];
        this.renderRegion();
        this.renderCities();
        this.loadDemoEvents();
        this.loadDemoFestivals();
    },

    async loadAllRegions() {
        try {
            const response = await AmbiletAPI.get('/api/proxy.php?action=locations.regions');

            if (response.success && response.data && response.data.regions) {
                this.otherRegions = response.data.regions.filter(r => r.slug !== this.slug);
                this.renderOtherRegions();
            } else {
                this.loadDemoOtherRegions();
            }
        } catch (e) {
            console.error('Failed to load regions:', e);
            this.loadDemoOtherRegions();
        }
    },

    loadDemoOtherRegions() {
        this.otherRegions = [
            { name: 'Muntenia', slug: 'muntenia', events_count: 312, icon: '🏛️' },
            { name: 'Moldova', slug: 'moldova', events_count: 145, icon: '⛪' },
            { name: 'Banat', slug: 'banat', events_count: 98, icon: '🎻' },
            { name: 'Dobrogea', slug: 'dobrogea', events_count: 87, icon: '🏖️' },
            { name: 'Oltenia', slug: 'oltenia', events_count: 64, icon: '🏔️' }
        ];
        this.renderOtherRegions();
    },

    renderRegion() {
        if (!this.region) return;

        const name = this.region.name;
        const desc = this.region.description || 'Descopera cele mai captivante evenimente din aceasta regiune.';
        const totalEvents = this.cities.reduce((sum, c) => sum + (c.events_count || 0), 0);

        // Update hero
        document.getElementById('breadcrumbRegion').textContent = name;
        document.getElementById('heroRegionName').textContent = name;
        document.getElementById('heroDescription').textContent = desc;

        // Update stats
        document.getElementById('statEvents').textContent = totalEvents;
        document.getElementById('statCities').textContent = this.cities.length;
        document.getElementById('statVenues').textContent = Math.round(totalEvents * 0.36); // estimate
        document.getElementById('statFestivals').textContent = Math.round(totalEvents * 0.02); // estimate

        // Update section names
        document.getElementById('sectionRegionName').textContent = name;
        document.getElementById('festivalsRegionName').textContent = name;
        document.getElementById('eventsRegionName').textContent = name;
        document.getElementById('aboutRegionName').textContent = name;
        document.getElementById('aboutDescription').textContent = desc;
        document.getElementById('eventsCount').textContent = totalEvents;
        document.getElementById('tabAllCount').textContent = totalEvents;

        // Update page title
        document.title = 'Evenimente in ' + name + ' - AmBilet.ro';

        // Render highlights
        this.renderHighlights();
    },

    renderHighlights() {
        const highlights = [
            { icon: '🏰', label: 'Cetati medievale' },
            { icon: '🎵', label: 'Festivaluri majore' },
            { icon: '🎭', label: 'Teatru de traditie' },
            { icon: '🏔️', label: 'Peisaje montane' }
        ];

        document.getElementById('regionHighlights').innerHTML = highlights
            .map(h => `<span class="px-3 py-1.5 text-sm font-medium rounded-full bg-slate-100 text-slate-600">${h.icon} ${h.label}</span>`)
            .join('');
    },

    showingAllCities: false,

    renderCities() {
        const container = document.getElementById('citiesGrid');
        const tabsContainer = document.getElementById('citiesTabs');
        const showAllBtn = document.getElementById('showAllCitiesBtn');

        if (!this.cities.length) {
            container.innerHTML = this.getEmptyState('Nu am gasit orase in aceasta regiune');
            if (showAllBtn) showAllBtn.style.display = 'none';
            return;
        }

        // Show first 4 cities or all if expanded
        const citiesToShow = this.showingAllCities ? this.cities : this.cities.slice(0, 4);
        container.innerHTML = citiesToShow.map(city => this.renderCityCard(city)).join('');

        // Update button text
        if (showAllBtn) {
            if (this.cities.length <= 4) {
                showAllBtn.style.display = 'none';
            } else {
                showAllBtn.innerHTML = this.showingAllCities
                    ? 'Arata mai putin <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>'
                    : `Vezi toate (${this.cities.length}) <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>`;
            }
        }

        // Render city tabs
        const cityTabs = this.cities.slice(0, 7).map(city => `
            <a href="/${city.slug}" class="flex items-center flex-shrink-0 gap-2 px-6 py-4 text-sm font-semibold transition-colors border-b-[3px] border-transparent text-muted hover:text-secondary hover:bg-slate-50">
                ${city.name} <span class="px-2 py-0.5 text-xs font-bold rounded-full bg-slate-200">${city.events_count}</span>
            </a>
        `).join('');

        // Keep the "All cities" tab and add city tabs
        const allTab = tabsContainer.querySelector('a');
        tabsContainer.innerHTML = allTab.outerHTML + cityTabs;
    },

    showAllCities() {
        this.showingAllCities = !this.showingAllCities;
        this.renderCities();

        // Scroll to cities section if collapsing
        if (!this.showingAllCities) {
            document.getElementById('citiesGrid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },

    renderCityCard(city) {
        const gradients = [
            'from-indigo-500 to-purple-600',
            'from-emerald-500 to-teal-600',
            'from-pink-500 to-rose-600',
            'from-amber-500 to-orange-600',
            'from-cyan-500 to-blue-600'
        ];
        const gradient = gradients[city.id % gradients.length];

        const imageHtml = city.image
            ? `<img src="${city.image}" alt="${city.name}" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105">`
            : `<div class="flex items-center justify-center w-full h-full bg-gradient-to-br ${gradient}">
                <svg class="w-10 h-10 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
               </div>`;

        return `
            <a href="/${city.slug}" class="overflow-hidden transition-all bg-white border group rounded-2xl border-border hover:-translate-y-1 hover:shadow-xl hover:border-primary">
                <div class="relative h-32 overflow-hidden">${imageHtml}</div>
                <div class="p-4">
                    <div class="mb-1 font-bold text-secondary">${city.name}</div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted">${city.events_count} evenimente</span>
                        <div class="flex items-center justify-center transition-colors rounded-full w-7 h-7 bg-slate-100 group-hover:bg-primary">
                            <svg class="w-3.5 h-3.5 text-muted group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </a>
        `;
    },

    async loadEvents() {
        try {
            // Get city slugs for this region
            const citySlugs = this.cities.map(c => c.slug);
            if (!citySlugs.length) return;

            const response = await AmbiletAPI.get('/api/proxy.php?action=events&city=' + citySlugs[0] + '&limit=3');

            if (response.data && response.data.length > 0) {
                this.renderEvents(response.data);
            } else {
                this.loadDemoEvents();
            }
        } catch (e) {
            console.error('Failed to load events:', e);
            this.loadDemoEvents();
        }
    },

    loadDemoEvents() {
        const demoEvents = [
            { slug: 'concert-cargo', title: 'Concert Cargo - Turneu National', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600', start_date: '2025-03-22', time: '21:00', venue: { name: 'BT Arena', city: 'Cluj-Napoca' }, min_price: 95, badge: 'hot' },
            { slug: 'standup-comedy', title: 'Stand-up Comedy Tour - Brasov', image: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=600', start_date: '2025-03-28', time: '20:00', venue: { name: 'Centrul Cultural Reduta', city: 'Brasov' }, min_price: 65, badge: 'new' },
            { slug: 'hamlet-sibiu', title: 'Hamlet - Teatrul National Sibiu', image: 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=600', start_date: '2025-04-05', time: '19:00', venue: { name: 'Teatrul National Radu Stanca', city: 'Sibiu' }, min_price: 55, badge: null }
        ];
        this.renderEvents(demoEvents);
    },

    renderEvents(events) {
        const container = document.getElementById('eventsGrid');

        if (!events.length) {
            container.innerHTML = this.getEmptyState('Nu am gasit evenimente in aceasta regiune');
            return;
        }

        container.innerHTML = events.slice(0, 3).map(event => this.renderEventCard(event)).join('');
    },

    renderEventCard(event) {
        const date = new Date(event.start_date || event.date);
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const gradients = ['from-indigo-500 to-purple-600', 'from-emerald-500 to-teal-600', 'from-pink-500 to-rose-600'];
        const gradient = gradients[Math.floor(Math.random() * gradients.length)];

        const imageHtml = event.image
            ? `<img src="${event.image}" alt="${event.title}" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105">`
            : `<div class="flex items-center justify-center w-full h-full bg-gradient-to-br ${gradient}">
                <svg class="w-10 h-10 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                </svg>
               </div>`;

        const badgeHtml = event.badge === 'hot'
            ? '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 left-3 bg-amber-500">Hot</span>'
            : event.badge === 'new'
            ? '<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded-lg top-3 left-3 bg-emerald-500">Nou</span>'
            : '';

        const cityName = event.venue?.city || '';
        const cityBadge = cityName ? `<span class="absolute px-2 py-1 text-xs font-semibold text-white rounded backdrop-blur-sm bottom-3 left-3 bg-black/60">${cityName}</span>` : '';

        return `
            <div class="overflow-hidden transition-all bg-white border group rounded-2xl border-border hover:-translate-y-1 hover:shadow-xl">
                <div class="relative overflow-hidden h-44">
                    ${imageHtml}
                    ${badgeHtml}
                    ${cityBadge}
                    <button class="absolute flex items-center justify-center transition-transform bg-white rounded-full w-9 h-9 top-3 right-3 hover:scale-110">
                        <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                        </svg>
                    </button>
                </div>
                <div class="p-5">
                    <div class="mb-1 text-xs font-semibold tracking-wide uppercase text-primary">
                        ${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()} ${event.time ? '• ' + event.time : ''}
                    </div>
                    <h3 class="mb-1 font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">
                        <a href="/bilete/${event.slug}">${event.title}</a>
                    </h3>
                    <div class="flex items-center gap-1 mb-4 text-sm text-muted">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        </svg>
                        ${event.venue?.name || 'Locatie TBA'}
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                        <div class="font-bold text-secondary">
                            ${event.min_price || 50} RON <span class="text-xs font-normal text-muted">de la</span>
                        </div>
                        <a href="/bilete/${event.slug}" class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-primary to-primary-dark hover:-translate-y-0.5 hover:shadow-md">
                            Cumpara
                        </a>
                    </div>
                </div>
            </div>
        `;
    },

    async loadFestivals() {
        // For now, use demo festivals
        this.loadDemoFestivals();
    },

    loadDemoFestivals() {
        const festivals = [
            { slug: 'untold-2025', title: 'UNTOLD Festival 2025', image: null, date: '7-10 August 2025', venue: { name: 'Cluj Arena', city: 'Cluj-Napoca' }, min_price: 499, badge: 'Sold Out Soon', gradient: 'from-indigo-500 to-purple-600' },
            { slug: 'electric-castle-2025', title: 'Electric Castle 2025', image: null, date: '18-21 Iulie 2025', venue: { name: 'Castelul Banffy', city: 'Bontida' }, min_price: 649, badge: null, gradient: 'from-emerald-500 to-teal-600' }
        ];
        this.renderFestivals(festivals);
    },

    renderFestivals(festivals) {
        const container = document.getElementById('festivalsGrid');

        container.innerHTML = festivals.map(fest => `
            <div class="overflow-hidden transition-all bg-white border rounded-2xl border-border hover:-translate-y-0.5 hover:shadow-xl">
                <div class="grid grid-cols-1 md:grid-cols-[200px_1fr]">
                    <div class="relative flex items-center justify-center h-40 md:h-full bg-gradient-to-br ${fest.gradient}">
                        ${fest.badge ? `<span class="absolute px-2 py-1 text-xs font-bold text-white uppercase rounded top-3 left-3 bg-amber-500">${fest.badge}</span>` : ''}
                        <svg class="w-12 h-12 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </div>
                    <div class="flex flex-col justify-center p-5">
                        <div class="mb-1 text-xs font-semibold tracking-wide uppercase text-primary">${fest.date}</div>
                        <h3 class="mb-1 text-lg font-bold text-secondary hover:text-primary">
                            <a href="/bilete/${fest.slug}">${fest.title}</a>
                        </h3>
                        <div class="flex items-center gap-1 mb-3 text-sm text-muted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                            ${fest.venue.name}, ${fest.venue.city}
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="font-bold text-secondary">${fest.min_price} RON <span class="text-xs font-normal text-muted">de la</span></div>
                            <a href="/bilete/${fest.slug}" class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-primary to-primary-dark hover:-translate-y-0.5 hover:shadow-md">
                                Cumpara
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    },

    renderOtherRegions() {
        const container = document.getElementById('otherRegionsGrid');

        const icons = {
            'muntenia': '🏛️',
            'moldova': '⛪',
            'banat': '🎻',
            'dobrogea': '🏖️',
            'oltenia': '🏔️',
            'crisana': '🏰',
            'maramures': '⛰️',
            'transilvania': '🗻'
        };

        container.innerHTML = this.otherRegions.slice(0, 5).map(region => `
            <a href="/regiune/${region.slug}" class="block p-5 text-center transition-all bg-white border rounded-xl border-border hover:border-primary hover:-translate-y-0.5 hover:shadow-lg">
                <div class="mb-2 text-2xl">${region.icon || icons[region.slug] || '📍'}</div>
                <div class="mb-0.5 font-semibold text-secondary">${region.name}</div>
                <div class="text-xs text-muted">${region.events_count} evenimente</div>
            </a>
        `).join('');
    },

    getEmptyState(message) {
        return `
            <div class="py-12 text-center col-span-full">
                <svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="mb-2 text-lg font-semibold text-secondary">Nu am gasit rezultate</h3>
                <p class="text-muted">${message}</p>
            </div>
        `;
    }
};

document.addEventListener('DOMContentLoaded', () => RegionPage.init());
</script>
<?php
$scriptsExtra = ob_get_clean();

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
