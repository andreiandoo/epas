<?php
/**
 * Venue Single Page
 * Template based on venue-single.html
 */

require_once __DIR__ . '/includes/config.php';

// Get venue slug from URL
$venueSlug = $_GET['slug'] ?? '';

$pageTitle = 'Locație'; // Updated by JS when data loads
$pageDescription = 'Descoperă evenimente și informații despre această locație.';
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

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
// Pass slug to JavaScript (htaccess rewrites URL, so query params not visible in browser)
echo '<script>window.VENUE_SLUG = ' . json_encode($venueSlug) . ';</script>';

$scriptsExtra = <<<SCRIPTS
<script src="/assets/js/pages/venue-single.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => VenuePage.init());</script>
SCRIPTS;

include __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
