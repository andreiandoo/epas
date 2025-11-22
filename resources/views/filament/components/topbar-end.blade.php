<div class="fi-topbar-end flex items-center gap-4">
    {{-- Language Selector --}}
    <div x-data="{ open: false }" class="relative">
        <button
            @click="open = !open"
            type="button"
            class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
            </svg>
            <span class="uppercase">{{ app()->getLocale() }}</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>

        <div
            x-show="open"
            @click.away="open = false"
            x-cloak
            class="absolute right-0 mt-2 w-36 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50"
        >
            @foreach(['en' => 'English', 'ro' => 'Română', 'de' => 'Deutsch', 'fr' => 'Français', 'es' => 'Español'] as $locale => $name)
                <a
                    href="{{ url()->current() }}?locale={{ $locale }}"
                    class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 {{ app()->getLocale() === $locale ? 'bg-gray-50 dark:bg-gray-700/50 font-semibold' : '' }}"
                >
                    <span class="uppercase text-xs text-gray-500 mr-2">{{ $locale }}</span>
                    {{ $name }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Public Site Link --}}
    <a
        href="{{ url('/') }}"
        target="_blank"
        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
        title="View Public Site"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
        </svg>
        <span class="hidden md:inline">Public Site</span>
    </a>
</div>
