<x-filament-panels::page>
    {{-- Stiluri proprii, ca pagina să nu depindă de clasele incluse în tema compilată a panoului --}}
    <style>
        .ts { --ts-card: #ffffff; --ts-line: #e5e7eb; --ts-text: #111827; --ts-muted: #4b5563; --ts-soft: #f3f4f6; --ts-accent: #4f46e5; --ts-ok: #15803d; --ts-ok-bg: #dcfce7; }
        .dark .ts { --ts-card: #1f2937; --ts-line: #374151; --ts-text: #f9fafb; --ts-muted: #d1d5db; --ts-soft: #374151; --ts-accent: #6366f1; --ts-ok: #86efac; --ts-ok-bg: rgba(22, 101, 52, .35); }
        .ts { color: var(--ts-text); display: grid; gap: 24px; }
        .ts-card { background: var(--ts-card); border: 1px solid var(--ts-line); border-radius: 14px; padding: 22px; }
        .ts-head { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .ts-head h2 { font-size: 24px; font-weight: 700; line-height: 1.2; }
        .ts-head p, .ts-step p, .ts-short span, .ts-mode p { margin-top: 6px; font-size: 14px; line-height: 1.5; color: var(--ts-muted); max-width: 62ch; }
        .ts-btn { display: inline-flex; align-items: center; min-height: 38px; padding: 0 16px; border-radius: 9px; background: var(--ts-accent); color: #ffffff; font-size: 14px; font-weight: 600; text-decoration: none; border: 1px solid transparent; cursor: pointer; }
        .ts-btn:hover { filter: brightness(1.1); }
        .ts-btn--quiet { background: var(--ts-soft); color: var(--ts-text); }
        .ts-btn--line { background: transparent; color: var(--ts-text); border-color: var(--ts-line); }
        .ts-bar { margin-top: 18px; }
        .ts-bar__row { display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; }
        .ts-bar__track { margin-top: 8px; height: 8px; border-radius: 4px; background: var(--ts-soft); overflow: hidden; }
        .ts-bar__fill { height: 8px; border-radius: 4px; background: var(--ts-accent); }
        .ts-steps { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .ts-step { display: flex; gap: 14px; }
        .ts-step.is-done { border-color: var(--ts-ok); }
        .ts-num { flex: none; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; background: var(--ts-soft); font-size: 14px; font-weight: 700; }
        .ts-step.is-done .ts-num { background: var(--ts-ok-bg); color: var(--ts-ok); }
        .ts-num svg { width: 20px; height: 20px; }
        .ts-step h3, .ts-mode h3 { font-size: 16px; font-weight: 600; line-height: 1.3; }
        .ts-step__foot { margin-top: 12px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .ts-state { font-size: 14px; font-weight: 600; color: var(--ts-ok); }
        .ts-label { font-size: 12px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; color: var(--ts-muted); margin-bottom: -10px; }
        .ts-shorts { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
        .ts-short { display: block; text-decoration: none; color: inherit; padding: 16px 18px; }
        .ts-short:hover { border-color: var(--ts-accent); }
        .ts-short b { display: block; font-size: 16px; font-weight: 600; }
        .ts-mode { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px; background: var(--ts-soft); }
    </style>

    @if(!$tenant)
        <div class="ts"><div class="ts-card">Nu am găsit organizația asociată acestui cont. Scrie-ne la suport.</div></div>
    @else
        @php $percent = count($steps) ? (int) round($done / count($steps) * 100) : 0; @endphp
        <div class="ts">
            {{-- Salut + progres --}}
            <div class="ts-card">
                <div class="ts-head">
                    <div>
                        <h2>Bine ai venit, {{ $tenant->public_name ?? $tenant->name }}</h2>
                        <p>Mai jos sunt pașii până la prima vânzare. Îi poți face în orice ordine; cei terminați se bifează singuri.</p>
                    </div>
                    @if($siteUrl)
                        <a class="ts-btn" href="{{ $siteUrl }}" target="_blank" rel="noopener">Deschide site-ul public</a>
                    @endif
                </div>
                <div class="ts-bar">
                    <div class="ts-bar__row"><span>{{ $done }} din {{ count($steps) }} pași făcuți</span><span>{{ $percent }}%</span></div>
                    <div class="ts-bar__track"><div class="ts-bar__fill" style="width: {{ $percent }}%"></div></div>
                </div>
            </div>

            {{-- Pașii --}}
            <div class="ts-steps">
                @foreach($steps as $i => $step)
                    <div class="ts-card ts-step {{ $step['done'] ? 'is-done' : '' }}">
                        <div class="ts-num">
                            @if($step['done'])
                                <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else
                                {{ $i + 1 }}
                            @endif
                        </div>
                        <div style="min-width:0;flex:1">
                            <h3>{{ $step['title'] }}</h3>
                            <p>{{ $step['text'] }}</p>
                            <div class="ts-step__foot">
                                <a class="ts-btn {{ $step['done'] ? 'ts-btn--quiet' : '' }}" href="{{ $step['url'] }}" @if(!empty($step['external'])) target="_blank" rel="noopener" @endif>{{ $step['cta'] }}</a>
                                @if($step['state'])
                                    <span class="ts-state">{{ $step['state'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Unde găsesc… --}}
            <div class="ts-label">Unde găsesc</div>
            <div class="ts-shorts">
                @foreach($shortcuts as [$label, $text, $url])
                    <a class="ts-card ts-short" href="{{ $url }}"><b>{{ $label }}</b><span>{{ $text }}</span></a>
                @endforeach
            </div>

            {{-- Meniu simplu / complet --}}
            <div class="ts-card ts-mode">
                <div>
                    <h3>{{ $simple ? 'Folosești meniul simplu' : 'Folosești meniul complet' }}</h3>
                    <p>
                        @if($simple)
                            Meniul din stânga arată doar ce folosești pentru competiții și bilete. Platforma are și alte funcții (afiliați, abonamente, pagini, rapoarte de sală); le poți afișa oricând.
                        @else
                            Sunt afișate toate funcțiile platformei. Poți reveni la meniul simplu, cu doar ce folosești pentru competiții și bilete.
                        @endif
                    </p>
                </div>
                <button type="button" class="ts-btn ts-btn--line" wire:click="toggleMenu">{{ $simple ? 'Arată toate funcțiile' : 'Revino la meniul simplu' }}</button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
