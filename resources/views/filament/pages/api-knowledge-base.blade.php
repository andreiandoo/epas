<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
            <h3 class="text-lg font-semibold text-primary-700 dark:text-primary-300 mb-2">Base URL</h3>
            <code class="text-sm bg-primary-100 dark:bg-primary-800 px-2 py-1 rounded">{{ url('/api') }}</code>
        </div>

        @foreach($this->getEndpoints() as $category)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-bold">{{ $category['category'] }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $category['description'] }}</p>
                </div>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($category['endpoints'] as $endpoint)
                        <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex items-center px-2 py-1 text-xs font-bold rounded
                                    @if($endpoint['method'] === 'GET') bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300
                                    @elseif($endpoint['method'] === 'POST') bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300
                                    @elseif($endpoint['method'] === 'PUT') bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300
                                    @elseif($endpoint['method'] === 'DELETE') bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300
                                    @else bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300
                                    @endif">
                                    {{ $endpoint['method'] }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    <code class="text-sm font-mono text-gray-800 dark:text-gray-200 break-all">{{ $endpoint['path'] }}</code>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $endpoint['description'] }}</p>
                                    @if(!empty($endpoint['response']))
                                        <div class="mt-2">
                                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Response:</span>
                                            <code class="block mt-1 text-xs bg-gray-100 dark:bg-gray-700 p-2 rounded overflow-x-auto">{{ $endpoint['response'] }}</code>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold mb-2">Example Usage (cURL)</h3>
            <pre class="text-sm bg-gray-900 text-green-400 p-3 rounded overflow-x-auto"><code>curl -H "X-API-Key: your_api_key" \
     {{ url('/api/v1/public/artists/artist-slug/stats') }}</code></pre>
        </div>

        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
            <h3 class="text-lg font-semibold text-amber-700 dark:text-amber-300 mb-2">Rate Limiting</h3>
            <p class="text-sm text-amber-600 dark:text-amber-400">
                API requests are rate limited. If you exceed the limit, you'll receive a 429 Too Many Requests response.
                YouTube and Spotify data is cached for 6 hours to reduce API calls.
            </p>
        </div>
    </div>
</x-filament-panels::page>
