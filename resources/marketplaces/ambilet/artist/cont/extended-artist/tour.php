<?php
/**
 * Extended Artist — Tour Optimizer (Modulul 3)
 *
 * 4 tab-uri: Hartă oportunități, Tour Planner, Predicții, Scenarii salvate.
 * State: Alpine.js. Charts: Chart.js (animation: false). Map: Leaflet.
 * API: /api/proxy.php?action=artist.tour.*
 */
require_once dirname(__DIR__, 3) . '/includes/config.php';

$pageTitle = 'Premium — Tour Optimizer';
$bodyClass = 'min-h-screen bg-surface font-sans';
$cssBundle = 'account';
require_once dirname(__DIR__, 3) . '/includes/head.php';
?>

<style>
    .to-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: 0.75rem; font-weight: 600; font-size: 0.875rem; transition: all 0.15s; cursor: pointer; border: none; }
    .to-btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .to-btn-primary { background: #A51C30; color: white; }
    .to-btn-primary:hover:not(:disabled) { background: #8B1728; }
    .to-btn-secondary { background: white; color: #1E293B; border: 1px solid #E2E8F0; }
    .to-btn-secondary:hover:not(:disabled) { background: #F8FAFC; }
    .to-btn-sm { padding: 0.4rem 0.875rem; font-size: 0.8125rem; }
    .to-input { width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #E2E8F0; border-radius: 0.5rem; font-size: 0.875rem; background: white; }
    .to-input:focus { outline: none; border-color: #A51C30; box-shadow: 0 0 0 3px rgba(165,28,48,0.1); }
    .to-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
    .pro-badge { background: linear-gradient(135deg, #E67E22, #A51C30); color: white; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.4rem; border-radius: 0.25rem; letter-spacing: 0.5px; }
    #opportunityMap, #plannerMap { width: 100%; border-radius: 1rem; z-index: 1; }
    /* Tooltip simplu cu CSS — afișează `data-tip` pe hover.
       Aliniat la dreapta iconiței (right: 0), tooltip se extinde spre stânga.
       Asta previne overflow-ul când iconița e aproape de marginea dreaptă a containerului. */
    .to-tip { position: relative; display: inline-flex; align-items: center; cursor: help; color: #94A3B8; }
    .to-tip:hover::after { content: attr(data-tip); position: absolute; bottom: calc(100% + 6px); right: 0; left: auto; transform: none; background: #1E293B; color: white; padding: 8px 12px; border-radius: 6px; font-size: 11px; line-height: 1.5; white-space: pre-wrap; text-align: left; max-width: 320px; min-width: 200px; width: max-content; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .to-tip:hover::before { content: ''; position: absolute; bottom: 100%; right: 4px; border: 5px solid transparent; border-top-color: #1E293B; z-index: 1000; }
    .to-section-title { display: flex; align-items: center; gap: 6px; font-size: 0.625rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; color: #64748B; margin-bottom: 8px; }
    .to-section-title .icon { font-size: 1rem; line-height: 1; }
    .to-stay22-iframe { width: 100%; height: 480px; border: 0; border-radius: 0.75rem; background: #F8FAFC; }
    @keyframes toPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(230,126,34,0.45); transform: scale(1); } 50% { box-shadow: 0 0 0 6px rgba(230,126,34,0); transform: scale(1.03); } }
    .to-pulse { animation: toPulse 1.6s ease-in-out infinite; background: #E67E22 !important; }
</style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<!-- flatpickr pentru date picker DD/MM/YYYY (locale ro) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/ro.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<?php require dirname(__DIR__) . '/_partials/sidebar.php'; ?>

<main class="min-h-screen pt-16 lg:ml-64 lg:pt-0" x-data="tourOptimizer()" x-init="init()" x-cloak>
    <div class="p-4 lg:p-8">

        <!-- Page Header -->
        <div class="mb-6">
            <div class="flex items-center gap-2 mb-2">
                <span class="pro-badge">PRO</span>
                <span class="text-xs font-semibold tracking-wider uppercase text-muted">Extended Artist · Tour Optimizer</span>
            </div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-2xl font-bold lg:text-3xl text-secondary">Tour Optimizer</h1>
                    <p class="mt-1 text-muted">Planifică turnee strategice pe baza datelor reale despre fanii tăi.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="setTab('planner')" class="to-btn to-btn-primary to-btn-sm">+ Tour nou</button>
                </div>
            </div>
        </div>

        <!-- KPI strip -->
        <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-4">
            <div class="p-5 bg-white border border-border rounded-2xl">
                <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Orașe oportunitate</p>
                <p class="text-2xl font-bold text-secondary" x-text="opportunities.kpis?.opportunity_cities ?? 0"></p>
                <p class="mt-1 text-xs text-success">cu fani neexploatați</p>
            </div>
            <div class="p-5 bg-white border border-border rounded-2xl">
                <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Fani „dormiți"</p>
                <p class="text-2xl font-bold text-secondary" x-text="formatNumber(opportunities.kpis?.dormant_fans ?? 0)"></p>
                <p class="mt-1 text-xs text-warning">nu au mai venit &gt;12 luni</p>
            </div>
            <div class="p-5 bg-white border border-border rounded-2xl">
                <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Bilete predictibile</p>
                <p class="text-2xl font-bold text-secondary" x-text="'~' + formatNumber(opportunities.kpis?.predicted_tickets ?? 0)"></p>
                <p class="mt-1 text-xs text-muted">tour 5 orașe optimizat</p>
            </div>
            <div class="p-5 bg-white border border-border rounded-2xl">
                <p class="mb-2 text-xs font-semibold tracking-wider uppercase text-muted">Scenarii salvate</p>
                <p class="text-2xl font-bold text-secondary" x-text="opportunities.kpis?.saved_scenarios ?? 0"></p>
                <p class="mt-1 text-xs text-muted">draft + active</p>
            </div>
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

            <!-- Tabs (NU adăuga overflow-hidden aici — strică sticky toolbar din itinerar) -->
            <div class="bg-white border rounded-2xl border-border">
                <div class="overflow-x-auto border-b border-border rounded-t-2xl">
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

                <!-- ============ TAB: OPPORTUNITIES ============ -->
                <div x-show="tab === 'opportunities'" class="p-6">
                    <div class="grid gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2">
                            <h2 class="mb-1 text-lg font-bold text-secondary">Hartă oportunități</h2>
                            <p class="mb-4 text-sm text-muted">Densitate fani vs concerte. Verde = activ, galben = revenire necesară, roșu = dormit, burgund = neexplorat.</p>
                            <div id="opportunityMap" style="height: 480px;"></div>
                            <div class="grid grid-cols-2 gap-3 mt-4 text-sm sm:grid-cols-4">
                                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-success"></span><span class="text-muted">Activ recent</span></div>
                                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-warning"></span><span class="text-muted">Revenire necesară</span></div>
                                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-error"></span><span class="text-muted">Dormit</span></div>
                                <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-primary"></span><span class="text-muted">Neexplorat</span></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-1 font-bold text-secondary">Top recomandări</h3>
                            <p class="mb-4 text-xs text-muted">Orașe cu cel mai mare potențial nemonetizat</p>

                            <div class="space-y-3">
                                <template x-for="(rec, idx) in opportunities.recommendations || []" :key="rec.city">
                                    <div class="p-4 transition-all border border-border rounded-xl hover:border-primary/30 hover:shadow-sm">
                                        <div class="flex items-start justify-between mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="flex items-center justify-center w-6 h-6 text-xs font-bold rounded-md bg-primary/10 text-primary" x-text="idx + 1"></span>
                                                <h4 class="font-bold text-secondary" x-text="rec.city"></h4>
                                            </div>
                                            <span class="to-badge" :class="statusBadgeClass(rec.status)" x-text="rec.status_label"></span>
                                        </div>
                                        <p class="mb-3 text-xs text-muted" x-text="rec.reason"></p>
                                        <div class="grid grid-cols-2 gap-2 mb-3 text-xs">
                                            <div class="p-2 rounded-lg bg-surface">
                                                <p class="text-muted">Fani locali</p>
                                                <p class="font-bold text-secondary" x-text="formatNumber(rec.fans)"></p>
                                            </div>
                                            <div class="p-2 rounded-lg bg-surface">
                                                <p class="text-muted">Predicție</p>
                                                <p class="font-bold text-secondary" x-text="rec.prediction"></p>
                                            </div>
                                        </div>
                                        <button @click="addCityToPlanner(rec.city)" class="w-full to-btn to-btn-secondary to-btn-sm">+ Adaugă în planner</button>
                                    </div>
                                </template>
                                <p x-show="!(opportunities.recommendations || []).length" class="text-sm text-muted">Date insuficiente — revino după 3+ concerte.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 mt-8 lg:grid-cols-2" x-show="(opportunities.dormant_alerts || []).length">
                        <template x-for="alert in opportunities.dormant_alerts || []" :key="alert.city">
                            <div class="p-5 border-2 border-warning/30 bg-warning/5 rounded-2xl">
                                <div class="flex items-start gap-3">
                                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 bg-warning/20 rounded-xl">⚠️</div>
                                    <div class="flex-1">
                                        <p class="mb-1 font-bold text-secondary" x-text="'⚠️ ' + alert.message"></p>
                                        <p class="mb-3 text-sm text-muted">Risc churn ridicat. O dată pe an ar putea recupera 60-70% din audiență.</p>
                                        <button @click="addCityToPlanner(alert.city); setTab('planner')" class="to-btn to-btn-primary to-btn-sm">Planifică <span x-text="alert.city"></span></button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- ============ TAB: PLANNER ============ -->
                <div x-show="tab === 'planner'" class="p-6">
                    <!-- Toggle pentru sidebar (vizibil doar pe lg+) -->
                    <button @click="sidebarCollapsed = !sidebarCollapsed" type="button"
                        class="hidden lg:inline-flex items-center gap-2 mb-4 text-xs font-semibold px-3 py-1.5 rounded-lg border bg-surface text-muted border-border hover:bg-primary/5 hover:text-primary hover:border-primary/30 transition-colors">
                        <span x-text="sidebarCollapsed ? '▶ Arată setări tour' : '◀ Ascunde setări tour'"></span>
                    </button>
                    <div class="grid gap-6" :class="sidebarCollapsed ? 'lg:grid-cols-1' : 'lg:grid-cols-12'">
                        <div x-show="!sidebarCollapsed" class="lg:col-span-4">
                            <div class="sticky space-y-5 top-6">
                                <div class="p-5 bg-white border border-border rounded-2xl">
                                    <label class="block mb-2 text-xs font-bold tracking-wider uppercase text-muted">Nume turneu</label>
                                    <input type="text" x-model="planner.name" class="mb-3 to-input" placeholder="Tour Vară 2026">
                                    <label class="block mb-2 text-xs font-bold tracking-wider uppercase text-muted">Perioadă</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <input type="date" x-model="planner.startDate" class="text-sm to-input">
                                        <input type="date" x-model="planner.endDate" class="text-sm to-input">
                                    </div>
                                </div>

                                <div class="p-5 bg-white border border-border rounded-2xl">
                                    <div class="flex items-center justify-between mb-3">
                                        <label class="text-xs font-bold tracking-wider uppercase text-muted">Orașe în turneu</label>
                                        <span class="text-xs text-muted"><span x-text="planner.cities.length"></span> orașe</span>
                                    </div>

                                    <p class="text-[11px] text-muted mb-3">Adaugă orașele aici, apoi setează data + venue + ordine direct în <strong>Itinerar</strong> după ce optimizezi.</p>

                                    <div class="mb-3 space-y-2">
                                        <template x-for="(city, idx) in planner.cities" :key="city.uid">
                                            <div class="flex items-center gap-2 p-2 rounded-lg bg-surface">
                                                <span class="flex items-center justify-center flex-shrink-0 w-6 h-6 text-xs font-bold rounded bg-primary/10 text-primary" x-text="idx + 1"></span>
                                                <span class="flex-1 text-sm font-medium truncate text-secondary" x-text="city.name"></span>
                                                <button @click="removeCity(idx)" class="p-1 text-muted hover:text-error" title="Șterge">✕</button>
                                            </div>
                                        </template>
                                        <p x-show="!planner.cities.length" class="py-2 text-xs text-center text-muted">Adaugă cel puțin 2 orașe.</p>
                                    </div>

                                    <input type="text" x-model="cityInput" @keydown.enter.prevent="addCityFromInput()" placeholder="Adaugă oraș (Enter)" class="text-sm to-input">
                                    <div class="flex flex-wrap gap-1 mt-3">
                                        <template x-for="quick in quickCities">
                                            <button @click="addCity(quick)" class="px-2 py-1 text-xs transition-colors rounded bg-surface text-muted hover:bg-primary/10 hover:text-primary">+ <span x-text="quick"></span></button>
                                        </template>
                                    </div>
                                </div>

                                <!-- Setări tour: organizate pe secțiuni clare -->
                                <div class="p-5 bg-white border border-border rounded-2xl">
                                    <button @click="planner.configOpen = !planner.configOpen" class="flex items-center justify-between w-full">
                                        <span class="text-xs font-bold tracking-wider uppercase text-muted">⚙️ Setări tour</span>
                                        <svg class="w-4 h-4 transition-transform text-muted" :class="planner.configOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </button>

                                    <div x-show="planner.configOpen" x-transition class="mt-4 space-y-5">

                                        <!-- 📍 Plecare turneu -->
                                        <div>
                                            <p class="to-section-title"><span class="icon">📍</span> Plecare turneu
                                                <span class="to-tip" data-tip="Locația de bază (home base) — orașul de unde pleacă echipa la primul concert și unde se întoarce la final. Folosit pentru calculul combustibilului total dus-întors.">ⓘ</span>
                                            </p>
                                            <div x-data="{ open: false, query: '' }" @click.outside="open = false" class="relative">
                                                <input type="text"
                                                    :value="open ? query : (planner.config.start_location + (homeBaseSubtitle(planner.config.start_location) ? ' · ' + homeBaseSubtitle(planner.config.start_location) : ''))"
                                                    @focus="open = true; query = ''"
                                                    @input="query = $event.target.value; open = true"
                                                    @keydown.escape="open = false"
                                                    placeholder="Caută oraș..."
                                                    class="text-sm to-input">
                                                <div x-show="open" x-cloak class="absolute left-0 right-0 z-50 mt-1 overflow-y-auto bg-white border rounded-lg shadow-lg border-border max-h-72">
                                                    <template x-for="city in filterHomeBaseOptions(query)" :key="city.name">
                                                        <button type="button"
                                                            @click="planner.config.start_location = city.name; open = false; query = ''; markDirty()"
                                                            class="w-full px-3 py-2 text-left transition-colors border-b hover:bg-surface border-border/40 last:border-0"
                                                            :class="planner.config.start_location === city.name ? 'bg-primary/5' : ''">
                                                            <p class="text-xs font-semibold text-secondary" x-text="city.name"></p>
                                                            <p class="text-xs text-muted" x-text="(city.state ? city.state + ', ' : '') + (city.country || '')"></p>
                                                        </button>
                                                    </template>
                                                    <template x-if="filterHomeBaseOptions(query).length === 0">
                                                        <p class="p-3 text-xs text-center text-muted">Niciun rezultat pentru „<span x-text="query"></span>"</p>
                                                    </template>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 🚐 Vehicule -->
                                        <div>
                                            <p class="to-section-title"><span class="icon">🚐</span> Vehicule transport
                                                <span class="to-tip" data-tip="Toate vehiculele cu care călătorește echipa. Combustibilul total = suma per vehicul × distanța × consum / 100 × preț/L. Adaugă o linie nouă pentru fiecare tip de vehicul (poate avea mai multe bucăți).">ⓘ</span>
                                            </p>
                                            <div class="grid grid-cols-12 gap-1 px-1 mb-1 text-xs font-semibold text-muted">
                                                <div class="col-span-4">Tip</div>
                                                <div class="col-span-2 text-center">Buc</div>
                                                <div class="col-span-2 text-center">Locuri</div>
                                                <div class="col-span-3 text-center">L/100km</div>
                                                <div class="col-span-1"></div>
                                            </div>
                                            <div class="space-y-2">
                                                <template x-for="(v, vi) in planner.config.vehicles" :key="vi">
                                                    <div class="grid items-center grid-cols-12 gap-1">
                                                        <select x-model="v.type" class="col-span-4 text-xs to-input">
                                                            <option value="van">Dubă</option>
                                                            <option value="microbus">Microbus</option>
                                                            <option value="truck">Camion</option>
                                                            <option value="suv">SUV</option>
                                                            <option value="bus">Autocar</option>
                                                        </select>
                                                        <input type="number" min="1" max="10" x-model.number="v.count" class="col-span-2 text-xs text-center to-input">
                                                        <input type="number" min="1" max="60" x-model.number="v.capacity_seats" class="col-span-2 text-xs text-center to-input">
                                                        <input type="number" step="0.1" min="1" max="50" x-model.number="v.consumption_l_100km" class="col-span-3 text-xs text-center to-input">
                                                        <button @click="planner.config.vehicles.splice(vi, 1)" :disabled="planner.config.vehicles.length === 1" class="col-span-1 text-muted hover:text-error disabled:opacity-30">✕</button>
                                                    </div>
                                                </template>
                                            </div>
                                            <button @click="planner.config.vehicles.push({type:'van',count:1,capacity_seats:8,consumption_l_100km:9.5})" class="mt-2 text-xs text-primary hover:underline">+ Adaugă vehicul</button>
                                        </div>

                                        <!-- ⛽ Combustibil -->
                                        <div>
                                            <p class="to-section-title"><span class="icon">⛽</span> Combustibil
                                                <span class="to-tip" data-tip="Tipul și prețul actual al combustibilului. Default 7.50 RON/L (referință 2026). Verifică pe peco.ro pentru prețul curent.">ⓘ</span>
                                            </p>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block mb-1 text-xs text-muted">Tip</label>
                                                    <select x-model="planner.config.fuel_type" class="text-xs to-input">
                                                        <option value="diesel">Motorină</option>
                                                        <option value="gasoline">Benzină</option>
                                                        <option value="electric">Electric</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block mb-1 text-xs text-muted">Preț RON / litru</label>
                                                    <input type="number" step="0.01" min="0" x-model.number="planner.config.fuel_price_ron_l" class="text-xs to-input">
                                                </div>
                                            </div>
                                        </div>

                                        <!-- 👥 Echipă & cazare -->
                                        <div>
                                            <p class="to-section-title"><span class="icon">👥</span> Echipă & cazare
                                                <span class="to-tip" data-tip="Persoanele care călătoresc + împărțirea pe camere (single, double = 2 pers, apartament = 4 pers). Capacitatea trebuie să acopere persoanele.">ⓘ</span>
                                            </p>
                                            <label class="block mb-1 text-xs text-muted">Persoane în turneu</label>
                                            <input type="number" min="1" max="50" x-model.number="planner.config.people_count" class="mb-3 text-xs to-input">

                                            <div class="grid grid-cols-3 gap-2">
                                                <div>
                                                    <label class="block mb-1 text-xs text-muted">Single</label>
                                                    <input type="number" min="0" x-model.number="planner.config.rooms.single" placeholder="buc" class="text-xs to-input">
                                                    <input type="number" min="0" x-model.number="planner.config.room_prices.single" placeholder="RON/n" class="mt-1 text-xs to-input">
                                                </div>
                                                <div>
                                                    <label class="block mb-1 text-xs text-muted">Double</label>
                                                    <input type="number" min="0" x-model.number="planner.config.rooms.double" placeholder="buc" class="text-xs to-input">
                                                    <input type="number" min="0" x-model.number="planner.config.room_prices.double" placeholder="RON/n" class="mt-1 text-xs to-input">
                                                </div>
                                                <div>
                                                    <label class="block mb-1 text-xs text-muted">Apartament</label>
                                                    <input type="number" min="0" x-model.number="planner.config.rooms.apartment" placeholder="buc" class="text-xs to-input">
                                                    <input type="number" min="0" x-model.number="planner.config.room_prices.apartment" placeholder="RON/n" class="mt-1 text-xs to-input">
                                                </div>
                                            </div>
                                            <p class="mt-2 text-xs" :class="roomCapacityValid ? 'text-success' : 'text-warning'">
                                                Capacitate: <span x-text="totalRoomCapacity"></span> persoane &middot; configurat pentru <span x-text="planner.config.people_count"></span>
                                                <span x-show="!roomCapacityValid"> &middot; ⚠️ camere insuficiente</span>
                                            </p>
                                        </div>

                                        <!-- 🍽️ Diurnă -->
                                        <div>
                                            <p class="to-section-title"><span class="icon">🍽️</span> Diurnă
                                                <span class="to-tip" data-tip="Mâncare RON/persoană/zi. Total = persoane × preț/zi × durata totală a tour-ului, distribuit egal per concert.">ⓘ</span>
                                            </p>
                                            <label class="block mb-1 text-xs text-muted">Preț RON / persoană / zi</label>
                                            <input type="number" min="0" x-model.number="planner.config.meal_price_per_day" class="text-xs to-input">
                                        </div>

                                        <!-- 🎟️ Venit -->
                                        <div>
                                            <p class="to-section-title"><span class="icon">🎟️</span> Venit estimat
                                                <span class="to-tip" data-tip="Preț mediu de bilet (RON). Folosit pentru a calcula venitul prognozat per concert și per tour total. Influențează profit + ROI.">ⓘ</span>
                                            </p>
                                            <label class="block mb-1 text-xs text-muted">Preț mediu bilet (RON)</label>
                                            <input type="number" min="0" x-model.number="planner.config.avg_ticket_price" class="text-xs to-input">
                                        </div>
                                    </div>
                                </div>

                                <div class="p-5 bg-white border border-border rounded-2xl">
                                    <label class="block mb-3 text-xs font-bold tracking-wider uppercase text-muted">Constrângeri rută</label>
                                    <div class="space-y-3">
                                        <div>
                                            <label class="block mb-1 text-xs text-muted">Zile minime între concerte (auto-schedule)</label>
                                            <input type="range" x-model.number="planner.minDaysBetween" min="1" max="7" class="w-full">
                                            <p class="mt-1 text-xs font-bold text-secondary" x-text="planner.minDaysBetween + ' zile'"></p>
                                        </div>
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" x-model="planner.includeBorder" class="w-4 h-4 rounded text-primary">
                                            <span class="text-xs text-secondary">Include orașe cross-border (CEE)</span>
                                        </label>
                                    </div>
                                </div>

                                <button @click="optimizeRoute()" :disabled="planner.cities.length < 2 || tabLoading" class="w-full to-btn to-btn-primary">
                                    ⚡ Optimizează rută
                                </button>
                            </div>
                        </div>

                        <div :class="sidebarCollapsed ? '' : 'lg:col-span-8'">
                            <div x-show="!planner.optimized">
                                <div class="p-12 text-center border border-dashed bg-surface border-border rounded-2xl">
                                    <div class="flex items-center justify-center w-16 h-16 mx-auto mb-4 bg-white rounded-full">🎯</div>
                                    <p class="font-medium text-secondary">Adaugă orașe și apasă „Optimizează rută"</p>
                                    <p class="mt-1 text-sm text-muted">Algoritm-ul calculează ordinea optimă, distanțe și predicții bilete.</p>
                                </div>
                            </div>

                            <div x-show="planner.optimized">
                                <div class="grid grid-cols-2 gap-3 mb-4 lg:grid-cols-4">
                                    <div class="p-4 bg-white border border-border rounded-xl">
                                        <p class="flex items-center gap-1 text-xs font-semibold tracking-wider uppercase text-muted">
                                            Distanță rută
                                            <span class="to-tip" :data-tip="planner.summary?.routing_has_fallback ? 'Unele segmente folosesc aproximare (Haversine × 1.35) pentru că OSRM era indisponibil. Dă Recalculează mai târziu pentru valori exacte.' : 'Distanță reală pe ruta auto, calculată cu OpenStreetMap (OSRM). Cache 30 zile per rută.'">ⓘ</span>
                                        </p>
                                        <div class="mt-1 text-xl font-bold text-secondary">
                                            ~<span x-text="formatNumber(planner.summary?.total_road_distance_km ?? 0)"></span> km
                                            <p class="ml-2 text-xs font-semibold text-muted">(<span x-text="formatDuration(planner.summary?.total_drive_time_min ?? 0)"></span> de condus)</p>
                                        </div>                                        
                                    </div>
                                    <div class="p-4 bg-white border border-border rounded-xl">
                                        <p class="text-xs font-semibold tracking-wider uppercase text-muted">Durată tour</p>
                                        <p class="mt-1 text-xl font-bold text-secondary"><span x-text="planner.summary?.duration_days ?? 0"></span> zile</p>
                                    </div>
                                    <div class="p-4 bg-white border border-border rounded-xl">
                                        <p class="text-xs font-semibold tracking-wider uppercase text-muted">Cost total*</p>
                                        <p class="mt-1 text-xl font-bold text-secondary">~<span x-text="formatNumber(planner.summary?.total_cost_ron ?? 0)"></span> RON</p>
                                    </div>
                                    <div class="p-4 bg-white border border-border rounded-xl">
                                        <p class="text-xs font-semibold tracking-wider uppercase text-muted">Bilete prog.</p>
                                        <p class="mt-1 text-xl font-bold text-success">~<span x-text="formatNumber(planner.summary?.predicted_tickets ?? 0)"></span></p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3 mb-4 sm:grid-cols-4">
                                    <div class="p-3 text-center border bg-surface rounded-xl border-border">
                                        <p class="text-xs font-semibold uppercase text-muted">⛽ Combustibil</p>
                                        <p class="text-sm font-bold text-secondary"><span x-text="formatNumber(planner.summary?.fuel_cost_ron ?? 0)"></span> RON</p>
                                    </div>
                                    <div class="p-3 text-center border bg-surface rounded-xl border-border">
                                        <p class="text-xs font-semibold uppercase text-muted">🛏️ Cazare</p>
                                        <p class="text-sm font-bold text-secondary"><span x-text="formatNumber(planner.summary?.accommodation_cost_ron ?? 0)"></span> RON</p>
                                    </div>
                                    <div class="p-3 text-center border bg-surface rounded-xl border-border">
                                        <p class="text-xs font-semibold uppercase text-muted">🍽️ Mâncare</p>
                                        <p class="text-sm font-bold text-secondary"><span x-text="formatNumber(planner.summary?.meal_cost_ron ?? 0)"></span> RON</p>
                                    </div>
                                    <div class="p-3 text-center rounded-xl" :class="(planner.summary?.extra_cost_ron ?? 0) > 0 ? 'bg-warning/5 border border-warning/20' : 'bg-surface border border-border'">
                                        <p class="text-xs font-semibold uppercase text-muted">➕ Extra</p>
                                        <p class="text-sm font-bold text-secondary"><span x-text="formatNumber(planner.summary?.extra_cost_ron ?? 0)"></span> RON</p>
                                    </div>
                                </div>

                                <p class="mb-4 text-xs text-center text-muted">* Toate costurile sunt <strong>estimative</strong>, calculate din setările tale (vehicule, cazare, diurnă).</p>

                                <div class="grid grid-cols-3 gap-3 mb-4">
                                    <div class="p-3 text-center border bg-success/5 border-success/20 rounded-xl">
                                        <p class="text-xs font-semibold uppercase text-success">💰 Venit estimat</p>
                                        <p class="text-base font-bold text-success">~<span x-text="formatNumber(planner.summary?.predicted_revenue_ron ?? 0)"></span> RON</p>
                                    </div>
                                    <div class="p-3 text-center border rounded-xl" :class="(planner.summary?.profit_ron ?? 0) >= 0 ? 'bg-success/10 border-success/30' : 'bg-error/10 border-error/30'">
                                        <p class="text-xs font-semibold uppercase" :class="(planner.summary?.profit_ron ?? 0) >= 0 ? 'text-success' : 'text-error'">📈 Profit estimat</p>
                                        <p class="text-base font-bold" :class="(planner.summary?.profit_ron ?? 0) >= 0 ? 'text-success' : 'text-error'">~<span x-text="formatNumber(planner.summary?.profit_ron ?? 0)"></span> RON</p>
                                    </div>
                                    <div class="p-3 text-center border rounded-xl" :class="(planner.summary?.margin_pct ?? 0) >= 0 ? 'bg-success/10 border-success/30' : 'bg-error/10 border-error/30'">
                                        <p class="text-xs font-semibold uppercase" :class="(planner.summary?.margin_pct ?? 0) >= 0 ? 'text-success' : 'text-error'">% Marjă</p>
                                        <p class="text-base font-bold" :class="(planner.summary?.margin_pct ?? 0) >= 0 ? 'text-success' : 'text-error'"><span x-text="planner.summary?.margin_pct ?? 0"></span>%</p>
                                    </div>
                                </div>

                                <div id="plannerMap" style="height: 320px; margin-bottom: 16px;"></div>

                                <div class="bg-white border border-border rounded-2xl">
                                    <!-- Sticky toolbar — rămâne vizibilă cât scroll-uiești prin orașe.
                                         Auto-recalc rulează la 1s după orice editare → nu mai e nevoie de buton manual. -->
                                    <div class="sticky top-0 z-30 px-5 py-3 bg-white border-b shadow-sm border-border rounded-t-2xl">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h3 class="font-bold text-secondary">Itinerar optim</h3>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span x-show="tabLoading" class="inline-flex items-center gap-2 text-xs font-semibold text-primary">
                                                    <span class="inline-block w-3 h-3 border-2 rounded-full border-primary border-t-transparent animate-spin"></span>
                                                    Se recalculează...
                                                </span>
                                                <span x-show="!tabLoading && planner.dirty" class="text-xs font-semibold text-warning">⏳ recalcul în curând...</span>
                                                <span x-show="!tabLoading && !planner.dirty && planner.optimized" class="text-xs font-semibold text-success">✓ actualizat</span>
                                                <!-- Buton Sortează cronologic — apare DOAR când există inconsistențe cronologice -->
                                                <button x-show="outOfOrderStops.length > 0" @click="sortChronologically()" :disabled="tabLoading"
                                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg border bg-error/5 text-error border-error/30 hover:bg-error/10 transition-colors"
                                                    :title="outOfOrderStops.length + ' stop-uri în afara ordinii cronologice'">
                                                    🕒 Sortează cronologic (<span x-text="outOfOrderStops.length"></span>)
                                                </button>
                                                <button @click="saveScenario()" :disabled="planner.saving || tabLoading"
                                                    class="to-btn to-btn-primary to-btn-sm">
                                                    <span x-text="planner.saving ? 'Se salvează...' : '💾 Salvează'"></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-5">
                                        <p class="mb-3 text-xs text-muted">Trage cardurile pentru a reordona. Editează data, venue-ul, costuri etc — totul se recalculează automat după ~1 secundă de inactivitate.</p>

                                    <div x-ref="routeList" class="space-y-3">
                                        <template x-for="(stop, idx) in planner.route || []" :key="stop.city + '-' + idx">
                                            <div x-data="{ expanded: !stop.fixed }" class="p-4 transition-colors rounded-xl" :data-idx="idx"
                                                :class="isStopOutOfOrder(idx) ? 'border-2 border-error bg-error/5' : (stop.fixed ? 'border-2 border-success bg-success/5' : (stop.is_home ? 'border border-accent/40 bg-accent/5' : 'border border-border hover:bg-surface/50'))">
                                                <div class="flex items-start gap-3">
                                                    <span x-show="!stop.fixed" class="flex-shrink-0 pt-1 text-xl leading-none select-none cursor-grab text-muted route-handle" title="Trage pentru reordonare">⋮⋮</span>
                                                    <span x-show="stop.fixed" class="flex-shrink-0 pt-1 text-xl leading-none text-success" title="Concert confirmat — drag dezactivat">🔒</span>
                                                    <button @click="expanded = !expanded" type="button"
                                                        class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors bg-surface text-muted border-border hover:bg-primary/5 hover:text-primary hover:border-primary/30">
                                                        <span x-text="expanded ? '▲ ' : '▼ '"></span>
                                                    </button>
                                                    <div class="flex items-center justify-center flex-shrink-0 w-10 h-10 font-bold text-white rounded-xl bg-gradient-to-br from-primary to-primary-dark" x-text="idx + 1"></div>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                                            <p class="font-bold text-secondary" x-text="stop.city"></p>
                                                            <span x-show="isStopOutOfOrder(idx)" class="text-xs to-badge bg-error/15 text-error" :title="'Data ' + stop.date + ' e mai veche decât concertul precedent'">⚠️ dată în afara ordinii</span>
                                                            <span x-show="stop.is_home" class="text-xs to-badge bg-accent/15 text-accent">🏠 acasă</span>
                                                            <span x-show="stop.fixed" class="text-xs to-badge bg-success/15 text-success">✓ confirmat</span>
                                                            <span x-show="stop.from_start && !stop.is_home" class="text-xs to-badge bg-accent/10 text-accent">↩ din <span x-text="planner.config.start_location"></span></span>
                                                            <span x-show="stop.venue_name" class="text-xs font-medium text-secondary" x-text="'· ' + stop.venue_name"></span>
                                                            <span x-show="stop.effective_capacity" class="text-xs to-badge bg-primary/10 text-primary"><span x-text="formatNumber(stop.effective_capacity)"></span> loc</span>
                                                            <span x-show="stop.manual_capacity && !stop.venue_capacity" class="to-badge bg-warning/10 text-warning text-[9px]" title="Capacitate setată manual">manual</span>
                                                        </div>
                                                        <p class="text-xs text-muted"><span x-text="stop.day" class="font-semibold capitalize"></span> · <span x-text="stop.date" class="font-semibold"></span> <span x-show="stop.arrival_road_km > 0"> · sosire ~<span x-text="formatNumber(stop.arrival_road_km)"></span> km (<span x-text="formatDuration(stop.arrival_drive_time_min)"></span>)</span><span x-show="stop.is_home"> · 🏠 nu e drum (concertul e acasă)</span></p>
                                                    </div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <button @click="toggleStopFixed(idx); expanded = !stop.fixed"
                                                            :class="stop.fixed ? 'bg-success/10 text-success border-success/30' : 'bg-surface text-muted border-border hover:bg-success/5 hover:text-success hover:border-success/30'"
                                                            class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors">
                                                            <span x-text="stop.fixed ? '🔓 Anulează confirmarea' : '🔒 Confirmă concert'"></span>
                                                        </button>
                                                        <span class="text-xs text-muted" x-show="stop.fixed">Concertul e blocat — data și venue-ul nu mai pot fi modificate.</span>
                                                    </div>
                                                    <div class="flex-shrink-0 text-right">
                                                        <p class="text-xs text-muted">Predicție</p>
                                                        <p class="text-sm font-bold text-success"><span x-text="formatNumber(stop.prediction)"></span> bilete</p>
                                                        <p class="text-xs text-muted">
                                                            <span x-show="stop.manual_prediction !== null">manual ·</span>
                                                            <span x-text="stop.confidence"></span>% confidence
                                                            <span class="to-tip" :data-tip="confidenceText(stop)">ⓘ</span>
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Tot conținutul de mai jos e collapsible -->
                                                <div x-show="expanded" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">

                                                <!-- Inline edit row: data + venue -->
                                                <div class="grid grid-cols-1 gap-2 my-3 sm:grid-cols-2 ml-7 sm:ml-12">
                                                    <div>
                                                        <label class="block mb-1 text-xs text-muted">📅 Data eveniment</label>
                                                        <input type="date" :value="stop.date_iso" @change="updateStopDate(idx, $event.target.value)" class="text-xs to-input disabled:bg-surface disabled:cursor-not-allowed" :min="planner.startDate" :max="planner.endDate" :disabled="stop.fixed">
                                                    </div>
                                                    <div x-data="{ open: false, query: '' }" @click.outside="open = false">
                                                        <label class="block mb-1 text-xs text-muted">🏟️ Locație (caută & alege)</label>
                                                        <div class="relative">
                                                            <input type="text"
                                                                :value="open ? query : (stop.venue_name || '')"
                                                                @focus="!stop.fixed && (open = true, query = '', loadVenuesForCity(stop.city))"
                                                                @input="query = $event.target.value; open = true"
                                                                @keydown.escape="open = false"
                                                                placeholder="Click pentru a vedea toate venues, sau caută..."
                                                                :disabled="stop.fixed"
                                                                class="text-xs to-input pr-7 disabled:bg-surface disabled:cursor-not-allowed">
                                                            <button type="button" x-show="stop.venue_id && !stop.fixed" @click.stop="selectVenueForStop(idx, null); query = ''" class="absolute text-xs -translate-y-1/2 right-2 top-1/2 text-muted hover:text-error" title="Șterge selecția">✕</button>
                                                            <div x-show="open && !stop.fixed" x-cloak class="absolute left-0 right-0 z-50 mt-1 overflow-y-auto bg-white border rounded-lg shadow-lg border-border max-h-72">
                                                                <template x-if="!venuesByCity[stop.city] || venuesByCity[stop.city].length === 0">
                                                                    <p class="p-3 text-xs text-center text-muted">
                                                                        <template x-if="!venuesByCity[stop.city]"><span>Se caută venues în <span x-text="stop.city"></span>…</span></template>
                                                                        <template x-if="venuesByCity[stop.city] && !venuesByCity[stop.city].length"><span>Niciun venue înregistrat în <span x-text="stop.city"></span>.</span></template>
                                                                    </p>
                                                                </template>
                                                                <template x-for="v in filterVenues(venuesByCity[stop.city] || [], query)" :key="v.id">
                                                                    <button type="button"
                                                                        @click="selectVenueForStop(idx, v); open = false; query = ''"
                                                                        class="w-full px-3 py-2 text-left transition-colors border-b hover:bg-surface border-border/40 last:border-0"
                                                                        :class="stop.venue_id === v.id ? 'bg-primary/5' : ''">
                                                                        <p class="text-xs font-semibold text-secondary" x-text="v.name"></p>
                                                                        <div class="flex items-center justify-between mt-0.5">
                                                                            <p class="flex-1 text-xs truncate text-muted" x-text="v.address || '—'"></p>
                                                                            <span x-show="v.capacity_total" class="flex-shrink-0 ml-2 text-xs font-bold text-primary"><span x-text="formatNumber(v.capacity_total)"></span> loc</span>
                                                                        </div>
                                                                    </button>
                                                                </template>
                                                                <template x-if="venuesByCity[stop.city]?.length && filterVenues(venuesByCity[stop.city] || [], query).length === 0">
                                                                    <p class="p-3 text-xs text-center text-muted">Niciun rezultat pentru „<span x-text="query"></span>"</p>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Manual overrides: capacitate + estimat vânzări -->
                                                <div class="grid grid-cols-1 gap-2 mb-3 sm:grid-cols-2 ml-7 sm:ml-12">
                                                    <div>
                                                        <label class="block mb-1 text-xs text-muted">
                                                            🎟️ Capacitate locație
                                                            <span class="to-tip" data-tip="Dacă venue-ul nu are capacitatea înregistrată sau vrei să o suprascrii pentru această ocazie. Lasă gol pentru a folosi capacitatea din DB.">ⓘ</span>
                                                        </label>
                                                        <input type="number" min="0" max="200000"
                                                            :value="stop.manual_capacity || ''"
                                                            @input.debounce.500ms="updateStopManualCapacity(idx, $event.target.value)"
                                                            :placeholder="stop.venue_capacity ? ('din DB: ' + stop.venue_capacity) : 'ex: 1500'"
                                                            class="text-xs to-input">
                                                    </div>
                                                    <div>
                                                        <label class="block mb-1 text-xs text-muted">
                                                            📊 Estimat vânzări (manual)
                                                            <span class="to-tip" data-tip="Dacă ai propria estimare pentru numărul de bilete vândute, scrie-o aici. Va suprascrie predicția automată. Lasă gol pentru predicția algoritmului.">ⓘ</span>
                                                        </label>
                                                        <input type="number" min="0" max="200000"
                                                            :value="stop.manual_prediction || ''"
                                                            @input.debounce.500ms="updateStopManualPrediction(idx, $event.target.value)"
                                                            :placeholder="'algoritm: ~' + (stop.prediction || 0) + ' bilete'"
                                                            class="text-xs to-input">
                                                    </div>
                                                </div>

                                                <!-- Costuri suplimentare opționale -->
                                                <div class="grid grid-cols-1 gap-2 mb-3 sm:grid-cols-3 ml-7 sm:ml-12">
                                                    <div>
                                                        <label class="block mb-1 text-xs text-muted">
                                                            ➕ Costuri suplimentare (RON)
                                                            <span class="to-tip" data-tip="Costuri ad-hoc pentru acest stop care nu intră în combustibil/cazare/diurnă: tehnică, securitate, transport echipament local, etc. Se adaugă la totalul stop-ului. Lasă gol = 0.">ⓘ</span>
                                                        </label>
                                                        <input type="number" min="0" max="1000000" step="0.01"
                                                            :value="stop.extra_cost || ''"
                                                            @input.debounce.500ms="updateStopExtraCost(idx, $event.target.value)"
                                                            placeholder="ex: 1500"
                                                            class="text-xs to-input">
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label class="block mb-1 text-xs text-muted">
                                                            📝 Descriere costuri suplimentare
                                                            <span class="to-tip" data-tip="Ce reprezintă cei X RON. Doar pentru evidență — nu afectează calculele.">ⓘ</span>
                                                        </label>
                                                        <input type="text" maxlength="300"
                                                            :value="stop.extra_cost_description || ''"
                                                            @input.debounce.700ms="updateStopExtraCostDescription(idx, $event.target.value)"
                                                            placeholder="ex: Sunet & lumini local, taxă publicitate"
                                                            class="text-xs to-input">
                                                    </div>
                                                </div>

                                                <label x-show="!stop.is_home" class="flex items-center gap-2 mb-3 cursor-pointer ml-7 sm:ml-12">
                                                    <input type="checkbox"
                                                        :checked="idx === 0 ? true : stop.from_start"
                                                        @change="updateStopFromStart(idx, $event.target.checked)"
                                                        class="w-4 h-4 rounded text-primary">
                                                    <span class="text-xs text-secondary">
                                                        <template x-if="idx === 0">
                                                            <span>🚀 Plecare din <strong x-text="planner.config.start_location"></strong> (primul concert)</span>
                                                        </template>
                                                        <template x-if="idx > 0">
                                                            <span>↩ Plecare din <strong x-text="planner.config.start_location"></strong> (nu din concertul precedent)</span>
                                                        </template>
                                                        <span class="to-tip" data-tip="Activează dacă echipa pleacă din home base înainte de acest concert (nu din concertul precedent). Combustibilul se calculează de la home base. Concertul precedent va include drumul de retur acasă.">ⓘ</span>
                                                    </span>
                                                </label>

                                                <!-- Cost breakdown extended cu tooltip cu formula -->
                                                <div class="grid grid-cols-2 gap-2 mb-3 text-xs sm:grid-cols-5">
                                                    <div class="p-2 rounded bg-surface">
                                                        <p class="flex items-center gap-1 text-sm text-muted">⛽ Combustibil
                                                            <span class="to-tip" :data-tip="fuelFormulaText(stop)">ⓘ</span>
                                                        </p>
                                                        <p class="font-semibold text-secondary"><span x-text="formatNumber(stop.fuel_cost ?? 0)"></span> RON</p>
                                                        <p class="hidden text-xs text-muted mt-0.5" x-show="(stop.fuel_arrival_cost ?? 0) > 0 && (stop.fuel_return_leg_cost ?? 0) > 0">
                                                            sosire <span x-text="formatNumber(stop.fuel_arrival_cost ?? 0)"></span> + retur <span x-text="formatNumber(stop.fuel_return_leg_cost ?? 0)"></span>
                                                        </p>
                                                        <p class="hidden text-xs text-muted mt-0.5" x-show="(stop.fuel_arrival_cost ?? 0) > 0 && (stop.fuel_return_leg_cost ?? 0) === 0">
                                                            <span x-text="formatNumber(stop.fuel_arrival_km ?? 0)"></span> km
                                                        </p>
                                                    </div>
                                                    <div class="p-2 rounded bg-surface">
                                                        <p class="flex items-center gap-1 text-sm text-muted">🛏️ Cazare (<span x-text="stop.nights ?? 1"></span>n)
                                                            <span class="to-tip" :data-tip="accommodationFormulaText(stop)">ⓘ</span>
                                                        </p>
                                                        <p class="font-semibold text-secondary"><span x-text="formatNumber(stop.accommodation_cost ?? 0)"></span> RON</p>
                                                    </div>
                                                    <div class="p-2 rounded bg-surface">
                                                        <p class="flex items-center gap-1 text-sm text-muted">🍽️ Diurnă
                                                            <span class="to-tip" :data-tip="mealFormulaText(stop)">ⓘ</span>
                                                        </p>
                                                        <p class="font-semibold text-secondary"><span x-text="formatNumber(stop.meal_cost ?? 0)"></span> RON</p>
                                                    </div>
                                                    <div class="p-2 rounded bg-surface" :class="(stop.extra_cost ?? 0) > 0 ? 'bg-warning/5 border border-warning/20' : ''">
                                                        <p class="flex items-center gap-1 text-sm text-muted">➕ Extra
                                                            <span class="to-tip" :data-tip="(stop.extra_cost_description || 'Costuri suplimentare introduse manual de tine pentru acest stop.')">ⓘ</span>
                                                        </p>
                                                        <p class="font-semibold text-secondary"><span x-text="formatNumber(stop.extra_cost ?? 0)"></span> RON</p>
                                                    </div>
                                                    <div class="p-2 border rounded bg-error/5 border-error/20">
                                                        <p class="text-sm font-bold text-error">💸 Cost total*</p>
                                                        <p class="font-bold text-error"><span x-text="formatNumber(stop.stop_total_cost ?? 0)"></span> RON</p>
                                                    </div>
                                                </div>

                                                <!-- Revenue + profit row -->
                                                <div class="grid grid-cols-3 gap-2 mb-3 text-xs">
                                                    <div class="p-2 border rounded bg-success/5 border-success/20">
                                                        <p class="text-xs font-bold text-success">💰 Venit estimat</p>
                                                        <p class="font-bold text-success"><span x-text="formatNumber(stop.revenue_estimate ?? 0)"></span> RON</p>
                                                    </div>
                                                    <div class="p-2 border rounded" :class="(stop.profit_estimate ?? 0) >= 0 ? 'bg-success/10 border-success/30' : 'bg-error/10 border-error/30'">
                                                        <p class="text-xs font-bold" :class="(stop.profit_estimate ?? 0) >= 0 ? 'text-success' : 'text-error'">📈 Profit</p>
                                                        <p class="font-bold" :class="(stop.profit_estimate ?? 0) >= 0 ? 'text-success' : 'text-error'"><span x-text="formatNumber(stop.profit_estimate ?? 0)"></span> RON</p>
                                                    </div>
                                                    <div class="p-2 border rounded" :class="(stop.margin_pct ?? 0) >= 0 ? 'bg-success/10 border-success/30' : 'bg-error/10 border-error/30'">
                                                        <p class="text-xs font-bold" :class="(stop.margin_pct ?? 0) >= 0 ? 'text-success' : 'text-error'">% Marjă profit</p>
                                                        <p class="font-bold" :class="(stop.margin_pct ?? 0) >= 0 ? 'text-success' : 'text-error'"><span x-text="stop.margin_pct ?? 0"></span>%</p>
                                                    </div>
                                                </div>

                                                <!-- Cazare embed -->
                                                <div class="pt-3 border-t border-border" x-data="{ stayOpen: false }">
                                                    <div class="flex items-center justify-between gap-x-4">
                                                        <button @click="stayOpen = !stayOpen; $nextTick(() => stayOpen && loadStay22(idx))" class="flex items-center gap-1 px-6 py-2 text-sm text-white rounded-md bg-primary hover:underline">
                                                            <span x-text="stayOpen ? 'Ascunde cazări' : 'Vezi cazări în zonă'"></span>
                                                        </button>
                                                        <p class="mt-1 text-xs font-semibold text-muted" x-show="!stayOpen">Recomandări lângă <span x-text="stop.venue_name || stop.city"></span> filtrate cu setările tale (<span x-text="planner.config.people_count"></span> persoane · <span x-text="totalRoomsCount()"></span> camere · max <span x-text="formatNumber(maxRoomPrice())"></span> RON/noapte).</p>
                                                    </div>
                                                    <div x-show="stayOpen" x-transition class="mt-3">
                                                        <iframe :id="'stay22-frame-' + idx" :src="stay22Url(stop)" class="to-stay22-iframe" loading="lazy" referrerpolicy="origin"></iframe>
                                                        <p class="mt-2 text-xs text-muted">
                                                            🛏️ <strong x-text="totalRoomsCount()"></strong> camere &middot;
                                                            👥 <strong x-text="planner.config.people_count"></strong> persoane &middot;
                                                            💰 max <strong><span x-text="formatNumber(maxRoomPrice())"></span> RON</strong>/cameră &middot;
                                                            📅 <span x-text="stop.nights ?? 1"></span> noapte/nopți (<span x-text="stop.date_iso"></span>)
                                                        </p>
                                                    </div>
                                                </div>

                                                <p x-show="stop.distance_to_next_km" class="text-[11px] text-muted mt-3 pt-2 border-t border-border/50">
                                                    → Distanța până la <span x-text="(planner.route[idx+1]?.from_start ? planner.config.start_location : (planner.route[idx+1]?.city ?? '—'))"></span>: <strong><span x-text="formatNumber(stop.distance_to_next_km)"></span> km</strong>
                                                </p>
                                                <p x-show="!stop.distance_to_next_km && stop.return_distance_km" class="text-[11px] text-muted mt-3 pt-2 border-t border-border/50">
                                                    → Întoarcere la <strong x-text="planner.config.start_location"></strong>: <strong><span x-text="formatNumber(stop.return_distance_km)"></span> km</strong> · ⛽ <span x-text="formatNumber(stop.return_fuel_cost ?? 0)"></span> RON
                                                </p>
                                                </div><!-- end x-show=expanded -->
                                            </div>
                                        </template>
                                    </div>

                                    </div><!-- end p-5 -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============ TAB: PREDICTIONS ============ -->
                <div x-show="tab === 'predictions'" class="p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-bold text-secondary">Predicții bilete</h2>
                        <p class="text-sm text-muted">Estimat per oraș în 3 scenarii de venue, pe baza fanilor locali, retenției istorice și sezonalității.</p>
                    </div>

                    <div class="p-4 mb-6 bg-surface rounded-xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-bold tracking-wider uppercase text-muted">Filtrează:</span>
                            <template x-for="f in [['all','Toate'],['warm','Recomandare'],['sleeping','Dormit'],['new','Neexplorat']]" :key="f[0]">
                                <button @click="predictionFilter = f[0]" :class="predictionFilter === f[0] ? 'bg-primary text-white' : 'bg-white text-muted'" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors" x-text="f[1]"></button>
                            </template>
                        </div>
                    </div>

                    <div class="px-6 -mx-6 overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-border">
                                    <th class="px-2 py-3 text-xs font-bold tracking-wider text-left uppercase text-muted">Oraș</th>
                                    <th class="px-2 py-3 text-xs font-bold tracking-wider text-right uppercase text-muted">Fani</th>
                                    <th class="px-2 py-3 text-xs font-bold tracking-wider text-center uppercase text-muted">Mic<br><span class="text-xs font-normal lowercase">300-500</span></th>
                                    <th class="px-2 py-3 text-xs font-bold tracking-wider text-center uppercase text-muted">Mediu<br><span class="text-xs font-normal lowercase">800-1500</span></th>
                                    <th class="px-2 py-3 text-xs font-bold tracking-wider text-center uppercase text-muted">Mare<br><span class="text-xs font-normal lowercase">2500+</span></th>
                                    <th class="px-2 py-3 text-xs font-bold tracking-wider text-left uppercase text-muted">Status</th>
                                    <th class="px-2 py-3 text-xs font-bold tracking-wider text-right uppercase text-muted"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="p in filteredPredictions" :key="p.city">
                                    <tr class="transition-colors border-b border-border hover:bg-surface">
                                        <td class="px-2 py-3">
                                            <p class="font-medium text-secondary" x-text="p.city"></p>
                                            <p class="text-xs text-muted" x-text="p.note"></p>
                                        </td>
                                        <td class="px-2 py-3 font-bold text-right text-secondary" x-text="formatNumber(p.fans)"></td>
                                        <td class="px-2 py-3 text-center">
                                            <p class="text-sm font-bold text-secondary" x-text="p.small.estimate"></p>
                                            <p class="text-xs text-muted" x-text="p.small.confidence + '% încredere'"></p>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <p class="text-sm font-bold text-secondary" x-text="p.medium.estimate"></p>
                                            <p class="text-xs text-muted" x-text="p.medium.confidence + '% încredere'"></p>
                                        </td>
                                        <td class="px-2 py-3 text-center">
                                            <p class="text-sm font-bold text-secondary" x-text="p.large.estimate"></p>
                                            <p class="text-xs text-muted" x-text="p.large.confidence + '% încredere'"></p>
                                        </td>
                                        <td class="px-2 py-3"><span class="text-xs to-badge" :class="statusBadgeClass(p.status)" x-text="p.status_label"></span></td>
                                        <td class="px-2 py-3 text-right">
                                            <button @click="addCityToPlanner(p.city)" class="text-sm font-medium text-primary hover:underline whitespace-nowrap">+ Planner</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                        <p x-show="!filteredPredictions.length" class="py-8 text-sm text-center text-muted">Date insuficiente.</p>
                    </div>

                    <div class="grid gap-6 mt-8 lg:grid-cols-2">
                        <div class="p-5 bg-white border border-border rounded-2xl">
                            <h3 class="mb-1 font-bold text-secondary">Performanță pe ziua săptămânii</h3>
                            <p class="mb-4 text-sm text-muted">Vânzări medii pe eveniment, agregat din evenimentele tale</p>
                            <div class="relative h-[260px]"><canvas id="weekdayChart"></canvas></div>
                        </div>
                        <div class="p-5 bg-white border border-border rounded-2xl">
                            <h3 class="mb-1 font-bold text-secondary">Sezonalitate</h3>
                            <p class="mb-4 text-sm text-muted">Vânzări lunare medii pe evenimentele tale</p>
                            <div class="relative h-[260px]"><canvas id="seasonChart"></canvas></div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 p-4 mt-6 border border-blue-200 bg-blue-50 rounded-xl">
                        <span class="flex-shrink-0">ℹ️</span>
                        <p class="text-sm text-blue-900">Predicțiile combină fani locali, retenție istorică, ziua săptămânii și sezonalitatea. Confidence-ul reflectă cât de bine sunt calibrate de date reale (vs inferențe).</p>
                    </div>
                </div>

                <!-- ============ TAB: SCENARIOS ============ -->
                <div x-show="tab === 'scenarios'" class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div>
                            <h2 class="text-lg font-bold text-secondary">Scenarii salvate</h2>
                            <p class="text-sm text-muted">Compară opțiuni de turneu side-by-side. Limita: <span x-text="scenariosData.limit"></span> scenarii.</p>
                        </div>
                        <button @click="setTab('planner')" class="to-btn to-btn-primary to-btn-sm">+ Scenariu nou</button>
                    </div>

                    <div class="mb-8 space-y-3">
                        <template x-for="s in scenariosData.scenarios || []" :key="s.id">
                            <div class="flex flex-col gap-3 p-4 transition-all border-2 sm:flex-row sm:items-center rounded-xl hover:shadow-sm" :class="s.status === 'active' ? 'border-primary bg-primary/5' : 'border-border bg-white'">
                                <!-- Status indicator + name + meta -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span x-show="s.status === 'active'" class="text-white to-badge bg-primary">Activ</span>
                                        <span x-show="s.status === 'draft'" class="text-white to-badge bg-muted/50">Draft</span>
                                        <p class="text-base font-bold truncate text-secondary" x-text="s.name"></p>
                                    </div>
                                    <p class="text-xs text-muted"><span x-text="s.cities_count"></span> orașe · <span x-text="s.date_range"></span></p>
                                </div>
                                <!-- Stats inline -->
                                <div class="flex flex-wrap items-center gap-4 px-2 sm:gap-6">
                                    <div class="text-center">
                                        <p class="text-[10px] text-muted uppercase">Distanță</p>
                                        <p class="text-sm font-bold text-secondary"><span x-text="formatNumber(s.summary?.total_distance_km ?? 0)"></span> km</p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[10px] text-muted uppercase">Bilete</p>
                                        <p class="text-sm font-bold text-secondary" x-text="'~' + formatNumber(s.summary?.predicted_tickets ?? 0)"></p>
                                    </div>
                                    <div class="text-center">
                                        <p class="text-[10px] text-muted uppercase">Cost</p>
                                        <p class="text-sm font-bold text-secondary" x-text="'~' + formatNumber(s.summary?.total_cost_ron ?? 0) + ' RON'"></p>
                                    </div>
                                    <div class="text-center" x-show="s.summary?.profit_ron !== undefined">
                                        <p class="text-[10px] text-muted uppercase">Profit</p>
                                        <p class="text-sm font-bold" :class="(s.summary?.profit_ron ?? 0) >= 0 ? 'text-success' : 'text-error'" x-text="'~' + formatNumber(s.summary?.profit_ron ?? 0) + ' RON'"></p>
                                    </div>
                                </div>
                                <!-- Actions -->
                                <div class="flex flex-shrink-0 gap-1">
                                    <button @click="loadScenarioToPlanner(s)" class="to-btn to-btn-primary to-btn-sm">Deschide</button>
                                    <button @click="toggleActive(s)" class="to-btn to-btn-secondary to-btn-sm" :title="s.status === 'active' ? 'Marchează draft' : 'Marchează activ'">
                                        <span x-text="s.status === 'active' ? '⭐' : '☆'"></span>
                                    </button>
                                    <button @click="deleteScenario(s)" class="to-btn to-btn-secondary to-btn-sm" title="Șterge">🗑️</button>
                                </div>
                            </div>
                        </template>
                        <p x-show="!(scenariosData.scenarios || []).length" class="py-8 text-sm text-center text-muted">Niciun scenariu salvat încă.</p>
                    </div>

                    <div x-show="(scenariosData.scenarios || []).length >= 2" class="p-5 bg-white border border-border rounded-2xl">
                        <h3 class="mb-4 font-bold text-secondary">Comparație side-by-side</h3>
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <select x-model.number="compare.aId" class="to-input" style="width:auto">
                                <option value="0">— alege A —</option>
                                <template x-for="s in scenariosData.scenarios || []" :key="'a-'+s.id"><option :value="s.id" x-text="s.name"></option></template>
                            </select>
                            <span class="text-muted">vs</span>
                            <select x-model.number="compare.bId" class="to-input" style="width:auto">
                                <option value="0">— alege B —</option>
                                <template x-for="s in scenariosData.scenarios || []" :key="'b-'+s.id"><option :value="s.id" x-text="s.name"></option></template>
                            </select>
                            <button @click="loadCompare()" :disabled="!compare.aId || !compare.bId || compare.aId === compare.bId" class="to-btn to-btn-primary to-btn-sm">Compară</button>
                        </div>

                        <div x-show="compare.data?.a" class="grid gap-4 lg:grid-cols-2">
                            <div class="p-5 border-2 border-primary rounded-xl">
                                <h4 class="mb-4 font-bold text-secondary" x-text="'A · ' + (compare.data?.a?.name ?? '—')"></h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between"><span class="text-muted">Orașe</span><span class="font-bold" x-text="compare.data?.a?.cities_count"></span></div>
                                    <div class="flex justify-between"><span class="text-muted">Durată</span><span class="font-bold" x-text="(compare.data?.a?.summary?.duration_days ?? 0) + ' zile'"></span></div>
                                    <div class="flex justify-between"><span class="text-muted">Distanță</span><span class="font-bold" x-text="formatNumber(compare.data?.a?.summary?.total_distance_km ?? 0) + ' km'"></span></div>
                                    <div class="flex justify-between"><span class="text-muted">Cost</span><span class="font-bold" x-text="formatNumber(compare.data?.a?.summary?.total_cost_ron ?? 0) + ' RON'"></span></div>
                                    <div class="flex justify-between"><span class="text-muted">Bilete prog.</span><span class="font-bold text-success" x-text="formatNumber(compare.data?.a?.summary?.predicted_tickets ?? 0)"></span></div>
                                    <div class="flex justify-between"><span class="text-muted">Venit estimat</span><span class="font-bold text-success" x-text="'~' + formatNumber(compare.data?.a?.summary?.predicted_revenue_ron ?? 0) + ' RON'"></span></div>
                                </div>
                            </div>
                            <div class="p-5 border-2 border-border rounded-xl">
                                <h4 class="mb-4 font-bold text-secondary" x-text="'B · ' + (compare.data?.b?.name ?? '—')"></h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex items-center justify-between"><span class="text-muted">Orașe</span><span><span class="font-bold" x-text="compare.data?.b?.cities_count"></span> <span class="ml-2 text-xs to-badge" :class="(compare.data?.delta?.cities ?? 0) >= 0 ? 'bg-success/10 text-success' : 'bg-error/10 text-error'" x-text="signed(compare.data?.delta?.cities ?? 0)"></span></span></div>
                                    <div class="flex items-center justify-between"><span class="text-muted">Durată</span><span><span class="font-bold" x-text="(compare.data?.b?.summary?.duration_days ?? 0) + ' zile'"></span> <span class="ml-2 text-xs to-badge" :class="(compare.data?.delta?.duration_days ?? 0) <= 0 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'" x-text="signed(compare.data?.delta?.duration_days ?? 0) + 'z'"></span></span></div>
                                    <div class="flex items-center justify-between"><span class="text-muted">Distanță</span><span><span class="font-bold" x-text="formatNumber(compare.data?.b?.summary?.total_distance_km ?? 0) + ' km'"></span> <span class="ml-2 text-xs to-badge" :class="(compare.data?.delta?.total_distance_km ?? 0) <= 0 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'" x-text="signed(compare.data?.delta?.total_distance_km ?? 0) + 'km'"></span></span></div>
                                    <div class="flex items-center justify-between"><span class="text-muted">Cost</span><span><span class="font-bold" x-text="formatNumber(compare.data?.b?.summary?.total_cost_ron ?? 0) + ' RON'"></span> <span class="ml-2 text-xs to-badge" :class="(compare.data?.delta?.total_cost_ron ?? 0) <= 0 ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'" x-text="signed(compare.data?.delta?.cost_pct ?? 0) + '%'"></span></span></div>
                                    <div class="flex items-center justify-between"><span class="text-muted">Bilete</span><span><span class="font-bold" x-text="formatNumber(compare.data?.b?.summary?.predicted_tickets ?? 0)"></span> <span class="ml-2 text-xs to-badge" :class="(compare.data?.delta?.predicted_tickets ?? 0) >= 0 ? 'bg-success/10 text-success' : 'bg-error/10 text-error'" x-text="signed(compare.data?.delta?.tickets_pct ?? 0) + '%'"></span></span></div>
                                    <div class="flex items-center justify-between"><span class="text-muted">Venit estimat</span><span><span class="font-bold" x-text="'~' + formatNumber(compare.data?.b?.summary?.predicted_revenue_ron ?? 0) + ' RON'"></span> <span class="ml-2 text-xs to-badge" :class="(compare.data?.delta?.predicted_revenue_ron ?? 0) >= 0 ? 'bg-success/10 text-success' : 'bg-error/10 text-error'" x-text="signed(compare.data?.delta?.revenue_pct ?? 0) + '%'"></span></span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>[x-cloak]{display:none!important}</style>

<script>
function tourOptimizer() {
    return {
        loading: true,
        tabLoading: false,
        tab: 'opportunities',
        tabs: [
            { id: 'opportunities', label: 'Hartă oportunități' },
            { id: 'planner',       label: 'Tour Planner' },
            { id: 'predictions',   label: 'Predicții' },
            { id: 'scenarios',     label: 'Scenarii salvate' },
        ],

        // Data
        opportunities: { cities: [], recommendations: [], dormant_alerts: [], kpis: {} },
        predictionsData: { cities: [], weekday: { labels: [], values: [] }, seasonality: { labels: [], values: [] } },
        scenariosData: { scenarios: [], total: 0, limit: 20 },

        // UI state
        cityInput: '',
        predictionFilter: 'all',
        quickCities: ['București', 'Cluj-Napoca', 'Iași', 'Brașov', 'Timișoara', 'Constanța', 'Sibiu'],
        // Home base options — populat la init() din API. Format: [{name, state, country}, ...]
        homeBaseOptions: [
            { name: 'București', state: 'București', country: 'RO' },
            { name: 'Cluj-Napoca', state: 'Cluj', country: 'RO' },
            { name: 'Timișoara', state: 'Timiș', country: 'RO' },
            { name: 'Iași', state: 'Iași', country: 'RO' },
            { name: 'Brașov', state: 'Brașov', country: 'RO' },
            { name: 'Constanța', state: 'Constanța', country: 'RO' },
            { name: 'Sibiu', state: 'Sibiu', country: 'RO' },
            { name: 'Oradea', state: 'Bihor', country: 'RO' },
            { name: 'Craiova', state: 'Dolj', country: 'RO' },
            { name: 'Galați', state: 'Galați', country: 'RO' },
        ],
        opportunityMap: null,
        plannerMap: null,
        plannerLayer: null,
        charts: {},

        planner: {
            name: 'Tour Vară 2026',
            startDate: '',
            endDate: '',
            cities: [],
            minDaysBetween: 2,
            includeBorder: false,
            optimized: false,
            saving: false,
            dirty: false,
            route: [],
            summary: null,
            currentScenarioId: null,
            configOpen: false,
            config: {
                vehicles: [{ type: 'van', count: 1, capacity_seats: 8, consumption_l_100km: 9.5 }],
                fuel_type: 'diesel',
                fuel_price_ron_l: 7.5,
                people_count: 6,
                rooms: { single: 0, double: 3, apartment: 0 },
                room_prices: { single: 250, double: 380, apartment: 600 },
                meal_price_per_day: 120,
            },
        },

        // venuesByCity[cityName] = [{id, name, capacity_total, ...}] | undefined while loading | [] if none
        venuesByCity: {},

        cityUidCounter: 1,

        compare: {
            aId: 0,
            bId: 0,
            data: null,
        },

        venueSearchActive: { stopIdx: -1, query: '' },
        sidebarCollapsed: false,

        get totalRoomCapacity() {
            const r = this.planner.config.rooms;
            return (r.single | 0) + (r.double | 0) * 2 + (r.apartment | 0) * 4;
        },

        get roomCapacityValid() {
            return this.totalRoomCapacity >= this.planner.config.people_count;
        },

        get venueSearchSuggestions() {
            const idx = this.venueSearchActive.stopIdx;
            if (idx < 0 || !this.planner.route?.[idx]) return [];
            const city = this.planner.route[idx].city;
            const all = this.venuesByCity[city] || [];
            const q = this.normalizeKey(this.venueSearchActive.query || '');
            if (!q) return all.slice(0, 30);
            return all.filter(v => {
                const hay = this.normalizeKey((v.name || '') + ' ' + (v.address || ''));
                return hay.indexOf(q) !== -1;
            }).slice(0, 30);
        },

        normalizeKey(s) {
            return (s || '').toString()
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .toLowerCase()
                .trim();
        },

        filterVenues(venues, query) {
            const q = this.normalizeKey(query);
            if (!q) return venues.slice(0, 50);
            return venues.filter(v => {
                const hay = this.normalizeKey((v.name || '') + ' ' + (v.address || ''));
                return hay.indexOf(q) !== -1;
            }).slice(0, 50);
        },

        // Returnează true dacă stop[idx].date_iso < stop[idx-1].date_iso → în afara ordinii cronologice
        isStopOutOfOrder(idx) {
            if (idx <= 0) return false;
            const route = this.planner.route || [];
            const cur = route[idx]?.date_iso;
            const prev = route[idx - 1]?.date_iso;
            if (!cur || !prev) return false;
            return cur < prev; // string comparison pe Y-m-d ISO funcționează cronologic
        },

        // Lista de indici cu probleme cronologice (pentru afișaj count în toolbar)
        get outOfOrderStops() {
            const out = [];
            const route = this.planner.route || [];
            for (let i = 0; i < route.length; i++) {
                if (this.isStopOutOfOrder(i)) out.push(i);
            }
            return out;
        },

        // Sortează planner.route după date_iso ascendent. Stop-urile fără dată merg la final.
        // După sortare: _syncCitiesFromRoute + auto-recalc.
        sortChronologically() {
            const route = (this.planner.route || []).slice();
            route.sort((a, b) => {
                const ad = a.date_iso || '9999-12-31';
                const bd = b.date_iso || '9999-12-31';
                if (ad < bd) return -1;
                if (ad > bd) return 1;
                return 0;
            });
            this.planner.route = route;
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        // Returnează prețul maxim per cameră bazat pe camerele REAL configurate (cu count > 0).
        // Ex: 0 single, 4 double, 0 apartment + price double=380 → returnează 380.
        maxRoomPrice() {
            const r = this.planner.config.rooms;
            const p = this.planner.config.room_prices;
            const candidates = [];
            if ((r.single | 0) > 0) candidates.push(p.single | 0);
            if ((r.double | 0) > 0) candidates.push(p.double | 0);
            if ((r.apartment | 0) > 0) candidates.push(p.apartment | 0);
            if (candidates.length === 0) {
                // Fallback: ia max din toate prețurile configurate, indiferent de count
                return Math.max(p.single | 0, p.double | 0, p.apartment | 0) || 400;
            }
            return Math.max(...candidates);
        },

        // Total camere configurate (suma single + double + apartment)
        totalRoomsCount() {
            const r = this.planner.config.rooms;
            return (r.single | 0) + (r.double | 0) + (r.apartment | 0);
        },

        stay22Url(stop) {
            const aff = '68f75671f26bfb6f2a73d0b9';
            const lat = stop.lat || 0;
            const lng = stop.lng || 0;
            const checkin = stop.date_iso || this.planner.startDate || '';
            const ci = checkin ? new Date(checkin) : new Date();
            const co = new Date(ci);
            co.setDate(co.getDate() + (stop.nights || 1));
            const checkout = co.toISOString().slice(0, 10);
            const address = encodeURIComponent((stop.venue_name || stop.city) + ', ' + stop.city);
            const maxprice = this.maxRoomPrice();
            const adults = Math.max(1, this.planner.config.people_count | 0);
            const rooms = Math.max(1, this.totalRoomsCount());
            const params = [
                'aid=' + aff,
                'lat=' + lat,
                'lng=' + lng,
                'address=' + address,
                'checkin=' + checkin,
                'checkout=' + checkout,
                'adults=' + adults,
                'rooms=' + rooms,
                'maxprice=' + maxprice,
                'currency=RON',
                'maincolor=A51C30',
                'markertype=circle',
                'zoom=14',
            ];
            return 'https://www.stay22.com/embed/gm?' + params.join('&');
        },

        loadStay22(idx) {
            const frame = document.getElementById('stay22-frame-' + idx);
            const stop = this.planner.route[idx];
            if (frame && stop) frame.src = this.stay22Url(stop);
        },

        get filteredPredictions() {
            if (this.predictionFilter === 'all') return this.predictionsData.cities || [];
            return (this.predictionsData.cities || []).filter(p => p.status === this.predictionFilter);
        },

        token() { return localStorage.getItem('ambilet_artist_token'); },

        defaultStartDate() {
            const d = new Date();
            d.setDate(d.getDate() + 30);
            return d.toISOString().slice(0, 10);
        },

        async init() {
            // Default planner dates: start = today + 30 days, end = start + 30 days
            if (!this.planner.startDate) {
                this.planner.startDate = this.defaultStartDate();
            }
            if (!this.planner.endDate) {
                const d = new Date(this.planner.startDate);
                d.setDate(d.getDate() + 30);
                this.planner.endDate = d.toISOString().slice(0, 10);
            }

            const validTabs = this.tabs.map(t => t.id);
            const urlTab = (new URL(window.location.href)).searchParams.get('tab');
            const initialTab = (urlTab && validTabs.includes(urlTab)) ? urlTab : 'opportunities';

            await Promise.all([
                this.loadOpportunities(),
                this.loadCitiesList(),
            ]);
            this.loading = false;

            if (initialTab !== 'opportunities') {
                await this.setTab(initialTab);
            } else {
                this.$nextTick(() => this.renderTab('opportunities'));
            }
            // Init flatpickr pe toate input[type=date] (sidebar planner + alt locuri)
            this.$nextTick(() => this.setupDatepickers());
        },

        async setTab(t) {
            this.tab = t;
            this.syncUrlTab(t);
            const needsLoad = (
                (t === 'predictions' && !this.predictionsData.cities.length) ||
                (t === 'scenarios' && !this.scenariosData.scenarios.length && !this.scenariosData.total)
            );
            if (needsLoad) this.tabLoading = true;
            try {
                if (t === 'predictions' && !this.predictionsData.cities.length) await this.loadPredictions();
                if (t === 'scenarios') await this.loadScenarios();
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
            } catch (e) {}
        },

        async fetchAction(action, params = {}, options = {}) {
            const qs = new URLSearchParams(params).toString();
            const opts = {
                method: options.method || 'GET',
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + this.token() },
            };
            if (options.body) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(options.body);
            }
            const res = await fetch(`/api/proxy.php?action=${action}` + (qs ? '&' + qs : ''), opts);
            if (res.status === 429) {
                const retryAfter = res.headers.get('Retry-After') || '60';
                alert('Ai făcut prea multe recalculări consecutiv. Mai așteaptă ~' + retryAfter + ' secunde și încearcă iar.');
                return { error: 'rate_limited' };
            }
            try { return await res.json(); } catch (e) { return null; }
        },

        async loadOpportunities() {
            const r = await this.fetchAction('artist.tour.opportunities');
            this.opportunities = r?.data || this.opportunities;
        },

        async loadCitiesList() {
            try {
                const r = await this.fetchAction('artist.tour.cities-list');
                const cities = r?.data?.cities;
                if (Array.isArray(cities) && cities.length > 0) {
                    // Backend now returns objects {name, state, country}; tolerate both formats
                    if (typeof cities[0] === 'string') {
                        this.homeBaseOptions = cities.map(name => ({ name, state: null, country: null }));
                    } else {
                        this.homeBaseOptions = cities;
                    }
                }
            } catch (e) { /* fallback la hardcoded */ }
        },

        async loadPredictions() {
            const r = await this.fetchAction('artist.tour.predictions');
            this.predictionsData = r?.data || this.predictionsData;
        },

        async loadScenarios() {
            const r = await this.fetchAction('artist.tour.scenarios');
            this.scenariosData = r?.data || this.scenariosData;
        },

        buildConstraintsPayload(preserveOrder = false) {
            return {
                min_days_between: this.planner.minDaysBetween,
                include_border: this.planner.includeBorder,
                preserve_order: !!preserveOrder,
                tour_config: this.planner.config,
            };
        },

        citiesPayload() {
            return this.planner.cities.map(c => ({
                name: c.name,
                fixed: !!c.fixed,
                date: c.date || null,
                venue_id: c.venue_id ? Number(c.venue_id) : null,
                from_start: !!c.from_start,
                manual_capacity: c.manual_capacity != null ? Number(c.manual_capacity) : null,
                manual_prediction: c.manual_prediction != null ? Number(c.manual_prediction) : null,
                extra_cost: c.extra_cost != null ? Number(c.extra_cost) : null,
                extra_cost_description: c.extra_cost_description ?? null,
            }));
        },

        async optimizeRoute(preserveOrder = false) {
            if (this.planner.cities.length < 2) return;
            if (!this.roomCapacityValid) {
                if (!confirm('Capacitatea camerelor (' + this.totalRoomCapacity + ') e mai mică decât persoanele în turneu (' + this.planner.config.people_count + '). Continui oricum?')) return;
            }
            this.tabLoading = true;
            try {
                const body = {
                    cities: this.citiesPayload(),
                    constraints: this.buildConstraintsPayload(preserveOrder),
                    start_date: this.planner.startDate,
                };
                const r = await this.fetchAction('artist.tour.optimize', {}, { method: 'POST', body });
                if (r?.data) {
                    this.planner.route = r.data.route || [];
                    this.planner.summary = r.data.summary || {};
                    this.planner.optimized = true;
                    this.planner.dirty = false;
                    // Pre-load venues for every city in route (for search suggestions on inline edit)
                    (this.planner.route || []).forEach(s => this.loadVenuesForCity(s.city));
                    this.$nextTick(() => {
                        this.renderPlannerMap();
                        this.setupSortable();
                    });
                } else {
                    alert(r?.message || 'Nu am putut optimiza ruta. Verifică orașele introduse.');
                }
            } finally {
                this.tabLoading = false;
            }
        },

        async saveScenario({ silent = false } = {}) {
            if (!this.planner.optimized) return;
            this.planner.saving = true;
            try {
                const body = {
                    name: this.planner.name,
                    start_date: this.planner.startDate,
                    end_date: this.planner.endDate,
                    cities: this.citiesPayload(),
                    constraints: this.buildConstraintsPayload(),
                    optimized_route: this.planner.route,
                    summary: this.planner.summary,
                };
                let r;
                // Dacă scenariul a fost deja salvat o dată (currentScenarioId există), PATCH în loc de POST.
                // Altfel s-ar crea duplicate la fiecare Save.
                if (this.planner.currentScenarioId) {
                    r = await this.fetchAction('artist.tour.scenario.update&id=' + this.planner.currentScenarioId, {}, { method: 'PATCH', body });
                } else {
                    body.status = 'draft';
                    r = await this.fetchAction('artist.tour.scenario.save', {}, { method: 'POST', body });
                }

                if (r?.data?.id || r?.data?.success) {
                    if (r?.data?.id && !this.planner.currentScenarioId) {
                        this.planner.currentScenarioId = r.data.id;
                    }
                    this.scenariosData.scenarios = []; // force reload on next visit
                    if (!silent) alert('Scenariul a fost salvat.');
                } else {
                    if (!silent) alert(r?.message || 'Eroare la salvare.');
                }
            } finally {
                this.planner.saving = false;
            }
        },

        async deleteScenario(s) {
            if (!confirm('Sigur ștergi scenariul „' + s.name + '"?')) return;
            await this.fetchAction('artist.tour.scenario.delete&id=' + s.id, {}, { method: 'DELETE' });
            this.scenariosData.scenarios = this.scenariosData.scenarios.filter(x => x.id !== s.id);
        },

        async toggleActive(s) {
            const newStatus = s.status === 'active' ? 'draft' : 'active';
            await this.fetchAction('artist.tour.scenario.update&id=' + s.id, {}, { method: 'PATCH', body: { status: newStatus } });
            s.status = newStatus;
        },

        async loadCompare() {
            if (!this.compare.aId || !this.compare.bId) return;
            this.tabLoading = true;
            try {
                const r = await this.fetchAction('artist.tour.scenarios.compare', { a: this.compare.aId, b: this.compare.bId });
                this.compare.data = r?.data || null;
            } finally {
                this.tabLoading = false;
            }
        },

        async loadScenarioToPlanner(s) {
            this.planner.name = s.name;
            this.planner.startDate = s.start_date;
            this.planner.endDate = s.end_date;
            this.planner.currentScenarioId = s.id;

            // Restore cities (fiecare cu uid nou ca să nu se ciocnească chei Alpine)
            const restoredCities = (s.cities || []).map(c => ({
                uid: 'c' + (this.cityUidCounter++),
                name: c.name,
                fixed: !!c.fixed,
                date: c.date || '',
                venue_id: c.venue_id || null,
                from_start: !!c.from_start,
                manual_capacity: c.manual_capacity ?? null,
                manual_prediction: c.manual_prediction ?? null,
                extra_cost: c.extra_cost ?? null,
                extra_cost_description: c.extra_cost_description ?? null,
            }));
            this.planner.cities = restoredCities;
            restoredCities.forEach(c => {
                delete this.venuesByCity[c.name];
                this.loadVenuesForCity(c.name);
            });

            // Restore constraints + tour_config
            const cs = s.constraints || {};
            this.planner.minDaysBetween = cs.min_days_between ?? 2;
            this.planner.includeBorder = !!cs.include_border;
            if (cs.tour_config) {
                this.planner.config = Object.assign({}, this.planner.config, cs.tour_config);
            }

            // Restore optimized route + summary (din JSON-ul salvat — folosește logica veche)
            this.planner.route = s.optimized_route || [];
            this.planner.summary = s.summary || null;
            this.planner.optimized = (this.planner.route && this.planner.route.length > 0);

            this.setTab('planner');

            // Re-rulăm calculul DOAR dacă scenariul folosește format vechi (lipsesc câmpurile noi).
            // Detectăm prin absența confidence_factors / arrival_road_km — acelea apar doar de la backend nou.
            const firstStop = this.planner.route?.[0];
            const usesOldFormat = firstStop && (
                firstStop.confidence_factors === undefined ||
                firstStop.arrival_road_km === undefined
            );
            if (usesOldFormat && this.planner.cities.length >= 2) {
                await this.optimizeRoute(true);
                // După recalc cu logica nouă, persistăm summary actualizat în DB (silent),
                // ca lista de Scenarii Salvate să afișeze cifrele corecte la următoarea vizualizare.
                if (this.planner.optimized) {
                    await this.saveScenario({ silent: true });
                }
            }
        },

        addCity(name) {
            if (!this.planner.cities.find(c => c.name === name)) {
                const uid = 'c' + (this.cityUidCounter++);
                // Primul oraș adăugat are by default from_start=true (pleacă din home base)
                const isFirst = this.planner.cities.length === 0;
                this.planner.cities.push({ uid, name, fixed: false, date: '', venue_id: null, from_start: isFirst });
                this.loadVenuesForCity(name);
                this.markDirty();
            }
        },

        addCityFromInput() {
            if (this.cityInput.trim()) {
                this.addCity(this.cityInput.trim());
                this.cityInput = '';
            }
        },

        addCityToPlanner(name) {
            this.addCity(name);
            this.setTab('planner');
        },

        removeCity(idx) {
            this.planner.cities.splice(idx, 1);
        },

        async loadVenuesForCity(cityName) {
            if (this.venuesByCity[cityName] && this.venuesByCity[cityName].length) return;
            this.venuesByCity[cityName] = [];
            try {
                const r = await this.fetchAction('artist.tour.venues', { city: cityName });
                this.venuesByCity[cityName] = (r?.data?.venues) || [];
            } catch (e) {
                this.venuesByCity[cityName] = [];
            }
        },

        markDirty() {
            // Marks state as needing recalc; only relevant if route was already optimized.
            // Auto-recalc: declanșăm un debounce → backend re-rulează automat după 1s de liniște.
            if (this.planner.optimized) {
                this.planner.dirty = true;
                this.scheduleAutoRecalc();
            }
        },

        // Inline edit handlers for itinerary
        updateStopDate(idx, value) {
            if (!this.planner.route?.[idx]) return;
            this.planner.route[idx].date_iso = value;
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        updateStopFromStart(idx, value) {
            if (!this.planner.route?.[idx]) return;
            this.planner.route[idx].from_start = value;
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        selectVenueForStop(idx, venue) {
            const stop = this.planner.route?.[idx];
            if (!stop) return;
            if (!venue) {
                stop.venue_id = null;
                stop.venue_name = null;
                stop.venue_capacity = null;
            } else {
                stop.venue_id = venue.id;
                stop.venue_name = venue.name;
                stop.venue_capacity = venue.capacity_total;
            }
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        toggleStopFixed(idx) {
            const stop = this.planner.route?.[idx];
            if (!stop) return;
            stop.fixed = !stop.fixed;
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        updateStopManualCapacity(idx, value) {
            const stop = this.planner.route?.[idx];
            if (!stop) return;
            stop.manual_capacity = value === '' ? null : Number(value);
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        updateStopManualPrediction(idx, value) {
            const stop = this.planner.route?.[idx];
            if (!stop) return;
            stop.manual_prediction = value === '' ? null : Number(value);
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        updateStopExtraCost(idx, value) {
            const stop = this.planner.route?.[idx];
            if (!stop) return;
            stop.extra_cost = value === '' ? null : Number(value);
            this._syncCitiesFromRoute();
            this.markDirty();
        },

        updateStopExtraCostDescription(idx, value) {
            const stop = this.planner.route?.[idx];
            if (!stop) return;
            stop.extra_cost_description = value || null;
            this._syncCitiesFromRoute();
            // NU markDirty pe descriere — text inutil să recalculeze
        },

        _syncCitiesFromRoute() {
            this.planner.cities = (this.planner.route || []).map((s, i) => ({
                uid: this.planner.cities[i]?.uid || ('c' + (this.cityUidCounter++)),
                name: s.city,
                fixed: !!s.fixed,
                date: s.date_iso || '',
                venue_id: s.venue_id || null,
                from_start: !!s.from_start,
                manual_capacity: s.manual_capacity ?? null,
                manual_prediction: s.manual_prediction ?? null,
                extra_cost: s.extra_cost ?? null,
                extra_cost_description: s.extra_cost_description ?? null,
            }));
        },

        async recalcRoute() {
            this._syncCitiesFromRoute();
            // Recalcul după drag/edit — păstrăm ordinea curentă, recomputăm doar costurile/predicțiile
            await this.optimizeRoute(true);
        },

        // Auto-recalc cu debounce: orice modificare relevantă invocă asta. Backend recompute după 1s
        // de inactivitate. Pe rapid-typing, doar ultima cerere pleacă.
        _autoRecalcTimer: null,
        scheduleAutoRecalc() {
            if (!this.planner.optimized) return;
            if (this._autoRecalcTimer) clearTimeout(this._autoRecalcTimer);
            this._autoRecalcTimer = setTimeout(() => {
                this._autoRecalcTimer = null;
                this.recalcRoute();
            }, 1000);
        },

        setupSortable() {
            if (this._sortable) {
                try { this._sortable.destroy(); } catch (e) {}
                this._sortable = null;
            }
            const list = this.$refs.routeList;
            if (!list || typeof Sortable === 'undefined') return;
            this._sortable = Sortable.create(list, {
                handle: '.route-handle',
                animation: 150,
                onEnd: (evt) => {
                    if (evt.oldIndex === evt.newIndex) return;
                    const route = this.planner.route || [];
                    const moved = route.splice(evt.oldIndex, 1)[0];
                    route.splice(evt.newIndex, 0, moved);
                    this.planner.route = route;
                    this._syncCitiesFromRoute();
                    this.markDirty();
                },
            });
            this.setupDatepickers();
        },

        // Init flatpickr pe toate input[type=date] din planner — afișează DD/MM/YYYY (locale ro)
        // dar trimite Y-m-d către backend (compatibil cu logica de date_iso existentă).
        setupDatepickers() {
            if (typeof flatpickr === 'undefined') return;
            const inputs = document.querySelectorAll('main input[type="date"]:not([data-fp-init])');
            inputs.forEach(el => {
                el.dataset.fpInit = '1';
                flatpickr(el, {
                    dateFormat: 'Y-m-d',
                    altInput: true,
                    altFormat: 'd/m/Y',
                    locale: (window.flatpickr?.l10ns?.ro) || 'default',
                    allowInput: true,
                });
            });
        },

        renderTab(t) {
            if (t === 'opportunities') this.renderOpportunityMap();
            else if (t === 'planner') {
                if (this.planner.optimized) {
                    this.$nextTick(() => {
                        this.renderPlannerMap();
                        this.setupSortable();
                    });
                }
            }
            else if (t === 'predictions') {
                this.renderWeekdayChart();
                this.renderSeasonChart();
            }
        },

        clearCanvas(elId) {
            const el = document.getElementById(elId);
            if (!el || typeof Chart === 'undefined') return null;
            const existing = Chart.getChart(el);
            if (existing) existing.destroy();
            return el;
        },

        destroyChart(key) {
            if (this.charts[key]) { this.charts[key].destroy(); delete this.charts[key]; }
        },

        renderOpportunityMap() {
            if (!this.opportunityMap) {
                this.opportunityMap = L.map('opportunityMap').setView([45.9432, 24.9668], 6);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { attribution: '© OpenStreetMap, © CARTO' }).addTo(this.opportunityMap);
            } else {
                this.opportunityMap.eachLayer(layer => {
                    if (layer instanceof L.Marker || layer instanceof L.CircleMarker) this.opportunityMap.removeLayer(layer);
                });
            }

            const colorMap = { active: '#10B981', warm: '#F59E0B', sleeping: '#EF4444', new: '#A51C30' };
            const recCities = (this.opportunities.recommendations || []).map(r => r.city);

            (this.opportunities.cities || []).forEach(c => {
                const isRec = recCities.includes(c.name);
                const size = Math.max(28, Math.min(60, c.fans / 80));
                const color = colorMap[c.status] || '#94A3B8';
                const ringColor = isRec ? '#E67E22' : 'white';
                const ringWidth = isRec ? 3 : 2;
                const fansLabel = c.fans > 999 ? Math.round(c.fans / 1000) + 'k' : c.fans;
                const icon = L.divIcon({
                    html: `<div style="background:${color};color:white;border-radius:50%;width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:11px;border:${ringWidth}px solid ${ringColor};box-shadow:0 2px 8px rgba(0,0,0,0.3)">${fansLabel}</div>`,
                    className: '',
                    iconSize: [size, size],
                });
                const marker = L.marker([c.lat, c.lng], { icon }).addTo(this.opportunityMap);
                const since = c.months_ago !== null ? c.months_ago + ' luni de la ultim concert' : 'Niciodată cântat acolo';
                marker.bindPopup(`<strong>${c.name}</strong><br>${c.fans.toLocaleString('ro-RO')} fani · ${c.events_count} evenimente<br><small>${since}</small>`);
            });

            setTimeout(() => this.opportunityMap.invalidateSize(), 100);
        },

        renderPlannerMap() {
            if (!this.planner.optimized) return;
            if (!this.plannerMap) {
                this.plannerMap = L.map('plannerMap').setView([45.9432, 24.9668], 6);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', { attribution: '© OpenStreetMap, © CARTO' }).addTo(this.plannerMap);
            } else {
                this.plannerMap.eachLayer(layer => {
                    if (layer instanceof L.Marker || layer instanceof L.Polyline) this.plannerMap.removeLayer(layer);
                });
            }

            const points = [];
            (this.planner.route || []).forEach((stop, idx) => {
                const icon = L.divIcon({
                    html: `<div style="background:#A51C30;color:white;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:14px;border:3px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.4)">${idx + 1}</div>`,
                    className: '',
                    iconSize: [36, 36],
                });
                L.marker([stop.lat, stop.lng], { icon }).addTo(this.plannerMap)
                    .bindPopup(`<strong>${idx + 1}. ${stop.city}</strong><br>${stop.date}<br>${stop.prediction} bilete prog.`);
                points.push([stop.lat, stop.lng]);
            });

            if (points.length > 1) {
                L.polyline(points, { color: '#A51C30', weight: 3, opacity: 0.8, dashArray: '8, 8' }).addTo(this.plannerMap);
                this.plannerMap.fitBounds(points, { padding: [40, 40] });
            }

            setTimeout(() => this.plannerMap.invalidateSize(), 100);
        },

        renderWeekdayChart() {
            this.destroyChart('weekday');
            const el = this.clearCanvas('weekdayChart');
            if (!el) return;
            const w = this.predictionsData.weekday || { labels: [], values: [] };
            this.charts.weekday = new Chart(el.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: w.labels,
                    datasets: [{
                        data: w.values,
                        backgroundColor: w.labels.map((_, i) => i >= 4 && i <= 5 ? '#A51C30' : (i === 3 || i === 6 ? '#E67E22' : '#94A3B8')),
                        borderRadius: 6,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: false, resizeDelay: 200, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.parsed.y + ' bilete medie/eveniment' } } }, scales: { y: { beginAtZero: true } } }
            });
        },

        renderSeasonChart() {
            this.destroyChart('season');
            const el = this.clearCanvas('seasonChart');
            if (!el) return;
            const s = this.predictionsData.seasonality || { labels: [], values: [] };
            this.charts.season = new Chart(el.getContext('2d'), {
                type: 'line',
                data: {
                    labels: s.labels,
                    datasets: [{
                        data: s.values,
                        borderColor: '#A51C30',
                        backgroundColor: 'rgba(165,28,48,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        borderWidth: 2,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: false, resizeDelay: 200, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => c.parsed.y + ' bilete medie/eveniment' } } }, scales: { y: { beginAtZero: true } } }
            });
        },

        statusBadgeClass(status) {
            return ({
                active: 'bg-success/10 text-success',
                warm: 'bg-warning/10 text-warning',
                sleeping: 'bg-error/10 text-error',
                new: 'bg-primary/10 text-primary',
            })[status] || 'bg-muted/10 text-muted';
        },

        formatNumber(n) {
            const num = Number(n) || 0;
            return num.toLocaleString('ro-RO');
        },

        formatDuration(minutes) {
            const m = Math.max(0, Math.round(Number(minutes) || 0));
            if (m < 60) return m + ' min';
            const h = Math.floor(m / 60);
            const mm = m % 60;
            return mm > 0 ? h + 'h ' + mm + 'min' : h + 'h';
        },

        confidenceText(stop) {
            const factors = stop?.confidence_factors || [];
            const lines = ['încredere= încrederea în predicția de bilete (25-95%).', 'Calculat din 4 factori:'];
            if (factors.length) {
                factors.forEach(f => lines.push('  • ' + f));
            } else {
                lines.push('  • baza: număr concerte trecute în oraș');
                lines.push('  • +/- pentru audiență (fani locali)');
                lines.push('  • -10 pentru venue mare cu istoric mic');
                lines.push('  • -5/-10 pentru ultimul concert vechi (>12/18 luni)');
            }
            lines.push('---');
            lines.push('Total: ' + (stop?.încredere?? 0) + '%');
            return lines.join('\n');
        },

        // Formule explicative pentru tooltip-urile cost cards
        fuelFormulaText(stop) {
            const cfg = this.planner.config;
            const totalConsumption = (cfg.vehicles || []).reduce((s, v) => s + (v.count * v.consumption_l_100km), 0);
            const lines = [];
            const src = stop?.routing_source || 'osrm';
            const sourceLabel = ({
                mapbox: 'Distanța reală pe ruta auto (Mapbox).',
                osrm: 'Distanța reală pe ruta auto (OpenStreetMap / OSRM).',
                fallback: 'Aproximativ (Haversine × 1.35) — routing API indisponibil acum.',
            })[src] || 'Distanța reală pe ruta auto.';
            lines.push(sourceLabel);
            lines.push('Formula: km × consum total / 100 × preț RON/L');
            lines.push('Consum total: ' + (cfg.vehicles || []).map(v => v.count + '×' + v.consumption_l_100km).join(' + ') + ' = ' + totalConsumption.toFixed(1) + ' L/100km');
            lines.push('Preț: ' + cfg.fuel_price_ron_l + ' RON/L');
            if (stop && (stop.fuel_arrival_km ?? 0) > 0) {
                lines.push('---');
                lines.push('Sosire ~' + (stop.arrival_road_km || 0) + ' km rută (' + this.formatDuration(stop.arrival_drive_time_min) + ') → ' + this.formatNumber(stop.fuel_arrival_cost) + ' RON');
            }
            if (stop && (stop.fuel_return_leg_km ?? 0) > 0) {
                lines.push('Retur la ' + this.planner.config.start_location + ' ~' + (stop.return_leg_road_km || 0) + ' km rută (' + this.formatDuration(stop.return_leg_drive_time_min) + ') → ' + this.formatNumber(stop.fuel_return_leg_cost) + ' RON');
            }
            return lines.join('\n');
        },

        accommodationFormulaText(stop) {
            const cfg = this.planner.config;
            const r = cfg.rooms || {};
            const p = cfg.room_prices || {};
            const lines = ['Estimativ.', 'Formula: nopți × suma camere × preț/noapte'];
            const breakdown = [];
            if ((r.single | 0) > 0) breakdown.push((r.single | 0) + '×single (' + (p.single | 0) + ' RON)');
            if ((r.double | 0) > 0) breakdown.push((r.double | 0) + '×double (' + (p.double | 0) + ' RON)');
            if ((r.apartment | 0) > 0) breakdown.push((r.apartment | 0) + '×apartament (' + (p.apartment | 0) + ' RON)');
            if (breakdown.length) lines.push(breakdown.join(' + '));
            const perNight = (r.single | 0) * (p.single | 0) + (r.double | 0) * (p.double | 0) + (r.apartment | 0) * (p.apartment | 0);
            lines.push('Total/noapte: ' + this.formatNumber(perNight) + ' RON');
            if (stop) lines.push('×' + (stop.nights || 1) + ' nopți = ' + this.formatNumber(stop.accommodation_cost || 0) + ' RON');
            return lines.join('\n');
        },

        mealFormulaText(stop) {
            const cfg = this.planner.config;
            const lines = ['Estimativ.', 'Formula: nopți × persoane × preț/zi'];
            lines.push(cfg.people_count + ' persoane × ' + cfg.meal_price_per_day + ' RON/zi');
            if (stop) {
                const nights = stop.nights || 0;
                if (stop.is_home) {
                    lines.push('🏠 Concert acasă → 0 RON (echipa nu e pe drum)');
                } else {
                    lines.push('×' + nights + ' nopți la stop = ' + this.formatNumber(stop.meal_cost || 0) + ' RON');
                }
            }
            return lines.join('\n');
        },

        homeBaseSubtitle(name) {
            const c = (this.homeBaseOptions || []).find(x => x.name === name);
            if (!c) return '';
            const parts = [];
            if (c.state) parts.push(c.state);
            if (c.country) parts.push(c.country);
            return parts.join(', ');
        },

        filterHomeBaseOptions(query) {
            const q = this.normalizeKey(query || '');
            const all = this.homeBaseOptions || [];
            if (!q) return all.slice(0, 60);
            return all.filter(c => {
                const hay = this.normalizeKey((c.name || '') + ' ' + (c.state || '') + ' ' + (c.country || ''));
                return hay.indexOf(q) !== -1;
            }).slice(0, 60);
        },

        signed(n) {
            const num = Number(n) || 0;
            return (num >= 0 ? '+' : '') + num;
        },
    };
}
</script>

<script>
(function() {
    const token = localStorage.getItem('ambilet_artist_token');
    if (!token) { window.location.href = '/artist/login'; return; }
    fetch('/api/proxy.php?action=artist.extended-artist.status', { headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token } })
        .then(r => r.json())
        .then(payload => { if (payload?.data?.enabled !== true) window.location.href = '/artist/cont/extended-artist'; })
        .catch(() => { window.location.href = '/artist/cont/extended-artist'; });
})();
</script>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/artist-cont-shared.js') . '"></script>';
require_once dirname(__DIR__, 3) . '/includes/scripts.php';
?>
