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

$pageTitle = 'Extended Artist — Fan CRM';
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

<main class="lg:ml-64 pt-16 lg:pt-0 min-h-screen" x-data="fanCrm()" x-init="init()" x-cloak>
    <div class="p-4 lg:p-8">

        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="pro-badge">PRO</span>
                <span class="text-xs text-muted uppercase tracking-wider font-semibold">Extended Artist · Audience Analytics</span>
            </div>
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-bold text-secondary">Fan CRM</h1>
                    <p class="text-muted mt-1">Înțelegere profundă a publicului tău — cine sunt, unde locuiesc, ce iubesc</p>
                </div>
            </div>
        </div>

        <!-- GDPR notice -->
        <div class="mb-6 bg-blue-50 border border-blue-200 rounded-xl p-3 flex items-start gap-2">
            <svg class="w-4 h-4 text-blue-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
            <p class="text-xs text-blue-900">Date conforme GDPR — toate cifrele și hărțile sunt agregate. Datele individuale (Listă fani, VIP) folosesc doar fanii care au consimțit la partajare în ToS.</p>
        </div>

        <!-- Loading -->
        <div x-show="loading" class="bg-white rounded-2xl border border-border p-12 text-center text-muted">
            <div class="inline-flex items-center gap-3">
                <span class="inline-block w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                <span>Se încarcă datele...</span>
            </div>
        </div>

        <div x-show="!loading" class="relative">
            <!-- Per-tab loader overlay -->
            <div x-show="tabLoading" x-cloak class="absolute inset-0 z-10 bg-white/70 backdrop-blur-sm flex items-center justify-center rounded-2xl">
                <div class="inline-flex items-center gap-3 bg-white shadow-lg border border-border rounded-xl px-5 py-3">
                    <span class="inline-block w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>
                    <span class="text-sm text-secondary font-medium">Se încarcă...</span>
                </div>
            </div>
            <!-- Tabs -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden mb-6">
                <div class="border-b border-border overflow-x-auto">
                    <div class="flex gap-1 p-2 min-w-max">
                        <template x-for="t in tabs" :key="t.id">
                            <button @click="setTab(t.id)"
                                    :class="tab === t.id ? 'bg-primary text-white' : 'text-muted hover:bg-surface hover:text-secondary'"
                                    class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors">
                                <span x-text="t.label"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: OVERVIEW ============ -->
            <div x-show="tab === 'overview'" class="space-y-6">
                <!-- KPIs -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">Total fani</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.total_fans)"></p>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">Fani noi (6L)</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.new_fans)"></p>
                        <p class="text-xs mt-1" :class="overview.kpis?.new_fans_trend >= 0 ? 'text-success' : 'text-error'">
                            <span x-text="(overview.kpis?.new_fans_trend >= 0 ? '+' : '') + (overview.kpis?.new_fans_trend ?? 0) + '%'"></span> vs perioada anterioară
                        </p>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">Retenție</p>
                        <p class="text-2xl font-bold text-secondary" x-text="(overview.kpis?.retention_rate ?? 0) + '%'"></p>
                        <p class="text-xs text-muted mt-1">2+ evenimente</p>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">LTV mediu</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.avg_ltv) + ' RON'"></p>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <p class="text-xs text-muted uppercase tracking-wider font-semibold mb-2">Orașe</p>
                        <p class="text-2xl font-bold text-secondary" x-text="formatNumber(overview.kpis?.cities_covered)"></p>
                    </div>
                </div>

                <!-- Growth + Fan Type Charts -->
                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <h3 class="font-bold text-secondary mb-1">Creștere fani (12 luni)</h3>
                        <p class="text-sm text-muted mb-4">Fani noi vs revenire</p>
                        <div class="relative h-[260px]"><canvas id="growthChart"></canvas></div>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <h3 class="font-bold text-secondary mb-1">Distribuție tipuri</h3>
                        <p class="text-sm text-muted mb-4">VIP / Loiali / Noi / Dormiți</p>
                        <div class="relative h-[260px]"><canvas id="fanTypeChart"></canvas></div>
                    </div>
                </div>

                <!-- Top Cities + Countries + Dormant -->
                <div class="grid lg:grid-cols-3 gap-4">
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <h3 class="font-bold text-secondary mb-3">Top orașe</h3>
                        <div class="space-y-2">
                            <template x-for="(city, idx) in overview.top_cities || []" :key="idx">
                                <div class="flex items-center justify-between p-2 bg-surface rounded-lg">
                                    <div>
                                        <p class="font-semibold text-sm text-secondary" x-text="city.name"></p>
                                        <p class="text-xs text-muted" x-text="city.events + ' evenimente'"></p>
                                    </div>
                                    <span class="text-sm font-bold text-primary" x-text="formatNumber(city.fans)"></span>
                                </div>
                            </template>
                            <p x-show="!(overview.top_cities || []).length" class="text-sm text-muted">Niciun oraș încă.</p>
                        </div>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <h3 class="font-bold text-secondary mb-3">Țări</h3>
                        <div class="space-y-2">
                            <template x-for="(country, idx) in overview.countries || []" :key="idx">
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="font-medium text-secondary"><span x-text="country.flag"></span> <span x-text="country.name"></span></span>
                                        <span class="text-muted"><span x-text="country.pct"></span>%</span>
                                    </div>
                                    <div class="h-2 bg-surface rounded-full overflow-hidden">
                                        <div class="h-full bg-primary rounded-full" :style="`width: ${country.pct}%`"></div>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!(overview.countries || []).length" class="text-sm text-muted">Date insuficiente.</p>
                        </div>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <h3 class="font-bold text-secondary mb-3">🌙 Orașe „dormite"</h3>
                        <p class="text-xs text-muted mb-3">Fani locali, dar nu ai mai cântat de mult</p>
                        <div class="space-y-2">
                            <template x-for="(c, idx) in overview.dormant_cities || []" :key="idx">
                                <div class="flex items-center justify-between p-2 bg-warning/5 border border-warning/20 rounded-lg">
                                    <div>
                                        <p class="font-semibold text-sm text-secondary" x-text="c.name"></p>
                                        <p class="text-xs text-muted" x-text="c.fans + ' fani · ultimul concert ' + (c.lastEvent || '?')"></p>
                                    </div>
                                </div>
                            </template>
                            <p x-show="!(overview.dormant_cities || []).length" class="text-sm text-muted">Niciun oraș dormit.</p>
                        </div>
                    </div>
                </div>

                <!-- Insights -->
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4" x-show="(overview.insights || []).length">
                    <template x-for="(insight, idx) in overview.insights || []" :key="idx">
                        <div class="bg-gradient-to-br from-accent/5 to-primary/5 border border-accent/20 rounded-2xl p-5 flex items-start gap-3">
                            <span class="text-2xl flex-shrink-0" x-text="insight.icon"></span>
                            <div>
                                <p class="font-bold text-secondary mb-1" x-text="insight.title"></p>
                                <p class="text-sm text-secondary" x-text="insight.text"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ============ TAB: MAP ============ -->
            <div x-show="tab === 'map'" class="space-y-4">
                <div class="bg-white border border-border rounded-2xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="font-bold text-secondary">Hartă fani</h3>
                        <div class="inline-flex bg-surface rounded-lg p-1">
                            <button @click="mapView='heat'; renderMap()" :class="mapView==='heat' ? 'bg-white shadow-sm text-secondary' : 'text-muted'" class="px-3 py-1 rounded text-sm font-medium">Heatmap</button>
                            <button @click="mapView='pins'; renderMap()" :class="mapView==='pins' ? 'bg-white shadow-sm text-secondary' : 'text-muted'" class="px-3 py-1 rounded text-sm font-medium">Pin-uri</button>
                        </div>
                    </div>
                    <div id="fanMap"></div>
                    <p class="text-xs text-muted mt-3">Date din top <span x-text="(mapData.points || []).length"></span> orașe cu fani identificați</p>
                </div>
            </div>

            <!-- ============ TAB: SEGMENTS ============ -->
            <div x-show="tab === 'segments'" class="space-y-6">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-secondary">Segmente predefinite</h3>
                        <span class="text-xs text-muted">Calcul automat live</span>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <template x-for="seg in segmentsData.predefined || []" :key="seg.id">
                            <div class="bg-white border border-border rounded-2xl p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <span class="fc-badge" :style="`background: ${seg.color}20; color: ${seg.color}`" x-text="seg.name"></span>
                                </div>
                                <p class="text-2xl font-bold text-secondary" x-text="formatNumber(segmentsData.counts?.[seg.id] ?? 0)"></p>
                                <p class="text-xs text-muted mt-1" x-text="seg.description"></p>
                            </div>
                        </template>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-bold text-secondary">Segmente personalizate</h3>
                        <button @click="openSegmentModal()" class="fc-btn fc-btn-primary fc-btn-sm">+ Segment nou</button>
                    </div>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3" x-show="(segmentsData.custom || []).length">
                        <template x-for="seg in segmentsData.custom || []" :key="seg.id">
                            <div class="bg-white border border-border rounded-2xl p-4">
                                <div class="flex items-start justify-between mb-2">
                                    <span class="fc-badge" :style="`background: ${seg.color}20; color: ${seg.color}`" x-text="seg.name"></span>
                                    <button @click="deleteSegment(seg.id)" class="text-muted hover:text-error">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                                <p class="text-sm text-secondary" x-text="seg.description || '—'"></p>
                                <button @click="setTab('fans'); fansFilters.custom_segment_id = seg.id; loadFans()" class="mt-3 text-sm text-primary font-medium hover:underline">Vezi fani →</button>
                            </div>
                        </template>
                    </div>
                    <p x-show="!(segmentsData.custom || []).length" class="text-sm text-muted">Nu ai segmente personalizate. Creează unul cu „+ Segment nou".</p>
                </div>
            </div>

            <!-- ============ TAB: FANS LIST ============ -->
            <div x-show="tab === 'fans'" class="space-y-4">
                <div class="bg-white border border-border rounded-2xl p-5">
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
                            <thead class="text-xs text-muted uppercase border-b border-border">
                                <tr>
                                    <th class="text-left py-2 px-3">Fan</th>
                                    <th class="text-left py-2 px-3">Oraș</th>
                                    <th class="text-left py-2 px-3">Segment</th>
                                    <th class="text-right py-2 px-3 whitespace-nowrap">Evenimente</th>
                                    <th class="text-right py-2 px-3 whitespace-nowrap">LTV</th>
                                    <th class="text-left py-2 px-3 whitespace-nowrap">Ultim eveniment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="fan in fansData.fans || []" :key="fan.id">
                                    <tr class="border-b border-border/50">
                                        <td class="py-3 px-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full bg-primary text-white text-xs font-bold flex items-center justify-center shrink-0" x-text="fan.initials"></div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-secondary truncate" x-text="fan.name"></p>
                                                    <p class="text-xs text-muted truncate" x-text="fan.email"></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3" x-text="fan.city"></td>
                                        <td class="px-3"><span class="fc-badge bg-surface" x-text="fan.segment"></span></td>
                                        <td class="text-right font-semibold px-3 whitespace-nowrap" x-text="fan.events"></td>
                                        <td class="text-right font-semibold px-3 whitespace-nowrap" x-text="formatNumber(fan.ltv) + ' RON'"></td>
                                        <td class="text-muted text-xs px-3 whitespace-nowrap" x-text="fan.last_event"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p x-show="!(fansData.fans || []).length" class="text-center text-muted py-8">Niciun fan găsit cu filtrele curente.</p>
                    </div>

                    <div class="flex items-center justify-between mt-4 pt-4 border-t border-border" x-show="(fansData.fans || []).length">
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
                    <div class="bg-white border border-border rounded-2xl p-4">
                        <p class="text-xs text-muted uppercase">Retenție medie M+3</p>
                        <p class="text-2xl font-bold text-secondary mt-1" x-text="(cohortData.avg_m3 ?? 0) + '%'"></p>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-4">
                        <p class="text-xs text-muted uppercase">Retenție medie M+12</p>
                        <p class="text-2xl font-bold text-secondary mt-1" x-text="(cohortData.avg_m12 ?? 0) + '%'"></p>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-4">
                        <p class="text-xs text-muted uppercase">Cel mai bun cohort</p>
                        <p class="text-lg font-bold text-secondary mt-1" x-text="cohortData.best_cohort || '—'"></p>
                    </div>
                </div>

                <div class="bg-white border border-border rounded-2xl p-5 overflow-x-auto">
                    <h3 class="font-bold text-secondary mb-3">Retenție pe cohorte</h3>
                    <p class="text-xs text-muted mb-4">Procent fani revenitori per lună de achiziție</p>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-muted uppercase border-b border-border">
                            <tr>
                                <th class="text-left py-2 px-2">Cohort</th>
                                <th class="text-right py-2 px-2">Fani</th>
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
                                    <td class="py-2 px-2 font-semibold text-secondary" x-text="row.month"></td>
                                    <td class="py-2 px-2 text-right" x-text="row.size"></td>
                                    <template x-for="(val, vi) in row.values || []" :key="vi">
                                        <td class="cohort-cell" :style="cohortStyle(val)" x-text="val !== null ? val + '%' : '—'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p x-show="!(cohortData.matrix || []).length" class="text-sm text-muted py-4">Date insuficiente — revino după 3+ luni de activitate.</p>
                </div>
            </div>

            <!-- ============ TAB: DEMOGRAPHICS ============ -->
            <div x-show="tab === 'demographics'" class="space-y-6">
                <div class="grid lg:grid-cols-2 gap-4">
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <h3 class="font-bold text-secondary mb-1">Distribuție vârstă</h3>
                        <p class="text-sm text-muted mb-4">Pe baza datei nașterii din profil</p>
                        <div x-show="demographicsData.has_age_data" class="relative h-[260px]"><canvas id="ageChart"></canvas></div>
                        <p x-show="!demographicsData.has_age_data" class="text-sm text-muted py-8 text-center">Niciun fan nu și-a completat data nașterii încă.</p>
                    </div>
                    <div class="bg-white border border-border rounded-2xl p-5">
                        <h3 class="font-bold text-secondary mb-1">Gen</h3>
                        <p class="text-sm text-muted mb-4">Distribuția fanilor</p>
                        <div x-show="demographicsData.has_gender_data" class="relative h-[260px]"><canvas id="genderChart"></canvas></div>
                        <p x-show="!demographicsData.has_gender_data" class="text-sm text-muted py-8 text-center">Niciun fan nu și-a completat genul încă.</p>
                    </div>
                </div>
            </div>

            <!-- ============ TAB: COMPARE ============ -->
            <div x-show="tab === 'compare'" class="space-y-4">
                <div class="bg-white border border-border rounded-2xl p-5">
                    <h3 class="font-bold text-secondary mb-4">Comparație An vs An</h3>
                    <div class="flex flex-wrap items-center gap-3 mb-6">
                        <select x-model.number="compareA" class="fc-input" style="width:140px">
                            <template x-for="y in compareYears" :key="y"><option :value="y" x-text="y"></option></template>
                        </select>
                        <span class="text-muted text-sm">vs</span>
                        <select x-model.number="compareB" class="fc-input" style="width:140px">
                            <template x-for="y in compareYears" :key="y"><option :value="y" x-text="y"></option></template>
                        </select>
                        <button @click="loadCompare()" :disabled="tabLoading" class="fc-btn fc-btn-primary fc-btn-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            <span x-text="compareData.a_kpis?.length ? 'Recalculează' : 'Afișează'"></span>
                        </button>
                    </div>

                    <p x-show="!compareData.a_kpis?.length && !tabLoading" class="text-sm text-muted py-4">Selectează cei doi ani și apasă „Afișează" pentru a calcula comparația.</p>

                    <div class="grid sm:grid-cols-2 gap-4 mb-6" x-show="compareData.supported">
                        <div class="border border-border rounded-xl p-4">
                            <p class="text-xs text-muted uppercase mb-3" x-text="compareData.a_label || 'Anul A'"></p>
                            <template x-for="(kpi, idx) in compareData.a_kpis || []" :key="idx">
                                <div class="flex justify-between py-2 border-b border-border/30 last:border-0">
                                    <span class="text-sm text-muted" x-text="kpi.label"></span>
                                    <span class="font-semibold text-secondary" x-text="formatNumber(kpi.value)"></span>
                                </div>
                            </template>
                        </div>
                        <div class="border border-border rounded-xl p-4">
                            <p class="text-xs text-muted uppercase mb-3" x-text="compareData.b_label || 'Anul B'"></p>
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
                <div class="bg-white border border-border rounded-2xl p-5">
                    <h3 class="font-bold text-secondary mb-4">🏆 Top 10 fani VIP</h3>
                    <div class="space-y-3">
                        <template x-for="(vip, idx) in vipData || []" :key="vip.id">
                            <div class="flex items-center gap-4 p-3 bg-surface rounded-xl">
                                <span class="text-2xl font-bold text-primary w-10 text-center" x-text="'#' + (idx + 1)"></span>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-white font-bold" x-text="vip.initials"></div>
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
                        <p x-show="!(vipData || []).length" class="text-sm text-muted text-center py-4">Niciun VIP încă (necesită minim 3 evenimente).</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============ SEGMENT BUILDER MODAL ============ -->
    <div x-show="segmentModal.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5)">
        <div class="bg-white rounded-2xl max-w-md w-full p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-secondary">Segment nou</h3>
                    <p class="text-sm text-muted mt-1">Definește criteriile pentru a vedea exact subgrupul de fani vizat.</p>
                </div>
                <button @click="segmentModal.open = false" class="text-muted hover:text-secondary">✕</button>
            </div>
            <form @submit.prevent="saveSegment()" class="space-y-3">
                <div>
                    <label class="text-xs text-muted uppercase">Nume *</label>
                    <input type="text" x-model="segmentModal.name" required maxlength="80" placeholder="Ex: Fani VIP din București" class="fc-input mt-1">
                </div>
                <div>
                    <label class="text-xs text-muted uppercase">Descriere (opțional)</label>
                    <textarea x-model="segmentModal.description" maxlength="500" rows="2" class="fc-input mt-1"></textarea>
                </div>

                <div class="border-t border-border pt-3">
                    <p class="text-xs text-muted uppercase mb-2">Criterii</p>
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

        renderGrowthChart() {
            this.destroyChart('growth');
            const el = document.getElementById('growthChart');
            if (!el || typeof Chart === 'undefined') return;
            const g = this.overview.growth_chart || { labels: [], new_fans: [], returning: [] };
            this.charts.growth = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: { labels: g.labels, datasets: [
                    { label: 'Fani noi', data: g.new_fans, backgroundColor: '#A51C30' },
                    { label: 'Revenire', data: g.returning, backgroundColor: '#E67E22' },
                ]},
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } }
            });
        },

        renderFanTypeChart() {
            this.destroyChart('fanType');
            const el = document.getElementById('fanTypeChart');
            if (!el || typeof Chart === 'undefined') return;
            const types = this.overview.fan_types || [];
            this.charts.fanType = new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels: types.map(t => t.label), datasets: [{ data: types.map(t => t.value), backgroundColor: types.map(t => t.color), borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '65%', plugins: { legend: { position: 'bottom' } } }
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
            const el = document.getElementById('ageChart');
            if (!el || typeof Chart === 'undefined') return;
            const buckets = this.demographicsData.age_buckets || [];
            this.charts.age = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: { labels: buckets.map(b => b.label), datasets: [{ data: buckets.map(b => b.pct), backgroundColor: '#A51C30', borderRadius: 8 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => v + '%' } } } }
            });
        },

        renderGenderChart() {
            this.destroyChart('gender');
            const el = document.getElementById('genderChart');
            if (!el || typeof Chart === 'undefined') return;
            const split = this.demographicsData.gender_split || [];
            this.charts.gender = new Chart(el.getContext('2d'), {
                type: 'doughnut',
                data: { labels: split.map(s => s.label), datasets: [{ data: split.map(s => s.pct), backgroundColor: ['#A51C30', '#3B82F6', '#94A3B8'], borderWidth: 0 }] },
                options: { responsive: true, maintainAspectRatio: false, cutout: '60%', plugins: { legend: { position: 'bottom' } } }
            });
        },

        renderCompareChart() {
            this.destroyChart('compare');
            const el = document.getElementById('compareChart');
            if (!el || typeof Chart === 'undefined' || !this.compareData.supported) return;
            const c = this.compareData.chart || { labels: [], a: [], b: [] };
            this.charts.compare = new Chart(el.getContext('2d'), {
                type: 'line',
                data: { labels: c.labels, datasets: [
                    { label: this.compareData.a_label, data: c.a, borderColor: '#A51C30', backgroundColor: 'rgba(165,28,48,0.1)', fill: true, tension: 0.4 },
                    { label: this.compareData.b_label, data: c.b, borderColor: '#94A3B8', borderDash: [5,5], fill: false, tension: 0.4 },
                ]},
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { beginAtZero: true } } }
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
