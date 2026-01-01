<x-filament-panels::page class="!p-0 !max-w-none">
    <style>
        /* Override Filament container styles for full-width */
        .fi-page-content > div {
            max-width: none !important;
            padding: 0 !important;
        }
        .fi-main {
            padding: 0 !important;
        }
        .fi-page {
            padding: 0 !important;
        }
    </style>

    <div class="-mx-4 -mt-4 sm:-mx-6 sm:-mt-6 lg:-mx-8 lg:-mt-8">
        @livewire('intelligence-monitor')
    </div>
</x-filament-panels::page>
