@php
    $localeFlags = [
        'en' => '🇬🇧',
        'ro' => '🇷🇴',
        'de' => '🇩🇪',
        'fr' => '🇫🇷',
        'es' => '🇪🇸'
    ];
    $localeNames = [
        'en' => 'English',
        'ro' => 'Română',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español'
    ];
    $currentLocale = app()->getLocale();
@endphp

<div class="fi-custom-topbar sticky top-0 z-20 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-4 py-3 mb-6">
    <div class="flex items-center justify-between max-w-full">
        {{-- Left: Sidebar Toggle Button --}}
        <div class="flex items-center gap-3">
            <button
                x-data
                @click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
                type="button"
                class="fi-icon-btn fi-icon-btn-sm flex items-center justify-center rounded-lg text-gray-400 outline-none transition duration-75 hover:text-gray-500 focus-visible:bg-gray-500/10 dark:text-gray-500 dark:hover:text-gray-400 dark:focus-visible:bg-gray-400/10 p-2 lg:hidden"
            >
                <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>
        </div>

        {{-- Right: Language Selector, Public Site Link & User Menu --}}
        <div class="flex items-center gap-3">
            {{-- Language Selector with Flags --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    type="button"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
                >
                    <span class="text-lg">{{ $localeFlags[$currentLocale] ?? '🌐' }}</span>
                    <span class="uppercase font-medium">{{ $currentLocale }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div
                    x-show="open"
                    @click.away="open = false"
                    x-cloak
                    class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50"
                >
                    @foreach($localeNames as $locale => $name)
                        <a
                            href="{{ url()->current() }}?locale={{ $locale }}"
                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $currentLocale === $locale ? 'bg-gray-50 dark:bg-gray-700/50 font-semibold' : '' }}"
                        >
                            <span class="text-lg">{{ $localeFlags[$locale] }}</span>
                            <div class="flex-1">
                                <div class="font-medium">{{ $name }}</div>
                                <div class="text-xs text-gray-500 uppercase">{{ $locale }}</div>
                            </div>
                            @if($currentLocale === $locale)
                                <svg class="w-4 h-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Public Site Link --}}
            <a
                href="{{ url('/' . $currentLocale) }}"
                target="_blank"
                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
                title="View Public Site"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                <span class="hidden md:inline">Public Site</span>
            </a>

            {{-- User Account Menu --}}
            @if(filament()->auth()->check())
                <div x-data="{ open: false }" class="relative">
                    <button
                        @click="open = !open"
                        type="button"
                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition"
                    >
                        <div class="w-8 h-8 rounded-full bg-primary-500 flex items-center justify-center text-white font-semibold text-xs">
                            {{ strtoupper(substr(filament()->auth()->user()->name, 0, 2)) }}
                        </div>
                        <span class="hidden md:inline font-medium">{{ filament()->auth()->user()->name }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-cloak
                        class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 py-1 z-50"
                    >
                        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ filament()->auth()->user()->name }}</div>
                            <div class="text-xs text-gray-500">{{ filament()->auth()->user()->email }}</div>
                        </div>

                        <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Logout
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
