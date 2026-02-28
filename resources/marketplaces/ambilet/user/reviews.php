<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Recenziile mele';
$currentPage = 'reviews';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<?php require_once dirname(__DIR__) . '/includes/user-wrap.php'; ?>
            <!-- Page Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-secondary">Recenziile mele</h1>
                <p class="mt-1 text-sm text-muted">Gestioneaza recenziile pe care le-ai scris pentru evenimente</p>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-4">
                <div class="p-4 bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 mb-2 rounded-lg bg-yellow-100">
                        <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                    <div class="text-2xl font-bold text-secondary" id="stat-total">0</div>
                    <div class="text-xs text-muted">Total recenzii</div>
                </div>
                <div class="p-4 bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 mb-2 rounded-lg bg-green-100">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="text-2xl font-bold text-secondary" id="stat-published">0</div>
                    <div class="text-xs text-muted">Publicate</div>
                </div>
                <div class="p-4 bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 mb-2 rounded-lg bg-blue-100">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div class="text-2xl font-bold text-secondary" id="stat-pending">0</div>
                    <div class="text-xs text-muted">In asteptare</div>
                </div>
                <div class="p-4 bg-white border rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 mb-2 rounded-lg bg-red-100">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
                    </div>
                    <div class="text-2xl font-bold text-secondary" id="stat-avg">0</div>
                    <div class="text-xs text-muted">Rating mediu</div>
                </div>
            </div>

            <!-- Pending Reviews Alert -->
            <div class="hidden items-center gap-4 p-4 mb-6 border rounded-xl bg-yellow-50 border-yellow-400" id="pending-events-alert">
                <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-white rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </div>
                <div class="flex-1">
                    <h3 class="font-semibold text-yellow-800">Ai <span id="pending-count">0</span> evenimente care asteapta o recenzie</h3>
                    <p class="text-sm text-yellow-700">Parerea ta conteaza! Ajuta alti utilizatori sa ia decizii informate.</p>
                </div>
                <a href="/cont/scrie-recenzie" class="px-4 py-2 text-sm font-semibold text-yellow-800 bg-white rounded-lg hover:bg-yellow-100">Scrie recenzii</a>
            </div>

            <!-- Filter Tabs -->
            <div class="flex flex-wrap gap-2 mb-6">
                <button class="filter-tab active px-4 py-2 text-sm font-medium rounded-full border transition-colors" data-filter="all">
                    Toate <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-white/20" id="count-all">0</span>
                </button>
                <button class="filter-tab px-4 py-2 text-sm font-medium text-muted bg-white border rounded-full border-border hover:border-primary hover:text-primary transition-colors" data-filter="published">
                    Publicate <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-surface" id="count-published">0</span>
                </button>
                <button class="filter-tab px-4 py-2 text-sm font-medium text-muted bg-white border rounded-full border-border hover:border-primary hover:text-primary transition-colors" data-filter="pending">
                    In asteptare <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-surface" id="count-pending">0</span>
                </button>
                <button class="filter-tab px-4 py-2 text-sm font-medium text-muted bg-white border rounded-full border-border hover:border-primary hover:text-primary transition-colors" data-filter="rejected">
                    Respinse <span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-surface" id="count-rejected">0</span>
                </button>
            </div>

            <!-- Reviews List -->
            <div class="space-y-4" id="reviews-list">
                <!-- Reviews will be loaded here -->
            </div>

            <!-- Empty State -->
            <div class="hidden p-12 text-center bg-white border rounded-2xl border-border" id="empty-reviews">
                <div class="flex items-center justify-center w-20 h-20 mx-auto mb-4 rounded-full bg-surface">
                    <svg class="w-10 h-10 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
                <h3 class="mb-2 text-lg font-bold text-secondary">Nu ai scris inca recenzii</h3>
                <p class="mb-6 text-muted">Dupa ce participi la un eveniment, poti lasa o recenzie pentru a ajuta alti utilizatori.</p>
                <a href="/cont/bilete" class="btn btn-primary">Vezi biletele tale</a>
            </div>
<?php 
require_once dirname(__DIR__) . '/includes/user-wrap-end.php';
require_once dirname(__DIR__) . '/includes/user-footer.php'; 
?>

<?php
$scriptsExtra = <<<'JS'
<script>
const ReviewsPage = {
    reviews: [],
    currentFilter: 'all',

    init() {
        if (!AmbiletAuth.isAuthenticated()) {
            window.location.href = '/autentificare?redirect=/cont/recenzii';
            return;
        }

        this.setupTabs();
        this.loadReviews();
    },

    setupTabs() {
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.filter-tab').forEach(t => {
                    t.classList.remove('active', 'bg-primary', 'text-white', 'border-primary');
                    t.classList.add('text-muted', 'bg-white', 'border-border');
                });
                tab.classList.add('active', 'bg-primary', 'text-white', 'border-primary');
                tab.classList.remove('text-muted', 'bg-white', 'border-border');
                this.currentFilter = tab.dataset.filter;
                this.renderReviews();
            });
        });

        const activeTab = document.querySelector('.filter-tab.active');
        if (activeTab) {
            activeTab.classList.add('bg-primary', 'text-white', 'border-primary');
            activeTab.classList.remove('text-muted', 'bg-white', 'border-border');
        }
    },

    async loadReviews() {
        try {
            const response = await AmbiletAPI.customer.getReviews();
            if (response.success && response.data) {
                this.reviews = response.data.reviews || response.data || [];
                this.updateStats(response.data.stats || response.stats || {});
                this.updateCounts();
                this.renderReviews();

                const pendingEvents = response.data.pending_events || response.pending_events || 0;
                if (pendingEvents > 0) {
                    document.getElementById('pending-count').textContent = pendingEvents;
                    document.getElementById('pending-events-alert').classList.remove('hidden');
                    document.getElementById('pending-events-alert').classList.add('flex');
                }
            } else {
                document.getElementById('empty-reviews').classList.remove('hidden');
            }
        } catch (error) {
            console.error('Error loading reviews:', error);
            document.getElementById('empty-reviews').classList.remove('hidden');
        }
    },

    updateStats(stats) {
        document.getElementById('stat-total').textContent = stats.total || '0';
        document.getElementById('stat-published').textContent = stats.published || '0';
        document.getElementById('stat-pending').textContent = stats.pending || '0';
        document.getElementById('stat-avg').textContent = stats.avg_rating || '0';
    },

    updateCounts() {
        const counts = {
            all: this.reviews.length,
            published: this.reviews.filter(r => r.status === 'published').length,
            pending: this.reviews.filter(r => r.status === 'pending').length,
            rejected: this.reviews.filter(r => r.status === 'rejected').length
        };
        document.getElementById('count-all').textContent = counts.all;
        document.getElementById('count-published').textContent = counts.published;
        document.getElementById('count-pending').textContent = counts.pending;
        document.getElementById('count-rejected').textContent = counts.rejected;
    },

    renderReviews() {
        const container = document.getElementById('reviews-list');
        const emptyState = document.getElementById('empty-reviews');

        let filtered = this.reviews;
        if (this.currentFilter !== 'all') {
            filtered = this.reviews.filter(r => r.status === this.currentFilter);
        }

        if (filtered.length === 0) {
            container.innerHTML = '';
            emptyState.classList.remove('hidden');
            return;
        }

        emptyState.classList.add('hidden');
        container.innerHTML = filtered.map(review => this.renderReviewCard(review)).join('');
    },

    renderReviewCard(review) {
        const statusClasses = {
            published: 'bg-green-100 text-green-700',
            pending: 'bg-yellow-100 text-yellow-700',
            rejected: 'bg-red-100 text-red-700'
        };
        const statusLabels = {
            published: 'Publicata',
            pending: 'In moderare',
            rejected: 'Respinsa'
        };

        const stars = Array(5).fill(0).map((_, i) =>
            `<svg class="w-4 h-4 ${i < review.rating ? 'text-yellow-400' : 'text-gray-200'}" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>`
        ).join('');

        return `
            <div class="overflow-hidden bg-white border rounded-xl border-border hover:shadow-md transition-shadow">
                <div class="flex flex-col gap-4 p-5 md:flex-row">
                    <div class="flex items-center justify-center flex-shrink-0 w-full h-32 md:w-24 md:h-24 rounded-xl bg-gradient-to-br from-purple-500 to-pink-500">
                        ${review.event_image ? `<img src="${review.event_image}" alt="${review.event_title}" class="object-cover w-full h-full rounded-xl">` : `
                        <svg class="w-10 h-10 text-white/90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                        `}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-start justify-between gap-2 mb-2">
                            <h3 class="font-bold text-secondary">
                                <a href="/eveniment/${review.event_slug}" class="hover:text-primary">${review.event_title}</a>
                            </h3>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full ${statusClasses[review.status]}">
                                ${statusLabels[review.status]}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 mb-3 text-sm text-muted">
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                ${review.event_date}
                            </span>
                            <span class="flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                ${review.venue}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mb-3">
                            ${stars}
                            <span class="text-sm text-muted">${review.rating}.0</span>
                        </div>
                        <p class="text-sm text-gray-600 line-clamp-3">${review.text}</p>
                        ${review.photos && review.photos.length > 0 ? `
                        <div class="flex gap-2 mt-3">
                            ${review.photos.slice(0, 3).map(p => `<div class="w-16 h-16 rounded-lg bg-gradient-to-br from-purple-400 to-pink-400"></div>`).join('')}
                            ${review.photos.length > 3 ? `<div class="flex items-center justify-center w-16 h-16 text-sm font-semibold text-white rounded-lg bg-secondary">+${review.photos.length - 3}</div>` : ''}
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-3 bg-surface border-t border-border">
                    <div class="flex flex-wrap items-center gap-4 text-sm text-muted">
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            ${review.status === 'published' ? 'Publicata pe ' : 'Trimisa pe '}${review.created_at}
                        </span>
                        ${review.helpful_count ? `
                        <span class="flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
                            ${review.helpful_count} persoane au gasit-o utila
                        </span>
                        ` : ''}
                    </div>
                    <div class="flex gap-2">
                        <button onclick="ReviewsPage.editReview('${review.id}')" class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-white border rounded-lg border-border text-muted hover:border-primary hover:text-primary transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                            Editeaza
                        </button>
                        <button onclick="ReviewsPage.deleteReview('${review.id}')" class="flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium bg-white border rounded-lg border-border text-muted hover:border-red-500 hover:text-red-500 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            ${review.status === 'pending' ? 'Retrage' : 'Sterge'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    },

    editReview(id) {
        window.location.href = '/cont/scrie-recenzie?edit=' + id;
    },

    async deleteReview(id) {
        if (!confirm('Esti sigur ca vrei sa stergi aceasta recenzie?')) return;

        try {
            const response = await AmbiletAPI.customer.deleteReview(id);
            if (response.success) {
                AmbiletNotifications.success('Recenzia a fost stearsa.');
                this.loadReviews();
            } else {
                AmbiletNotifications.error(response.message || 'Eroare la stergerea recenziei.');
            }
        } catch (error) {
            AmbiletNotifications.error('Eroare la stergerea recenziei.');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => ReviewsPage.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
