<div>
    @if(!$affiliate)
        {{-- No affiliate account --}}
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('No Affiliate Account') }}</h3>
            <p class="text-gray-500 mb-4">{{ __('You don\'t have an affiliate account yet.') }}</p>
            <a href="{{ route('customer.account', ['tenant' => $tenant->slug]) }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                {{ __('Join the Affiliate Program') }}
            </a>
        </div>

    @elseif($affiliate->status === 'pending')
        {{-- Pending approval --}}
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-yellow-900">{{ __('Application Pending') }}</h3>
                    <p class="mt-1 text-sm text-yellow-700">
                        {{ __('Your affiliate application is under review. We will notify you once it is approved.') }}
                    </p>
                </div>
            </div>
        </div>

    @elseif($affiliate->status !== 'active')
        {{-- Inactive/Suspended --}}
        <div class="bg-red-50 border border-red-200 rounded-xl p-6">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-medium text-red-900">{{ __('Account :status', ['status' => $affiliate->getStatusLabel()]) }}</h3>
                    <p class="mt-1 text-sm text-red-700">
                        {{ __('Your affiliate account is not currently active. Please contact support for assistance.') }}
                    </p>
                </div>
            </div>
        </div>

    @else
        {{-- Active affiliate dashboard --}}
        <div class="space-y-6">
            {{-- Header with affiliate link --}}
            <div class="bg-gradient-to-r from-indigo-600 to-purple-600 rounded-xl p-6 text-white">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold mb-1">{{ __('Your Affiliate Link') }}</h2>
                        <p class="text-white/80 text-sm">{{ __('Share this link to earn commissions on sales') }}</p>
                    </div>
                    <div class="flex items-center gap-2 bg-white/10 rounded-lg p-2">
                        <input type="text"
                               readonly
                               value="{{ $trackingUrl }}"
                               class="flex-1 bg-transparent border-none text-white text-sm focus:ring-0 min-w-0"
                               id="affiliate-link">
                        <button type="button"
                                wire:click="copyUrl"
                                onclick="navigator.clipboard.writeText('{{ $trackingUrl }}')"
                                class="px-4 py-2 text-sm font-medium text-indigo-600 bg-white rounded-lg hover:bg-gray-100 transition flex-shrink-0">
                            {{ $urlCopied ? __('Copied!') : __('Copy') }}
                        </button>
                    </div>
                </div>

                {{-- Commission info and share buttons --}}
                <div class="mt-4 pt-4 border-t border-white/20 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-white/70">{{ __('Your commission rate:') }}</span>
                        <span class="font-semibold">{{ $affiliate->getFormattedCommission() }}</span>
                        <span class="text-white/70">{{ __('per sale') }}</span>
                    </div>

                    {{-- Social Share Buttons --}}
                    <div class="flex items-center gap-2">
                        <span class="text-white/70 text-sm mr-1">{{ __('Share:') }}</span>
                        {{-- Facebook --}}
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($trackingUrl) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition"
                           title="Facebook">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.77 7.46H14.5v-1.9c0-.9.6-1.1 1-1.1h3V.5h-4.33C10.24.5 9.5 3.44 9.5 5.32v2.15h-3v4h3v12h5v-12h3.85l.42-4z"/></svg>
                        </a>
                        {{-- Twitter/X --}}
                        <a href="https://twitter.com/intent/tweet?url={{ urlencode($trackingUrl) }}&text={{ urlencode(__('Check this out!')) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition"
                           title="Twitter/X">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        </a>
                        {{-- WhatsApp --}}
                        <a href="https://wa.me/?text={{ urlencode($trackingUrl) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition"
                           title="WhatsApp">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        </a>
                        {{-- Telegram --}}
                        <a href="https://t.me/share/url?url={{ urlencode($trackingUrl) }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition"
                           title="Telegram">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                        </a>
                        {{-- Email --}}
                        <a href="mailto:?subject={{ urlencode(__('Check this out!')) }}&body={{ urlencode($trackingUrl) }}"
                           class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition"
                           title="Email">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Stats Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                {{-- Clicks --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('Total Clicks') }}</div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_clicks'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ number_format($stats['clicks_this_month'] ?? 0) }} {{ __('this month') }}
                    </div>
                </div>

                {{-- Conversions --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('Conversions') }}</div>
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_conversions'] ?? 0) }}</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ number_format($stats['pending_conversions'] ?? 0) }} {{ __('pending') }}
                    </div>
                </div>

                {{-- Conversion Rate --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('Conversion Rate') }}</div>
                    @php
                        $conversionRate = ($stats['total_clicks'] ?? 0) > 0
                            ? (($stats['total_conversions'] ?? 0) / $stats['total_clicks']) * 100
                            : 0;
                    @endphp
                    <div class="text-2xl font-bold text-gray-900">{{ number_format($conversionRate, 1) }}%</div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ __('clicks to sales') }}
                    </div>
                </div>

                {{-- Total Sales Generated --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('Sales Generated') }}</div>
                    <div class="text-2xl font-bold text-gray-900">
                        {{ number_format($stats['total_sales'] ?? 0, 2) }}
                        <span class="text-sm font-normal text-gray-400">{{ $settings->currency ?? 'RON' }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ __('from referrals') }}
                    </div>
                </div>

                {{-- Total Earned --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('Total Earned') }}</div>
                    <div class="text-2xl font-bold text-green-600">
                        {{ number_format(($stats['total_commission'] ?? 0) + ($stats['pending_commission'] ?? 0), 2) }}
                        <span class="text-sm font-normal text-gray-400">{{ $settings->currency ?? 'RON' }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ number_format($stats['pending_commission'] ?? 0, 2) }} {{ __('pending') }}
                    </div>
                </div>

                {{-- Available Balance --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ __('Available Balance') }}</div>
                    <div class="text-2xl font-bold text-indigo-600">
                        {{ number_format($affiliate->available_balance, 2) }}
                        <span class="text-sm font-normal text-gray-400">{{ $settings->currency ?? 'RON' }}</span>
                    </div>
                    @if($affiliate->available_balance >= ($settings->min_withdrawal_amount ?? 50))
                        <button wire:click="openWithdrawalModal"
                                class="mt-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                            {{ __('Request Withdrawal') }}
                        </button>
                    @else
                        <div class="text-xs text-gray-500 mt-1">
                            {{ __('Min. :amount to withdraw', ['amount' => number_format($settings->min_withdrawal_amount ?? 50, 2)]) }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Coupon Codes Section --}}
            @if($this->activeCoupons->isNotEmpty())
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">{{ __('Your Coupon Codes') }}</h3>
                        <p class="text-sm text-gray-500 mt-1">{{ __('Share these codes with your audience for additional tracking and discounts.') }}</p>
                    </div>
                    <div class="p-6">
                        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($this->activeCoupons as $coupon)
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg p-4 border border-gray-200 border-dashed">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-medium text-gray-500 uppercase">{{ __('Coupon Code') }}</span>
                                        @if($coupon->expires_at)
                                            <span class="text-xs text-gray-400">
                                                {{ __('Expires') }} {{ $coupon->expires_at->format('d M Y') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <code class="text-lg font-bold text-indigo-600 tracking-wider">{{ $coupon->coupon_code }}</code>
                                        <button type="button"
                                                onclick="navigator.clipboard.writeText('{{ $coupon->coupon_code }}')"
                                                class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition"
                                                title="{{ __('Copy code') }}">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between text-xs">
                                        <span class="text-green-600 font-medium">
                                            @if($coupon->discount_type === 'percent')
                                                {{ number_format($coupon->discount_value, 0) }}% {{ __('off') }}
                                            @else
                                                {{ number_format($coupon->discount_value, 2) }} {{ $settings->currency ?? 'RON' }} {{ __('off') }}
                                            @endif
                                        </span>
                                        @if($coupon->max_uses)
                                            <span class="text-gray-400">
                                                {{ $coupon->used_count ?? 0 }}/{{ $coupon->max_uses }} {{ __('used') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Two column layout for conversions and withdrawals --}}
            <div class="grid lg:grid-cols-2 gap-6">
                {{-- Recent Conversions --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">{{ __('Recent Conversions') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($this->recentConversions as $conversion)
                            <div class="px-6 py-4 flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $conversion->order_ref }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $conversion->created_at->format('d M Y H:i') }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-green-600">
                                        +{{ number_format($conversion->commission_value, 2) }} {{ $settings->currency ?? 'RON' }}
                                    </div>
                                    <div class="text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $conversion->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $conversion->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $conversion->status === 'reversed' ? 'bg-red-100 text-red-800' : '' }}">
                                            {{ ucfirst($conversion->status) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-gray-500 text-sm">
                                {{ __('No conversions yet. Share your link to start earning!') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Recent Withdrawals --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">{{ __('Recent Withdrawals') }}</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($this->recentWithdrawals as $withdrawal)
                            <div class="px-6 py-4 flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $withdrawal->reference }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $withdrawal->created_at->format('d M Y H:i') }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ number_format($withdrawal->amount, 2) }} {{ $withdrawal->currency }}
                                    </div>
                                    <div class="text-xs">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                            {{ $withdrawal->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                            {{ $withdrawal->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                            {{ $withdrawal->status === 'processing' ? 'bg-blue-100 text-blue-800' : '' }}
                                            {{ $withdrawal->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $withdrawal->status === 'cancelled' ? 'bg-gray-100 text-gray-800' : '' }}">
                                            {{ $withdrawal->getStatusLabel() }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-8 text-center text-gray-500 text-sm">
                                {{ __('No withdrawals yet.') }}
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Pending balance info --}}
            @if($affiliate->pending_balance > 0)
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-900">{{ __('Pending Balance') }}</h4>
                            <p class="text-sm text-blue-700 mt-1">
                                {{ __('You have :amount :currency in pending commissions. These will become available after the :days-day hold period.', [
                                    'amount' => number_format($affiliate->pending_balance, 2),
                                    'currency' => $settings->currency ?? 'RON',
                                    'days' => $settings->commission_hold_days ?? 30,
                                ]) }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Withdrawal Modal --}}
        @if($showWithdrawalModal)
            <div class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    {{-- Background overlay --}}
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" wire:click="closeWithdrawalModal"></div>

                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                    {{-- Modal panel --}}
                    <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                        <div class="bg-white px-6 pt-6 pb-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Request Withdrawal') }}</h3>

                            @if($withdrawalError)
                                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <p class="text-sm text-red-700">{{ $withdrawalError }}</p>
                                </div>
                            @endif

                            @if($withdrawalSuccess)
                                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg">
                                    <p class="text-sm text-green-700">{{ $withdrawalSuccess }}</p>
                                </div>
                            @else
                                <div class="space-y-4">
                                    {{-- Amount --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            {{ __('Amount') }} ({{ __('Available:') }} {{ number_format($affiliate->available_balance, 2) }} {{ $settings->currency ?? 'RON' }})
                                        </label>
                                        <input type="number"
                                               wire:model="withdrawalAmount"
                                               step="0.01"
                                               min="{{ $settings->min_withdrawal_amount ?? 50 }}"
                                               max="{{ $affiliate->available_balance }}"
                                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <p class="text-xs text-gray-500 mt-1">
                                            {{ __('Minimum: :amount :currency', ['amount' => number_format($settings->min_withdrawal_amount ?? 50, 2), 'currency' => $settings->currency ?? 'RON']) }}
                                        </p>
                                    </div>

                                    {{-- Payment Method --}}
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Payment Method') }}</label>
                                        <select wire:model.live="paymentMethod"
                                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach($settings->getPaymentMethodOptions() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    {{-- Payment Details based on method --}}
                                    @if($paymentMethod === 'bank_transfer')
                                        <div class="space-y-3">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Bank Name') }}</label>
                                                <input type="text" wire:model="bankName" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="e.g., ING Bank">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('IBAN') }}</label>
                                                <input type="text" wire:model="iban" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="RO49AAAA1B31007593840000">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account Holder Name') }}</label>
                                                <input type="text" wire:model="accountHolder" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                        </div>
                                    @elseif($paymentMethod === 'paypal')
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('PayPal Email') }}</label>
                                            <input type="email" wire:model="paypalEmail" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    @elseif($paymentMethod === 'revolut')
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Revolut Tag or Phone') }}</label>
                                            <input type="text" wire:model="revolutTag" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="@username or +40...">
                                        </div>
                                    @elseif($paymentMethod === 'wise')
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Wise Email') }}</label>
                                            <input type="email" wire:model="wiseEmail" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-3">
                            <button type="button"
                                    wire:click="closeWithdrawalModal"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                {{ $withdrawalSuccess ? __('Close') : __('Cancel') }}
                            </button>
                            @if(!$withdrawalSuccess)
                                <button type="button"
                                        wire:click="requestWithdrawal"
                                        wire:loading.attr="disabled"
                                        class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="requestWithdrawal">{{ __('Submit Request') }}</span>
                                    <span wire:loading wire:target="requestWithdrawal">{{ __('Processing...') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
