<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="bg-gradient-to-r from-gray-700 to-gray-800 rounded-2xl p-6 text-white shadow-xl">
            <div class="flex items-center gap-4">
                @if($microservice->icon_image)
                    <img src="{{ Storage::url($microservice->icon_image) }}"
                         alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                         class="w-16 h-16 rounded-xl object-cover bg-white/10">
                @else
                    <div class="w-16 h-16 bg-white/20 rounded-xl flex items-center justify-center">
                        <x-heroicon-o-cog-6-tooth class="w-8 h-8" />
                    </div>
                @endif
                <div>
                    <h2 class="text-2xl font-bold">{{ $microservice->getTranslation('name', app()->getLocale()) }}</h2>
                    <p class="text-gray-300 text-sm">{{ $microservice->getTranslation('short_description', app()->getLocale()) }}</p>
                </div>
            </div>
        </div>

        {{-- Info Card --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6">
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    <x-heroicon-o-information-circle class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <h3 class="font-semibold text-blue-900 dark:text-blue-100 mb-2">Settings Managed by Platform</h3>
                    <p class="text-blue-700 dark:text-blue-300 text-sm">
                        {{ $message }}
                    </p>
                    <p class="text-blue-600 dark:text-blue-400 text-sm mt-2">
                        This microservice is active for your marketplace. Core configuration is managed at the platform level to ensure consistency and security.
                    </p>
                </div>
            </div>
        </div>

        {{-- Status Card --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Status</h3>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                    <span class="w-2 h-2 rounded-full bg-green-500 mr-2 animate-pulse"></span>
                    Active
                </span>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    This microservice is enabled for your marketplace
                </span>
            </div>
        </div>

        {{-- Quick Links --}}
        @php
            $quickLinks = match($microservice->slug) {
                'invitations' => [
                    ['label' => 'Manage Invitations', 'url' => route('filament.marketplace.pages.invitations'), 'icon' => 'heroicon-o-envelope'],
                ],
                'analytics' => [
                    ['label' => 'View Analytics Dashboard', 'url' => route('filament.marketplace.pages.analytics-dashboard'), 'icon' => 'heroicon-o-chart-bar'],
                ],
                'ticket-customizer' => [
                    ['label' => 'Design Tickets', 'url' => route('filament.marketplace.pages.ticket-customizer'), 'icon' => 'heroicon-o-ticket'],
                ],
                'gamification' => [
                    ['label' => 'Gamification Settings', 'url' => route('filament.marketplace.resources.gamification-settings.index'), 'icon' => 'heroicon-o-star'],
                ],
                'tracking-pixels-manager' => [
                    ['label' => 'Tracking Settings', 'url' => route('filament.marketplace.pages.tracking-settings'), 'icon' => 'heroicon-o-chart-bar'],
                ],
                'whatsapp-notifications' => [
                    ['label' => 'WhatsApp Settings', 'url' => route('filament.marketplace.pages.whatsapp-notifications'), 'icon' => 'heroicon-o-chat-bubble-left'],
                ],
                'group-booking' => [
                    ['label' => 'Group Bookings', 'url' => route('filament.marketplace.pages.group-booking'), 'icon' => 'heroicon-o-user-group'],
                ],
                default => [],
            };
        @endphp

        @if(count($quickLinks) > 0)
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($quickLinks as $link)
                        <a href="{{ $link['url'] }}"
                           class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                            <div class="w-10 h-10 rounded-lg bg-primary-100 dark:bg-primary-900/50 flex items-center justify-center">
                                <x-dynamic-component :component="$link['icon']" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
                            </div>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
