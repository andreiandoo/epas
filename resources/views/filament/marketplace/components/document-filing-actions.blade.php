@php
    use App\Models\MarketplaceTaxRegistry;
    use App\Services\Marketplace\EventDocumentFilingService as Filing;
    use App\Services\Marketplace\EventDocumentRegistration as Registration;

    // Manual filing buttons of one document, shared by the event's Documente
    // tab and /marketplace/organizer-documents/{id}. Expects $event, $type,
    // $row, $registry, $recipient. Native <dialog>: it opens above the panel's
    // layout and the Enter key is stopped so it can't submit the event form.
    $filing = $row['filing'];
    $number = $filing['registration_number'] ?? null;
    $tz = \App\Support\MarketplaceTz::tz($event->marketplaceClient);
    $today = \Carbon\Carbon::now($tz)->toDateString();
    // The registration day defaults to the one saved, else the filing day.
    $registrationDay = !empty($filing['registration_date'])
        ? \Carbon\Carbon::createFromFormat('d.m.Y', $filing['registration_date'])->toDateString()
        : (!empty($filing['filed_at']) ? \Carbon\Carbon::parse($filing['filed_at'], 'UTC')->setTimezone($tz)->toDateString() : $today);
    // Also without a generated document: it may have been filed before the
    // platform tracked it.
    $canConfirm = !$row['filed'];
    $canNumber = $row['filed'] && Filing::registrationAvailable();
    $canUpload = $row['filed'] && Registration::available();
    $fileUrl = !empty($row['document']['file_path'])
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($row['document']['file_path'])
        : null;
    $button = 'display:inline-flex;align-items:center;padding:5px 10px;border-radius:8px;font-size:12px;font-weight:600;border:0;cursor:pointer;';
    $dialog = 'width:min(480px,calc(100vw - 32px));margin:auto;padding:20px;border:0;border-radius:12px;font-size:13px;line-height:1.5;';
    $label = 'font-size:11px;opacity:.65;';
    $input = 'width:100%;padding:7px 9px;border:1px solid rgba(128,128,128,.4);border-radius:8px;background:transparent;color:inherit;font-size:14px;';
@endphp

@if($canConfirm || $canNumber || $canUpload)
    <style>
        dialog.ep-filing { background:#fff; color:#111827; }
        dialog.ep-filing::backdrop { background:rgba(0,0,0,.5); }
        .dark dialog.ep-filing { background:#18181b; color:#f4f4f5; }
    </style>

    <div
        style="margin-top:4px;display:flex;flex-wrap:wrap;gap:6px;"
        x-data="{
            busy: false,
            error: null,
            number: @js($number ?? ''),
            registrationDate: @js($registrationDay),
            filedOn: @js($today),
            open(ref) { this.error = null; this.$refs[ref].showModal(); },
            done(request) {
                request
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) { window.location.reload(); return; }
                        this.busy = false;
                        this.error = data.message || 'Eroare';
                    })
                    .catch(e => { this.busy = false; this.error = 'Eroare: ' + e.message; });
            },
            send(payload) {
                this.busy = true;
                this.error = null;
                this.done(fetch('/marketplace/api/events/{{ $event->id }}/document-filing', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ ...payload, document_type: @js($type) })
                }));
            },
            upload() {
                const file = this.$refs.registeredFile.files[0];
                if (!file) { this.error = 'Alege fișierul PDF primit de la primărie.'; return; }
                const body = new FormData();
                body.append('action', 'upload_registered');
                body.append('document_type', @js($type));
                body.append('registration_number', this.number);
                body.append('registration_date', this.registrationDate);
                body.append('file', file);
                this.busy = true;
                this.error = null;
                this.done(fetch('/marketplace/api/events/{{ $event->id }}/document-filing', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body
                }));
            }
        }"
    >
        @if($canConfirm)
            <button type="button" style="{{ $button }}background:#2563eb;color:#fff;" x-on:click="open('confirmDialog')">
                Depune manual
            </button>

            <dialog x-ref="confirmDialog" class="ep-filing" style="{{ $dialog }}">
                <div style="font-size:15px;font-weight:700;margin-bottom:12px;">Depunere manuală: {{ $row['label'] }}</div>

                @if(!$row['document'])
                    <div style="margin-bottom:8px;padding:6px 8px;border-radius:8px;background:rgba(245,158,11,.12);">
                        Documentul nu e generat în platformă. Depunerea se înregistrează ca făcută în afara platformei.
                    </div>
                @endif

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

                <label style="display:block;margin-top:12px;max-width:220px;">
                    <span style="{{ $label }}display:block;margin-bottom:2px;">Data depunerii</span>
                    <input type="date" x-model="filedOn" max="{{ $today }}" required x-on:keydown.enter.prevent style="{{ $input }}">
                </label>

                <div x-show="error" x-text="error" style="margin-top:8px;color:#dc2626;"></div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" style="{{ $button }}background:transparent;color:inherit;" x-on:click="$refs.confirmDialog.close()">Renunță</button>
                    <button type="button" style="{{ $button }}background:#2563eb;color:#fff;" x-bind:disabled="busy || !filedOn"
                        x-on:click="send({ action: 'confirm_manual', filed_on: filedOn })">
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

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <label style="display:block;">
                        <span style="{{ $label }}display:block;margin-bottom:2px;">Nr. înregistrare</span>
                        <input type="text" maxlength="100" x-model="number" autofocus x-on:keydown.enter.prevent style="{{ $input }}">
                    </label>
                    <label style="display:block;">
                        <span style="{{ $label }}display:block;margin-bottom:2px;">Data înregistrării</span>
                        <input type="date" x-model="registrationDate" max="{{ $today }}" x-on:keydown.enter.prevent style="{{ $input }}">
                    </label>
                </div>
                @if(($filing['registered_source'] ?? null) !== \App\Models\EventDocumentFiling::REGISTERED_UPLOADED)
                    <div style="margin-top:8px;font-size:12px;opacity:.75;">Dacă documentul a fost generat aici, PDF-ul se completează cu numărul și data.</div>
                @endif

                <div x-show="error" x-text="error" style="margin-top:8px;color:#dc2626;"></div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" style="{{ $button }}background:transparent;color:inherit;" x-on:click="$refs.numberDialog.close()">Renunță</button>
                    <button type="button" style="{{ $button }}background:#2563eb;color:#fff;" x-bind:disabled="busy || !number.trim()"
                        x-on:click="send({ action: 'registration_number', registration_number: number, registration_date: registrationDate })">
                        <span x-text="busy ? 'Se salvează...' : 'Salvează'"></span>
                    </button>
                </div>
            </dialog>
        @endif

        @if($canUpload)
            <button type="button" style="{{ $button }}background:rgba(128,128,128,.15);color:inherit;" x-on:click="open('uploadDialog')">
                Încarcă exemplarul înregistrat
            </button>

            <dialog x-ref="uploadDialog" class="ep-filing" style="{{ $dialog }}">
                <div style="font-size:15px;font-weight:700;margin-bottom:4px;">Exemplar înregistrat</div>
                <div style="margin-bottom:12px;opacity:.75;">
                    {{ $row['label'] }} · PDF-ul primit înapoi de la primărie, cu numărul de înregistrare. Apare și în contul organizatorului.
                </div>

                <label style="display:block;margin-bottom:8px;">
                    <span style="{{ $label }}display:block;margin-bottom:2px;">Fișier PDF (maximum 10 MB)</span>
                    <input type="file" accept="application/pdf,.pdf" x-ref="registeredFile" style="{{ $input }}">
                </label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <label style="display:block;">
                        <span style="{{ $label }}display:block;margin-bottom:2px;">Nr. înregistrare</span>
                        <input type="text" maxlength="100" x-model="number" x-on:keydown.enter.prevent style="{{ $input }}">
                    </label>
                    <label style="display:block;">
                        <span style="{{ $label }}display:block;margin-bottom:2px;">Data înregistrării</span>
                        <input type="date" x-model="registrationDate" max="{{ $today }}" x-on:keydown.enter.prevent style="{{ $input }}">
                    </label>
                </div>

                <div x-show="error" x-text="error" style="margin-top:8px;color:#dc2626;"></div>

                <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                    <button type="button" style="{{ $button }}background:transparent;color:inherit;" x-on:click="$refs.uploadDialog.close()">Renunță</button>
                    <button type="button" style="{{ $button }}background:#2563eb;color:#fff;" x-bind:disabled="busy || !number.trim()"
                        x-on:click="upload()">
                        <span x-text="busy ? 'Se încarcă...' : 'Încarcă'"></span>
                    </button>
                </div>
            </dialog>
        @endif
    </div>
@endif
