
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
                                    <tr class="cursor-pointer hover:bg-white/20 dark:hover:bg-white/20 group" onclick="window.location='{{ route('filament.marketplace.resources.orders.edit', ['record' => $ord->id]) }}'">
                                        <td class="px-3 py-2 font-mono text-xs">
                                            <a href="{{ route('filament.marketplace.resources.orders.edit', ['record' => $ord->id]) }}" class="text-primary-600 hover:underline">{{ $ord->order_number ?? '#' . str_pad($ord->id, 6, '0', STR_PAD_LEFT) }}</a>
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
                                        <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50" onclick="window.location='{{ route('filament.marketplace.resources.tickets.view', ['record' => $tkt->id]) }}'">
                                            <td class="px-3 py-2 font-mono text-xs">
                                                <a href="{{ route('filament.marketplace.resources.tickets.view', ['record' => $tkt->id]) }}" class="text-primary-600 hover:underline">{{ $tkt->code ?? '-' }}</a>
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
                        <x-slot name="heading">Cumpărături per Organizator</x-slot>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Organizator</th>
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
             TAB 3: ISTORIC EMAIL-URI
