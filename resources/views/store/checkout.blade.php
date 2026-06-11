<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout - Tixello Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/favicon.ico">
</head>
<body class="min-h-screen bg-gray-50 text-gray-800 antialiased">
    <!-- Header -->
    <header class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <a href="{{ route('store.index') }}" class="font-semibold text-xl">
                <span class="text-indigo-600">Tixello</span> Store
            </a>
            <span class="text-sm text-gray-500">Secure Checkout</span>
        </div>
    </header>

    <main class="py-12">
        <div class="max-w-4xl mx-auto px-4">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Checkout</h1>

            <!-- Flash Messages -->
            @if(session('error'))
                <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            @if(!$stripeConfigured)
                <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                    <strong>Payment system not configured.</strong> Please contact the administrator to set up Stripe.
                </div>
            @endif

            <!-- Already Owned Warning -->
            @if($alreadyOwned->isNotEmpty())
                <div class="mb-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded-lg">
                    <strong>Note:</strong> You already own some items in your cart:
                    <ul class="mt-2 list-disc list-inside">
                        @foreach($alreadyOwned as $owned)
                            <li>{{ $owned->getTranslation('name', app()->getLocale()) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Order Summary -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6 border-b">
                            <h2 class="text-lg font-semibold text-gray-900">Order Summary</h2>
                        </div>

                        <div class="divide-y">
                            @foreach($microservices as $microservice)
                                <div class="p-6 flex items-center gap-4">
                                    <div class="flex-shrink-0 w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-lg flex items-center justify-center">
                                        @if($microservice->icon_image)
                                            <img src="{{ Storage::url($microservice->icon_image) }}"
                                                 alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                                                 class="w-8 h-8">
                                        @else
                                            <svg class="w-6 h-6 text-white/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                            </svg>
                                        @endif
                                    </div>
                                    <div class="flex-grow">
                                        <h3 class="font-medium text-gray-900">
                                            {{ $microservice->getTranslation('name', app()->getLocale()) }}
                                        </h3>
                                        <p class="text-sm text-gray-500">
                                            {{ $microservice->pricing_model ? ucfirst($microservice->pricing_model) : 'One-time' }}
                                            @if($microservice->billing_cycle)
                                                &bull; {{ $microservice->billing_cycle }}
                                            @endif
                                        </p>
                                    </div>
                                    <div class="font-medium text-gray-900">
                                        {{ number_format($microservice->price, 2) }} {{ $microservice->currency ?? 'EUR' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Billing Info -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 mt-6 p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Billing Information</h2>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="text-gray-500">Company</span>
                                <p class="font-medium">{{ $tenant->company_name ?? $tenant->name }}</p>
                            </div>
                            <div>
                                <span class="text-gray-500">Email</span>
                                <p class="font-medium">{{ $tenant->contact_email ?? $tenant->owner->email ?? 'N/A' }}</p>
                            </div>
                            @if($tenant->cui)
                                <div>
                                    <span class="text-gray-500">CUI</span>
                                    <p class="font-medium">{{ $tenant->cui }}</p>
                                </div>
                            @endif
                            @if($tenant->address)
                                <div class="col-span-2">
                                    <span class="text-gray-500">Address</span>
                                    <p class="font-medium">
                                        {{ $tenant->address }}
                                        @if($tenant->city), {{ $tenant->city }}@endif
                                        @if($tenant->country), {{ $tenant->country }}@endif
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 sticky top-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Payment Summary</h2>

                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Subtotal</span>
                                <span>{{ number_format($subtotal, 2) }} {{ $currency }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Tax</span>
                                <span>Calculated at checkout</span>
                            </div>
                            <div class="border-t pt-3 flex justify-between font-semibold">
                                <span>Total</span>
                                <span class="text-xl">{{ number_format($subtotal, 2) }} {{ $currency }}</span>
                            </div>
                        </div>

                        <form action="{{ route('store.checkout.process') }}" method="POST">
                            @csrf
                            <button type="submit"
                                    @if(!$stripeConfigured) disabled @endif
                                    class="w-full bg-indigo-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                Pay with Stripe
                            </button>
                        </form>

                        <p class="text-xs text-gray-500 text-center mt-4">
                            You will be redirected to Stripe's secure checkout page.
                        </p>

                        <!-- Security badges -->
                        <div class="mt-6 pt-6 border-t">
                            <div class="flex items-center justify-center gap-2 text-xs text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Secured by Stripe
                            </div>
                        </div>

                        <a href="{{ route('store.cart') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800 mt-4">
                            &larr; Back to cart
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-t bg-white mt-12">
        <div class="max-w-7xl mx-auto px-4 py-8 text-sm text-gray-500 text-center">
            <p>&copy; {{ date('Y') }} Tixello &bull; Your Event Management Platform</p>
        </div>
    </footer>
</body>
</html>
