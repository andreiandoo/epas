<x-filament-panels::page>
    <div class="space-y-6" x-data="canvasDesigner()">
        {{-- Canvas Container --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Canvas Preview</h3>
                    <p class="text-sm text-gray-500">Layout: {{ $canvasWidth }}x{{ $canvasHeight }}px</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="zoomOut" type="button" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded">-</button>
                    <span class="text-sm font-medium" x-text="`${Math.round(zoom * 100)}%`"></span>
                    <button @click="zoomIn" type="button" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded">+</button>
                    <button @click="resetZoom" type="button" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded">Reset</button>
                </div>
            </div>

            <div class="border-2 border-dashed border-gray-300 rounded-lg overflow-hidden bg-gray-50 relative cursor-move"
                 style="max-width: 100%; height: 600px;"
                 x-ref="container"
                 @mousedown="startPan"
                 @mousemove="pan"
                 @mouseup="endPan"
                 @mouseleave="endPan"
                 @wheel.prevent="handleWheel">

                <div class="relative origin-top-left transition-transform"
                     :style="`width: {{ $canvasWidth }}px; height: {{ $canvasHeight }}px; transform: translate(${panX}px, ${panY}px) scale(${zoom});`">

                    {{-- Background Image --}}
                    @if($backgroundUrl)
                        <img src="{{ $backgroundUrl }}"
                             alt="Background"
                             class="absolute inset-0 w-full h-full object-contain opacity-30 pointer-events-none">
                    @endif

                    {{-- Render Sections --}}
                    @foreach($sections as $section)
                        <div class="absolute border-2 border-blue-500 bg-blue-100 bg-opacity-20 rounded-lg hover:bg-opacity-30 transition-all cursor-move group"
                             style="left: {{ $section['x_position'] }}px;
                                    top: {{ $section['y_position'] }}px;
                                    width: {{ $section['width'] }}px;
                                    height: {{ $section['height'] }}px;
                                    transform: rotate({{ $section['rotation'] ?? 0 }}deg);"
                             title="{{ $section['name'] }}">

                            {{-- Section Label --}}
                            <div class="absolute top-1 left-1 bg-blue-500 text-white text-xs px-2 py-1 rounded shadow-sm">
                                {{ $section['section_code'] }} - {{ $section['name'] }}
                            </div>

                            {{-- Row/Seat Count --}}
                            <div class="absolute bottom-1 right-1 bg-white text-gray-700 text-xs px-2 py-1 rounded shadow-sm">
                                {{ count($section['rows'] ?? []) }} rows
                            </div>

                            {{-- Edit Button (on hover) --}}
                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="#"
                                   class="bg-white text-blue-600 px-3 py-1 rounded shadow-lg text-sm font-medium hover:bg-blue-50">
                                    Edit Section
                                </a>
                            </div>
                        </div>
                    @endforeach

                    {{-- Empty State --}}
                    @if(count($sections) === 0)
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No sections yet</h3>
                                <p class="mt-1 text-sm text-gray-500">Get started by adding a section using the button above.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Helper Text --}}
            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm text-blue-700">
                            <strong>Designer Tips:</strong> Use "Add Section" to create seating areas, then use "Bulk Generate Seats"
                            to automatically create rows and seats. This is a simplified preview - full drag-and-drop designer coming soon!
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Sections List --}}
        @if(count($sections) > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Sections Overview</h3>

                <div class="space-y-3">
                    @foreach($sections as $section)
                        <div class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900">
                                        {{ $section['section_code'] }} - {{ $section['name'] }}
                                    </h4>
                                    <div class="mt-1 text-sm text-gray-500">
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                                            </svg>
                                            {{ count($section['rows'] ?? []) }} rows
                                        </span>
                                        <span class="mx-2">•</span>
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                            {{ collect($section['rows'] ?? [])->sum(fn($row) => count($row['seats'] ?? [])) }} seats
                                        </span>
                                    </div>
                                    <div class="mt-2 text-xs text-gray-400">
                                        Position: ({{ $section['x_position'] }}, {{ $section['y_position'] }})
                                        • Size: {{ $section['width'] }}x{{ $section['height'] }}
                                        @if(($section['rotation'] ?? 0) != 0)
                                            • Rotation: {{ $section['rotation'] }}°
                                        @endif
                                    </div>
                                </div>

                                <div class="ml-4 flex-shrink-0">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ ucfirst($section['section_type']) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <script>
        function canvasDesigner() {
            return {
                zoom: 1,
                panX: 0,
                panY: 0,
                isPanning: false,
                startX: 0,
                startY: 0,

                zoomIn() {
                    this.zoom = Math.min(this.zoom + 0.1, 3);
                },

                zoomOut() {
                    this.zoom = Math.max(this.zoom - 0.1, 0.1);
                },

                resetZoom() {
                    this.zoom = 1;
                    this.panX = 0;
                    this.panY = 0;
                },

                handleWheel(e) {
                    const delta = e.deltaY > 0 ? -0.05 : 0.05;
                    this.zoom = Math.max(0.1, Math.min(3, this.zoom + delta));
                },

                startPan(e) {
                    if (e.button === 0) { // Left mouse button
                        this.isPanning = true;
                        this.startX = e.clientX - this.panX;
                        this.startY = e.clientY - this.panY;
                        e.preventDefault();
                    }
                },

                pan(e) {
                    if (this.isPanning) {
                        this.panX = e.clientX - this.startX;
                        this.panY = e.clientY - this.startY;
                    }
                },

                endPan() {
                    this.isPanning = false;
                }
            }
        }
    </script>
</x-filament-panels::page>
