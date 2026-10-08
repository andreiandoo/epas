<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Statistics Overview --}}
        <x-filament::section heading="{{ __('Statistics Overview') }}">
            <div class="grid grid-cols-4 gap-4">
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-3xl font-bold">{{ $stats['total_conversions'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">{{ __('Total Conversions') }}</div>
                </div>
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <div class="text-3xl font-bold text-green-600">{{ $stats['approved_conversions'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">{{ __('Approved') }}</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <div class="text-3xl font-bold text-yellow-600">{{ $stats['pending_conversions'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">{{ __('Pending') }}</div>
                </div>
                <div class="text-center p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                    <div class="text-3xl font-bold text-red-600">{{ $stats['reversed_conversions'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500">{{ __('Reversed') }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Commission Summary --}}
        <x-filament::section heading="{{ __('Commission Summary') }}">
            <div class="grid grid-cols-3 gap-4">
                <div class="text-center p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">{{ number_format($stats['total_commission'] ?? 0, 2) }} RON</div>
                    <div class="text-sm text-gray-500">{{ __('Total Commission Earned') }}</div>
                </div>
                <div class="text-center p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                    <div class="text-2xl font-bold text-yellow-600">{{ number_format($stats['pending_commission'] ?? 0, 2) }} RON</div>
                    <div class="text-sm text-gray-500">{{ __('Pending Commission') }}</div>
                </div>
                <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                    <div class="text-2xl font-bold">{{ number_format($stats['total_sales'] ?? 0, 2) }} RON</div>
                    <div class="text-sm text-gray-500">{{ __('Total Sales Generated') }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Affiliate Details --}}
        <x-filament::section heading="{{ __('Affiliate Details') }}">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <div class="text-sm text-gray-500">{{ __('Affiliate Code') }}</div>
                    <div class="font-bold text-lg">{{ $affiliate->code }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('Name') }}</div>
                    <div>{{ $affiliate->name }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('Email') }}</div>
                    <div>{{ $affiliate->contact_email }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('Status') }}</div>
                    <div>
                        <x-filament::badge :color="match($affiliate->status) { 'active' => 'success', 'suspended' => 'warning', default => 'gray' }">
                            {{ ucfirst($affiliate->status) }}
                        </x-filament::badge>
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('Commission Rate') }}</div>
                    <div>
                        @if($affiliate->commission_type === 'fixed')
                            {{ __(':commission_rate RON per order', ['commission_rate' => number_format($affiliate->commission_rate, 2)]) }}
                        @else
                            {{ __(':commission_rate% of order value', ['commission_rate' => $affiliate->commission_rate]) }}
                        @endif
                    </div>
                </div>
                <div>
                    <div class="text-sm text-gray-500">{{ __('Coupon Code') }}</div>
                    <div>{{ $coupon?->coupon_code ?? '-' }}</div>
                </div>
            </div>
        </x-filament::section>

        {{-- Tracking Links --}}
        <x-filament::section heading="{{ __('Tracking Links') }}" description="{{ __('Generate tracking links for this affiliate') }}">
            <div>
                <div class="text-sm text-gray-500 mb-1">{{ __('Default Tracking URL') }}</div>
                <div class="flex items-center gap-2">
                    <code class="bg-gray-100 dark:bg-gray-800 px-3 py-2 rounded flex-1">{{ $trackingUrl }}</code>
                    <x-filament::icon-button
                        icon="heroicon-o-clipboard"
                        x-data="{}"
                        x-on:click="navigator.clipboard.writeText('{{ $trackingUrl }}'); $tooltip(@js(__('Copied!')))"
                        label="{{ __('Copy') }}"
                    />
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
