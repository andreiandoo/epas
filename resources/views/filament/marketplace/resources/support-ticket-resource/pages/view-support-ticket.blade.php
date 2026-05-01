@php
    /** @var \App\Models\SupportTicket $ticket */
    $ticket = $this->record;
    $messages = $this->getThreadMessages();
    $opener = $this->getOpener();
    $events = $this->getActiveEvents();
    $context = $this->getRequestContext();

    $statusBadge = match ($ticket->status) {
        'open' => ['label' => 'Deschis', 'class' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/20 dark:text-blue-300'],
        'in_progress' => ['label' => 'În lucru', 'class' => 'bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300'],
        'awaiting_organizer' => ['label' => 'Așteaptă răspuns', 'class' => 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300'],
        'resolved' => ['label' => 'Rezolvat', 'class' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'],
        'closed' => ['label' => 'Închis', 'class' => 'bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-200'],
        default => ['label' => $ticket->status, 'class' => 'bg-slate-100 text-slate-700'],
    };
    $priorityLabel = match ($ticket->priority) {
        'low' => 'Scăzută',
        'high' => 'Ridicată',
        'urgent' => 'Urgentă',
        default => 'Normală',
    };
    $priorityClass = match ($ticket->priority) {
        'urgent' => 'bg-red-100 text-red-700 dark:bg-red-500/20 dark:text-red-300',
        'high' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300',
        'low' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300',
        default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
    };
@endphp

<x-filament-panels::page>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ========== Main column: thread ========== --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Header card --}}
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                <div class="flex flex-wrap items-center gap-2 mb-2 text-xs">
                    <span class="font-mono text-gray-500">{{ $ticket->ticket_number ?: ('#' . $ticket->id) }}</span>
                    <span class="px-2 py-0.5 rounded-full font-medium {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
                    <span class="px-2 py-0.5 rounded-full font-medium {{ $priorityClass }}">{{ $priorityLabel }}</span>
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
                                $authorName = $message->author->name ?? $message->author->public_name ?? $message->author->email ?? '—';
                                $initial = strtoupper(mb_substr($authorName ?: '?', 0, 1));
                                $bubbleClass = $isInternal
                                    ? 'bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-900 dark:text-amber-200'
                                    : ($isStaff
                                        ? 'bg-primary-50 dark:bg-primary-500/15 text-primary-900 dark:text-primary-100'
                                        : 'bg-gray-100 dark:bg-white/5 text-gray-900 dark:text-gray-100');
                                $align = $isStaff ? 'flex-row' : 'flex-row-reverse';
                            @endphp
                            <div class="flex {{ $align }} gap-3 items-start">
                                <div class="w-9 h-9 rounded-full {{ $isStaff ? 'bg-primary-200 text-primary-800 dark:bg-primary-500/30 dark:text-primary-200' : 'bg-gray-200 text-gray-700 dark:bg-white/10 dark:text-gray-300' }} flex items-center justify-center text-xs font-bold flex-shrink-0">{{ $initial }}</div>
                                <div class="max-w-[85%] flex-1">
                                    <div class="rounded-2xl px-4 py-3 {{ $bubbleClass }}">
                                        @if ($isInternal)
                                            <div class="flex items-center gap-1.5 mb-1 text-xs font-semibold uppercase tracking-wider">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                                Notă internă
                                            </div>
                                        @endif
                                        <div class="text-xs opacity-70 mb-1">{{ $authorName }}</div>
                                        <div class="text-sm whitespace-pre-wrap break-words">{{ $message->body }}</div>
                                        @if (!empty($message->attachments))
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                @foreach ($message->attachments as $a)
                                                    @php
                                                        $url = isset($a['path']) ? \Illuminate\Support\Facades\Storage::disk($a['disk'] ?? 'public')->url($a['path']) : null;
                                                    @endphp
                                                    @if ($url)
                                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs bg-white/40 dark:bg-white/10 hover:bg-white/60 dark:hover:bg-white/15">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                            <span>{{ $a['original_name'] ?? 'fișier' }}</span>
                                                        </a>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-[10px] text-gray-500 mt-1 {{ $isStaff ? '' : 'text-right' }}">
                                        {{ $message->created_at?->format('d M Y, H:i') }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ========== Sidebar: opener / events / context ========== --}}
        <div class="space-y-4">

            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">{{ $opener['type'] === 'organizer' ? 'Organizator' : ucfirst($opener['type']) }}</h3>
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
