<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl w-full">
            <!-- Success Card -->
            <div class="bg-white rounded-lg shadow-xl overflow-hidden">
                <!-- Success Icon -->
                <div class="bg-green-500 px-6 py-8 text-center">
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-white">
                        <svg class="h-12 w-12 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="mt-4 text-3xl font-bold text-white">Payment Successful!</h1>
                    <p class="mt-2 text-green-100">Your microservices have been activated</p>
                </div>

                <div class="px-6 py-8">
                    <!-- Order Summary -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Summary</h2>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Order ID:</span>
                                <span class="font-mono text-sm text-gray-900">{{ $session->id }}</span>
                            </div>
                            <div class="flex justify-between mb-2">
                                <span class="text-gray-600">Amount Paid:</span>
                                <span class="font-bold text-gray-900">{{ number_format($session->amount_total / 100, 2) }} {{ strtoupper($session->currency) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Payment Status:</span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Paid
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Activated Microservices -->
                    <div class="mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Activated Microservices</h2>
                        <div class="space-y-3">
                            @foreach($microservices as $microservice)
                                <div class="flex items-center justify-between bg-gray-50 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </div>
                                        <div class="ml-4">
                                            <h3 class="text-lg font-medium text-gray-900">{{ $microservice->name }}</h3>
                                            <p class="text-sm text-gray-600">{{ $microservice->short_description }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-600">
                                            {{ number_format($microservice->price, 2) }} RON
                                            @if($microservice->pricing_model !== 'one-time')
                                                <span class="text-xs">/{{ $microservice->pricing_model === 'monthly' ? 'mo' : 'yr' }}</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Next Steps -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-8">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">What's Next?</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>A confirmation email with your invoice has been sent to your email address</li>
                                        <li>Your microservices are now active and ready to use</li>
                                        <li>You can configure them in your admin panel</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('micro.marketplace') }}" class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-6 rounded-lg text-center transition duration-200">
                            Browse More Services
                        </a>
                        <a href="/admin" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-center transition duration-200">
                            Go to Admin Panel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Invoice Download -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Your invoice will be sent to your email shortly.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
