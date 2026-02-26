<x-filament-panels::page>

    {{-- TAB NAVIGATION --}}
    <div x-data="{ activeTab: 'overview' }" class="space-y-6">
        {{-- Tab Buttons --}}
        <div class="flex flex-wrap gap-2 pb-1 border-b border-gray-200 dark:border-gray-700">
            @foreach([
                'overview' => ['Prezentare Generală', 'heroicon-o-user-circle'],
                'gamification' => ['Gamification', 'heroicon-o-star'],
                'orders' => ['Comenzi & Bilete', 'heroicon-o-receipt-percent'],
                'emails' => ['Istoric Email-uri', 'heroicon-o-envelope'],
                'insights' => ['Customer Insights', 'heroicon-o-chart-bar'],
            ] as $tabKey => [$tabLabel, $tabIcon])
                <button
                    @click="activeTab = '{{ $tabKey }}'"
                    :class="activeTab === '{{ $tabKey }}'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800'"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-all"
                >
                    <x-filament::icon :icon="$tabIcon" class="w-5 h-5" />
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 1: PREZENTARE GENERALĂ
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'overview'" x-cloak>

            <div class="grid grid-cols-2 gap-6 lg:grid-cols-2">

                {{-- LEFT COLUMN: Stat Cards (2-col grid) --}}
                <div class="space-y-6">
                    <x-filament::section>
                        <div class="grid grid-cols-3 gap-3">
                            @php
                                $topGenre = !empty($artistGenres) ? $artistGenres[0]['label'] : '-';
                                $top3Artists = !empty($topArtists) ? collect($topArtists)->take(3)->pluck('name')->implode(', ') : '-';
                                $topEventGenre = !empty($eventGenres) ? $eventGenres[0]['label'] : '-';
                                $topCity = !empty($preferredCities) ? $preferredCities[0]['label'] : '-';
                                $topDay = !empty($preferredDays) ? $preferredDays[0]['label'] : '-';
                                $topMonth = !empty($preferredMonths) ? $preferredMonths[0]['label'] : '-';
                            @endphp

                            @foreach([
                                ['Lifetime Value', number_format($lifetimeStats['lifetime_value'] ?? 0, 2) . ' RON', 'heroicon-o-banknotes', 'text-green-600 dark:text-green-400'],
                                ['Client din', ($lifetimeStats['customer_since'] ?? 'N/A') . ' (' . number_format($lifetimeStats['lifetime_days'] ?? 0) . ' zile)', 'heroicon-o-calendar', 'text-blue-600 dark:text-blue-400'],
                                ['Comenzi', number_format($lifetimeStats['total_orders'] ?? 0), 'heroicon-o-shopping-cart', 'text-amber-600 dark:text-amber-400'],
                                ['Bilete', number_format($lifetimeStats['total_tickets'] ?? 0), 'heroicon-o-ticket', 'text-cyan-600 dark:text-cyan-400'],
                                ['Evenimente', number_format($lifetimeStats['total_events'] ?? 0), 'heroicon-o-calendar-days', 'text-rose-600 dark:text-rose-400'],
                                ['Top Gen Muzical', $topGenre, 'heroicon-o-musical-note', 'text-violet-600 dark:text-violet-400'],
                                ['Top 3 Artiști', $top3Artists, 'heroicon-o-microphone', 'text-fuchsia-600 dark:text-fuchsia-400'],
                                ['Top Gen Eveniment', $topEventGenre, 'heroicon-o-sparkles', 'text-indigo-600 dark:text-indigo-400'],
                                ['Oraș Preferat', $topCity, 'heroicon-o-map-pin', 'text-emerald-600 dark:text-emerald-400'],
                                ['Zi Preferată', $topDay, 'heroicon-o-clock', 'text-orange-600 dark:text-orange-400'],
                                ['Lună Preferată', $topMonth, 'heroicon-o-sun', 'text-yellow-600 dark:text-yellow-400'],
                                ['Medie / Comandă', number_format($orderStatusBreakdown['avg_per_order'] ?? 0, 2) . ' RON', 'heroicon-o-calculator', 'text-teal-600 dark:text-teal-400'],
                                ['Medie / Bilet', number_format($orderStatusBreakdown['avg_per_ticket'] ?? 0, 2) . ' RON', 'heroicon-o-tag', 'text-sky-600 dark:text-sky-400'],
                                ['Pending', number_format($orderStatusBreakdown['pending_value'] ?? 0, 2) . ' RON', 'heroicon-o-clock', 'text-yellow-600 dark:text-yellow-400'],
                                ['Anulate', number_format($orderStatusBreakdown['cancelled_value'] ?? 0, 2) . ' RON', 'heroicon-o-x-circle', 'text-red-600 dark:text-red-400'],
                                ['Failed / Expirate', number_format($orderStatusBreakdown['failed_value'] ?? 0, 2) . ' RON', 'heroicon-o-exclamation-triangle', 'text-red-500 dark:text-red-400'],
                                ['Rambursări', number_format($orderStatusBreakdown['refund_value'] ?? 0, 2) . ' RON', 'heroicon-o-arrow-uturn-left', 'text-gray-600 dark:text-gray-400'],
                            ] as [$label, $value, $icon, $color])
                                <div class="p-3 bg-white shadow-sm rounded-xl dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <span class="text-[10px] font-medium text-gray-400 dark:text-gray-400 uppercase tracking-wide">{{ $label }}</span>
                                        <x-filament::icon :icon="$icon" class="w-4 h-4 {{ $color }}" />
                                    </div>
                                    <div class="text-sm font-bold {{ $color }} truncate" title="{{ $value }}">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>

                    {{-- Price Range --}}
                    @if(($priceRange['max'] ?? 0) > 0)
                        <x-filament::section>
                            <x-slot name="heading">Range de Preț (per bilet)</x-slot>
                            <div class="grid grid-cols-4 gap-3">
                                @foreach([
                                    ['Min', $priceRange['min'], 'text-blue-600'],
                                    ['Max', $priceRange['max'], 'text-red-600'],
                                    ['Medie', $priceRange['avg'], 'text-green-600'],
                                    ['Median', $priceRange['median'], 'text-purple-600'],
                                ] as [$prLabel, $prValue, $prColor])
                                    <div class="text-center">
                                        <span class="text-[10px] text-gray-500 uppercase tracking-wide">{{ $prLabel }}</span>
                                        <div class="text-sm font-bold {{ $prColor }}">{{ number_format($prValue, 2) }} RON</div>
                                    </div>
                                @endforeach
                            </div>
                        </x-filament::section>
                    @endif
                </div>

                {{-- RIGHT COLUMN: Personal Info + Tenant & System --}}
                <div>
                    <x-filament::section>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @php $mp = $marketplaceProfile; @endphp
                            @foreach([
                                'Nume' => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: '-',
                                'Email' => $record->email ?? '-',
                                'Telefon' => $record->phone ?? '-',
                                'Gen' => $mp ? match($mp->gender) { 'male' => 'Masculin', 'female' => 'Feminin', 'other' => 'Altul', default => '-' } : '-',
                                'Data nașterii' => $record->date_of_birth?->format('d.m.Y') ?? ($mp?->birth_date?->format('d.m.Y') ?? '-'),
                                'Vârstă' => $record->age ?? '-',
                                'Oraș' => $record->city ?? '-',
                                'Țară' => $record->country ?? '-',
                                'Adresă' => $mp?->address ?? '-',
                                'Limbă' => $mp ? match($mp->locale) { 'ro' => 'Română', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', default => $mp->locale ?? '-' } : '-',
                                'Status' => $mp?->status ?? '-',
                                'Email verificat' => $mp?->email_verified_at ? 'Da (' . $mp->email_verified_at->format('d.m.Y') . ')' : 'Nu',
                                'Accepts Marketing' => $mp ? ($mp->accepts_marketing ? 'Da' : 'Nu') : '-',
                                'Ultimul login' => $mp?->last_login_at?->format('d.m.Y H:i') ?? 'Niciodată',
                                'Cod referral' => $record->referral_code ?? '-',
                                'Tenant (creat)' => $record->tenant?->name ?? '-',
                                'Primary Tenant' => $record->primaryTenant?->name ?? '-',
                                'Creat la' => $record->created_at?->format('d.m.Y H:i') ?? '-',
                                'Actualizat la' => $record->updated_at?->format('d.m.Y H:i') ?? '-',
                            ] as $fieldLabel => $fieldValue)
                                @php $isEmpty = in_array($fieldValue, ['-', 'Nu', 'Niciodată']); @endphp
                                <div class="flex justify-between px-1 py-2">
                                    <span class="text-sm font-medium {{ $isEmpty ? 'text-red-500 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }}">{{ $fieldLabel }}</span>
                                    <span class="text-sm {{ $isEmpty ? 'text-red-400 dark:text-red-500' : 'text-gray-900 dark:text-gray-100' }}">{{ $fieldValue }}</span>
                                </div>
                            @endforeach

                            {{-- Profile completion --}}
                            <div class="px-1 py-2">
                                <div class="flex justify-between mb-1">
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Completare profil</span>
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $record->getProfileCompletionPercentage() }}%</span>
                                </div>
                                <div class="w-full h-2 bg-gray-200 rounded-full dark:bg-gray-700">
                                    <div class="h-2 transition-all rounded-full bg-primary-600" style="width: {{ $record->getProfileCompletionPercentage() }}%"></div>
                                </div>
                            </div>

                            {{-- Member of tenants --}}
                            <div class="px-1 py-2">
                                <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Membru în tenants</span>
                                <div class="flex flex-wrap gap-1 mt-1">
                                    @forelse($record->tenants as $t)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">{{ $t->name }}</span>
                                    @empty
                                        <span class="text-sm text-gray-400">-</span>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Meta --}}
                            @if(!empty($record->meta))
                                <div class="px-1 py-2">
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Metadata</span>
                                    <div class="mt-1 space-y-1">
                                        @foreach($record->meta as $key => $val)
                                            <div class="flex gap-2 text-xs">
                                                <span class="font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-gray-600 dark:text-gray-300">{{ $key }}</span>
                                                <span class="text-gray-700 dark:text-gray-300">{{ is_array($val) ? json_encode($val) : $val }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-filament::section>

                    {{-- Beneficiaries / Attendees --}}
                    @if(!empty($attendees))
                        <x-filament::section>
                            <x-slot name="heading">Beneficiari ({{ count($attendees) }})</x-slot>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Nume</th>
                                            <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Email</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach($attendees as $att)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $att->attendee_name }}</td>
                                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $att->attendee_email ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </x-filament::section>
                    @endif
                </div>
            </div>

            {{-- CoreCustomer Tracking / Attribution --}}
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">
                            <div class="flex items-center gap-2">
                                <x-filament::icon icon="heroicon-o-signal" class="w-5 h-5 text-indigo-500" />
                                Tracking & Atribuire (CoreCustomer)
                            </div>
                        </x-slot>
                    @if(!empty($trackingData))
                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                            {{-- Google --}}
                            <div class="p-3 space-y-2 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                                <div class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-blue-600 uppercase">
                                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-4 h-4" /> Google
                                </div>
                                @foreach([
                                    'GCLID (first)' => $trackingData['first_gclid'] ?? null,
                                    'GCLID (last)' => $trackingData['last_gclid'] ?? null,
                                    'Google User ID' => $trackingData['google_user_id'] ?? null,
                                ] as $tkLabel => $tkValue)
                                    @if($tkValue)
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">{{ $tkLabel }}</span>
                                            <span class="font-mono text-gray-700 dark:text-gray-300 truncate max-w-[180px]" title="{{ $tkValue }}">{{ $tkValue }}</span>
                                        </div>
                                    @endif
                                @endforeach
                                @if(!($trackingData['first_gclid'] ?? null) && !($trackingData['last_gclid'] ?? null) && !($trackingData['google_user_id'] ?? null))
                                    <span class="text-xs text-gray-400">Fără date</span>
                                @endif
                            </div>

                            {{-- Meta --}}
                            <div class="p-3 space-y-2 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                                <div class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-blue-500 uppercase">
                                    <x-filament::icon icon="heroicon-o-share" class="w-4 h-4" /> Meta (Facebook)
                                </div>
                                @foreach([
                                    'FBCLID (first)' => $trackingData['first_fbclid'] ?? null,
                                    'FBCLID (last)' => $trackingData['last_fbclid'] ?? null,
                                    'Facebook User ID' => $trackingData['facebook_user_id'] ?? null,
                                ] as $tkLabel => $tkValue)
                                    @if($tkValue)
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">{{ $tkLabel }}</span>
                                            <span class="font-mono text-gray-700 dark:text-gray-300 truncate max-w-[180px]" title="{{ $tkValue }}">{{ $tkValue }}</span>
                                        </div>
                                    @endif
                                @endforeach
                                @if(!($trackingData['first_fbclid'] ?? null) && !($trackingData['last_fbclid'] ?? null) && !($trackingData['facebook_user_id'] ?? null))
                                    <span class="text-xs text-gray-400">Fără date</span>
                                @endif
                            </div>

                            {{-- TikTok + LinkedIn --}}
                            <div class="p-3 space-y-2 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                                <div class="flex items-center gap-1.5 text-xs font-semibold tracking-wide text-gray-700 dark:text-gray-300 uppercase">
                                    <x-filament::icon icon="heroicon-o-play" class="w-4 h-4" /> TikTok / LinkedIn
                                </div>
                                @foreach([
                                    'TTCLID (first)' => $trackingData['first_ttclid'] ?? null,
                                    'TTCLID (last)' => $trackingData['last_ttclid'] ?? null,
                                    'LI FAT ID (first)' => $trackingData['first_li_fat_id'] ?? null,
                                    'LI FAT ID (last)' => $trackingData['last_li_fat_id'] ?? null,
                                ] as $tkLabel => $tkValue)
                                    @if($tkValue)
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">{{ $tkLabel }}</span>
                                            <span class="font-mono text-gray-700 dark:text-gray-300 truncate max-w-[180px]" title="{{ $tkValue }}">{{ $tkValue }}</span>
                                        </div>
                                    @endif
                                @endforeach
                                @if(!($trackingData['first_ttclid'] ?? null) && !($trackingData['last_ttclid'] ?? null) && !($trackingData['first_li_fat_id'] ?? null) && !($trackingData['last_li_fat_id'] ?? null))
                                    <span class="text-xs text-gray-400">Fără date</span>
                                @endif
                            </div>
                        </div>

                        {{-- UTM + Activity row --}}
                        <div class="grid grid-cols-1 gap-4 mt-4 lg:grid-cols-2">
                            {{-- UTM --}}
                            <div class="p-3 space-y-2 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                                <div class="text-xs font-semibold tracking-wide text-emerald-600 uppercase">UTM Atribuire</div>
                                @foreach([
                                    'First: source' => $trackingData['first_utm_source'] ?? null,
                                    'First: medium' => $trackingData['first_utm_medium'] ?? null,
                                    'First: campaign' => $trackingData['first_utm_campaign'] ?? null,
                                    'Last: source' => $trackingData['last_utm_source'] ?? null,
                                    'Last: medium' => $trackingData['last_utm_medium'] ?? null,
                                    'Last: campaign' => $trackingData['last_utm_campaign'] ?? null,
                                ] as $tkLabel => $tkValue)
                                    @if($tkValue)
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">{{ $tkLabel }}</span>
                                            <span class="font-mono text-gray-700 dark:text-gray-300">{{ $tkValue }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>

                            {{-- Activity --}}
                            <div class="p-3 space-y-2 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                                <div class="text-xs font-semibold tracking-wide text-amber-600 uppercase">Activitate & Dispozitiv</div>
                                @foreach([
                                    'Segment' => $trackingData['segment'] ?? null,
                                    'RFM Segment' => $trackingData['rfm_segment'] ?? null,
                                    'Health Score' => $trackingData['health_score'] ?? null,
                                    'Vizite totale' => $trackingData['total_visits'] ?? null,
                                    'Sesiuni' => $trackingData['total_sessions'] ?? null,
                                    'Pageviews' => $trackingData['total_pageviews'] ?? null,
                                    'First seen' => $trackingData['first_seen_at'] ?? null,
                                    'Last seen' => $trackingData['last_seen_at'] ?? null,
                                    'Dispozitiv' => $trackingData['primary_device'] ?? null,
                                    'Browser' => $trackingData['primary_browser'] ?? null,
                                    'Stripe ID' => $trackingData['stripe_customer_id'] ?? null,
                                    'Visitor ID' => $trackingData['visitor_id'] ?? null,
                                ] as $tkLabel => $tkValue)
                                    @if($tkValue)
                                        <div class="flex justify-between text-xs">
                                            <span class="text-gray-500">{{ $tkLabel }}</span>
                                            <span class="font-mono text-gray-700 dark:text-gray-300 truncate max-w-[200px]" title="{{ $tkValue }}">{{ $tkValue }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="p-4 text-sm text-center text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-information-circle" class="inline w-5 h-5 mr-1 text-gray-400" />
                            Nu a fost găsit un CoreCustomer asociat cu email-ul <strong>{{ $record->email }}</strong>.
                            Datele de tracking vor apărea automat când clientul este identificat prin CoreCustomer.
                        </div>
                    @endif
                    </x-filament::section>
                </div>

            {{-- Orders by Month Chart --}}
            @if(!empty($monthlyChart['labels']))
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Comenzi pe luni - {{ $monthlyChart['year'] }}</x-slot>
                        <div style="position:relative; height:280px;">
                            <canvas id="profileMonthlyChart"></canvas>
                        </div>
                    </x-filament::section>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 2: GAMIFICATION
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'gamification'" x-cloak>
            @php
                $pts = $gamification['points'] ?? null;
                $xp = $gamification['experience'] ?? null;
                $transactions = $gamification['transactions'] ?? collect();
                $badges = $gamification['badges'] ?? collect();
                $redemptions = $gamification['redemptions'] ?? collect();
            @endphp

            {{-- Points & XP Cards --}}
            <div class="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-3 lg:grid-cols-6">
                @foreach([
                    ['Sold Puncte', number_format($pts?->current_balance ?? 0), 'heroicon-o-star', 'text-amber-600'],
                    ['Total Câștigate', '+' . number_format($pts?->total_earned ?? 0), 'heroicon-o-arrow-trending-up', 'text-green-600'],
                    ['Total Cheltuite', '-' . number_format($pts?->total_spent ?? 0), 'heroicon-o-arrow-trending-down', 'text-red-600'],
                    ['Tier', $pts?->current_tier ?? 'N/A', 'heroicon-o-trophy', 'text-purple-600'],
                    ['XP Total', number_format($xp?->total_xp ?? 0), 'heroicon-o-bolt', 'text-cyan-600'],
                    ['Level', $xp?->current_level ?? 0, 'heroicon-o-signal', 'text-rose-600'],
                ] as [$label, $value, $icon, $color])
                    <div class="p-4 bg-white shadow-sm rounded-xl dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="flex items-center gap-2 mb-1">
                            <x-filament::icon :icon="$icon" class="w-5 h-5 {{ $color }}" />
                            <span class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $label }}</span>
                        </div>
                        <div class="text-xl font-bold {{ $color }}">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            {{-- XP Progress --}}
            @if($xp)
                <div class="mb-6">
                    <x-filament::section>
                        <x-slot name="heading">Progres Level</x-slot>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Level {{ $xp->current_level }} → {{ $xp->current_level + 1 }}</span>
                            <span class="text-sm font-semibold">{{ $xp->xp_in_current_level ?? 0 }} / {{ $xp->xp_to_next_level ?? '?' }} XP</span>
                        </div>
                        <div class="w-full h-3 bg-gray-200 rounded-full dark:bg-gray-700">
                            @php $progress = ($xp->xp_to_next_level > 0) ? min(100, ($xp->xp_in_current_level / $xp->xp_to_next_level) * 100) : 0; @endphp
                            <div class="h-3 transition-all rounded-full bg-cyan-600" style="width: {{ $progress }}%"></div>
                        </div>
                        @if($xp->current_level_group)
                            <div class="mt-1 text-xs text-gray-500">Grup: <span class="font-medium">{{ $xp->current_level_group }}</span></div>
                        @endif
                    </x-filament::section>
                </div>
            @endif

            {{-- Badges --}}
            @if($badges->isNotEmpty())
                <div class="mb-6">
                    <x-filament::section>
                        <x-slot name="heading">Badge-uri Câștigate ({{ $badges->count() }})</x-slot>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach($badges as $cb)
                                <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                                    @if($cb->badge?->icon_url)
                                        <img src="{{ $cb->badge->icon_url }}" class="w-10 h-10 rounded" alt="">
                                    @else
                                        <div class="flex items-center justify-center w-10 h-10 text-lg rounded" style="background: {{ $cb->badge?->color ?? '#e5e7eb' }}">🏅</div>
                                    @endif
                                    <div class="min-w-0">
                                        @php
                                            $badgeName = $cb->badge?->name;
                                            if (is_array($badgeName)) $badgeName = $badgeName['ro'] ?? $badgeName['en'] ?? reset($badgeName);
                                        @endphp
                                        <div class="text-sm font-medium text-gray-900 truncate dark:text-gray-100">{{ $badgeName ?? 'Badge' }}</div>
                                        <div class="text-xs text-gray-500">{{ $cb->earned_at?->format('d.m.Y') ?? '' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                </div>
            @endif

            {{-- Points Transactions --}}
            <x-filament::section>
                <x-slot name="heading">Istoric Puncte (ultimele 20)</x-slot>
                @if($transactions->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Data</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Tip</th>
                                    <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Puncte</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Descriere</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($transactions as $tx)
                                    @php
                                        $txDesc = $tx->description;
                                        if (is_array($txDesc)) $txDesc = $txDesc['ro'] ?? $txDesc['en'] ?? reset($txDesc) ?? '-';
                                        $txDesc = $txDesc ?: ($tx->admin_note ?? '-');
                                        $typeBadge = match($tx->type) {
                                            'earned' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                            'spent' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                            'expired' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                                            'adjusted' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                            'refunded' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                        $typeLabel = match($tx->type) {
                                            'earned' => 'Câștigate', 'spent' => 'Cheltuite', 'expired' => 'Expirate',
                                            'adjusted' => 'Ajustare', 'refunded' => 'Returnat', default => ucfirst($tx->type),
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tx->created_at?->format('d.m.Y H:i') }}</td>
                                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-medium {{ $typeBadge }}">{{ $typeLabel }}</span></td>
                                        <td class="px-3 py-2 text-right font-semibold {{ $tx->points >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $tx->points >= 0 ? '+' : '' }}{{ number_format($tx->points) }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $txDesc }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nu există tranzacții de puncte.</p>
                @endif
            </x-filament::section>

            {{-- Reward Redemptions --}}
            @if($redemptions->isNotEmpty())
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Recompense Revendicate</x-slot>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Recompensă</th>
                                        <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Puncte</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Voucher</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Status</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($redemptions as $rd)
                                        @php
                                            $rwName = $rd->reward?->name;
                                            if (is_array($rwName)) $rwName = $rwName['ro'] ?? $rwName['en'] ?? reset($rwName);
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $rwName ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 font-semibold text-right text-red-600">-{{ number_format($rd->points_spent ?? 0) }}</td>
                                            <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $rd->voucher_code ?? '-' }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded text-xs font-medium
                                                    {{ match($rd->status) {
                                                        'active' => 'bg-green-100 text-green-700',
                                                        'used' => 'bg-blue-100 text-blue-700',
                                                        'expired' => 'bg-orange-100 text-orange-700',
                                                        'cancelled' => 'bg-red-100 text-red-700',
                                                        default => 'bg-gray-100 text-gray-700',
                                                    } }}">{{ ucfirst($rd->status) }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $rd->created_at?->format('d.m.Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-filament::section>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 3: COMENZI & BILETE
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'orders'" x-cloak>

            {{-- Orders Table --}}
            <x-filament::section>
                <x-slot name="heading">Comenzi ({{ count($ordersList) }})</x-slot>
                @if(!empty($ordersList))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">#</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Eveniment</th>
                                    <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Total</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Status</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($ordersList as $ord)
                                    <tr class="cursor-pointer hover:bg-white/20 dark:hover:bg-white/20 group" onclick="window.location='{{ route('filament.admin.resources.orders.edit', ['record' => $ord->id]) }}'">
                                        <td class="px-3 py-2 font-mono text-xs">
                                            <a href="{{ route('filament.admin.resources.orders.edit', ['record' => $ord->id]) }}" class="text-primary-600 hover:underline">{{ $ord->order_number ?? '#' . str_pad($ord->id, 6, '0', STR_PAD_LEFT) }}</a>
                                        </td>
                                        <td class="max-w-xs px-3 py-2 text-gray-800 truncate dark:text-gray-200 group-hover:text-slate-400">{{ $ord->event_title }}</td>
                                        <td class="px-3 py-2 font-semibold text-right text-gray-800 dark:text-gray-200 group-hover:text-slate-400">{{ number_format(($ord->total_cents ?? 0) / 100, 2) }} {{ $ord->currency ?? 'RON' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                                {{ match($ord->status) {
                                                    'paid', 'confirmed', 'completed' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                                    'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                                    'cancelled', 'expired' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                                    'refunded' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                                    default => 'bg-gray-100 text-gray-700',
                                                } }}">{{ ucfirst($ord->status) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($ord->created_at)->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nu există comenzi.</p>
                @endif
            </x-filament::section>

            {{-- Tickets Table --}}
            <div class="mt-6">
                <x-filament::section>
                    <x-slot name="heading">Bilete ({{ count($ticketsList) }})</x-slot>
                    @if(!empty($ticketsList))
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Cod</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Eveniment</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Tip Bilet</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Participant</th>
                                        <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Preț</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Payment</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Loc</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Status</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Check-in</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($ticketsList as $tkt)
                                        <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50" onclick="window.location='{{ route('filament.admin.resources.tickets.index') }}?tableSearch={{ urlencode($tkt->code ?? '') }}'">
                                            <td class="px-3 py-2 font-mono text-xs">
                                                <a href="{{ route('filament.admin.resources.tickets.index') }}?tableSearch={{ urlencode($tkt->code ?? '') }}" class="text-primary-600 hover:underline">{{ $tkt->code ?? '-' }}</a>
                                            </td>
                                            <td class="max-w-xs px-3 py-2 text-gray-800 truncate dark:text-gray-200">{{ $tkt->event_title }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tkt->ticket_type_name ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tkt->attendee_name ?? '-' }}</td>
                                            <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">{{ $tkt->price ? number_format($tkt->price, 2) . ' RON' : '-' }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                                @if($tkt->payment_processor)
                                                    <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ ucfirst($tkt->payment_processor) }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tkt->seat_label ?? '-' }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded text-xs font-medium
                                                    {{ match($tkt->status) {
                                                        'valid' => 'bg-green-100 text-green-700',
                                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                                        'cancelled' => 'bg-red-100 text-red-700',
                                                        default => 'bg-gray-100 text-gray-700',
                                                    } }}">{{ ucfirst($tkt->status ?? 'N/A') }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tkt->checked_in_at ? \Carbon\Carbon::parse($tkt->checked_in_at)->format('d.m.Y H:i') : '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Nu există bilete.</p>
                    @endif
                </x-filament::section>
            </div>

            {{-- Tenants --}}
            @if(!empty($tenantsList))
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Cumpărături per Tenant</x-slot>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Tenant</th>
                                        <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Comenzi</th>
                                        <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Valoare (RON)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($tenantsList as $tn)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $tn->name }}</td>
                                            <td class="px-3 py-2 text-right">{{ $tn->cnt }}</td>
                                            <td class="px-3 py-2 text-right">{{ number_format(($tn->total ?? 0) / 100, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-filament::section>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 4: ISTORIC EMAIL-URI
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'emails'" x-cloak>
            <x-filament::section>
                <x-slot name="heading">Istoric Email-uri ({{ count($emailLogs) }})</x-slot>
                @if(!empty($emailLogs))
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Subiect</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Template</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Status</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Trimis la</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Creat la</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($emailLogs as $log)
                                    <tr>
                                        <td class="max-w-xs px-3 py-2 text-gray-800 truncate dark:text-gray-200">{{ $log->subject }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $log->template_name ?? '-' }}</td>
                                        <td class="px-3 py-2">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                                {{ match($log->status) {
                                                    'sent' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                                    'pending' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                                    'failed' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                                    default => 'bg-gray-100 text-gray-700',
                                                } }}">{{ ucfirst($log->status) }}</span>
                                        </td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $log->sent_at ? \Carbon\Carbon::parse($log->sent_at)->format('d.m.Y H:i') : '-' }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ \Carbon\Carbon::parse($log->created_at)->format('d.m.Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nu s-au trimis email-uri către acest client.</p>
                @endif
            </x-filament::section>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 5: CUSTOMER INSIGHTS
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'insights'" x-cloak>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-filament::section>
                    <x-slot name="heading">Tipuri Eveniment</x-slot>
                    @if(!empty($eventTypes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($eventTypes as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Genuri Eveniment</x-slot>
                    @if(!empty($eventGenres))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($eventGenres as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Tag-uri Eveniment</x-slot>
                    @if(!empty($eventTags))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($eventTags as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">
                <x-filament::section>
                    <x-slot name="heading">Tipuri Locație</x-slot>
                    @if(!empty($venueTypes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($venueTypes as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Genuri Muzicale (Artiști)</x-slot>
                    @if(!empty($artistGenres))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($artistGenres as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Top Artiști</x-slot>
                    @if(!empty($topArtists))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($topArtists as $a)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $a->name }}</span>
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">{{ $a->cnt }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-3">
                <x-filament::section>
                    <x-slot name="heading">Top 3 Zile Preferate</x-slot>
                    @if(!empty($preferredDays))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredDays as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Orașe Preferate</x-slot>
                    @if(!empty($preferredCities))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredCities as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Ora Start Preferată</x-slot>
                    @if(!empty($preferredStartTimes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredStartTimes as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            <div class="grid grid-cols-1 gap-6 mt-6 lg:grid-cols-2">
                <x-filament::section>
                    <x-slot name="heading">Luni Preferate ale Anului</x-slot>
                    @if(!empty($preferredMonths))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredMonths as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Perioadă Lunară Preferată</x-slot>
                    @if(!empty($preferredMonthPeriods))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredMonthPeriods as $item)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                    <span class="text-xs text-gray-500">({{ $item['count'] }}) <span class="font-semibold text-primary-600">{{ $item['percentage'] }}%</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm text-gray-500">Fără date.</p>
                    @endif
                </x-filament::section>
            </div>

            @if(!empty($recentEvents))
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Evenimente Recente</x-slot>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($recentEvents as $ev)
                                <li class="flex items-center justify-between px-1 py-2">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $ev->title }}</span>
                                    <a href="{{ route('filament.admin.resources.events.edit', ['record' => $ev->id]) }}" class="text-xs text-primary-600 hover:underline">Deschide</a>
                                </li>
                            @endforeach
                        </ul>
                    </x-filament::section>
                </div>
            @endif
        </div>

    </div>

    {{-- Chart.js for Monthly Orders --}}
    @if(!empty($monthlyChart['labels']))
        @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('profileMonthlyChart');
                if (!ctx) return;
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: @json($monthlyChart['labels']),
                        datasets: [
                            {
                                label: 'Comenzi',
                                data: @json($monthlyChart['counts']),
                                backgroundColor: 'rgba(59, 130, 246, 0.5)',
                                borderColor: 'rgb(59, 130, 246)',
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: 'Venit (RON)',
                                data: @json($monthlyChart['revenues']),
                                type: 'line',
                                borderColor: 'rgb(34, 197, 94)',
                                backgroundColor: 'rgba(34, 197, 94, 0.1)',
                                tension: 0.3,
                                fill: true,
                                yAxisID: 'y1',
                                order: 1,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Comenzi' } },
                            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'RON' } },
                        }
                    }
                });
            });
        </script>
        @endpush
    @endif
</x-filament-panels::page>
