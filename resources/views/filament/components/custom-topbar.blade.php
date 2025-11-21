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
 
<div class="sticky top-0 z-20 px-4 bg-white border-b border-gray-200 fi-custom-topbar dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between max-w-full gap-4">
        {{-- Center: Global Search --}}
        <div class="flex-1 hidden md:block">
            <div class="epas-global-search">
                <input
                    type="text"
                    id="epas-global-search-input"
                    placeholder="Search venues, artists, tenants, customers, users..."
                    class="dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 dark:border-gray-600"
                    autocomplete="off"
                >
                <div class="search-icon">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <div id="epas-search-results" class="hidden epas-search-results"></div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('epas-global-search-input');
            const resultsContainer = document.getElementById('epas-search-results');
            let debounceTimer;

            if (!searchInput || !resultsContainer) return;

            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                const query = this.value.trim();

                if (query.length < 2) {
                    resultsContainer.classList.add('hidden');
                    resultsContainer.innerHTML = '';
                    return;
                }

                debounceTimer = setTimeout(async () => {
                    try {
                        const response = await fetch(`{{ route('admin.api.global-search') }}?q=${encodeURIComponent(query)}`);
                        const data = await response.json();
                        displayResults(data);
                    } catch (error) {
                        console.error('Search error:', error);
                    }
                }, 300);
            });

            searchInput.addEventListener('focus', function() {
                if (resultsContainer.innerHTML && this.value.length >= 2) {
                    resultsContainer.classList.remove('hidden');
                }
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
                    resultsContainer.classList.add('hidden');
                }
            });

            function displayResults(data) {
                if (!Object.keys(data).length) {
                    resultsContainer.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No results found</div>';
                    resultsContainer.classList.remove('hidden');
                    return;
                }

                const icons = {
                    venues: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>',
                    artists: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>',
                    events: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
                    tenants: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
                    customers: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>',
                    users: '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>'
                };

                const labels = {
                    venues: 'Venues',
                    artists: 'Artists',
                    events: 'Events',
                    tenants: 'Tenants',
                    customers: 'Customers',
                    users: 'Users'
                };

                let html = '';
                for (const [type, items] of Object.entries(data)) {
                    html += `<div class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 dark:bg-gray-700/50 dark:text-gray-400">${labels[type] || type}</div>`;
                    for (const item of items) {
                        html += `
                            <a href="${item.url}" class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <span class="text-gray-400">${icons[type] || ''}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate dark:text-white">${item.name}</div>
                                    ${item.subtitle ? `<div class="text-xs text-gray-500 truncate dark:text-gray-400">${item.subtitle}</div>` : ''}
                                </div>
                            </a>
                        `;
                    }
                }

                resultsContainer.innerHTML = html;
                resultsContainer.classList.remove('hidden');
            }
        });
        </script>

        {{-- Right: Language Selector, Public Site Link & User Menu --}}
        <div class="flex items-center gap-3">
            {{-- Language Selector with Flags --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    type="button"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    <span class="text-lg">{{ $localeFlags[$currentLocale] ?? '🌐' }}</span>
                    <span class="font-medium uppercase">{{ $currentLocale }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div
                    x-show="open"
                    @click.away="open = false"
                    x-cloak
                    class="absolute right-0 z-50 w-48 py-1 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700"
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
                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
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
                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <div class="flex items-center justify-center w-8 h-8 text-xs font-semibold text-white rounded-full bg-primary-500">
                            {{ strtoupper(substr(filament()->auth()->user()->name, 0, 2)) }}
                        </div>
                        <span class="hidden font-medium md:inline">{{ filament()->auth()->user()->name }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-cloak
                        class="absolute right-0 z-50 w-48 py-1 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700"
                    >
                        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ filament()->auth()->user()->name }}</div>
                            <div class="text-xs text-gray-500">{{ filament()->auth()->user()->email }}</div>
                        </div>

                        <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
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
