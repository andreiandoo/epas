<?php
/**
 * Venues Listing Page
 * Template based on venues-listing.html
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Locații';
$pageDescription = 'Descoperă cele mai populare locații pentru evenimente din România. De la arene și stadioane la teatre și cluburi.';
$bodyClass = 'bg-surface min-h-screen';
$transparentHeader = true;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="pt-40 pb-8 text-center bg-gradient-to-br from-secondary to-slate-600">
    <div class="max-w-2xl px-6 mx-auto">
        <h1 class="mb-4 text-4xl font-extrabold text-white">Locații</h1>
        <p class="text-lg leading-relaxed text-white/70">Descoperă cele mai populare locații pentru evenimente din România. De la arene și stadioane la teatre și cluburi.</p>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-6xl px-6 py-10 mx-auto">
    <!-- Featured Venues -->
    <section class="mb-12">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-secondary flex items-center gap-2.5">
                <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                Locații populare
            </h2>
        </div>

        <div id="featuredVenues" class="grid grid-cols-2 gap-6">
            <!-- Skeleton -->
            <div class="relative overflow-hidden bg-gray-200 rounded-2xl aspect-video animate-pulse"></div>
            <div class="relative overflow-hidden bg-gray-200 rounded-2xl aspect-video animate-pulse"></div>
        </div>
    </section>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-4 mb-8">
        <div class="flex-1 min-w-[280px] relative">
            <svg class="absolute w-5 h-5 -translate-y-1/2 left-4 top-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21l-4.35-4.35"/>
            </svg>
            <input type="text" id="searchInput" placeholder="Caută locații..." class="w-full py-3.5 pl-12 pr-5 bg-white border border-border rounded-xl text-base focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all">
        </div>
        <select id="cityFilter" class="py-3.5 pl-4 pr-10 bg-white border border-border rounded-xl text-sm font-medium text-secondary cursor-pointer hover:border-muted focus:outline-none focus:border-primary transition-all appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22/%3E%3C/svg%3E')] bg-no-repeat bg-[right_14px_center]">
            <option value="">Toate orașele</option>
            <option value="bucuresti">București</option>
            <option value="cluj">Cluj-Napoca</option>
            <option value="timisoara">Timișoara</option>
            <option value="iasi">Iași</option>
            <option value="brasov">Brașov</option>
            <option value="constanta">Constanța</option>
        </select>
        <select id="capacityFilter" class="py-3.5 pl-4 pr-10 bg-white border border-border rounded-xl text-sm font-medium text-secondary cursor-pointer hover:border-muted focus:outline-none focus:border-primary transition-all appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22/%3E%3C/svg%3E')] bg-no-repeat bg-[right_14px_center]">
            <option value="">Capacitate</option>
            <option value="small">Sub 500 locuri</option>
            <option value="medium">500 - 2.000 locuri</option>
            <option value="large">2.000 - 10.000 locuri</option>
            <option value="xlarge">Peste 10.000 locuri</option>
        </select>
        <select id="sortFilter" class="py-3.5 pl-4 pr-10 bg-white border border-border rounded-xl text-sm font-medium text-secondary cursor-pointer hover:border-muted focus:outline-none focus:border-primary transition-all appearance-none bg-[url('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20width%3D%2216%22%20height%3D%2216%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22none%22%20stroke%3D%22%2394A3B8%22%20stroke-width%3D%222%22%3E%3Cpath%20d%3D%22M6%209l6%206%206-6%22/%3E%3C/svg%3E')] bg-no-repeat bg-[right_14px_center]">
            <option value="">Sortare</option>
            <option value="popular">Cele mai populare</option>
            <option value="events">După evenimente</option>
            <option value="capacity">După capacitate</option>
            <option value="name">Alfabetic</option>
        </select>
    </div>

    <!-- Category Tabs -->
    <div id="categoryTabs" class="flex flex-wrap gap-2 mb-8">
        <button class="flex items-center gap-2 px-5 py-3 text-sm font-medium text-white transition-all border rounded-full category-tab active bg-primary border-primary" data-category="all">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
            </svg>
            Toate
            <span class="px-2 py-0.5 bg-white/20 rounded-full text-xs font-semibold" id="totalCount">0</span>
        </button>
        <button class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition-all bg-white border rounded-full category-tab border-border text-muted hover:border-muted hover:text-secondary" data-category="arena">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 21h18"/>
                <path d="M5 21V7l8-4v18"/>
                <path d="M19 21V11l-6-4"/>
            </svg>
            Arene & Stadioane
            <span class="px-2 py-0.5 bg-black/10 rounded-full text-xs font-semibold">24</span>
        </button>
        <button class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition-all bg-white border rounded-full category-tab border-border text-muted hover:border-muted hover:text-secondary" data-category="theater">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                <line x1="9" y1="9" x2="9.01" y2="9"/>
                <line x1="15" y1="9" x2="15.01" y2="9"/>
            </svg>
            Teatre & Săli
            <span class="px-2 py-0.5 bg-black/10 rounded-full text-xs font-semibold">86</span>
        </button>
        <button class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition-all bg-white border rounded-full category-tab border-border text-muted hover:border-muted hover:text-secondary" data-category="club">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 18V5l12-2v13"/>
                <circle cx="6" cy="18" r="3"/>
                <circle cx="18" cy="16" r="3"/>
            </svg>
            Cluburi & Baruri
            <span class="px-2 py-0.5 bg-black/10 rounded-full text-xs font-semibold">142</span>
        </button>
        <button class="flex items-center gap-2 px-5 py-3 text-sm font-medium transition-all bg-white border rounded-full category-tab border-border text-muted hover:border-muted hover:text-secondary" data-category="openair">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="5"/>
                <line x1="12" y1="1" x2="12" y2="3"/>
                <line x1="12" y1="21" x2="12" y2="23"/>
            </svg>
            Open Air
            <span class="px-2 py-0.5 bg-black/10 rounded-full text-xs font-semibold">38</span>
        </button>
    </div>

    <!-- Results Info -->
    <div class="flex items-center justify-between mb-6">
        <span class="text-sm text-muted">Se afișează <strong class="text-secondary" id="resultsCount">0</strong> locații</span>
        <div class="flex gap-1 p-1 bg-gray-100 rounded-lg">
            <button class="flex items-center justify-center bg-white rounded-lg shadow-sm view-btn active w-9 h-9 text-primary" data-view="grid" title="Grilă">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"/>
                    <rect x="14" y="3" width="7" height="7"/>
                    <rect x="14" y="14" width="7" height="7"/>
                    <rect x="3" y="14" width="7" height="7"/>
                </svg>
            </button>
            <button class="flex items-center justify-center transition-all rounded-lg view-btn w-9 h-9 text-muted hover:text-secondary" data-view="list" title="Listă">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="8" y1="6" x2="21" y2="6"/>
                    <line x1="8" y1="12" x2="21" y2="12"/>
                    <line x1="8" y1="18" x2="21" y2="18"/>
                    <line x1="3" y1="6" x2="3.01" y2="6"/>
                    <line x1="3" y1="12" x2="3.01" y2="12"/>
                    <line x1="3" y1="18" x2="3.01" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Venues Grid -->
    <div id="venuesGrid" class="grid grid-cols-3 gap-6 mb-12">
        <!-- Skeleton cards -->
        <div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">
            <div class="aspect-[16/10] bg-gray-200"></div>
            <div class="p-5">
                <div class="w-3/4 h-5 mb-2 bg-gray-200 rounded"></div>
                <div class="w-1/2 h-4 mb-3 bg-gray-200 rounded"></div>
                <div class="w-full h-3 bg-gray-200 rounded"></div>
            </div>
        </div>
        <div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">
            <div class="aspect-[16/10] bg-gray-200"></div>
            <div class="p-5">
                <div class="w-3/4 h-5 mb-2 bg-gray-200 rounded"></div>
                <div class="w-1/2 h-4 mb-3 bg-gray-200 rounded"></div>
                <div class="w-full h-3 bg-gray-200 rounded"></div>
            </div>
        </div>
        <div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">
            <div class="aspect-[16/10] bg-gray-200"></div>
            <div class="p-5">
                <div class="w-3/4 h-5 mb-2 bg-gray-200 rounded"></div>
                <div class="w-1/2 h-4 mb-3 bg-gray-200 rounded"></div>
                <div class="w-full h-3 bg-gray-200 rounded"></div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div id="pagination" class="flex items-center justify-center gap-2">
        <!-- Rendered by JS -->
    </div>
</main>

<?php include __DIR__ . '/includes/featured-carousel.php'; ?>

<?php
include __DIR__ . '/includes/footer.php';

$scriptsExtra = <<<'SCRIPTS'
<script>
const VenuesPage = {
    venues: [],
    currentPage: 1,
    itemsPerPage: 9,

    async init() {
        await this.loadVenues();
        this.bindEvents();
    },

    bindEvents() {
        // Search
        document.getElementById('searchInput')?.addEventListener('input', AmbiletUtils.debounce(() => this.filterVenues(), 300));

        // Filters
        document.getElementById('cityFilter')?.addEventListener('change', () => this.filterVenues());
        document.getElementById('capacityFilter')?.addEventListener('change', () => this.filterVenues());
        document.getElementById('sortFilter')?.addEventListener('change', () => this.filterVenues());

        // Category tabs
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.category-tab').forEach(t => {
                    t.classList.remove('active', 'bg-primary', 'border-primary', 'text-white');
                    t.classList.add('bg-white', 'border-border', 'text-muted');
                });
                tab.classList.add('active', 'bg-primary', 'border-primary', 'text-white');
                tab.classList.remove('bg-white', 'border-border', 'text-muted');
                this.filterVenues();
            });
        });
    },

    async loadVenues() {
        try {
            const response = await AmbiletAPI.getVenues({ per_page: 50 });
            const raw = response.data || response || [];
            this.venues = (Array.isArray(raw) ? raw : []).map(v => ({
                id: v.id,
                name: v.name || '',
                slug: v.slug || '',
                image: v.image || '/assets/images/placeholder-venue.jpg',
                location: v.city || '',
                capacity: v.capacity || 0,
                eventsCount: v.events_count || 0,
                featured: v.is_featured || false,
                type: (v.categories && v.categories[0]?.name) || 'Locație',
                category: (v.categories && v.categories[0]?.slug) || 'other',
                eventTypes: v.categories ? v.categories.map(c => c.name).join(', ') : ''
            }));
            this.renderFeatured();
            this.renderVenues();
        } catch (err) {
            console.error('Failed to load venues:', err);
            this.venues = [];
            this.renderVenues();
        }
    },

    renderFeatured() {
        const featured = this.venues.filter(v => v.featured);
        const container = document.getElementById('featuredVenues');
        const section = container?.closest('section');
        if (!featured.length) {
            if (section) section.style.display = 'none';
            return;
        }
        if (section) section.style.display = '';

        container.innerHTML = featured.map(v => `
            <a href="/locatie/${v.slug}" class="relative overflow-hidden rounded-2xl aspect-video group">
                <img src="${v.image}" alt="${v.name}" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105" loading="lazy">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                <div class="absolute bottom-0 left-0 right-0 p-7">
                    <span class="inline-block px-3 py-1.5 bg-primary rounded-md text-xs font-bold text-white uppercase tracking-wider mb-3">${v.type}</span>
                    <h3 class="mb-2 text-2xl font-extrabold text-white">${v.name}</h3>
                    <div class="flex items-center gap-1.5 text-base text-white/80 mb-3">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        ${v.location}
                    </div>
                    <div class="flex gap-5">
                        <span class="flex items-center gap-1.5 text-sm text-white/80">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                            ${v.capacity} locuri
                        </span>
                        <span class="flex items-center gap-1.5 text-sm text-white/80">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                            </svg>
                            ${v.eventsCount} evenimente
                        </span>
                    </div>
                </div>
            </a>
        `).join('');
    },

    filterVenues() {
        const search = document.getElementById('searchInput')?.value.toLowerCase() || '';
        const city = document.getElementById('cityFilter')?.value || '';
        const activeTab = document.querySelector('.category-tab.active')?.dataset.category || 'all';

        let filtered = this.venues.filter(v => {
            const matchSearch = !search || v.name.toLowerCase().includes(search) || v.location.toLowerCase().includes(search);
            const matchCity = !city || v.location.toLowerCase().includes(city);
            const matchCategory = activeTab === 'all' || v.category === activeTab;
            return matchSearch && matchCity && matchCategory;
        });

        this.currentPage = 1;
        this.renderVenues(filtered);
    },

    renderVenues(venues = this.venues) {
        const grid = document.getElementById('venuesGrid');
        const start = (this.currentPage - 1) * this.itemsPerPage;
        const paginated = venues.slice(start, start + this.itemsPerPage);

        document.getElementById('totalCount').textContent = venues.length;
        document.getElementById('resultsCount').textContent = venues.length;

        grid.innerHTML = paginated.map(v => `
            <a href="/locatie/${v.slug}" class="overflow-hidden transition-all duration-300 bg-white border rounded-2xl border-border hover:-translate-y-1 hover:shadow-lg hover:border-primary">
                <div class="relative aspect-[16/10] overflow-hidden">
                    <img src="${v.image}" alt="${v.name}" class="object-cover w-full h-full transition-transform duration-500 hover:scale-105" loading="lazy">
                    <span class="absolute top-3 left-3 px-3 py-1.5 bg-white/95 rounded-md text-xs font-semibold text-gray-600 uppercase tracking-wide">${v.type}</span>
                    <span class="absolute top-3 right-3 px-3 py-1.5 bg-primary rounded-md text-xs font-semibold text-white">${v.eventsCount} evenimente</span>
                </div>
                <div class="p-5">
                    <h3 class="mb-2 text-lg font-bold leading-tight text-secondary">${v.name}</h3>
                    <div class="flex items-center gap-1.5 text-sm text-muted mb-3">
                        <svg class="w-4 h-4 text-muted/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                            <circle cx="12" cy="10" r="3"/>
                        </svg>
                        ${v.location}
                    </div>
                    <div class="flex gap-4 pt-3 border-t border-gray-100">
                        <span class="flex items-center gap-1.5 text-sm text-muted">
                            <svg class="w-4 h-4 text-muted/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                            ${v.capacity} locuri
                        </span>
                        <span class="flex items-center gap-1.5 text-sm text-muted">
                            <svg class="w-4 h-4 text-muted/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 18V5l12-2v13"/>
                                <circle cx="6" cy="18" r="3"/>
                            </svg>
                            ${v.eventTypes}
                        </span>
                    </div>
                </div>
            </a>
        `).join('');

        this.renderPagination(venues.length);
    },

    renderPagination(total) {
        const pages = Math.ceil(total / this.itemsPerPage);
        const container = document.getElementById('pagination');

        if (pages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = `<button class="pagination-btn ${this.currentPage === 1 ? 'disabled opacity-50 cursor-not-allowed' : ''}" ${this.currentPage === 1 ? 'disabled' : ''} onclick="VenuesPage.goToPage(${this.currentPage - 1})">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </button>`;

        for (let i = 1; i <= Math.min(pages, 5); i++) {
            html += `<button class="pagination-btn ${this.currentPage === i ? 'active bg-primary border-primary text-white' : 'bg-white border-border text-muted hover:border-muted hover:text-secondary'} w-10 h-10 flex items-center justify-center border rounded-xl text-sm font-semibold transition-all" onclick="VenuesPage.goToPage(${i})">${i}</button>`;
        }

        if (pages > 5) {
            html += `<span class="flex items-center justify-center w-10 h-10 text-muted">...</span>`;
            html += `<button class="flex items-center justify-center w-10 h-10 text-sm font-semibold transition-all bg-white border pagination-btn border-border rounded-xl text-muted hover:border-muted hover:text-secondary" onclick="VenuesPage.goToPage(${pages})">${pages}</button>`;
        }

        html += `<button class="pagination-btn ${this.currentPage === pages ? 'disabled opacity-50 cursor-not-allowed' : ''}" ${this.currentPage === pages ? 'disabled' : ''} onclick="VenuesPage.goToPage(${this.currentPage + 1})">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
        </button>`;

        container.innerHTML = html;
    },

    goToPage(page) {
        this.currentPage = page;
        this.renderVenues();
        window.scrollTo({ top: 400, behavior: 'smooth' });
    }
};

document.addEventListener('DOMContentLoaded', () => { VenuesPage.init(); FeaturedCarousel.init(); });
</script>
SCRIPTS;

include __DIR__ . '/includes/scripts.php';
?>
