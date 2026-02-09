<x-filament-panels::page>
    <div class="seating-designer-root space-y-6"
         wire:ignore
         x-cloak
         x-data="{
            stage: null,
            layer: null,
            transformer: null,
            backgroundLayer: null,
            drawLayer: null,
            seatsLayer: null,
            zoom: 1,
            showGrid: true,
            snapToGrid: false,
            gridSize: 20,
            selectedSection: null,
            sections: {{ Js::from($sections) }},
            iconDefinitions: {{ Js::from($iconDefinitions ?? []) }},
            canvasWidth: {{ $seatingLayout->canvas_w ?? 1200 }},
            canvasHeight: {{ $seatingLayout->canvas_h ?? 800 }},
            backgroundColor: '{{ $seatingLayout->background_color ?? '#f3f4f6' }}',
            backgroundUrl: {{ Js::from($seatingLayout->background_image_url) }},
            backgroundVisible: true,
            backgroundScale: {{ $seatingLayout->background_scale ?? 1 }},
            backgroundX: {{ $seatingLayout->background_x ?? 0 }},
            backgroundY: {{ $seatingLayout->background_y ?? 0 }},
            backgroundOpacity: {{ $seatingLayout->background_opacity ?? 0.3 }},
            showBackgroundControls: false,
            drawMode: 'select',
            polygonPoints: [],
            tempPolygon: null,
            lineStart: null,
            tempLine: null,
            circleStart: null,
            tempCircle: null,
            showExportModal: false,
            showColorModal: false,
            showShapeConfigModal: false,
            showContextMenu: false,
            editColorHex: '#3B82F6',
            editSeatColor: '#22C55E',
            shapeConfigType: null,
            shapeConfigData: null,
            shapeConfigText: '',
            shapeConfigFontSize: 16,
            shapeConfigFontFamily: 'Arial',
            shapeConfigFontWeight: 'normal',
            shapeConfigStrokeWidth: 2,
            shapeConfigTension: 0,
            shapeConfigColor: '#000000',
            shapeConfigOpacity: 1,
            shapeConfigLabel: '',
            contextMenuX: 0,
            contextMenuY: 0,
            contextMenuSectionId: null,
            contextMenuSectionType: null,
            selectedSeats: [],
            selectedRows: [],
            assignToSectionId: '',
            assignToRowLabel: '',
            isBoxSelecting: false,
            boxSelectStart: null,
            boxSelectRect: null,
            sectionWidth: 200,
            sectionHeight: 150,
            sectionRotation: 0,
            sectionScale: 1,
            sectionCurve: 0,
            sectionCornerRadius: 0,
            sectionLabel: '',
            sectionFontSize: 14,
            addSeatsMode: false,
            savedViewState: null,
            rowSeatSize: 15,
            rowSeatSpacing: 20,
            rowSpacing: 20,
            tableSeats: 5,
            tableSeatsRect: 6,
            tempDrawRect: null,
            drawRectStart: null,
            tempRowLine: null,
            rowDrawStart: null,
            tempRowSeats: [],
            tempMultiRowRect: null,
            multiRowStart: null,
            tempMultiRowSeats: [],
            selectedDrawnRow: null,
            drawnRowSeats: 10,
            drawnRowCurve: 0,
            drawnRowSpacing: 20,
            rowNumberingMode: 'alpha',
            rowStartNumber: 1,
            rowNumberingDirection: 'ltr',
            seatNumberingType: 'numeric',
            currentDrawingShape: null,
            selectedRowForDrag: null,
            init() {
                console.log('Alpine initialized - 3 column layout');
            },
            getTotalSeats() {
                return this.sections.reduce((total, section) => {
                    if (section.rows) {
                        return total + section.rows.reduce((rowTotal, row) => rowTotal + (row.seats?.length || 0), 0);
                    }
                    return total;
                }, 0);
            },
            getSelectedSectionData() {
                return this.sections.find(s => s.id === this.selectedSection);
            }
         }"
         x-init="init()"
         x-on:keydown.window="handleKeyDown && handleKeyDown($event)"
         x-on:section-deleted.window="handleSectionDeleted && handleSectionDeleted($event.detail)"
         x-on:section-added.window="handleSectionAdded && handleSectionAdded($event.detail)"
         x-on:seat-added.window="handleSeatAdded && handleSeatAdded($event.detail)"
         x-on:layout-imported.window="handleLayoutImported && handleLayoutImported($event.detail)"
         x-on:layout-updated.window="handleLayoutUpdated && handleLayoutUpdated($event.detail)">

        {{-- Main Designer Layout with Left Sidebar, Canvas, Right Sidebar --}}
        <div class="flex gap-4">
            {{-- LEFT SIDEBAR - Tools Panel --}}
            <div class="flex-shrink-0 p-4 space-y-4 bg-white border border-gray-200 rounded-lg shadow-sm w-72">
                <h4 class="pb-2 text-sm font-bold tracking-wide text-gray-700 uppercase border-b border-gray-200">Instrumente</h4>

                {{-- Selection Tools --}}
                <div class="space-y-2">
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Selectie</div>
                    <div class="grid grid-cols-1 gap-1">
                        <button x-on:click="drawMode = 'select'" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'select' ? 'bg-blue-600 border-blue-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            Selectare
                        </button>
                    </div>
                </div>

                {{-- View Controls --}}
                <div class="pt-3 space-y-2 border-t border-gray-200">
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Vedere</div>
                    <div class="flex items-center gap-2">
                        <span class="flex-1 text-sm font-medium text-center" x-text="`${Math.round(zoom * 100)}%`"></span>
                    </div>
                </div>
            </div>

            {{-- CENTER - Canvas Area --}}
            <div class="flex-1 min-w-0">
                <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <h3 class="text-base font-semibold text-gray-900">Canvas</h3>
                            <span class="px-2 py-0.5 text-xs bg-gray-100 rounded text-gray-600" x-text="`${canvasWidth} x ${canvasHeight}px`"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span x-text="`${sections.length} sectiuni`"></span>
                            <span>|</span>
                            <span x-text="`${getTotalSeats()} locuri`"></span>
                        </div>
                    </div>

                    {{-- Canvas placeholder --}}
                    <div class="overflow-hidden bg-gray-100 border-2 border-gray-300 rounded-lg">
                        <div id="konva-container" wire:ignore style="width: 100%; height: 600px; background: #f3f4f6;"></div>
                    </div>
                </div>
            </div>

            {{-- RIGHT SIDEBAR - Properties Panel --}}
            <div class="flex-shrink-0 p-4 space-y-4 bg-white border border-gray-200 rounded-lg shadow-sm w-80" x-show="selectedSection || addSeatsMode" x-transition>
                <h4 class="pb-2 text-sm font-bold tracking-wide text-gray-700 uppercase border-b border-gray-200">Proprietati</h4>
                <div class="text-sm text-gray-600">
                    <p>Sectiune selectata: <span x-text="selectedSection || 'Niciuna'"></span></p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
