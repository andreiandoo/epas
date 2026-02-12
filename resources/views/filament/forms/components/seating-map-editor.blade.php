@php
    // $record is passed from the Placeholder content closure via view('...', ['record' => $record])
    $eventId = $record?->id;
    $layoutId = $record?->seating_layout_id;

    $layout = null;
    $sections = collect();
    $canvasW = 1920;
    $canvasH = 1080;
    $bgImage = '';
    $ticketTypesData = [];
    $rowAssignments = []; // rowId => [{id, name, color}]
    $rowInfoMap = [];

    if ($layoutId) {
        $layout = \App\Models\Seating\SeatingLayout::withoutGlobalScopes()
            ->with([
                'sections' => fn($q) => $q->where('section_type', 'standard')->orderBy('display_order'),
                'sections.rows.seats',
            ])
            ->find($layoutId);

        if ($layout) {
            $sections = $layout->sections;
            $canvasW = $layout->canvas_w ?? 1920;
            $canvasH = $layout->canvas_h ?? 1080;

            // Background image
            $bgPath = $layout->background_image_path ?? $layout->background_image_url;
            if ($bgPath) {
                $bgUrl = str_starts_with($bgPath, 'http') ? $bgPath : asset('storage/' . $bgPath);
                $bgScale = $layout->background_scale ?? 1;
                $bgX = $layout->background_x ?? 0;
                $bgY = $layout->background_y ?? 0;
                $bgOpacity = $layout->background_opacity ?? 0.5;
                $bgW = $canvasW * $bgScale;
                $bgH = $canvasH * $bgScale;
                $bgImage = '<image href="' . e($bgUrl) . '" x="' . $bgX . '" y="' . $bgY . '" width="' . $bgW . '" height="' . $bgH . '" opacity="' . $bgOpacity . '" preserveAspectRatio="xMidYMid meet"/>';
            }
        }
    }

    // Load ticket types with seating row assignments
    if ($eventId) {
        $tts = \App\Models\TicketType::where('event_id', $eventId)
            ->with('seatingRows')
            ->orderBy('sort_order')
            ->get();

        foreach ($tts as $tt) {
            $ticketTypesData[] = [
                'id' => $tt->id,
                'name' => $tt->name,
                'color' => $tt->color ?? '#6b7280',
                'price' => $tt->price_max ?? 0,
                'currency' => $tt->currency ?? 'RON',
            ];

            foreach ($tt->seatingRows as $row) {
                if (!isset($rowAssignments[$row->id])) {
                    $rowAssignments[$row->id] = [];
                }
                $rowAssignments[$row->id][] = [
                    'id' => $tt->id,
                    'name' => $tt->name,
                    'color' => $tt->color ?? '#6b7280',
                ];
            }
        }
    }

    // Build row info map for tooltips
    foreach ($sections as $section) {
        foreach ($section->rows as $row) {
            $rowInfoMap[$row->id] = [
                'section' => $section->name,
                'label' => $row->label,
                'seatCount' => $row->seat_count,
            ];
        }
    }

    $ticketTypesJson = json_encode($ticketTypesData);
    $rowAssignmentsJson = json_encode($rowAssignments);
    $rowInfoJson = json_encode($rowInfoMap);
@endphp

@if($layout && $sections->isNotEmpty())
<div
    x-data="{
        // Ticket types
        ticketTypes: {{ $ticketTypesJson }},
        selectedTT: {{ !empty($ticketTypesData) ? $ticketTypesData[0]['id'] : 'null' }},

        // Row assignments: { rowId: [{id, name, color}, ...] }
        assignments: {{ $rowAssignmentsJson }},
        rowInfo: {{ $rowInfoJson }},

        // Zoom & pan state
        vbX: 0,
        vbY: 0,
        vbW: {{ $canvasW }},
        vbH: {{ $canvasH }},
        origW: {{ $canvasW }},
        origH: {{ $canvasH }},
        minZoom: 0.3,
        maxZoom: 5,
        isPanning: false,
        panStartX: 0,
        panStartY: 0,
        panStartVbX: 0,
        panStartVbY: 0,
        mouseDownX: 0,
        mouseDownY: 0,
        dragThreshold: 5,

        // Tooltip
        tip: '',
        tipX: 0,
        tipY: 0,
        showTip: false,

        // Saving indicator
        saving: false,

        get currentZoom() {
            return this.origW / this.vbW;
        },

        get selectedTTColor() {
            const tt = this.ticketTypes.find(t => t.id === this.selectedTT);
            return tt ? tt.color : '#3b82f6';
        },

        get selectedTTName() {
            const tt = this.ticketTypes.find(t => t.id === this.selectedTT);
            return tt ? tt.name : '';
        },

        // Get row color: first check if assigned to selected TT (bright), then any TT (dimmed), else gray
        getRowColor(rowId) {
            const assigns = this.assignments[rowId];
            if (assigns && assigns.length > 0) {
                // Check if assigned to selected TT first
                const selectedAssign = assigns.find(a => a.id === this.selectedTT);
                if (selectedAssign) return selectedAssign.color;
                return assigns[0].color;
            }
            return '#374151';
        },

        getRowOpacity(rowId) {
            const assigns = this.assignments[rowId];
            if (!assigns || assigns.length === 0) return 0.5;
            const selectedAssign = assigns.find(a => a.id === this.selectedTT);
            if (selectedAssign) return 1;
            return 0.35;
        },

        isAssignedToSelected(rowId) {
            const assigns = this.assignments[rowId];
            if (!assigns) return false;
            return assigns.some(a => a.id === this.selectedTT);
        },

        toggleRow(rowId) {
            if (!this.selectedTT) return;
            rowId = Number(rowId);

            this.saving = true;
            $wire.call('toggleSeatingRowAssignment', this.selectedTT, rowId).then(result => {
                if (result !== false) {
                    // Update local state
                    if (!this.assignments[rowId]) this.assignments[rowId] = [];
                    const idx = this.assignments[rowId].findIndex(a => a.id === this.selectedTT);
                    if (idx > -1) {
                        this.assignments[rowId].splice(idx, 1);
                        if (this.assignments[rowId].length === 0) delete this.assignments[rowId];
                    } else {
                        this.assignments[rowId].push({
                            id: this.selectedTT,
                            name: this.selectedTTName,
                            color: this.selectedTTColor
                        });
                    }
                }
                this.saving = false;
            }).catch(() => { this.saving = false; });
        },

        // Zoom with mouse wheel
        handleWheel(e) {
            e.preventDefault();
            const svg = this.$refs.svgMap;
            const rect = svg.getBoundingClientRect();

            // Mouse position in SVG coordinates
            const mx = ((e.clientX - rect.left) / rect.width) * this.vbW + this.vbX;
            const my = ((e.clientY - rect.top) / rect.height) * this.vbH + this.vbY;

            const factor = e.deltaY < 0 ? 1.15 : (1 / 1.15);
            let newW = this.vbW / factor;
            let newH = this.vbH / factor;

            // Clamp zoom
            const zoom = this.origW / newW;
            if (zoom < this.minZoom || zoom > this.maxZoom) return;

            // Adjust viewBox so that the point under cursor stays fixed
            this.vbX = mx - (mx - this.vbX) / factor;
            this.vbY = my - (my - this.vbY) / factor;
            this.vbW = newW;
            this.vbH = newH;
        },

        handleMouseDown(e) {
            if (e.button !== 0) return;
            this.mouseDownX = e.clientX;
            this.mouseDownY = e.clientY;
            this.isPanning = false;
            this.panStartX = e.clientX;
            this.panStartY = e.clientY;
            this.panStartVbX = this.vbX;
            this.panStartVbY = this.vbY;
        },

        handleMouseMove(e) {
            const dx = e.clientX - this.mouseDownX;
            const dy = e.clientY - this.mouseDownY;

            if (!this.isPanning && (Math.abs(dx) > this.dragThreshold || Math.abs(dy) > this.dragThreshold)) {
                this.isPanning = true;
            }

            if (this.isPanning) {
                const svg = this.$refs.svgMap;
                const rect = svg.getBoundingClientRect();
                const scaleX = this.vbW / rect.width;
                const scaleY = this.vbH / rect.height;
                this.vbX = this.panStartVbX - (e.clientX - this.panStartX) * scaleX;
                this.vbY = this.panStartVbY - (e.clientY - this.panStartY) * scaleY;
            }

            // Tooltip
            const row = e.target.closest('[data-row-id]');
            if (row) {
                const rowId = row.dataset.rowId;
                const info = this.rowInfo[rowId];
                if (info) {
                    let text = info.section + ' — Rând ' + info.label + ' (' + info.seatCount + ' locuri)';
                    const assigns = this.assignments[rowId];
                    if (assigns && assigns.length > 0) {
                        text += '\\n' + assigns.map(a => a.name).join(', ');
                    } else {
                        text += '\\nNeatribuit';
                    }
                    this.tip = text;
                    this.tipX = e.clientX + 14;
                    this.tipY = e.clientY - 10;
                    this.showTip = true;
                }
            } else {
                this.showTip = false;
            }
        },

        handleMouseUp(e) {
            if (!this.isPanning) {
                // It was a click, not a drag — check if a row was clicked
                const row = e.target.closest('[data-row-id]');
                if (row) {
                    this.toggleRow(row.dataset.rowId);
                }
            }
            this.isPanning = false;
        },

        resetZoom() {
            this.vbX = 0;
            this.vbY = 0;
            this.vbW = this.origW;
            this.vbH = this.origH;
        },

        // Summary: rows per ticket type
        getAssignmentSummary() {
            const summary = {};
            this.ticketTypes.forEach(tt => {
                summary[tt.id] = { name: tt.name, color: tt.color, rows: [], totalSeats: 0 };
            });
            for (const [rowId, assigns] of Object.entries(this.assignments)) {
                assigns.forEach(a => {
                    if (summary[a.id]) {
                        const info = this.rowInfo[rowId];
                        if (info) {
                            summary[a.id].rows.push({ rowId, section: info.section, label: info.label, seatCount: info.seatCount });
                            summary[a.id].totalSeats += info.seatCount;
                        }
                    }
                });
            }
            return Object.values(summary);
        }
    }"
    x-init="$watch('assignments', () => {})"
    class="space-y-3"
    wire:ignore.self
>
    {{-- Ticket Type Selector --}}
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs text-gray-500 mr-1">Tip bilet activ:</span>
        <template x-for="tt in ticketTypes" :key="tt.id">
            <button type="button"
                @click="selectedTT = tt.id"
                :class="selectedTT === tt.id
                    ? 'ring-2 ring-offset-1 ring-offset-gray-900'
                    : 'opacity-60 hover:opacity-90'"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white transition-all"
                :style="'background:' + tt.color + '; --tw-ring-color:' + tt.color"
            >
                <span x-text="tt.name"></span>
                <span class="text-xs opacity-70" x-text="tt.price > 0 ? (tt.price.toFixed(2) + ' ' + tt.currency) : ''"></span>
            </button>
        </template>
        <span x-show="saving" class="ml-2 text-xs text-yellow-400 animate-pulse">Salvare...</span>
    </div>

    {{-- Zoom controls --}}
    <div class="flex items-center gap-2">
        <button type="button" @click="handleWheel({preventDefault(){}, deltaY: -100, clientX: $refs.svgMap.getBoundingClientRect().width/2 + $refs.svgMap.getBoundingClientRect().left, clientY: $refs.svgMap.getBoundingClientRect().height/2 + $refs.svgMap.getBoundingClientRect().top})"
            class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">
            + Zoom In
        </button>
        <button type="button" @click="handleWheel({preventDefault(){}, deltaY: 100, clientX: $refs.svgMap.getBoundingClientRect().width/2 + $refs.svgMap.getBoundingClientRect().left, clientY: $refs.svgMap.getBoundingClientRect().height/2 + $refs.svgMap.getBoundingClientRect().top})"
            class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">
            − Zoom Out
        </button>
        <button type="button" @click="resetZoom()"
            class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">
            Reset
        </button>
        <span class="text-xs text-gray-500" x-text="Math.round(currentZoom * 100) + '%'"></span>
        <span class="ml-auto text-xs text-gray-400">Click rând = asignare. Drag = mută harta. Scroll = zoom.</span>
    </div>

    {{-- SVG Map --}}
    <div class="bg-gray-900 border border-gray-700 rounded-lg overflow-hidden relative">
        <svg
            x-ref="svgMap"
            :viewBox="vbX + ' ' + vbY + ' ' + vbW + ' ' + vbH"
            preserveAspectRatio="xMidYMid meet"
            class="w-full bg-gray-950 select-none"
            style="height: 450px; max-height: 550px; cursor: grab;"
            :style="isPanning ? 'cursor: grabbing' : 'cursor: grab'"
            @wheel.prevent="handleWheel($event)"
            @mousedown="handleMouseDown($event)"
            @mousemove="handleMouseMove($event)"
            @mouseup="handleMouseUp($event)"
            @mouseleave="showTip = false; isPanning = false"
        >
            {!! $bgImage !!}

            @foreach($sections as $section)
                @php
                    $sX = $section->x_position ?? 0;
                    $sY = $section->y_position ?? 0;
                    $sW = $section->width ?? 200;
                    $sH = $section->height ?? 150;
                    $rot = $section->rotation ?? 0;
                    $cx = $sX + $sW / 2;
                    $cy = $sY + $sH / 2;
                @endphp

                <g @if($rot != 0) transform="rotate({{ $rot }} {{ $cx }} {{ $cy }})" @endif>
                    {{-- Section boundary --}}
                    <rect x="{{ $sX }}" y="{{ $sY }}" width="{{ $sW }}" height="{{ $sH }}"
                          fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1" rx="4"/>
                    <text x="{{ $sX + 4 }}" y="{{ max(12, $sY - 6) }}"
                          fill="rgba(255,255,255,0.4)" font-size="13" font-weight="600">{{ $section->name }}</text>

                    @foreach($section->rows as $row)
                        <g data-row-id="{{ $row->id }}"
                           class="cursor-pointer"
                           :opacity="getRowOpacity({{ $row->id }})"
                           style="transition: opacity 0.15s"
                        >
                            @foreach($row->seats as $seat)
                                @php
                                    $seatX = $sX + ($seat->x ?? 0);
                                    $seatY = $sY + ($seat->y ?? 0);
                                @endphp
                                @if($seat->status === 'imposibil')
                                    <circle cx="{{ $seatX }}" cy="{{ $seatY }}" r="6"
                                            fill="#1f2937" stroke="#4b5563" stroke-width="0.5"/>
                                @else
                                    <circle cx="{{ $seatX }}" cy="{{ $seatY }}" r="6"
                                            :fill="getRowColor({{ $row->id }})"
                                            stroke="#fff" stroke-width="0.5"
                                            class="transition-colors duration-100"/>
                                @endif
                            @endforeach

                            {{-- Row label --}}
                            @php
                                $firstSeat = $row->seats->first();
                                $labelX = $firstSeat ? $sX + ($firstSeat->x ?? 0) - 16 : $sX;
                                $labelY = $firstSeat ? $sY + ($firstSeat->y ?? 0) : $sY;
                            @endphp
                            <text x="{{ $labelX }}" y="{{ $labelY + 4 }}"
                                  fill="rgba(255,255,255,0.3)" font-size="10" text-anchor="end"
                                  class="pointer-events-none select-none"
                                  :fill="isAssignedToSelected({{ $row->id }}) ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.3)'"
                            >{{ $row->label }}</text>
                        </g>
                    @endforeach
                </g>
            @endforeach
        </svg>

        {{-- Custom tooltip --}}
        <div x-show="showTip" x-cloak
             :style="'left:' + tipX + 'px;top:' + tipY + 'px'"
             class="fixed z-50 pointer-events-none px-3 py-2 text-xs bg-gray-800 border border-gray-600 rounded-lg shadow-xl text-white whitespace-pre-line"
             x-text="tip">
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-2 text-xs">
        <template x-for="tt in ticketTypes" :key="'leg-'+tt.id">
            <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="'background:' + tt.color"></span>
                <span class="text-gray-200" x-text="tt.name"></span>
            </span>
        </template>
        <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full bg-gray-700 flex-shrink-0"></span>
            <span class="text-gray-400">Neatribuit</span>
        </span>
        <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#1f2937;border:1px solid #4b5563"></span>
            <span class="text-gray-400">Blocat</span>
        </span>
    </div>

    {{-- Assignment summary --}}
    <div class="border border-gray-700 rounded-lg overflow-hidden">
        <div class="bg-gray-800/50 px-4 py-2 border-b border-gray-700">
            <span class="text-sm font-medium text-gray-300">Rânduri asignate per tip bilet</span>
        </div>
        <div class="divide-y divide-gray-800">
            <template x-for="item in getAssignmentSummary()" :key="'sum-'+item.name">
                <div class="px-4 py-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full flex-shrink-0" :style="'background:' + item.color"></span>
                            <span class="text-sm font-medium text-white" x-text="item.name"></span>
                        </div>
                        <span class="text-xs text-gray-400" x-text="item.totalSeats + ' locuri'"></span>
                    </div>
                    <div x-show="item.rows.length > 0" class="mt-1.5 ml-5 flex flex-wrap gap-1">
                        <template x-for="r in item.rows" :key="'r-'+r.rowId">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-gray-800 text-gray-300">
                                <span class="text-gray-500" x-text="r.section"></span>
                                <span x-text="'Rând ' + r.label"></span>
                                <span class="text-gray-500" x-text="'(' + r.seatCount + ')'"></span>
                            </span>
                        </template>
                    </div>
                    <div x-show="item.rows.length === 0" class="mt-1 ml-5 text-xs text-gray-600 italic">
                        Nicio asignare
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
@else
    <div class="p-4 text-center text-gray-500 text-sm">
        Nu există o hartă de locuri configurată sau salvată pentru acest eveniment.
    </div>
@endif
