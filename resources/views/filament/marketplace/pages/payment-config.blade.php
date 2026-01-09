<x-filament-panels::page>
    <div class="space-y-6">
        @if(!$hasPaymentMethods)
            {{-- No payment methods configured --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-credit-card class="w-8 h-8 text-gray-400" />
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">No Payment Methods Available</h3>
                        <p class="mt-2 text-gray-600 dark:text-gray-400">
                            No payment methods have been assigned to your marketplace yet.
                            Please contact the platform administrator to enable payment processing.
                        </p>
                    </div>
                </div>
            </div>
        @else
            {{-- Payment Methods List --}}
            <div class="grid gap-4">
                @foreach($paymentMethods as $pm)
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-4">
                                @if($pm['icon'])
                                    <img src="{{ $pm['icon'] }}" alt="{{ $pm['name'] }}" class="w-12 h-12 rounded-lg object-contain" />
                                @else
                                    <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <x-heroicon-o-credit-card class="w-6 h-6 text-gray-400" />
                                    </div>
                                @endif
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $pm['name'] }}</h3>
                                        @if($pm['is_default'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                                Default
                                            </span>
                                        @endif
                                        @if($pm['is_active'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                Inactive
                                            </span>
                                        @endif
                                        @if(!$pm['is_configured'])
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100">
                                                Not Configured
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $pm['description'] }}</p>
                                </div>
                            </div>
                            <div>
                                <button
                                    wire:click="editPaymentMethod({{ $pm['id'] }})"
                                    class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-lg text-primary-600 bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/50 dark:text-primary-400 dark:hover:bg-primary-900"
                                >
                                    <x-heroicon-o-cog-6-tooth class="w-4 h-4 mr-1.5" />
                                    Configure
                                </button>
                            </div>
                        </div>

                        {{-- Configuration Form (shown when editing this payment method) --}}
                        @if($this->editingPaymentMethodId === $pm['id'])
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <form wire:submit.prevent="savePaymentMethod">
                                    <div class="space-y-4">
                                        {{-- Status toggles --}}
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <label class="flex items-center gap-3">
                                                <input type="checkbox" wire:model="formData.is_active" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Enable this payment method</span>
                                            </label>
                                            <label class="flex items-center gap-3">
                                                <input type="checkbox" wire:model="formData.is_default" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Set as default payment method</span>
                                            </label>
                                        </div>

                                        {{-- Dynamic settings fields based on schema --}}
                                        @if(count($pm['settings_schema']) > 0)
                                            <div class="mt-4">
                                                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Configuration Settings</h4>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    @foreach($pm['settings_schema'] as $field)
                                                        <div class="{{ ($field['type'] ?? 'text') === 'textarea' ? 'md:col-span-2' : '' }}">
                                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                                {{ $field['label'] }}
                                                                @if($field['required'] ?? false)
                                                                    <span class="text-danger-500">*</span>
                                                                @endif
                                                            </label>
                                                            @if(($field['type'] ?? 'text') === 'textarea')
                                                                <textarea
                                                                    wire:model="formData.settings.{{ $field['key'] }}"
                                                                    rows="3"
                                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                    @if($field['required'] ?? false) required @endif
                                                                ></textarea>
                                                            @elseif(($field['type'] ?? 'text') === 'password')
                                                                <input
                                                                    type="password"
                                                                    wire:model="formData.settings.{{ $field['key'] }}"
                                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                    @if($field['required'] ?? false) required @endif
                                                                />
                                                            @elseif(($field['type'] ?? 'text') === 'boolean')
                                                                <label class="flex items-center gap-2 mt-2">
                                                                    <input
                                                                        type="checkbox"
                                                                        wire:model="formData.settings.{{ $field['key'] }}"
                                                                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                                                    />
                                                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $field['label'] }}</span>
                                                                </label>
                                                            @elseif(($field['type'] ?? 'text') === 'select')
                                                                <select
                                                                    wire:model="formData.settings.{{ $field['key'] }}"
                                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                    @if($field['required'] ?? false) required @endif
                                                                >
                                                                    <option value="">Select...</option>
                                                                    @foreach($field['options'] ?? [] as $option)
                                                                        <option value="{{ $option }}">{{ ucfirst($option) }}</option>
                                                                    @endforeach
                                                                </select>
                                                            @elseif(($field['type'] ?? 'text') === 'number')
                                                                <input
                                                                    type="number"
                                                                    wire:model="formData.settings.{{ $field['key'] }}"
                                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                    @if($field['required'] ?? false) required @endif
                                                                />
                                                            @else
                                                                <input
                                                                    type="text"
                                                                    wire:model="formData.settings.{{ $field['key'] }}"
                                                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                                    @if($field['required'] ?? false) required @endif
                                                                />
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        {{-- Form actions --}}
                                        <div class="flex items-center justify-end gap-3 mt-6">
                                            <button
                                                type="button"
                                                wire:click="cancelEdit"
                                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500"
                                            >
                                                Save Configuration
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
