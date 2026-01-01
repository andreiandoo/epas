<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Stats Overview --}}
        <div class="grid grid-cols-4 gap-4">
            @php
                $tiers = [
                    'early_warning' => ['label' => 'Early Warning', 'color' => 'yellow', 'icon' => 'heroicon-o-exclamation-triangle', 'desc' => '30-60 days inactive'],
                    'gentle_nudge' => ['label' => 'Gentle Nudge', 'color' => 'orange', 'icon' => 'heroicon-o-hand-raised', 'desc' => '61-90 days inactive'],
                    'win_back' => ['label' => 'Win-Back', 'color' => 'red', 'icon' => 'heroicon-o-gift', 'desc' => '91-180 days inactive'],
                    'last_chance' => ['label' => 'Last Chance', 'color' => 'purple', 'icon' => 'heroicon-o-clock', 'desc' => '181-365 days inactive'],
                ];
            @endphp

            @foreach($tiers as $tier => $config)
                <button wire:click="filterByTier('{{ $tier }}')"
                        class="p-4 text-left transition-all bg-white rounded-xl shadow-lg dark:bg-gray-800 hover:shadow-xl {{ $selectedTier === $tier ? 'ring-2 ring-primary-500' : '' }}">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="p-2 rounded-lg bg-{{ $config['color'] }}-100 dark:bg-{{ $config['color'] }}-900/30">
                            <x-dynamic-component :component="$config['icon']" class="w-5 h-5 text-{{ $config['color'] }}-600 dark:text-{{ $config['color'] }}-400" />
                        </div>
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ $config['label'] }}</span>
                    </div>
                    <div class="text-3xl font-bold text-gray-900 dark:text-white">
                        {{ $stats[$tier] ?? 0 }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ $config['desc'] }}</div>
                </button>
            @endforeach
        </div>

        {{-- Filter Tabs --}}
        <div class="flex gap-2 p-1 bg-gray-100 rounded-lg dark:bg-gray-800 w-fit">
            <button wire:click="filterByTier('all')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $selectedTier === 'all' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                All ({{ $stats['total'] ?? 0 }})
            </button>
            @foreach($tiers as $tier => $config)
                <button wire:click="filterByTier('{{ $tier }}')"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $selectedTier === $tier ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900' }}">
                    {{ $config['label'] }} ({{ $stats[$tier] ?? 0 }})
                </button>
            @endforeach
        </div>

        {{-- Candidates Table --}}
        <div class="overflow-hidden bg-white shadow-lg dark:bg-gray-800 rounded-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Customer</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Tier</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">LTV</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Days Inactive</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Churn Risk</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Offer</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($this->getFilteredCandidates() as $candidate)
                            @php
                                $tierConfig = $tiers[$candidate['tier']] ?? $tiers['early_warning'];
                                $riskColors = [
                                    'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                    'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                                    'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                    'low' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                                ];
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex items-center justify-center w-8 h-8 text-sm font-medium text-white rounded-full bg-gradient-to-br from-primary-500 to-primary-600">
                                            {{ strtoupper(substr($candidate['email_hash'] ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">Person #{{ $candidate['person_id'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $candidate['total_purchases'] ?? 0 }} purchases</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-full bg-{{ $tierConfig['color'] }}-100 text-{{ $tierConfig['color'] }}-800 dark:bg-{{ $tierConfig['color'] }}-900/30 dark:text-{{ $tierConfig['color'] }}-400">
                                        {{ $tierConfig['label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-white">
                                    {{ number_format($candidate['ltv'] ?? 0, 2) }} RON
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                    {{ $candidate['days_inactive'] ?? 0 }} days
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $riskColors[$candidate['churn_risk'] ?? 'medium'] }}">
                                        {{ ucfirst($candidate['churn_risk'] ?? 'unknown') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if(isset($candidate['offer']))
                                        <div class="text-sm">
                                            <span class="font-medium text-primary-600">{{ $candidate['offer']['discount_percent'] ?? 0 }}% off</span>
                                            <div class="text-xs text-gray-500">Code: {{ $candidate['offer']['code'] ?? 'N/A' }}</div>
                                        </div>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('filament.tenant.resources.tracking.person-profiles.view', $candidate['person_id']) }}"
                                       class="text-primary-600 hover:underline text-sm">
                                        View Profile
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <x-heroicon-o-face-smile class="w-12 h-12 mx-auto mb-3 text-green-500" />
                                    <p class="text-gray-500 dark:text-gray-400">No customers in this segment</p>
                                    <p class="text-sm text-gray-400">Your customers are active and engaged!</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Campaign Tips --}}
        <div class="p-4 border border-blue-200 rounded-xl bg-blue-50 dark:bg-blue-900/20 dark:border-blue-800">
            <div class="flex gap-3">
                <x-heroicon-o-light-bulb class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                <div class="text-sm">
                    <p class="font-medium text-blue-900 dark:text-blue-100">Win-Back Best Practices</p>
                    <ul class="mt-2 space-y-1 text-blue-700 dark:text-blue-300 list-disc list-inside">
                        <li><strong>Early Warning:</strong> Send a friendly reminder about upcoming events they might like</li>
                        <li><strong>Gentle Nudge:</strong> Offer early access or exclusive content</li>
                        <li><strong>Win-Back:</strong> Provide a meaningful discount (15-20%) with urgency</li>
                        <li><strong>Last Chance:</strong> Make your best offer (25%+) before they're considered lost</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
