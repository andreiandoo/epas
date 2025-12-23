<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('My Account')) - {{ $tenant->public_name ?? $tenant->name ?? config('app.name') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Livewire Styles -->
    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="{{ url('/') }}" class="flex items-center">
                        <span class="text-xl font-bold text-gray-900">{{ $tenant->public_name ?? $tenant->name ?? config('app.name') }}</span>
                    </a>
                </div>

                <div class="flex items-center space-x-4">
                    @auth('customer')
                        <span class="text-sm text-gray-600">{{ auth('customer')->user()->email }}</span>
                        <form method="POST" action="{{ route('customer.logout', ['tenant' => $tenant->slug]) }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">
                                {{ __('Logout') }}
                            </button>
                        </form>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar + Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Navigation -->
            <aside class="w-full lg:w-64 flex-shrink-0">
                <nav class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 space-y-1">
                    <a href="{{ route('customer.account', ['tenant' => $tenant->slug]) }}"
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('customer.account') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        {{ __('My Account') }}
                    </a>

                    <a href="{{ route('customer.orders', ['tenant' => $tenant->slug]) }}"
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('customer.orders*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        {{ __('My Orders') }}
                    </a>

                    <a href="{{ route('customer.tickets', ['tenant' => $tenant->slug]) }}"
                       class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('customer.tickets*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                        {{ __('My Tickets') }}
                    </a>

                    @if($tenant->hasMicroservice('affiliate-tracking'))
                        <a href="{{ route('customer.affiliate', ['tenant' => $tenant->slug]) }}"
                           class="flex items-center px-4 py-2.5 text-sm font-medium rounded-lg {{ request()->routeIs('customer.affiliate*') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50' }}">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            {{ __('Affiliate Program') }}
                        </a>
                    @endif
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="flex-1 min-w-0">
                @yield('content')
            </main>
        </div>
    </div>

    <!-- Livewire Scripts -->
    @livewireScripts

    @stack('scripts')
</body>
</html>
