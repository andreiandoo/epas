<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Search and Select --}}
        <x-filament::section>
            <x-slot name="heading">Select Customers to Merge</x-slot>
            <x-slot name="description">Search for customers by email or UUID. The source customer will be merged into the target customer.</x-slot>

            <form wire:submit="merge">
                {{ $this->form }}
            </form>

            @if($sourceCustomerId && $targetCustomerId && $sourceCustomerId !== $targetCustomerId)
                <div class="mt-4 flex justify-center">
                    <x-filament::button wire:click="swapCustomers" color="gray" icon="heroicon-o-arrows-right-left">
                        Swap Source & Target
                    </x-filament::button>
                </div>
            @endif
        </x-filament::section>

        {{-- Customer Comparison --}}
        @if($sourceCustomer && $targetCustomer)
            <div class="grid md:grid-cols-2 gap-6">
                {{-- Source Customer --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-arrow-right class="w-5 h-5 text-danger-500" />
                            Source (Will Be Merged)
                        </span>
                    </x-slot>

                    <div class="space-y-3">
                        <div>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Name:</span>
                            <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $sourceCustomer['display_name'] }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Email:</span>
                            <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $sourceCustomer['email'] ?? 'N/A' }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 pt-3 border-t dark:border-gray-700">
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Orders:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $sourceCustomer['total_orders'] }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Spent:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">${{ number_format($sourceCustomer['total_spent'], 2) }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Visits:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $sourceCustomer['total_visits'] }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Events:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $sourceCustomer['events_count'] }}</span>
                            </div>
                        </div>
                        <div class="pt-3 border-t dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
                            <div>First seen: {{ $sourceCustomer['first_seen_at'] ?? 'N/A' }}</div>
                            <div>Last seen: {{ $sourceCustomer['last_seen_at'] ?? 'N/A' }}</div>
                        </div>
                    </div>
                </x-filament::section>

                {{-- Target Customer --}}
                <x-filament::section>
                    <x-slot name="heading">
                        <span class="flex items-center gap-2">
                            <x-heroicon-o-arrow-left class="w-5 h-5 text-success-500" />
                            Target (Will Receive Data)
                        </span>
                    </x-slot>

                    <div class="space-y-3">
                        <div>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Name:</span>
                            <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $targetCustomer['display_name'] }}</span>
                        </div>
                        <div>
                            <span class="text-sm text-gray-500 dark:text-gray-400">Email:</span>
                            <span class="ml-2 font-medium text-gray-900 dark:text-white">{{ $targetCustomer['email'] ?? 'N/A' }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 pt-3 border-t dark:border-gray-700">
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Orders:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $targetCustomer['total_orders'] }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Spent:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">${{ number_format($targetCustomer['total_spent'], 2) }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Visits:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $targetCustomer['total_visits'] }}</span>
                            </div>
                            <div>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Events:</span>
                                <span class="ml-2 font-semibold text-gray-900 dark:text-white">{{ $targetCustomer['events_count'] }}</span>
                            </div>
                        </div>
                        <div class="pt-3 border-t dark:border-gray-700 text-sm text-gray-500 dark:text-gray-400">
                            <div>First seen: {{ $targetCustomer['first_seen_at'] ?? 'N/A' }}</div>
                            <div>Last seen: {{ $targetCustomer['last_seen_at'] ?? 'N/A' }}</div>
                        </div>
                    </div>
                </x-filament::section>
            </div>

            {{-- Merge Preview --}}
            @php $preview = $this->getMergePreview(); @endphp
            <x-filament::section>
                <x-slot name="heading">Merge Preview</x-slot>
                <x-slot name="description">After merging, the target customer will have these combined totals:</x-slot>

                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-primary-600">{{ $preview['total_orders'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Orders</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-success-600">${{ number_format($preview['total_spent'], 2) }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Spent</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $preview['total_visits'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Visits</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $preview['events_count'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Events</div>
                    </div>
                    <div class="text-center p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ $preview['sessions_count'] }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Total Sessions</div>
                    </div>
                </div>

                <div class="mt-6 flex justify-center">
                    <x-filament::button wire:click="merge" color="danger" icon="heroicon-o-arrows-right-left" size="lg">
                        Merge Customers
                    </x-filament::button>
                </div>

                <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 inline-block text-warning-500" />
                    This action cannot be undone. The source customer will be marked as merged.
                </div>
            </x-filament::section>
        @endif

        {{-- Duplicate Candidates --}}
        @if(!empty($duplicateCandidates))
            <x-filament::section>
                <x-slot name="heading">Potential Duplicates</x-slot>
                <x-slot name="description">These customers share the same email address and may need to be merged.</x-slot>

                <div class="space-y-4">
                    @foreach($duplicateCandidates as $group)
                        <div class="border dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ $group['count'] }} customers with same email
                                </span>
                            </div>
                            <div class="space-y-2">
                                @foreach($group['customers'] as $index => $customer)
                                    <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-800 rounded">
                                        <div>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $customer['display_name'] }}</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $customer['email'] }}</span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">
                                                ({{ $customer['total_orders'] }} orders, ${{ number_format($customer['total_spent'], 2) }})
                                            </span>
                                        </div>
                                        @if($index > 0)
                                            <x-filament::button
                                                wire:click="selectDuplicatePair({{ $customer['id'] }}, {{ $group['customers'][0]['id'] }})"
                                                size="sm"
                                                color="gray"
                                            >
                                                Merge into Primary
                                            </x-filament::button>
                                        @else
                                            <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-medium text-success-800 dark:bg-success-900 dark:text-success-200">
                                                Primary
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
