<x-filament-panels::page>
    <div class="space-y-6"
         x-data="konvaDesigner()"
         x-init="init()"
         @@keydown.window="handleKeyDown($event)"
         @@section-deleted.window="handleSectionDeleted($event.detail)"
         @@section-added.window="handleSectionAdded($event.detail)"
         @@seat-added.window="handleSeatAdded($event.detail)"
         @@layout-imported.window="handleLayoutImported($event.detail)"
         @@layout-updated.window="handleLayoutUpdated($event.detail)">
        {{-- Canvas Container --}}
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Canvas Designer </h3>
                    <p class="text-sm text-gray-500">Layout: {{ $canvasWidth }}x{{ $canvasHeight }}px</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button @click="zoomOut" type="button" class="px-3 py-1 text-sm bg-gray-100 rounded-md hover:bg-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                        </svg>
                    </button>
                    <span class="px-2 text-sm font-medium" x-text="`${Math.round(zoom * 100)}%`"></span>
                    <button @click="zoomIn" type="button" class="px-3 py-1 text-sm bg-gray-100 rounded-md hover:bg-gray-200">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                    </button>
                    <button @click="resetView" type="button" class="px-3 py-1 text-sm bg-gray-100 rounded-md hover:bg-gray-200">Reset</button>
                    <button @click="zoomToFit" type="button" class="px-3 py-1 text-sm bg-gray-100 rounded-md hover:bg-gray-200" title="Fit all content in view">Fit</button>
                    <button @click="toggleGrid" type="button" class="flex items-center gap-2 px-3 py-1 text-sm" :class="showGrid ? 'bg-blue-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvagrid" class="w-5 h-5 text-purple-600" />
                        Grid
                    </button>
                    <button @click="toggleSnapToGrid" type="button" class="flex items-center gap-2 px-3 py-1 text-sm" :class="snapToGrid ? 'bg-indigo-500 text-white' : 'bg-gray-100'" title="Snap sections to grid when moving">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"></path>
                        </svg>
                        Snap
                    </button>

                    <div class="h-6 mx-1 border-l border-gray-300"></div>

                    <button @click="setDrawMode('select')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'select' ? 'bg-blue-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvaselect" class="w-5 h-5 text-purple-600" />
                        Select
                    </button>
                    <button @click="setDrawMode('multiselect')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'multiselect' ? 'bg-orange-500 text-white' : 'bg-gray-100'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                        </svg>
                        Multi-Select
                    </button>
                    <button @click="setDrawMode('polygon')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'polygon' ? 'bg-green-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvapolygon" class="w-5 h-5 text-purple-600" />
                        Polygon
                    </button>
                    <button @click="setDrawMode('circle')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'circle' ? 'bg-green-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvacircle" class="w-5 h-5 text-purple-600" />
                        Circle
                    </button>
                    <button @click="setDrawMode('seat')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'seat' ? 'bg-purple-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvaseats" class="w-5 h-5 text-purple-600" />
                        Add Seats
                    </button>
                    <div x-show="drawMode === 'seat'" x-transition class="flex items-center gap-2 px-2 py-1 ml-1 border rounded-md bg-purple-50 border-purple-200">
                        <label class="text-xs text-purple-700">Size:</label>
                        <input type="number" x-model="seatSize" min="4" max="30" step="1" class="w-12 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded">
                        <select x-model="seatShape" class="px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded">
                            <option value="circle">Circle</option>
                            <option value="rect">Square</option>
                        </select>
                    </div>
                    <button @click="finishDrawing" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-white bg-green-600 border rounded-md border-slate-200" x-show="['polygon', 'circle'].includes(drawMode) && polygonPoints.length > 0">
                        <x-svg-icon name="konvafinish" class="w-5 h-5 text-purple-600" />
                        Finish
                    </button>
                    <button @click="cancelDrawing" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-white bg-gray-600 border rounded-md border-slate-200" x-show="drawMode !== 'select' && drawMode !== 'multiselect'">
                        <x-svg-icon name="konvacancel" class="w-5 h-5 text-purple-600" />
                        Cancel
                    </button>

                    <div class="h-6 mx-1 border-l border-gray-300"></div>

                    <button @click="deleteSelected" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-red-700 bg-red-100 rounded-md hover:bg-red-200" x-show="selectedSection">
                        <x-svg-icon name="konvadelete" class="w-5 h-5 text-purple-600" />
                        Delete
                    </button>

                    <button @click="exportSVG" type="button" class="flex items-center gap-2 px-3 py-1 text-sm bg-gray-100 rounded-md hover:bg-gray-200" title="Export as SVG image">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        SVG
                    </button>
                    <button @click="exportJSON" type="button" class="flex items-center gap-2 px-3 py-1 text-sm bg-gray-100 rounded-md hover:bg-gray-200" title="Export as JSON backup">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        JSON
                    </button>
                </div>
            </div>

            {{-- Seats selection toolbar --}}
            <div x-show="selectedSeats.length > 0" x-transition class="flex items-center gap-4 p-3 mb-4 border rounded-lg bg-orange-50 border-orange-200">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-orange-800" x-text="`${selectedSeats.length} seats selected`"></span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select x-model="assignToSectionId" class="text-sm text-gray-900 bg-white border-gray-300 rounded-md">
                        <option value="">Select Section...</option>
                        @foreach($sections as $section)
                            @if($section['section_type'] === 'standard')
                                <option value="{{ $section['id'] }}">{{ $section['name'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    <input type="text" x-model="assignToRowLabel" placeholder="Row label (e.g., A, 1)" class="w-32 text-sm text-gray-900 bg-white border-gray-300 rounded-md placeholder-gray-400">
                    <button @click="assignSelectedSeats" type="button" class="px-3 py-1 text-sm text-white bg-orange-600 rounded-md hover:bg-orange-700" :disabled="!assignToSectionId || !assignToRowLabel">
                        Assign to Row
                    </button>
                    <button @click="deleteSelectedSeats" type="button" class="px-3 py-1 text-sm text-white bg-red-600 rounded-md hover:bg-red-700">
                        Delete Selected
                    </button>
                    <button @click="clearSelection" type="button" class="px-3 py-1 text-sm text-gray-800 bg-gray-200 rounded-md hover:bg-gray-300">
                        Clear Selection
                    </button>
                </div>
            </div>

            {{-- Rows selection toolbar --}}
            <div x-show="selectedRows.length > 0" x-transition class="flex items-center gap-4 p-3 mb-4 border rounded-lg bg-blue-50 border-blue-200">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-blue-800" x-text="`${selectedRows.length} rows selected`"></span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-blue-600">Align:</span>
                    <button @click="alignSelectedRows('left')" type="button" class="px-3 py-1 text-sm text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200" title="Align left">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h14"></path>
                        </svg>
                    </button>
                    <button @click="alignSelectedRows('center')" type="button" class="px-3 py-1 text-sm text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200" title="Align center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M5 18h14"></path>
                        </svg>
                    </button>
                    <button @click="alignSelectedRows('right')" type="button" class="px-3 py-1 text-sm text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200" title="Align right">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M6 18h14"></path>
                        </svg>
                    </button>
                    <button @click="clearRowSelection" type="button" class="px-3 py-1 text-sm text-gray-800 bg-gray-200 rounded-md hover:bg-gray-300">
                        Clear Selection
                    </button>
                </div>
            </div>

            {{-- Background image controls --}}
            <div x-show="backgroundUrl" x-transition class="flex flex-wrap items-center gap-4 p-3 mb-4 border rounded-lg bg-indigo-50 border-indigo-200">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-indigo-800">Background:</span>
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">Scale:</label>
                    <input type="range" x-model="backgroundScale" min="0.1" max="3" step="0.01" @input="updateBackgroundScale()" class="w-20">
                    <input type="number" x-model="backgroundScale" min="0.1" max="3" step="0.01" @input="updateBackgroundScale()" class="w-16 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded">
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">X:</label>
                    <input type="range" x-model="backgroundX" min="-1000" max="1000" step="1" @input="updateBackgroundPosition()" class="w-20">
                    <input type="number" x-model="backgroundX" step="1" @input="updateBackgroundPosition()" class="w-16 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded">
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">Y:</label>
                    <input type="range" x-model="backgroundY" min="-1000" max="1000" step="1" @input="updateBackgroundPosition()" class="w-20">
                    <input type="number" x-model="backgroundY" step="1" @input="updateBackgroundPosition()" class="w-16 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded">
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">Opacity:</label>
                    <input type="range" x-model="backgroundOpacity" min="0" max="1" step="0.01" @input="updateBackgroundOpacity()" class="w-16">
                    <input type="number" x-model="backgroundOpacity" min="0" max="1" step="0.01" @input="updateBackgroundOpacity()" class="w-14 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded">
                </div>
                <button @click="resetBackgroundPosition" type="button" class="px-2 py-1 text-xs text-indigo-700 bg-indigo-100 rounded hover:bg-indigo-200">Reset</button>
                <button @click="saveBackgroundSettings" type="button" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Save Settings</button>
            </div>

            <div class="overflow-hidden bg-gray-100 border-2 border-gray-300 rounded-lg">
                <div id="konva-container" wire:ignore></div>
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

        {{-- Sections List --}}
        @if(count($sections) > 0)
            <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Sections</h3>
                <div class="space-y-2">
                    @foreach($sections as $section)
                        <div x-data="{ expanded: false }" class="border rounded-lg">
                            <div class="flex items-center justify-between p-3 cursor-pointer hover:bg-gray-50"
                                 @click="selectSection({{ $section['id'] }})">
                                <div class="flex items-center gap-3">
                                    @if($section['section_type'] === 'standard' && count($section['rows'] ?? []) > 0)
                                    <button @click.stop="expanded = !expanded" class="p-1 text-gray-500 hover:text-gray-700">
                                        <svg class="w-4 h-4 transition-transform" :class="expanded ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </button>
                                    @endif
                                    <div class="flex gap-1">
                                        <div class="w-4 h-4 border rounded" style="background-color: {{ $section['color_hex'] ?? '#3B82F6' }}" title="Section color"></div>
                                        <div class="w-4 h-4 border rounded" style="background-color: {{ $section['seat_color'] ?? '#22C55E' }}" title="Seat color"></div>
                                    </div>
                                    <div>
                                        <div class="font-medium">{{ $section['section_code'] }} - {{ $section['name'] }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ count($section['rows'] ?? []) }} rows •
                                            {{ collect($section['rows'] ?? [])->sum(fn($row) => count($row['seats'] ?? [])) }} seats
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="text-xs text-gray-400">
                                        ({{ $section['x_position'] }}, {{ $section['y_position'] }}) •
                                        {{ $section['width'] }}x{{ $section['height'] }}
                                    </div>
                                    <button @click.stop="editSectionColors({{ $section['id'] }}, '{{ $section['color_hex'] ?? '#3B82F6' }}', '{{ $section['seat_color'] ?? '#22C55E' }}')"
                                            class="px-2 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200">
                                        Edit Colors
                                    </button>
                                    @if($section['section_type'] === 'standard')
                                    <button @click.stop="selectRowsBySection({{ $section['id'] }})"
                                            class="px-2 py-1 text-xs text-blue-700 bg-blue-100 rounded hover:bg-blue-200"
                                            title="Select all rows in this section">
                                        Select Rows
                                    </button>
                                    <button @click.stop="recalculateRows({{ $section['id'] }})"
                                            class="px-2 py-1 text-xs text-orange-700 bg-orange-100 rounded hover:bg-orange-200"
                                            title="Re-group seats into rows based on Y position">
                                        Recalc Rows
                                    </button>
                                    @endif
                                </div>
                            </div>
                            {{-- Expandable rows list --}}
                            @if($section['section_type'] === 'standard' && count($section['rows'] ?? []) > 0)
                            <div x-show="expanded" x-transition class="px-3 pb-3 ml-8 border-t">
                                <div class="pt-2 space-y-1">
                                    @foreach($section['rows'] ?? [] as $row)
                                    <div class="flex items-center justify-between px-2 py-1 text-sm rounded hover:bg-gray-100"
                                         :class="selectedRows.find(r => r.rowId === {{ $row['id'] }}) ? 'bg-blue-100' : ''">
                                        <button @click.stop="selectRow({{ $section['id'] }}, {{ $row['id'] }})"
                                                class="flex items-center gap-2 flex-1 text-left">
                                            <span class="font-medium">Row {{ $row['label'] }}</span>
                                            <span class="text-xs text-gray-500">{{ count($row['seats'] ?? []) }} seats</span>
                                        </button>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Color Edit Modal --}}
        <div x-show="showColorModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="p-6 bg-white rounded-lg shadow-xl w-96" @click.away="showColorModal = false">
                <h3 class="mb-4 text-lg font-semibold">Edit Section Colors</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block mb-1 text-sm font-medium">Section Background Color</label>
                        <input type="color" x-model="editColorHex" class="w-full h-10 rounded cursor-pointer">
                    </div>
                    <div>
                        <label class="block mb-1 text-sm font-medium">Seat Color (Available)</label>
                        <input type="color" x-model="editSeatColor" class="w-full h-10 rounded cursor-pointer">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button @click="showColorModal = false" type="button" class="px-4 py-2 text-sm bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                        <button @click="saveSectionColors" type="button" class="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    {{-- Konva.js CDN --}}
    <script src="https://unpkg.com/konva@9/konva.min.js"></script>

    <script>
        function konvaDesigner() {
            return {
                stage: null,
                layer: null,
                transformer: null,
                backgroundLayer: null,
                drawLayer: null,
                zoom: 1,
                showGrid: true,
                selectedSection: null,
                sections: @json($sections),
                canvasWidth: {{ $canvasWidth }},
                canvasHeight: {{ $canvasHeight }},
                backgroundUrl: '{{ $backgroundUrl }}',
                drawMode: 'select',
                polygonPoints: [],
                currentDrawingShape: null,
                tempCircle: null,
                circleStart: null,

                // Multi-select state
                selectedSeats: [],
                selectedRows: [],
                selectionRect: null,
                selectionStartPos: null,
                assignToSectionId: '',
                assignToRowLabel: '',

                // Color edit modal
                showColorModal: false,
                editSectionId: null,
                editColorHex: '#3B82F6',
                editSeatColor: '#22C55E',

                // Snap to grid
                snapToGrid: false,
                gridSize: 50,

                // Seat settings
                seatSize: 8,
                seatShape: 'circle',

                // Tooltip
                tooltip: null,

                // Background image controls (loaded from database)
                backgroundScale: {{ $backgroundScale ?? 1 }},
                backgroundOpacity: {{ $backgroundOpacity ?? 0.3 }},
                backgroundX: {{ $backgroundX ?? 0 }},
                backgroundY: {{ $backgroundY ?? 0 }},
                backgroundImage: null,
                backgroundBaseX: 0,
                backgroundBaseY: 0,

                init() {
                    this.createStage();
                    this.loadSections();
                    this.createTooltip();
                },

                // Keyboard shortcuts handler
                handleKeyDown(e) {
                    // Don't handle if typing in an input
                    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                        return;
                    }

                    switch (e.key) {
                        case 'Delete':
                        case 'Backspace':
                            if (this.selectedSeats.length > 0) {
                                this.deleteSelectedSeats();
                            } else if (this.selectedSection) {
                                this.deleteSelected();
                            }
                            e.preventDefault();
                            break;

                        case 'Escape':
                            this.cancelDrawing();
                            this.clearSelection();
                            this.transformer.nodes([]);
                            this.selectedSection = null;
                            this.layer.batchDraw();
                            break;

                        case 'a':
                            if (e.ctrlKey || e.metaKey) {
                                // Ctrl+A - select all seats in multi-select mode
                                if (this.drawMode === 'multiselect') {
                                    this.selectAllSeats();
                                    e.preventDefault();
                                }
                            }
                            break;

                        case 'ArrowLeft':
                        case 'ArrowRight':
                        case 'ArrowUp':
                        case 'ArrowDown':
                            if (this.selectedSection) {
                                e.preventDefault();
                                const step = e.shiftKey ? 10 : 1; // Shift for larger steps
                                let deltaX = 0, deltaY = 0;

                                switch (e.key) {
                                    case 'ArrowLeft': deltaX = -step; break;
                                    case 'ArrowRight': deltaX = step; break;
                                    case 'ArrowUp': deltaY = -step; break;
                                    case 'ArrowDown': deltaY = step; break;
                                }

                                // Update visual position
                                const node = this.stage.findOne(`#section-${this.selectedSection}`);
                                if (node) {
                                    node.x(node.x() + deltaX);
                                    node.y(node.y() + deltaY);
                                    this.layer.batchDraw();

                                    // Save to backend
                                    @this.call('moveSection', this.selectedSection, deltaX, deltaY);
                                }
                            }
                            break;
                    }
                },

                // Handle section moved event from backend
                handleSectionMoved(event) {
                    const { sectionId, x, y } = event.detail;
                    const node = this.stage.findOne(`#section-${sectionId}`);
                    if (node) {
                        node.x(x);
                        node.y(y);
                        this.layer.batchDraw();
                    }
                },

                // Select all seats
                selectAllSeats() {
                    this.clearSelection();
                    this.seatsLayer.find('.seat').forEach(seat => {
                        const seatId = seat.getAttr('seatId');
                        if (seatId) {
                            this.selectedSeats.push({ id: seatId, node: seat });
                            seat.stroke('#F97316');
                            seat.strokeWidth(3);
                        }
                    });
                    this.seatsLayer.batchDraw();
                },

                // Statistics functions
                getTotalRows() {
                    return this.sections.reduce((sum, section) => sum + (section.rows?.length || 0), 0);
                },

                getTotalSeats() {
                    return this.sections.reduce((sum, section) => {
                        return sum + (section.rows || []).reduce((rowSum, row) => {
                            return rowSum + (row.seats?.length || 0);
                        }, 0);
                    }, 0);
                },

                // Zoom to fit all content
                zoomToFit() {
                    if (this.sections.length === 0) {
                        this.resetView();
                        return;
                    }

                    // Calculate bounding box of all sections
                    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;

                    this.sections.forEach(section => {
                        const x = section.x_position || 0;
                        const y = section.y_position || 0;
                        const w = section.width || 200;
                        const h = section.height || 150;

                        minX = Math.min(minX, x);
                        minY = Math.min(minY, y);
                        maxX = Math.max(maxX, x + w);
                        maxY = Math.max(maxY, y + h);
                    });

                    const contentWidth = maxX - minX;
                    const contentHeight = maxY - minY;

                    const container = document.getElementById('konva-container');
                    const containerWidth = container.offsetWidth || 1200;
                    const containerHeight = 700;

                    // Calculate scale to fit with padding
                    const padding = 50;
                    const scaleX = (containerWidth - padding * 2) / contentWidth;
                    const scaleY = (containerHeight - padding * 2) / contentHeight;
                    const scale = Math.min(scaleX, scaleY, 2); // Cap at 2x zoom

                    this.zoom = Math.max(0.1, scale);
                    this.stage.scale({ x: this.zoom, y: this.zoom });

                    // Center the content
                    const newX = (containerWidth / 2) - ((minX + contentWidth / 2) * this.zoom);
                    const newY = (containerHeight / 2) - ((minY + contentHeight / 2) * this.zoom);
                    this.stage.position({ x: newX, y: newY });
                },

                // Toggle snap to grid
                toggleSnapToGrid() {
                    this.snapToGrid = !this.snapToGrid;
                },

                // Snap position to grid
                snapPosition(pos) {
                    if (!this.snapToGrid) return pos;
                    return {
                        x: Math.round(pos.x / this.gridSize) * this.gridSize,
                        y: Math.round(pos.y / this.gridSize) * this.gridSize
                    };
                },

                // Create tooltip element
                createTooltip() {
                    this.tooltip = document.createElement('div');
                    this.tooltip.style.cssText = `
                        position: fixed;
                        padding: 6px 10px;
                        background: rgba(0,0,0,0.85);
                        color: white;
                        border-radius: 4px;
                        font-size: 12px;
                        pointer-events: none;
                        z-index: 9999;
                        display: none;
                        white-space: nowrap;
                    `;
                    document.body.appendChild(this.tooltip);
                },

                // Show tooltip for seat
                showSeatTooltip(seat, pos) {
                    const seatId = seat.getAttr('seatId');
                    const sectionId = seat.getAttr('sectionId');

                    // Find seat data
                    let seatData = null;
                    let sectionData = null;
                    let rowData = null;

                    for (const section of this.sections) {
                        if (section.id === sectionId) {
                            sectionData = section;
                            for (const row of section.rows || []) {
                                for (const s of row.seats || []) {
                                    if (s.id === seatId) {
                                        seatData = s;
                                        rowData = row;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if (seatData && sectionData) {
                        const displayName = seatData.display_name || `${sectionData.name}, Row ${rowData?.label || '?'}, Seat ${seatData.label}`;
                        this.tooltip.innerHTML = `
                            <div><strong>${displayName}</strong></div>
                            <div style="font-size: 10px; color: #aaa;">UID: ${seatData.seat_uid || 'N/A'}</div>
                        `;
                        this.tooltip.style.left = (pos.x + 15) + 'px';
                        this.tooltip.style.top = (pos.y + 15) + 'px';
                        this.tooltip.style.display = 'block';
                    }
                },

                hideTooltip() {
                    if (this.tooltip) {
                        this.tooltip.style.display = 'none';
                    }
                },

                // JSON Export
                exportJSON() {
                    const exportData = {
                        layout: {
                            id: {{ $layout->id }},
                            name: '{{ $layout->name }}',
                            canvasWidth: this.canvasWidth,
                            canvasHeight: this.canvasHeight,
                            exportedAt: new Date().toISOString(),
                        },
                        sections: this.sections.map(section => ({
                            id: section.id,
                            name: section.name,
                            section_code: section.section_code,
                            section_type: section.section_type,
                            x_position: section.x_position,
                            y_position: section.y_position,
                            width: section.width,
                            height: section.height,
                            rotation: section.rotation,
                            color_hex: section.color_hex,
                            seat_color: section.seat_color,
                            background_color: section.background_color,
                            corner_radius: section.corner_radius,
                            metadata: section.metadata,
                            rows: (section.rows || []).map(row => ({
                                id: row.id,
                                label: row.label,
                                y: row.y,
                                rotation: row.rotation,
                                seats: (row.seats || []).map(seat => ({
                                    id: seat.id,
                                    label: seat.label,
                                    display_name: seat.display_name,
                                    x: seat.x,
                                    y: seat.y,
                                    angle: seat.angle,
                                    shape: seat.shape,
                                    seat_uid: seat.seat_uid,
                                }))
                            }))
                        })),
                        statistics: {
                            totalSections: this.sections.length,
                            totalRows: this.getTotalRows(),
                            totalSeats: this.getTotalSeats(),
                        }
                    };

                    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `seating-layout-{{ $layout->id }}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                },

                createStage() {
                    const container = document.getElementById('konva-container');
                    const containerWidth = container.offsetWidth || 1200;
                    const containerHeight = 700;

                    // Create stage
                    this.stage = new Konva.Stage({
                        container: 'konva-container',
                        width: containerWidth,
                        height: containerHeight,
                        draggable: true,
                    });

                    // Background layer
                    this.backgroundLayer = new Konva.Layer();
                    this.stage.add(this.backgroundLayer);

                    // Draw background
                    this.drawBackground();

                    // Main layer for sections (shapes only)
                    this.layer = new Konva.Layer();
                    this.stage.add(this.layer);

                    // Seats layer (above sections, absolute positioning)
                    this.seatsLayer = new Konva.Layer();
                    this.stage.add(this.seatsLayer);

                    // Drawing layer (for temporary shapes while drawing)
                    this.drawLayer = new Konva.Layer();
                    this.stage.add(this.drawLayer);

                    // Transformer for selection/resize
                    this.transformer = new Konva.Transformer({
                        enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
                        rotateEnabled: true,
                        borderStroke: '#4F46E5',
                        borderStrokeWidth: 2,
                        anchorStroke: '#4F46E5',
                        anchorFill: '#fff',
                        anchorSize: 10,
                    });
                    this.layer.add(this.transformer);

                    // Click handler for drawing and selection
                    this.stage.on('click', (e) => {
                        const pos = this.stage.getPointerPosition();
                        const stagePos = {
                            x: (pos.x - this.stage.x()) / this.zoom,
                            y: (pos.y - this.stage.y()) / this.zoom
                        };

                        if (this.drawMode === 'polygon') {
                            this.addPolygonPoint(stagePos);
                        } else if (this.drawMode === 'circle' && !this.tempCircle) {
                            // Start drawing circle
                            this.circleStart = stagePos;
                        } else if (this.drawMode === 'seat') {
                            // Add seat mode
                            if (!this.selectedSection) {
                                alert('Please select a section first by clicking on it.');
                                return;
                            }
                            this.addSeatAtPosition(stagePos);
                        } else if (this.drawMode === 'multiselect') {
                            // Handle multi-select click on seats
                            this.handleMultiSelectClick(e);
                        } else if (this.drawMode === 'select') {
                            if (e.target === this.stage || e.target.getLayer() === this.backgroundLayer) {
                                this.transformer.nodes([]);
                                this.selectedSection = null;
                                this.clearSelection();
                            }
                        }
                    });

                    // Mouse down for box selection
                    this.stage.on('mousedown', (e) => {
                        if (this.drawMode === 'multiselect' && (e.target === this.stage || e.target.getLayer() === this.backgroundLayer)) {
                            this.startBoxSelection(e);
                        }
                    });

                    // Mouse move handler for circle drawing and box selection
                    this.stage.on('mousemove', (e) => {
                        if (this.drawMode === 'circle' && this.circleStart) {
                            const pos = this.stage.getPointerPosition();
                            const stagePos = {
                                x: (pos.x - this.stage.x()) / this.zoom,
                                y: (pos.y - this.stage.y()) / this.zoom
                            };

                            const radius = Math.sqrt(
                                Math.pow(stagePos.x - this.circleStart.x, 2) +
                                Math.pow(stagePos.y - this.circleStart.y, 2)
                            );

                            this.drawLayer.destroyChildren();
                            this.tempCircle = new Konva.Circle({
                                x: this.circleStart.x,
                                y: this.circleStart.y,
                                radius: radius,
                                stroke: '#10B981',
                                strokeWidth: 2,
                                fill: '#10B981',
                                opacity: 0.2,
                            });
                            this.drawLayer.add(this.tempCircle);
                            this.drawLayer.batchDraw();
                        }

                        // Box selection
                        if (this.drawMode === 'multiselect' && this.selectionStartPos) {
                            this.updateBoxSelection(e);
                        }
                    });

                    // Mouse up handler for circle drawing and box selection
                    this.stage.on('mouseup', (e) => {
                        if (this.drawMode === 'circle' && this.circleStart && this.tempCircle) {
                            const radius = this.tempCircle.radius();
                            if (radius > 10) {
                                const sectionData = {
                                    x_position: Math.round(this.circleStart.x - radius),
                                    y_position: Math.round(this.circleStart.y - radius),
                                    width: Math.round(radius * 2),
                                    height: Math.round(radius * 2),
                                    metadata: {
                                        shape: 'circle'
                                    }
                                };
                                this.openSectionForm(sectionData);
                            }
                            this.cancelDrawing();
                        }

                        // End box selection
                        if (this.drawMode === 'multiselect' && this.selectionStartPos) {
                            this.endBoxSelection(e);
                        }
                    });

                    // Wheel zoom
                    this.stage.on('wheel', (e) => {
                        e.evt.preventDefault();
                        const oldScale = this.stage.scaleX();
                        const pointer = this.stage.getPointerPosition();
                        const mousePointTo = {
                            x: (pointer.x - this.stage.x()) / oldScale,
                            y: (pointer.y - this.stage.y()) / oldScale,
                        };

                        const direction = e.evt.deltaY > 0 ? -1 : 1;
                        const newScale = direction > 0 ? oldScale * 1.05 : oldScale / 1.05;
                        this.zoom = Math.max(0.1, Math.min(3, newScale));

                        this.stage.scale({ x: this.zoom, y: this.zoom });

                        const newPos = {
                            x: pointer.x - mousePointTo.x * this.zoom,
                            y: pointer.y - mousePointTo.y * this.zoom,
                        };
                        this.stage.position(newPos);
                    });
                },

                // Multi-select methods
                handleMultiSelectClick(e) {
                    const target = e.target;

                    // Check if clicked on a seat
                    if (target.hasName && target.hasName('seat')) {
                        const seatId = target.getAttr('seatId');
                        if (seatId) {
                            const isShift = e.evt.shiftKey;

                            if (isShift) {
                                // Toggle selection
                                const index = this.selectedSeats.findIndex(s => s.id === seatId);
                                if (index > -1) {
                                    this.selectedSeats.splice(index, 1);
                                    target.stroke('#1F2937');
                                    target.strokeWidth(1);
                                } else {
                                    this.selectedSeats.push({ id: seatId, node: target });
                                    target.stroke('#F97316');
                                    target.strokeWidth(3);
                                }
                            } else {
                                // Single select
                                this.clearSelection();
                                this.selectedSeats.push({ id: seatId, node: target });
                                target.stroke('#F97316');
                                target.strokeWidth(3);
                            }

                            this.layer.batchDraw();
                        }
                    }
                },

                startBoxSelection(e) {
                    const pos = this.stage.getPointerPosition();
                    this.selectionStartPos = {
                        x: (pos.x - this.stage.x()) / this.zoom,
                        y: (pos.y - this.stage.y()) / this.zoom
                    };

                    // Create selection rectangle
                    this.selectionRect = new Konva.Rect({
                        x: this.selectionStartPos.x,
                        y: this.selectionStartPos.y,
                        width: 0,
                        height: 0,
                        fill: 'rgba(249, 115, 22, 0.2)',
                        stroke: '#F97316',
                        strokeWidth: 1,
                        dash: [5, 5],
                    });
                    this.drawLayer.add(this.selectionRect);
                },

                updateBoxSelection(e) {
                    if (!this.selectionRect || !this.selectionStartPos) return;

                    const pos = this.stage.getPointerPosition();
                    const currentPos = {
                        x: (pos.x - this.stage.x()) / this.zoom,
                        y: (pos.y - this.stage.y()) / this.zoom
                    };

                    const x = Math.min(this.selectionStartPos.x, currentPos.x);
                    const y = Math.min(this.selectionStartPos.y, currentPos.y);
                    const width = Math.abs(currentPos.x - this.selectionStartPos.x);
                    const height = Math.abs(currentPos.y - this.selectionStartPos.y);

                    this.selectionRect.x(x);
                    this.selectionRect.y(y);
                    this.selectionRect.width(width);
                    this.selectionRect.height(height);
                    this.drawLayer.batchDraw();
                },

                endBoxSelection(e) {
                    if (!this.selectionRect) {
                        this.selectionStartPos = null;
                        return;
                    }

                    const box = this.selectionRect.getClientRect();

                    // Find all seats within selection
                    this.layer.find('.seat').forEach(seat => {
                        const seatBox = seat.getClientRect();

                        // Check if seat is within selection box
                        if (this.intersects(box, seatBox)) {
                            const seatId = seat.getAttr('seatId');
                            if (seatId && !this.selectedSeats.find(s => s.id === seatId)) {
                                this.selectedSeats.push({ id: seatId, node: seat });
                                seat.stroke('#F97316');
                                seat.strokeWidth(3);
                            }
                        }
                    });

                    // Clean up
                    this.selectionRect.destroy();
                    this.selectionRect = null;
                    this.selectionStartPos = null;
                    this.drawLayer.batchDraw();
                    this.layer.batchDraw();
                },

                intersects(r1, r2) {
                    return !(r2.x > r1.x + r1.width ||
                             r2.x + r2.width < r1.x ||
                             r2.y > r1.y + r1.height ||
                             r2.y + r2.height < r1.y);
                },

                clearSelection() {
                    this.selectedSeats.forEach(seat => {
                        if (seat.node) {
                            seat.node.stroke('#1F2937');
                            seat.node.strokeWidth(1);
                        }
                    });
                    this.selectedSeats = [];
                    if (this.seatsLayer) this.seatsLayer.batchDraw();
                    this.layer.batchDraw();
                },

                assignSelectedSeats() {
                    if (!this.assignToSectionId || !this.assignToRowLabel || this.selectedSeats.length === 0) {
                        alert('Please select seats, a section, and enter a row label.');
                        return;
                    }

                    const seatIds = this.selectedSeats.map(s => s.id);
                    @this.call('assignSeatsToSection', seatIds, parseInt(this.assignToSectionId), this.assignToRowLabel);

                    this.clearSelection();
                    this.assignToSectionId = '';
                    this.assignToRowLabel = '';
                },

                deleteSelectedSeats() {
                    if (this.selectedSeats.length === 0) return;

                    if (!confirm(`Delete ${this.selectedSeats.length} selected seats?`)) return;

                    // Use bulk delete for better performance
                    const seatIds = this.selectedSeats.map(s => s.id);
                    @this.call('deleteSeats', seatIds);

                    this.clearSelection();
                },

                // Row selection methods
                selectRow(sectionId, rowId) {
                    const existingIndex = this.selectedRows.findIndex(r => r.rowId === rowId);
                    if (existingIndex >= 0) {
                        // Deselect
                        this.selectedRows.splice(existingIndex, 1);
                    } else {
                        // Select
                        this.selectedRows.push({ sectionId, rowId });
                    }
                    this.highlightSelectedRows();
                },

                selectRowsBySection(sectionId) {
                    const section = this.sections.find(s => s.id === sectionId);
                    if (!section || !section.rows) return;

                    // Select all rows in this section
                    section.rows.forEach(row => {
                        if (!this.selectedRows.find(r => r.rowId === row.id)) {
                            this.selectedRows.push({ sectionId, rowId: row.id });
                        }
                    });
                    this.highlightSelectedRows();
                },

                clearRowSelection() {
                    this.selectedRows = [];
                    this.highlightSelectedRows();
                },

                highlightSelectedRows() {
                    // Highlight seats belonging to selected rows
                    this.seatsLayer.find('.seat').forEach(seat => {
                        const seatData = this.getSeatDataById(seat.getAttr('seatId'));
                        if (seatData) {
                            const isRowSelected = this.selectedRows.some(r => r.rowId === seatData.rowId);
                            if (isRowSelected) {
                                seat.stroke('#3B82F6');
                                seat.strokeWidth(3);
                            } else {
                                seat.stroke('#1F2937');
                                seat.strokeWidth(1);
                            }
                        }
                    });
                    this.seatsLayer.batchDraw();
                },

                getSeatDataById(seatId) {
                    for (const section of this.sections) {
                        if (section.rows) {
                            for (const row of section.rows) {
                                if (row.seats) {
                                    const seat = row.seats.find(s => s.id === seatId);
                                    if (seat) {
                                        return { ...seat, rowId: row.id, sectionId: section.id };
                                    }
                                }
                            }
                        }
                    }
                    return null;
                },

                alignSelectedRows(alignment) {
                    if (this.selectedRows.length === 0) {
                        alert('Please select rows first');
                        return;
                    }

                    // Group by section
                    const bySection = {};
                    this.selectedRows.forEach(r => {
                        if (!bySection[r.sectionId]) bySection[r.sectionId] = [];
                        bySection[r.sectionId].push(r.rowId);
                    });

                    // Call alignment for each section
                    Object.keys(bySection).forEach(sectionId => {
                        @this.call('alignRows', parseInt(sectionId), bySection[sectionId], alignment);
                    });

                    this.clearRowSelection();
                },

                // Color edit methods
                editSectionColors(sectionId, colorHex, seatColor) {
                    this.editSectionId = sectionId;
                    this.editColorHex = colorHex;
                    this.editSeatColor = seatColor;
                    this.showColorModal = true;
                },

                saveSectionColors() {
                    if (this.editSectionId) {
                        @this.call('updateSectionColors', this.editSectionId, this.editColorHex, this.editSeatColor);
                        this.showColorModal = false;
                    }
                },

                // Export SVG
                exportSVG() {
                    // Create SVG content (avoid PHP parsing by splitting <?xml)
                    let svgContent = '<' + '?xml version="1.0" encoding="UTF-8"?' + '>\n';
                    svgContent += `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${this.canvasWidth} ${this.canvasHeight}" width="${this.canvasWidth}" height="${this.canvasHeight}">
  <style>
    .section { opacity: 0.6; }
    .section-label { font-family: Arial, sans-serif; font-size: 14px; fill: #1F2937; }
    .seat { opacity: 0.8; stroke: #1F2937; stroke-width: 1; }
  </style>
  <rect width="100%" height="100%" fill="#f3f4f6"/>
`;

                    // Add sections
                    this.sections.forEach(section => {
                        const x = section.x_position || 0;
                        const y = section.y_position || 0;
                        const w = section.width || 200;
                        const h = section.height || 150;
                        const color = section.color_hex || '#3B82F6';
                        const seatColor = section.seat_color || '#22C55E';

                        // Section rectangle
                        svgContent += `  <g transform="translate(${x}, ${y})">
    <rect class="section" width="${w}" height="${h}" fill="${color}" stroke="${color}" stroke-width="2" rx="4"/>
    <text class="section-label" x="8" y="20">${section.section_code || ''} - ${section.name}</text>
`;

                        // Seats
                        if (section.rows) {
                            section.rows.forEach(row => {
                                if (row.seats) {
                                    row.seats.forEach(seat => {
                                        const sx = parseFloat(seat.x || 0);
                                        const sy = parseFloat(seat.y || 0) + 20;
                                        svgContent += `    <circle class="seat" cx="${sx}" cy="${sy}" r="4" fill="${seatColor}" data-seat-id="${seat.id}" data-seat-uid="${seat.seat_uid || ''}"/>
`;
                                    });
                                }
                            });
                        }

                        svgContent += `  </g>
`;
                    });

                    svgContent += `</svg>`;

                    // Download
                    const blob = new Blob([svgContent], { type: 'image/svg+xml' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `seating-layout-{{ $layout->id }}.svg`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                },

                drawBackground() {
                    // Grid
                    if (this.showGrid) {
                        const gridSize = 50;
                        for (let i = 0; i < this.canvasWidth / gridSize; i++) {
                            this.backgroundLayer.add(new Konva.Line({
                                points: [i * gridSize, 0, i * gridSize, this.canvasHeight],
                                stroke: '#ddd',
                                strokeWidth: 1,
                            }));
                        }
                        for (let j = 0; j < this.canvasHeight / gridSize; j++) {
                            this.backgroundLayer.add(new Konva.Line({
                                points: [0, j * gridSize, this.canvasWidth, j * gridSize],
                                stroke: '#ddd',
                                strokeWidth: 1,
                            }));
                        }
                    }

                    // Background image (preserve aspect ratio)
                    if (this.backgroundUrl) {
                        const imageObj = new Image();
                        imageObj.crossOrigin = 'anonymous'; // Handle CORS for external images
                        imageObj.onerror = () => {
                            console.warn('Failed to load background image:', this.backgroundUrl);
                        };
                        imageObj.onload = () => {
                            // Calculate dimensions while preserving aspect ratio
                            const imgAspect = imageObj.width / imageObj.height;
                            const canvasAspect = this.canvasWidth / this.canvasHeight;

                            let width, height;
                            if (imgAspect > canvasAspect) {
                                // Image is wider than canvas
                                width = this.canvasWidth;
                                height = this.canvasWidth / imgAspect;
                            } else {
                                // Image is taller than canvas
                                height = this.canvasHeight;
                                width = this.canvasHeight * imgAspect;
                            }

                            // Apply current scale
                            const scale = parseFloat(this.backgroundScale);
                            const scaledWidth = width * scale;
                            const scaledHeight = height * scale;

                            // Calculate base position (centered)
                            this.backgroundBaseX = (this.canvasWidth - scaledWidth) / 2;
                            this.backgroundBaseY = (this.canvasHeight - scaledHeight) / 2;

                            this.backgroundImage = new Konva.Image({
                                x: this.backgroundBaseX + parseFloat(this.backgroundX),
                                y: this.backgroundBaseY + parseFloat(this.backgroundY),
                                image: imageObj,
                                width: width,
                                height: height,
                                opacity: parseFloat(this.backgroundOpacity),
                                scaleX: scale,
                                scaleY: scale,
                                originalWidth: width,
                                originalHeight: height,
                            });
                            this.backgroundLayer.add(this.backgroundImage);
                            this.backgroundLayer.batchDraw();
                        };
                        imageObj.src = this.backgroundUrl;
                    }
                },

                loadSections() {
                    // Clear seats layer before loading new sections
                    if (this.seatsLayer) {
                        this.seatsLayer.destroyChildren();
                    }

                    this.sections.forEach(section => {
                        this.createSection(section);
                    });

                    if (this.seatsLayer) {
                        this.seatsLayer.batchDraw();
                    }
                },

                createSection(section) {
                    const group = new Konva.Group({
                        x: section.x_position || 100,
                        y: section.y_position || 100,
                        rotation: section.rotation || 0,
                        draggable: true,
                        id: `section-${section.id}`,
                        sectionData: section,
                    });

                    // Check if section has custom shape in metadata
                    const metadata = section.metadata || {};
                    const shape = metadata.shape || 'rectangle';

                    // Check if this is a decorative zone
                    const isDecorativeZone = ['decorative', 'stage', 'dance_floor'].includes(section.section_type);

                    // Determine fill color and opacity based on zone type
                    const fillColor = isDecorativeZone && section.background_color
                        ? section.background_color
                        : (section.color_hex || '#3B82F6');
                    const fillOpacity = isDecorativeZone ? 0.7 : 0.2;
                    const strokeColor = isDecorativeZone && section.background_color
                        ? section.background_color
                        : (section.color_hex || '#3B82F6');
                    const strokeWidth = isDecorativeZone ? 3 : 2;
                    const cornerRadius = section.corner_radius || 4;

                    // Seat color for this section
                    const seatColor = section.seat_color || '#22C55E';

                    let backgroundShape;

                    if (shape === 'polygon' && metadata.points) {
                        // Custom polygon shape
                        backgroundShape = new Konva.Line({
                            points: metadata.points,
                            fill: fillColor,
                            opacity: fillOpacity,
                            stroke: strokeColor,
                            strokeWidth: strokeWidth,
                            closed: true,
                        });
                    } else if (shape === 'circle') {
                        // Circle shape
                        const radius = Math.min(section.width, section.height) / 2;
                        backgroundShape = new Konva.Circle({
                            x: section.width / 2,
                            y: section.height / 2,
                            radius: radius,
                            fill: fillColor,
                            opacity: fillOpacity,
                            stroke: strokeColor,
                            strokeWidth: strokeWidth,
                        });
                    } else {
                        // Default rectangle shape
                        backgroundShape = new Konva.Rect({
                            width: section.width || 200,
                            height: section.height || 150,
                            fill: fillColor,
                            opacity: fillOpacity,
                            stroke: strokeColor,
                            strokeWidth: strokeWidth,
                            cornerRadius: cornerRadius,
                        });
                    }

                    // Label
                    const label = new Konva.Text({
                        text: `${section.section_code || ''} - ${section.name}`,
                        fontSize: 14,
                        fontFamily: 'Arial',
                        fill: '#1F2937',
                        padding: 8,
                        align: 'center',
                        width: section.width || 200,
                    });

                    group.add(backgroundShape);
                    group.add(label);

                    // Add background image for decorative zones if available
                    if (isDecorativeZone && section.background_image) {
                        const imageObj = new Image();
                        imageObj.onload = () => {
                            const bgImage = new Konva.Image({
                                image: imageObj,
                                width: section.width || 200,
                                height: section.height || 150,
                                cornerRadius: cornerRadius,
                                opacity: 0.8,
                            });
                            // Insert image between background shape and label
                            group.children.splice(1, 0, bgImage);
                            this.layer.batchDraw();
                        };
                        // Build the full URL for the background image
                        const imagePath = section.background_image.startsWith('http')
                            ? section.background_image
                            : `/storage/${section.background_image}`;
                        imageObj.src = imagePath;
                    }

                    // Draw seats on separate layer with absolute coordinates (skip for decorative zones)
                    if (!isDecorativeZone && section.rows && section.rows.length > 0) {
                        const sectionX = section.x_position || 0;
                        const sectionY = section.y_position || 0;

                        section.rows.forEach(row => {
                            if (row.seats && row.seats.length > 0) {
                                row.seats.forEach(seat => {
                                    // Calculate absolute position
                                    const absoluteX = sectionX + parseFloat(seat.x || 0);
                                    const absoluteY = sectionY + parseFloat(seat.y || 0);

                                    const seatShape = this.createSeatAbsolute(seat, seatColor, section.id, absoluteX, absoluteY);
                                    this.seatsLayer.add(seatShape);
                                });
                            }
                        });
                        this.seatsLayer.batchDraw();
                    }

                    // Add bounding box for selection highlight
                    const boundingBox = new Konva.Rect({
                        x: -2,
                        y: -2,
                        width: (section.width || 200) + 4,
                        height: (section.height || 150) + 4,
                        stroke: '#F97316',
                        strokeWidth: 3,
                        dash: [8, 4],
                        visible: false,
                        name: 'boundingBox',
                    });
                    group.add(boundingBox);

                    // Click to select
                    group.on('click', (e) => {
                        if (this.drawMode === 'multiselect') {
                            // Toggle section highlight in multi-select mode
                            const bb = group.findOne('.boundingBox');
                            if (bb) {
                                bb.visible(!bb.visible());
                                this.layer.batchDraw();
                            }
                            // Also update Livewire selectedSection for Edit Section modal
                            if (bb && bb.visible()) {
                                @this.set('selectedSection', section.id);
                            }
                        } else {
                            this.transformer.nodes([group]);
                            this.selectedSection = section.id;
                            @this.set('selectedSection', section.id);
                        }
                    });

                    // Save on drag end
                    group.on('dragend', () => {
                        // Apply snap to grid if enabled
                        const snappedPos = this.snapPosition({
                            x: group.x(),
                            y: group.y()
                        });
                        group.position(snappedPos);

                        this.saveSection(section.id, {
                            x_position: Math.round(snappedPos.x),
                            y_position: Math.round(snappedPos.y),
                        });
                        this.layer.batchDraw();
                    });

                    // Save on transform end
                    group.on('transformend', () => {
                        // Get current dimensions from the background shape
                        const currentWidth = backgroundShape.width ? backgroundShape.width() : (section.width || 200);
                        const currentHeight = backgroundShape.height ? backgroundShape.height() : (section.height || 150);

                        // Calculate new dimensions with scale
                        const scaleX = group.scaleX();
                        const scaleY = group.scaleY();
                        const newWidth = Math.round(currentWidth * Math.abs(scaleX));
                        const newHeight = Math.round(currentHeight * Math.abs(scaleY));

                        // Only update dimensions if they are valid (> 0)
                        if (newWidth > 0 && newHeight > 0) {
                            this.saveSection(section.id, {
                                x_position: Math.round(group.x()),
                                y_position: Math.round(group.y()),
                                width: newWidth,
                                height: newHeight,
                                rotation: Math.round(group.rotation()),
                            });

                            // Update background shape dimensions
                            if (backgroundShape.width) {
                                backgroundShape.width(newWidth);
                            }
                            if (backgroundShape.height) {
                                backgroundShape.height(newHeight);
                            }
                            label.width(newWidth);

                            // Update bounding box
                            boundingBox.width(newWidth + 4);
                            boundingBox.height(newHeight + 4);
                        } else {
                            // Just save rotation if dimensions would be invalid
                            this.saveSection(section.id, {
                                x_position: Math.round(group.x()),
                                y_position: Math.round(group.y()),
                                rotation: Math.round(group.rotation()),
                            });
                        }

                        // Reset scale to 1
                        group.scaleX(1);
                        group.scaleY(1);
                        this.layer.batchDraw();
                    });

                    this.layer.add(group);

                    // Note: Removed caching as it breaks click events on individual seats
                    this.layer.batchDraw();
                },

                // Create seat at absolute position (for seatsLayer)
                createSeatAbsolute(seat, seatColor, sectionId, absoluteX, absoluteY) {
                    const angle = parseFloat(seat.angle || 0);
                    const shape = seat.shape || 'circle';
                    const seatSize = this.seatSize || 8;

                    let seatShape;
                    if (shape === 'circle') {
                        seatShape = new Konva.Circle({
                            x: absoluteX,
                            y: absoluteY,
                            radius: seatSize / 2,
                            fill: seatColor || '#22C55E',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            name: 'seat',
                            seatId: seat.id,
                            sectionId: sectionId,
                        });
                    } else if (shape === 'rect') {
                        seatShape = new Konva.Rect({
                            x: absoluteX - seatSize / 2,
                            y: absoluteY - seatSize / 2,
                            width: seatSize,
                            height: seatSize,
                            fill: seatColor || '#22C55E',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            rotation: angle,
                            name: 'seat',
                            seatId: seat.id,
                            sectionId: sectionId,
                        });
                    } else { // stadium
                        seatShape = new Konva.Rect({
                            x: absoluteX - seatSize / 2,
                            y: absoluteY - seatSize / 2,
                            width: seatSize,
                            height: seatSize,
                            fill: seatColor || '#22C55E',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            cornerRadius: seatSize / 2,
                            rotation: angle,
                            name: 'seat',
                            seatId: seat.id,
                            sectionId: sectionId,
                        });
                    }

                    // Add tooltip events
                    seatShape.on('mouseover', (e) => {
                        const mouseEvent = e.evt;
                        this.showSeatTooltip(seatShape, {
                            x: mouseEvent.clientX,
                            y: mouseEvent.clientY
                        });
                        seatShape.strokeWidth(2);
                        this.seatsLayer.batchDraw();
                    });

                    seatShape.on('mouseout', () => {
                        this.hideTooltip();
                        const isSelected = this.selectedSeats.find(s => s.id === seat.id);
                        seatShape.strokeWidth(isSelected ? 3 : 1);
                        this.seatsLayer.batchDraw();
                    });

                    // Click to select individual seat
                    seatShape.on('click', (e) => {
                        e.cancelBubble = true;

                        if (this.drawMode === 'select' || this.drawMode === 'multiselect') {
                            const existingIndex = this.selectedSeats.findIndex(s => s.id === seat.id);
                            if (existingIndex >= 0) {
                                this.selectedSeats.splice(existingIndex, 1);
                                seatShape.stroke('#1F2937');
                                seatShape.strokeWidth(1);
                            } else {
                                if (this.drawMode === 'select' && !e.evt.shiftKey) {
                                    this.clearSelection();
                                }
                                this.selectedSeats.push({ id: seat.id, node: seatShape });
                                seatShape.stroke('#F97316');
                                seatShape.strokeWidth(3);
                            }
                            this.seatsLayer.batchDraw();
                        }
                    });

                    return seatShape;
                },

                // Legacy createSeat for compatibility (relative coordinates within group)
                createSeat(seat, seatColor, sectionId) {
                    const x = parseFloat(seat.x || 0);
                    const y = parseFloat(seat.y || 0);
                    const angle = parseFloat(seat.angle || 0);
                    const shape = seat.shape || 'circle';
                    const seatSize = this.seatSize || 8;

                    let seatShape;
                    if (shape === 'circle') {
                        seatShape = new Konva.Circle({
                            x: x,
                            y: y,
                            radius: seatSize / 2,
                            fill: seatColor || '#22C55E',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            name: 'seat',
                            seatId: seat.id,
                            sectionId: sectionId,
                        });
                    } else if (shape === 'rect') {
                        seatShape = new Konva.Rect({
                            x: x - seatSize / 2,
                            y: y - seatSize / 2,
                            width: seatSize,
                            height: seatSize,
                            fill: seatColor || '#22C55E',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            rotation: angle,
                            name: 'seat',
                            seatId: seat.id,
                            sectionId: sectionId,
                        });
                    } else { // stadium
                        seatShape = new Konva.Rect({
                            x: x - seatSize / 2,
                            y: y - seatSize / 2,
                            width: seatSize,
                            height: seatSize,
                            fill: seatColor || '#22C55E',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            cornerRadius: seatSize / 2,
                            rotation: angle,
                            name: 'seat',
                            seatId: seat.id,
                            sectionId: sectionId,
                        });
                    }

                    // Add tooltip events
                    seatShape.on('mouseover', (e) => {
                        const mouseEvent = e.evt;
                        this.showSeatTooltip(seatShape, {
                            x: mouseEvent.clientX,
                            y: mouseEvent.clientY
                        });
                        // Highlight on hover
                        seatShape.strokeWidth(2);
                        this.layer.batchDraw();
                    });

                    seatShape.on('mouseout', () => {
                        this.hideTooltip();
                        // Reset stroke unless selected
                        const isSelected = this.selectedSeats.find(s => s.id === seat.id);
                        seatShape.strokeWidth(isSelected ? 3 : 1);
                        this.layer.batchDraw();
                    });

                    // Click to select individual seat
                    seatShape.on('click', (e) => {
                        e.cancelBubble = true; // Stop propagation to section

                        if (this.drawMode === 'select') {
                            // Toggle selection in select mode
                            const existingIndex = this.selectedSeats.findIndex(s => s.id === seat.id);
                            if (existingIndex >= 0) {
                                // Deselect
                                this.selectedSeats.splice(existingIndex, 1);
                                seatShape.stroke('#1F2937');
                                seatShape.strokeWidth(1);
                            } else {
                                // Select (hold Shift for multi-select)
                                if (!e.evt.shiftKey) {
                                    // Clear previous selection
                                    this.clearSelection();
                                }
                                this.selectedSeats.push({ id: seat.id, node: seatShape });
                                seatShape.stroke('#F97316');
                                seatShape.strokeWidth(3);
                            }
                            this.layer.batchDraw();
                        } else if (this.drawMode === 'multiselect') {
                            // Multi-select mode - toggle seat
                            const existingIndex = this.selectedSeats.findIndex(s => s.id === seat.id);
                            if (existingIndex >= 0) {
                                this.selectedSeats.splice(existingIndex, 1);
                                seatShape.stroke('#1F2937');
                                seatShape.strokeWidth(1);
                            } else {
                                this.selectedSeats.push({ id: seat.id, node: seatShape });
                                seatShape.stroke('#F97316');
                                seatShape.strokeWidth(3);
                            }
                            this.layer.batchDraw();
                        }
                    });

                    return seatShape;
                },

                saveSection(sectionId, updates) {
                    console.log('Saving section', sectionId, updates);
                    @this.call('updateSection', sectionId, updates)
                        .then(() => console.log('Section saved successfully'))
                        .catch(err => console.error('Failed to save section:', err));
                },

                selectSection(sectionId) {
                    const node = this.stage.findOne(`#section-${sectionId}`);
                    if (node) {
                        this.transformer.nodes([node]);
                        this.selectedSection = sectionId;
                        this.layer.batchDraw();
                    }
                },

                deleteSelected() {
                    if (this.selectedSection) {
                        if (confirm('Delete this section?')) {
                            @this.call('deleteSection', this.selectedSection);
                        }
                    }
                },

                zoomIn() {
                    this.zoom = Math.min(this.zoom * 1.2, 3);
                    this.stage.scale({ x: this.zoom, y: this.zoom });
                },

                zoomOut() {
                    this.zoom = Math.max(this.zoom / 1.2, 0.1);
                    this.stage.scale({ x: this.zoom, y: this.zoom });
                },

                resetView() {
                    this.zoom = 1;
                    this.stage.scale({ x: 1, y: 1 });
                    this.stage.position({ x: 0, y: 0 });
                },

                toggleGrid() {
                    this.showGrid = !this.showGrid;
                    this.backgroundLayer.destroyChildren();
                    this.drawBackground();
                    this.backgroundLayer.batchDraw();
                },

                updateBackgroundScale() {
                    if (this.backgroundImage) {
                        const scale = parseFloat(this.backgroundScale);
                        this.backgroundImage.scale({ x: scale, y: scale });

                        // Calculate new base position (centered)
                        const width = this.backgroundImage.getAttr('originalWidth') * scale;
                        const height = this.backgroundImage.getAttr('originalHeight') * scale;
                        this.backgroundBaseX = (this.canvasWidth - width) / 2;
                        this.backgroundBaseY = (this.canvasHeight - height) / 2;

                        // Apply position offset
                        this.backgroundImage.x(this.backgroundBaseX + parseFloat(this.backgroundX));
                        this.backgroundImage.y(this.backgroundBaseY + parseFloat(this.backgroundY));

                        this.backgroundLayer.batchDraw();
                    }
                },

                updateBackgroundPosition() {
                    if (this.backgroundImage) {
                        this.backgroundImage.x(this.backgroundBaseX + parseFloat(this.backgroundX));
                        this.backgroundImage.y(this.backgroundBaseY + parseFloat(this.backgroundY));
                        this.backgroundLayer.batchDraw();
                    }
                },

                updateBackgroundOpacity() {
                    if (this.backgroundImage) {
                        this.backgroundImage.opacity(parseFloat(this.backgroundOpacity));
                        this.backgroundLayer.batchDraw();
                    }
                },

                resetBackgroundPosition() {
                    this.backgroundScale = 1;
                    this.backgroundX = 0;
                    this.backgroundY = 0;
                    this.backgroundOpacity = 0.3;
                    this.updateBackgroundScale();
                    this.updateBackgroundOpacity();
                },

                saveBackgroundSettings() {
                    @this.call('saveBackgroundSettings',
                        parseFloat(this.backgroundScale),
                        parseInt(this.backgroundX),
                        parseInt(this.backgroundY),
                        parseFloat(this.backgroundOpacity)
                    );
                },

                recalculateRows(sectionId) {
                    if (confirm('This will re-group all seats in this section into rows based on their Y position. Continue?')) {
                        @this.call('recalculateRows', sectionId);
                    }
                },

                setDrawMode(mode) {
                    this.drawMode = mode;
                    this.polygonPoints = [];
                    this.drawLayer.destroyChildren();
                    this.drawLayer.batchDraw();

                    // Enable stage dragging in select and multiselect modes
                    this.stage.draggable(mode === 'select' || mode === 'multiselect');

                    // Disable transformer in non-select modes
                    if (mode !== 'select') {
                        this.transformer.nodes([]);
                        this.selectedSection = null;
                    }

                    // Clear selection when changing modes (except multiselect)
                    if (mode !== 'multiselect' && mode !== 'select') {
                        this.clearSelection();
                    }
                },

                addPolygonPoint(pos) {
                    const snapDistance = 15; // pixels

                    // Check if we should snap to close (click near first point)
                    if (this.polygonPoints.length >= 6) {
                        const firstX = this.polygonPoints[0];
                        const firstY = this.polygonPoints[1];
                        const distance = Math.sqrt(
                            Math.pow(pos.x - firstX, 2) + Math.pow(pos.y - firstY, 2)
                        );

                        if (distance <= snapDistance) {
                            // Snap to close - finish the polygon
                            this.finishDrawing();
                            return;
                        }
                    }

                    this.polygonPoints.push(pos.x, pos.y);

                    // Draw points
                    this.drawLayer.destroyChildren();

                    // Draw line preview
                    if (this.polygonPoints.length >= 4) {
                        const line = new Konva.Line({
                            points: this.polygonPoints,
                            stroke: '#10B981',
                            strokeWidth: 2,
                            closed: false,
                        });
                        this.drawLayer.add(line);
                    }

                    // Draw points as circles
                    for (let i = 0; i < this.polygonPoints.length; i += 2) {
                        const isFirstPoint = (i === 0);
                        const circle = new Konva.Circle({
                            x: this.polygonPoints[i],
                            y: this.polygonPoints[i + 1],
                            radius: isFirstPoint && this.polygonPoints.length >= 6 ? 10 : 5,
                            fill: isFirstPoint && this.polygonPoints.length >= 6 ? '#F59E0B' : '#10B981',
                            stroke: '#fff',
                            strokeWidth: 2,
                        });
                        this.drawLayer.add(circle);
                    }

                    // Add hint text near first point if we have enough points
                    if (this.polygonPoints.length >= 6) {
                        const hintText = new Konva.Text({
                            x: this.polygonPoints[0] + 15,
                            y: this.polygonPoints[1] - 10,
                            text: 'Click to close',
                            fontSize: 12,
                            fill: '#F59E0B',
                            fontStyle: 'bold',
                        });
                        this.drawLayer.add(hintText);
                    }

                    this.drawLayer.batchDraw();
                },

                finishDrawing() {
                    if (this.drawMode === 'polygon' && this.polygonPoints.length >= 6) {
                        // Calculate bounding box
                        let minX = this.polygonPoints[0];
                        let maxX = this.polygonPoints[0];
                        let minY = this.polygonPoints[1];
                        let maxY = this.polygonPoints[1];

                        for (let i = 2; i < this.polygonPoints.length; i += 2) {
                            minX = Math.min(minX, this.polygonPoints[i]);
                            maxX = Math.max(maxX, this.polygonPoints[i]);
                            minY = Math.min(minY, this.polygonPoints[i + 1]);
                            maxY = Math.max(maxY, this.polygonPoints[i + 1]);
                        }

                        const width = maxX - minX;
                        const height = maxY - minY;

                        // Normalize points relative to top-left corner
                        const normalizedPoints = [];
                        for (let i = 0; i < this.polygonPoints.length; i += 2) {
                            normalizedPoints.push(this.polygonPoints[i] - minX);
                            normalizedPoints.push(this.polygonPoints[i + 1] - minY);
                        }

                        // Save to backend
                        const sectionData = {
                            x_position: Math.round(minX),
                            y_position: Math.round(minY),
                            width: Math.round(width),
                            height: Math.round(height),
                            metadata: {
                                shape: 'polygon',
                                points: normalizedPoints
                            }
                        };

                        // Open Filament modal to get section details
                        this.openSectionForm(sectionData);

                        this.cancelDrawing();
                    }
                },

                openSectionForm(geometryData) {
                    // Pre-fill hidden fields with geometry data
                    this.$nextTick(() => {
                        // Find the Add Section button and click it
                        const addButton = document.querySelector('[wire\\:click*="mountAction"][wire\\:click*="addSection"]');
                        if (addButton) {
                            addButton.click();

                            // Wait for modal to open and populate fields
                            setTimeout(() => {
                                const xInput = document.querySelector('input[name="x_position"]');
                                const yInput = document.querySelector('input[name="y_position"]');
                                const widthInput = document.querySelector('input[name="width"]');
                                const heightInput = document.querySelector('input[name="height"]');
                                const rotationInput = document.querySelector('input[name="rotation"]');
                                const displayOrderInput = document.querySelector('input[name="display_order"]');
                                const metadataInput = document.querySelector('input[name="metadata"]');

                                if (xInput) xInput.value = geometryData.x_position;
                                if (yInput) yInput.value = geometryData.y_position;
                                if (widthInput) widthInput.value = geometryData.width;
                                if (heightInput) heightInput.value = geometryData.height;
                                if (rotationInput) rotationInput.value = geometryData.rotation || 0;
                                if (displayOrderInput) displayOrderInput.value = 0;
                                if (metadataInput && geometryData.metadata) {
                                    metadataInput.value = JSON.stringify(geometryData.metadata);
                                }

                                // Trigger Livewire to recognize the changes
                                [xInput, yInput, widthInput, heightInput, rotationInput, displayOrderInput, metadataInput].forEach(input => {
                                    if (input) {
                                        input.dispatchEvent(new Event('input', { bubbles: true }));
                                        input.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                });
                            }, 300);
                        }
                    });
                },

                handleSectionDeleted(detail) {
                    const sectionId = detail.sectionId;
                    const node = this.stage.findOne(`#section-${sectionId}`);
                    if (node) {
                        node.destroy();
                        this.layer.batchDraw();
                    }
                    this.selectedSection = null;
                    this.transformer.nodes([]);

                    // Update sections count
                    this.sections = this.sections.filter(s => s.id !== sectionId);
                },

                handleSectionAdded(detail) {
                    const section = detail.section;
                    this.sections.push(section);
                    this.createSection(section);
                },

                handleLayoutImported(detail) {
                    // Reload all sections after import
                    this.sections = detail.sections;

                    // Clear and rebuild canvas
                    this.layer.destroyChildren();

                    // Re-add transformer
                    this.transformer = new Konva.Transformer({
                        enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
                        rotateEnabled: true,
                        borderStroke: '#4F46E5',
                        borderStrokeWidth: 2,
                        anchorStroke: '#4F46E5',
                        anchorFill: '#fff',
                        anchorSize: 10,
                    });
                    this.layer.add(this.transformer);

                    // Reload sections
                    this.loadSections();
                },

                handleLayoutUpdated(detail) {
                    // Reload all sections after update
                    this.sections = detail.sections;

                    // Clear and rebuild canvas
                    this.layer.destroyChildren();
                    if (this.seatsLayer) this.seatsLayer.destroyChildren();

                    // Re-add transformer
                    this.transformer = new Konva.Transformer({
                        enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right'],
                        rotateEnabled: true,
                        borderStroke: '#4F46E5',
                        borderStrokeWidth: 2,
                        anchorStroke: '#4F46E5',
                        anchorFill: '#fff',
                        anchorSize: 10,
                    });
                    this.layer.add(this.transformer);

                    // Reload sections
                    this.loadSections();
                },

                addSeatAtPosition(stagePos) {
                    if (!this.selectedSection) return;

                    // Find the selected section data
                    const section = this.sections.find(s => s.id === this.selectedSection);
                    if (!section) return;

                    // Get the section node to calculate relative position
                    const sectionNode = this.stage.findOne(`#section-${this.selectedSection}`);
                    if (!sectionNode) return;

                    // Calculate position relative to section
                    const relativeX = stagePos.x - sectionNode.x();
                    const relativeY = stagePos.y - sectionNode.y();

                    // Check if position is within section bounds
                    if (relativeX < 0 || relativeY < 0 || relativeX > section.width || relativeY > section.height) {
                        alert('Please click inside the selected section to add a seat.');
                        return;
                    }

                    // Count existing seats to generate label
                    const totalSeats = (section.rows || []).reduce((sum, row) => sum + (row.seats || []).length, 0);
                    const seatLabel = (totalSeats + 1).toString();

                    // Prompt for seat details
                    const customLabel = prompt('Enter seat label:', seatLabel);
                    if (!customLabel) return;

                    const rowLabel = prompt('Enter row label:', 'Manual');
                    if (!rowLabel) return;

                    // Call Livewire to save seat
                    @this.call('addSeat', {
                        section_id: this.selectedSection,
                        x: Math.round(relativeX),
                        y: Math.round(relativeY),
                        label: customLabel,
                        row_label: rowLabel,
                        shape: 'circle',
                        angle: 0
                    });
                },

                handleSeatAdded(detail) {
                    const seat = detail.seat;
                    const sectionId = detail.sectionId;

                    // Find section and add seat to it
                    const section = this.sections.find(s => s.id === sectionId);
                    if (section) {
                        // Add seat to section data
                        if (!section.rows) section.rows = [];

                        // Find or create a row for manually placed seats
                        let row = section.rows.find(r => r.label === (seat.row_label || 'Manual'));
                        if (!row) {
                            row = {
                                id: `row-${sectionId}-${seat.row_label || 'manual'}`,
                                label: seat.row_label || 'Manual',
                                seats: []
                            };
                            section.rows.push(row);
                        }
                        row.seats.push(seat);

                        // Draw the seat on canvas
                        const sectionNode = this.stage.findOne(`#section-${sectionId}`);
                        if (sectionNode) {
                            const seatShape = this.createSeat(seat, section.seat_color || section.color_hex, sectionId);
                            sectionNode.add(seatShape);
                            this.layer.batchDraw();
                        }
                    }
                },

                cancelDrawing() {
                    this.polygonPoints = [];
                    this.circleStart = null;
                    this.tempCircle = null;
                    this.drawLayer.destroyChildren();
                    this.drawLayer.batchDraw();
                    this.setDrawMode('select');
                },
            }
        }
    </script>
    @endpush
</x-filament-panels::page>
