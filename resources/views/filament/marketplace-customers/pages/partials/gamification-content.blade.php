            @php
                $pts = $gamification['points'] ?? null;
                $xp = $gamification['experience'] ?? null;
                $transactions = $gamification['transactions'] ?? collect();
                $badges = $gamification['badges'] ?? collect();
                $redemptions = $gamification['redemptions'] ?? collect();
            @endphp

            {{-- Points & XP Cards --}}
            <div class="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-3 lg:grid-cols-6">
                @foreach([
                    ['Sold Puncte', number_format($pts?->current_balance ?? 0), 'heroicon-o-star', 'text-amber-600'],
                    ['Total Câștigate', '+' . number_format($pts?->total_earned ?? 0), 'heroicon-o-arrow-trending-up', 'text-green-600'],
                    ['Total Cheltuite', '-' . number_format($pts?->total_spent ?? 0), 'heroicon-o-arrow-trending-down', 'text-red-600'],
                    ['Tier', $pts?->current_tier ?? 'N/A', 'heroicon-o-trophy', 'text-purple-600'],
                    ['XP Total', number_format($xp?->total_xp ?? 0), 'heroicon-o-bolt', 'text-cyan-600'],
                    ['Level', $xp?->current_level ?? 0, 'heroicon-o-signal', 'text-rose-600'],
                ] as [$label, $value, $icon, $color])
                    <div class="p-4 bg-white shadow-sm rounded-xl dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="flex items-center gap-2 mb-1">
                            <x-filament::icon :icon="$icon" class="w-5 h-5 {{ $color }}" />
                            <span class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ $label }}</span>
                        </div>
                        <div class="text-xl font-bold {{ $color }}">{{ $value }}</div>
                    </div>
                @endforeach
            </div>

            {{-- XP Progress --}}
            @if($xp)
                <div class="mb-6">
                    <x-filament::section>
                        <x-slot name="heading">Progres Level</x-slot>
                        <div class="flex justify-between mb-1">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Level {{ $xp->current_level }} → {{ $xp->current_level + 1 }}</span>
                            <span class="text-sm font-semibold">{{ $xp->xp_in_current_level ?? 0 }} / {{ $xp->xp_to_next_level ?? '?' }} XP</span>
                        </div>
                        <div class="w-full h-3 bg-gray-200 rounded-full dark:bg-gray-700">
                            @php $progress = ($xp->xp_to_next_level > 0) ? min(100, ($xp->xp_in_current_level / $xp->xp_to_next_level) * 100) : 0; @endphp
                            <div class="h-3 transition-all rounded-full bg-cyan-600" style="width: {{ $progress }}%"></div>
                        </div>
                        @if($xp->current_level_group)
                            <div class="mt-1 text-xs text-gray-500">Grup: <span class="font-medium">{{ $xp->current_level_group }}</span></div>
                        @endif
                    </x-filament::section>
                </div>
            @endif

            {{-- Badges --}}
            @if($badges->isNotEmpty())
                <div class="mb-6">
                    <x-filament::section>
                        <x-slot name="heading">Badge-uri Câștigate ({{ $badges->count() }})</x-slot>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach($badges as $cb)
                                <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 ring-1 ring-gray-200 dark:ring-gray-700">
                                    @if($cb->badge?->icon_url)
                                        <img src="{{ $cb->badge->icon_url }}" class="w-10 h-10 rounded" alt="">
                                    @else
                                        <div class="flex items-center justify-center w-10 h-10 text-lg rounded" style="background: {{ $cb->badge?->color ?? '#e5e7eb' }}">🏅</div>
                                    @endif
                                    <div class="min-w-0">
                                        @php
                                            $badgeName = $cb->badge?->name;
                                            if (is_array($badgeName)) $badgeName = $badgeName['ro'] ?? $badgeName['en'] ?? reset($badgeName);
                                        @endphp
                                        <div class="text-sm font-medium text-gray-900 truncate dark:text-gray-100">{{ $badgeName ?? 'Badge' }}</div>
                                        <div class="text-xs text-gray-500">{{ $cb->earned_at?->format('d.m.Y') ?? '' }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-filament::section>
                </div>
            @endif

            {{-- Points Transactions --}}
            <x-filament::section>
                <x-slot name="heading">Istoric Puncte (ultimele 20)</x-slot>
                @if($transactions->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-800">
                                <tr>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Data</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Tip</th>
                                    <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Puncte</th>
                                    <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Descriere</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach($transactions as $tx)
                                    @php
                                        $txDesc = $tx->description;
                                        if (is_array($txDesc)) $txDesc = $txDesc['ro'] ?? $txDesc['en'] ?? reset($txDesc) ?? '-';
                                        $txDesc = $txDesc ?: ($tx->admin_note ?? '-');
                                        $typeBadge = match($tx->type) {
                                            'earned' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                            'spent' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                            'expired' => 'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300',
                                            'adjusted' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
                                            'refunded' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                            default => 'bg-gray-100 text-gray-700',
                                        };
                                        $typeLabel = match($tx->type) {
                                            'earned' => 'Câștigate', 'spent' => 'Cheltuite', 'expired' => 'Expirate',
                                            'adjusted' => 'Ajustare', 'refunded' => 'Returnat', default => ucfirst($tx->type),
                                        };
                                    @endphp
                                    <tr>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $tx->created_at?->format('d.m.Y H:i') }}</td>
                                        <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-medium {{ $typeBadge }}">{{ $typeLabel }}</span></td>
                                        <td class="px-3 py-2 text-right font-semibold {{ $tx->points >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ $tx->points >= 0 ? '+' : '' }}{{ number_format($tx->points) }}</td>
                                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $txDesc }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-gray-500">Nu există tranzacții de puncte.</p>
                @endif
            </x-filament::section>

            {{-- Reward Redemptions --}}
            @if($redemptions->isNotEmpty())
                <div class="mt-6">
                    <x-filament::section>
                        <x-slot name="heading">Recompense Revendicate</x-slot>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Recompensă</th>
                                        <th class="px-3 py-2 font-medium text-right text-gray-600 dark:text-gray-300">Puncte</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Voucher</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Status</th>
                                        <th class="px-3 py-2 font-medium text-left text-gray-600 dark:text-gray-300">Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach($redemptions as $rd)
                                        @php
                                            $rwName = $rd->reward?->name;
                                            if (is_array($rwName)) $rwName = $rwName['ro'] ?? $rwName['en'] ?? reset($rwName);
                                        @endphp
                                        <tr>
                                            <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $rwName ?? 'N/A' }}</td>
                                            <td class="px-3 py-2 font-semibold text-right text-red-600">-{{ number_format($rd->points_spent ?? 0) }}</td>
                                            <td class="px-3 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $rd->voucher_code ?? '-' }}</td>
                                            <td class="px-3 py-2">
                                                <span class="px-2 py-0.5 rounded text-xs font-medium
                                                    {{ match($rd->status) {
                                                        'active' => 'bg-green-100 text-green-700',
                                                        'used' => 'bg-blue-100 text-blue-700',
                                                        'expired' => 'bg-orange-100 text-orange-700',
                                                        'cancelled' => 'bg-red-100 text-red-700',
                                                        default => 'bg-gray-100 text-gray-700',
                                                    } }}">{{ ucfirst($rd->status) }}</span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $rd->created_at?->format('d.m.Y') }}</td>
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
             TAB 3: COMENZI & BILETE
