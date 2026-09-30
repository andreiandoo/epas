<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Financial Summary Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Available Balance --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/50 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-wallet class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Sold disponibil</p>
                        <p class="text-xl font-bold text-green-600 dark:text-green-400">
                            {{ number_format((float) $organizer->available_balance, 2, ',', '.') }} RON
                        </p>
                    </div>
                </div>
            </div>

            {{-- Pending Payouts --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 dark:bg-yellow-900/50 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-clock class="w-5 h-5 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Plăți în așteptare</p>
                        <p class="text-xl font-bold text-yellow-600 dark:text-yellow-400">
                            {{ number_format((float) $organizer->pending_balance, 2, ',', '.') }} RON
                        </p>
                    </div>
                </div>
            </div>

            {{-- Total Paid Out --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total plătit</p>
                        <p class="text-xl font-bold text-blue-600 dark:text-blue-400">
                            {{ number_format((float) $organizer->total_paid_out, 2, ',', '.') }} RON
                        </p>
                    </div>
                </div>
            </div>

            {{-- Total Revenue --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-gray-600 dark:text-gray-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Venituri totale</p>
                        <p class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ number_format((float) $organizer->total_revenue, 2, ',', '.') }} RON
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Avansuri --}}
        @if($advances->isNotEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                        <x-heroicon-o-arrow-trending-up class="w-5 h-5 text-gray-400" />
                        Avansuri
                    </h3>
                    <span class="text-sm {{ $advanceOpen > 0 ? 'text-amber-600 dark:text-amber-400 font-semibold' : 'text-gray-500 dark:text-gray-400' }}">
                        Avans nedecontat: {{ number_format($advanceOpen, 2, ',', '.') }} RON
                    </span>
                </div>
                <p class="px-6 pt-3 text-xs text-gray-500 dark:text-gray-400">
                    Avansul se scade din sold la înregistrare. Deconturile pe eveniment aprobate după aceea se compensează automat din el, cel mai vechi avans întâi; la plată se transferă doar restul.
                </p>
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 mt-3">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Avans</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plătit la</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sumă</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Compensat</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rămas</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Compensat în deconturile</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($advances as $advance)
                            <tr>
                                <td class="px-6 py-3 text-sm font-mono">
                                    <a href="{{ url('/marketplace/payouts/' . $advance->id) }}" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 hover:underline">{{ $advance->reference }}</a>
                                    @if($advance->payment_reference)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-sans">Ref: {{ $advance->payment_reference }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">{{ ($advance->completed_at ?? $advance->created_at)?->format('d.m.Y') }}</td>
                                <td class="px-6 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">{{ number_format((float) $advance->amount, 2, ',', '.') }} RON</td>
                                <td class="px-6 py-3 text-sm text-right text-gray-600 dark:text-gray-300">{{ number_format((float) $advance->advance_used, 2, ',', '.') }} RON</td>
                                <td class="px-6 py-3 text-sm text-right font-semibold {{ $advance->advance_remaining > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">{{ number_format((float) $advance->advance_remaining, 2, ',', '.') }} RON</td>
                                <td class="px-6 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    @forelse($advance->allocationsFromAdvance as $alloc)
                                        @php
                                            $allocEvent = $alloc->payout?->event;
                                            $allocTitle = $allocEvent ? (is_array($allocEvent->title) ? ($allocEvent->title['ro'] ?? $allocEvent->title['en'] ?? (reset($allocEvent->title) ?: null)) : $allocEvent->title) : null;
                                        @endphp
                                        <div>
                                            <a href="{{ url('/marketplace/payouts/' . $alloc->payout_id) }}" class="font-mono text-primary-600 dark:text-primary-400 hover:underline">{{ $alloc->payout?->decont_series ?? $alloc->payout?->reference ?? ('#' . $alloc->payout_id) }}</a>
                                            @if($allocTitle) · {{ $allocTitle }} @endif
                                            · {{ number_format((float) $alloc->amount, 2, ',', '.') }} RON
                                        </div>
                                    @empty
                                        <span class="text-gray-400">Încă necompensat</span>
                                    @endforelse
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Bank Info --}}
        @if($organizer->bank_name || $organizer->iban)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-building-library class="w-5 h-5 text-gray-400" />
                    <div class="flex gap-6 text-sm">
                        @if($organizer->bank_name)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">Bancă:</span>
                                <span class="font-medium text-gray-900 dark:text-white">{{ $organizer->bank_name }}</span>
                            </div>
                        @endif
                        @if($organizer->iban)
                            <div>
                                <span class="text-gray-500 dark:text-gray-400">IBAN:</span>
                                <span class="font-mono font-medium text-gray-900 dark:text-white">{{ $organizer->iban }}</span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Revenue per Event --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-calendar class="w-5 h-5 text-gray-400" />
                    Venituri pe eveniment
                </h3>
            </div>
            @if($revenuePerEvent->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-chart-bar class="mx-auto h-10 w-10 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nu există încă comenzi finalizate.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Eveniment</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Comenzi</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Venit brut</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Comision</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Venit net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($revenuePerEvent as $row)
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-900 dark:text-white">
                                    {{ $row->marketplaceEvent?->title ?? 'Eveniment #' . $row->marketplace_event_id }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right text-gray-600 dark:text-gray-300">
                                    {{ $row->orders_count }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right text-gray-900 dark:text-white">
                                    {{ number_format($row->gross_revenue, 2, ',', '.') }} RON
                                </td>
                                <td class="px-6 py-3 text-sm text-right text-red-600 dark:text-red-400">
                                    -{{ number_format($row->total_commission, 2, ',', '.') }} RON
                                </td>
                                <td class="px-6 py-3 text-sm text-right font-medium text-green-600 dark:text-green-400">
                                    {{ number_format($row->net_revenue, 2, ',', '.') }} RON
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <td class="px-6 py-3 text-sm font-semibold text-gray-900 dark:text-white">Total</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ $revenuePerEvent->sum('orders_count') }}</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900 dark:text-white">{{ number_format($revenuePerEvent->sum('gross_revenue'), 2, ',', '.') }} RON</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-red-600 dark:text-red-400">-{{ number_format($revenuePerEvent->sum('total_commission'), 2, ',', '.') }} RON</td>
                            <td class="px-6 py-3 text-sm text-right font-semibold text-green-600 dark:text-green-400">{{ number_format($revenuePerEvent->sum('net_revenue'), 2, ',', '.') }} RON</td>
                        </tr>
                    </tfoot>
                </table>
            @endif
        </div>

        {{-- Payout History --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    <x-heroicon-o-banknotes class="w-5 h-5 text-gray-400" />
                    Lista deconturi
                </h3>
            </div>
            @if($payouts->isEmpty())
                <div class="text-center py-8">
                    <x-heroicon-o-banknotes class="mx-auto h-10 w-10 text-gray-400" />
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nu există încă deconturi.</p>
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Eveniment</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Referință</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Sumă</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Referință plată</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Creat</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Finalizat</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($payouts as $payout)
                            @php
                                $ev = $payout->event;
                                $evTitle = null;
                                $evDate = null;
                                $evVenue = null;
                                $evCity = null;
                                if ($ev) {
                                    $t = $ev->title;
                                    $evTitle = is_array($t) ? ($t['ro'] ?? $t['en'] ?? (reset($t) ?: null)) : $t;
                                    $evDate = $ev->start_date?->format('d.m.Y');
                                    if ($ev->venue) {
                                        $vn = $ev->venue->name;
                                        $evVenue = is_array($vn) ? ($vn['ro'] ?? $vn['en'] ?? (reset($vn) ?: null)) : $vn;
                                        $evCity = $ev->venue->city ?: null;
                                    }
                                    // Event date - venue - city, only the parts we have
                                    $metaParts = array_filter([$evDate, $evVenue, $evCity]);
                                    $evMeta = implode(' - ', $metaParts);
                                }
                            @endphp
                            <tr>
                                <td class="px-6 py-3 text-sm">
                                    @if($ev)
                                        <a href="{{ url('/marketplace/events/' . $ev->id . '/edit') }}" class="font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">
                                            {{ $evTitle ?? ('Eveniment #' . $ev->id) }}
                                        </a>
                                        @if(!empty($evMeta))
                                            <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $evMeta }}</div>
                                        @endif
                                    @elseif($payout->isAdvance())
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300">Avans</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm font-mono">
                                    <a href="{{ url('/marketplace/payouts/' . $payout->id) }}" class="text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">
                                        {{ $payout->reference }}
                                    </a>
                                    @if($payout->decont_series)
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $payout->decont_series }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-sm text-right font-medium text-gray-900 dark:text-white">
                                    {{ number_format((float) $payout->amount, 2, ',', '.') }} {{ $payout->currency ?? 'RON' }}
                                    @if((float) ($payout->advance_covered ?? 0) > 0)
                                        <div class="text-xs font-normal text-amber-600 dark:text-amber-400 mt-0.5">din avans: −{{ number_format((float) $payout->advance_covered, 2, ',', '.') }}</div>
                                        <div class="text-xs font-normal text-gray-500 dark:text-gray-400">de plată: {{ number_format(max(0, (float) $payout->amount - (float) $payout->advance_covered), 2, ',', '.') }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center">
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300',
                                            'approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
                                            'processing' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300',
                                            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
                                            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
                                            'cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                        ];
                                        $color = $statusColors[$payout->status] ?? $statusColors['cancelled'];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $color }}">
                                        {{ ['pending' => 'În așteptare', 'approved' => 'Aprobat', 'processing' => 'În procesare', 'completed' => 'Finalizat', 'rejected' => 'Respins', 'cancelled' => 'Anulat'][$payout->status] ?? ucfirst($payout->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->payment_reference ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->created_at?->format('d.m.Y') }}
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $payout->completed_at?->format('d.m.Y') ?? '-' }}
                                </td>
                                <td class="px-6 py-3 text-sm text-right whitespace-nowrap">
                                    @if($payout->canBeCompleted())
                                        {{ ($this->completePayoutAction)(['payout' => $payout->id]) }}
                                    @elseif($payout->isCompleted())
                                        {{ ($this->editPaymentReferenceAction)(['payout' => $payout->id]) }}
                                    @elseif($payout->isPending())
                                        <a href="{{ url('/marketplace/payouts/' . $payout->id) }}" class="text-xs text-gray-500 hover:underline dark:text-gray-400" title="Decontul trebuie aprobat înainte de a fi finalizat">Aprobă întâi</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</x-filament-panels::page>
