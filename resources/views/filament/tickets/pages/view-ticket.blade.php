<x-filament-panels::page>
    @php
        $ticket = $this->record;
        $event = $ticket->ticketType?->event;
        $eventTitle = is_array($event?->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : ($event?->title ?? '');
        $venue = $event?->venue;
        $venueName = $venue ? ($venue->getTranslation('name', app()->getLocale()) ?? 'N/A') : 'N/A';
        $beneficiary = $ticket->meta['beneficiary'] ?? null;
        $currentPanelId = filament()->getCurrentPanel()->getId();
        $isAdminPanel = $currentPanelId === 'admin';
        $isMarketplacePanel = $currentPanelId === 'marketplace';

        // Resolve seat info using the Ticket model helper (meta → EventSeat → uid parsing)
        $seatDetails = $ticket->getSeatDetails();
        $seatSection = $seatDetails['section_name'] ?? null;
        $seatRow = $seatDetails['row_label'] ?? null;
        $seatNumber = $seatDetails['seat_number'] ?? null;
        $seatLabel = $ticket->seat_label ?? null;
        $hasSeatInfo = $seatLabel || $seatSection || $seatRow || $seatNumber;
        $isRefundable = (bool) ($ticket->marketplaceTicketType?->is_refundable ?? $ticket->ticketType?->is_refundable ?? false);
        $hasInsurance = (bool) ($ticket->meta['has_insurance'] ?? false);
        $insuranceAmount = $ticket->meta['insurance_amount'] ?? null;
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">
        {{-- Left Column: QR Code --}}
        <div class="lg:col-span-1">
            <div class="sticky p-6 bg-white border border-gray-200 shadow-sm top-20 dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <div class="text-center">
                    <div class="inline-block p-3 bg-white border-2 border-gray-100 rounded-lg">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($ticket->getVerifyUrl()) }}&color=181622&margin=0"
                            alt="QR Code"
                            class="w-40 h-40"
                        />
                    </div>
                    <p class="mt-3 font-mono text-xl font-bold text-gray-900 dark:text-white">{{ $ticket->code }}</p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Cod scanabil la intrare</p>

                    @if($ticket->barcode)
                        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Barcode</p>
                            <p class="mt-1 font-mono text-xs text-gray-700 dark:text-gray-300 break-all">{{ $ticket->barcode }}</p>
                        </div>
                    @endif

                    {{-- Status Badge --}}
                    <div class="mt-4 space-y-2">
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
                        <div class="flex flex-wrap justify-center gap-1">
                            @if($hasInsurance)
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                    ✓ Asigurat{{ $insuranceAmount ? ' (' . number_format($insuranceAmount, 2) . ' ' . ($ticket->order?->currency ?? 'RON') . ')' : '' }}
                                </span>
                            @elseif($isRefundable)
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">
                                    ↩ Returnabil
                                </span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                    ✗ Nereturnabil
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Details --}}
        <div class="space-y-4 lg:col-span-3">
            {{-- Ticket Details --}}
            <div class="p-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Detalii bilet</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Tip bilet</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $ticket->marketplaceTicketType?->name ?? $ticket->ticketType?->name ?? 'N/A' }}</p>
                    </div>
                    @if($hasSeatInfo)
                        @if($seatSection)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Secțiune</span>
                                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $seatSection }}</p>
                            </div>
                        @endif
                        @if($seatRow)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Rând</span>
                                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $seatRow }}</p>
                            </div>
                        @endif
                        @if($seatNumber)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Loc</span>
                                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $seatNumber }}</p>
                            </div>
                        @endif
                        @if($seatLabel && !$seatSection && !$seatRow && !$seatNumber)
                            <div>
                                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Loc (ID)</span>
                                <p class="text-base font-semibold text-gray-900 dark:text-white">{{ $seatLabel }}</p>
                            </div>
                        @endif
                    @endif
                    <div>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Preț</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ number_format($ticket->price ?? (($ticket->ticketType?->price_cents ?? 0) / 100), 2) }} {{ $ticket->order?->currency ?? $ticket->ticketType?->currency ?? 'RON' }}
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
                <div class="p-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Eveniment</h3>
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
                                @elseif($isMarketplacePanel)
                                    <a href="{{ \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $event]) }}" class="text-primary-600 hover:underline">
                                        {{ $eventTitle }}
                                    </a>
                                @else
                                    <a href="{{ \App\Filament\Tenant\Resources\EventResource::getUrl('edit', ['record' => $event]) }}" class="text-primary-600 hover:underline">
                                        {{ $eventTitle }}
                                    </a>
                                @endif
                            </p>
                            <div class="flex flex-wrap mt-2 text-sm text-gray-600 gap-x-4 gap-y-1 dark:text-gray-300">
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
                <div class="p-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Comandă & Client</h3>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
                        <div>
                            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Nr. comandă</span>
                            <p class="text-base font-semibold text-gray-900 dark:text-white">
                                @if($isAdminPanel)
                                    <a href="{{ \App\Filament\Resources\Orders\OrderResource::getUrl('view', ['record' => $ticket->order]) }}" class="text-primary-600 hover:underline">
                                        #{{ str_pad($ticket->order->id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                    @if($ticket->order->order_number)
                                        <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ $ticket->order->order_number }})</span>
                                    @endif
                                @elseif($isMarketplacePanel)
                                    <a href="{{ \App\Filament\Marketplace\Resources\OrderResource::getUrl('view', ['record' => $ticket->order]) }}" class="text-primary-600 hover:underline">
                                        #{{ str_pad($ticket->order->id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                    @if($ticket->order->order_number)
                                        <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ $ticket->order->order_number }})</span>
                                    @endif
                                @else
                                    <a href="{{ \App\Filament\Tenant\Resources\OrderResource::getUrl('view', ['record' => $ticket->order]) }}" class="text-primary-600 hover:underline">
                                        #{{ str_pad($ticket->order->id, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                    @if($ticket->order->order_number)
                                        <span class="ml-1 text-sm text-gray-500 dark:text-gray-400">({{ $ticket->order->order_number }})</span>
                                    @endif
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
                                    @if(in_array($ticket->order->status, ['paid', 'confirmed', 'completed'])) bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300
                                    @elseif($ticket->order->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300
                                    @elseif(in_array($ticket->order->status, ['cancelled', 'refunded'])) bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300
                                    @else bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300
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
                <div class="p-5 bg-white border border-gray-200 shadow-sm dark:bg-gray-800 rounded-xl dark:border-gray-700">
                    <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">Beneficiar bilet</h3>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-3">
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
