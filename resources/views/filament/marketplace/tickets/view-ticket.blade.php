<x-filament-panels::page>
    @php
        $ticket = $this->record;
        $event = $ticket->ticketType?->event;
        $eventTitle = $event ? ($event->getTranslation('title', app()->getLocale()) ?: $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en')) : '';
        $venue = $event?->venue;
        $venueName = $venue ? ($venue->getTranslation('name', app()->getLocale()) ?? '') : '';
        $beneficiary = $ticket->meta['beneficiary'] ?? null;
        $client = $ticket->order?->marketplaceClient;
        $tz = fn ($dt, $fmt = 'd.m.Y H:i') => \App\Support\MarketplaceTz::fmt($dt, $fmt, $client);
        $currency = $ticket->order?->currency ?? $ticket->ticketType?->currency ?? 'RON';
        $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $currency;
        $seat = \App\Filament\Marketplace\Resources\TicketResource\Pages\ViewTicket::seatHuman($ticket);
        $eventUrl = $event ? \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => $event]) : null;

        $checkInSourceLabel = match ($ticket->checked_in_via) {
            'organizer_app' => 'Aplicație organizator (mobil)',
            'venue_app' => 'Aplicație locație (mobil)',
            'pos_app' => 'POS automat (la vânzare)',
            'staff_app' => 'Aplicație staff festival',
            'activities_app' => 'Aplicație activități',
            'leisure_app' => 'Aplicație leisure',
            'manual' => 'Manual (cont organizator)',
            default => $ticket->checked_in_via ?: 'Necunoscut (înainte de tracking sursă)',
        };

        $order = $ticket->order;
        $customerName = $order ? ($order->customer_name ?? ($order->meta['customer_name'] ?? null) ?? $order->marketplaceCustomer?->name ?? $order->customer?->full_name ?? 'N/A') : null;
        $customerLink = ($order && $order->marketplace_customer_id)
            ? \App\Filament\Marketplace\Resources\MarketplaceCustomerResource::getUrl('edit', ['record' => $order->marketplace_customer_id])
            : null;
        [$orderStatusLabel, $orderTone] = match ($order?->status) {
            'paid', 'confirmed', 'completed' => [__('Finalizată'), 'ok'],
            'pending' => [__('În așteptare'), 'warn'],
            'cancelled' => [__('Anulată'), 'bad'],
            'refunded' => [__('Rambursată'), 'bad'],
            default => [ucfirst((string) $order?->status), 'mute'],
        };

        $ticketMeta = is_array($ticket->meta) ? $ticket->meta : [];
        $transferHistory = $ticketMeta['transfers'] ?? [];
        $seatAllocActivities = \Spatie\Activitylog\Models\Activity::query()
            ->where('log_name', 'seat_allocation')
            ->where('subject_type', \App\Models\Ticket::class)
            ->where('subject_id', $ticket->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();
    @endphp

    <style>
        .ep-tk-wrap, .fi-header { --tk-text:#111827; --tk-soft:#4b5563; --tk-muted:#6b7280; --tk-line:#e5e7eb; --tk-card:#fff; --tk-sub:#f9fafb; --tk-dot:#d1d5db; }
        .dark .ep-tk-wrap, .dark .fi-header { --tk-text:#e6e9ee; --tk-soft:#aab3c0; --tk-muted:#8b95a3; --tk-line:#232a35; --tk-card:#12161c; --tk-sub:#0f1318; --tk-dot:#3a4453; }
        .ep-tk-code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing: .02em; }
        .ep-tk-pill { display: inline-flex; align-items: center; gap: 5px; height: 24px; padding: 0 9px; margin-left: 6px; border-radius: 999px; font-size: 12px; font-weight: 600; vertical-align: middle; letter-spacing: 0; font-family: inherit; }
        .ep-tk-pill i { width: 6px; height: 6px; border-radius: 999px; background: currentColor; }
        .ep-tk-pill--ok { background: rgba(16,185,129,.12); color: #059669; } .dark .ep-tk-pill--ok { color: #34d399; }
        .ep-tk-pill--info { background: rgba(59,130,246,.12); color: #1d4ed8; } .dark .ep-tk-pill--info { color: #93c5fd; }
        .ep-tk-pill--warn { background: rgba(245,158,11,.14); color: #b45309; } .dark .ep-tk-pill--warn { color: #fcd34d; }
        .ep-tk-pill--bad { background: rgba(239,68,68,.12); color: #b91c1c; } .dark .ep-tk-pill--bad { color: #fca5a5; }
        .ep-tk-pill--mute { background: rgba(107,114,128,.14); color: var(--tk-soft); }
        .ep-tk-meta { font-size: 13px; font-weight: 400; color: var(--tk-soft); }
        .ep-tk-meta a { color: inherit; text-decoration: underline; text-decoration-color: var(--tk-dot); }
        .ep-tk-sep { color: var(--tk-dot); margin: 0 2px; }
        .ep-tk-wrap { display: flex; flex-direction: column; gap: 20px; color: var(--tk-text); }
        .ep-tk-card { background: var(--tk-card); border: 1px solid var(--tk-line); border-radius: 12px; }
        .ep-tk-card-h { display: flex; align-items: center; gap: 10px; padding: 14px 20px; border-bottom: 1px solid var(--tk-line); }
        .ep-tk-card-h h2 { margin: 0; font-size: 15px; font-weight: 600; }
        .ep-tk-body { padding: 18px 20px; }
        .ep-tk-kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px 20px; }
        .ep-tk-k { font-size: 12px; color: var(--tk-muted); }
        .ep-tk-v { font-size: 14px; font-weight: 500; margin-top: 3px; }
        .ep-tk-v a, .ep-tk-link { color: var(--primary-600); }
        .dark .ep-tk-v a, .dark .ep-tk-link { color: var(--primary-400); }
        .ep-tk-hint { font-size: 12px; color: var(--tk-muted); line-height: 1.45; }
        .ep-tk-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .ep-tk-cols { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
        .ep-tk-main { flex: 999 1 620px; min-width: 0; display: flex; flex-direction: column; gap: 20px; }
        .ep-tk-side { flex: 1 1 300px; max-width: 400px; min-width: 0; display: flex; flex-direction: column; gap: 20px; }
        .ep-tk-entry { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 16px 18px; border-radius: 12px; border: 1px solid var(--tk-line); background: var(--tk-sub); }
        .ep-tk-entry.is-in { border-color: rgba(59,130,246,.35); background: rgba(59,130,246,.06); }
        .ep-tk-cap { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #3b82f6; }
        .ep-tk-tl { display: flex; gap: 12px; padding: 8px 0; font-size: 13px; }
        .ep-tk-tl-dot { flex: none; width: 8px; height: 8px; margin-top: 5px; border-radius: 999px; background: var(--tk-dot); }
        .ep-tk-qr { width: 132px; height: 132px; padding: 8px; border-radius: 10px; background: #fff; box-sizing: border-box; }
        .ep-tk-qr img { width: 100%; height: 100%; display: block; }
        .fi-resource-view-record-page.ep-tk-page .fi-header { flex-direction: row !important; flex-wrap: wrap; align-items: center !important; justify-content: space-between; gap: 12px 16px; }
        .ep-tk-page .fi-header-heading { font-size: 26px; line-height: 1.25; }
        .ep-tk-page .fi-header-subheading { margin-top: 6px; max-width: none; }
        .ep-tk-page .fi-header-actions-ctn { margin-inline-start: auto; }
    </style>

    <div class="ep-tk-wrap">
        {{-- Entry: the first thing the team checks --}}
        @if($ticket->checked_in_at)
            <div class="ep-tk-entry is-in">
                <div style="flex: 1 1 360px;">
                    <div class="ep-tk-cap">{{ __('Intrare') }}</div>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 3px;">{{ __('Scanat la') }} {{ $tz($ticket->checked_in_at, 'd.m.Y H:i:s') }}</div>
                    <div class="ep-tk-hint" style="font-size: 13px; margin-top: 2px;">{{ __('Scanat de') }} {{ $ticket->checked_in_by ?: '—' }} <span class="ep-tk-sep">|</span> {{ $checkInSourceLabel }}</div>
                </div>
            </div>
        @else
            <div class="ep-tk-entry">
                <div style="flex: 1 1 360px;">
                    <div class="ep-tk-cap">{{ __('Intrare') }}</div>
                    <div style="font-size: 16px; font-weight: 600; margin-top: 3px;">{{ __('Nescanat încă') }}</div>
                    @if($event?->event_date)
                        <div class="ep-tk-hint" style="font-size: 13px; margin-top: 2px;">{{ __('Evenimentul are loc pe') }} {{ $event->event_date->format('d.m.Y') }}.</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="ep-tk-cols">
            <div class="ep-tk-main">
                {{-- Ticket --}}
                <section class="ep-tk-card">
                    <div class="ep-tk-card-h"><h2>{{ __('Bilet') }}</h2></div>
                    <div class="ep-tk-body" style="display: flex; flex-wrap: wrap; gap: 24px;">
                        <div style="flex: none; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                            <div class="ep-tk-qr">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($ticket->getVerifyUrl()) }}&color=181622&margin=0" alt="{{ __('QR Code') }}">
                            </div>
                            <span class="ep-tk-hint">{{ __('Cod scanabil la intrare') }}</span>
                        </div>
                        <div style="flex: 1 1 360px; display: flex; flex-direction: column; gap: 18px;">
                            <div class="ep-tk-kv">
                                <div><div class="ep-tk-k">{{ __('Tip bilet') }}</div><div class="ep-tk-v">{{ $ticket->marketplaceTicketType?->name ?? $ticket->ticketType?->name ?? 'N/A' }}</div></div>
                                @if($seat !== '')
                                    <div><div class="ep-tk-k">{{ __('Loc') }}</div><div class="ep-tk-v">{{ $seat }}</div></div>
                                @endif
                                <div>
                                    <div class="ep-tk-k">{{ __('Preț') }}</div>
                                    @if($ticket->hasDiscount())
                                        <div class="ep-tk-v" style="color: #059669;">{{ $money($ticket->getEffectivePrice()) }} <span class="ep-tk-hint" style="text-decoration: line-through;">{{ number_format((float) ($ticket->price ?? 0), 2, ',', '.') }}</span></div>
                                        <div class="ep-tk-hint">{{ __('Reducere aplicată') }}: {{ $money($ticket->getDiscountAmount()) }}</div>
                                    @else
                                        <div class="ep-tk-v">{{ $money($ticket->getEffectivePrice() ?: (($ticket->ticketType?->price_cents ?? 0) / 100)) }}</div>
                                    @endif
                                </div>
                                <div><div class="ep-tk-k">{{ __('Emis') }}</div><div class="ep-tk-v">{{ $tz($ticket->created_at) }}</div></div>
                            </div>
                            @if($ticket->barcode)
                                <div><div class="ep-tk-k">{{ __('Cod de bare') }}</div><div class="ep-tk-mono ep-tk-hint" style="margin-top: 3px; word-break: break-all;">{{ $ticket->barcode }}</div></div>
                            @endif
                        </div>
                    </div>
                </section>

                {{-- Event --}}
                @if($event)
                    <section class="ep-tk-card">
                        <div class="ep-tk-card-h"><h2>{{ __('Eveniment') }}</h2></div>
                        <div class="ep-tk-body" style="display: flex; gap: 16px; align-items: center;">
                            @if($event->poster_url)
                                <img src="{{ Storage::disk('public')->url($event->poster_url) }}" alt="" style="flex: none; width: 54px; height: 72px; object-fit: cover; border-radius: 8px;">
                            @endif
                            <div>
                                <a href="{{ $eventUrl }}" class="ep-tk-link" style="font-size: 15px; font-weight: 600;">{{ $eventTitle }}</a>
                                <div class="ep-tk-hint" style="font-size: 13px; margin-top: 4px;">
                                    {{ $event->event_date ? $event->event_date->format('d.m.Y') : __('TBA') }}@if($event->start_time), {{ substr((string) $event->start_time, 0, 5) }}@endif
                                    @if($venue) <span class="ep-tk-sep">|</span> {{ $venueName }}{{ $venue->city ? ', ' . $venue->city : '' }}@endif
                                </div>
                            </div>
                        </div>
                    </section>
                @endif

                {{-- Order & customer --}}
                @if($order)
                    <section class="ep-tk-card">
                        <div class="ep-tk-card-h"><h2>{{ __('Comandă și client') }}</h2><span class="ep-tk-pill ep-tk-pill--{{ $orderTone }}" style="margin-left: auto;"><i></i>{{ $orderStatusLabel }}</span></div>
                        <div class="ep-tk-body ep-tk-kv">
                            <div>
                                <div class="ep-tk-k">{{ __('Comandă') }}</div>
                                <div class="ep-tk-v"><a href="{{ \App\Filament\Marketplace\Resources\OrderResource::getUrl('view', ['record' => $order]) }}">#{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</a>@if($order->order_number) <span class="ep-tk-mono ep-tk-hint">{{ $order->order_number }}</span>@endif</div>
                            </div>
                            <div>
                                <div class="ep-tk-k">{{ __('Client') }}</div>
                                <div class="ep-tk-v">@if($customerLink)<a href="{{ $customerLink }}">{{ $customerName }}</a>@else{{ $customerName }}@endif</div>
                                @if($order->customer_email)<div class="ep-tk-hint"><a href="{{ $customerLink ?: 'mailto:' . $order->customer_email }}" class="ep-tk-link">{{ $order->customer_email }}</a></div>@endif
                                @if($order->meta['customer_phone'] ?? null)<div class="ep-tk-hint"><a href="tel:{{ $order->meta['customer_phone'] }}" class="ep-tk-link">{{ $order->meta['customer_phone'] }}</a></div>@endif
                            </div>
                            @if($order->marketplaceOrganizer)
                                <div><div class="ep-tk-k">{{ __('Organizator') }}</div><div class="ep-tk-v">{{ $order->marketplaceOrganizer->company_name ?? $order->marketplaceOrganizer->name ?? '' }}</div></div>
                            @elseif($order->tenant)
                                <div><div class="ep-tk-k">{{ __('Organizator') }}</div><div class="ep-tk-v">{{ $order->tenant->name }}</div></div>
                            @endif
                            @if($order->source === 'external_import')
                                <div><div class="ep-tk-k">{{ __('Sursă import') }}</div><div class="ep-tk-v">{{ $order->meta['external_platform'] ?? $order->meta['imported_from'] ?? __('Extern') }}</div></div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>

            <div class="ep-tk-side">
                {{-- History: transfers, seat allocations, issue --}}
                <section class="ep-tk-card">
                    <div class="ep-tk-card-h"><h2>{{ __('Istoric') }}</h2></div>
                    <div style="padding: 10px 20px 14px;">
                        @if($ticket->checked_in_at)
                            <div class="ep-tk-tl"><span class="ep-tk-tl-dot" style="background: #3b82f6;"></span><div><div>{{ __('Check-in') }}</div><div class="ep-tk-hint">{{ $tz($ticket->checked_in_at) }} <span class="ep-tk-sep">|</span> {{ $ticket->checked_in_by ?: '—' }}</div></div></div>
                        @endif
                        @foreach(array_reverse($transferHistory) as $transfer)
                            <div class="ep-tk-tl">
                                <span class="ep-tk-tl-dot" style="background: #6366f1;"></span>
                                <div style="min-width: 0;">
                                    <div>{{ __('Transfer') }}: <strong>{{ $transfer['from_name'] ?? '—' }}</strong> → <strong>{{ $transfer['to_name'] ?? '—' }}</strong></div>
                                    <div class="ep-tk-hint">{{ isset($transfer['at']) ? $tz($transfer['at']) : '—' }}@if($transfer['from_email'] ?? null) <span class="ep-tk-sep">|</span> {{ $transfer['from_email'] }}@endif @if($transfer['to_email'] ?? null) → {{ $transfer['to_email'] }}@endif</div>
                                    @if(($transfer['ip_address'] ?? null) || ($transfer['user_agent'] ?? null))
                                        <div class="ep-tk-hint">@if($transfer['ip_address'] ?? null)<span class="ep-tk-mono">{{ __('IP :ip_address', ['ip_address' => $transfer['ip_address']]) }}</span>@endif @if($transfer['user_agent'] ?? null)<span style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $transfer['user_agent'] }}">{{ \Illuminate\Support\Str::limit($transfer['user_agent'], 90) }}</span>@endif</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @foreach($seatAllocActivities as $act)
                            @php
                                $props = $act->properties ?? collect();
                                if ($props instanceof \Illuminate\Support\Collection) $props = $props->all();
                                $causer = (! empty($act->causer_id) && ! empty($act->causer_type) && class_exists($act->causer_type)) ? $act->causer_type::find($act->causer_id) : null;
                                $causerName = $causer?->name ?? $causer?->full_name ?? $causer?->email ?? __('sistem');
                                $seatHumanAct = trim((string) ($props['section_name'] ?? '') . (! empty($props['row_label']) ? ', ' . __('rând') . ' ' . $props['row_label'] : '') . (! empty($props['seat_label_field']) ? ', ' . __('loc') . ' ' . $props['seat_label_field'] : ''));
                                $reassigned = ($props['action_type'] ?? 'allocated') === 'reassigned';
                            @endphp
                            <div class="ep-tk-tl">
                                <span class="ep-tk-tl-dot" style="background: {{ $reassigned ? '#f59e0b' : '#6366f1' }};"></span>
                                <div style="min-width: 0;">
                                    <div>{{ $reassigned ? __('Reasignare loc') : __('Alocare manuală loc') }}: <span class="ep-tk-mono" style="font-size: 12px;">{{ $props['seat_uid'] ?? '—' }}</span>@if($seatHumanAct) <span class="ep-tk-hint">({{ $seatHumanAct }})</span>@endif</div>
                                    <div class="ep-tk-hint">{{ $tz($act->created_at) }} <span class="ep-tk-sep">|</span> {{ $causerName }}</div>
                                    @if(! empty($props['previous_seat_uid']))<div class="ep-tk-hint">{{ __('Loc anterior eliberat:') }} <span class="ep-tk-mono">{{ $props['previous_seat_uid'] }}</span></div>@endif
                                    @if(! empty($props['reason']))<div class="ep-tk-hint" style="font-style: italic;">{{ __('Motiv: :reason', ['reason' => $props['reason']]) }}</div>@endif
                                    @if(! empty($props['order_number']) || ! empty($props['customer_email']))
                                        <div class="ep-tk-hint">@if(! empty($props['order_number'])){{ __('Comandă') }} {{ $props['order_number'] }}@endif @if(! empty($props['customer_name']) || ! empty($props['customer_email']))<span class="ep-tk-sep">|</span> {{ $props['customer_name'] ?? '' }} {{ ! empty($props['customer_email']) ? '(' . $props['customer_email'] . ')' : '' }}@endif</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                        @if($seatAllocActivities->isEmpty() && $seat !== '')
                            <div class="ep-tk-tl"><span class="ep-tk-tl-dot"></span><div><div>{{ __('Loc atribuit automat la checkout') }}</div><div class="ep-tk-hint">{{ __('nicio realocare manuală') }}</div></div></div>
                        @endif
                        <div class="ep-tk-tl"><span class="ep-tk-tl-dot" style="background: #10b981;"></span><div><div>{{ __('Bilet emis') }}@if($order) {{ __('comanda') }} #{{ $order->id }}@endif</div><div class="ep-tk-hint">{{ $tz($ticket->created_at) }}</div></div></div>
                    </div>
                </section>

                @if($beneficiary)
                    <section class="ep-tk-card">
                        <div class="ep-tk-card-h"><h2>{{ __('Beneficiar') }}</h2></div>
                        <div class="ep-tk-body" style="display: flex; flex-direction: column; gap: 4px;">
                            <div class="ep-tk-v" style="margin: 0;">{{ $beneficiary['name'] ?? 'N/A' }}</div>
                            @if($beneficiary['email'] ?? null)<div class="ep-tk-hint">{{ $beneficiary['email'] }}</div>@endif
                            @if($beneficiary['phone'] ?? null)<div class="ep-tk-hint">{{ $beneficiary['phone'] }}</div>@endif
                        </div>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
