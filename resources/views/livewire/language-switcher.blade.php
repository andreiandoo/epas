<div x-data="{ open: false }" class="relative">
    <button
        @click="open = !open"
        type="button"
        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
    >
        <span class="flex-shrink-0">{!! $localeFlags[$currentLocale] ?? '' !!}</span>
        <span class="font-medium uppercase">{{ $currentLocale }}</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 z-50 w-48 py-1 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700"
    >
        @foreach($localeNames as $locale => $name)
            <button
                wire:click="setLocale('{{ $locale }}')"
                @click="open = false"
                type="button"
                class="flex items-center w-full gap-3 px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $currentLocale === $locale ? 'bg-gray-50 dark:bg-gray-700/50 font-semibold' : '' }}"
            >
                <span class="flex-shrink-0">{!! $localeFlags[$locale] !!}</span>
                <div class="flex-1">
                    <div class="font-medium">{{ $name }}</div>
                    <div class="text-xs text-gray-500 uppercase">{{ $locale }}</div>
                </div>
                @if($currentLocale === $locale)
                    <svg class="w-4 h-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                @endif
            </button>
        @endforeach
    </div>
</div>
