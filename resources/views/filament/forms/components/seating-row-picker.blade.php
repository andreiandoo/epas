@php
    $statePath = $getStatePath();
    $colorPath = str_replace('.seatingRows', '.color', $statePath);

    // Load layout data
    $formData = $getLivewire()->data ?? [];
    $layoutId = $formData['seating_layout_id'] ?? null;

    $layout = null;
    $sections = collect();
    $canvasW = 1920;
    $canvasH = 1080;
    $bgImage = '';

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

    // Load other ticket types' row assignments for context
    $otherRowColors = [];
    $eventId = $formData['id'] ?? null;
    if ($eventId && $layoutId) {
        $otherTicketTypes = \App\Models\TicketType::where('event_id', $eventId)
            ->with('seatingRows')
            ->get();
        foreach ($otherTicketTypes as $tt) {
            foreach ($tt->seatingRows as $row) {
                if (!isset($otherRowColors[$row->id])) {
                    $otherRowColors[$row->id] = [];
                }
                $otherRowColors[$row->id][] = [
                    'name' => $tt->name,
                    'color' => $tt->color ?? '#6b7280',
                ];
            }
        }
    }

    // Prepare row info as JSON for Alpine
    $rowInfoMap = [];
    foreach ($sections as $section) {
        foreach ($section->rows as $row) {
            $rowInfoMap[$row->id] = [
                'section' => $section->name,
                'label' => $row->label,
                'seatCount' => $row->seat_count,
            ];
        }
    }

    // JSON-encode the other assignments for Alpine
    $otherRowColorsJson = json_encode($otherRowColors);
    $rowInfoJson = json_encode($rowInfoMap);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    @if($layout && $sections->isNotEmpty())
        <div
            x-data="{
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                get color() { return $wire.{{ "\$entangle('{$colorPath}')" }} || '#3b82f6' },
                otherColors: {{ $otherRowColorsJson }},
                rowInfo: {{ $rowInfoJson }},
                tip: '',
                tipX: 0,
                tipY: 0,
                showTip: false,
                toggleRow(rowId) {
                    rowId = Number(rowId);
                    let current = Array.isArray(this.state) ? [...this.state] : [];
                    const idx = current.findIndex(id => Number(id) === rowId);
                    if (idx > -1) {
                        current.splice(idx, 1);
                    } else {
                        current.push(rowId);
                    }
                    this.state = current;
                },
                isSelected(rowId) {
                    if (!Array.isArray(this.state)) return false;
                    return this.state.some(id => Number(id) === Number(rowId));
                },
                getRowColor(rowId) {
                    if (this.isSelected(rowId)) return this.color || '#3b82f6';
                    const other = this.otherColors[rowId];
                    if (other && other.length > 0) return other[0].color;
                    return '#374151';
                },
                getRowOpacity(rowId) {
                    if (this.isSelected(rowId)) return 1;
                    const other = this.otherColors[rowId];
                    if (other && other.length > 0) return 0.35;
                    return 0.5;
                },
                get selectedCount() {
                    return Array.isArray(this.state) ? this.state.length : 0;
                },
                get selectedSeats() {
                    if (!Array.isArray(this.state)) return 0;
                    let total = 0;
                    this.state.forEach(id => {
                        const info = this.rowInfo[Number(id)];
                        if (info) total += info.seatCount;
                    });
                    return total;
                },
                handleHover(e) {
                    const row = e.target.closest('[data-row-id]');
                    if (row) {
                        const rowId = row.dataset.rowId;
                        const info = this.rowInfo[rowId];
                        if (info) {
                            let text = info.section + ' — Rând ' + info.label + ' (' + info.seatCount + ' locuri)';
                            const other = this.otherColors[rowId];
                            if (other && other.length > 0) {
                                text += '\n' + other.map(o => o.name).join(', ');
                            }
                            this.tip = text;
                            this.tipX = e.clientX + 14;
                            this.tipY = e.clientY - 10;
                            this.showTip = true;
                        }
                    } else {
                        this.showTip = false;
                    }
                }
            }"
            class="space-y-2"
        >
            {{-- Summary --}}
            <div class="flex items-center justify-between">
                <span class="text-sm text-gray-300">
                    Click pe rânduri pentru a selecta/deselecta.
                    Selectate: <span class="font-bold" :style="'color:' + (color || '#3b82f6')" x-text="selectedCount"></span>
                    <span class="text-gray-500">(<span x-text="selectedSeats"></span> locuri)</span>
                </span>
                <div class="flex items-center gap-3 text-xs text-gray-500">
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-full" :style="'background:' + (color || '#3b82f6')"></span> Selectate
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-full bg-gray-600 opacity-40"></span> Alte tipuri
                    </span>
                    <span class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-full bg-gray-700"></span> Neasignate
                    </span>
                </div>
            </div>

            {{-- SVG Map --}}
            <div class="bg-gray-900 border border-gray-700 rounded-lg p-2 relative">
                <svg
                    viewBox="0 0 {{ $canvasW }} {{ $canvasH }}"
                    preserveAspectRatio="xMidYMid meet"
                    class="w-full bg-gray-950 rounded border border-gray-800"
                    style="height: 350px; max-height: 450px;"
                    @mousemove="handleHover($event)"
                    @mouseleave="showTip = false"
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
                                   @click="toggleRow({{ $row->id }})"
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
                                          fill="rgba(255,255,255,0.5)" font-size="10" text-anchor="end"
                                          class="pointer-events-none select-none"
                                          :fill="isSelected({{ $row->id }}) ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.3)'"
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
        </div>
    @else
        <div class="p-4 text-center text-gray-500 text-sm">
            Nu există o hartă de locuri configurată pentru acest eveniment.
        </div>
    @endif
</x-dynamic-component>
