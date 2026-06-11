
    {{-- TAB NAVIGATION --}}
    <div x-data="{ activeTab: 'overview' }" class="space-y-6">
        {{-- Tab Buttons --}}
        <div class="flex flex-wrap gap-2 pb-1 border-b border-gray-200 dark:border-gray-700">
            @foreach([
                'overview' => ['Prezentare Generală', 'heroicon-o-user-circle'],
                'gamification' => ['Gamification', 'heroicon-o-star'],
                'orders' => ['Comenzi & Bilete', 'heroicon-o-receipt-percent'],
                'emails' => ['Istoric Email-uri', 'heroicon-o-envelope'],
                'insights' => ['Customer Insights', 'heroicon-o-chart-bar'],
            ] as $tabKey => [$tabLabel, $tabIcon])
                <button
                    @click="activeTab = '{{ $tabKey }}'"
                    :class="activeTab === '{{ $tabKey }}'
                        ? 'border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-900/20'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-50 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:bg-gray-800'"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-all"
                >
                    <x-filament::icon :icon="$tabIcon" class="w-5 h-5" />
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        <div x-show="activeTab === 'overview'" x-cloak>
            @include('filament.marketplace-customers.pages.partials.overview-content')
        </div>

        <div x-show="activeTab === 'gamification'" x-cloak>
            @include('filament.marketplace-customers.pages.partials.gamification-content')
        </div>

        <div x-show="activeTab === 'orders'" x-cloak>
            @include('filament.marketplace-customers.pages.partials.orders-content')
        </div>

        <div x-show="activeTab === 'emails'" x-cloak>
            @include('filament.marketplace-customers.pages.partials.emails-content')
        </div>

        <div x-show="activeTab === 'insights'" x-cloak>
            @include('filament.marketplace-customers.pages.partials.insights-content')
        </div>
    </div>
