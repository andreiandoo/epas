<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">{{ $tenantName }}</h1>
                <p class="text-sm text-gray-500">
                    {{ $teamMember?->user?->name }}
                    @if($leisureRole)
                        ·
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 text-xs">
                            {{ \App\Models\Leisure\TenantTeamMember::LEISURE_ROLES[$leisureRole] ?? $leisureRole }}
                        </span>
                    @endif
                </p>
            </div>
            <div class="text-right text-sm text-gray-500">
                {{ now()->format('l, d M Y') }}<br>
                <span x-data="{ now: '' }" x-init="setInterval(() => now = new Date().toLocaleTimeString('ro-RO'), 1000)" x-text="now" class="font-mono"></span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 bg-white dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Rentals active</div>
                <div class="text-3xl font-bold mt-2">{{ $stats['active_rentals'] }}</div>
            </div>
            <div class="rounded-xl border @if($stats['overdue_rentals'] > 0) border-red-300 dark:border-red-700 @else border-gray-200 dark:border-gray-700 @endif p-5 bg-white dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Depășire durată</div>
                <div class="text-3xl font-bold mt-2 @if($stats['overdue_rentals'] > 0) text-red-600 dark:text-red-400 @endif">
                    {{ $stats['overdue_rentals'] }}
                </div>
            </div>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-5 bg-white dark:bg-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Finalizate azi</div>
                <div class="text-3xl font-bold mt-2">{{ $stats['rentals_today'] }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @if(in_array($leisureRole, ['check_in', 'admin', 'pos_manager']))
                <a href="{{ url('/operator/check-in') }}"
                   class="flex items-center gap-4 p-5 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border-2 border-emerald-500 hover:bg-emerald-100 dark:hover:bg-emerald-900/50 transition">
                    <div class="p-3 rounded-lg bg-emerald-500 text-white text-2xl">📷</div>
                    <div>
                        <div class="font-semibold">Check-in bilete</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Scanare QR la intrare</div>
                    </div>
                </a>
            @endif

            @if(in_array($leisureRole, ['rental_operator', 'admin', 'pos_manager']))
                <a href="{{ url('/operator/active-rentals') }}"
                   class="flex items-center gap-4 p-5 rounded-xl bg-blue-50 dark:bg-blue-900/30 border-2 border-blue-500 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition">
                    <div class="p-3 rounded-lg bg-blue-500 text-white text-2xl">⏱️</div>
                    <div>
                        <div class="font-semibold">Rentals active</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Start / Stop curse</div>
                    </div>
                </a>
            @endif

            @if(in_array($leisureRole, ['pos_cashier', 'pos_manager', 'admin']))
                <a href="{{ url('/operator/pos') }}"
                   class="flex items-center gap-4 p-5 rounded-xl bg-violet-50 dark:bg-violet-900/30 border-2 border-violet-500 hover:bg-violet-100 dark:hover:bg-violet-900/50 transition">
                    <div class="p-3 rounded-lg bg-violet-500 text-white text-2xl">💳</div>
                    <div>
                        <div class="font-semibold">POS vânzări</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Bilete la fața locului</div>
                    </div>
                </a>
            @endif

            @if(in_array($leisureRole, ['inventory_manager', 'admin']))
                <a href="{{ url('/operator/inventory') }}"
                   class="flex items-center gap-4 p-5 rounded-xl bg-amber-50 dark:bg-amber-900/30 border-2 border-amber-500 hover:bg-amber-100 dark:hover:bg-amber-900/50 transition">
                    <div class="p-3 rounded-lg bg-amber-500 text-white text-2xl">📦</div>
                    <div>
                        <div class="font-semibold">Inventar fizic</div>
                        <div class="text-xs text-gray-600 dark:text-gray-400">Status echipamente</div>
                    </div>
                </a>
            @endif
        </div>
    </div>
</x-filament-panels::page>
