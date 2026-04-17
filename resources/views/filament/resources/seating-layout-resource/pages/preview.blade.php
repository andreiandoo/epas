<x-filament-panels::page>
    @php
        $layout = $this->seatingLayout ?? \App\Models\Seating\SeatingLayout::withoutGlobalScopes()->findOrFail($this->layoutId);
        $sections = \App\Models\Seating\SeatingSection::withoutGlobalScopes()
            ->where('layout_id', $layout->id)
            ->where('section_type', 'standard')
            ->orderBy('display_order')
            ->with('rows.seats')
            ->get();
        $textLayers = \App\Models\Seating\SeatingSection::withoutGlobalScopes()
            ->where('layout_id', $layout->id)
            ->where('section_type', 'decorative')
            ->get()
            ->filter(fn ($s) => ($s->metadata['shape'] ?? '') === 'text');
        $canvasW = $layout->canvas_w ?? 1920;
        $canvasH = $layout->canvas_h ?? 1080;

        $bgImage = '';
        $bgPath = $layout->background_image_path ?? $layout->background_image_url;
        if ($bgPath) {
            $bgUrl = asset('storage/' . $bgPath);
            $bgImage = "<image href=\"{$bgUrl}\" x=\"0\" y=\"0\" width=\"{$canvasW}\" height=\"{$canvasH}\" preserveAspectRatio=\"xMidYMid meet\" opacity=\"0.3\"/>";
        }
    @endphp

    <div class="space-y-3">
        {{-- SVG Map --}}
        <div class="overflow-hidden relative" style="background-color:#ffffff;">
            <svg viewBox="0 0 {{ $canvasW }} {{ $canvasH }}"
                preserveAspectRatio="xMidYMid meet"
                class="w-full select-none"
                style="background-color:#ffffff;height:calc(100vh - 200px);min-height:500px;">
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
                        $seatRadius = (($section->metadata['seat_size'] ?? 15) / 2);
                        $seatFontSize = round($seatRadius * 0.95, 1);

                        // Compute section-wide seat X bounds and gap for aligned row labels
                        $allSeatXs = [];
                        $seatGap = $seatRadius * 3;
                        foreach ($section->rows as $_r) {
                            foreach ($_r->seats as $_s) {
                                $allSeatXs[] = $_s->x ?? 0;
                            }
                            if ($seatGap === $seatRadius * 3) {
                                $sortedXs = $_r->seats->pluck('x')->sort()->values();
                                if ($sortedXs->count() >= 2) {
                                    $seatGap = abs($sortedXs[1] - $sortedXs[0]);
                                }
                            }
                        }
                        $secMinX = !empty($allSeatXs) ? min($allSeatXs) : 0;
                        $secMaxX = !empty($allSeatXs) ? max($allSeatXs) : 0;
                        $leftLabelX = $sX + $secMinX - $seatGap;
                        $rightLabelX = $sX + $secMaxX + $seatGap;
                        $rowLabelSize = max(10, round($seatFontSize * 1.1, 1));
                    @endphp

                    <g @if($rot != 0) transform="rotate({{ $rot }} {{ $cx }} {{ $cy }})" @endif>
                        @foreach($section->rows as $row)
                            @php
                                $rowMeta = $row->metadata ?? [];
                                $isTable = $rowMeta['is_table'] ?? false;
                            @endphp
                            <g>
                                {{-- Draw table shape if this row is a table --}}
                                @if($isTable)
                                    @php
                                        $tableType = $rowMeta['table_type'] ?? 'round';
                                        $tableCx = $sX + ($rowMeta['center_x'] ?? 0);
                                        $tableCy = $sY + ($rowMeta['center_y'] ?? 0);
                                        $tableColor = $section->background_color ?? '#6B7280';
                                    @endphp
                                    @if($tableType === 'round')
                                        @php $tableRadius = $rowMeta['radius'] ?? 30; @endphp
                                        <circle cx="{{ $tableCx }}" cy="{{ $tableCy }}" r="{{ $tableRadius }}"
                                                fill="{{ $tableColor }}" fill-opacity="0.3"
                                                stroke="{{ $tableColor }}" stroke-width="1.5" stroke-opacity="0.6"/>
                                    @else
                                        @php
                                            $tableW = $rowMeta['width'] ?? 80;
                                            $tableH = $rowMeta['height'] ?? 30;
                                            $tableRx = $tableCx - $tableW / 2;
                                            $tableRy = $tableCy - $tableH / 2;
                                        @endphp
                                        <rect x="{{ $tableRx }}" y="{{ $tableRy }}"
                                              width="{{ $tableW }}" height="{{ $tableH }}" rx="4"
                                              fill="{{ $tableColor }}" fill-opacity="0.3"
                                              stroke="{{ $tableColor }}" stroke-width="1.5" stroke-opacity="0.6"/>
                                    @endif
                                    {{-- Table label --}}
                                    <text x="{{ $tableCx }}" y="{{ $tableCy + 4 }}"
                                          font-size="{{ max(10, $seatFontSize) }}" text-anchor="middle" font-weight="700"
                                          fill="rgba(0,0,0,0.5)" class="pointer-events-none select-none">{{ $row->label }}</text>
                                @endif

                                @foreach($row->seats as $seat)
                                    @php
                                        $seatX = $sX + ($seat->x ?? 0);
                                        $seatY = $sY + ($seat->y ?? 0);
                                    @endphp
                                    @if($seat->status === 'imposibil')
                                        <circle cx="{{ $seatX }}" cy="{{ $seatY }}" r="{{ $seatRadius }}"
                                                fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.5"/>
                                    @else
                                        <circle cx="{{ $seatX }}" cy="{{ $seatY }}" r="{{ $seatRadius }}"
                                                fill="{{ $section->seat_color ?? '#22C55E' }}" stroke="#fff" stroke-width="0.5"/>
                                        <text x="{{ $seatX }}" y="{{ $seatY + $seatRadius * 0.4 }}"
                                              font-size="{{ $seatFontSize }}" text-anchor="middle" font-weight="600"
                                              fill="rgba(255,255,255,0.9)"
                                              class="pointer-events-none select-none">{{ $seat->label }}</text>
                                    @endif
                                @endforeach

                                {{-- Row labels (only for non-table rows, and only when section opts in) --}}
                                @if(!$isTable && ($section->metadata['auto_show_row_labels'] ?? true))
                                    @php
                                        $firstSeat = $row->seats->first();
                                        $rowLabelY = $firstSeat ? $sY + ($firstSeat->y ?? 0) + $seatRadius * 0.4 : $sY;
                                    @endphp
                                    <text x="{{ $leftLabelX }}" y="{{ $rowLabelY }}"
                                          font-size="{{ $rowLabelSize }}" text-anchor="end" font-weight="600"
                                          fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">{{ $row->label }}</text>
                                    <text x="{{ $rightLabelX }}" y="{{ $rowLabelY }}"
                                          font-size="{{ $rowLabelSize }}" text-anchor="start" font-weight="600"
                                          fill="rgba(0,0,0,0.7)" class="pointer-events-none select-none">{{ $row->label }}</text>
                                @endif
                            </g>
                        @endforeach
                    </g>
                @endforeach

                {{-- Text layers --}}
                @foreach($textLayers as $tl)
                    @php
                        $tlX = $tl->x_position ?? 0;
                        $tlY = $tl->y_position ?? 0;
                        $tlMeta = $tl->metadata ?? [];
                        $tlText = $tlMeta['text'] ?? '';
                        $tlFontSize = $tlMeta['fontSize'] ?? 16;
                        $tlFontFamily = $tlMeta['fontFamily'] ?? 'Arial';
                        $tlFontWeight = $tlMeta['fontWeight'] ?? 'normal';
                        $tlColor = $tl->background_color ?? '#000000';
                        $tlRot = $tl->rotation ?? 0;
                    @endphp
                    <text x="{{ $tlX }}" y="{{ $tlY + $tlFontSize }}"
                          font-size="{{ $tlFontSize }}" font-family="{{ $tlFontFamily }}"
                          font-weight="{{ $tlFontWeight }}" fill="{{ $tlColor }}"
                          @if($tlRot != 0) transform="rotate({{ $tlRot }} {{ $tlX }} {{ $tlY }})" @endif
                          class="pointer-events-none select-none">{{ $tlText }}</text>
                @endforeach
            </svg>
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap gap-2 px-3 text-xs">
            @foreach($sections as $section)
                @foreach($section->rows as $row)
                    @php
                        $seatCount = $row->seats->where('status', '!=', 'imposibil')->count();
                    @endphp
                @endforeach
            @endforeach
            <span class="text-gray-400">{{ $sections->sum(fn ($s) => $s->rows->sum(fn ($r) => $r->seats->where('status', '!=', 'imposibil')->count())) }} locuri total</span>
            <span class="text-gray-500">|</span>
            <span class="text-gray-400">{{ $sections->count() }} secțiuni</span>
            <span class="text-gray-500">|</span>
            <span class="text-gray-400">{{ $sections->sum(fn ($s) => $s->rows->count()) }} rânduri</span>
        </div>
    </div>
</x-filament-panels::page>
