<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $microservice->getTranslation('name', app()->getLocale()) }} - Documentation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <nav class="text-sm text-gray-500 mb-2">
                            <a href="{{ route('docs.microservices.index') }}" class="hover:text-gray-700">Documentation</a>
                            <span class="mx-2">/</span>
                            <span class="text-gray-900">{{ $microservice->getTranslation('name', app()->getLocale()) }}</span>
                        </nav>
                        <h1 class="text-3xl font-bold text-gray-900">
                            {{ $microservice->getTranslation('name', app()->getLocale()) ?? $microservice->name['en'] ?? $microservice->slug }}
                        </h1>
                    </div>
                    <a href="{{ route('docs.microservices.index') }}" class="text-sm text-blue-600 hover:text-blue-800">
                        Back to All Microservices
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Main Documentation -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Overview -->
                    <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Overview</h2>
                        <div class="prose prose-sm max-w-none text-gray-600">
                            {!! $microservice->getTranslation('description', app()->getLocale()) ?? $microservice->description['en'] ?? 'No description available.' !!}
                        </div>
                    </section>

                    <!-- Features -->
                    @php
                        $locale = app()->getLocale();
                        $features = $microservice->features[$locale] ?? $microservice->features['en'] ?? $microservice->features ?? [];
                    @endphp
                    @if(!empty($features))
                        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Features</h2>
                            <ul class="space-y-3">
                                @foreach($features as $feature)
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        <span class="text-sm text-gray-600">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <!-- API Endpoints -->
                    @if(!empty($microservice->metadata['endpoints']))
                        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">API Endpoints</h2>
                            <div class="space-y-3">
                                @foreach($microservice->metadata['endpoints'] as $endpoint)
                                    @php
                                        $parts = explode(' ', $endpoint, 2);
                                        $method = $parts[0] ?? 'GET';
                                        $path = $parts[1] ?? $endpoint;
                                        $methodColors = [
                                            'GET' => 'bg-green-100 text-green-800',
                                            'POST' => 'bg-blue-100 text-blue-800',
                                            'PUT' => 'bg-yellow-100 text-yellow-800',
                                            'PATCH' => 'bg-orange-100 text-orange-800',
                                            'DELETE' => 'bg-red-100 text-red-800',
                                        ];
                                    @endphp
                                    <div class="flex items-center space-x-3 font-mono text-sm">
                                        <span class="px-2 py-1 rounded text-xs font-bold {{ $methodColors[$method] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $method }}
                                        </span>
                                        <code class="text-gray-700">{{ $path }}</code>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <!-- Technical Details -->
                    @if(!empty($microservice->metadata))
                        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-4">Technical Details</h2>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                @foreach($microservice->metadata as $key => $value)
                                    @if($key !== 'endpoints')
                                        <div>
                                            <dt class="text-sm font-medium text-gray-500">
                                                {{ ucwords(str_replace('_', ' ', $key)) }}
                                            </dt>
                                            <dd class="mt-1 text-sm text-gray-900">
                                                @if(is_array($value))
                                                    {{ implode(', ', $value) }}
                                                @else
                                                    {{ $value }}
                                                @endif
                                            </dd>
                                        </div>
                                    @endif
                                @endforeach
                            </dl>
                        </section>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Quick Info -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Info</h3>
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Category</dt>
                                <dd class="mt-1">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ ucfirst(str_replace('-', ' ', $microservice->category ?? 'Uncategorized')) }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Pricing</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    €{{ number_format($microservice->price, 2) }} / {{ $microservice->billing_cycle }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    @if($microservice->is_active)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Inactive
                                        </span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Related Microservices -->
                    @if($related->isNotEmpty())
                        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Related Microservices</h3>
                            <ul class="space-y-3">
                                @foreach($related as $relatedService)
                                    <li>
                                        <a href="{{ route('docs.microservices.show', $relatedService->slug) }}"
                                           class="block hover:bg-gray-50 -mx-2 px-2 py-2 rounded">
                                            <span class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                                {{ $relatedService->getTranslation('name', app()->getLocale()) }}
                                            </span>
                                            <p class="text-xs text-gray-500 mt-1 line-clamp-2">
                                                {{ $relatedService->getTranslation('short_description', app()->getLocale()) }}
                                            </p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Need Help -->
                    <div class="bg-blue-50 rounded-lg border border-blue-200 p-6">
                        <h3 class="text-lg font-semibold text-blue-900 mb-2">Need Help?</h3>
                        <p class="text-sm text-blue-700 mb-4">
                            Contact our support team for assistance with integration or configuration.
                        </p>
                        <a href="mailto:support@eventpilot.com"
                           class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                            Contact Support
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
