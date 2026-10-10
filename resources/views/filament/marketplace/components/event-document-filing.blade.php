@php
    use App\Models\MarketplaceTaxRegistry;
    use App\Services\Marketplace\EventDocumentFilingService as Filing;

    // "Depunere la primărie" — one card per group of documents filed together
    // (cerere vizare; impozit + PV distrugere). Inline styles on purpose: the
    // panel theme is prebuilt and would not pick up new utility classes.
    $badge = fn (string $bg, string $fg) => "display:inline-block;padding:1px 8px;border-radius:999px;font-size:11px;font-weight:600;background:{$bg};color:{$fg};";
    $button = 'display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;color:#fff;border:0;cursor:pointer;';
@endphp

<div
    x-data="{
        busy: null,
        run(group, action, question) {
            if (!confirm(question)) return;
            this.busy = group + action;
            fetch('/marketplace/api/events/{{ $event->id }}/document-filing', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ group: group, action: action })
            })
            .then(r => r.json())
            .then(data => {
                this.busy = null;
                if (data.success) { window.location.reload(); }
                else { alert(data.message || 'Eroare'); }
            })
            .catch(e => { this.busy = null; alert('Eroare: ' + e.message); });
        }
    }"
    style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:12px;"
>
    @foreach($groups as $g)
        @php
            $registry = $g['registry'];
            $isThirdParty = $g['method'] === MarketplaceTaxRegistry::SUBMISSION_THIRD_PARTY;
            $hasFiling = collect($g['rows'])->contains(fn ($r) => $r['filing'] !== null);
            $thirdPartyName = $registry?->third_party_name ?: 'soluția terță';
        @endphp
        <div style="border:1px solid rgba(128,128,128,.3);border-radius:10px;padding:12px 14px;font-size:13px;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:8px;">
                <strong style="font-size:14px;">{{ $g['label'] }}</strong>
                @if($g['filed'])
                    <span style="{{ $badge('#dcfce7', '#14532d') }}">Depus</span>
                @elseif($g['refile'])
                    <span style="{{ $badge('#fef3c7', '#92400e') }}">De redepus</span>
                @elseif(!$g['blocked'])
                    <span style="{{ $badge('#fef3c7', '#92400e') }}">De depus</span>
                @endif
            </div>

            @foreach($g['rows'] as $row)
                <div style="margin-bottom:6px;">
                    <div style="font-weight:600;">{{ $row['label'] }}</div>
                    <div style="opacity:.75;font-size:12px;">
                        @if($row['document'])
                            generat {{ Filing::stamp($row['document']['generated_at'], $event) }}
                        @else
                            negenerat
                        @endif
                        @if($row['filing'])
                            · {{ $row['filed'] ? 'depus' : 'versiunea anterioară depusă' }}
                            {{ Filing::stamp($row['filing']['filed_at'], $event) }}
                            prin {{ $row['filing']['method_label'] }}@if($row['filing']['by']), de {{ $row['filing']['by'] }}@endif
                        @endif
                    </div>
                </div>
            @endforeach

            <div style="margin:10px 0 8px;padding-top:8px;border-top:1px dashed rgba(128,128,128,.3);font-size:12px;">
                @if($registry)
                    <a href="/marketplace/tax-registry/{{ $registry->id }}/edit" target="_blank" style="text-decoration:underline;">{{ $registry->name }}</a>
                    · {{ $registry->submissionLabel() ?? 'metodă de depunere nesetată' }}
                    @if($g['method'] === MarketplaceTaxRegistry::SUBMISSION_EMAIL && $g['recipient'])
                        · {{ $g['recipient'] }}
                    @endif
                @else
                    Fără registru fiscal asociat evenimentului
                @endif
            </div>

            @if($isThirdParty)
                <div style="margin-bottom:8px;padding:8px 10px;border-radius:8px;background:rgba(59,130,246,.08);font-size:12px;">
                    <div style="font-weight:600;margin-bottom:2px;">
                        Se depune prin {{ $thirdPartyName }}
                        @if($registry->third_party_url)
                            · <a href="{{ $registry->third_party_url }}" target="_blank" rel="noopener" style="text-decoration:underline;">deschide</a>
                        @endif
                    </div>
                    @if($registry->third_party_procedure)
                        <div>{!! nl2br(e($registry->third_party_procedure)) !!}</div>
                    @else
                        <div style="opacity:.75;">Procedura nu e completată pe registru.</div>
                    @endif
                </div>
            @endif

            @if($g['blocked'])
                <div style="font-size:12px;color:#b45309;">{{ $g['blocked'] }}</div>
            @elseif(!$g['filed'])
                @if($isThirdParty)
                    <button type="button" style="{{ $button }}background:#2563eb;"
                        x-bind:disabled="busy !== null"
                        x-on:click="run('{{ $g['group'] }}', 'confirm_third_party', 'Confirmi că ai depus documentele prin {{ addslashes($thirdPartyName) }}?')">
                        <span x-text="busy === '{{ $g['group'] }}confirm_third_party' ? 'Se salvează...' : 'Confirmă depunerea prin {{ addslashes($thirdPartyName) }}'"></span>
                    </button>
                @else
                    <button type="button" style="{{ $button }}background:#2563eb;"
                        x-bind:disabled="busy !== null"
                        x-on:click="run('{{ $g['group'] }}', 'send_email', 'Trimiți documentele pe email la {{ addslashes($g['recipient']) }}?')">
                        <span x-text="busy === '{{ $g['group'] }}send_email' ? 'Se trimite...' : 'Trimite email'"></span>
                    </button>
                @endif
            @endif

            @if($isSuperAdmin && $hasFiling)
                <button type="button" style="margin-left:6px;font-size:12px;text-decoration:underline;background:none;border:0;cursor:pointer;opacity:.75;"
                    x-bind:disabled="busy !== null"
                    x-on:click="run('{{ $g['group'] }}', 'undo', 'Anulezi depunerea înregistrată? Documentele vor apărea din nou ca nedepuse.')">
                    Anulează depunerea
                </button>
            @endif
        </div>
    @endforeach
</div>
