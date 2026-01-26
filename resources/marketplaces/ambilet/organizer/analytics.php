<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Analytics Eveniment';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'events';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';

// Get event ID from URL
$eventId = $_GET['event'] ?? null;
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Back Button & Event Header -->
            <div class="flex items-center gap-4 mb-6">
                <a href="/organizator/events" class="flex items-center gap-2 transition-colors text-muted hover:text-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Înapoi la evenimente
                </a>
            </div>

            <!-- Event Info Banner -->
            <div id="event-banner" class="relative p-6 mb-8 overflow-hidden text-white bg-gradient-to-r from-primary to-primary-dark rounded-2xl">
                <div class="absolute top-0 right-0 w-64 h-64 translate-x-1/2 -translate-y-1/2 rounded-full bg-white/5"></div>
                <div class="relative flex items-start justify-between">
                    <div>
                        <h1 id="event-title" class="mb-2 text-2xl font-bold md:text-3xl">Se încarcă...</h1>
                        <p id="event-info" class="mb-4 text-white/80"></p>
                        <div id="event-status" class="flex items-center gap-3"></div>
                    </div>
                    <div class="items-center hidden gap-3 lg:flex">
                        <button onclick="exportCsv()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors bg-white/10 hover:bg-white/20 rounded-xl">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Export CSV
                        </button>
                        <button onclick="exportPdf()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors bg-white/10 hover:bg-white/20 rounded-xl">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            Export PDF
                        </button>
                    </div>
                </div>
            </div>

            <!-- Period Selector -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-2 p-1 bg-white border rounded-xl border-border">
                    <button class="px-4 py-2 text-sm font-medium text-white transition-colors rounded-lg period-btn bg-primary" data-period="7d">7 zile</button>
                    <button class="px-4 py-2 text-sm font-medium transition-colors rounded-lg period-btn text-muted hover:bg-surface" data-period="30d">30 zile</button>
                    <button class="px-4 py-2 text-sm font-medium transition-colors rounded-lg period-btn text-muted hover:bg-surface" data-period="90d">90 zile</button>
                    <button class="px-4 py-2 text-sm font-medium transition-colors rounded-lg period-btn text-muted hover:bg-surface" data-period="all">Tot</button>
                </div>
                <button onclick="refreshData()" class="flex items-center gap-2 px-4 py-2 text-sm font-medium transition-colors text-muted hover:text-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Actualizează
                </button>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 gap-4 mb-8 lg:grid-cols-4">
                <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-success/10 rounded-xl">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span id="stat-revenue-change" class="px-2 py-1 text-xs font-medium rounded-full text-success bg-success/10">+0%</span>
                    </div>
                    <p id="stat-revenue" class="text-2xl font-bold text-secondary">0 lei</p>
                    <p class="mt-1 text-sm text-muted">Venituri totale</p>
                </div>

                <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-primary/10 rounded-xl">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                        </div>
                        <span id="stat-tickets-change" class="px-2 py-1 text-xs font-medium rounded-full text-success bg-success/10">+0%</span>
                    </div>
                    <p id="stat-tickets" class="text-2xl font-bold text-secondary">0</p>
                    <p class="mt-1 text-sm text-muted">Bilete vândute</p>
                </div>

                <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-cyan-500/10 rounded-xl">
                            <svg class="w-5 h-5 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </div>
                        <span id="stat-views-change" class="px-2 py-1 text-xs font-medium rounded-full text-success bg-success/10">+0%</span>
                    </div>
                    <p id="stat-views" class="text-2xl font-bold text-secondary">0</p>
                    <p class="mt-1 text-sm text-muted">Vizualizări</p>
                </div>

                <div class="p-5 bg-white border stat-card rounded-2xl border-border">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center justify-center w-10 h-10 bg-amber-500/10 rounded-xl">
                            <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                    </div>
                    <p id="stat-conversion" class="text-2xl font-bold text-secondary">0%</p>
                    <p class="mt-1 text-sm text-muted">Rata conversie</p>
                </div>
            </div>

            <!-- Main Grid -->
            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Left Column - Charts -->
                <div class="space-y-8 lg:col-span-2">
                    <!-- Sales Chart -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Performanța vânzări</h2>
                                <p class="text-sm text-muted">Venituri și bilete vândute</p>
                            </div>
                        </div>
                        <div class="h-72">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>

                    <!-- Traffic Sources -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Surse trafic</h2>
                                <p class="text-sm text-muted">De unde vin vizitatorii</p>
                            </div>
                        </div>
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="h-64">
                                <canvas id="trafficChart"></canvas>
                            </div>
                            <div id="traffic-list" class="space-y-3"></div>
                        </div>
                    </div>

                    <!-- Milestones Timeline -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-6">
                            <div>
                                <h2 class="text-lg font-bold text-secondary">Campanii & Milestone-uri</h2>
                                <p class="text-sm text-muted">Urmărește impactul campaniilor tale</p>
                            </div>
                            <button onclick="showAddMilestoneModal()" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-primary hover:bg-primary/5 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Adaugă
                            </button>
                        </div>
                        <div id="milestones-timeline" class="space-y-4"></div>
                        <div id="no-milestones" class="hidden py-8 text-center">
                            <svg class="w-12 h-12 mx-auto mb-3 text-muted/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                            <p class="mb-2 text-muted">Nu ai campanii active</p>
                            <p class="text-sm text-muted/70">Adaugă milestone-uri pentru a urmări impactul campaniilor tale</p>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-8">
                    <!-- Goals Progress -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="font-bold text-secondary">Obiective</h2>
                            <button onclick="showAddGoalModal()" class="text-sm font-medium text-primary hover:underline">+ Adaugă</button>
                        </div>
                        <div id="goals-list" class="space-y-4"></div>
                        <div id="no-goals" class="hidden py-6 text-center">
                            <svg class="w-10 h-10 mx-auto mb-2 text-muted/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
                            <p class="text-sm text-muted">Setează obiective pentru a urmări progresul</p>
                        </div>
                    </div>

                    <!-- Ticket Types Performance -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <h2 class="mb-4 font-bold text-secondary">Performanță tipuri bilete</h2>
                        <div id="ticket-types-list" class="space-y-3"></div>
                    </div>

                    <!-- Top Locations -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <h2 class="mb-4 font-bold text-secondary">Top locații</h2>
                        <div id="locations-list" class="space-y-2"></div>
                    </div>

                    <!-- Recent Sales -->
                    <div class="p-6 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="font-bold text-secondary">Vânzări recente</h2>
                            <a href="/organizator/sales?event=<?= $eventId ?>" class="text-sm font-medium text-primary">Vezi toate</a>
                        </div>
                        <div id="recent-sales" class="space-y-3"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Goal Modal -->
    <div id="goal-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeGoalModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
                <div class="p-6 border-b border-border">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-secondary">Adaugă obiectiv</h2>
                        <button onclick="closeGoalModal()" class="p-2 transition-colors text-muted hover:text-secondary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <form id="goal-form" onsubmit="saveGoal(event)" class="p-6 space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Tip obiectiv</label>
                        <select name="type" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                            <option value="revenue">Venituri (RON)</option>
                            <option value="tickets">Bilete vândute</option>
                            <option value="visitors">Vizitatori</option>
                            <option value="conversion_rate">Rată conversie (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Valoare țintă</label>
                        <input type="number" name="target_value" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required min="0" step="any">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Deadline (opțional)</label>
                        <input type="date" name="deadline" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="email_alerts" checked class="rounded border-border text-primary">
                            <span class="text-sm text-muted">Alerte email</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeGoalModal()" class="px-4 py-2 text-sm font-medium transition-colors text-muted hover:bg-surface rounded-xl">Anulează</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white transition-colors bg-primary hover:bg-primary-dark rounded-xl">Salvează</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Milestone Modal -->
    <div id="milestone-modal" class="fixed inset-0 z-50 hidden">
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" onclick="closeMilestoneModal()"></div>
        <div class="fixed inset-0 flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl" onclick="event.stopPropagation()">
                <div class="p-6 border-b border-border">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-secondary">Adaugă campanie/milestone</h2>
                        <button onclick="closeMilestoneModal()" class="p-2 transition-colors text-muted hover:text-secondary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
                <form id="milestone-form" onsubmit="saveMilestone(event)" class="p-6 space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Nume campanie</label>
                        <input type="text" name="name" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required placeholder="ex: Campanie Facebook Ads">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Tip</label>
                        <select name="type" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                            <option value="facebook_ads">Facebook Ads</option>
                            <option value="google_ads">Google Ads</option>
                            <option value="instagram_ads">Instagram Ads</option>
                            <option value="tiktok_ads">TikTok Ads</option>
                            <option value="email_campaign">Email Campaign</option>
                            <option value="influencer">Influencer</option>
                            <option value="press_release">Press Release</option>
                            <option value="price_change">Schimbare preț</option>
                            <option value="ticket_release">Lansare bilete noi</option>
                            <option value="other">Altele</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block mb-1 text-sm font-medium text-secondary">Data start</label>
                            <input type="date" name="start_date" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" required>
                        </div>
                        <div>
                            <label class="block mb-1 text-sm font-medium text-secondary">Data sfârșit</label>
                            <input type="date" name="end_date" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        </div>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">Buget (RON, optional)</label>
                        <input type="number" name="budget" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" min="0" step="0.01">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-secondary">UTM Source (optional)</label>
                        <input type="text" name="utm_source" class="w-full px-4 py-2 text-sm border border-border rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="ex: facebook">
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" onclick="closeMilestoneModal()" class="px-4 py-2 text-sm font-medium transition-colors text-muted hover:bg-surface rounded-xl">Anulează</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white transition-colors bg-primary hover:bg-primary-dark rounded-xl">Salvează</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
const eventId = <?= json_encode($eventId) ?>;
let currentPeriod = '30d';
let salesChart = null;
let trafficChart = null;

// Initialize
document.addEventListener('DOMContentLoaded', () => {
    if (!eventId) {
        window.location.href = '/organizator/events';
        return;
    }
    loadAnalytics();
    setupPeriodButtons();
});

function setupPeriodButtons() {
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.period-btn').forEach(b => {
                b.classList.remove('bg-primary', 'text-white');
                b.classList.add('text-muted');
            });
            this.classList.remove('text-muted');
            this.classList.add('bg-primary', 'text-white');
            currentPeriod = this.dataset.period;
            loadAnalytics();
        });
    });
}

async function loadAnalytics() {
    try {
        // Load dashboard data
        const response = await AmbiletAPI.get(`/organizer/events/${eventId}/analytics?period=${currentPeriod}`);
        if (response.success) {
            updateDashboard(response.data);
        }

        // Load milestones
        const milestonesResponse = await AmbiletAPI.get(`/organizer/events/${eventId}/milestones`);
        if (milestonesResponse.success) {
            updateMilestones(milestonesResponse.data);
        }

        // Load goals
        const goalsResponse = await AmbiletAPI.get(`/organizer/events/${eventId}/goals`);
        if (goalsResponse.success) {
            updateGoals(goalsResponse.data);
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
    }
}

function updateDashboard(data) {
    // Event banner
    if (data.event) {
        document.getElementById('event-title').textContent = data.event.title || 'Eveniment';
        document.getElementById('event-info').innerHTML = `
            <span>${data.event.date || ''}</span>
            ${data.event.venue ? `<span class="mx-2">•</span><span>${data.event.venue}</span>` : ''}
        `;

        let statusHtml = '';
        if (data.event.is_cancelled) {
            statusHtml = '<span class="px-3 py-1 text-sm text-white rounded-full bg-red-500/20">Anulat</span>';
        } else if (data.event.is_sold_out) {
            statusHtml = '<span class="px-3 py-1 text-sm text-white rounded-full bg-amber-500/20">Sold Out</span>';
        } else {
            statusHtml = '<span class="px-3 py-1 text-sm text-white rounded-full bg-white/20">Activ</span>';
        }
        document.getElementById('event-status').innerHTML = statusHtml;
    }

    // Overview stats
    if (data.overview) {
        document.getElementById('stat-revenue').textContent = formatCurrency(data.overview.total_revenue || 0);
        document.getElementById('stat-tickets').textContent = formatNumber(data.overview.tickets_sold || 0);
        document.getElementById('stat-views').textContent = formatNumber(data.overview.page_views || 0);
        document.getElementById('stat-conversion').textContent = (data.overview.conversion_rate || 0).toFixed(1) + '%';

        // Changes
        updateChangeIndicator('stat-revenue-change', data.overview.revenue_change);
        updateChangeIndicator('stat-tickets-change', data.overview.tickets_change);
        updateChangeIndicator('stat-views-change', data.overview.views_change);
    }

    // Chart
    if (data.chart) {
        updateSalesChart(data.chart);
    }

    // Traffic
    if (data.traffic_sources) {
        updateTrafficSources(data.traffic_sources);
    }

    // Ticket types
    if (data.ticket_performance) {
        updateTicketTypes(data.ticket_performance);
    }

    // Top locations
    if (data.top_locations) {
        updateLocations(data.top_locations);
    }

    // Recent sales
    if (data.recent_sales) {
        updateRecentSales(data.recent_sales);
    }
}

function updateSalesChart(chartData) {
    const ctx = document.getElementById('salesChart').getContext('2d');

    if (salesChart) {
        salesChart.destroy();
    }

    salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: chartData.labels || [],
            datasets: [{
                label: 'Venituri (RON)',
                data: chartData.revenue || [],
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.4,
                yAxisID: 'y'
            }, {
                label: 'Bilete',
                data: chartData.tickets || [],
                borderColor: '#10b981',
                backgroundColor: 'transparent',
                borderDash: [5, 5],
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', display: true, position: 'left', grid: { color: '#f1f5f9' } },
                y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } },
                x: { grid: { display: false } }
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function updateTrafficSources(sources) {
    const ctx = document.getElementById('trafficChart').getContext('2d');

    if (trafficChart) {
        trafficChart.destroy();
    }

    const colors = {
        'Facebook': '#1877f2',
        'Google': '#ea4335',
        'Instagram': '#e4405f',
        'TikTok': '#000000',
        'Direct': '#6b7280',
        'Email': '#f59e0b',
        'Other': '#10b981'
    };

    const labels = sources.map(s => s.source || 'Direct');
    const data = sources.map(s => s.revenue || s.visitors || 0);
    const backgroundColors = labels.map(l => colors[l] || colors['Other']);

    trafficChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: backgroundColors,
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Update list
    const listHtml = sources.map(s => `
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-2">
                <div class="w-3 h-3 rounded-full" style="background-color: ${colors[s.source] || colors['Other']}"></div>
                <span class="text-sm text-secondary">${s.source || 'Direct'}</span>
            </div>
            <div class="text-right">
                <span class="text-sm font-medium text-secondary">${formatCurrency(s.revenue || 0)}</span>
                <span class="ml-2 text-xs text-muted">${s.visitors || 0} vizite</span>
            </div>
        </div>
    `).join('');
    document.getElementById('traffic-list').innerHTML = listHtml;
}

function updateMilestones(milestones) {
    const container = document.getElementById('milestones-timeline');
    const noMilestones = document.getElementById('no-milestones');

    if (!milestones || milestones.length === 0) {
        container.innerHTML = '';
        noMilestones.classList.remove('hidden');
        return;
    }

    noMilestones.classList.add('hidden');

    const typeColors = {
        'facebook_ads': 'bg-blue-500',
        'google_ads': 'bg-red-500',
        'instagram_ads': 'bg-pink-500',
        'email_campaign': 'bg-amber-500',
        'price_change': 'bg-green-500',
        'ticket_release': 'bg-purple-500',
        'other': 'bg-gray-500'
    };

    const html = milestones.map(m => `
        <div class="relative pb-4 pl-8 border-l-2 border-border last:border-l-0 last:pb-0">
            <div class="absolute -left-2 top-0 w-4 h-4 rounded-full ${typeColors[m.type] || 'bg-gray-500'}"></div>
            <div class="p-4 bg-surface rounded-xl">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <h3 class="font-semibold text-secondary">${m.name}</h3>
                        <p class="text-xs text-muted">${m.start_date}${m.end_date ? ' - ' + m.end_date : ''}</p>
                    </div>
                    ${m.is_active ? '<span class="px-2 py-0.5 bg-success/10 text-success text-xs rounded-full">Activ</span>' : ''}
                </div>
                ${m.metrics ? `
                <div class="grid grid-cols-3 gap-2 mt-3">
                    <div class="text-center">
                        <p class="text-lg font-bold text-secondary">${formatNumber(m.metrics.tickets_sold || 0)}</p>
                        <p class="text-xs text-muted">Bilete</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-secondary">${formatCurrency(m.metrics.revenue || 0)}</p>
                        <p class="text-xs text-muted">Venituri</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-secondary">${m.metrics.roi ? m.metrics.roi.toFixed(1) + 'x' : '-'}</p>
                        <p class="text-xs text-muted">ROI</p>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function updateGoals(goals) {
    const container = document.getElementById('goals-list');
    const noGoals = document.getElementById('no-goals');

    if (!goals || goals.length === 0) {
        container.innerHTML = '';
        noGoals.classList.remove('hidden');
        return;
    }

    noGoals.classList.add('hidden');

    const typeLabels = {
        'revenue': 'Venituri',
        'tickets': 'Bilete',
        'visitors': 'Vizitatori',
        'conversion_rate': 'Conversie'
    };

    const statusColors = {
        'achieved': 'bg-success text-success',
        'on_track': 'bg-primary text-primary',
        'at_risk': 'bg-amber-500 text-amber-500',
        'missed': 'bg-red-500 text-red-500',
        'pending': 'bg-gray-400 text-gray-500'
    };

    const html = goals.map(g => `
        <div class="p-4 border rounded-xl border-border">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-medium text-secondary">${g.name || typeLabels[g.type] || g.type}</span>
                <span class="text-xs px-2 py-0.5 rounded-full ${statusColors[g.status]?.replace('text-', 'bg-').replace('bg-', 'bg-') + '/10'} ${statusColors[g.status]?.split(' ')[1] || ''}">${g.status}</span>
            </div>
            <div class="flex items-baseline gap-1 mb-2">
                <span class="text-xl font-bold text-secondary">${g.formatted_current || 0}</span>
                <span class="text-sm text-muted">/ ${g.formatted_target || 0}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-surface">
                <div class="h-full rounded-full transition-all ${g.progress_percent >= 100 ? 'bg-success' : g.progress_percent >= 75 ? 'bg-primary' : g.progress_percent >= 50 ? 'bg-amber-500' : 'bg-red-400'}" style="width: ${Math.min(g.progress_percent || 0, 100)}%"></div>
            </div>
            <div class="flex items-center justify-between mt-2">
                <span class="text-xs text-muted">${(g.progress_percent || 0).toFixed(1)}%</span>
                ${g.days_remaining > 0 ? `<span class="text-xs text-muted">${g.days_remaining} zile ramase</span>` : ''}
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function updateTicketTypes(tickets) {
    const container = document.getElementById('ticket-types-list');

    if (!tickets || tickets.length === 0) {
        container.innerHTML = '<p class="py-4 text-sm text-center text-muted">Nu exista tipuri de bilete</p>';
        return;
    }

    const html = tickets.map(t => `
        <div class="flex items-center justify-between py-2">
            <div>
                <p class="text-sm font-medium text-secondary">${t.name}</p>
                <p class="text-xs text-muted">${formatCurrency(t.price)} / bilet</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-secondary">${t.sold || 0}</p>
                <p class="text-xs text-muted">${t.capacity ? `din ${t.capacity}` : 'vandute'}</p>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function updateLocations(locations) {
    const container = document.getElementById('locations-list');

    if (!locations || locations.length === 0) {
        container.innerHTML = '<p class="py-4 text-sm text-center text-muted">Nu exista date</p>';
        return;
    }

    const html = locations.slice(0, 5).map((l, i) => `
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-2">
                <span class="flex items-center justify-center w-5 h-5 text-xs font-medium rounded bg-surface">${i + 1}</span>
                <span class="text-sm text-secondary">${l.city || l.country || 'Necunoscut'}</span>
            </div>
            <span class="text-sm font-medium text-secondary">${l.visitors || l.count || 0}</span>
        </div>
    `).join('');

    container.innerHTML = html;
}

function updateRecentSales(sales) {
    const container = document.getElementById('recent-sales');

    if (!sales || sales.length === 0) {
        container.innerHTML = '<p class="py-4 text-sm text-center text-muted">Nu exista vanzari recente</p>';
        return;
    }

    const html = sales.slice(0, 5).map(s => `
        <div class="flex items-center justify-between py-2 border-b border-border last:border-b-0">
            <div>
                <p class="text-sm font-medium text-secondary">${s.buyer_name || 'Client'}</p>
                <p class="text-xs text-muted">${s.time_ago || s.created_at}</p>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold text-success">+${formatCurrency(s.amount || 0)}</p>
                <p class="text-xs text-muted">${s.tickets || 1} bilet(e)</p>
            </div>
        </div>
    `).join('');

    container.innerHTML = html;
}

function updateChangeIndicator(elementId, change) {
    const el = document.getElementById(elementId);
    if (!el) return;

    const value = parseFloat(change) || 0;
    if (value >= 0) {
        el.textContent = '+' + value.toFixed(1) + '%';
        el.className = 'text-xs font-medium text-success bg-success/10 px-2 py-1 rounded-full';
    } else {
        el.textContent = value.toFixed(1) + '%';
        el.className = 'text-xs font-medium text-red-500 bg-red-500/10 px-2 py-1 rounded-full';
    }
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ro-RO', { style: 'decimal', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(value) + ' lei';
}

function formatNumber(value) {
    return new Intl.NumberFormat('ro-RO').format(value);
}

function refreshData() {
    loadAnalytics();
}

// Export functions
async function exportCsv() {
    window.open(`/api/organizer/events/${eventId}/analytics/export/csv?period=${currentPeriod}`, '_blank');
}

async function exportPdf() {
    window.open(`/api/organizer/events/${eventId}/analytics/export/pdf?period=${currentPeriod}`, '_blank');
}

// Goal modal
function showAddGoalModal() {
    document.getElementById('goal-modal').classList.remove('hidden');
}

function closeGoalModal() {
    document.getElementById('goal-modal').classList.add('hidden');
    document.getElementById('goal-form').reset();
}

async function saveGoal(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        type: form.type.value,
        target_value: parseFloat(form.target_value.value),
        deadline: form.deadline.value || null,
        email_alerts: form.email_alerts.checked,
        alert_thresholds: [25, 50, 75, 90, 100]
    };

    try {
        const response = await AmbiletAPI.post(`/organizer/events/${eventId}/goals`, data);

        if (response.success) {
            closeGoalModal();
            loadAnalytics();
        }
    } catch (error) {
        console.error('Error saving goal:', error);
    }
}

// Milestone modal
function showAddMilestoneModal() {
    document.getElementById('milestone-modal').classList.remove('hidden');
}

function closeMilestoneModal() {
    document.getElementById('milestone-modal').classList.add('hidden');
    document.getElementById('milestone-form').reset();
}

async function saveMilestone(e) {
    e.preventDefault();
    const form = e.target;
    const data = {
        name: form.name.value,
        type: form.type.value,
        start_date: form.start_date.value,
        end_date: form.end_date.value || null,
        budget: form.budget.value ? parseFloat(form.budget.value) : null,
        utm_source: form.utm_source.value || null
    };

    try {
        const response = await AmbiletAPI.post(`/organizer/events/${eventId}/milestones`, data);

        if (response.success) {
            closeMilestoneModal();
            loadAnalytics();
        }
    } catch (error) {
        console.error('Error saving milestone:', error);
    }
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
