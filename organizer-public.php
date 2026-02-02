<?php
/**
 * Organizer Public Page - Ambilet Marketplace
 * Public profile page for event organizers
 */

require_once __DIR__ . '/includes/config.php';

// Page configuration
$pageTitle = "Events Pro Romania — Organizator";
$pageDescription = "Descoperă evenimentele organizate de Events Pro Romania. Concerte, festivaluri și experiențe de neuitat.";
$bodyClass = 'page-organizer';

// Include head
require_once __DIR__ . '/includes/head.php';

// Include header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="relative h-[320px] overflow-hidden">
    <img id="heroImage" src="https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=1920&h=600&fit=crop" alt="Events Pro Romania" class="absolute inset-0 object-cover object-center w-full h-full">
    <div class="absolute inset-0 bg-gradient-to-b from-black/40 to-black/70"></div>
</section>

<!-- Profile Section -->
<section class="relative z-10 px-6 mx-auto -mt-24 max-w-7xl">
    <div id="profileCard" class="bg-white rounded-[20px] p-8 shadow-xl flex flex-col md:flex-row gap-8 items-start">
        <!-- Skeleton -->
        <div class="w-[140px] h-[140px] rounded-[20px] bg-gray-200 animate-pulse flex-shrink-0 border-4 border-white shadow-lg"></div>
        <div class="flex-1">
            <div class="w-32 h-6 mb-3 bg-gray-200 rounded animate-pulse"></div>
            <div class="w-64 h-8 mb-2 bg-gray-200 rounded animate-pulse"></div>
            <div class="w-full h-4 max-w-md mb-4 bg-gray-100 rounded animate-pulse"></div>
            <div class="w-48 h-4 mb-5 bg-gray-100 rounded animate-pulse"></div>
            <div class="flex gap-8">
                <div class="w-16 h-12 bg-gray-200 rounded animate-pulse"></div>
                <div class="w-16 h-12 bg-gray-200 rounded animate-pulse"></div>
                <div class="w-16 h-12 bg-gray-200 rounded animate-pulse"></div>
                <div class="w-16 h-12 bg-gray-200 rounded animate-pulse"></div>
            </div>
        </div>
        <div class="flex flex-col items-center w-full gap-3 md:items-end md:w-auto">
            <div class="w-40 h-12 bg-gray-200 rounded-xl animate-pulse"></div>
            <div class="flex gap-2">
                <div class="w-10 h-10 bg-gray-200 rounded-lg animate-pulse"></div>
                <div class="w-10 h-10 bg-gray-200 rounded-lg animate-pulse"></div>
                <div class="w-10 h-10 bg-gray-200 rounded-lg animate-pulse"></div>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="px-6 py-10 mx-auto max-w-7xl">
    <!-- Tabs -->
    <div class="flex gap-1 bg-white p-1.5 rounded-[14px] border border-gray-200 mb-8">
        <button class="tab-btn flex-1 py-3.5 px-5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 transition-all bg-primary text-white" data-tab="events">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/>
                <line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            <span>Evenimente</span>
            <span class="px-2 py-0.5 bg-white/20 rounded-full text-xs">12</span>
        </button>
        <button class="tab-btn flex-1 py-3.5 px-5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-all" data-tab="past">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20V10"/>
                <path d="M18 20V4"/>
                <path d="M6 20v-4"/>
            </svg>
            <span>Trecut</span>
            <span class="px-2 py-0.5 bg-black/10 rounded-full text-xs">144</span>
        </button>
        <button class="tab-btn flex-1 py-3.5 px-5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-all" data-tab="reviews">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <span>Recenzii</span>
            <span class="px-2 py-0.5 bg-black/10 rounded-full text-xs">324</span>
        </button>
        <button class="tab-btn flex-1 py-3.5 px-5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-all" data-tab="about">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="16" x2="12" y2="12"/>
                <line x1="12" y1="8" x2="12.01" y2="8"/>
            </svg>
            <span>Despre</span>
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-8">
        <!-- Main Content -->
        <div>
            <!-- Upcoming Events -->
            <section class="mb-10">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                        <svg class="w-[22px] h-[22px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        Evenimente viitoare
                    </h2>
                </div>

                <div id="eventsGrid" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <!-- Skeleton events -->
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="overflow-hidden bg-white border border-gray-200 rounded-2xl">
                        <div class="relative bg-gray-200 aspect-video animate-pulse">
                            <div class="absolute w-12 bg-white rounded-lg top-3 left-3 h-14"></div>
                        </div>
                        <div class="p-5">
                            <div class="w-16 h-3 mb-2 bg-gray-200 rounded animate-pulse"></div>
                            <div class="w-3/4 h-5 mb-3 bg-gray-200 rounded animate-pulse"></div>
                            <div class="w-1/2 h-4 mb-4 bg-gray-100 rounded animate-pulse"></div>
                            <div class="flex justify-between pt-4 border-t border-gray-100">
                                <div class="w-20 h-5 bg-gray-200 rounded animate-pulse"></div>
                                <div class="w-20 bg-gray-200 rounded-lg h-9 animate-pulse"></div>
                            </div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </section>

            <!-- Past Events -->
            <section>
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                        <svg class="w-[22px] h-[22px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20V10"/>
                            <path d="M18 20V4"/>
                            <path d="M6 20v-4"/>
                        </svg>
                        Evenimente trecute
                    </h2>
                    <a href="#" class="flex items-center gap-1 text-sm font-semibold transition-all text-primary hover:gap-2">
                        Vezi toate (144)
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>

                <div id="pastEventsGrid" class="flex flex-col gap-3">
                    <!-- Skeleton past events -->
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="flex items-center gap-4 p-4 bg-white rounded-[14px] border border-gray-200">
                        <div class="flex-shrink-0 w-20 h-20 bg-gray-200 rounded-xl animate-pulse"></div>
                        <div class="flex-1">
                            <div class="w-24 h-3 mb-1 bg-gray-200 rounded animate-pulse"></div>
                            <div class="w-48 h-5 mb-2 bg-gray-200 rounded animate-pulse"></div>
                            <div class="h-4 bg-gray-100 rounded w-36 animate-pulse"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </section>
        </div>

        <!-- Sidebar -->
        <aside class="flex flex-col gap-6">
            <!-- About -->
            <div class="p-6 bg-white border border-gray-200 rounded-2xl">
                <h3 class="flex items-center gap-2 mb-4 text-base font-bold text-gray-900">
                    <svg class="w-5 h-5 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="16" x2="12" y2="12"/>
                        <line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    Despre organizator
                </h3>
                <p id="aboutText" class="text-sm leading-relaxed text-gray-600">
                    <span class="block w-full h-4 mb-2 bg-gray-200 rounded animate-pulse"></span>
                    <span class="block w-5/6 h-4 mb-2 bg-gray-200 rounded animate-pulse"></span>
                    <span class="block w-4/5 h-4 bg-gray-200 rounded animate-pulse"></span>
                </p>
            </div>

            <!-- Quick Facts -->
            <div id="quickFacts" class="p-6 bg-white border border-gray-200 rounded-2xl">
                <h3 class="mb-4 text-base font-bold text-gray-900">Informații rapide</h3>
                <div class="space-y-3">
                    <?php for ($i = 0; $i < 4; $i++): ?>
                    <div class="flex items-center gap-3.5 py-3 border-b border-gray-100 last:border-0">
                        <div class="w-10 h-10 bg-gray-100 rounded-lg animate-pulse"></div>
                        <div class="flex-1">
                            <div class="w-16 h-3 mb-1 bg-gray-200 rounded animate-pulse"></div>
                            <div class="w-24 h-4 bg-gray-100 rounded animate-pulse"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Reviews Summary -->
            <div id="reviewsCard" class="p-6 bg-white border border-gray-200 rounded-2xl">
                <div class="flex items-center gap-4 mb-5">
                    <div class="text-center">
                        <div class="text-4xl font-extrabold leading-none text-gray-900">4.9</div>
                        <div class="flex gap-0.5 mt-1">
                            <?php for ($i = 0; $i < 5; $i++): ?>
                            <svg class="w-4 h-4 fill-amber-400 text-amber-400" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-gray-900">324 recenzii</div>
                        <div class="text-[13px] text-gray-500">Bazat pe evenimente participante</div>
                    </div>
                </div>

                <!-- Rating bars -->
                <div class="space-y-2">
                    <?php
                    $ratings = [['5', 85, 275], ['4', 12, 39], ['3', 2, 7], ['2', 0.5, 2], ['1', 0.3, 1]];
                    foreach ($ratings as $rating): ?>
                    <div class="flex items-center gap-2.5">
                        <span class="w-4 text-xs text-center text-gray-500"><?= $rating[0] ?></span>
                        <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-amber-400" style="width: <?= $rating[1] ?>%"></div>
                        </div>
                        <span class="w-8 text-xs text-right text-gray-400"><?= $rating[2] ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Featured Review -->
                <div class="p-4 mt-4 bg-gray-50 rounded-xl">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 overflow-hidden rounded-full">
                            <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&h=80&fit=crop" alt="Maria D." class="object-cover w-full h-full">
                        </div>
                        <div class="flex-1">
                            <div class="text-sm font-semibold text-gray-900">Maria D.</div>
                            <div class="text-xs text-gray-400">Summer Fest 2024</div>
                        </div>
                    </div>
                    <p class="text-[13px] leading-relaxed text-gray-600">"Organizare impecabilă! Am fost la multe festivaluri, dar acesta a fost de departe cel mai bine organizat. Recomand cu încredere!"</p>
                </div>
            </div>

            <!-- Contact Card -->
            <div class="p-6 bg-gradient-to-br from-gray-900 to-gray-700 rounded-2xl">
                <h3 class="mb-2 text-base font-bold text-white">Interesat de colaborare?</h3>
                <p class="mb-5 text-sm text-white/70">Contactează organizatorul pentru evenimente private sau corporate.</p>
                <button class="w-full flex items-center justify-center gap-2 py-3.5 bg-primary rounded-lg text-white text-sm font-semibold hover:bg-primary-dark transition-colors mb-3">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Trimite mesaj
                </button>
                <button class="w-full flex items-center justify-center gap-2 py-3.5 bg-white/10 border border-white/20 rounded-lg text-white text-sm font-semibold hover:bg-white/20 transition-colors">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    Solicită ofertă
                </button>
            </div>
        </aside>
    </div>
</main>

<?php
// Include footer
require_once __DIR__ . '/includes/footer.php';

// Page-specific scripts
$scriptsExtra = <<<'SCRIPTS'
<script>
const OrganizerPage = {
    init() {
        this.loadOrganizerData();
        this.initTabs();
    },

    async loadOrganizerData() {
        // TODO: API integration needed - fetch organizer data from API
        try {
            const slug = window.location.pathname.split('/').pop();
            const response = await AmbiletAPI.get('/api/proxy.php?action=organizer&slug=' + slug);
            if (response.success && response.data) {
                this.renderOrganizer(response.data);
                return;
            }
        } catch (e) {
            console.error('Failed to load organizer data:', e);
        }
        // Show empty state when no data available
        document.getElementById('profileCard').innerHTML = `
            <div class="py-12 text-center col-span-full">
                <p class="text-lg font-medium text-gray-500">Datele organizatorului nu sunt disponibile momentan.</p>
            </div>
        `;
        document.getElementById('eventsGrid').innerHTML = '';
        document.getElementById('pastEventsGrid').innerHTML = '';
    },

    renderOrganizer(data) {
        // Profile Card
        document.getElementById('profileCard').innerHTML = `
            <div class="w-[140px] h-[140px] rounded-[20px] overflow-hidden flex-shrink-0 border-4 border-white shadow-lg">
                <img src="${data.avatar}" alt="${data.name}" class="object-cover w-full h-full">
            </div>
            <div class="flex-1">
                <div class="flex gap-2 mb-3">
                    ${data.verified ? `
                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-100 text-blue-700 rounded-full text-xs font-semibold">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                            </svg>
                            Verificat
                        </span>
                    ` : ''}
                    ${data.pro ? `<span class="px-3 py-1.5 bg-gradient-to-r from-primary to-primary-light text-white rounded-full text-xs font-semibold">PRO</span>` : ''}
                </div>
                <h1 class="text-[32px] font-extrabold text-gray-900 mb-2">${data.name}</h1>
                <p class="mb-4 text-base text-gray-500">${data.tagline}</p>
                <div class="flex items-center gap-1.5 text-sm text-gray-500 mb-5">
                    <svg class="w-[18px] h-[18px] text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    ${data.location}
                </div>
                <div class="flex gap-8">
                    <div class="text-center">
                        <div class="text-2xl font-extrabold text-gray-900">${data.stats.events}</div>
                        <div class="text-[13px] text-gray-500">Evenimente</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-extrabold text-gray-900">${data.stats.tickets}</div>
                        <div class="text-[13px] text-gray-500">Bilete vândute</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-extrabold text-gray-900">${data.stats.followers}</div>
                        <div class="text-[13px] text-gray-500">Urmăritori</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-extrabold text-gray-900">${data.stats.rating}</div>
                        <div class="text-[13px] text-gray-500">Rating</div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col items-center w-full gap-3 md:items-end md:w-auto">
                <button class="flex items-center gap-2 px-8 py-3.5 bg-gradient-to-r from-primary to-primary-light rounded-xl text-white font-semibold shadow-lg shadow-primary/25 hover:-translate-y-0.5 hover:shadow-xl transition-all">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                    </svg>
                    Urmărește
                </button>
                <div class="flex gap-2">
                    <a href="${data.social.facebook}" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-colors bg-gray-100 rounded-lg hover:bg-gray-900 hover:text-white">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>
                    <a href="${data.social.instagram}" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-colors bg-gray-100 rounded-lg hover:bg-gray-900 hover:text-white">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>
                    <a href="${data.social.website}" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-colors bg-gray-100 rounded-lg hover:bg-gray-900 hover:text-white">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                    </a>
                </div>
            </div>
        `;

        // Upcoming Events
        document.getElementById('eventsGrid').innerHTML = data.upcomingEvents.map(event => `
            <a href="/event/${AmbiletUtils.slugify(event.title)}" class="overflow-hidden transition-all bg-white border border-gray-200 rounded-2xl hover:-translate-y-1 hover:shadow-xl hover:border-primary group">
                <div class="relative overflow-hidden aspect-video">
                    <img src="${event.image}" alt="${event.title}" class="object-cover w-full h-full transition-transform duration-500 group-hover:scale-105" loading="lazy">
                    <div class="absolute p-2 px-3 text-center bg-white rounded-lg shadow-md top-3 left-3">
                        <div class="text-xl font-extrabold leading-none text-primary">${event.day}</div>
                        <div class="text-[11px] font-semibold text-gray-500 uppercase">${event.month}</div>
                    </div>
                    ${event.status === 'soon' ? `<span class="absolute top-3 right-3 px-3 py-1.5 bg-amber-500 text-white text-[11px] font-bold uppercase rounded-md">Curând</span>` : ''}
                    ${event.status === 'soldout' ? `<span class="absolute top-3 right-3 px-3 py-1.5 bg-gray-500 text-white text-[11px] font-bold uppercase rounded-md">Sold Out</span>` : ''}
                </div>
                <div class="p-5">
                    <div class="text-[11px] font-semibold text-primary uppercase tracking-wide mb-1.5">${event.category}</div>
                    <h3 class="text-[17px] font-bold text-gray-900 mb-2.5 leading-tight">${event.title}</h3>
                    <div class="flex flex-wrap gap-3 text-[13px] text-gray-500 mb-4">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                            ${event.venue}
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            ${event.time}
                        </span>
                    </div>
                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <div class="text-[13px] text-gray-500">de la <strong class="text-lg font-bold text-emerald-500">${event.price} lei</strong></div>
                        <button class="px-5 py-2.5 bg-gray-900 rounded-lg text-white text-[13px] font-semibold hover:bg-gray-800 transition-colors ${event.status === 'soldout' ? 'opacity-50 cursor-not-allowed' : ''}" ${event.status === 'soldout' ? 'disabled' : ''}>
                            ${event.status === 'soldout' ? 'Epuizat' : 'Cumpără'}
                        </button>
                    </div>
                </div>
            </a>
        `).join('');

        // Past Events
        document.getElementById('pastEventsGrid').innerHTML = data.pastEvents.map(event => `
            <a href="#" class="flex items-center gap-4 p-4 bg-white rounded-[14px] border border-gray-200 hover:border-gray-300 hover:shadow-md transition-all">
                <div class="flex-shrink-0 w-20 h-20 overflow-hidden rounded-xl">
                    <img src="${event.image}" alt="${event.title}" class="object-cover w-full h-full" loading="lazy">
                </div>
                <div class="flex-1">
                    <div class="mb-1 text-xs text-gray-400">${event.date}</div>
                    <h3 class="text-[15px] font-bold text-gray-900 mb-1.5">${event.title}</h3>
                    <div class="flex gap-4 text-[13px] text-gray-500">
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                            </svg>
                            ${event.participants} participanți
                        </span>
                        <span class="flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                            ${event.rating} rating
                        </span>
                    </div>
                </div>
            </a>
        `).join('');

        // About
        document.getElementById('aboutText').textContent = data.about;

        // Quick Facts
        const iconMap = {
            calendar: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            location: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            star: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            shield: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
        };

        document.getElementById('quickFacts').innerHTML = `
            <h3 class="mb-4 text-base font-bold text-gray-900">Informații rapide</h3>
            ${data.facts.map(fact => `
                <div class="flex items-center gap-3.5 py-3 border-b border-gray-100 last:border-0">
                    <div class="flex items-center justify-center w-10 h-10 text-gray-500 rounded-lg bg-gray-50">
                        ${iconMap[fact.icon]}
                    </div>
                    <div class="flex-1">
                        <div class="text-xs text-gray-400 mb-0.5">${fact.label}</div>
                        <div class="text-sm font-semibold text-gray-900">${fact.value}</div>
                    </div>
                </div>
            `).join('')}
        `;
    },

    initTabs() {
        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => {
                    t.classList.remove('bg-primary', 'text-white');
                    t.classList.add('text-gray-500');
                });
                tab.classList.remove('text-gray-500');
                tab.classList.add('bg-primary', 'text-white');
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => OrganizerPage.init());
</script>
SCRIPTS;

require_once __DIR__ . '/includes/scripts.php';
?>
