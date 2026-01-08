<?php
/**
 * Events Listing Page - Main events browse and search page
 *
 * Features:
 * - Category filter (from API)
 * - City/Region filter
 * - Genre filter
 * - Artist filter
 * - Price filter
 * - Date filter
 * - Popularity sorting
 * - Search
 * - Pagination
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/nav-cache.php';

// Get filter params from URL
$filterCategory = $_GET['categorie'] ?? $_GET['category'] ?? '';
$filterCity = $_GET['oras'] ?? $_GET['city'] ?? '';
$filterRegion = $_GET['regiune'] ?? $_GET['region'] ?? '';
$filterGenre = $_GET['gen'] ?? $_GET['genre'] ?? '';
$filterArtist = $_GET['artist'] ?? '';
$filterPrice = $_GET['pret'] ?? $_GET['price'] ?? '';
$filterDate = $_GET['data'] ?? $_GET['date'] ?? '';
$filterSort = $_GET['sortare'] ?? $_GET['sort'] ?? 'date';
$searchQuery = $_GET['q'] ?? $_GET['search'] ?? '';

// Load categories for filter
$eventCategories = getEventCategories();
$featuredCities = getFeaturedCities();

$pageTitle = 'Evenimente';
if ($searchQuery) {
    $pageTitle = 'Căutare: ' . htmlspecialchars($searchQuery);
} elseif ($filterCategory) {
    $categoryName = array_filter($eventCategories, fn($c) => $c['slug'] === $filterCategory);
    $categoryName = reset($categoryName);
    $pageTitle = 'Evenimente - ' . ($categoryName['name'] ?? ucfirst($filterCategory));
}

$pageDescription = 'Descoperă cele mai bune evenimente din România. Concerte, festivaluri, teatru, stand-up și multe altele.';
$currentPage = 'events';
$transparentHeader = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="relative pt-40 pb-8 overflow-hidden bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute inset-0 bg-[url('data:image/svg+xml,%3Csvg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cg fill=\"none\" fill-rule=\"evenodd\"%3E%3Cg fill=\"%23ffffff\" fill-opacity=\"1\"%3E%3Cpath d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/%3E%3C/g%3E%3C/g%3E%3C/svg%3E')]"></div>
    </div>
    <div class="relative px-4 mx-auto max-w-7xl">
        <div class="flex flex-col items-center text-center">
            <h1 class="mb-4 text-4xl font-extrabold text-white md:text-5xl">
                <?php if ($searchQuery): ?>
                    Rezultate pentru "<?= htmlspecialchars($searchQuery) ?>"
                <?php elseif ($filterCategory): ?>
                    <?= htmlspecialchars($categoryName['name'] ?? ucfirst($filterCategory)) ?>
                <?php else: ?>
                    Descoperă Evenimente
                <?php endif; ?>
            </h1>
            <p class="max-w-2xl mb-8 text-lg text-gray-300">
                Găsește și cumpără bilete pentru cele mai tari concerte, festivaluri, spectacole de teatru și multe altele.
            </p>
        </div>
    </div>
</section>

<!-- Category Quick Filters -->
<section class="py-6 bg-white border-b border-gray-200">
    <div class="px-4 mx-auto max-w-7xl">
        <div id="categoryFilters" class="flex items-center gap-3 overflow-x-auto scrollbar-hide">
            <button onclick="EventsPage.setCategory('')" data-category="" class="category-btn flex-shrink-0 px-5 py-2.5 text-sm font-semibold rounded-full transition-all cursor-pointer <?= !$filterCategory ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                Toate
            </button>
            <?php foreach ($eventCategories as $category): ?>
            <button onclick="EventsPage.setCategory('<?= addslashes($category['slug']) ?>')" data-category="<?= htmlspecialchars($category['slug']) ?>" class="category-btn flex-shrink-0 flex items-center gap-2 px-5 py-2.5 text-sm font-semibold rounded-full transition-all cursor-pointer <?= $filterCategory === $category['slug'] ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <span class="text-base"><?= $category['icon_emoji'] ?? '🎫' ?></span>
                <?= htmlspecialchars($category['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Filters Bar -->
<section class="sticky top-[72px] z-20 py-4 bg-white border-b border-gray-200 shadow-sm">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-wrap items-center gap-3">
            <!-- City Filter -->
            <select id="cityFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="">Toate orașele</option>
                <?php foreach ($featuredCities as $city): ?>
                <option value="<?= htmlspecialchars($city['slug']) ?>" <?= $filterCity === $city['slug'] ? 'selected' : '' ?>><?= htmlspecialchars($city['name']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Genre Filter -->
            <select id="genreFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="">Toate genurile</option>
                <option value="pop" <?= $filterGenre === 'pop' ? 'selected' : '' ?>>Pop</option>
                <option value="rock" <?= $filterGenre === 'rock' ? 'selected' : '' ?>>Rock</option>
                <option value="hip-hop" <?= $filterGenre === 'hip-hop' ? 'selected' : '' ?>>Hip-Hop</option>
                <option value="electronic" <?= $filterGenre === 'electronic' ? 'selected' : '' ?>>Electronic</option>
                <option value="jazz" <?= $filterGenre === 'jazz' ? 'selected' : '' ?>>Jazz</option>
                <option value="clasic" <?= $filterGenre === 'clasic' ? 'selected' : '' ?>>Clasic</option>
                <option value="folk" <?= $filterGenre === 'folk' ? 'selected' : '' ?>>Folk</option>
            </select>

            <!-- Date Filter -->
            <select id="dateFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="" <?= !$filterDate ? 'selected' : '' ?>>Oricând</option>
                <option value="today" <?= $filterDate === 'today' ? 'selected' : '' ?>>Astăzi</option>
                <option value="tomorrow" <?= $filterDate === 'tomorrow' ? 'selected' : '' ?>>Mâine</option>
                <option value="weekend" <?= $filterDate === 'weekend' ? 'selected' : '' ?>>Weekend</option>
                <option value="week" <?= $filterDate === 'week' ? 'selected' : '' ?>>Săptămâna asta</option>
                <option value="month" <?= $filterDate === 'month' ? 'selected' : '' ?>>Luna asta</option>
                <option value="next-month" <?= $filterDate === 'next-month' ? 'selected' : '' ?>>Luna viitoare</option>
            </select>

            <!-- Price Filter -->
            <select id="priceFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                <option value="" <?= !$filterPrice ? 'selected' : '' ?>>Orice preț</option>
                <option value="free" <?= $filterPrice === 'free' ? 'selected' : '' ?>>Gratuit</option>
                <option value="0-50" <?= $filterPrice === '0-50' ? 'selected' : '' ?>>Sub 50 lei</option>
                <option value="50-100" <?= $filterPrice === '50-100' ? 'selected' : '' ?>>50 - 100 lei</option>
                <option value="100-200" <?= $filterPrice === '100-200' ? 'selected' : '' ?>>100 - 200 lei</option>
                <option value="200-500" <?= $filterPrice === '200-500' ? 'selected' : '' ?>>200 - 500 lei</option>
                <option value="500+" <?= $filterPrice === '500+' ? 'selected' : '' ?>>Peste 500 lei</option>
            </select>

            <!-- Sort -->
            <div class="flex items-center gap-2 ml-auto">
                <span class="text-sm text-gray-500">Sortare:</span>
                <select id="sortFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" onchange="EventsPage.applyFilters()">
                    <option value="date" <?= $filterSort === 'date' ? 'selected' : '' ?>>Data (aproape)</option>
                    <option value="popular" <?= $filterSort === 'popular' ? 'selected' : '' ?>>Popularitate</option>
                    <option value="price_asc" <?= $filterSort === 'price_asc' ? 'selected' : '' ?>>Preț (mic - mare)</option>
                    <option value="price_desc" <?= $filterSort === 'price_desc' ? 'selected' : '' ?>>Preț (mare - mic)</option>
                    <option value="newest" <?= $filterSort === 'newest' ? 'selected' : '' ?>>Cele mai noi</option>
                </select>
            </div>
        </div>

        <!-- Active Filters -->
        <div id="activeFilters" class="flex flex-wrap items-center gap-2 mt-3" style="display: none;">
            <span class="text-sm text-gray-500">Filtre active:</span>
            <div id="activeFilterTags" class="flex flex-wrap gap-2"></div>
            <button onclick="EventsPage.clearFilters()" class="ml-2 text-sm font-medium text-primary hover:underline">Șterge toate</button>
        </div>
    </div>
</section>

<!-- Results Info -->
<section class="py-4 bg-gray-50">
    <div class="flex items-center justify-between px-4 mx-auto max-w-7xl">
        <p class="text-sm text-gray-600">
            <span id="resultsCount" class="font-semibold text-gray-900">0</span> evenimente găsite
        </p>
        <div class="flex items-center gap-2">
            <button onclick="EventsPage.setView('grid')" id="viewGrid" class="p-2 transition-colors bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
            </button>
            <button onclick="EventsPage.setView('list')" id="viewList" class="p-2 transition-colors bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>
</section>

<!-- Events Grid -->
<section class="py-8 bg-gray-50">
    <div class="px-4 mx-auto max-w-7xl">
        <!-- Loading State -->
        <div id="loadingState" class="py-20 text-center">
            <div class="inline-block w-12 h-12 border-4 rounded-full border-primary border-t-transparent animate-spin"></div>
            <p class="mt-4 text-gray-500">Se încarcă evenimentele...</p>
        </div>

        <!-- Events Container -->
        <div id="eventsGrid" class="hidden grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"></div>

        <!-- Empty State -->
        <div id="emptyState" class="hidden py-20 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3 class="mb-2 text-xl font-bold text-gray-900">Nu am găsit evenimente</h3>
            <p class="mb-6 text-gray-500">Încearcă să modifici filtrele sau să cauți altceva.</p>
            <button onclick="EventsPage.clearFilters()" class="px-6 py-3 font-semibold text-white transition-colors rounded-xl bg-primary hover:bg-primary-dark">
                Resetează filtrele
            </button>
        </div>

        <!-- Pagination -->
        <div id="pagination" class="flex items-center justify-center gap-2 mt-10"></div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
<?php require_once __DIR__ . '/includes/scripts.php'; ?>

<script>
const EventsPage = {
    events: [],
    page: 1,
    perPage: 12,
    totalPages: 1,
    view: 'grid',
    filters: {
        category: '<?= addslashes($filterCategory) ?>',
        city: '<?= addslashes($filterCity) ?>',
        region: '<?= addslashes($filterRegion) ?>',
        genre: '<?= addslashes($filterGenre) ?>',
        artist: '<?= addslashes($filterArtist) ?>',
        price: '<?= addslashes($filterPrice) ?>',
        date: '<?= addslashes($filterDate) ?>',
        sort: '<?= addslashes($filterSort) ?>',
        search: '<?= addslashes($searchQuery) ?>'
    },

    init() {
        this.loadEvents();
        this.updateActiveFilters();
        this.setView(this.view);
    },

    async loadEvents() {
        document.getElementById('loadingState').classList.remove('hidden');
        document.getElementById('eventsGrid').classList.add('hidden');
        document.getElementById('emptyState').classList.add('hidden');

        try {
            const params = new URLSearchParams({
                page: this.page,
                per_page: this.perPage,
                ...Object.fromEntries(Object.entries(this.filters).filter(([_, v]) => v))
            });

            const response = await fetch('/api/proxy.php?action=events&' + params);
            const data = await response.json();

            // API returns data directly as array or in data.events
            let eventsData = null;
            let meta = null;

            if (data.success) {
                if (Array.isArray(data.data)) {
                    // API returns events array directly in data
                    eventsData = data.data;
                    meta = data.meta;
                } else if (data.data && data.data.events) {
                    // Or nested in data.events
                    eventsData = data.data.events;
                    meta = data.data.meta;
                } else if (data.data) {
                    // Single object case - wrap in array
                    eventsData = [data.data];
                }
            }

            if (eventsData && eventsData.length > 0) {
                // Transform API response to expected format
                this.events = eventsData.map(function(e) {
                    // Format time to HH:MM (remove seconds if present)
                    var time = e.start_time || '20:00';
                    if (time && time.length > 5) {
                        time = time.substring(0, 5); // "20:00:00" -> "20:00"
                    }
                    return {
                        id: e.id,
                        title: e.name || e.title || 'Eveniment',
                        slug: e.slug,
                        date: e.starts_at || e.event_date || e.date,
                        time: time,
                        venue: e.venue || 'Locatie TBA',
                        city: e.city || '',
                        category: e.category || 'Evenimente',
                        price_from: e.price_from || e.min_price || 0,
                        image: e.image_url || e.image || '/assets/images/placeholder-event.jpg'
                    };
                });
                this.totalPages = meta?.last_page || 1;
                document.getElementById('resultsCount').textContent = meta?.total || this.events.length;
                this.renderEvents();
                this.renderPagination();
            } else {
                this.showEmpty();
            }
        } catch (error) {
            console.error('Error loading events:', error);
            // Fallback to demo data
            this.loadDemoData();
        }
    },

    loadDemoData() {
        this.events = [
            {
                id: 1,
                title: 'Coldplay - Music of the Spheres Tour',
                slug: 'coldplay-music-of-the-spheres',
                date: '2025-06-15',
                time: '20:00',
                venue: 'Arena Națională',
                city: 'București',
                category: 'Concerte',
                price_from: 299,
                image: 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=800&h=500&fit=crop'
            },
            {
                id: 2,
                title: 'UNTOLD Festival 2025',
                slug: 'untold-2025',
                date: '2025-08-07',
                time: '16:00',
                venue: 'Cluj Arena',
                city: 'Cluj-Napoca',
                category: 'Festivaluri',
                price_from: 449,
                image: 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=800&h=500&fit=crop'
            },
            {
                id: 3,
                title: 'Electric Castle 2025',
                slug: 'electric-castle-2025',
                date: '2025-07-16',
                time: '14:00',
                venue: 'Castelul Banffy',
                city: 'Bonțida',
                category: 'Festivaluri',
                price_from: 359,
                image: 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&h=500&fit=crop'
            },
            {
                id: 4,
                title: 'Micutzu - Stand-up Comedy',
                slug: 'micutzu-stand-up',
                date: '2025-02-20',
                time: '19:00',
                venue: 'Sala Palatului',
                city: 'București',
                category: 'Stand-up',
                price_from: 89,
                image: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=800&h=500&fit=crop'
            },
            {
                id: 5,
                title: 'Lacul Lebedelor - Balet',
                slug: 'lacul-lebedelor',
                date: '2025-03-08',
                time: '19:30',
                venue: 'Opera Națională',
                city: 'București',
                category: 'Teatru',
                price_from: 120,
                image: 'https://images.unsplash.com/photo-1518834107812-67b0b7c58434?w=800&h=500&fit=crop'
            },
            {
                id: 6,
                title: 'Carla\'s Dreams - Concert',
                slug: 'carlas-dreams-concert',
                date: '2025-04-12',
                time: '20:00',
                venue: 'BT Arena',
                city: 'Cluj-Napoca',
                category: 'Concerte',
                price_from: 149,
                image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=800&h=500&fit=crop'
            },
            {
                id: 7,
                title: 'Neversea 2025',
                slug: 'neversea-2025',
                date: '2025-07-03',
                time: '15:00',
                venue: 'Plaja Modern',
                city: 'Constanța',
                category: 'Festivaluri',
                price_from: 389,
                image: 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=800&h=500&fit=crop'
            },
            {
                id: 8,
                title: 'Delia - Concert Aniversar',
                slug: 'delia-concert-aniversar',
                date: '2025-05-20',
                time: '20:00',
                venue: 'Arenele Romane',
                city: 'București',
                category: 'Concerte',
                price_from: 179,
                image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=800&h=500&fit=crop'
            }
        ];
        document.getElementById('resultsCount').textContent = this.events.length;
        this.renderEvents();
    },

    renderEvents() {
        const grid = document.getElementById('eventsGrid');
        document.getElementById('loadingState').classList.add('hidden');

        if (this.events.length === 0) {
            this.showEmpty();
            return;
        }

        grid.innerHTML = this.events.map(event => this.renderEventCard(event)).join('');
        grid.classList.remove('hidden');
        grid.classList.add('grid');
    },

    renderEventCard(event) {
        const date = new Date(event.date);
        const formattedDay = date.toLocaleDateString('ro-RO', { weekday: 'short' });
        const formattedMonth = date.toLocaleDateString('ro-RO', { month: 'short' });
        const venueCity = event.city ? event.venue + ', ' + event.city : event.venue;

        return '<a href="/bilete/' + event.slug + '" class="overflow-hidden transition-all bg-white border border-gray-200 group rounded-2xl hover:shadow-xl hover:-translate-y-1">' +
            '<div class="relative aspect-[16/10] overflow-hidden">' +
                '<img src="' + event.image + '" alt="' + event.title + '" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105">' +
                '<div class="absolute top-3 left-3">' +
                    '<span class="px-3 py-1 text-xs font-bold text-white rounded-full bg-primary">' + event.category + '</span>' +
                '</div>' +
                '<div class="absolute flex flex-col items-center justify-center text-center bg-white shadow-lg w-14 h-14 rounded-xl top-3 right-3">' +
                    '<span class="text-xs font-medium text-gray-500 uppercase">' + formattedDay + '</span>' +
                    '<span class="text-lg font-bold text-gray-900">' + date.getDate() + '</span>' +
                    '<span class="text-[10px] font-medium text-gray-500 uppercase">' + formattedMonth + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="p-4">' +
                '<h3 class="mb-2 text-lg font-bold text-gray-900 transition-colors line-clamp-2 group-hover:text-primary">' + event.title + '</h3>' +
                '<div class="flex items-center gap-2 mb-3 text-sm text-gray-500">' +
                    '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>' +
                    '</svg>' +
                    venueCity +
                '</div>' +
                '<div class="flex items-center justify-between">' +
                    '<div class="flex items-center gap-1 text-sm text-gray-500">' +
                        '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>' +
                        '</svg>' +
                        event.time +
                    '</div>' +
                    '<div class="text-right">' +
                        '<span class="text-xs text-gray-500">de la</span>' +
                        '<span class="ml-1 text-lg font-bold text-primary">' + event.price_from + ' lei</span>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</a>';
    },

    showEmpty() {
        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('eventsGrid').classList.add('hidden');
        document.getElementById('emptyState').classList.remove('hidden');
    },

    renderPagination() {
        const container = document.getElementById('pagination');
        if (this.totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        // Previous button
        if (this.page > 1) {
            html += `<button onclick="EventsPage.goToPage(${this.page - 1})" class="px-4 py-2 font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">Anterior</button>`;
        }

        // Page numbers
        for (let i = 1; i <= this.totalPages; i++) {
            if (i === this.page) {
                html += `<button class="w-10 h-10 font-bold text-white rounded-xl bg-primary">${i}</button>`;
            } else if (i === 1 || i === this.totalPages || (i >= this.page - 2 && i <= this.page + 2)) {
                html += `<button onclick="EventsPage.goToPage(${i})" class="w-10 h-10 font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">${i}</button>`;
            } else if (i === this.page - 3 || i === this.page + 3) {
                html += `<span class="px-2">...</span>`;
            }
        }

        // Next button
        if (this.page < this.totalPages) {
            html += `<button onclick="EventsPage.goToPage(${this.page + 1})" class="px-4 py-2 font-medium text-gray-700 transition-colors bg-white border border-gray-200 rounded-xl hover:bg-gray-50">Următor</button>`;
        }

        container.innerHTML = html;
    },

    goToPage(page) {
        this.page = page;
        this.loadEvents();
        window.scrollTo({ top: 400, behavior: 'smooth' });
    },

    applyFilters() {
        this.filters.city = document.getElementById('cityFilter').value;
        this.filters.genre = document.getElementById('genreFilter').value;
        this.filters.date = document.getElementById('dateFilter').value;
        this.filters.price = document.getElementById('priceFilter').value;
        this.filters.sort = document.getElementById('sortFilter').value;

        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    setCategory(categorySlug) {
        // Update the filter value
        this.filters.category = categorySlug;

        // Update visual state of category buttons
        const buttons = document.querySelectorAll('#categoryFilters .category-btn');
        buttons.forEach(btn => {
            const btnCategory = btn.getAttribute('data-category') || '';
            if (btnCategory === categorySlug) {
                btn.classList.remove('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
                btn.classList.add('bg-primary', 'text-white');
            } else {
                btn.classList.remove('bg-primary', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
            }
        });

        // Reset to page 1 and reload
        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    search() {
        this.filters.search = document.getElementById('searchInput').value;
        this.page = 1;
        this.updateURL();
        this.loadEvents();
    },

    clearFilters() {
        this.filters = {
            category: '',
            city: '',
            region: '',
            genre: '',
            artist: '',
            price: '',
            date: '',
            sort: 'date',
            search: ''
        };

        // Reset category buttons visual state
        const buttons = document.querySelectorAll('#categoryFilters .category-btn');
        buttons.forEach(btn => {
            const btnCategory = btn.getAttribute('data-category') || '';
            if (btnCategory === '') {
                // "Toate" button - make it active
                btn.classList.remove('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
                btn.classList.add('bg-primary', 'text-white');
            } else {
                // Other buttons - make them inactive
                btn.classList.remove('bg-primary', 'text-white');
                btn.classList.add('bg-gray-100', 'text-gray-700', 'hover:bg-gray-200');
            }
        });

        // Reset dropdown filters
        document.getElementById('cityFilter').value = '';
        document.getElementById('genreFilter').value = '';
        document.getElementById('dateFilter').value = '';
        document.getElementById('priceFilter').value = '';
        document.getElementById('sortFilter').value = 'date';
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';

        this.page = 1;
        this.updateURL();
        this.updateActiveFilters();
        this.loadEvents();
    },

    updateURL() {
        const params = new URLSearchParams();
        if (this.filters.category) params.set('categorie', this.filters.category);
        if (this.filters.city) params.set('oras', this.filters.city);
        if (this.filters.genre) params.set('gen', this.filters.genre);
        if (this.filters.date) params.set('data', this.filters.date);
        if (this.filters.price) params.set('pret', this.filters.price);
        if (this.filters.sort && this.filters.sort !== 'date') params.set('sortare', this.filters.sort);
        if (this.filters.search) params.set('q', this.filters.search);

        const newURL = params.toString() ? `/evenimente?${params}` : '/evenimente';
        history.pushState({}, '', newURL);
    },

    updateActiveFilters() {
        const container = document.getElementById('activeFilters');
        const tagsContainer = document.getElementById('activeFilterTags');
        const activeFilters = [];

        if (this.filters.category) {
            // Get category name from the button text
            const categoryBtn = document.querySelector('#categoryFilters .category-btn[data-category="' + this.filters.category + '"]');
            const categoryName = categoryBtn ? categoryBtn.textContent.trim() : this.filters.category;
            activeFilters.push({ key: 'category', label: 'Categorie: ' + categoryName });
        }
        if (this.filters.city) activeFilters.push({ key: 'city', label: 'Oraș: ' + this.filters.city });
        if (this.filters.genre) activeFilters.push({ key: 'genre', label: 'Gen: ' + this.filters.genre });
        if (this.filters.date) activeFilters.push({ key: 'date', label: 'Data: ' + this.filters.date });
        if (this.filters.price) activeFilters.push({ key: 'price', label: 'Preț: ' + this.filters.price });
        if (this.filters.search) activeFilters.push({ key: 'search', label: 'Căutare: ' + this.filters.search });

        if (activeFilters.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.style.display = 'flex';
        tagsContainer.innerHTML = activeFilters.map(f => `
            <span class="inline-flex items-center gap-1 px-3 py-1 text-sm font-medium text-gray-700 bg-gray-100 rounded-full">
                ${f.label}
                <button onclick="EventsPage.removeFilter('${f.key}')" class="ml-1 text-gray-400 hover:text-gray-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </span>
        `).join('');
    },

    removeFilter(key) {
        this.filters[key] = '';

        if (key === 'category') {
            // Reset category button visual state
            this.setCategory('');
            return; // setCategory already handles the rest
        }

        const elementId = {
            city: 'cityFilter',
            genre: 'genreFilter',
            date: 'dateFilter',
            price: 'priceFilter',
            search: 'searchInput'
        }[key];
        if (elementId) document.getElementById(elementId).value = '';
        this.applyFilters();
    },

    setView(view) {
        this.view = view;
        const grid = document.getElementById('eventsGrid');
        const gridBtn = document.getElementById('viewGrid');
        const listBtn = document.getElementById('viewList');

        if (view === 'grid') {
            grid.classList.remove('grid-cols-1');
            grid.classList.add('sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4');
            gridBtn.classList.add('bg-primary', 'text-white');
            gridBtn.classList.remove('bg-white');
            listBtn.classList.remove('bg-primary', 'text-white');
            listBtn.classList.add('bg-white');
        } else {
            grid.classList.add('grid-cols-1');
            grid.classList.remove('sm:grid-cols-2', 'lg:grid-cols-3', 'xl:grid-cols-4');
            listBtn.classList.add('bg-primary', 'text-white');
            listBtn.classList.remove('bg-white');
            gridBtn.classList.remove('bg-primary', 'text-white');
            gridBtn.classList.add('bg-white');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => EventsPage.init());
</script>
