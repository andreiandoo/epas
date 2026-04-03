<div class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        {{-- Account --}}
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">Account</h4>
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Number</dt><dd class="font-medium">{{ $account->account_number }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd><span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $account->status?->value === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">{{ $account->status?->value ?? $account->status }}</span></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Edition</dt><dd>{{ $account->edition?->name ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Wristband</dt><dd>{{ $account->wristband?->uid ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Activated</dt><dd>{{ $account->activated_at?->format('d M Y H:i') ?? '-' }}</dd></div>
            </dl>
        </div>

        {{-- Customer --}}
        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
            <h4 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-2">Customer</h4>
            @if($account->customer)
            <dl class="space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Name</dt><dd class="font-medium">{{ $account->customer->first_name }} {{ $account->customer->last_name }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Email</dt><dd>{{ $account->customer->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Phone</dt><dd>{{ $account->customer->phone ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">City</dt><dd>{{ $account->customer->city ?? '-' }}</dd></div>
            </dl>
            @else
            <p class="text-sm text-gray-400">No customer linked</p>
            @endif
        </div>
    </div>

    {{-- Balances --}}
    <div class="grid grid-cols-4 gap-3">
        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg text-center">
            <div class="text-xs text-gray-500 dark:text-gray-400">Balance</div>
            <div class="text-lg font-bold text-green-700 dark:text-green-300">{{ number_format(($account->balance_cents ?? 0) / 100, 2) }} RON</div>
        </div>
        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
            <div class="text-xs text-gray-500 dark:text-gray-400">Top-ups</div>
            <div class="text-lg font-bold text-blue-700 dark:text-blue-300">{{ number_format(($account->total_topped_up_cents ?? 0) / 100, 2) }} RON</div>
        </div>
        <div class="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg text-center">
            <div class="text-xs text-gray-500 dark:text-gray-400">Spent</div>
            <div class="text-lg font-bold text-orange-700 dark:text-orange-300">{{ number_format(($account->total_spent_cents ?? 0) / 100, 2) }} RON</div>
        </div>
        <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-center">
            <div class="text-xs text-gray-500 dark:text-gray-400">Cashed Out</div>
            <div class="text-lg font-bold text-purple-700 dark:text-purple-300">{{ number_format(($account->total_cashed_out_cents ?? 0) / 100, 2) }} RON</div>
        </div>
    </div>
</div>
