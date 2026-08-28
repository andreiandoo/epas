<x-filament-panels::page>
    <div wire:poll.5s="heartbeat">
        {{-- Presence bar --}}
        <div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-300">Status operator:</span>
                @php
                    $presenceMeta = [
                        'online'  => ['Online', 'bg-green-500'],
                        'away'    => ['Away', 'bg-amber-500'],
                        'offline' => ['Offline', 'bg-gray-400'],
                    ];
                    [$presenceLabel, $presenceDot] = $presenceMeta[$presence] ?? $presenceMeta['offline'];
                @endphp
                <span class="inline-flex items-center gap-1.5 text-sm">
                    <span class="w-2.5 h-2.5 rounded-full {{ $presenceDot }}"></span>{{ $presenceLabel }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" wire:click="goOnline"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $presence === 'online' ? 'bg-green-600 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200' }}">Online</button>
                <button type="button" wire:click="goAway"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $presence === 'away' ? 'bg-amber-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200' }}">Away</button>
                <button type="button" wire:click="goOffline"
                    class="px-3 py-1.5 text-xs font-medium rounded-lg {{ $presence === 'offline' ? 'bg-gray-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200' }}">Offline</button>
            </div>
        </div>

        {{-- Stats strip --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                <div class="text-[11px] text-gray-400 uppercase tracking-wide">În așteptare</div>
                <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $stats['queued'] ?? 0 }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                <div class="text-[11px] text-gray-400 uppercase tracking-wide">Active</div>
                <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $stats['active'] ?? 0 }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                <div class="text-[11px] text-gray-400 uppercase tracking-wide">Ale mele</div>
                <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ $stats['mine'] ?? 0 }}</div>
            </div>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                <div class="text-[11px] text-gray-400 uppercase tracking-wide">Rating mediu</div>
                <div class="text-xl font-bold text-gray-800 dark:text-gray-100">{{ ($stats['avg_rating'] ?? 0) ?: '—' }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
            {{-- Left: lists --}}
            <div class="lg:col-span-4 space-y-4">
                {{-- Queue --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase tracking-wide text-gray-500 flex items-center justify-between">
                        <span>În așteptare</span>
                        <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-primary-600 text-white text-[11px]">{{ $queue->count() }}</span>
                    </div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-64 overflow-y-auto">
                        @forelse($queue as $c)
                            <div class="px-3 py-2 flex items-center justify-between gap-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <button type="button" wire:click="select('{{ $c->reference }}')" class="text-left flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</span>
                                        @if($c->isOrganizer())
                                            <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">ORGANIZATOR</span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-gray-400 truncate">{{ $c->reference }} · {{ optional($c->queued_at)->diffForHumans() }}</div>
                                </button>
                                <button type="button" wire:click="claim('{{ $c->reference }}')"
                                    class="shrink-0 px-2 py-1 text-[11px] font-medium rounded-md bg-primary-600 text-white hover:bg-primary-700">Preia</button>
                            </div>
                        @empty
                            <div class="px-3 py-6 text-center text-xs text-gray-400">Nicio conversație în așteptare.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Offline messages --}}
                @if($offline->count())
                    <div class="rounded-xl border border-amber-200 dark:border-amber-800 overflow-hidden">
                        <div class="px-3 py-2 bg-amber-50 dark:bg-amber-900/20 text-xs font-semibold uppercase tracking-wide text-amber-600 flex items-center justify-between">
                            <span>Mesaje offline</span>
                            <span class="inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-amber-500 text-white text-[11px]">{{ $offline->count() }}</span>
                        </div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-56 overflow-y-auto">
                            @foreach($offline as $c)
                                <div class="px-3 py-2 flex items-center justify-between gap-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <button type="button" wire:click="select('{{ $c->reference }}')" class="text-left flex-1 min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</span>
                                            @if($c->isOrganizer())
                                                <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">ORG</span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-gray-400 truncate">{{ $c->guest_email ?: $c->reference }} · {{ optional($c->last_activity_at)->diffForHumans() }}</div>
                                    </button>
                                    <button type="button" wire:click="claim('{{ $c->reference }}')"
                                        class="shrink-0 px-2 py-1 text-[11px] font-medium rounded-md bg-amber-500 text-white hover:bg-amber-600">Preia</button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Mine --}}
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase tracking-wide text-gray-500">Conversațiile mele ({{ $mine->count() }})</div>
                    <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-64 overflow-y-auto">
                        @forelse($mine as $c)
                            <button type="button" wire:click="select('{{ $c->reference }}')"
                                class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 {{ $activeReference === $c->reference ? 'bg-primary-50 dark:bg-primary-900/20' : '' }}">
                                <div class="flex items-center gap-1.5">
                                    <span class="truncate text-sm font-medium text-gray-800 dark:text-gray-100">{{ $c->openerName() }}</span>
                                    @if($c->isOrganizer())
                                        <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">ORG</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-400 truncate">{{ $c->reference }} · {{ optional($c->last_activity_at)->diffForHumans() }}</div>
                            </button>
                        @empty
                            <div class="px-3 py-6 text-center text-xs text-gray-400">Nicio conversație activă.</div>
                        @endforelse
                    </div>
                </div>

                {{-- Others --}}
                @if($others->count())
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="px-3 py-2 bg-gray-50 dark:bg-gray-800 text-xs font-semibold uppercase tracking-wide text-gray-500">Alți operatori ({{ $others->count() }})</div>
                        <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-40 overflow-y-auto">
                            @foreach($others as $c)
                                <button type="button" wire:click="select('{{ $c->reference }}')" class="w-full text-left px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800">
                                    <span class="truncate text-sm text-gray-700 dark:text-gray-200">{{ $c->openerName() }}</span>
                                    <span class="text-[11px] text-gray-400"> · {{ $c->assignee?->name ?? 'nealocat' }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Center: thread --}}
            <div class="lg:col-span-5">
                <div class="rounded-xl border border-gray-200 dark:border-gray-700 h-[36rem] flex flex-col">
                    @if($active)
                        <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $active->openerName() }}</span>
                                    @if($active->isOrganizer())
                                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300">ORGANIZATOR</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-400">{{ $active->reference }} · {{ ucfirst($active->status) }}</div>
                            </div>
                            @unless($active->isClosed())
                                <div class="flex items-center gap-2 shrink-0">
                                    @if($operators->count())
                                        <select
                                            x-on:change="if($event.target.value){ $wire.transfer('{{ $active->reference }}', $event.target.value); $event.target.value=''; }"
                                            class="text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 py-1.5">
                                            <option value="">Transferă…</option>
                                            @foreach($operators as $op)
                                                <option value="{{ $op->marketplace_admin_id }}">{{ $op->operator?->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <button type="button" wire:click="resolve('{{ $active->reference }}')"
                                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-green-600 text-white hover:bg-green-700">Rezolvă</button>
                                </div>
                            @endunless
                        </div>

                        <div class="flex-1 overflow-y-auto p-4 space-y-3">
                            @foreach($messages as $m)
                                @if($m->type === 'system')
                                    <div class="text-center text-[11px] text-gray-400">{{ $m->body }}</div>
                                @elseif($m->is_internal)
                                    <div class="max-w-[85%] ml-auto rounded-lg px-3 py-2 bg-amber-50 border border-amber-200 dark:bg-amber-900/20 dark:border-amber-800">
                                        <div class="text-[10px] font-semibold text-amber-600 mb-0.5">Notă internă · {{ $m->author_label }}</div>
                                        <div class="text-sm text-gray-700 dark:text-gray-200 whitespace-pre-wrap">{{ $m->body }}</div>
                                    </div>
                                @elseif($m->isFromStaff())
                                    <div class="max-w-[85%] ml-auto rounded-lg px-3 py-2 bg-primary-600 text-white">
                                        <div class="text-[10px] opacity-80 mb-0.5">{{ $m->author_label }}</div>
                                        <div class="text-sm whitespace-pre-wrap">{{ $m->body }}</div>
                                    </div>
                                @else
                                    <div class="max-w-[85%] rounded-lg px-3 py-2 bg-gray-100 dark:bg-gray-800">
                                        <div class="text-sm text-gray-800 dark:text-gray-100 whitespace-pre-wrap">{{ $m->body }}</div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        @unless($active->isClosed())
                            <div class="border-t border-gray-100 dark:border-gray-800 p-3">
                                <div class="flex items-center gap-2 mb-2 flex-wrap">
                                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer">
                                        <input type="checkbox" wire:model="internalNote" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                                        Notă internă (invizibilă clientului)
                                    </label>
                                    @if($canned->count())
                                        <select
                                            x-on:change="if($event.target.value){ $wire.insertCanned(parseInt($event.target.value)); $event.target.value=''; }"
                                            class="ml-auto text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 py-1">
                                            <option value="">Răspuns predefinit…</option>
                                            @foreach($canned as $cr)
                                                <option value="{{ $cr->id }}">{{ $cr->shortcut }} — {{ $cr->title }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                </div>
                                <form wire:submit.prevent="send" class="flex items-end gap-2">
                                    <textarea wire:model="reply" rows="2" placeholder="Scrie un răspuns..."
                                        class="flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-sm focus:ring-primary-500 focus:border-primary-500"></textarea>
                                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700">Trimite</button>
                                </form>
                            </div>
                        @endunless
                    @else
                        <div class="flex-1 flex items-center justify-center text-sm text-gray-400">
                            Selectează o conversație din stânga.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Right: context --}}
            <div class="lg:col-span-3">
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
                            @if(data_get($active->context, 'opened_url'))
                                <div>
                                    <dt class="text-[11px] text-gray-400">Pagină</dt>
                                    <dd class="text-gray-600 dark:text-gray-300 text-xs break-all">{{ data_get($active->context, 'opened_url') }}</dd>
                                </div>
                            @endif
                        </dl>
                    @else
                        <p class="text-xs text-gray-400">Selectează o conversație pentru a vedea contextul.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
