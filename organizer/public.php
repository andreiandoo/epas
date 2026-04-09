<?php
/**
 * Organizer Public Page - Ambilet Marketplace
 * Public profile page for event organizers
 * All data loaded dynamically from API
 */

require_once __DIR__ . '/../includes/config.php';

// Page configuration — updated dynamically after API load
$pageTitle = "Organizator — Ambilet";
$pageDescription = "Descoperă evenimentele acestui organizator pe Ambilet.";
$bodyClass = 'page-organizer';

// Include head
$cssBundle = 'organizer';
require_once __DIR__ . '/../includes/head.php';

// Include header
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Hero Section -->
<section class="relative h-[320px] overflow-hidden bg-gradient-to-br from-gray-900 to-gray-700">
    <div id="heroImage" class="absolute inset-0"></div>
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
            <span id="tabCountEvents" class="px-2 py-0.5 bg-white/20 rounded-full text-xs">0</span>
        </button>
        <button class="tab-btn flex-1 py-3.5 px-5 rounded-lg text-sm font-semibold flex items-center justify-center gap-2 text-gray-500 hover:text-gray-900 hover:bg-gray-50 transition-all" data-tab="past">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 20V10"/>
                <path d="M18 20V4"/>
                <path d="M6 20v-4"/>
            </svg>
            <span>Trecut</span>
            <span id="tabCountPast" class="px-2 py-0.5 bg-black/10 rounded-full text-xs">0</span>
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
            <!-- Tab: Events (Upcoming) -->
            <section id="tabContentEvents" class="tab-content">
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

                <div id="eventsGrid" class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
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

            <!-- Tab: Past Events -->
            <section id="tabContentPast" class="tab-content hidden">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                        <svg class="w-[22px] h-[22px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 20V10"/>
                            <path d="M18 20V4"/>
                            <path d="M6 20v-4"/>
                        </svg>
                        Evenimente trecute
                    </h2>
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

            <!-- Tab: About -->
            <section id="tabContentAbout" class="tab-content hidden">
                <div class="flex items-center justify-between mb-5">
                    <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2.5">
                        <svg class="w-[22px] h-[22px] text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="16" x2="12" y2="12"/>
                            <line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        Despre organizator
                    </h2>
                </div>

                <!-- About Content -->
                <div class="p-6 mb-6 bg-white border border-gray-200 rounded-2xl">
                    <p id="aboutTextFull" class="text-base leading-relaxed text-gray-600">
                        <span class="block w-full h-4 mb-2 bg-gray-200 rounded animate-pulse"></span>
                        <span class="block w-5/6 h-4 mb-2 bg-gray-200 rounded animate-pulse"></span>
                        <span class="block w-4/5 h-4 mb-2 bg-gray-200 rounded animate-pulse"></span>
                        <span class="block w-3/4 h-4 bg-gray-200 rounded animate-pulse"></span>
                    </p>
                </div>

                <!-- Quick Facts Full -->
                <div id="quickFactsFull" class="p-6 mb-6 bg-white border border-gray-200 rounded-2xl">
                    <h3 class="mb-5 text-base font-bold text-gray-900">Informații despre organizator</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="flex items-center gap-3.5 p-4 bg-gray-50 rounded-xl">
                            <div class="w-12 h-12 bg-gray-200 rounded-lg animate-pulse"></div>
                            <div class="flex-1">
                                <div class="w-16 h-3 mb-1 bg-gray-200 rounded animate-pulse"></div>
                                <div class="w-24 h-4 bg-gray-100 rounded animate-pulse"></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
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

            <!-- Contact Card -->
            <div class="p-6 bg-gradient-to-br from-gray-900 to-gray-700 rounded-2xl">
                <h3 class="mb-2 text-base font-bold text-white">Interesat de colaborare?</h3>
                <p class="mb-5 text-sm text-white/90">Contactează organizatorul pentru evenimente private sau corporate.</p>
                <button onclick="var m=document.getElementById('contactModal');m.style.display='flex'" class="w-full flex items-center justify-center gap-2 py-3.5 bg-primary rounded-lg text-white text-sm font-semibold hover:bg-primary-dark transition-colors mb-3 cursor-pointer">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Trimite mesaj
                </button>
                <a id="contactWebsite" href="#" target="_blank" class="w-full flex items-center justify-center gap-2 py-3.5 bg-white/10 border border-white/20 rounded-lg text-white text-sm font-semibold hover:bg-white/20 transition-colors hidden">
                    <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                    Website
                </a>
            </div>
        </aside>
    </div>
</main>

<!-- Contact Modal -->
<div id="contactModal" class="fixed inset-0 z-50 items-center justify-center bg-black/50 backdrop-blur-sm" style="display:none;" onclick="if(event.target===this)this.style.display='none'">
    <div class="w-full max-w-md mx-4 bg-white shadow-2xl rounded-2xl">
        <div class="flex items-center justify-between p-5 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-900">Trimite mesaj</h3>
            <button onclick="document.getElementById('contactModal').style.display='none'" class="p-1 text-gray-400 transition-colors hover:text-gray-600">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <form id="contactForm" class="p-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Prenume *</label>
                    <input type="text" name="first_name" required class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="Prenumele tău">
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-gray-700">Nume *</label>
                    <input type="text" name="last_name" required class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="Numele tău">
                </div>
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">Email *</label>
                <input type="email" name="email" required class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="email@exemplu.ro">
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">Telefon</label>
                <input type="tel" name="phone" class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none" placeholder="07xx xxx xxx">
            </div>
            <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">Mesaj *</label>
                <textarea name="message" required rows="4" class="w-full px-3 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none resize-none" placeholder="Scrie mesajul tău aici..."></textarea>
            </div>
            <div id="contactFormMessage" class="hidden px-4 py-3 text-sm rounded-lg"></div>
            <button type="submit" id="contactSubmitBtn" class="w-full py-3 text-sm font-semibold text-white transition-colors rounded-lg bg-primary hover:bg-primary-dark">
                Trimite mesajul
            </button>
        </form>
    </div>
</div>

<?php
// Include footer
require_once __DIR__ . '/../includes/footer.php';

// Page-specific scripts (defined before scripts.php include so AmbiletAPI is loaded first)
$scriptsExtra = '';
require_once __DIR__ . '/../includes/scripts.php';
?>
<script>
const OrganizerPage = {
    init() {
        this.loadOrganizerData();
        this.initTabs();
    },

    async loadOrganizerData() {
        try {
            const slug = window.location.pathname.split('/').pop();
            const response = await AmbiletAPI.get('/marketplace-events/organizers/' + slug);
            if (response.success && response.data) {
                this.renderOrganizer(response.data);
                return;
            }
        } catch (e) {
            console.error('Failed to load organizer data:', e);
        }
        this.renderEmptyState();
    },

    renderEmptyState() {
        document.getElementById('profileCard').innerHTML = `
            <div class="py-12 text-center col-span-full">
                <p class="text-lg font-medium text-gray-500">Datele organizatorului nu sunt disponibile momentan.</p>
            </div>
        `;
        document.getElementById('eventsGrid').innerHTML = '<p class="py-8 text-center text-gray-400 col-span-2">Nu sunt evenimente disponibile.</p>';
        document.getElementById('pastEventsGrid').innerHTML = '';
    },

    renderOrganizer(data) {
        // Update page title
        document.title = data.name + ' — Ambilet';

        // Hero image: prefer cover_image, fallback to first event image, else keep gradient
        if (data.cover_image) {
            document.getElementById('heroImage').innerHTML = `<img src="${data.cover_image}" alt="${data.name}" class="absolute inset-0 object-cover object-center w-full h-full">`;
        } else if (data.upcomingEvents && data.upcomingEvents.length > 0 && data.upcomingEvents[0].image) {
            document.getElementById('heroImage').innerHTML = `<img src="${data.upcomingEvents[0].image}" alt="${data.name}" class="absolute inset-0 object-cover object-center w-full h-full">`;
        }

        // Profile Card
        document.getElementById('profileCard').innerHTML = `
            <div class="w-[140px] h-[140px] rounded-[20px] overflow-hidden flex-shrink-0 border-4 border-white shadow-lg ${data.avatar ? '' : 'bg-gradient-to-br from-primary to-primary-dark flex items-center justify-center'}">
                ${data.avatar
                    ? `<img src="${data.avatar}" alt="${data.name}" class="object-cover w-full h-full">`
                    : `<span class="text-5xl font-bold text-white">${data.name.charAt(0).toUpperCase()}</span>`
                }
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
                ${data.tagline ? `<p class="mb-4 text-base text-gray-500">${data.tagline}</p>` : ''}
                <div class="flex items-center gap-1.5 text-sm text-gray-500 mb-5">
                    <svg class="w-[18px] h-[18px] text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    ${data.location || 'România'}
                </div>
                <div class="flex gap-8">
                    ${data.stats.followers !== '0' ? `
                    <div class="text-center">
                        <div class="text-2xl font-extrabold text-gray-900">${data.stats.followers}</div>
                        <div class="text-[13px] text-gray-500">Urmăritori</div>
                    </div>` : ''}
                    ${data.stats.rating !== '-' ? `
                    <div class="text-center">
                        <div class="text-2xl font-extrabold text-gray-900">${data.stats.rating}</div>
                        <div class="text-[13px] text-gray-500">Rating</div>
                    </div>` : ''}
                </div>
            </div>
            <div class="flex flex-col items-center w-full gap-3 md:items-end md:w-auto">
                <div class="flex gap-2">
                    ${data.social.facebook ? `
                    <a href="${data.social.facebook}" target="_blank" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-colors bg-gray-100 rounded-lg hover:bg-gray-900 hover:text-white">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                        </svg>
                    </a>` : ''}
                    ${data.social.instagram ? `
                    <a href="${data.social.instagram}" target="_blank" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-colors bg-gray-100 rounded-lg hover:bg-gray-900 hover:text-white">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="2" width="20" height="20" rx="5" ry="5"/>
                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                            <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                        </svg>
                    </a>` : ''}
                    ${data.social.website ? `
                    <a href="${data.social.website}" target="_blank" class="flex items-center justify-center w-10 h-10 text-gray-500 transition-colors bg-gray-100 rounded-lg hover:bg-gray-900 hover:text-white">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="2" y1="12" x2="22" y2="12"/>
                            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        </svg>
                    </a>` : ''}
                </div>
            </div>
        `;

        // Upcoming Events — use shared vertical card component (3 per row)
        if (data.upcomingEvents && data.upcomingEvents.length > 0) {
            document.getElementById('eventsGrid').innerHTML = AmbiletEventCard.renderMany(data.upcomingEvents, { columns: 3 });
        } else {
            document.getElementById('eventsGrid').innerHTML = '<p class="py-8 text-center text-gray-400 col-span-3">Nu sunt evenimente viitoare momentan.</p>';
        }

        // Past Events — horizontal cards, no link
        if (data.pastEvents && data.pastEvents.length > 0) {
            document.getElementById('pastEventsGrid').innerHTML = AmbiletEventCard.renderManyHorizontal(data.pastEvents, { showBuyButton: false, showPrice: false, showTime: false });
        } else {
            document.getElementById('pastEventsGrid').innerHTML = '<p class="py-8 text-center text-gray-400">Nu sunt evenimente trecute.</p>';
        }

        // Update tab counts
        const upCount = data.upcomingEvents?.length || 0;
        const pastCount = data.pastEvents?.length || 0;
        document.getElementById('tabCountEvents').textContent = upCount;
        document.getElementById('tabCountPast').textContent = pastCount;

        // About text
        document.getElementById('aboutText').textContent = data.about || 'Informații indisponibile momentan.';
        document.getElementById('aboutTextFull').textContent = data.about || 'Informații indisponibile momentan.';

        // Quick Facts (sidebar)
        const iconMap = {
            calendar: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            location: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            star: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            shield: '<svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
        };
        const iconMapLg = {
            calendar: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            location: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            star: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
            shield: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
        };

        if (data.facts && data.facts.length > 0) {
            document.getElementById('quickFacts').innerHTML = `
                <h3 class="mb-4 text-base font-bold text-gray-900">Informații rapide</h3>
                ${data.facts.map(fact => `
                    <div class="flex items-center gap-3.5 py-3 border-b border-gray-100 last:border-0">
                        <div class="flex items-center justify-center w-10 h-10 text-gray-500 rounded-lg bg-gray-50">
                            ${iconMap[fact.icon] || ''}
                        </div>
                        <div class="flex-1">
                            <div class="text-xs text-gray-400 mb-0.5">${fact.label}</div>
                            <div class="text-sm font-semibold text-gray-900">${fact.value}</div>
                        </div>
                    </div>
                `).join('')}
            `;

            // Quick Facts Full (About tab)
            document.getElementById('quickFactsFull').innerHTML = `
                <h3 class="mb-5 text-base font-bold text-gray-900">Informații despre organizator</h3>
                <div class="grid gap-4 md:grid-cols-2">
                    ${data.facts.map(fact => `
                        <div class="flex items-center gap-3.5 p-4 bg-gray-50 rounded-xl">
                            <div class="flex items-center justify-center w-12 h-12 text-primary rounded-lg bg-primary/10">
                                ${iconMapLg[fact.icon] || ''}
                            </div>
                            <div class="flex-1">
                                <div class="text-xs text-gray-400 mb-0.5">${fact.label}</div>
                                <div class="text-sm font-semibold text-gray-900">${fact.value}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
        }

        // Update tab counts
        document.getElementById('tabCountEvents').textContent = data.upcomingEvents ? data.upcomingEvents.length : 0;
        document.getElementById('tabCountPast').textContent = data.pastEvents ? data.pastEvents.length : 0;

        // Contact card - update links
        if (data.social && data.social.website) {
            var websiteBtn = document.getElementById('contactWebsite');
            websiteBtn.href = data.social.website;
            websiteBtn.classList.remove('hidden');
        }
    },

    initTabs() {
        const tabs = document.querySelectorAll('.tab-btn');
        const contents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // Update tab buttons styling
                tabs.forEach(t => {
                    t.classList.remove('bg-primary', 'text-white');
                    t.classList.add('text-gray-500');
                    const badge = t.querySelector('span[id^="tabCount"]');
                    if (badge) {
                        badge.classList.remove('bg-white/20');
                        badge.classList.add('bg-black/10');
                    }
                });
                tab.classList.remove('text-gray-500');
                tab.classList.add('bg-primary', 'text-white');
                const activeBadge = tab.querySelector('span[id^="tabCount"]');
                if (activeBadge) {
                    activeBadge.classList.remove('bg-black/10');
                    activeBadge.classList.add('bg-white/20');
                }

                // Show/hide content sections
                contents.forEach(content => content.classList.add('hidden'));
                const targetContent = document.getElementById(`tabContent${targetTab.charAt(0).toUpperCase() + targetTab.slice(1)}`);
                if (targetContent) {
                    targetContent.classList.remove('hidden');
                }
            });
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    OrganizerPage.init();

    // Contact form submit
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('contactSubmitBtn');
            const msg = document.getElementById('contactFormMessage');
            const slug = window.location.pathname.split('/').pop();

            btn.disabled = true;
            btn.textContent = 'Se trimite...';
            msg.className = 'hidden';

            try {
                const fd = new FormData(contactForm);
                const payload = Object.fromEntries(fd.entries());
                await AmbiletAPI.post('/marketplace-events/organizers/' + slug + '/contact', payload);

                msg.textContent = 'Mesajul a fost trimis cu succes! Organizatorul va reveni cu un răspuns.';
                msg.className = 'px-4 py-3 text-sm rounded-lg bg-green-50 text-green-700';
                contactForm.reset();
                setTimeout(() => { document.getElementById('contactModal').style.display = 'none'; msg.className = 'hidden'; }, 3000);
            } catch (err) {
                msg.textContent = err.message || 'A apărut o eroare. Încearcă din nou.';
                msg.className = 'px-4 py-3 text-sm rounded-lg bg-red-50 text-red-700';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Trimite mesajul';
            }
        });
    }
});
</script>
