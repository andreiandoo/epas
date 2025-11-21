<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $microservice->getTranslation('name', app()->getLocale()) }} - Tixello Store</title>
    <meta name="description" content="{{ $microservice->getTranslation('short_description', app()->getLocale()) }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/favicon.ico">
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased" x-data="{ cartCount: {{ $cartCount }}, inCart: {{ $inCart ? 'true' : 'false' }} }">
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
            <!-- Breadcrumb -->
            <nav class="mb-8">
                <ol class="flex items-center gap-2 text-sm text-gray-500">
                    <li><a href="{{ route('store.index') }}" class="hover:text-gray-700">Store</a></li>
                    <li>/</li>
                    <li class="text-gray-900">{{ $microservice->getTranslation('name', app()->getLocale()) }}</li>
                </ol>
            </nav>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Content -->
                <div class="lg:col-span-2">
                    <!-- Hero Image -->
                    @if($microservice->public_image)
                        <div class="aspect-video bg-gray-100 rounded-xl overflow-hidden mb-8">
                            <img src="{{ Storage::url($microservice->public_image) }}"
                                 alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                                 class="w-full h-full object-cover">
                        </div>
                    @else
                        <div class="aspect-video bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center mb-8">
                            @if($microservice->icon_image)
                                <img src="{{ Storage::url($microservice->icon_image) }}"
                                     alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                                     class="w-24 h-24">
                            @else
                                <svg class="w-24 h-24 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                </svg>
                            @endif
                        </div>
                    @endif

                    <!-- Title & Description -->
                    <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 mb-8">
                        <div class="flex items-start justify-between mb-6">
                            <h1 class="text-3xl font-bold text-gray-900">
                                {{ $microservice->getTranslation('name', app()->getLocale()) }}
                            </h1>
                            @if($microservice->category)
                                <span class="text-sm bg-indigo-50 text-indigo-700 px-3 py-1 rounded-full">
                                    {{ $microservice->category }}
                                </span>
                            @endif
                        </div>

                        <div class="prose prose-gray max-w-none">
                            {!! nl2br(e($microservice->getTranslation('description', app()->getLocale()))) !!}
                        </div>
                    </div>

                    <!-- Features -->
                    @if($microservice->features && count($microservice->features) > 0)
                        <div class="bg-white rounded-xl p-8 shadow-sm border border-gray-100 mb-8">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Features</h2>
                            <ul class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach($microservice->features as $feature)
                                    <li class="flex items-start gap-3">
                                        <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span class="text-gray-700">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Documentation Link -->
                    @if($microservice->documentation_url)
                        <div class="bg-blue-50 rounded-xl p-6 border border-blue-100">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                                </svg>
                                <div>
                                    <h3 class="font-semibold text-blue-900">Documentation</h3>
                                    <a href="{{ $microservice->documentation_url }}" target="_blank" class="text-blue-600 hover:text-blue-800 text-sm">
                                        View integration guide &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 sticky top-24">
                        <!-- Price -->
                        <div class="text-center mb-6 pb-6 border-b">
                            <span class="text-4xl font-bold text-gray-900">
                                {{ number_format($microservice->price, 2) }}
                            </span>
                            <span class="text-lg text-gray-500">
                                {{ $microservice->currency ?? 'EUR' }}
                            </span>
                            @if($microservice->billing_cycle)
                                <div class="text-sm text-gray-500 mt-1">
                                    per {{ $microservice->billing_cycle }}
                                </div>
                            @endif
                            @if($microservice->pricing_model)
                                <div class="text-xs text-gray-400 mt-2">
                                    {{ ucfirst($microservice->pricing_model) }} pricing
                                </div>
                            @endif
                        </div>

                        <!-- Add to Cart -->
                        <form action="{{ route('store.cart.add') }}" method="POST"
                              @submit.prevent="
                                  if (inCart) {
                                      window.location.href = '{{ route('store.cart') }}';
                                      return;
                                  }
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
                                      inCart = true;
                                  })
                              ">
                            @csrf
                            <input type="hidden" name="microservice_id" value="{{ $microservice->id }}">
                            <button type="submit"
                                    :class="inCart ? 'bg-green-600 hover:bg-green-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                                    class="w-full text-white font-semibold py-3 px-4 rounded-lg transition-colors mb-3">
                                <span x-text="inCart ? 'View Cart' : 'Add to Cart'"></span>
                            </button>
                        </form>

                        <a href="{{ route('store.checkout') }}"
                           class="block w-full text-center border border-indigo-600 text-indigo-600 font-semibold py-3 px-4 rounded-lg hover:bg-indigo-50 transition-colors">
                            Buy Now
                        </a>

                        <!-- Info -->
                        <div class="mt-6 pt-6 border-t text-sm text-gray-500 space-y-3">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                Secure payment via Stripe
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                                Instant activation
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                                Email support included
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Microservices -->
            @if($relatedMicroservices->isNotEmpty())
                <div class="mt-16">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Microservices</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        @foreach($relatedMicroservices as $related)
                            <a href="{{ route('store.show', $related->slug) }}" class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                                <h3 class="font-semibold text-gray-900 mb-2">
                                    {{ $related->getTranslation('name', app()->getLocale()) }}
                                </h3>
                                <p class="text-sm text-gray-600 mb-4 line-clamp-2">
                                    {{ $related->getTranslation('short_description', app()->getLocale()) }}
                                </p>
                                <div class="text-lg font-bold text-indigo-600">
                                    {{ number_format($related->price, 2) }} {{ $related->currency ?? 'EUR' }}
                                </div>
                            </a>
                        @endforeach
                    </div>
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
