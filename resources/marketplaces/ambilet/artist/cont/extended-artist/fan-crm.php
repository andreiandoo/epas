<?php
/**
 * Extended Artist — Fan CRM (Modulul 1)
 *
 * Audience analytics dashboard cu 8 tab-uri: Overview, Hartă, Segmente,
 * Listă fani, Cohort, Demografie, Comparații, VIP.
 *
 * State: Alpine.js. Charts: Chart.js. Map: Leaflet + leaflet-heat.
 * API: /api/proxy.php?action=artist.fan-crm.*
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Premium — Fan CRM';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<style>
    .fc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.875rem; transition: all 0.15s; cursor: pointer; border: none; }
    .fc-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .fc-btn-primary { background: #A51C30; color: white; }
    .fc-btn-primary:hover:not(:disabled) { background: #8B1728; }
    .fc-btn-secondary { background: white; color: #1E293B; border: 1px solid #E2E8F0; }
    .fc-btn-secondary:hover:not(:disabled) { background: #F8FAFC; }
    .fc-btn-sm { padding: 0.4rem 0.875rem; font-size: 0.8125rem; }
    .fc-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.5rem; font-size: 0.875rem; background: white; }
    .fc-input:focus { outline: none; border-color: #A51C30; box-shadow: 0 0 0 3px rgba(165,28,48,0.1); }
    .fc-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .pro-badge { background: linear-gradient(135deg, #E67E22, #A51C30); color: white; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 0.25rem; letter-spacing: 0.5px; }
    #fanMap { width: 100%; height: 480px; border-radius: 1rem; }
    .cohort-cell { padding: 0.5rem; text-align: center; font-size: 0.75rem; min-width: 60px; }
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="min-h-screen pt-16 lg:ml-64 lg:pt-0" x-data="fanCrm()" x-init="init()" x-cloak>
    <div class="p-4 lg:p-8">

        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="pro-badge">PRO</span>
                <span class="text-xs font-semibold tracking-wider uppercase text-muted">Extended Artist · Audience Analytics</span>
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold lg:text-3xl text-secondary">Fan CRM</h1>
                    <p class="mt-1 text-muted">Înțelegere profundă a publicului tău — cine sunt, unde locuiesc, ce iubesc</p>
                </div>
            </div>
        </div>

        <!-- GDPR notice -->
        <div class="flex items-start gap-2 p-3 mb-6 border border-blue-200 bg-blue-50 rounded-xl">
            <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <p class="text-xs text-blue-900">Date conforme GDPR — toate cifrele și hărțile sunt agregate. Datele individuale (Listă fani, VIP) folosesc doar fanii care au consimțit la partajare în ToS.</p>
        </div>

        <!-- Loading -->
        <div x-show="loading" class="p-12 text-center bg-white border rounded-2xl border-border text-muted">
            <div class="inline-flex items-center gap-3">
                <span class="inline-block w-5 h-5 border-2 rounded-full border-primary border-t-transparent animate-spin"></span>
                <span>Se încarcă datele...</span>
            </div>
        </div>

        <div x-show="!loading" class="relative">
            <!-- Per-tab loader overlay -->
            <div x-show="tabLoading" x-cloak class="absolute inset-0 z-10 flex items-center justify-center bg-white/70 backdrop-blur-sm rounded-2xl">
                <div class="inline-flex items-center gap-3 px-5 py-3 bg-white border shadow-lg border-border rounded-xl">
                    <span class="inline-block w-5 h-5 border-2 rounded-full border-primary border-t-transparent animate-spin"></span>
                    <span class="text-sm font-medium text-secondary">Se încarcă...</span>
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
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: OVERVIEW ============ -->
            <div x-show="tab === 'overview'" class="space-y-6">
                <!-- KPIs -->
                <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Total fani</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.total_fans)"></p>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Fani noi (6L)</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.new_fans)"></p>
                        <p class="mt-1 text-xs" :class="overview.kpis?.new_fans_trend >= 0 ? 'text-success' : 'text-error'">
                            <span x-text="(overview.kpis?.new_fans_trend >= 0 ? '+' : '') + (overview.kpis?.new_fans_trend ?? 0) + '%'"></span> vs perioada anterioară
                        </p>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Retenție</p>
                        <p class="text-2xl font-bold text-secondary" x-text="(overview.kpis?.retention_rate ?? 0) + '%'"></p>
                        <p class="mt-1 text-xs text-muted">2+ evenimente</p>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">LTV mediu</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.avg_ltv) + ' RON'"></p>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Orașe</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.cities_covered)"></p>
                    </div>
                </div>

                <!-- Growth + Fan Type Charts -->
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-1 font-bold text-secondary">Creștere fani (12 luni)</h3>
                        <p class="mb-4 text-sm text-muted">Fani noi vs revenire</p>
                        <div class="relative h-[260px]"><canvas id="growthChart"></canvas></div>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-1 font-bold text-secondary">Distribuție tipuri</h3>
                        <p class="mb-4 text-sm text-muted">VIP / Loiali / Noi / Dormiți</p>
                        <div class="relative h-[260px]"><canvas id="fanTypeChart"></canvas></div>
                    </div>
                </div>

                <!-- Top Cities + Countries + Dormant -->
                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-3 font-bold text-secondary">Top orașe</h3>
                        <div class="space-y-2">
                            <template x-for="(city, idx) in overview.top_cities || []" :key="idx">
                                <div class="flex items-center justify-between p-2 rounded-lg bg-surface">
                                    <div>
                                        <p class="text-sm font-semibold text-secondary" x-text="city.name"></p>
                                        <p class="text-xs text-muted" x-text="city.events + ' evenimente'"></p>
                                    </div>
                                    <span class="text-sm font-bold text-primary" x-text="formatNumber(city.fans)"></span>
                                </div>
                            </template>
                            <p x-show="!(overview.top_cities || []).length" class="text-sm text-muted">Niciun oraș încă.</p>
                        </div>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-3 font-bold text-secondary">Țări</h3>
                        <div class="space-y-2">
                            <template x-for="(country, idx) in overview.countries || []" :key="idx">
                                <div>
                                    <div class="flex items-center justify-between mb-1 text-sm">
                                        <span class="font-medium text-secondary"><span x-text="country.flag"></span> <span x-text="country.name"></span></span>
                                        <span class="text-muted"><span x-text="country.pct"></span>%</span>
                                    </div>
                                    <div class="h-2 overflow-hidden rounded-full bg-surface">
                                        <div class="h-full rounded-full bg-primary" :style="`width: ${country.pct}%`"></div>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!(overview.countries || []).length" class="text-sm text-muted">Date insuficiente.</p>
                        </div>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-3 font-bold text-secondary">🌙 Orașe „dormite"</h3>
                        <p class="mb-3 text-xs text-muted">Fani locali, dar nu ai mai cântat de mult</p>
                        <div class="space-y-2">
                            <template x-for="(c, idx) in overview.dormant_cities || []" :key="idx">
                                <div class="flex items-center justify-between p-2 border rounded-lg bg-warning/5 border-warning/20">
                                    <div>
                                        <p class="text-sm font-semibold text-secondary" x-text="c.name"></p>
                                        <p class="text-xs text-muted" x-text="c.fans + ' fani · ultimul concert ' + (c.lastEvent || '?')"></p>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!(overview.dormant_cities || []).length" class="text-sm text-muted">Niciun oraș dormit.</p>
                        </div>
                    </div>
                </div>

                <!-- Insights -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" x-show="(overview.insights || []).length">
                    <template x-for="(insight, idx) in overview.insights || []" :key="idx">
                        <div class="flex items-start gap-3 p-5 border bg-gradient-to-br from-accent/5 to-primary/5 border-accent/20 rounded-2xl">
                            <span class="flex-shrink-0 text-2xl" x-text="insight.icon"></span>
                            <div>
                                <p class="mb-1 font-bold text-secondary" x-text="insight.title"></p>
                                <p class="text-sm text-secondary" x-text="insight.text"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ============ TAB: MAP ============ -->
            <div x-show="tab === 'map'" class="space-y-4">
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-secondary">Hartă fani</h3>
                        <div class="inline-flex p-1 rounded-lg bg-surface">
                            <button @click="mapView='heat'; renderMap()" :class="mapView==='heat' ? 'bg-white shadow-sm text-secondary' : 'text-muted'" class="px-3 py-1 text-sm font-medium rounded">Heatmap</button>
                            <button @click="mapView='pins'; renderMap()" :class="mapView==='pins' ? 'bg-white shadow-sm text-secondary' : 'text-muted'" class="px-3 py-1 text-sm font-medium rounded">Pin-uri</button>
                        </div>
                    </div>
                    <div id="fanMap"></div>
                    <p class="mt-3 text-xs text-muted">Date din top <span x-text="(mapData.points || []).length"></span> orașe cu fani identificați</p>
                </div>
            </div>

            <!-- ============ TAB: SEGMENTS ============ -->
            <div x-show="tab === 'segments'" class="space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-secondary">Segmente predefinite</h3>
                        <span class="text-xs text-muted">Calcul automat live</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <template x-for="seg in segmentsData.predefined || []" :key="seg.id">
                            <div class="p-4 bg-white border border-border rounded-2xl">
                                <div class="flex items-start justify-between mb-2">
                                    <span class="fc-badge" :style="`background: ${seg.color}20; color: ${seg.color}`" x-text="seg.name"></span>
                                </div>
                                <p class="text-2xl font-bold text-secondary" x-text="formatNumber(segmentsData.counts?.[seg.id] ?? 0)"></p>
                                <p class="mt-1 text-xs text-muted" x-text="seg.description"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-secondary">Segmente personalizate</h3>
                        <button @click="openSegmentModal()" class="fc-btn fc-btn-primary fc-btn-sm">+ Segment nou</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-show="(segmentsData.custom || []).length">
                        <template x-for="seg in segmentsData.custom || []" :key="seg.id">
                            <div class="p-4 bg-white border border-border rounded-2xl">
                                <div class="flex items-start justify-between mb-2">
                                    <span class="fc-badge" :style="`background: ${seg.color}20; color: ${seg.color}`" x-text="seg.name"></span>
                                    <button @click="deleteSegment(seg.id)" class="text-muted hover:text-error">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                                <p class="text-sm text-secondary" x-text="seg.description || '—'"></p>
                                <button @click="setTab('fans'); fansFilters.custom_segment_id = seg.id; loadFans()" class="mt-3 text-sm font-medium text-primary hover:underline">Vezi fani →</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="!(segmentsData.custom || []).length" class="text-sm text-muted">Nu ai segmente personalizate. Creează unul cu „+ Segment nou".</p>
                </div>
            </div>

            <!-- ============ TAB: FANS LIST ============ -->
            <div x-show="tab === 'fans'" class="space-y-4">
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <input type="text" x-model="fansFilters.search" @input.debounce.500ms="loadFans()" placeholder="Caută nume/email/oraș..." class="fc-input" style="flex:1 1 200px; min-width:0">
                        <select x-model="fansFilters.segment" @change="loadFans()" class="fc-input" style="width:200px; flex:0 0 200px">
                            <option value="">Toate segmentele</option>
                            <template x-for="seg in segmentsData.predefined || []" :key="seg.id">
                                <option :value="seg.id" x-text="seg.name"></option>
                            </template>
                        </select>
                        <a :href="exportUrl()" target="_blank" class="fc-btn fc-btn-secondary fc-btn-sm">📥 Export CSV</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-xs uppercase border-b text-muted border-border">
                                <tr>
                                    <th class="px-3 py-2 text-left">Fan</th>
                                    <th class="px-3 py-2 text-left">Oraș</th>
                                    <th class="px-3 py-2 text-left">Segment</th>
                                    <th class="px-3 py-2 text-right whitespace-nowrap">Evenimente</th>
                                    <th class="px-3 py-2 text-right whitespace-nowrap">LTV</th>
                                    <th class="px-3 py-2 text-left whitespace-nowrap">Ultim eveniment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="fan in fansData.fans || []" :key="fan.id">
                                    <tr class="border-b border-border/50">
                                        <td class="px-3 py-3">
                                            <div class="flex items-center gap-2">
                                                <div class="flex items-center justify-center w-8 h-8 text-xs font-bold text-white rounded-full bg-primary shrink-0" x-text="fan.initials"></div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold truncate text-secondary" x-text="fan.name"></p>
                                                    <p class="text-xs truncate text-muted" x-text="fan.email"></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3" x-text="fan.city"></td>
                                        <td class="px-3"><span class="fc-badge bg-surface" x-text="fan.segment"></span></td>
                                        <td class="px-3 font-semibold text-right whitespace-nowrap" x-text="fan.events"></td>
                                        <td class="px-3 font-semibold text-right whitespace-nowrap" x-text="formatNumber(fan.ltv) + ' RON'"></td>
                                        <td class="px-3 text-xs text-muted whitespace-nowrap" x-text="fan.last_event"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p x-show="!(fansData.fans || []).length" class="py-8 text-center text-muted">Niciun fan găsit cu filtrele curente.</p>
                    </div>

                    <div class="flex items-center justify-between pt-4 mt-4 border-t border-border" x-show="(fansData.fans || []).length">
                        <p class="text-xs text-muted">
                            Pagina <span x-text="fansData.page"></span> din <span x-text="fansData.pages"></span> · <span x-text="fansData.total"></span> fani total
                        </p>
                        <div class="flex gap-1">
                            <button @click="changePage(fansData.page - 1)" :disabled="fansData.page <= 1" class="fc-btn fc-btn-secondary fc-btn-sm">← Anterior</button>
                            <button @click="changePage(fansData.page + 1)" :disabled="fansData.page >= fansData.pages" class="fc-btn fc-btn-secondary fc-btn-sm">Următor →</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: COHORT ============ -->
            <div x-show="tab === 'cohort'" class="space-y-4">
                <div class="grid grid-cols-3 gap-3">
                    <div class="p-4 bg-white border border-border rounded-2xl">
                        <p class="text-xs uppercase text-muted">Retenție medie M+3</p>
                        <p class="mt-1 text-2xl font-bold text-secondary" x-text="(cohortData.avg_m3 ?? 0) + '%'"></p>
                    </div>
                    <div class="p-4 bg-white border border-border rounded-2xl">
                        <p class="text-xs uppercase text-muted">Retenție medie M+12</p>
                        <p class="mt-1 text-2xl font-bold text-secondary" x-text="(cohortData.avg_m12 ?? 0) + '%'"></p>
                    </div>
                    <div class="p-4 bg-white border border-border rounded-2xl">
                        <p class="text-xs uppercase text-muted">Cel mai bun cohort</p>
                        <p class="mt-1 text-lg font-bold text-secondary" x-text="cohortData.best_cohort || '—'"></p>
                    </div>
                </div>

                <div class="p-5 overflow-x-auto bg-white border border-border rounded-2xl">
                    <h3 class="mb-3 font-bold text-secondary">Retenție pe cohorte</h3>
                    <p class="mb-4 text-xs text-muted">Procent fani revenitori per lună de achiziție</p>
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase border-b text-muted border-border">
                            <tr>
                                <th class="px-2 py-2 text-left">Cohort</th>
                                <th class="px-2 py-2 text-right">Fani</th>
                                <th class="cohort-cell">M+1</th>
                                <th class="cohort-cell">M+3</th>
                                <th class="cohort-cell">M+6</th>
                                <th class="cohort-cell">M+12</th>
                                <th class="cohort-cell">M+24</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(row, idx) in cohortData.matrix || []" :key="idx">
                                <tr class="border-b border-border/50">
                                    <td class="px-2 py-2 font-semibold text-secondary" x-text="row.month"></td>
                                    <td class="px-2 py-2 text-right" x-text="row.size"></td>
                                    <template x-for="(val, vi) in row.values || []" :key="vi">
                                        <td class="cohort-cell" :style="cohortStyle(val)" x-text="val !== null ? val + '%' : '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="!(cohortData.matrix || []).length" class="py-4 text-sm text-muted">Date insuficiente — revino după 3+ luni de activitate.</p>
                </div>
            </div>

            <!-- ============ TAB: DEMOGRAPHICS ============ -->
            <div x-show="tab === 'demographics'" class="space-y-6">
                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-1 font-bold text-secondary">Distribuție vârstă</h3>
                        <p class="mb-4 text-sm text-muted">Pe baza datei nașterii din profil</p>
                        <div x-show="demographicsData.has_age_data" class="relative h-[260px]"><canvas id="ageChart"></canvas></div>
                        <p x-show="!demographicsData.has_age_data" class="py-8 text-sm text-center text-muted">Niciun fan nu și-a completat data nașterii încă.</p>
                    </div>
                    <div class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-1 font-bold text-secondary">Gen</h3>
                        <p class="mb-4 text-sm text-muted">Distribuția fanilor</p>
                        <div x-show="demographicsData.has_gender_data" class="relative h-[260px]"><canvas id="genderChart"></canvas></div>
                        <p x-show="!demographicsData.has_gender_data" class="py-8 text-sm text-center text-muted">Niciun fan nu și-a completat genul încă.</p>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: COMPARE ============ -->
            <div x-show="tab === 'compare'" class="space-y-4">
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <h3 class="mb-4 font-bold text-secondary">Comparație An vs An</h3>
                    <div class="flex flex-wrap items-center gap-3 mb-6">
                        <select x-model.number="compareA" class="fc-input" style="width:140px">
                            <template x-for="y in compareYears" :key="y"><option :value="y" x-text="y"></option></template>
                        </select>
                        <span class="text-sm text-muted">vs</span>
                        <select x-model.number="compareB" class="fc-input" style="width:140px">
                            <template x-for="y in compareYears" :key="y"><option :value="y" x-text="y"></option></template>
                        </select>
                        <button @click="loadCompare()" :disabled="tabLoading" class="fc-btn fc-btn-primary fc-btn-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span x-text="compareData.a_kpis?.length ? 'Recalculează' : 'Afișează'"></span>
                        </button>
                    </div>

                    <p x-show="!compareData.a_kpis?.length && !tabLoading" class="py-4 text-sm text-muted">Selectează cei doi ani și apasă „Afișează" pentru a calcula comparația.</p>

                    <div class="grid gap-4 mb-6 sm:grid-cols-2" x-show="compareData.supported">
                        <div class="p-4 border border-border rounded-xl">
                            <p class="mb-3 text-xs uppercase text-muted" x-text="compareData.a_label || 'Anul A'"></p>
                            <template x-for="(kpi, idx) in compareData.a_kpis || []" :key="idx">
                                <div class="flex justify-between py-2 border-b border-border/30 last:border-0">
                                    <span class="text-sm text-muted" x-text="kpi.label"></span>
                                    <span class="font-semibold text-secondary" x-text="formatNumber(kpi.value)"></span>
                                </div>
                            </template>
                        </div>
                        <div class="p-4 border border-border rounded-xl">
                            <p class="mb-3 text-xs uppercase text-muted" x-text="compareData.b_label || 'Anul B'"></p>
                            <template x-for="(kpi, idx) in compareData.b_kpis || []" :key="idx">
                                <div class="flex justify-between py-2 border-b border-border/30 last:border-0">
                                    <span class="text-sm text-muted" x-text="kpi.label"></span>
                                    <span class="font-semibold text-secondary" x-text="formatNumber(kpi.value)"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="relative h-[300px]" x-show="compareData.supported"><canvas id="compareChart"></canvas></div>
                </div>
            </div>

            <!-- ============ TAB: VIP ============ -->
            <div x-show="tab === 'vip'" class="space-y-4">
                <div class="p-5 bg-white border border-border rounded-2xl">
                    <h3 class="mb-4 font-bold text-secondary">🏆 Top 10 fani VIP</h3>
                    <div class="space-y-3">
                        <template x-for="(vip, idx) in vipData || []" :key="vip.id">
                            <div class="flex items-center gap-4 p-3 bg-surface rounded-xl">
                                <span class="w-10 text-2xl font-bold text-center text-primary" x-text="'#' + (idx + 1)"></span>
                                <div class="flex items-center justify-center w-12 h-12 font-bold text-white rounded-full bg-gradient-to-br from-primary to-accent" x-text="vip.initials"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-secondary" x-text="vip.name"></p>
                                    <p class="text-xs text-muted">
                                        <span x-text="vip.city"></span> · cu tine din <span x-text="vip.since"></span>
                                        <span x-show="vip.years > 0"> · <span x-text="vip.years"></span> ani</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-primary" x-text="formatNumber(vip.ltv) + ' RON'"></p>
                                    <p class="text-xs text-muted"><span x-text="vip.events"></span> evenimente</p>
                                </div>
                            </div>
                        </template>
                        <p x-show="!(vipData || []).length" class="py-4 text-sm text-center text-muted">Niciun VIP încă (necesită minim 3 evenimente).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SEGMENT BUILDER MODAL ============ -->
    <div x-show="segmentModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5)">
        <div class="w-full max-w-md p-6 bg-white rounded-2xl">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-secondary">Segment nou</h3>
                    <p class="mt-1 text-sm text-muted">Definește criteriile pentru a vedea exact subgrupul de fani vizat.</p>
                </div>
                <button @click="segmentModal.open = false" class="text-muted hover:text-secondary">✕</button>
            </div>
            <form @submit.prevent="saveSegment()" class="space-y-3">
                <div>
                    <label class="text-xs uppercase text-muted">Nume *</label>
                    <input type="text" x-model="segmentModal.name" required maxlength="80" placeholder="Ex: Fani VIP din București" class="mt-1 fc-input">
                </div>
                <div>
                    <label class="text-xs uppercase text-muted">Descriere (opțional)</label>
                    <textarea x-model="segmentModal.description" maxlength="500" rows="2" class="mt-1 fc-input"></textarea>
                </div>

                <div class="pt-3 border-t border-border">
                    <p class="mb-2 text-xs uppercase text-muted">Criterii</p>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <input type="number" x-model.number="segmentModal.criteria.events_min" placeholder="Min evenimente" class="fc-input">
                        <input type="number" x-model.number="segmentModal.criteria.events_max" placeholder="Max evenimente" class="fc-input">
                    </div>
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <input type="number" x-model.number="segmentModal.criteria.spend_min" placeholder="Min spend (RON)" class="fc-input">
                        <input type="number" x-model.number="segmentModal.criteria.spend_max" placeholder="Max spend (RON)" class="fc-input">
                    </div>
                    <div class="mb-2">
                        <input type="text" x-model="segmentModal.citiesInput" @input="parseCities()" placeholder="Orașe (separate cu virgulă)" class="fc-input">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" x-model="segmentModal.criteria.last_event_after" class="fc-input">
                        <input type="date" x-model="segmentModal.criteria.last_event_before" class="fc-input">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="segmentModal.open = false" class="fc-btn fc-btn-secondary fc-btn-sm">Anulează</button>
                    <button type="submit" :disabled="segmentModal.saving" class="fc-btn fc-btn-primary fc-btn-sm">
                        <span x-text="segmentModal.saving ? 'Se salvează...' : 'Salvează'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<style>[x-cloak]{display:none!important}</style>

<script>
function fanCrm() {
    return {
        loading: true,
        tabLoading: false,
        tab: 'overview',
        tabs: [
            { id: 'overview',     label: 'Overview' },
            { id: 'map',          label: 'Hartă' },
            { id: 'segments',     label: 'Segmente' },
            { id: 'fans',         label: 'Listă fani' },
            { id: 'cohort',       label: 'Cohort' },
            { id: 'demographics', label: 'Demografie' },
            { id: 'compare',      label: 'Comparații' },
            { id: 'vip',          label: 'VIP' },
        ],

        // Data per tab
        overview: { kpis: {}, top_cities: [], countries: [], dormant_cities: [], growth_chart: null, fan_types: [], insights: [] },
        mapData: { points: [] },
        segmentsData: { predefined: [], counts: {}, custom: [] },
        fansData: { fans: [], total: 0, page: 1, pages: 1 },
        cohortData: { matrix: [], avg_m3: 0, avg_m12: 0, best_cohort: null },
        demographicsData: { age_buckets: [], gender_split: [] },
        compareData: { supported: false, a_kpis: [], b_kpis: [], chart: null },
        vipData: [],

        // UI state
        mapView: 'heat',
        map: null, heatLayer: null, pinLayer: null,
        fansFilters: { search: '', segment: '', custom_segment_id: null, page: 1 },
        compareA: new Date().getFullYear(),
        compareB: new Date().getFullYear() - 1,
        compareYears: [],
        charts: {},

        segmentModal: {
            open: false,
            saving: false,
            name: '',
            description: '',
            citiesInput: '',
            criteria: { events_min: null, events_max: null, spend_min: null, spend_max: null, cities: [], last_event_after: null, last_event_before: null },
        },

        token() { return localStorage.getItem('ambilet_artist_token'); },

        async init() {
            this.compareYears = [];
            const yr = new Date().getFullYear();
            for (let y = yr; y >= yr - 5; y--) this.compareYears.push(y);

            // Read ?tab= from URL for deep-linking
            const validTabs = this.tabs.map(t => t.id);
            const urlTab = (new URL(window.location.href)).searchParams.get('tab');
            const initialTab = (urlTab && validTabs.includes(urlTab)) ? urlTab : 'overview';

            await this.loadOverview();
            await this.loadSegments();
            this.loading = false;

            if (initialTab !== 'overview') {
                await this.setTab(initialTab);
            } else {
                this.$nextTick(() => this.renderTab('overview'));
            }
        },

        async setTab(t) {
            this.tab = t;
            this.syncUrlTab(t);
            const needsLoad = (
                (t === 'map' && !this.mapData.points.length) ||
                (t === 'fans' && !this.fansData.fans.length) ||
                (t === 'cohort' && !this.cohortData.matrix.length) ||
                (t === 'demographics' && !this.demographicsData.age_buckets.length) ||
                (t === 'vip' && !this.vipData.length)
            );
            if (needsLoad) this.tabLoading = true;
            try {
                if (t === 'map' && !this.mapData.points.length) await this.loadMap();
                if (t === 'fans' && !this.fansData.fans.length) await this.loadFans();
                if (t === 'cohort' && !this.cohortData.matrix.length) await this.loadCohort();
                if (t === 'demographics' && !this.demographicsData.age_buckets.length) await this.loadDemographics();
                if (t === 'vip' && !this.vipData.length) await this.loadVip();
            } finally {
                this.tabLoading = false;
            }
            this.$nextTick(() => this.renderTab(t));
        },

        syncUrlTab(t) {
            try {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', t);
                window.history.replaceState({}, '', url.toString());
            } catch (e) { /* noop */ }
        },

        async fetchAction(action, params = {}) {
            const qs = new URLSearchParams(params).toString();
            const res = await fetch(`/api/proxy.php?action=${action}` + (qs ? '&' + qs : ''), {
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
            });
            return await res.json();
        },

        async loadOverview() {
            const r = await this.fetchAction('artist.fan-crm.overview');
            this.overview = r?.data || this.overview;
        },

        async loadMap() {
            const r = await this.fetchAction('artist.fan-crm.map');
            this.mapData = r?.data || this.mapData;
        },

        async loadSegments() {
            const r = await this.fetchAction('artist.fan-crm.segments');
            this.segmentsData = r?.data || this.segmentsData;
        },

        async loadFans() {
            this.tabLoading = true;
            try {
                const params = { page: this.fansFilters.page };
                if (this.fansFilters.search) params.search = this.fansFilters.search;
                if (this.fansFilters.segment) params.segment = this.fansFilters.segment;
                if (this.fansFilters.custom_segment_id) params.custom_segment_id = this.fansFilters.custom_segment_id;
                const r = await this.fetchAction('artist.fan-crm.fans', params);
                this.fansData = r?.data || this.fansData;
            } finally {
                this.tabLoading = false;
            }
        },

        async changePage(p) {
            if (p < 1 || p > this.fansData.pages) return;
            this.fansFilters.page = p;
            await this.loadFans();
        },

        async loadCohort() {
            const r = await this.fetchAction('artist.fan-crm.cohort');
            this.cohortData = r?.data || this.cohortData;
        },

        async loadDemographics() {
            const r = await this.fetchAction('artist.fan-crm.demographics');
            this.demographicsData = r?.data || this.demographicsData;
        },

        async loadCompare() {
            this.tabLoading = true;
            try {
                const r = await this.fetchAction('artist.fan-crm.compare', { type: 'period', a_id: this.compareA, b_id: this.compareB });
                this.compareData = r?.data || { supported: false, a_kpis: [], b_kpis: [], chart: null };
            } finally {
                this.tabLoading = false;
            }
            this.$nextTick(() => this.renderCompareChart());
        },

        async loadVip() {
            const r = await this.fetchAction('artist.fan-crm.vip', { limit: 10 });
            this.vipData = r?.data || [];
        },

        renderTab(t) {
            if (t === 'overview') {
                this.renderGrowthChart();
                this.renderFanTypeChart();
            } else if (t === 'map') {
                this.renderMap();
            } else if (t === 'demographics') {
                this.renderAgeChart();
                this.renderGenderChart();
            } else if (t === 'compare') {
                this.renderCompareChart();
            }
        },

        destroyChart(key) {
            if (this.charts[key]) { this.charts[key].destroy(); delete this.charts[key]; }
        },

        // Defensive: kill any Chart.js instance attached to this canvas
        // (handles stale instances from previous renders not tracked in this.charts).
        clearCanvas(elId) {
            const el = document.getElementById(elId);
            if (!el || typeof Chart === 'undefined') return null;
            const existing = Chart.getChart(el);
            if (existing) existing.destroy();
            return el;
        },

        renderGrowthChart() {
            this.destroyChart('growth');
            const el = this.clearCanvas('growthChart');
            if (!el) return;
            const g = this.overview.growth_chart || { labels: [], new_fans: [], returning: [] };
            this.charts.growth = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: { labels: g.labels, datasets: [
                    { label: 'Fani noi', data: g.new_fans, backgroundColor: '#A51C30' },
                    { label: 'Revenire', data: g.returning, backgroundColor: '#E67E22' },
                ]},
                options: { responsive: true, maintainAspectRatio: false, animation: false, resizeDelay: 200, plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
            });
        },

        renderFanTypeChart() {
            this.destroyChart('fanType');
            const el = this.clearCanvas('fanTypeChart');
            if (!el) return;
            const types = this.overview.fan_types || [];
            this.charts.fanType = new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels: types.map(t => t.label), datasets: [{ data: types.map(t => t.value), backgroundColor: types.map(t => t.color), borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, animation: false, resizeDelay: 200, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
            });
        },

        renderMap() {
            if (!this.map) {
                const el = document.getElementById('fanMap');
                if (!el) return;
                this.map = L.map('fanMap').setView([this.mapData.center?.lat || 45.94, this.mapData.center?.lng || 24.97], this.mapData.zoom || 6);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { attribution: '© OpenStreetMap, © CARTO' }).addTo(this.map);
            }
            // Curăță layere vechi
            if (this.heatLayer) this.map.removeLayer(this.heatLayer);
            if (this.pinLayer) this.map.removeLayer(this.pinLayer);

            const points = this.mapData.points || [];
            if (this.mapView === 'heat') {
                const data = points.map(p => [p.lat, p.lng, p.fans]);
                this.heatLayer = L.heatLayer(data, { radius: 35, blur: 25, maxZoom: 10, gradient: { 0.2: '#3b82f6', 0.4: '#10b981', 0.6: '#f59e0b', 0.8: '#E67E22', 1.0: '#A51C30' } }).addTo(this.map);
            } else {
                this.pinLayer = L.layerGroup();
                points.forEach(p => {
                    const size = Math.max(20, Math.min(60, p.fans / 5));
                    const circle = L.circleMarker([p.lat, p.lng], { radius: size / 2, fillColor: '#A51C30', color: '#fff', weight: 2, fillOpacity: 0.7 });
                    circle.bindPopup(`<strong>${p.name}</strong><br>${p.fans} fani · ${p.events} evenimente`);
                    this.pinLayer.addLayer(circle);
                });
                this.pinLayer.addTo(this.map);
            }
        },

        renderAgeChart() {
            this.destroyChart('age');
            if (!this.demographicsData.has_age_data) return;
            const el = this.clearCanvas('ageChart');
            if (!el) return;
            const buckets = this.demographicsData.age_buckets || [];
            this.charts.age = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: { labels: buckets.map(b => b.label), datasets: [{ data: buckets.map(b => b.pct), backgroundColor: '#A51C30', borderRadius: 8 }] },
                options: { responsive: true, maintainAspectRatio: false, animation: false, resizeDelay: 200, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => v + '%' } } } }
            });
        },

        renderGenderChart() {
            this.destroyChart('gender');
            if (!this.demographicsData.has_gender_data) return;
            const el = this.clearCanvas('genderChart');
            if (!el) return;
            const split = this.demographicsData.gender_split || [];
            this.charts.gender = new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels: split.map(s => s.label), datasets: [{ data: split.map(s => s.pct), backgroundColor: ['#A51C30', '#3B82F6', '#94A3B8'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, animation: false, resizeDelay: 200, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
            });
        },

        renderCompareChart() {
            this.destroyChart('compare');
            if (!this.compareData.supported) return;
            const el = this.clearCanvas('compareChart');
            if (!el) return;
            const c = this.compareData.chart || { labels: [], a: [], b: [] };
            this.charts.compare = new Chart(el.getContext('2d'), {
                type: 'line',
                data: { labels: c.labels, datasets: [
                    { label: this.compareData.a_label, data: c.a, borderColor: '#A51C30', backgroundColor: 'rgba(165,28,48,0.1)', fill: true, tension: 0.4 },
                    { label: this.compareData.b_label, data: c.b, borderColor: '#94A3B8', borderDash: [5,5], fill: false, tension: 0.4 },
                ]},
                options: { responsive: true, maintainAspectRatio: false, animation: false, resizeDelay: 200, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
            });
        },

        // Segment modal
        openSegmentModal() {
            this.segmentModal.open = true;
            this.segmentModal.name = '';
            this.segmentModal.description = '';
            this.segmentModal.citiesInput = '';
            this.segmentModal.criteria = { events_min: null, events_max: null, spend_min: null, spend_max: null, cities: [], last_event_after: null, last_event_before: null };
        },

        parseCities() {
            this.segmentModal.criteria.cities = this.segmentModal.citiesInput
                .split(',')
                .map(c => c.trim())
                .filter(c => c.length > 0);
        },

        async saveSegment() {
            this.segmentModal.saving = true;
            try {
                const cleanCriteria = {};
                Object.entries(this.segmentModal.criteria).forEach(([k, v]) => {
                    if (v !== null && v !== '' && !(Array.isArray(v) && v.length === 0)) cleanCriteria[k] = v;
                });
                const res = await fetch('/api/proxy.php?action=artist.fan-crm.segment.create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                    body: JSON.stringify({
                        name: this.segmentModal.name,
                        description: this.segmentModal.description || null,
                        criteria: cleanCriteria,
                    }),
                });
                const payload = await res.json();
                if (!res.ok || payload?.success === false) throw new Error(payload?.message || 'Eroare');
                this.segmentModal.open = false;
                await this.loadSegments();
            } catch (e) { alert('Eroare la salvare: ' + e.message); }
            finally { this.segmentModal.saving = false; }
        },

        async deleteSegment(id) {
            if (!confirm('Sigur ștergi segmentul?')) return;
            try {
                await fetch(`/api/proxy.php?action=artist.fan-crm.segment.delete&id=${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
                });
                await this.loadSegments();
            } catch (e) { alert('Eroare: ' + e.message); }
        },

        exportUrl() {
            const params = new URLSearchParams();
            params.set('action', 'artist.fan-crm.fans.export');
            params.set('token', this.token());
            if (this.fansFilters.search) params.set('search', this.fansFilters.search);
            if (this.fansFilters.segment) params.set('segment', this.fansFilters.segment);
            if (this.fansFilters.custom_segment_id) params.set('custom_segment_id', this.fansFilters.custom_segment_id);
            return '/api/proxy.php?' + params.toString();
        },

        cohortStyle(val) {
            if (val === null || val === undefined) return '';
            const opacity = Math.min(0.85, (val / 100) * 0.85);
            const textColor = val >= 30 ? 'white' : '#1E293B';
            return `background: rgba(165,28,48,${opacity}); color: ${textColor};`;
        },

        formatNumber(n) {
            if (n === null || n === undefined || n === '') return '0';
            const num = Number(n);
            if (isNaN(num)) return n;
            return num.toLocaleString('ro-RO');
        },
    }
}
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>
