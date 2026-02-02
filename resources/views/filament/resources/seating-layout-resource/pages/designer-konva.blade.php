<x-filament-panels::page>
    {{-- Render action modals outside wire:ignore to ensure proper Livewire DOM updates --}}
    <x-filament-actions::modals />

    <div class="space-y-6"
         wire:ignore.self
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
            <div class="flex flex-col items-center justify-between mb-4 gap-y-4">
                <div class="flex flex-row items-center gap-x-4">
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
                    <button @click="toggleGrid" type="button" class="flex items-center gap-2 px-3 py-1 text-sm rounded-md" :class="showGrid ? 'bg-blue-600 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvagrid" class="w-4 h-4 text-gray-100" />
                        Grid
                    </button>
                    <button @click="toggleSnapToGrid" type="button" class="flex items-center gap-2 px-3 py-1 text-sm rounded-md" :class="snapToGrid ? 'bg-blue-600 text-white' : 'bg-gray-100'" title="Snap sections to grid when moving">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5z"></path>
                        </svg>
                        Snap
                    </button>

                    {{-- Background image controls toggle button --}}
                    <div x-show="backgroundUrl" class="">
                        <button @click="showBackgroundControls = !showBackgroundControls" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg"
                            :class="showBackgroundControls ? 'bg-blue-600 text-white' : 'text-gray-100 hover:bg-gray-200'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="h-6 mx-1 border-l border-gray-300"></div>

                    <button @click="setDrawMode('select')" type="button" class="flex items-center gap-2 px-3 py-2 text-sm border rounded-md border-slate-200" :class="drawMode === 'select' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-gray-100'">
                        <svg viewBox="0 0 32 32" style="enable-background:new 0 0 512 512" class="w-5 h-5"><g><path d="M31.371 17.433 10.308 9.008c-.775-.31-1.629.477-1.3 1.3l8.426 21.064c.346.866 1.633.797 1.89-.098l2.654-9.295 9.296-2.656c.895-.255.96-1.544.097-1.89zM4.324 2.91A1 1 0 1 0 2.91 4.326L5.72 7.133c.195.195.45.293.706.293.875 0 1.328-1.088.708-1.707zM13.447 7.422a.998.998 0 0 0 .707-.29l2.809-2.807a1 1 0 1 0-1.414-1.414L12.74 5.719c-.616.616-.157 1.704.707 1.703zM5.719 12.74 2.91 15.548c-.612.612-.154 1.707.707 1.707a.997.997 0 0 0 .707-.293l2.809-2.808a1 1 0 1 0-1.414-1.414zM5.56 9.937a1 1 0 0 0-1-1H1a1 1 0 1 0 0 2h3.56a1 1 0 0 0 1-1zM9.937 5.56a1 1 0 0 0 1-1V1a1 1 0 1 0-2 0v3.56a1 1 0 0 0 1 1z" fill="currentColor" opacity="1" class=""></path></g></svg>
                        Select
                    </button>
                    <button @click="setDrawMode('multiselect')" type="button" class="flex items-center gap-2 px-3 py-2 text-sm border rounded-md border-slate-200" :class="drawMode === 'multiselect' ? 'bg-orange-500 text-white' : 'bg-gray-100'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
                        </svg>
                        Multi-Select
                    </button>
                    <button @click="setDrawMode('selectseats')" type="button" class="flex items-center gap-2 px-3 py-2 text-sm border rounded-md border-slate-200" :class="drawMode === 'selectseats' ? 'bg-pink-500 text-white' : 'bg-gray-100'" title="Select individual seats - drag to select multiple">
                        <svg viewBox="0 0 64 64" style="enable-background:new 0 0 512 512"  class="w-5 h-5"><g><path d="M17.5 36.944a8.257 8.257 0 0 1 7.25-4.316c.111 0 .22.012.33.017v-.343c0-.578-.077-1.132-.229-1.646a6.172 6.172 0 0 0-1.598-2.767 6.214 6.214 0 0 0-4.423-1.838c-.402 0-.78.037-1.124.109a6.227 6.227 0 0 0-5.126 6.141v.667a8.286 8.286 0 0 1 4.92 3.976zM45.5 24.41v-1.288a6.195 6.195 0 0 0-1.827-4.413 6.218 6.218 0 0 0-4.423-1.837 6.257 6.257 0 0 0-6.25 6.25v.945c.111-.004.217-.016.33-.016 2.201 0 4.274.861 5.837 2.423a8.152 8.152 0 0 1 1.417 1.93 8.232 8.232 0 0 1 4.916-3.995zM39.25 32.628c.111 0 .22.012.33.017v-.343c0-.578-.077-1.132-.229-1.646a6.172 6.172 0 0 0-1.598-2.767 6.214 6.214 0 0 0-4.423-1.838c-.402 0-.78.037-1.124.109a6.237 6.237 0 0 0-4.892 4.474 5.89 5.89 0 0 0-.234 1.667v.667A8.286 8.286 0 0 1 32 36.944a8.257 8.257 0 0 1 7.25-4.316zM24.75 34.628a6.257 6.257 0 0 0-6.25 6.25v6.25H31v-6.25a6.257 6.257 0 0 0-6.25-6.25zM60 40.878c0-3.446-2.804-6.25-6.25-6.25s-6.25 2.804-6.25 6.25v6.25H60zM46.5 36.944a8.257 8.257 0 0 1 7.25-4.316c.111 0 .22.012.33.017v-.343a6.199 6.199 0 0 0-1.827-4.413 6.217 6.217 0 0 0-4.423-1.837c-.402 0-.78.037-1.124.109a6.237 6.237 0 0 0-4.892 4.474 5.89 5.89 0 0 0-.234 1.667v.667a8.286 8.286 0 0 1 4.92 3.976zM31 24.41v-1.288a6.195 6.195 0 0 0-1.827-4.413 6.218 6.218 0 0 0-4.423-1.837 6.257 6.257 0 0 0-6.25 6.25v.945c.111-.004.217-.016.33-.016 2.201 0 4.274.861 5.837 2.423a8.152 8.152 0 0 1 1.417 1.93A8.232 8.232 0 0 1 31 24.409zM45.5 40.878c0-3.446-2.804-6.25-6.25-6.25S33 37.432 33 40.878v6.25h12.5zM16.5 40.878c0-3.446-2.804-6.25-6.25-6.25S4 37.432 4 40.878v6.25h12.5z" fill="currentColor" opacity="1"  class=""></path></g></svg>
                        Select Seats
                    </button>
                    <button @click="setDrawMode('polygon')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'polygon' ? 'bg-green-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvapolygon" class="w-4 h-4 text-purple-600" />
                        Polygon
                    </button>
                    <button @click="setDrawMode('circle')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'circle' ? 'bg-green-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvacircle" class="w-4 h-4 text-purple-600" />
                        Circle
                    </button>
                    <button @click="setDrawMode('seat')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'seat' ? 'bg-purple-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvaseats" class="w-5 h-5 text-purple-600" />
                        Add Seats
                    </button>
                    
                    <div x-show="drawMode === 'seat'" x-transition class="flex items-center gap-2 px-2 py-1 ml-1 border border-purple-200 rounded-md bg-purple-50">
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
                    <button @click="cancelDrawing" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-white bg-gray-600 border rounded-md border-slate-200" x-show="drawMode !== 'select' && drawMode !== 'multiselect' && drawMode !== 'selectseats'">
                        <x-svg-icon name="konvacancel" class="w-5 h-5 text-purple-600" />
                        Cancel
                    </button>

                    <div class="h-6 mx-1 border-l border-gray-300"></div>

                    <button @click="deleteSelected" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-white bg-red-700 rounded-md hover:bg-red-800" x-show="selectedSection">
                        <x-svg-icon name="konvadelete" class="w-5 h-5 text-white" />
                        Delete
                    </button>

                    <button @click="showExportModal = true" type="button" class="flex items-center gap-2 px-3 py-1 text-sm bg-gray-100 rounded-md hover:bg-gray-200" title="Export layout">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Seats selection toolbar --}}
            <div x-show="selectedSeats.length > 0" x-transition class="flex items-center gap-4 p-3 mb-4 border border-orange-200 rounded-lg bg-orange-50">
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
                    <input type="text" x-model="assignToRowLabel" placeholder="Row label (e.g., A, 1)" class="w-32 text-sm text-gray-900 placeholder-gray-400 bg-white border-gray-300 rounded-md">
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
            <div x-show="selectedRows.length > 0" x-transition class="flex items-center gap-4 p-3 mb-4 border border-blue-200 rounded-lg bg-blue-50">
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

            {{-- Background image controls (collapsible) --}}
            <div x-show="backgroundUrl && showBackgroundControls" x-transition class="flex flex-wrap items-center gap-4 p-3 mb-4 border border-indigo-200 rounded-lg bg-indigo-50">
                <div class="flex items-center gap-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="backgroundVisible" @change="toggleBackgroundVisibility()" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                        <span class="text-sm font-medium text-indigo-800">Show Background</span>
                    </label>
                </div>
                <div class="h-6 mx-1 border-l border-indigo-300"></div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">Scale:</label>
                    <input type="range" x-model="backgroundScale" min="0.1" max="3" step="0.01" @input="updateBackgroundScale()" class="w-20" :disabled="!backgroundVisible">
                    <input type="number" x-model="backgroundScale" min="0.1" max="3" step="0.01" @input="updateBackgroundScale()" class="w-16 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded" :disabled="!backgroundVisible">
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">X:</label>
                    <input type="range" x-model="backgroundX" min="-1000" max="1000" step="1" @input="updateBackgroundPosition()" class="w-20" :disabled="!backgroundVisible">
                    <input type="number" x-model="backgroundX" step="1" @input="updateBackgroundPosition()" class="w-16 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded" :disabled="!backgroundVisible">
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">Y:</label>
                    <input type="range" x-model="backgroundY" min="-1000" max="1000" step="1" @input="updateBackgroundPosition()" class="w-20" :disabled="!backgroundVisible">
                    <input type="number" x-model="backgroundY" step="1" @input="updateBackgroundPosition()" class="w-16 px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded" :disabled="!backgroundVisible">
                </div>
                <div class="flex items-center gap-1">
                    <label class="text-xs text-indigo-700">Opacity:</label>
                    <input type="range" x-model="backgroundOpacity" min="0" max="1" step="0.01" @input="updateBackgroundOpacity()" class="w-16" :disabled="!backgroundVisible">
                    <input type="number" x-model="backgroundOpacity" min="0" max="1" step="0.01" @input="updateBackgroundOpacity()" class="px-1 text-xs text-gray-900 bg-white border border-gray-300 rounded w-14" :disabled="!backgroundVisible">
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

        {{-- Export Modal --}}
        <div x-show="showExportModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" @click.self="showExportModal = false" @keydown.escape.window="showExportModal = false">
            <div x-show="showExportModal" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="w-full max-w-md p-6 bg-white rounded-lg shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Export Layout</h3>
                    <button @click="showExportModal = false" type="button" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <button @click="exportSVG(); showExportModal = false" type="button" class="flex flex-col items-center gap-3 p-6 transition border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 group">
                        <svg class="w-12 h-12 text-gray-400 transition group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 group-hover:text-blue-700">Export SVG</span>
                        <span class="text-xs text-gray-500">Vector image format</span>
                    </button>
                    <button @click="exportJSON(); showExportModal = false" type="button" class="flex flex-col items-center gap-3 p-6 transition border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 group">
                        <svg class="w-12 h-12 text-gray-400 transition group-hover:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 group-hover:text-green-700">Export JSON</span>
                        <span class="text-xs text-gray-500">Backup data format</span>
                    </button>
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
                                    @if($section['section_type'] === 'icon')
                                        {{-- Icon display - use a simple marker since icons are full SVG strings --}}
                                        <div class="flex items-center justify-center w-6 h-6 rounded" style="background-color: {{ $section['background_color'] ?? '#3B82F6' }}">
                                            <svg class="w-4 h-4" fill="{{ $section['metadata']['icon_color'] ?? '#FFFFFF' }}" viewBox="0 0 24 24">
                                                {{-- Generic map pin icon for sidebar display --}}
                                                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                            </svg>
                                        </div>
                                    @else
                                        <div class="flex gap-1">
                                            <div class="w-4 h-4 border rounded" style="background-color: {{ $section['color_hex'] ?? '#3B82F6' }}" title="Section color"></div>
                                            <div class="w-4 h-4 border rounded" style="background-color: {{ $section['seat_color'] ?? '#22C55E' }}" title="Seat color"></div>
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-medium">
                                            @if($section['section_type'] === 'icon')
                                                <span class="text-xs text-blue-600 uppercase">Icon:</span>
                                            @endif
                                            {{ $section['name'] }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            @if($section['section_type'] === 'icon')
                                                {{ $iconDefinitions[$section['metadata']['icon_key'] ?? 'info_point']['label'] ?? 'Icon' }}
                                            @else
                                                {{ count($section['rows'] ?? []) }} rows •
                                                {{ collect($section['rows'] ?? [])->sum(fn($row) => count($row['seats'] ?? [])) }} seats
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="text-xs text-gray-400">
                                        ({{ $section['x_position'] }}, {{ $section['y_position'] }})
                                        @if($section['section_type'] !== 'icon')
                                            • {{ $section['width'] }}x{{ $section['height'] }}
                                        @endif
                                    </div>
                                    @if($section['section_type'] !== 'icon')
                                        <button @click.stop="editSectionColors({{ $section['id'] }}, '{{ $section['color_hex'] ?? '#3B82F6' }}', '{{ $section['seat_color'] ?? '#22C55E' }}')"
                                                class="px-2 py-1 text-xs bg-gray-100 rounded hover:bg-gray-200">
                                            Edit Colors
                                        </button>
                                    @endif
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
                                                class="flex items-center flex-1 gap-2 text-left">
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

        {{-- Section Context Menu --}}
        <div x-show="showContextMenu"
             x-transition:enter="transition ease-out duration-100"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-75"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             :style="`position: fixed; left: ${contextMenuX}px; top: ${contextMenuY}px; z-index: 100;`"
             @click.away="showContextMenu = false"
             class="w-48 bg-white border border-gray-200 rounded-lg shadow-xl">
            <div class="py-1">
                <button @click="openEditSectionModal()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-gray-200 hover:bg-gray-100 hover:text-gray-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Section
                </button>
                <template x-if="contextMenuSectionType === 'standard'">
                    <div>
                        <button @click="selectRowsBySectionFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-blue-700 hover:bg-blue-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            Select Rows
                        </button>
                        <button @click="editColorsFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-gray-200 hover:bg-gray-100 hover:text-gray-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                            </svg>
                            Edit Colors
                        </button>
                        <button @click="recalcRowsFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-orange-700 hover:bg-orange-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Recalc Rows
                        </button>
                    </div>
                </template>
                <div class="border-t border-gray-200"></div>
                <button @click="deleteFromMenu()" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-red-600 hover:bg-red-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Section
                </button>
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
                iconDefinitions: @json($iconDefinitions ?? []),
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
                selectedRowForDrag: null, // Track row being dragged with CTRL
                rowDragStartPos: null, // Start position for row drag
                selectionRect: null,
                selectionStartPos: null,
                assignToSectionId: '',
                assignToRowLabel: '',

                // Color edit modal
                showColorModal: false,
                editSectionId: null,
                editColorHex: '#3B82F6',
                editSeatColor: '#22C55E',

                // Context menu
                showContextMenu: false,
                contextMenuX: 0,
                contextMenuY: 0,
                contextMenuSectionId: null,
                contextMenuSectionType: 'standard',

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

                // Section drag tracking (for moving seats with section)
                sectionDragStartPos: null,
                draggingSectionId: null,

                // Rectangle selection for seats
                rectSelectionBox: null,
                rectSelectionStart: null,
                isRectSelecting: false,

                // Background controls toggle
                showBackgroundControls: false,
                backgroundVisible: true,

                // Export modal
                showExportModal: false,

                init() {
                    this.createStage();
                    this.loadSections();
                    this.createTooltip();
                },

                // Wrapper methods to access Livewire from Konva handlers
                // These use @this which is Blade syntax that compiles to the component reference

                // Set selectedSection locally without triggering a Livewire network request
                // The third parameter 'false' prevents the network round-trip that causes deselection
                setLivewireSelectedSection(sectionId) {
                    @this.set('selectedSection', sectionId, false);
                },

                // Sync selectedSection to server (when we actually need it on server, like before modal opens)
                syncSelectedSectionToServer() {
                    if (this.selectedSection) {
                        @this.call('setSelectedSection', this.selectedSection);
                    }
                },

                async mountLivewireAction(actionName) {
                    // Sync selectedSection to server before mounting action (for Edit Section modal)
                    // Must await set() to ensure server has the value before mounting the action
                    if (this.selectedSection) {
                        await @this.set('selectedSection', this.selectedSection);
                    }
                    await @this.mountAction(actionName);
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

                // Select all seats (seats are now children of section groups in the main layer)
                selectAllSeats() {
                    this.clearSelection();
                    this.layer.find('.seat').forEach(seat => {
                        const seatId = seat.getAttr('seatId');
                        if (seatId) {
                            this.selectedSeats.push({ id: seatId, node: seat });
                            seat.stroke('#F97316');
                            seat.strokeWidth(3);
                        }
                    });
                    this.layer.batchDraw();
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
                        enabledAnchors: ['top-left', 'top-center', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right'],
                        rotateEnabled: true,
                        keepRatio: false,
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
                        } else if (this.drawMode === 'selectseats') {
                            // Handle seat selection click
                            this.handleSeatSelectClick(e);
                        } else if (this.drawMode === 'select') {
                            if (e.target === this.stage || e.target.getLayer() === this.backgroundLayer) {
                                this.transformer.nodes([]);
                                this.selectedSection = null;
                                this.hideAllCurveHandles();
                                this.clearSelection();
                            }
                        }
                    });

                    // Mouse down for box selection
                    this.stage.on('mousedown', (e) => {
                        // Ctrl+drag for rectangle selection in any mode
                        if (e.evt.ctrlKey && (e.target === this.stage || e.target.getLayer() === this.backgroundLayer || e.target.getLayer() === this.layer)) {
                            this.startRectSelection(e);
                            return;
                        }

                        if (this.drawMode === 'multiselect' && (e.target === this.stage || e.target.getLayer() === this.backgroundLayer)) {
                            this.startBoxSelection(e);
                        }
                        // Rectangle selection for seats (without Ctrl, in selectseats mode)
                        if (this.drawMode === 'selectseats' && (e.target === this.stage || e.target.getLayer() === this.backgroundLayer || e.target.getLayer() === this.layer)) {
                            this.startRectSelection(e);
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

                        // Rectangle selection for seats (works with Ctrl+drag in any mode)
                        if (this.isRectSelecting) {
                            this.updateRectSelection(e);
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

                        // End rectangle selection for seats (works with Ctrl+drag in any mode)
                        if (this.isRectSelecting) {
                            this.endRectSelection(e);
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

                // Seat selection mode - click handler
                handleSeatSelectClick(e) {
                    const target = e.target;

                    // Check if clicked on a seat
                    if (target.hasName && target.hasName('seat')) {
                        const seatId = target.getAttr('seatId');
                        if (seatId) {
                            const isShift = e.evt.shiftKey;

                            if (isShift) {
                                // Toggle selection with shift
                                const index = this.selectedSeats.findIndex(s => s.id === seatId);
                                if (index > -1) {
                                    this.selectedSeats.splice(index, 1);
                                    target.stroke('#1F2937');
                                    target.strokeWidth(1);
                                } else {
                                    this.selectedSeats.push({ id: seatId, node: target });
                                    target.stroke('#EC4899');
                                    target.strokeWidth(3);
                                }
                            } else {
                                // Single select - clear previous and select this one
                                this.clearSelection();
                                this.selectedSeats.push({ id: seatId, node: target });
                                target.stroke('#EC4899');
                                target.strokeWidth(3);
                            }
                            this.seatsLayer.batchDraw();
                        }
                    } else if (e.target === this.stage || e.target.getLayer() === this.backgroundLayer) {
                        // Clicked on empty space - clear selection
                        this.clearSelection();
                    }
                },

                // Rectangle selection for seats
                startRectSelection(e) {
                    const pos = this.stage.getPointerPosition();
                    this.rectSelectionStart = {
                        x: (pos.x - this.stage.x()) / this.zoom,
                        y: (pos.y - this.stage.y()) / this.zoom
                    };
                    this.isRectSelecting = true;

                    // Create selection rectangle with pink color for seat selection
                    this.rectSelectionBox = new Konva.Rect({
                        x: this.rectSelectionStart.x,
                        y: this.rectSelectionStart.y,
                        width: 0,
                        height: 0,
                        fill: 'rgba(236, 72, 153, 0.2)',
                        stroke: '#EC4899',
                        strokeWidth: 2,
                        dash: [5, 5],
                    });
                    this.drawLayer.add(this.rectSelectionBox);
                },

                updateRectSelection(e) {
                    if (!this.rectSelectionBox || !this.rectSelectionStart) return;

                    const pos = this.stage.getPointerPosition();
                    const currentPos = {
                        x: (pos.x - this.stage.x()) / this.zoom,
                        y: (pos.y - this.stage.y()) / this.zoom
                    };

                    const x = Math.min(this.rectSelectionStart.x, currentPos.x);
                    const y = Math.min(this.rectSelectionStart.y, currentPos.y);
                    const width = Math.abs(currentPos.x - this.rectSelectionStart.x);
                    const height = Math.abs(currentPos.y - this.rectSelectionStart.y);

                    this.rectSelectionBox.x(x);
                    this.rectSelectionBox.y(y);
                    this.rectSelectionBox.width(width);
                    this.rectSelectionBox.height(height);
                    this.drawLayer.batchDraw();
                },

                endRectSelection(e) {
                    this.isRectSelecting = false;

                    if (!this.rectSelectionBox) {
                        this.rectSelectionStart = null;
                        return;
                    }

                    const box = this.rectSelectionBox.getClientRect();

                    // Don't clear existing selection if shift is held
                    const isShift = e.evt.shiftKey;
                    if (!isShift) {
                        this.clearSelection();
                    }

                    // Find all seats within selection (seats are children of section groups)
                    this.layer.find('.seat').forEach(seat => {
                        const seatBox = seat.getClientRect();

                        // Check if seat is within selection box
                        if (this.intersects(box, seatBox)) {
                            const seatId = seat.getAttr('seatId');
                            if (seatId && !this.selectedSeats.find(s => s.id === seatId)) {
                                this.selectedSeats.push({ id: seatId, node: seat });
                                seat.stroke('#EC4899');
                                seat.strokeWidth(3);
                            }
                        }
                    });

                    // Clean up
                    this.rectSelectionBox.destroy();
                    this.rectSelectionBox = null;
                    this.rectSelectionStart = null;
                    this.drawLayer.batchDraw();
                    this.layer.batchDraw();
                },

                clearSelection() {
                    this.selectedSeats.forEach(seat => {
                        if (seat.node) {
                            seat.node.stroke('#1F2937');
                            seat.node.strokeWidth(1);
                            seat.node.draggable(false);
                        }
                    });
                    this.selectedSeats = [];
                    // Also clear row drag selection
                    this.clearRowDragSelection();
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
                    // Highlight seats belonging to selected rows (seats are children of section groups)
                    this.layer.find('.seat').forEach(seat => {
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
                    this.layer.batchDraw();
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

                // CTRL+click to select entire row for dragging
                selectEntireRow(sectionId, rowId) {
                    // Clear previous seat selection
                    this.clearSelection();

                    // Find the section group
                    const sectionGroup = this.stage.findOne(`#section-${sectionId}`);
                    if (!sectionGroup) return;

                    // Find all seats belonging to this row
                    const rowSeats = sectionGroup.find('.seat').filter(seat => {
                        return seat.getAttr('rowId') === rowId;
                    });

                    if (rowSeats.length === 0) return;

                    // Track the selected row for drag
                    this.selectedRowForDrag = {
                        sectionId: sectionId,
                        rowId: rowId,
                        seats: rowSeats,
                        startPositions: rowSeats.map(seat => ({
                            node: seat,
                            x: seat.x(),
                            y: seat.y()
                        }))
                    };

                    // Highlight all seats in row and add to selectedSeats
                    rowSeats.forEach(seat => {
                        seat.stroke('#3B82F6'); // Blue for row selection
                        seat.strokeWidth(3);
                        seat.draggable(true); // Enable drag
                        this.selectedSeats.push({
                            id: seat.getAttr('seatId'),
                            node: seat,
                            rowId: rowId
                        });
                    });

                    // Set up drag handlers for the first seat (leader)
                    const leaderSeat = rowSeats[0];
                    if (leaderSeat) {
                        // Track drag start
                        leaderSeat.on('dragstart.rowdrag', () => {
                            this.rowDragStartPos = { x: leaderSeat.x(), y: leaderSeat.y() };
                        });

                        // Move all seats together
                        leaderSeat.on('dragmove.rowdrag', () => {
                            if (!this.rowDragStartPos || !this.selectedRowForDrag) return;

                            const dx = leaderSeat.x() - this.rowDragStartPos.x;
                            const dy = leaderSeat.y() - this.rowDragStartPos.y;

                            // Move all other seats by the same delta
                            this.selectedRowForDrag.startPositions.forEach((pos, index) => {
                                if (index > 0) { // Skip leader (already moved by Konva)
                                    pos.node.x(pos.x + dx);
                                    pos.node.y(pos.y + dy);
                                }
                            });
                            this.layer.batchDraw();
                        });

                        // Save on drag end
                        leaderSeat.on('dragend.rowdrag', () => {
                            if (!this.rowDragStartPos || !this.selectedRowForDrag) return;

                            const dx = leaderSeat.x() - this.selectedRowForDrag.startPositions[0].x;
                            const dy = leaderSeat.y() - this.selectedRowForDrag.startPositions[0].y;

                            // Update start positions for next drag
                            this.selectedRowForDrag.startPositions.forEach(pos => {
                                pos.x = pos.node.x();
                                pos.y = pos.node.y();
                            });

                            // Save the row movement to backend
                            this.saveRowMovement(sectionId, rowId, dx, dy);

                            this.rowDragStartPos = null;
                        });
                    }

                    console.log(`Selected row ${rowId} with ${rowSeats.length} seats for drag`);
                },

                // Clear row drag selection
                clearRowDragSelection() {
                    if (this.selectedRowForDrag) {
                        this.selectedRowForDrag.seats.forEach(seat => {
                            seat.off('.rowdrag');
                            seat.draggable(false);
                        });
                        this.selectedRowForDrag = null;
                    }
                    this.rowDragStartPos = null;
                },

                // Save row movement to backend
                saveRowMovement(sectionId, rowId, dx, dy) {
                    console.log('Saving row movement', { sectionId, rowId, dx, dy });
                    @this.call('moveRow', sectionId, rowId, dx, dy)
                        .then(() => console.log('Row movement saved'))
                        .catch(err => console.error('Failed to save row movement:', err));
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

                // Context menu methods
                showSectionContextMenu(sectionId, x, y) {
                    const section = this.sections.find(s => s.id === sectionId);
                    this.contextMenuSectionId = sectionId;
                    this.contextMenuSectionType = section?.section_type || 'standard';
                    this.contextMenuX = x;
                    this.contextMenuY = y;
                    this.showContextMenu = true;
                },

                hideContextMenu() {
                    this.showContextMenu = false;
                    this.contextMenuSectionId = null;
                },

                async openEditSectionModal() {
                    if (this.contextMenuSectionId) {
                        // Update Alpine variable - mountLivewireAction will sync to server
                        this.selectedSection = this.contextMenuSectionId;
                        await this.mountLivewireAction('editSection');
                    }
                    this.hideContextMenu();
                },

                selectRowsBySectionFromMenu() {
                    if (this.contextMenuSectionId) {
                        this.selectRowsBySection(this.contextMenuSectionId);
                    }
                    this.hideContextMenu();
                },

                editColorsFromMenu() {
                    if (this.contextMenuSectionId) {
                        const section = this.sections.find(s => s.id === this.contextMenuSectionId);
                        if (section) {
                            this.editSectionColors(
                                this.contextMenuSectionId,
                                section.color_hex || '#3B82F6',
                                section.seat_color || '#22C55E'
                            );
                        }
                    }
                    this.hideContextMenu();
                },

                recalcRowsFromMenu() {
                    if (this.contextMenuSectionId) {
                        this.recalculateRows(this.contextMenuSectionId);
                    }
                    this.hideContextMenu();
                },

                deleteFromMenu() {
                    if (this.contextMenuSectionId) {
                        this.selectedSection = this.contextMenuSectionId;
                        this.deleteSelected();
                    }
                    this.hideContextMenu();
                },

                // Export SVG
                exportSVG() {
                    // Create SVG content (avoid PHP parsing by splitting xml)
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
                    // Handle icon type sections specially
                    if (section.section_type === 'icon') {
                        this.createIconSection(section);
                        return;
                    }

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

                    // Label is hidden - only visible in tooltip
                    const label = new Konva.Text({
                        text: `${section.section_code || ''} - ${section.name}`,
                        fontSize: 14,
                        fontFamily: 'Arial',
                        fill: '#1F2937',
                        padding: 8,
                        align: 'center',
                        width: section.width || 200,
                        visible: false, // Hidden - name shown in tooltip only
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

                    // Draw seats INSIDE the section group with local coordinates (skip for decorative zones)
                    // This way seats automatically rotate with the section
                    if (!isDecorativeZone && section.rows && section.rows.length > 0) {
                        // Get seat size and shape from section metadata
                        const metadata = section.metadata || {};
                        const sectionSeatSize = metadata.seat_size || 10;
                        const sectionSeatShape = metadata.seat_shape || 'circle';
                        const curveAmount = parseFloat(metadata.curve_amount || 0);
                        const sectionWidth = section.width || 200;

                        section.rows.forEach(row => {
                            if (row.seats && row.seats.length > 0) {
                                row.seats.forEach(seat => {
                                    // Calculate curve offset for this seat
                                    // Parabolic curve: center seats get max offset, edges get 0
                                    let curveOffset = 0;
                                    if (curveAmount !== 0) {
                                        const seatX = parseFloat(seat.x || 0);
                                        const xNormalized = seatX / sectionWidth; // 0 to 1
                                        // Parabola: max at center (0.5), zero at edges (0, 1)
                                        curveOffset = curveAmount * (1 - 4 * Math.pow(xNormalized - 0.5, 2));
                                    }

                                    // Create seat with local coordinates (relative to section origin)
                                    // Use section's configured seat size and shape
                                    // Pass row info for CTRL+drag row selection
                                    const seatShape = this.createSeat(seat, seatColor, section.id, sectionSeatSize, sectionSeatShape, row, curveOffset);
                                    group.add(seatShape);
                                });
                            }
                        });
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

                    // Add curve handle (diamond shape at top center, draggable vertically)
                    // Only for sections with seats (not decorative zones)
                    if (!isDecorativeZone && section.rows && section.rows.length > 0) {
                        const sectionWidth = section.width || 200;
                        const initialCurve = parseFloat(metadata.curve_amount || 0);

                        const curveHandle = new Konva.RegularPolygon({
                            x: sectionWidth / 2,
                            y: -20 - initialCurve, // Position above section, offset by current curve
                            sides: 4, // Diamond shape
                            radius: 10,
                            fill: '#8B5CF6',
                            stroke: '#6D28D9',
                            strokeWidth: 2,
                            rotation: 0,
                            draggable: true,
                            visible: false, // Only visible when section selected
                            name: 'curveHandle',
                            cursor: 'ns-resize',
                        });

                        // Store initial Y for calculating delta
                        let curveHandleStartY = curveHandle.y();
                        let currentCurveAmount = initialCurve;

                        curveHandle.on('dragstart', () => {
                            curveHandleStartY = curveHandle.y();
                        });

                        curveHandle.on('dragmove', () => {
                            // Constrain to vertical movement only
                            curveHandle.x(sectionWidth / 2);

                            // Calculate new curve amount from drag delta
                            // Moving up (negative Y) = positive curve, moving down = negative curve
                            const deltaY = curveHandleStartY - curveHandle.y();
                            currentCurveAmount = initialCurve + deltaY;

                            // Clamp curve amount to reasonable range
                            currentCurveAmount = Math.max(-100, Math.min(100, currentCurveAmount));

                            // Update seats in real-time with new curve
                            this.updateSectionCurveVisual(group, section, currentCurveAmount);
                        });

                        curveHandle.on('dragend', () => {
                            // Save the curve amount to backend
                            this.saveSectionCurve(section.id, Math.round(currentCurveAmount));

                            // Update the section's metadata locally
                            const sectionIndex = this.sections.findIndex(s => s.id === section.id);
                            if (sectionIndex !== -1) {
                                if (!this.sections[sectionIndex].metadata) {
                                    this.sections[sectionIndex].metadata = {};
                                }
                                this.sections[sectionIndex].metadata.curve_amount = currentCurveAmount;
                            }
                        });

                        group.add(curveHandle);
                    }

                    // Click to select
                    group.on('click', (e) => {
                        // Stop propagation to prevent stage click handler from clearing selection
                        e.cancelBubble = true;

                        if (this.drawMode === 'multiselect') {
                            // Toggle section highlight in multi-select mode
                            const bb = group.findOne('.boundingBox');
                            if (bb) {
                                bb.visible(!bb.visible());
                                this.layer.batchDraw();
                            }
                            // Also update Livewire selectedSection for Edit Section modal (deferred to avoid re-render)
                            if (bb && bb.visible()) {
                                this.setLivewireSelectedSection( section.id);
                            }
                        } else {
                            // Hide all curve handles first
                            this.hideAllCurveHandles();
                            // Select this section
                            this.transformer.nodes([group]);
                            this.selectedSection = section.id;
                            // Show curve handle for this section
                            const ch = group.findOne('.curveHandle');
                            if (ch) ch.visible(true);
                            this.layer.batchDraw();
                            // Defer Livewire sync to avoid re-render issues - only needed for Edit Section modal
                            this.setLivewireSelectedSection( section.id);
                        }
                    });

                    // Right-click context menu
                    group.on('contextmenu', (e) => {
                        e.evt.preventDefault();
                        e.cancelBubble = true;
                        // Select this section
                        this.transformer.nodes([group]);
                        this.selectedSection = section.id;
                        this.setLivewireSelectedSection( section.id);
                        this.layer.batchDraw();
                        // Show context menu at mouse position
                        this.showSectionContextMenu(section.id, e.evt.clientX, e.evt.clientY);
                    });

                    // Simple drag end handler - seats are children of group, move automatically
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

                    // Simplified transform end handler - seats are children and rotate automatically
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

                // Create icon section (map markers like exit, toilet, info point, etc.)
                createIconSection(section) {
                    const metadata = section.metadata || {};
                    const iconKey = metadata.icon_key || 'info_point';
                    const iconColor = metadata.icon_color || '#FFFFFF';
                    const iconSize = metadata.icon_size || 48;
                    const bgColor = section.background_color || '#3B82F6';
                    const cornerRadius = section.corner_radius || 8;

                    // Get icon SVG from definitions
                    const iconDef = this.iconDefinitions[iconKey];
                    const svgData = iconDef ? iconDef.svg : 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z';

                    const group = new Konva.Group({
                        x: section.x_position || 100,
                        y: section.y_position || 100,
                        rotation: section.rotation || 0,
                        draggable: true,
                        id: `section-${section.id}`,
                        sectionData: section,
                    });

                    // Background rectangle with rounded corners
                    const background = new Konva.Rect({
                        x: 0,
                        y: 0,
                        width: iconSize,
                        height: iconSize,
                        fill: bgColor,
                        cornerRadius: cornerRadius,
                        shadowColor: 'black',
                        shadowBlur: 4,
                        shadowOffset: { x: 2, y: 2 },
                        shadowOpacity: 0.3,
                    });
                    group.add(background);

                    // Icon rendering - supports both path data and full SVG strings
                    const iconPadding = iconSize * 0.15;
                    const iconInnerSize = iconSize - (iconPadding * 2);

                    // Check if it's a full SVG string
                    if (svgData.trim().startsWith('<svg')) {
                        // Parse full SVG - extract viewBox and path data
                        const parser = new DOMParser();
                        const svgDoc = parser.parseFromString(svgData, 'image/svg+xml');
                        const svgElement = svgDoc.querySelector('svg');

                        // Get viewBox for scaling (default to 24x24 if not found)
                        let viewBoxWidth = 24, viewBoxHeight = 24;
                        const viewBox = svgElement?.getAttribute('viewBox');
                        if (viewBox) {
                            const parts = viewBox.split(/[\s,]+/).map(Number);
                            if (parts.length >= 4) {
                                viewBoxWidth = parts[2] || 24;
                                viewBoxHeight = parts[3] || 24;
                            }
                        } else {
                            // Try width/height attributes
                            viewBoxWidth = parseFloat(svgElement?.getAttribute('width')) || 24;
                            viewBoxHeight = parseFloat(svgElement?.getAttribute('height')) || 24;
                        }

                        // Extract all path elements
                        const paths = svgDoc.querySelectorAll('path');
                        const scaleX = iconInnerSize / viewBoxWidth;
                        const scaleY = iconInnerSize / viewBoxHeight;

                        paths.forEach(pathEl => {
                            const d = pathEl.getAttribute('d');
                            if (d) {
                                const iconPath = new Konva.Path({
                                    x: iconPadding,
                                    y: iconPadding,
                                    data: d,
                                    fill: iconColor,
                                    scaleX: scaleX,
                                    scaleY: scaleY,
                                });
                                group.add(iconPath);
                            }
                        });
                    } else {
                        // Simple path data string (assumes 24x24 viewBox)
                        const iconPath = new Konva.Path({
                            x: iconPadding,
                            y: iconPadding,
                            data: svgData,
                            fill: iconColor,
                            scaleX: iconInnerSize / 24,
                            scaleY: iconInnerSize / 24,
                        });
                        group.add(iconPath);
                    }

                    // Label below the icon
                    const label = new Konva.Text({
                        x: 0,
                        y: iconSize + 4,
                        text: section.name || '',
                        fontSize: 11,
                        fontFamily: 'Arial',
                        fontStyle: 'bold',
                        fill: '#1F2937',
                        width: iconSize + 40,
                        align: 'center',
                        offsetX: 20,
                    });
                    group.add(label);

                    // Selection bounding box
                    const boundingBox = new Konva.Rect({
                        x: -4,
                        y: -4,
                        width: iconSize + 8,
                        height: iconSize + 28,
                        stroke: '#F97316',
                        strokeWidth: 2,
                        dash: [5, 3],
                        visible: false,
                        name: 'boundingBox',
                    });
                    group.add(boundingBox);

                    // Click handler
                    group.on('click', (e) => {
                        // Stop propagation to prevent stage click handler from clearing selection
                        e.cancelBubble = true;

                        if (this.drawMode === 'multiselect') {
                            const bb = group.findOne('.boundingBox');
                            if (bb) {
                                bb.visible(!bb.visible());
                                this.layer.batchDraw();
                            }
                            if (bb && bb.visible()) {
                                this.setLivewireSelectedSection( section.id);
                            }
                        } else {
                            this.transformer.nodes([group]);
                            this.selectedSection = section.id;
                            this.setLivewireSelectedSection( section.id);
                            this.layer.batchDraw();
                        }
                    });

                    // Right-click context menu for decorative zones
                    group.on('contextmenu', (e) => {
                        e.evt.preventDefault();
                        e.cancelBubble = true;
                        // Select this section
                        this.transformer.nodes([group]);
                        this.selectedSection = section.id;
                        this.setLivewireSelectedSection( section.id);
                        this.layer.batchDraw();
                        // Show context menu at mouse position
                        this.showSectionContextMenu(section.id, e.evt.clientX, e.evt.clientY);
                    });

                    // Drag handlers
                    group.on('dragend', () => {
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

                    this.layer.add(group);
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
                // Now accepts optional seatSize, seatShape, row and curveOffset from section metadata
                createSeat(seat, seatColor, sectionId, sectionSeatSize = null, sectionSeatShape = null, row = null, curveOffset = 0) {
                    const x = parseFloat(seat.x || 0);
                    const baseY = parseFloat(seat.y || 0); // Original Y without curve
                    // Apply curve offset to Y position (negative = curve up, positive = curve down)
                    const y = baseY + curveOffset;
                    const angle = parseFloat(seat.angle || 0);
                    // Use section's configured values if provided, otherwise fall back to defaults
                    const shape = sectionSeatShape || seat.shape || 'circle';
                    const seatSize = sectionSeatSize || this.seatSize || 8;
                    const rowId = row ? row.id : null;
                    const rowLabel = row ? row.label : null;

                    // Store original seat data for curve recalculation
                    const seatData = { x: x, y: baseY };

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
                            rowId: rowId,
                            rowLabel: rowLabel,
                            seatData: seatData,
                            draggable: false, // Enable for row drag
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
                            rowId: rowId,
                            rowLabel: rowLabel,
                            seatData: seatData,
                            draggable: false,
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
                            rowId: rowId,
                            rowLabel: rowLabel,
                            seatData: seatData,
                            draggable: false,
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

                    // Click to select individual seat or entire row (CTRL+click)
                    seatShape.on('click', (e) => {
                        e.cancelBubble = true; // Stop propagation to section

                        // CTRL+click to select entire row
                        if (e.evt.ctrlKey && rowId) {
                            this.selectEntireRow(sectionId, rowId);
                            this.layer.batchDraw();
                            return;
                        }

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

                    // Update local sections array immediately to stay in sync
                    const sectionIndex = this.sections.findIndex(s => s.id === sectionId);
                    if (sectionIndex !== -1) {
                        if (updates.x_position !== undefined) this.sections[sectionIndex].x_position = updates.x_position;
                        if (updates.y_position !== undefined) this.sections[sectionIndex].y_position = updates.y_position;
                        if (updates.width !== undefined) this.sections[sectionIndex].width = updates.width;
                        if (updates.height !== undefined) this.sections[sectionIndex].height = updates.height;
                        if (updates.rotation !== undefined) this.sections[sectionIndex].rotation = updates.rotation;
                    }

                    @this.call('updateSection', sectionId, updates)
                        .then(() => console.log('Section saved successfully'))
                        .catch(err => console.error('Failed to save section:', err));
                },

                // Update section curve visually (real-time as handle is dragged)
                updateSectionCurveVisual(group, section, curveAmount) {
                    const sectionWidth = section.width || 200;
                    const metadata = section.metadata || {};
                    const sectionSeatSize = metadata.seat_size || 10;

                    // Find all seats in this group and update their Y position
                    group.find('.seat').forEach(seatNode => {
                        const seatData = seatNode.getAttr('seatData');
                        if (seatData) {
                            const baseY = parseFloat(seatData.y || 0);
                            const seatX = parseFloat(seatData.x || 0);

                            // Calculate new curve offset
                            let curveOffset = 0;
                            if (curveAmount !== 0) {
                                const xNormalized = seatX / sectionWidth;
                                curveOffset = curveAmount * (1 - 4 * Math.pow(xNormalized - 0.5, 2));
                            }

                            // Update seat Y position
                            // For circles, y() is center; for rects, y() is top-left, so we need to adjust
                            if (seatNode.getClassName() === 'Circle') {
                                seatNode.y(baseY + curveOffset);
                            } else {
                                // Rect/Stadium: position is top-left corner, so offset by half size
                                seatNode.y(baseY + curveOffset - sectionSeatSize / 2);
                            }
                        }
                    });

                    this.layer.batchDraw();
                },

                // Save section curve amount to backend
                saveSectionCurve(sectionId, curveAmount) {
                    console.log('Saving section curve', sectionId, curveAmount);
                    @this.call('updateSectionCurve', sectionId, curveAmount)
                        .then(() => console.log('Section curve saved successfully'))
                        .catch(err => console.error('Failed to save section curve:', err));
                },

                // Show/hide curve handles for a section
                showCurveHandle(sectionId, visible) {
                    const group = this.stage.findOne(`#section-${sectionId}`);
                    if (group) {
                        const curveHandle = group.findOne('.curveHandle');
                        if (curveHandle) {
                            curveHandle.visible(visible);
                            this.layer.batchDraw();
                        }
                    }
                },

                // Hide all curve handles
                hideAllCurveHandles() {
                    this.layer.find('.curveHandle').forEach(handle => {
                        handle.visible(false);
                    });
                    this.layer.batchDraw();
                },

                selectSection(sectionId) {
                    const node = this.stage.findOne(`#section-${sectionId}`);
                    if (node) {
                        // Hide all curve handles first
                        this.hideAllCurveHandles();
                        // Select this section
                        this.transformer.nodes([node]);
                        this.selectedSection = sectionId;
                        // Show curve handle for selected section
                        this.showCurveHandle(sectionId, true);
                        this.layer.batchDraw();
                    }
                },

                deleteSelected() {
                    if (this.selectedSection) {
                        // Find the section to determine its type
                        const section = this.sections.find(s => s.id === this.selectedSection);
                        let confirmMessage = 'Delete this section?';

                        if (section) {
                            if (section.section_type === 'icon') {
                                confirmMessage = `Delete icon "${section.name}"?`;
                            } else if (section.section_type === 'decorative') {
                                confirmMessage = `Delete decorative zone "${section.name}"?`;
                            } else {
                                confirmMessage = `Delete section "${section.name}"?`;
                            }
                        }

                        if (confirm(confirmMessage)) {
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

                toggleBackgroundVisibility() {
                    if (this.backgroundImage) {
                        this.backgroundImage.visible(this.backgroundVisible);
                        this.backgroundLayer.batchDraw();
                    }
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
                        enabledAnchors: ['top-left', 'top-center', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right'],
                        rotateEnabled: true,
                        keepRatio: false,
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
                    // Preserve the currently selected section ID before rebuilding
                    const previouslySelectedSection = this.selectedSection;

                    // Reload all sections after update
                    this.sections = detail.sections;

                    // Clear and rebuild canvas
                    this.layer.destroyChildren();
                    if (this.seatsLayer) this.seatsLayer.destroyChildren();

                    // Re-add transformer
                    this.transformer = new Konva.Transformer({
                        enabledAnchors: ['top-left', 'top-center', 'top-right', 'middle-left', 'middle-right', 'bottom-left', 'bottom-center', 'bottom-right'],
                        rotateEnabled: true,
                        keepRatio: false,
                        borderStroke: '#4F46E5',
                        borderStrokeWidth: 2,
                        anchorStroke: '#4F46E5',
                        anchorFill: '#fff',
                        anchorSize: 10,
                    });
                    this.layer.add(this.transformer);

                    // Reload sections
                    this.loadSections();

                    // Restore selection after sections are recreated
                    if (previouslySelectedSection) {
                        const sectionNode = this.stage.findOne(`#section-${previouslySelectedSection}`);
                        if (sectionNode) {
                            this.transformer.nodes([sectionNode]);
                            this.selectedSection = previouslySelectedSection;
                            this.layer.batchDraw();
                        }
                    }
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
                            // Get seat size and shape from section metadata
                            const metadata = section.metadata || {};
                            const sectionSeatSize = metadata.seat_size || 10;
                            const sectionSeatShape = metadata.seat_shape || 'circle';
                            // Calculate curve offset for manually placed seats
                            const curveAmount = parseFloat(metadata.curve_amount || 0);
                            const sectionWidth = section.width || 200;
                            let curveOffset = 0;
                            if (curveAmount !== 0) {
                                const seatX = parseFloat(seat.x || 0);
                                const xNormalized = seatX / sectionWidth;
                                curveOffset = curveAmount * (1 - 4 * Math.pow(xNormalized - 0.5, 2));
                            }
                            const seatShape = this.createSeat(seat, section.seat_color || section.color_hex, sectionId, sectionSeatSize, sectionSeatShape, row, curveOffset);
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
