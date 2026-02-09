<x-filament-panels::page>
    <div class="seating-designer-root space-y-6"
         wire:ignore.self
         x-cloak
         x-data="{
            // Canvas state
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

            // Drawing and mode state
            drawMode: 'select',
            polygonPoints: [],
            tempPolygon: null,
            lineStart: null,
            tempLine: null,
            circleStart: null,
            tempCircle: null,

            // Modal states
            showExportModal: false,
            showColorModal: false,
            showShapeConfigModal: false,
            showContextMenu: false,

            // Color editing
            editColorHex: '#3B82F6',
            editSeatColor: '#22C55E',

            // Shape config
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

            // Context menu
            contextMenuX: 0,
            contextMenuY: 0,
            contextMenuSectionId: null,
            contextMenuSectionType: null,

            // Selection state
            selectedSeats: [],
            selectedRows: [],
            assignToSectionId: '',
            assignToRowLabel: '',
            isBoxSelecting: false,
            boxSelectStart: null,
            boxSelectRect: null,

            // Section editing
            sectionWidth: 200,
            sectionHeight: 150,
            sectionRotation: 0,
            sectionScale: 1,
            sectionCurve: 0,
            sectionCornerRadius: 0,
            sectionLabel: '',
            sectionFontSize: 14,

            // Add Seats Mode
            addSeatsMode: false,
            savedViewState: null,

            // Row drawing settings
            rowSeatSize: 15,
            rowSeatSpacing: 20,
            rowSpacing: 20,

            // Table settings
            tableSeats: 5,
            tableSeatsRect: 6,

            // Rectangle drawing
            tempDrawRect: null,
            drawRectStart: null,

            // Row drawing state
            tempRowLine: null,
            rowDrawStart: null,
            tempRowSeats: [],

            // Multi-row drawing
            tempMultiRowRect: null,
            multiRowStart: null,
            tempMultiRowSeats: [],

            // Row properties
            selectedDrawnRow: null,
            drawnRowSeats: 10,
            drawnRowCurve: 0,
            drawnRowSpacing: 20,
            rowNumberingMode: 'alpha',
            rowStartNumber: 1,
            rowNumberingDirection: 'ltr',
            seatNumberingType: 'numeric',

            // Additional state
            currentDrawingShape: null,
            selectedRowForDrag: null,

            // Placeholder init - methods loaded async from scripts
            init() {
                const self = this;
                const waitForMethods = setInterval(() => {
                    if (window.konvaDesignerMethods && window.konvaDesignerMethods.realInit) {
                        clearInterval(waitForMethods);
                        Object.assign(self, window.konvaDesignerMethods);
                        if (typeof self.realInit === 'function') {
                            self.realInit();
                        }
                    }
                }, 50);
                setTimeout(() => clearInterval(waitForMethods), 5000);
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
            {{-- ═══════════════════════════════════════════════════════════════════════ --}}
            {{-- LEFT SIDEBAR - Tools Panel --}}
            {{-- ═══════════════════════════════════════════════════════════════════════ --}}
            <div class="flex-shrink-0 p-4 space-y-4 bg-white border border-gray-200 rounded-lg shadow-sm w-72">
                <h4 class="pb-2 text-sm font-bold tracking-wide text-gray-700 uppercase border-b border-gray-200">Instrumente</h4>

                {{-- Selection Tools (always visible - Selectare button needed in addSeats mode too) --}}
                <div class="space-y-2">
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Selecție</div>
                    <div class="grid grid-cols-1 gap-1">
                        <button x-on:click="setDrawMode('select')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'select' ? 'bg-blue-600 border-blue-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg viewBox="0 0 32 32" class="w-5 h-5"><path d="M31.371 17.433 10.308 9.008c-.775-.31-1.629.477-1.3 1.3l8.426 21.064c.346.866 1.633.797 1.89-.098l2.654-9.295 9.296-2.656c.895-.255.96-1.544.097-1.89z" fill="currentColor"></path></svg>
                            Selectare
                        </button>
                        <button x-on:click="setDrawMode('selectseats')" type="button" x-show="!addSeatsMode"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'selectseats' ? 'bg-pink-500 border-pink-500 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <x-svg-icon name="konvaseats" class="w-5 h-5" />
                            Selectare Locuri
                        </button>
                    </div>
                </div>

                {{-- Section Tools (hidden when in addSeats mode) --}}
                <div class="space-y-2" x-show="!addSeatsMode" x-transition>
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Secțiuni</div>
                    <div class="grid grid-cols-1 gap-1">
                        <button x-on:click="setDrawMode('drawRect')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawRect' ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"></path>
                            </svg>
                            Dreptunghi
                        </button>
                        <button x-on:click="setDrawMode('polygon')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'polygon' ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <x-svg-icon name="konvapolygon" class="w-5 h-5" />
                            Poligon
                        </button>
                    </div>
                </div>

                {{-- Seats Tools (shown when in addSeats mode or when section selected) --}}
                <div x-show="addSeatsMode" x-transition class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Adaugă Locuri</div>
                        <button x-on:click="exitAddSeatsMode()" x-show="addSeatsMode" type="button" class="text-xs text-gray-400 hover:text-gray-600">✕</button>
                    </div>
                    <div class="grid grid-cols-1 gap-1">
                        {{-- Single Row --}}
                        <button x-on:click="setDrawMode('drawSingleRow')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawSingleRow' ? 'bg-purple-600 border-purple-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="4" cy="12" r="2.5"/><circle cx="9" cy="12" r="2.5"/><circle cx="14" cy="12" r="2.5"/><circle cx="19" cy="12" r="2.5"/>
                            </svg>
                            Un singur rând
                        </button>
                        {{-- Multiple Rows --}}
                        <button x-on:click="setDrawMode('drawMultiRows')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawMultiRows' ? 'bg-purple-600 border-purple-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <circle cx="5" cy="6" r="2"/><circle cx="10" cy="6" r="2"/><circle cx="15" cy="6" r="2"/><circle cx="20" cy="6" r="2"/>
                                <circle cx="5" cy="12" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="20" cy="12" r="2"/>
                                <circle cx="5" cy="18" r="2"/><circle cx="10" cy="18" r="2"/><circle cx="15" cy="18" r="2"/><circle cx="20" cy="18" r="2"/>
                            </svg>
                            Multiple rânduri
                        </button>
                        {{-- Round Table --}}
                        <button x-on:click="setDrawMode('drawRoundTable')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawRoundTable' ? 'bg-amber-600 border-amber-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="6" stroke-width="2"/>
                                <circle cx="12" cy="4" r="1.5" fill="currentColor"/><circle cx="18" cy="8" r="1.5" fill="currentColor"/>
                                <circle cx="18" cy="16" r="1.5" fill="currentColor"/><circle cx="12" cy="20" r="1.5" fill="currentColor"/>
                                <circle cx="6" cy="16" r="1.5" fill="currentColor"/><circle cx="6" cy="8" r="1.5" fill="currentColor"/>
                            </svg>
                            Masă rotundă
                        </button>
                        {{-- Rectangular Table --}}
                        <button x-on:click="setDrawMode('drawRectTable')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawRectTable' ? 'bg-amber-600 border-amber-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="6" y="8" width="12" height="8" stroke-width="2" rx="1"/>
                                <circle cx="8" cy="5" r="1.5" fill="currentColor"/><circle cx="12" cy="5" r="1.5" fill="currentColor"/><circle cx="16" cy="5" r="1.5" fill="currentColor"/>
                                <circle cx="8" cy="19" r="1.5" fill="currentColor"/><circle cx="12" cy="19" r="1.5" fill="currentColor"/><circle cx="16" cy="19" r="1.5" fill="currentColor"/>
                            </svg>
                            Masă dreptunghiulară
                        </button>
                    </div>

                    {{-- Row/Seat Settings --}}
                    <div x-show="['drawSingleRow', 'drawMultiRows'].includes(drawMode)" x-transition class="p-3 mt-2 space-y-3 border border-purple-200 rounded-lg bg-purple-50">
                        <div class="text-xs font-semibold text-purple-700">Setări Rând</div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-purple-600">Dimensiune loc</label>
                                <input type="number" x-model="rowSeatSize" min="8" max="40" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs text-purple-600">Spațiu locuri</label>
                                <input type="number" x-model="rowSeatSpacing" min="0" max="50" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>
                        <div x-show="drawMode === 'drawMultiRows'">
                            <label class="block text-xs text-purple-600">Spațiu rânduri</label>
                            <input type="number" x-model="rowSpacing" min="10" max="100" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                        </div>
                    </div>

                    {{-- Table Settings --}}
                    <div x-show="['drawRoundTable', 'drawRectTable'].includes(drawMode)" x-transition class="p-3 mt-2 space-y-3 border border-amber-200 rounded-lg bg-amber-50">
                        <div class="text-xs font-semibold text-amber-700">Setări Masă</div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs text-amber-600">Nr. locuri</label>
                                <input type="number" x-model="tableSeats" min="3" max="12" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs text-amber-600">Dim. loc</label>
                                <input type="number" x-model="rowSeatSize" min="8" max="40" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>
                        <p class="text-xs text-amber-600">Click pe hartă pentru a plasa masa</p>
                    </div>
                </div>

                {{-- Other Drawing Tools (hidden when in addSeats mode) --}}
                <div class="space-y-2" x-show="!addSeatsMode" x-transition>
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Alte Instrumente</div>
                    <div class="grid grid-cols-2 gap-1">
                        <button x-on:click="setDrawMode('text')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'text' ? 'bg-gray-700 border-gray-700 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M8 6v14m4-14v14"></path>
                            </svg>
                            Text
                        </button>
                        <button x-on:click="setDrawMode('line')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'line' ? 'bg-gray-700 border-gray-700 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 20L20 4"></path>
                            </svg>
                            Linie
                        </button>
                    </div>
                </div>

                {{-- Drawing Controls --}}
                <div x-show="['polygon', 'drawRect', 'drawSingleRow', 'drawMultiRows'].includes(drawMode)" x-transition class="pt-3 space-y-2 border-t border-gray-200">
                    <button x-on:click="finishDrawing" type="button" x-show="polygonPoints.length > 0 || tempDrawRect"
                        class="flex items-center justify-center w-full gap-2 px-3 py-2 text-sm font-medium text-white transition-all bg-green-600 rounded-lg hover:bg-green-700">
                        <x-svg-icon name="konvafinish" class="w-5 h-5" />
                        Finalizează
                    </button>
                    <button x-on:click="cancelDrawing" type="button"
                        class="flex items-center justify-center w-full gap-2 px-3 py-2 text-sm font-medium text-gray-700 transition-all bg-gray-200 rounded-lg hover:bg-gray-300">
                        <x-svg-icon name="konvacancel" class="w-5 h-5" />
                        Anulează
                    </button>
                </div>

                {{-- View Controls --}}
                <div class="pt-3 space-y-2 border-t border-gray-200">
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Vedere</div>
                    <div class="flex items-center gap-2">
                        <button x-on:click="zoomOut" type="button" class="p-2 text-sm bg-gray-100 rounded-md hover:bg-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                            </svg>
                        </button>
                        <span class="flex-1 text-sm font-medium text-center" x-text="`${Math.round(zoom * 100)}%`"></span>
                        <button x-on:click="zoomIn" type="button" class="p-2 text-sm bg-gray-100 rounded-md hover:bg-gray-200">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-2 gap-1">
                        <button x-on:click="resetView" type="button" class="px-3 py-1 text-xs bg-gray-100 rounded-md hover:bg-gray-200">Reset</button>
                        <button x-on:click="zoomToFit" type="button" class="px-3 py-1 text-xs bg-gray-100 rounded-md hover:bg-gray-200">Fit</button>
                    </div>
                    <div class="flex gap-1">
                        <button x-on:click="toggleGrid" type="button" class="flex items-center flex-1 gap-1 px-2 py-1 text-xs rounded-md" :class="showGrid ? 'bg-blue-600 text-white' : 'bg-gray-100'">
                            <x-svg-icon name="konvagrid" class="w-3 h-3" /> Grid
                        </button>
                        <button x-on:click="toggleSnapToGrid" type="button" class="flex items-center flex-1 gap-1 px-2 py-1 text-xs rounded-md" :class="snapToGrid ? 'bg-blue-600 text-white' : 'bg-gray-100'">
                            Snap
                        </button>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="pt-3 space-y-2 border-t border-gray-200">
                    <button x-on:click="showBackgroundControls = !showBackgroundControls" type="button"
                        class="flex items-center w-full gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                        :class="showBackgroundControls ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        Fundal
                    </button>
                    <button x-on:click="showExportModal = true" type="button"
                        class="flex items-center w-full gap-2 px-3 py-2 text-sm font-medium transition-all bg-gray-50 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-100">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Export
                    </button>
                    <button x-on:click="deleteSelected" type="button" x-show="selectedSection"
                        class="flex items-center w-full gap-2 px-3 py-2 text-sm font-medium text-white transition-all bg-red-600 rounded-lg hover:bg-red-700">
                        <x-svg-icon name="konvadelete" class="w-4 h-4" />
                        Șterge Secțiunea
                    </button>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════════════ --}}
            {{-- CENTER - Canvas Area --}}
            {{-- ═══════════════════════════════════════════════════════════════════════ --}}
            <div class="flex-1 min-w-0">
                <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                    {{-- Top bar with title and quick actions --}}
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <h3 class="text-base font-semibold text-gray-900">Canvas</h3>
                            <span class="px-2 py-0.5 text-xs bg-gray-100 rounded text-gray-600" x-text="`${canvasWidth}×${canvasHeight}px`"></span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-500">
                            <span x-text="`${sections.length} secțiuni`"></span>
                            <span>•</span>
                            <span x-text="`${getTotalSeats()} locuri`"></span>
                        </div>
                    </div>

                    {{-- Seats selection toolbar --}}
                    <div x-show="selectedSeats.length > 0" x-transition class="flex items-center gap-4 p-3 mb-3 border border-orange-200 rounded-lg bg-orange-50">
                        <span class="text-sm font-medium text-orange-800" x-text="`${selectedSeats.length} locuri selectate`"></span>
                        <div class="flex flex-wrap items-center gap-2">
                            <select x-model="assignToSectionId" class="text-sm text-gray-900 bg-white border-gray-300 rounded-md">
                                <option value="">Alege secțiunea...</option>
                                @foreach($sections as $section)
                                    @if($section['section_type'] === 'standard')
                                        <option value="{{ $section['id'] }}">{{ $section['name'] }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <input type="text" x-model="assignToRowLabel" placeholder="Rând (ex: A, 1)" class="w-24 text-sm text-gray-900 bg-white border-gray-300 rounded-md">
                            <button x-on:click="assignSelectedSeats" type="button" class="px-3 py-1 text-sm text-white bg-orange-600 rounded-md hover:bg-orange-700">Atribuie</button>
                            <button x-on:click="deleteSelectedSeats" type="button" class="px-3 py-1 text-sm text-white bg-red-600 rounded-md hover:bg-red-700">Șterge</button>
                            <button x-on:click="clearSelection" type="button" class="px-3 py-1 text-sm text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Anulează</button>
                        </div>
                    </div>

                    {{-- Rows selection toolbar --}}
                    <div x-show="selectedRows.length > 0" x-transition class="flex items-center gap-4 p-3 mb-3 border border-blue-200 rounded-lg bg-blue-50">
                        <span class="text-sm font-medium text-blue-800" x-text="`${selectedRows.length} rânduri selectate`"></span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-blue-600">Aliniere:</span>
                            <button x-on:click="alignSelectedRows('left')" type="button" class="p-1 text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14"></path></svg>
                            </button>
                            <button x-on:click="alignSelectedRows('center')" type="button" class="p-1 text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14"></path></svg>
                            </button>
                            <button x-on:click="alignSelectedRows('right')" type="button" class="p-1 text-blue-700 bg-blue-100 rounded hover:bg-blue-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14"></path></svg>
                            </button>
                            <button x-on:click="clearRowSelection" type="button" class="px-2 py-1 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Anulează</button>
                        </div>
                    </div>

                    {{-- Background controls (collapsible) --}}
                    <div x-show="showBackgroundControls" x-transition class="p-3 mb-3 border border-indigo-200 rounded-lg bg-indigo-50">
                        <div class="flex flex-wrap items-center gap-4">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-medium text-indigo-800">Culoare:</label>
                                <input type="color" x-model="backgroundColor" x-on:input="updateBackgroundColor()" class="w-8 h-8 border border-gray-300 rounded cursor-pointer">
                                <button x-on:click="saveBackgroundColor" type="button" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Salvează</button>
                            </div>
                            <div x-show="backgroundUrl" class="flex flex-wrap items-center gap-3">
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="checkbox" x-model="backgroundVisible" x-on:change="toggleBackgroundVisibility()" class="w-4 h-4 text-indigo-600 border-gray-300 rounded">
                                    <span class="text-xs text-indigo-800">Imagine</span>
                                </label>
                                <div class="flex items-center gap-1">
                                    <label class="text-xs text-indigo-700">Scală:</label>
                                    <input type="range" x-model="backgroundScale" min="0.1" max="3" step="0.01" x-on:input="updateBackgroundScale()" class="w-16" :disabled="!backgroundVisible">
                                </div>
                                <div class="flex items-center gap-1">
                                    <label class="text-xs text-indigo-700">Opacitate:</label>
                                    <input type="range" x-model="backgroundOpacity" min="0" max="1" step="0.01" x-on:input="updateBackgroundOpacity()" class="w-16" :disabled="!backgroundVisible">
                                </div>
                                <button x-on:click="saveBackgroundSettings" type="button" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Salvează</button>
                            </div>
                        </div>
                    </div>

                    {{-- Canvas --}}
                    <div class="overflow-hidden bg-gray-100 border-2 border-gray-300 rounded-lg">
                        <div id="konva-container" wire:ignore></div>
                    </div>

                    {{-- Keyboard shortcuts --}}
                    <div class="flex flex-wrap items-center justify-center gap-3 mt-2 text-xs text-gray-500">
                        <span><kbd class="px-1 py-0.5 bg-gray-100 border rounded">Del</kbd> Șterge</span>
                        <span><kbd class="px-1 py-0.5 bg-gray-100 border rounded">Esc</kbd> Anulează</span>
                        <span><kbd class="px-1 py-0.5 bg-gray-100 border rounded">Scroll</kbd> Zoom</span>
                        <span><kbd class="px-1 py-0.5 bg-gray-100 border rounded">Drag</kbd> Pan</span>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════════════════════════════════════════════════════ --}}
            {{-- RIGHT SIDEBAR - Properties Panel --}}
            {{-- ═══════════════════════════════════════════════════════════════════════ --}}
            <div class="flex-shrink-0 p-4 space-y-4 bg-white border border-gray-200 rounded-lg shadow-sm w-80" x-show="selectedSection || selectedDrawnRow || addSeatsMode" x-transition>
                {{-- Add Seats Mode Panel --}}
                <template x-if="addSeatsMode && selectedSection && !selectedDrawnRow">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-purple-200">
                            <h4 class="text-sm font-bold tracking-wide text-purple-700 uppercase">Adaugă Locuri</h4>
                            <button x-on:click="exitAddSeatsMode()" class="text-purple-400 hover:text-purple-600">✕</button>
                        </div>

                        {{-- Selected Section Info --}}
                        <div class="p-3 rounded-lg bg-purple-50">
                            <div class="text-xs font-semibold text-purple-600">Secțiune selectată</div>
                            <div class="text-sm font-medium text-purple-900" x-text="getSelectedSectionData()?.name || 'Necunoscut'"></div>
                        </div>

                        {{-- Row Settings --}}
                        <div class="p-3 space-y-3 border border-purple-200 rounded-lg bg-purple-50">
                            <div class="text-xs font-semibold text-purple-700 uppercase">Setări Rând</div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-purple-600">Dimensiune loc</label>
                                    <input type="number" x-model="rowSeatSize" min="8" max="40" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                </div>
                                <div>
                                    <label class="block text-xs text-purple-600">Spațiu locuri</label>
                                    <input type="number" x-model="rowSeatSpacing" min="0" max="50" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-purple-600">Spațiu între rânduri</label>
                                <input type="number" x-model="rowSpacing" min="10" max="100" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>

                        {{-- Table Settings --}}
                        <div class="p-3 space-y-3 border border-amber-200 rounded-lg bg-amber-50" x-show="['drawRoundTable', 'drawRectTable'].includes(drawMode)">
                            <div class="text-xs font-semibold text-amber-700 uppercase">Setări Masă</div>
                            <div>
                                <label class="block text-xs text-amber-600">Nr. locuri la masă</label>
                                <input type="number" x-model="tableSeats" min="3" max="12" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>

                        {{-- Numbering Settings --}}
                        <div class="p-3 space-y-3 rounded-lg bg-blue-50">
                            <div class="text-xs font-semibold text-blue-700 uppercase">Numerotare</div>
                            <div>
                                <label class="block text-xs text-blue-600">Mod numerotare rând</label>
                                <select x-model="rowNumberingMode" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                    <option value="numeric">Numere (1, 2, 3...)</option>
                                    <option value="alpha">Litere (A, B, C...)</option>
                                    <option value="roman">Romane (I, II, III...)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-blue-600">Direcție numerotare</label>
                                <select x-model="rowNumberingDirection" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                    <option value="ltr">Stânga → Dreapta</option>
                                    <option value="rtl">Dreapta → Stânga</option>
                                </select>
                            </div>
                        </div>

                        {{-- Instructions --}}
                        <div class="p-3 text-xs text-gray-600 rounded-lg bg-gray-50">
                            <p class="font-medium">Instrucțiuni:</p>
                            <ul class="mt-1 ml-4 list-disc">
                                <li>Selectează tipul de locuri din stânga</li>
                                <li>Click pe canvas pentru a plasa</li>
                                <li>Trage pentru a desena rânduri</li>
                            </ul>
                        </div>
                    </div>
                </template>

                {{-- Section Properties (hidden when in addSeats mode) --}}
                <template x-if="selectedSection && !selectedDrawnRow && !addSeatsMode">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-gray-200">
                            <h4 class="text-sm font-bold tracking-wide text-gray-700 uppercase">Proprietăți Secțiune</h4>
                            <button x-on:click="selectedSection = null; transformer.nodes([]); layer.batchDraw()" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>

                        {{-- Section Name --}}
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Nume Secțiune</label>
                            <div class="text-sm font-semibold text-gray-900" x-text="getSelectedSectionData()?.name || 'Fără nume'"></div>
                        </div>

                        {{-- Add Seats Button --}}
                        <button x-on:click="enterAddSeatsMode()" type="button"
                            class="flex items-center justify-center w-full gap-2 px-4 py-3 text-sm font-semibold text-white transition-all rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 shadow-md"
                            x-show="getSelectedSectionData()?.section_type === 'standard'">
                            <x-svg-icon name="konvaseats" class="w-5 h-5" />
                            Adaugă Locuri
                        </button>

                        {{-- Transform Section --}}
                        <div class="p-3 space-y-3 rounded-lg bg-gray-50">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Transformare</div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500">Lățime</label>
                                    <input type="number" x-model="sectionWidth" x-on:input="updateSectionDimensions()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Înălțime</label>
                                    <input type="number" x-model="sectionHeight" x-on:input="updateSectionDimensions()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Rotație (°)</label>
                                <input type="range" x-model="sectionRotation" min="0" max="360" x-on:input="updateSectionRotation()" class="w-full">
                                <div class="text-xs text-center text-gray-500" x-text="sectionRotation + '°'"></div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Colțuri rotunjite</label>
                                <input type="range" x-model="sectionCornerRadius" min="0" max="50" x-on:input="updateSectionCornerRadius()" class="w-full">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Scalare</label>
                                <input type="range" x-model="sectionScale" min="0.5" max="2" step="0.1" x-on:input="updateSectionScale()" class="w-full">
                                <div class="text-xs text-center text-gray-500" x-text="(sectionScale * 100).toFixed(0) + '%'"></div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Curbură</label>
                                <input type="range" x-model="sectionCurve" min="-100" max="100" x-on:input="updateSectionCurve()" class="w-full">
                                <div class="text-xs text-center text-gray-500" x-text="sectionCurve"></div>
                            </div>
                        </div>

                        {{-- Label Section --}}
                        <div class="p-3 space-y-3 rounded-lg bg-gray-50">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Etichetă</div>
                            <div>
                                <label class="block text-xs text-gray-500">Nume afișat</label>
                                <input type="text" x-model="sectionLabel" x-on:input="updateSectionLabel()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded" placeholder="Nume secțiune">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Dimensiune font</label>
                                <input type="number" x-model="sectionFontSize" min="8" max="72" x-on:input="updateSectionLabel()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>

                        {{-- Colors --}}
                        <div class="p-3 space-y-3 rounded-lg bg-gray-50">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Culori</div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500">Fundal</label>
                                    <input type="color" x-model="editColorHex" x-on:input="previewSectionColor()" class="w-full h-8 border rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Locuri</label>
                                    <input type="color" x-model="editSeatColor" x-on:input="previewSeatColor()" class="w-full h-8 border rounded cursor-pointer">
                                </div>
                            </div>
                            <button x-on:click="saveSectionColors()" type="button" class="w-full px-3 py-1 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">Salvează Culorile</button>
                        </div>

                        {{-- Section Info --}}
                        <div class="p-3 space-y-1 text-xs rounded-lg bg-gray-50">
                            <div class="flex justify-between"><span class="text-gray-500">Rânduri:</span> <span class="font-medium" x-text="getSelectedSectionData()?.rows?.length || 0"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Locuri:</span> <span class="font-medium" x-text="getSelectedSectionSeatsCount()"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Poziție:</span> <span class="font-medium" x-text="`${Math.round(getSelectedSectionData()?.x_position || 0)}, ${Math.round(getSelectedSectionData()?.y_position || 0)}`"></span></div>
                        </div>
                    </div>
                </template>

                {{-- Row Properties (when a row is selected after drawing) --}}
                <template x-if="selectedDrawnRow">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-gray-200">
                            <h4 class="text-sm font-bold tracking-wide text-gray-700 uppercase">Proprietăți Rând</h4>
                            <button x-on:click="selectedDrawnRow = null" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>

                        {{-- Row Settings --}}
                        <div class="p-3 space-y-3 rounded-lg bg-purple-50">
                            <div class="text-xs font-semibold text-purple-700 uppercase">Rând</div>
                            <div>
                                <label class="block text-xs text-purple-600">Număr locuri</label>
                                <input type="number" x-model="drawnRowSeats" min="1" max="100" x-on:input="updateDrawnRowSeats()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs text-purple-600">Curbură</label>
                                <input type="range" x-model="drawnRowCurve" min="-50" max="50" x-on:input="updateDrawnRowCurve()" class="w-full">
                                <div class="text-xs text-center text-purple-500" x-text="drawnRowCurve"></div>
                            </div>
                            <div>
                                <label class="block text-xs text-purple-600">Spațiu între locuri</label>
                                <input type="number" x-model="drawnRowSpacing" min="0" max="50" x-on:input="updateDrawnRowSpacing()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>

                        {{-- Numbering --}}
                        <div class="p-3 space-y-3 rounded-lg bg-blue-50">
                            <div class="text-xs font-semibold text-blue-700 uppercase">Numerotare</div>
                            <div>
                                <label class="block text-xs text-blue-600">Mod numerotare rând</label>
                                <select x-model="rowNumberingMode" x-on:change="updateRowNumbering()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                    <option value="numeric">Numere (1, 2, 3...)</option>
                                    <option value="alpha">Litere (A, B, C...)</option>
                                    <option value="roman">Romane (I, II, III...)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-blue-600">Începe de la</label>
                                <input type="number" x-model="rowStartNumber" min="1" x-on:input="updateRowNumbering()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs text-blue-600">Direcție</label>
                                <select x-model="rowNumberingDirection" x-on:change="updateRowNumbering()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                    <option value="ltr">Stânga → Dreapta</option>
                                    <option value="rtl">Dreapta → Stânga</option>
                                </select>
                            </div>
                        </div>

                        {{-- Seat Naming --}}
                        <div class="p-3 space-y-3 rounded-lg bg-green-50">
                            <div class="text-xs font-semibold text-green-700 uppercase">Nume Loc</div>
                            <div>
                                <label class="block text-xs text-green-600">Tip numerotare</label>
                                <select x-model="seatNumberingType" x-on:change="updateSeatNumbering()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                    <option value="numeric">Numere (1, 2, 3...)</option>
                                    <option value="alpha">Litere (A, B, C...)</option>
                                </select>
                            </div>
                        </div>

                        {{-- Save Row --}}
                        <button x-on:click="saveDrawnRow()" type="button"
                            class="w-full px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">
                            Salvează Rândul
                        </button>
                    </div>
                </template>

                {{-- Empty state when nothing selected --}}
                <div x-show="!selectedSection && !selectedDrawnRow" class="py-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">Selectează o secțiune pentru a vedea proprietățile</p>
                </div>
            </div>
        </div>

            {{-- Statistics --}}
            <div class="grid grid-cols-4 gap-4 mt-4 text-sm">
                <div class="p-3 text-center rounded-lg bg-gray-50">
                    <div class="text-gray-600">Sections</div>
                    <div class="text-2xl font-bold text-gray-900" x-text="sections.length"></div>
                </div>
                <div class="p-3 text-center rounded-lg bg-blue-50">
                    <div class="text-blue-600">Rows</div>
                    <div class="text-2xl font-bold text-blue-900" x-text="getTotalRows()"></div>
                </div>
                <div class="p-3 text-center rounded-lg bg-green-50">
                    <div class="text-green-600">Seats</div>
                    <div class="text-2xl font-bold text-green-900" x-text="getTotalSeats()"></div>
                </div>
                <div class="p-3 text-center rounded-lg bg-purple-50">
                    <div class="text-purple-600">Canvas</div>
                    <div class="text-sm font-bold text-purple-900" x-text="`${canvasWidth}x${canvasHeight}`"></div>
                </div>
            </div>

            {{-- Keyboard Shortcuts --}}
            <div class="p-3 mt-2 border rounded-lg bg-slate-50 border-slate-200">
                <div class="flex flex-wrap items-center justify-center gap-4 text-xs text-slate-600">
                    <span><kbd class="px-1 py-0.5 bg-white border rounded shadow-sm">Del</kbd> Delete selected</span>
                    <span><kbd class="px-1 py-0.5 bg-white border rounded shadow-sm">Esc</kbd> Cancel / Deselect</span>
                    <span><kbd class="px-1 py-0.5 bg-white border rounded shadow-sm">Shift</kbd>+Click Multi-select</span>
                    <span><kbd class="px-1 py-0.5 bg-white border rounded shadow-sm">←↑→↓</kbd> Move section (1px)</span>
                    <span><kbd class="px-1 py-0.5 bg-white border rounded shadow-sm">Shift</kbd>+Arrow Move section (10px)</span>
                    <span><kbd class="px-1 py-0.5 bg-white border rounded shadow-sm">Scroll</kbd> Zoom</span>
                    <span><kbd class="px-1 py-0.5 bg-white border rounded shadow-sm">Drag</kbd> Pan canvas</span>
                </div>
            </div>
        </div>

        {{-- Export Modal --}}
        <div x-cloak x-show="showExportModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" x-on:click.self="showExportModal = false" x-on:keydown.escape.window="showExportModal = false">
            <div x-show="showExportModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Export Layout</h3>
                    <button x-on:click="showExportModal = false" type="button" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <button x-on:click="exportSVG(); showExportModal = false" type="button" class="flex flex-col items-center gap-3 p-6 transition border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 group">
                        <svg class="w-12 h-12 text-gray-400 transition group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 group-hover:text-blue-700">Export SVG</span>
                        <span class="text-xs text-gray-500">Vector image format</span>
                    </button>
                    <button x-on:click="exportJSON(); showExportModal = false" type="button" class="flex flex-col items-center gap-3 p-6 transition border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 group">
                        <svg class="w-12 h-12 text-gray-400 transition group-hover:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 group-hover:text-green-700">Export JSON</span>
                        <span class="text-xs text-gray-500">Backup data format</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Sections List (Alpine-driven) --}}
        <div x-show="sections.length > 0" class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
            <h3 class="mb-4 text-lg font-semibold text-gray-900">Sections</h3>
            <div class="space-y-2">
                <template x-for="section in sections" :key="section.id">
                    <div x-data="{ expanded: false }" class="border rounded-lg">
                        <div class="flex items-center justify-between p-3 cursor-pointer hover:bg-gray-50"
                             x-on:click="selectSection(section.id)">
                            <div class="flex items-center gap-3">
                                {{-- Expand button for standard sections with rows --}}
                                <template x-if="section.section_type === 'standard' && (section.rows || []).length > 0">
                                    <button x-on:click.stop="expanded = !expanded" class="p-1 text-gray-500 hover:text-gray-700">
                                        <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </button>
                                </template>
                                {{-- Icon display for icon sections --}}
                                <template x-if="section.section_type === 'icon'">
                                    <div class="flex items-center justify-center w-6 h-6 rounded" :style="'background-color:' + (section.background_color || '#3B82F6')">
                                        <svg class="w-4 h-4" :fill="(section.metadata && section.metadata.icon_color) || '#FFFFFF'" viewBox="0 0 24 24">
                                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                        </svg>
                                    </div>
                                </template>
                                {{-- Color squares for non-icon sections --}}
                                <template x-if="section.section_type !== 'icon'">
                                    <div class="flex gap-1">
                                        <div class="w-4 h-4 border rounded" :style="'background-color:' + (section.color_hex || '#3B82F6')" title="Section color"></div>
                                        <div class="w-4 h-4 border rounded" :style="'background-color:' + (section.seat_color || '#22C55E')" title="Seat color"></div>
                                    </div>
                                </template>
                                <div>
                                    <div class="font-medium">
                                        <template x-if="section.section_type === 'icon'">
                                            <span class="text-xs text-blue-600 uppercase">Icon:</span>
                                        </template>
                                        <span x-text="section.name"></span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <template x-if="section.section_type === 'icon'">
                                            <span x-text="getIconLabel(section)"></span>
                                        </template>
                                        <template x-if="section.section_type !== 'icon'">
                                            <span x-text="getSectionStats(section)"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="text-xs text-gray-400">
                                    <span x-text="'(' + section.x_position + ', ' + section.y_position + ')'"></span>
                                    <template x-if="section.section_type !== 'icon'">
                                        <span x-text="' • ' + section.width + 'x' + section.height"></span>
                                    </template>
                                </div>
                                <template x-if="section.section_type !== 'icon'">
                                    <button x-on:click.stop="editSectionColors(section.id, section.color_hex || '#3B82F6', section.seat_color || '#22C55E')"
                                            class="px-2 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200">
                                        Edit Colors
                                    </button>
                                </template>
                                <template x-if="section.section_type === 'standard'">
                                    <button x-on:click.stop="selectRowsBySection(section.id)"
                                            class="px-2 py-1 text-xs text-blue-700 bg-blue-100 rounded hover:bg-blue-200"
                                            title="Select all rows in this section">
                                        Select Rows
                                    </button>
                                </template>
                                <template x-if="section.section_type === 'standard'">
                                    <button x-on:click.stop="recalculateRows(section.id)"
                                            class="px-2 py-1 text-xs text-orange-700 bg-orange-100 rounded hover:bg-orange-200"
                                            title="Re-group seats into rows based on Y position">
                                        Recalc Rows
                                    </button>
                                </template>
                            </div>
                        </div>
                        {{-- Expandable rows list --}}
                        <template x-if="section.section_type === 'standard' && (section.rows || []).length > 0">
                            <div x-show="expanded" x-transition class="px-3 pb-3 ml-8 border-t">
                                <div class="pt-2 space-y-1">
                                    <template x-for="row in (section.rows || [])" :key="row.id">
                                        <div class="flex items-center justify-between px-2 py-1 text-sm rounded hover:bg-gray-100"
                                             :class="selectedRows.find(r => r.rowId === row.id) ? 'bg-blue-100' : ''">
                                            <button x-on:click.stop="selectRow(section.id, row.id)"
                                                    class="flex items-center flex-1 gap-2 text-left">
                                                <span class="font-medium" x-text="'Row ' + row.label"></span>
                                                <span class="text-xs text-gray-500" x-text="(row.seats || []).length + ' seats'"></span>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        {{-- Color Edit Modal --}}
        <div x-cloak x-show="showColorModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="p-6 bg-white rounded-lg shadow-xl w-96" x-on:click.away="showColorModal = false">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Edit Section Colors</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Section Background Color</label>
                        <input type="color" x-model="editColorHex" class="w-full h-10 rounded cursor-pointer">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700">Seat Color (Available)</label>
                        <input type="color" x-model="editSeatColor" class="w-full h-10 rounded cursor-pointer">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button x-on:click="showColorModal = false" type="button" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                        <button x-on:click="saveSectionColors" type="button" class="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">Save</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Shape Config Modal (for drawn shapes: polygon, circle, text, line) --}}
        <div x-cloak x-show="showShapeConfigModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
            <div class="p-6 bg-white rounded-lg shadow-xl w-96" x-on:click.away="showShapeConfigModal = false">
                <h3 class="mb-4 text-lg font-semibold text-gray-900" x-text="'Add ' + (shapeConfigType || 'Shape')"></h3>
                <div class="space-y-4">
                    <div x-show="shapeConfigType === 'text'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Text Content</label>
                        <input type="text" x-model="shapeConfigText" class="w-full px-3 py-2 text-gray-900 bg-white border border-gray-300 rounded-md" placeholder="Enter text...">
                    </div>
                    <div x-show="shapeConfigType === 'text'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Font Size (px)</label>
                        <input type="number" x-model="shapeConfigFontSize" min="8" max="200" class="w-full px-3 py-2 text-gray-900 bg-white border border-gray-300 rounded-md">
                    </div>
                    <div x-show="shapeConfigType === 'text'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Font Family</label>
                        <select x-model="shapeConfigFontFamily" class="w-full px-3 py-2 text-gray-900 bg-white border border-gray-300 rounded-md [&>option]:text-gray-900 [&>option]:bg-white">
                            <option value="Arial">Arial</option>
                            <option value="Helvetica">Helvetica</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Verdana">Verdana</option>
                            <option value="Courier New">Courier New</option>
                        </select>
                    </div>
                    <div x-show="shapeConfigType === 'text'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Font Weight</label>
                        <select x-model="shapeConfigFontWeight" class="w-full px-3 py-2 text-gray-900 bg-white border border-gray-300 rounded-md [&>option]:text-gray-900 [&>option]:bg-white">
                            <option value="normal">Normal</option>
                            <option value="bold">Bold</option>
                            <option value="100">Thin (100)</option>
                            <option value="300">Light (300)</option>
                            <option value="500">Medium (500)</option>
                            <option value="600">Semi-Bold (600)</option>
                            <option value="900">Black (900)</option>
                        </select>
                    </div>
                    <div x-show="shapeConfigType === 'line'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Stroke Width</label>
                        <input type="number" x-model="shapeConfigStrokeWidth" min="1" max="20" class="w-full px-3 py-2 text-gray-900 bg-white border border-gray-300 rounded-md">
                    </div>
                    <div x-show="shapeConfigType === 'polygon'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Edge Smoothing</label>
                        <input type="range" x-model="shapeConfigTension" min="0" max="1" step="0.05" class="w-full">
                        <span class="text-xs text-gray-500" x-text="'Tension: ' + shapeConfigTension"></span>
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium text-gray-700" x-text="shapeConfigType === 'line' ? 'Line Color' : (shapeConfigType === 'text' ? 'Text Color' : 'Fill Color')"></label>
                        <input type="color" x-model="shapeConfigColor" class="w-full h-10 rounded cursor-pointer">
                    </div>
                    <div x-show="shapeConfigType !== 'text' && shapeConfigType !== 'line'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Opacity</label>
                        <input type="range" x-model="shapeConfigOpacity" min="0.1" max="1" step="0.05" class="w-full">
                        <span class="text-xs text-gray-500" x-text="shapeConfigOpacity"></span>
                    </div>
                    <div x-show="shapeConfigType !== 'text' && shapeConfigType !== 'line'">
                        <label class="block mb-1 text-sm font-medium text-gray-700">Label (optional)</label>
                        <input type="text" x-model="shapeConfigLabel" class="w-full px-3 py-2 text-gray-900 bg-white border border-gray-300 rounded-md" placeholder="e.g., Stage, Exit...">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button x-on:click="cancelShapeConfig()" type="button" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                        <button x-on:click="confirmShapeConfig()" type="button" class="px-4 py-2 text-sm text-white bg-green-600 rounded hover:bg-green-700">Add</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section Context Menu --}}
        <div x-show="showContextMenu"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             :style="`position: fixed; left: ${contextMenuX}px; top: ${contextMenuY}px; z-index: 100;`"
             x-on:click.away="showContextMenu = false"
             class="w-48 bg-white border border-gray-200 rounded-lg shadow-xl">
            <div class="py-1">
                <button x-on:click="openEditSectionModal()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-gray-200 hover:bg-gray-100 hover:text-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Section
                </button>
                <template x-if="contextMenuSectionType === 'standard'">
                    <div>
                        <button x-on:click="selectRowsBySectionFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-blue-700 hover:bg-blue-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            Select Rows
                        </button>
                        <button x-on:click="editColorsFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-gray-200 hover:bg-gray-100 hover:text-gray-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                            </svg>
                            Edit Colors
                        </button>
                        <button x-on:click="recalcRowsFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-orange-700 hover:bg-orange-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Recalc Rows
                        </button>
                    </div>
                </template>
                <div class="border-t border-gray-200"></div>
                <div class="px-3 py-1 text-xs font-medium tracking-wide text-gray-400 uppercase">Layer Order</div>
                <button x-on:click="changeLayerOrder('front')" class="flex items-center w-full gap-2 px-4 py-1.5 text-sm text-left text-gray-700 hover:bg-gray-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11l7-7 7 7M5 19l7-7 7 7"></path></svg>
                    Bring to Front
                </button>
                <button x-on:click="changeLayerOrder('up')" class="flex items-center w-full gap-2 px-4 py-1.5 text-sm text-left text-gray-700 hover:bg-gray-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path></svg>
                    Move Up
                </button>
                <button x-on:click="changeLayerOrder('down')" class="flex items-center w-full gap-2 px-4 py-1.5 text-sm text-left text-gray-700 hover:bg-gray-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    Move Down
                </button>
                <button x-on:click="changeLayerOrder('back')" class="flex items-center w-full gap-2 px-4 py-1.5 text-sm text-left text-gray-700 hover:bg-gray-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7M19 5l-7 7-7-7"></path></svg>
                    Send to Back
                </button>
                <div class="border-t border-gray-200"></div>
                <button x-on:click="deleteFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-red-600 hover:bg-red-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Section
                </button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
