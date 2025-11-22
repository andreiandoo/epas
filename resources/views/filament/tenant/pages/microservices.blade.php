<x-filament-panels::page>
    @if($microservices->isEmpty())
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border p-12 text-center">
            <x-heroicon-o-puzzle-piece class="w-12 h-12 text-gray-300 mx-auto mb-4" />
            <h3 class="text-lg font-semibold text-gray-900 mb-2">No microservices yet</h3>
            <p class="text-gray-600 mb-6">Browse our store to add powerful features to your account.</p>
            <a href="{{ route('store.index') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700">
                Browse Store
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($microservices as $microservice)
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border overflow-hidden">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="font-semibold text-gray-900">
                                {{ $microservice->getTranslation('name', app()->getLocale()) }}
                            </h3>
                            @php
                                $status = $microservice->pivot->status;
                                $statusColors = [
                                    'active' => 'bg-green-100 text-green-800',
                                    'trial' => 'bg-blue-100 text-blue-800',
                                    'suspended' => 'bg-yellow-100 text-yellow-800',
                                    'cancelled' => 'bg-red-100 text-red-800',
                                ];
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucfirst($status) }}
                            </span>
                        </div>

                        <p class="text-sm text-gray-600 mb-4">
                            {{ $microservice->getTranslation('short_description', app()->getLocale()) }}
                        </p>

                        <dl class="text-sm space-y-2">
                            @if($microservice->pivot->activated_at)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Activated</dt>
                                    <dd class="font-medium">{{ \Carbon\Carbon::parse($microservice->pivot->activated_at)->format('M d, Y') }}</dd>
                                </div>
                            @endif
                            @if($microservice->pivot->expires_at)
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Expires</dt>
                                    <dd class="font-medium {{ \Carbon\Carbon::parse($microservice->pivot->expires_at)->isPast() ? 'text-red-600' : '' }}">
                                        {{ \Carbon\Carbon::parse($microservice->pivot->expires_at)->format('M d, Y') }}
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 text-center">
            <a href="{{ route('store.index') }}" class="text-indigo-600 hover:text-indigo-800">
                Browse more microservices &rarr;
            </a>
        </div>
    @endif
</x-filament-panels::page>
