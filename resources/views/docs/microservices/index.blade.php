<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Microservices Documentation - EventPilot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Microservices Documentation</h1>
                        <p class="mt-1 text-sm text-gray-600">Technical documentation for all available microservices</p>
                    </div>
                    <a href="/admin" class="text-sm text-blue-600 hover:text-blue-800">Back to Admin</a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Category Filter -->
            <div class="mb-8">
                <div class="flex flex-wrap gap-2">
                    @php
                        $categories = $microservices->pluck('category')->unique()->filter()->sort();
                    @endphp
                    <span class="px-3 py-1 text-sm font-medium text-gray-700">Filter by category:</span>
                    @foreach($categories as $category)
                        <span class="px-3 py-1 text-sm font-medium text-gray-600 bg-gray-100 rounded-full">
                            {{ ucfirst(str_replace('-', ' ', $category)) }}
                        </span>
                    @endforeach
                </div>
            </div>

            <!-- Microservices Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($microservices as $microservice)
                    <a href="{{ route('docs.microservices.show', $microservice->slug) }}"
                       class="block bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        {{ $microservice->getTranslation('name', app()->getLocale()) ?? $microservice->name['en'] ?? $microservice->slug }}
                                    </h3>
                                    @if($microservice->category)
                                        <span class="inline-block mt-1 px-2 py-0.5 text-xs font-medium text-gray-600 bg-gray-100 rounded">
                                            {{ ucfirst(str_replace('-', ' ', $microservice->category)) }}
                                        </span>
                                    @endif
                                </div>
                                @if($microservice->icon_image)
                                    <img src="{{ Storage::url($microservice->icon_image) }}"
                                         alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}"
                                         class="w-10 h-10 rounded-lg">
                                @endif
                            </div>

                            <p class="mt-3 text-sm text-gray-600 line-clamp-2">
                                {{ $microservice->getTranslation('short_description', app()->getLocale()) ?? $microservice->short_description['en'] ?? '' }}
                            </p>

                            <div class="mt-4 flex items-center text-sm text-blue-600">
                                <span>View Documentation</span>
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($microservices->isEmpty())
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No microservices</h3>
                    <p class="mt-1 text-sm text-gray-500">No microservices have been configured yet.</p>
                </div>
            @endif
        </main>
    </div>
</body>
</html>
