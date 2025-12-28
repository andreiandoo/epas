<?php
/**
 * Category / Events Listing Page
 */
require_once __DIR__ . '/includes/config.php';

$categorySlug = $_GET['type'] ?? '';
$pageTitle = $categorySlug ? ucfirst($categorySlug) : 'Toate evenimentele';
$pageDescription = 'Descopera evenimentele din categoria ' . $pageTitle;

require_once __DIR__ . '/includes/head.php';
?>

    <div id="header"></div>

    <!-- Page Header -->
    <section class="bg-gradient-to-r from-primary to-primary-dark py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <nav class="flex items-center gap-2 text-white/60 text-sm mb-4">
                <a href="/" class="hover:text-white">Acasa</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-white" id="breadcrumb-category"><?= htmlspecialchars($pageTitle) ?></span>
            </nav>
            <h1 class="text-3xl lg:text-4xl font-bold text-white" id="page-title"><?= htmlspecialchars($pageTitle) ?></h1>
            <p class="text-white/80 mt-2" id="page-subtitle">Toate evenimentele disponibile</p>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Filters Sidebar -->
                <aside class="lg:w-64 shrink-0">
                    <div class="sticky top-24 bg-white rounded-2xl border border-border p-6 space-y-6">
                        <div>
                            <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider mb-3">Oras</h3>
                            <select id="filter-city" class="input w-full">
                                <option value="">Toate orasele</option>
                            </select>
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider mb-3">Data</h3>
                            <input type="date" id="filter-date" class="input w-full">
                        </div>

                        <div>
                            <h3 class="text-sm font-semibold text-secondary uppercase tracking-wider mb-3">Pret</h3>
                            <div class="flex gap-2">
                                <input type="number" id="filter-min-price" class="input" placeholder="Min">
                                <input type="number" id="filter-max-price" class="input" placeholder="Max">
                            </div>
                        </div>

                        <button id="apply-filters" class="btn btn-primary w-full">Aplica filtre</button>
                        <button id="clear-filters" class="btn btn-secondary w-full">Reseteaza</button>
                    </div>
                </aside>

                <!-- Events Grid -->
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-6">
                        <p id="results-count" class="text-muted">Se incarca...</p>
                        <select id="sort-events" class="input w-auto">
                            <option value="date_asc">Data (cel mai curand)</option>
                            <option value="date_desc">Data (cel mai tarziu)</option>
                            <option value="price_asc">Pret (crescator)</option>
                            <option value="price_desc">Pret (descrescator)</option>
                        </select>
                    </div>

                    <div id="events-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6"></div>

                    <div id="pagination" class="flex justify-center mt-8"></div>
                </div>
            </div>
        </div>
    </section>

    <?php require_once __DIR__ . '/includes/cta-organizer.php'; ?>

    <div id="footer"></div>

<?php
$scriptsExtra = <<<JS
<script>
const CategoryPage = {
    category: '$categorySlug',
    currentPage: 1,
    filters: {},

    async init() {
        if (this.category) {
            this.filters.category = this.category;
            await this.loadCategoryInfo();
        }
        await Promise.all([
            this.loadCities(),
            this.loadEvents()
        ]);
        this.bindEvents();
    },

    async loadCategoryInfo() {
        try {
            const response = await AmbiletAPI.getCategories();
            if (response.success) {
                const cat = response.data.find(c => c.slug === this.category);
                if (cat) {
                    document.getElementById('page-title').textContent = cat.name;
                    document.getElementById('breadcrumb-category').textContent = cat.name;
                    document.getElementById('page-subtitle').textContent = cat.description || 'Toate evenimentele disponibile';
                }
            }
        } catch (e) { console.error(e); }
    },

    async loadCities() {
        try {
            const response = await AmbiletAPI.getCities();
            if (response.success) {
                const select = document.getElementById('filter-city');
                select.innerHTML = '<option value="">Toate orasele</option>' +
                    response.data.map(c => '<option value="' + c.city + '">' + c.city + ' (' + c.count + ')</option>').join('');
            }
        } catch (e) { console.error(e); }
    },

    async loadEvents() {
        const container = document.getElementById('events-grid');
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
                document.getElementById('results-count').textContent = (meta?.total || events.length) + ' evenimente gasite';

                if (events.length > 0) {
                    container.innerHTML = events.map(e => AmbiletEventCard.render(e)).join('');
                } else {
                    container.innerHTML = '<div class="col-span-full text-center py-12"><p class="text-muted">Nu am gasit evenimente</p></div>';
                }
                this.renderPagination(meta);
            }
        } catch (e) {
            container.innerHTML = '<p class="col-span-full text-center text-error py-8">Eroare la incarcare</p>';
        }
    },

    renderPagination(meta) {
        const container = document.getElementById('pagination');
        if (!meta || meta.last_page <= 1) { container.innerHTML = ''; return; }
        let html = '<div class="flex items-center gap-2">';
        if (meta.current_page > 1) html += '<button class="px-4 py-2 rounded-lg border hover:bg-surface" onclick="CategoryPage.goToPage(' + (meta.current_page - 1) + ')">Anterior</button>';
        for (let i = 1; i <= meta.last_page; i++) {
            if (i === meta.current_page) html += '<button class="px-4 py-2 rounded-lg bg-primary text-white">' + i + '</button>';
            else if (i === 1 || i === meta.last_page || Math.abs(i - meta.current_page) <= 2) html += '<button class="px-4 py-2 rounded-lg border hover:bg-surface" onclick="CategoryPage.goToPage(' + i + ')">' + i + '</button>';
            else if (Math.abs(i - meta.current_page) === 3) html += '<span class="px-2">...</span>';
        }
        if (meta.current_page < meta.last_page) html += '<button class="px-4 py-2 rounded-lg border hover:bg-surface" onclick="CategoryPage.goToPage(' + (meta.current_page + 1) + ')">Urmator</button>';
        html += '</div>';
        container.innerHTML = html;
    },

    goToPage(page) { this.currentPage = page; this.loadEvents(); window.scrollTo({ top: 0, behavior: 'smooth' }); },

    bindEvents() {
        document.getElementById('apply-filters')?.addEventListener('click', () => this.applyFilters());
        document.getElementById('clear-filters')?.addEventListener('click', () => this.clearFilters());
        document.getElementById('sort-events')?.addEventListener('change', () => this.loadEvents());
    },

    applyFilters() {
        this.filters = {
            category: this.category || document.getElementById('filter-category')?.value,
            city: document.getElementById('filter-city')?.value,
            from_date: document.getElementById('filter-date')?.value,
            min_price: document.getElementById('filter-min-price')?.value,
            max_price: document.getElementById('filter-max-price')?.value
        };
        Object.keys(this.filters).forEach(k => { if (!this.filters[k]) delete this.filters[k]; });
        this.currentPage = 1;
        this.loadEvents();
    },

    clearFilters() {
        ['filter-city', 'filter-date', 'filter-min-price', 'filter-max-price'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        this.filters = this.category ? { category: this.category } : {};
        this.currentPage = 1;
        this.loadEvents();
    }
};

document.addEventListener('DOMContentLoaded', () => CategoryPage.init());
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';
?>
