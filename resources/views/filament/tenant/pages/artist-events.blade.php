<x-filament-panels::page>
    @if(!$artist)
        <div class="fi-section rounded-xl bg-white dark:bg-gray-900 p-6 text-center">
            <p class="text-gray-500 dark:text-gray-400">No artist profile linked to this tenant.</p>
        </div>
    @else
        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 p-4 text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Total Events</p>
            </div>
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 p-4 text-center">
                <p class="text-2xl font-bold text-emerald-500">{{ $stats['upcoming'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Live</p>
            </div>
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 p-4 text-center">
                <p class="text-2xl font-bold text-gray-400">{{ $stats['past'] }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Ended</p>
            </div>
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 p-4 text-center">
                <p class="text-2xl font-bold text-blue-400">{{ number_format($stats['total_sold']) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Tickets Sold</p>
            </div>
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 p-4 text-center">
                <p class="text-2xl font-bold text-amber-400">{{ number_format($stats['total_revenue'], 2) }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Total Revenue</p>
            </div>
        </div>

        {{-- Status Filter --}}
        <div class="flex items-center gap-2 mb-4">
            @php
                $filters = [
                    'all' => ['label' => 'All', 'count' => $stats['total']],
                    'live' => ['label' => 'Live', 'count' => $stats['upcoming']],
                    'ended' => ['label' => 'Ended', 'count' => $stats['past']],
                    'cancelled' => ['label' => 'Cancelled', 'count' => $stats['cancelled'] ?? 0],
                    'postponed' => ['label' => 'Postponed', 'count' => $stats['postponed'] ?? 0],
                    'unknown' => ['label' => 'Unknown', 'count' => $stats['unknown'] ?? 0],
                ];
            @endphp
            @foreach($filters as $key => $filter)
                @if($filter['count'] > 0 || $key === 'all')
                    <button
                        wire:click="$set('statusFilter', '{{ $key }}')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition
                            {{ $statusFilter === $key
                                ? 'bg-primary-500 text-white'
                                : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-gray-200' }}"
                    >
                        {{ $filter['label'] }}
                        @if($filter['count'] > 0)
                            <span class="ml-1 opacity-60">{{ $filter['count'] }}</span>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Core Events --}}
        @if($events->isNotEmpty())
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 overflow-hidden">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($events as $event)
                        @php
                            $title = is_array($event->title) ? ($event->title[app()->getLocale()] ?? $event->title['en'] ?? $event->title['ro'] ?? array_values($event->title)[0] ?? '—') : ($event->title ?? '—');
                            $isUpcoming = $event->computed_status === 'live';
                            $ts = $event->ticket_stats;
                            $eventUrl = $event->public_url;

                            // Venue + City
                            $venueName = '';
                            $venueCity = '';
                            if ($event->venue) {
                                $venueName = is_array($event->venue->name) ? ($event->venue->name[app()->getLocale()] ?? $event->venue->name['en'] ?? array_values($event->venue->name)[0] ?? '') : $event->venue->name;
                                $venueCity = $event->venue->city ?? '';
                            }

                            // Organizer
                            $orgName = $event->tenant?->public_name ?? $event->tenant?->name ?? '';
                            $orgCompany = $event->tenant?->company_name ?? '';
                        @endphp
                        <div class="px-4 py-3 flex items-center gap-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                            {{-- Date --}}
                            <div class="flex-shrink-0 w-14 text-center">
                                @if($event->event_date)
                                    <div class="text-xs font-medium text-gray-400 uppercase">{{ \Carbon\Carbon::parse($event->event_date)->format('M') }}</div>
                                    <div class="text-xl font-bold {{ $isUpcoming ? 'text-emerald-500' : 'text-gray-400' }}">{{ \Carbon\Carbon::parse($event->event_date)->format('d') }}</div>
                                    <div class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($event->event_date)->format('Y') }}</div>
                                    @if($event->days_until !== null)
                                        <div class="text-[10px] font-semibold mt-0.5 {{ $event->days_until <= 7 ? 'text-red-400' : ($event->days_until <= 30 ? 'text-amber-400' : 'text-gray-500') }}">{{ $event->days_until }}d</div>
                                    @endif
                                @else
                                    <div class="text-xs text-gray-500 mt-2">TBD</div>
                                @endif
                            </div>

                            {{-- Event Info --}}
                            <div class="flex-1 min-w-0">
                                @if($eventUrl)
                                    <a href="{{ $eventUrl }}" target="_blank" class="font-semibold text-sm text-primary-400 hover:text-primary-300 truncate block transition">{{ $title }}</a>
                                @else
                                    <div class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ $title }}</div>
                                @endif
                                <div class="flex items-center gap-2 mt-1 text-xs text-gray-500 dark:text-gray-400 flex-wrap">
                                    @if($venueName)
                                        <span class="text-gray-300">{{ $venueName }}@if($venueCity), {{ $venueCity }}@endif</span>
                                    @endif
                                    @if($orgName)
                                        <span class="text-gray-500">{{ $orgName }}@if($orgCompany && $orgCompany !== $orgName) <span class="text-gray-600">({{ $orgCompany }})</span>@endif</span>
                                    @endif
                                    @if($event->is_cancelled)
                                        <span class="px-1.5 py-0.5 rounded bg-red-500/10 text-red-400 text-[10px] font-semibold">CANCELLED</span>
                                    @elseif($event->is_postponed)
                                        <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-400 text-[10px] font-semibold">POSTPONED</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Ticket Sales --}}
                            <div class="flex-shrink-0 text-right">
                                <div class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ $ts['sold'] }} / {{ $ts['capacity'] ?: '—' }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    @if($ts['fill_rate'] > 0)
                                        <span class="{{ $ts['fill_rate'] >= 80 ? 'text-emerald-400' : ($ts['fill_rate'] >= 50 ? 'text-blue-400' : 'text-gray-400') }}">{{ $ts['fill_rate'] }}% sold</span>
                                    @else
                                        <span class="text-gray-400">No sales</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Revenue --}}
                            <div class="flex-shrink-0 w-24 text-right">
                                @if($ts['revenue'] > 0)
                                    <div class="text-sm font-semibold text-emerald-400">{{ number_format($ts['revenue'], 2) }}</div>
                                    <div class="text-xs text-gray-500">RON</div>
                                @else
                                    <div class="text-xs text-gray-400">—</div>
                                @endif
                            </div>

                            {{-- Action buttons --}}
                            @if($eventUrl)
                                <div class="flex-shrink-0 flex items-center gap-1.5">
                                    {{-- Copy link --}}
                                    <button onclick="navigator.clipboard.writeText('{{ $eventUrl }}').then(() => { this.querySelector('.cp-ok').classList.remove('hidden'); setTimeout(() => this.querySelector('.cp-ok').classList.add('hidden'), 1500); })"
                                            class="p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 transition" title="Copy link">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m9.86-2.54a4.5 4.5 0 00-6.364-6.364L4.5 8.25"/></svg>
                                        <svg class="w-4 h-4 text-emerald-400 hidden cp-ok absolute" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                    {{-- Tickets --}}
                                    <a href="{{ $eventUrl }}" target="_blank"
                                       style="background: linear-gradient(135deg, #6366f1, #818cf8); color: white;"
                                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-xs font-bold shadow-lg hover:opacity-90 transition-all duration-200">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                        Tickets
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Marketplace Events --}}
        @if($marketplaceEvents->isNotEmpty())
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 overflow-hidden mt-4">
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Marketplace Events ({{ $marketplaceEvents->count() }})</h3>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($marketplaceEvents as $mpEvent)
                        @php
                            $mpTitle = is_array($mpEvent->title) ? ($mpEvent->title[app()->getLocale()] ?? $mpEvent->title['en'] ?? array_values($mpEvent->title)[0] ?? '—') : ($mpEvent->title ?? '—');
                            $mpSlug = is_array($mpEvent->slug) ? ($mpEvent->slug[app()->getLocale()] ?? $mpEvent->slug['en'] ?? '') : ($mpEvent->slug ?? '');
                            $mpDomain = $mpEvent->marketplaceClient?->domain ?? null;
                            $mpUrl = ($mpDomain && $mpSlug) ? 'https://' . preg_replace('#^https?://#', '', $mpDomain) . '/bilete/' . $mpSlug : null;
                        @endphp
                        <div class="px-4 py-3 flex items-center gap-4">
                            <div class="flex-shrink-0 w-14 text-center">
                                @if($mpEvent->starts_at)
                                    <div class="text-xs font-medium text-gray-400 uppercase">{{ $mpEvent->starts_at->format('M') }}</div>
                                    <div class="text-xl font-bold text-gray-400">{{ $mpEvent->starts_at->format('d') }}</div>
                                    <div class="text-xs text-gray-500">{{ $mpEvent->starts_at->format('Y') }}</div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                @if($mpUrl)
                                    <a href="{{ $mpUrl }}" target="_blank" class="font-semibold text-sm text-primary-400 hover:text-primary-300 truncate block transition">{{ $mpTitle }}</a>
                                @else
                                    <div class="font-semibold text-sm text-gray-900 dark:text-white truncate">{{ $mpTitle }}</div>
                                @endif
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 text-[10px] font-semibold">MARKETPLACE</span>
                                </div>
                            </div>
                            @if($mpUrl)
                                <div class="flex-shrink-0">
                                    <a href="{{ $mpUrl }}" target="_blank"
                                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg
                                              bg-gradient-to-r from-primary-600 to-primary-500
                                              hover:from-primary-500 hover:to-primary-400
                                              text-white text-xs font-bold
                                              shadow-md shadow-primary-500/20 hover:shadow-lg hover:shadow-primary-500/30
                                              transition-all duration-200 hover:-translate-y-0.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                                        Tickets
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if($events->isEmpty() && $marketplaceEvents->isEmpty())
            <div class="fi-section rounded-xl bg-white dark:bg-gray-900 p-8 text-center">
                <svg class="w-12 h-12 mx-auto text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                <p class="text-gray-500 dark:text-gray-400">No events found for this filter.</p>
            </div>
        @endif
    @endif
</x-filament-panels::page>
