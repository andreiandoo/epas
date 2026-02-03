<x-filament-panels::page>
    @php
        $eventData = $this->getEventData();
        $organizerData = $this->getOrganizerData();
        $venueData = $this->getVenueData();
        $ticketTypesData = $this->getTicketTypesData();
        $salesStats = $this->getSalesStats();
        $recentOrders = $this->getRecentOrders();
        $artists = $this->getArtists();
        $taxonomies = $this->getTaxonomies();
    @endphp

    <div class="space-y-6">
        {{-- Event Header with Image --}}
        <div class="relative overflow-hidden rounded-xl bg-gray-900">
            @if($eventData['hero_image_url'] || $eventData['poster_url'])
                <img
                    src="{{ $eventData['hero_image_url'] ?? $eventData['poster_url'] }}"
                    alt="{{ $eventData['title'] }}"
                    class="object-cover w-full h-64 opacity-50"
                >
            @else
                <div class="w-full h-64 bg-gradient-to-r from-primary-600 to-primary-800"></div>
            @endif
            <div class="absolute inset-0 flex items-end">
                <div class="w-full p-6 bg-gradient-to-t from-gray-900 to-transparent">
                    <h1 class="text-3xl font-bold text-white">{{ $eventData['title'] }}</h1>
                    @if($eventData['subtitle'])
                        <p class="mt-1 text-lg text-gray-300">{{ $eventData['subtitle'] }}</p>
                    @endif

                    {{-- Status Badges --}}
                    <div class="flex flex-wrap gap-2 mt-3">
                        @if($eventData['is_cancelled'])
                            <span class="px-3 py-1 text-sm font-medium text-red-200 rounded-full bg-red-500/30">
                                Cancelled
                            </span>
                        @endif
                        @if($eventData['is_postponed'])
                            <span class="px-3 py-1 text-sm font-medium text-yellow-200 rounded-full bg-yellow-500/30">
                                Postponed
                            </span>
                        @endif
                        @if($eventData['is_sold_out'])
                            <span class="px-3 py-1 text-sm font-medium text-purple-200 rounded-full bg-purple-500/30">
                                Sold Out
                            </span>
                        @endif
                        @if($eventData['is_promoted'])
                            <span class="px-3 py-1 text-sm font-medium text-green-200 rounded-full bg-green-500/30">
                                Promoted
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-primary-600">{{ number_format($salesStats['tickets_sold']) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Tickets Sold</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-green-600">{{ number_format($salesStats['revenue'], 2) }} RON</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Total Revenue</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-blue-600">{{ $salesStats['occupancy'] }}%</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Occupancy</div>
            </div>
            <div class="p-4 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="text-2xl font-bold text-purple-600">{{ number_format($salesStats['unique_customers']) }}</div>
                <div class="text-sm text-gray-500 dark:text-gray-400">Unique Customers</div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            {{-- Main Content --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Event Details --}}
                <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Event Details</h2>

                    <dl class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Date</dt>
                            <dd class="text-gray-900 dark:text-white">
                                @if($eventData['duration_mode'] === 'single_day')
                                    {{ $eventData['event_date'] ? \Carbon\Carbon::parse($eventData['event_date'])->format('d M Y') : 'TBD' }}
                                @elseif($eventData['duration_mode'] === 'range')
                                    {{ $eventData['range_start_date'] ? \Carbon\Carbon::parse($eventData['range_start_date'])->format('d M') : '' }}
                                    -
                                    {{ $eventData['range_end_date'] ? \Carbon\Carbon::parse($eventData['range_end_date'])->format('d M Y') : '' }}
                                @else
                                    Multiple dates
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Time</dt>
                            <dd class="text-gray-900 dark:text-white">
                                {{ $eventData['start_time'] ?? 'TBD' }}
                                @if($eventData['end_time'])
                                    - {{ $eventData['end_time'] }}
                                @endif
                            </dd>
                        </div>
                        @if($eventData['door_time'])
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Door Time</dt>
                                <dd class="text-gray-900 dark:text-white">{{ $eventData['door_time'] }}</dd>
                            </div>
                        @endif
                    </dl>

                    @if($eventData['short_description'])
                        <div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                            <p class="text-gray-600 dark:text-gray-300">{{ $eventData['short_description'] }}</p>
                        </div>
                    @endif

                    @if($eventData['description'])
                        <div class="pt-4 mt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="prose dark:prose-invert max-w-none">
                                {{-- SECURITY FIX: Sanitize HTML to prevent XSS --}}
                                {!! \App\Helpers\HtmlSanitizer::sanitize($eventData['description']) !!}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Ticket Types --}}
                <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Ticket Types</h2>

                    @if(count($ticketTypesData) > 0)
                        <div class="space-y-3">
                            @foreach($ticketTypesData as $ticket)
                                <div class="flex items-center justify-between p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <div>
                                        <div class="font-medium text-gray-900 dark:text-white">{{ $ticket['name'] }}</div>
                                        @if($ticket['description'])
                                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ $ticket['description'] }}</div>
                                        @endif
                                    </div>
                                    <div class="text-right">
                                        @if($ticket['sale_price'] && $ticket['sale_price'] < $ticket['price'])
                                            <div class="text-sm line-through text-gray-400">{{ number_format($ticket['price'], 2) }} {{ $ticket['currency'] }}</div>
                                            <div class="font-bold text-green-600">{{ number_format($ticket['sale_price'], 2) }} {{ $ticket['currency'] }}</div>
                                        @else
                                            <div class="font-bold text-gray-900 dark:text-white">{{ number_format($ticket['price'], 2) }} {{ $ticket['currency'] }}</div>
                                        @endif
                                        <div class="text-xs text-gray-500">Capacity: {{ $ticket['capacity'] }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">No ticket types configured.</p>
                    @endif
                </div>

                {{-- Recent Orders --}}
                @if(count($recentOrders) > 0)
                    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Recent Orders</h2>

                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th class="pb-3 text-left">Customer</th>
                                        <th class="pb-3 text-left">Tickets</th>
                                        <th class="pb-3 text-right">Total</th>
                                        <th class="pb-3 text-right">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($recentOrders as $order)
                                        <tr>
                                            <td class="py-3">
                                                <div class="font-medium text-gray-900 dark:text-white">{{ $order['customer_name'] }}</div>
                                                <div class="text-xs text-gray-500">{{ $order['customer_email'] }}</div>
                                            </td>
                                            <td class="py-3 text-gray-600 dark:text-gray-300">{{ $order['tickets_count'] }}</td>
                                            <td class="py-3 text-right font-medium text-gray-900 dark:text-white">{{ number_format($order['total'], 2) }} RON</td>
                                            <td class="py-3 text-right text-gray-500 dark:text-gray-400">{{ $order['created_at']->format('d M H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Organizer Info --}}
                <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                    <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Organizer</h3>

                    <div class="space-y-3">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">{{ $organizerData['name'] }}</div>
                            @if($organizerData['company_name'])
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $organizerData['company_name'] }}</div>
                            @endif
                        </div>

                        @if($organizerData['contact_email'])
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <x-heroicon-o-envelope class="w-4 h-4" />
                                {{ $organizerData['contact_email'] }}
                            </div>
                        @endif

                        @if($organizerData['contact_phone'])
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <x-heroicon-o-phone class="w-4 h-4" />
                                {{ $organizerData['contact_phone'] }}
                            </div>
                        @endif

                        @if($organizerData['website'])
                            <a href="{{ $organizerData['website'] }}" target="_blank" class="flex items-center gap-2 text-sm text-primary-600 hover:underline">
                                <x-heroicon-o-globe-alt class="w-4 h-4" />
                                Visit Website
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Venue Info --}}
                @if($venueData['name'])
                    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Venue</h3>

                        @if($venueData['image_url'])
                            <img src="{{ $venueData['image_url'] }}" alt="{{ $venueData['name'] }}" class="object-cover w-full h-32 mb-4 rounded-lg">
                        @endif

                        <div class="space-y-2">
                            <div class="font-medium text-gray-900 dark:text-white">{{ $venueData['name'] }}</div>
                            @if($venueData['address'])
                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $venueData['address'] }}</div>
                            @endif
                            @if($venueData['city'])
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $venueData['city'] }}@if($venueData['country']), {{ $venueData['country'] }}@endif
                                </div>
                            @endif
                            @if($venueData['capacity'])
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Capacity: {{ number_format($venueData['capacity']) }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Artists --}}
                @if(count($artists) > 0)
                    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Artists</h3>

                        <div class="space-y-3">
                            @foreach($artists as $artist)
                                <div class="flex items-center gap-3">
                                    @if($artist['image'])
                                        <img src="{{ $artist['image'] }}" alt="{{ $artist['name'] }}" class="object-cover w-10 h-10 rounded-full">
                                    @else
                                        <div class="flex items-center justify-center w-10 h-10 text-sm font-bold rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                                            {{ strtoupper(substr($artist['name'], 0, 2)) }}
                                        </div>
                                    @endif
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $artist['name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Taxonomies --}}
                @if(count($taxonomies['event_types']) > 0 || count($taxonomies['event_genres']) > 0 || count($taxonomies['tags']) > 0)
                    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Categories</h3>

                        <div class="space-y-3">
                            @if(count($taxonomies['event_types']) > 0)
                                <div>
                                    <div class="mb-1 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Types</div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($taxonomies['event_types'] as $type)
                                            <span class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                {{ $type['name'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(count($taxonomies['event_genres']) > 0)
                                <div>
                                    <div class="mb-1 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Genres</div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($taxonomies['event_genres'] as $genre)
                                            <span class="px-2 py-1 text-xs rounded bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                {{ $genre['name'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(count($taxonomies['tags']) > 0)
                                <div>
                                    <div class="mb-1 text-xs font-medium text-gray-500 uppercase dark:text-gray-400">Tags</div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($taxonomies['tags'] as $tag)
                                            <span class="px-2 py-1 text-xs rounded bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                {{ $tag['name'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Links --}}
                @if($eventData['website_url'] || $eventData['facebook_url'] || $eventData['event_website_url'])
                    <div class="p-6 rounded-lg bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700">
                        <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Links</h3>

                        <div class="space-y-2">
                            @if($eventData['event_website_url'])
                                <a href="{{ $eventData['event_website_url'] }}" target="_blank" class="flex items-center gap-2 text-sm text-primary-600 hover:underline">
                                    <x-heroicon-o-globe-alt class="w-4 h-4" />
                                    Event Website
                                </a>
                            @endif
                            @if($eventData['facebook_url'])
                                <a href="{{ $eventData['facebook_url'] }}" target="_blank" class="flex items-center gap-2 text-sm text-primary-600 hover:underline">
                                    <x-heroicon-o-link class="w-4 h-4" />
                                    Facebook Event
                                </a>
                            @endif
                            @if($eventData['website_url'])
                                <a href="{{ $eventData['website_url'] }}" target="_blank" class="flex items-center gap-2 text-sm text-primary-600 hover:underline">
                                    <x-heroicon-o-link class="w-4 h-4" />
                                    Website
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
