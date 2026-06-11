<x-filament-panels::page>
    {{-- Summary Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-primary-600">{{ $summary['total_active'] ?? 0 }}</div>
                <div class="text-sm text-gray-500">Active Taxes</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-success-600">{{ $summary['general']['active'] ?? 0 }}</div>
                <div class="text-sm text-gray-500">General Taxes</div>
                <div class="text-xs text-gray-400">{{ $summary['general']['total'] ?? 0 }} total</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-info-600">{{ $summary['local']['active'] ?? 0 }}</div>
                <div class="text-sm text-gray-500">Local Taxes</div>
                <div class="text-xs text-gray-400">{{ $summary['local']['total'] ?? 0 }} total</div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-center">
                <div class="text-3xl font-bold text-warning-600">{{ ($summary['general']['inactive'] ?? 0) + ($summary['local']['inactive'] ?? 0) }}</div>
                <div class="text-sm text-gray-500">Inactive/Expired</div>
            </div>
        </x-filament::section>
    </div>

    {{-- Configuration Issues --}}
    @if(count($issues) > 0)
        <x-filament::section class="mb-6">
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500" />
                    Configuration Notices
                </div>
            </x-slot>

            <div class="space-y-2">
                @foreach($issues as $issue)
                    <div class="flex items-start gap-3 p-3 rounded-lg {{ $issue['severity'] === 'warning' ? 'bg-warning-50 dark:bg-warning-900/20' : 'bg-info-50 dark:bg-info-900/20' }}">
                        @if($issue['severity'] === 'warning')
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-warning-500 flex-shrink-0 mt-0.5" />
                        @else
                            <x-heroicon-o-information-circle class="w-5 h-5 text-info-500 flex-shrink-0 mt-0.5" />
                        @endif
                        <span class="text-sm {{ $issue['severity'] === 'warning' ? 'text-warning-700 dark:text-warning-300' : 'text-info-700 dark:text-info-300' }}">
                            {{ $issue['message'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Recent General Taxes --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Recent General Taxes</span>
                    <x-filament::link href="{{ route('filament.admin.resources.general-taxes.index') }}" class="text-sm">
                        View All
                    </x-filament::link>
                </div>
            </x-slot>

            @if($recentGeneralTaxes->isEmpty())
                <div class="text-center py-6 text-gray-500">
                    <x-heroicon-o-calculator class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                    <p>No general taxes configured yet.</p>
                    <x-filament::link href="{{ route('filament.admin.resources.general-taxes.create') }}" class="mt-2 inline-block">
                        Add your first general tax
                    </x-filament::link>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($recentGeneralTaxes as $tax)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <div class="font-medium">{{ $tax->name }}</div>
                                <div class="text-sm text-gray-500">
                                    @if($tax->eventType)
                                        {{ $tax->eventType->name[$tenantLanguage] ?? $tax->eventType->name['en'] ?? 'Unknown' }}
                                    @else
                                        All Event Types
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ $tax->getFormattedValue() }}</div>
                                <div class="text-xs">
                                    @php $status = $tax->getValidityStatus(); @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $status === 'active' ? 'bg-success-100 text-success-800' : '' }}
                                        {{ $status === 'inactive' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $status === 'scheduled' ? 'bg-info-100 text-info-800' : '' }}
                                        {{ $status === 'expired' ? 'bg-danger-100 text-danger-800' : '' }}
                                    ">
                                        {{ ucfirst($status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Recent Local Taxes --}}
        <x-filament::section>
            <x-slot name="heading">
                <div class="flex items-center justify-between">
                    <span>Recent Local Taxes</span>
                    <x-filament::link href="{{ route('filament.admin.resources.local-taxes.index') }}" class="text-sm">
                        View All
                    </x-filament::link>
                </div>
            </x-slot>

            @if($recentLocalTaxes->isEmpty())
                <div class="text-center py-6 text-gray-500">
                    <x-heroicon-o-map-pin class="w-12 h-12 mx-auto mb-2 text-gray-300" />
                    <p>No local taxes configured yet.</p>
                    <x-filament::link href="{{ route('filament.admin.resources.local-taxes.create') }}" class="mt-2 inline-block">
                        Add your first local tax
                    </x-filament::link>
                </div>
            @else
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($recentLocalTaxes as $tax)
                        <div class="py-3 flex items-center justify-between">
                            <div>
                                <div class="font-medium">{{ $tax->getLocationString() }}</div>
                                <div class="text-sm text-gray-500">
                                    @if($tax->eventTypes->isEmpty())
                                        All Event Types
                                    @else
                                        {{ $tax->eventTypes->count() }} event type(s)
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ $tax->getFormattedValue() }}</div>
                                <div class="text-xs">
                                    @php $status = $tax->getValidityStatus(); @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                        {{ $status === 'active' ? 'bg-success-100 text-success-800' : '' }}
                                        {{ $status === 'inactive' ? 'bg-gray-100 text-gray-800' : '' }}
                                        {{ $status === 'scheduled' ? 'bg-info-100 text-info-800' : '' }}
                                        {{ $status === 'expired' ? 'bg-danger-100 text-danger-800' : '' }}
                                    ">
                                        {{ ucfirst($status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Quick Links --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Quick Actions</x-slot>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="{{ route('filament.admin.resources.general-taxes.index') }}"
               class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <x-heroicon-o-calculator class="w-8 h-8 text-primary-500" />
                <div>
                    <div class="font-medium">General Taxes</div>
                    <div class="text-sm text-gray-500">Manage all</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.local-taxes.index') }}"
               class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <x-heroicon-o-map-pin class="w-8 h-8 text-success-500" />
                <div>
                    <div class="font-medium">Local Taxes</div>
                    <div class="text-sm text-gray-500">By location</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.general-taxes.create') }}"
               class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <x-heroicon-o-plus-circle class="w-8 h-8 text-info-500" />
                <div>
                    <div class="font-medium">New General Tax</div>
                    <div class="text-sm text-gray-500">Add tax rule</div>
                </div>
            </a>

            <a href="{{ route('filament.admin.resources.local-taxes.create') }}"
               class="flex items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                <x-heroicon-o-plus-circle class="w-8 h-8 text-warning-500" />
                <div>
                    <div class="font-medium">New Local Tax</div>
                    <div class="text-sm text-gray-500">Add location tax</div>
                </div>
            </a>
        </div>
    </x-filament::section>
</x-filament-panels::page>
