<x-filament-panels::page>
    @php
        $ticket = $this->record;
        $event = $ticket->ticketType?->event;
        $eventTitle = is_array($event?->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : ($event?->title ?? '');
        $venue = $event?->venue;
        $venueName = $venue ? ($venue->getTranslation('name', app()->getLocale()) ?? 'N/A') : 'N/A';
        $beneficiary = $ticket->meta['beneficiary'] ?? null;
        $isAdminPanel = filament()->getCurrentPanel()->getId() === 'admin';
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left Column: QR Code --}}
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="text-center">
                    <div class="inline-block p-3 bg-white rounded-lg border-2 border-gray-100">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($ticket->code) }}&color=181622&margin=0"
                            alt="QR Code"
                            class="w-40 h-40"
                        />
                    </div>
                    <p class="mt-3 text-xl font-mono font-bold text-gray-900 dark:text-white">{{ $ticket->code }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cod scanabil la intrare</p>

                    {{-- Status Badge --}}
                    <div class="mt-4">
                        <span class="inline-flex items-center px-3 py-1.5 text-sm font-semibold rounded-full
                            @if($ticket->status === 'valid') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                            @elseif($ticket->status === 'used') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                            @elseif($ticket->status === 'cancelled' || $ticket->status === 'void') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                            @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200
                            @endif">
                            @if($ticket->status === 'valid') ✓ Valid
                            @elseif($ticket->status === 'used') ✓ Utilizat
                            @elseif($ticket->status === 'cancelled') ✗ Anulat
                            @elseif($ticket->status === 'void') ✗ Anulat
                            @else {{ ucfirst($ticket->status) }}
                            @endif
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Details --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Ticket Details --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                <h3 class="text-sm font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Detalii bilet</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tip bilet</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $ticket->ticketType?->name ?? 'N/A' }}</p>
                    </div>
                    @if($ticket->seat_label)
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Loc</span>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $ticket->seat_label }}</p>
                        </div>
                    @endif
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Preț</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ number_format(($ticket->ticketType?->price_cents ?? 0) / 100, 2) }} {{ $ticket->ticketType?->currency ?? 'RON' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Creat la</span>
                        <p class="text-base text-gray-900 dark:text-white">{{ $ticket->created_at->format('d M Y, H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Event Details --}}
            @if($event)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Eveniment</h3>
                    <div class="flex items-start gap-4">
                        @if($event->poster_url)
                            <img src="{{ Storage::disk('public')->url($event->poster_url) }}" alt="{{ $eventTitle }}" class="object-cover rounded-lg" style="max-width: 100px; height: auto;">
                        @endif
                        <div class="flex-1">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">
                                @if($isAdminPanel)
                                    <a href="{{ \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $event]) }}" class="text-primary-600 hover:underline">
                                        {{ $eventTitle }}
                                    </a>
                                @else
                                    <a href="{{ \App\Filament\Tenant\Resources\EventResource::getUrl('edit', ['record' => $event]) }}" class="text-primary-600 hover:underline">
                                        {{ $eventTitle }}
                                    </a>
                                @endif
                            </p>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-300">
                                <span class="flex items-center">
                                    <x-heroicon-o-calendar class="w-4 h-4 mr-1 text-gray-400" />
                                    {{ $event->event_date ? $event->event_date->format('d M Y') : 'TBA' }}
                                </span>
                                @if($event->start_time)
                                    <span class="flex items-center">
                                        <x-heroicon-o-clock class="w-4 h-4 mr-1 text-gray-400" />
                                        {{ $event->start_time }}
                                    </span>
                                @endif
                                @if($venue)
                                    <span class="flex items-center">
                                        <x-heroicon-o-map-pin class="w-4 h-4 mr-1 text-gray-400" />
                                        {{ $venueName }}{{ $venue->city ? ', ' . $venue->city : '' }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Order & Customer --}}
            @if($ticket->order)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Comandă & Client</h3>
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Nr. comandă</span>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                @if($isAdminPanel)
                                    <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $ticket->order]) }}" class="text-primary-600 hover:underline">
                                        #{{ str_pad($ticket->order->id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                @else
                                    <a href="{{ \App\Filament\Tenant\Resources\OrderResource::getUrl('view', ['record' => $ticket->order]) }}" class="text-primary-600 hover:underline">
                                        #{{ str_pad($ticket->order->id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                @endif
                            </p>
                        </div>
                        @if($ticket->order->tenant)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Organizator</span>
                                <p class="text-base text-gray-900 dark:text-white">
                                    @if($isAdminPanel)
                                        <a href="{{ \App\Filament\Resources\Tenants\TenantResource::getUrl('edit', ['record' => $ticket->order->tenant]) }}" class="text-primary-600 hover:underline">
                                            {{ $ticket->order->tenant->name }}
                                        </a>
                                    @else
                                        {{ $ticket->order->tenant->name }}
                                    @endif
                                </p>
                            </div>
                        @endif
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Client</span>
                            <p class="text-base text-gray-900 dark:text-white">
                                {{ $ticket->order->meta['customer_name'] ?? $ticket->order->customer_email ?? 'N/A' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Email</span>
                            <p class="text-base text-gray-900 dark:text-white">
                                <a href="mailto:{{ $ticket->order->customer_email }}" class="text-primary-600 hover:underline">
                                    {{ $ticket->order->customer_email }}
                                </a>
                            </p>
                        </div>
                        @if($ticket->order->meta['customer_phone'] ?? null)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Telefon</span>
                                <p class="text-base text-gray-900 dark:text-white">
                                    <a href="tel:{{ $ticket->order->meta['customer_phone'] }}" class="text-primary-600 hover:underline">
                                        {{ $ticket->order->meta['customer_phone'] }}
                                    </a>
                                </p>
                            </div>
                        @endif
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Status comandă</span>
                            <p class="text-base">
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full
                                    @if($ticket->order->status === 'paid' || $ticket->order->status === 'confirmed') bg-green-100 text-green-800
                                    @elseif($ticket->order->status === 'pending') bg-yellow-100 text-yellow-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($ticket->order->status) }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Beneficiary --}}
            @if($beneficiary)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                    <h3 class="text-sm font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Beneficiar bilet</h3>
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Nume</span>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $beneficiary['name'] ?? 'N/A' }}</p>
                        </div>
                        @if($beneficiary['email'] ?? null)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Email</span>
                                <p class="text-base text-gray-900 dark:text-white">{{ $beneficiary['email'] }}</p>
                            </div>
                        @endif
                        @if($beneficiary['phone'] ?? null)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Telefon</span>
                                <p class="text-base text-gray-900 dark:text-white">{{ $beneficiary['phone'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
