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
                if (!isset($rowAssignments[$row->id])) $rowAssignments[$row->id] = [];
                $rowAssignments[$row->id][] = ['id' => $tt->id, 'name' => $tt->name, 'color' => $tt->color ?? '#6b7280'];
            }
        }
    }

    foreach ($sections as $section) {
        foreach ($section->rows as $row) {
            $rowInfoMap[$row->id] = ['section' => $section->name, 'label' => $row->label, 'seatCount' => $row->seat_count];
        }
    }

    $ticketTypesJson = json_encode($ticketTypesData);
    $rowAssignmentsJson = json_encode((object) $rowAssignments);
    $rowInfoJson = json_encode((object) $rowInfoMap);
    $firstTTId = !empty($ticketTypesData) ? $ticketTypesData[0]['id'] : 'null';
@endphp

@if($layout && $sections->isNotEmpty())
<div
    wire:ignore
    x-data="{
        TT: {{ $ticketTypesJson }},
        sel: {{ $firstTTId }},
        A: {{ $rowAssignmentsJson }},
        RI: {{ $rowInfoJson }},
        mode: 'view',
        saving: false,
        vbX: 0, vbY: 0, vbW: {{ $canvasW }}, vbH: {{ $canvasH }},
        OW: {{ $canvasW }}, OH: {{ $canvasH }},
        md: false, pan: false, mdRow: null, mdX: 0, mdY: 0, psX: 0, psY: 0, pvX: 0, pvY: 0,
        tip: '', tipX: 0, tipY: 0, showTip: false,

        get zoom() { return Math.round((this.OW / this.vbW) * 100) },

        syncVB() {
            this.$refs.svg.setAttribute('viewBox', this.vbX + ' ' + this.vbY + ' ' + this.vbW + ' ' + this.vbH);
        },

        rc(rid) {
            let a = this.A[rid];
            if (a && a.length) { let s = a.find(x => Number(x.id) === Number(this.sel)); if (s) return s.color; return a[0].color; }
            return '#374151';
        },
        ro(rid) {
            let a = this.A[rid];
            if (!a || !a.length) return 0.5;
            if (this.mode === 'assign') { return a.find(x => Number(x.id) === Number(this.sel)) ? 1 : 0.35; }
            return 0.8;
        },
        isSel(rid) { let a = this.A[rid]; return a ? a.some(x => Number(x.id) === Number(this.sel)) : false; },

        zoomAt(cx, cy, f) {
            let r = this.$refs.svg.getBoundingClientRect();
            let mx = ((cx - r.left) / r.width) * this.vbW + this.vbX;
            let my = ((cy - r.top) / r.height) * this.vbH + this.vbY;
            let nw = this.vbW / f, nh = this.vbH / f;
            let z = this.OW / nw;
            if (z < 0.3 || z > 5) return;
            this.vbX = mx - (mx - this.vbX) / f;
            this.vbY = my - (my - this.vbY) / f;
            this.vbW = nw; this.vbH = nh;
            this.syncVB();
        },
        zoomCenter(f) {
            let r = this.$refs.svg.getBoundingClientRect();
            this.zoomAt(r.left + r.width/2, r.top + r.height/2, f);
        },
        resetZoom() {
            this.vbX = 0; this.vbY = 0; this.vbW = this.OW; this.vbH = this.OH;
            this.syncVB();
        },

        toggle(rid) {
            if (this.mode !== 'assign' || !this.sel) return;
            rid = Number(rid);
            this.saving = true;

            let el = this.$el.closest('[wire\\:id]');
            if (!el) { this.saving = false; return; }
            let wid = el.getAttribute('wire:id');
            if (!wid || !window.Livewire) { this.saving = false; return; }
            let comp = window.Livewire.find(wid);
            if (!comp) { this.saving = false; return; }

            comp.$wire.toggleSeatingRowAssignment(this.sel, rid).then(ok => {
                if (ok !== false) {
                    if (!this.A[rid]) this.A[rid] = [];
                    let idx = this.A[rid].findIndex(x => Number(x.id) === Number(this.sel));
                    if (idx > -1) {
                        this.A[rid].splice(idx, 1);
                        if (!this.A[rid].length) delete this.A[rid];
                    } else {
                        let t = this.TT.find(x => Number(x.id) === Number(this.sel));
                        this.A[rid] = [...(this.A[rid] || []), {id: this.sel, name: t ? t.name : '', color: t ? t.color : '#6b7280'}];
                    }
                }
                this.saving = false;
            }).catch(() => { this.saving = false; });
        },

        enterAssign() { this.mode = 'assign'; },
        exitAssign() { this.mode = 'view'; },

        hoverTip(e) {
            if (this.md) { this.showTip = false; return; }
            let rg = e.target.closest('[data-row-id]');
            if (rg) {
                let rid = rg.dataset.rowId, info = this.RI[rid];
                if (info) {
                    let t = info.section + ' \u2014 R\u00e2nd ' + info.label + ' (' + info.seatCount + ' locuri)';
                    let a = this.A[rid];
                    if (a && a.length) t += String.fromCharCode(10) + a.map(x => x.name).join(', ');
                    else t += String.fromCharCode(10) + 'Neatribuit';
                    this.tip = t; this.tipX = e.clientX + 14; this.tipY = e.clientY - 10; this.showTip = true;
                } else { this.showTip = false; }
            } else { this.showTip = false; }
        },

        summaryFor(ttId) {
            ttId = Number(ttId);
            let rows = [], seats = 0;
            let keys = Object.keys(this.A);
            for (let k = 0; k < keys.length; k++) {
                let rid = keys[k];
                let arr = this.A[rid];
                if (arr && arr.length) {
                    for (let j = 0; j < arr.length; j++) {
                        if (Number(arr[j].id) === ttId) {
                            let i = this.RI[rid];
                            if (i) { rows.push({rid: rid, section: i.section, label: i.label, seatCount: i.seatCount}); seats += i.seatCount; }
                            break;
                        }
                    }
                }
            }
            return { rows, seats };
        }
    }"
    x-init="
        let svg = $refs.svg;
        svg.addEventListener('wheel', (e) => {
            e.preventDefault(); e.stopPropagation();
            zoomAt(e.clientX, e.clientY, e.deltaY < 0 ? 1.15 : 1/1.15);
        }, {passive: false});
        svg.addEventListener('mousedown', (e) => {
            if (e.button !== 0) return;
            md = true; pan = false;
            mdX = e.clientX; mdY = e.clientY;
            psX = e.clientX; psY = e.clientY;
            pvX = vbX; pvY = vbY;
            let rg = e.target.closest('[data-row-id]');
            mdRow = rg ? rg.dataset.rowId : null;
        });
        window.addEventListener('mousemove', (e) => {
            if (!md) return;
            if (mode === 'assign' && mdRow) return;
            let dx = e.clientX - mdX, dy = e.clientY - mdY;
            if (!pan && (Math.abs(dx) > 5 || Math.abs(dy) > 5)) pan = true;
            if (pan) {
                let r = svg.getBoundingClientRect();
                vbX = pvX - (e.clientX - psX) * (vbW / r.width);
                vbY = pvY - (e.clientY - psY) * (vbH / r.height);
                svg.setAttribute('viewBox', vbX + ' ' + vbY + ' ' + vbW + ' ' + vbH);
            }
        });
        window.addEventListener('mouseup', (e) => {
            if (!md) return;
            if (mode === 'assign' && mdRow) {
                toggle(mdRow);
            } else if (!pan) {
                let el = document.elementFromPoint(e.clientX, e.clientY);
                if (el && svg.contains(el)) {
                    let rg = el.closest('[data-row-id]');
                    if (rg) toggle(rg.dataset.rowId);
                }
            }
            md = false; pan = false; mdRow = null;
        });
    "
    class="space-y-3"
>
    {{-- Toolbar --}}
    <div class="flex items-center gap-3">
        <button type="button" x-show="mode==='view'" x-on:click="enterAssign()"
            class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-200 border border-gray-600 transition-all">
            <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Aloc&#259; bilete
        </button>
        <div class="flex items-center gap-2 ml-auto">
            <button type="button" x-on:click="zoomCenter(1.3)" class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">+ Zoom</button>
            <button type="button" x-on:click="zoomCenter(1/1.3)" class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">&minus; Zoom</button>
            <button type="button" x-on:click="resetZoom()" class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">Reset</button>
            <span class="text-xs text-gray-500" x-text="zoom + '%'"></span>
        </div>
    </div>

    {{-- Ticket type selector (assign mode only) --}}
    <div x-show="mode==='assign'" x-cloak>
        <div class="flex flex-wrap items-center gap-2 p-3 bg-gray-800/60 border border-gray-700 rounded-lg">
            <span class="text-xs text-gray-400 mr-1">Selecteaz&#259; tip bilet, apoi click pe r&#226;nduri:</span>
            <template x-for="tt in TT" :key="tt.id">
                <button type="button" x-on:click="sel = tt.id"
                    :class="sel === tt.id ? 'ring-2 ring-offset-1 ring-offset-gray-900' : 'opacity-60 hover:opacity-90'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-medium text-white transition-all"
                    :style="`background:${tt.color}; --tw-ring-color:${tt.color}`">
                    <span x-text="tt.name"></span>
                    <span class="text-xs opacity-70" x-text="tt.price > 0 ? (tt.price.toFixed(2)+' '+tt.currency) : ''"></span>
                </button>
            </template>
            <button type="button" x-on:click="exitAssign()"
                class="ml-auto px-3 py-1.5 text-xs font-medium rounded-lg bg-green-700 hover:bg-green-600 text-white transition">Terminat</button>
            <span x-show="saving" class="ml-2 text-xs text-yellow-400 animate-pulse">Salvare...</span>
        </div>
    </div>

    {{-- SVG Map --}}
    <div class="bg-gray-900 border border-gray-700 rounded-lg overflow-hidden relative">
        <svg x-ref="svg"
            viewBox="0 0 {{ $canvasW }} {{ $canvasH }}"
            preserveAspectRatio="xMidYMid meet"
            class="w-full bg-gray-950 select-none"
            style="height: calc(100vh - 300px); min-height: 500px;"
            :style="pan ? 'cursor:grabbing' : (mode==='assign' ? 'cursor:crosshair' : 'cursor:grab')"
            x-on:mousemove="hoverTip($event)"
            x-on:mouseleave="showTip = false"
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
                    <rect x="{{ $sX }}" y="{{ $sY }}" width="{{ $sW }}" height="{{ $sH }}"
                          fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="1" rx="4"/>
                    <text x="{{ $sX + 4 }}" y="{{ max(12, $sY - 6) }}"
                          fill="rgba(255,255,255,0.4)" font-size="13" font-weight="600">{{ $section->name }}</text>

                    @foreach($section->rows as $row)
                        <g data-row-id="{{ $row->id }}" style="transition: opacity 0.15s"
                           :opacity="ro({{ $row->id }})">
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
                                            :fill="rc({{ $row->id }})"
                                            stroke="#fff" stroke-width="0.5"
                                            class="transition-colors duration-100"/>
                                @endif
                            @endforeach

                            @php
                                $firstSeat = $row->seats->first();
                                $labelX = $firstSeat ? $sX + ($firstSeat->x ?? 0) - 16 : $sX;
                                $labelY = $firstSeat ? $sY + ($firstSeat->y ?? 0) : $sY;
                            @endphp
                            <text x="{{ $labelX }}" y="{{ $labelY + 4 }}"
                                  font-size="10" text-anchor="end"
                                  class="pointer-events-none select-none"
                                  :fill="isSel({{ $row->id }}) ? 'rgba(255,255,255,0.9)' : 'rgba(255,255,255,0.3)'"
                            >{{ $row->label }}</text>
                        </g>
                    @endforeach
                </g>
            @endforeach
        </svg>

        {{-- Tooltip --}}
        <div x-show="showTip" x-cloak
             :style="`left:${tipX}px;top:${tipY}px`"
             class="fixed z-50 pointer-events-none px-3 py-2 text-xs bg-gray-800 border border-gray-600 rounded-lg shadow-xl text-white whitespace-pre-line"
             x-text="tip"></div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-2 text-xs">
        <template x-for="tt in TT" :key="'l'+tt.id">
            <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${tt.color}`"></span>
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
            <span class="text-sm font-medium text-gray-300">R&#226;nduri asignate per tip bilet</span>
        </div>
        <div class="divide-y divide-gray-800">
            <template x-for="tt in TT" :key="'s'+tt.id">
                <div class="px-4 py-2.5">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full flex-shrink-0" :style="`background:${tt.color}`"></span>
                            <span class="text-sm font-medium text-white" x-text="tt.name"></span>
                        </div>
                        <span class="text-xs text-gray-400" x-text="summaryFor(tt.id).seats + ' locuri'"></span>
                    </div>
                    <div x-show="summaryFor(tt.id).rows.length > 0" class="mt-1.5 ml-5 flex flex-wrap gap-1">
                        <template x-for="r in summaryFor(tt.id).rows" :key="r.rid">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-gray-800 text-gray-300">
                                <span class="text-gray-500" x-text="r.section"></span>
                                <span x-text="'R&#226;nd ' + r.label"></span>
                                <span class="text-gray-500" x-text="'(' + r.seatCount + ')'"></span>
                            </span>
                        </template>
                    </div>
                    <div x-show="summaryFor(tt.id).rows.length === 0" class="mt-1 ml-5 text-xs text-gray-600 italic">Nicio asignare</div>
                </div>
            </template>
        </div>
    </div>
</div>
@else
<div class="p-4 text-center text-gray-500 text-sm">
    Nu exist&#259; o hart&#259; de locuri configurat&#259; sau salvat&#259; pentru acest eveniment.
</div>
@endif
