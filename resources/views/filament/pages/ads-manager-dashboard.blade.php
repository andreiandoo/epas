<x-filament-panels::page>
    {{-- Date Filters --}}
    <div class="mb-6 p-4 bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                <input type="date" wire:model.live="startDate" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                <input type="date" wire:model.live="endDate" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm">
            </div>
            <div class="flex items-end">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    Showing data for {{ \Carbon\Carbon::parse($startDate)->format('M d') }} — {{ \Carbon\Carbon::parse($endDate)->format('M d, Y') }}
                </span>
            </div>
        </div>
    </div>

    {{-- Summary KPI Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Active Campaigns</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $summary['active_campaigns'] ?? 0 }}</div>
            @if(($summary['pending_requests'] ?? 0) > 0)
                <div class="text-xs text-amber-600 mt-1">{{ $summary['pending_requests'] }} pending requests</div>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Ad Spend</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($summary['total_spend'] ?? 0, 2) }} EUR</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Revenue</div>
            <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1">{{ number_format($summary['total_revenue'] ?? 0, 2) }} EUR</div>
            @php $profit = $summary['profit'] ?? 0; @endphp
            <div class="text-xs {{ $profit >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                {{ $profit >= 0 ? '+' : '' }}{{ number_format($profit, 2) }} net
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">ROAS</div>
            @php $roas = $summary['roas'] ?? 0; @endphp
            <div class="text-2xl font-bold {{ $roas >= 2 ? 'text-green-600' : ($roas >= 1 ? 'text-yellow-600' : 'text-red-600') }} mt-1">{{ number_format($roas, 2) }}x</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Conversions</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($summary['total_conversions'] ?? 0) }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ number_format($summary['total_tickets'] ?? 0) }} tickets sold</div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">CAC</div>
            <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($summary['cac'] ?? 0, 2) }} EUR</div>
            <div class="text-xs text-gray-500 mt-1">CTR: {{ $summary['avg_ctr'] ?? 0 }}% | CPC: {{ $summary['avg_cpc'] ?? 0 }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Platform Comparison --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Platform Comparison</h3>
            </div>
            <div class="p-4 overflow-x-auto">
                @if(count($platformComparison) > 0)
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-gray-500 dark:text-gray-400">
                                <th class="pb-2 font-medium">Platform</th>
                                <th class="pb-2 font-medium text-right">Spend</th>
                                <th class="pb-2 font-medium text-right">Revenue</th>
                                <th class="pb-2 font-medium text-right">ROAS</th>
                                <th class="pb-2 font-medium text-right">Conv.</th>
                                <th class="pb-2 font-medium text-right">CTR</th>
                                <th class="pb-2 font-medium text-right">CPC</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($platformComparison as $platform)
                                <tr class="border-t border-gray-100 dark:border-gray-700">
                                    <td class="py-2 font-medium text-gray-900 dark:text-white">
                                        @if($platform['platform'] === 'Facebook')
                                            <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-blue-500 mr-2"></span>Facebook</span>
                                        @elseif($platform['platform'] === 'Instagram')
                                            <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-pink-500 mr-2"></span>Instagram</span>
                                        @elseif($platform['platform'] === 'Google')
                                            <span class="inline-flex items-center"><span class="w-2 h-2 rounded-full bg-yellow-500 mr-2"></span>Google</span>
                                        @else
                                            {{ $platform['platform'] }}
                                        @endif
                                    </td>
                                    <td class="py-2 text-right">{{ number_format($platform['spend'], 2) }}</td>
                                    <td class="py-2 text-right text-green-600">{{ number_format($platform['revenue'], 2) }}</td>
                                    <td class="py-2 text-right font-semibold {{ $platform['roas'] >= 2 ? 'text-green-600' : ($platform['roas'] >= 1 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ number_format($platform['roas'], 2) }}x
                                    </td>
                                    <td class="py-2 text-right">{{ number_format($platform['conversions']) }}</td>
                                    <td class="py-2 text-right">{{ $platform['ctr'] }}%</td>
                                    <td class="py-2 text-right">{{ number_format($platform['cpc'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No platform data available for this period.</p>
                @endif
            </div>
        </div>

        {{-- Daily Spend vs Revenue Trend --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Daily Spend vs Revenue</h3>
            </div>
            <div class="p-4">
                @if(count($dailyTrend) > 0)
                    <div class="space-y-2 max-h-80 overflow-y-auto">
                        @php
                            $maxValue = max(
                                max(array_column($dailyTrend, 'spend') ?: [1]),
                                max(array_column($dailyTrend, 'revenue') ?: [1])
                            );
                        @endphp
                        @foreach($dailyTrend as $day)
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-14 text-gray-500 shrink-0">{{ $day['date'] }}</span>
                                <div class="flex-1 flex flex-col gap-0.5">
                                    <div class="flex items-center gap-1">
                                        <div class="h-3 bg-red-400 rounded" style="width: {{ $maxValue > 0 ? ($day['spend'] / $maxValue) * 100 : 0 }}%"></div>
                                        <span class="text-gray-600 dark:text-gray-400">{{ number_format($day['spend'], 0) }}</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <div class="h-3 bg-green-400 rounded" style="width: {{ $maxValue > 0 ? ($day['revenue'] / $maxValue) * 100 : 0 }}%"></div>
                                        <span class="text-gray-600 dark:text-gray-400">{{ number_format($day['revenue'], 0) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex gap-4 mt-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-red-400"></span> Spend</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded bg-green-400"></span> Revenue</span>
                    </div>
                @else
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No daily data available for this period.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Top Campaigns Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Top Campaigns by Revenue</h3>
        </div>
        <div class="p-4 overflow-x-auto">
            @if(count($topCampaigns) > 0)
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wide">
                            <th class="pb-3 font-medium">Campaign</th>
                            <th class="pb-3 font-medium">Status</th>
                            <th class="pb-3 font-medium">Platforms</th>
                            <th class="pb-3 font-medium text-right">Budget</th>
                            <th class="pb-3 font-medium text-right">Spend</th>
                            <th class="pb-3 font-medium text-right">Revenue</th>
                            <th class="pb-3 font-medium text-right">ROAS</th>
                            <th class="pb-3 font-medium text-right">Conv.</th>
                            <th class="pb-3 font-medium text-center">Health</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topCampaigns as $campaign)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-2 font-medium text-gray-900 dark:text-white">{{ $campaign['name'] }}</td>
                                <td class="py-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ match($campaign['status']) {
                                            'active' => 'bg-green-100 text-green-800',
                                            'completed' => 'bg-blue-100 text-blue-800',
                                            'paused' => 'bg-yellow-100 text-yellow-800',
                                            default => 'bg-gray-100 text-gray-800',
                                        } }}">
                                        {{ ucfirst($campaign['status']) }}
                                    </span>
                                </td>
                                <td class="py-2 text-gray-500">
                                    @foreach($campaign['platforms'] as $p)
                                        <span class="inline-block w-2 h-2 rounded-full mr-0.5
                                            {{ match($p) { 'facebook' => 'bg-blue-500', 'instagram' => 'bg-pink-500', 'google' => 'bg-yellow-500', default => 'bg-gray-400' } }}"></span>
                                    @endforeach
                                </td>
                                <td class="py-2 text-right">{{ number_format($campaign['total_budget'], 0) }}</td>
                                <td class="py-2 text-right">{{ number_format($campaign['spend'], 2) }}</td>
                                <td class="py-2 text-right text-green-600 font-medium">{{ number_format($campaign['revenue'], 2) }}</td>
                                <td class="py-2 text-right font-semibold {{ $campaign['roas'] >= 2 ? 'text-green-600' : ($campaign['roas'] >= 1 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ number_format($campaign['roas'], 2) }}x
                                </td>
                                <td class="py-2 text-right">{{ number_format($campaign['conversions']) }}</td>
                                <td class="py-2 text-center">
                                    @php $score = $campaign['health_score']; @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold
                                        {{ match(true) {
                                            $score >= 80 => 'bg-green-100 text-green-700',
                                            $score >= 60 => 'bg-blue-100 text-blue-700',
                                            $score >= 40 => 'bg-yellow-100 text-yellow-700',
                                            $score >= 20 => 'bg-orange-100 text-orange-700',
                                            default => 'bg-red-100 text-red-700',
                                        } }}">
                                        {{ $score }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No campaign data available for this period.</p>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Active Campaigns Overview --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active Campaigns</h3>
            </div>
            <div class="p-4">
                @forelse($activeCampaigns as $campaign)
                    <div class="py-3 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                        <div class="flex justify-between items-start mb-1">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $campaign['name'] }}</span>
                                <span class="text-xs text-gray-500 ml-1">{{ $campaign['event_name'] }}</span>
                            </div>
                            @php $score = $campaign['health_score']; @endphp
                            <span class="text-xs font-bold {{ match(true) { $score >= 80 => 'text-green-600', $score >= 60 => 'text-blue-600', $score >= 40 => 'text-yellow-600', default => 'text-red-600' } }}">
                                {{ $this->getHealthLabel($score) }}
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 mb-1">
                            <div class="h-1.5 rounded-full {{ $campaign['budget_used_percent'] > 90 ? 'bg-red-500' : ($campaign['budget_used_percent'] > 70 ? 'bg-yellow-500' : 'bg-green-500') }}"
                                 style="width: {{ min(100, $campaign['budget_used_percent']) }}%"></div>
                        </div>
                        <div class="flex justify-between text-xs text-gray-500">
                            <span>Budget: {{ $campaign['budget_used_percent'] }}% used</span>
                            <span>ROAS: {{ $campaign['roas'] }}x | {{ $campaign['total_conversions'] }} conv.</span>
                            @if($campaign['days_remaining'] !== null)
                                <span>{{ $campaign['days_remaining'] }} days left</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No active campaigns.</p>
                @endforelse
            </div>
        </div>

        {{-- Pending Requests --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Requests</h3>
            </div>
            <div class="p-4">
                @forelse($pendingRequests as $request)
                    <div class="py-3 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $request['name'] }}</span>
                                <div class="text-xs text-gray-500 mt-0.5">
                                    {{ $request['tenant_name'] }} — {{ $request['event_name'] }}
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-medium text-sm text-gray-900 dark:text-white">{{ number_format($request['budget'], 0) }} {{ $request['currency'] }}</span>
                                <div class="text-xs text-gray-500">{{ $request['created_at'] }}</div>
                            </div>
                        </div>
                        <div class="flex gap-1 mt-1">
                            @foreach($request['platforms'] as $p)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                    {{ ucfirst($p) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500 dark:text-gray-400 text-center py-4">No pending requests.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Recent Optimizations Log --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Optimization Actions</h3>
            <p class="text-sm text-gray-500">Automatic and manual optimization events across all campaigns</p>
        </div>
        <div class="p-4">
            @forelse($recentOptimizations as $opt)
                <div class="flex items-start gap-3 py-2 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium mt-0.5
                        {{ $opt['source'] === 'auto' ? 'bg-blue-100 text-blue-700' : ($opt['source'] === 'ai_suggested' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700') }}">
                        {{ ucfirst($opt['source']) }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-900 dark:text-white">
                            <span class="font-medium">{{ $opt['campaign_name'] }}</span>
                            — {{ $opt['action'] }}
                        </div>
                        @if($opt['description'])
                            <div class="text-xs text-gray-500 truncate">{{ $opt['description'] }}</div>
                        @endif
                    </div>
                    <span class="text-xs text-gray-400 whitespace-nowrap">{{ $opt['created_at'] }}</span>
                </div>
            @empty
                <p class="text-gray-500 dark:text-gray-400 text-center py-4">No optimization actions recorded yet.</p>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
