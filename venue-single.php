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
<section class="relative h-[300px] sm:h-[400px] lg:h-[550px] overflow-hidden" id="venueHero">
    <!-- Skeleton -->
    <div class="absolute inset-0 bg-gray-200 skeleton-hero animate-pulse"></div>

    <!-- Content loaded by JS -->
    <img id="heroImage" src="" alt="" class="absolute inset-0 hidden object-cover object-center w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-b from-black/30 to-black/70"></div>

    <div class="relative z-10 flex flex-col justify-end h-full max-w-6xl px-6 pb-24 mx-auto">
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

        <!-- Follow Button -->
        <button id="follow-btn" onclick="VenuePage.toggleFollow()" class="flex items-center gap-2 px-5 py-2.5 mt-4 bg-white/20 backdrop-blur-md rounded-xl text-white font-semibold transition-all hover:bg-white/30 absolute right-4 bottom-28">
            <svg id="follow-icon" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span id="follow-text">Urmărește locația</span>
        </button>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-6xl px-6 mx-auto">
    <!-- Info Cards -->
    <div class="relative z-20 grid grid-cols-4 gap-5 mb-10 -mt-16 mobile:grid-cols-2" id="infoCards">
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
            <section id="gallerySection" class="mb-10">
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
                    <div class="flex items-start gap-3.5 py-3.5 border-b border-gray-100 animate-pulse mobile:truncate">
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
            <div id="amenitiesSection" class="p-6 bg-white border rounded-2xl border-border">
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
                <button onclick="VenuePage.openContactModal()" class="flex items-center justify-center gap-2 w-full py-3.5 bg-primary hover:bg-primary-dark rounded-xl text-white text-sm font-semibold transition-all mb-3">
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
    <section id="similarVenuesSection" class="mt-10 mb-16">
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

<!-- Contact Venue Modal -->
<div id="contactVenueModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/60 backdrop-blur-sm" onclick="VenuePage.closeContactModal(event)">
    <div class="w-full max-w-lg mx-4 bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border">
            <div>
                <h2 class="text-lg font-bold text-secondary">Contactează locația</h2>
                <p id="contactVenueName" class="text-sm text-muted"></p>
            </div>
            <button onclick="VenuePage.closeContactModal()" class="p-2 transition-colors rounded-lg hover:bg-surface">
                <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form id="contactVenueForm" onsubmit="VenuePage.submitContactForm(event)" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Numele tău</label>
                    <input type="text" name="name" required maxlength="100" class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="Nume și prenume">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-secondary">Email</label>
                    <input type="email" name="email" required maxlength="150" class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="exemplu@email.com">
                </div>
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-secondary">Subiect</label>
                <input type="text" name="subject" required maxlength="200" class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary" placeholder="Despre ce este mesajul?">
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-secondary">Mesajul tău</label>
                <textarea name="message" required maxlength="2000" rows="5" class="w-full px-4 py-2.5 border border-border rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary resize-none" placeholder="Scrie mesajul tău aici..."></textarea>
            </div>
            <div id="contactFormError" class="hidden p-3 text-sm text-red-700 bg-red-50 rounded-xl"></div>
            <div id="contactFormSuccess" class="hidden p-3 text-sm text-green-700 bg-green-50 rounded-xl"></div>
            <button type="submit" id="contactSubmitBtn" class="flex items-center justify-center w-full gap-2 py-3 text-sm font-semibold text-white transition-all bg-primary rounded-xl hover:bg-primary-dark">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>
                </svg>
                <span>Trimite mesajul</span>
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
// Pass slug to JavaScript (htaccess rewrites URL, so query params not visible in browser)
echo '<script>window.VENUE_SLUG = ' . json_encode($venueSlug) . ';</script>';

$scriptsExtra = '<script src="' . asset('assets/js/pages/venue-single.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => VenuePage.init());</script>';

include __DIR__ . '/includes/scripts.php';
