<?php
/**
 * Venue Single Page
 * Layout inspired by designs/new-venue-single.html.
 *
 * Elements are populated by assets/js/pages/venue-single.js. IDs used by the
 * JS layer are preserved so existing render paths keep working; new UI pieces
 * (share dropdown, gallery lightbox, event filter tabs, stats tiles,
 * mobile-sticky CTA, reviews grid) have their own dedicated IDs.
 */
$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';

// Get venue slug from URL
$venueSlug = $_GET['slug'] ?? '';

$pageTitle = 'Locație'; // Updated by JS when data loads
$pageDescription = 'Descoperă evenimente și informații despre această locație.';
$bodyClass = 'bg-slate-50';

$cssBundle = 'single';
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<style>
    /* Page-local tweaks (animations + gradients that can't be expressed
       cleanly with Tailwind utilities alone). */
    .venue-card-shadow { box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.04); }
    .venue-card-shadow-hover:hover { box-shadow: 0 4px 12px rgba(16,24,40,.08), 0 2px 6px rgba(16,24,40,.04); }

    #eventsList .event-row { transition: all .2s ease; }
    #eventsList .event-row:hover { border-left-color: var(--color-primary, #C8102E); transform: translateY(-1px); }
    #eventsList .event-row:hover .event-buy-btn { background: var(--color-primary, #C8102E); color: #fff; }

    .venue-thumb-1 { background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%); }
    .venue-thumb-2 { background: linear-gradient(135deg, #a855f7 0%, #6d28d9 100%); }
    .venue-thumb-3 { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
    .venue-thumb-4 { background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); }
    .venue-thumb-5 { background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); }
    .venue-thumb-6 { background: linear-gradient(135deg, #ec4899 0%, #9d174d 100%); }

    .venue-hero-overlay { background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.3) 60%, rgba(0,0,0,0.7) 100%); }

    @keyframes venue-pulse-dot { 0%,100% { opacity: 1; } 50% { opacity: .5; } }
    .venue-pulse-dot { animation: venue-pulse-dot 2s ease-in-out infinite; }

    .event-tab { position: relative; }
    .event-tab.is-active { color: var(--color-primary, #C8102E); }
    .event-tab.is-active::after { content:''; position:absolute; bottom:-1px; left:0; right:0; height:2px; background: var(--color-primary, #C8102E); }

    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

    /* Gallery lightbox thumbnail strip ring */
    .lightbox-thumb.is-active { outline: 2px solid var(--color-primary, #C8102E); outline-offset: 2px; }
</style>

<!-- ═══════════════ HERO (compressed) ═══════════════ -->
<section class="px-4 pt-4 mx-auto max-w-7xl lg:px-6 mt-17 mobile:mt-16">
    <div id="venueHero" class="relative h-[280px] md:h-[340px] rounded-2xl overflow-hidden venue-card-shadow bg-slate-200">
        <!-- Skeleton / fallback colour before image loads -->
        <div class="absolute inset-0 skeleton-hero animate-pulse bg-slate-300"></div>

        <img id="heroImage" src="" alt="" class="absolute inset-0 hidden object-cover w-full h-full">
        <div class="absolute inset-0 pointer-events-none venue-hero-overlay"></div>

        <div class="relative z-10 flex flex-col justify-between h-full p-5 md:p-8">
            <!-- Top row: category + actions -->
            <div class="flex items-start justify-between gap-3">
                <div id="venueCategoryBadge" class="hidden inline-flex items-center gap-2 bg-white/95 backdrop-blur px-3 py-1.5 rounded-full text-xs font-semibold text-slate-800">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
                    <span id="venueCategoryBadgeLabel">Locație</span>
                </div>
                <div class="relative flex items-center gap-2">
                    <!-- Share button + dropdown -->
                    <div class="relative">
                        <button type="button" id="shareBtn" onclick="VenuePage.toggleShareDropdown(event)" class="flex items-center justify-center transition rounded-full w-9 h-9 bg-white/95 backdrop-blur hover:bg-white" title="Distribuie" aria-label="Distribuie">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></svg>
                        </button>
                        <div id="shareDropdown" class="absolute right-0 z-20 hidden w-64 overflow-hidden bg-white border shadow-xl top-11 rounded-xl border-slate-200">
                            <div class="px-4 pt-3 pb-2 border-b border-slate-100">
                                <div class="text-xs font-semibold text-slate-900">Distribuie locația</div>
                                <div id="shareUrl" class="text-xs truncate text-slate-500"></div>
                            </div>
                            <button type="button" onclick="VenuePage.copyShareLink()" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition text-left">
                                <div id="shareCopyIconWrap" class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100">
                                    <svg id="shareCopyIcon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                </div>
                                <div class="flex-1">
                                    <div id="shareCopyLabel" class="text-sm font-medium text-slate-900">Copiază link</div>
                                    <div id="shareCopyHint" class="text-xs text-slate-500">Copiază URL-ul paginii</div>
                                </div>
                            </button>
                            <a id="shareFacebook" href="#" target="_blank" rel="noopener" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                                <div class="flex items-center justify-center w-8 h-8 bg-blue-100 rounded-lg">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </div>
                                <div class="text-sm font-medium text-slate-900">Facebook</div>
                            </a>
                            <a id="shareWhatsapp" href="#" target="_blank" rel="noopener" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="#25D366"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                </div>
                                <div class="text-sm font-medium text-slate-900">WhatsApp</div>
                            </a>
                            <a id="shareTwitter" href="#" target="_blank" rel="noopener" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-slate-900">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="white"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                </div>
                                <div class="text-sm font-medium text-slate-900">X (Twitter)</div>
                            </a>
                            <a id="shareEmail" href="#" class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-slate-50 transition">
                                <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-amber-100">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                </div>
                                <div class="text-sm font-medium text-slate-900">Email</div>
                            </a>
                        </div>
                    </div>
                    <!-- Gallery button with counter -->
                    <button type="button" id="galleryBtn" onclick="VenuePage.openLightbox(0)" class="relative flex items-center justify-center hidden transition rounded-full w-9 h-9 bg-white/95 backdrop-blur hover:bg-white" title="Galerie" aria-label="Deschide galeria">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                        <span id="galleryCount" class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 bg-primary text-white text-[10px] font-bold rounded-full flex items-center justify-center">0</span>
                    </button>
                </div>
            </div>

            <!-- Bottom row: title + follow -->
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="text-white">
                    <h1 id="venueName" class="mb-2 text-2xl font-bold tracking-tight md:text-4xl lg:text-5xl">&nbsp;</h1>
                    <div class="flex flex-wrap items-center text-sm gap-x-4 gap-y-1 text-white/90">
                        <span class="flex items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 7-8 13-8 13s-8-6-8-13a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                            <span id="venueLocation"></span>
                        </span>
                        <span id="venueOpenStatus" class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 bg-green-400 rounded-full venue-pulse-dot"></span>
                            <span id="venueOpenStatusText">În funcție de evenimente</span>
                        </span>
                        <span id="venueRatingHero" class="hidden items-center gap-1.5">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <span id="venueRatingHeroValue">0</span>
                            <span class="text-white/70">(<span id="venueRatingHeroCount">0</span>)</span>
                        </span>
                    </div>
                </div>
                <button id="follow-btn" onclick="VenuePage.toggleFollow()" class="bg-white hover:bg-slate-100 transition px-4 py-2.5 rounded-lg text-sm font-semibold text-slate-900 flex items-center gap-2" aria-label="Urmărește locația">
                    <svg id="follow-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    <span id="follow-text">Urmărește locația</span>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════ MAIN GRID ═══════════════ -->
<main class="px-4 py-8 pb-24 mx-auto max-w-7xl lg:px-6 lg:pb-8">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <!-- ━━━━━━━━━━ LEFT (2/3) ━━━━━━━━━━ -->
        <div class="space-y-6 lg:col-span-2">

            <!-- ─── EVENTS (primary CTA) ─── -->
            <section class="overflow-hidden bg-white rounded-2xl venue-card-shadow">
                <div class="px-5 pt-6 pb-4 border-b md:px-6 border-slate-100">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-secondary">Evenimente viitoare</h2>
                                <p id="eventsSubheader" class="text-sm text-muted">Se încarcă…</p>
                            </div>
                        </div>
                        <a id="allEventsLink" href="/evenimente" class="flex items-center gap-1 text-sm font-semibold text-primary hover:text-primary-dark">
                            Vezi toate evenimentele
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>

                    <!-- Filter tabs (categories, built dynamically from events) -->
                    <div id="eventFilterTabs" class="items-center hidden gap-1 pb-2 -mb-4 overflow-x-auto no-scrollbar"></div>
                </div>

                <!-- Events list — populated by JS -->
                <div id="eventsList" class="divide-y divide-slate-100">
                    <!-- Skeleton rows -->
                    <div class="flex items-center gap-4 p-5 animate-pulse">
                        <div class="w-16 h-16 rounded-lg bg-slate-200"></div>
                        <div class="hidden w-20 h-20 rounded-lg sm:block bg-slate-200"></div>
                        <div class="flex-1">
                            <div class="w-24 h-3 mb-2 rounded bg-slate-200"></div>
                            <div class="w-3/4 h-4 mb-2 rounded bg-slate-200"></div>
                            <div class="w-32 h-3 rounded bg-slate-200"></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 p-5 animate-pulse">
                        <div class="w-16 h-16 rounded-lg bg-slate-200"></div>
                        <div class="hidden w-20 h-20 rounded-lg sm:block bg-slate-200"></div>
                        <div class="flex-1">
                            <div class="w-24 h-3 mb-2 rounded bg-slate-200"></div>
                            <div class="w-3/4 h-4 mb-2 rounded bg-slate-200"></div>
                            <div class="w-32 h-3 rounded bg-slate-200"></div>
                        </div>
                    </div>
                </div>

                <div id="eventsFooter" class="flex flex-wrap items-center justify-between hidden gap-3 px-5 py-4 border-t md:px-6 border-slate-100">
                    <span id="eventsFooterCount" class="text-sm text-muted"></span>
                </div>
            </section>

            <!-- ─── STATS (compact tiles) ─── -->
            <section class="grid grid-cols-2 gap-3 md:grid-cols-3">
                <div class="flex items-center gap-3 p-4 bg-white rounded-xl venue-card-shadow">
                    <div class="flex items-center justify-center flex-none w-10 h-10 rounded-lg bg-blue-50">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div id="statCapacity" class="text-lg font-bold leading-tight text-secondary">-</div>
                        <div class="text-xs text-muted">Capacitate locuri</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-4 bg-white rounded-xl venue-card-shadow">
                    <div class="flex items-center justify-center flex-none w-10 h-10 rounded-lg bg-primary/10">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div id="statEvents" class="text-lg font-bold leading-tight text-secondary">-</div>
                        <div class="text-xs text-muted">Evenimente viitoare</div>
                    </div>
                </div>
                <div id="statRatingTile" class="flex items-center hidden gap-3 p-4 bg-white rounded-xl venue-card-shadow">
                    <div class="flex items-center justify-center flex-none w-10 h-10 rounded-lg bg-amber-50">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-lg font-bold leading-tight text-secondary"><span id="statRatingValue">-</span><span class="text-xs font-medium text-muted">/5</span></div>
                        <div id="statRatingCount" class="text-xs text-muted">0 recenzii</div>
                    </div>
                </div>
            </section>

            <!-- ─── ABOUT + AMENITIES ─── -->
            <section id="aboutSection" class="hidden p-6 bg-white rounded-2xl venue-card-shadow">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary/10">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    </div>
                    <h2 class="text-xl font-bold text-secondary">Despre locație</h2>
                </div>
                <div id="venueAbout" class="mb-6 leading-relaxed text-slate-700"></div>

                <div id="amenitiesBlock" class="hidden">
                    <h3 class="mb-3 text-sm font-semibold text-secondary">Dotări &amp; facilități</h3>
                    <div id="venueAmenities" class="grid grid-cols-1 gap-2 sm:grid-cols-2 md:grid-cols-3"></div>
                </div>
            </section>

            <!-- ─── REVIEWS (gated on Google Reviews data) ─── -->
            <section id="reviewsSection" class="hidden p-6 bg-white rounded-2xl venue-card-shadow">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-secondary">Recenzii vizitatori</h2>
                            <p id="reviewsSubheader" class="text-sm text-muted">0 din 5 · 0 recenzii</p>
                        </div>
                    </div>
                    <a id="reviewsSeeAllLink" href="#" target="_blank" rel="noopener" class="hidden text-sm font-semibold text-primary hover:text-primary-dark">Vezi toate pe Google →</a>
                </div>
                <div id="reviewsGrid" class="grid grid-cols-1 gap-4 md:grid-cols-3"></div>
            </section>
        </div>

        <!-- ━━━━━━━━━━ RIGHT (1/3, sticky) ━━━━━━━━━━ -->
        <aside class="space-y-6 lg:sticky lg:top-20 lg:self-start">

            <!-- ─── Quick Info / Contact ─── -->
            <div class="overflow-hidden bg-white rounded-2xl venue-card-shadow">
                <div class="flex items-center gap-2 px-5 py-4 border-b border-slate-100">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-primary"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                    <h3 class="font-bold text-secondary">Informații rapide</h3>
                </div>
                <div id="quickInfo" class="divide-y divide-slate-100">
                    <!-- Populated by JS -->
                    <div class="px-5 py-3.5 animate-pulse flex items-start gap-3">
                        <div class="w-4 h-4 bg-slate-200 rounded mt-0.5"></div>
                        <div class="flex-1">
                            <div class="w-14 h-3 bg-slate-200 rounded mb-1.5"></div>
                            <div class="w-32 h-4 rounded bg-slate-200"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Map ─── -->
            <div class="overflow-hidden bg-white rounded-2xl venue-card-shadow">
                <div id="venueMap" class="flex flex-col items-center justify-center aspect-[4/3] bg-slate-100 text-muted">
                    <svg class="w-10 h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <p class="mt-2 text-sm">Hartă interactivă</p>
                </div>
                <div class="p-4">
                    <p id="venueAddress" class="mb-3 text-sm leading-relaxed text-slate-700"></p>
                    <a href="#" id="mapsLink" target="_blank" rel="noopener" class="w-full bg-slate-900 hover:bg-slate-800 text-white py-2.5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 transition">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                        Deschide în Google Maps
                    </a>
                </div>
            </div>

            <!-- ─── CTA Organizers ─── -->
            <div class="relative p-5 overflow-hidden text-white bg-primary rounded-2xl">
                <div class="absolute w-32 h-32 rounded-full -top-8 -right-8 bg-white/10"></div>
                <div class="absolute w-32 h-32 rounded-full -bottom-10 -left-10 bg-white/5"></div>
                <div class="relative">
                    <div class="flex items-center justify-center w-10 h-10 mb-3 rounded-lg bg-white/20">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h3 class="mb-1 text-lg font-bold">Organizezi un eveniment?</h3>
                    <p class="mb-4 text-sm text-white/85">Contactează echipa locației pentru disponibilitate și tarife.</p>
                    <button type="button" onclick="VenuePage.openContactModal()" class="w-full block bg-white text-primary text-center py-2.5 rounded-lg text-sm font-bold hover:bg-slate-100 transition" aria-label="Contactează locația">
                        Contactează locația →
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <!-- ═══════════════ SIMILAR VENUES ═══════════════ -->
    <section id="similarVenuesSection" class="hidden mt-10">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <div>
                <h2 id="similarVenuesTitle" class="text-xl font-bold text-secondary">Locații similare</h2>
                <p id="similarVenuesSubtitle" class="text-sm text-muted mt-0.5">Alte locații pe care le poți descoperi</p>
            </div>
            <a id="similarVenuesSeeAllLink" href="/locatii" class="text-sm font-semibold text-primary hover:text-primary-dark">Vezi toate →</a>
        </div>
        <div id="similarVenues" class="grid grid-cols-2 gap-4 md:grid-cols-4"></div>
    </section>
</main>

<!-- ═══════════════ MOBILE STICKY CTA ═══════════════ -->
<div id="mobileStickyCta" class="fixed bottom-0 left-0 right-0 z-30 flex items-center hidden gap-3 p-3 bg-white border-t shadow-lg lg:hidden border-slate-200">
    <div class="flex-1 min-w-0">
        <div class="text-xs text-muted">Următorul eveniment</div>
        <div id="mobileCtaEventLabel" class="text-sm font-semibold truncate text-secondary"></div>
    </div>
    <a id="mobileCtaLink" href="#" class="px-5 py-3 text-sm font-semibold text-white rounded-lg bg-primary whitespace-nowrap">Bilete →</a>
</div>

<!-- ═══════════════ GALLERY LIGHTBOX ═══════════════ -->
<div id="galleryLightbox" class="hidden fixed inset-0 z-[9999] bg-black/90 backdrop-blur-sm flex flex-col">
    <div class="flex items-center justify-between p-4 text-white md:p-6">
        <div>
            <div class="text-lg font-bold">Galerie foto</div>
            <div class="text-sm text-white/70">
                Fotografia <span id="lightboxIndex">1</span> din <span id="lightboxTotal">0</span>
            </div>
        </div>
        <button type="button" onclick="VenuePage.closeLightbox()" class="flex items-center justify-center w-10 h-10 transition rounded-lg bg-white/10 hover:bg-white/20" aria-label="Închide galeria">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>

    <div class="relative flex items-center justify-center flex-1 px-4 pb-4 md:px-16">
        <button type="button" onclick="VenuePage.lightboxPrev()" class="absolute z-10 flex items-center justify-center text-white transition -translate-y-1/2 rounded-full left-2 md:left-6 top-1/2 w-11 h-11 md:w-12 md:h-12 bg-white/10 hover:bg-white/20" aria-label="Fotografia anterioară">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
        </button>
        <img id="lightboxImage" src="" alt="" class="max-w-full max-h-[75vh] object-contain rounded-xl shadow-2xl">
        <button type="button" onclick="VenuePage.lightboxNext()" class="absolute z-10 flex items-center justify-center text-white transition -translate-y-1/2 rounded-full right-2 md:right-6 top-1/2 w-11 h-11 md:w-12 md:h-12 bg-white/10 hover:bg-white/20" aria-label="Fotografia următoare">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
        </button>
    </div>

    <div class="p-4 pb-6 md:px-6">
        <div id="lightboxThumbs" class="flex items-center max-w-5xl gap-2 pb-2 mx-auto overflow-x-auto no-scrollbar"></div>
        <div class="mt-3 text-xs text-center text-white/50">
            Folosește <kbd class="bg-white/10 px-1.5 py-0.5 rounded mx-1 font-mono">←</kbd> <kbd class="bg-white/10 px-1.5 py-0.5 rounded mx-1 font-mono">→</kbd> pentru navigare · <kbd class="bg-white/10 px-1.5 py-0.5 rounded mx-1 font-mono">Esc</kbd> pentru închidere
        </div>
    </div>
</div>

<!-- ═══════════════ CONTACT MODAL (unchanged) ═══════════════ -->
<div id="contactVenueModal" class="fixed inset-0 z-[9999] hidden items-center justify-center bg-black/60 backdrop-blur-sm" onclick="VenuePage.closeContactModal(event)">
    <div class="w-full max-w-lg mx-4 bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border">
            <div>
                <h2 class="text-lg font-bold text-secondary">Contactează locația</h2>
                <p id="contactVenueName" class="text-sm text-muted"></p>
            </div>
            <button onclick="VenuePage.closeContactModal()" class="p-2 transition-colors rounded-lg hover:bg-surface" aria-label="Închide modalul de contact">
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
