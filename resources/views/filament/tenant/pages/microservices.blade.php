<x-filament-panels::page>
    @if($microservices->isEmpty())
        <div class="backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 p-12 text-center">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                <x-heroicon-o-puzzle-piece class="w-8 h-8 text-indigo-500" />
            </div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">No microservices yet</h3>
            <p class="text-gray-600 dark:text-gray-400 mb-6">Browse our store to add powerful features to your account.</p>
            <a href="{{ route('store.index') }}" class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-semibold rounded-xl hover:from-indigo-700 hover:to-purple-700 transition-all shadow-lg shadow-indigo-500/25">
                Browse Store
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($microservices as $microservice)
                <a href="/tenant/microservices/{{ $microservice->slug }}/settings"
                   class="group block backdrop-blur-sm bg-white/70 dark:bg-gray-800/70 rounded-2xl shadow-lg border border-white/20 dark:border-gray-700/50 overflow-hidden hover:shadow-xl hover:scale-[1.02] transition-all duration-300">
                    <div class="p-6">
                        {{-- Header with icon and status --}}
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                @if($microservice->icon_image)
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/10 to-purple-500/10 p-1.5 flex items-center justify-center">
                                        <img src="{{ Storage::disk('public')->url($microservice->icon_image) }}"
                                             class="w-full h-full object-contain"
                                             alt="{{ $microservice->getTranslation('name', app()->getLocale()) }}">
                                    </div>
                                @else
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500/20 to-purple-500/20 flex items-center justify-center">
                                        <x-heroicon-o-puzzle-piece class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                @endif
                                <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                                    {{ $microservice->getTranslation('name', app()->getLocale()) }}
                                </h3>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5 animate-pulse"></span>
                                Active
                            </span>
                        </div>

                        {{-- Description --}}
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4 line-clamp-2">
                            {{ $microservice->getTranslation('short_description', app()->getLocale()) }}
                        </p>

                        {{-- Meta info --}}
                        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-500 pt-4 border-t border-gray-200/50 dark:border-gray-700/50">
                            @if($microservice->pivot->activated_at)
                                <span class="flex items-center gap-1">
                                    <x-heroicon-o-calendar class="w-3.5 h-3.5" />
                                    {{ \Carbon\Carbon::parse($microservice->pivot->activated_at)->format('M d, Y') }}
                                </span>
                            @else
                                <span></span>
                            @endif

                            <span class="flex items-center gap-1 text-indigo-600 dark:text-indigo-400 font-medium group-hover:translate-x-1 transition-transform">
                                Configure
                                <x-heroicon-o-arrow-right class="w-3.5 h-3.5" />
                            </span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8 text-center">
            <a href="{{ route('store.index') }}" class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                <span>Browse more microservices</span>
                <x-heroicon-o-arrow-right class="w-4 h-4" />
            </a>
        </div>
    @endif
</x-filament-panels::page>
