<x-filament-panels::page>
    @php
        $c = $this->record; // CoreCustomer
        $m = $this->linkedMarketplaceCustomer; // MarketplaceCustomer or null
        $has = $this->hasMarketplaceData;

        // Helper: format number
        $fmt = fn ($v, $cur = 'EUR') => number_format((float) ($v ?? 0), 2) . ' ' . $cur;
        $fmtInt = fn ($v) => number_format((int) ($v ?? 0));
    @endphp

    <div x-data="{ tab: 'overview' }" class="space-y-6" wire:ignore.self>
        {{-- UNIFIED TAB NAVIGATION --}}
        <div class="flex flex-wrap gap-1 pb-1 border-b border-gray-700">
            @foreach([
                'overview' => ['Prezentare Generală', 'heroicon-o-user-circle'],
                'insights' => ['Customer Insights', 'heroicon-o-chart-bar'],
                'analytics' => ['Analiză & Scoring', 'heroicon-o-chart-pie'],
                'attribution' => ['Attribution & Platform', 'heroicon-o-arrow-trending-up'],
                'orders' => ['Comenzi & Bilete', 'heroicon-o-receipt-percent'],
                'emails' => ['Email & Privacy', 'heroicon-o-envelope'],
                'integrations' => ['Integrations', 'heroicon-o-link'],
                'notes' => ['Notes', 'heroicon-o-pencil-square'],
            ] as $tabKey => [$tabLabel, $tabIcon])
                <button @click="tab = '{{ $tabKey }}'"
                    :class="tab === '{{ $tabKey }}'
                        ? 'border-primary-500 text-primary-400 bg-primary-900/20'
                        : 'border-transparent text-gray-400 hover:text-gray-200 hover:bg-gray-800'"
                    class="flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-t-lg border-b-2 transition-all">
                    <x-filament::icon :icon="$tabIcon" class="w-4 h-4" />
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 1: PREZENTARE GENERALĂ (Overview + Sinteză)
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'overview'" x-cloak>
            {{-- Stats Cards --}}
            <div class="grid grid-cols-2 gap-3 mb-6 md:grid-cols-5">
                @foreach([
                    ['Orders', $fmtInt($c->total_orders), '#60A5FA'],
                    ['Tickets', $fmtInt($c->total_tickets), '#34D399'],
                    ['Total Spent', $fmt($c->total_spent, $c->currency ?? 'EUR'), '#F59E0B'],
                    ['Avg Order', $fmt($c->average_order_value, $c->currency ?? 'EUR'), '#A78BFA'],
                    ['LTV', $fmt($c->lifetime_value, $c->currency ?? 'EUR'), '#F472B6'],
                ] as [$label, $value, $color])
                    <div class="p-4 text-center rounded-xl" style="background:rgba(30,41,59,0.5);">
                        <div class="text-xl font-bold" style="color:{{ $color }}">{{ $value }}</div>
                        <div class="mt-1 text-xs text-gray-500">{{ $label }}</div>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                {{-- Identity --}}
                <div class="p-4 rounded-xl bg-gray-800/40">
                    <h3 class="mb-3 text-sm font-semibold text-gray-300">Identitate</h3>
                    @foreach([
                        'Email' => $c->email ?? '—',
                        'Nume' => $c->full_name ?? '—',
                        'Telefon' => $c->phone ?? '—',
                        'Locație' => implode(', ', array_filter([$c->city, $c->region, $c->country_code])) ?: '—',
                        'Limbă' => strtoupper($c->language ?? '—'),
                        'Gen' => ucfirst($c->gender ?? '—'),
                        'Vârstă' => $c->age_range ?? '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>

                {{-- Scoring --}}
                <div class="p-4 rounded-xl bg-gray-800/40">
                    <h3 class="mb-3 text-sm font-semibold text-gray-300">Scoring & Status</h3>
                    @foreach([
                        'Segment' => $c->customer_segment ?? '—',
                        'RFM Segment' => $c->rfm_segment ?? '—',
                        'RFM Score' => ($c->rfm_recency_score ?? 0) . '/' . ($c->rfm_frequency_score ?? 0) . '/' . ($c->rfm_monetary_score ?? 0),
                        'Engagement' => ($c->engagement_score ?? 0) . '/100',
                        'Health' => ($c->health_score ?? 0) . '/100',
                        'Churn Risk' => ($c->churn_risk_score ?? 0) . '/100',
                        'First Purchase' => $c->first_purchase_at?->format('d M Y') ?? '—',
                        'Last Purchase' => $c->last_purchase_at?->format('d M Y') ?? '—',
                        'First Seen' => $c->first_seen_at?->format('d M Y') ?? '—',
                        'Last Seen' => $c->last_seen_at?->diffForHumans() ?? '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Marketplace Overview (if linked) --}}
            @if($has)
                <div class="pt-4 mt-4 border-t border-gray-700">
                    @include('filament.marketplace-customers.pages.partials.overview-content', ['record' => $m])
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 2: CUSTOMER INSIGHTS
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'insights'" x-cloak>
            @if($has)
                @include('filament.marketplace-customers.pages.partials.insights-content')
            @else
                <p class="py-12 text-sm text-center text-gray-500">Nu există date de insights — lipsă cont marketplace asociat.</p>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 3: ANALIZĂ & SCORING (Purchase + Segmentation + Gamification + Engagement)
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'analytics'" x-cloak>
            {{-- Purchase Behavior --}}
            <div class="p-4 mb-4 rounded-xl bg-gray-800/40">
                <h3 class="mb-3 text-sm font-semibold text-gray-300">Purchase Behavior</h3>
                <div class="grid gap-x-6 gap-y-1 md:grid-cols-3">
                    @foreach([
                        'Total Orders' => $fmtInt($c->total_orders),
                        'Total Tickets' => $fmtInt($c->total_tickets),
                        'Total Spent' => $fmt($c->total_spent, $c->currency ?? 'EUR'),
                        'Avg Order Value' => $fmt($c->average_order_value, $c->currency ?? 'EUR'),
                        'LTV' => $fmt($c->lifetime_value, $c->currency ?? 'EUR'),
                        'Purchase Frequency' => $c->purchase_frequency_days ? 'Every ' . $c->purchase_frequency_days . ' days' : '—',
                        'Days Since Last' => $c->days_since_last_purchase ?? '—',
                        'Cart Abandoned' => $c->has_cart_abandoned ? 'Yes' : 'No',
                        'Predicted LTV' => $c->predicted_ltv ? $fmt($c->predicted_ltv, $c->currency ?? 'EUR') : '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Engagement Metrics --}}
            <div class="p-4 mb-4 rounded-xl bg-gray-800/40">
                <h3 class="mb-3 text-sm font-semibold text-gray-300">Engagement Metrics</h3>
                <div class="grid gap-x-6 gap-y-1 md:grid-cols-3">
                    @php
                        $timeSpent = $c->total_time_spent_seconds ?? 0;
                        $timeLabel = $timeSpent < 60 ? $timeSpent . 's' : ($timeSpent < 3600 ? round($timeSpent/60) . 'min' : round($timeSpent/3600,1) . 'h');
                        $avgSession = $c->avg_session_duration_seconds ?? 0;
                        $avgLabel = $avgSession < 60 ? $avgSession . 's' : round($avgSession/60,1) . 'min';
                    @endphp
                    @foreach([
                        'Total Visits' => $fmtInt($c->total_visits),
                        'Total Pageviews' => $fmtInt($c->total_pageviews),
                        'Total Sessions' => $fmtInt($c->total_sessions),
                        'Time Spent' => $timeLabel,
                        'Avg Session' => $avgLabel,
                        'Bounce Rate' => $c->bounce_rate !== null ? number_format((float) $c->bounce_rate, 1) . '%' : '—',
                        'Events Viewed' => $c->total_events_viewed ?? 0,
                        'Events Attended' => $c->total_events_attended ?? 0,
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Gamification (from marketplace) --}}
            @if($has)
                @include('filament.marketplace-customers.pages.partials.gamification-content')
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 4: ATTRIBUTION & PLATFORM
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'attribution'" x-cloak>
            <div class="grid gap-4 md:grid-cols-2">
                {{-- First Touch --}}
                <div class="p-4 rounded-xl bg-gray-800/40">
                    <h3 class="mb-3 text-sm font-semibold text-gray-300">First Touch</h3>
                    @foreach([
                        'Source' => $c->first_source ?? '—',
                        'Medium' => $c->first_medium ?? '—',
                        'Campaign' => $c->first_campaign ?? '—',
                        'Referrer' => $c->first_referrer ?? '—',
                        'UTM' => implode(' / ', array_filter([$c->first_utm_source, $c->first_utm_medium, $c->first_utm_campaign])) ?: '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ e($value) }}</span>
                        </div>
                    @endforeach
                </div>
                {{-- Last Touch --}}
                <div class="p-4 rounded-xl bg-gray-800/40">
                    <h3 class="mb-3 text-sm font-semibold text-gray-300">Last Touch</h3>
                    @foreach([
                        'Source' => $c->last_source ?? '—',
                        'Medium' => $c->last_medium ?? '—',
                        'Campaign' => $c->last_campaign ?? '—',
                        'UTM' => implode(' / ', array_filter([$c->last_utm_source, $c->last_utm_medium, $c->last_utm_campaign])) ?: '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ e($value) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Click IDs --}}
            <div class="p-4 mt-4 rounded-xl bg-gray-800/40">
                <h3 class="mb-3 text-sm font-semibold text-gray-300">Click IDs</h3>
                <div class="grid gap-x-6 gap-y-1 md:grid-cols-4">
                    @foreach([
                        'Google (gclid)' => $c->first_gclid ? substr($c->first_gclid, 0, 20) . '...' : '—',
                        'Facebook (fbclid)' => $c->first_fbclid ? substr($c->first_fbclid, 0, 20) . '...' : '—',
                        'TikTok (ttclid)' => $c->first_ttclid ? substr($c->first_ttclid, 0, 20) . '...' : '—',
                        'LinkedIn' => $c->first_li_fat_id ? substr($c->first_li_fat_id, 0, 20) . '...' : '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-mono text-gray-200" style="font-size:10px">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Platform & Device --}}
            <div class="p-4 mt-4 rounded-xl bg-gray-800/40">
                <h3 class="mb-3 text-sm font-semibold text-gray-300">Platform & Device</h3>
                <div class="grid gap-x-6 gap-y-1 md:grid-cols-3">
                    @php
                        $marketplace = $c->primary_marketplace_client_id
                            ? (\App\Models\MarketplaceClient::find($c->primary_marketplace_client_id)?->name ?? 'ID: ' . $c->primary_marketplace_client_id)
                            : '—';
                        $tenant = $c->primary_tenant_id
                            ? (\App\Models\Tenant::find($c->primary_tenant_id)?->public_name ?? \App\Models\Tenant::find($c->primary_tenant_id)?->name ?? 'ID: ' . $c->primary_tenant_id)
                            : '—';
                    @endphp
                    @foreach([
                        'Marketplace' => $marketplace,
                        'Tenant' => $tenant,
                        'Marketplace Count' => $c->marketplace_client_count ?? 0,
                        'Tenant Count' => $c->tenant_count ?? 0,
                        'Device' => ucfirst($c->device_type ?? $c->primary_device ?? '—'),
                        'Browser' => $c->browser ?? $c->primary_browser ?? '—',
                        'OS' => $c->os ?? '—',
                        'IP' => $c->ip_address ?? '—',
                        'Visitor ID' => $c->visitor_id ? substr($c->visitor_id, 0, 16) . '...' : '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ e($value) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Event Timeline --}}
            @php
                $events = $c->events()->orderBy('created_at', 'desc')->limit(50)->get();
            @endphp
            @if($events->isNotEmpty())
                <div class="p-4 mt-4 rounded-xl bg-gray-800/40">
                    <h3 class="mb-3 text-sm font-semibold text-gray-300">Event Timeline (last 50)</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead><tr class="text-gray-500 border-b border-gray-700">
                                <th class="px-2 py-1.5 text-left">Time</th>
                                <th class="px-2 py-1.5 text-left">Event</th>
                                <th class="px-2 py-1.5 text-left">Page</th>
                                <th class="px-2 py-1.5 text-right">Value</th>
                            </tr></thead>
                            <tbody>
                                @foreach($events as $ev)
                                    <tr class="border-b border-gray-800/50">
                                        <td class="px-2 py-1.5 text-gray-400 whitespace-nowrap">{{ $ev->created_at->format('d M H:i:s') }}</td>
                                        <td class="px-2 py-1.5">
                                            @php $evColor = match($ev->event_type) { 'purchase' => 'green', 'add_to_cart' => 'yellow', 'begin_checkout' => 'blue', 'page_view' => 'gray', 'view_item' => 'gray', default => 'gray' }; @endphp
                                            <span class="px-1.5 py-0.5 rounded text-{{ $evColor }}-400 bg-{{ $evColor }}-500/20">{{ $ev->event_type }}</span>
                                        </td>
                                        <td class="px-2 py-1.5 text-gray-400 max-w-[200px] truncate">{{ $ev->page_url ?? $ev->page_path ?? '—' }}</td>
                                        <td class="px-2 py-1.5 text-right text-gray-300">{{ $ev->conversion_value ? number_format((float) $ev->conversion_value, 2) : '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 5: COMENZI & BILETE
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'orders'" x-cloak>
            @if($has)
                @include('filament.marketplace-customers.pages.partials.orders-content', ['record' => $m])
            @else
                <p class="py-12 text-sm text-center text-gray-500">Nu există date de comenzi — lipsă cont marketplace asociat.</p>
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 6: EMAIL & PRIVACY + ISTORIC EMAIL-URI
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'emails'" x-cloak>
            {{-- Core Email Engagement --}}
            <div class="p-4 mb-4 rounded-xl bg-gray-800/40">
                <h3 class="mb-3 text-sm font-semibold text-gray-300">Email Engagement</h3>
                <div class="grid gap-x-6 gap-y-1 md:grid-cols-3">
                    @foreach([
                        'Subscribed' => $c->email_subscribed ? 'Yes' : 'No',
                        'Emails Sent' => $c->emails_sent ?? 0,
                        'Emails Opened' => $c->emails_opened ?? 0,
                        'Emails Clicked' => $c->emails_clicked ?? 0,
                        'Open Rate' => $c->email_open_rate !== null ? number_format((float) $c->email_open_rate, 1) . '%' : '—',
                        'Click Rate' => $c->email_click_rate !== null ? number_format((float) $c->email_click_rate, 1) . '%' : '—',
                        'Last Opened' => $c->last_email_opened_at?->format('d M Y H:i') ?? '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Consent & Privacy --}}
            <div class="p-4 mb-4 rounded-xl bg-gray-800/40">
                <h3 class="mb-3 text-sm font-semibold text-gray-300">Consent & Privacy</h3>
                <div class="grid gap-x-6 gap-y-1 md:grid-cols-3">
                    @foreach([
                        'Marketing Consent' => $c->marketing_consent ? 'Yes' : 'No',
                        'Analytics Consent' => $c->analytics_consent ? 'Yes' : 'No',
                        'Personalization' => $c->personalization_consent ? 'Yes' : 'No',
                        'Consent Updated' => $c->consent_updated_at?->format('d M Y') ?? '—',
                        'Consent Source' => $c->consent_source ?? '—',
                        'GDPR Anonymized' => $c->is_anonymized ? 'Yes' : 'No',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-medium text-gray-200">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Marketplace Email Logs --}}
            @if($has)
                @include('filament.marketplace-customers.pages.partials.emails-content', ['record' => $m])
            @endif
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 7: INTEGRATIONS
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'integrations'" x-cloak>
            <div class="p-4 rounded-xl bg-gray-800/40">
                <div class="grid gap-x-6 gap-y-1 md:grid-cols-2">
                    @foreach([
                        'Stripe Customer ID' => $c->stripe_customer_id ?? '—',
                        'Facebook User ID' => $c->facebook_user_id ?? '—',
                        'Google User ID' => $c->google_user_id ?? '—',
                        'Cohort Month' => $c->cohort_month ?? '—',
                        'Cohort Week' => $c->cohort_week ?? '—',
                        'UUID' => $c->uuid ?? '—',
                    ] as $label => $value)
                        <div class="flex justify-between py-1.5 text-xs border-b border-gray-700/50">
                            <span class="text-gray-500">{{ $label }}</span>
                            <span class="font-mono font-medium text-gray-200" style="font-size:11px">{{ e($value) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════
             TAB 8: NOTES
             ══════════════════════════════════════════════════════════ --}}
        <div x-show="tab === 'notes'" x-cloak>
            <div class="p-4 rounded-xl bg-gray-800/40">
                <h3 class="mb-3 text-sm font-semibold text-gray-300">Tags & Notes</h3>
                <div class="mb-3">
                    <label class="block mb-1 text-xs font-medium text-gray-400">Tags</label>
                    <div class="text-sm text-gray-200">
                        @if(!empty($c->tags))
                            @foreach((array) $c->tags as $tag)
                                <span class="inline-block px-2 py-0.5 mr-1 mb-1 text-xs rounded bg-primary-500/20 text-primary-300">{{ e($tag) }}</span>
                            @endforeach
                        @else
                            <span class="text-gray-500">—</span>
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block mb-1 text-xs font-medium text-gray-400">Notes</label>
                    <div class="text-sm text-gray-200 whitespace-pre-wrap">{{ e($c->notes ?? '') ?: '—' }}</div>
                </div>
                <div class="mt-4">
                    <a href="{{ \App\Filament\Resources\CoreCustomerResource::getUrl('edit', ['record' => $c]) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg bg-primary-500/20 text-primary-300 hover:bg-primary-500/30 transition-colors">
                        <x-filament::icon icon="heroicon-o-pencil-square" class="w-3.5 h-3.5" />
                        Edit Tags & Notes
                    </a>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
