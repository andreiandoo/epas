<?php
/**
 * Artist Single Page - Ambilet Marketplace
 * Individual artist/performer page with events and details
 */

require_once __DIR__ . '/includes/config.php';

// Get artist slug from URL
$artistSlug = $_GET['slug'] ?? '';
if (empty($artistSlug)) {
    // Try to get from REQUEST_URI
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#/artist/([a-z0-9-]+)#i', $uri, $matches)) {
        $artistSlug = $matches[1];
    }
}

// Page configuration (will be updated by JS with real data)
$pageTitle = "Artist — Ambilet";
$pageDescription = "Descoperă concertele și evenimentele artistului. Cumpără bilete online pe Ambilet.";
$bodyClass = 'page-artist-single';

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative h-[480px] overflow-hidden" id="artistHero">
    <!-- Skeleton -->
    <div class="absolute inset-0 bg-gray-200 skeleton-hero animate-pulse"></div>
    <!-- Hero Image (loaded via JS) -->
    <img id="heroImage" src="" alt="" class="absolute inset-0 w-full h-full object-cover object-[center_30%] hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-black/20 to-black/70"></div>
    <div class="relative z-10 flex flex-col justify-end h-full px-6 pb-10 mx-auto max-w-7xl">
        <!-- Verified Badge -->
        <div class="inline-flex items-center gap-1.5 bg-white/15 backdrop-blur-lg px-3.5 py-2 rounded-full mb-4 w-fit">
            <div class="w-[18px] h-[18px] bg-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-2.5 h-2.5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M20 6L9 17l-5-5"/>
                </svg>
            </div>
            <span class="text-[13px] font-semibold text-white">Artist verificat</span>
        </div>
        <!-- Artist Name -->
        <h1 id="artistName" class="text-[56px] font-extrabold text-white mb-4 leading-tight">
            <span class="inline-block rounded h-14 w-96 bg-white/20 animate-pulse"></span>
        </h1>
        <!-- Genre Tags -->
        <div id="genreTags" class="flex flex-wrap gap-2">
            <span class="w-16 h-8 rounded-full bg-white/20 animate-pulse"></span>
            <span class="w-20 h-8 rounded-full bg-white/20 animate-pulse"></span>
        </div>
    </div>
</section>

<!-- Profile Section -->
<div class="px-6 mx-auto max-w-7xl">
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-6 -mt-20 relative z-20">
        <!-- Stats Card -->
        <div class="flex flex-wrap items-center gap-8 bg-white shadow-lg rounded-2xl p-7">
            <div id="statsContainer" class="flex flex-wrap items-center w-full gap-8">
                <!-- Skeleton stats -->
                <div class="text-center flex-1 min-w-[100px]">
                    <div class="w-20 h-8 mx-auto mb-2 bg-gray-200 rounded animate-pulse"></div>
                    <div class="w-24 h-4 mx-auto bg-gray-100 rounded animate-pulse"></div>
                </div>
                <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
                <div class="text-center flex-1 min-w-[100px]">
                    <div class="w-16 h-8 mx-auto mb-2 bg-gray-200 rounded animate-pulse"></div>
                    <div class="w-20 h-4 mx-auto bg-gray-100 rounded animate-pulse"></div>
                </div>
                <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
                <div class="text-center flex-1 min-w-[100px]">
                    <div class="w-12 h-8 mx-auto mb-2 bg-gray-200 rounded animate-pulse"></div>
                    <div class="w-16 h-4 mx-auto bg-gray-100 rounded animate-pulse"></div>
                </div>
                <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
                <div class="text-center flex-1 min-w-[100px]">
                    <div class="w-10 h-8 mx-auto mb-2 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-4 mx-auto bg-gray-100 rounded w-14 animate-pulse"></div>
                </div>
            </div>
        </div>

        <!-- Actions Card -->
        <div class="p-6 bg-white shadow-lg rounded-2xl">
            <div class="flex gap-3 mb-5">
                <button class="flex-1 flex items-center justify-center gap-2 px-5 py-3.5 bg-gradient-to-r from-primary to-primary-light rounded-xl text-white font-semibold transition-all hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/35">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Urmărește
                </button>
                <button class="flex items-center justify-center w-12 h-12 text-gray-500 transition-colors bg-gray-100 rounded-xl hover:bg-gray-200 hover:text-gray-900">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="18" cy="5" r="3"/>
                        <circle cx="6" cy="12" r="3"/>
                        <circle cx="18" cy="19" r="3"/>
                        <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                        <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                    </svg>
                </button>
            </div>
            <div id="socialLinksContainer" class="flex flex-wrap gap-2.5">
                <a href="#" id="socialFacebook" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-[#1877F2] hover:text-white hover:border-[#1877F2] transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Facebook
                </a>
                <a href="#" id="socialInstagram" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-gradient-to-r hover:from-[#F58529] hover:via-[#DD2A7B] hover:to-[#8134AF] hover:text-white hover:border-[#DD2A7B] transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                    Instagram
                </a>
                <a href="#" id="socialYoutube" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-[#FF0000] hover:text-white hover:border-[#FF0000] transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                    YouTube
                </a>
                <a href="#" id="socialTiktok" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-black hover:text-white hover:border-black transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                    TikTok
                </a>
                <a href="#" id="socialSpotify" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-[#1DB954] hover:text-white hover:border-[#1DB954] transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                    Spotify
                </a>
            </div>
        </div>
    </div>

    <!-- Upcoming Events Section -->
    <section class="mt-10">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[22px] font-bold text-gray-900 flex items-center gap-2.5">
                <span class="text-2xl">🎤</span>
                Concerte viitoare
            </h2>
            <a href="#" id="viewAllEventsLink" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                Vezi toate
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
        </div>

        <div id="eventsList" class="flex flex-col gap-4">
            <!-- Skeleton events -->
            <?php for ($i = 0; $i < 3; $i++): ?>
            <div class="flex flex-col overflow-hidden bg-white border border-gray-200 shadow-sm md:flex-row rounded-2xl">
                <div class="w-full md:w-[100px] h-16 md:h-auto bg-gray-200 animate-pulse"></div>
                <div class="flex-1 p-5">
                    <div class="w-3/4 h-5 mb-3 bg-gray-200 rounded animate-pulse"></div>
                    <div class="w-1/2 h-4 bg-gray-100 rounded animate-pulse"></div>
                </div>
                <div class="flex flex-col items-end justify-center gap-2 p-5">
                    <div class="w-20 h-4 bg-gray-100 rounded animate-pulse"></div>
                    <div class="h-10 bg-gray-200 rounded-lg w-28 animate-pulse"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    <!-- About Section -->
    <section class="mt-10">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[22px] font-bold text-gray-900 flex items-center gap-2.5">
                <span class="text-2xl">📖</span>
                Despre artist
            </h2>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">
            <!-- About Card -->
            <div id="aboutCard" class="bg-white border border-gray-200 shadow-sm rounded-2xl p-7">
                <div class="w-full h-4 mb-4 bg-gray-200 rounded animate-pulse"></div>
                <div class="w-5/6 h-4 mb-4 bg-gray-200 rounded animate-pulse"></div>
                <div class="w-4/5 h-4 mb-4 bg-gray-200 rounded animate-pulse"></div>
                <div class="w-3/4 h-4 bg-gray-200 rounded animate-pulse"></div>
            </div>

            <!-- Quick Facts -->
            <div id="factsCard" class="p-6 bg-white border border-gray-200 shadow-sm rounded-2xl">
                <h3 class="flex items-center gap-2 mb-5 text-base font-bold text-gray-900">⚡ Quick Facts</h3>
                <div class="space-y-3">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="flex justify-between py-3.5 border-b border-gray-100 last:border-0">
                        <div class="w-20 h-4 bg-gray-200 rounded animate-pulse"></div>
                        <div class="w-32 h-4 bg-gray-100 rounded animate-pulse"></div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="mt-10">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[22px] font-bold text-gray-900 flex items-center gap-2.5">
                <span class="text-2xl">📸</span>
                Galerie
            </h2>
        </div>

        <div id="galleryGrid" class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <!-- First item spans 2x2 -->
            <div class="col-span-2 row-span-2 bg-gray-200 aspect-square rounded-xl animate-pulse"></div>
            <div class="bg-gray-200 aspect-square rounded-xl animate-pulse"></div>
            <div class="bg-gray-200 aspect-square rounded-xl animate-pulse"></div>
            <div class="bg-gray-200 aspect-square rounded-xl animate-pulse"></div>
            <div class="bg-gray-200 aspect-square rounded-xl animate-pulse"></div>
        </div>
    </section>

    <!-- Spotify Section -->
    <section class="mt-10">
        <div class="grid items-center grid-cols-1 gap-10 p-8 bg-white border border-gray-200 shadow-sm rounded-2xl lg:grid-cols-2">
            <div>
                <h3 class="text-[22px] font-bold text-gray-900 mb-3 flex items-center gap-2.5">
                    <svg class="w-7 h-7 text-[#1DB954]" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
                    </svg>
                    Ascultă pe Spotify
                </h3>
                <p class="text-gray-500 text-[15px] leading-relaxed mb-5">Descoperă toate albumele, single-urile și colaborările. Peste 2.4 milioane de ascultători lunari!</p>
                <a href="#" target="_blank" class="inline-flex items-center gap-2 px-7 py-3.5 bg-[#1DB954] rounded-full text-white font-semibold text-sm hover:bg-[#1ed760] hover:scale-[1.02] transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02z"/>
                    </svg>
                    Deschide în Spotify
                </a>
            </div>
            <div class="bg-gray-50 rounded-xl h-[200px] flex items-center justify-center text-gray-400 text-sm">
                🎵 Spotify Player Embed
            </div>
        </div>
    </section>

    <!-- Similar Artists Section -->
    <section class="mt-10 mb-16">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[22px] font-bold text-gray-900 flex items-center gap-2.5">
                <span class="text-2xl">🎵</span>
                Artiști similari
            </h2>
        </div>

        <div id="similarArtists" class="grid grid-cols-2 gap-5 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            <!-- Skeleton artists -->
            <?php for ($i = 0; $i < 6; $i++): ?>
            <div class="text-center">
                <div class="w-full aspect-square rounded-full bg-gray-200 animate-pulse mb-3 border-[3px] border-gray-200"></div>
                <div class="w-20 h-4 mx-auto mb-1 bg-gray-200 rounded animate-pulse"></div>
                <div class="w-16 h-3 mx-auto bg-gray-100 rounded animate-pulse"></div>
            </div>
            <?php endfor; ?>
        </div>
    </section>
</div>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';

// Page-specific scripts
$artistSlugJS = json_encode($artistSlug);
$scriptsExtra = <<<SCRIPTS
<script>
const ArtistPage = {
    artistSlug: {$artistSlugJS},
    artistData: null,

    init() {
        this.loadArtistData();
    },

    async loadArtistData() {
        if (!this.artistSlug) {
            console.error('No artist slug provided');
            return;
        }

        try {
            const response = await AmbiletAPI.get('/artists/' + this.artistSlug);
            if (response.success && response.data) {
                this.artistData = response.data;
                this.renderArtist(this.transformApiData(response.data));
            } else {
                console.error('Artist not found');
                // Redirect to artists page
                window.location.href = '/artisti';
            }
        } catch (e) {
            console.error('Failed to load artist:', e);
            // Show error message or redirect
            window.location.href = '/artisti';
        }
    },

    transformApiData(api) {
        // Transform API response to page format
        const months = ['IAN', 'FEB', 'MAR', 'APR', 'MAI', 'IUN', 'IUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];

        // Calculate total followers (sum of all social platforms)
        const totalFollowers = (api.stats?.instagram_followers || 0) +
                              (api.stats?.facebook_followers || 0) +
                              (api.stats?.youtube_subscribers || 0) +
                              (api.stats?.tiktok_followers || 0);

        return {
            name: api.name,
            slug: api.slug,
            image: api.image || api.portrait || 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1920&h=800&fit=crop',
            verified: api.is_verified,
            genres: (api.genres || []).map(g => g.name),
            stats: {
                spotifyListeners: this.formatNumber(api.stats?.spotify_listeners || 0),
                totalFollowers: this.formatNumber(totalFollowers),
                spotifyPopularity: api.stats?.spotify_popularity || 0,
                concerts: (api.stats?.upcoming_events || 0) + (api.stats?.past_events || 0),
                upcomingEvents: api.stats?.upcoming_events || 0
            },
            about: api.biography ? [api.biography] : ['Informații despre acest artist vor fi adăugate în curând.'],
            facts: [
                { label: "Origine", value: [api.city, api.country].filter(Boolean).join(', ') || '-' },
                { label: "Gen muzical", value: (api.genres || []).map(g => g.name).join(', ') || '-' },
                { label: "Tip", value: (api.types || []).map(t => t.name).join(', ') || '-' },
                { label: "Concerte viitoare", value: (api.stats?.upcoming_events || 0).toString() },
                { label: "Concerte anterioare", value: (api.stats?.past_events || 0).toString() }
            ],
            spotifyId: api.external_ids?.spotify_id || null,
            events: (api.upcoming_events || []).map(event => {
                const date = new Date(event.event_date);
                return {
                    slug: event.slug,
                    day: date.getDate().toString().padStart(2, '0'),
                    month: months[date.getMonth()],
                    title: event.title,
                    venue: event.venue ? event.venue.name + ', ' + event.venue.city : '-',
                    time: event.start_time ? event.start_time.substring(0, 5) : '-',
                    price: event.min_price,
                    currency: event.currency || 'RON',
                    soldOut: event.is_sold_out || false,
                    image: event.image
                };
            }),
            gallery: api.youtube_videos?.length > 0 ?
                api.youtube_videos.map((v, i) => ({ url: v.thumbnail, isVideo: true })) :
                [],
            similarArtists: (api.similar_artists || []).map(a => ({
                slug: a.slug,
                name: a.name,
                genre: a.genres?.map(g => g.name).join(', ') || 'Artist',
                image: a.image || 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=300&h=300&fit=crop'
            })),
            social: api.social || {}
        };
    },

    formatNumber(num) {
        if (!num || num === 0) return '0';
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\\.0\$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\\.0\$/, '') + 'K';
        }
        return num.toString();
    },

    renderArtist(data) {
        // Update page title
        document.title = data.name + ' — Ambilet';

        // Update "Vezi toate" link to filter by artist
        const viewAllLink = document.getElementById('viewAllEventsLink');
        if (viewAllLink) {
            viewAllLink.href = '/evenimente?artist=' + encodeURIComponent(data.slug || data.name);
        }
        // Hero image
        const heroImage = document.getElementById('heroImage');
        heroImage.src = data.image;
        heroImage.alt = data.name;
        heroImage.onload = () => {
            heroImage.classList.remove('hidden');
            document.querySelector('.skeleton-hero').classList.add('hidden');
        };

        // Artist name
        document.getElementById('artistName').textContent = data.name;

        // Genre tags
        document.getElementById('genreTags').innerHTML = data.genres.map(genre =>
            `<span class="px-4 py-2 bg-white/20 rounded-full text-[13px] font-medium text-white">${genre}</span>`
        ).join('');

        // Stats
        document.getElementById('statsContainer').innerHTML = `
            <div class="text-center flex-1 min-w-[100px]">
                <div class="text-[28px] font-extrabold text-gray-900">\${data.stats.spotifyListeners}</div>
                <div class="text-[13px] text-gray-500 mt-1">Ascultători Spotify</div>
            </div>
            <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
            <div class="text-center flex-1 min-w-[100px]">
                <div class="text-[28px] font-extrabold text-gray-900">\${data.stats.totalFollowers}</div>
                <div class="text-[13px] text-gray-500 mt-1">Total Followers</div>
            </div>
            <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
            <div class="text-center flex-1 min-w-[100px]">
                <div class="text-[28px] font-extrabold text-gray-900">\${data.stats.spotifyPopularity}</div>
                <div class="text-[13px] text-gray-500 mt-1">Spotify Popularity</div>
            </div>
            <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
            <div class="text-center flex-1 min-w-[100px]">
                <div class="text-[28px] font-extrabold text-gray-900">\${data.stats.upcomingEvents}</div>
                <div class="text-[13px] text-gray-500 mt-1">Concerte viitoare</div>
            </div>
        `;

        // Update social links
        this.updateSocialLinks(data.social);

        // Update Spotify embed if spotifyId available
        this.updateSpotifyEmbed(data.spotifyId, data.social?.spotify, data.stats?.spotifyListeners);

        // Events
        if (data.events.length > 0) {
            document.getElementById('eventsList').innerHTML = data.events.map(event => `
                <a href="/eveniment/\${event.slug}" class="flex flex-col md:flex-row bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-200 hover:shadow-lg hover:-translate-y-0.5 hover:border-primary transition-all">
                    <div class="w-full md:w-[100px] p-5 bg-gradient-to-br from-primary to-primary-light flex md:flex-col items-center justify-center text-white gap-2.5 md:gap-0">
                        <div class="text-[32px] font-extrabold leading-none">\${event.day}</div>
                        <div class="text-sm font-semibold uppercase opacity-90">\${event.month}</div>
                    </div>
                    <div class="flex flex-col justify-center flex-1 p-5">
                        <h3 class="mb-2 text-lg font-bold text-gray-900">\${event.title}</h3>
                        <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                    <circle cx="12" cy="10" r="3"/>
                                </svg>
                                \${event.venue}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                                \${event.time}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-row items-center justify-between gap-2 p-5 md:flex-col md:justify-center">
                        \${event.soldOut ? `
                            <span class="px-5 py-2.5 bg-red-100 rounded-lg text-red-600 text-[13px] font-semibold">SOLD OUT</span>
                        ` : `
                            <div class="text-[13px] text-gray-500">de la <strong class="text-xl font-bold text-emerald-500">\${event.price || '-'} \${event.currency}</strong></div>
                            <button class="px-6 py-3 text-sm font-semibold text-white transition-colors bg-gray-900 rounded-lg hover:bg-gray-800">Cumpără bilete</button>
                        `}
                    </div>
                </a>
            `).join('');
        } else {
            document.getElementById('eventsList').innerHTML = `
                <div class="py-12 text-center bg-white border border-gray-200 rounded-2xl">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                    </svg>
                    <h3 class="mb-2 text-lg font-semibold text-gray-700">Niciun concert programat</h3>
                    <p class="text-sm text-gray-500">Urmărește artistul pentru a fi notificat când apar concerte noi.</p>
                </div>
            `;
        }

        // About
        document.getElementById('aboutCard').innerHTML = data.about.map(text =>
            \`<p class="text-base leading-[1.8] text-gray-600 mb-4 last:mb-0">\${text}</p>\`
        ).join('');

        // Facts
        document.getElementById('factsCard').innerHTML = \`
            <h3 class="flex items-center gap-2 mb-5 text-base font-bold text-gray-900">⚡ Quick Facts</h3>
            \${data.facts.map(fact => \`
                <div class="flex justify-between py-3.5 border-b border-gray-100 last:border-0">
                    <span class="text-sm text-gray-500">\${fact.label}</span>
                    <span class="text-sm font-semibold text-gray-900">\${fact.value}</span>
                </div>
            \`).join('')}
        \`;

        // Gallery
        document.getElementById('galleryGrid').innerHTML = data.gallery.map((item, index) => \`
            <div class="relative rounded-xl overflow-hidden cursor-pointer group \${index === 0 ? 'col-span-2 row-span-2' : ''} aspect-square">
                <img src="\${item.url}" alt="Gallery" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 flex items-center justify-center transition-colors bg-black/0 group-hover:bg-black/30">
                    \${item.isVideo ? \`
                        <div class="w-[60px] h-[60px] bg-primary/90 rounded-full flex items-center justify-center opacity-0 scale-75 group-hover:opacity-100 group-hover:scale-100 transition-all">
                            <svg class="w-6 h-6 ml-1 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                    \` : ''}
                </div>
            </div>
        \`).join('');

        // Similar Artists
        if (data.similarArtists.length > 0) {
            document.getElementById('similarArtists').innerHTML = data.similarArtists.map(artist => `
                <a href="/artist/\${artist.slug}" class="text-center transition-transform group hover:-translate-y-1">
                    <div class="w-full aspect-square rounded-full overflow-hidden mb-3 border-[3px] border-gray-200 group-hover:border-primary transition-colors">
                        <img src="\${artist.image}" alt="\${artist.name}" class="object-cover w-full h-full">
                    </div>
                    <h3 class="text-[15px] font-bold text-gray-900 mb-0.5">\${artist.name}</h3>
                    <p class="text-[13px] text-gray-500">\${artist.genre}</p>
                </a>
            `).join('');
        } else {
            document.getElementById('similarArtists').parentElement.style.display = 'none';
        }
    },

    updateSocialLinks(social) {
        // Map social platforms to their element IDs and URLs
        const socialMap = {
            facebook: { id: 'socialFacebook', url: social?.facebook },
            instagram: { id: 'socialInstagram', url: social?.instagram },
            youtube: { id: 'socialYoutube', url: social?.youtube },
            tiktok: { id: 'socialTiktok', url: social?.tiktok },
            spotify: { id: 'socialSpotify', url: social?.spotify }
        };

        // Update each social link
        Object.values(socialMap).forEach(({ id, url }) => {
            const link = document.getElementById(id);
            if (link) {
                if (url) {
                    link.href = url;
                    link.classList.remove('hidden');
                    link.classList.add('flex');
                } else {
                    link.classList.add('hidden');
                    link.classList.remove('flex');
                }
            }
        });
    },

    updateSpotifyEmbed(spotifyId, spotifyUrl, listeners) {
        const spotifySection = document.querySelector('section:has(.text-\\[\\#1DB954\\])');
        if (!spotifySection) return;

        // Update Spotify link in the section
        const spotifyLink = spotifySection.querySelector('a[target="_blank"]');
        if (spotifyLink && spotifyUrl) {
            spotifyLink.href = spotifyUrl;
        }

        // Update listeners text
        const listenersText = spotifySection.querySelector('p');
        if (listenersText && listeners) {
            listenersText.textContent = `Descoperă toate albumele, single-urile și colaborările. Peste \${listeners} ascultători lunari!`;
        }

        // Add Spotify embed player if spotifyId is available
        const embedContainer = spotifySection.querySelector('.bg-gray-50.rounded-xl');
        if (embedContainer) {
            if (spotifyId) {
                embedContainer.innerHTML = `
                    <iframe
                        style="border-radius:12px"
                        src="https://open.spotify.com/embed/artist/\${spotifyId}?utm_source=generator&theme=0"
                        width="100%"
                        height="200"
                        frameBorder="0"
                        allowfullscreen=""
                        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                        loading="lazy">
                    </iframe>
                `;
                embedContainer.classList.remove('flex', 'items-center', 'justify-center', 'text-gray-400', 'text-sm');
            } else if (!spotifyUrl) {
                // Hide entire Spotify section if no spotify data
                spotifySection.style.display = 'none';
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', () => ArtistPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
