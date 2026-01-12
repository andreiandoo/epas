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

$artistName = $_GET['name'] ?? 'artistului'; // Fallback name


// Page configuration (will be updated by JS with real data)
$pageTitle = "{$artistName} — {$siteName}";
$pageDescription = "Descoperă concertele și evenimentele {$artistName}. Cumpără bilete online pe {$siteName}.";
$bodyClass = 'page-artist-single';

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative h-[480px] overflow-hidden mt-24 mobile:mt-0" id="artistHero">
    <!-- Skeleton -->
    <div class="absolute inset-0 bg-gray-200 skeleton-hero animate-pulse"></div>
    <!-- Hero Image (loaded via JS) -->
    <img id="heroImage" src="" alt="" class="absolute inset-0 w-full h-full object-cover object-[center_30%] hidden">
    <div class="absolute inset-0 bg-gradient-to-b from-black/20 to-black/70"></div>
    <div class="relative z-10 flex flex-col justify-end h-full px-6 pb-20 mx-auto max-w-7xl">
        <!-- Genre Tags -->
        <div id="genreTags" class="flex flex-wrap gap-2 mb-4">
            <span class="inline-block w-20 h-8 rounded-full bg-white/20 animate-pulse"></span>
            <span class="inline-block w-24 h-8 rounded-full bg-white/20 animate-pulse"></span>
        </div>
        <!-- Artist Name -->
        <div class="flex items-center gap-x-4">
            <h1 id="artistName" class="text-[56px] font-extrabold text-white mb-4 leading-tight">
                <span class="inline-block rounded h-14 w-96 bg-white/20 animate-pulse"></span>
            </h1>
            <!-- Verified Badge -->
            <div class="w-[34px] h-[34px] bg-blue-500 rounded-full flex items-center justify-center">
                <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <path d="M20 6L9 17l-5-5"/>
                </svg>
            </div>
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
                <button id="follow-btn" onclick="ArtistPage.toggleFollow()" class="flex-1 flex items-center justify-center gap-2 px-5 py-3.5 bg-gradient-to-r from-primary to-primary-light rounded-xl text-white font-semibold transition-all hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/35">
                    <svg id="follow-icon" class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    <span id="follow-text">Urmărește</span>
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
                </a>
                <a href="#" id="socialInstagram" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-gradient-to-r hover:from-[#F58529] hover:via-[#DD2A7B] hover:to-[#8134AF] hover:text-white hover:border-transparent transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                </a>
                <a href="#" id="socialYoutube" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-[#FF0000] hover:text-white hover:border-[#FF0000] transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                </a>
                <a href="#" id="socialTiktok" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-black hover:text-white hover:border-black transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                </a>
                <a href="#" id="socialSpotify" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-[#1DB954] hover:text-white hover:border-[#1DB954] transition-colors hidden" target="_blank">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/></svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Upcoming Events Section -->
    <section class="mt-10">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[22px] font-bold text-gray-900 flex items-center gap-2.5">
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
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6 items-start">
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

    <!-- Booking Agency (hidden by default, shown if data exists) -->
    <section id="bookingAgencyCard" class="hidden mt-10">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[22px] font-bold text-gray-900 flex items-center gap-2.5">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                Agenția de Booking
            </h2>
        </div>
        <div id="bookingAgencyContent" class="flex items-center justify-between gap-4">
            <!-- Content will be loaded dynamically -->
        </div>
    </section>

    <!-- YouTube Videos Section -->
    <section id="youtubeVideosSection" class="hidden mt-10">
        <div class="flex items-center justify-between mb-5">
            <h2 class="text-[22px] font-bold text-gray-900 flex items-center gap-2.5">
                <svg class="w-7 h-7 text-[#FF0000]" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                </svg>
                Videoclipuri
            </h2>
        </div>

        <div id="youtubeVideosGrid" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            <!-- Videos will be loaded dynamically -->
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="hidden mt-10">
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
    <section id="spotifySection" class="mt-10">
        <div class="grid items-center grid-cols-1 gap-10 p-8 bg-white lg:grid-cols-2">
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
                        <path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.54.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z"/>
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

// Pass artist slug to JavaScript
echo '<script>window.ARTIST_SLUG = ' . json_encode($artistSlug) . ';</script>';

// Load external artist page controller
$scriptsExtra = '<script src="' . asset('assets/js/pages/artist-single.js') . '"></script>
<script>document.addEventListener(\'DOMContentLoaded\', () => ArtistPage.init());</script>';

require_once __DIR__ . '/includes/scripts.php';
