<?php
/**
 * Cities Listing Page
 * Template based on cities-listing.html
 */

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Orașe';
$pageDescription = 'Găsește evenimente în orașul tău sau descoperă ce se întâmplă în alte orașe din România.';
$bodyClass = 'bg-surface min-h-screen';
$transparentHeader = true;

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Page Hero -->
<section class="relative pt-32 pb-16 overflow-hidden text-center bg-gradient-to-br from-primary to-primary-dark">
    <div class="absolute -top-1/2 -right-1/5 w-[500px] h-[500px] bg-[radial-gradient(circle,rgba(255,255,255,0.1)_0%,transparent_70%)] rounded-full"></div>
    <div class="relative z-10 max-w-2xl px-6 mx-auto">
        <h1 class="mb-4 text-4xl font-extrabold text-white">Orașe</h1>
        <p class="text-lg leading-relaxed text-white/85">Găsește evenimente în orașul tău sau descoperă ce se întâmplă în alte orașe din România.</p>
    </div>
</section>

<!-- Search -->
<section class="px-6">
    <div class="relative z-10 max-w-xl mx-auto -mt-7">
        <div class="flex items-center p-2 bg-white shadow-xl rounded-2xl">
            <input type="text" id="citySearch" placeholder="Caută un oraș..." class="flex-1 px-5 py-4 text-base bg-transparent border-none outline-none">
            <button class="py-3.5 px-7 bg-gradient-to-br from-primary to-primary-light rounded-xl text-white text-base font-semibold flex items-center gap-2 hover:-translate-y-0.5 hover:shadow-lg transition-all">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/>
                    <path d="M21 21l-4.35-4.35"/>
                </svg>
                Caută
            </button>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-6xl px-6 py-10 mx-auto">
    <!-- Stats Bar -->
    <div class="flex justify-center gap-12 py-8 mb-12 bg-white border shadow-sm rounded-2xl border-border mobile:grid mobile:grid-cols-2 mobile:gap-6">
        <div class="text-center">
            <div class="text-4xl font-extrabold leading-none text-primary">42</div>
            <div class="mt-2 text-sm text-muted">Orașe active</div>
        </div>
        <div class="text-center">
            <div class="text-4xl font-extrabold leading-none text-primary">1,247</div>
            <div class="mt-2 text-sm text-muted">Evenimente live</div>
        </div>
        <div class="text-center">
            <div class="text-4xl font-extrabold leading-none text-primary">290</div>
            <div class="mt-2 text-sm text-muted">Locații partenere</div>
        </div>
        <div class="text-center">
            <div class="text-4xl font-extrabold leading-none text-primary">850K+</div>
            <div class="mt-2 text-sm text-muted">Bilete vândute</div>
        </div>
    </div>

    <!-- Featured Cities -->
    <section class="mb-16">
        <div class="flex items-center mb-6">
            <h2 class="text-2xl font-bold text-secondary flex items-center gap-2.5">
                <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                Orașe principale
            </h2>
        </div>

        <div id="featuredCities" class="grid grid-cols-3 gap-6 mobile:grid-cols-1">
            <!-- First card spans 2 rows -->
            <a href="/bucuresti" class="relative row-span-2 overflow-hidden rounded-2xl group">
                <img src="https://images.unsplash.com/photo-1584646098378-0874589d76b1?w=800&h=1000&fit=crop" alt="București" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/10"></div>
                <div class="absolute bottom-0 left-0 right-0 p-7">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary rounded-md text-xs font-bold text-white uppercase tracking-wider mb-3">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>
                        </svg>
                        Capitala
                    </span>
                    <h3 class="mb-2 text-4xl font-extrabold text-white">București</h3>
                    <div class="flex gap-5">
                        <span class="flex items-center gap-1.5 text-sm text-white/85">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                            </svg>
                            238 evenimente
                        </span>
                        <span class="flex items-center gap-1.5 text-sm text-white/85">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            87 locații
                        </span>
                    </div>
                </div>
            </a>

            <a href="/cluj" class="relative rounded-2xl overflow-hidden aspect-[4/5] group">
                <img src="https://images.unsplash.com/photo-1587974928442-77dc3e0dba72?w=600&h=750&fit=crop" alt="Cluj-Napoca" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/10"></div>
                <div class="absolute bottom-0 left-0 right-0 p-7">
                    <h3 class="mb-2 text-3xl font-extrabold text-white">Cluj-Napoca</h3>
                    <div class="flex gap-5">
                        <span class="flex items-center gap-1.5 text-sm text-white/85">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                            </svg>
                            94 evenimente
                        </span>
                    </div>
                </div>
            </a>

            <a href="/timisoara" class="relative rounded-2xl overflow-hidden aspect-[4/5] group">
                <img src="https://images.unsplash.com/photo-1598971861713-54ad16a7e72e?w=600&h=750&fit=crop" alt="Timișoara" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/10"></div>
                <div class="absolute bottom-0 left-0 right-0 p-7">
                    <h3 class="mb-2 text-3xl font-extrabold text-white">Timișoara</h3>
                    <div class="flex gap-5">
                        <span class="flex items-center gap-1.5 text-sm text-white/85">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                            </svg>
                            67 evenimente
                        </span>
                    </div>
                </div>
            </a>

            <a href="/iasi" class="relative rounded-2xl overflow-hidden aspect-[4/5] group">
                <img src="https://images.unsplash.com/photo-1560969184-10fe8719e047?w=600&h=750&fit=crop" alt="Iași" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/10"></div>
                <div class="absolute bottom-0 left-0 right-0 p-7">
                    <h3 class="mb-2 text-3xl font-extrabold text-white">Iași</h3>
                    <div class="flex gap-5">
                        <span class="flex items-center gap-1.5 text-sm text-white/85">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                            </svg>
                            52 evenimente
                        </span>
                    </div>
                </div>
            </a>

            <a href="/brasov" class="relative rounded-2xl overflow-hidden aspect-[4/5] group">
                <img src="https://images.unsplash.com/photo-1565264216052-3c9012481015?w=600&h=750&fit=crop" alt="Brașov" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/10"></div>
                <div class="absolute bottom-0 left-0 right-0 p-7">
                    <h3 class="mb-2 text-3xl font-extrabold text-white">Brașov</h3>
                    <div class="flex gap-5">
                        <span class="flex items-center gap-1.5 text-sm text-white/85">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                            </svg>
                            41 evenimente
                        </span>
                    </div>
                </div>
            </a>
        </div>
    </section>

    <!-- All Cities -->
    <section class="mb-16">
        <div class="flex items-center mb-6">
            <h2 class="text-2xl font-bold text-secondary flex items-center gap-2.5">
                <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
                Toate orașele
            </h2>
        </div>

        <!-- Alphabet Navigation -->
        <div id="alphabetNav" class="flex flex-wrap gap-1.5 mb-8 p-4 bg-white rounded-xl border border-border">
            <!-- Generated by JS -->
        </div>

        <div id="citiesGrid" class="grid grid-cols-4 gap-5 mobile:grid-cols-1">
            <!-- Generated by JS -->
        </div>
    </section>

    <!-- Regions -->
    <section class="mb-16">
        <div class="flex items-center mb-6">
            <h2 class="text-2xl font-bold text-secondary flex items-center gap-2.5">
                <svg class="w-7 h-7 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
                    <line x1="8" y1="2" x2="8" y2="18"/>
                    <line x1="16" y1="6" x2="16" y2="22"/>
                </svg>
                După regiune
            </h2>
        </div>

        <div id="regionsGrid" class="grid grid-cols-3 gap-5 mobile:grid-cols-1">
            <!-- Generated by JS -->
        </div>
    </section>

    <!-- Newsletter -->
    <section class="relative p-12 overflow-hidden text-center bg-gradient-to-br from-secondary to-slate-600 rounded-2xl">
        <div class="absolute -top-24 -right-24 w-72 h-72 bg-[radial-gradient(circle,rgba(165,28,48,0.3)_0%,transparent_70%)] rounded-full"></div>
        <div class="relative z-10 max-w-md mx-auto">
            <h2 class="mb-3 text-3xl font-extrabold text-white">Primește noutăți din orașul tău</h2>
            <p class="mb-6 text-base text-white/70">Abonează-te pentru a primi notificări despre evenimente noi în orașul tău preferat.</p>
            <form class="flex gap-3">
                <input type="email" placeholder="Adresa ta de email" class="flex-1 px-5 py-4 text-base text-white transition-all border outline-none bg-white/10 border-white/20 rounded-xl placeholder-white/50 focus:border-primary focus:bg-white/15">
                <button type="submit" class="px-8 py-4 text-base font-semibold text-white transition-all bg-primary hover:bg-primary-dark rounded-xl">Abonează-te</button>
            </form>
        </div>
    </section>
</main>

<?php
include __DIR__ . '/includes/footer.php';

$scriptsExtra = <<<'SCRIPTS'
<script>
const CitiesPage = {
    cities: [],
    regions: [],

    async init() {
        this.loadData();
        this.renderAlphabet();
        this.bindEvents();
    },

    bindEvents() {
        document.getElementById('citySearch')?.addEventListener('input', AmbiletUtils.debounce(() => this.filterCities(), 300));
    },

    loadData() {
        this.cities = [
            { name: 'Alba Iulia', region: 'Alba', events: 12, image: 'https://images.unsplash.com/photo-1565264216052-3c9012481015?w=200&h=200&fit=crop' },
            { name: 'Arad', region: 'Arad', events: 18, image: 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=200&h=200&fit=crop' },
            { name: 'Bacău', region: 'Bacău', events: 14, image: 'https://images.unsplash.com/photo-1480714378408-67cf0d13bc1b?w=200&h=200&fit=crop' },
            { name: 'Baia Mare', region: 'Maramureș', events: 9, image: 'https://images.unsplash.com/photo-1449824913935-59a10b8d2000?w=200&h=200&fit=crop' },
            { name: 'Constanța', region: 'Constanța', events: 38, image: 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=200&h=200&fit=crop' },
            { name: 'Craiova', region: 'Dolj', events: 24, image: 'https://images.unsplash.com/photo-1477959858617-67f85cf4f1df?w=200&h=200&fit=crop' },
            { name: 'Galați', region: 'Galați', events: 16, image: 'https://images.unsplash.com/photo-1514924013411-cbf25faa35bb?w=200&h=200&fit=crop' },
            { name: 'Oradea', region: 'Bihor', events: 21, image: 'https://images.unsplash.com/photo-1519681393784-d120267933ba?w=200&h=200&fit=crop' }
        ];

        this.regions = [
            { name: 'Transilvania', cities: ['Cluj-Napoca', 'Brașov', 'Sibiu', 'Târgu Mureș', 'Alba Iulia'], count: 12, events: 245 },
            { name: 'Muntenia', cities: ['București', 'Ploiești', 'Pitești', 'Târgoviște'], count: 8, events: 312 },
            { name: 'Moldova', cities: ['Iași', 'Bacău', 'Suceava', 'Galați'], count: 9, events: 156 },
            { name: 'Dobrogea', cities: ['Constanța', 'Mamaia', 'Tulcea', 'Mangalia'], count: 4, events: 89 },
            { name: 'Banat', cities: ['Timișoara', 'Arad', 'Reșița', 'Lugoj'], count: 5, events: 112 },
            { name: 'Oltenia', cities: ['Craiova', 'Râmnicu Vâlcea', 'Drobeta-Turnu Severin'], count: 6, events: 78 }
        ];

        this.renderCities();
        this.renderRegions();
    },

    renderAlphabet() {
        const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
        const activeLetters = ['A', 'B', 'C', 'D', 'F', 'G', 'H', 'I', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'V', 'Z'];

        document.getElementById('alphabetNav').innerHTML = alphabet.map(letter => {
            const isActive = activeLetters.includes(letter);
            const isCurrent = letter === 'B';
            return `<a href="#${letter}" class="w-9 h-9 flex items-center justify-center ${isCurrent ? 'bg-primary text-white' : isActive ? 'bg-surface text-muted hover:bg-gray-100 hover:text-secondary' : 'bg-surface text-muted/40 pointer-events-none'} rounded-lg text-sm font-semibold transition-all">${letter}</a>`;
        }).join('');
    },

    renderCities() {
        document.getElementById('citiesGrid').innerHTML = this.cities.map(city => `
            <a href="/${city.name.toLowerCase().replace(/\s+/g, '-').replace(/ă/g, 'a').replace(/â/g, 'a').replace(/î/g, 'i').replace(/ș/g, 's').replace(/ț/g, 't')}" class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-border hover:border-primary hover:shadow-lg hover:-translate-y-0.5 transition-all">
                <div class="flex-shrink-0 w-16 h-16 overflow-hidden rounded-xl">
                    <img src="${city.image}" alt="${city.name}" class="object-cover w-full h-full">
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="mb-1 text-base font-bold text-secondary">${city.name}</h3>
                    <p class="text-sm text-muted mb-1.5">${city.region}</p>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 rounded-full text-xs font-semibold text-primary">
                        <span class="w-1.5 h-1.5 bg-success rounded-full"></span>
                        ${city.events} evenimente
                    </span>
                </div>
            </a>
        `).join('');
    },

    renderRegions() {
        const icons = [
            '<path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/>',
            '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
            '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>',
            '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/>',
            '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/>',
            '<path d="M18 8h1a4 4 0 0 1 0 8h-1"/><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"/>'
        ];

        document.getElementById('regionsGrid').innerHTML = this.regions.map((region, idx) => `
            <div class="p-6 transition-all bg-white border rounded-2xl border-border hover:border-muted hover:shadow-md">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex items-center justify-center w-12 h-12 text-white bg-gradient-to-br from-primary to-primary-light rounded-xl">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">${icons[idx]}</svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-secondary">${region.name}</h3>
                        <p class="text-sm text-muted">${region.count} orașe • ${region.events} evenimente</p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    ${region.cities.map(city => `
                        <a href="/${city.toLowerCase().replace(/\s+/g, '-').replace(/ă/g, 'a').replace(/â/g, 'a').replace(/î/g, 'i').replace(/ș/g, 's').replace(/ț/g, 't')}" class="px-3.5 py-2 bg-surface rounded-lg text-gray-600 text-sm font-medium hover:bg-primary hover:text-white transition-all">${city}</a>
                    `).join('')}
                </div>
            </div>
        `).join('');
    },

    filterCities() {
        const search = document.getElementById('citySearch')?.value.toLowerCase() || '';
        const filtered = this.cities.filter(c => c.name.toLowerCase().includes(search) || c.region.toLowerCase().includes(search));

        document.getElementById('citiesGrid').innerHTML = filtered.length ? filtered.map(city => `
            <a href="/${city.name.toLowerCase().replace(/\s+/g, '-')}" class="flex items-center gap-4 p-4 bg-white rounded-2xl border border-border hover:border-primary hover:shadow-lg hover:-translate-y-0.5 transition-all">
                <div class="flex-shrink-0 w-16 h-16 overflow-hidden rounded-xl">
                    <img src="${city.image}" alt="${city.name}" class="object-cover w-full h-full">
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="mb-1 text-base font-bold text-secondary">${city.name}</h3>
                    <p class="text-sm text-muted mb-1.5">${city.region}</p>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-50 rounded-full text-xs font-semibold text-primary">
                        <span class="w-1.5 h-1.5 bg-success rounded-full"></span>
                        ${city.events} evenimente
                    </span>
                </div>
            </a>
        `).join('') : '<div class="col-span-4 py-12 text-center text-muted">Nu am găsit niciun oraș.</div>';
    }
};

document.addEventListener('DOMContentLoaded', () => CitiesPage.init());
</script>
SCRIPTS;

include __DIR__ . '/includes/scripts.php';
?>
