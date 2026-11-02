@php
    use App\Models\MarketplaceTaxRegistry;
    use App\Services\Marketplace\EventDocumentFilingService as Filing;

    // Manual filing buttons of one document, shared by the event's Documente
    // tab and /marketplace/organizer-documents/{id}. Expects $event, $type,
    // $row, $registry, $recipient. Native <dialog>: it opens above the panel's
    // layout and the Enter key is stopped so it can't submit the event form.
    $number = $row['filing']['registration_number'] ?? null;
    $canConfirm = $row['document'] && !$row['filed'];
    $canNumber = $row['filed'] && Filing::registrationAvailable();
    $fileUrl = !empty($row['document']['file_path'])
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($row['document']['file_path'])
        : null;
    $button = 'display:inline-flex;align-items:center;padding:5px 10px;border-radius:8px;font-size:12px;font-weight:600;border:0;cursor:pointer;';
    $dialog = 'width:min(480px,calc(100vw - 32px));margin:auto;padding:20px;border:0;border-radius:12px;font-size:13px;line-height:1.5;';
    $label = 'font-size:11px;opacity:.65;';
@endphp

@if($canConfirm || $canNumber)
    <style>
        dialog.ep-filing { background:#fff; color:#111827; }
        dialog.ep-filing::backdrop { background:rgba(0,0,0,.5); }
        .dark dialog.ep-filing { background:#18181b; color:#f4f4f5; }
    </style>

    <div
        style="margin-top:4px;"
        x-data="{
            busy: false,
            error: null,
            number: @js($number ?? ''),
            open(ref) { this.error = null; this.$refs[ref].showModal(); },
            send(payload) {
                this.busy = true;
                this.error = null;
                fetch('/marketplace/api/events/{{ $event->id }}/document-filing', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ ...payload, document_type: @js($type) })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) { window.location.reload(); return; }
                    this.busy = false;
                    this.error = data.message || 'Eroare';
                })
                .catch(e => { this.busy = false; this.error = 'Eroare: ' + e.message; });
            }
        }"
    >
        @if($canConfirm)
            <button type="button" style="{{ $button }}background:#2563eb;color:#fff;" x-on:click="open('confirmDialog')">
                Depune manual
            </button>

            <dialog x-ref="confirmDialog" class="ep-filing" style="{{ $dialog }}">
                <div style="font-size:15px;font-weight:700;margin-bottom:12px;">Depunere manuală: {{ $row['label'] }}</div>

                @if($registry)
                    <div style="margin-bottom:8px;">
                        <div style="{{ $label }}">Direcția</div>
                        <div style="font-weight:600;">{{ $registry->name }}@if($registry->subname) · {{ $registry->subname }}@endif</div>
                    </div>
                    @if($registry->address || $registry->city)
                        <div style="margin-bottom:8px;">
                            <div style="{{ $label }}">Adresă</div>
                            <div>{{ implode(', ', array_filter([$registry->address, $registry->city])) }}</div>
                        </div>
                    @endif
                    @if($recipient)
                        <div style="margin-bottom:8px;">
                            <div style="{{ $label }}">Email</div>
                            <div><a href="mailto:{{ $recipient }}" style="text-decoration:underline;">{{ $recipient }}</a></div>
                        </div>
                    @endif
                    @if($registry->phone)
                        <div style="margin-bottom:8px;">
                            <div style="{{ $label }}">Telefon</div>
                            <div>{{ $registry->phone }}</div>
                        </div>
                    @endif
                    @if($registry->submission_method === MarketplaceTaxRegistry::SUBMISSION_THIRD_PARTY)
                        <div style="margin-bottom:8px;">
                            <div style="{{ $label }}">Se depune prin</div>
                            <div>
                                {{ $registry->third_party_name ?: 'soluția terță' }}
                                @if($registry->third_party_url)
                                    · <a href="{{ $registry->third_party_url }}" target="_blank" rel="noopener" style="text-decoration:underline;">deschide</a>
                                @endif
                            </div>
                            @if($registry->third_party_procedure)
                                <div style="margin-top:4px;">{!! nl2br(e($registry->third_party_procedure)) !!}</div>
                            @endif
                        </div>
                    @endif
                    @if(!$recipient && !$registry->address && !$registry->city && !$registry->third_party_name)
                        <div style="margin-bottom:8px;color:#b45309;">Registrul fiscal nu are completate datele de depunere.</div>
                    @endif
                @else
                    <div style="margin-bottom:8px;color:#b45309;">Evenimentul nu are un registru fiscal asociat, deci nu știm unde se depune.</div>
                @endif

                @if($fileUrl)
                    <div style="margin-bottom:8px;">
                        <a href="{{ $fileUrl }}" target="_blank" rel="noopener" style="text-decoration:underline;">Deschide documentul</a>
                    </div>
                @endif

                <div x-show="error" x-text="error" style="margin-top:8px;color:#dc2626;"></div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" style="{{ $button }}background:transparent;color:inherit;" x-on:click="$refs.confirmDialog.close()">Renunță</button>
                    <button type="button" style="{{ $button }}background:#2563eb;color:#fff;" x-bind:disabled="busy"
                        x-on:click="send({ action: 'confirm_manual' })">
                        <span x-text="busy ? 'Se salvează...' : 'Confirmă depunerea'"></span>
                    </button>
                </div>
            </dialog>
        @endif

        @if($canNumber)
            <button type="button" style="{{ $button }}background:rgba(128,128,128,.15);color:inherit;" x-on:click="open('numberDialog')">
                {{ $number ? 'Modifică nr. înregistrare' : 'Adaugă nr. înregistrare' }}
            </button>

            <dialog x-ref="numberDialog" class="ep-filing" style="{{ $dialog }}">
                <div style="font-size:15px;font-weight:700;margin-bottom:4px;">Număr de înregistrare</div>
                <div style="margin-bottom:12px;opacity:.75;">
                    {{ $row['label'] }}@if($registry) · {{ $registry->name }}@endif
                </div>

                <input type="text" maxlength="100" x-model="number" autofocus
                    aria-label="Număr de înregistrare"
                    x-on:keydown.enter.prevent="send({ action: 'registration_number', registration_number: number })"
                    style="width:100%;padding:8px 10px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;color:inherit;font-size:14px;">

                <div x-show="error" x-text="error" style="margin-top:8px;color:#dc2626;"></div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" style="{{ $button }}background:transparent;color:inherit;" x-on:click="$refs.numberDialog.close()">Renunță</button>
                    <button type="button" style="{{ $button }}background:#2563eb;color:#fff;" x-bind:disabled="busy || !number.trim()"
                        x-on:click="send({ action: 'registration_number', registration_number: number })">
                        <span x-text="busy ? 'Se salvează...' : 'Salvează'"></span>
                    </button>
                </div>
            </dialog>
        @endif
    </div>
@endif
