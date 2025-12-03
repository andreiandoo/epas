<x-filament-panels::page>
    <div class="mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-6">
            <div class="flex items-start gap-4">
                @if($microservice->icon_image)
                    <img src="{{ Storage::disk('public')->url($microservice->icon_image) }}"
                         class="w-16 h-16 rounded-lg object-contain"
                         alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}">
                @else
                    <div class="w-16 h-16 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <x-heroicon-o-puzzle-piece class="w-8 h-8 text-indigo-600" />
                    </div>
                @endif
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $microservice->getTranslation('name', app()->getLocale()) }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $microservice->getTranslation('short_description', app()->getLocale()) }}
                    </p>
                    @if($tenantMicroservice->activated_at)
                        <p class="text-xs text-gray-500 mt-2">
                            Active since {{ \Carbon\Carbon::parse($tenantMicroservice->activated_at)->format('M d, Y') }}
                        </p>
                    @endif
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    Active
                </span>
            </div>
        </div>
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" size="lg">
                Save Settings
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
