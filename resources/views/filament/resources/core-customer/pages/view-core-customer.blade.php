<x-filament-panels::page>
    @php
        $c = $this->record;
        $has = $this->hasMarketplaceData;
        $mkCustomer = $has && $this->linkedMktCustomerId ? \App\Models\MarketplaceCustomer::find($this->linkedMktCustomerId) : null;

        $fmt = fn ($v, $cur = 'EUR') => number_format((float) ($v ?? 0), 2) . ' ' . $cur;
        $fmtInt = fn ($v) => number_format((int) ($v ?? 0));
    @endphp

    <div x-data="{ tab: 'overview' }" class="space-y-6">
        {{-- UNIFIED TAB NAVIGATION --}}
        <div class="flex flex-wrap gap-1 pb-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700">
            @foreach([
                'overview' => ['Prezentare Generală', 'heroicon-o-user-circle'],
                'insights' => ['Customer Insights', 'heroicon-o-chart-bar'],
                'analytics' => ['Analiză & Scoring', 'heroicon-o-chart-pie'],
                'gamification' => ['Gamification', 'heroicon-o-star'],
                'orders' => ['Comenzi & Bilete', 'heroicon-o-receipt-percent'],
                'emails' => ['Istoric Email-uri', 'heroicon-o-envelope'],
                'tracking' => ['Engagement Tracking', 'heroicon-o-cursor-arrow-rays'],
                'attribution' => ['Attribution & Platform', 'heroicon-o-arrow-trending-up'],
                'integrations' => ['Integrations', 'heroicon-o-link'],
                'notes' => ['Notes', 'heroicon-o-pencil-square'],
            ] as $tabKey => [$tabLabel, $tabIcon])
                <button @click="tab = '{{ $tabKey }}'"
                    :class="tab === '{{ $tabKey }}'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800'"
                    class="flex items-center gap-2 px-3 py-2 text-xs font-medium border-b-2 rounded-t-lg transition-all whitespace-nowrap">
                    <x-filament::icon :icon="$tabIcon" class="w-4 h-4" />
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        {{-- ═══ TAB 1: PREZENTARE GENERALĂ ═══ --}}
        <div x-show="tab === 'overview'" x-cloak>
            {{-- CoreCustomer Stats --}}
            <div class="grid grid-cols-2 gap-3 mb-6 md:grid-cols-5">
                @foreach([
                    ['Orders', $fmtInt($c->total_orders), 'text-blue-400'],
                    ['Tickets', $fmtInt($c->total_tickets), 'text-emerald-400'],
                    ['Total Spent', $fmt($c->total_spent, $c->currency ?? 'EUR'), 'text-amber-400'],
                    ['Avg Order', $fmt($c->average_order_value, $c->currency ?? 'EUR'), 'text-violet-400'],
                    ['LTV', $fmt($c->lifetime_value, $c->currency ?? 'EUR'), 'text-pink-400'],
                ] as [$label, $value, $color])
                    <div class="p-4 text-center bg-white shadow-sm rounded-xl dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="text-xl font-bold {{ $color }}">{{ $value }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                {{-- Identity --}}
                <x-filament::section>
                    <x-slot name="heading">Identitate</x-slot>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach([
                            'Email' => $c->email ?? '—',
                            'Nume' => $c->full_name ?? '—',
                            'Telefon' => $c->phone ?? '—',
                            'Locație' => implode(', ', array_filter([$c->city, $c->region, $c->country_code])) ?: '—',
                            'Limbă' => strtoupper($c->language ?? '—'),
                            'Gen' => ucfirst($c->gender ?? '—'),
                            'Vârstă' => $c->age_range ?? '—',
                            'First Seen' => $c->first_seen_at?->format('d M Y') ?? '—',
                            'Last Seen' => $c->last_seen_at?->diffForHumans() ?? '—',
                        ] as $label => $value)
                            <div class="flex justify-between px-1 py-2 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>

                {{-- Scoring --}}
                <x-filament::section>
                    <x-slot name="heading">Scoring & Status</x-slot>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach([
                            'Segment' => $c->customer_segment ?? '—',
                            'RFM Segment' => $c->rfm_segment ?? '—',
                            'RFM Score' => ($c->rfm_recency_score ?? 0) . '/' . ($c->rfm_frequency_score ?? 0) . '/' . ($c->rfm_monetary_score ?? 0),
                            'Engagement' => ($c->engagement_score ?? 0) . '/100',
                            'Health' => ($c->health_score ?? 0) . '/100',
                            'Churn Risk' => ($c->churn_risk_score ?? 0) . '/100',
                            'Purchase Likelihood' => ($c->purchase_likelihood_score ?? 0) . '/100',
                            'First Purchase' => $c->first_purchase_at?->format('d M Y') ?? '—',
                            'Last Purchase' => $c->last_purchase_at?->format('d M Y') ?? '—',
                        ] as $label => $value)
                            <div class="flex justify-between px-1 py-2 text-sm">
                                <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            </div>

            {{-- Marketplace Overview (narrative, chart, etc.) --}}
            @if($has)
                @php $record = $mkCustomer; @endphp
                @if(!empty($profileNarrative))
                    <div class="p-4 mt-6 rounded-xl bg-gradient-to-r from-indigo-50 via-purple-50 to-pink-50 dark:from-indigo-950/40 dark:via-purple-950/40 dark:to-pink-950/40 ring-1 ring-indigo-200/60 dark:ring-indigo-700/40">
                        <div class="flex items-start gap-3">
                            <div class="flex-shrink-0 p-2 rounded-lg bg-indigo-100 dark:bg-indigo-900/50">
                                <x-filament::icon icon="heroicon-o-user-circle" class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div>
                                <h3 class="mb-1 text-sm font-semibold text-indigo-700 dark:text-indigo-300">Profil Client Generat</h3>
                                <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $profileNarrative }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Marketplace personal info --}}
                @if($mkCustomer)
                    <div class="mt-6">
                        <x-filament::section>
                            <x-slot name="heading">Date Marketplace</x-slot>
                            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach([
                                    'Marketplace' => $mkCustomer->marketplaceClient?->name ?? '—',
                                    'Status' => $mkCustomer->status ?? '—',
                                    'Email Verificat' => $emailVerifiedDisplay ?? 'Nu',
                                    'Accepts Marketing' => $acceptsMarketingDisplay ? 'Da' : 'Nu',
                                    'Ultimul Login' => $mkCustomer->last_login_at?->format('d.m.Y H:i') ?? 'Niciodată',
                                    'Înregistrat' => $mkCustomer->created_at?->format('d.m.Y H:i') ?? '—',
                                ] as $label => $value)
                                    <div class="flex justify-between px-1 py-2 text-sm">
                                        <span class="text-gray-500 dark:text-gray-400">{{ $label }}</span>
                                        <span class="text-gray-900 dark:text-gray-100">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    </div>
                @endif
            @endif
        </div>

        {{-- ═══ TAB 2: CUSTOMER INSIGHTS ═══ --}}
        <div x-show="tab === 'insights'" x-cloak>
            @if($has)
                @include('filament.resources.core-customer.pages.partials.insights')
            @else
                <p class="py-12 text-sm text-center text-gray-500">Lipsă cont marketplace asociat.</p>
            @endif
        </div>

        {{-- ═══ TAB 3: ANALIZĂ & SCORING ═══ --}}
        <div x-show="tab === 'analytics'" x-cloak>
            @include('filament.resources.core-customer.pages.partials.analytics')
        </div>

        {{-- ═══ TAB 4: GAMIFICATION ═══ --}}
        <div x-show="tab === 'gamification'" x-cloak>
            @if($has)
                @include('filament.resources.core-customer.pages.partials.gamification')
            @else
                <p class="py-12 text-sm text-center text-gray-500">Lipsă cont marketplace asociat.</p>
            @endif
        </div>

        {{-- ═══ TAB 5: COMENZI & BILETE ═══ --}}
        <div x-show="tab === 'orders'" x-cloak>
            @if($has)
                @include('filament.resources.core-customer.pages.partials.orders')
            @else
                <p class="py-12 text-sm text-center text-gray-500">Lipsă cont marketplace asociat.</p>
            @endif
        </div>

        {{-- ═══ TAB 6: ISTORIC EMAIL-URI ═══ --}}
        <div x-show="tab === 'emails'" x-cloak>
            @include('filament.resources.core-customer.pages.partials.emails')
        </div>

        {{-- ═══ TAB: ENGAGEMENT TRACKING ═══ --}}
        <div x-show="tab === 'tracking'" x-cloak>
            @php $trackingEvents = $c->events()->orderBy('created_at', 'desc')->limit(100)->get(); @endphp
            <x-filament::section>
                <x-slot name="heading">Event Timeline ({{ $trackingEvents->count() }} cele mai recente)</x-slot>
                @if($trackingEvents->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800"><tr>
                                <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Time</th>
                                <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Event</th>
                                <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Category</th>
                                <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Page</th>
                                <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Content</th>
                                <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Value</th>
                            </tr></thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($trackingEvents as $ev)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $ev->created_at->format('d M Y H:i:s') }}</td>
                                        <td class="px-3 py-2">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                                {{ match($ev->event_type) {
                                                    'purchase' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                                    'add_to_cart' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                                    'begin_checkout' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                                    'page_view' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                                    'view_item' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
                                                    'sign_up', 'login' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300',
                                                    default => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                                } }}">{{ $ev->event_type }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $ev->event_category ?? '—' }}</td>
                                        <td class="max-w-xs px-3 py-2 text-gray-600 truncate dark:text-gray-400" title="{{ $ev->page_url ?? '' }}">{{ $ev->page_path ?? $ev->page_url ?? '—' }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $ev->content_name ?? $ev->content_type ?? '—' }}</td>
                                        <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">{{ $ev->conversion_value ? number_format((float)$ev->conversion_value, 2) : ($ev->event_value ?? '') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nu există date de tracking pentru acest client.</p>
                @endif
            </x-filament::section>
        </div>

        {{-- ═══ TAB: ATTRIBUTION & PLATFORM ═══ --}}
        <div x-show="tab === 'attribution'" x-cloak>
            @include('filament.resources.core-customer.pages.partials.attribution')
        </div>

        {{-- ═══ TAB 8: INTEGRATIONS ═══ --}}
        <div x-show="tab === 'integrations'" x-cloak>
            @include('filament.resources.core-customer.pages.partials.integrations')
        </div>

        {{-- ═══ TAB 9: NOTES ═══ --}}
        <div x-show="tab === 'notes'" x-cloak>
            <x-filament::section>
                <x-slot name="heading">Tags & Notes</x-slot>
                <div class="mb-3">
                    <label class="block mb-1 text-xs font-medium text-gray-500">Tags</label>
                    <div class="text-sm">
                        @if(!empty($c->tags))
                            @foreach((array) $c->tags as $tag)
                                <span class="inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-primary-500/20 text-primary-600 dark:text-primary-300">{{ e($tag) }}</span>
                            @endforeach
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block mb-1 text-xs font-medium text-gray-500">Notes</label>
                    <div class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ e($c->notes ?? '') ?: '—' }}</div>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
