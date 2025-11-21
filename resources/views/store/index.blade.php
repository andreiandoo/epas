<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Microservices Store - Tixello</title>
    <meta name="description" content="Browse and purchase microservices to enhance your event management platform">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/favicon.ico">
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased" x-data="{ cartCount: {{ $cartCount }} }">
    <!-- Header -->
    <header class="bg-white border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('store.index') }}" class="font-semibold text-xl">
                <span class="text-indigo-600">Tixello</span> Store
            </a>
            <nav class="flex items-center gap-6 text-sm">
                <a href="/" class="hover:text-black">Home</a>
                <a href="{{ route('store.cart') }}" class="relative hover:text-black flex items-center gap-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Cart
                    <span x-show="cartCount > 0" x-text="cartCount" class="absolute -top-2 -right-4 bg-indigo-600 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"></span>
                </a>
                @auth
                    <a href="/admin" class="text-indigo-600 hover:text-indigo-800">Dashboard</a>
                @else
                    <a href="/admin/login" class="text-indigo-600 hover:text-indigo-800">Login</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="py-12">
        <div class="max-w-7xl mx-auto px-4">
            <!-- Hero Section -->
            <div class="text-center mb-12">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">Microservices Store</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Enhance your event management platform with powerful integrations and services.
                    Add new capabilities with just a few clicks.
                </p>
            </div>

            <!-- Flash Messages -->
            @if(session('success'))
                <div class="mb-6 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Microservices Grid -->
            @if($microservices->isEmpty())
                <div class="text-center py-12">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <p class="text-gray-500">No microservices available at the moment.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($microservices as $microservice)
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                            <!-- Image -->
                            @if($microservice->public_image)
                                <div class="aspect-video bg-gray-100">
                                    <img src="{{ Storage::url($microservice->public_image) }}"
                                         alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                                         class="w-full h-full object-cover">
                                </div>
                            @else
                                <div class="aspect-video bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                                    @if($microservice->icon_image)
                                        <img src="{{ Storage::url($microservice->icon_image) }}"
                                             alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                                             class="w-16 h-16">
                                    @else
                                        <svg class="w-16 h-16 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                        </svg>
                                    @endif
                                </div>
                            @endif

                            <!-- Content -->
                            <div class="p-6">
                                <div class="flex items-start justify-between mb-3">
                                    <h3 class="font-semibold text-lg text-gray-900">
                                        {{ $microservice->getTranslation('name', app()->getLocale()) }}
                                    </h3>
                                    @if($microservice->category)
                                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded-full">
                                            {{ $microservice->category }}
                                        </span>
                                    @endif
                                </div>

                                <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                    {{ $microservice->getTranslation('short_description', app()->getLocale()) }}
                                </p>

                                <!-- Features Preview -->
                                @php
                                    $features = $microservice->getTranslation('features', app()->getLocale()) ?? [];
                                @endphp
                                @if(is_array($features) && count($features) > 0)
                                    <div class="mb-4">
                                        <ul class="text-sm text-gray-500 space-y-1">
                                            @foreach(array_slice($features, 0, 3) as $feature)
                                                <li class="flex items-center gap-2">
                                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    {{ $feature }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <!-- Price & Actions -->
                                <div class="flex items-center justify-between pt-4 border-t">
                                    <div>
                                        <span class="text-2xl font-bold text-gray-900">
                                            {{ number_format($microservice->price, 2) }}
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            {{ $microservice->currency ?? 'EUR' }}
                                            @if($microservice->billing_cycle)
                                                / {{ $microservice->billing_cycle }}
                                            @endif
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="{{ route('store.show', $microservice->slug) }}"
                                           class="text-sm text-indigo-600 hover:text-indigo-800">
                                            Details
                                        </a>
                                        <form action="{{ route('store.cart.add') }}" method="POST" class="inline"
                                              @submit.prevent="
                                                  fetch('{{ route('store.cart.add') }}', {
                                                      method: 'POST',
                                                      headers: {
                                                          'Content-Type': 'application/json',
                                                          'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                          'Accept': 'application/json'
                                                      },
                                                      body: JSON.stringify({ microservice_id: {{ $microservice->id }} })
                                                  })
                                                  .then(r => r.json())
                                                  .then(data => {
                                                      cartCount = data.cartCount;
                                                      $el.querySelector('button').textContent = 'Added!';
                                                      setTimeout(() => $el.querySelector('button').textContent = 'Add to Cart', 2000);
                                                  })
                                              ">
                                            @csrf
                                            <input type="hidden" name="microservice_id" value="{{ $microservice->id }}">
                                            <button type="submit"
                                                    class="bg-indigo-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors">
                                                Add to Cart
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t bg-white mt-12">
        <div class="max-w-7xl mx-auto px-4 py-8 text-sm text-gray-500 flex flex-col sm:flex-row gap-3 justify-between">
            <p>&copy; {{ date('Y') }} Tixello &bull; Your Event Management Platform</p>
            <p>
                <a href="/about" class="hover:text-black">About</a> &bull;
                <a href="/contact" class="hover:text-black">Contact</a>
            </p>
        </div>
    </footer>
</body>
</html>
