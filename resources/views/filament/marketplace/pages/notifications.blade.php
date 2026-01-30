<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-primary-100 dark:bg-primary-900/30">
                        <x-heroicon-o-bell class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Total notificari</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($totalCount) }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-warning-100 dark:bg-warning-900/30">
                        <x-heroicon-o-bell-alert class="w-6 h-6 text-warning-600 dark:text-warning-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Necitite</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($unreadCount) }}</p>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <div class="flex items-center gap-4">
                    <div class="p-3 rounded-lg bg-success-100 dark:bg-success-900/30">
                        <x-heroicon-o-calendar class="w-6 h-6 text-success-600 dark:text-success-400" />
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Astazi</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($todayCount) }}</p>
                    </div>
                </div>
            </x-filament::section>
        </div>

        {{-- Filters --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center gap-4">
                <div class="w-48">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Tip notificare</label>
                    <select wire:model.live="filterType" class="w-full mt-1 border-gray-300 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Toate tipurile</option>
                        @foreach($typeLabels as $type => $label)
                            <option value="{{ $type }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-48">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                    <select wire:model.live="filterStatus" class="w-full mt-1 border-gray-300 rounded-lg shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white focus:border-primary-500 focus:ring-primary-500">
                        <option value="">Toate</option>
                        <option value="unread">Necitite</option>
                        <option value="read">Citite</option>
                    </select>
                </div>
            </div>
        </x-filament::section>

        {{-- Notifications List --}}
        <x-filament::section>
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($notifications as $notification)
                    <div class="flex items-start gap-4 py-4 {{ !$notification->isRead() ? 'bg-primary-50/50 dark:bg-primary-900/10' : '' }} px-4 -mx-4 first:rounded-t-lg last:rounded-b-lg">
                        {{-- Icon --}}
                        <div class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-full
                            @switch($notification->color ?? $notification->default_color)
                                @case('success') bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400 @break
                                @case('warning') bg-yellow-100 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400 @break
                                @case('danger') bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400 @break
                                @case('info') bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 @break
                                @default bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400
                            @endswitch
                        ">
                            <x-heroicon-o-bell class="w-5 h-5" />
                        </div>

                        {{-- Content --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white {{ !$notification->isRead() ? 'font-bold' : '' }}">
                                        {{ $notification->title }}
                                    </p>
                                    @if($notification->message)
                                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $notification->message }}
                                        </p>
                                    @endif
                                    <div class="flex items-center gap-3 mt-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @switch($notification->color ?? $notification->default_color)
                                                @case('success') bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 @break
                                                @case('warning') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 @break
                                                @case('danger') bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 @break
                                                @case('info') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 @break
                                                @default bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-400
                                            @endswitch
                                        ">
                                            {{ $notification->type_label }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-500">
                                            {{ $notification->created_at->format('d.m.Y H:i') }} ({{ $notification->time_ago }})
                                        </span>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center gap-2">
                                    @if($notification->action_url)
                                        <a href="{{ $notification->action_url }}" target="_blank" class="p-2 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400" title="Vezi">
                                            <x-heroicon-o-eye class="w-5 h-5" />
                                        </a>
                                    @endif

                                    @if($notification->isRead())
                                        <button wire:click="markAsUnread({{ $notification->id }})" class="p-2 text-gray-500 hover:text-warning-600 dark:hover:text-warning-400" title="Marcheaza necitit">
                                            <x-heroicon-o-envelope class="w-5 h-5" />
                                        </button>
                                    @else
                                        <button wire:click="markAsRead({{ $notification->id }})" class="p-2 text-gray-500 hover:text-success-600 dark:hover:text-success-400" title="Marcheaza citit">
                                            <x-heroicon-o-check class="w-5 h-5" />
                                        </button>
                                    @endif

                                    <button wire:click="deleteNotification({{ $notification->id }})" wire:confirm="Esti sigur ca vrei sa stergi aceasta notificare?" class="p-2 text-gray-500 hover:text-danger-600 dark:hover:text-danger-400" title="Sterge">
                                        <x-heroicon-o-trash class="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12">
                        <x-heroicon-o-bell-slash class="w-12 h-12 text-gray-300 dark:text-gray-600" />
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Nu exista notificari</p>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($notifications->hasPages())
                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                    {{ $notifications->links() }}
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
