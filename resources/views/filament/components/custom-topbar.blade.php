@php
    // Using inline SVG flags for consistent rendering across all browsers/systems
    $localeFlags = [
        'en' => '<svg class="w-5 h-5 rounded-sm shadow-sm" viewBox="0 0 60 30" xmlns="http://www.w3.org/2000/svg"><clipPath id="s"><path d="M0,0 v30 h60 v-30 z"/></clipPath><clipPath id="t"><path d="M30,15 h30 v15 z v15 h-30 z h-30 v-15 z v-15 h30 z"/></clipPath><g clip-path="url(#s)"><path d="M0,0 v30 h60 v-30 z" fill="#012169"/><path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/><path d="M0,0 L60,30 M60,0 L0,30" clip-path="url(#t)" stroke="#C8102E" stroke-width="4"/><path d="M30,0 v30 M0,15 h60" stroke="#fff" stroke-width="10"/><path d="M30,0 v30 M0,15 h60" stroke="#C8102E" stroke-width="6"/></g></svg>',
        'ro' => '<svg class="w-5 h-5 rounded-sm shadow-sm" viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg"><rect width="1" height="2" x="0" fill="#002B7F"/><rect width="1" height="2" x="1" fill="#FCD116"/><rect width="1" height="2" x="2" fill="#CE1126"/></svg>',
        'de' => '<svg class="w-5 h-5 rounded-sm shadow-sm" viewBox="0 0 5 3" xmlns="http://www.w3.org/2000/svg"><rect width="5" height="3" y="0" fill="#000"/><rect width="5" height="2" y="1" fill="#D00"/><rect width="5" height="1" y="2" fill="#FFCE00"/></svg>',
        'fr' => '<svg class="w-5 h-5 rounded-sm shadow-sm" viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg"><rect width="1" height="2" fill="#002395"/><rect width="1" height="2" x="1" fill="#FFF"/><rect width="1" height="2" x="2" fill="#ED2939"/></svg>',
        'es' => '<svg class="w-5 h-5 rounded-sm shadow-sm" viewBox="0 0 3 2" xmlns="http://www.w3.org/2000/svg"><rect width="3" height="2" fill="#AA151B"/><rect width="3" height="1" y="0.5" fill="#F1BF00"/></svg>'
    ];
    $localeNames = [
        'en' => 'English',
        'ro' => 'Română',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español'
    ];
    $currentLocale = app()->getLocale();

    // Detect which panel we're in
    $isAdminPanel = request()->is('admin*');
    $isMarketplacePanel = request()->is('marketplace*');
    $isTenantPanel = request()->is('tenant*');

    // Set panel type for data attribute
    $panelType = $isAdminPanel ? 'admin' : ($isMarketplacePanel ? 'marketplace' : 'tenant');

    // Set search placeholder based on panel
    if ($isAdminPanel) {
        $searchPlaceholder = 'Search pages, events, venues, tenants...';
    } elseif ($isMarketplacePanel) {
        $searchPlaceholder = 'Search events, organizers, orders, customers...';
    } else {
        $searchPlaceholder = 'Search pages, events, orders, tickets...';
    }

    // Get tenant slug for search API (tenant panel only)
    $tenantSlug = null;
    if ($isTenantPanel && auth()->check() && auth()->user()->tenant) {
        $tenantSlug = auth()->user()->tenant->slug;
    }

    // Get marketplace client ID for search API (marketplace panel only)
    $marketplaceClientId = null;
    if ($isMarketplacePanel) {
        // Use Filament's auth in panel context, fall back to guard
        $mpUser = filament()->auth()->user() ?? auth('marketplace_admin')->user();
        if ($mpUser && isset($mpUser->marketplace_client_id)) {
            $marketplaceClientId = $mpUser->marketplace_client_id;
        }
    }

    // Check if current user is super-admin from core (for marketplace switcher)
    $isSuperAdminInMarketplace = $isMarketplacePanel && session('marketplace_is_super_admin');
    $marketplaceClients = [];
    $currentMarketplaceClient = null;
    if ($isSuperAdminInMarketplace) {
        $marketplaceClients = \App\Models\MarketplaceClient::where('status', 'active')->orderBy('name')->get();
        $currentMarketplaceClient = \App\Models\MarketplaceClient::find(session('super_admin_marketplace_client_id'));
    } elseif ($isMarketplacePanel && $marketplaceClientId) {
        // Get marketplace client for regular marketplace admins
        $currentMarketplaceClient = \App\Models\MarketplaceClient::find($marketplaceClientId);
    }

    // Determine Public Site URL based on panel
    $publicSiteUrl = url('/' . $currentLocale);
    if ($isMarketplacePanel && $currentMarketplaceClient?->domain) {
        $domain = $currentMarketplaceClient->domain;
        // Ensure it has https:// prefix
        if (!str_starts_with($domain, 'http://') && !str_starts_with($domain, 'https://')) {
            $domain = 'https://' . $domain;
        }
        $publicSiteUrl = $domain;
    }
@endphp
<div class="sticky top-0 z-20 px-4 py-3 mb-4 bg-white border-b border-gray-200 fi-custom-topbar dark:bg-gray-900 dark:border-gray-700">
    <div class="flex items-center justify-between max-w-full gap-4">
        {{-- Center: Global Search --}}
        <div class="flex-1 hidden md:block">
            <div class="epas-global-search">
                <input
                    type="text"
                    id="epas-global-search-input"
                    placeholder="{{ $searchPlaceholder }}"
                    class="dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 dark:border-transparent"
                    autocomplete="off"
                    data-panel="{{ $panelType }}"
                    @if($tenantSlug) data-tenant="{{ $tenantSlug }}" @endif
                    @if($marketplaceClientId) data-marketplace="{{ $marketplaceClientId }}" @endif
                >
                <div class="search-icon">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <div id="epas-search-results" class="hidden epas-search-results"></div>
            </div>
        </div>

        {{-- Right: Marketplace Switcher (for super-admins), Language Selector, Public Site Link & User Menu --}}
        <div class="flex items-center gap-3">
            {{-- Marketplace Switcher (only for super-admins in marketplace panel) --}}
            @if($isSuperAdminInMarketplace && count($marketplaceClients) > 0)
                <div x-data="{ open: false }" class="relative">
                    <button
                        @click="open = !open"
                        type="button"
                        class="flex items-center gap-2 px-3 py-2 text-sm transition border rounded-lg text-amber-700 bg-amber-50 border-amber-200 hover:bg-amber-100 dark:bg-amber-900/30 dark:text-amber-300 dark:border-transparent"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span class="font-medium">{{ $currentMarketplaceClient?->name ?? 'Select Marketplace' }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-cloak
                        class="absolute right-0 z-50 py-1 mt-2 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg w-72 max-h-96 dark:bg-gray-800 dark:border-gray-700"
                    >
                        <div class="px-4 py-2 text-xs font-semibold tracking-wider text-gray-500 uppercase border-b border-gray-200 dark:border-gray-700 dark:text-gray-400">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                Super Admin Mode
                            </div>
                        </div>
                        @foreach($marketplaceClients as $client)
                            <a
                                href="{{ url('/marketplace/switch-client/' . $client->id) }}"
                                class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $currentMarketplaceClient?->id === $client->id ? 'bg-amber-50 dark:bg-amber-900/30 font-semibold' : '' }}"
                            >
                                <div class="flex items-center justify-center flex-shrink-0 w-8 h-8 text-xs font-semibold rounded-full bg-primary-100 text-primary-700">
                                    {{ strtoupper(substr($client->name, 0, 2)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-medium truncate">{{ $client->name }}</div>
                                    <div class="text-xs text-gray-500 truncate">{{ $client->website }}</div>
                                </div>
                                @if($currentMarketplaceClient?->id === $client->id)
                                    <svg class="flex-shrink-0 w-4 h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                        <div class="px-4 py-2 border-t border-gray-200 dark:border-gray-700">
                            <a href="{{ url('/admin') }}" class="flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                                </svg>
                                Back to Admin Panel
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Language Selector with Flags --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    type="button"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    <span class="flex-shrink-0">{!! $localeFlags[$currentLocale] ?? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>' !!}</span>
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
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Public Site Link --}}
            <a
                href="{{ $publicSiteUrl }}"
                target="_blank"
                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                title="View Public Site"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                <span class="hidden md:inline">Public Site</span>
            </a>

            {{-- User Account Menu with Theme Switcher --}}
            @if(filament()->auth()->check())
                <div x-data="{
                    open: false,
                    theme: localStorage.getItem('theme') || 'system',
                    setTheme(newTheme) {
                        this.theme = newTheme;
                        localStorage.setItem('theme', newTheme);
                        this.applyTheme();
                        this.open = false;
                    },
                    applyTheme() {
                        if (this.theme === 'dark' || (this.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                            document.documentElement.classList.add('dark');
                        } else {
                            document.documentElement.classList.remove('dark');
                        }
                    }
                }" x-init="applyTheme()" class="relative fi-user-menu">
                    <button
                        @click="open = !open"
                        type="button"
                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg fi-dropdown-trigger dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <x-filament-panels::avatar.user :user="filament()->auth()->user()" class="w-8 h-8" />
                        <span class="hidden font-medium md:inline">{{ filament()->auth()->user()->name }}</span>
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
                        class="absolute right-0 z-50 w-56 py-1 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg fi-dropdown-panel dark:bg-gray-800 dark:border-gray-700"
                    >
                        {{-- User Info Header --}}
                        <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-200 fi-dropdown-header dark:border-gray-700">
                            <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-5.5-2.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0ZM10 12a5.99 5.99 0 0 0-4.793 2.39A6.483 6.483 0 0 0 10 16.5a6.483 6.483 0 0 0 4.793-2.11A5.99 5.99 0 0 0 10 12Z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-medium text-gray-900 dark:text-white">{{ filament()->auth()->user()->name }}</span>
                        </div>

                        {{-- Theme Switcher --}}
                        <div class="px-4 py-2 border-b border-gray-200 fi-dropdown-list dark:border-gray-700">
                            <div class="flex items-center justify-center gap-1 fi-theme-switcher">
                                {{-- Light Theme --}}
                                <button
                                    @click="setTheme('light')"
                                    type="button"
                                    :class="{ 'bg-gray-100 dark:bg-gray-700 text-primary-600': theme === 'light' }"
                                    class="p-2 text-gray-500 transition rounded-lg fi-theme-switcher-btn hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400"
                                    title="Enable light theme"
                                >
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10 2a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 2ZM10 15a.75.75 0 0 1 .75.75v1.5a.75.75 0 0 1-1.5 0v-1.5A.75.75 0 0 1 10 15ZM10 7a3 3 0 1 0 0 6 3 3 0 0 0 0-6ZM15.657 5.404a.75.75 0 1 0-1.06-1.06l-1.061 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM6.464 14.596a.75.75 0 1 0-1.06-1.06l-1.06 1.06a.75.75 0 0 0 1.06 1.06l1.06-1.06ZM18 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 18 10ZM5 10a.75.75 0 0 1-.75.75h-1.5a.75.75 0 0 1 0-1.5h1.5A.75.75 0 0 1 5 10ZM14.596 15.657a.75.75 0 0 0 1.06-1.06l-1.06-1.061a.75.75 0 1 0-1.06 1.06l1.06 1.06ZM5.404 6.464a.75.75 0 0 0 1.06-1.06l-1.06-1.06a.75.75 0 1 0-1.061 1.06l1.06 1.06Z"/>
                                    </svg>
                                </button>

                                {{-- Dark Theme --}}
                                <button
                                    @click="setTheme('dark')"
                                    type="button"
                                    :class="{ 'bg-gray-100 dark:bg-gray-700 text-primary-600': theme === 'dark' }"
                                    class="p-2 text-gray-500 transition rounded-lg fi-theme-switcher-btn hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400"
                                    title="Enable dark theme"
                                >
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77 7 7 0 0 0 9.958 7.967.75.75 0 0 1 1.067.853A8.5 8.5 0 1 1 6.647 1.921a.75.75 0 0 1 .808.083Z" clip-rule="evenodd"/>
                                    </svg>
                                </button>

                                {{-- System Theme --}}
                                <button
                                    @click="setTheme('system')"
                                    type="button"
                                    :class="{ 'bg-gray-100 dark:bg-gray-700 text-primary-600': theme === 'system' }"
                                    class="p-2 text-gray-500 transition rounded-lg fi-theme-switcher-btn hover:bg-gray-100 dark:hover:bg-gray-700 dark:text-gray-400"
                                    title="Enable system theme"
                                >
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M2 4.25A2.25 2.25 0 0 1 4.25 2h11.5A2.25 2.25 0 0 1 18 4.25v8.5A2.25 2.25 0 0 1 15.75 15h-3.105a3.501 3.501 0 0 0 1.1 1.677A.75.75 0 0 1 13.26 18H6.74a.75.75 0 0 1-.484-1.323A3.501 3.501 0 0 0 7.355 15H4.25A2.25 2.25 0 0 1 2 12.75v-8.5Zm1.5 0a.75.75 0 0 1 .75-.75h11.5a.75.75 0 0 1 .75.75v7.5a.75.75 0 0 1-.75.75H4.25a.75.75 0 0 1-.75-.75v-7.5Z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Logout --}}
                        <div class="fi-dropdown-list">
                            <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                                @csrf
                                <button type="submit" class="flex items-center w-full gap-3 px-4 py-2 text-sm text-left text-gray-700 fi-dropdown-list-item dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M3 4.25A2.25 2.25 0 0 1 5.25 2h5.5A2.25 2.25 0 0 1 13 4.25v2a.75.75 0 0 1-1.5 0v-2a.75.75 0 0 0-.75-.75h-5.5a.75.75 0 0 0-.75.75v11.5c0 .414.336.75.75.75h5.5a.75.75 0 0 0 .75-.75v-2a.75.75 0 0 1 1.5 0v2A2.25 2.25 0 0 1 10.75 18h-5.5A2.25 2.25 0 0 1 3 15.75V4.25Z" clip-rule="evenodd"/>
                                        <path fill-rule="evenodd" d="M19 10a.75.75 0 0 0-.75-.75H8.704l1.048-.943a.75.75 0 1 0-1.004-1.114l-2.5 2.25a.75.75 0 0 0 0 1.114l2.5 2.25a.75.75 0 1 0 1.004-1.114l-1.048-.943h9.546A.75.75 0 0 0 19 10Z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="fi-dropdown-list-item-label">Sign out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Move topbar before .fi-main for marketplace panel --}}
@if($isMarketplacePanel)
<script>
// Run immediately and on Livewire updates
(function() {
    function setupTopbar() {
        // Remove any duplicate topbars first (keep only the last one)
        const topbars = document.querySelectorAll('.fi-custom-topbar');
        if (topbars.length > 1) {
            // Keep only the first one, remove the rest
            for (let i = 1; i < topbars.length; i++) {
                topbars[i].remove();
            }
        }

        const topbar = document.querySelector('.fi-custom-topbar');
        const mainCtn = document.querySelector('.fi-main-ctn');
        const main = document.querySelector('.fi-main');

        if (topbar && mainCtn && main) {
            // Only move if not already in correct position
            if (topbar.parentElement !== mainCtn || topbar.nextElementSibling !== main) {
                topbar.remove();
                mainCtn.insertBefore(topbar, main);
            }
        }
    }

    // Run on initial load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupTopbar);
    } else {
        setupTopbar();
    }

    // Run after Livewire updates
    document.addEventListener('livewire:navigated', setupTopbar);
    document.addEventListener('livewire:morph-updated', setupTopbar);

    // Also run with a small delay after any Livewire message
    if (window.Livewire) {
        Livewire.hook('message.processed', () => {
            setTimeout(setupTopbar, 10);
        });
    } else {
        document.addEventListener('livewire:init', () => {
            Livewire.hook('message.processed', () => {
                setTimeout(setupTopbar, 10);
            });
        });
    }
})();
</script>
@endif
