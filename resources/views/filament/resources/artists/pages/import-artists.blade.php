<x-filament-panels::page>
    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove>Import Artists</span>
                <span wire:loading>Importing...</span>
            </x-filament::button>
        </div>
    </form>

    @if(!empty($importResults))
        <div class="mt-6">
            <x-filament::section>
                <x-slot name="heading">Import Results</x-slot>

                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-600">{{ $importResults['imported'] }}</div>
                        <div class="text-sm text-green-700">Imported</div>
                    </div>
                    <div class="bg-blue-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600">{{ $importResults['updated'] }}</div>
                        <div class="text-sm text-blue-700">Updated</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-600">{{ $importResults['skipped'] }}</div>
                        <div class="text-sm text-gray-700">Skipped</div>
                    </div>
                </div>

                @if(!empty($importResults['errors']))
                    <div class="mt-4">
                        <h4 class="font-medium text-red-600 mb-2">Errors ({{ count($importResults['errors']) }})</h4>
                        <ul class="list-disc list-inside text-sm text-red-600 space-y-1">
                            @foreach($importResults['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
