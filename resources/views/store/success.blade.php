<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Successful - Tixello Store</title>
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
        </div>
    </header>

    <main class="py-12">
        <div class="max-w-2xl mx-auto px-4">
            <!-- Success Message -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 text-center mb-8">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>

                <h1 class="text-2xl font-bold text-gray-900 mb-2">Payment Successful!</h1>
                <p class="text-gray-600 mb-6">
                    Thank you for your purchase. Your microservices have been activated and are ready to use.
                </p>

                @if($tenant)
                    <p class="text-sm text-gray-500">
                        A confirmation email has been sent to <strong>{{ $tenant->contact_email ?? $tenant->owner->email ?? 'your email' }}</strong>
                    </p>
                @endif
            </div>

            <!-- Order Details -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-8">
                <div class="p-6 border-b">
                    <h2 class="text-lg font-semibold text-gray-900">Order Details</h2>
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
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="flex-grow">
                                <h3 class="font-medium text-gray-900">
                                    {{ $microservice->getTranslation('name', app()->getLocale()) }}
                                </h3>
                                <p class="text-sm text-green-600 flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Activated
                                </p>
                            </div>
                            <div class="font-medium text-gray-900">
                                {{ number_format($microservice->price, 2) }} {{ $microservice->currency ?? 'EUR' }}
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($session)
                    <div class="bg-gray-50 p-6 border-t">
                        <div class="flex justify-between text-sm mb-2">
                            <span class="text-gray-500">Payment ID</span>
                            <span class="font-mono text-xs">{{ $session->id }}</span>
                        </div>
                        <div class="flex justify-between font-semibold">
                            <span>Total Paid</span>
                            <span>{{ number_format($session->amount_total / 100, 2) }} {{ strtoupper($session->currency) }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Next Steps -->
            <div class="bg-blue-50 rounded-xl p-6 border border-blue-100 mb-8">
                <h3 class="font-semibold text-blue-900 mb-3">What's Next?</h3>
                <ul class="space-y-2 text-sm text-blue-800">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Your microservices are now active in your dashboard
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Check your email for invoice and activation details
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Configure your microservices in Settings
                    </li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-4">
                <a href="/admin"
                   class="flex-1 text-center bg-indigo-600 text-white font-semibold py-3 px-4 rounded-lg hover:bg-indigo-700 transition-colors">
                    Go to Dashboard
                </a>
                <a href="{{ route('store.index') }}"
                   class="flex-1 text-center border border-gray-300 text-gray-700 font-semibold py-3 px-4 rounded-lg hover:bg-gray-100 transition-colors">
                    Continue Shopping
                </a>
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
