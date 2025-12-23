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

                {{-- Commission info --}}
                <div class="mt-4 pt-4 border-t border-white/20">
                    <div class="flex items-center gap-2 text-sm">
                        <span class="text-white/70">{{ __('Your commission rate:') }}</span>
                        <span class="font-semibold">{{ $affiliate->getFormattedCommission() }}</span>
                        <span class="text-white/70">{{ __('per sale') }}</span>
                    </div>
                </div>
            </div>

            {{-- Stats Grid --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
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
