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
<div id="seating-map-editor-root" class="space-y-3" wire:ignore>
    {{-- Mode toggle + controls bar --}}
    <div id="sme-toolbar" class="flex items-center gap-3">
        <button type="button" id="sme-btn-assign"
            class="px-4 py-2 text-sm font-medium rounded-lg transition-all bg-gray-700 hover:bg-gray-600 text-gray-200 border border-gray-600">
            <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Alocă bilete
        </button>
        <div id="sme-zoom-controls" class="flex items-center gap-2 ml-auto">
            <button type="button" id="sme-zoom-in"
                class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">+ Zoom</button>
            <button type="button" id="sme-zoom-out"
                class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">− Zoom</button>
            <button type="button" id="sme-zoom-reset"
                class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">Reset</button>
            <span id="sme-zoom-pct" class="text-xs text-gray-500">100%</span>
        </div>
    </div>

    {{-- Ticket type selector (hidden by default, shown in assign mode) --}}
    <div id="sme-tt-selector" class="hidden">
        <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-800/60 border border-gray-700 rounded-lg">
            <span class="text-xs text-gray-400 mr-1">Selectează tip bilet, apoi click pe rânduri:</span>
            <div id="sme-tt-chips" class="flex flex-wrap gap-2"></div>
            <button type="button" id="sme-btn-done"
                class="ml-auto px-3 py-1.5 text-xs font-medium rounded-lg bg-green-700 hover:bg-green-600 text-white transition">
                Terminat
            </button>
            <span id="sme-saving" class="hidden ml-2 text-xs text-yellow-400 animate-pulse">Salvare...</span>
        </div>
    </div>

    {{-- SVG Map container --}}
    <div class="bg-gray-900 border border-gray-700 rounded-lg overflow-hidden relative" id="sme-map-wrap">
        <svg id="sme-svg"
            viewBox="0 0 {{ $canvasW }} {{ $canvasH }}"
            preserveAspectRatio="xMidYMid meet"
            class="w-full bg-gray-950 select-none"
            style="height: 70vh; min-height: 500px; max-height: 800px; cursor: grab;"
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
                        <g data-row-id="{{ $row->id }}" class="cursor-pointer" style="transition: opacity 0.15s">
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
                                            data-row-color="{{ $row->id }}"
                                            fill="#374151"
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
                                  data-row-label="{{ $row->id }}"
                                  class="pointer-events-none select-none">{{ $row->label }}</text>
                        </g>
                    @endforeach
                </g>
            @endforeach
        </svg>

        {{-- Tooltip --}}
        <div id="sme-tooltip" class="fixed z-50 pointer-events-none px-3 py-2 text-xs bg-gray-800 border border-gray-600 rounded-lg shadow-xl text-white whitespace-pre-line" style="display:none;"></div>
    </div>

    {{-- Legend --}}
    <div id="sme-legend" class="flex flex-wrap gap-2 text-xs"></div>

    {{-- Assignment summary --}}
    <div id="sme-summary" class="border border-gray-700 rounded-lg overflow-hidden">
        <div class="bg-gray-800/50 px-4 py-2 border-b border-gray-700">
            <span class="text-sm font-medium text-gray-300">Rânduri asignate per tip bilet</span>
        </div>
        <div id="sme-summary-body" class="divide-y divide-gray-800"></div>
    </div>
</div>

<script>
(function() {
    // Wait for DOM to be ready
    const root = document.getElementById('seating-map-editor-root');
    if (!root) return;

    // ---- Data ----
    const ticketTypes = {!! $ticketTypesJson !!};
    const assignments = {!! $rowAssignmentsJson !!};
    const rowInfo = {!! $rowInfoJson !!};

    const CANVAS_W = {{ $canvasW }};
    const CANVAS_H = {{ $canvasH }};

    // ---- State ----
    let mode = 'view'; // 'view' or 'assign'
    let selectedTT = ticketTypes.length > 0 ? ticketTypes[0].id : null;
    let saving = false;

    // Zoom/pan
    let vbX = 0, vbY = 0, vbW = CANVAS_W, vbH = CANVAS_H;
    let isMouseDown = false, isPanning = false;
    let mouseDownX = 0, mouseDownY = 0;
    let panStartX = 0, panStartY = 0, panStartVbX = 0, panStartVbY = 0;
    const DRAG_THRESHOLD = 5;
    const MIN_ZOOM = 0.3, MAX_ZOOM = 5;

    // ---- DOM refs ----
    const svg = document.getElementById('sme-svg');
    const tooltip = document.getElementById('sme-tooltip');
    const ttSelector = document.getElementById('sme-tt-selector');
    const ttChips = document.getElementById('sme-tt-chips');
    const btnAssign = document.getElementById('sme-btn-assign');
    const btnDone = document.getElementById('sme-btn-done');
    const savingEl = document.getElementById('sme-saving');
    const zoomPct = document.getElementById('sme-zoom-pct');
    const legendEl = document.getElementById('sme-legend');
    const summaryBody = document.getElementById('sme-summary-body');

    // Find $wire (Livewire component)
    function getWire() {
        const el = root.closest('[wire\\:id]');
        if (el && el.__livewire) return el.__livewire;
        // Fallback: search up from root
        let node = root;
        while (node) {
            if (node.__livewire) return node.__livewire;
            node = node.parentElement;
        }
        return null;
    }

    // ---- Rendering ----
    function updateViewBox() {
        svg.setAttribute('viewBox', vbX + ' ' + vbY + ' ' + vbW + ' ' + vbH);
        const zoom = Math.round((CANVAS_W / vbW) * 100);
        zoomPct.textContent = zoom + '%';
    }

    function getRowColor(rowId) {
        const assigns = assignments[rowId];
        if (assigns && assigns.length > 0) {
            if (mode === 'assign' && selectedTT) {
                const sel = assigns.find(a => a.id === selectedTT);
                if (sel) return sel.color;
            }
            return assigns[0].color;
        }
        return '#374151';
    }

    function getRowOpacity(rowId) {
        const assigns = assignments[rowId];
        if (!assigns || assigns.length === 0) return 0.5;
        if (mode === 'assign' && selectedTT) {
            const sel = assigns.find(a => a.id === selectedTT);
            if (sel) return 1;
            return 0.35;
        }
        return 0.8;
    }

    function isAssignedToSelected(rowId) {
        const assigns = assignments[rowId];
        if (!assigns) return false;
        return assigns.some(a => a.id === selectedTT);
    }

    function renderRowColors() {
        // Update all seat circles
        svg.querySelectorAll('[data-row-id]').forEach(g => {
            const rowId = g.dataset.rowId;
            const color = getRowColor(rowId);
            const opacity = getRowOpacity(rowId);
            g.style.opacity = opacity;

            g.querySelectorAll('circle[data-row-color]').forEach(c => {
                c.setAttribute('fill', color);
            });

            // Row label brightness
            const label = g.querySelector('[data-row-label]');
            if (label) {
                const bright = (mode === 'assign') ? isAssignedToSelected(rowId) : (assignments[rowId] && assignments[rowId].length > 0);
                label.setAttribute('fill', bright ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.3)');
            }
        });
    }

    function renderLegend() {
        let html = '';
        ticketTypes.forEach(tt => {
            html += '<span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">';
            html += '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:' + tt.color + '"></span>';
            html += '<span class="text-gray-200">' + tt.name + '</span>';
            html += '</span>';
        });
        html += '<span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">';
        html += '<span class="w-2.5 h-2.5 rounded-full bg-gray-700 flex-shrink-0"></span>';
        html += '<span class="text-gray-400">Neatribuit</span></span>';
        html += '<span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">';
        html += '<span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#1f2937;border:1px solid #4b5563"></span>';
        html += '<span class="text-gray-400">Blocat</span></span>';
        legendEl.innerHTML = html;
    }

    function renderSummary() {
        let html = '';
        ticketTypes.forEach(tt => {
            const rows = [];
            let totalSeats = 0;
            for (const [rowId, assigns] of Object.entries(assignments)) {
                if (assigns.some(a => a.id === tt.id)) {
                    const info = rowInfo[rowId];
                    if (info) {
                        rows.push(info);
                        totalSeats += info.seatCount;
                    }
                }
            }
            html += '<div class="px-4 py-2.5">';
            html += '<div class="flex items-center justify-between">';
            html += '<div class="flex items-center gap-2">';
            html += '<span class="w-3 h-3 rounded-full flex-shrink-0" style="background:' + tt.color + '"></span>';
            html += '<span class="text-sm font-medium text-white">' + tt.name + '</span>';
            html += '</div>';
            html += '<span class="text-xs text-gray-400">' + totalSeats + ' locuri</span>';
            html += '</div>';
            if (rows.length > 0) {
                html += '<div class="mt-1.5 ml-5 flex flex-wrap gap-1">';
                rows.forEach(r => {
                    html += '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-gray-800 text-gray-300">';
                    html += '<span class="text-gray-500">' + r.section + '</span>';
                    html += '<span>Rând ' + r.label + '</span>';
                    html += '<span class="text-gray-500">(' + r.seatCount + ')</span>';
                    html += '</span>';
                });
                html += '</div>';
            } else {
                html += '<div class="mt-1 ml-5 text-xs text-gray-600 italic">Nicio asignare</div>';
            }
            html += '</div>';
        });
        summaryBody.innerHTML = html;
    }

    function renderTTChips() {
        let html = '';
        ticketTypes.forEach(tt => {
            const sel = tt.id === selectedTT;
            const cls = sel
                ? 'ring-2 ring-offset-1 ring-offset-gray-900'
                : 'opacity-60 hover:opacity-90';
            html += '<button type="button" data-tt-id="' + tt.id + '" ';
            html += 'class="sme-tt-chip flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white transition-all ' + cls + '" ';
            html += 'style="background:' + tt.color + '; --tw-ring-color:' + tt.color + '">';
            html += '<span>' + tt.name + '</span>';
            if (tt.price > 0) html += '<span class="text-xs opacity-70">' + tt.price.toFixed(2) + ' ' + tt.currency + '</span>';
            html += '</button>';
        });
        ttChips.innerHTML = html;

        // Bind chip clicks
        ttChips.querySelectorAll('.sme-tt-chip').forEach(btn => {
            btn.addEventListener('click', () => {
                selectedTT = Number(btn.dataset.ttId);
                renderTTChips();
                renderRowColors();
            });
        });
    }

    // ---- Mode toggle ----
    function enterAssignMode() {
        mode = 'assign';
        btnAssign.classList.add('hidden');
        ttSelector.classList.remove('hidden');
        svg.style.cursor = 'crosshair';
        renderTTChips();
        renderRowColors();
    }

    function exitAssignMode() {
        mode = 'view';
        btnAssign.classList.remove('hidden');
        ttSelector.classList.add('hidden');
        svg.style.cursor = 'grab';
        renderRowColors();
    }

    btnAssign.addEventListener('click', enterAssignMode);
    btnDone.addEventListener('click', exitAssignMode);

    // ---- Toggle row assignment ----
    function toggleRow(rowId) {
        if (mode !== 'assign' || !selectedTT) return;
        rowId = Number(rowId);

        saving = true;
        savingEl.classList.remove('hidden');

        const wire = getWire();
        if (!wire) { saving = false; savingEl.classList.add('hidden'); return; }

        wire.call('toggleSeatingRowAssignment', selectedTT, rowId).then(result => {
            if (result !== false) {
                // Update local assignments
                if (!assignments[rowId]) assignments[rowId] = [];
                const idx = assignments[rowId].findIndex(a => a.id === selectedTT);
                if (idx > -1) {
                    assignments[rowId].splice(idx, 1);
                    if (assignments[rowId].length === 0) delete assignments[rowId];
                } else {
                    const tt = ticketTypes.find(t => t.id === selectedTT);
                    assignments[rowId].push({ id: selectedTT, name: tt ? tt.name : '', color: tt ? tt.color : '#6b7280' });
                }
                renderRowColors();
                renderSummary();
            }
            saving = false;
            savingEl.classList.add('hidden');
        }).catch(() => { saving = false; savingEl.classList.add('hidden'); });
    }

    // ---- Zoom ----
    function zoomAt(clientX, clientY, factor) {
        const rect = svg.getBoundingClientRect();
        const mx = ((clientX - rect.left) / rect.width) * vbW + vbX;
        const my = ((clientY - rect.top) / rect.height) * vbH + vbY;

        let newW = vbW / factor;
        let newH = vbH / factor;

        const zoom = CANVAS_W / newW;
        if (zoom < MIN_ZOOM || zoom > MAX_ZOOM) return;

        vbX = mx - (mx - vbX) / factor;
        vbY = my - (my - vbY) / factor;
        vbW = newW;
        vbH = newH;
        updateViewBox();
    }

    function zoomCenter(factor) {
        const rect = svg.getBoundingClientRect();
        zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, factor);
    }

    svg.addEventListener('wheel', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const factor = e.deltaY < 0 ? 1.15 : (1 / 1.15);
        zoomAt(e.clientX, e.clientY, factor);
    }, { passive: false });

    document.getElementById('sme-zoom-in').addEventListener('click', () => zoomCenter(1.3));
    document.getElementById('sme-zoom-out').addEventListener('click', () => zoomCenter(1 / 1.3));
    document.getElementById('sme-zoom-reset').addEventListener('click', () => {
        vbX = 0; vbY = 0; vbW = CANVAS_W; vbH = CANVAS_H;
        updateViewBox();
    });

    // ---- Pan ----
    svg.addEventListener('mousedown', function(e) {
        if (e.button !== 0) return;
        isMouseDown = true;
        isPanning = false;
        mouseDownX = e.clientX;
        mouseDownY = e.clientY;
        panStartX = e.clientX;
        panStartY = e.clientY;
        panStartVbX = vbX;
        panStartVbY = vbY;
    });

    window.addEventListener('mousemove', function(e) {
        // Tooltip (always, if over SVG)
        if (!isMouseDown) {
            const el = document.elementFromPoint(e.clientX, e.clientY);
            if (el && svg.contains(el)) {
                const rowG = el.closest('[data-row-id]');
                if (rowG) {
                    const rowId = rowG.dataset.rowId;
                    const info = rowInfo[rowId];
                    if (info) {
                        let text = info.section + ' \u2014 R\u00e2nd ' + info.label + ' (' + info.seatCount + ' locuri)';
                        const assigns = assignments[rowId];
                        if (assigns && assigns.length > 0) {
                            text += '\n' + assigns.map(a => a.name).join(', ');
                        } else {
                            text += '\nNeatribuit';
                        }
                        tooltip.textContent = text;
                        tooltip.style.left = (e.clientX + 14) + 'px';
                        tooltip.style.top = (e.clientY - 10) + 'px';
                        tooltip.style.display = 'block';
                    } else {
                        tooltip.style.display = 'none';
                    }
                } else {
                    tooltip.style.display = 'none';
                }
            } else {
                tooltip.style.display = 'none';
            }
        } else {
            tooltip.style.display = 'none';
        }

        // Pan
        if (!isMouseDown) return;
        const dx = e.clientX - mouseDownX;
        const dy = e.clientY - mouseDownY;

        if (!isPanning && (Math.abs(dx) > DRAG_THRESHOLD || Math.abs(dy) > DRAG_THRESHOLD)) {
            isPanning = true;
            svg.style.cursor = 'grabbing';
        }

        if (isPanning) {
            const rect = svg.getBoundingClientRect();
            const scaleX = vbW / rect.width;
            const scaleY = vbH / rect.height;
            vbX = panStartVbX - (e.clientX - panStartX) * scaleX;
            vbY = panStartVbY - (e.clientY - panStartY) * scaleY;
            updateViewBox();
        }
    });

    window.addEventListener('mouseup', function(e) {
        if (!isMouseDown) return;

        if (!isPanning) {
            // It was a click, not a drag
            const el = document.elementFromPoint(e.clientX, e.clientY);
            if (el && svg.contains(el)) {
                const rowG = el.closest('[data-row-id]');
                if (rowG) {
                    toggleRow(rowG.dataset.rowId);
                }
            }
        }

        isMouseDown = false;
        isPanning = false;
        svg.style.cursor = (mode === 'assign') ? 'crosshair' : 'grab';
    });

    svg.addEventListener('mouseleave', function() {
        tooltip.style.display = 'none';
    });

    // ---- Initial render ----
    renderRowColors();
    renderLegend();
    renderSummary();
})();
</script>
@else
    <div class="p-4 text-center text-gray-500 text-sm">
        Nu există o hartă de locuri configurată sau salvată pentru acest eveniment.
    </div>
@endif
