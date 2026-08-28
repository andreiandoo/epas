<x-filament-panels::page>
    @php
        $presenceMeta = [
            'online'  => ['Online', 'bg-green-500'],
            'away'    => ['Away', 'bg-amber-500'],
            'offline' => ['Offline', 'bg-gray-400'],
        ];
        [$presenceLabel, $presenceDot] = $presenceMeta[$presence] ?? $presenceMeta['offline'];

        $statusMeta = [
            'queued'          => ['În așteptare', 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'],
            'active'          => ['Activ', 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300'],
            'offline_message' => ['Offline', 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'],
            'resolved'        => ['Rezolvat', 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'],
            'closed'          => ['Închis', 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'],
        ];
    @endphp

    <div>
        {{-- Polling is driven by a JS interval (not wire:poll) so it can be
             PAUSED while the operator is typing a reply. A Livewire re-render
             while typing would blank the textarea + steal focus; pausing avoids
             it entirely. The interval lives in a wire:ignore node so it is
             created once and never touched by morphs. --}}
        <div wire:ignore x-data x-init="
            if (window.__epChatPoll) clearInterval(window.__epChatPoll);
            window.__epChatPoll = setInterval(() => {
                if (!window.__epChatTyping) { try { @this.heartbeat(); } catch (e) {} }
            }, 3000);
        "></div>

        {{-- Top bar: presence + quick pills --}}
        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-600 dark:text-gray-300">
                    <span class="w-2.5 h-2.5 rounded-full {{ $presenceDot }}"></span>{{ $presenceLabel }}
                </span>
                <div class="flex items-center gap-1 ml-1">
                    <button type="button" wire:click="goOnline" class="px-2.5 py-1 text-xs font-medium rounded-lg {{ $presence === 'online' ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200' }}">Online</button>
                    <button type="button" wire:click="goAway" class="px-2.5 py-1 text-xs font-medium rounded-lg {{ $presence === 'away' ? 'bg-amber-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200' }}">Away</button>
                    <button type="button" wire:click="goOffline" class="px-2.5 py-1 text-xs font-medium rounded-lg {{ $presence === 'offline' ? 'bg-gray-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200' }}">Offline</button>
                </div>
                @if(($stats['my_ratings'] ?? 0) > 0)
                    <span class="ml-2 inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-300" title="Rating-ul tău mediu din {{ $stats['my_ratings'] }} evaluări">
                        <span class="text-amber-400">★</span>{{ $stats['my_avg'] }}
                        <span class="text-gray-400">({{ $stats['my_ratings'] }})</span>
                    </span>
                @endif
            </div>

            @php
                $pills = [
                    ['key' => 'queue',   'label' => 'În așteptare', 'count' => $queue->count(),   'dot' => 'bg-blue-500'],
                    ['key' => 'offline', 'label' => 'Offline',      'count' => $offline->count(), 'dot' => 'bg-amber-500'],
                    ['key' => 'mine',    'label' => 'Ale mele',     'count' => $mine->count(),    'dot' => 'bg-green-500'],
                    ['key' => 'all',     'label' => 'Toate',        'count' => $all->count(),     'dot' => 'bg-gray-400'],
                ];
            @endphp
            <style>
                @keyframes epQueuePulse { 0%,100% { box-shadow: 0 0 0 0 rgba(59,130,246,.55); } 50% { box-shadow: 0 0 0 6px rgba(59,130,246,0); } }
                .ep-queue-pulse { animation: epQueuePulse 1.15s ease-out infinite; border-color: rgb(59,130,246) !important; }
            </style>
            <div class="flex items-center gap-2 flex-wrap">
                @foreach($pills as $p)
                    <button type="button" wire:click="togglePanel('{{ $p['key'] }}')"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition {{ $p['key'] === 'queue' && $p['count'] > 0 && $openPanel !== 'queue' ? 'ep-queue-pulse' : '' }} {{ $openPanel === $p['key'] ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200' }}">
                        <span class="w-2 h-2 rounded-full {{ $p['dot'] }}"></span>
                        {{ $p['label'] }}
                        <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-[11px] font-semibold {{ $openPanel === $p['key'] ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">{{ $p['count'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Dropdown pickers (absolute, one at a time; close after choosing) --}}
        <div class="relative z-20">
            {{-- Queue --}}
            @if($openPanel === 'queue')
                <div
                class="absolute right-0 top-0 w-72 sm:w-80 max-h-96 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl">
                @forelse($queue as $c)
                    <div class="px-3 py-1.5 flex items-center justify-between gap-2 border-b border-gray-50 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <button type="button" wire:click="select('{{ $c->reference }}')" class="text-left flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</span>
                                @if($c->isOrganizer())<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">ORG</span>@endif
                            </div>
                            <div class="text-[11px] text-gray-400 truncate">{{ $c->reference }} · {{ optional($c->queued_at)->diffForHumans() }}</div>
                        </button>
                        <button type="button" wire:click="claim('{{ $c->reference }}')" class="shrink-0 px-2 py-1 text-[11px] font-medium rounded-md bg-primary-600 text-white">Preia</button>
                    </div>
                @empty
                    <div class="px-3 py-6 text-center text-xs text-gray-400">Nicio conversație în așteptare.</div>
                @endforelse
            </div>
            @endif

            {{-- Offline --}}
            @if($openPanel === 'offline')
                <div
                class="absolute right-0 top-0 w-72 sm:w-80 max-h-96 overflow-y-auto rounded-xl border border-amber-200 dark:border-amber-800 bg-white dark:bg-gray-900 shadow-xl">
                @forelse($offline as $c)
                    <div class="px-3 py-1.5 flex items-center justify-between gap-2 border-b border-gray-50 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <button type="button" wire:click="select('{{ $c->reference }}')" class="text-left flex-1 min-w-0">
                            <span class="block truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</span>
                            <span class="block text-[11px] text-gray-400 truncate">{{ $c->guest_email ?: $c->reference }} · {{ optional($c->last_activity_at)->diffForHumans() }}</span>
                        </button>
                        <button type="button" wire:click="claim('{{ $c->reference }}')" class="shrink-0 px-2 py-1 text-[11px] font-medium rounded-md bg-amber-500 text-white">Preia</button>
                    </div>
                @empty
                    <div class="px-3 py-6 text-center text-xs text-gray-400">Niciun mesaj offline.</div>
                @endforelse
            </div>
            @endif

            {{-- Mine --}}
            @if($openPanel === 'mine')
                <div
                class="absolute right-0 top-0 w-72 sm:w-80 max-h-96 overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl">
                @forelse($mine as $c)
                    <button type="button" wire:click="select('{{ $c->reference }}')" class="w-full text-left px-3 py-1.5 border-b border-gray-50 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <div class="flex items-center gap-1.5">
                            <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</span>
                            @if($c->isOrganizer())<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700">ORG</span>@endif
                        </div>
                        <div class="text-[11px] text-gray-400 truncate">{{ $c->reference }} · {{ optional($c->last_activity_at)->diffForHumans() }}</div>
                    </button>
                @empty
                    <div class="px-3 py-6 text-center text-xs text-gray-400">Nicio conversație activă.</div>
                @endforelse
            </div>
            @endif

            {{-- All (with who claimed) --}}
            @if($openPanel === 'all')
                <div
                class="absolute right-0 top-0 w-80 sm:w-96 max-h-[28rem] overflow-y-auto rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl">
                @forelse($all as $c)
                    @php [$slabel, $sclass] = $statusMeta[$c->status] ?? ['—', 'bg-gray-100 text-gray-600']; @endphp
                    <div class="flex items-center gap-1 px-3 py-1.5 border-b border-gray-50 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                        <button type="button" wire:click="select('{{ $c->reference }}')" class="flex-1 min-w-0 text-left">
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</span>
                                <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $sclass }}">{{ $slabel }}</span>
                            </div>
                            <div class="text-[11px] text-gray-400 truncate">
                                {{ $c->reference }} ·
                                @if($c->assignee)
                                    <span class="text-gray-500 dark:text-gray-300">preluat de {{ $c->assignee->name }}</span>
                                @else
                                    <span class="text-amber-500">nepreluat</span>
                                @endif
                            </div>
                        </button>
                        <button type="button" title="Șterge conversația"
                            x-on:click="if (confirm('Sigur ștergi această conversație?') && confirm('Confirmă din nou: ștergerea este permanentă și nu poate fi anulată.')) { $wire.deleteConversation('{{ $c->reference }}'); }"
                            class="shrink-0 p-1.5 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                @empty
                    <div class="px-3 py-6 text-center text-xs text-gray-400">Nicio conversație.</div>
                @endforelse
            </div>
            @endif
        </div>

        {{-- Main: thread (focus) + visitor context --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-1">
            {{-- Thread --}}
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 h-[38rem] flex flex-col">
                    @if($active)
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $active->openerName() }}</span>
                                    @if($active->isOrganizer())<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">ORGANIZATOR</span>@endif
                                    @php [$aslabel, $asclass] = $statusMeta[$active->status] ?? ['—','bg-gray-100 text-gray-600']; @endphp
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $asclass }}">{{ $aslabel }}</span>
                                    @if($active->status === 'active' && $active->last_activity_at)
                                        {{-- Inactivity countdown: resets whenever a new message bumps last_activity_at (re-runs on poll). --}}
                                        <span wire:key="cd-{{ $active->reference }}-{{ $active->last_activity_at->timestamp }}"
                                            x-data="{ label: '' }" x-init="
                                                if (window.__epChatCountdown) clearInterval(window.__epChatCountdown);
                                                const end = {{ $active->last_activity_at->timestamp }} * 1000 + {{ (int) config('chat.conversation.inactivity_timeout_minutes', 4) }} * 60000;
                                                const tick = () => { const r = Math.max(0, Math.floor((end - Date.now())/1000)); const m = Math.floor(r/60), s = r % 60; label = m + ':' + (s < 10 ? '0' : '') + s; };
                                                tick(); window.__epChatCountdown = setInterval(tick, 1000);
                                            "
                                            class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                            title="Se închide automat la inactivitate">⏱ <span x-text="label"></span></span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-400">
                                    {{ $active->reference }}
                                    @if($active->assignee) · preluat de {{ $active->assignee->name }} @endif
                                </div>
                            </div>
                            @unless($active->isClosed())
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($operators->count())
                                        <select x-on:change="if($event.target.value){ $wire.transfer('{{ $active->reference }}', $event.target.value); $event.target.value=''; }"
                                            class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 py-1.5">
                                            <option value="">Transferă…</option>
                                            @foreach($operators as $op)
                                                <option value="{{ $op->marketplace_admin_id }}">{{ $op->operator?->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="button"
                                        x-on:click="var r = prompt('Motivul blocării acestui vizitator (IP + email):', ''); if (r !== null) { $wire.blockVisitor('{{ $active->reference }}', r); }"
                                        title="Adaugă IP-ul și emailul în blocklist"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-red-600 text-white hover:bg-red-700">Blochează</button>
                                    <button type="button" wire:click="resolve('{{ $active->reference }}')" class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">Rezolvă</button>
                                </div>
                            @endunless
                        </div>

                        <div class="flex-1 overflow-y-auto p-4 space-y-3" x-data
                            x-init="$el.scrollTop = $el.scrollHeight; new MutationObserver(() => { $el.scrollTop = $el.scrollHeight; }).observe($el, { childList: true, subtree: true });">
                            <div class="text-center text-[11px] text-gray-400">Conversație începută {{ optional($active->created_at)->format('d.m.Y H:i') }}</div>
                            @foreach($messages as $m)
                                @if($m->type === 'system')
                                    <div class="text-center text-[11px] text-gray-400">{{ $m->body }} · {{ optional($m->created_at)->format('H:i') }}</div>
                                @elseif($m->is_internal)
                                    {{-- Internal note (operator-only) → LEFT, amber --}}
                                    <div class="flex justify-start">
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-sm px-3 py-2 bg-amber-50 border border-amber-200 dark:bg-amber-900/20 dark:border-amber-800">
                                            <div class="text-[10px] font-semibold text-amber-600 mb-0.5">Notă internă · {{ $m->author_label }} · {{ optional($m->created_at)->format('H:i') }}</div>
                                            <div class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ $m->body }}</div>
                                        </div>
                                    </div>
                                @elseif($m->isFromStaff())
                                    {{-- Operator (staff) → LEFT --}}
                                    <div class="flex justify-start">
                                        <div class="max-w-[80%] rounded-2xl rounded-bl-sm px-3 py-2 bg-gray-100 dark:bg-gray-800">
                                            <div class="text-[10px] font-semibold text-gray-500 mb-0.5">{{ $m->author_label }} · {{ optional($m->created_at)->format('H:i') }}</div>
                                            @if($m->body)<div class="text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap">{{ $m->body }}</div>@endif
                                            @foreach($m->attachments ?? [] as $att)
                                                @if(!empty($att['token']))
                                                    <img src="{{ route('marketplace.chat.attachment', ['conversation' => $active->id, 'token' => $att['token']]) }}" onclick="window.open(this.src,'_blank')" class="mt-1 rounded-lg max-w-[220px] max-h-[220px] cursor-pointer border border-black/5" alt="imagine">
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    {{-- Client (opener) → RIGHT --}}
                                    <div class="flex justify-end">
                                        <div class="max-w-[80%] rounded-2xl rounded-br-sm px-3 py-2 bg-primary-600 text-white">
                                            @if($m->body)<div class="text-sm whitespace-pre-wrap">{{ $m->body }}</div>@endif
                                            @foreach($m->attachments ?? [] as $att)
                                                @if(!empty($att['token']))
                                                    <img src="{{ route('marketplace.chat.attachment', ['conversation' => $active->id, 'token' => $att['token']]) }}" onclick="window.open(this.src,'_blank')" class="mt-1 rounded-lg max-w-[220px] max-h-[220px] cursor-pointer border border-white/20" alt="imagine">
                                                @endif
                                            @endforeach
                                            <div class="text-[10px] text-white/70 mt-0.5 text-right">{{ optional($m->created_at)->format('H:i') }}</div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @if($active->isClosed())
                            @php
                                $closedReason = $active->status === 'resolved'
                                    ? 'Marcată ca rezolvată de operator.'
                                    : 'Închisă automat din inactivitate.';
                                $closedAt = $active->closed_at ?? $active->resolved_at;
                            @endphp
                            <div class="border-t border-gray-100 dark:border-gray-800 p-3 text-center">
                                <div class="text-xs font-semibold text-gray-600 dark:text-gray-300">Conversație încheiată</div>
                                <div class="text-[11px] text-gray-400 mt-0.5">{{ $closedReason }}@if($closedAt) · {{ $closedAt->format('d.m.Y H:i') }}@endif</div>
                            </div>
                        @endif

                        @unless($active->isClosed())
                            @php
                                // shortcut => expanded body ({name}/{event} substituted) for typed-shortcut expansion.
                                $cannedMap = [];
                                foreach ($canned as $cr) {
                                    $cannedMap[$cr->shortcut] = str_replace(
                                        ['{name}', '{event}'],
                                        [$active->openerName(), $active->event_id ? '#'.$active->event_id : ''],
                                        $cr->body
                                    );
                                }
                            @endphp
                            {{-- wire:ignore on the WHOLE composer so the 3s poll never re-inits the
                                 Alpine x-data (which would blank the textarea) or steal focus.
                                 wire:key rebuilds it only when the active conversation changes. --}}
                            <div wire:ignore wire:key="composer-{{ $active->reference }}"
                                x-data="{ msg: '', internal: false, uploading: false, cmap: {{ \Illuminate\Support\Js::from($cannedMap) }}, attachUrl: '{{ route('marketplace.chat.attach', ['conversation' => $active->id]) }}',
                                    uploadImg(e) {
                                        var f = e.target.files && e.target.files[0]; e.target.value = '';
                                        if (!f) return;
                                        if (!/^image\/(png|jpe?g|webp)$/.test(f.type)) { alert('Doar imagini (JPG, PNG, WEBP).'); return; }
                                        if (f.size > 3145728) { alert('Imagine prea mare (max 3MB).'); return; }
                                        this.uploading = true; var self = this; var reader = new FileReader();
                                        reader.onload = function () {
                                            fetch(self.attachUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' }, body: JSON.stringify({ data: reader.result, name: f.name }) })
                                                .then(function (r) { return r.json(); }).then(function (res) { self.uploading = false; if (!res || !res.ok) { alert((res && res.message) || 'Încărcarea a eșuat.'); } })
                                                .catch(function () { self.uploading = false; alert('Eroare la încărcare.'); });
                                        };
                                        reader.readAsDataURL(f);
                                    } }"
                                class="border-t border-gray-100 dark:border-gray-800 p-3">
                                <div class="flex items-center gap-2 mb-2 flex-wrap">
                                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                                        <input type="checkbox" x-model="internal" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                                        Notă internă (invizibilă clientului)
                                    </label>
                                    @if($canned->count())
                                        <select x-on:change="if ($event.target.value) { msg = (msg ? msg + '\n' : '') + ($event.target.selectedOptions[0].dataset.body || ''); $event.target.value = ''; window.__epChatTyping = true; $refs.replyBox.focus(); }"
                                            class="ml-auto text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 py-1">
                                            <option value="">Răspuns predefinit…</option>
                                            @foreach($canned as $cr)
                                                <option value="{{ $cr->id }}" data-body="{{ $cannedMap[$cr->shortcut] ?? $cr->body }}">{{ $cr->shortcut }} — {{ $cr->title }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <div class="flex items-end gap-2">
                                    <input type="file" x-ref="opFile" accept="image/png,image/jpeg,image/webp" class="hidden" x-on:change="uploadImg($event)">
                                    <button type="button" x-on:click="$refs.opFile.click()" x-bind:disabled="uploading"
                                        title="Atașează o imagine" class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 hover:bg-gray-200 disabled:opacity-50">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21.44 11.05l-9.19 9.19a5 5 0 0 1-7.07-7.07l9.19-9.19a3.5 3.5 0 0 1 4.95 4.95l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                    </button>
                                    <textarea x-ref="replyBox" x-model="msg" rows="2" placeholder="Scrie un răspuns... (poți folosi o scurtătură, ex: /refund)"
                                        x-on:focus="window.__epChatTyping = true"
                                        x-on:blur="window.__epChatTyping = false"
                                        x-on:input="if (cmap[msg.trim()] !== undefined) { msg = cmap[msg.trim()]; }"
                                        x-on:keydown.enter.prevent="if(msg.trim()){ window.__epChatTyping = false; $wire.sendReply(msg, internal); msg=''; }"
                                        class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm px-3 py-2 leading-relaxed focus:ring-primary-500 focus:border-primary-500"></textarea>
                                    <button type="button" x-on:click="if(msg.trim()){ window.__epChatTyping = false; $wire.sendReply(msg, internal); msg=''; }"
                                        class="px-4 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700">Trimite</button>
                                </div>
                            </div>
                        @endunless
                    @else
                        <div class="flex-1 flex items-center justify-center text-sm text-gray-400 p-8 text-center">
                            Alege o conversație din butoanele de sus (În așteptare / Offline / Ale mele / Toate).
                        </div>
                    @endif
                </div>
            </div>

            {{-- Visitor context --}}
            <div class="lg:col-span-1">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-3">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Context vizitator</div>
                    @if($active)
                        <dl class="text-sm space-y-2">
                            <div>
                                <dt class="text-[11px] text-gray-400">Nume</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ $active->openerName() }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] text-gray-400">Tip</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ ucfirst($active->visitor_type) }}</dd>
                            </div>
                            @if($active->guest_email)
                                <div>
                                    <dt class="text-[11px] text-gray-400">Email</dt>
                                    <dd class="text-gray-800 dark:text-gray-100 break-all">{{ $active->guest_email }}</dd>
                                </div>
                            @endif
                            @if($active->event_id)
                                <div>
                                    <dt class="text-[11px] text-gray-400">Eveniment</dt>
                                    <dd class="text-gray-800 dark:text-gray-100">{{ $eventTitle ? $eventTitle . ' (#' . $active->event_id . ')' : '#' . $active->event_id }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="text-[11px] text-gray-400">Operator</dt>
                                <dd class="text-gray-800 dark:text-gray-100">{{ $active->assignee?->name ?? 'nepreluat' }}</dd>
                            </div>
                            @if(data_get($active->context, 'opened_url'))
                                <div>
                                    <dt class="text-[11px] text-gray-400">Pagină</dt>
                                    <dd class="text-gray-600 dark:text-gray-300 text-xs break-all">{{ data_get($active->context, 'opened_url') }}</dd>
                                </div>
                            @endif
                            @php $ctx = $active->context ?? []; @endphp
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div>
                                    <dt class="text-[11px] text-gray-400">IP</dt>
                                    <dd class="text-gray-700 dark:text-gray-200 text-xs">{{ data_get($ctx, 'ip') ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] text-gray-400">Dispozitiv</dt>
                                    <dd class="text-gray-700 dark:text-gray-200 text-xs">{{ data_get($ctx, 'device') ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] text-gray-400">Browser</dt>
                                    <dd class="text-gray-700 dark:text-gray-200 text-xs">{{ data_get($ctx, 'browser') ?: '—' }}{{ data_get($ctx, 'os') ? ' · '.data_get($ctx, 'os') : '' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] text-gray-400">Rezoluție</dt>
                                    <dd class="text-gray-700 dark:text-gray-200 text-xs">{{ data_get($ctx, 'screen') ?: '—' }}</dd>
                                </div>
                            </div>
                        </dl>

                        @if($history->count())
                            <div class="pt-3 mt-3 border-t border-gray-100 dark:border-gray-800">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Istoric conversații ({{ $history->count() }})</div>
                                <div class="space-y-1 max-h-40 overflow-y-auto">
                                    @foreach($history as $h)
                                        @php [$hlabel, $hclass] = $statusMeta[$h->status] ?? ['—','bg-gray-100 text-gray-600']; @endphp
                                        <button type="button" wire:click="select('{{ $h->reference }}')"
                                            class="w-full text-left flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ optional($h->created_at)->format('d.m.Y H:i') }}</span>
                                            <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $hclass }}">{{ $hlabel }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @else
                        <p class="text-xs text-gray-400">Selectează o conversație pentru a vedea contextul.</p>
                    @endif
                </div>

                @if($active && !empty($visitor['type']))
                    @php $isCust = $visitor['type'] === 'customer'; @endphp
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 mt-3">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {{ $isCust ? 'Istoric client' : 'Istoric organizator' }}
                            </div>
                            <div class="text-[11px] text-gray-400">
                                @if($isCust)
                                    {{ ($visitor['stats']['orders'] ?? 0) }} comenzi · {{ ($visitor['stats']['tickets'] ?? 0) }} bilete{{ !empty($visitor['stats']['spent']) ? ' · '.$visitor['stats']['spent'] : '' }}
                                @else
                                    {{ $visitor['stats']['events'] ?? 0 }} evenimente · {{ $visitor['stats']['tickets_sold'] ?? 0 }} bilete vândute
                                @endif
                            </div>
                        </div>

                        @if($isCust)
                            <div class="relative mb-2">
                                <input type="text" wire:model.live.debounce.500ms="historySearch"
                                    x-on:focus="window.__epChatTyping = true" x-on:blur="window.__epChatTyping = false"
                                    placeholder="Caută: eveniment, nr. comandă sau nr. bilet..."
                                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 pl-3 pr-7 py-1.5">
                                @if($visitor['searching'] ?? false)
                                    <button type="button" wire:click="$set('historySearch','')" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">&times;</button>
                                @endif
                            </div>
                        @endif

                        @if(count($visitor['upcoming'] ?? []))
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-green-600 mb-1">Urmează</div>
                            <div class="space-y-1.5 mb-3">
                                @foreach($visitor['upcoming'] as $ev)
                                    @include('filament.marketplace.pages.partials.chat-history-event', ['ev' => $ev, 'isCust' => $isCust, 'upcoming' => true])
                                @endforeach
                            </div>
                        @endif

                        @if(count($visitor['past'] ?? []))
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">Trecute</div>
                            <div class="space-y-1.5 max-h-72 overflow-y-auto">
                                @foreach($visitor['past'] as $ev)
                                    @include('filament.marketplace.pages.partials.chat-history-event', ['ev' => $ev, 'isCust' => $isCust, 'upcoming' => false])
                                @endforeach
                            </div>
                        @endif

                        @if(!count($visitor['upcoming'] ?? []) && !count($visitor['past'] ?? []))
                            <p class="text-xs text-gray-400">{{ ($visitor['searching'] ?? false) ? 'Niciun rezultat pentru căutare.' : ($isCust ? 'Nicio comandă găsită.' : 'Niciun eveniment găsit.') }}</p>
                        @endif
                    </div>
                @endif

                @if(($stats['avg_rating'] ?? 0) > 0)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 mt-3">
                        <div class="text-xs text-gray-400">Rating mediu</div>
                        <div class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $stats['avg_rating'] }} <span class="text-amber-400 text-lg">★</span></div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
