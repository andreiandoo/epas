<x-filament-panels::page>
    <div class="seating-designer-root space-y-6"
         wire:ignore
         x-cloak
         x-data="{
            stage: null,
            zoom: 1,
            sections: {{ Js::from($sections) }},
            canvasWidth: {{ $seatingLayout->canvas_w ?? 1200 }},
            canvasHeight: {{ $seatingLayout->canvas_h ?? 800 }},
            init() {
                console.log('Alpine initialized');
            }
         }"
         x-init="init()">
        <div class="p-4 bg-white rounded-lg shadow">
            <h3 class="text-lg font-bold">Seating Designer - Minimal Test</h3>
            <p class="text-gray-600">Canvas: <span x-text="canvasWidth"></span> x <span x-text="canvasHeight"></span></p>
            <p class="text-gray-600">Sections: <span x-text="sections.length"></span></p>
        </div>
    </div>
</x-filament-panels::page>
