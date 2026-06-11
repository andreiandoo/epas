<x-filament-panels::page>
    <div class="mb-4">
        <a href="{{ \App\Filament\Marketplace\Resources\ContactListResource::getUrl('index') }}"
           class="text-sm text-primary-600 hover:text-primary-500">
            &larr; Back to Contact Lists
        </a>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
