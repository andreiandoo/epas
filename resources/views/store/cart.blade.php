<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Shopping Cart - Tixello Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="icon" href="/favicon.ico">
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased">
    <!-- Header -->
    <header class="bg-white border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('store.index') }}" class="font-semibold text-xl">
                <span class="text-indigo-600">Tixello</span> Store
            </a>
            <nav class="flex items-center gap-6 text-sm">
                <a href="/" class="hover:text-black">Home</a>
                <a href="{{ route('store.index') }}" class="hover:text-black">Store</a>
                @auth
                    <a href="/admin" class="text-indigo-600 hover:text-indigo-800">Dashboard</a>
                @else
                    <a href="/admin/login" class="text-indigo-600 hover:text-indigo-800">Login</a>
                @endauth
            </nav>
        </div>
    </header>

    <main class="py-12">
        <div class="max-w-4xl mx-auto px-4">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>

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

            @if(session('warning'))
                <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                    {{ session('warning') }}
                </div>
            @endif

            @if($microservices->isEmpty())
                <div class="bg-white rounded-xl p-12 text-center shadow-sm border border-gray-100">
                    <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <h2 class="text-xl font-semibold text-gray-900 mb-2">Your cart is empty</h2>
                    <p class="text-gray-600 mb-6">Browse our microservices and add them to your cart.</p>
                    <a href="{{ route('store.index') }}"
                       class="inline-block bg-indigo-600 text-white font-semibold py-3 px-6 rounded-lg hover:bg-indigo-700 transition-colors">
                        Browse Store
                    </a>
                </div>
            @else
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- Cart Items -->
                    <div class="divide-y">
                        @foreach($microservices as $microservice)
                            <div class="p-6 flex items-center gap-6">
                                <!-- Icon/Image -->
                                <div class="flex-shrink-0 w-16 h-16 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                                    @if($microservice->icon_image)
                                        <img src="{{ Storage::url($microservice->icon_image) }}"
                                             alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                                             class="w-10 h-10">
                                    @else
                                        <svg class="w-8 h-8 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                        </svg>
                                    @endif
                                </div>

                                <!-- Details -->
                                <div class="flex-grow">
                                    <h3 class="font-semibold text-gray-900">
                                        <a href="{{ route('store.show', $microservice->slug) }}" class="hover:text-indigo-600">
                                            {{ $microservice->getTranslation('name', app()->getLocale()) }}
                                        </a>
                                    </h3>
                                    <p class="text-sm text-gray-500">
                                        {{ $microservice->pricing_model ? ucfirst($microservice->pricing_model) : 'One-time' }} license
                                        @if($microservice->billing_cycle)
                                            &bull; Billed {{ $microservice->billing_cycle }}
                                        @endif
                                    </p>
                                </div>

                                <!-- Price -->
                                <div class="text-right">
                                    <div class="font-semibold text-gray-900">
                                        {{ number_format($microservice->price, 2) }} {{ $microservice->currency ?? 'EUR' }}
                                    </div>
                                </div>

                                <!-- Remove -->
                                <form action="{{ route('store.cart.remove') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="microservice_id" value="{{ $microservice->id }}">
                                    <button type="submit" class="text-gray-400 hover:text-red-600 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>

                    <!-- Summary -->
                    <div class="bg-gray-50 p-6">
                        <div class="flex items-center justify-between mb-6">
                            <span class="text-lg font-semibold text-gray-900">Total</span>
                            <span class="text-2xl font-bold text-gray-900">
                                {{ number_format($subtotal, 2) }} {{ $currency }}
                            </span>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="{{ route('store.index') }}"
                               class="flex-1 text-center border border-gray-300 text-gray-700 font-semibold py-3 px-4 rounded-lg hover:bg-gray-100 transition-colors">
                                Continue Shopping
                            </a>
                            <a href="{{ route('store.checkout') }}"
                               class="flex-1 text-center bg-indigo-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-indigo-700 transition-colors">
                                Proceed to Checkout
                            </a>
                        </div>

                        @guest
                            <p class="text-sm text-gray-500 text-center mt-4">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                You'll need to <a href="/admin/login" class="text-indigo-600 hover:text-indigo-800">login</a> or
                                <a href="/register" class="text-indigo-600 hover:text-indigo-800">register as a tenant</a> to checkout.
                            </p>
                        @endguest
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
