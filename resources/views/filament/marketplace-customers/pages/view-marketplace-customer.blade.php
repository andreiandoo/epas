<x-filament-panels::page>

    {{-- TAB NAVIGATION --}}
    <div x-data="{ activeTab: 'overview' }" class="space-y-6">
        {{-- Tab Buttons --}}
        <div class="flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-700 pb-1">
            @foreach([
                'overview' => ['Prezentare Generală', 'heroicon-o-user-circle'],
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

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                {{-- LEFT COLUMN: Stat Cards (2-col grid) --}}
                <div class="space-y-6">
                    <x-filament::section>
                        <x-slot name="heading">Statistici Rapide</x-slot>
                        <div class="grid grid-cols-2 gap-3">
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
                                <div class="rounded-xl bg-white dark:bg-gray-900 p-3 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                                    <div class="flex items-center gap-1.5 mb-0.5">
                                        <x-filament::icon :icon="$icon" class="w-4 h-4 {{ $color }}" />
                                        <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">{{ $label }}</span>
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

                    {{-- Beneficiaries / Attendees --}}
                    @if(!empty($attendees))
                        <x-filament::section>
                            <x-slot name="heading">Beneficiari ({{ count($attendees) }})</x-slot>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Nume</th>
                                            <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Email</th>
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

                {{-- RIGHT COLUMN: Personal Info --}}
                <div>
                    <x-filament::section>
                        <x-slot name="heading">Informații Client</x-slot>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach([
                                'Nume' => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: '-',
                                'Email' => $record->email ?? '-',
                                'Telefon' => $record->phone ?? '-',
                                'Gen' => match($record->gender) { 'male' => 'Masculin', 'female' => 'Feminin', 'other' => 'Altul', default => '-' },
                                'Data nașterii' => $record->birth_date?->format('d.m.Y') ?? '-',
                                'Oraș' => $record->city ?? '-',
                                'Țară' => $record->country ?? '-',
                                'Adresă' => $record->address ?? '-',
                                'Limbă' => match($record->locale) { 'ro' => 'Română', 'en' => 'English', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español', default => $record->locale ?? '-' },
                                'Status' => $record->status ?? '-',
                                'Email verificat' => $record->email_verified_at ? 'Da (' . $record->email_verified_at->format('d.m.Y') . ')' : 'Nu',
                                'Accepts Marketing' => $record->accepts_marketing ? 'Da' : 'Nu',
                                'Ultimul login' => $record->last_login_at?->format('d.m.Y H:i') ?? 'Niciodată',
                                'Creat la' => $record->created_at?->format('d.m.Y H:i') ?? '-',
                                'Actualizat la' => $record->updated_at?->format('d.m.Y H:i') ?? '-',
                            ] as $fieldLabel => $fieldValue)
                                <div class="flex justify-between py-2 px-1">
                                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $fieldLabel }}</span>
                                    <span class="text-sm text-gray-900 dark:text-gray-100">{{ $fieldValue }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                </div>
            </div>

            {{-- Orders by Month Chart --}}
            @if(!empty($monthlyChart['labels']))
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Comenzi pe luni - {{ $monthlyChart['year'] }}</x-slot>
                        <div style="position:relative; height:280px;">
                            <canvas id="mpProfileMonthlyChart"></canvas>
                        </div>
                    </x-filament::section>
                </div>
            @endif
        </div>

        {{-- ═══════════════════════════════════════════════════════════════
             TAB 2: COMENZI & BILETE
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'orders'" x-cloak>

            {{-- Orders Table --}}
            <x-filament::section>
                <x-slot name="heading">Comenzi ({{ count($ordersList) }})</x-slot>
                @if(!empty($ordersList))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">#</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Eveniment</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Total</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Data</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($ordersList as $ord)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer" onclick="window.location='{{ route('filament.admin.resources.orders.edit', ['record' => $ord->id]) }}'">
                                        <td class="px-3 py-2 font-mono text-xs">
                                            <a href="{{ route('filament.admin.resources.orders.edit', ['record' => $ord->id]) }}" class="text-primary-600 hover:underline">{{ $ord->order_number ?? '#' . str_pad($ord->id, 6, '0', STR_PAD_LEFT) }}</a>
                                        </td>
                                        <td class="px-3 py-2 text-gray-800 dark:text-gray-200 max-w-xs truncate">{{ $ord->event_title }}</td>
                                        <td class="px-3 py-2 text-right font-semibold text-gray-800 dark:text-gray-200">{{ number_format(($ord->total_cents ?? 0) / 100, 2) }} {{ $ord->currency ?? 'RON' }}</td>
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
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Cod</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Eveniment</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Tip Bilet</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Participant</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Preț</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Loc</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Check-in</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($ticketsList as $tkt)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer" onclick="window.location='{{ route('filament.admin.resources.tickets.index') }}?tableSearch={{ urlencode($tkt->code ?? '') }}'">
                                            <td class="px-3 py-2 font-mono text-xs">
                                                <a href="{{ route('filament.admin.resources.tickets.index') }}?tableSearch={{ urlencode($tkt->code ?? '') }}" class="text-primary-600 hover:underline">{{ $tkt->code ?? '-' }}</a>
                                            </td>
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200 max-w-xs truncate">{{ $tkt->event_title }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tkt->ticket_type_name ?? '-' }}</td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tkt->attendee_name ?? '-' }}</td>
                                            <td class="px-3 py-2 text-right text-gray-800 dark:text-gray-200">{{ $tkt->price ? number_format($tkt->price, 2) . ' RON' : '-' }}</td>
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
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Tenant</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Comenzi</th>
                                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Valoare (RON)</th>
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
             TAB 3: ISTORIC EMAIL-URI
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'emails'" x-cloak>
            <x-filament::section>
                <x-slot name="heading">Istoric Email-uri ({{ count($emailLogs) }})</x-slot>
                @if(!empty($emailLogs))
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Subiect</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Template</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Trimis la</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Creat la</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($emailLogs as $log)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-800 dark:text-gray-200 max-w-xs truncate">{{ $log->subject }}</td>
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
             TAB 4: CUSTOMER INSIGHTS
             ═══════════════════════════════════════════════════════════════ --}}
        <div x-show="activeTab === 'insights'" x-cloak>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-filament::section>
                    <x-slot name="heading">Tipuri Eveniment</x-slot>
                    @if(!empty($eventTypes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($eventTypes as $item)
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
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

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mt-6">
                <x-filament::section>
                    <x-slot name="heading">Tipuri Locație</x-slot>
                    @if(!empty($venueTypes))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($venueTypes as $item)
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
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

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mt-6">
                <x-filament::section>
                    <x-slot name="heading">Top 3 Zile Preferate</x-slot>
                    @if(!empty($preferredDays))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredDays as $item)
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
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

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mt-6">
                <x-filament::section>
                    <x-slot name="heading">Luni Preferate ale Anului</x-slot>
                    @if(!empty($preferredMonths))
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($preferredMonths as $item)
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
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
                                <li class="flex items-center justify-between py-2 px-1">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ $ev->title }}</span>
                                    <a href="{{ route('filament.admin.resources.events.edit', ['record' => $ev->id]) }}" class="text-primary-600 hover:underline text-xs">Deschide</a>
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
                const ctx = document.getElementById('mpProfileMonthlyChart');
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
