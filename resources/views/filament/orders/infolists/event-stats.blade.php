<div>
    @php
        $order = $getRecord();
        $firstTicket = $order->tickets()->with('ticketType.event')->first();
        $event = $firstTicket?->ticketType?->event;

        if ($event) {
            $ticketTypes = $event->ticketTypes;
        }
    @endphp

    @if($event && $ticketTypes->count() > 0)
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left">Ticket Type</th>
                        <th class="px-3 py-2 text-center">Available</th>
                        <th class="px-3 py-2 text-center">Sold</th>
                        <th class="px-3 py-2 text-right">Revenue</th>
                        <th class="px-3 py-2 text-center">Occupancy</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ticketTypes as $ticketType)
                        @php
                            $quotaTotal = $ticketType->quota_total ?? 0;
                            $quotaSold = $ticketType->quota_sold ?? 0;
                            $revenue = ($ticketType->price_cents ?? 0) * $quotaSold;
                            $occupancy = $quotaTotal > 0 ? round(($quotaSold / $quotaTotal) * 100, 1) : 0;
                        @endphp
                        <tr class="border-t dark:border-gray-700">
                            <td class="px-3 py-2 font-medium">{{ $ticketType->name }}</td>
                            <td class="px-3 py-2 text-center">{{ number_format($quotaTotal) }}</td>
                            <td class="px-3 py-2 text-center">{{ number_format($quotaSold) }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($revenue / 100, 2) }} RON</td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-md
                                    {{ $occupancy >= 90 ? 'bg-red-100 text-red-800' : ($occupancy >= 70 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800') }}">
                                    {{ $occupancy }}%
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 dark:bg-gray-800 font-medium">
                    <tr>
                        <td class="px-3 py-2">Total</td>
                        <td class="px-3 py-2 text-center">{{ number_format($ticketTypes->sum('quota_total')) }}</td>
                        <td class="px-3 py-2 text-center">{{ number_format($ticketTypes->sum('quota_sold')) }}</td>
                        <td class="px-3 py-2 text-right">
                            {{ number_format($ticketTypes->sum(fn($tt) => ($tt->price_cents * $tt->quota_sold)) / 100, 2) }} RON
                        </td>
                        <td class="px-3 py-2 text-center">
                            @php
                                $totalQuota = $ticketTypes->sum('quota_total');
                                $totalSold = $ticketTypes->sum('quota_sold');
                                $totalOccupancy = $totalQuota > 0 ? round(($totalSold / $totalQuota) * 100, 1) : 0;
                            @endphp
                            {{ $totalOccupancy }}%
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <p class="text-gray-500 text-sm">No ticket type statistics available.</p>
    @endif
</div>
