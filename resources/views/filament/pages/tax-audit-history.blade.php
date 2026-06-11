<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Tax Configuration Change History
        </x-slot>
        <x-slot name="description">
            Track all changes made to tax configurations including who made the change and when
        </x-slot>

        {{ $this->table }}
    </x-filament::section>
</x-filament-panels::page>
