{{-- Tabul „Documente” al unui eveniment de tenant. Generează prin /tenant/api/events/{id}/fiscal-documents. --}}
@php
    $when = ['published' => 'după publicarea evenimentului', 'finished' => 'după încheierea evenimentului'];
@endphp
<style>
    .tsd { --c-card: #ffffff; --c-line: #e5e7eb; --c-text: #111827; --c-muted: #4b5563; --c-soft: #f3f4f6; --c-accent: #4f46e5; display: grid; gap: 18px; color: var(--c-text); }
    .dark .tsd { --c-card: #1f2937; --c-line: #374151; --c-text: #f9fafb; --c-muted: #d1d5db; --c-soft: #374151; --c-accent: #818cf8; }
    .tsd-card { background: var(--c-card); border: 1px solid var(--c-line); border-radius: 12px; padding: 18px 20px; }
    .tsd-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 20px; padding: 14px 0; border-bottom: 1px solid var(--c-line); }
    .tsd-row:last-child { border-bottom: 0; padding-bottom: 0; }
    .tsd-row:first-child { padding-top: 0; }
    .tsd h3 { font-size: 15px; font-weight: 600; margin-bottom: 12px; }
    .tsd b { font-weight: 600; }
    .tsd small { display: block; margin-top: 3px; font-size: 13px; color: var(--c-muted); }
    .tsd-btn { display: inline-flex; align-items: center; min-height: 36px; padding: 0 14px; border-radius: 8px; border: 1px solid transparent; background: var(--c-accent); color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; }
    .tsd-btn[disabled] { opacity: .45; cursor: not-allowed; }
    .tsd-btn--line { background: transparent; color: var(--c-text); border-color: var(--c-line); }
    .tsd-btn--danger { background: transparent; color: #dc2626; border-color: transparent; padding: 0 8px; }
    .tsd-note { font-size: 13px; color: var(--c-muted); }
    .tsd-alert { padding: 12px 14px; border-radius: 8px; background: #fef3c7; color: #92400e; font-size: 14px; }
    .tsd-msg { padding: 10px 14px; border-radius: 8px; font-size: 14px; }
</style>

<div class="tsd" x-data="{
        busy: null, msg: '', ok: true,
        token: document.querySelector('meta[name=csrf-token]')?.content,
        async call(url, method, body, key) {
            this.busy = key; this.msg = '';
            try {
                const r = await fetch(url, { method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.token }, body: body ? JSON.stringify(body) : null });
                const d = await r.json().catch(() => ({}));
                this.ok = r.ok && d.success !== false;
                this.msg = d.message || (this.ok ? 'Gata.' : 'Operațiunea nu a reușit.');
                if (this.ok) { setTimeout(() => window.location.reload(), 900); }
            } catch (e) { this.ok = false; this.msg = 'Conexiunea a eșuat. Încearcă din nou.'; }
            this.busy = null;
        }
    }">
    @if(!$ready)
        <div class="tsd-alert">Documentele fiscale nu sunt încă activate pentru acest cont. Scrie echipei Tixello ca să-ți configureze șabloanele.</div>
    @else
        <div class="tsd-msg" x-show="msg" x-cloak x-text="msg" :style="ok ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;'"></div>

        @if(!$registry)
            <div class="tsd-alert">Evenimentul nu are o direcție fiscală asociată, deci documentele vor ieși fără datele primăriei și fără cota de impozit. Alege-o în tabul „Detalii”, la „Direcție fiscală”.</div>
        @endif

        <div class="tsd-card">
            <h3>Documente de generat</h3>
            @foreach($types as $type => $label)
                @php
                    $tpl = $templates[$type] ?? null;
                    $can = $tpl && ($canGenerate[$type] ?? false);
                    $last = $documents->firstWhere('type', $type);
                @endphp
                <div class="tsd-row">
                    <div>
                        <b>{{ $label }}</b>
                        <small>
                            @if(!$tpl) Nu există șablon pentru acest document.
                            @elseif($last) Ultima generare: {{ $last->created_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') }}@if($last->generated_by_name), de {{ $last->generated_by_name }}@endif.
                            @elseif($can) Încă negenerat.
                            @else Se poate genera {{ $when[$whenRules[$type]] ?? '' }}.
                            @endif
                        </small>
                    </div>
                    <button type="button" class="tsd-btn {{ $last ? 'tsd-btn--line' : '' }}" @disabled(!$can) :disabled="{{ $can ? 'busy !== null' : 'true' }}"
                            @click="call('{{ url('/tenant/api/events/' . $event->id . '/fiscal-documents') }}', 'POST', { type: '{{ $type }}' }, '{{ $type }}')">
                        <span x-text="busy === '{{ $type }}' ? 'Se generează…' : '{{ $last ? 'Generează din nou' : 'Generează' }}'">{{ $last ? 'Generează din nou' : 'Generează' }}</span>
                    </button>
                </div>
            @endforeach
        </div>

        <div class="tsd-card">
            <h3>Documente generate</h3>
            @forelse($documents as $doc)
                <div class="tsd-row">
                    <div>
                        <b>{{ $doc->typeLabel() }}</b>
                        <small>{{ $doc->created_at?->timezone('Europe/Bucharest')->format('d.m.Y H:i') }}@if($doc->generated_by_name) · {{ $doc->generated_by_name }}@endif @if($doc->file_size) · {{ number_format($doc->file_size / 1024, 0, ',', '.') }} KB @endif</small>
                    </div>
                    <div style="display:flex;align-items:center;gap:6px">
                        <a class="tsd-btn tsd-btn--line" href="{{ $doc->url() }}" target="_blank" rel="noopener">Descarcă PDF</a>
                        <button type="button" class="tsd-btn tsd-btn--danger" :disabled="busy !== null"
                                @click="if (confirm('Ștergi acest document?')) call('{{ url('/tenant/api/events/' . $event->id . '/fiscal-documents/' . $doc->id) }}', 'DELETE', null, 'del{{ $doc->id }}')">Șterge</button>
                    </div>
                </div>
            @empty
                <p class="tsd-note">Niciun document generat încă.</p>
            @endforelse
        </div>

        <p class="tsd-note">
            Documentele folosesc datele firmei din Setări și șabloanele tale. <a href="{{ url('/tenant/fiscal-templates') }}" style="color:var(--c-accent);font-weight:600">Vezi șabloanele</a>
            @if($registry) · Direcție fiscală: {{ $registry->name }}@if($registry->tax_rate !== null && $registry->tax_rate !== ''), cotă {{ rtrim(rtrim(number_format((float) $registry->tax_rate, 2, ',', '.'), '0'), ',') }}%@endif @endif
        </p>
    @endif
</div>
