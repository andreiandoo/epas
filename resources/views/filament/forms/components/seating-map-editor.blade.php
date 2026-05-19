@php
    // $record is passed from the Placeholder content closure via view('...', ['record' => $record])
    $eventId = $record?->id;
    $layoutId = $record?->seating_layout_id;

    $layout = null;
    $sections = collect();
    $textLayers = collect();
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

            // Load text layers (decorative sections with shape=text)
            $textLayers = \App\Models\Seating\SeatingSection::withoutGlobalScopes()
                ->where('layout_id', $layout->id)
                ->where('section_type', 'decorative')
                ->get()
                ->filter(fn ($s) => ($s->metadata['shape'] ?? '') === 'text');
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

    // Load event-level seat statuses (blocked/sold/held) for display
    $eventSeatStatuses = [];
    $invitationOrgSeatUids = [];
    $invitationAdminSeatUids = [];
    if ($eventId && $layoutId) {
        $eventSeating = \App\Models\Seating\EventSeatingLayout::where('event_id', $eventId)
            ->where('layout_id', $layoutId)
            ->first();
        if ($eventSeating) {
            $eventSeatStatuses = \App\Models\Seating\EventSeat::where('event_seating_id', $eventSeating->id)
                ->whereIn('status', ['blocked', 'sold', 'held', 'disabled'])
                ->pluck('status', 'seat_uid')
                ->toArray();
        }

        // Pull all invitation tickets for this event with their batch_id
        // so we can split by emitter: organizer panel (the batch row has
        // marketplace_organizer_id, /organizator/invitatii flow) vs
        // admin panel (NULL — the Filament marketplace admin Invitations
        // page sets created_by=null and no organizer_id). The seat_uid
        // was backfilled onto each invitation ticket's meta by an
        // earlier commit + tinker oneliner.
        $invitationTickets = \App\Models\Ticket::query()
            ->where('event_id', $eventId)
            ->where('meta->is_invitation', true)
            ->whereIn('status', ['valid', 'used'])
            ->whereNotNull('meta->seat_uid')
            ->get(['id', 'meta']);

        $batchIds = $invitationTickets
            ->map(fn ($t) => (int) ($t->meta['invite_batch_id'] ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $orgBatchSet = [];
        if (!empty($batchIds)) {
            $orgBatchSet = array_flip(
                \App\Models\InviteBatch::query()
                    ->whereIn('id', $batchIds)
                    ->whereNotNull('marketplace_organizer_id')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all()
            );
        }

        foreach ($invitationTickets as $t) {
            $uid = $t->meta['seat_uid'] ?? null;
            if (!$uid) continue;
            $batchId = (int) ($t->meta['invite_batch_id'] ?? 0);
            if ($batchId > 0 && isset($orgBatchSet[$batchId])) {
                $invitationOrgSeatUids[] = $uid;
            } else {
                $invitationAdminSeatUids[] = $uid;
            }
        }
        $invitationOrgSeatUids = array_values(array_unique($invitationOrgSeatUids));
        $invitationAdminSeatUids = array_values(array_unique($invitationAdminSeatUids));
    }

    // Build seat info map for blocked seats summary (seat_uid => [section, row, seatLabel])
    $seatInfoMap = [];
    foreach ($sections as $section) {
        foreach ($section->rows as $row) {
            foreach ($row->seats as $seat) {
                if ($seat->seat_uid && $seat->status !== 'imposibil') {
                    $seatInfoMap[$seat->seat_uid] = [$section->name, $row->label, $seat->label];
                }
            }
        }
    }

    // Count imposibil seats and total seats for legend
    $imposibilCount = 0;
    $totalSeatCount = 0;
    foreach ($sections as $section) {
        foreach ($section->rows as $row) {
            foreach ($row->seats as $seat) {
                if ($seat->status === 'imposibil') $imposibilCount++;
                else $totalSeatCount++;
            }
        }
    }

    $ticketTypesJson = json_encode($ticketTypesData);
    $rowAssignmentsJson = json_encode((object) $rowAssignments);
    $rowInfoJson = json_encode((object) $rowInfoMap);
    $eventSeatStatusesJson = json_encode((object) $eventSeatStatuses);
    $seatInfoJson = json_encode((object) $seatInfoMap);
    $invitationOrgSeatUidsJson = json_encode($invitationOrgSeatUids);
    $invitationAdminSeatUidsJson = json_encode($invitationAdminSeatUids);
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
        ES: {{ $eventSeatStatusesJson }},
        SI: {{ $seatInfoJson }},
        // Invitation seats split by emitter so the map can color them
        // distinctly. Kept as Sets for O(1) membership in fc() / tooltips.
        IS_ORG: new Set({{ $invitationOrgSeatUidsJson }}),
        IS_ADMIN: new Set({{ $invitationAdminSeatUidsJson }}),
        imposibilCount: {{ $imposibilCount }},
        totalSeatCount: {{ $totalSeatCount }},
        mode: 'view',
        saving: false,
        vbX: 0, vbY: 0, vbW: {{ $canvasW }}, vbH: {{ $canvasH }},
        OW: {{ $canvasW }}, OH: {{ $canvasH }},
        md: false, pan: false, mdRow: null, mdSeat: null, mdX: 0, mdY: 0, psX: 0, psY: 0, pvX: 0, pvY: 0,
        tip: '', tipX: 0, tipY: 0, showTip: false,
        selSeats: [],
        blockAction: 'block',
        mkInvite: false,
        blockSaving: false,

        // ─── seat → ticket allocation state ───
        alloc: {
            open: false,
            loading: false,
            saving: false,
            error: '',
            successMsg: '',
            seat: null,           // { seat_uid, section_name, row_label, seat_label, status, occupant_ticket }
            customers: [],
            customersLoaded: false,
            customerId: null,
            orders: [],
            ordersLoading: false,
            orderId: null,
            tickets: [],
            ticketsLoading: false,
            includeAllocated: false,
            ticketId: null,
            reason: '',
            confirmCheck: false,
            confirmHeld: false,
            overrideExisting: false,
        },
        recentAllocLog: [],
        recentAllocOpen: false,
        recentAllocLoaded: false,

        get zoom() { return Math.round((this.OW / this.vbW) * 100) },

        syncVB() {
            this.$refs.svg.setAttribute('viewBox', this.vbX + ' ' + this.vbY + ' ' + this.vbW + ' ' + this.vbH);
        },

        rc(rid) {
            let a = this.A[rid];
            if (a && a.length) { let s = a.find(x => Number(x.id) === Number(this.sel)); if (s) return s.color; return a[0].color; }
            return '#9ca3af';
        },
        fc(uid, rid) {
            if (this.mode === 'block' && this.selSeats.includes(uid)) return '#fbbf24';
            let es = this.ES[uid];
            // Invitation seats render distinctly from regular paid sales.
            // Organizer-emitted = violet, admin-emitted = orange so the
            // admin viewing the map can tell at a glance who issued
            // each invitation.
            if ((es === 'sold' || es === 'blocked') && this.IS_ORG.has(uid)) return '#a78bfa';
            if ((es === 'sold' || es === 'blocked') && this.IS_ADMIN.has(uid)) return '#fb923c';
            if (es === 'blocked') return '#dc2626';
            if (es === 'sold') return '#dc2626';
            if (es === 'held') return '#f59e0b';
            if (es === 'disabled') return '#e5e7eb';
            return this.rc(rid);
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

            if (!this.A[rid]) this.A[rid] = [];
            let idx = this.A[rid].findIndex(x => Number(x.id) === Number(this.sel));
            if (idx > -1) {
                this.A[rid].splice(idx, 1);
                if (!this.A[rid].length) delete this.A[rid];
            } else {
                let t = this.TT.find(x => Number(x.id) === Number(this.sel));
                this.A[rid] = [...(this.A[rid] || []), {id: this.sel, name: t ? t.name : '', color: t ? t.color : '#6b7280'}];
            }

            this.saving = true;
            let done = () => { this.saving = false; };
            try {
                let el = this.$el.closest('[wire\\:id]');
                let wid = el ? el.getAttribute('wire:id') : null;
                if (wid && window.Livewire) {
                    let lw = window.Livewire.find(wid);
                    if (lw) {
                        let p = (typeof lw.call === 'function')
                            ? lw.call('toggleSeatingRowAssignment', this.sel, rid)
                            : lw.toggleSeatingRowAssignment(this.sel, rid);
                        if (p && p.then) p.then(done).catch(done);
                        else done();
                    } else done();
                } else done();
            } catch(e) { done(); }
        },

        toggleSeat(uid) {
            if (this.mode !== 'block') return;
            let idx = this.selSeats.indexOf(uid);
            if (idx > -1) this.selSeats = this.selSeats.filter(u => u !== uid);
            else this.selSeats = [...this.selSeats, uid];
        },

        enterAssign() { this.mode = 'assign'; },
        exitAssign() { this.mode = 'view'; },
        enterBlock() { this.mode = 'block'; this.selSeats = []; this.blockAction = 'block'; this.mkInvite = false; },
        exitBlock() { this.mode = 'view'; this.selSeats = []; },

        // ─── seat → ticket allocation flow ───
        _lw() {
            let el = this.$el.closest('[wire\\:id]');
            let wid = el ? el.getAttribute('wire:id') : null;
            return (wid && window.Livewire) ? window.Livewire.find(wid) : null;
        },

        async enterAllocate() {
            this.mode = 'allocate';
            this.alloc.error = '';
            this.alloc.successMsg = '';
            if (this.alloc.customersLoaded) return;
            let lw = this._lw();
            if (!lw) return;
            try {
                let res = await lw.call('getCustomersForEvent');
                this.alloc.customers = Array.isArray(res) ? res : [];
                this.alloc.customersLoaded = true;
            } catch (e) {
                console.error('getCustomersForEvent failed', e);
                this.alloc.error = 'Eroare la încărcarea clienților.';
            }
        },
        exitAllocate() { this.mode = 'view'; this.closeAllocModal(); },

        async openAllocModal(uid) {
            this.alloc.open = true;
            this.alloc.loading = true;
            this.alloc.error = '';
            this.alloc.successMsg = '';
            this.alloc.customerId = null;
            this.alloc.orders = [];
            this.alloc.orderId = null;
            this.alloc.tickets = [];
            this.alloc.ticketId = null;
            this.alloc.reason = '';
            this.alloc.confirmCheck = false;
            this.alloc.confirmHeld = false;
            this.alloc.overrideExisting = false;
            this.alloc.includeAllocated = false;

            let lw = this._lw();
            if (!lw) { this.alloc.loading = false; return; }
            try {
                let ctx = await lw.call('getSeatAllocationContext', uid);
                if (!ctx || !ctx.ok) {
                    this.alloc.error = 'Nu pot încărca info loc: ' + (ctx?.error || 'unknown');
                    this.alloc.seat = null;
                } else {
                    this.alloc.seat = ctx;
                }
            } catch (e) {
                console.error('getSeatAllocationContext failed', e);
                this.alloc.error = 'Eroare la încărcarea locului.';
            } finally {
                this.alloc.loading = false;
            }
        },
        closeAllocModal() {
            this.alloc.open = false;
            this.alloc.seat = null;
            this.alloc.error = '';
            this.alloc.successMsg = '';
        },

        async onAllocCustomerChange() {
            this.alloc.orders = [];
            this.alloc.tickets = [];
            this.alloc.orderId = null;
            this.alloc.ticketId = null;
            if (!this.alloc.customerId) return;
            this.alloc.ordersLoading = true;
            let lw = this._lw();
            if (!lw) { this.alloc.ordersLoading = false; return; }
            try {
                let res = await lw.call('getOrdersForCustomer', Number(this.alloc.customerId));
                this.alloc.orders = Array.isArray(res) ? res : [];
            } catch (e) {
                console.error('getOrdersForCustomer failed', e);
                this.alloc.error = 'Eroare la încărcarea comenzilor.';
            } finally {
                this.alloc.ordersLoading = false;
            }
        },
        async onAllocOrderChange() {
            this.alloc.tickets = [];
            this.alloc.ticketId = null;
            if (!this.alloc.orderId) return;
            await this.refreshAllocTickets();
        },
        async refreshAllocTickets() {
            this.alloc.ticketsLoading = true;
            let lw = this._lw();
            if (!lw) { this.alloc.ticketsLoading = false; return; }
            try {
                let res = await lw.call('getTicketsForOrder', Number(this.alloc.orderId), !!this.alloc.includeAllocated);
                this.alloc.tickets = Array.isArray(res) ? res : [];
            } catch (e) {
                console.error('getTicketsForOrder failed', e);
                this.alloc.error = 'Eroare la încărcarea biletelor.';
            } finally {
                this.alloc.ticketsLoading = false;
            }
        },

        get allocSelectedTicket() {
            if (!this.alloc.ticketId || !this.alloc.tickets) return null;
            return this.alloc.tickets.find(t => Number(t.id) === Number(this.alloc.ticketId)) || null;
        },
        get allocCanSubmit() {
            if (!this.alloc.seat) return false;
            if (this.alloc.saving) return false;
            if (!this.alloc.customerId || !this.alloc.orderId || !this.alloc.ticketId) return false;
            if (!this.alloc.confirmCheck) return false;
            if ((this.alloc.reason || '').trim().length < 10) return false;
            if (this.alloc.seat.status === 'held' && !this.alloc.confirmHeld) return false;
            if (this.allocSelectedTicket?.has_seat && !this.alloc.overrideExisting) return false;
            return true;
        },

        async submitAllocation() {
            if (!this.allocCanSubmit) return;
            this.alloc.saving = true;
            this.alloc.error = '';
            let lw = this._lw();
            if (!lw) { this.alloc.saving = false; return; }
            try {
                let res = await lw.call(
                    'allocateSeatToTicket',
                    this.alloc.seat.seat_uid,
                    Number(this.alloc.ticketId),
                    this.alloc.reason,
                    !!this.alloc.overrideExisting,
                    !!this.alloc.confirmHeld,
                );
                if (!res || !res.ok) {
                    this.alloc.error = res?.message || ('Eroare: ' + (res?.error || 'unknown'));
                    this.alloc.saving = false;
                    return;
                }
                // Reflect in local ES map: new seat → sold; old seat (if any) → released
                let newES = {...this.ES};
                newES[this.alloc.seat.seat_uid] = 'sold';
                if (res.released_seat_uid) delete newES[res.released_seat_uid];
                this.ES = newES;

                this.alloc.successMsg = 'Loc alocat: ' + res.seat_label;
                this.alloc.saving = false;
                // Invalidate recent activity cache so next open re-fetches
                this.recentAllocLoaded = false;
                if (this.recentAllocOpen) this.loadRecentAlloc();
                // Auto-close after 1.4s
                setTimeout(() => { if (this.alloc.successMsg) this.closeAllocModal(); }, 1400);
            } catch (e) {
                console.error('allocateSeatToTicket failed', e);
                this.alloc.error = 'Eroare neașteptată: ' + (e?.message || e);
                this.alloc.saving = false;
            }
        },

        async loadRecentAlloc() {
            this.recentAllocOpen = !this.recentAllocOpen;
            if (!this.recentAllocOpen || this.recentAllocLoaded) return;
            let lw = this._lw();
            if (!lw) return;
            try {
                let res = await lw.call('getRecentSeatAllocations', 20);
                this.recentAllocLog = Array.isArray(res) ? res : [];
                this.recentAllocLoaded = true;
            } catch (e) {
                console.error('getRecentSeatAllocations failed', e);
            }
        },

        applyBlock() {
            if (!this.selSeats.length) return;
            this.blockSaving = true;
            let uids = [...this.selSeats];
            let action = this.blockAction;
            let invite = this.mkInvite;

            let update = () => {
                let newES = {...this.ES};
                uids.forEach(uid => {
                    if (action === 'block') newES[uid] = 'blocked';
                    else delete newES[uid];
                });
                this.ES = newES;
                this.selSeats = [];
                this.blockSaving = false;
            };
            try {
                let el = this.$el.closest('[wire\\:id]');
                let wid = el ? el.getAttribute('wire:id') : null;
                if (wid && window.Livewire) {
                    let lw = window.Livewire.find(wid);
                    if (lw) {
                        let p = (typeof lw.call === 'function')
                            ? lw.call('updateSeatStatuses', uids, action, invite)
                            : lw.updateSeatStatuses(uids, action, invite);
                        if (p && p.then) p.then(r => {
                            console.log('updateSeatStatuses response:', r);
                            update();
                            if (r && r.invite_url) window.open(r.invite_url, '_blank');
                        }).catch(e => { console.error('updateSeatStatuses error:', e); update(); });
                        else update();
                    } else { console.error('Livewire component not found'); this.blockSaving = false; }
                } else { console.error('No wire:id or Livewire'); this.blockSaving = false; }
            } catch(e) { console.error('applyBlock exception:', e); this.blockSaving = false; }
        },

        selectAllBlockedInView() {
            let svg = this.$refs.svg;
            let circles = svg.querySelectorAll('circle[data-seat-uid]');
            let uids = [];
            circles.forEach(c => {
                let uid = c.dataset.seatUid;
                if (this.ES[uid] === 'blocked') uids.push(uid);
            });
            this.selSeats = uids;
        },

        hoverTip(e) {
            if (this.md) { this.showTip = false; return; }
            if (this.mode === 'block') {
                let circle = e.target.closest('circle[data-seat-uid]');
                if (circle) {
                    let uid = circle.dataset.seatUid;
                    let label = circle.dataset.seatLabel || '';
                    let rg = circle.closest('[data-row-id]');
                    let rid = rg ? rg.dataset.rowId : null;
                    let info = rid ? this.RI[rid] : null;
                    let es = this.ES[uid];
                    let t = info ? info.section + ' \u2014 ' + (/^Mas/i.test(info.label) ? '' : 'R\u00e2nd ') + info.label + ' \u2014 Loc ' + label : 'Loc ' + label;
                    let isInvOrg = this.IS_ORG.has(uid);
                    let isInvAdmin = this.IS_ADMIN.has(uid);
                    if ((es === 'blocked' || es === 'sold') && isInvOrg) t += '\n\u2709\ufe0f Invita\u021bie de la organizator';
                    else if ((es === 'blocked' || es === 'sold') && isInvAdmin) t += '\n\u2709\ufe0f Invita\u021bie de la admin';
                    else if (es === 'blocked') t += '\n\u26d4 Blocat';
                    else if (es === 'sold') t += '\n\u2714 V\u00e2ndut';
                    else if (es === 'held') t += '\n\u23f3 Re\u021binut';
                    else t += '\n\u2705 Disponibil';
                    this.tip = t; this.tipX = e.clientX + 14; this.tipY = e.clientY - 10; this.showTip = true;
                } else { this.showTip = false; }
                return;
            }
            let circle = e.target.closest('circle[data-seat-uid]');
            let rg = e.target.closest('[data-row-id]');
            if (circle) {
                let uid = circle.dataset.seatUid;
                let label = circle.dataset.seatLabel || '';
                let rid = rg ? rg.dataset.rowId : null;
                let info = rid ? this.RI[rid] : null;
                let es = this.ES[uid];
                let isInvOrg = this.IS_ORG.has(uid);
                let isInvAdmin = this.IS_ADMIN.has(uid);
                let isInv = isInvOrg || isInvAdmin;
                if (es === 'blocked' && !isInv) {
                    this.tip = '\u26d4 Blocat'; this.tipX = e.clientX + 14; this.tipY = e.clientY - 10; this.showTip = true;
                } else {
                    let t = info ? info.section + ' \u2014 ' + (/^Mas/i.test(info.label) ? '' : 'R\u00e2nd ') + info.label + ' \u2014 Loc ' + label : 'Loc ' + label;
                    let a = rid ? this.A[rid] : null;
                    if (a && a.length) t += '\n' + a.map(x => x.name).join(', ');
                    else t += '\nNeatribuit';
                    if (isInvOrg && (es === 'sold' || es === 'blocked')) t += '\n\u2709\ufe0f Invita\u021bie de la organizator';
                    else if (isInvAdmin && (es === 'sold' || es === 'blocked')) t += '\n\u2709\ufe0f Invita\u021bie de la admin';
                    else if (es === 'sold') t += '\n\u2714 V\u00e2ndut';
                    else if (es === 'held') t += '\n\u23f3 Re\u021binut';
                    this.tip = t; this.tipX = e.clientX + 14; this.tipY = e.clientY - 10; this.showTip = true;
                }
            } else if (rg) {
                let rid = rg.dataset.rowId, info = this.RI[rid];
                if (info) {
                    let t = info.section + ' \u2014 ' + (/^Mas/i.test(info.label) ? '' : 'R\u00e2nd ') + info.label + ' (' + info.seatCount + ' locuri)';
                    let a = this.A[rid];
                    if (a && a.length) t += '\n' + a.map(x => x.name).join(', ');
                    else t += '\nNeatribuit';
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
        },

        get blockedSummary() {
            let groups = {}, total = 0;
            let keys = Object.keys(this.ES);
            for (let i = 0; i < keys.length; i++) {
                let uid = keys[i];
                if (this.ES[uid] !== 'blocked') continue;
                let si = this.SI[uid];
                if (!si) continue;
                let key = si[0] + '|' + si[1];
                if (!groups[key]) groups[key] = { section: si[0], row: si[1], seats: [] };
                groups[key].seats.push(si[2]);
                total++;
            }
            return { groups: Object.values(groups), total };
        },
        get LC() {
            let esKeys = Object.keys(this.ES);
            let blocked = 0, sold = 0, held = 0, invOrg = 0, invAdmin = 0;
            for (let i = 0; i < esKeys.length; i++) {
                let uid = esKeys[i], s = this.ES[uid];
                let isInvOrg = this.IS_ORG.has(uid);
                let isInvAdmin = this.IS_ADMIN.has(uid);
                if (s === 'blocked' || s === 'sold') {
                    if (isInvOrg) invOrg++;
                    else if (isInvAdmin) invAdmin++;
                    else if (s === 'blocked') blocked++;
                    else sold++;
                } else if (s === 'held') held++;
            }
            let assigned = 0, riKeys = Object.keys(this.RI), assignedRids = new Set();
            for (let k = 0; k < riKeys.length; k++) {
                let rid = riKeys[k];
                if (this.A[rid] && this.A[rid].length) { assigned += this.RI[rid].seatCount; assignedRids.add(rid); }
            }
            let unassigned = 0;
            for (let k = 0; k < riKeys.length; k++) {
                let rid = riKeys[k];
                if (!assignedRids.has(rid)) unassigned += this.RI[rid].seatCount;
            }
            return { blocked, sold, held, invOrg, invAdmin, unassigned, imposibil: this.imposibilCount };
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
            let sc = e.target.closest('circle[data-seat-uid]');
            mdSeat = sc ? sc.dataset.seatUid : null;
        });
        window.addEventListener('mousemove', (e) => {
            if (!md) return;
            if (mode === 'assign' && mdRow) return;
            if (mode === 'block' && mdSeat) return;
            if (mode === 'allocate' && mdSeat) return;
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
            if (mode === 'block') {
                if (mdSeat && !pan) {
                    toggleSeat(mdSeat);
                } else if (!pan) {
                    let el = document.elementFromPoint(e.clientX, e.clientY);
                    if (el && svg.contains(el)) {
                        let sc = el.closest('circle[data-seat-uid]');
                        if (sc) toggleSeat(sc.dataset.seatUid);
                    }
                }
            } else if (mode === 'allocate') {
                if (!pan) {
                    let uid = mdSeat;
                    if (!uid) {
                        let el = document.elementFromPoint(e.clientX, e.clientY);
                        if (el && svg.contains(el)) {
                            let sc = el.closest('circle[data-seat-uid]');
                            if (sc) uid = sc.dataset.seatUid;
                        }
                    }
                    if (uid) openAllocModal(uid);
                }
            } else if (mode === 'assign' && mdRow) {
                toggle(mdRow);
            } else if (!pan) {
                let el = document.elementFromPoint(e.clientX, e.clientY);
                if (el && svg.contains(el)) {
                    let rg = el.closest('[data-row-id]');
                    if (rg) toggle(rg.dataset.rowId);
                }
            }
            md = false; pan = false; mdRow = null; mdSeat = null;
        });
    "
    class="space-y-3"
>
    {{-- Toolbar --}}
    <div class="flex items-center gap-3 px-3 pt-3">
        <button type="button" x-show="mode==='view'" x-on:click="enterAssign()"
            class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-200 border border-gray-600 transition-all">
            <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            Aloc&#259; bilete
        </button>
        <button type="button" x-show="mode==='view'" x-on:click="enterBlock()"
            class="px-4 py-2 text-sm font-medium rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-200 border border-gray-600 transition-all">
            <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Blocheaz&#259; locuri
        </button>
        <button type="button" x-show="mode==='view'" x-on:click="enterAllocate()"
            class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-700 hover:bg-indigo-600 text-white border border-indigo-600 transition-all">
            <svg class="w-4 h-4 inline -mt-0.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            Aloc&#259; loc &rarr; bilet
        </button>
        <div class="flex items-center gap-2 ml-auto">
            <button type="button" x-on:click="zoomCenter(1.3)" class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">+ Zoom</button>
            <button type="button" x-on:click="zoomCenter(1/1.3)" class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">&minus; Zoom</button>
            <button type="button" x-on:click="resetZoom()" class="px-2 py-1 text-xs bg-gray-700 hover:bg-gray-600 text-white rounded transition">Reset</button>
            <span class="text-xs text-gray-500" x-text="zoom + '%'"></span>
        </div>
    </div>

    {{-- Ticket type selector (assign mode only) --}}
    <div x-show="mode==='assign'" x-cloak class="px-3">
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

    {{-- Block seats toolbar (block mode only) --}}
    <div x-show="mode==='block'" x-cloak class="px-3">
        <div class="flex flex-wrap items-center gap-3 p-3 bg-red-900/20 border border-red-800/40 rounded-lg">
            <span class="text-xs text-gray-300">Click pe locuri individuale pentru a le selecta:</span>
            <select x-model="blockAction" class="text-sm bg-gray-700 text-white border border-gray-600 rounded-lg px-3 py-1.5 focus:ring-1 focus:ring-red-500 focus:border-red-500">
                <option value="block">Blocheaz&#259; locuri</option>
                <option value="unblock">Deblocheaz&#259; locuri</option>
            </select>
            <label class="flex items-center gap-1.5 text-xs text-gray-300 cursor-pointer" x-show="blockAction==='block'" x-cloak>
                <input type="checkbox" x-model="mkInvite" class="rounded border-gray-600 bg-gray-700 text-blue-500 focus:ring-blue-500 focus:ring-offset-0">
                Genereaz&#259; invita&#539;ie
            </label>
            <span class="text-xs font-medium" :class="selSeats.length > 0 ? 'text-yellow-400' : 'text-gray-500'" x-text="selSeats.length + ' locuri selectate'"></span>
            <button type="button" x-on:click="selectAllBlockedInView()" x-show="blockAction==='unblock'" x-cloak
                class="px-2 py-1 text-xs rounded bg-gray-700 hover:bg-gray-600 text-gray-300 transition">Selecteaz&#259; toate blocate</button>
            <button type="button" x-on:click="applyBlock()" :disabled="!selSeats.length || blockSaving"
                class="px-3 py-1.5 text-xs font-medium rounded-lg text-white transition disabled:opacity-40"
                :class="blockAction==='block' ? 'bg-red-700 hover:bg-red-600' : 'bg-blue-700 hover:bg-blue-600'">
                <span x-show="!blockSaving" x-text="blockAction==='block' ? 'Blocheaz\u0103 (' + selSeats.length + ')' : 'Deblocheaz\u0103 (' + selSeats.length + ')'"></span>
                <span x-show="blockSaving" class="animate-pulse">Salvare...</span>
            </button>
            <button type="button" x-on:click="exitBlock()"
                class="ml-auto px-3 py-1.5 text-xs font-medium rounded-lg bg-green-700 hover:bg-green-600 text-white transition">Terminat</button>
        </div>
    </div>

    {{-- Allocate mode toolbar (seat → ticket pairing) --}}
    <div x-show="mode==='allocate'" x-cloak class="px-3">
        <div class="flex flex-wrap items-center gap-3 p-3 bg-indigo-900/20 border border-indigo-700/40 rounded-lg">
            <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-xs text-indigo-100">Click pe un loc din hartă pentru a-l aloca unui bilet existent (re-asignare permisă pentru bilete care au deja loc).</span>
            <button type="button" x-on:click="exitAllocate()"
                class="ml-auto px-3 py-1.5 text-xs font-medium rounded-lg bg-green-700 hover:bg-green-600 text-white transition">Terminat</button>
        </div>
    </div>

    {{-- SVG Map --}}
    <div class="border overflow-hidden relative" style="background-color:#ffffff;border-color:#d1d5db;border-radius:0">
        <svg x-ref="svg"
            viewBox="0 0 {{ $canvasW }} {{ $canvasH }}"
            preserveAspectRatio="xMidYMid meet"
            class="w-full select-none"
            style="background-color:#ffffff;height:calc(100vh - 300px);min-height:500px;"
            :style="pan ? 'cursor:grabbing' : (mode==='assign' ? 'cursor:crosshair' : (mode==='block' ? 'cursor:crosshair' : (mode==='allocate' ? 'cursor:crosshair' : 'cursor:grab')))"
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
                    $seatRadius = (($section->metadata['seat_size'] ?? 15) / 2);
                    $seatFontSize = round($seatRadius * 0.95, 1);
                    $xOff = round($seatRadius * 0.5, 1);
                    // Section-level "Afișare automată nume rânduri" toggle
                    // from the seating designer. Default true; only false
                    // when the organizer explicitly unchecks it.
                    $autoShowRowLabels = ($section->metadata['auto_show_row_labels'] ?? true) !== false;

                    // Compute section-wide seat X bounds and gap for aligned row labels
                    $allSeatXs = [];
                    $seatGap = $seatRadius * 3; // fallback
                    foreach ($section->rows as $_r) {
                        foreach ($_r->seats as $_s) {
                            $allSeatXs[] = $_s->x ?? 0;
                        }
                        // Compute gap from first row with 2+ seats
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
                            // Tables (drawRectTable / drawRoundTable in the
                            // Konva designer) are stored on the row's
                            // metadata JSON. Without rendering them here
                            // the admin's "harta" tab shows only the seats
                            // around each table — the table shape itself
                            // is missing, which is what the organizer was
                            // seeing.
                            $rowMeta = $row->metadata ?? [];
                            $isTable = !empty($rowMeta['is_table']);
                            $tableType = $rowMeta['table_type'] ?? 'rect';
                            $tcx = $sX + (float)($rowMeta['center_x'] ?? 0);
                            $tcy = $sY + (float)($rowMeta['center_y'] ?? 0);
                            $tableColor = $section->background_color ?? '#6B7280';
                        @endphp
                        @if($isTable)
                            @if($tableType === 'round')
                                @php $tr = (float)($rowMeta['radius'] ?? 30); @endphp
                                <circle cx="{{ $tcx }}" cy="{{ $tcy }}" r="{{ $tr }}"
                                        fill="{{ $tableColor }}" fill-opacity="0.25"
                                        stroke="{{ $tableColor }}" stroke-width="1.5" stroke-opacity="0.5"
                                        class="pointer-events-none"/>
                            @else
                                @php
                                    $tw = (float)($rowMeta['table_width'] ?? 80);
                                    $th = (float)($rowMeta['table_height'] ?? 30);
                                @endphp
                                <rect x="{{ $tcx - $tw/2 }}" y="{{ $tcy - $th/2 }}"
                                      width="{{ $tw }}" height="{{ $th }}" rx="4"
                                      fill="{{ $tableColor }}" fill-opacity="0.25"
                                      stroke="{{ $tableColor }}" stroke-width="1.5" stroke-opacity="0.5"
                                      class="pointer-events-none"/>
                            @endif
                            <text x="{{ $tcx }}" y="{{ $tcy + 4 }}" text-anchor="middle"
                                  font-size="10" font-weight="700" fill="rgba(0,0,0,0.4)"
                                  class="pointer-events-none select-none">{{ $row->label }}</text>
                        @endif
                        <g data-row-id="{{ $row->id }}" style="transition: opacity 0.15s"
                           :opacity="mode==='block' ? 1 : ro({{ $row->id }})">
                            @foreach($row->seats as $seat)
                                @php
                                    $seatX = $sX + ($seat->x ?? 0);
                                    $seatY = $sY + ($seat->y ?? 0);
                                    $seatUid = $seat->seat_uid ?? '';
                                @endphp
                                @if($seat->status === 'imposibil')
                                    <circle cx="{{ $seatX }}" cy="{{ $seatY }}" r="{{ $seatRadius }}"
                                            fill="#e5e7eb" stroke="#9ca3af" stroke-width="0.5"
                                            class="pointer-events-none"/>
                                @else
                                    <circle cx="{{ $seatX }}" cy="{{ $seatY }}" r="{{ $seatRadius }}"
                                            data-seat-uid="{{ $seatUid }}"
                                            data-seat-label="{{ $seat->label }}"
                                            :fill="fc('{{ $seatUid }}', {{ $row->id }})"
                                            :stroke="mode==='block' && selSeats.includes('{{ $seatUid }}') ? '#fbbf24' : '#fff'"
                                            :stroke-width="mode==='block' && selSeats.includes('{{ $seatUid }}') ? '2.5' : '0.5'"
                                            :r="mode==='block' && selSeats.includes('{{ $seatUid }}') ? '{{ $seatRadius + 1 }}' : '{{ $seatRadius }}'"
                                            class="transition-colors duration-100"/>
                                    <text x="{{ $seatX }}" y="{{ $seatY + $seatRadius * 0.4 }}"
                                          font-size="{{ $seatFontSize }}" text-anchor="middle" font-weight="600"
                                          fill="rgba(255,255,255,0.9)"
                                          class="pointer-events-none select-none"
                                          :style="ES['{{ $seatUid }}'] === 'blocked' && !selSeats.includes('{{ $seatUid }}') ? 'display:none' : ''">{{ $seat->label }}</text>
                                    <line x1="{{ $seatX - $xOff }}" y1="{{ $seatY - $xOff }}" x2="{{ $seatX + $xOff }}" y2="{{ $seatY + $xOff }}"
                                          stroke="white" stroke-width="1.8" stroke-linecap="round" class="pointer-events-none"
                                          :style="ES['{{ $seatUid }}'] === 'blocked' && !selSeats.includes('{{ $seatUid }}') ? '' : 'display:none'"/>
                                    <line x1="{{ $seatX + $xOff }}" y1="{{ $seatY - $xOff }}" x2="{{ $seatX - $xOff }}" y2="{{ $seatY + $xOff }}"
                                          stroke="white" stroke-width="1.8" stroke-linecap="round" class="pointer-events-none"
                                          :style="ES['{{ $seatUid }}'] === 'blocked' && !selSeats.includes('{{ $seatUid }}') ? '' : 'display:none'"/>
                                @endif
                            @endforeach

                            @php
                                $firstSeat = $row->seats->first();
                                $rowLabelY = $firstSeat ? $sY + ($firstSeat->y ?? 0) + $seatRadius * 0.4 : $sY;
                            @endphp
                            @if(!$isTable && $autoShowRowLabels)
                                {{-- Left row label (skipped for tables — label sits inside the table shape — and when the section has auto_show_row_labels=false) --}}
                                <text x="{{ $leftLabelX }}" y="{{ $rowLabelY }}"
                                      font-size="{{ $rowLabelSize }}" text-anchor="end" font-weight="600"
                                      class="pointer-events-none select-none"
                                      :fill="isSel({{ $row->id }}) ? 'rgba(0,0,0,1)' : 'rgba(0,0,0,0.7)'"
                                >{{ $row->label }}</text>
                                {{-- Right row label --}}
                                <text x="{{ $rightLabelX }}" y="{{ $rowLabelY }}"
                                      font-size="{{ $rowLabelSize }}" text-anchor="start" font-weight="600"
                                      class="pointer-events-none select-none"
                                      :fill="isSel({{ $row->id }}) ? 'rgba(0,0,0,1)' : 'rgba(0,0,0,0.7)'"
                                >{{ $row->label }}</text>
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

        {{-- Tooltip --}}
        <div x-show="showTip" x-cloak
             :style="`left:${tipX}px;top:${tipY}px`"
             class="fixed z-50 pointer-events-none px-3 py-2 text-xs bg-gray-800 border border-gray-600 rounded-lg shadow-xl text-white whitespace-pre-line"
             x-text="tip"></div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-2 text-xs px-3">
        <template x-for="tt in TT" :key="'l'+tt.id">
            <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" :style="`background:${tt.color}`"></span>
                <span class="text-gray-200" x-text="tt.name"></span>
                <span class="text-gray-500" x-text="summaryFor(tt.id).seats"></span>
            </span>
        </template>
        <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#9ca3af"></span>
            <span class="text-gray-400">Neatribuit</span>
            <span class="text-gray-500" x-text="LC.unassigned"></span>
        </span>
        <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0 relative" style="background:#dc2626">
                <span class="absolute inset-0 flex items-center justify-center text-white font-bold" style="font-size:6px;line-height:1">&times;</span>
            </span>
            <span class="text-gray-400">Blocat</span>
            <span class="text-gray-500" x-text="LC.blocked"></span>
        </span>
        <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#a78bfa"></span>
            <span class="text-gray-400">Invitație de la organizator</span>
            <span class="text-gray-500" x-text="LC.invOrg"></span>
        </span>
        <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#fb923c"></span>
            <span class="text-gray-400">Invitație de la admin</span>
            <span class="text-gray-500" x-text="LC.invAdmin"></span>
        </span>
        <span x-show="mode==='block'" x-cloak class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#fbbf24"></span>
            <span class="text-gray-400">Selectat</span>
            <span class="text-gray-500" x-text="selSeats.length"></span>
        </span>
        <span x-show="mode==='block'" x-cloak class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#dc2626"></span>
            <span class="text-gray-400">V&#226;ndut</span>
            <span class="text-gray-500" x-text="LC.sold"></span>
        </span>
        <span class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-800 border border-gray-700">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#e5e7eb;border:1px solid #9ca3af"></span>
            <span class="text-gray-400">Imposibil</span>
            <span class="text-gray-500" x-text="LC.imposibil"></span>
        </span>
    </div>

    {{-- Assignment summary --}}
    <div class="border border-gray-700 rounded-lg overflow-hidden mx-3">
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
                                <span x-text="(/^Mas/i.test(r.label) ? '' : 'R\u00e2nd ') + r.label"></span>
                                <span class="text-gray-500" x-text="'(' + r.seatCount + ')'"></span>
                            </span>
                        </template>
                    </div>
                    <div x-show="summaryFor(tt.id).rows.length === 0" class="mt-1 ml-5 text-xs text-gray-600 italic">Nicio asignare</div>
                </div>
            </template>
        </div>
    </div>

    {{-- Recent seat allocations log (collapsible) --}}
    <div class="border border-gray-700 rounded-lg overflow-hidden mx-3">
        <button type="button" x-on:click="loadRecentAlloc()"
            class="w-full flex items-center justify-between bg-gray-800/50 px-4 py-2 border-b border-gray-700 hover:bg-gray-800 transition">
            <span class="text-sm font-medium text-gray-300">
                <svg class="w-4 h-4 inline -mt-0.5 mr-1 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Activitate recent&#259; aloc&#259;ri locuri
            </span>
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="recentAllocOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div x-show="recentAllocOpen" x-cloak class="px-4 py-3 max-h-96 overflow-y-auto">
            <div x-show="!recentAllocLoaded" class="text-xs text-gray-500">Se &icirc;ncarc&#259;...</div>
            <div x-show="recentAllocLoaded && recentAllocLog.length === 0" class="text-xs text-gray-500 italic">Nicio aloc&#259;re manual&#259; &icirc;nregistrat&#259; pentru acest eveniment.</div>
            <ul class="divide-y divide-gray-800">
                <template x-for="entry in recentAllocLog" :key="entry.id">
                    <li class="py-2.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="text-xs text-gray-400" x-text="entry.when + ' · ' + (entry.causer_name || entry.causer_email || '—')"></div>
                                <div class="mt-0.5 text-sm text-gray-200">
                                    <span x-text="entry.action_label"></span>:
                                    <span class="font-mono" x-text="entry.seat_uid"></span>
                                    (<span x-text="entry.seat_human"></span>)
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500">
                                    Bilet <a :href="entry.ticket_url" target="_blank" class="text-indigo-400 hover:underline" x-text="'#' + entry.ticket_id"></a>
                                    · Comand&#259; <a :href="entry.order_url" target="_blank" class="text-indigo-400 hover:underline" x-text="entry.order_number"></a>
                                    · <span x-text="entry.customer_label || '—'"></span>
                                </div>
                                <div class="mt-1 text-xs text-gray-400 italic truncate" :title="entry.reason" x-text="'Motiv: ' + (entry.reason || '—')"></div>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>
    </div>

    {{-- Seat allocation modal --}}
    <div x-show="alloc.open" x-cloak
         class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/60"
         x-on:click.self="closeAllocModal()"
         x-on:keydown.escape.window="if (alloc.open) closeAllocModal()">
        <div class="w-full max-w-2xl bg-gray-900 border border-gray-700 rounded-xl shadow-2xl flex flex-col max-h-[90vh]">
            <div class="px-5 py-3 border-b border-gray-700 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-semibold text-white">
                        Aloc&#259; loc &rarr; bilet
                    </h3>
                    <p class="mt-0.5 text-xs text-gray-400" x-show="alloc.seat" x-cloak>
                        <span x-text="alloc.seat?.section_name"></span> &middot;
                        R&#226;nd <span x-text="alloc.seat?.row_label"></span> &middot;
                        Loc <span x-text="alloc.seat?.seat_label"></span>
                        <span class="ml-1 font-mono text-gray-500" x-text="'(' + alloc.seat?.seat_uid + ')'"></span>
                    </p>
                </div>
                <button type="button" x-on:click="closeAllocModal()" class="text-gray-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3">
                <div x-show="alloc.loading" class="text-sm text-gray-400">Se &icirc;ncarc&#259;...</div>

                <template x-if="alloc.seat && !alloc.loading">
                    <div class="space-y-3">
                        {{-- Seat status badge --}}
                        <div class="flex items-center gap-2">
                            <span class="text-xs uppercase tracking-wider text-gray-500">Status curent loc:</span>
                            <span class="px-2 py-0.5 text-xs rounded-full font-medium"
                                :class="{
                                    'bg-green-900/40 text-green-300': alloc.seat.status === 'available',
                                    'bg-red-900/40 text-red-300': alloc.seat.status === 'blocked' || alloc.seat.status === 'sold',
                                    'bg-amber-900/40 text-amber-300': alloc.seat.status === 'held',
                                    'bg-gray-700 text-gray-300': alloc.seat.status === 'disabled',
                                }"
                                x-text="alloc.seat.status"></span>
                        </div>

                        {{-- Held warning --}}
                        <div x-show="alloc.seat.status === 'held'" x-cloak class="p-3 rounded-lg bg-amber-900/20 border border-amber-700/40">
                            <p class="text-xs text-amber-200 font-medium">
                                &#9888; Locul este &icirc;n hold activ pentru o sesiune real&#259; (client &icirc;n cart).
                            </p>
                            <label class="mt-2 flex items-start gap-2 text-xs text-amber-100 cursor-pointer">
                                <input type="checkbox" x-model="alloc.confirmHeld" class="mt-0.5 rounded border-amber-600 bg-amber-900 text-amber-500">
                                <span>Confirm c&#259; aloc peste hold (vei prelua locul; clientul &icirc;n cart va e&#537;ua la checkout).</span>
                            </label>
                        </div>

                        {{-- Occupant warning (sold) --}}
                        <div x-show="alloc.seat.status === 'sold' && alloc.seat.occupant_ticket" x-cloak class="p-3 rounded-lg bg-red-900/20 border border-red-700/40">
                            <p class="text-xs text-red-200 font-medium">
                                &#9888; Locul este deja v&#226;ndut: bilet #<span x-text="alloc.seat.occupant_ticket?.id"></span>
                                (comand&#259; <span x-text="alloc.seat.occupant_ticket?.order_number"></span>,
                                <span x-text="alloc.seat.occupant_ticket?.customer_email"></span>).
                            </p>
                            <p class="mt-1 text-xs text-red-300">
                                Nu se permite suprapunerea. Elibereaz&#259; locul biletului de mai sus &icirc;nt&#226;i.
                            </p>
                        </div>

                        {{-- Customer dropdown --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Client</label>
                            <select x-model="alloc.customerId" x-on:change="onAllocCustomerChange()"
                                class="w-full text-sm bg-gray-800 text-white border border-gray-700 rounded-lg px-3 py-2 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">— alege client —</option>
                                <template x-for="c in alloc.customers" :key="c.id">
                                    <option :value="c.id" x-text="c.label"></option>
                                </template>
                            </select>
                            <p x-show="alloc.customers.length === 0 && alloc.customersLoaded" x-cloak class="mt-1 text-xs text-gray-500 italic">
                                Niciun client cu comand&#259; pl&#259;tit&#259; pe acest eveniment.
                            </p>
                        </div>

                        {{-- Order dropdown --}}
                        <div x-show="alloc.customerId" x-cloak>
                            <label class="block text-xs font-medium text-gray-300 mb-1">Comand&#259;</label>
                            <select x-model="alloc.orderId" x-on:change="onAllocOrderChange()" :disabled="alloc.ordersLoading"
                                class="w-full text-sm bg-gray-800 text-white border border-gray-700 rounded-lg px-3 py-2 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-50">
                                <option value="">— alege comand&#259; —</option>
                                <template x-for="o in alloc.orders" :key="o.id">
                                    <option :value="o.id" x-text="o.label"></option>
                                </template>
                            </select>
                            <p x-show="alloc.ordersLoading" class="mt-1 text-xs text-gray-500">Se &icirc;ncarc&#259;...</p>
                        </div>

                        {{-- Ticket dropdown --}}
                        <div x-show="alloc.orderId" x-cloak>
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-xs font-medium text-gray-300">Bilet</label>
                                <label class="flex items-center gap-1.5 text-xs text-gray-400 cursor-pointer">
                                    <input type="checkbox" x-model="alloc.includeAllocated"
                                        x-on:change="refreshAllocTickets()"
                                        class="rounded border-gray-600 bg-gray-700 text-indigo-500">
                                    <span>Include bilete cu loc alocat (re-asignare)</span>
                                </label>
                            </div>
                            <select x-model="alloc.ticketId" :disabled="alloc.ticketsLoading"
                                class="w-full text-sm bg-gray-800 text-white border border-gray-700 rounded-lg px-3 py-2 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 disabled:opacity-50">
                                <option value="">— alege bilet —</option>
                                <template x-for="t in alloc.tickets" :key="t.id">
                                    <option :value="t.id" x-text="t.label"></option>
                                </template>
                            </select>
                            <p x-show="alloc.ticketsLoading" class="mt-1 text-xs text-gray-500">Se &icirc;ncarc&#259;...</p>
                            <p x-show="!alloc.ticketsLoading && alloc.tickets.length === 0" x-cloak class="mt-1 text-xs text-gray-500 italic">
                                <span x-show="!alloc.includeAllocated">Toate biletele din aceast&#259; comand&#259; au deja loc alocat. Bifeaz&#259; "Include bilete cu loc alocat" pentru re-asignare.</span>
                                <span x-show="alloc.includeAllocated">Nicio bilet valid &icirc;n aceast&#259; comand&#259; pe acest eveniment.</span>
                            </p>
                        </div>

                        {{-- Re-assign warning --}}
                        <div x-show="allocSelectedTicket?.has_seat" x-cloak class="p-3 rounded-lg bg-amber-900/20 border border-amber-700/40">
                            <p class="text-xs text-amber-200 font-medium">
                                Biletul are deja loc alocat:
                                <span class="font-mono" x-text="allocSelectedTicket?.current_seat_label || allocSelectedTicket?.current_seat_uid"></span>.
                            </p>
                            <label class="mt-2 flex items-start gap-2 text-xs text-amber-100 cursor-pointer">
                                <input type="checkbox" x-model="alloc.overrideExisting" class="mt-0.5 rounded border-amber-600 bg-amber-900 text-amber-500">
                                <span>Re-asignare: elibereaz&#259; locul vechi (devine <strong>available</strong>) &#537;i atribuie cel nou.</span>
                            </label>
                        </div>

                        {{-- Reason --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-300 mb-1">
                                Motiv aloc&#259;rii <span class="text-red-400">*</span>
                                <span class="ml-1 text-gray-500">(min 10 caractere, vizibil &icirc;n log)</span>
                            </label>
                            <textarea x-model="alloc.reason" rows="3" maxlength="500"
                                placeholder="Ex: Locurile alese de client n-au fost propagate la checkout. Confirmat telefonic c&#259; dore&#537;te B-20."
                                class="w-full text-sm bg-gray-800 text-white border border-gray-700 rounded-lg px-3 py-2 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                            <p class="mt-1 text-xs text-gray-500" x-text="(alloc.reason || '').length + ' / 500'"></p>
                        </div>

                        {{-- Confirmation checkbox --}}
                        <label class="flex items-start gap-2 text-xs text-gray-300 cursor-pointer">
                            <input type="checkbox" x-model="alloc.confirmCheck" class="mt-0.5 rounded border-gray-600 bg-gray-700 text-indigo-500">
                            <span>Am verificat c&#259; biletul apar&#539;ine clientului corect &#537;i locul ales este cel solicitat.</span>
                        </label>
                    </div>
                </template>

                {{-- Error --}}
                <div x-show="alloc.error" x-cloak class="p-3 rounded-lg bg-red-900/30 border border-red-700/50 text-sm text-red-200" x-text="alloc.error"></div>

                {{-- Success --}}
                <div x-show="alloc.successMsg" x-cloak class="p-3 rounded-lg bg-green-900/30 border border-green-700/50 text-sm text-green-200" x-text="alloc.successMsg"></div>
            </div>

            <div class="px-5 py-3 border-t border-gray-700 flex items-center justify-end gap-2">
                <button type="button" x-on:click="closeAllocModal()"
                    class="px-3 py-1.5 text-sm rounded-lg bg-gray-700 hover:bg-gray-600 text-gray-200 transition">Anuleaz&#259;</button>
                <button type="button" x-on:click="submitAllocation()" :disabled="!allocCanSubmit"
                    class="px-4 py-1.5 text-sm font-medium rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-show="!alloc.saving">Aloc&#259;</span>
                    <span x-show="alloc.saving" class="animate-pulse">Se salveaz&#259;...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Blocked seats summary --}}
    <div class="border border-gray-700 rounded-lg overflow-hidden mx-3" x-show="blockedSummary.total > 0">
        <div class="bg-gray-800/50 px-4 py-2 border-b border-gray-700 flex items-center justify-between">
            <span class="text-sm font-medium text-gray-300">Locuri blocate</span>
            <span class="text-xs text-gray-500" x-text="blockedSummary.total + ' locuri'"></span>
        </div>
        <div class="px-4 py-2.5">
            <div class="flex flex-wrap gap-1.5">
                <template x-for="g in blockedSummary.groups" :key="g.section + g.row">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs bg-gray-800 text-gray-300">
                        <span class="w-2 h-2 rounded-full bg-gray-500 flex-shrink-0"></span>
                        <span class="text-gray-500" x-text="g.section"></span>
                        <span x-text="(/^Mas/i.test(g.row) ? '' : 'R\u00e2nd ') + g.row"></span>
                        <span class="text-gray-400" x-text="'Loc ' + g.seats.sort((a,b) => a-b).join(', ')"></span>
                    </span>
                </template>
            </div>
        </div>
    </div>
</div>
@else
<div class="p-4 text-center text-gray-500 text-sm">
    Nu exist&#259; o hart&#259; de locuri configurat&#259; sau salvat&#259; pentru acest eveniment.
</div>
@endif
