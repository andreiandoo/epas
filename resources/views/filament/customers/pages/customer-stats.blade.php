<x-filament-panels::page>
    {{-- Header widgets (StatsOverview + Chart) se randă automat în header-ul paginii --}}

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Preferred Genres --}}
        <x-filament::section>
            <x-slot name="heading">Top Event Genres</x-slot>
            @if(!empty($preferredGenres))
                <ul class="divide-y divide-gray-100 bg-white rounded-xl shadow-sm">
                    @foreach($preferredGenres as $name => $cnt)
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-gray-700">{{ $name }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100">
                                {{ $cnt }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">No data yet.</p>
            @endif
        </x-filament::section>

        {{-- Recent Events --}}
        <x-filament::section>
            <x-slot name="heading">Recent Events</x-slot>
            @if(!empty($eventsList))
                <ul class="divide-y divide-gray-100 bg-white rounded-xl shadow-sm">
                    @foreach($eventsList as $ev)
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-gray-700">{{ $ev->title }}</span>
                            <a
                                href="{{ route('filament.admin.resources.events.edit', ['record' => $ev->id]) }}"
                                class="text-primary-600 hover:underline text-xs"
                            >Open</a>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">No events yet.</p>
            @endif
        </x-filament::section>

        {{-- Top Artists --}}
        <x-filament::section>
            <x-slot name="heading">Top Artists</x-slot>
            @if(!empty($artistsList))
                <ul class="divide-y divide-gray-100 bg-white rounded-xl shadow-sm">
                    @foreach($artistsList as $a)
                        <li class="flex items-center justify-between px-4 py-3">
                            <span class="text-sm text-gray-700">{{ $a->name }}</span>
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100">{{ $a->cnt }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">No artists yet.</p>
            @endif
        </x-filament::section>
    </div>

    <div class="mt-6">
        <x-filament::section>
            <x-slot name="heading">Tenants Purchased From</x-slot>
            @if(!empty($tenantsList))
                <div class="overflow-x-auto bg-white rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-700">Tenant</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700">Orders</th>
                                <th class="px-3 py-2 text-right font-medium text-gray-700">Value (RON)</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach($tenantsList as $tn)
                                <tr>
                                    <td class="px-3 py-2 text-gray-800">{{ $tn->name }}</td>
                                    <td class="px-3 py-2 text-right">{{ $tn->cnt }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format(($tn->total ?? 0)/100, 2) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <a
                                            href="{{ route('filament.admin.resources.orders.index') . '?tableSearch=' . urlencode($record->email) }}"
                                            class="text-primary-600 hover:underline"
                                        >View orders</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-sm text-gray-500">No purchases yet.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
