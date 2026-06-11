<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microservices Marketplace - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <h1 class="text-3xl font-bold text-gray-900">Microservices Marketplace</h1>
                <p class="mt-2 text-sm text-gray-600">Enhance your platform with powerful features</p>
            </div>
        </header>

        <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <!-- Stripe Configuration Warning -->
            @if(!$stripeConfigured)
                <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                Stripe is not configured. Please configure Stripe in Settings > Connections to enable purchases.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Alerts -->
            @if(session('error'))
                <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4">
                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                </div>
            @endif

            @if(session('success'))
                <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4">
                    <p class="text-sm text-green-700">{{ session('success') }}</p>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <p class="text-sm text-yellow-700">{{ session('warning') }}</p>
                </div>
            @endif

            <!-- Shopping Cart Form -->
            <form method="POST" action="{{ route('micro.checkout') }}" id="checkoutForm">
                @csrf
                <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">

                <!-- Microservices Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                    @foreach($microservices as $microservice)
                        @php
                            $isActive = in_array($microservice->id, $activeMicroserviceIds);
                        @endphp
                        <div class="bg-white rounded-lg shadow-md overflow-hidden {{ $isActive ? 'border-2 border-green-500' : '' }}">
                            <!-- Microservice Icon -->
                            @if($microservice->icon_image)
                                <img src="{{ Storage::url($microservice->icon_image) }}" alt="{{ $microservice->name }}" class="w-full h-48 object-cover">
                            @else
                                <div class="w-full h-48 bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center">
                                    <svg class="w-20 h-20 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                                    </svg>
                                </div>
                            @endif

                            <div class="p-6">
                                <div class="flex items-start justify-between mb-4">
                                    <h3 class="text-xl font-semibold text-gray-900">{{ $microservice->name }}</h3>
                                    @if($isActive)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    @endif
                                </div>

                                <p class="text-gray-600 text-sm mb-4">{{ $microservice->short_description }}</p>

                                <!-- Pricing -->
                                <div class="mb-4">
                                    <span class="text-2xl font-bold text-gray-900">
                                        {{ number_format($microservice->price, 2) }} RON
                                    </span>
                                    <span class="text-sm text-gray-500 ml-2">
                                        @if($microservice->pricing_model === 'one-time')
                                            One-time
                                        @elseif($microservice->pricing_model === 'monthly')
                                            /month
                                        @elseif($microservice->pricing_model === 'yearly')
                                            /year
                                        @else
                                            {{ ucfirst($microservice->pricing_model) }}
                                        @endif
                                    </span>
                                </div>

                                <!-- Features -->
                                @if($microservice->features && is_array($microservice->features))
                                    <ul class="space-y-2 mb-4">
                                        @foreach(array_slice($microservice->features, 0, 3) as $feature)
                                            <li class="flex items-start">
                                                <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                                <span class="text-sm text-gray-600">{{ is_array($feature) ? $feature[1] : $feature }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <!-- Add to Cart Checkbox -->
                                @if(!$isActive && $stripeConfigured)
                                    <div class="mt-4">
                                        <label class="flex items-center cursor-pointer">
                                            <input type="checkbox" name="microservices[]" value="{{ $microservice->id }}" class="microservice-checkbox w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                            <span class="ml-2 text-sm font-medium text-gray-900">Add to cart</span>
                                        </label>
                                    </div>
                                @elseif($isActive)
                                    <p class="text-sm text-green-600 font-medium">✓ Already activated</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Checkout Summary -->
                <div class="bg-white rounded-lg shadow-md p-6 sticky bottom-4" id="checkoutSummary" style="display: none;">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Selected: <span id="selectedCount">0</span> microservice(s)</h3>
                            <p class="text-sm text-gray-600">Total: <span id="totalPrice" class="font-bold text-xl text-gray-900">0.00</span> RON</p>
                        </div>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition duration-200" {{ !$stripeConfigured ? 'disabled' : '' }}>
                            Proceed to Checkout
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.microservice-checkbox');
            const summary = document.getElementById('checkoutSummary');
            const selectedCount = document.getElementById('selectedCount');
            const totalPrice = document.getElementById('totalPrice');

            const microservices = @json($microservices->map(function($m) {
                return ['id' => $m->id, 'price' => $m->price];
            }));

            function updateSummary() {
                const checked = Array.from(checkboxes).filter(cb => cb.checked);
                const count = checked.length;

                if (count > 0) {
                    summary.style.display = 'block';
                    selectedCount.textContent = count;

                    const total = checked.reduce((sum, cb) => {
                        const microservice = microservices.find(m => m.id == cb.value);
                        return sum + (microservice ? parseFloat(microservice.price) : 0);
                    }, 0);

                    totalPrice.textContent = total.toFixed(2);
                } else {
                    summary.style.display = 'none';
                }
            }

            checkboxes.forEach(cb => {
                cb.addEventListener('change', updateSummary);
            });
        });
    </script>
</body>
</html>
