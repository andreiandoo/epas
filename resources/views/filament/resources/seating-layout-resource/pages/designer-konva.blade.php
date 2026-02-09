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
            konvaInitialized: false,
            init() {
                console.log('Konva Designer: waiting for Konva library...');
                this.waitForKonva();
            },
            waitForKonva() {
                // Check if Konva is loaded
                if (typeof Konva !== 'undefined') {
                    // Also ensure DOM is ready and container exists with dimensions
                    this.$nextTick(() => {
                        const container = document.getElementById('konva-container');
                        if (container && container.offsetWidth > 0) {
                            if (!this.konvaInitialized) {
                                this.initKonva();
                            }
                        } else {
                            // Container not ready, wait a bit more
                            setTimeout(() => this.waitForKonva(), 50);
                        }
                    });
                } else {
                    // Konva not loaded yet, check again
                    setTimeout(() => this.waitForKonva(), 50);
                }
            },
            initKonva() {
                if (this.konvaInitialized) {
                    console.log('Konva Designer: already initialized, skipping');
                    return;
                }

                const container = document.getElementById('konva-container');
                if (!container) {
                    console.error('Konva container not found');
                    return;
                }

                const containerWidth = container.offsetWidth || 800;
                const containerHeight = 600;

                console.log('Konva Designer: initializing with dimensions', containerWidth, 'x', containerHeight);

                // Create stage
                this.stage = new Konva.Stage({
                    container: 'konva-container',
                    width: containerWidth,
                    height: containerHeight,
                    draggable: true
                });

                this.konvaInitialized = true;

                // Create layers
                this.backgroundLayer = new Konva.Layer();
                this.layer = new Konva.Layer();
                this.drawLayer = new Konva.Layer();
                this.seatsLayer = new Konva.Layer();

                this.stage.add(this.backgroundLayer);
                this.stage.add(this.layer);
                this.stage.add(this.seatsLayer);
                this.stage.add(this.drawLayer);

                // Create transformer
                this.transformer = new Konva.Transformer({
                    rotateEnabled: true,
                    enabledAnchors: ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'middle-left', 'middle-right', 'top-center', 'bottom-center']
                });
                this.layer.add(this.transformer);

                // Draw background
                this.drawBackground();

                // Draw grid
                this.drawGrid();

                // Draw existing sections
                this.drawSections();

                // Setup event handlers
                this.setupStageEvents();

                // Handle window resize
                window.addEventListener('resize', () => this.handleResize());

                console.log('Konva Designer initialized successfully');
            },
            drawBackground() {
                // Clear background layer
                this.backgroundLayer.destroyChildren();

                // Background color rect
                const bgRect = new Konva.Rect({
                    x: 0,
                    y: 0,
                    width: this.canvasWidth,
                    height: this.canvasHeight,
                    fill: this.backgroundColor
                });
                this.backgroundLayer.add(bgRect);

                // Background image if exists
                if (this.backgroundUrl && this.backgroundVisible) {
                    const imageObj = new Image();
                    imageObj.onload = () => {
                        const bgImage = new Konva.Image({
                            x: this.backgroundX,
                            y: this.backgroundY,
                            image: imageObj,
                            opacity: this.backgroundOpacity,
                            scaleX: this.backgroundScale,
                            scaleY: this.backgroundScale
                        });
                        this.backgroundLayer.add(bgImage);
                        bgImage.moveToBottom();
                        bgRect.moveToBottom();
                        this.backgroundLayer.batchDraw();
                    };
                    imageObj.src = this.backgroundUrl;
                }

                this.backgroundLayer.batchDraw();
            },
            drawGrid() {
                // Remove old grid
                this.layer.find('.grid-line').forEach(l => l.destroy());

                if (!this.showGrid) {
                    this.layer.batchDraw();
                    return;
                }

                const gridSize = this.gridSize;
                const width = this.canvasWidth;
                const height = this.canvasHeight;

                // Vertical lines
                for (let x = 0; x <= width; x += gridSize) {
                    const line = new Konva.Line({
                        points: [x, 0, x, height],
                        stroke: '#e5e7eb',
                        strokeWidth: 0.5,
                        name: 'grid-line'
                    });
                    this.layer.add(line);
                    line.moveToBottom();
                }

                // Horizontal lines
                for (let y = 0; y <= height; y += gridSize) {
                    const line = new Konva.Line({
                        points: [0, y, width, y],
                        stroke: '#e5e7eb',
                        strokeWidth: 0.5,
                        name: 'grid-line'
                    });
                    this.layer.add(line);
                    line.moveToBottom();
                }

                this.layer.batchDraw();
            },
            drawSections() {
                // Clear existing section shapes
                this.layer.find('.section-shape').forEach(s => s.destroy());
                this.seatsLayer.destroyChildren();

                this.sections.forEach(section => {
                    this.drawSection(section);
                });

                this.layer.batchDraw();
                this.seatsLayer.batchDraw();
            },
            drawSection(section) {
                const sectionWidth = section.width || 200;
                const sectionHeight = section.height || 150;

                // Create group with offset at center for rotation around center
                const group = new Konva.Group({
                    x: (section.x_position || 0) + sectionWidth / 2,
                    y: (section.y_position || 0) + sectionHeight / 2,
                    offsetX: sectionWidth / 2,
                    offsetY: sectionHeight / 2,
                    rotation: section.rotation || 0,
                    scaleX: section.scale || 1,
                    scaleY: section.scale || 1,
                    draggable: this.drawMode === 'select', // Only draggable in select mode
                    id: `section-${section.id}`,
                    name: 'section-shape'
                });

                // Section background
                const rect = new Konva.Rect({
                    width: sectionWidth,
                    height: sectionHeight,
                    fill: section.color_hex || '#3B82F6',
                    opacity: 0.3,
                    stroke: section.color_hex || '#3B82F6',
                    strokeWidth: 2,
                    cornerRadius: section.corner_radius || 0
                });
                group.add(rect);

                // Section label
                const label = new Konva.Text({
                    text: section.label || section.name || 'Section',
                    fontSize: section.font_size || 14,
                    fontFamily: 'Arial',
                    fill: '#1f2937',
                    x: 10,
                    y: 10,
                    name: 'section-label'
                });
                group.add(label);

                // Draw seats if they exist
                if (section.rows) {
                    section.rows.forEach(row => {
                        if (row.seats) {
                            row.seats.forEach(seat => {
                                const seatCircle = new Konva.Circle({
                                    x: seat.x_offset || 0,
                                    y: seat.y_offset || 0,
                                    radius: 8,
                                    fill: section.seat_color || '#22C55E',
                                    stroke: '#166534',
                                    strokeWidth: 1,
                                    id: `seat-${seat.id}`,
                                    name: 'seat'
                                });
                                group.add(seatCircle);
                            });
                        }
                    });
                }

                // Event handlers for section
                group.on('click tap', (e) => {
                    if (this.drawMode === 'select') {
                        e.cancelBubble = true;
                        this.selectSection(section.id);
                    }
                });

                group.on('contextmenu', (e) => {
                    e.evt.preventDefault();
                    this.showSectionContextMenu(e, section);
                });

                group.on('dragend', () => {
                    // Account for offset - position is center, we need top-left for storage
                    const topLeftX = group.x() - group.offsetX();
                    const topLeftY = group.y() - group.offsetY();
                    this.updateSectionPosition(section.id, Math.round(topLeftX), Math.round(topLeftY));
                });

                group.on('transformend', () => {
                    const scaleX = group.scaleX();
                    const scaleY = group.scaleY();
                    group.scaleX(1);
                    group.scaleY(1);
                    rect.width(rect.width() * scaleX);
                    rect.height(rect.height() * scaleY);
                    this.updateSectionTransform(section.id, group);
                });

                this.layer.add(group);
            },
            setupStageEvents() {
                // Click on stage
                this.stage.on('click tap', (e) => {
                    if (e.target === this.stage && this.drawMode === 'select') {
                        this.deselectAll();
                    }

                    if (this.drawMode === 'text') {
                        this.addTextAtPosition(this.stage.getPointerPosition());
                    }
                });

                // Mouse down for drawing
                this.stage.on('mousedown touchstart', (e) => {
                    const pos = this.stage.getPointerPosition();
                    const transformed = this.getTransformedPoint(pos);

                    // For section drawing, only allow on empty stage
                    if (this.drawMode === 'drawRect' && e.target === this.stage) {
                        this.startDrawRect(transformed);
                    } else if (this.drawMode === 'polygon' && e.target === this.stage) {
                        this.addPolygonPoint(transformed);
                    }
                    // For seat drawing modes, allow anywhere (even on sections)
                    else if (this.drawMode === 'drawSingleRow') {
                        this.startDrawRow(transformed);
                    } else if (this.drawMode === 'drawMultiRows') {
                        this.startDrawMultiRows(transformed);
                    } else if (this.drawMode === 'drawRoundTable') {
                        this.addRoundTable(transformed);
                    } else if (this.drawMode === 'drawRectTable') {
                        this.addRectTable(transformed);
                    }
                });

                // Mouse move for drawing preview
                this.stage.on('mousemove touchmove', (e) => {
                    const pos = this.stage.getPointerPosition();
                    if (!pos) return;
                    const transformed = this.getTransformedPoint(pos);

                    if (this.drawMode === 'drawRect' && this.drawRectStart) {
                        this.updateDrawRect(transformed);
                    } else if (this.drawMode === 'drawSingleRow' && this.rowDrawStart) {
                        this.updateDrawRow(transformed);
                    } else if (this.drawMode === 'drawMultiRows' && this.multiRowStart) {
                        this.updateDrawMultiRows(transformed);
                    }
                });

                // Mouse up to finish drawing
                this.stage.on('mouseup touchend', () => {
                    if (this.drawMode === 'drawRect' && this.drawRectStart) {
                        this.finishDrawRect();
                    } else if (this.drawMode === 'drawSingleRow' && this.rowDrawStart) {
                        this.finishDrawRow();
                    } else if (this.drawMode === 'drawMultiRows' && this.multiRowStart) {
                        this.finishDrawMultiRows();
                    }
                });

                // Zoom with scroll
                this.stage.on('wheel', (e) => {
                    e.evt.preventDefault();
                    const scaleBy = 1.05;
                    const oldScale = this.stage.scaleX();
                    const pointer = this.stage.getPointerPosition();

                    const mousePointTo = {
                        x: (pointer.x - this.stage.x()) / oldScale,
                        y: (pointer.y - this.stage.y()) / oldScale,
                    };

                    const newScale = e.evt.deltaY > 0 ? oldScale / scaleBy : oldScale * scaleBy;
                    this.zoom = Math.max(0.1, Math.min(3, newScale));

                    this.stage.scale({ x: this.zoom, y: this.zoom });

                    const newPos = {
                        x: pointer.x - mousePointTo.x * this.zoom,
                        y: pointer.y - mousePointTo.y * this.zoom,
                    };
                    this.stage.position(newPos);
                    this.stage.batchDraw();
                });
            },
            getTransformedPoint(pos) {
                const transform = this.stage.getAbsoluteTransform().copy().invert();
                return transform.point(pos);
            },
            selectSection(sectionId) {
                this.selectedSection = sectionId;
                const group = this.stage.findOne(`#section-${sectionId}`);
                if (group) {
                    this.transformer.nodes([group]);
                    const section = this.sections.find(s => s.id === sectionId);
                    if (section) {
                        this.sectionWidth = section.width || 200;
                        this.sectionHeight = section.height || 150;
                        this.sectionRotation = section.rotation || 0;
                        this.sectionScale = section.scale || 1;
                        this.sectionCornerRadius = section.corner_radius || 0;
                        this.sectionLabel = section.label || section.name || '';
                        this.sectionFontSize = section.font_size || 14;
                        this.editColorHex = section.color_hex || '#3B82F6';
                        this.editSeatColor = section.seat_color || '#22C55E';
                    }
                }
                this.layer.batchDraw();
            },
            deselectAll() {
                this.selectedSection = null;
                this.transformer.nodes([]);
                this.layer.batchDraw();
            },
            updateSectionPreview() {
                if (!this.selectedSection) return;

                const newWidth = parseInt(this.sectionWidth) || 200;
                const newHeight = parseInt(this.sectionHeight) || 150;
                const newScale = parseFloat(this.sectionScale) || 1;

                // Update local section data for preview
                const section = this.sections.find(s => s.id === this.selectedSection);
                if (section) {
                    section.width = newWidth;
                    section.height = newHeight;
                    section.rotation = parseInt(this.sectionRotation) || 0;
                    section.scale = newScale;
                    section.color_hex = this.editColorHex;
                    section.seat_color = this.editSeatColor;
                    section.corner_radius = parseInt(this.sectionCornerRadius) || 0;
                    section.label = this.sectionLabel;
                    section.font_size = parseInt(this.sectionFontSize) || 14;
                }

                // Update the Konva group directly for instant preview
                const group = this.stage.findOne(`#section-${this.selectedSection}`);
                if (group) {
                    // Update offset for center rotation when size changes
                    group.offsetX(newWidth / 2);
                    group.offsetY(newHeight / 2);
                    group.rotation(parseInt(this.sectionRotation) || 0);
                    group.scaleX(newScale);
                    group.scaleY(newScale);

                    const rect = group.findOne('Rect');
                    if (rect) {
                        rect.width(newWidth);
                        rect.height(newHeight);
                        rect.fill(this.editColorHex);
                        rect.stroke(this.editColorHex);
                        rect.cornerRadius(parseInt(this.sectionCornerRadius) || 0);
                    }

                    // Update label text
                    const label = group.findOne('.section-label');
                    if (label) {
                        label.text(this.sectionLabel || section?.name || 'Section');
                        label.fontSize(parseInt(this.sectionFontSize) || 14);
                    }

                    // Update seats color
                    group.find('.seat').forEach(seat => {
                        seat.fill(this.editSeatColor);
                    });
                    this.transformer.forceUpdate();
                }
                this.layer.batchDraw();
            },
            saveSectionChanges() {
                if (!this.selectedSection) return;

                // Save via Livewire
                this.$wire.updateSection(this.selectedSection, {
                    width: parseInt(this.sectionWidth),
                    height: parseInt(this.sectionHeight),
                    rotation: parseInt(this.sectionRotation),
                    x_position: this.getSelectedSectionData()?.x_position,
                    y_position: this.getSelectedSectionData()?.y_position,
                });

                // Save colors separately
                this.$wire.updateSectionColors(
                    this.selectedSection,
                    this.editColorHex,
                    this.editSeatColor
                );
            },
            applySectionChanges() {
                // Alias for backwards compatibility
                this.saveSectionChanges();
            },
            showSectionContextMenu(e, section) {
                const containerRect = this.stage.container().getBoundingClientRect();
                this.contextMenuX = containerRect.left + e.evt.offsetX;
                this.contextMenuY = containerRect.top + e.evt.offsetY;
                this.contextMenuSectionId = section.id;
                this.contextMenuSectionType = section.section_type;
                this.showContextMenu = true;
            },
            startDrawRect(pos) {
                this.drawRectStart = pos;
                this.tempDrawRect = new Konva.Rect({
                    x: pos.x,
                    y: pos.y,
                    width: 0,
                    height: 0,
                    fill: 'rgba(59, 130, 246, 0.2)',
                    stroke: '#3B82F6',
                    strokeWidth: 2,
                    dash: [5, 5]
                });
                this.drawLayer.add(this.tempDrawRect);
            },
            updateDrawRect(pos) {
                if (!this.tempDrawRect) return;
                const width = pos.x - this.drawRectStart.x;
                const height = pos.y - this.drawRectStart.y;
                this.tempDrawRect.width(width);
                this.tempDrawRect.height(height);
                this.drawLayer.batchDraw();
            },
            finishDrawRect() {
                if (!this.tempDrawRect || !this.drawRectStart) return;

                const width = Math.abs(this.tempDrawRect.width());
                const height = Math.abs(this.tempDrawRect.height());

                if (width > 20 && height > 20) {
                    const x = Math.min(this.drawRectStart.x, this.drawRectStart.x + this.tempDrawRect.width());
                    const y = Math.min(this.drawRectStart.y, this.drawRectStart.y + this.tempDrawRect.height());

                    // Create section via Livewire
                    this.$wire.createSection({
                        name: 'New Section',
                        section_type: 'standard',
                        x_position: Math.round(x),
                        y_position: Math.round(y),
                        width: Math.round(width),
                        height: Math.round(height),
                        color_hex: '#3B82F6'
                    });
                }

                this.tempDrawRect.destroy();
                this.tempDrawRect = null;
                this.drawRectStart = null;
                this.drawLayer.batchDraw();
                this.drawMode = 'select';
            },
            addPolygonPoint(pos) {
                this.polygonPoints.push(pos.x, pos.y);

                // Draw preview
                if (this.tempPolygon) {
                    this.tempPolygon.destroy();
                }
                this.tempPolygon = new Konva.Line({
                    points: this.polygonPoints,
                    stroke: '#10B981',
                    strokeWidth: 2,
                    closed: false,
                    dash: [5, 5]
                });
                this.drawLayer.add(this.tempPolygon);
                this.drawLayer.batchDraw();
            },
            finishPolygon() {
                if (this.polygonPoints.length < 6) return; // Need at least 3 points

                // Calculate bounding box
                let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                for (let i = 0; i < this.polygonPoints.length; i += 2) {
                    minX = Math.min(minX, this.polygonPoints[i]);
                    maxX = Math.max(maxX, this.polygonPoints[i]);
                    minY = Math.min(minY, this.polygonPoints[i + 1]);
                    maxY = Math.max(maxY, this.polygonPoints[i + 1]);
                }

                this.$wire.createSection({
                    name: 'Polygon Section',
                    section_type: 'polygon',
                    x_position: Math.round(minX),
                    y_position: Math.round(minY),
                    width: Math.round(maxX - minX),
                    height: Math.round(maxY - minY),
                    color_hex: '#10B981',
                    polygon_points: this.polygonPoints
                });

                this.cancelDrawing();
            },
            startDrawRow(pos) {
                this.rowDrawStart = pos;
                this.tempRowSeats = [];
            },
            updateDrawRow(pos) {
                // Clear temp seats
                this.drawLayer.find('.temp-seat').forEach(s => s.destroy());

                const dx = pos.x - this.rowDrawStart.x;
                const dy = pos.y - this.rowDrawStart.y;
                const length = Math.sqrt(dx * dx + dy * dy);
                const numSeats = Math.max(1, Math.floor(length / this.rowSeatSpacing));

                for (let i = 0; i < numSeats; i++) {
                    const t = numSeats > 1 ? i / (numSeats - 1) : 0;
                    const x = this.rowDrawStart.x + dx * t;
                    const y = this.rowDrawStart.y + dy * t;

                    const seat = new Konva.Circle({
                        x: x,
                        y: y,
                        radius: this.rowSeatSize / 2,
                        fill: 'rgba(34, 197, 94, 0.5)',
                        stroke: '#166534',
                        strokeWidth: 1,
                        name: 'temp-seat'
                    });
                    this.drawLayer.add(seat);
                    this.tempRowSeats.push({ x, y });
                }
                this.drawLayer.batchDraw();
            },
            finishDrawRow() {
                if (this.tempRowSeats.length > 0 && this.selectedSection) {
                    // Convert absolute coordinates to section-relative coordinates
                    const section = this.sections.find(s => s.id === this.selectedSection);
                    if (section) {
                        const sectionX = section.x_position || 0;
                        const sectionY = section.y_position || 0;
                        const relativeSeats = this.tempRowSeats.map(seat => ({
                            x: seat.x - sectionX,
                            y: seat.y - sectionY
                        }));
                        this.$wire.addSeatsToSection(this.selectedSection, relativeSeats);
                    }
                }

                this.drawLayer.find('.temp-seat').forEach(s => s.destroy());
                this.tempRowSeats = [];
                this.rowDrawStart = null;
                this.drawLayer.batchDraw();
            },
            startDrawMultiRows(pos) {
                this.multiRowStart = pos;
                this.tempMultiRowSeats = [];
            },
            updateDrawMultiRows(pos) {
                // Clear temp seats
                this.drawLayer.find('.temp-multi-seat').forEach(s => s.destroy());
                this.tempMultiRowSeats = [];

                const dx = pos.x - this.multiRowStart.x;
                const dy = pos.y - this.multiRowStart.y;
                const width = Math.abs(dx);
                const height = Math.abs(dy);
                const startX = Math.min(this.multiRowStart.x, pos.x);
                const startY = Math.min(this.multiRowStart.y, pos.y);

                const numRows = Math.max(1, Math.floor(height / this.rowSpacing));
                const seatsPerRow = Math.max(1, Math.floor(width / this.rowSeatSpacing));

                for (let row = 0; row < numRows; row++) {
                    const rowY = startY + row * this.rowSpacing + this.rowSpacing / 2;
                    for (let col = 0; col < seatsPerRow; col++) {
                        const seatX = startX + col * this.rowSeatSpacing + this.rowSeatSpacing / 2;
                        const seat = new Konva.Circle({
                            x: seatX,
                            y: rowY,
                            radius: this.rowSeatSize / 2,
                            fill: 'rgba(34, 197, 94, 0.5)',
                            stroke: '#166534',
                            strokeWidth: 1,
                            name: 'temp-multi-seat'
                        });
                        this.drawLayer.add(seat);
                        this.tempMultiRowSeats.push({ x: seatX, y: rowY, row: row });
                    }
                }
                this.drawLayer.batchDraw();
            },
            finishDrawMultiRows() {
                if (this.tempMultiRowSeats.length > 0 && this.selectedSection) {
                    const section = this.sections.find(s => s.id === this.selectedSection);
                    if (section) {
                        const sectionX = section.x_position || 0;
                        const sectionY = section.y_position || 0;

                        // Group seats by row for proper row creation
                        const rowGroups = {};
                        this.tempMultiRowSeats.forEach(seat => {
                            if (!rowGroups[seat.row]) rowGroups[seat.row] = [];
                            rowGroups[seat.row].push({
                                x: seat.x - sectionX,
                                y: seat.y - sectionY
                            });
                        });

                        // Add each row separately
                        Object.values(rowGroups).forEach(seats => {
                            this.$wire.addSeatsToSection(this.selectedSection, seats);
                        });
                    }
                }

                this.drawLayer.find('.temp-multi-seat').forEach(s => s.destroy());
                this.tempMultiRowSeats = [];
                this.multiRowStart = null;
                this.drawLayer.batchDraw();
            },
            addRoundTable(pos) {
                if (!this.selectedSection) return;

                const section = this.sections.find(s => s.id === this.selectedSection);
                if (!section) return;

                const sectionX = section.x_position || 0;
                const sectionY = section.y_position || 0;
                const seats = [];
                const radius = 40;
                const numSeats = this.tableSeats;

                for (let i = 0; i < numSeats; i++) {
                    const angle = (i / numSeats) * Math.PI * 2 - Math.PI / 2;
                    seats.push({
                        x: (pos.x + Math.cos(angle) * radius) - sectionX,
                        y: (pos.y + Math.sin(angle) * radius) - sectionY
                    });
                }

                this.$wire.addSeatsToSection(this.selectedSection, seats);
            },
            addRectTable(pos) {
                if (!this.selectedSection) return;

                const section = this.sections.find(s => s.id === this.selectedSection);
                if (!section) return;

                const sectionX = section.x_position || 0;
                const sectionY = section.y_position || 0;
                const seats = [];
                const width = 80;
                const height = 40;
                const seatsPerSide = Math.ceil(this.tableSeatsRect / 2);
                const spacing = width / (seatsPerSide + 1);

                // Top seats
                for (let i = 1; i <= seatsPerSide; i++) {
                    seats.push({
                        x: (pos.x - width/2 + i * spacing) - sectionX,
                        y: (pos.y - height/2 - 15) - sectionY
                    });
                }
                // Bottom seats
                for (let i = 1; i <= seatsPerSide; i++) {
                    seats.push({
                        x: (pos.x - width/2 + i * spacing) - sectionX,
                        y: (pos.y + height/2 + 15) - sectionY
                    });
                }

                this.$wire.addSeatsToSection(this.selectedSection, seats);
            },
            updateSectionsDraggable() {
                // Update all sections' draggable state based on current draw mode
                const isDraggable = this.drawMode === 'select';
                this.layer.find('.section-shape').forEach(group => {
                    group.draggable(isDraggable);
                });
                this.layer.batchDraw();
            },
            setDrawMode(mode) {
                this.drawMode = mode;
                this.updateSectionsDraggable();
            },
            addTextAtPosition(pos) {
                this.shapeConfigType = 'text';
                this.shapeConfigData = this.getTransformedPoint(pos);
                this.showShapeConfigModal = true;
            },
            confirmShapeConfig() {
                if (this.shapeConfigType === 'text' && this.shapeConfigText) {
                    const text = new Konva.Text({
                        x: this.shapeConfigData.x,
                        y: this.shapeConfigData.y,
                        text: this.shapeConfigText,
                        fontSize: this.shapeConfigFontSize,
                        fontFamily: this.shapeConfigFontFamily,
                        fill: this.shapeConfigColor,
                        draggable: true,
                        name: 'text-element'
                    });
                    this.layer.add(text);
                    this.layer.batchDraw();
                }
                this.showShapeConfigModal = false;
                this.shapeConfigText = '';
            },
            cancelDrawing() {
                if (this.tempPolygon) {
                    this.tempPolygon.destroy();
                    this.tempPolygon = null;
                }
                if (this.tempDrawRect) {
                    this.tempDrawRect.destroy();
                    this.tempDrawRect = null;
                }
                this.drawLayer.find('.temp-seat').forEach(s => s.destroy());
                this.polygonPoints = [];
                this.drawRectStart = null;
                this.rowDrawStart = null;
                this.tempRowSeats = [];
                this.drawLayer.batchDraw();
                this.drawMode = 'select';
            },
            finishDrawing() {
                if (this.drawMode === 'polygon' && this.polygonPoints.length >= 6) {
                    this.finishPolygon();
                } else if (this.drawMode === 'drawRect' && this.tempDrawRect) {
                    this.finishDrawRect();
                }
            },
            toggleGrid() {
                this.showGrid = !this.showGrid;
                this.drawGrid();
            },
            toggleSnapToGrid() {
                this.snapToGrid = !this.snapToGrid;
            },
            zoomIn() {
                this.zoom = Math.min(3, this.zoom + 0.1);
                this.stage.scale({ x: this.zoom, y: this.zoom });
                this.stage.batchDraw();
            },
            zoomOut() {
                this.zoom = Math.max(0.1, this.zoom - 0.1);
                this.stage.scale({ x: this.zoom, y: this.zoom });
                this.stage.batchDraw();
            },
            resetView() {
                this.zoom = 1;
                this.stage.scale({ x: 1, y: 1 });
                this.stage.position({ x: 0, y: 0 });
                this.stage.batchDraw();
            },
            zoomToFit() {
                const container = this.stage.container();
                const containerWidth = container.offsetWidth;
                const containerHeight = container.offsetHeight;
                const scaleX = containerWidth / this.canvasWidth;
                const scaleY = containerHeight / this.canvasHeight;
                this.zoom = Math.min(scaleX, scaleY, 1);
                this.stage.scale({ x: this.zoom, y: this.zoom });
                this.stage.position({ x: 0, y: 0 });
                this.stage.batchDraw();
            },
            handleKeyDown(e) {
                if (e.key === 'Escape') {
                    this.cancelDrawing();
                    this.deselectAll();
                } else if (e.key === 'Delete' && this.selectedSection) {
                    this.deleteSelected();
                }
            },
            deleteSelected() {
                if (this.selectedSection) {
                    this.$wire.deleteSection(this.selectedSection);
                    this.deselectAll();
                }
            },
            updateSectionPosition(sectionId, x, y) {
                this.$wire.updateSectionPosition(sectionId, Math.round(x), Math.round(y));
            },
            updateSectionTransform(sectionId, group) {
                const rect = group.findOne('Rect');
                this.$wire.updateSectionTransform(sectionId, {
                    x_position: Math.round(group.x()),
                    y_position: Math.round(group.y()),
                    width: Math.round(rect.width()),
                    height: Math.round(rect.height()),
                    rotation: Math.round(group.rotation())
                });
            },
            updateBackgroundColor() {
                this.drawBackground();
            },
            saveBackgroundColor() {
                this.$wire.saveBackgroundColor(this.backgroundColor);
            },
            toggleBackgroundVisibility() {
                this.drawBackground();
            },
            updateBackgroundScale() {
                this.drawBackground();
            },
            updateBackgroundOpacity() {
                this.drawBackground();
            },
            saveBackgroundSettings() {
                this.$wire.saveBackgroundSettings({
                    background_scale: this.backgroundScale,
                    background_opacity: this.backgroundOpacity,
                    background_x: this.backgroundX,
                    background_y: this.backgroundY
                });
            },
            exportSVG() {
                const dataURL = this.stage.toDataURL({ pixelRatio: 2 });
                const link = document.createElement('a');
                link.download = 'seating-layout.png';
                link.href = dataURL;
                link.click();
            },
            exportJSON() {
                const data = JSON.stringify(this.sections, null, 2);
                const blob = new Blob([data], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.download = 'seating-layout.json';
                link.href = url;
                link.click();
                URL.revokeObjectURL(url);
            },
            handleResize() {
                const container = this.stage.container();
                this.stage.width(container.offsetWidth);
                this.stage.batchDraw();
            },
            handleSectionDeleted(detail) {
                const idx = this.sections.findIndex(s => s.id === detail.sectionId);
                if (idx !== -1) {
                    this.sections.splice(idx, 1);
                }
                this.drawSections();
            },
            handleSectionAdded(detail) {
                this.sections.push(detail.section);
                this.drawSections();
            },
            handleSeatAdded(detail) {
                const section = this.sections.find(s => s.id === detail.sectionId);
                if (section) {
                    if (!section.rows) section.rows = [];
                    // Logic to add seats to rows
                }
                this.drawSections();
            },
            handleLayoutImported(detail) {
                this.sections = detail.sections;
                this.drawSections();
            },
            handleLayoutUpdated(detail) {
                this.sections = detail.sections;
                this.drawSections();
            },
            getSelectedSectionSeatsCount() {
                const section = this.getSelectedSectionData();
                if (!section || !section.rows) return 0;
                return section.rows.reduce((total, row) => total + (row.seats?.length || 0), 0);
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

                {{-- Section Tools --}}
                <div class="space-y-2" x-show="!addSeatsMode" x-transition>
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Sectiuni</div>
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

                {{-- Add Seats Tools --}}
                <div x-show="addSeatsMode" x-transition class="space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Adauga Locuri</div>
                        <button x-on:click="addSeatsMode = false" type="button" class="text-xs text-gray-400 hover:text-gray-600">x</button>
                    </div>
                    <div class="grid grid-cols-1 gap-1">
                        <button x-on:click="setDrawMode('drawSingleRow')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawSingleRow' ? 'bg-purple-600 border-purple-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            Un singur rand
                        </button>
                        <button x-on:click="setDrawMode('drawMultiRows')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawMultiRows' ? 'bg-purple-600 border-purple-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            Multiple randuri
                        </button>
                        <button x-on:click="setDrawMode('drawRoundTable')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawRoundTable' ? 'bg-amber-600 border-amber-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            Masa rotunda
                        </button>
                        <button x-on:click="setDrawMode('drawRectTable')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'drawRectTable' ? 'bg-amber-600 border-amber-600 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            Masa dreptunghiulara
                        </button>
                    </div>
                </div>

                {{-- Other Tools --}}
                <div class="space-y-2" x-show="!addSeatsMode" x-transition>
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Alte Instrumente</div>
                    <div class="grid grid-cols-2 gap-1">
                        <button x-on:click="setDrawMode('text')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'text' ? 'bg-gray-700 border-gray-700 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            Text
                        </button>
                        <button x-on:click="setDrawMode('line')" type="button"
                            class="flex items-center gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                            :class="drawMode === 'line' ? 'bg-gray-700 border-gray-700 text-white shadow-sm' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                            Linie
                        </button>
                    </div>
                </div>

                {{-- View Controls --}}
                <div class="pt-3 space-y-2 border-t border-gray-200">
                    <div class="text-xs font-semibold tracking-wide text-gray-500 uppercase">Vedere</div>
                    <div class="flex items-center gap-2">
                        <button x-on:click="zoomOut()" type="button" class="px-2 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded">-</button>
                        <span class="flex-1 text-sm font-medium text-center" x-text="`${Math.round(zoom * 100)}%`"></span>
                        <button x-on:click="zoomIn()" type="button" class="px-2 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded">+</button>
                        <button x-on:click="resetView()" type="button" class="px-2 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded">Reset</button>
                    </div>
                    <div class="flex gap-1">
                        <button x-on:click="toggleGrid()" type="button" class="flex items-center flex-1 gap-1 px-2 py-1 text-xs rounded-md" :class="showGrid ? 'bg-blue-600 text-white' : 'bg-gray-100'">
                            <x-svg-icon name="konvagrid" class="w-3 h-3" /> Grid
                        </button>
                        <button x-on:click="toggleSnapToGrid()" type="button" class="flex items-center flex-1 gap-1 px-2 py-1 text-xs rounded-md" :class="snapToGrid ? 'bg-blue-600 text-white' : 'bg-gray-100'">
                            Snap
                        </button>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="pt-3 space-y-2 border-t border-gray-200">
                    <button x-on:click="showBackgroundControls = !showBackgroundControls" type="button"
                        class="flex items-center w-full gap-2 px-3 py-2 text-sm font-medium transition-all border rounded-lg"
                        :class="showBackgroundControls ? 'bg-indigo-100 border-indigo-300 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-700 hover:bg-gray-100'">
                        Fundal
                    </button>
                    <button x-on:click="showExportModal = true" type="button"
                        class="flex items-center w-full gap-2 px-3 py-2 text-sm font-medium transition-all bg-gray-50 border border-gray-200 rounded-lg text-gray-700 hover:bg-gray-100">
                        Export
                    </button>
                    <button x-on:click="deleteSelected()" type="button" x-show="selectedSection"
                        class="flex items-center w-full gap-2 px-3 py-2 text-sm font-medium text-white transition-all bg-red-600 rounded-lg hover:bg-red-700">
                        Sterge Sectiunea
                    </button>
                </div>
            </div>

            {{-- CENTER - Canvas Area --}}
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
                                <input type="color" x-model="backgroundColor" x-on:input="updateBackgroundColor && updateBackgroundColor()" class="w-8 h-8 border border-gray-300 rounded cursor-pointer">
                                <button x-on:click="saveBackgroundColor && saveBackgroundColor()" type="button" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Salvează</button>
                            </div>
                            <div x-show="backgroundUrl" class="flex flex-wrap items-center gap-3">
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="checkbox" x-model="backgroundVisible" x-on:change="toggleBackgroundVisibility && toggleBackgroundVisibility()" class="w-4 h-4 text-indigo-600 border-gray-300 rounded">
                                    <span class="text-xs text-indigo-800">Imagine</span>
                                </label>
                                <div class="flex items-center gap-1">
                                    <label class="text-xs text-indigo-700">Scală:</label>
                                    <input type="range" x-model="backgroundScale" min="0.1" max="3" step="0.01" x-on:input="updateBackgroundScale && updateBackgroundScale()" class="w-16" :disabled="!backgroundVisible">
                                </div>
                                <div class="flex items-center gap-1">
                                    <label class="text-xs text-indigo-700">Opacitate:</label>
                                    <input type="range" x-model="backgroundOpacity" min="0" max="1" step="0.01" x-on:input="updateBackgroundOpacity && updateBackgroundOpacity()" class="w-16" :disabled="!backgroundVisible">
                                </div>
                                <button x-on:click="saveBackgroundSettings && saveBackgroundSettings()" type="button" class="px-2 py-1 text-xs text-white bg-indigo-600 rounded hover:bg-indigo-700">Salvează</button>
                            </div>
                        </div>
                    </div>

                    {{-- Canvas --}}
                    <div class="overflow-hidden bg-gray-100 border-2 border-gray-300 rounded-lg">
                        <div id="konva-container" wire:ignore style="width: 100%; height: 600px; background: #f3f4f6;"></div>
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

            {{-- RIGHT SIDEBAR - Properties Panel --}}
            <div class="flex-shrink-0 p-4 space-y-4 bg-white border border-gray-200 rounded-lg shadow-sm w-80" x-show="selectedSection || selectedDrawnRow || addSeatsMode" x-transition>
                {{-- Add Seats Mode Panel --}}
                <template x-if="addSeatsMode && selectedSection && !selectedDrawnRow">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-purple-200">
                            <h4 class="text-sm font-bold tracking-wide text-purple-700 uppercase">Adaugă Locuri</h4>
                            <button x-on:click="addSeatsMode = false" class="text-purple-400 hover:text-purple-600">✕</button>
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
                            <button x-on:click="selectedSection = null" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>

                        {{-- Section Name --}}
                        <div>
                            <label class="block mb-1 text-xs font-medium text-gray-600">Nume Secțiune</label>
                            <div class="text-sm font-semibold text-gray-900" x-text="getSelectedSectionData()?.name || 'Fără nume'"></div>
                        </div>

                        {{-- Add Seats Button --}}
                        <button x-on:click="addSeatsMode = true" type="button"
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
                                    <input type="number" x-model="sectionWidth" x-on:input="updateSectionPreview()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Înălțime</label>
                                    <input type="number" x-model="sectionHeight" x-on:input="updateSectionPreview()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Rotație (°)</label>
                                <input type="range" x-model="sectionRotation" x-on:input="updateSectionPreview()" min="0" max="360" class="w-full">
                                <div class="text-xs text-center text-gray-500" x-text="sectionRotation + '°'"></div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Colțuri rotunjite</label>
                                <input type="range" x-model="sectionCornerRadius" x-on:input="updateSectionPreview()" min="0" max="50" class="w-full">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Scalare</label>
                                <input type="range" x-model="sectionScale" x-on:input="updateSectionPreview()" min="0.5" max="2" step="0.1" class="w-full">
                                <div class="text-xs text-center text-gray-500" x-text="(sectionScale * 100).toFixed(0) + '%'"></div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Curbură</label>
                                <input type="range" x-model="sectionCurve" x-on:input="updateSectionPreview()" min="-100" max="100" class="w-full">
                                <div class="text-xs text-center text-gray-500" x-text="sectionCurve"></div>
                            </div>
                        </div>

                        {{-- Label Section --}}
                        <div class="p-3 space-y-3 rounded-lg bg-gray-50">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Etichetă</div>
                            <div>
                                <label class="block text-xs text-gray-500">Nume afișat</label>
                                <input type="text" x-model="sectionLabel" x-on:input="updateSectionPreview()" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded" placeholder="Nume secțiune">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500">Dimensiune font</label>
                                <input type="number" x-model="sectionFontSize" x-on:input="updateSectionPreview()" min="8" max="72" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>

                        {{-- Colors --}}
                        <div class="p-3 space-y-3 rounded-lg bg-gray-50">
                            <div class="text-xs font-semibold text-gray-600 uppercase">Culori</div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500">Fundal</label>
                                    <input type="color" x-model="editColorHex" x-on:input="updateSectionPreview()" class="w-full h-8 border rounded cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500">Locuri</label>
                                    <input type="color" x-model="editSeatColor" x-on:input="updateSectionPreview()" class="w-full h-8 border rounded cursor-pointer">
                                </div>
                            </div>
                        </div>

                        {{-- Save Button --}}
                        <button x-on:click="saveSectionChanges()" type="button"
                            class="flex items-center justify-center w-full gap-2 px-4 py-2 text-sm font-semibold text-white transition-all bg-green-600 rounded-lg hover:bg-green-700">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                            Salvează
                        </button>

                        {{-- Section Info --}}
                        <div class="p-3 space-y-1 text-xs rounded-lg bg-gray-50">
                            <div class="flex justify-between"><span class="text-gray-500">Rânduri:</span> <span class="font-medium" x-text="getSelectedSectionData()?.rows?.length || 0"></span></div>
                            <div class="flex justify-between"><span class="text-gray-500">Locuri:</span> <span class="font-medium" x-text="getSelectedSectionSeatsCount && getSelectedSectionSeatsCount() || 0"></span></div>
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
                                <input type="number" x-model="drawnRowSeats" min="1" max="100" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs text-purple-600">Curbură</label>
                                <input type="range" x-model="drawnRowCurve" min="-50" max="50" class="w-full">
                                <div class="text-xs text-center text-purple-500" x-text="drawnRowCurve"></div>
                            </div>
                            <div>
                                <label class="block text-xs text-purple-600">Spațiu între locuri</label>
                                <input type="number" x-model="drawnRowSpacing" min="0" max="50" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                        </div>

                        {{-- Numbering --}}
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
                                <label class="block text-xs text-blue-600">Începe de la</label>
                                <input type="number" x-model="rowStartNumber" min="1" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                            </div>
                            <div>
                                <label class="block text-xs text-blue-600">Direcție</label>
                                <select x-model="rowNumberingDirection" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
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
                                <select x-model="seatNumberingType" class="w-full px-2 py-1 text-sm text-gray-900 bg-white border border-gray-300 rounded">
                                    <option value="numeric">Numere (1, 2, 3...)</option>
                                    <option value="alpha">Litere (A, B, C...)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </template>

                {{-- Empty state when nothing selected --}}
                <div x-show="!selectedSection && !selectedDrawnRow && !addSeatsMode" class="py-8 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path>
                    </svg>
                    <p class="mt-2 text-sm text-gray-500">Selectează o secțiune pentru a vedea proprietățile</p>
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
                    <button x-on:click="exportSVG && exportSVG(); showExportModal = false" type="button" class="flex flex-col items-center gap-3 p-6 transition border-2 border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 group">
                        <svg class="w-12 h-12 text-gray-400 transition group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 group-hover:text-blue-700">Export SVG</span>
                        <span class="text-xs text-gray-500">Vector image format</span>
                    </button>
                    <button x-on:click="exportJSON && exportJSON(); showExportModal = false" type="button" class="flex flex-col items-center gap-3 p-6 transition border-2 border-gray-200 rounded-lg hover:border-green-500 hover:bg-green-50 group">
                        <svg class="w-12 h-12 text-gray-400 transition group-hover:text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="text-sm font-medium text-gray-700 group-hover:text-green-700">Export JSON</span>
                        <span class="text-xs text-gray-500">Backup data format</span>
                    </button>
                </div>
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
                        <button x-on:click="saveSectionColors && saveSectionColors()" type="button" class="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">Save</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Shape Config Modal --}}
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
                        <select x-model="shapeConfigFontFamily" class="w-full px-3 py-2 text-gray-900 bg-white border border-gray-300 rounded-md">
                            <option value="Arial">Arial</option>
                            <option value="Helvetica">Helvetica</option>
                            <option value="Times New Roman">Times New Roman</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Verdana">Verdana</option>
                            <option value="Courier New">Courier New</option>
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
                        <button x-on:click="showShapeConfigModal = false" type="button" class="px-4 py-2 text-sm text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Cancel</button>
                        <button x-on:click="confirmShapeConfig && confirmShapeConfig()" type="button" class="px-4 py-2 text-sm text-white bg-green-600 rounded hover:bg-green-700">Add</button>
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
                <button x-on:click="showContextMenu = false" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Edit Section
                </button>
                <template x-if="contextMenuSectionType === 'standard'">
                    <div>
                        <button x-on:click="showContextMenu = false" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-blue-700 hover:bg-blue-50">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                            Select Rows
                        </button>
                        <button x-on:click="showColorModal = true; showContextMenu = false" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                            </svg>
                            Edit Colors
                        </button>
                    </div>
                </template>
                <div class="border-t border-gray-200"></div>
                <button x-on:click="deleteSelected && deleteSelected(); showContextMenu = false" class="flex items-center w-full gap-2 px-4 py-2 text-sm text-left text-red-600 hover:bg-red-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Section
                </button>
            </div>
        </div>
    </div>

    {{-- Load Konva library --}}
    <script src="https://unpkg.com/konva@9/konva.min.js"></script>
</x-filament-panels::page>
