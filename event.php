<?php
require_once __DIR__ . '/includes/config.php';

$eventSlug = $_GET['slug'] ?? '';
$pageTitle = 'Eveniment';
$pageDescription = 'Detalii eveniment si cumparare bilete';
$bodyClass = 'bg-surface';

require_once __DIR__ . '/includes/head.php';
?>
    <style>
        .date-badge { background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%); }
        .btn-primary { background: linear-gradient(135deg, #A51C30 0%, #8B1728 100%); transition: all 0.3s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(165, 28, 48, 0.3); }

        .ticket-card { transition: all 0.3s ease; }
        .ticket-card:hover { border-color: #A51C30; }
        .ticket-card.selected { border-color: #A51C30; background-color: rgba(165, 28, 48, 0.05); }

        .tooltip {
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            transform: translateY(5px);
        }
        .tooltip-trigger:hover .tooltip {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .event-card { transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
        .event-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(165, 28, 48, 0.2); }
        .event-card:hover .event-image { transform: scale(1.08); }
        .event-image { transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); }

        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

        .points-counter { animation: pointsPulse 0.3s ease; }
        @keyframes pointsPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .discount-badge { background: linear-gradient(135deg, #10B981 0%, #059669 100%); }

        .sticky-cart { position: sticky; top: 88px; }

        .gallery-thumb { transition: all 0.2s ease; }
        .gallery-thumb:hover, .gallery-thumb.active { border-color: #A51C30; opacity: 1; }
    </style>

<?php require_once __DIR__ . '/includes/header.php'; ?>

    <!-- Breadcrumb -->
    <div class="bg-white border-b border-border mt-28 mobile:mt-22">
        <div class="px-4 py-3 mx-auto max-w-7xl">
            <nav class="flex items-center gap-2 text-sm" id="breadcrumb">
                <a href="/" class="transition-colors text-muted hover:text-primary">Acasa</a>
                <svg class="w-4 h-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-medium text-secondary" id="breadcrumb-title">Se incarca...</span>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <main class="px-4 py-8 mx-auto max-w-7xl" id="main-content">
        <!-- Loading State -->
        <div id="loading-state" class="flex flex-col gap-8 lg:flex-row">
            <div class="lg:w-2/3">
                <div class="mb-8 overflow-hidden bg-white border rounded-3xl border-border">
                    <div class="bg-gray-200 animate-pulse h-72 md:h-[29rem]"></div>
                    <div class="p-6 md:p-8">
                        <div class="w-3/4 h-10 mb-4 bg-gray-200 rounded animate-pulse"></div>
                        <div class="grid gap-4 mb-6 sm:grid-cols-2">
                            <div class="h-24 bg-gray-100 animate-pulse rounded-xl"></div>
                            <div class="h-24 bg-gray-100 animate-pulse rounded-xl"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="lg:w-1/3">
                <div class="bg-gray-100 animate-pulse h-96 rounded-3xl"></div>
            </div>
        </div>

        <!-- Event Content (hidden until loaded) -->
        <div id="event-content" class="flex flex-col hidden gap-8 lg:flex-row">
            <!-- Left Column - Event Details -->
            <div class="lg:w-2/3">
                <!-- Event Header -->
                <div class="mb-8 overflow-hidden bg-white border rounded-3xl border-border">
                    <!-- Main Image -->
                    <div class="relative overflow-hidden h-72 md:h-96">
                        <img id="mainImage" src="" alt="" class="object-cover w-full h-full">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                        <div class="absolute flex gap-2 top-4 left-4" id="event-badges"></div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <div class="flex gap-2" id="gallery-thumbs"></div>
                        </div>
                    </div>

                    <!-- Event Info -->
                    <div class="p-6 md:p-8">
                        <h1 id="event-title" class="mb-4 text-3xl font-extrabold md:text-4xl text-secondary"></h1>

                        <!-- Key Details -->
                        <div class="grid gap-4 mb-6 sm:grid-cols-2">
                            <div class="flex items-start gap-3 p-4 bg-surface rounded-xl">
                                <div class="flex-shrink-0 px-3 py-2 text-center text-white date-badge rounded-xl">
                                    <span id="event-day" class="block text-xl font-bold leading-none">--</span>
                                    <span id="event-month" class="block text-[10px] uppercase tracking-wide mt-0.5">---</span>
                                </div>
                                <div>
                                    <p id="event-weekday" class="font-semibold text-secondary"></p>
                                    <p id="event-date-full" class="text-sm text-muted"></p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 p-4 bg-surface rounded-xl">
                                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl">
                                    <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <div>
                                    <p id="event-time" class="font-semibold text-secondary"></p>
                                    <p id="event-doors" class="text-sm text-muted"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Location -->
                        <div class="flex items-center gap-3 p-4 mb-6 bg-surface rounded-xl">
                            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-primary/10 rounded-xl">
                                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            </div>
                            <div class="flex-1">
                                <p id="venue-name" class="font-semibold text-secondary"></p>
                                <p id="venue-address" class="text-sm text-muted"></p>
                            </div>
                            <a href="#venue" class="text-sm font-semibold text-primary hover:underline">Vezi locatia &rarr;</a>
                        </div>

                        <!-- Social Stats -->
                        <div class="flex flex-wrap items-center gap-4">
                            <!-- Interested Button -->
                            <button id="interest-btn" onclick="EventPage.toggleInterest()" class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium transition-all rounded-full border border-border hover:border-primary hover:text-primary">
                                <svg id="interest-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                                <span id="event-interested">0 interesati</span>
                            </button>
                            <!-- Views -->
                            <span class="flex items-center gap-2 text-sm text-muted">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                <span id="event-views">0 vizualizari</span>
                            </span>
                            <!-- Share Dropdown -->
                            <div class="relative" id="share-dropdown">
                                <button onclick="EventPage.toggleShareMenu()" class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium transition-colors rounded-full border border-border hover:border-primary hover:text-primary">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                                    <span>Distribuie</span>
                                </button>
                                <div id="share-menu" class="absolute right-0 z-50 hidden w-48 py-2 mt-2 bg-white border shadow-lg rounded-xl border-border">
                                    <a href="#" onclick="EventPage.shareOn('facebook'); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-[#1877F2]" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/></svg>
                                        Facebook
                                    </a>
                                    <a href="#" onclick="EventPage.shareOn('whatsapp'); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-[#25D366]" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                        WhatsApp
                                    </a>
                                    <a href="#" onclick="EventPage.shareOn('email'); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        Email
                                    </a>
                                    <a href="#" onclick="EventPage.copyLink(); return false;" class="flex items-center gap-3 px-4 py-2 text-sm transition-colors hover:bg-surface">
                                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                        Copiaza link
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div id="event-description" class="prose prose-slate max-w-none"></div>
                    </div>
                </div>

                <!-- Artist Section -->
                <div class="mb-8" id="artist-section" style="display:none;">
                    <div id="artist-content"></div>
                </div>

                <!-- Venue Section -->
                <div class="mb-8" id="venue">
                    <div id="venue-content"></div>
                </div>
            </div>

            <!-- Right Column - Ticket Selection -->
            <div class="lg:w-1/3">
                <div class="sticky-cart">
                    <div class="overflow-hidden bg-white border rounded-3xl border-border">
                        <div class="p-6 border-b border-border">
                            <h2 class="mb-2 text-xl font-bold text-secondary">Selecteaza bilete</h2>
                            <p class="text-sm text-muted">Alege tipul de bilet si cantitatea</p>
                        </div>

                        <!-- Ticket Types -->
                        <div class="p-4 space-y-2" id="ticket-types"></div>

                        <!-- Cart Summary -->
                        <div id="cartSummary" class="hidden border-t border-border">
                            <div class="p-4 bg-surface/50">
                                <!-- Points Earned -->
                                <div class="flex items-center justify-between p-3 mb-4 bg-accent/10 rounded-xl">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xl">🎁</span>
                                        <span class="text-sm font-medium text-secondary">Puncte castigate</span>
                                    </div>
                                    <span id="pointsEarned" class="text-lg font-bold text-accent points-counter">0</span>
                                </div>

                                <!-- Summary -->
                                <div class="mb-4 space-y-2">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-muted">Subtotal:</span>
                                        <span id="subtotal" class="font-medium">0 lei</span>
                                    </div>
                                    <!-- Dynamic taxes container -->
                                    <div id="taxesContainer" class="space-y-1">
                                        <!-- Taxes will be rendered here dynamically -->
                                    </div>
                                    <div class="flex justify-between pt-2 text-lg font-bold border-t border-border">
                                        <span>Total:</span>
                                        <span id="totalPrice" class="text-primary">0 lei</span>
                                    </div>
                                </div>

                                <button id="checkoutBtn" onclick="EventPage.addToCart()" class="flex items-center justify-center w-full gap-2 py-4 text-lg font-bold text-white btn-primary rounded-xl">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                    Cumpara bilete
                                </button>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div id="emptyCart" class="p-4 text-center border-t border-border">
                            <p class="text-sm text-muted">Selecteaza cel putin un bilet pentru a continua</p>
                        </div>
                    </div>

                    <!-- Trust Badges -->
                    <div class="p-4 mt-4 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-center gap-6">
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                Plata securizata
                            </div>
                            <div class="flex items-center gap-2 text-xs text-muted">
                                <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Livrare instant
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Events -->
        <section class="mt-16" id="related-events-section" style="display:none;">
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-secondary">Alte evenimente care ti-ar putea placea</h2>
                    <p class="mt-1 text-muted" id="related-category-text">Evenimente similare</p>
                </div>
                <a href="/evenimente" id="see-all-link" class="items-center hidden gap-2 font-semibold md:flex text-primary hover:underline">
                    Vezi toate
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" id="related-events"></div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = <<<'JS'
<script>
const EventPage = {
    slug: new URLSearchParams(window.location.search).get('slug') || window.location.pathname.split('/bilete/')[1]?.split('?')[0] || '',
    event: null,
    quantities: {},
    ticketTypes: [],
    galleryImages: [],
    isInterested: false,
    shareMenuOpen: false,

    async init() {
        if (!this.slug) {
            window.location.href = '/';
            return;
        }
        await this.loadEvent();
        this.updateHeaderCart();
        this.trackView();
        this.loadInterestStatus();
        this.setupClickOutside();
    },

    async trackView() {
        try {
            await AmbiletAPI.trackEventView(this.slug);
        } catch (e) {
            console.log('View tracking failed:', e);
        }
    },

    async loadInterestStatus() {
        try {
            var response = await AmbiletAPI.checkEventInterest(this.slug);
            if (response.success && response.data) {
                this.isInterested = response.data.is_interested;
                this.updateInterestButton();
                if (response.data.interested_count) {
                    document.getElementById('event-interested').textContent = this.formatCount(response.data.interested_count) + ' interesati';
                }
                if (response.data.views_count) {
                    document.getElementById('event-views').textContent = this.formatCount(response.data.views_count) + ' vizualizari';
                }
            }
        } catch (e) {
            console.log('Interest check failed:', e);
        }
    },

    async toggleInterest() {
        try {
            var response = await AmbiletAPI.toggleEventInterest(this.slug);
            if (response.success && response.data) {
                this.isInterested = response.data.is_interested;
                this.updateInterestButton();
                document.getElementById('event-interested').textContent = this.formatCount(response.data.interested_count) + ' interesati';
            }
        } catch (e) {
            console.error('Toggle interest failed:', e);
        }
    },

    updateInterestButton() {
        var btn = document.getElementById('interest-btn');
        var icon = document.getElementById('interest-icon');
        if (this.isInterested) {
            btn.classList.add('border-primary', 'text-primary', 'bg-primary/5');
            icon.setAttribute('fill', 'currentColor');
        } else {
            btn.classList.remove('border-primary', 'text-primary', 'bg-primary/5');
            icon.setAttribute('fill', 'none');
        }
    },

    formatCount(num) {
        if (num >= 1000000) {
            return (num / 1000000).toFixed(1).replace(/\.0$/, '') + 'M';
        }
        if (num >= 1000) {
            return (num / 1000).toFixed(1).replace(/\.0$/, '') + 'k';
        }
        return String(num);
    },

    toggleShareMenu() {
        var menu = document.getElementById('share-menu');
        this.shareMenuOpen = !this.shareMenuOpen;
        menu.classList.toggle('hidden', !this.shareMenuOpen);
    },

    setupClickOutside() {
        var self = this;
        document.addEventListener('click', function(e) {
            var dropdown = document.getElementById('share-dropdown');
            if (dropdown && !dropdown.contains(e.target) && self.shareMenuOpen) {
                self.shareMenuOpen = false;
                document.getElementById('share-menu').classList.add('hidden');
            }
        });
    },

    shareOn(platform) {
        var url = window.location.href;
        var title = this.event ? this.event.title : document.title;
        var shareUrl = '';

        switch (platform) {
            case 'facebook':
                shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
                break;
            case 'whatsapp':
                shareUrl = 'https://wa.me/?text=' + encodeURIComponent(title + ' - ' + url);
                break;
            case 'email':
                shareUrl = 'mailto:?subject=' + encodeURIComponent(title) + '&body=' + encodeURIComponent('Uita-te la acest eveniment: ' + url);
                window.location.href = shareUrl;
                this.toggleShareMenu();
                return;
        }

        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
        this.toggleShareMenu();
    },

    async copyLink() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            // Show brief success message
            var btn = document.querySelector('[onclick*="copyLink"]');
            var originalText = btn.innerHTML;
            btn.innerHTML = '<svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Copiat!';
            setTimeout(function() {
                btn.innerHTML = originalText;
            }, 2000);
        } catch (e) {
            console.error('Copy failed:', e);
        }
        this.toggleShareMenu();
    },

    async loadEvent() {
        try {
            const response = await AmbiletAPI.getEvent(this.slug);
            if (response.success && response.data) {
                // Transform API response to expected format
                this.event = this.transformApiData(response.data);
                this.render();
            } else {
                this.showError('Eveniment negasit');
            }
        } catch (error) {
            console.error('Failed to load event:', error);
            if (error.status === 404) {
                this.showError('Eveniment negasit');
            } else {
                this.showError('Eroare la incarcarea evenimentului');
            }
        }
    },

    transformApiData(apiData) {
        // API returns { event: {...}, venue: {...}, ticket_types: [...], artists: [...] }
        var eventData = apiData.event || apiData;
        var venueData = apiData.venue || null;
        var artistsData = apiData.artists || [];
        var ticketTypesData = apiData.ticket_types || [];

        // Parse starts_at to get date and time
        var startsAt = eventData.starts_at ? new Date(eventData.starts_at) : new Date();
        var doorsAt = eventData.doors_open_at ? new Date(eventData.doors_open_at) : null;

        // Format time as HH:MM
        function formatTime(date) {
            if (!date) return null;
            return String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
        }

        // Get main image from API response - use image_url or cover_image_url
        var mainImage = eventData.image_url || eventData.cover_image_url || null;
        var coverImage = eventData.cover_image_url || eventData.image_url || null;

        return {
            id: eventData.id,
            title: eventData.name,
            slug: eventData.slug,
            description: eventData.description,
            content: eventData.description,
            short_description: eventData.short_description,
            image: coverImage || mainImage,
            images: [coverImage, mainImage].filter(Boolean).filter((v, i, a) => a.indexOf(v) === i),
            category: eventData.category,
            category_slug: eventData.category_slug || (eventData.category ? eventData.category.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-') : null),
            tags: eventData.tags,
            start_date: eventData.starts_at,
            date: eventData.starts_at,
            end_date: eventData.ends_at,
            start_time: formatTime(startsAt),
            doors_time: formatTime(doorsAt),
            is_popular: eventData.is_featured,
            is_featured: eventData.is_featured,
            interested: Math.floor(Math.random() * 500) + 100,
            views: (Math.random() * 3 + 0.5).toFixed(1) + 'k',
            venue: venueData ? {
                name: venueData.name,
                description: venueData.description,
                address: venueData.address,
                city: venueData.city,
                state: venueData.state,
                country: venueData.country,
                latitude: venueData.latitude,
                longitude: venueData.longitude,
                google_maps_url: venueData.google_maps_url,
                image: venueData.image,
                capacity: venueData.capacity
            } : null,
            location: venueData ? (venueData.city ? venueData.name + ', ' + venueData.city : venueData.name) : 'Locatie TBA',
            artist: artistsData.length ? {
                name: artistsData[0].name,
                image: artistsData[0].image_url,
                slug: artistsData[0].slug
            } : null,
            artists: artistsData,
            ticket_types: ticketTypesData.map(function(tt) {
                // API returns available_quantity, not available
                var available = tt.available_quantity !== undefined ? tt.available_quantity : (tt.available !== undefined ? tt.available : 999);
                return {
                    id: tt.id,
                    name: tt.name,
                    description: tt.description,
                    price: tt.price,
                    original_price: tt.original_price || null,
                    discount_percent: tt.discount_percent || null,
                    currency: tt.currency || 'RON',
                    available: available,
                    min_per_order: tt.min_per_order || 1,
                    max_per_order: tt.max_per_order || 10,
                    status: tt.status,
                    is_sold_out: available <= 0
                };
            }),
            max_tickets_per_order: eventData.max_tickets_per_order || 10,
            // Commission settings from API
            commission_rate: apiData.commission_rate || 5,
            commission_mode: apiData.commission_mode || 'included',
            // Taxes from API (e.g., Red Cross stamp)
            taxes: apiData.taxes || []
        };
    },

    showError(message) {
        document.getElementById('loading-state').innerHTML = `
            <div class="w-full py-16 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h1 class="mb-4 text-2xl font-bold text-secondary">${message}</h1>
                <a href="/" class="inline-flex items-center gap-2 px-6 py-3 font-semibold text-white transition-colors bg-primary rounded-xl hover:bg-primary-dark">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Inapoi acasa
                </a>
            </div>
        `;
    },

    render() {
        const e = this.event;

        // Update page title
        document.title = `${e.title} — ${AMBILET_CONFIG.SITE_NAME}`;

        // Update breadcrumb
        document.getElementById('breadcrumb-title').textContent = e.title;

        // Show content, hide loading
        document.getElementById('loading-state').classList.add('hidden');
        document.getElementById('event-content').classList.remove('hidden');

        // Main image
        const mainImg = e.image || e.images?.[0] || '/assets/images/placeholder-event.jpg';
        document.getElementById('mainImage').src = mainImg;
        document.getElementById('mainImage').alt = e.title;

        // Gallery
        this.galleryImages = e.images?.length ? e.images : [mainImg];
        this.renderGallery();

        // Badges
        const badgesHtml = [];
        if (e.category) badgesHtml.push(`<span class="px-3 py-1.5 bg-accent text-white text-xs font-bold rounded-lg uppercase">${e.category}</span>`);
        if (e.is_popular) badgesHtml.push(`<span class="px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg uppercase">🔥 Popular</span>`);
        document.getElementById('event-badges').innerHTML = badgesHtml.join('');

        // Title
        document.getElementById('event-title').textContent = e.title;

        // Date
        const eventDate = new Date(e.start_date || e.date);
        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const weekdays = ['Duminica', 'Luni', 'Marti', 'Miercuri', 'Joi', 'Vineri', 'Sambata'];

        document.getElementById('event-day').textContent = eventDate.getDate();
        document.getElementById('event-month').textContent = months[eventDate.getMonth()];
        document.getElementById('event-weekday').textContent = weekdays[eventDate.getDay()];
        document.getElementById('event-date-full').textContent = `${eventDate.getDate()} ${months[eventDate.getMonth()]} ${eventDate.getFullYear()}`;

        // Time
        document.getElementById('event-time').textContent = `Ora inceperii: ${e.start_time || '20:00'}`;
        document.getElementById('event-doors').textContent = `Deschidere usi: ${e.doors_time || '19:00'}`;

        // Venue
        document.getElementById('venue-name').textContent = e.venue?.name || e.location || 'Locatie TBA';
        document.getElementById('venue-address').textContent = e.venue?.address || '';

        // Stats
        document.getElementById('event-interested').textContent = `${e.interested || Math.floor(Math.random() * 500) + 100} interesati`;
        document.getElementById('event-views').textContent = `${e.views || (Math.random() * 3 + 0.5).toFixed(1)}k vizualizari`;

        // Description
        document.getElementById('event-description').innerHTML = this.formatDescription(e.description || e.content || 'Descriere indisponibila');

        // Artist section
        if (e.artist || e.artists?.length) {
            this.renderArtist(e.artist || e.artists[0]);
        }

        // Venue section
        this.renderVenue(e.venue || { name: e.location || 'Locatie TBA' });

        // Ticket types
        this.ticketTypes = e.ticket_types || this.getDefaultTicketTypes();
        this.renderTicketTypes();

        // Related events
        this.loadRelatedEvents();
    },

    formatDescription(desc) {
        if (!desc) return '<p class="text-muted">Descriere indisponibila</p>';

        // Check if it's already HTML (contains common HTML tags)
        var hasHtml = /<[a-z][\s\S]*>/i.test(desc);

        if (hasHtml) {
            // Wrap in a styled container that handles HTML properly
            return '<div class="space-y-2 prose prose-slate prose-p:text-muted prose-p:leading-relaxed prose-headings:text-secondary prose-strong:text-secondary prose-a:text-primary prose-li:text-muted max-w-none">' + desc + '</div>';
        }

        // Convert plain text to paragraphs
        // Handle both double newlines and single newlines
        var paragraphs = desc.split(/\n\n+/).filter(function(p) { return p.trim(); });
        if (paragraphs.length === 1) {
            // Try splitting by single newlines if no double newlines
            paragraphs = desc.split(/\n/).filter(function(p) { return p.trim(); });
        }

        return paragraphs.map(function(p) {
            return '<p class="mb-4 leading-relaxed text-muted">' + p.trim() + '</p>';
        }).join('');
    },

    renderGallery() {
        const container = document.getElementById('gallery-thumbs');
        if (this.galleryImages.length <= 1) {
            container.innerHTML = '';
            return;
        }
        container.innerHTML = this.galleryImages.slice(0, 4).map((img, i) => `
            <button onclick="EventPage.changeImage(${i})" class="gallery-thumb ${i === 0 ? 'active' : ''} w-16 h-12 rounded-lg overflow-hidden border-2 border-white/50 opacity-80">
                <img src="${img}" class="object-cover w-full h-full">
            </button>
        `).join('');
    },

    changeImage(index) {
        document.getElementById('mainImage').src = this.galleryImages[index];
        document.querySelectorAll('.gallery-thumb').forEach((thumb, i) => {
            thumb.classList.toggle('active', i === index);
        });
    },

    renderArtist(artist) {
        if (!artist) return;
        document.getElementById('artist-section').style.display = 'block';

        // Update section title based on whether we have multiple artists
        // var hasMultiple = this.event.artists && this.event.artists.length > 1;
        // document.getElementById('artist-section-title').textContent = hasMultiple ? 'Despre artisti' : 'Despre artist';

        // Artist image with link to artist page
        var artistImage = artist.image_url || artist.image || '/assets/images/placeholder-artist.jpg';
        var artistLink = artist.slug ? '/artist/' + artist.slug : '#';
        var artistDescription = artist.description || artist.bio || '';

        var html = '<div class="flex flex-col gap-6 md:flex-row">' +
            '<div class="md:w-1/3">' +
                '<a href="' + artistLink + '">' +
                    '<img src="' + artistImage + '" alt="' + artist.name + '" class="object-cover w-full transition-transform aspect-square rounded-2xl hover:scale-105">' +
                '</a>' +
            '</div>' +
            '<div class="md:w-2/3">' +
                '<div class="flex items-center gap-3 mb-4">' +
                    '<a href="' + artistLink + '" class="text-2xl font-bold text-secondary hover:text-primary">' + artist.name + '</a>' +
                    (artist.verified ? '<span class="px-3 py-1 text-xs font-bold rounded-full bg-primary/10 text-primary">Verified</span>' : '') +
                '</div>' +
                (artistDescription ? '<p class="mb-4 leading-relaxed text-muted">' + artistDescription + '</p>' : '<p class="mb-4 leading-relaxed text-muted">Detalii despre artist vor fi disponibile in curand.</p>') +
                '<a href="' + artistLink + '" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline">' +
                    '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>' +
                    'Vezi profilul artistului' +
                '</a>' +
            '</div>' +
        '</div>';

        document.getElementById('artist-content').innerHTML = html;
    },

    renderVenue(venue) {
        // Generate Google Maps URL from latitude/longitude if available
        var googleMapsUrl = venue.google_maps_url || null;
        if (!googleMapsUrl && venue.latitude && venue.longitude) {
            googleMapsUrl = 'https://www.google.com/maps/search/?api=1&query=' + venue.latitude + ',' + venue.longitude;
        }

        // Build venue address for display
        var venueAddress = venue.address || '';
        if (venue.city) {
            venueAddress = venueAddress ? venueAddress + ', ' + venue.city : venue.city;
        }
        if (venue.state && venue.state !== venue.city) {
            venueAddress = venueAddress ? venueAddress + ', ' + venue.state : venue.state;
        }

        var html = '<div class="flex flex-col gap-6 md:flex-row">' +
            '<div class="md:w-1/3">' +
                '<img src="' + (venue.image || '/assets/images/placeholder-venue.jpg') + '" alt="' + venue.name + '" class="object-cover w-full h-64 mb-4 rounded-2xl">' +
            '</div>' +
            '<div class="md:w-2/3">' +
                '<h3 class="mb-2 text-xl font-bold text-secondary">' + venue.name + '</h3>' +
                '<p class="mb-4 text-muted">' + venueAddress + '</p>' +
                '<p class="mb-4 leading-relaxed text-muted">' + (venue.description || '') + '</p>';

        // Amenities
        if (venue.amenities && venue.amenities.length) {
            html += '<div class="mb-6 space-y-3">';
            venue.amenities.forEach(function(a) {
                html += '<div class="flex items-center gap-3">' +
                    '<div class="flex items-center justify-center w-10 h-10 rounded-lg bg-success/10">' +
                        '<svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>' +
                    '</div>' +
                    '<span class="text-sm text-secondary">' + a + '</span>' +
                '</div>';
            });
            html += '</div>';
        }

        // Google Maps link
        if (googleMapsUrl) {
            html += '<a href="' + googleMapsUrl + '" target="_blank" class="inline-flex items-center gap-2 font-semibold text-primary hover:underline">' +
                '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                'Deschide in Google Maps' +
            '</a>';
        }

        html += '</div></div>';

        document.getElementById('venue-content').innerHTML = html;
    },

    getDefaultTicketTypes() {
        return [
            { id: 'early', name: 'Early Bird 🐦', price: 65, original_price: 80, available: 23, description: 'Acces general o zi • Primii 100 de cumparatori' },
            { id: 'standard', name: 'Standard', price: 80, available: 245, description: 'Acces general o zi • Standing area' },
            { id: 'vip', name: 'VIP ⭐', price: 150, available: 12, description: 'Acces ambele zile • Loc rezervat • Meet & Greet' },
            { id: 'premium', name: 'Premium 👑', price: 250, available: 5, description: 'Toate beneficiile VIP + Backstage Access + Merch exclusiv' }
        ];
    },

    renderTicketTypes() {
        const container = document.getElementById('ticket-types');
        var self = this;
        var commissionRate = this.event.commission_rate || 5;
        var commissionMode = this.event.commission_mode || 'included';

        container.innerHTML = this.ticketTypes.map(tt => {
            self.quantities[tt.id] = 0;
            const hasDiscount = tt.original_price && tt.original_price > tt.price;
            const discountPercent = hasDiscount ? Math.round((1 - tt.price / tt.original_price) * 100) : 0;

            // Only show availability count if < 40 tickets remaining
            let availabilityHtml = '';
            if (tt.is_sold_out || tt.available <= 0) {
                availabilityHtml = '<span class="text-xs font-semibold text-primary">❌ Sold Out</span>';
            } else if (tt.available <= 5) {
                availabilityHtml = '<span class="text-xs font-semibold text-primary">🔥 Doar ' + tt.available + ' disponibile</span>';
            } else if (tt.available <= 20) {
                availabilityHtml = '<span class="text-xs font-semibold text-accent">⚡ Doar ' + tt.available + ' disponibile</span>';
            } else if (tt.available < 40) {
                availabilityHtml = '<span class="text-xs font-semibold text-success">✓ ' + tt.available + ' disponibile</span>';
            } else {
                // Don't show count for >= 40 tickets
                availabilityHtml = '<span class="text-xs font-semibold text-success">✓ Disponibil</span>';
            }

            // Calculate commission based on mode
            var displayPrice = tt.price;
            var basePrice, commissionAmount;
            if (commissionMode === 'included') {
                // Commission is included in the price - customer pays tt.price
                basePrice = tt.price / (1 + commissionRate / 100);
                commissionAmount = tt.price - basePrice;
            } else {
                // Commission is added on top - calculate what customer will pay
                basePrice = tt.price;
                commissionAmount = tt.price * (commissionRate / 100);
                displayPrice = tt.price + commissionAmount;
            }

            // Build tooltip content dynamically
            var tooltipHtml = '<p class="mb-2 text-sm font-semibold">Detalii pret bilet:</p>' +
                '<div class="space-y-1 text-xs">';

            if (commissionMode === 'included') {
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/70">Pret bilet:</span><span>' + basePrice.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/70">Comision platforma (' + commissionRate + '%):</span><span>' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total:</span><span class="font-semibold">' + tt.price.toFixed(2) + ' lei</span></div>';
            } else {
                tooltipHtml += '<div class="flex justify-between"><span class="text-white/70">Pret bilet:</span><span>' + tt.price.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between"><span class="text-white/70">Comision platforma (' + commissionRate + '%):</span><span>+' + commissionAmount.toFixed(2) + ' lei</span></div>' +
                    '<div class="flex justify-between pt-1 mt-1 border-t border-white/20"><span class="font-semibold">Total:</span><span class="font-semibold">' + displayPrice.toFixed(2) + ' lei</span></div>';
            }
            tooltipHtml += '</div>';

            return '<div class="relative z-10 p-4 border-2 cursor-pointer ticket-card border-border rounded-2xl hover:z-20" data-ticket="' + tt.id + '" data-price="' + displayPrice + '">' +
                '<div class="flex items-start justify-between">' +
                    '<div class="relative tooltip-trigger">' +
                        '<h3 class="flex items-center font-bold gap-x-2 text-secondary cursor-help border-muted">' + tt.name + (hasDiscount ? '<span class="discount-badge text-white text-[10px] font-bold py-1 px-2 rounded-full">-' + discountPercent + '%</span> ' : '') + '</h3>' + 
                        '<p class="text-sm text-muted">' + (tt.description || '') + '</p>' +
                        '<div class="absolute left-0 z-10 w-64 p-4 mt-2 text-white shadow-xl tooltip top-full bg-secondary rounded-xl">' +
                            tooltipHtml +
                        '</div>' +
                    '</div>' +
                    '<div class="text-right">' +
                        (hasDiscount ? '<span class="text-sm line-through text-muted">' + (commissionMode === 'add_on_top' ? (tt.original_price + tt.original_price * commissionRate / 100).toFixed(0) : tt.original_price) + ' lei</span>' : '') +
                        '<span class="block text-xl font-bold text-primary">' + displayPrice.toFixed(2) + ' lei</span>' +
                    '</div>' +
                '</div>' +
                '<div class="flex items-center justify-between">' +
                    availabilityHtml +
                    '<div class="flex items-center gap-2">' +
                        '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', -1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">-</button>' +
                        '<span id="qty-' + tt.id + '" class="w-8 font-bold text-center">0</span>' +
                        '<button onclick="EventPage.updateQuantity(\'' + tt.id + '\', 1)" class="flex items-center justify-center w-8 h-8 font-bold transition-colors rounded-lg bg-surface hover:bg-primary hover:text-white">+</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
        }).join('');
    },

    updateQuantity(ticketId, delta) {
        // Handle both string and number IDs with loose comparison
        const tt = this.ticketTypes.find(t => String(t.id) === String(ticketId));
        if (!tt) return;

        const newQty = (this.quantities[ticketId] || 0) + delta;
        if (newQty >= 0 && newQty <= tt.available) {
            this.quantities[ticketId] = newQty;
            document.getElementById(`qty-${ticketId}`).textContent = newQty;

            // Update ticket card selection
            const card = document.querySelector(`[data-ticket="${ticketId}"]`);
            if (card) card.classList.toggle('selected', newQty > 0);

            this.updateCart();
        }
    },

    updateCart() {
        const totalTickets = Object.values(this.quantities).reduce((a, b) => a + b, 0);
        let subtotal = 0;
        var commissionRate = this.event.commission_rate || 5;
        var commissionMode = this.event.commission_mode || 'included';

        for (const [ticketId, qty] of Object.entries(this.quantities)) {
            const tt = this.ticketTypes.find(t => String(t.id) === String(ticketId));
            if (tt) {
                // If commission is add_on_top, the price displayed already includes commission
                // so we use it directly. If included, tt.price is already the final price.
                var ticketPrice = tt.price;
                if (commissionMode === 'add_on_top') {
                    ticketPrice = tt.price + (tt.price * commissionRate / 100);
                }
                subtotal += qty * ticketPrice;
            }
        }

        // Calculate taxes from API
        var self = this;
        var totalTaxes = 0;
        var taxBreakdown = [];
        var taxes = this.event.taxes || [];

        taxes.forEach(function(tax) {
            var taxAmount = 0;
            if (tax.value_type === 'percent') {
                taxAmount = subtotal * (tax.value / 100);
            } else if (tax.value_type === 'fixed') {
                taxAmount = tax.value;
            }
            totalTaxes += taxAmount;
            taxBreakdown.push({ name: tax.name, amount: taxAmount, value: tax.value, value_type: tax.value_type });
        });

        const total = subtotal + totalTaxes;
        const points = Math.floor(subtotal / 10);

        // Update header cart count
        this.updateHeaderCart();

        // Show/hide cart summary
        const cartSummary = document.getElementById('cartSummary');
        const emptyCart = document.getElementById('emptyCart');

        if (totalTickets > 0) {
            cartSummary.classList.remove('hidden');
            emptyCart.classList.add('hidden');

            document.getElementById('subtotal').textContent = subtotal.toFixed(2) + ' lei';

            // Render dynamic taxes
            var taxesContainer = document.getElementById('taxesContainer');
            if (taxesContainer) {
                var taxesHtml = '';
                taxBreakdown.forEach(function(tax) {
                    var rateLabel = tax.value_type === 'percent' ? '(' + tax.value + '%)' : '';
                    taxesHtml += '<div class="flex justify-between text-sm"><span class="text-muted">' + tax.name + ' ' + rateLabel + ':</span><span class="font-medium">' + tax.amount.toFixed(2) + ' lei</span></div>';
                });
                taxesContainer.innerHTML = taxesHtml;
            }

            document.getElementById('totalPrice').textContent = total.toFixed(2) + ' lei';

            const pointsEl = document.getElementById('pointsEarned');
            pointsEl.textContent = points;
            pointsEl.classList.remove('points-counter');
            void pointsEl.offsetWidth;
            pointsEl.classList.add('points-counter');
        } else {
            cartSummary.classList.add('hidden');
            emptyCart.classList.remove('hidden');
        }
    },

    addToCart() {
        var addedAny = false;
        var self = this;

        for (var ticketId in this.quantities) {
            var qty = this.quantities[ticketId];
            if (qty > 0) {
                var tt = this.ticketTypes.find(function(t) {
                    return String(t.id) === String(ticketId);
                });
                if (tt) {
                    // Cart expects: addItem(eventId, eventData, ticketTypeId, ticketTypeData, quantity)
                    var eventData = {
                        id: self.event.id,
                        title: self.event.title,
                        slug: self.event.slug,
                        start_date: self.event.start_date || self.event.date,
                        start_time: self.event.start_time,
                        image: self.event.image,
                        venue: self.event.venue
                    };
                    var ticketTypeData = {
                        id: tt.id,
                        name: tt.name,
                        price: tt.price,
                        original_price: tt.original_price,
                        description: tt.description
                    };
                    AmbiletCart.addItem(self.event.id, eventData, tt.id, ticketTypeData, qty);
                    addedAny = true;
                }
            }
        }

        if (addedAny) {
            // Open cart drawer instead of redirecting
            setTimeout(function() {
                if (typeof window.openCartDrawer === 'function') {
                    window.openCartDrawer();
                } else if (typeof AmbiletCart !== 'undefined' && AmbiletCart.openDrawer) {
                    AmbiletCart.openDrawer();
                } else {
                    // Fallback: redirect to cart page if drawer not available
                    window.location.href = '/cos';
                }
            }, 300);
        }
    },

    async loadRelatedEvents() {
        try {
            const response = await AmbiletAPI.get('/events', { limit: 8 });
            if (response.success && response.data?.length) {
                // Filter out current event by ID and slug, then take first 4
                const currentId = this.event.id;
                const currentSlug = this.event.slug;
                const filtered = response.data.filter(function(e) {
                    return e.id !== currentId && e.slug !== currentSlug;
                }).slice(0, 4);

                if (filtered.length > 0) {
                    this.renderRelatedEvents(filtered);
                }
            }
        } catch (e) {
            console.error('Failed to load related events:', e);
            // Don't show demo events if API fails - just hide the section
        }
    },

    renderRelatedEvents(events) {
        document.getElementById('related-events-section').style.display = 'block';

        // Set category text and dynamic link
        var category = this.event.category;
        var categorySlug = this.event.category_slug;
        var seeAllLink = document.getElementById('see-all-link');

        if (category && categorySlug) {
            document.getElementById('related-category-text').textContent = 'Evenimente similare din categoria ' + category;
            seeAllLink.href = '/evenimente?category=' + encodeURIComponent(categorySlug);
        } else if (category) {
            document.getElementById('related-category-text').textContent = 'Evenimente similare din categoria ' + category;
            // Slugify the category name for the URL
            var slug = category.toLowerCase().replace(/[^\w\s-]/g, '').replace(/\s+/g, '-');
            seeAllLink.href = '/evenimente?category=' + encodeURIComponent(slug);
        } else {
            document.getElementById('related-category-text').textContent = 'Evenimente similare';
            seeAllLink.href = '/evenimente';
        }

        const months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        document.getElementById('related-events').innerHTML = events.map(function(e) {
            // API returns: name, image_url, starts_at/event_date, venue (string), city, price_from
            const eventDate = e.starts_at || e.event_date || e.start_date || e.date;
            const date = eventDate ? new Date(eventDate) : new Date();
            const title = e.name || e.title || 'Eveniment';
            const image = e.image_url || e.image || '/assets/images/placeholder-event.jpg';
            const venue = e.venue || e.location || 'Locatie TBA';
            const city = e.city ? ', ' + e.city : '';
            const price = e.price_from || e.price || e.min_price || 50;

            return '<a href="/bilete/' + e.slug + '" class="overflow-hidden bg-white border event-card rounded-2xl border-border group">' +
                '<div class="relative overflow-hidden h-80">' +
                    '<img src="' + image + '" alt="' + title + '" class="object-cover w-full h-full event-image">' +
                    '<div class="absolute top-3 left-3">' +
                        '<div class="px-3 py-2 text-center text-white shadow-lg date-badge rounded-xl">' +
                            '<span class="block text-lg font-bold leading-none">' + date.getDate() + '</span>' +
                            '<span class="block text-[10px] uppercase tracking-wide mt-0.5">' + months[date.getMonth()] + '</span>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="p-4">' +
                    '<h3 class="font-bold leading-snug transition-colors text-secondary group-hover:text-primary line-clamp-2">' + title + '</h3>' +
                    '<p class="text-sm text-muted mt-2 flex items-center gap-1.5">' +
                        '<svg class="flex-shrink-0 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>' +
                        venue + city +
                    '</p>' +
                    '<div class="flex items-center justify-between pt-3 mt-3 border-t border-border">' +
                        '<span class="font-bold text-primary">de la ' + price + ' lei</span>' +
                    '</div>' +
                '</div>' +
            '</a>';
        }).join('');
    },

    updateHeaderCart() {
        const count = AmbiletCart.getItemCount();
        const cartBadge = document.getElementById('cartBadge');
        const cartDrawerCount = document.getElementById('cartDrawerCount');

        if (cartBadge) {
            if (count > 0) {
                cartBadge.textContent = count > 99 ? '99+' : count;
                cartBadge.classList.remove('hidden');
                cartBadge.classList.add('flex');
            } else {
                cartBadge.classList.add('hidden');
                cartBadge.classList.remove('flex');
            }
        }

        if (cartDrawerCount) {
            if (count > 0) {
                cartDrawerCount.textContent = count;
                cartDrawerCount.classList.remove('hidden');
            } else {
                cartDrawerCount.classList.add('hidden');
            }
        }
    }
};

document.addEventListener('DOMContentLoaded', () => EventPage.init());
</script>
JS;

require_once __DIR__ . '/includes/scripts.php';
?>
