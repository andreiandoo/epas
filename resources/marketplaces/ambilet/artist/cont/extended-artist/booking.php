<?php
/**
 * Extended Artist — Booking Marketplace (Modulul 4).
 *
 * Inbox cereri + Listingul meu + Calendar + Contracte. Reviews & Stats placeholder.
 * State: Alpine.js. API: /api/proxy.php?action=artist.booking.*
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Premium — Booking Marketplace';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<style>
    .bk-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.875rem; transition: all 0.15s; cursor: pointer; border: none; }
    .bk-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .bk-btn-primary { background: #A51C30; color: white; }
    .bk-btn-primary:hover:not(:disabled) { background: #8B1728; }
    .bk-btn-secondary { background: white; color: #1E293B; border: 1px solid #E2E8F0; }
    .bk-btn-secondary:hover:not(:disabled) { background: #F8FAFC; }
    .bk-btn-success { background: #16A34A; color: white; }
    .bk-btn-success:hover:not(:disabled) { background: #15803D; }
    .bk-btn-danger { background: #DC2626; color: white; }
    .bk-btn-danger:hover:not(:disabled) { background: #B91C1C; }
    .bk-btn-sm { padding: 0.4rem 0.875rem; font-size: 0.8125rem; }
    .bk-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.5rem; font-size: 0.875rem; background: white; }
    .bk-input:focus { outline: none; border-color: #A51C30; box-shadow: 0 0 0 3px rgba(165,28,48,0.1); }
    .bk-textarea { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.5rem; font-size: 0.875rem; background: white; resize: vertical; min-height: 80px; }
    .pro-badge { background: linear-gradient(135deg, #E67E22, #A51C30); color: white; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 0.25rem; letter-spacing: 0.5px; }
    .status-dot { display: inline-block; width: 0.5rem; height: 0.5rem; border-radius: 9999px; }
    .calendar-cell { aspect-ratio: 1; display: flex; align-items: center; justify-content: center; border-radius: 0.5rem; cursor: pointer; font-size: 0.8125rem; font-weight: 500; transition: background-color 0.1s; user-select: none; }
    .calendar-cell:hover:not(.cal-empty) { background: #F1F5F9; }
    .calendar-cell.cal-empty { cursor: default; opacity: 0; pointer-events: none; }
    .calendar-cell.cal-blocked { background: #FEE2E2; color: #991B1B; }
    .calendar-cell.cal-blocked:hover { background: #FECACA; }
    .calendar-cell.cal-today { outline: 2px solid #A51C30; outline-offset: -2px; }
    .calendar-cell.cal-past { color: #94A3B8; }
    .thread-msg-artist { background: #FEF2F2; border-color: #FECACA; }
    .thread-msg-guest { background: #F1F5F9; border-color: #E2E8F0; }
</style>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="min-h-screen pt-16 lg:ml-64 lg:pt-0" x-data="bookingApp()" x-init="init()" x-cloak>
    <div class="p-4 lg:p-8">

        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="pro-badge">PRO</span>
                <span class="text-xs font-semibold tracking-wider uppercase text-muted">Extended Artist · Booking</span>
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold lg:text-3xl text-secondary">Booking Marketplace</h1>
                    <p class="mt-1 text-muted">Primești cereri de booking, negociezi termenii, confirmi colaborarea — totul într-un singur loc.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-muted">Status listing:</span>
                    <span x-show="listing.status === 'active'" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-emerald-700 rounded-full bg-emerald-100">
                        <span class="status-dot bg-emerald-500"></span> Activ
                    </span>
                    <span x-show="listing.status !== 'active'" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold text-slate-700 rounded-full bg-slate-100">
                        <span class="status-dot bg-slate-400"></span> În pauză
                    </span>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <div x-show="loading" class="p-12 text-center bg-white border rounded-2xl border-border text-muted">
            <div class="inline-flex items-center gap-3">
                <span class="inline-block w-5 h-5 border-2 rounded-full border-primary border-t-transparent animate-spin"></span>
                <span>Se încarcă datele...</span>
            </div>
        </div>

        <div x-show="!loading">
            <!-- KPIs -->
            <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-4">
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Cereri active</p>
                    <p class="text-2xl font-bold text-secondary" x-text="kpis.active ?? 0"></p>
                    <p class="mt-1 text-xs text-muted">
                        <span x-text="(kpis.new ?? 0) + ' noi'"></span> · <span x-text="(kpis.negotiating ?? 0) + ' în negociere'"></span>
                    </p>
                </div>
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Booking-uri an curent</p>
                    <p class="text-2xl font-bold text-secondary" x-text="kpis.this_year ?? 0"></p>
                    <p class="mt-1 text-xs" :class="(kpis.year_trend ?? 0) >= 0 ? 'text-success' : 'text-error'">
                        <span x-text="((kpis.year_trend ?? 0) >= 0 ? '+' : '') + (kpis.year_trend ?? 0) + '%'"></span> vs anul trecut
                    </p>
                </div>
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Acceptance rate</p>
                    <p class="text-2xl font-bold text-secondary" x-text="(kpis.acceptance_rate ?? 0) + '%'"></p>
                    <p class="mt-1 text-xs text-muted" x-text="(kpis.total_decided ?? 0) + ' cereri decise'"></p>
                </div>
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Răspuns mediu</p>
                    <p class="text-2xl font-bold text-secondary">
                        <span x-show="kpis.avg_response_hours !== null && kpis.avg_response_hours !== undefined" x-text="(kpis.avg_response_hours ?? 0) + 'h'"></span>
                        <span x-show="kpis.avg_response_hours === null || kpis.avg_response_hours === undefined" class="text-muted">—</span>
                    </p>
                    <p class="mt-1 text-xs text-muted" x-text="'Țintă: ' + (listing.response_target_hours ?? 24) + 'h'"></p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="mb-6 overflow-hidden bg-white border rounded-2xl border-border">
                <div class="overflow-x-auto border-b border-border">
                    <div class="flex gap-1 p-2 min-w-max">
                        <template x-for="t in tabs" :key="t.id">
                            <button @click="setTab(t.id)"
                                    :class="tab === t.id ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-secondary'"
                                    class="px-4 py-2 text-sm font-medium transition-colors rounded-lg whitespace-nowrap">
                                <span x-text="t.label"></span>
                                <span x-show="t.id === 'inbox' && (kpis.new ?? 0) > 0" class="ml-1.5 px-1.5 py-0.5 text-xs font-bold rounded-full bg-amber-400 text-amber-900" x-text="kpis.new"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: INBOX ============ -->
            <div x-show="tab === 'inbox'" class="space-y-4">
                <!-- Filter bar -->
                <div class="flex flex-col gap-3 p-4 bg-white border lg:flex-row lg:items-center rounded-2xl border-border">
                    <div class="flex flex-wrap gap-1">
                        <template x-for="f in inboxFilters" :key="f.id">
                            <button @click="setInboxFilter(f.id)"
                                    :class="inboxFilter === f.id ? 'bg-primary text-white' : 'bg-surface text-muted hover:text-secondary'"
                                    class="px-3 py-1.5 text-sm font-medium rounded-lg transition-colors">
                                <span x-text="f.label"></span>
                            </button>
                        </template>
                    </div>
                    <div class="lg:ml-auto lg:w-72">
                        <input type="text" x-model="inboxSearch" @input.debounce.400ms="loadInbox()"
                               placeholder="Caută după nume, oraș, venue..."
                               class="bk-input">
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-12">
                    <!-- List column -->
                    <div class="lg:col-span-5">
                        <div x-show="inboxLoading" class="p-6 text-center bg-white border rounded-2xl border-border text-muted">
                            <span class="inline-block w-5 h-5 border-2 rounded-full border-primary border-t-transparent animate-spin"></span>
                        </div>

                        <div x-show="!inboxLoading && inbox.length === 0" class="p-8 text-center bg-white border rounded-2xl border-border">
                            <p class="text-sm font-semibold text-secondary">Nicio cerere</p>
                            <p class="mt-1 text-xs text-muted">Nu există cereri în categoria selectată.</p>
                        </div>

                        <div x-show="!inboxLoading && inbox.length > 0" class="space-y-2">
                            <template x-for="r in inbox" :key="r.id">
                                <button @click="openRequest(r.id)"
                                        :class="currentRequest && currentRequest.id === r.id ? 'border-primary ring-2 ring-primary/20' : 'border-border hover:border-slate-300'"
                                        class="w-full p-4 text-left transition-all bg-white border rounded-2xl">
                                    <div class="flex items-start gap-3">
                                        <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 text-sm font-bold text-white rounded-full bg-primary" x-text="r.guest.initials"></div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-secondary truncate" x-text="r.guest.name"></p>
                                                    <p class="text-xs text-muted truncate" x-show="r.guest.company" x-text="r.guest.company"></p>
                                                </div>
                                                <span :class="statusBadgeClass(r.status)" class="px-2 py-0.5 text-[11px] font-semibold rounded-full whitespace-nowrap">
                                                    <span x-text="statusLabel(r.status)"></span>
                                                </span>
                                            </div>
                                            <div class="mt-2 text-xs text-muted">
                                                <span x-text="r.event.date"></span>
                                                <span x-show="r.event.city"> · <span x-text="r.event.city"></span></span>
                                                <span x-show="r.event.fee_ron"> · <span x-text="formatNumber(r.event.fee_ron) + ' RON'"></span></span>
                                            </div>
                                            <p class="mt-2 text-xs text-muted line-clamp-2" x-text="r.preview"></p>
                                            <p class="mt-2 text-[11px] text-muted" x-text="r.received_ago"></p>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Detail column -->
                    <div class="lg:col-span-7">
                        <div x-show="!currentRequest && !requestLoading" class="p-8 text-center bg-white border rounded-2xl border-border">
                            <p class="text-sm font-semibold text-secondary">Nicio cerere selectată</p>
                            <p class="mt-1 text-xs text-muted">Selectează o cerere din listă pentru a vedea detaliile.</p>
                        </div>

                        <div x-show="requestLoading" class="p-8 text-center bg-white border rounded-2xl border-border text-muted">
                            <span class="inline-block w-5 h-5 border-2 rounded-full border-primary border-t-transparent animate-spin"></span>
                        </div>

                        <div x-show="currentRequest && !requestLoading" class="bg-white border rounded-2xl border-border">
                            <!-- Detail header -->
                            <div class="p-5 border-b border-border">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-bold text-secondary" x-text="currentRequest && currentRequest.guest ? currentRequest.guest.name : ''"></h3>
                                        <p class="text-sm text-muted" x-text="currentRequest && currentRequest.guest ? (currentRequest.guest.company || '—') : ''"></p>
                                        <p class="mt-1 text-xs text-muted">
                                            <span x-show="currentRequest && currentRequest.guest && currentRequest.guest.email">📧 <span x-text="currentRequest && currentRequest.guest ? currentRequest.guest.email : ''"></span></span>
                                            <span x-show="currentRequest && currentRequest.guest && currentRequest.guest.phone"> · 📞 <span x-text="currentRequest && currentRequest.guest ? currentRequest.guest.phone : ''"></span></span>
                                        </p>
                                    </div>
                                    <span :class="currentRequest ? statusBadgeClass(currentRequest.status) : ''" class="px-2.5 py-1 text-xs font-semibold rounded-full whitespace-nowrap">
                                        <span x-text="currentRequest ? statusLabel(currentRequest.status) : ''"></span>
                                    </span>
                                </div>

                                <div class="grid grid-cols-2 gap-3 mt-4 lg:grid-cols-4">
                                    <div>
                                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Dată</p>
                                        <p class="mt-1 text-sm font-semibold text-secondary" x-text="currentRequest && currentRequest.event ? currentRequest.event.date : ''"></p>
                                        <p class="text-xs text-muted" x-show="currentRequest && currentRequest.event && currentRequest.event.time" x-text="currentRequest && currentRequest.event ? currentRequest.event.time : ''"></p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Locație</p>
                                        <p class="mt-1 text-sm font-semibold text-secondary" x-text="currentRequest && currentRequest.event ? (currentRequest.event.city + (currentRequest.event.country ? ', ' + currentRequest.event.country : '')) : ''"></p>
                                        <p class="text-xs text-muted" x-show="currentRequest && currentRequest.event && currentRequest.event.venue" x-text="currentRequest && currentRequest.event ? currentRequest.event.venue : ''"></p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Tip</p>
                                        <p class="mt-1 text-sm font-semibold text-secondary" x-text="currentRequest && currentRequest.event ? eventTypeLabel(currentRequest.event.type) : ''"></p>
                                        <p class="text-xs text-muted" x-show="currentRequest && currentRequest.event && currentRequest.event.audience" x-text="currentRequest && currentRequest.event ? (formatNumber(currentRequest.event.audience) + ' audiență') : ''"></p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Cachet propus</p>
                                        <p class="mt-1 text-sm font-bold text-primary" x-text="currentRequest && currentRequest.event ? (formatNumber(currentRequest.event.fee_ron) + ' RON') : ''"></p>
                                        <p class="text-xs text-muted" x-show="currentRequest && currentRequest.event && currentRequest.event.set_length_min" x-text="currentRequest && currentRequest.event ? (currentRequest.event.set_length_min + ' min set') : ''"></p>
                                    </div>
                                </div>

                                <div class="mt-4" x-show="currentRequest && currentRequest.event && (currentRequest.event.conditions || []).length">
                                    <p class="text-[11px] font-semibold tracking-wider uppercase text-muted">Condiții cerute</p>
                                    <div class="flex flex-wrap gap-1.5 mt-2">
                                        <template x-for="c in (currentRequest && currentRequest.event ? (currentRequest.event.conditions || []) : [])" :key="c">
                                            <span class="px-2 py-0.5 text-[11px] rounded-full bg-blue-50 text-blue-700 border border-blue-200" x-text="conditionLabel(c)"></span>
                                        </template>
                                    </div>
                                </div>

                                <!-- Final terms (when accepted) -->
                                <div x-show="currentRequest && currentRequest.status === 'accepted' && currentRequest.final_terms" class="p-4 mt-4 border rounded-xl bg-emerald-50 border-emerald-200">
                                    <p class="text-xs font-bold tracking-wider uppercase text-emerald-700">✓ Termeni acceptați</p>
                                    <div class="grid grid-cols-2 gap-3 mt-2 text-sm lg:grid-cols-3">
                                        <div>
                                            <p class="text-xs text-emerald-700">Dată</p>
                                            <p class="font-semibold text-emerald-900" x-text="currentRequest && currentRequest.final_terms ? (currentRequest.final_terms.event_date || '') : ''"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-emerald-700">Cachet</p>
                                            <p class="font-semibold text-emerald-900" x-text="currentRequest && currentRequest.final_terms ? (formatNumber(currentRequest.final_terms.fee_ron) + ' RON') : ''"></p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-emerald-700">Set</p>
                                            <p class="font-semibold text-emerald-900" x-text="currentRequest && currentRequest.final_terms ? ((currentRequest.final_terms.set_length_min || 0) + ' min') : ''"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Initial message + Thread -->
                            <div class="p-5 space-y-3 max-h-[480px] overflow-y-auto">
                                <!-- Initial message from guest -->
                                <div class="p-4 border rounded-xl thread-msg-guest">
                                    <div class="flex items-center gap-2 mb-2">
                                        <div class="flex items-center justify-center w-7 h-7 text-xs font-bold text-white rounded-full bg-slate-500" x-text="currentRequest && currentRequest.guest ? currentRequest.guest.initials : ''"></div>
                                        <p class="text-sm font-semibold text-secondary" x-text="currentRequest && currentRequest.guest ? currentRequest.guest.name : ''"></p>
                                        <span class="text-xs text-muted">cererea inițială</span>
                                    </div>
                                    <p class="text-sm whitespace-pre-line text-secondary" x-text="currentRequest ? (currentRequest.initial_message || '') : ''"></p>
                                </div>

                                <!-- Thread -->
                                <template x-for="m in (currentRequest ? (currentRequest.thread || []) : [])" :key="m.id">
                                    <div :class="m.sender_type === 'artist' ? 'thread-msg-artist' : 'thread-msg-guest'" class="p-4 border rounded-xl">
                                        <div class="flex items-center gap-2 mb-2">
                                            <div :class="m.sender_type === 'artist' ? 'bg-primary' : 'bg-slate-500'" class="flex items-center justify-center w-7 h-7 text-xs font-bold text-white rounded-full" x-text="m.initials"></div>
                                            <p class="text-sm font-semibold text-secondary" x-text="m.sender_type === 'artist' ? 'Tu' : (currentRequest && currentRequest.guest ? currentRequest.guest.name : '')"></p>
                                            <span x-show="m.type === 'counter'" class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded-full bg-amber-100 text-amber-800">contraofertă</span>
                                            <span x-show="m.type === 'accept'" class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded-full bg-emerald-100 text-emerald-800">acceptat</span>
                                            <span x-show="m.type === 'reject'" class="px-2 py-0.5 text-[10px] font-bold tracking-wider uppercase rounded-full bg-rose-100 text-rose-800">refuzat</span>
                                            <span class="ml-auto text-[11px] text-muted" x-text="m.time"></span>
                                        </div>
                                        <p x-show="m.body" class="text-sm whitespace-pre-line text-secondary" x-text="m.body"></p>
                                        <div x-show="m.type === 'counter' && m.counter_terms" class="grid grid-cols-3 gap-3 p-3 mt-3 rounded-lg bg-white/60">
                                            <div x-show="m.counter_terms && m.counter_terms.fee_ron">
                                                <p class="text-[10px] tracking-wider uppercase text-muted">Cachet</p>
                                                <p class="text-sm font-bold text-secondary" x-text="(m.counter_terms ? formatNumber(m.counter_terms.fee_ron) : 0) + ' RON'"></p>
                                            </div>
                                            <div x-show="m.counter_terms && m.counter_terms.set_length_min">
                                                <p class="text-[10px] tracking-wider uppercase text-muted">Set</p>
                                                <p class="text-sm font-bold text-secondary" x-text="(m.counter_terms ? m.counter_terms.set_length_min : 0) + ' min'"></p>
                                            </div>
                                            <div x-show="m.counter_terms && m.counter_terms.event_date">
                                                <p class="text-[10px] tracking-wider uppercase text-muted">Dată</p>
                                                <p class="text-sm font-bold text-secondary" x-text="m.counter_terms ? m.counter_terms.event_date : ''"></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <!-- Reply / Counter / Accept / Reject panel -->
                            <div x-show="currentRequest && currentRequest.status !== 'accepted' && currentRequest.status !== 'rejected' && currentRequest.status !== 'expired'" class="p-5 border-t border-border bg-surface">
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <button @click="replyMode = 'message'" :class="replyMode === 'message' ? 'bk-btn-primary' : 'bk-btn-secondary'" class="bk-btn bk-btn-sm">💬 Mesaj</button>
                                    <button @click="replyMode = 'counter'" :class="replyMode === 'counter' ? 'bk-btn-primary' : 'bk-btn-secondary'" class="bk-btn bk-btn-sm">🔄 Contraofertă</button>
                                    <button @click="replyMode = 'accept'" :class="replyMode === 'accept' ? 'bk-btn-success' : 'bk-btn-secondary'" class="bk-btn bk-btn-sm">✓ Acceptă</button>
                                    <button @click="replyMode = 'reject'" :class="replyMode === 'reject' ? 'bk-btn-danger' : 'bk-btn-secondary'" class="bk-btn bk-btn-sm">✕ Refuză</button>
                                </div>

                                <!-- Message mode -->
                                <div x-show="replyMode === 'message'">
                                    <textarea x-model="replyBody" placeholder="Scrie un mesaj cumpărătorului..." class="bk-textarea"></textarea>
                                    <div class="flex justify-end mt-3">
                                        <button @click="sendMessage()" :disabled="!replyBody.trim() || sending" class="bk-btn bk-btn-primary">
                                            <span x-show="!sending">Trimite mesaj</span>
                                            <span x-show="sending">Se trimite...</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Counter mode -->
                                <div x-show="replyMode === 'counter'" class="space-y-3">
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                                        <div>
                                            <label class="block mb-1 text-xs font-semibold text-muted">Cachet propus (RON)</label>
                                            <input type="number" x-model.number="counterFee" min="0" step="100" class="bk-input">
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-xs font-semibold text-muted">Set length (min)</label>
                                            <input type="number" x-model.number="counterSetLength" min="15" max="600" step="5" class="bk-input">
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-xs font-semibold text-muted">Dată propusă</label>
                                            <input type="date" x-model="counterDate" class="bk-input">
                                        </div>
                                    </div>
                                    <textarea x-model="replyBody" placeholder="Notă pentru cumpărător (opțional)..." class="bk-textarea"></textarea>
                                    <div class="flex justify-end">
                                        <button @click="sendCounter()" :disabled="!counterFee || sending" class="bk-btn bk-btn-primary">
                                            <span x-show="!sending">Trimite contraofertă</span>
                                            <span x-show="sending">Se trimite...</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Accept mode -->
                                <div x-show="replyMode === 'accept'" class="space-y-3">
                                    <div class="p-3 border rounded-lg bg-emerald-50 border-emerald-200">
                                        <p class="text-sm font-semibold text-emerald-900">Vei accepta termenii curenți și booking-ul devine confirmat.</p>
                                        <p class="mt-1 text-xs text-emerald-700">Cumpărătorul va primi email cu detaliile finale. Plata se face în afara platformei conform contractului.</p>
                                    </div>
                                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                                        <div>
                                            <label class="block mb-1 text-xs font-semibold text-muted">Cachet final (RON)</label>
                                            <input type="number" x-model.number="acceptFee" min="0" step="100" class="bk-input">
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-xs font-semibold text-muted">Set length (min)</label>
                                            <input type="number" x-model.number="acceptSetLength" min="15" max="600" step="5" class="bk-input">
                                        </div>
                                        <div>
                                            <label class="block mb-1 text-xs font-semibold text-muted">Dată finală</label>
                                            <input type="date" x-model="acceptDate" class="bk-input">
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button @click="replyMode = 'message'" class="bk-btn bk-btn-secondary">Anulează</button>
                                        <button @click="acceptRequest()" :disabled="sending" class="bk-btn bk-btn-success">
                                            <span x-show="!sending">✓ Confirmă acceptare</span>
                                            <span x-show="sending">Se trimite...</span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Reject mode -->
                                <div x-show="replyMode === 'reject'" class="space-y-3">
                                    <div class="p-3 border rounded-lg bg-rose-50 border-rose-200">
                                        <p class="text-sm font-semibold text-rose-900">Vei refuza această cerere.</p>
                                        <p class="mt-1 text-xs text-rose-700">Cumpărătorul va primi email cu motivul refuzului (opțional).</p>
                                    </div>
                                    <textarea x-model="rejectReason" placeholder="Motiv (opțional)..." class="bk-textarea"></textarea>
                                    <div class="flex justify-end gap-2">
                                        <button @click="replyMode = 'message'" class="bk-btn bk-btn-secondary">Anulează</button>
                                        <button @click="rejectRequest()" :disabled="sending" class="bk-btn bk-btn-danger">
                                            <span x-show="!sending">✕ Confirmă refuz</span>
                                            <span x-show="sending">Se trimite...</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: LISTING ============ -->
            <div x-show="tab === 'listing'" class="space-y-4">
                <div class="p-5 bg-white border rounded-2xl border-border">
                    <div class="flex flex-col gap-3 mb-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Listingul meu public</h2>
                            <p class="text-sm text-muted">Setează cachetul, condițiile tehnice și tipurile de evenimente pe care le accepți.</p>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-sm font-medium text-secondary" x-text="listing.status === 'active' ? 'Listing activ' : 'Listing pe pauză'"></span>
                            <input type="checkbox" :checked="listing.status === 'active'" @change="listing.status = $event.target.checked ? 'active' : 'paused'" class="sr-only peer">
                            <div class="relative w-11 h-6 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-emerald-500 after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                        <!-- Cachet -->
                        <div>
                            <label class="block mb-2 text-sm font-semibold text-secondary">Cachet (RON)</label>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block mb-1 text-xs text-muted">Minim</label>
                                    <input type="number" x-model.number="listing.min_fee_ron" min="0" step="500" class="bk-input">
                                </div>
                                <div>
                                    <label class="block mb-1 text-xs text-muted">Maxim</label>
                                    <input type="number" x-model.number="listing.max_fee_ron" min="0" step="500" class="bk-input">
                                </div>
                            </div>
                            <label class="flex items-center gap-2 mt-2 cursor-pointer">
                                <input type="checkbox" x-model="listing.show_fee_publicly" class="rounded">
                                <span class="text-xs text-muted">Afișează intervalul public pe profilul meu</span>
                            </label>
                        </div>

                        <!-- Răspuns -->
                        <div>
                            <label class="block mb-2 text-sm font-semibold text-secondary">Țintă timp de răspuns (ore)</label>
                            <input type="number" x-model.number="listing.response_target_hours" min="1" max="168" step="1" class="bk-input">
                            <p class="mt-1 text-xs text-muted">Cumpărătorii văd această țintă pe formularul de cerere.</p>
                        </div>

                        <!-- Tipuri evenimente -->
                        <div class="lg:col-span-2">
                            <label class="block mb-2 text-sm font-semibold text-secondary">Tipuri de evenimente acceptate</label>
                            <div class="grid grid-cols-2 gap-2 lg:grid-cols-3">
                                <template x-for="t in availableEventTypes" :key="t.id">
                                    <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer border-border hover:bg-surface">
                                        <input type="checkbox" :value="t.id" x-model="listing.event_types" class="rounded">
                                        <span class="text-sm text-secondary" x-text="t.label"></span>
                                    </label>
                                </template>
                            </div>
                        </div>

                        <!-- Set & audience -->
                        <div>
                            <label class="block mb-2 text-sm font-semibold text-secondary">Set length standard (min)</label>
                            <input type="number" x-model.number="listing.standard_set_length_min" min="15" max="600" step="5" class="bk-input">
                        </div>

                        <div>
                            <label class="block mb-2 text-sm font-semibold text-secondary">Audiență</label>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="number" x-model.number="listing.standard_min_audience" min="0" placeholder="Min" class="bk-input">
                                <input type="number" x-model.number="listing.standard_max_audience" min="0" placeholder="Max" class="bk-input">
                            </div>
                        </div>

                        <!-- Conditii tehnice -->
                        <div class="lg:col-span-2">
                            <label class="block mb-2 text-sm font-semibold text-secondary">Condiții tehnice / logistice</label>
                            <div class="grid grid-cols-2 gap-2 lg:grid-cols-3">
                                <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer border-border hover:bg-surface">
                                    <input type="checkbox" x-model="listing.requires_soundcheck" class="rounded">
                                    <span class="text-sm text-secondary">Soundcheck obligatoriu</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer border-border hover:bg-surface">
                                    <input type="checkbox" x-model="listing.requires_backline" class="rounded">
                                    <span class="text-sm text-secondary">Backline asigurat</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer border-border hover:bg-surface">
                                    <input type="checkbox" x-model="listing.requires_catering" class="rounded">
                                    <span class="text-sm text-secondary">Catering / masă</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer border-border hover:bg-surface">
                                    <input type="checkbox" x-model="listing.requires_accommodation" class="rounded">
                                    <span class="text-sm text-secondary">Cazare</span>
                                </label>
                                <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer border-border hover:bg-surface">
                                    <input type="checkbox" x-model="listing.requires_transport" class="rounded">
                                    <span class="text-sm text-secondary">Transport</span>
                                </label>
                            </div>
                            <div x-show="listing.requires_soundcheck" class="mt-3">
                                <label class="block mb-1 text-xs text-muted">Soundcheck minim (minute)</label>
                                <input type="number" x-model.number="listing.soundcheck_min_minutes" min="0" max="600" step="15" class="bk-input lg:max-w-xs">
                            </div>
                        </div>

                        <!-- Distanta -->
                        <div>
                            <label class="block mb-2 text-sm font-semibold text-secondary">Distanță maximă acceptată (km)</label>
                            <input type="number" x-model.number="listing.max_distance_km" min="0" max="10000" step="50" class="bk-input">
                            <p class="mt-1 text-xs text-muted">Lasă gol pentru fără limită.</p>
                        </div>

                        <!-- Descriere -->
                        <div class="lg:col-span-2">
                            <label class="block mb-2 text-sm font-semibold text-secondary">Descriere publică</label>
                            <textarea x-model="listingDescriptionRo" rows="4" placeholder="Spune-le organizatorilor ce te diferențiază — gen, atmosferă, tipul de event preferat..." class="bk-textarea"></textarea>
                            <p class="mt-1 text-xs text-muted">Apare în formularul public de cerere.</p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-6">
                        <button @click="loadListing()" class="bk-btn bk-btn-secondary">Resetează</button>
                        <button @click="saveListing()" :disabled="savingListing" class="bk-btn bk-btn-primary">
                            <span x-show="!savingListing">Salvează listing</span>
                            <span x-show="savingListing">Se salvează...</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: CALENDAR ============ -->
            <div x-show="tab === 'calendar'" class="grid gap-4 lg:grid-cols-12">
                <!-- Calendar grid -->
                <div class="lg:col-span-8">
                    <div class="p-5 bg-white border rounded-2xl border-border">
                        <div class="flex items-center justify-between mb-4">
                            <button @click="calPrev()" class="p-2 rounded-lg hover:bg-surface" aria-label="Luna anterioară">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <h2 class="text-lg font-bold text-secondary" x-text="calMonthLabel()"></h2>
                            <button @click="calNext()" class="p-2 rounded-lg hover:bg-surface" aria-label="Luna următoare">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>

                        <div class="grid grid-cols-7 gap-1 mb-2 text-center">
                            <template x-for="d in ['L','Ma','Mi','J','V','S','D']" :key="d">
                                <div class="text-[11px] font-semibold tracking-wider uppercase text-muted" x-text="d"></div>
                            </template>
                        </div>
                        <div class="grid grid-cols-7 gap-1">
                            <template x-for="(c, idx) in calCells" :key="idx">
                                <div :class="calCellClass(c)" @click="onCalCellClick(c)">
                                    <span x-show="c.day" x-text="c.day"></span>
                                </div>
                            </template>
                        </div>

                        <p class="mt-4 text-xs text-muted">💡 Click pe o zi pentru a o marca ca indisponibilă (sau pentru a o debloca dacă e deja blocată).</p>
                    </div>
                </div>

                <!-- Block list -->
                <div class="lg:col-span-4">
                    <div class="p-5 bg-white border rounded-2xl border-border">
                        <h3 class="mb-3 text-base font-bold text-secondary">Zile blocate</h3>
                        <div x-show="!unavailableDates.length" class="p-3 text-sm text-center rounded-lg bg-surface text-muted">
                            Nicio zi blocată momentan.
                        </div>
                        <div x-show="unavailableDates.length" class="space-y-2 max-h-[420px] overflow-y-auto">
                            <template x-for="d in unavailableDates" :key="d.id">
                                <div class="flex items-start gap-2 p-3 border rounded-lg border-border">
                                    <div class="flex-shrink-0 w-1 h-10 rounded-full" :style="`background:${d.color || '#94A3B8'}`"></div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-secondary">
                                            <span x-text="formatDate(d.date_start)"></span>
                                            <span x-show="d.date_end && d.date_end !== d.date_start"> → <span x-text="formatDate(d.date_end)"></span></span>
                                        </p>
                                        <p x-show="d.reason" class="text-xs text-muted" x-text="d.reason"></p>
                                    </div>
                                    <button @click="removeUnavailable(d.id)" class="p-1 rounded hover:bg-rose-50 text-muted hover:text-rose-600" title="Șterge">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: CONTRACTE ============ -->
            <div x-show="tab === 'contracts'" class="space-y-4">
                <div class="p-5 bg-white border rounded-2xl border-border">
                    <h2 class="mb-1 text-lg font-bold text-secondary">Booking-uri confirmate</h2>
                    <p class="mb-5 text-sm text-muted">Toate cererile cu status acceptat. Conversația rămâne ca dovadă a termenilor agreați.</p>

                    <div x-show="!contracts.length" class="p-8 text-center rounded-xl bg-surface">
                        <p class="text-sm font-semibold text-secondary">Niciun booking confirmat încă</p>
                        <p class="mt-1 text-xs text-muted">După ce accepți o cerere, va apărea aici cu toate detaliile finale.</p>
                    </div>

                    <div x-show="contracts.length" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left border-b border-border">
                                    <th class="pb-3 text-xs font-semibold tracking-wider uppercase text-muted">Eveniment</th>
                                    <th class="pb-3 text-xs font-semibold tracking-wider uppercase text-muted">Organizator</th>
                                    <th class="pb-3 text-xs font-semibold tracking-wider uppercase text-muted">Locație</th>
                                    <th class="pb-3 text-xs font-semibold tracking-wider uppercase text-muted">Cachet</th>
                                    <th class="pb-3 text-xs font-semibold tracking-wider uppercase text-muted">Set</th>
                                    <th class="pb-3 text-xs font-semibold tracking-wider uppercase text-muted text-right">Acțiuni</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="c in contracts" :key="c.id">
                                    <tr class="border-b border-border hover:bg-surface">
                                        <td class="py-3 pr-3">
                                            <p class="font-semibold text-secondary" x-text="formatDate(c.event_date)"></p>
                                            <p class="text-xs text-muted" x-show="c.event_time" x-text="c.event_time"></p>
                                            <p class="text-xs text-muted" x-text="eventTypeLabel(c.event_type)"></p>
                                        </td>
                                        <td class="py-3 pr-3">
                                            <p class="font-semibold text-secondary" x-text="c.guest_name"></p>
                                            <p class="text-xs text-muted" x-show="c.guest_company" x-text="c.guest_company"></p>
                                        </td>
                                        <td class="py-3 pr-3">
                                            <p class="text-secondary" x-text="c.event_city || '—'"></p>
                                            <p class="text-xs text-muted" x-show="c.event_venue_name" x-text="c.event_venue_name"></p>
                                        </td>
                                        <td class="py-3 pr-3 font-bold text-primary" x-text="formatNumber(c.final_fee_ron) + ' RON'"></td>
                                        <td class="py-3 pr-3 text-secondary" x-text="(c.final_set_length_min || 0) + ' min'"></td>
                                        <td class="py-3 text-right">
                                            <button @click="setTab('inbox'); openRequest(c.id)" class="bk-btn bk-btn-secondary bk-btn-sm">Vezi conversația</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: REVIEWS (placeholder) ============ -->
            <div x-show="tab === 'reviews'" class="p-10 text-center bg-white border rounded-2xl border-border">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-amber-500/10">
                    <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-secondary">Reviews — În curând</h2>
                <p class="max-w-md mx-auto text-sm text-muted">Recenziile post-eveniment de la organizatori vor apărea aici. Sistem de rating + feedback structurat în pregătire.</p>
            </div>

            <!-- ============ TAB: STATS (placeholder) ============ -->
            <div x-show="tab === 'stats'" class="p-10 text-center bg-white border rounded-2xl border-border">
                <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 rounded-full bg-blue-500/10">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/></svg>
                </div>
                <h2 class="mb-2 text-xl font-bold text-secondary">Statistici avansate — În curând</h2>
                <p class="max-w-md mx-auto text-sm text-muted">Funnel cereri → acceptate, distribuție pe orașe, evoluție cachet, top organizatori — vine la sprintul următor.</p>
            </div>
        </div>

        <!-- Toast -->
        <div x-show="toast.message" x-transition class="fixed z-50 bottom-6 right-6">
            <div :class="toast.type === 'error' ? 'bg-rose-600' : (toast.type === 'success' ? 'bg-emerald-600' : 'bg-secondary')" class="flex items-center gap-2 px-4 py-3 text-sm font-medium text-white shadow-xl rounded-xl">
                <span x-text="toast.message"></span>
                <button @click="toast.message = ''" class="ml-2 opacity-70 hover:opacity-100">×</button>
            </div>
        </div>
    </div>
</main>

<script>
(function() {
    const token = localStorage.getItem('ambilet_artist_token');
    if (!token) { window.location.href = '/artist/login'; return; }
    fetch('/api/proxy.php?action=artist.extended-artist.status', { headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token } })
        .then(r => r.json())
        .then(payload => { if (payload?.data?.enabled !== true) window.location.href = '/artist/cont/extended-artist'; })
        .catch(() => { window.location.href = '/artist/cont/extended-artist'; });
})();

function bookingApp() {
    return {
        loading: true,
        tab: 'inbox',
        tabs: [
            { id: 'inbox', label: 'Inbox' },
            { id: 'listing', label: 'Listingul meu' },
            { id: 'calendar', label: 'Calendar' },
            { id: 'contracts', label: 'Contracte' },
            { id: 'reviews', label: 'Reviews' },
            { id: 'stats', label: 'Statistici' },
        ],
        kpis: { active: 0, new: 0, negotiating: 0, this_year: 0, last_year: 0, year_trend: 0, acceptance_rate: 0, avg_response_hours: null, total_decided: 0 },

        // Inbox state
        inbox: [],
        inboxFilter: 'all',
        inboxFilters: [
            { id: 'all', label: 'Toate' },
            { id: 'new', label: 'Noi' },
            { id: 'negotiating', label: 'Negociere' },
            { id: 'accepted', label: 'Acceptate' },
            { id: 'archive', label: 'Arhivă' },
        ],
        inboxSearch: '',
        inboxLoading: false,
        currentRequest: null,
        requestLoading: false,
        replyMode: 'message',
        replyBody: '',
        counterFee: 0,
        counterSetLength: 60,
        counterDate: '',
        acceptFee: 0,
        acceptSetLength: 60,
        acceptDate: '',
        rejectReason: '',
        sending: false,

        // Listing state
        listing: { status: 'paused', event_types: [], min_fee_ron: 0, max_fee_ron: 0, show_fee_publicly: false, requires_soundcheck: false, requires_backline: false, requires_catering: false, requires_accommodation: false, requires_transport: false, soundcheck_min_minutes: 60, standard_set_length_min: 60, standard_min_audience: null, standard_max_audience: null, max_distance_km: null, response_target_hours: 24, description: {} },
        listingDescriptionRo: '',
        savingListing: false,
        availableEventTypes: [
            { id: 'concert', label: 'Concert' },
            { id: 'festival', label: 'Festival' },
            { id: 'private', label: 'Eveniment privat' },
            { id: 'corporate', label: 'Corporate' },
            { id: 'wedding', label: 'Nuntă' },
            { id: 'club', label: 'Club / lounge' },
            { id: 'show', label: 'Show TV / online' },
            { id: 'charity', label: 'Caritate' },
        ],

        // Calendar state
        calYear: 0,
        calMonth: 0,
        calCells: [],
        unavailableDates: [],

        // Contracts
        contracts: [],

        toast: { message: '', type: 'info' },

        async init() {
            const url = new URL(window.location.href);
            const t = url.searchParams.get('tab');
            if (t && this.tabs.find(x => x.id === t)) this.tab = t;

            const today = new Date();
            this.calYear = today.getFullYear();
            this.calMonth = today.getMonth();
            this.buildCalendar();

            await Promise.all([
                this.loadKpis(),
                this.loadListing(),
                this.loadInbox(),
                this.loadCalendar(),
                this.loadContracts(),
            ]);

            const reqId = url.searchParams.get('request');
            if (reqId) {
                this.tab = 'inbox';
                this.openRequest(parseInt(reqId, 10));
            }

            this.loading = false;
        },

        setTab(id) {
            this.tab = id;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', id);
            window.history.replaceState({}, '', url);
        },

        // ---------------- API helpers ----------------
        async api(action, options = {}) {
            const tk = localStorage.getItem('ambilet_artist_token');
            const headers = Object.assign({
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + tk,
            }, options.headers || {});
            if (options.body && typeof options.body === 'object') {
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(options.body);
            }
            const url = '/api/proxy.php?action=' + encodeURIComponent(action) + (options.query ? '&' + options.query : '');
            const r = await fetch(url, Object.assign({}, options, { headers }));
            const text = await r.text();
            let data = null;
            try { data = JSON.parse(text); } catch (e) {}
            if (!r.ok) {
                const msg = data?.message || data?.error || `Eroare ${r.status}`;
                throw new Error(msg);
            }
            return data;
        },

        // ---------------- KPIs ----------------
        async loadKpis() {
            try {
                const d = await this.api('artist.booking.kpis');
                this.kpis = Object.assign(this.kpis, d.data || {});
            } catch (e) { console.warn('kpis', e); }
        },

        // ---------------- Listing ----------------
        async loadListing() {
            try {
                const d = await this.api('artist.booking.listing');
                const l = d.data || {};
                this.listing = {
                    status: l.status || 'paused',
                    min_fee_ron: l.min_fee_ron ?? 0,
                    max_fee_ron: l.max_fee_ron ?? 0,
                    show_fee_publicly: !!l.show_fee_publicly,
                    event_types: Array.isArray(l.event_types) ? l.event_types : [],
                    standard_set_length_min: l.standard_set_length_min ?? 60,
                    standard_min_audience: l.standard_min_audience,
                    standard_max_audience: l.standard_max_audience,
                    requires_soundcheck: !!l.requires_soundcheck,
                    soundcheck_min_minutes: l.soundcheck_min_minutes ?? 60,
                    requires_backline: !!l.requires_backline,
                    requires_catering: !!l.requires_catering,
                    requires_accommodation: !!l.requires_accommodation,
                    requires_transport: !!l.requires_transport,
                    description: l.description || {},
                    max_distance_km: l.max_distance_km,
                    response_target_hours: l.response_target_hours ?? 24,
                };
                this.listingDescriptionRo = (l.description && typeof l.description === 'object') ? (l.description.ro || '') : '';
            } catch (e) { this.showToast(e.message, 'error'); }
        },

        async saveListing() {
            this.savingListing = true;
            try {
                const payload = Object.assign({}, this.listing);
                payload.description = Object.assign({}, this.listing.description || {}, { ro: this.listingDescriptionRo });
                const d = await this.api('artist.booking.listing.update', { method: 'PATCH', body: payload });
                this.showToast(d.message || 'Listing actualizat.', 'success');
                await this.loadListing();
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.savingListing = false;
            }
        },

        // ---------------- Inbox ----------------
        setInboxFilter(id) {
            this.inboxFilter = id;
            this.loadInbox();
        },

        async loadInbox() {
            this.inboxLoading = true;
            try {
                const params = new URLSearchParams();
                if (this.inboxFilter && this.inboxFilter !== 'all') params.set('status', this.inboxFilter);
                if (this.inboxSearch) params.set('search', this.inboxSearch);
                const d = await this.api('artist.booking.inbox', { query: params.toString() });
                this.inbox = d.data?.requests || [];
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.inboxLoading = false;
            }
        },

        async openRequest(id) {
            this.requestLoading = true;
            this.replyMode = 'message';
            this.replyBody = '';
            try {
                const d = await this.api('artist.booking.request.show', { query: 'id=' + id });
                this.currentRequest = d.data;
                if (this.currentRequest && this.currentRequest.event) {
                    this.counterFee = this.currentRequest.event.fee_ron || 0;
                    this.counterSetLength = this.currentRequest.event.set_length_min || 60;
                    this.counterDate = this.currentRequest.event.date_iso || '';
                    this.acceptFee = this.currentRequest.event.fee_ron || 0;
                    this.acceptSetLength = this.currentRequest.event.set_length_min || 60;
                    this.acceptDate = this.currentRequest.event.date_iso || '';

                    // Pull latest counter terms (by either side) into accept defaults
                    const counters = (this.currentRequest.thread || []).filter(m => m.type === 'counter' && m.counter_terms);
                    if (counters.length) {
                        const last = counters[counters.length - 1].counter_terms;
                        if (last.fee_ron) this.acceptFee = last.fee_ron;
                        if (last.set_length_min) this.acceptSetLength = last.set_length_min;
                        if (last.event_date) this.acceptDate = last.event_date;
                    }
                }

                const url = new URL(window.location.href);
                url.searchParams.set('request', id);
                window.history.replaceState({}, '', url);

                // Refresh inbox to update unread/status indicators
                this.loadInbox();
                this.loadKpis();
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.requestLoading = false;
            }
        },

        async sendMessage() {
            if (!this.currentRequest) return;
            this.sending = true;
            try {
                await this.api('artist.booking.request.message', {
                    method: 'POST',
                    query: 'id=' + this.currentRequest.id,
                    body: { type: 'message', body: this.replyBody.trim() },
                });
                this.replyBody = '';
                this.showToast('Mesaj trimis.', 'success');
                await this.openRequest(this.currentRequest.id);
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.sending = false;
            }
        },

        async sendCounter() {
            if (!this.currentRequest) return;
            if (!this.counterFee || this.counterFee <= 0) {
                this.showToast('Cachetul propus trebuie să fie mai mare decât 0.', 'error');
                return;
            }
            this.sending = true;
            try {
                await this.api('artist.booking.request.message', {
                    method: 'POST',
                    query: 'id=' + this.currentRequest.id,
                    body: {
                        type: 'counter',
                        body: this.replyBody.trim() || null,
                        counter_terms: {
                            fee_ron: this.counterFee,
                            set_length_min: this.counterSetLength,
                            event_date: this.counterDate || undefined,
                        },
                    },
                });
                this.replyBody = '';
                this.showToast('Contraofertă trimisă.', 'success');
                await this.openRequest(this.currentRequest.id);
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.sending = false;
            }
        },

        async acceptRequest() {
            if (!this.currentRequest) return;
            if (!confirm('Ești sigur(ă) că vrei să accepți acest booking? Cumpărătorul va primi notificare.')) return;
            this.sending = true;
            try {
                await this.api('artist.booking.request.accept', {
                    method: 'POST',
                    query: 'id=' + this.currentRequest.id,
                    body: {
                        final_terms: {
                            fee_ron: this.acceptFee,
                            set_length_min: this.acceptSetLength,
                            event_date: this.acceptDate || undefined,
                        },
                    },
                });
                this.showToast('Booking acceptat.', 'success');
                await Promise.all([
                    this.openRequest(this.currentRequest.id),
                    this.loadContracts(),
                    this.loadKpis(),
                ]);
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.sending = false;
            }
        },

        async rejectRequest() {
            if (!this.currentRequest) return;
            if (!confirm('Ești sigur(ă) că vrei să refuzi această cerere?')) return;
            this.sending = true;
            try {
                await this.api('artist.booking.request.reject', {
                    method: 'POST',
                    query: 'id=' + this.currentRequest.id,
                    body: { reason: this.rejectReason.trim() || null },
                });
                this.rejectReason = '';
                this.showToast('Cerere refuzată.', 'success');
                await Promise.all([
                    this.openRequest(this.currentRequest.id),
                    this.loadKpis(),
                    this.loadInbox(),
                ]);
            } catch (e) {
                this.showToast(e.message, 'error');
            } finally {
                this.sending = false;
            }
        },

        // ---------------- Contracts ----------------
        async loadContracts() {
            try {
                const d = await this.api('artist.booking.contracts');
                this.contracts = d.data?.contracts || [];
            } catch (e) { console.warn('contracts', e); }
        },

        // ---------------- Calendar ----------------
        async loadCalendar() {
            try {
                const from = new Date(this.calYear, this.calMonth - 1, 1).toISOString().slice(0, 10);
                const to = new Date(this.calYear, this.calMonth + 2, 0).toISOString().slice(0, 10);
                const d = await this.api('artist.booking.calendar', { query: 'from=' + from + '&to=' + to });
                this.unavailableDates = d.data?.dates || [];
            } catch (e) {
                console.warn('calendar', e);
            }
        },

        buildCalendar() {
            const first = new Date(this.calYear, this.calMonth, 1);
            const startWeekday = (first.getDay() + 6) % 7; // Monday-first
            const lastDay = new Date(this.calYear, this.calMonth + 1, 0).getDate();
            const cells = [];
            for (let i = 0; i < startWeekday; i++) cells.push({ empty: true });
            const today = new Date();
            const todayIso = today.toISOString().slice(0, 10);
            for (let d = 1; d <= lastDay; d++) {
                const iso = `${this.calYear}-${String(this.calMonth + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                cells.push({
                    day: d,
                    iso,
                    isToday: iso === todayIso,
                    isPast: iso < todayIso,
                });
            }
            this.calCells = cells;
        },

        calMonthLabel() {
            const months = ['Ianuarie','Februarie','Martie','Aprilie','Mai','Iunie','Iulie','August','Septembrie','Octombrie','Noiembrie','Decembrie'];
            return months[this.calMonth] + ' ' + this.calYear;
        },

        async calPrev() {
            this.calMonth--;
            if (this.calMonth < 0) { this.calMonth = 11; this.calYear--; }
            this.buildCalendar();
            await this.loadCalendar();
        },

        async calNext() {
            this.calMonth++;
            if (this.calMonth > 11) { this.calMonth = 0; this.calYear++; }
            this.buildCalendar();
            await this.loadCalendar();
        },

        isDateBlocked(iso) {
            return this.unavailableDates.find(d => iso >= d.date_start && iso <= (d.date_end || d.date_start));
        },

        calCellClass(c) {
            if (c.empty) return 'calendar-cell cal-empty';
            const blocked = this.isDateBlocked(c.iso);
            const classes = ['calendar-cell'];
            if (blocked) classes.push('cal-blocked');
            if (c.isToday) classes.push('cal-today');
            if (c.isPast && !blocked) classes.push('cal-past');
            return classes.join(' ');
        },

        async onCalCellClick(c) {
            if (c.empty) return;
            const blocked = this.isDateBlocked(c.iso);
            if (blocked) {
                if (!confirm('Vrei să deblochezi data ' + this.formatDate(c.iso) + '?')) return;
                await this.removeUnavailable(blocked.id);
                return;
            }
            const reason = prompt('Marchează această zi ca indisponibilă. Motiv (opțional):', '');
            if (reason === null) return;
            try {
                await this.api('artist.booking.calendar.add', {
                    method: 'POST',
                    body: { date_start: c.iso, date_end: c.iso, reason: reason || null, color: '#DC2626' },
                });
                this.showToast('Dată blocată.', 'success');
                await this.loadCalendar();
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },

        async removeUnavailable(id) {
            try {
                await this.api('artist.booking.calendar.remove', { method: 'DELETE', query: 'id=' + id });
                this.showToast('Dată deblocată.', 'success');
                await this.loadCalendar();
            } catch (e) {
                this.showToast(e.message, 'error');
            }
        },

        // ---------------- Helpers ----------------
        statusBadgeClass(status) {
            const map = {
                new: 'bg-blue-100 text-blue-800',
                viewed: 'bg-slate-100 text-slate-700',
                negotiating: 'bg-amber-100 text-amber-800',
                accepted: 'bg-emerald-100 text-emerald-800',
                rejected: 'bg-rose-100 text-rose-800',
                expired: 'bg-slate-200 text-slate-600',
            };
            return map[status] || 'bg-slate-100 text-slate-700';
        },

        statusLabel(status) {
            const map = { new: 'Nouă', viewed: 'Văzută', negotiating: 'Negociere', accepted: 'Acceptată', rejected: 'Refuzată', expired: 'Expirată' };
            return map[status] || status;
        },

        eventTypeLabel(type) {
            const t = this.availableEventTypes.find(x => x.id === type);
            return t ? t.label : (type || '—');
        },

        conditionLabel(c) {
            const map = { soundcheck: 'Soundcheck', backline: 'Backline', catering: 'Catering', accommodation: 'Cazare', transport: 'Transport' };
            return map[c] || c;
        },

        formatNumber(n) {
            if (n === null || n === undefined || n === '') return '0';
            return new Intl.NumberFormat('ro-RO').format(n);
        },

        formatDate(iso) {
            if (!iso) return '';
            try {
                const d = new Date(iso + (iso.length === 10 ? 'T00:00:00' : ''));
                return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
            } catch (e) { return iso; }
        },

        showToast(message, type = 'info') {
            this.toast = { message, type };
            setTimeout(() => { if (this.toast.message === message) this.toast.message = ''; }, 4000);
        },
    };
}
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>
