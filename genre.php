<?php
/**
 * Genre Page - Events filtered by music genre
 * Based on genre.html template
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/category-config.php';

$genreSlug = $_GET['slug'] ?? $_GET['genre'] ?? '';

// Get genre config from centralized file
$genreConfig = getGenre($genreSlug);

if ($genreConfig) {
    $pageTitle = $genreConfig['name'];
    $pageDescription = $genreConfig['description'];
    $genreIcon = $genreConfig['icon'];
    $genreHeroImage = $genreConfig['hero_image'];
    $genreColor = $genreConfig['color'];
    $parentCategory = $genreConfig['category'];
    $parentCategoryConfig = getCategory($parentCategory);
} else {
    $pageTitle = $genreSlug ? ucfirst(str_replace('-', ' ', $genreSlug)) : 'Gen muzical';
    $pageDescription = 'Descopera cele mai tari evenimente din acest gen.';
    $genreIcon = '🎵';
    $genreHeroImage = getHeroImage('rock', 'genre');
    $genreColor = '#A51C30';
    $parentCategory = 'concerte';
    $parentCategoryConfig = getCategory('concerte');
}

$currentPage = 'events';

require_once __DIR__ . '/includes/head.php';
?>

<?php require_once __DIR__ . '/includes/header.php'; ?>

<!-- Hero Banner -->
<section class="relative overflow-hidden h-72 md:h-96">
    <img id="genreBanner" src="<?= htmlspecialchars($genreHeroImage) ?>" alt="<?= htmlspecialchars($pageTitle) ?>" class="absolute inset-0 object-cover w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/60 to-black/30"></div>
    <div class="relative flex flex-col justify-end h-full px-4 pb-10 mx-auto max-w-7xl">
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/60">
            <a href="/" class="transition-colors hover:text-white">Acasa</a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/<?= htmlspecialchars($parentCategory) ?>" id="parentCategoryLink" class="transition-colors hover:text-white"><?= htmlspecialchars($parentCategoryConfig['name'] ?? 'Concerte') ?></a>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white" id="genreBreadcrumb"><?= htmlspecialchars($pageTitle) ?></span>
        </nav>
        <div class="flex items-center gap-4 mb-4">
            <span id="genreIcon" class="flex items-center justify-center w-16 h-16 text-3xl shadow-lg rounded-2xl" style="background-color: <?= htmlspecialchars($genreColor) ?>"><?= $genreIcon ?></span>
            <div>
                <span class="text-sm font-medium tracking-wider uppercase text-white/60">Gen muzical</span>
                <h1 id="pageTitle" class="text-4xl font-extrabold text-white md:text-5xl"><?= htmlspecialchars($pageTitle) ?></h1>
            </div>
        </div>
        <p id="pageDescription" class="max-w-2xl mb-6 text-lg text-white/80">Descopera cele mai electrizante evenimente din acest gen.</p>
        <div class="flex flex-wrap items-center gap-3">
            <span id="eventsCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- evenimente</span>
            <span id="artistsCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- artisti</span>
            <span id="citiesCount" class="px-4 py-2 text-sm font-medium text-white rounded-full bg-white/10 backdrop-blur-sm">-- orase</span>
        </div>
    </div>
</section>

<!-- Featured Artists -->
<section class="py-10 bg-white border-b border-border" id="artistsSection">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-6 text-xl font-bold text-secondary">Artisti populari in acest gen</h2>
        <div class="flex gap-4 px-4 pb-4 -mx-4 overflow-x-auto" style="scrollbar-width: none;" id="artistsScroll">
            <!-- Artists will be loaded dynamically -->
            <div class="flex-shrink-0 w-32">
                <div class="w-20 h-20 mx-auto mb-3 rounded-full skeleton"></div>
                <div class="w-16 mx-auto skeleton skeleton-text"></div>
            </div>
            <div class="flex-shrink-0 w-32">
                <div class="w-20 h-20 mx-auto mb-3 rounded-full skeleton"></div>
                <div class="w-16 mx-auto skeleton skeleton-text"></div>
            </div>
            <div class="flex-shrink-0 w-32">
                <div class="w-20 h-20 mx-auto mb-3 rounded-full skeleton"></div>
                <div class="w-16 mx-auto skeleton skeleton-text"></div>
            </div>
        </div>
    </div>
</section>

<!-- Subgenres -->
<section class="py-8 bg-white border-b border-border" id="subgenresSection">
    <div class="px-4 mx-auto max-w-7xl">
        <h3 class="mb-4 text-sm font-semibold tracking-wider uppercase text-muted">Subgenuri</h3>
        <div class="flex flex-wrap gap-2" id="subgenresPills">
            <button class="px-4 py-2 text-sm font-medium text-white rounded-full bg-primary" data-subgenre="">Toate</button>
            <!-- Subgenres will be loaded dynamically -->
        </div>
    </div>
</section>

<!-- Events Content -->
<section class="py-8 md:py-12">
    <div class="px-4 mx-auto max-w-7xl">
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
            </div>
            <div class="flex items-center gap-3">
                <span id="resultsCount" class="text-sm text-muted">-- rezultate</span>
                <select id="sortEvents" class="px-4 py-2.5 bg-surface border border-border rounded-xl text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="date_asc">Data (aproape)</option>
                    <option value="date_desc">Data (departe)</option>
                    <option value="price_asc">Pret (mic)</option>
                    <option value="price_desc">Pret (mare)</option>
                    <option value="popularity">Popularitate</option>
                </select>
            </div>
        </div>

        <!-- Featured Event -->
        <div class="mb-8" id="featuredEventSection" style="display: none;">
            <a href="#" id="featuredEvent" class="block overflow-hidden transition-shadow bg-white border rounded-3xl border-border hover:shadow-xl">
                <!-- Featured event will be loaded dynamically -->
            </a>
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

        <!-- Load More -->
        <div class="mt-12 text-center" id="loadMoreSection">
            <button id="loadMoreBtn" onclick="GenrePage.loadMore()" class="inline-flex items-center gap-2 px-8 py-4 font-bold transition-all border-2 border-primary text-primary rounded-xl hover:bg-primary hover:text-white">
                Incarca mai multe
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<SCRIPTS
<script>
const GenrePage = {
    genre: '$genreSlug',
    currentPage: 1,
    totalEvents: 0,
    hasMore: false,
    filters: {},

    async init() {
        if (!this.genre) {
            window.location.href = '/categorie/concerte';
            return;
        }

        this.filters.genre = this.genre;

        // Load data in parallel
        await Promise.all([
            this.loadGenreInfo(),
            this.loadArtists(),
            this.loadSubgenres(),
            this.loadCities(),
            this.loadEvents()
        ]);

        this.bindEvents();
    },

    async loadGenreInfo() {
        try {
            const response = await AmbiletAPI.get('/genres/' + this.genre);
            if (response.data) {
                const genre = response.data;
                document.getElementById('pageTitle').textContent = genre.name;
                document.getElementById('genreBreadcrumb').textContent = genre.name;
                if (genre.description) {
                    document.getElementById('pageDescription').textContent = genre.description;
                }
                if (genre.icon) {
                    document.getElementById('genreIcon').textContent = genre.icon;
                }
                if (genre.image) {
                    document.getElementById('genreBanner').src = genre.image;
                }
                if (genre.category) {
                    const link = document.getElementById('parentCategoryLink');
                    link.textContent = genre.category.name;
                    link.href = '/categorie/' + genre.category.slug;
                }
            }
        } catch (e) {
            console.warn('Failed to load genre info:', e);
        }
    },

    async loadArtists() {
        try {
            const response = await AmbiletAPI.get('/artists?genre=' + this.genre + '&limit=6');
            if (response.data && response.data.length > 0) {
                const container = document.getElementById('artistsScroll');
                container.innerHTML = response.data.map(artist => \`
                    <a href="/artist/\${artist.slug}" class="flex flex-col items-center flex-shrink-0 gap-3 p-4 artist-card bg-surface rounded-2xl hover:bg-primary/5">
                        <img src="\${artist.image || '/assets/images/placeholder-artist.jpg'}" alt="\${artist.name}" class="object-cover w-20 h-20 rounded-full ring-4 ring-primary/20">
                        <span class="text-sm font-semibold text-secondary">\${artist.name}</span>
                        <span class="text-xs text-muted">\${artist.events_count || 0} evenimente</span>
                    </a>
                \`).join('');

                document.getElementById('artistsCount').textContent = response.data.length + ' artisti';
            } else {
                document.getElementById('artistsSection').style.display = 'none';
            }
        } catch (e) {
            document.getElementById('artistsSection').style.display = 'none';
        }
    },

    async loadSubgenres() {
        try {
            const response = await AmbiletAPI.get('/subgenres?genre=' + this.genre);
            if (response.data && response.data.length > 0) {
                const container = document.getElementById('subgenresPills');
                container.innerHTML = '<button class="px-4 py-2 text-sm font-medium text-white rounded-full bg-primary" data-subgenre="">Toate</button>';

                response.data.forEach(sub => {
                    container.innerHTML += \`<button class="px-4 py-2 text-sm font-medium transition-all border rounded-full bg-surface border-border hover:bg-primary hover:text-white hover:border-primary" data-subgenre="\${sub.slug}">\${sub.name}</button>\`;
                });
            } else {
                document.getElementById('subgenresSection').style.display = 'none';
            }
        } catch (e) {
            document.getElementById('subgenresSection').style.display = 'none';
        }
    },

    async loadCities() {
        try {
            const response = await AmbiletAPI.get('/cities?genre=' + this.genre);
            if (response.data) {
                const select = document.getElementById('filterCity');
                select.innerHTML = '<option value="">Toate orasele</option>';
                response.data.forEach(city => {
                    select.innerHTML += \`<option value="\${city.slug}">\${city.name}</option>\`;
                });
                document.getElementById('citiesCount').textContent = response.data.length + ' orase';
            }
        } catch (e) {
            console.warn('Failed to load cities:', e);
        }
    },

    async loadEvents(append = false) {
        const container = document.getElementById('eventsGrid');

        if (!append) {
            this.currentPage = 1;
        }

        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                per_page: 12,
                sort: document.getElementById('sortEvents')?.value || 'date_asc'
            });

            Object.keys(this.filters).forEach(key => {
                if (this.filters[key]) params.append(key, this.filters[key]);
            });

            const response = await AmbiletAPI.get('/events?' + params.toString());
            if (response.data) {
                const events = response.data;
                const meta = response.meta || {};
                this.totalEvents = meta.total || events.length;
                this.hasMore = meta.current_page < meta.last_page;

                document.getElementById('eventsCount').textContent = this.totalEvents + ' evenimente';
                document.getElementById('resultsCount').textContent = this.totalEvents + ' rezultate';

                // Render featured event (first one) if first page
                if (!append && events.length > 0) {
                    this.renderFeaturedEvent(events[0]);
                    if (events.length > 1) {
                        const gridHtml = events.slice(1).map(e => this.renderEventCard(e)).join('');
                        container.innerHTML = gridHtml;
                    } else {
                        container.innerHTML = '';
                    }
                } else if (append) {
                    container.innerHTML += events.map(e => this.renderEventCard(e)).join('');
                } else if (events.length === 0) {
                    document.getElementById('featuredEventSection').style.display = 'none';
                    container.innerHTML = '<div class="py-16 text-center col-span-full"><svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><h3 class="mb-2 text-lg font-semibold text-secondary">Nu am gasit evenimente</h3><p class="text-muted">Incearca sa modifici filtrele sau sa cauti altceva</p></div>';
                }

                // Show/hide load more button
                document.getElementById('loadMoreSection').style.display = this.hasMore ? 'block' : 'none';
            }
        } catch (e) {
            console.error('Failed to load events:', e);
            container.innerHTML = '<p class="py-8 text-center col-span-full text-error">Eroare la incarcarea evenimentelor</p>';
        }
    },

    renderFeaturedEvent(event) {
        const section = document.getElementById('featuredEventSection');
        const container = document.getElementById('featuredEvent');
        const date = new Date(event.start_date);
        const day = date.getDate();
        const month = date.toLocaleDateString('ro-RO', { month: 'long' });
        const dayName = date.toLocaleDateString('ro-RO', { weekday: 'long' });

        container.href = '/eveniment/' + event.slug;
        container.innerHTML = \`
            <div class="flex flex-col lg:flex-row">
                <div class="relative h-64 overflow-hidden lg:w-2/5 lg:h-auto">
                    <img src="\${event.image || '/assets/images/placeholder-event.jpg'}" alt="\${event.title}" class="object-cover w-full h-full">
                    <div class="absolute top-4 left-4">
                        <span class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg uppercase">Recomandat</span>
                    </div>
                </div>
                <div class="flex flex-col justify-center p-6 lg:w-3/5 lg:p-8">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="px-4 py-3 text-center text-white date-badge rounded-xl">
                            <span class="block text-2xl font-bold leading-none">\${day}</span>
                            <span class="block mt-1 text-xs tracking-wide uppercase">\${month}</span>
                        </div>
                        <div>
                            <span class="text-sm capitalize text-muted">\${dayName}</span>
                            <p class="text-sm text-muted">\${event.start_time || '20:00'}</p>
                        </div>
                    </div>
                    <h2 class="mb-3 text-2xl font-bold transition-colors lg:text-3xl text-secondary hover:text-primary">\${event.title}</h2>
                    <p class="mb-4 text-muted line-clamp-2">\${event.description || ''}</p>
                    <div class="flex items-center gap-4 mb-6">
                        <span class="flex items-center gap-1.5 text-sm text-muted">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                            \${event.venue?.name || event.city?.name || 'Romania'}
                        </span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-sm text-muted">de la</span>
                            <span class="ml-1 text-2xl font-bold text-primary">\${event.min_price || '--'} lei</span>
                        </div>
                        <span class="px-6 py-3 font-bold text-white transition-all btn-primary rounded-xl">
                            Cumpara bilete &rarr;
                        </span>
                    </div>
                </div>
            </div>
        \`;

        section.style.display = 'block';
    },

    renderEventCard(event) {
        const date = new Date(event.start_date);
        const day = date.getDate();
        const month = date.toLocaleDateString('ro-RO', { month: 'short' }).replace('.', '');

        let priceDisplay = '<span class="font-bold text-primary">de la ' + (event.min_price || '--') + ' lei</span><span class="text-xs text-muted">Disponibil</span>';
        if (event.is_sold_out) {
            priceDisplay = '<span class="font-bold line-through text-muted">' + (event.min_price || '--') + ' lei</span><span class="text-xs font-semibold text-primary">Epuizat</span>';
        } else if (event.is_low_stock) {
            priceDisplay = '<span class="font-bold text-primary">de la ' + (event.min_price || '--') + ' lei</span><span class="text-xs font-semibold text-accent">Ultimele locuri</span>';
        }

        return '<a href="/eveniment/' + event.slug + '" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">' +
            '<div class="relative h-48 overflow-hidden">' +
                '<img src="' + (event.image || '/assets/images/placeholder-event.jpg') + '" alt="' + event.title + '" class="object-cover w-full h-full event-image" loading="lazy">' +
                '<div class="absolute top-3 left-3"><div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl"><span class="block text-xl font-bold leading-none">' + day + '</span><span class="block text-[10px] uppercase tracking-wide mt-0.5">' + month + '</span></div></div>' +
            '</div>' +
            '<div class="p-4">' +
                '<h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">' + event.title + '</h3>' +
                '<p class="text-sm text-muted mt-2 flex items-center gap-1.5"><svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' + (event.venue?.name || event.city?.name || 'Romania') + '</p>' +
                '<div class="flex items-center justify-between pt-3 mt-3 border-t border-border">' + priceDisplay + '</div>' +
            '</div>' +
        '</a>';
    },

    loadMore() {
        this.currentPage++;
        this.loadEvents(true);
    },

    bindEvents() {
        // Subgenre pills
        document.querySelectorAll('#subgenresPills button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('#subgenresPills button').forEach(b => {
                    b.classList.remove('bg-primary', 'text-white');
                    b.classList.add('bg-surface', 'border', 'border-border');
                });
                e.target.classList.remove('bg-surface', 'border', 'border-border');
                e.target.classList.add('bg-primary', 'text-white');

                const subgenre = e.target.dataset.subgenre;
                if (subgenre) {
                    this.filters.subgenre = subgenre;
                } else {
                    delete this.filters.subgenre;
                }
                this.loadEvents();
            });
        });

        // Filter changes
        document.getElementById('filterCity')?.addEventListener('change', (e) => {
            if (e.target.value) {
                this.filters.city = e.target.value;
            } else {
                delete this.filters.city;
            }
            this.loadEvents();
        });

        document.getElementById('filterDate')?.addEventListener('change', (e) => {
            if (e.target.value) {
                this.filters.date_filter = e.target.value;
            } else {
                delete this.filters.date_filter;
            }
            this.loadEvents();
        });

        document.getElementById('sortEvents')?.addEventListener('change', () => this.loadEvents());
    }
};

document.addEventListener('DOMContentLoaded', () => GenrePage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
