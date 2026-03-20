<x-filament-panels::page>

    {{-- CoreCustomer form schema (Segmentation, Purchase, Engagement, etc.) --}}
    {{ $this->form }}

    @if($this->hasMarketplaceData)
        {{-- Separator --}}
        <div class="pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2 mb-4">
                <x-filament::icon icon="heroicon-o-shopping-bag" class="w-5 h-5 text-primary-500" />
                Marketplace Customer Data
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                    (via {{ $this->linkedMarketplaceCustomer->marketplaceClient?->name ?? 'marketplace' }})
                </span>
            </h2>
        </div>

        {{-- Reuse the marketplace customer view blade content --}}
        @include('filament.marketplace-customers.pages.view-marketplace-customer-content', ['record' => $this->linkedMarketplaceCustomer])
    @else
        <div class="p-6 mt-6 text-center border border-dashed rounded-lg border-gray-300 dark:border-gray-700">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-8 h-8 mx-auto mb-2 text-gray-400" />
            <p class="text-sm text-gray-500 dark:text-gray-400">Nu a fost găsit un cont de marketplace asociat acestui client.</p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Asocierea se face automat pe baza adresei de email.</p>
        </div>
    @endif

</x-filament-panels::page>
