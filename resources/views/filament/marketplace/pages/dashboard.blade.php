<x-filament-panels::page>
    @if(!$marketplace)
        <div class="p-6 text-center border border-yellow-200 rounded-lg bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800">
            <p class="text-yellow-800 dark:text-yellow-200">{{ __('No marketplace account found. Please contact support.') }}</p>
        </div>
    @else
        @php
            $isSA = $isSuperAdmin ?? false;
            $hasChat = (bool) \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->user()?->marketplaceClient?->hasMicroservice('live-chat');
            $tzName = \App\Support\MarketplaceTz::tz($marketplace);
            $nowTz = \Illuminate\Support\Carbon::now($tzName)->locale(app()->getLocale());
            $hasSupport = isset($pendingSupportTickets) && $pendingSupportTickets->count() > 0;
            $hasReview = isset($pendingReviewEvents) && $pendingReviewEvents->count() > 0;
            $hasRecent = isset($recentlyPublishedEvents);
            $showLost = $isSA && isset($invitationAbuse) && ($invitationAbuse['all_time']['summary']['events_count'] ?? 0) > 0;
            $salesBreakdownUrl = route('filament.marketplace.pages.sales-breakdown', ['month' => ($chartMonthMeta['monthRef'] ?? \Carbon\Carbon::now('Europe/Bucharest')->format('Y-m'))]);
            $initialPanel = !empty($expiredCapiTokens) ? 'tokens' : null;
        @endphp

        <style>
            .ep-dash {
                --ep-card: #ffffff; --ep-bd: #e5e7eb; --ep-bd2: #f1f3f5; --ep-tx: #111827; --ep-mu: #6b7280; --ep-mu2: #4b5563;
                --ep-in: #f3f4f6; --ep-on: #ffffff; --ep-btn: #ffffff; --ep-btnbd: #d1d5db; --ep-hover: #9ca3af; --ep-link: #2563eb;
                --ep-amber: #b45309; --ep-rose: #be123c; --ep-green: #047857; --ep-yellow: #ca8a04;
                --ep-g1: #4f46e5; --ep-g2: #7e22ce; --ep-g3: #047857; --ep-g3bg: rgba(16, 185, 129, .08);
                --ep-okbg: #ecfdf5; --ep-warnbg: #fffbeb; --ep-pos: #047857; --ep-neg: #b91c1c;
                color: var(--ep-tx); max-width: 1360px; margin: 0 auto; width: 100%;
                display: flex; flex-direction: column; gap: 22px;
            }
            .dark .ep-dash {
                --ep-card: #12161c; --ep-bd: #232a35; --ep-bd2: #1c222b; --ep-tx: #e6e9ee; --ep-mu: #8b95a3; --ep-mu2: #aab3c0;
                --ep-in: #0e1217; --ep-on: #232a35; --ep-btn: #171c23; --ep-btnbd: #2c3441; --ep-hover: #3a4453; --ep-link: #7cb4ff;
                --ep-amber: #fbbf24; --ep-rose: #fca5a5; --ep-green: #6ee7b7; --ep-yellow: #facc15;
                --ep-g1: #a5b4fc; --ep-g2: #d8b4fe; --ep-g3: #6ee7b7; --ep-g3bg: rgba(16, 185, 129, .06);
                --ep-okbg: #10261d; --ep-warnbg: #2a2110; --ep-pos: #34d399; --ep-neg: #f87171;
            }
            .ep-dash a { color: var(--ep-link); text-decoration: none; }
            .ep-dash a:hover { text-decoration: underline; }
            .ep-dash [x-cloak] { display: none !important; }
            .ep-card { background: var(--ep-card); border: 1px solid var(--ep-bd); border-radius: 12px; }
            .ep-card-h { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; padding: 14px 20px; border-bottom: 1px solid var(--ep-bd); }
            .ep-card-h h2 { margin: 0; font-size: 15px; font-weight: 600; color: var(--ep-tx); }
            .ep-hint { font-size: 12px; color: var(--ep-mu); line-height: 1.45; }
            .ep-cap { font-size: 11px; font-weight: 600; line-height: 1; letter-spacing: .06em; text-transform: uppercase; color: var(--ep-mu); }
            .ep-sec { display: flex; flex-direction: column; gap: 10px; }
            .ep-sec-h { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
            .ep-btn { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--ep-btnbd); background: var(--ep-btn); color: var(--ep-tx); font-size: 12px; font-weight: 500; line-height: 1; cursor: pointer; white-space: nowrap; }
            .ep-dash a.ep-btn { color: var(--ep-tx); }
            .ep-btn:disabled { opacity: .35; cursor: not-allowed; }
            .ep-btn-ghost { border-color: transparent; background: transparent; color: var(--ep-mu2); }
            .ep-dash a.ep-btn-ghost { color: var(--ep-link); }
            .ep-in { height: 30px; padding: 0 10px; border-radius: 8px; border: 1px solid var(--ep-btnbd); background: var(--ep-btn); color: var(--ep-tx); font-size: 12px; color-scheme: light; }
            .dark .ep-in { color-scheme: dark; }
            .ep-seg { display: inline-flex; flex-wrap: wrap; padding: 3px; border-radius: 9px; background: var(--ep-in); border: 1px solid var(--ep-btnbd); gap: 2px; }
            .ep-seg button { height: 26px; padding: 0 10px; border: 0; border-radius: 7px; background: transparent; color: var(--ep-mu); font-size: 12px; font-weight: 500; line-height: 1; cursor: pointer; }
            .ep-seg button.on { background: var(--ep-on); color: var(--ep-tx); box-shadow: 0 1px 2px rgba(0, 0, 0, .12); }

            /* Cer atentie */
            .ep-attrow { display: flex; flex-wrap: wrap; gap: 12px; }
            .ep-att { flex: 1 1 260px; display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 12px; border: 1px solid var(--ep-bd); background: var(--ep-card); color: var(--ep-tx); text-align: left; cursor: pointer; text-decoration: none; font: inherit; }
            .ep-dash a.ep-att { color: var(--ep-tx); }
            .ep-att:hover, .ep-dash a.ep-att:hover { border-color: var(--ep-hover); text-decoration: none; }
            .ep-att .n { font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; min-width: 28px; }
            .ep-att .t { font-weight: 600; font-size: 14px; }
            .ep-att .chev { margin-left: auto; width: 16px; height: 16px; color: var(--ep-mu); transition: transform .15s; flex: none; }
            .ep-att .chev.open { transform: rotate(90deg); }
            .ep-att.tone-red { border-color: rgba(220, 38, 38, .5); } .ep-att.tone-red .n { color: var(--ep-neg); }
            .ep-att.tone-orange { border-color: rgba(234, 88, 12, .5); } .ep-att.tone-orange .n { color: #ea580c; }
            .ep-att.tone-amber .n, .ep-att.tone-yellow .n { color: var(--ep-amber); }
            .ep-att.tone-yellow { border-color: rgba(217, 119, 6, .45); }

            /* KPI-uri */
            .ep-kpis { display: grid; gap: 1px; background: var(--ep-bd); border: 1px solid var(--ep-bd); border-radius: 12px; overflow: hidden; }
            .ep-k6 { grid-template-columns: repeat(6, minmax(0, 1fr)); }
            .ep-k3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .ep-k4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .ep-kpi { background: var(--ep-card); padding: 16px 18px; display: flex; flex-direction: column; gap: 4px; min-width: 0; }
            .ep-kpi .l { font-size: 12px; color: var(--ep-mu); }
            .ep-kpi .v { font-size: 24px; font-weight: 700; letter-spacing: -.01em; font-variant-numeric: tabular-nums; }
            .ep-kpi .v small { font-size: 13px; font-weight: 500; color: var(--ep-mu); }
            .ep-kpi .s { font-size: 12px; color: var(--ep-mu); line-height: 1.4; }
            .ep-mini { background: var(--ep-card); padding: 12px 14px; display: flex; flex-direction: column; gap: 3px; min-width: 0; }
            .ep-mini .l { font-size: 11.5px; color: var(--ep-mu); }
            .ep-mini .v { font-size: 17px; font-weight: 700; font-variant-numeric: tabular-nums; }
            .ep-mini .v small { font-size: 12px; font-weight: 500; color: var(--ep-mu); }
            .ep-mini .s { font-size: 11.5px; color: var(--ep-mu); line-height: 1.4; }
            .ep-mini.ok { background: var(--ep-okbg); } .ep-mini.ok .v { color: var(--ep-green); }
            .ep-mini.warn { background: var(--ep-warnbg); } .ep-mini.warn .v { color: var(--ep-amber); }
            .ep-mini.pred { background: var(--ep-g3bg); } .ep-mini.pred .v { color: var(--ep-g3); }
            .ep-amber { color: var(--ep-amber); } .ep-rose { color: var(--ep-rose); } .ep-green { color: var(--ep-green); }
            .ep-pct { font-weight: 600; } .ep-pct.pos { color: var(--ep-pos); } .ep-pct.neg { color: var(--ep-neg); }
            .ep-grp { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; padding: 0 2px 8px; }

            /* Coloane */
            .ep-cols { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
            .ep-main { flex: 999 1 640px; min-width: 0; display: flex; flex-direction: column; gap: 20px; }
            .ep-rail { flex: 1 1 300px; max-width: 400px; min-width: 0; display: flex; flex-direction: column; gap: 20px; }
            @media (max-width: 1100px) { .ep-rail { max-width: none; flex-basis: 100%; } }

            /* Randuri / liste */
            .ep-row { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; padding: 7px 0; border-top: 1px solid var(--ep-bd2); }
            .ep-row:first-child { border-top: 0; }
            .ep-row > span:first-child { color: var(--ep-mu2); }
            .ep-row .num { font-variant-numeric: tabular-nums; text-align: right; }
            .ep-li { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 20px; border-top: 1px solid var(--ep-bd2); }
            .ep-li:first-child { border-top: 0; }
            .ep-li .main { flex: 1; min-width: 0; }
            .ep-li .ttl { font-size: 14px; font-weight: 500; }
            .ep-li .meta { display: flex; flex-wrap: wrap; gap: 2px 12px; margin-top: 3px; font-size: 12px; color: var(--ep-mu); }
            .ep-list-scroll { max-height: 24rem; overflow-y: auto; }
            .ep-badge { display: inline-flex; align-items: center; height: 18px; padding: 0 7px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--ep-in); color: var(--ep-mu2); margin: 0 6px 0 4px; }
            .ep-badge.t-red { background: rgba(220, 38, 38, .14); color: var(--ep-neg); }
            .ep-badge.t-blue { background: rgba(59, 130, 246, .14); color: var(--ep-link); }
            .ep-badge.t-amber { background: rgba(245, 158, 11, .16); color: var(--ep-amber); }
            .ep-badge.t-green { background: rgba(16, 185, 129, .14); color: var(--ep-pos); }
            .ep-badge.t-gray { background: var(--ep-in); color: var(--ep-mu2); }

            /* Tabele */
            .ep-tbl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
            .ep-tbl th { font-weight: 500; color: var(--ep-mu); padding: 9px 14px; border-bottom: 1px solid var(--ep-bd); text-align: right; white-space: nowrap; background: var(--ep-card); }
            .ep-tbl th.l { text-align: left; }
            .ep-tbl th.grp { text-align: center; font-weight: 600; }
            .ep-tbl th.g1 { color: var(--ep-g1); } .ep-tbl th.g2 { color: var(--ep-g3); }
            .ep-tbl td { padding: 11px 14px; border-bottom: 1px solid var(--ep-bd2); }
            .ep-tbl td.num { text-align: right; font-variant-numeric: tabular-nums; }
            .ep-tbl td.strong { font-weight: 600; } .ep-tbl td.mut { color: var(--ep-mu2); }
            .ep-tbl .sep { border-left: 1px solid var(--ep-bd); }
            .ep-tbl td .sub { display: block; font-size: 11px; font-weight: 400; color: var(--ep-mu); }
            .ep-tbl .clip { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
            .ep-tbl td.nm, .ep-tbl td.ven { overflow: hidden; }
            .ep-tbl a.ep-evlink { color: var(--ep-tx); }
            .ep-tbl a.ep-evlink:hover { color: var(--ep-link); }
            .ep-tbl .evn { font-weight: 500; }
            .ep-tbl .multi { background: var(--ep-warnbg); } .ep-tbl .multi-name { font-weight: 500; color: var(--ep-amber); }
            .ep-tbl tfoot td { border-bottom: 0; border-top: 1px solid var(--ep-bd); font-weight: 600; background: var(--ep-card); }
            .ep-tbl tfoot td.mut { font-weight: 500; }
            .ep-tbl thead.ep-sticky th { position: sticky; z-index: 5; }
            .ep-tbl thead.ep-sticky tr.r1 th { top: 0; height: 36px; box-sizing: border-box; }
            .ep-tbl thead.ep-sticky tr.r2 th { top: 36px; }
            .ep-drwrap { overflow: auto; max-height: 70vh; border-radius: 0 0 12px 12px; }
            .ep-showall { width: 100%; padding: 10px 12px; font-size: 13px; font-weight: 600; color: var(--ep-link); background: transparent; border: 0; cursor: pointer; }
            .ep-showall:hover { background: var(--ep-in); }
            .ep-tblwrap { overflow-x: auto; }

            /* Grafic */
            .ep-legend { padding: 14px 20px 4px; display: flex; flex-wrap: wrap; gap: 14px; }
            .ep-legend .sw { display: inline-block; vertical-align: middle; margin-right: 4px; }
            .ep-chart { position: relative; height: 260px; padding: 10px 20px 12px; box-sizing: content-box; }
            .ep-chart > div { position: relative; height: 100%; }
            .ep-monthnav { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px 20px; border-bottom: 1px solid var(--ep-bd); }
            .ep-mbadge { display: inline-flex; align-items: center; height: 20px; padding: 0 7px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--ep-in); color: var(--ep-mu2); }
            .ep-mbadge.cur, .ep-mbadge.future { background: rgba(16, 185, 129, .12); color: var(--ep-pos); }
            .ep-sumwrap { display: flex; flex-direction: column; gap: 14px; padding: 6px 20px 20px; }

            /* Eveniment urmarit */
            .ep-fe-body { padding: 16px 20px; display: flex; flex-direction: column; gap: 16px; }
            .ep-blocks { display: flex; flex-wrap: wrap; gap: 12px; }
            .ep-block { flex: 1 1 260px; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--ep-bd); }
            .ep-block h3 { margin: 0 0 6px; font-size: 13px; font-weight: 600; }

            /* Ceas */
            .ep-clock { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 26px; font-weight: 600; letter-spacing: .02em; padding: 6px 12px; border-radius: 10px; background: var(--ep-card); border: 1px solid var(--ep-bd); font-variant-numeric: tabular-nums; }

            @media (max-width: 1279px) { .ep-k6 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
            @media (max-width: 640px) {
                .ep-k6, .ep-k3.six { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .ep-k3, .ep-k4 { grid-template-columns: minmax(0, 1fr); }
                .ep-k4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                .ep-li { flex-wrap: wrap; padding: 12px 14px; }
            }
        </style>

        <div class="ep-dash"
             x-data="{ panel: @js($initialPanel), lostOpen: false, jump(id) { this.$nextTick(() => { const el = document.getElementById(id); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }); } }">

            {{-- Titlu + data + ceas (fusul orar al contului) --}}
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px 16px;">
                <h1 style="margin: 0; font-size: 26px; font-weight: 700; letter-spacing: -0.01em; flex: 1 1 240px;">{{ __('Panou de control') }}</h1>
                <div style="display: flex; align-items: center; gap: 16px;"
                     x-data="{
                        now: new Date(), tz: @js($tzName), loc: @js(app()->getLocale()),
                        fmt(o) { try { return new Intl.DateTimeFormat(this.loc, Object.assign({ timeZone: this.tz }, o)).format(this.now); } catch (e) { return ''; } }
                     }"
                     x-init="const t = setInterval(() => { if (!$el.isConnected) { clearInterval(t); return; } now = new Date(); }, 1000)">
                    <div style="text-align: right; line-height: 1.25;">
                        <div style="font-size: 13px; color: var(--ep-mu2); text-transform: capitalize;" x-text="fmt({ weekday: 'long' })">{{ $nowTz->translatedFormat('l') }}</div>
                        <div style="font-size: 14px; font-weight: 600;" x-text="fmt({ day: 'numeric', month: 'long', year: 'numeric' })">{{ $nowTz->translatedFormat('j F Y') }}</div>
                    </div>
                    <div class="ep-clock" role="timer" aria-label="{{ __('Ora curentă') }}" x-text="fmt({ hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23' })">{{ $nowTz->format('H:i:s') }}</div>
                </div>
            </div>

            {{-- Cer atenție: carduri clicabile. Alertele condiționale (tokenuri Facebook,
                 tichete suport, evenimente de revizuit) apar doar când există; cardul
                 de evenimente publicate recent deschide lista. --}}
            @if($hasChat || !empty($expiredCapiTokens) || $hasSupport || $hasReview || $hasRecent)
            <div class="ep-sec" id="ep-attention">
                <div class="ep-cap">{{ __('Cer atenție') }}</div>
                <div class="ep-attrow">
                    @if($hasChat)
                        @livewire(\App\Livewire\Marketplace\ChatAttentionCard::class)
                    @endif

                    @if(!empty($expiredCapiTokens))
                    <button type="button" class="ep-att tone-red" @click="panel = panel === 'tokens' ? null : 'tokens'" :aria-expanded="panel === 'tokens'">
                        <span class="n">{{ count($expiredCapiTokens) }}</span>
                        <div>
                            <div class="t">{{ __('Tokenuri Facebook expirate') }}</div>
                            <div class="ep-hint">{{ __('Conversiile nu ajung în Meta până la schimbarea tokenului') }}</div>
                        </div>
                        <svg class="chev" :class="{ open: panel === 'tokens' }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                    </button>
                    @endif

                    @if($hasSupport)
                    <button type="button" class="ep-att tone-orange" @click="panel = panel === 'support' ? null : 'support'" :aria-expanded="panel === 'support'">
                        <span class="n">{{ $pendingSupportTicketsCount }}</span>
                        <div>
                            <div class="t">{{ __('Tichete suport active') }}</div>
                            <div class="ep-hint">{{ __('Deschide lista') }}</div>
                        </div>
                        <svg class="chev" :class="{ open: panel === 'support' }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                    </button>
                    @endif

                    @if($hasReview)
                    <button type="button" class="ep-att tone-yellow" @click="panel = panel === 'review' ? null : 'review'" :aria-expanded="panel === 'review'">
                        <span class="n">{{ $pendingReviewEvents->count() }}</span>
                        <div>
                            <div class="t">{{ __('Evenimente de revizuit') }}</div>
                            <div class="ep-hint">{{ __('Deschide lista') }}</div>
                        </div>
                        <svg class="chev" :class="{ open: panel === 'review' }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                    </button>
                    @endif

                    @if($hasRecent)
                    <button type="button" class="ep-att" @click="panel = panel === 'recent' ? null : 'recent'" :aria-expanded="panel === 'recent'">
                        <span class="n">{{ $recentlyPublishedEvents->count() }}</span>
                        <div>
                            <div class="t">{{ __('Evenimente publicate') }}</div>
                            <div class="ep-hint">{{ __('în ultimele 7 zile') }}</div>
                        </div>
                        <svg class="chev" :class="{ open: panel === 'recent' }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                    </button>
                    @endif
                </div>

                {{-- Tokenuri Facebook CAPI expirate / invalide, pe organizator --}}
                @if(!empty($expiredCapiTokens))
                <div class="ep-card" x-show="panel === 'tokens'" x-collapse x-cloak style="overflow: hidden;">
                    <div class="ep-card-h">
                        <h2>{{ __('Tokenuri Facebook expirate') }}</h2>
                        <span class="ep-hint">{{ __('Conversiile nu ajung în Meta până la schimbarea tokenului') }}</span>
                    </div>
                    @foreach($expiredCapiTokens as $capi)
                    @php
                        $capiEditUrl = route('filament.marketplace.resources.organizers.edit', ['record' => $capi['organizer_id']]) . '?tab=bilete-termeni';
                    @endphp
                    <div class="ep-li">
                        <div class="main">
                            <div>
                                <a href="{{ $capiEditUrl }}" class="ttl">{{ $capi['organizer_name'] }}</a>
                                <span class="ep-badge t-red">{{ $capi['is_expired'] ? 'Expirat' : 'Invalid' }}</span>
                                <span class="ep-hint">Pixel {{ $capi['pixel_id'] }}</span>
                            </div>
                            <div class="meta">
                                @if($capi['expired_at'])
                                    <span>{{ __('Expirat pe') }} <strong>{{ \App\Support\MarketplaceTz::fmt($capi['expired_at'], 'd.m.Y H:i', fallback: '') }}</strong></span>
                                @endif
                                @if($capi['first_failed_at'])
                                    <span>Respins din {{ \App\Support\MarketplaceTz::fmt($capi['first_failed_at'], 'd.m.Y H:i', fallback: '') }}</span>
                                @endif
                                <span>{{ $capi['failed_count'] }} {{ $capi['failed_count'] === 1 ? 'eveniment pierdut' : 'evenimente pierdute' }}</span>
                                <span>Ultimul trimis cu succes: {{ $capi['last_sent_at'] ? \App\Support\MarketplaceTz::fmt($capi['last_sent_at'], 'd.m.Y H:i', fallback: '') : 'niciodată' }}</span>
                            </div>
                            @if($capi['message'])
                                <p class="ep-hint" style="margin: 4px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $capi['message'] }}">{{ $capi['message'] }}</p>
                            @endif
                        </div>
                        <a href="{{ $capiEditUrl }}" class="ep-btn">{{ __('Schimbă tokenul') }}</a>
                    </div>
                    @endforeach
                    <p class="ep-hint" style="margin: 0; padding: 10px 20px; border-top: 1px solid var(--ep-bd2);">
                        {{ __('Tokenul se schimbă în tab-ul „Bilete & Termeni”, secțiunea Facebook Conversions API. Rândul dispare după primul eveniment trimis cu succes (lista se actualizează la 10 minute).') }}
                    </p>
                </div>
                @endif

                {{-- Tichete suport active — vizibile tuturor adminilor --}}
                @if($hasSupport)
                <div class="ep-card" x-show="panel === 'support'" x-collapse x-cloak style="overflow: hidden;">
                    <div class="ep-card-h">
                        <h2>{{ __('Tichete suport active') }}</h2>
                        <a href="{{ route('filament.marketplace.resources.support-tickets.index') }}" style="margin-left: auto; font-size: 12px;">{{ __('Vezi toate') }}</a>
                    </div>
                    <div class="ep-list-scroll">
                        @foreach($pendingSupportTickets as $supportTicket)
                        @php
                            $statusLabel = match ($supportTicket->status) {
                                'open' => 'Deschis',
                                'in_progress' => 'Așteaptă răspuns',
                                'awaiting_organizer' => 'Răspuns trimis',
                                default => $supportTicket->status,
                            };
                            $statusTone = match ($supportTicket->status) {
                                'open' => 't-blue',
                                'in_progress' => 't-amber',
                                'awaiting_organizer' => 't-green',
                                default => 't-gray',
                            };
                            $openerName = $supportTicket->opener?->name ?? $supportTicket->opener?->public_name ?? '—';
                        @endphp
                        <div class="ep-li">
                            <div class="main">
                                <div>
                                    <a href="{{ route('filament.marketplace.resources.support-tickets.view', $supportTicket->id) }}" class="ttl">{{ $supportTicket->subject }}</a>
                                    <span class="ep-badge {{ $statusTone }}">{{ $statusLabel }}</span>
                                    <span class="ep-hint">{{ $supportTicket->ticket_number }}</span>
                                </div>
                                <div class="meta">
                                    <span>{{ $openerName }}</span>
                                    <span>{{ $supportTicket->department?->getTranslation('name', 'ro') ?? '—' }}</span>
                                    @if ($supportTicket->assignee)
                                        <span>Asignat: {{ $supportTicket->assignee->name }}</span>
                                    @endif
                                    <span>{{ $supportTicket->last_activity_at?->diffForHumans() }}</span>
                                </div>
                            </div>
                            <a href="{{ route('filament.marketplace.resources.support-tickets.view', $supportTicket->id) }}" class="ep-btn">{{ __('Deschide') }}</a>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Evenimente de revizuit — vizibile tuturor adminilor --}}
                @if($hasReview)
                <div class="ep-card" x-show="panel === 'review'" x-collapse x-cloak style="overflow: hidden;">
                    <div class="ep-card-h">
                        <h2>{{ __('Evenimente de revizuit') }}</h2>
                        <a href="{{ route('filament.marketplace.resources.events.index') }}?tableFilters[is_published][value]=0" style="margin-left: auto; font-size: 12px;">{{ __('Vezi toate') }}</a>
                    </div>
                    <div class="ep-list-scroll">
                        @foreach($pendingReviewEvents->take(10) as $event)
                        <div class="ep-li">
                            <div class="main">
                                <div>
                                    <a href="{{ route('filament.marketplace.resources.events.edit', $event->id) }}" class="ttl">{{ $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') }}</a>
                                    @if($event->suggested_venue_name)
                                        <span class="ep-badge t-amber">{{ $event->suggested_venue_name }}</span>
                                    @endif
                                </div>
                                <div class="meta">
                                    @if($event->marketplaceOrganizer)
                                        <span>{{ $event->marketplaceOrganizer->name }}</span>
                                    @endif
                                    @if($event->event_date)
                                        <span>{{ $event->event_date->format('d.m.Y') }}</span>
                                    @endif
                                    @if($event->venue)
                                        <span>{{ $event->venue->getTranslation('name', 'ro') }}</span>
                                    @endif
                                    <span>Trimis {{ $event->submitted_at->diffForHumans() }}</span>
                                </div>
                            </div>
                            <a href="{{ route('filament.marketplace.resources.events.edit', $event->id) }}" class="ep-btn">{{ __('Revizuieste') }}</a>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Evenimente publicate recent (ultimele 7 zile) — vizibile tuturor adminilor --}}
                @if($hasRecent)
                <div class="ep-card" id="ep-recent" x-show="panel === 'recent'" x-collapse x-cloak style="overflow: hidden;">
                    <div class="ep-card-h">
                        <h2>{{ __('Evenimente publicate recent') }}</h2>
                        <span class="ep-hint">{{ __('ultimele 7 zile') }}</span>
                        <a href="{{ route('filament.marketplace.resources.events.index') }}" style="margin-left: auto; font-size: 12px;">{{ __('Vezi toate') }}</a>
                    </div>
                    @if($recentlyPublishedEvents->isEmpty())
                        <p class="ep-hint" style="margin: 0; padding: 14px 20px;">{{ __('Niciun eveniment publicat în ultimele 7 zile.') }}</p>
                    @else
                    <div class="ep-list-scroll">
                        @foreach($recentlyPublishedEvents as $event)
                        @php $eventDate = $event->event_date ?? $event->range_start_date; @endphp
                        <div class="ep-li">
                            <div class="main">
                                <a href="{{ route('filament.marketplace.resources.events.edit', $event->id) }}" class="ttl">{{ $event->getTranslation('title', 'ro') ?: $event->getTranslation('title', 'en') }}</a>
                                <div class="meta">
                                    @if($event->marketplaceOrganizer)
                                        <span>{{ $event->marketplaceOrganizer->name }}</span>
                                    @endif
                                    @if($eventDate)
                                        <span>{{ \Carbon\Carbon::parse($eventDate)->format('d.m.Y') }}</span>
                                    @endif
                                    @if($event->venue)
                                        <span>{{ $event->venue->getTranslation('name', 'ro') }}</span>
                                    @endif
                                    <span>Publicat {{ \Carbon\Carbon::parse($event->published_moment)->diffForHumans() }}</span>
                                </div>
                            </div>
                            <a href="{{ route('filament.marketplace.resources.events.edit', $event->id) }}" class="ep-btn">{{ __('Deschide') }}</a>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif
            </div>
            @endif

            @if($isSA)
            {{-- Azi --}}
            @if(isset($todayStats))
            <div class="ep-sec">
                <div class="ep-sec-h">
                    <div class="ep-cap">{{ __('Azi') }}</div>
                    <span class="ep-hint">{{ $todayStats['date_label'] }}</span>
                </div>
                <div class="ep-kpis ep-k6">
                    <div class="ep-kpi">
                        <span class="l">{{ __('Încasări') }}</span>
                        <span class="v">{{ number_format($todayStats['revenue'], 2) }} <small>{{ __('RON') }}</small></span>
                    </div>
                    <div class="ep-kpi">
                        <span class="l">{{ __('Comisioane') }}</span>
                        <span class="v">{{ number_format($todayStats['commission'] ?? 0, 2) }} <small>{{ __('RON') }}</small></span>
                        @if(($todayVatRate ?? 0) > 0)
                        <span class="s">net | +TVA {{ number_format(($todayStats['commission'] ?? 0) * $todayVatRate / 100, 2) }} RON</span>
                        @endif
                    </div>
                    <div class="ep-kpi">
                        <span class="l">{{ __('Bilete vândute') }}</span>
                        <span class="v">{{ number_format($todayStats['tickets_sold']) }}</span>
                    </div>
                    <div class="ep-kpi">
                        <span class="l">{{ __('Comenzi') }}</span>
                        <span class="v">{{ number_format($todayStats['total_orders']) }}</span>
                        @if(($todayStats['total_orders'] ?? 0) > 0)
                        <span class="s">{{ $todayStats['paid_orders'] ?? 0 }} {{ __('plătite') }} | {{ $todayStats['pending_orders'] ?? 0 }} pending | {{ $todayStats['failed_orders'] ?? 0 }} {{ __('eșuate') }} | {{ $todayStats['cancelled_orders'] ?? 0 }} {{ __('anulate') }} | {{ $todayStats['expired_orders'] ?? 0 }} {{ __('expirate') }} | {{ $todayStats['refunded_orders'] ?? 0 }} {{ __('rambursate') }}</span>
                        @endif
                    </div>
                    <div class="ep-kpi">
                        <span class="l">{{ __('Clienți noi') }}</span>
                        <span class="v">{{ number_format($todayStats['new_customers']) }}</span>
                        <span class="s">{{ $todayStats['registered_customers'] ?? 0 }} {{ __('cu cont') }} | {{ $todayStats['guest_customers'] ?? 0 }} {{ __('oaspeți') }}</span>
                    </div>
                    <div class="ep-kpi">
                        <span class="l">{{ __('Evenimente publicate') }}</span>
                        <span class="v">{{ number_format($todayStats['events_published'] ?? 0) }}</span>
                    </div>
                </div>
            </div>
            @endif

            <div class="ep-cols">
            <div class="ep-main">

                {{-- Luna --}}
                @if(isset($monthStats))
                <div class="ep-sec">
                    <div class="ep-sec-h">
                        <div class="ep-cap">{{ __('Luna') }}</div>
                        <span class="ep-hint">{{ $monthStats['month_label'] }}</span>
                        <input type="month"
                            wire:model.live="selectedMonth"
                            class="ep-in"
                            style="margin-left: auto;"
                            max="{{ now()->format('Y-m') }}"
                        />
                    </div>
                    @php $mVat = $monthStats['vat'] ?? null; @endphp
                    <div class="ep-kpis ep-k3 six">
                        <div class="ep-kpi">
                            <span class="l">{{ __('Total vânzări') }}</span>
                            <span class="v">{{ number_format($monthStats['total_sales'], 0) }} <small>{{ $monthStats['currency'] }}</small></span>
                            <span class="s">{{ __('medie zilnică') }} {{ number_format($monthStats['avg_daily_sales'], 0) }} {{ $monthStats['currency'] }}</span>
                        </div>
                        <div class="ep-kpi">
                            <span class="l">{{ __('Venituri brute') }}</span>
                            <span class="v ep-amber">{{ number_format($monthStats['total_commission'], 0) }} <small>{{ $monthStats['currency'] }}</small></span>
                        </div>
                        <div class="ep-kpi">
                            <span class="l">{{ __('TVA de plată') }} @if(!empty($mVat['from_label']))<span>(din {{ $mVat['from_label'] }})</span>@endif</span>
                            <span class="v ep-rose">{{ number_format($mVat['total'] ?? 0, 2) }} <small>{{ $monthStats['currency'] }}</small></span>
                            @if(($mVat['total'] ?? 0) > 0)
                            <span class="s">comision {{ number_format($mVat['commission'], 2) }} | servicii {{ number_format($mVat['services'], 2) }} | taxe retur/card cultural {{ number_format($mVat['other'], 2) }}</span>
                            @endif
                        </div>
                        <div class="ep-kpi">
                            <span class="l">{{ __('Bilete vândute') }}</span>
                            <span class="v">{{ number_format($monthStats['tickets_sold']) }}</span>
                            <span class="s">{{ number_format($monthStats['month_orders']) }} {{ __('comenzi') }}</span>
                        </div>
                        <div class="ep-kpi">
                            <span class="l">{{ __('Clienți noi') }}</span>
                            <span class="v">{{ number_format($monthStats['new_customers']) }}</span>
                        </div>
                        <div class="ep-kpi">
                            <span class="l">{{ __('Evenimente live acum') }}</span>
                            <span class="v">{{ number_format($monthStats['live_events']) }}</span>
                            <span class="s">{{ number_format($monthStats['ended_events']) }} {{ __('încheiate') }} | {{ number_format($monthStats['new_organizers']) }} {{ __('organizatori noi') }}</span>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Vânzări și bilete (Chart.js — scriptul de mai jos citește #combinedChart) --}}
                <section class="ep-card" aria-labelledby="ep-h-ch" wire:key="charts-{{ $chartPeriod }}-{{ $chartMonth }}">
                    <div class="ep-card-h">
                        <h2 id="ep-h-ch">{{ __('Vânzări și bilete') }}</h2>
                        <div class="ep-seg" role="group" aria-label="{{ __('Perioadă') }}" style="margin-left: auto;">
                            @foreach(['month' => 'Avansat', '7' => '7 zile', '15' => '15 zile', '30' => '30 zile', '60' => '60 zile', '90' => '90 zile'] as $pVal => $pLabel)
                                <button type="button"
                                        wire:click="$set('chartPeriod', '{{ $pVal }}')"
                                        class="{{ (string) $chartPeriod === (string) $pVal ? 'on' : '' }}"
                                        aria-pressed="{{ (string) $chartPeriod === (string) $pVal ? 'true' : 'false' }}">{{ __($pLabel) }}</button>
                            @endforeach
                        </div>
                    </div>

                    @if($chartPeriod === 'month' && $chartMonthMeta)
                    <div class="ep-monthnav">
                        <button type="button"
                                wire:click="shiftChartMonth(-1)"
                                @disabled(!($chartMonthMeta['canGoPrev'] ?? false))
                                class="ep-btn ep-btn-ghost"
                                title="{{ __('Luna anterioară') }}">‹ {{ __('Luna anterioară') }}</button>
                        <span style="font-size: 14px; font-weight: 600; padding: 0 6px;">{{ ucfirst($chartMonthMeta['monthLabel']) }}</span>
                        @if($chartMonthMeta['mode'] === 'past')
                            <span class="ep-mbadge">{{ __('Trecut') }}</span>
                        @elseif($chartMonthMeta['mode'] === 'future')
                            <span class="ep-mbadge future">{{ __('Predicție') }}</span>
                        @else
                            <span class="ep-mbadge cur">{{ __('în curs, cu predicție') }}</span>
                        @endif
                        <button type="button"
                                wire:click="shiftChartMonth(1)"
                                @disabled(!($chartMonthMeta['canGoNext'] ?? false))
                                class="ep-btn ep-btn-ghost"
                                title="{{ __('Luna următoare') }}">{{ __('Luna următoare') }} ›</button>
                        @if($chartMonthMeta['mode'] !== 'current')
                            <button type="button"
                                    wire:click="$set('chartMonth', '{{ \Carbon\Carbon::now('Europe/Bucharest')->format('Y-m') }}')"
                                    class="ep-btn"
                                    style="margin-left: auto;"
                                    title="{{ __('Revino la luna în curs') }}">{{ __('Revino la luna în curs') }}</button>
                        @endif
                    </div>
                    @endif

                    <div class="ep-legend ep-hint">
                        @if($chartPeriod === 'month' && $chartMonthMeta && $chartMonthMeta['mode'] !== 'future')
                            <span><span class="sw" style="width: 14px; height: 2px; background: #6366f1;"></span>{{ __('Vânzări (RON)') }}</span>
                            <span><span class="sw" style="width: 10px; height: 10px; border-radius: 3px; background: rgba(147, 51, 234, .7);"></span>{{ __('Bilete') }}</span>
                        @elseif($chartPeriod !== 'month')
                            <span><span class="sw" style="width: 14px; height: 2px; background: #6366f1;"></span>{{ __('Vânzări (RON)') }}</span>
                            <span><span class="sw" style="width: 10px; height: 10px; border-radius: 3px; background: rgba(147, 51, 234, .7);"></span>{{ __('Bilete') }}</span>
                        @endif
                        <span><span class="sw" style="width: 10px; height: 10px; border-radius: 3px; background: #eab308;"></span>{{ __('weekend') }}</span>
                        <span><span class="sw" style="width: 14px; border-top: 1.5px dashed rgba(99, 102, 241, .7);"></span>{{ __('Medie') }}</span>
                        @if($chartPeriod === 'month')
                            <span><span class="sw" style="width: 14px; border-top: 2px dashed rgba(202, 138, 4, .8);"></span>{{ __('Anul trecut') }}</span>
                            <span><span class="sw" style="width: 14px; border-top: 1.5px dotted rgba(202, 138, 4, .8);"></span>{{ __('Medie an trecut') }}</span>
                            @if($chartMonthMeta && $chartMonthMeta['mode'] !== 'past')
                                <span><span class="sw" style="width: 14px; border-top: 2px dashed rgba(16, 185, 129, .85);"></span>{{ __('Predicție') }}</span>
                            @endif
                        @endif
                    </div>

                    <div class="ep-chart">
                        <div>
                            <canvas id="combinedChart"
                                    data-sales='@json($chartData)'
                                    data-tickets='@json($ticketChartData)'
                                    data-prev-sales='@json($prevYearChartData ?? null)'
                                    data-prev-tickets='@json($prevYearTicketChartData ?? null)'
                                    data-chart-meta='@json($chartMonthMeta ?? null)'
                                    data-currency="RON"></canvas>
                        </div>
                    </div>

                    @if($chartPeriod === 'month' && isset($chartSummary) && $chartMonthMeta)
                        @php
                            $monthLabel = ucfirst($chartMonthMeta['monthLabel']);
                            $prevLabel = ucfirst($chartMonthMeta['prevMonthLabel']);
                            $mode = $chartMonthMeta['mode'];
                            $isPast = $mode === 'past';
                            $isFuture = $mode === 'future';
                            // % change vs last year (null when no last-year baseline).
                            $pct = function ($cur, $prev) {
                                $prev = (float) $prev;
                                if ($prev <= 0) return null;
                                return round(((float) $cur - $prev) / $prev * 100, 1);
                            };
                            $pctText = function ($p) {
                                if ($p === null) return '';
                                $pos = $p >= 0;
                                $val = ($pos ? '+' : '') . number_format($p, 1, ',', '.');
                                return ' | <span class="ep-pct ' . ($pos ? 'pos' : 'neg') . '">' . ($pos ? '▲' : '▼') . ' ' . $val . '%</span> ' . e(__('față de anul trecut'));
                            };
                        @endphp
                        <div class="ep-sumwrap">
                            {{-- Până acum (luna în curs) / totaluri (luna trecută); ascuns pentru luni viitoare --}}
                            @if(!$isFuture)
                            <div>
                                <div class="ep-grp" style="color: var(--ep-g1);">{{ $isPast ? __('Total lună') : __('Până acum') }}</div>
                                <div class="ep-kpis ep-k3">
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Vânzări') }}</span>
                                        <span class="v">{{ number_format($chartSummary['sales_so_far'], 0, ',', '.') }} <small>{{ __('RON') }}</small></span>
                                        <span class="s">{{ $monthLabel }}{!! $pctText($pct($chartSummary['sales_so_far'], $chartSummary['prev_year_sales'] ?? 0)) !!}</span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Bilete') }}</span>
                                        <span class="v">{{ number_format($chartSummary['tickets_so_far'], 0, ',', '.') }}</span>
                                        <span class="s">{{ $monthLabel }}{!! $pctText($pct($chartSummary['tickets_so_far'], $chartSummary['prev_year_tickets'] ?? 0)) !!}</span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Comisioane') }}</span>
                                        <span class="v">{{ number_format($chartSummary['commission_so_far'] ?? 0, 0, ',', '.') }} <small>{{ __('RON') }}</small></span>
                                        <span class="s">{{ $monthLabel }}{!! $pctText($pct($chartSummary['commission_so_far'] ?? 0, $chartSummary['prev_year_commission'] ?? 0)) !!}</span>
                                    </div>
                                </div>
                            </div>
                            @endif

                            {{-- Anul trecut --}}
                            <div>
                                <div class="ep-grp" style="color: var(--ep-g2);">{{ __('Anul trecut, aceeași lună') }}</div>
                                <div class="ep-kpis ep-k3">
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Vânzări') }}</span>
                                        <span class="v">{{ number_format($chartSummary['prev_year_sales'], 0, ',', '.') }} <small>{{ __('RON') }}</small></span>
                                        <span class="s">{{ $prevLabel }}</span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Bilete') }}</span>
                                        <span class="v">{{ number_format($chartSummary['prev_year_tickets'], 0, ',', '.') }}</span>
                                        <span class="s">{{ $prevLabel }}</span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Comisioane') }}</span>
                                        <span class="v">{{ number_format($chartSummary['prev_year_commission'] ?? 0, 0, ',', '.') }} <small>{{ __('RON') }}</small></span>
                                        <span class="s">estimat | {{ $prevLabel }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Predicție (ascunsă pentru luni trecute — valorile reale sunt cunoscute) --}}
                            @if(!$isPast)
                            <div>
                                <div class="ep-grp" style="color: var(--ep-g3);">{{ __('Predicție pentru toată luna') }}</div>
                                <div class="ep-kpis ep-k3">
                                    <div class="ep-mini pred">
                                        <span class="l">{{ __('Vânzări') }}</span>
                                        <span class="v">{{ number_format($chartSummary['predicted_sales'], 0, ',', '.') }} <small>{{ __('RON') }}</small></span>
                                        <span class="s">total {{ $monthLabel }}{!! $pctText($pct($chartSummary['predicted_sales'] ?? 0, $chartSummary['prev_year_sales'] ?? 0)) !!}</span>
                                    </div>
                                    <div class="ep-mini pred">
                                        <span class="l">{{ __('Bilete') }}</span>
                                        <span class="v">{{ number_format($chartSummary['predicted_tickets'], 0, ',', '.') }}</span>
                                        <span class="s">total {{ $monthLabel }}{!! $pctText($pct($chartSummary['predicted_tickets'] ?? 0, $chartSummary['prev_year_tickets'] ?? 0)) !!}</span>
                                    </div>
                                    <div class="ep-mini pred">
                                        <span class="l">{{ __('Comisioane') }}</span>
                                        <span class="v">{{ number_format($chartSummary['predicted_commission'] ?? 0, 0, ',', '.') }} <small>{{ __('RON') }}</small></span>
                                        <span class="s">total {{ $monthLabel }}{!! $pctText($pct($chartSummary['predicted_commission'] ?? 0, $chartSummary['prev_year_commission'] ?? 0)) !!}</span>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    @endif
                </section>

                {{-- Raport vânzări pe zi, pe evenimente — componentă Livewire lazy proprie:
                     schimbarea zilei re-randează doar tabelul, nu tot dashboardul. --}}
                @livewire(\App\Livewire\Marketplace\DailySalesReport::class)

                {{-- Eveniment urmărit (deschis implicit) — doar admini --}}
                @if(!empty($featuredEventStats))
                @php
                    $fes = $featuredEventStats;
                    $cur = $fes['currency'];
                    $money = fn ($v) => number_format((float) $v, 2, ',', '.') . ' ' . $cur;
                    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
                    $avg = fn ($v) => number_format((float) $v, 1, ',', '.');
                    // Seria de 30 de zile: preferă vânzările; dacă e plată (evenimente
                    // cu POS), cade pe numărul de bilete ca graficul să rămână vizibil.
                    $dsSales   = $fes['daily_sales'] ?? [];
                    $dsTickets = $fes['daily_tickets'] ?? [];
                    $sum30     = array_sum($dsSales);
                    $barSeries = array_sum($dsSales) > 0 ? $dsSales : $dsTickets;
                    $maxD      = !empty($barSeries) ? max($barSeries) : 0;
                    $maxD      = $maxD > 0 ? $maxD : 1;
                    $hasTrend  = array_sum($dsTickets) > 0 || array_sum($dsSales) > 0;
                @endphp
                <section class="ep-card" aria-labelledby="ep-h-fe">
                    <div class="ep-card-h">
                        <h2 id="ep-h-fe">{{ $fes['event_name'] }}</h2>
                        <span class="ep-hint">{{ __('eveniment urmărit') }}</span>
                        <a class="ep-btn ep-btn-ghost" href="{{ route('filament.marketplace.resources.events.edit', ['record' => $fes['event_id']]) }}" style="margin-left: auto;">{{ __('Deschide evenimentul') }} ↗</a>
                    </div>
                    <div class="ep-fe-body">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
                                <span class="ep-hint" style="font-size: 13px;">{{ __('Vânzări · ultimele 30 zile') }}</span>
                                <span style="font-weight: 600;">{{ $money($sum30) }}</span>
                            </div>
                            @if($hasTrend)
                                {{-- Bare cu înălțime în pixeli și culoare inline: nu depind de clase Tailwind compilate. --}}
                                <div style="display: flex; align-items: flex-end; gap: 2px; height: 64px;">
                                    @foreach($barSeries as $i => $v)
                                        @php
                                            $barPx = $v > 0 ? max(4, (int) round($v / $maxD * 58)) : 2;
                                            $isWknd = $fes['daily_weekend'][$i] ?? false;
                                            $tip = ($fes['daily_labels'][$i] ?? '')
                                                . "\nVânzări: " . $money($fes['daily_sales'][$i] ?? 0)
                                                . "\n• Online: " . $money($fes['daily_sales_online'][$i] ?? 0)
                                                . "\n• POS: " . $money($fes['daily_sales_pos'][$i] ?? 0)
                                                . "\nBilete: " . ($fes['daily_tickets'][$i] ?? 0)
                                                . "\n• Online: " . ($fes['daily_tickets_online'][$i] ?? 0)
                                                . "\n• POS: " . ($fes['daily_tickets_pos'][$i] ?? 0);
                                        @endphp
                                        {{-- Weekendul: bară mai lată (flex 2) și galbenă. --}}
                                        <div style="flex: {{ $isWknd ? '2' : '1' }}; height: {{ $barPx }}px; border-radius: 2px 2px 0 0; background-color: {{ $isWknd ? '#eab308' : '#6366f1' }}; opacity: {{ $isWknd ? '1' : '.85' }};"
                                             title="{{ $tip }}"></div>
                                    @endforeach
                                </div>
                                <div style="display: flex; gap: 2px; margin-top: 4px;">
                                    @foreach($barSeries as $i => $v)
                                        @php $isWknd = $fes['daily_weekend'][$i] ?? false; @endphp
                                        <div style="flex: {{ $isWknd ? '2' : '1' }}; text-align: center; font-size: 8px; line-height: 1; color: {{ $isWknd ? '#ca8a04' : '#9ca3af' }}; font-weight: {{ $isWknd ? '700' : '400' }};">{{ $fes['daily_day_num'][$i] ?? '' }}</div>
                                    @endforeach
                                </div>
                            @else
                                <div class="ep-hint" style="display: flex; align-items: center; justify-content: center; height: 64px; border: 1px dashed var(--ep-bd); border-radius: 8px;">
                                    {{ __('Nicio vânzare în ultimele 30 zile') }}
                                </div>
                            @endif
                        </div>

                        {{-- Decontare (compensare) — perioada în derulare (jumătate de lună).
                             Oglinda secțiunii "Decontare (compensare)" din /organizator/deconturi,
                             ca adminul să vadă balanța zilnic fără să se logheze în cont. --}}
                        @php
                            $hasStl = !empty($fes['settlement']);
                            if ($hasStl) {
                                $stl = $fes['settlement'];
                                $bal = $stl['balance'];
                                $on = $stl['online'];
                                $po = $stl['pos'];
                                $pFrom = \Carbon\Carbon::parse($stl['period_from'])->locale('ro')->translatedFormat('j M');
                                $pTo = \Carbon\Carbon::parse($stl['period_to'])->locale('ro')->translatedFormat('j M Y');
                                $dirTone = ['ambilet_to_venue' => 'ok', 'venue_to_ambilet' => 'warn'][$bal['direction']] ?? '';
                                $dirLabel = $bal['direction'] === 'ambilet_to_venue' ? 'AmBilet → Locație'
                                    : ($bal['direction'] === 'venue_to_ambilet' ? 'Locație → AmBilet' : 'Sold zero');
                                $dirIcon = $bal['direction'] === 'ambilet_to_venue' ? '→'
                                    : ($bal['direction'] === 'venue_to_ambilet' ? '←' : '=');
                            }
                        @endphp
                        <div class="ep-kpis ep-k3 six">
                            <div class="ep-mini">
                                <span class="l">{{ __('Total bilete') }}</span>
                                <span class="v">{{ $num($fes['tickets_total']) }}</span>
                                <span class="s">{{ $num($fes['tickets_online']) }} online | {{ $num($fes['tickets_pos']) }} POS</span>
                            </div>
                            <div class="ep-mini">
                                <span class="l">{{ __('Total vânzări') }}</span>
                                <span class="v">{{ $money($fes['sales_total']) }}</span>
                                <span class="s">{{ $num($fes['sales_online']) }} online | {{ $num($fes['sales_pos']) }} POS</span>
                            </div>
                            <div class="ep-mini">
                                <span class="l">{{ __('Total comision') }}</span>
                                <span class="v">{{ $money($fes['comm_total']) }}</span>
                                <span class="s">{{ $num($fes['comm_online']) }} online | {{ $num($fes['comm_pos']) }} POS</span>
                            </div>
                            <div class="ep-mini">
                                <span class="l">{{ __('Pe zi, în medie') }}</span>
                                <span class="v">{{ $avg($fes['days'] > 0 ? $fes['tickets_total'] / $fes['days'] : 0) }} <small>{{ __('bilete') }}</small></span>
                                <span class="s">{{ $money($fes['days'] > 0 ? $fes['sales_total'] / $fes['days'] : 0) }} {{ __('încasări') }} | {{ $money($fes['days'] > 0 ? $fes['comm_total'] / $fes['days'] : 0) }} {{ __('comision') }}</span>
                            </div>
                            <div class="ep-mini">
                                <span class="l">{{ __('Azi') }}</span>
                                <span class="v">{{ $num($fes['tickets_today']) }} <small>{{ __('bilete') }}</small></span>
                                <span class="s">{{ $money($fes['sales_today']) }} {{ __('vânzări') }} | {{ $money($fes['comm_today']) }} {{ __('comision') }}</span>
                            </div>
                            @if($hasStl)
                            <div class="ep-mini {{ $dirTone }}">
                                <span class="l">{{ __('Decontare (compensare) · perioada în derulare') }}</span>
                                <span class="v">{{ $money($bal['amount']) }}</span>
                                <span class="s">{{ $dirIcon }} {{ $dirLabel }} | {{ __('de achitat, prin compensare') }}</span>
                                <span class="s">{{ __('AmBilet → Locație:') }} {{ $money($bal['ambilet_owes_venue']) }} | {{ __('Locație → AmBilet:') }} {{ $money($bal['venue_owes_ambilet']) }}</span>
                            </div>
                            @else
                            <div class="ep-mini"></div>
                            @endif
                        </div>

                        @if($hasStl)
                        <div class="ep-blocks">
                            {{-- Online (AmBilet încasează) --}}
                            <div class="ep-block">
                                <h3>{{ __('Online · încasat de AmBilet') }}</h3>
                                <div class="ep-row"><span>{{ __('Brut') }}</span><span class="num">{{ $money($on['gross']) }}</span></div>
                                <div class="ep-row"><span>{{ __('Comision AmBilet') }}</span><span class="num ep-amber">−{{ $money($on['commission']) }}</span></div>
                                <div class="ep-row"><span style="font-weight: 600;">{{ __('Net (către locație)') }}</span><span class="num" style="font-weight: 700;">{{ $money($on['net']) }}</span></div>
                            </div>
                            {{-- POS (locația încasează fizic) --}}
                            <div class="ep-block">
                                <h3>{{ __('POS · încasat în locație') }}</h3>
                                <div class="ep-row"><span>{{ __('Brut') }}</span><span class="num">{{ $money($po['gross']) }}</span></div>
                                <div class="ep-row"><span>{{ __('Cash') }}</span><span class="num">{{ $money($po['cash']) }}</span></div>
                                <div class="ep-row"><span>{{ __('Card') }}</span><span class="num">{{ $money($po['card']) }}</span></div>
                                <div class="ep-row"><span style="font-weight: 600;">{{ __('Comision datorat') }}</span><span class="num" style="font-weight: 700;">{{ $money($po['commission']) }}</span></div>
                            </div>
                        </div>
                        <div class="ep-hint">{{ $pFrom }} – {{ $pTo }} | {{ __('online încasat de AmBilet, POS încasat în locație') }} | {{ __('se compensează comisioanele') }}</div>
                        @endif
                    </div>
                </section>
                @endif

                {{-- Comisioane pierdute (invitații peste comisionul câștigat) — doar super-admin, închis implicit --}}
                @if($showLost)
                @php
                    $iaAllSum = $invitationAbuse['all_time']['summary'];
                    $iaMonthSum = $invitationAbuse['current_month']['summary'];
                    $iaMonthEvents = $invitationAbuse['current_month']['events'];
                    $iaMonthLabel = $invitationAbuse['current_month']['label'] ?? '';
                    $currency = $marketplace->currency ?? 'RON';
                @endphp
                <section class="ep-card" id="ep-lost" style="overflow: hidden;">
                    <div class="ep-card-h">
                        <button type="button" class="ep-btn ep-btn-ghost" style="padding: 0; height: auto;" @click="lostOpen = !lostOpen" :aria-expanded="lostOpen">
                            <svg style="width: 16px; height: 16px; transition: transform .15s;" :style="lostOpen ? 'transform: rotate(90deg)' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd"/></svg>
                            <span style="font-size: 15px; font-weight: 600; color: var(--ep-tx);">{{ __('Comisioane pierdute') }}</span>
                        </button>
                        <span class="ep-badge t-amber">{{ number_format($iaAllSum['events_count']) }} evenimente</span>
                        <div style="margin-left: auto; display: flex; align-items: center; gap: 14px; font-size: 12px;">
                            <a href="{{ route('filament.marketplace.pages.invitation-abuse-report') }}">{{ __('Vezi raport complet') }} ↗</a>
                            <a href="{{ request()->fullUrlWithQuery(['refresh_invite_abuse' => 1]) }}">Actualizează</a>
                        </div>
                    </div>

                    <div x-show="lostOpen" x-collapse x-cloak>
                        <div style="max-height: 32rem; overflow-y: auto;">
                            {{-- Totaluri istorice --}}
                            <div style="padding: 16px 20px; border-bottom: 1px solid var(--ep-bd);">
                                <div class="ep-grp" style="color: var(--ep-mu);">{{ __('Total istoric') }}</div>
                                <div class="ep-kpis ep-k4">
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Comision pierdut (estimat)') }}</span>
                                        <span class="v ep-rose">{{ number_format($iaAllSum['total_lost'], 2) }} <small>{{ $currency }}</small></span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Comision efectiv câștigat') }}</span>
                                        <span class="v ep-green">{{ number_format($iaAllSum['total_earned'], 2) }} <small>{{ $currency }}</small></span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Invitații / total bilete') }}</span>
                                        <span class="v">{{ number_format($iaAllSum['total_invitations']) }} <small>/ {{ number_format($iaAllSum['total_invitations'] + $iaAllSum['total_paid_tickets']) }}</small></span>
                                        <span class="s ep-rose">{{ number_format($iaAllSum['invitation_ratio_pct'], 1) }}%</span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Organizatori afectați') }}</span>
                                        <span class="v">{{ number_format($iaAllSum['unique_organizers']) }}</span>
                                    </div>
                                </div>
                            </div>

                            <p class="ep-hint" style="margin: 0; padding: 10px 20px; font-style: italic; border-bottom: 1px solid var(--ep-bd);">
                                {{ __('Pentru fiecare eveniment se calculează media comisionului pe bilet vândut, apoi se proiectează pe numărul de invitații emise. Sunt afișate DOAR evenimentele unde proiecția de comision pierdut depășește comisionul efectiv câștigat. Include: invitații standalone (fără comandă) + bilete cu valoare 0 emise prin comandă (promo 100%, bulk admin, etc).') }}
                            </p>

                            {{-- Luna curentă --}}
                            <div style="padding: 16px 20px; border-bottom: 1px solid var(--ep-bd);">
                                <div class="ep-grp" style="color: var(--ep-g1);">Luna curentă — {{ ucfirst($iaMonthLabel) }}</div>
                                @if($iaMonthSum['events_count'] > 0)
                                <div class="ep-kpis ep-k4">
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Comision pierdut (estimat)') }}</span>
                                        <span class="v ep-rose">{{ number_format($iaMonthSum['total_lost'], 2) }} <small>{{ $currency }}</small></span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Comision efectiv câștigat') }}</span>
                                        <span class="v ep-green">{{ number_format($iaMonthSum['total_earned'], 2) }} <small>{{ $currency }}</small></span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Invitații / total bilete') }}</span>
                                        <span class="v">{{ number_format($iaMonthSum['total_invitations']) }} <small>/ {{ number_format($iaMonthSum['total_invitations'] + $iaMonthSum['total_paid_tickets']) }}</small></span>
                                        <span class="s ep-rose">{{ number_format($iaMonthSum['invitation_ratio_pct'], 1) }}%</span>
                                    </div>
                                    <div class="ep-mini">
                                        <span class="l">{{ __('Evenimente / organizatori') }}</span>
                                        <span class="v">{{ number_format($iaMonthSum['events_count']) }} <small>/ {{ number_format($iaMonthSum['unique_organizers']) }}</small></span>
                                    </div>
                                </div>
                                @else
                                <p class="ep-hint" style="margin: 0;">{{ __('Nicio alertă pe luna în curs.') }}</p>
                                @endif
                            </div>

                            {{-- Evenimentele lunii curente --}}
                            @if(count($iaMonthEvents) > 0)
                            <div class="ep-tblwrap">
                                <table class="ep-tbl">
                                    <thead>
                                        <tr>
                                            <th class="l">{{ __('Eveniment') }}</th>
                                            <th class="l">{{ __('Organizator') }}</th>
                                            <th>{{ __('Vândute') }}</th>
                                            <th>{{ __('Invitații') }}</th>
                                            <th>{{ __('% inv') }}</th>
                                            <th>{{ __('Câștigat') }}</th>
                                            <th>{{ __('Pierdut (estimat)') }}</th>
                                            <th style="text-align: center;">{{ __('Impact') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($iaMonthEvents as $ev)
                                        @php
                                            $impactTones = ['high' => 't-red', 'medium' => 't-amber', 'low' => 't-gray'];
                                            $impactLabels = ['high' => 'HIGH', 'medium' => 'MED', 'low' => 'LOW'];
                                        @endphp
                                        <tr style="{{ ($ev['is_past'] ?? false) ? 'opacity: .7;' : '' }}">
                                            <td>
                                                <a href="{{ route('filament.marketplace.resources.events.edit', ['record' => $ev['event_id'], 'tab' => 'vanzari']) }}" class="evn" style="color: var(--ep-tx);">
                                                    {{ $ev['event_title'] ?? ('Event #' . $ev['event_id']) }}
                                                </a>
                                                <div class="ep-hint">
                                                    @if($ev['event_date'] ?? null){{ $ev['event_date'] }}@endif
                                                    @if($ev['venue_name'] ?? null) | {{ $ev['venue_name'] }}@if($ev['venue_city'] ?? null), {{ $ev['venue_city'] }}@endif @endif
                                                </div>
                                            </td>
                                            <td>
                                                @if($ev['organizer_id'] ?? null)
                                                    <a href="{{ route('filament.marketplace.resources.organizers.edit', ['record' => $ev['organizer_id']]) }}">{{ $ev['organizer_name'] ?? 'Organizer #' . $ev['organizer_id'] }}</a>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="num">{{ number_format($ev['paid_tickets']) }}</td>
                                            <td class="num ep-rose">{{ number_format($ev['invitations']) }}</td>
                                            <td class="num ep-rose strong">{{ number_format($ev['invitation_ratio_pct'], 1) }}%</td>
                                            <td class="num ep-green">{{ number_format($ev['commission_earned'], 2) }}</td>
                                            <td class="num ep-rose strong">{{ number_format($ev['lost_commission_estimate'], 2) }}</td>
                                            <td style="text-align: center;"><span class="ep-badge {{ $impactTones[$ev['impact']] ?? 't-gray' }}" style="margin: 0;">{{ $impactLabels[$ev['impact']] ?? '—' }}</span></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </div>
                    </div>
                </section>
                @endif
            </div>{{-- /ep-main --}}

            {{-- Coloana din dreapta --}}
            <div class="ep-rail">
                {{-- De plătit către Tixello --}}
                @if(isset($billing))
                <section class="ep-card" aria-labelledby="ep-h-tx">
                    <div class="ep-card-h">
                        <h2 id="ep-h-tx">{{ __('De plată către Tixello') }}</h2>
                        <span class="ep-hint">{{ $billing['month_label'] }}</span>
                        <a href="{{ route('filament.marketplace.pages.billing-breakdown') }}" style="margin-left: auto; font-size: 12px;">{{ __('Detalii') }} →</a>
                    </div>
                    <div style="padding: 6px 20px 14px;">
                        <div class="ep-row">
                            <span>Comision ticketing <span class="ep-hint">{{ $billing['commission_rate'] }}% din {{ number_format($billing['ticket_commission_base'] ?? $billing['order_revenue'], 2) }}</span></span>
                            <span class="num">{{ number_format($billing['ticketing_commission'], 2) }}</span>
                        </div>
                        @foreach($billing['services'] as $service)
                        <div class="ep-row">
                            <span>{{ $service['label'] }}</span>
                            <span class="num" @if(!($service['amount'] > 0)) style="color: var(--ep-mu);" @endif>{{ number_format($service['amount'], 2) }}</span>
                        </div>
                        @endforeach
                        <div class="ep-row" style="font-size: 12px;">
                            <span>{{ __('Subtotal servicii') }}</span>
                            <span class="num">{{ number_format($billing['services_total'], 2) }}</span>
                        </div>
                        <div class="ep-row" style="font-size: 15px;">
                            <span style="color: var(--ep-tx); font-weight: 600;">{{ __('Total') }}</span>
                            <span class="num ep-rose" style="font-weight: 700;">{{ number_format($billing['grand_total'], 2) }} {{ $billing['currency'] }}</span>
                        </div>
                    </div>
                </section>
                @endif

                {{-- Rapoarte --}}
                <section class="ep-card" aria-labelledby="ep-h-lk">
                    <div class="ep-card-h"><h2 id="ep-h-lk">{{ __('Rapoarte') }}</h2></div>
                    <div style="padding: 6px 20px 14px;">
                        <div class="ep-row">
                            <a href="{{ $salesBreakdownUrl }}" title="{{ __('Încasări online, refund-uri prin procesator, split POS cash / card, per eveniment') }}">{{ __('Breakdown vânzări & bilete') }}</a>
                            <span class="ep-hint">{{ __('online, retururi, POS') }}</span>
                        </div>
                        <div class="ep-row">
                            <a href="{{ route('filament.marketplace.pages.all-time-stats') }}" title="{{ __('Totaluri cumulate + breakdown importat (legacy) vs procesat prin Tixello') }}">{{ __('Statistici All Time') }}</a>
                            <span class="ep-hint">{{ __('totaluri cumulate') }}</span>
                        </div>
                        @if($showLost)
                        <div class="ep-row">
                            <a href="#ep-lost" @click.prevent="lostOpen = true; jump('ep-lost')">{{ __('Comisioane pierdute') }}</a>
                            <span class="ep-hint">{{ number_format($invitationAbuse['all_time']['summary']['events_count']) }} evenimente</span>
                        </div>
                        @endif
                        @if($hasRecent)
                        <div class="ep-row">
                            <a href="#ep-recent" @click.prevent="panel = 'recent'; jump('ep-attention')">{{ __('Evenimente publicate recent') }}</a>
                            <span class="ep-hint">{{ $recentlyPublishedEvents->count() }}</span>
                        </div>
                        @endif
                    </div>
                </section>
            </div>{{-- /ep-rail --}}
            </div>{{-- /ep-cols --}}
            @endif {{-- end isSA --}}
        </div>{{-- /ep-dash --}}
    @endif

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // jsdelivr fallback — observed transient ERR_SSL_PROTOCOL_ERROR on
        // some networks. cdnjs serves a pinned UMD build of the same major
        // version, so Chart.js APIs stay compatible.
        if (typeof Chart === 'undefined') {
            const fb = document.createElement('script');
            fb.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
            fb.onload = function() {
                if (typeof initCombinedChart === 'function') initCombinedChart();
            };
            document.head.appendChild(fb);
        }
        document.addEventListener('DOMContentLoaded', function() { initCombinedChart(); });
        document.addEventListener('livewire:navigated', function() { initCombinedChart(); });
        document.addEventListener('charts-updated', function() { setTimeout(() => initCombinedChart(), 100); });
        // Guard Livewire.hook — Filament occasionally surfaces a version
        // mismatch between published assets and the installed Livewire
        // package ("published Livewire assets are out of date" warning).
        // When that happens the Livewire global is missing at parse time
        // and an unguarded .hook() call throws ReferenceError, taking
        // down every other initializer in this block.
        if (typeof Livewire !== 'undefined' && Livewire?.hook) {
            Livewire.hook('morph.updated', ({ el }) => {
                if (el.querySelector && el.querySelector('#combinedChart')) {
                    setTimeout(() => initCombinedChart(), 100);
                }
            });
        }

        function initCombinedChart() {
            const ctx = document.getElementById('combinedChart');
            if (!ctx) return;
            // Chart.js is loaded via jsdelivr CDN — if the CDN is unreachable
            // (intermittent SSL_PROTOCOL_ERROR observed on some networks),
            // bail out quietly instead of throwing a cascade of errors.
            if (typeof Chart === 'undefined') {
                console.warn('Chart.js failed to load — skipping chart render.');
                return;
            }
            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();
            const isDark = document.documentElement.classList.contains('dark');

            const salesStr = ctx.getAttribute('data-sales');
            const ticketsStr = ctx.getAttribute('data-tickets');
            const prevSalesStr = ctx.getAttribute('data-prev-sales');
            const prevTicketsStr = ctx.getAttribute('data-prev-tickets');
            const chartMetaStr = ctx.getAttribute('data-chart-meta');
            if (!salesStr || !ticketsStr) return;

            const salesData = JSON.parse(salesStr);
            const ticketData = JSON.parse(ticketsStr);
            const prevSalesData = prevSalesStr && prevSalesStr !== 'null' ? JSON.parse(prevSalesStr) : null;
            const prevTicketsData = prevTicketsStr && prevTicketsStr !== 'null' ? JSON.parse(prevTicketsStr) : null;
            const chartMeta = chartMetaStr && chartMetaStr !== 'null' ? JSON.parse(chartMetaStr) : null;
            const currency = ctx.getAttribute('data-currency') || 'RON';

            // Weekend detection for the X-axis. Month view ("Avansat") carries
            // chartMeta.monthStart (index 0 = the 1st), so day = monthStart + i.
            // All other (N-day) views end "today" and run back N days, so the
            // last index is today. Used to paint weekend bars + labels yellow.
            const weekendFlags = (function () {
                const n = ((ticketData && ticketData.labels) || (salesData && salesData.labels) || []).length;
                const flags = [];
                if (chartMeta && chartMeta.monthStart) {
                    const base = new Date(chartMeta.monthStart + 'T00:00:00');
                    for (let i = 0; i < n; i++) {
                        const d = new Date(base.getFullYear(), base.getMonth(), base.getDate() + i);
                        const dow = d.getDay();
                        flags.push(dow === 0 || dow === 6);
                    }
                } else {
                    const t = new Date(); t.setHours(0, 0, 0, 0);
                    for (let i = 0; i < n; i++) {
                        const d = new Date(t.getFullYear(), t.getMonth(), t.getDate() - (n - 1 - i));
                        const dow = d.getDay();
                        flags.push(dow === 0 || dow === 6);
                    }
                }
                return flags;
            })();
            const WEEKEND_BAR = isDark ? 'rgba(234, 179, 8, 0.85)' : 'rgba(202, 138, 4, 0.85)';
            const WEEKEND_BORDER = '#ca8a04';
            const WEEKEND_TICK = isDark ? '#facc15' : '#ca8a04';

            // Chart-meta mode: null = non-month view; 'past'/'current'/'future'
            // = month view variants. For future months the actuals line is
            // all zeros — blank it out so the chart isn't dragged to a flat
            // bottom and only the prediction + prev-year lines are visible.
            const chartMode = chartMeta ? chartMeta.mode : null;
            if (chartMode === 'future') {
                salesData.data = salesData.labels.map(() => null);
                ticketData.data = ticketData.labels.map(() => null);
            }

            // Average of sales / tickets over the rendered period — drawn as a
            // flat dashed line so operators can spot above/below-average days
            // at a glance. Filters nulls (future-month case where actuals don't
            // exist yet) so the avg isn't dragged to 0.
            const avg = (arr) => {
                const valid = arr.filter(v => v !== null && v !== undefined);
                return valid.length > 0 ? valid.reduce((a, b) => a + (Number(b) || 0), 0) / valid.length : 0;
            };
            const salesAvg = avg(salesData.data);
            const ticketAvg = avg(ticketData.data);

            const datasets = [
                {
                    type: 'line',
                    label: 'Vânzări (RON)',
                    data: salesData.data,
                    borderColor: isDark ? '#818cf8' : '#6366f1',
                    backgroundColor: isDark ? 'rgba(129, 140, 248, 0.08)' : 'rgba(99, 102, 241, 0.08)',
                    borderWidth: 2, fill: true, tension: 0.3, pointRadius: 2, pointHoverRadius: 4,
                    yAxisID: 'y',
                    order: 1,
                },
                {
                    type: 'bar',
                    label: 'Bilete',
                    data: ticketData.data,
                    // Weekend bars are yellow with a thicker border so they stand out.
                    backgroundColor: ticketData.data.map((_, i) => weekendFlags[i] ? WEEKEND_BAR : (isDark ? 'rgba(168, 85, 247, 0.6)' : 'rgba(147, 51, 234, 0.6)')),
                    borderColor: ticketData.data.map((_, i) => weekendFlags[i] ? WEEKEND_BORDER : (isDark ? '#a855f7' : '#9333ea')),
                    borderWidth: ticketData.data.map((_, i) => weekendFlags[i] ? 2 : 1),
                    borderRadius: 3,
                    yAxisID: 'y1',
                    order: 2,
                },
            ];

            // Current-year avg lines — skip on future month (no observations,
            // would just draw a flat line at zero).
            if (chartMode !== 'future') {
                datasets.push({
                    type: 'line',
                    label: 'Medie vânzări (RON)',
                    data: salesData.labels.map(() => salesAvg),
                    borderColor: isDark ? 'rgba(129, 140, 248, 0.65)' : 'rgba(99, 102, 241, 0.6)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.25, borderDash: [4, 4], fill: false, pointRadius: 0, pointHoverRadius: 0,
                    yAxisID: 'y',
                    order: 4,
                });
                datasets.push({
                    type: 'line',
                    label: 'Medie bilete',
                    data: ticketData.labels.map(() => ticketAvg),
                    borderColor: isDark ? 'rgba(168, 85, 247, 0.55)' : 'rgba(147, 51, 234, 0.5)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.25, borderDash: [4, 4], fill: false, pointRadius: 0, pointHoverRadius: 0,
                    yAxisID: 'y1',
                    order: 4,
                });
            }

            // Previous year comparison (only for month view)
            if (prevSalesData) {
                datasets.push({
                    type: 'line',
                    label: 'Anul trecut (RON)',
                    data: prevSalesData.data,
                    borderColor: isDark ? 'rgba(234, 179, 8, 0.5)' : 'rgba(202, 138, 4, 0.5)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.5, borderDash: [4, 4], fill: false, tension: 0.3, pointRadius: 0, pointHoverRadius: 3,
                    yAxisID: 'y',
                    order: 0,
                });
                // Flat avg line for last year — comparable to the current
                // year's avg line so the operator sees year-over-year shift.
                const prevSalesAvg = avg(prevSalesData.data);
                datasets.push({
                    type: 'line',
                    label: 'Medie anul trecut (RON)',
                    data: prevSalesData.labels.map(() => prevSalesAvg),
                    borderColor: isDark ? 'rgba(234, 179, 8, 0.55)' : 'rgba(202, 138, 4, 0.5)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.25, borderDash: [2, 4], fill: false, pointRadius: 0, pointHoverRadius: 0,
                    yAxisID: 'y',
                    order: 0,
                });
            }
            if (prevTicketsData) {
                datasets.push({
                    type: 'bar',
                    label: 'Bilete anul trecut',
                    data: prevTicketsData.data,
                    backgroundColor: isDark ? 'rgba(234, 179, 8, 0.15)' : 'rgba(202, 138, 4, 0.15)',
                    borderColor: isDark ? 'rgba(234, 179, 8, 0.3)' : 'rgba(202, 138, 4, 0.3)',
                    borderWidth: 1, borderRadius: 3,
                    yAxisID: 'y1',
                    order: 3,
                });
            }

            // Prediction line (only for month view).
            // PAST month: skip entirely — actuals are known, nothing to predict.
            // CURRENT month: per-DOW capped/geomean with MTD pacing fallback
            //   for early-month (matches PHP predictMonthlySeries).
            // FUTURE month: prev[i + dowShift] × (capped overall growth from
            //   current month MTD, passed via chartMeta.futureGrowth).
            if (prevSalesData && chartMode !== 'past' && chartMeta) {
                const totalDays = salesData.labels.length;
                // monthStart driven by chartMeta — NOT new Date() — so that
                // navigating to a different month sees the right reference.
                const monthStart = new Date(chartMeta.monthStart + 'T00:00:00');
                const today = new Date();
                const currentDay = chartMeta.currentDay; // 1-based; meaningful only for 'current' mode

                const prevMonthStart = new Date(monthStart.getFullYear() - 1, monthStart.getMonth(), 1);
                const prevDaysInMonth = new Date(monthStart.getFullYear() - 1, monthStart.getMonth() + 1, 0).getDate();
                const dowShift = ((monthStart.getDay() - prevMonthStart.getDay()) + 7) % 7;
                const prevAt = (i) => {
                    const idx = i + dowShift;
                    if (idx < 0 || idx >= prevSalesData.data.length || idx >= prevDaysInMonth) return 0;
                    return prevSalesData.data[idx] || 0;
                };

                // FUTURE month — single uniform growth applied to prev year.
                if (chartMode === 'future') {
                    const clampFuture = (x) => Math.max(0.3, Math.min(3.5, x));
                    const growth = chartMeta.futureGrowth !== null && chartMeta.futureGrowth !== undefined
                        ? clampFuture(chartMeta.futureGrowth)
                        : 1;
                    const predictionData = [];
                    for (let i = 0; i < totalDays; i++) {
                        predictionData.push(Math.round(prevAt(i) * growth));
                    }
                    datasets.push({
                        type: 'line',
                        label: 'Predicție (RON)',
                        data: predictionData,
                        borderColor: isDark ? 'rgba(16, 185, 129, 0.7)' : 'rgba(5, 150, 105, 0.7)',
                        backgroundColor: isDark ? 'rgba(16, 185, 129, 0.05)' : 'rgba(5, 150, 105, 0.05)',
                        borderWidth: 2, borderDash: [6, 3], fill: true, tension: 0.3,
                        pointRadius: 0, pointHoverRadius: 4,
                        yAxisID: 'y',
                        order: 0,
                    });
                } else if (salesData.data.length > 0) {
                    // CURRENT month — per-DOW capped/geomean + MTD pacing fallback.

                // Smoothing helpers — see PHP predictMonthlySeries comments
                // for the backtest rationale. Caps prevent a single big
                // launch-day spike from sending growth ratios to 4× or
                // 0.2×; geometric mean dampens outliers far more than
                // arithmetic.
                const clamp = (x) => Math.max(0.3, Math.min(3.5, x));
                const geomean = (vals) => {
                    const filtered = vals.filter(v => v > 0);
                    if (filtered.length === 0) return null;
                    if (filtered.length === 1) return filtered[0];
                    let sumLog = 0;
                    for (const v of filtered) sumLog += Math.log(v);
                    return Math.exp(sumLog / filtered.length);
                };

                // Collect ratios from completed days
                const dowRatios = {};
                const allRatios = [];
                let completedTotal = 0, completedDays = 0;
                for (let i = 0; i < currentDay - 1 && i < salesData.data.length; i++) {
                    const d = new Date(monthStart);
                    d.setDate(i + 1);
                    const dow = d.getDay();
                    const curr = salesData.data[i];
                    const prev = prevAt(i);
                    if (!dowRatios[dow]) dowRatios[dow] = [];
                    if (prev > 0 && curr > 0) {
                        dowRatios[dow].push(curr / prev);
                        allRatios.push(curr / prev);
                    }
                    completedTotal += curr;
                    if (curr > 0) completedDays++;
                }

                const dowGrowth = {};
                for (let dow = 0; dow < 7; dow++) {
                    const g = geomean(dowRatios[dow] || []);
                    dowGrowth[dow] = g !== null ? clamp(g) : null;
                }
                const overallGeoGrowth = geomean(allRatios);
                const overallGrowth = overallGeoGrowth !== null ? clamp(overallGeoGrowth) : null;
                const overallAvg = completedDays > 0 ? completedTotal / completedDays : 0;

                // Estimate today's full-day total based on hours elapsed
                const hoursElapsed = today.getHours() + today.getMinutes() / 60;
                const todayActual = salesData.data[currentDay - 1] || 0;
                const todayEstimated = hoursElapsed > 1 ? Math.round(todayActual * (24 / hoursElapsed)) : todayActual;

                // MTD pacing fallback for early-month (< 7 completed days).
                // Per-DOW ratios with 1 sample/day are pure noise; project
                // month total from MTD vs same-pace point in prev year and
                // distribute remaining proportionally to prev days.
                const useMtdPacing = (currentDay - 1) < 7;
                let mtdRemaining = null;
                if (useMtdPacing) {
                    const currMtd = completedTotal + todayEstimated;
                    let prevMtdAligned = 0;
                    for (let j = 0; j < currentDay; j++) prevMtdAligned += prevAt(j);
                    let prevTotalAligned = 0;
                    for (let j = 0; j < totalDays; j++) prevTotalAligned += prevAt(j);
                    if (prevMtdAligned > 0 && prevTotalAligned > 0) {
                        const pacePct = prevMtdAligned / prevTotalAligned;
                        const projectedTotal = currMtd / pacePct;
                        mtdRemaining = Math.max(0, projectedTotal - currMtd);
                    }
                }

                // Pre-compute prev remaining sum for proportional distribution
                let prevRemainingSum = 0;
                for (let j = currentDay; j < totalDays; j++) prevRemainingSum += prevAt(j);

                // Build prediction for ALL days (past + today + future).
                // Past days use per-day capped/geomean ratios so the chart
                // shows a "model verification" line over actual past sales.
                // Future days use MTD pacing when in early-month mode.
                const predictionData = [];
                for (let i = 0; i < totalDays; i++) {
                    if (i === currentDay - 1) {
                        predictionData.push(todayEstimated);
                        continue;
                    }
                    const isFuture = i >= currentDay;
                    if (isFuture && useMtdPacing && mtdRemaining !== null && prevRemainingSum > 0) {
                        const weight = prevAt(i) / prevRemainingSum;
                        predictionData.push(Math.round(mtdRemaining * weight));
                        continue;
                    }
                    const dayDate = new Date(monthStart);
                    dayDate.setDate(i + 1);
                    const dow = dayDate.getDay();
                    const prevVal = prevAt(i);
                    const ratio = dowGrowth[dow] !== null ? dowGrowth[dow] : (overallGrowth !== null ? overallGrowth : 1);
                    let predicted;
                    if (prevVal > 0 && (dowGrowth[dow] !== null || overallGrowth !== null)) {
                        predicted = prevVal * ratio;
                    } else {
                        predicted = overallAvg;
                    }
                    predictionData.push(Math.round(predicted));
                }

                // Split prediction into past (solid) and future (dashed) segments
                const predPast = predictionData.map((v, i) => i < currentDay ? v : null);
                const predFuture = predictionData.map((v, i) => i >= currentDay - 1 ? v : null);

                datasets.push({
                    type: 'line',
                    label: 'Predicție verificare',
                    data: predPast,
                    borderColor: isDark ? 'rgba(16, 185, 129, 0.4)' : 'rgba(5, 150, 105, 0.4)',
                    backgroundColor: 'transparent',
                    borderWidth: 1.5, fill: false, tension: 0.3,
                    pointRadius: 0, pointHoverRadius: 3,
                    spanGaps: false,
                    yAxisID: 'y',
                    order: 0,
                });
                datasets.push({
                    type: 'line',
                    label: 'Predicție (RON)',
                    data: predFuture,
                    borderColor: isDark ? 'rgba(16, 185, 129, 0.7)' : 'rgba(5, 150, 105, 0.7)',
                    backgroundColor: isDark ? 'rgba(16, 185, 129, 0.05)' : 'rgba(5, 150, 105, 0.05)',
                    borderWidth: 2, borderDash: [6, 3], fill: true, tension: 0.3,
                    pointRadius: 0, pointHoverRadius: 4,
                    spanGaps: false,
                    yAxisID: 'y',
                    order: 0,
                });
                } // end CURRENT-month branch
            } // end prediction block (chartMode !== 'past')

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: salesData.labels,
                    datasets: datasets
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: isDark ? '#1f2937' : '#fff',
                            titleColor: isDark ? '#f3f4f6' : '#111827',
                            bodyColor: isDark ? '#d1d5db' : '#4b5563',
                            borderColor: isDark ? '#374151' : '#e5e7eb',
                            borderWidth: 1, padding: 12, displayColors: true,
                            usePointStyle: false,
                            filter: function(tooltipItem) {
                                // Hide prev year + prediction from main labels — shown in afterBody.
                                // Also hide "Medie *" flat-average lines; they're constant
                                // across the period and add no per-day signal.
                                const label = tooltipItem.dataset.label || '';
                                return !label.includes('trecut')
                                    && !label.includes('Predic')
                                    && !label.includes('verificare')
                                    && !label.startsWith('Medie');
                            },
                            callbacks: {
                                label: function(context) {
                                    const val = context.parsed.y;
                                    const fmt = (v) => new Intl.NumberFormat('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(v);
                                    if (context.dataset.yAxisID === 'y') {
                                        return 'Vânzări: ' + fmt(val) + ' ' + currency;
                                    }
                                    return 'Bilete: ' + val;
                                },
                                afterBody: function(tooltipItems) {
                                    if (!tooltipItems.length) return [];
                                    const idx = tooltipItems[0].dataIndex;
                                    const chart = tooltipItems[0].chart;
                                    const fmt = (v) => new Intl.NumberFormat('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(v);
                                    const lines = [];

                                    // Find prev year datasets
                                    const prevSalesDs = chart.data.datasets.find(d => (d.label || '').includes('Anul trecut'));
                                    const prevTicketsDs = chart.data.datasets.find(d => (d.label || '').includes('Bilete anul'));
                                    const predictionDs = chart.data.datasets.find(d => (d.label || '').includes('Predic'));

                                    if (prevSalesDs || prevTicketsDs) {
                                        lines.push('');
                                        lines.push('─── Anul trecut ───');
                                        if (prevSalesDs) {
                                            const v = prevSalesDs.data[idx] || 0;
                                            lines.push('🟡 Vânzări: ' + fmt(v) + ' ' + currency);
                                        }
                                        if (prevTicketsDs) {
                                            const v = prevTicketsDs.data[idx] || 0;
                                            lines.push('🟡 Bilete: ' + v);
                                        }
                                    }

                                    // Show prediction (future or verification)
                                    const predFutureDs = chart.data.datasets.find(d => d.label === 'Predicție (RON)');
                                    const predPastDs = chart.data.datasets.find(d => (d.label || '').includes('verificare'));
                                    const predVal = predFutureDs?.data[idx] ?? predPastDs?.data[idx] ?? null;
                                    if (predVal !== null && predVal !== undefined) {
                                        lines.push('');
                                        lines.push('🟢 Predicție: ' + fmt(predVal) + ' ' + currency);
                                    }

                                    return lines;
                                },
                                labelColor: function(context) {
                                    const label = context.dataset.label || '';
                                    if (label.includes('Predic')) {
                                        return { backgroundColor: 'rgba(5, 150, 105, 0.7)', borderColor: 'rgba(5, 150, 105, 0.7)', borderWidth: 1 };
                                    }
                                    if (label.includes('Anul trecut') || label.includes('anul trecut')) {
                                        return { backgroundColor: 'rgba(202, 138, 4, 0.7)', borderColor: 'rgba(202, 138, 4, 0.7)', borderWidth: 1 };
                                    }
                                    if (context.dataset.yAxisID === 'y') {
                                        return { backgroundColor: '#6366f1', borderColor: '#6366f1', borderWidth: 1 };
                                    }
                                    return { backgroundColor: 'rgba(147, 51, 234, 0.6)', borderColor: '#9333ea', borderWidth: 1 };
                                }
                            },
                            itemSort: function(a, b) {
                                // Current year first (order 1,2), then prev year (order 0,3)
                                const aIsPrev = (a.dataset.label || '').includes('trecut') ? 1 : 0;
                                const bIsPrev = (b.dataset.label || '').includes('trecut') ? 1 : 0;
                                if (aIsPrev !== bIsPrev) return aIsPrev - bIsPrev;
                                return a.datasetIndex - b.datasetIndex;
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: {
                            color: (c) => weekendFlags[c.index] ? WEEKEND_TICK : (isDark ? '#9ca3af' : '#6b7280'),
                            font: (c) => ({ size: 10, weight: weekendFlags[c.index] ? '700' : '400' }),
                            maxRotation: 45,
                        } },
                        y: {
                            type: 'linear', position: 'left', beginAtZero: true,
                            grid: { color: isDark ? '#374151' : '#f3f4f6' },
                            ticks: { color: isDark ? '#818cf8' : '#6366f1', font: { size: 10 }, callback: (v) => new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 }).format(v) }
                        },
                        y1: {
                            type: 'linear', position: 'right', beginAtZero: true,
                            grid: { drawOnChartArea: false },
                            ticks: { color: isDark ? '#a855f7' : '#9333ea', font: { size: 10 }, stepSize: 1, callback: (v) => Number.isInteger(v) ? v : '' }
                        }
                    }
                }
            });
        }
    </script>
    @endpush
</x-filament-panels::page>
