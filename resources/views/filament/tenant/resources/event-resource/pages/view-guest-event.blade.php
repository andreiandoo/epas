<x-filament-panels::page>
    @php
        $event = $this->getEventData();
        $organizer = $this->getOrganizerData();
        $venue = $this->getVenueData();
        $ticketTypes = $this->getTicketTypesData();
        $stats = $this->getSalesStats();
        $recentOrders = $this->getRecentOrders();
        $artists = $this->getArtists();
        $taxonomies = $this->getTaxonomies();
        $tenant = auth()->user()->tenant;
    @endphp

    <!-- Guest Event Banner -->
    <div class="bg-gradient-to-r from-purple-500 to-indigo-600 rounded-xl p-4 mb-6 text-white">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-white/20 rounded-lg">
                <x-heroicon-o-users class="w-6 h-6" />
            </div>
            <div>
                <h3 class="font-semibold text-lg">Hosted Event</h3>
                <p class="text-white/80 text-sm">This event is organized by <strong>{{ $organizer['name'] }}</strong> and is taking place at your venue.</p>
            </div>
        </div>
    </div>

    <!-- Stats Overview -->
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4 mb-6">
        <!-- Tickets Sold -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                    <x-heroicon-o-ticket class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['tickets_sold']) }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Tickets Sold</p>
                </div>
            </div>
        </div>

        <!-- Revenue -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-green-100 dark:bg-green-900/30 rounded-lg">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['revenue'], 2) }} <span class="text-sm font-medium text-gray-500">{{ $tenant->currency ?? 'EUR' }}</span></p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Revenue</p>
                </div>
            </div>
        </div>

        <!-- Customers -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <x-heroicon-o-users class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['unique_customers']) }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Customers</p>
                </div>
            </div>
        </div>

        <!-- Capacity -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                    <x-heroicon-o-building-office class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($stats['total_capacity']) }}</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Capacity</p>
                </div>
            </div>
        </div>

        <!-- Occupancy -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center gap-3">
                <div class="p-2.5 {{ $stats['occupancy'] >= 80 ? 'bg-green-100 dark:bg-green-900/30' : ($stats['occupancy'] >= 50 ? 'bg-amber-100 dark:bg-amber-900/30' : 'bg-gray-100 dark:bg-gray-700') }} rounded-lg">
                    <x-heroicon-o-chart-pie class="w-5 h-5 {{ $stats['occupancy'] >= 80 ? 'text-green-600 dark:text-green-400' : ($stats['occupancy'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-500 dark:text-gray-400') }}" />
                </div>
                <div>
                    <p class="text-2xl font-bold {{ $stats['occupancy'] >= 80 ? 'text-green-600 dark:text-green-400' : ($stats['occupancy'] >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white') }}">{{ $stats['occupancy'] }}%</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Occupancy</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Event Details -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                @if($event['hero_image_url'] || $event['poster_url'])
                    <div class="h-48 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <img src="{{ $event['hero_image_url'] ?? $event['poster_url'] }}" alt="{{ $event['title'] }}" class="w-full h-full object-cover">
                    </div>
                @endif
                <div class="p-5">
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        @if($event['is_cancelled'])
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">
                                <x-heroicon-o-x-circle class="w-3 h-3 mr-1" />
                                Cancelled
                            </span>
                        @endif
                        @if($event['is_sold_out'])
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400">
                                <x-heroicon-o-lock-closed class="w-3 h-3 mr-1" />
                                Sold Out
                            </span>
                        @endif
                        @if($event['is_postponed'])
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                                <x-heroicon-o-clock class="w-3 h-3 mr-1" />
                                Postponed
                            </span>
                        @endif
                        @if($event['is_promoted'])
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                <x-heroicon-o-sparkles class="w-3 h-3 mr-1" />
                                Promoted
                            </span>
                        @endif
                        @if($event['door_sales_only'])
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                <x-heroicon-o-key class="w-3 h-3 mr-1" />
                                Door Sales Only
                            </span>
                        @endif
                    </div>

                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-2">{{ $event['title'] }}</h2>

                    @if($event['subtitle'])
                        <p class="text-gray-600 dark:text-gray-400 mb-3">{{ $event['subtitle'] }}</p>
                    @endif

                    <!-- Schedule -->
                    <div class="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400 mb-4">
                        <span class="flex items-center gap-1">
                            <x-heroicon-o-calendar class="w-4 h-4" />
                            @if($event['duration_mode'] === 'single_day')
                                {{ $event['event_date']?->format('F d, Y') ?? 'TBD' }}
                            @elseif($event['duration_mode'] === 'range')
                                {{ $event['range_start_date']?->format('M d') }} - {{ $event['range_end_date']?->format('M d, Y') }}
                            @endif
                        </span>
                        @if($event['start_time'])
                            <span class="flex items-center gap-1">
                                <x-heroicon-o-clock class="w-4 h-4" />
                                {{ $event['start_time'] }}
                                @if($event['door_time'])
                                    (doors: {{ $event['door_time'] }})
                                @endif
                            </span>
                        @endif
                    </div>

                    @if($event['short_description'])
                        <div class="text-gray-700 dark:text-gray-300 mb-4">
                            {{ $event['short_description'] }}
                        </div>
                    @endif

                    @if($event['description'])
                        <div class="prose dark:prose-invert max-w-none text-gray-700 dark:text-gray-300">
                            {{-- SECURITY FIX: Sanitize HTML to prevent XSS --}}
                            {!! \App\Helpers\HtmlSanitizer::sanitize($event['description']) !!}
                        </div>
                    @endif

                    <!-- Taxonomies -->
                    @if(!empty($taxonomies['event_types']) || !empty($taxonomies['event_genres']) || !empty($taxonomies['tags']))
                        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <div class="flex flex-wrap gap-2">
                                @foreach($taxonomies['event_types'] as $type)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                        {{ $type['name'] }}
                                    </span>
                                @endforeach
                                @foreach($taxonomies['event_genres'] as $genre)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400">
                                        {{ $genre['name'] }}
                                    </span>
                                @endforeach
                                @foreach($taxonomies['tags'] as $tag)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $tag['name'] }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Ticket Types -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ticket Types</h3>
                </div>
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($ticketTypes as $ticket)
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium text-gray-900 dark:text-white">{{ $ticket['name'] }}</h4>
                                    @if($ticket['description'])
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $ticket['description'] }}</p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center gap-2">
                                        @if($ticket['sale_price'] && $ticket['sale_price'] < $ticket['price'])
                                            <span class="text-sm text-gray-400 line-through">{{ number_format($ticket['price'], 2) }}</span>
                                            <span class="font-bold text-green-600 dark:text-green-400">{{ number_format($ticket['sale_price'], 2) }} {{ $ticket['currency'] }}</span>
                                        @else
                                            <span class="font-bold text-gray-900 dark:text-white">{{ number_format($ticket['price'], 2) }} {{ $ticket['currency'] }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Capacity: {{ number_format($ticket['capacity']) }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-gray-500 dark:text-gray-400">
                            No ticket types configured.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Orders</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Last 10 paid orders</p>
                </div>
                @if(empty($recentOrders))
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        <x-heroicon-o-shopping-cart class="w-12 h-12 mx-auto mb-3 opacity-50" />
                        No orders yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 dark:bg-gray-700/50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Customer</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tickets</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($recentOrders as $order)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $order['customer_name'] }}</div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $order['customer_email'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                            {{ $order['tickets_count'] }}
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ number_format($order['total'], 2) }} {{ $tenant->currency ?? 'EUR' }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $order['created_at']->format('M d, H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Organizer Info -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Organizer</h3>
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Name</p>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $organizer['name'] }}</p>
                    </div>
                    @if($organizer['company_name'])
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Company</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $organizer['company_name'] }}</p>
                        </div>
                    @endif
                    @if($organizer['contact_email'])
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Email</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $organizer['contact_email'] }}</p>
                        </div>
                    @endif
                    @if($organizer['contact_phone'])
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Phone</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $organizer['contact_phone'] }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Venue Info -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Venue</h3>
                @if($venue['image_url'])
                    <div class="h-32 rounded-lg overflow-hidden mb-4">
                        <img src="{{ $venue['image_url'] }}" alt="{{ $venue['name'] }}" class="w-full h-full object-cover">
                    </div>
                @endif
                <div class="space-y-3">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Name</p>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $venue['name'] }}</p>
                    </div>
                    @if($venue['address'])
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Address</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $venue['address'] }}</p>
                        </div>
                    @endif
                    @if($venue['city'])
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">City</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ $venue['city'] }}{{ $venue['country'] ? ', ' . $venue['country'] : '' }}</p>
                        </div>
                    @endif
                    @if($venue['capacity'])
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Capacity</p>
                            <p class="font-medium text-gray-900 dark:text-white">{{ number_format($venue['capacity']) }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Artists -->
            @if(!empty($artists))
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Artists</h3>
                    <div class="space-y-3">
                        @foreach($artists as $artist)
                            <div class="flex items-center gap-3">
                                @if($artist['image'])
                                    <img src="{{ $artist['image'] }}" alt="{{ $artist['name'] }}" class="w-10 h-10 rounded-full object-cover">
                                @else
                                    <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                        <x-heroicon-o-user class="w-5 h-5 text-gray-400" />
                                    </div>
                                @endif
                                <span class="font-medium text-gray-900 dark:text-white">{{ $artist['name'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Order Stats -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Order Summary</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Total Orders</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $stats['orders']['total'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Paid</span>
                        <span class="font-medium text-green-600 dark:text-green-400">{{ $stats['orders']['paid'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Pending</span>
                        <span class="font-medium text-yellow-600 dark:text-yellow-400">{{ $stats['orders']['pending'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Cancelled</span>
                        <span class="font-medium text-red-600 dark:text-red-400">{{ $stats['orders']['cancelled'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500 dark:text-gray-400">Refunded</span>
                        <span class="font-medium text-gray-600 dark:text-gray-300">{{ $stats['orders']['refunded'] }}</span>
                    </div>
                </div>
            </div>

            <!-- Links -->
            @if($event['website_url'] || $event['facebook_url'] || $event['event_website_url'])
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Links</h3>
                    <div class="space-y-2">
                        @if($event['event_website_url'])
                            <a href="{{ $event['event_website_url'] }}" target="_blank" class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                                <x-heroicon-o-globe-alt class="w-4 h-4" />
                                Event Website
                            </a>
                        @endif
                        @if($event['website_url'])
                            <a href="{{ $event['website_url'] }}" target="_blank" class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                                <x-heroicon-o-link class="w-4 h-4" />
                                Website
                            </a>
                        @endif
                        @if($event['facebook_url'])
                            <a href="{{ $event['facebook_url'] }}" target="_blank" class="flex items-center gap-2 text-primary-600 dark:text-primary-400 hover:underline">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Facebook Event
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
