<?php
/**
 * Venue Single Page
 * Template based on venue-single.html
 */

require_once __DIR__ . '/includes/config.php';

// Get venue slug from URL
$venueSlug = $_GET['slug'] ?? '';

$pageTitle = 'Sala Palatului'; // Would be loaded from API
$pageDescription = 'Descoperă evenimente și informații despre Sala Palatului - una dintre cele mai prestigioase săli de spectacole din România.';
$bodyClass = 'bg-surface';

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="relative h-[450px] overflow-hidden" id="venueHero">
    <!-- Skeleton -->
    <div class="absolute inset-0 bg-gray-200 skeleton-hero animate-pulse"></div>

    <!-- Content loaded by JS -->
    <img id="heroImage" src="" alt="" class="absolute inset-0 hidden object-cover object-center w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-b from-black/30 to-black/70"></div>

    <div class="relative z-10 flex flex-col justify-end h-full max-w-6xl px-6 pb-10 mx-auto">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 mb-4 text-sm text-white/70">
            <a href="/" class="transition-colors hover:text-white">Acasă</a>
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
            <a href="/locatii" class="transition-colors hover:text-white">Locații</a>
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="9 18 15 12 9 6"/>
            </svg>
            <span id="breadcrumbName" class="text-white/90"></span>
        </nav>

        <!-- Venue Type Badge -->
        <div id="venueTypeBadge" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white/15 backdrop-blur-md rounded-full mb-4 w-fit text-sm font-semibold text-white">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 21h18"/>
                <path d="M5 21V7l8-4v18"/>
                <path d="M19 21V11l-6-4"/>
            </svg>
            <span id="venueType">Sală de concerte</span>
        </div>

        <h1 id="venueName" class="mb-3 text-5xl font-extrabold leading-tight text-white"></h1>

        <div class="flex items-center gap-2 text-lg text-white/90">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
            </svg>
            <span id="venueLocation"></span>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-6xl px-6 mx-auto">
    <!-- Info Cards -->
    <div class="relative z-20 grid grid-cols-4 gap-5 mb-10 -mt-16" id="infoCards">
        <div class="p-6 text-center bg-white shadow-lg rounded-2xl">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-red-50 rounded-xl text-primary">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </div>
            <div id="venueCapacity" class="mb-1 text-2xl font-extrabold text-secondary">-</div>
            <div class="text-sm text-muted">Capacitate locuri</div>
        </div>
        <div class="p-6 text-center bg-white shadow-lg rounded-2xl">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-red-50 rounded-xl text-primary">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <div id="venueEventsCount" class="mb-1 text-2xl font-extrabold text-secondary">-</div>
            <div class="text-sm text-muted">Evenimente viitoare</div>
        </div>
        <div class="p-6 text-center bg-white shadow-lg rounded-2xl">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-red-50 rounded-xl text-primary">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
            </div>
            <div id="venueRating" class="mb-1 text-2xl font-extrabold text-secondary">-</div>
            <div class="text-sm text-muted">Rating</div>
        </div>
        <div class="p-6 text-center bg-white shadow-lg rounded-2xl">
            <div class="flex items-center justify-center w-12 h-12 mx-auto mb-3 bg-red-50 rounded-xl text-primary">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                    <line x1="3" y1="9" x2="21" y2="9"/>
                    <line x1="9" y1="21" x2="9" y2="9"/>
                </svg>
            </div>
            <div id="venueYear" class="mb-1 text-2xl font-extrabold text-secondary">-</div>
            <div class="text-sm text-muted">Anul construcției</div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_380px] gap-8">
        <!-- Main Content -->
        <div>
            <!-- Upcoming Events -->
            <section class="mb-10">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-secondary flex items-center gap-2.5">
                        <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Evenimente viitoare
                    </h2>
                    <a href="#" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                        Vezi toate
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                <div id="eventsList" class="flex flex-col gap-4">
                    <!-- Skeleton Events -->
                    <div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">
                        <div class="flex">
                            <div class="w-24 h-32 bg-gray-200"></div>
                            <div class="flex-1 p-5">
                                <div class="w-16 h-3 mb-2 bg-gray-200 rounded"></div>
                                <div class="w-3/4 h-5 mb-3 bg-gray-200 rounded"></div>
                                <div class="w-24 h-4 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                    <div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">
                        <div class="flex">
                            <div class="w-24 h-32 bg-gray-200"></div>
                            <div class="flex-1 p-5">
                                <div class="w-16 h-3 mb-2 bg-gray-200 rounded"></div>
                                <div class="w-3/4 h-5 mb-3 bg-gray-200 rounded"></div>
                                <div class="w-24 h-4 bg-gray-200 rounded"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- About Section -->
            <section class="mb-10">
                <div class="flex items-center mb-5">
                    <h2 class="text-xl font-bold text-secondary flex items-center gap-2.5">
                        <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        Despre locație
                    </h2>
                </div>

                <div id="venueAbout" class="bg-white border rounded-2xl p-7 border-border">
                    <div class="animate-pulse">
                        <div class="w-full h-4 mb-3 bg-gray-200 rounded"></div>
                        <div class="w-full h-4 mb-3 bg-gray-200 rounded"></div>
                        <div class="w-3/4 h-4 bg-gray-200 rounded"></div>
                    </div>
                </div>
            </section>

            <!-- Gallery -->
            <section class="mb-10">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-secondary flex items-center gap-2.5">
                        <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                        Galerie foto
                    </h2>
                    <a href="#" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                        Vezi toate
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                <div id="venueGallery" class="grid grid-cols-3 gap-3">
                    <!-- Skeleton gallery -->
                    <div class="col-span-2 row-span-2 bg-gray-200 aspect-auto rounded-xl animate-pulse"></div>
                    <div class="aspect-[4/3] rounded-xl bg-gray-200 animate-pulse"></div>
                    <div class="aspect-[4/3] rounded-xl bg-gray-200 animate-pulse"></div>
                </div>
            </section>
        </div>

        <!-- Sidebar -->
        <aside class="flex flex-col gap-6">
            <!-- Quick Info -->
            <div class="p-6 bg-white border rounded-2xl border-border">
                <h3 class="flex items-center gap-2 mb-5 text-base font-bold text-secondary">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Informații rapide
                </h3>

                <div id="quickInfo" class="space-y-0">
                    <!-- Skeleton -->
                    <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100 animate-pulse">
                        <div class="w-10 h-10 bg-gray-200 rounded-xl"></div>
                        <div class="flex-1">
                            <div class="h-3 bg-gray-200 rounded w-12 mb-1.5"></div>
                            <div class="w-32 h-4 bg-gray-200 rounded"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map -->
            <div class="overflow-hidden bg-white border rounded-2xl border-border">
                <div id="venueMap" class="flex flex-col items-center justify-center h-48 gap-3 bg-surface text-muted">
                    <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <p class="text-sm">Hartă interactivă</p>
                </div>
                <div class="p-5">
                    <p id="venueAddress" class="mb-4 text-sm leading-relaxed text-gray-600"></p>
                    <a href="#" id="mapsLink" target="_blank" class="flex items-center justify-center w-full gap-2 py-3 text-sm font-semibold text-gray-600 transition-all border bg-surface border-border rounded-xl hover:bg-primary hover:border-primary hover:text-white">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                        </svg>
                        Deschide în Google Maps
                    </a>
                </div>
            </div>

            <!-- Amenities -->
            <div class="p-6 bg-white border rounded-2xl border-border">
                <h3 class="flex items-center gap-2 mb-5 text-base font-bold text-secondary">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/>
                        <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Facilități
                </h3>
                <div id="venueAmenities" class="grid grid-cols-2 gap-3">
                    <!-- Skeleton -->
                    <div class="bg-gray-100 h-11 rounded-xl animate-pulse"></div>
                    <div class="bg-gray-100 h-11 rounded-xl animate-pulse"></div>
                </div>
            </div>

            <!-- Contact CTA -->
            <div class="p-6 bg-gradient-to-br from-secondary to-slate-600 rounded-2xl">
                <h3 class="mb-4 text-base font-bold text-white">Organizezi un eveniment?</h3>
                <button class="flex items-center justify-center gap-2 w-full py-3.5 bg-primary hover:bg-primary-dark rounded-xl text-white text-sm font-semibold transition-all mb-3">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Contactează locația
                </button>
                <button class="flex items-center justify-center gap-2 w-full py-3.5 bg-white/10 border border-white/20 hover:bg-white/20 rounded-xl text-white text-sm font-semibold transition-all">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                        <polyline points="16 6 12 2 8 6"/>
                        <line x1="12" y1="2" x2="12" y2="15"/>
                    </svg>
                    Descarcă kit-ul media
                </button>
            </div>
        </aside>
    </div>

    <!-- Similar Venues -->
    <section class="mt-10 mb-16">
        <div class="flex items-center mb-5">
            <h2 class="text-xl font-bold text-secondary flex items-center gap-2.5">
                <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                    <circle cx="12" cy="10" r="3"/>
                </svg>
                Locații similare
            </h2>
        </div>

        <div id="similarVenues" class="grid grid-cols-4 gap-5">
            <!-- Skeleton cards -->
            <div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">
                <div class="aspect-[16/10] bg-gray-200"></div>
                <div class="p-4">
                    <div class="w-3/4 h-4 mb-2 bg-gray-200 rounded"></div>
                    <div class="w-1/2 h-3 bg-gray-200 rounded"></div>
                </div>
            </div>
            <div class="overflow-hidden bg-white border rounded-2xl border-border animate-pulse">
                <div class="aspect-[16/10] bg-gray-200"></div>
                <div class="p-4">
                    <div class="w-3/4 h-4 mb-2 bg-gray-200 rounded"></div>
                    <div class="w-1/2 h-3 bg-gray-200 rounded"></div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
include __DIR__ . '/includes/footer.php';

$scriptsExtra = <<<'SCRIPTS'
<script>
const VenuePage = {
    venueSlug: new URLSearchParams(window.location.search).get('slug') || 'sala-palatului',

    async init() {
        await this.loadVenueData();
    },

    async loadVenueData() {
        try {
            // In demo mode, use mock data
            if (window.AMBILET_CONFIG?.DEMO_MODE) {
                this.renderVenue(this.getMockData());
                return;
            }

            const data = await AmbiletAPI.getVenue(this.venueSlug);
            this.renderVenue(data);
        } catch (error) {
            console.error('Failed to load venue:', error);
            this.renderVenue(this.getMockData());
        }
    },

    getMockData() {
        return {
            name: 'Sala Palatului',
            slug: 'sala-palatului',
            type: 'Sală de concerte',
            location: 'București, Sector 1',
            address: 'Str. Ion Câmpineanu 28, Sector 1, București 010039',
            capacity: '4.000',
            rating: '4.8',
            reviewsCount: 324,
            yearBuilt: 1960,
            eventsCount: 28,
            image: 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=1920&h=800&fit=crop',
            description: `Sala Palatului este una dintre cele mai importante și prestigioase săli de spectacole din România, situată în inima Bucureștiului. Inaugurată în 1960, aceasta găzduiește anual sute de evenimente culturale, de la concerte și spectacole de operă până la conferințe și gale.

Cu o capacitate de aproximativ 4.000 de locuri, sala oferă o acustică excepțională și o vizibilitate perfectă din orice unghi.`,
            phone: '+40 21 315 6170',
            email: 'contact@salapalatului.ro',
            website: 'salapalatului.ro',
            schedule: 'Luni - Duminică, în funcție de evenimente',
            amenities: ['Parcare', 'Acces dizabilități', 'Garderobă', 'Bar & Cafenea', 'WiFi gratuit', 'Aer condiționat'],
            gallery: [
                'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=800&h=600&fit=crop',
                'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400&h=300&fit=crop',
                'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=400&h=300&fit=crop'
            ],
            events: [
                { day: '15', month: 'MAR', category: 'Concert', title: "Carla's Dreams - Turneul Național 2025", time: '20:00', price: 149 },
                { day: '22', month: 'MAR', category: 'Concert', title: 'Ștefan Bănică Jr. - Spectacol de Paște', time: '19:30', price: 199 },
                { day: '05', month: 'APR', category: 'Operă', title: 'Opera Națională - La Traviata', time: '19:00', price: 120 }
            ],
            similarVenues: [
                { name: 'Ateneul Român', location: 'București · 800 locuri', events: 15, image: 'https://images.unsplash.com/photo-1503095396549-807759245b35?w=400&h=250&fit=crop' },
                { name: 'Opera Națională', location: 'București · 1.000 locuri', events: 32, image: 'https://images.unsplash.com/photo-1522158637959-30385a09e0da?w=400&h=250&fit=crop' },
                { name: 'Teatrul Național', location: 'București · 1.200 locuri', events: 42, image: 'https://images.unsplash.com/photo-1507676184212-d03ab07a01bf?w=400&h=250&fit=crop' },
                { name: 'Arenele Romane', location: 'București · 5.000 locuri', events: 15, image: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400&h=250&fit=crop' }
            ]
        };
    },

    renderVenue(venue) {
        // Hero
        const heroImg = document.getElementById('heroImage');
        heroImg.src = venue.image;
        heroImg.alt = venue.name;
        heroImg.classList.remove('hidden');
        document.querySelector('.skeleton-hero')?.remove();

        document.getElementById('breadcrumbName').textContent = venue.name;
        document.getElementById('venueType').textContent = venue.type;
        document.getElementById('venueName').textContent = venue.name;
        document.getElementById('venueLocation').textContent = venue.location;

        // Info cards
        document.getElementById('venueCapacity').textContent = venue.capacity;
        document.getElementById('venueEventsCount').textContent = venue.eventsCount;
        document.getElementById('venueRating').textContent = venue.rating;
        document.getElementById('venueYear').textContent = venue.yearBuilt;

        // About
        document.getElementById('venueAbout').innerHTML = `
            <p class="text-base leading-relaxed text-gray-600 whitespace-pre-line">${venue.description}</p>
        `;

        // Quick Info
        document.getElementById('quickInfo').innerHTML = `
            <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-surface rounded-xl text-muted">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
                <div>
                    <div class="mb-1 text-xs tracking-wide uppercase text-muted">Program</div>
                    <div class="text-sm font-semibold text-secondary">${venue.schedule}</div>
                </div>
            </div>
            <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-surface rounded-xl text-muted">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72"/>
                    </svg>
                </div>
                <div>
                    <div class="mb-1 text-xs tracking-wide uppercase text-muted">Telefon</div>
                    <div class="text-sm font-semibold text-secondary"><a href="tel:${venue.phone}" class="text-primary hover:underline">${venue.phone}</a></div>
                </div>
            </div>
            <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-surface rounded-xl text-muted">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <div>
                    <div class="mb-1 text-xs tracking-wide uppercase text-muted">Email</div>
                    <div class="text-sm font-semibold text-secondary"><a href="mailto:${venue.email}" class="text-primary hover:underline">${venue.email}</a></div>
                </div>
            </div>
            <div class="flex items-start gap-3.5 py-3.5">
                <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-surface rounded-xl text-muted">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                </div>
                <div>
                    <div class="mb-1 text-xs tracking-wide uppercase text-muted">Website</div>
                    <div class="text-sm font-semibold text-secondary"><a href="https://${venue.website}" target="_blank" class="text-primary hover:underline">${venue.website}</a></div>
                </div>
            </div>
        `;

        // Address
        document.getElementById('venueAddress').innerHTML = venue.address.replace(', ', '<br>');

        // Amenities
        document.getElementById('venueAmenities').innerHTML = venue.amenities.map(a => `
            <div class="flex items-center gap-2.5 p-3 bg-surface rounded-xl">
                <svg class="w-4 h-4 text-success" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 11 12 14 22 4"/>
                </svg>
                <span class="text-sm font-medium text-gray-600">${a}</span>
            </div>
        `).join('');

        // Gallery
        document.getElementById('venueGallery').innerHTML = venue.gallery.map((img, idx) => `
            <div class="${idx === 0 ? 'col-span-2 row-span-2' : ''} relative rounded-xl overflow-hidden ${idx === 0 ? 'aspect-auto' : 'aspect-[4/3]'} cursor-pointer group">
                <img src="${img}" alt="Galerie" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 transition-all bg-black/0 group-hover:bg-black/30"></div>
                ${idx === 4 ? '<div class="absolute inset-0 flex items-center justify-center text-lg font-bold text-white bg-black/60">+19</div>' : ''}
            </div>
        `).join('');

        // Events
        document.getElementById('eventsList').innerHTML = venue.events.map(e => `
            <a href="/event/${e.title.toLowerCase().replace(/\s+/g, '-')}" class="flex bg-white rounded-2xl overflow-hidden border border-border hover:shadow-lg hover:-translate-y-0.5 hover:border-primary transition-all">
                <div class="flex flex-col items-center justify-center flex-shrink-0 w-24 py-5 text-center bg-gradient-to-br from-primary to-primary-light">
                    <div class="text-3xl font-extrabold leading-none text-white">${e.day}</div>
                    <div class="mt-1 text-sm font-semibold uppercase text-white/90">${e.month}</div>
                </div>
                <div class="flex flex-col justify-center flex-1 px-5 py-4">
                    <div class="mb-1 text-xs font-semibold tracking-wide uppercase text-primary">${e.category}</div>
                    <h3 class="mb-2 text-base font-bold leading-tight text-secondary">${e.title}</h3>
                    <div class="flex gap-4 text-sm text-muted">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-muted/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            ${e.time}
                        </span>
                    </div>
                </div>
                <div class="py-4 px-5 flex flex-col items-end justify-center gap-1.5">
                    <div class="text-xs text-muted">de la <strong class="text-lg font-bold text-success">${e.price} lei</strong></div>
                    <button class="py-2.5 px-5 bg-secondary hover:bg-secondary/90 rounded-lg text-white text-sm font-semibold transition-all">Cumpără bilete</button>
                </div>
            </a>
        `).join('');

        // Similar venues
        document.getElementById('similarVenues').innerHTML = venue.similarVenues.map(v => `
            <a href="/locatie/${v.name.toLowerCase().replace(/\s+/g, '-')}" class="overflow-hidden transition-all bg-white border rounded-2xl border-border hover:-translate-y-1 hover:shadow-lg hover:border-primary">
                <div class="aspect-[16/10] overflow-hidden">
                    <img src="${v.image}" alt="${v.name}" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105">
                </div>
                <div class="p-4">
                    <h3 class="mb-1 text-base font-bold text-secondary">${v.name}</h3>
                    <p class="mb-2 text-sm text-muted">${v.location}</p>
                    <span class="text-xs font-semibold text-primary">${v.events} evenimente</span>
                </div>
            </a>
        `).join('');
    }
};

document.addEventListener('DOMContentLoaded', () => VenuePage.init());
</script>
SCRIPTS;

include __DIR__ . '/includes/scripts.php';
?>
