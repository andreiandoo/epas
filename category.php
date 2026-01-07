<?php
/**
 * Category / Events Listing Page
 * Based on category.html template
 *
 * Loads category data from:
 * 1. API cache (nav-cache.php) for dynamic categories from DB
 * 2. Fallback to static category-config.php for defaults
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/nav-cache.php';
require_once __DIR__ . '/includes/category-config.php';

$categorySlug = $_GET['type'] ?? $_GET['slug'] ?? '';

// First try to get category from API cache (dynamic DB data)
$apiCategories = getEventCategories();
$apiCategory = null;
foreach ($apiCategories as $cat) {
    if ($cat['slug'] === $categorySlug) {
        $apiCategory = $cat;
        break;
    }
}

if ($apiCategory) {
    // Use API data (from DB)
    $pageTitle = $apiCategory['name'];
    $pageDescription = $apiCategory['description'] ?? 'Descopera evenimentele din aceasta categorie.';
    $categoryIcon = $apiCategory['icon_emoji'] ?? '🎫';
    $categoryHeroImage = $apiCategory['image'] ?? getHeroImage($categorySlug, 'category');
    $categoryColor = $apiCategory['color'] ?? '#A51C30';
} else {
    // Fallback to static config file
    $categoryConfig = getCategory($categorySlug);

    if ($categoryConfig) {
        $pageTitle = $categoryConfig['name'];
        $pageDescription = $categoryConfig['description'];
        $categoryIcon = $categoryConfig['icon'];
        $categoryHeroImage = $categoryConfig['hero_image'];
        $categoryColor = $categoryConfig['color'];
    } else {
        $pageTitle = $categorySlug ? ucfirst(str_replace('-', ' ', $categorySlug)) : 'Toate evenimentele';
        $pageDescription = 'Descopera evenimentele din aceasta categorie.';
        $categoryIcon = '🎫';
        $categoryHeroImage = getHeroImage('concerte', 'category');
        $categoryColor = '#A51C30';
    }
}

$currentPage = 'events';
$transparentHeader = true;
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php'; ?>

<!-- Hero Banner -->
<section class="relative h-[420px] md:h-[480px] overflow-hidden">
    <img id="categoryBanner" src="<?= htmlspecialchars($categoryHeroImage) ?>" alt="<?= htmlspecialchars($pageTitle) ?>" class="absolute inset-0 object-cover w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-r from-black/80 via-black/60 to-black/40"></div>
    <div class="relative flex flex-col justify-end h-full px-4 pb-12 mx-auto max-w-7xl">
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/60">
            <a href="/" class="transition-colors hover:text-white">Acasa</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white" id="breadcrumbTitle"><?= htmlspecialchars($pageTitle) ?></span>
        </nav>
        <h1 id="pageTitle" class="mb-3 text-4xl font-extrabold text-white md:text-5xl"><?= $categoryIcon ?> <?= htmlspecialchars($pageTitle) ?></h1>
        <p id="pageDescription" class="max-w-xl text-lg text-white/80"><?= htmlspecialchars($pageDescription) ?></p>
        <div class="flex items-center gap-4 mt-6">
            <span id="eventsCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- evenimente</span>
            <span id="citiesCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- orase</span>
        </div>
    </div>
</section>

<!-- Filters & Content -->
<section class="py-8 md:py-12">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Genre Pills -->
        <div class="mb-8" id="genresSection">
            <h3 class="mb-4 text-sm font-semibold tracking-wider uppercase text-muted">Filtreaza dupa gen</h3>
            <div class="flex flex-wrap gap-2" id="genresPills">
                <button class="genre-pill active px-5 py-2.5 bg-white border border-border rounded-full font-medium text-sm transition-all" data-genre="">Toate</button>
                <!-- Genres will be loaded dynamically -->
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="flex flex-col justify-between gap-4 p-4 mb-8 bg-white border md:flex-row md:items-center rounded-2xl border-border">
            <div class="flex flex-wrap items-center gap-3">
                <select id="filterCity" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Toate orasele</option>
                </select>
                <select id="filterDate" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Oricand</option>
                    <option value="today">Astazi</option>
                    <option value="tomorrow">Maine</option>
                    <option value="this_week">Saptamana aceasta</option>
                    <option value="this_month">Luna aceasta</option>
                </select>
                <select id="filterPrice" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Orice pret</option>
                    <option value="0-50">Sub 50 lei</option>
                    <option value="50-100">50 - 100 lei</option>
                    <option value="100-200">100 - 200 lei</option>
                    <option value="200-">Peste 200 lei</option>
                </select>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted">Sorteaza:</span>
                <select id="sortEvents" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="date_asc">Data (aproape)</option>
                    <option value="date_desc">Data (departe)</option>
                    <option value="price_asc">Pret (mic)</option>
                    <option value="price_desc">Pret (mare)</option>
                    <option value="popularity">Popularitate</option>
                </select>
            </div>
        </div>

        <!-- Events Grid -->
        <div id="eventsGrid" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <!-- Events will be loaded dynamically -->
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div class="h-48 skeleton"></div>
                <div class="p-4">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    <div class="w-1/2 mt-3 skeleton skeleton-text"></div>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="flex items-center justify-center gap-2 mt-12"></div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<SCRIPTS
<script>
const CategoryPage = {
    category: '$categorySlug',
    currentPage: 1,
    totalEvents: 0,
    filters: {},

    async init() {
        // Set initial category filter
        if (this.category) {
            this.filters.category = this.category;
        }

        // Load data in parallel
        await Promise.all([
            this.loadCategoryInfo(),
            this.loadGenres(),
            this.loadCities(),
            this.loadEvents()
        ]);

        this.bindEvents();
    },

    async loadCategoryInfo() {
        if (!this.category) return;

        try {
            // Use the new event-categories endpoint from API
            const response = await AmbiletAPI.get('event-categories');
            if (response.data?.categories) {
                const cat = response.data.categories.find(c => c.slug === this.category);
                if (cat) {
                    document.getElementById('pageTitle').innerHTML = (cat.icon_emoji || '🎫') + ' ' + cat.name;
                    document.getElementById('breadcrumbTitle').textContent = cat.name;
                    if (cat.description) {
                        document.getElementById('pageDescription').textContent = cat.description;
                    }
                    if (cat.image) {
                        document.getElementById('categoryBanner').src = cat.image;
                    }
                }
            }
        } catch (e) {
            console.warn('Failed to load category info:', e);
        }
    },

    async loadGenres() {
        if (!this.category) {
            document.getElementById('genresSection').style.display = 'none';
            return;
        }

        try {
            const response = await AmbiletAPI.get('/genres?category=' + this.category);
            if (response.data && response.data.length > 0) {
                const container = document.getElementById('genresPills');
                container.innerHTML = '<button class="genre-pill active px-5 py-2.5 bg-white border border-border rounded-full font-medium text-sm transition-all" data-genre="">Toate</button>';

                response.data.forEach(genre => {
                    container.innerHTML += '<a href="/gen/' + genre.slug + '" class="genre-pill px-5 py-2.5 bg-white border border-border rounded-full font-medium text-sm transition-all">' + genre.name + '</a>';
                });
            } else {
                document.getElementById('genresSection').style.display = 'none';
            }
        } catch (e) {
            document.getElementById('genresSection').style.display = 'none';
        }
    },

    async loadCities() {
        try {
            const params = this.category ? '?category=' + this.category : '';
            const response = await AmbiletAPI.get('/cities' + params);
            if (response.data) {
                const select = document.getElementById('filterCity');
                select.innerHTML = '<option value="">Toate orasele</option>';
                response.data.forEach(city => {
                    select.innerHTML += '<option value="' + city.slug + '">' + city.name + ' (' + (city.events_count || 0) + ')</option>';
                });

                // Update cities count badge
                document.getElementById('citiesCount').textContent = response.data.length + ' orase';
            }
        } catch (e) {
            console.warn('Failed to load cities:', e);
        }
    },

    async loadEvents() {
        const container = document.getElementById('eventsGrid');

        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: 12,
                sort: document.getElementById('sortEvents')?.value || 'date_asc'
            });

            // Add filters
            Object.keys(this.filters).forEach(key => {
                if (this.filters[key]) params.append(key, this.filters[key]);
            });

            const response = await AmbiletAPI.get('/events?' + params.toString());
            if (response.data) {
                const events = response.data;
                const meta = response.meta || {};
                this.totalEvents = meta.total || events.length;

                // Update events count badge
                document.getElementById('eventsCount').textContent = this.totalEvents + ' evenimente';

                if (events.length > 0) {
                    container.innerHTML = events.map(event => this.renderEventCard(event)).join('');
                } else {
                    container.innerHTML = '<div class="py-16 text-center col-span-full"><svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><h3 class="mb-2 text-lg font-semibold text-secondary">Nu am gasit evenimente</h3><p class="text-muted">Incearca sa modifici filtrele sau sa cauti altceva</p></div>';
                }

                this.renderPagination(meta);
            }
        } catch (e) {
            console.error('Failed to load events:', e);
            container.innerHTML = '<p class="py-8 text-center col-span-full text-error">Eroare la incarcarea evenimentelor</p>';
        }
    },

    renderEventCard(event) {
        // API returns starts_at, event_date, or start_date
        const eventDate = event.starts_at || event.event_date || event.start_date || event.date;
        const date = new Date(eventDate);
        const day = date.getDate();
        const month = date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '');

        const genreColors = {
            'rock': 'bg-accent',
            'pop': 'bg-blue-600',
            'jazz': 'bg-yellow-600',
            'electronic': 'bg-purple-600',
            'folk': 'bg-emerald-600',
            'metal': 'bg-red-800',
            'alternative': 'bg-purple-600'
        };
        const genreBg = genreColors[event.genre?.slug] || 'bg-accent';

        let statusBadge = '';
        if (event.is_sold_out) {
            statusBadge = '<span class="bg-secondary text-white text-[10px] font-bold px-2.5 py-1 rounded-lg uppercase animate-pulse">Sold Out</span>';
        } else if (event.genre?.name) {
            statusBadge = '<span class="' + genreBg + ' text-white text-[10px] font-bold px-2.5 py-1 rounded-lg uppercase">' + event.genre.name + '</span>';
        }

        let priceDisplay = '';
        if (event.is_sold_out) {
            priceDisplay = '<span class="font-bold line-through text-muted">' + (event.min_price || '--') + ' lei</span><span class="text-xs font-semibold text-primary">Epuizat</span>';
        } else if (event.is_low_stock) {
            priceDisplay = '<span class="font-bold text-primary">de la ' + (event.min_price || '--') + ' lei</span><span class="text-xs font-semibold text-accent">Ultimele locuri</span>';
        } else {
            priceDisplay = '<span class="font-bold text-primary">de la ' + (event.min_price || '--') + ' lei</span><span class="text-xs text-muted">Disponibil</span>';
        }

        // API returns name or title, image_url or image, venue as string or object
        var eventTitle = event.name || event.title || 'Eveniment';
        var eventImage = event.image_url || event.image || '/assets/images/placeholder-event.jpg';
        var eventVenue = (typeof event.venue === 'string' ? event.venue : event.venue?.name) || event.city || 'Romania';
        var eventPrice = event.price_from || event.min_price || '--';

        return '<a href="/bilete/' + event.slug + '" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">' +
            '<div class="relative h-48 overflow-hidden">' +
                (event.is_sold_out ? '<div class="absolute inset-0 bg-black/30"></div>' : '') +
                '<img src="' + eventImage + '" alt="' + eventTitle + '" class="object-cover w-full h-full event-image" loading="lazy">' +
                '<div class="absolute top-3 left-3"><div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl"><span class="block text-xl font-bold leading-none">' + day + '</span><span class="block text-[10px] uppercase tracking-wide mt-0.5">' + month + '</span></div></div>' +
                (statusBadge ? '<div class="absolute top-3 right-3">' + statusBadge + '</div>' : '') +
            '</div>' +
            '<div class="p-4">' +
                '<h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">' + eventTitle + '</h3>' +
                '<p class="text-sm text-muted mt-2 flex items-center gap-1.5"><svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + eventVenue + '</p>' +
                '<div class="flex items-center justify-between pt-3 mt-3 border-t border-border">' + priceDisplay + '</div>' +
            '</div>' +
        '</a>';
    },

    renderPagination(meta) {
        const container = document.getElementById('pagination');
        if (!meta || meta.last_page <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        // Previous button
        if (meta.current_page > 1) {
            html += '<button onclick="CategoryPage.goToPage(' + (meta.current_page - 1) + ')" class="flex items-center justify-center w-10 h-10 transition-colors border rounded-xl border-border hover:bg-surface"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg></button>';
        }

        // Page numbers
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === meta.current_page) {
                html += '<button class="w-10 h-10 font-bold text-white rounded-xl bg-primary">' + i + '</button>';
            } else if (i === 1 || i === meta.last_page || Math.abs(i - meta.current_page) <= 2) {
                html += '<button onclick="CategoryPage.goToPage(' + i + ')" class="w-10 h-10 font-medium transition-colors border rounded-xl border-border hover:bg-surface">' + i + '</button>';
            } else if (Math.abs(i - meta.current_page) === 3) {
                html += '<span class="px-2 text-muted">...</span>';
            }
        }

        // Next button
        if (meta.current_page < meta.last_page) {
            html += '<button onclick="CategoryPage.goToPage(' + (meta.current_page + 1) + ')" class="flex items-center justify-center w-10 h-10 transition-colors border rounded-xl border-border hover:bg-surface"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></button>';
        }

        container.innerHTML = html;
    },

    goToPage(page) {
        this.currentPage = page;
        this.loadEvents();
        window.scrollTo({ top: 300, behavior: 'smooth' });
    },

    bindEvents() {
        // Genre pills
        document.querySelectorAll('.genre-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                if (e.target.tagName === 'A') return; // Let links work normally
                e.preventDefault();
                document.querySelectorAll('.genre-pill').forEach(p => p.classList.remove('active'));
                e.target.classList.add('active');
                this.filters.genre = e.target.dataset.genre || '';
                this.currentPage = 1;
                this.loadEvents();
            });
        });

        // Filter changes
        document.getElementById('filterCity')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('filterDate')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('filterPrice')?.addEventListener('change', () => this.applyFilters());
        document.getElementById('sortEvents')?.addEventListener('change', () => this.loadEvents());
    },

    applyFilters() {
        const city = document.getElementById('filterCity')?.value;
        const dateFilter = document.getElementById('filterDate')?.value;
        const priceRange = document.getElementById('filterPrice')?.value;

        if (city) this.filters.city = city;
        else delete this.filters.city;

        if (dateFilter) this.filters.date_filter = dateFilter;
        else delete this.filters.date_filter;

        if (priceRange) {
            const [min, max] = priceRange.split('-');
            if (min) this.filters.min_price = min;
            else delete this.filters.min_price;
            if (max) this.filters.max_price = max;
            else delete this.filters.max_price;
        } else {
            delete this.filters.min_price;
            delete this.filters.max_price;
        }

        this.currentPage = 1;
        this.loadEvents();
    }
};

document.addEventListener('DOMContentLoaded', () => CategoryPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
