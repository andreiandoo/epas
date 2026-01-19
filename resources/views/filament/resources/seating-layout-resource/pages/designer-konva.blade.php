<x-filament-panels::page>
    <div class="space-y-6"
         x-data="konvaDesigner()"
         x-init="init()"
         @@section-deleted.window="handleSectionDeleted($event.detail)"
         @@section-added.window="handleSectionAdded($event.detail)"
         @@seat-added.window="handleSeatAdded($event.detail)">
        {{-- Canvas Container --}}
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Canvas Designer </h3>
                    <p class="text-sm text-gray-500">Layout: {{ $canvasWidth }}x{{ $canvasHeight }}px</p>
                </div>
                <div class="flex items-center gap-2">
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
                    <button @click="toggleGrid" type="button" class="flex items-center gap-2 px-3 py-1 text-sm" :class="showGrid ? 'bg-blue-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvagrid" class="w-5 h-5 text-purple-600" />
                        Grid
                    </button>

                    <div class="h-6 mx-1 border-l border-gray-300"></div>

                    <button @click="setDrawMode('select')" type="button" class="flex items-center gap-2 px-3 py-1 text-sm border rounded-md border-slate-200" :class="drawMode === 'select' ? 'bg-blue-500 text-white' : 'bg-gray-100'">
                        <x-svg-icon name="konvaselect" class="w-5 h-5 text-purple-600" />
                        Select
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
                    <button @click="finishDrawing" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-white bg-green-600 border rounded-md border-slate-200" x-show="['polygon', 'circle'].includes(drawMode) && polygonPoints.length > 0">
                        <x-svg-icon name="konvafinish" class="w-5 h-5 text-purple-600" />
                        Finish
                    </button>
                    <button @click="cancelDrawing" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-white bg-gray-600 border rounded-md border-slate-200" x-show="drawMode !== 'select'">
                        <x-svg-icon name="konvacancel" class="w-5 h-5 text-purple-600" />
                        Cancel
                    </button>

                    <div class="h-6 mx-1 border-l border-gray-300"></div>

                    <button @click="deleteSelected" type="button" class="flex items-center gap-2 px-3 py-1 text-sm text-red-700 bg-red-100 rounded-md hover:bg-red-200" x-show="selectedSection">
                        <x-svg-icon name="konvadelete" class="w-5 h-5 text-purple-600" />
                        Delete
                    </button>
                </div>
            </div>

            <div class="overflow-hidden bg-gray-100 border-2 border-gray-300 rounded-lg">
                <div id="konva-container" wire:ignore></div>
            </div>

            <div class="grid grid-cols-4 gap-4 mt-4 text-sm">
                <div class="p-3 text-center rounded-lg bg-gray-50">
                    <div class="text-gray-600">Sections</div>
                    <div class="text-2xl font-bold" x-text="sections.length"></div>
                </div>
                <div class="p-3 text-center rounded-lg bg-blue-50">
                    <div class="text-blue-600">Pan</div>
                    <div class="text-sm font-medium">Click + Drag Background</div>
                </div>
                <div class="p-3 text-center rounded-lg bg-green-50">
                    <div class="text-green-600">Zoom</div>
                    <div class="text-sm font-medium">Mouse Wheel</div>
                </div>
                <div class="p-3 text-center rounded-lg bg-purple-50">
                    <div class="text-purple-600">Move/Resize</div>
                    <div class="text-sm font-medium">Drag Sections</div>
                </div>
            </div>
        </div>

        {{-- Sections List --}}
        @if(count($sections) > 0)
            <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
                <h3 class="mb-4 text-lg font-semibold text-gray-900">Sections</h3>
                <div class="space-y-2">
                    @foreach($sections as $section)
                        <div class="flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50"
                             @click="selectSection({{ $section['id'] }})">
                            <div class="flex items-center gap-3">
                                <div class="w-4 h-4 rounded" style="background-color: {{ $section['color_hex'] ?? '#3B82F6' }}"></div>
                                <div>
                                    <div class="font-medium">{{ $section['section_code'] }} - {{ $section['name'] }}</div>
                                    <div class="text-xs text-gray-500">
                                        {{ count($section['rows'] ?? []) }} rows •
                                        {{ collect($section['rows'] ?? [])->sum(fn($row) => count($row['seats'] ?? [])) }} seats
                                    </div>
                                </div>
                            </div>
                            <div class="text-xs text-gray-400">
                                ({{ $section['x_position'] }}, {{ $section['y_position'] }}) •
                                {{ $section['width'] }}x{{ $section['height'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

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

                init() {
                    this.createStage();
                    this.loadSections();
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

                    // Main layer for sections
                    this.layer = new Konva.Layer();
                    this.stage.add(this.layer);

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
                        } else if (this.drawMode === 'select') {
                            if (e.target === this.stage || e.target.getLayer() === this.backgroundLayer) {
                                this.transformer.nodes([]);
                                this.selectedSection = null;
                            }
                        }
                    });

                    // Mouse move handler for circle drawing
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
                    });

                    // Mouse up handler for circle drawing
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

                            const konvaImage = new Konva.Image({
                                x: (this.canvasWidth - width) / 2,
                                y: (this.canvasHeight - height) / 2,
                                image: imageObj,
                                width: width,
                                height: height,
                                opacity: 0.3,
                            });
                            this.backgroundLayer.add(konvaImage);
                            this.backgroundLayer.batchDraw();
                        };
                        imageObj.src = this.backgroundUrl;
                    }
                },

                loadSections() {
                    this.sections.forEach(section => {
                        this.createSection(section);
                    });
                },

                createSection(section) {
                    const group = new Konva.Group({
                        x: section.x_position || 100,
                        y: section.y_position || 100,
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

                    // Draw seats if available (skip for decorative zones)
                    if (!isDecorativeZone && section.rows && section.rows.length > 0) {
                        section.rows.forEach(row => {
                            if (row.seats && row.seats.length > 0) {
                                row.seats.forEach(seat => {
                                    const seatShape = this.createSeat(seat, section.color_hex);
                                    group.add(seatShape);
                                });
                            }
                        });
                    }

                    // Click to select
                    group.on('click', () => {
                        this.transformer.nodes([group]);
                        this.selectedSection = section.id;
                    });

                    // Save on drag end
                    group.on('dragend', () => {
                        this.saveSection(section.id, {
                            x_position: Math.round(group.x()),
                            y_position: Math.round(group.y()),
                        });
                    });

                    // Save on transform end
                    group.on('transformend', () => {
                        this.saveSection(section.id, {
                            x_position: Math.round(group.x()),
                            y_position: Math.round(group.y()),
                            width: Math.round(group.width() * group.scaleX()),
                            height: Math.round(group.height() * group.scaleY()),
                            rotation: Math.round(group.rotation()),
                        });

                        // Reset scale
                        rect.width(group.width() * group.scaleX());
                        rect.height(group.height() * group.scaleY());
                        label.width(group.width() * group.scaleX());
                        group.scaleX(1);
                        group.scaleY(1);
                    });

                    this.layer.add(group);
                    this.layer.batchDraw();
                },

                createSeat(seat, sectionColor) {
                    const x = parseFloat(seat.x || 0);
                    const y = parseFloat(seat.y || 0);
                    const angle = parseFloat(seat.angle || 0);
                    const shape = seat.shape || 'circle';
                    const seatSize = 8; // Seat size in pixels

                    let seatShape;
                    if (shape === 'circle') {
                        seatShape = new Konva.Circle({
                            x: x,
                            y: y + 20, // Offset to not overlap with label
                            radius: seatSize / 2,
                            fill: sectionColor || '#3B82F6',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                        });
                    } else if (shape === 'rect') {
                        seatShape = new Konva.Rect({
                            x: x - seatSize / 2,
                            y: y + 20 - seatSize / 2,
                            width: seatSize,
                            height: seatSize,
                            fill: sectionColor || '#3B82F6',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            rotation: angle,
                        });
                    } else { // stadium
                        seatShape = new Konva.Rect({
                            x: x - seatSize / 2,
                            y: y + 20 - seatSize / 2,
                            width: seatSize,
                            height: seatSize,
                            fill: sectionColor || '#3B82F6',
                            stroke: '#1F2937',
                            strokeWidth: 1,
                            opacity: 0.8,
                            cornerRadius: seatSize / 2,
                            rotation: angle,
                        });
                    }

                    return seatShape;
                },

                saveSection(sectionId, updates) {
                    console.log('Saving section', sectionId, updates);
                    // TODO: Implement Livewire save
                    @this.call('updateSection', sectionId, updates);
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

                setDrawMode(mode) {
                    this.drawMode = mode;
                    this.polygonPoints = [];
                    this.drawLayer.destroyChildren();
                    this.drawLayer.batchDraw();

                    // Disable stage dragging in draw mode
                    this.stage.draggable(mode === 'select');

                    // Disable transformer in draw mode
                    if (mode !== 'select') {
                        this.transformer.nodes([]);
                        this.selectedSection = null;
                    }
                },

                addPolygonPoint(pos) {
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
                        const circle = new Konva.Circle({
                            x: this.polygonPoints[i],
                            y: this.polygonPoints[i + 1],
                            radius: 5,
                            fill: '#10B981',
                            stroke: '#fff',
                            strokeWidth: 2,
                        });
                        this.drawLayer.add(circle);
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

                    // Call Livewire to save seat
                    @this.call('addSeat', {
                        section_id: this.selectedSection,
                        x: Math.round(relativeX),
                        y: Math.round(relativeY),
                        label: customLabel,
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

                        // Find or create a "Manual" row for manually placed seats
                        let manualRow = section.rows.find(r => r.label === 'Manual');
                        if (!manualRow) {
                            manualRow = {
                                id: `manual-${sectionId}`,
                                label: 'Manual',
                                seats: []
                            };
                            section.rows.push(manualRow);
                        }
                        manualRow.seats.push(seat);

                        // Draw the seat on canvas
                        const sectionNode = this.stage.findOne(`#section-${sectionId}`);
                        if (sectionNode) {
                            const seatShape = this.createSeat(seat, section.color_hex);
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
</x-filament-panels::page>
