<?php
/**
 * Ambilet Homepage
 */
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Bilete Evenimente Romania';
$pageDescription = 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele. Platforma de ticketing pentru evenimente din Romania.';

require_once __DIR__ . '/includes/head.php';
?>

    <!-- Header -->
    <div id="header"></div>

    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary via-primary-dark to-secondary overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute top-20 left-20 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
            <div class="absolute bottom-20 right-20 w-96 h-96 bg-accent/10 rounded-full blur-3xl"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-24">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-4xl lg:text-6xl font-extrabold text-white mb-6 fade-in">
                    Descopera evenimente <span class="text-accent">memorabile</span>
                </h1>
                <p class="text-lg lg:text-xl text-white/80 mb-8 fade-in delay-1">
                    Concerte, festivaluri, teatru, sport si multe altele. Cumpara bilete simplu si rapid pentru cele mai bune evenimente din Romania.
                </p>

                <!-- Search Bar -->
                <div class="max-w-2xl mx-auto fade-in delay-2">
                    <div class="flex flex-col sm:flex-row gap-3 p-2 bg-white rounded-2xl shadow-xl">
                        <div class="flex-1 relative">
                            <input
                                type="text"
                                id="hero-search"
                                placeholder="Cauta evenimente, artisti, locatii..."
                                class="w-full pl-12 pr-4 py-4 bg-transparent border-0 text-secondary placeholder-muted focus:outline-none text-lg"
                            >
                            <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                        </div>
                        <button id="hero-search-btn" class="btn btn-primary btn-lg shrink-0">
                            Cauta
                        </button>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="flex flex-wrap justify-center gap-3 mt-8 fade-in delay-3">
                    <?php
                    $quickCategories = [
                        ['slug' => 'concert', 'icon' => '🎵', 'label' => 'Concerte'],
                        ['slug' => 'festival', 'icon' => '🎪', 'label' => 'Festivaluri'],
                        ['slug' => 'theater', 'icon' => '🎭', 'label' => 'Teatru'],
                        ['slug' => 'sport', 'icon' => '⚽', 'label' => 'Sport'],
                        ['slug' => 'comedy', 'icon' => '😂', 'label' => 'Stand-up'],
                    ];
                    foreach ($quickCategories as $cat):
                    ?>
                    <a href="/category.php?type=<?= $cat['slug'] ?>" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white rounded-full text-sm font-medium transition-colors">
                        <?= $cat['icon'] ?> <?= $cat['label'] ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Events -->
    <section class="py-16 lg:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl lg:text-3xl font-bold text-secondary">Evenimente recomandate</h2>
                    <p class="text-muted mt-1">Cele mai populare evenimente ale momentului</p>
                </div>
                <a href="/category.php" class="hidden sm:flex items-center gap-2 text-primary font-medium hover:underline">
                    Vezi toate
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>

            <!-- Featured Event (Large) -->
            <div id="featured-event" class="mb-8">
                <div class="skeleton h-96 rounded-3xl"></div>
            </div>

            <!-- Featured Grid -->
            <div id="featured-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
        </div>
    </section>

    <!-- Categories -->
    <section class="py-16 lg:py-24 bg-surface">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="text-center mb-12">
                <h2 class="text-2xl lg:text-3xl font-bold text-secondary mb-4">Exploreaza pe categorii</h2>
                <p class="text-muted">Gaseste evenimentele care ti se potrivesc</p>
            </div>

            <div id="categories-grid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <div class="skeleton h-32 rounded-2xl"></div>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <!-- All Events -->
    <section class="py-16 lg:py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Filters (Desktop) -->
                <aside class="lg:w-64 shrink-0">
                    <div class="sticky top-24 space-y-6">
                        <div>
                            <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider mb-3">Oras</h3>
                            <select id="filter-city" class="input">
                                <option value="">Toate orasele</option>
                            </select>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider mb-3">Categorie</h3>
                            <select id="filter-category" class="input">
                                <option value="">Toate categoriile</option>
                            </select>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider mb-3">Pret</h3>
                            <div class="flex gap-2">
                                <input type="number" id="filter-min-price" class="input" placeholder="Min">
                                <input type="number" id="filter-max-price" class="input" placeholder="Max">
                            </div>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider mb-3">Data</h3>
                            <input type="date" id="filter-date-from" class="input mb-2" placeholder="De la">
                            <input type="date" id="filter-date-to" class="input" placeholder="Pana la">
                        </div>

                        <button id="apply-filters" class="btn btn-primary w-full">
                            Aplica filtre
                        </button>

                        <button id="clear-filters" class="btn btn-secondary w-full">
                            Reseteaza
                        </button>
                    </div>
                </aside>

                <!-- Events List -->
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-bold text-secondary">Toate evenimentele</h2>
                        <select id="sort-events" class="input w-auto">
                            <option value="date_asc">Data (cel mai curand)</option>
                            <option value="date_desc">Data (cel mai tarziu)</option>
                            <option value="price_asc">Pret (crescator)</option>
                            <option value="price_desc">Pret (descrescator)</option>
                            <option value="popular">Popularitate</option>
                        </select>
                    </div>

                    <p id="results-count" class="text-muted mb-4">Se incarca...</p>

                    <div id="events-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6"></div>

                    <div id="pagination" class="flex justify-center mt-8"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA for Organizers -->
    <?php require_once __DIR__ . '/includes/cta-organizer.php'; ?>

    <!-- Footer -->
    <div id="footer"></div>

<?php
$scriptsExtra = <<<'JS'
<script>
    const HomePage = {
        currentPage: 1,
        filters: {},

        async init() {
            await Promise.all([
                this.loadFeaturedEvents(),
                this.loadCategories(),
                this.loadCities(),
                this.loadEvents()
            ]);
            this.bindEvents();
            this.checkUrlParams();
        },

        bindEvents() {
            document.getElementById('hero-search-btn')?.addEventListener('click', () => this.handleSearch());
            document.getElementById('hero-search')?.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') this.handleSearch();
            });
            document.getElementById('apply-filters')?.addEventListener('click', () => this.applyFilters());
            document.getElementById('clear-filters')?.addEventListener('click', () => this.clearFilters());
            document.getElementById('sort-events')?.addEventListener('change', () => this.loadEvents());
        },

        checkUrlParams() {
            const search = AmbiletUtils.getUrlParam('search');
            if (search) {
                document.getElementById('hero-search').value = search;
                this.filters.search = search;
                this.loadEvents();
            }
        },

        handleSearch() {
            const query = document.getElementById('hero-search')?.value.trim();
            if (query) {
                this.filters.search = query;
                AmbiletUtils.setUrlParam('search', query);
                this.loadEvents();
            }
        },

        async loadFeaturedEvents() {
            try {
                const response = await AmbiletAPI.getFeaturedEvents(4);
                if (response.success && response.data.length > 0) {
                    const featuredContainer = document.getElementById('featured-event');
                    if (featuredContainer && response.data[0]) {
                        featuredContainer.innerHTML = AmbiletEventCard.renderFeatured(response.data[0]);
                    }
                    const gridContainer = document.getElementById('featured-grid');
                    if (gridContainer && response.data.length > 1) {
                        gridContainer.innerHTML = response.data.slice(1, 4).map(event => AmbiletEventCard.render(event)).join('');
                    }
                }
            } catch (error) {
                console.error('Failed to load featured events:', error);
                document.getElementById('featured-event').innerHTML = '<p class="text-muted text-center py-8">Nu s-au putut incarca evenimentele</p>';
            }
        },

        async loadCategories() {
            try {
                const response = await AmbiletAPI.getCategories();
                if (response.success && response.data) {
                    const container = document.getElementById('categories-grid');
                    const filterSelect = document.getElementById('filter-category');
                    const categoryIcons = {'concert':'🎵','festival':'🎪','theater':'🎭','sport':'⚽','comedy':'😂','conference':'🎤','exhibition':'🖼️','workshop':'🛠️'};

                    container.innerHTML = response.data.map(cat => `
                        <a href="/category.php?type=${cat.slug || cat.id}" class="group bg-white rounded-2xl p-6 text-center border border-border hover:border-primary hover:shadow-lg transition-all">
                            <div class="text-4xl mb-3">${categoryIcons[cat.slug] || '📅'}</div>
                            <h3 class="font-semibold text-secondary group-hover:text-primary transition-colors">${cat.name}</h3>
                            <p class="text-sm text-muted">${cat.events_count || 0} evenimente</p>
                        </a>
                    `).join('');

                    filterSelect.innerHTML = '<option value="">Toate categoriile</option>' +
                        response.data.map(cat => `<option value="${cat.id}">${cat.name}</option>`).join('');
                }
            } catch (error) {
                console.error('Failed to load categories:', error);
            }
        },

        async loadCities() {
            try {
                const response = await AmbiletAPI.getCities();
                if (response.success && response.data) {
                    const filterSelect = document.getElementById('filter-city');
                    filterSelect.innerHTML = '<option value="">Toate orasele</option>' +
                        response.data.map(city => `<option value="${city.city}">${city.city} (${city.count})</option>`).join('');
                }
            } catch (error) {
                console.error('Failed to load cities:', error);
            }
        },

        async loadEvents() {
            const container = document.getElementById('events-grid');
            const countEl = document.getElementById('results-count');
            container.innerHTML = AmbiletEventCard.renderSkeletons(6);

            try {
                const params = {
                    page: this.currentPage,
                    per_page: 12,
                    sort: document.getElementById('sort-events')?.value || 'date_asc',
                    ...this.filters
                };

                const response = await AmbiletAPI.getEvents(params);
                if (response.success) {
                    const events = response.data;
                    const meta = response.meta;
                    countEl.textContent = `${meta?.total || events.length} evenimente gasite`;

                    if (events.length > 0) {
                        container.innerHTML = events.map(event => AmbiletEventCard.render(event)).join('');
                    } else {
                        container.innerHTML = `
                            <div class="col-span-full text-center py-12">
                                <svg class="w-16 h-16 text-muted mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <h3 class="text-lg font-semibold text-secondary mb-2">Nu am gasit evenimente</h3>
                                <p class="text-muted">Incearca sa modifici filtrele sau sa cauti altceva</p>
                            </div>
                        `;
                    }
                    this.renderPagination(meta);
                }
            } catch (error) {
                console.error('Failed to load events:', error);
                container.innerHTML = `<p class="col-span-full text-center text-error py-8">Eroare la incarcarea evenimentelor</p>`;
            }
        },

        renderPagination(meta) {
            const container = document.getElementById('pagination');
            if (!meta || meta.last_page <= 1) { container.innerHTML = ''; return; }

            let html = '<div class="flex items-center gap-2">';
            if (meta.current_page > 1) {
                html += `<button class="px-4 py-2 rounded-lg border border-border hover:bg-surface" onclick="HomePage.goToPage(${meta.current_page - 1})">Anterior</button>`;
            }
            for (let i = 1; i <= meta.last_page; i++) {
                if (i === meta.current_page) {
                    html += `<button class="px-4 py-2 rounded-lg bg-primary text-white">${i}</button>`;
                } else if (i === 1 || i === meta.last_page || Math.abs(i - meta.current_page) <= 2) {
                    html += `<button class="px-4 py-2 rounded-lg border border-border hover:bg-surface" onclick="HomePage.goToPage(${i})">${i}</button>`;
                } else if (Math.abs(i - meta.current_page) === 3) {
                    html += `<span class="px-2">...</span>`;
                }
            }
            if (meta.current_page < meta.last_page) {
                html += `<button class="px-4 py-2 rounded-lg border border-border hover:bg-surface" onclick="HomePage.goToPage(${meta.current_page + 1})">Urmator</button>`;
            }
            html += '</div>';
            container.innerHTML = html;
        },

        goToPage(page) {
            this.currentPage = page;
            this.loadEvents();
            AmbiletUtils.scrollTo('#events-grid', 150);
        },

        applyFilters() {
            this.filters = {
                city: document.getElementById('filter-city')?.value,
                category: document.getElementById('filter-category')?.value,
                min_price: document.getElementById('filter-min-price')?.value,
                max_price: document.getElementById('filter-max-price')?.value,
                from_date: document.getElementById('filter-date-from')?.value,
                to_date: document.getElementById('filter-date-to')?.value
            };
            Object.keys(this.filters).forEach(key => { if (!this.filters[key]) delete this.filters[key]; });
            this.currentPage = 1;
            this.loadEvents();
        },

        clearFilters() {
            ['filter-city', 'filter-category', 'filter-min-price', 'filter-max-price', 'filter-date-from', 'filter-date-to'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            this.filters = {};
            this.currentPage = 1;
            this.loadEvents();
        }
    };

    document.addEventListener('DOMContentLoaded', () => HomePage.init());
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';
?>
