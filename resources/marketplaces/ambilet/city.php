<?php
/**
 * City Page - Events filtered by city
 *
 * Dynamically loads city data from API
 */
require_once __DIR__ . '/includes/config.php';

$citySlug = $_GET['slug'] ?? '';

if (!$citySlug) {
    header('Location: /orase');
    exit;
}

// Default city config (will be overwritten by API data via JavaScript)
// This provides fallback data for SEO and initial render
$cityConfig = [
    'name' => ucwords(str_replace('-', ' ', $citySlug)),
    'description' => 'Descopera cele mai bune evenimente din acest oras.',
    'hero_image' => 'https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=1920&q=80',
    'count' => 0
];

$pageTitle = 'Evenimente în ' . $cityConfig['name'];
$pageDescription = $cityConfig['description'];
$currentPage = 'cities';
$transparentHeader = true;

require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php'; ?>

<!-- Hero Banner -->
<section class="relative h-[420px] md:h-[480px] overflow-hidden">
    <img src="<?= htmlspecialchars($cityConfig['hero_image']) ?>" alt="<?= htmlspecialchars($cityConfig['name']) ?>" class="absolute inset-0 object-cover w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-black/30"></div>
    <div class="relative flex flex-col justify-end h-full px-4 pb-12 mx-auto max-w-7xl">
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/60">
            <a href="/" class="transition-colors hover:text-white">Acasa</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/orase" class="transition-colors hover:text-white">Orașe</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white"><?= htmlspecialchars($cityConfig['name']) ?></span>
        </nav>
        <div class="flex items-center gap-4 mb-4">
            <div class="flex items-center justify-center w-16 h-16 text-3xl shadow-lg bg-primary rounded-2xl">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <span class="text-sm font-medium tracking-wider uppercase text-white/60">Evenimente în</span>
                <h1 class="text-4xl font-extrabold text-white md:text-5xl"><?= htmlspecialchars($cityConfig['name']) ?></h1>
            </div>
        </div>
        <p class="max-w-2xl mb-6 text-lg text-white/80"><?= htmlspecialchars($cityConfig['description']) ?></p>
        <div class="flex flex-wrap items-center gap-3">
            <span class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm" id="eventsCount"><?= $cityConfig['count'] ?> evenimente disponibile</span>
        </div>
    </div>
</section>

<!-- Filters -->
<section class="sticky top-[72px] z-20 py-4 bg-white border-b border-border">
    <div class="flex flex-wrap items-center gap-3 px-4 mx-auto max-w-7xl">
        <select id="categoryFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-surface border-0 rounded-xl focus:ring-2 focus:ring-primary/20" onchange="CityPage.filter()">
            <option value="">Toate categoriile</option>
            <option value="concerte">Concerte</option>
            <option value="festivaluri">Festivaluri</option>
            <option value="teatru">Teatru</option>
            <option value="stand-up">Stand-up</option>
            <option value="sport">Sport</option>
        </select>
        <select id="dateFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-surface border-0 rounded-xl focus:ring-2 focus:ring-primary/20" onchange="CityPage.filter()">
            <option value="">Oricând</option>
            <option value="today">Astăzi</option>
            <option value="weekend">Weekend</option>
            <option value="week">Săptămâna asta</option>
            <option value="month">Luna asta</option>
        </select>
        <select id="priceFilter" class="px-4 py-2.5 pr-10 text-sm font-medium bg-surface border-0 rounded-xl focus:ring-2 focus:ring-primary/20" onchange="CityPage.filter()">
            <option value="">Orice preț</option>
            <option value="free">Gratuit</option>
            <option value="0-50">Sub 50 lei</option>
            <option value="50-100">50 - 100 lei</option>
            <option value="100-200">100 - 200 lei</option>
            <option value="200+">Peste 200 lei</option>
        </select>
        <div class="ml-auto">
            <select id="sortSelect" class="px-4 py-2.5 pr-10 text-sm font-medium bg-surface border-0 rounded-xl focus:ring-2 focus:ring-primary/20" onchange="CityPage.filter()">
                <option value="date">Data (aproape)</option>
                <option value="price_asc">Preț (mic - mare)</option>
                <option value="price_desc">Preț (mare - mic)</option>
                <option value="popular">Popularitate</option>
            </select>
        </div>
    </div>
</section>

<!-- Events Grid -->
<main class="px-4 py-12 mx-auto max-w-7xl">
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" id="eventsGrid">
        <!-- Loading skeletons -->
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="overflow-hidden bg-white border rounded-2xl border-border">
            <div class="h-48 skeleton"></div>
            <div class="p-5">
                <div class="w-3/4 mb-2 skeleton skeleton-title"></div>
                <div class="w-1/2 mb-3 skeleton skeleton-text"></div>
                <div class="w-1/3 h-6 skeleton"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Pagination -->
    <div class="flex items-center justify-center gap-2 mt-12" id="pagination"></div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$citySlugJS = htmlspecialchars($citySlug);
$scriptsExtra = <<<SCRIPTS
<script>
const CityPage = {
    city: '{$citySlugJS}',
    cityData: null,
    page: 1,
    filters: {},

    async init() {
        // First, load city data from API to verify the city exists
        const cityValid = await this.loadCityData();
        if (!cityValid) {
            // City not found - redirect to cities listing
            window.location.href = '/orase';
            return;
        }
        await this.loadEvents();
    },

    async loadCityData() {
        try {
            // Search for city in the cities list
            const response = await AmbiletAPI.get('/api/proxy.php?action=locations.cities&search=' + encodeURIComponent(this.city) + '&per_page=1');

            if (response.success && response.data && response.data.length > 0) {
                // Find exact match
                const city = response.data.find(c => c.slug === this.city);
                if (city) {
                    this.cityData = city;
                    this.updatePageWithCityData(city);
                    return true;
                }
            }

            // City not found in API - use demo mode or show basic info
            console.warn('City not found in API, using fallback');
            return true; // Still allow page to render with fallback data

        } catch (e) {
            console.error('Failed to load city data:', e);
            // On error, still allow page to work with fallback data
            return true;
        }
    },

    updatePageWithCityData(city) {
        // Update page title
        document.title = 'Evenimente in ' + city.name + ' - AmBilet.ro';

        // Update hero section
        const heroTitle = document.querySelector('h1');
        if (heroTitle) {
            heroTitle.textContent = city.name;
        }

        // Update description if we have region info
        const descEl = document.querySelector('.hero-description, .text-white\\/80');
        if (descEl && city.region) {
            descEl.textContent = 'Descopera cele mai bune evenimente din ' + city.name + ', ' + city.region + '.';
        }

        // Update events count badge
        if (city.events_count !== undefined) {
            const countEl = document.getElementById('eventsCount');
            if (countEl) {
                countEl.textContent = city.events_count + ' evenimente disponibile';
            }
        }
    },

    async loadEvents() {
        const container = document.getElementById('eventsGrid');

        try {
            const params = new URLSearchParams({
                city: this.city,
                page: this.page,
                per_page: 12,
                ...this.filters
            });

            const response = await AmbiletAPI.get('/events?' + params.toString());

            if (response.data) {
                const events = response.data;
                if (events.length > 0) {
                    container.innerHTML = events.map(event => this.renderEventCard(event)).join('');
                } else {
                    container.innerHTML = this.getEmptyState();
                }

                if (response.meta) {
                    document.getElementById('eventsCount').textContent = response.meta.total + ' evenimente disponibile';
                    this.renderPagination(response.meta);
                }
            }
        } catch (e) {
            console.error('Failed to load events:', e);
            // Show demo events
            this.loadDemoEvents();
        }
    },

    loadDemoEvents() {
        const demoEvents = [
            { slug: 'concert-demo-1', title: 'Concert Rock în Centrul Vechi', image: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600', start_date: '2025-02-15', venue: { name: 'Club Underground' }, min_price: 60, category: 'Concerte' },
            { slug: 'standup-demo', title: 'Stand-up Comedy Night', image: 'https://images.unsplash.com/photo-1585699324551-f6c309eedeca?w=600', start_date: '2025-02-18', venue: { name: 'Comedy Club' }, min_price: 45, category: 'Stand-up' },
            { slug: 'teatru-demo', title: 'Hamlet - Premiera', image: 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=600', start_date: '2025-02-20', venue: { name: 'Teatrul Național' }, min_price: 80, category: 'Teatru' },
            { slug: 'festival-demo', title: 'Electronic Music Festival', image: 'https://images.unsplash.com/photo-1571266028243-e4c3a0d64c10?w=600', start_date: '2025-03-01', venue: { name: 'Arena Events' }, min_price: 120, category: 'Festivaluri' },
        ];

        document.getElementById('eventsGrid').innerHTML = demoEvents.map(event => this.renderEventCard(event)).join('');
    },

    renderEventCard(event) {
        const date = new Date(event.start_date || event.date);
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        return \`
            <a href="/bilete/\${event.slug}" class="overflow-hidden transition-all bg-white border group rounded-2xl border-border hover:-translate-y-1 hover:shadow-xl hover:border-primary">
                <div class="relative h-48 overflow-hidden">
                    <img src="\${event.image || '/assets/images/placeholder-event.jpg'}" alt="\${event.title}" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" loading="lazy">
                    <div class="absolute top-3 left-3">
                        <div class="px-3 py-2 text-center text-white shadow-lg bg-primary rounded-xl">
                            <span class="block text-lg font-bold leading-none">\${date.getDate()}</span>
                            <span class="block text-[10px] uppercase tracking-wide mt-0.5">\${months[date.getMonth()]}</span>
                        </div>
                    </div>
                    \${event.category ? \`<span class="absolute px-2 py-1 text-xs font-semibold text-white uppercase rounded-lg top-3 right-3 bg-black/60 backdrop-blur-sm">\${event.category}</span>\` : ''}
                </div>
                <div class="p-5">
                    <h3 class="mb-2 font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">\${event.title}</h3>
                    <p class="text-sm text-muted flex items-center gap-1.5 mb-3">
                        <svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        \${event.venue?.name || event.location || 'Locație TBA'}
                    </p>
                    <div class="flex items-center justify-between pt-3 border-t border-border">
                        <span class="font-bold text-primary">de la \${event.min_price || event.price || 50} lei</span>
                        <span class="text-xs text-muted">Disponibil</span>
                    </div>
                </div>
            </a>
        \`;
    },

    getEmptyState() {
        return \`
            <div class="py-16 text-center col-span-full">
                <svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h3 class="mb-2 text-lg font-semibold text-secondary">Nu am găsit evenimente</h3>
                <p class="text-muted">Încearcă să modifici filtrele sau verifică mai târziu</p>
            </div>
        \`;
    },

    filter() {
        this.filters = {
            category: document.getElementById('categoryFilter').value,
            date: document.getElementById('dateFilter').value,
            price: document.getElementById('priceFilter').value,
            sort: document.getElementById('sortSelect').value
        };
        this.page = 1;
        this.loadEvents();
    },

    renderPagination(meta) {
        const container = document.getElementById('pagination');
        if (!meta || meta.last_page <= 1) {
            container.innerHTML = '';
            return;
        }
        // Pagination logic similar to artists.php
        let html = '';
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === meta.current_page) {
                html += \`<button class="w-10 h-10 font-bold text-white rounded-xl bg-primary">\${i}</button>\`;
            } else {
                html += \`<button onclick="CityPage.goToPage(\${i})" class="w-10 h-10 font-medium transition-colors bg-white border rounded-xl border-border hover:bg-surface">\${i}</button>\`;
            }
        }
        container.innerHTML = html;
    },

    goToPage(page) {
        this.page = page;
        this.loadEvents();
        window.scrollTo({ top: 400, behavior: 'smooth' });
    }
};

document.addEventListener('DOMContentLoaded', () => CityPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
