<?php
/**
 * Artist Single Page - Ambilet Marketplace
 * Individual artist/performer page with events and details
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Carla's Dreams — Artist";
$pageDescription = "Descoperă concertele și evenimentele lui Carla's Dreams. Cumpără bilete online pe Ambilet.";
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
            <div class="flex gap-2.5">
                <a href="#" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-gray-100 hover:text-gray-900 transition-colors">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><rect x="2" y="2" width="20" height="20" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    Instagram
                </a>
                <a href="#" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-gray-100 hover:text-gray-900 transition-colors">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="white"/></svg>
                    YouTube
                </a>
                <a href="#" class="flex-1 flex items-center justify-center gap-1.5 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-500 text-[13px] font-medium hover:bg-gray-100 hover:text-gray-900 transition-colors">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02z"/></svg>
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
            <a href="/evenimente" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
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
            <a href="#" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                Vezi toate
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
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
$scriptsExtra = <<<'SCRIPTS'
<script>
const ArtistPage = {
    init() {
        this.loadArtistData();
    },

    async loadArtistData() {
        // Simulate API call
        await new Promise(resolve => setTimeout(resolve, 800));
        const data = this.getMockData();
        this.renderArtist(data);
    },

    getMockData() {
        return {
            name: "Carla's Dreams",
            image: "https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=1920&h=800&fit=crop",
            verified: true,
            genres: ["Pop", "Hip-Hop", "R&B"],
            stats: {
                listeners: "2.4M",
                followers: "847K",
                concerts: 156,
                awards: 12
            },
            about: [
                "<span class='font-semibold text-primary'>Carla's Dreams</span> este un proiect muzical misterios din Republica Moldova, lansat în 2012. Cunoscut pentru stilul său unic care îmbină pop-ul cu hip-hop-ul și R&B-ul, artistul și-a construit o bază impresionantă de fani în întreaga Europă de Est.",
                "Cu hituri precum <span class='font-semibold text-primary'>\"Sub Pielea Mea\"</span>, <span class='font-semibold text-primary'>\"Imperfect\"</span> și <span class='font-semibold text-primary'>\"Antiexemplu\"</span>, Carla's Dreams a acumulat miliarde de vizualizări pe YouTube și a devenit unul dintre cei mai ascultați artiști români pe Spotify.",
                "Identitatea artistului rămâne un mister, acesta alegând să apară mereu cu fața acoperită, lăsând muzica să vorbească de la sine."
            ],
            facts: [
                { label: "Origine", value: "Chișinău, Moldova" },
                { label: "Activ din", value: "2012" },
                { label: "Gen muzical", value: "Pop, Hip-Hop, R&B" },
                { label: "Casa de discuri", value: "Global Records" },
                { label: "Albume", value: "4 albume de studio" }
            ],
            events: [
                { day: "15", month: "MAR", title: "Carla's Dreams - Turneul Național 2025", venue: "Sala Palatului, București", time: "20:00", price: 149, soldOut: false },
                { day: "22", month: "MAR", title: "Carla's Dreams - Turneul Național 2025", venue: "BT Arena, Cluj-Napoca", time: "20:00", price: 129, soldOut: false },
                { day: "29", month: "MAR", title: "Carla's Dreams - Turneul Național 2025", venue: "Sala Capitol, Timișoara", time: "20:00", price: null, soldOut: true }
            ],
            gallery: [
                { url: "https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=600&h=600&fit=crop", isVideo: true },
                { url: "https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=400&h=400&fit=crop", isVideo: false },
                { url: "https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=400&h=400&fit=crop", isVideo: false },
                { url: "https://images.unsplash.com/photo-1516450360452-9312f5e86fc7?w=400&h=400&fit=crop", isVideo: false },
                { url: "https://images.unsplash.com/photo-1429962714451-bb934ecdc4ec?w=400&h=400&fit=crop", isVideo: false }
            ],
            similarArtists: [
                { name: "INNA", genre: "Pop, Dance", image: "https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=300&h=300&fit=crop" },
                { name: "The Motans", genre: "Pop, Rock", image: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&h=300&fit=crop" },
                { name: "Irina Rimes", genre: "Pop, R&B", image: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=300&h=300&fit=crop" },
                { name: "Delia", genre: "Pop, Dance", image: "https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=300&h=300&fit=crop" },
                { name: "Smiley", genre: "Pop, Hip-Hop", image: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=300&h=300&fit=crop" },
                { name: "Andra", genre: "Pop, Soul", image: "https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300&h=300&fit=crop" }
            ]
        };
    },

    renderArtist(data) {
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
                <div class="text-[28px] font-extrabold text-gray-900">${data.stats.listeners}</div>
                <div class="text-[13px] text-gray-500 mt-1">Ascultători lunari</div>
            </div>
            <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
            <div class="text-center flex-1 min-w-[100px]">
                <div class="text-[28px] font-extrabold text-gray-900">${data.stats.followers}</div>
                <div class="text-[13px] text-gray-500 mt-1">Followers</div>
            </div>
            <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
            <div class="text-center flex-1 min-w-[100px]">
                <div class="text-[28px] font-extrabold text-gray-900">${data.stats.concerts}</div>
                <div class="text-[13px] text-gray-500 mt-1">Concerte</div>
            </div>
            <div class="hidden w-px h-12 bg-gray-200 lg:block"></div>
            <div class="text-center flex-1 min-w-[100px]">
                <div class="text-[28px] font-extrabold text-gray-900">${data.stats.awards}</div>
                <div class="text-[13px] text-gray-500 mt-1">Premii</div>
            </div>
        `;

        // Events
        document.getElementById('eventsList').innerHTML = data.events.map(event => `
            <a href="/event/${AmbiletUtils.slugify(event.title)}" class="flex flex-col md:flex-row bg-white rounded-2xl shadow-sm overflow-hidden border border-gray-200 hover:shadow-lg hover:-translate-y-0.5 hover:border-primary transition-all">
                <div class="w-full md:w-[100px] p-5 bg-gradient-to-br from-primary to-primary-light flex md:flex-col items-center justify-center text-white gap-2.5 md:gap-0">
                    <div class="text-[32px] font-extrabold leading-none">${event.day}</div>
                    <div class="text-sm font-semibold uppercase opacity-90">${event.month}</div>
                </div>
                <div class="flex flex-col justify-center flex-1 p-5">
                    <h3 class="mb-2 text-lg font-bold text-gray-900">${event.title}</h3>
                    <div class="flex flex-wrap gap-4 text-sm text-gray-500">
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            ${event.venue}
                        </span>
                        <span class="flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            ${event.time}
                        </span>
                    </div>
                </div>
                <div class="flex flex-row items-center justify-between gap-2 p-5 md:flex-col md:justify-center">
                    ${event.soldOut ? `
                        <span class="px-5 py-2.5 bg-red-100 rounded-lg text-red-600 text-[13px] font-semibold">SOLD OUT</span>
                    ` : `
                        <div class="text-[13px] text-gray-500">de la <strong class="text-xl font-bold text-emerald-500">${event.price} lei</strong></div>
                        <button class="px-6 py-3 text-sm font-semibold text-white transition-colors bg-gray-900 rounded-lg hover:bg-gray-800">Cumpără bilete</button>
                    `}
                </div>
            </a>
        `).join('');

        // About
        document.getElementById('aboutCard').innerHTML = data.about.map(text =>
            `<p class="text-base leading-[1.8] text-gray-600 mb-4 last:mb-0">${text}</p>`
        ).join('');

        // Facts
        document.getElementById('factsCard').innerHTML = `
            <h3 class="flex items-center gap-2 mb-5 text-base font-bold text-gray-900">⚡ Quick Facts</h3>
            ${data.facts.map(fact => `
                <div class="flex justify-between py-3.5 border-b border-gray-100 last:border-0">
                    <span class="text-sm text-gray-500">${fact.label}</span>
                    <span class="text-sm font-semibold text-gray-900">${fact.value}</span>
                </div>
            `).join('')}
        `;

        // Gallery
        document.getElementById('galleryGrid').innerHTML = data.gallery.map((item, index) => `
            <div class="relative rounded-xl overflow-hidden cursor-pointer group ${index === 0 ? 'col-span-2 row-span-2' : ''} aspect-square">
                <img src="${item.url}" alt="Gallery" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105">
                <div class="absolute inset-0 flex items-center justify-center transition-colors bg-black/0 group-hover:bg-black/30">
                    ${item.isVideo ? `
                        <div class="w-[60px] h-[60px] bg-primary/90 rounded-full flex items-center justify-center opacity-0 scale-75 group-hover:opacity-100 group-hover:scale-100 transition-all">
                            <svg class="w-6 h-6 ml-1 text-white" viewBox="0 0 24 24" fill="currentColor">
                                <polygon points="5 3 19 12 5 21 5 3"/>
                            </svg>
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');

        // Similar Artists
        document.getElementById('similarArtists').innerHTML = data.similarArtists.map(artist => `
            <a href="/artist/${AmbiletUtils.slugify(artist.name)}" class="text-center transition-transform group hover:-translate-y-1">
                <div class="w-full aspect-square rounded-full overflow-hidden mb-3 border-[3px] border-gray-200 group-hover:border-primary transition-colors">
                    <img src="${artist.image}" alt="${artist.name}" class="object-cover w-full h-full">
                </div>
                <h3 class="text-[15px] font-bold text-gray-900 mb-0.5">${artist.name}</h3>
                <p class="text-[13px] text-gray-500">${artist.genre}</p>
            </a>
        `).join('');
    }
};

document.addEventListener('DOMContentLoaded', () => ArtistPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
