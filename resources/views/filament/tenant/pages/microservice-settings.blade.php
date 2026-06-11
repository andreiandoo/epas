<x-filament-panels::page>
    <div class="mb-6">
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-6">
            <div class="flex items-start gap-4">
                @if($microservice->icon_image)
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 p-2 flex items-center justify-center">
                        <img src="{{ Storage::disk('public')->url($microservice->icon_image) }}"
                             class="w-full h-full object-contain"
                             alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}">
                    </div>
                @else
                    <div class="w-16 h-16 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                        <x-heroicon-o-puzzle-piece class="w-8 h-8 text-indigo-600 dark:text-indigo-400" />
                    </div>
                @endif
                <div class="flex-1">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ $microservice->getTranslation('name', app()->getLocale()) }}
                    </h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ $microservice->getTranslation('short_description', app()->getLocale()) }}
                    </p>
                    @if($activatedAt)
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-2 flex items-center gap-1">
                            <x-heroicon-o-calendar class="w-3.5 h-3.5" />
                            Active since {{ \Carbon\Carbon::parse($activatedAt)->format('M d, Y') }}
                        </p>
                    @endif
                </div>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
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
