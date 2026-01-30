<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Event Summary --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ $taxReport['event']['title'] }}</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $taxReport['event']['date'] }} &middot; {{ $taxReport['event']['venue'] }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $taxReport['event']['location'] }}</p>
                </div>
                <span class="px-3 py-1 text-sm rounded-full {{ $taxReport['event']['status'] === 'upcoming' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                    {{ ucfirst($taxReport['event']['status']) }}
                </span>
            </div>
        </div>

        {{-- Financial Summary --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Estimated Revenue</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ number_format($taxReport['estimated_revenue'], 2) }} RON</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Taxes</p>
                <p class="text-xl font-bold text-warning-600">{{ number_format($taxReport['total_tax'], 2) }} RON</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Effective Tax Rate</p>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $taxReport['effective_tax_rate'] }}%</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">Net Revenue</p>
                <p class="text-xl font-bold text-green-600">{{ number_format($taxReport['net_revenue'], 2) }} RON</p>
            </div>
        </div>

        {{-- Tax Details --}}
        @if(count($taxReport['taxes']) > 0)
            <div class="space-y-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Applicable Taxes</h3>

                @foreach($taxReport['taxes'] as $tax)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        {{-- Tax Header --}}
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div>
                                <h4 class="font-semibold text-gray-900 dark:text-white">{{ $tax['name'] }}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2 py-0.5 text-xs rounded-full {{ $tax['type'] === 'general' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' }}">
                                        {{ $tax['type'] === 'general' ? 'General Tax' : 'Local Tax' }}
                                    </span>
                                    <span class="text-sm text-gray-500">{{ $tax['formatted_value'] }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-lg font-bold text-warning-600">{{ number_format($tax['amount'], 2) }} RON</p>
                                @if($tax['payment_deadline'])
                                    <p class="text-xs {{ $tax['payment_deadline']['is_overdue'] ? 'text-red-600' : 'text-gray-500' }}">
                                        Due: {{ $tax['payment_deadline']['date'] }}
                                        @if($tax['payment_deadline']['is_overdue'])
                                            <span class="font-semibold">(OVERDUE)</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="p-6 space-y-6">
                            {{-- Tax Application Rules --}}
                            @if($tax['explanation'] || $tax['legal_basis'])
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                                        <x-heroicon-o-information-circle class="w-4 h-4" />
                                        Tax Application Rules
                                    </h5>
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 space-y-2 text-sm">
                                        @if($tax['explanation'])
                                            <p class="text-gray-600 dark:text-gray-400">{{ $tax['explanation'] }}</p>
                                        @endif
                                        @if($tax['legal_basis'])
                                            <p class="text-gray-500 dark:text-gray-500">
                                                <strong>Legal basis:</strong> {{ $tax['legal_basis'] }}
                                            </p>
                                        @endif
                                        @if($tax['is_added_to_price'])
                                            <p class="text-blue-600 dark:text-blue-400">This tax is included in the ticket price.</p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Payment Information --}}
                            @if($tax['payment_info'])
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                                        <x-heroicon-o-banknotes class="w-4 h-4" />
                                        Payment Information
                                    </h5>
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 space-y-3 text-sm">
                                        @if($tax['payment_info']['beneficiary'])
                                            <div class="flex justify-between">
                                                <span class="text-gray-500 dark:text-gray-400">Beneficiary:</span>
                                                <span class="text-gray-900 dark:text-white font-medium">{{ $tax['payment_info']['beneficiary'] }}</span>
                                            </div>
                                        @endif
                                        @if($tax['payment_info']['iban'])
                                            <div class="flex justify-between">
                                                <span class="text-gray-500 dark:text-gray-400">IBAN:</span>
                                                <span class="text-gray-900 dark:text-white font-mono text-xs">{{ $tax['payment_info']['iban'] }}</span>
                                            </div>
                                        @endif
                                        @if($tax['payment_info']['address'])
                                            <div class="flex justify-between">
                                                <span class="text-gray-500 dark:text-gray-400">Address:</span>
                                                <span class="text-gray-900 dark:text-white">{{ $tax['payment_info']['address'] }}</span>
                                            </div>
                                        @endif
                                        @if($tax['payment_info']['where_to_pay'])
                                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                                <span class="text-gray-500 dark:text-gray-400 block mb-1">Where to pay:</span>
                                                <span class="text-gray-900 dark:text-white">{{ $tax['payment_info']['where_to_pay'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Payment Terms --}}
                            @if($tax['payment_deadline'])
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                                        <x-heroicon-o-calendar class="w-4 h-4" />
                                        Payment Terms
                                    </h5>
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 text-sm">
                                        <div class="flex items-center justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">{{ $tax['payment_deadline']['description'] }}</span>
                                            <span class="{{ $tax['payment_deadline']['is_overdue'] ? 'text-red-600 font-bold' : 'text-gray-900 dark:text-white' }}">
                                                {{ $tax['payment_deadline']['date'] }}
                                            </span>
                                        </div>
                                        @if(!$tax['payment_deadline']['is_overdue'] && $tax['payment_deadline']['days_remaining'] > 0)
                                            <p class="text-green-600 dark:text-green-400 mt-2 text-xs">
                                                {{ $tax['payment_deadline']['days_remaining'] }} days remaining
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Legal & Documentation --}}
                            @if($tax['payment_info'] && ($tax['payment_info']['declaration'] || $tax['payment_info']['before_event_instructions'] || $tax['payment_info']['after_event_instructions']))
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                                        <x-heroicon-o-document-text class="w-4 h-4" />
                                        Legal & Documentation
                                    </h5>
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 space-y-3 text-sm">
                                        @if($tax['payment_info']['declaration'])
                                            <div>
                                                <span class="text-gray-500 dark:text-gray-400 block mb-1">Declaration required:</span>
                                                <span class="text-gray-900 dark:text-white">{{ $tax['payment_info']['declaration'] }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Instructions --}}
                            @if($tax['payment_info'] && ($tax['payment_info']['before_event_instructions'] || $tax['payment_info']['after_event_instructions']))
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2 flex items-center gap-2">
                                        <x-heroicon-o-clipboard-document-list class="w-4 h-4" />
                                        Instructions
                                    </h5>
                                    <div class="space-y-3">
                                        @if($tax['payment_info']['before_event_instructions'])
                                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                                <h6 class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Before Event</h6>
                                                <div class="text-sm text-blue-700 dark:text-blue-400 prose prose-sm dark:prose-invert max-w-none">
                                                    {!! nl2br(e($tax['payment_info']['before_event_instructions'])) !!}
                                                </div>
                                            </div>
                                        @endif
                                        @if($tax['payment_info']['after_event_instructions'])
                                            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                                                <h6 class="text-sm font-medium text-green-800 dark:text-green-300 mb-2">After Event</h6>
                                                <div class="text-sm text-green-700 dark:text-green-400 prose prose-sm dark:prose-invert max-w-none">
                                                    {!! nl2br(e($tax['payment_info']['after_event_instructions'])) !!}
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
                <x-heroicon-o-check-circle class="mx-auto h-12 w-12 text-green-500" />
                <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">No taxes applicable</h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">This event has no applicable taxes based on its location and type.</p>
            </div>
        @endif

        {{-- Back Button --}}
        <div class="pt-4">
            <a href="{{ route('filament.marketplace.pages.tax-reports') }}"
               class="inline-flex items-center gap-2 text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400">
                <x-heroicon-o-arrow-left class="w-4 h-4" />
                Back to Tax Reports
            </a>
        </div>
    </div>
</x-filament-panels::page>
