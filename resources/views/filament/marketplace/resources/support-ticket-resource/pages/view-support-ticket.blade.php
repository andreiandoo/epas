@php
    /** @var \App\Models\SupportTicket $ticket */
    $ticket = $this->record;
    $messages = $this->getThreadMessages();
    $opener = $this->getOpener();
    $events = $this->getActiveEvents();
    $context = $this->getRequestContext();

    // Use Filament's semantic color tokens — they're always compiled into
    // the panel CSS, so contrast is guaranteed regardless of Tailwind purge.
    // Status labels reflect the *staff perspective*: when 'awaiting_organizer'
    // we just sent a reply ("Răspuns trimis"); 'in_progress' = organizer
    // replied so the ball is back in our court ("Așteaptă răspuns").
    $statusMap = [
        'open' => ['label' => 'Deschis', 'color' => 'info'],
        'in_progress' => ['label' => 'Așteaptă răspuns', 'color' => 'warning'],
        'awaiting_organizer' => ['label' => 'Răspuns trimis', 'color' => 'success'],
        'resolved' => ['label' => 'Rezolvat', 'color' => 'success'],
        'closed' => ['label' => 'Închis', 'color' => 'gray'],
    ];
    $statusBadge = $statusMap[$ticket->status] ?? ['label' => $ticket->status, 'color' => 'gray'];

    $priorityMap = [
        'low' => ['label' => 'Scăzută', 'color' => 'info'],
        'normal' => ['label' => 'Normală', 'color' => 'gray'],
        'high' => ['label' => 'Ridicată', 'color' => 'warning'],
        'urgent' => ['label' => 'Urgentă', 'color' => 'danger'],
    ];
    $priorityBadge = $priorityMap[$ticket->priority] ?? ['label' => $ticket->priority, 'color' => 'gray'];

    // Bubble colors: inline hex so they render even if Tailwind utility
    // classes weren't picked up by the panel theme build.
    $bubbleStaff = 'background:#dbeafe;color:#1e3a8a;';        // blue-100/blue-900
    $bubbleStaffAvatar = 'background:#bfdbfe;color:#1e40af;';  // blue-200/blue-800
    $bubbleOpener = 'background:#f1f5f9;color:#0f172a;';       // slate-100/slate-900
    $bubbleOpenerAvatar = 'background:#cbd5e1;color:#334155;'; // slate-300/slate-700
    $bubbleNote = 'background:#fef3c7;color:#78350f;border:1px solid #fde68a;'; // amber-100/amber-900
    $bubbleNoteAvatar = 'background:#fde68a;color:#78350f;';   // amber-200/amber-900
@endphp

<x-filament-panels::page>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ========== Main column: thread ========== --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Header card --}}
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                <div class="flex flex-wrap items-center gap-2 mb-2 text-xs">
                    <span class="font-mono text-gray-500">{{ $ticket->ticket_number ?: ('#' . $ticket->id) }}</span>
                    <x-filament::badge :color="$statusBadge['color']">{{ $statusBadge['label'] }}</x-filament::badge>
                    <x-filament::badge :color="$priorityBadge['color']">{{ $priorityBadge['label'] }}</x-filament::badge>
                    <span class="text-gray-400">·</span>
                    <span class="text-gray-600 dark:text-gray-300">
                        {{ $ticket->department?->getTranslation('name', 'ro') ?? '—' }}
                    </span>
                    @if ($ticket->problemType)
                        <span class="text-gray-400">·</span>
                        <span class="text-gray-600 dark:text-gray-300">{{ $ticket->problemType->getTranslation('name', 'ro') }}</span>
                    @endif
                </div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $ticket->subject }}</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Deschis pe <strong>{{ $ticket->opened_at?->format('d M Y, H:i') }}</strong>
                    @if ($ticket->first_response_at)
                        · Primul răspuns: {{ $ticket->first_response_at->diffForHumans($ticket->opened_at, ['parts' => 2, 'short' => true]) }}
                    @endif
                </p>

                @if (!empty($ticket->meta))
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-white/10 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        @foreach ($ticket->meta as $key => $value)
                            @if ($value === null || $value === '')
                                @continue
                            @endif
                            @php
                                $label = match ($key) {
                                    'url' => 'URL pagină',
                                    'invoice_series' => 'Seria decont',
                                    'invoice_number' => 'Număr decont',
                                    'event_id' => 'Eveniment',
                                    'module_name' => 'Modul afectat',
                                    default => $key,
                                };
                            @endphp
                            <div>
                                <p class="text-xs text-gray-500 mb-0.5">{{ $label }}</p>
                                @if ($key === 'url' && preg_match('#^https?://#', (string) $value))
                                    <a href="{{ $value }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline break-all">{{ $value }}</a>
                                @elseif ($key === 'event_id')
                                    @php
                                        $eventEditUrl = null;
                                        if (class_exists(\App\Filament\Marketplace\Resources\EventResource::class)) {
                                            try { $eventEditUrl = \App\Filament\Marketplace\Resources\EventResource::getUrl('edit', ['record' => (int) $value]); } catch (\Throwable) {}
                                        }
                                    @endphp
                                    @if ($eventEditUrl)
                                        <a href="{{ $eventEditUrl }}" class="text-primary-600 hover:underline">Eveniment #{{ $value }}</a>
                                    @else
                                        <span class="text-gray-700 dark:text-gray-300">Eveniment #{{ $value }}</span>
                                    @endif
                                @else
                                    <span class="text-gray-700 dark:text-gray-300">{{ $value }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Conversation thread --}}
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-4">Conversație</h3>
                @if ($messages->isEmpty())
                    <p class="text-sm text-gray-500 py-4 text-center">Nu există mesaje încă.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($messages as $message)
                            @php
                                $isStaff = $message->author_type === 'staff';
                                $isInternal = $message->is_internal_note;
                                $isEvent = !empty($message->event_type);
                                $authorName = $message->author->name ?? $message->author->public_name ?? $message->author->email ?? '—';
                                $initial = strtoupper(mb_substr($authorName ?: '?', 0, 1));
                                $bubbleStyle = $isInternal ? $bubbleNote : ($isStaff ? $bubbleStaff : $bubbleOpener);
                                $avatarStyle = $isInternal ? $bubbleNoteAvatar : ($isStaff ? $bubbleStaffAvatar : $bubbleOpenerAvatar);
                                $align = $isStaff ? 'flex-row' : 'flex-row-reverse';
                                $eventIcon = match ($message->event_type) {
                                    'resolved' => 'M5 13l4 4L19 7',
                                    'closed' => 'M6 18L18 6M6 6l12 12',
                                    'reopened' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
                                    'department_changed' => 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
                                    'assigned' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7zM21 12h-6m3-3v6',
                                    default => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                                };
                            @endphp
                            @if ($isEvent)
                                <div class="flex items-center gap-3 py-1 my-1">
                                    <div class="flex-1 h-px" style="background:#e5e7eb"></div>
                                    <div class="flex items-center gap-2 px-3 py-1 text-xs rounded-full" style="background:#f1f5f9;color:#475569">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $eventIcon }}"/></svg>
                                        <span><strong>{{ $message->body }}</strong> <span style="opacity:.7">de {{ $authorName }} · {{ $message->created_at?->format('d M Y, H:i') }}</span></span>
                                    </div>
                                    <div class="flex-1 h-px" style="background:#e5e7eb"></div>
                                </div>
                                @continue
                            @endif
                            <div class="flex {{ $align }} gap-3 items-start">
                                <div class="flex items-center justify-center flex-shrink-0 text-xs font-bold rounded-full" style="width:2.25rem;height:2.25rem;{{ $avatarStyle }}">{{ $initial }}</div>
                                <div class="flex-1" style="max-width:85%">
                                    <div class="px-4 py-3 rounded-2xl" style="{{ $bubbleStyle }}">
                                        @if ($isInternal)
                                            <div class="flex items-center gap-1.5 mb-1 text-xs font-semibold uppercase tracking-wider">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                Notă internă
                                            </div>
                                        @endif
                                        <div class="mb-1 text-xs font-semibold" style="opacity:.75">{{ $authorName }}</div>
                                        <div class="text-sm whitespace-pre-wrap break-words">{{ $message->body }}</div>
                                        @if (!empty($message->attachments))
                                            <div class="flex flex-wrap mt-2 gap-1.5">
                                                @foreach ($message->attachments as $a)
                                                    @php
                                                        $url = isset($a['path']) ? \Illuminate\Support\Facades\Storage::disk($a['disk'] ?? 'public')->url($a['path']) : null;
                                                    @endphp
                                                    @if ($url)
                                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs" style="background:rgba(255,255,255,.6)">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                            <span>{{ $a['original_name'] ?? 'fișier' }}</span>
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 {{ $isStaff ? '' : 'text-right' }}">
                                        {{ $message->created_at?->format('d M Y, H:i') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ========== Sidebar: assignment / opener / events / context ========== --}}
        <div class="space-y-4">

            <div class="p-5 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase">Asignare</h3>
                @if ($ticket->assignee)
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-sm font-bold rounded-full" style="background:#dbeafe;color:#1e3a8a">{{ strtoupper(mb_substr($ticket->assignee->name ?? '?', 0, 1)) }}</div>
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate dark:text-gray-100">{{ $ticket->assignee->name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $ticket->assignee->email }}</p>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-500">— nealocat —</p>
                @endif
            </div>

            <div class="p-5 bg-white border border-gray-200 rounded-xl dark:border-white/10 dark:bg-gray-900">
                <h3 class="mb-3 text-sm font-semibold tracking-wider text-gray-500 uppercase">{{ $opener['type'] === 'organizer' ? 'Organizator' : ucfirst($opener['type']) }}</h3>
                <dl class="space-y-2 text-sm">
                    <div>
                        <dt class="text-xs text-gray-500">Nume</dt>
                        <dd class="text-gray-900 dark:text-gray-100 font-medium">{{ $opener['name'] ?? '—' }}</dd>
                    </div>
                    @if (!empty($opener['company']))
                        <div>
                            <dt class="text-xs text-gray-500">Societate</dt>
                            <dd>
                                @if (!empty($opener['company_url']))
                                    <a href="{{ $opener['company_url'] }}" class="text-primary-600 hover:underline">{{ $opener['company'] }}</a>
                                @else
                                    <span class="text-gray-700 dark:text-gray-300">{{ $opener['company'] }}</span>
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if (!empty($opener['email']))
                        <div>
                            <dt class="text-xs text-gray-500">Email</dt>
                            <dd><a href="mailto:{{ $opener['email'] }}" class="text-primary-600 hover:underline break-all">{{ $opener['email'] }}</a></dd>
                        </div>
                    @endif
                    @if (!empty($opener['phone']))
                        <div>
                            <dt class="text-xs text-gray-500">Telefon</dt>
                            <dd><a href="tel:{{ $opener['phone'] }}" class="text-gray-700 dark:text-gray-300">{{ $opener['phone'] }}</a></dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs text-gray-500">Tichete anterioare</dt>
                        <dd class="text-gray-700 dark:text-gray-300">{{ $opener['past_tickets_count'] }}</dd>
                    </div>
                </dl>
            </div>

            @if (!empty($events))
                <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Evenimente active ({{ count($events) }})</h3>
                    <ul class="space-y-2 text-sm">
                        @foreach ($events as $ev)
                            <li class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    @if (!empty($ev['url']))
                                        <a href="{{ $ev['url'] }}" class="text-primary-600 hover:underline truncate block">{{ $ev['title'] }}</a>
                                    @else
                                        <span class="text-gray-900 dark:text-gray-100 truncate block">{{ $ev['title'] }}</span>
                                    @endif
                                    <p class="text-[11px] text-gray-500">
                                        @if ($ev['starts_at'])
                                            {{ \Carbon\Carbon::parse($ev['starts_at'])->format('d M Y') }} ·
                                        @endif
                                        {{ $ev['tickets_sold'] }} bilete vândute
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!empty($context))
                <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Context cerere</h3>
                    <dl class="space-y-2 text-xs">
                        @if (!empty($context['ip']))
                            <div><dt class="text-gray-500">IP</dt><dd class="font-mono text-gray-700 dark:text-gray-300">{{ $context['ip'] }}</dd></div>
                        @endif
                        @if (!empty($context['browser']))
                            <div><dt class="text-gray-500">Browser</dt><dd class="text-gray-700 dark:text-gray-300">{{ $context['browser'] }} {{ $context['browser_version'] ?? '' }}</dd></div>
                        @endif
                        @if (!empty($context['os']))
                            <div><dt class="text-gray-500">Sistem</dt><dd class="text-gray-700 dark:text-gray-300">{{ $context['os'] }} {{ $context['os_version'] ?? '' }}</dd></div>
                        @endif
                        @if (!empty($context['device_type']))
                            <div><dt class="text-gray-500">Tip device</dt><dd class="text-gray-700 dark:text-gray-300 capitalize">{{ $context['device_type'] }}</dd></div>
                        @endif
                        @if (!empty($context['source_url']))
                            <div>
                                <dt class="text-gray-500">Pagina de unde a deschis</dt>
                                <dd><a href="{{ $context['source_url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline break-all">{{ $context['source_url'] }}</a></dd>
                            </div>
                        @endif
                        @if (!empty($context['referer']) && (empty($context['source_url']) || $context['referer'] !== $context['source_url']))
                            <div><dt class="text-gray-500">Referrer</dt><dd class="text-gray-700 dark:text-gray-300 break-all">{{ $context['referer'] }}</dd></div>
                        @endif
                        @if (!empty($context['screen_resolution']))
                            <div><dt class="text-gray-500">Ecran</dt><dd class="text-gray-700 dark:text-gray-300">{{ $context['screen_resolution'] }}@if (!empty($context['viewport'])) (viewport {{ $context['viewport'] }})@endif</dd></div>
                        @endif
                        @if (!empty($context['language']))
                            <div><dt class="text-gray-500">Limbă</dt><dd class="text-gray-700 dark:text-gray-300">{{ $context['language'] }}</dd></div>
                        @endif
                        @if (!empty($context['captured_at']))
                            <div><dt class="text-gray-500">Capturat la</dt><dd class="text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($context['captured_at'])->format('d M Y, H:i:s') }}</dd></div>
                        @endif
                    </dl>
                </div>
            @endif
        </div>
    </div>

</x-filament-panels::page>
