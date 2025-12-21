@php
    $user = auth('organizer')->user();
    $organizer = $user?->organizer;
@endphp

<div class="flex items-center gap-4">
    {{-- Organizer Status Badge --}}
    @if($organizer)
        <span @class([
            'px-2 py-1 text-xs font-medium rounded-full',
            'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' => $organizer->status === 'active',
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100' => $organizer->status === 'pending_approval',
            'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' => $organizer->status === 'suspended',
        ])>
            {{ ucfirst(str_replace('_', ' ', $organizer->status)) }}
        </span>
    @endif

    {{-- Pending Payout Indicator --}}
    @if($organizer && $organizer->pending_payout > 0)
        <div class="hidden sm:flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
            <x-heroicon-s-banknotes class="w-4 h-4" />
            <span>{{ number_format($organizer->pending_payout, 2) }} RON</span>
        </div>
    @endif

    {{-- User Info --}}
    @if($user)
        <div class="hidden sm:flex items-center gap-2">
            <div class="text-right">
                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $user->name }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">
                    {{ ucfirst($user->role) }}
                </div>
            </div>
        </div>
    @endif
</div>
