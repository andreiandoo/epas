<x-filament-panels::page>
    <div class="space-y-6">
        @if($microservices->isEmpty())
            <div class="text-center py-12">
                <x-heroicon-o-puzzle-piece class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No active microservices</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Contact support to enable microservices for your account.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($microservices as $service)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-center gap-4">
                            @if($service->icon_image)
                                <img src="{{ Storage::url($service->icon_image) }}" alt="{{ $service->getTranslation('name', app()->getLocale()) }}" class="w-12 h-12 rounded-lg object-cover">
                            @else
                                <div class="w-12 h-12 rounded-lg bg-primary-100 dark:bg-primary-900 flex items-center justify-center">
                                    <x-heroicon-o-cube class="w-6 h-6 text-primary-600 dark:text-primary-400" />
                                </div>
                            @endif
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $service->getTranslation('name', app()->getLocale()) }}</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $service->getTranslation('short_description', app()->getLocale()) }}</p>
                            </div>
                        </div>
                        @if($service->pivot->activated_at)
                            <p class="mt-4 text-xs text-gray-400">Active since {{ \Carbon\Carbon::parse($service->pivot->activated_at)->format('d M Y') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
