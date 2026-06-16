@php
    /** @var \App\Models\MarketplaceTodo $todo */
    $todo = $this->record;
    $comments = $this->getThreadComments();

    $statusMap = [
        'open' => ['label' => 'Deschis', 'color' => 'info'],
        'in_progress' => ['label' => 'În lucru', 'color' => 'warning'],
        'awaiting_response' => ['label' => 'Așteaptă răspuns', 'color' => 'success'],
        'resolved' => ['label' => 'Rezolvat', 'color' => 'success'],
        'closed' => ['label' => 'Închis', 'color' => 'gray'],
    ];
    $statusBadge = $statusMap[$todo->status] ?? ['label' => $todo->status, 'color' => 'gray'];

    $priorityMap = [
        'low' => ['label' => 'Scăzută', 'color' => 'info'],
        'normal' => ['label' => 'Normală', 'color' => 'gray'],
        'high' => ['label' => 'Ridicată', 'color' => 'warning'],
        'urgent' => ['label' => 'Urgentă', 'color' => 'danger'],
    ];
    $priorityBadge = $priorityMap[$todo->priority] ?? ['label' => $todo->priority, 'color' => 'gray'];

    $currentAdminId = \Illuminate\Support\Facades\Auth::guard('marketplace_admin')->id();

    $bubbleSelf = 'background:#dbeafe;color:#1e3a8a;';
    $bubbleSelfAvatar = 'background:#bfdbfe;color:#1e40af;';
    $bubbleOther = 'background:#f1f5f9;color:#0f172a;';
    $bubbleOtherAvatar = 'background:#cbd5e1;color:#334155;';
    $bubbleSystem = 'background:#fef3c7;color:#78350f;border:1px solid #fde68a;';
@endphp

<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ========== Main column: thread ========== --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Header card --}}
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                <div class="flex flex-wrap items-center gap-2 mb-2 text-xs">
                    <span class="font-mono text-gray-500">{{ $todo->todo_number ?: ('#' . $todo->id) }}</span>
                    <x-filament::badge :color="$statusBadge['color']">{{ $statusBadge['label'] }}</x-filament::badge>
                    <x-filament::badge :color="$priorityBadge['color']">{{ $priorityBadge['label'] }}</x-filament::badge>
                    @if ($todo->category)
                        <span class="text-gray-400">·</span>
                        <span class="text-gray-600 dark:text-gray-300">
                            {{ $todo->category->name }}
                        </span>
                    @endif
                </div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $todo->title }}</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Deschis pe <strong>{{ $todo->opened_at?->format('d M Y, H:i') }}</strong>
                    @if ($todo->creator)
                        de <strong>{{ $todo->creator->name }}</strong>
                    @endif
                </p>

                {{-- Description (rich text) --}}
                @if ($todo->description)
                    <div class="mt-4 prose prose-sm dark:prose-invert max-w-none text-gray-800 dark:text-gray-200">
                        {!! $todo->description !!}
                    </div>
                @endif

                {{-- Attachments thumbnails --}}
                @if (!empty($todo->attachments))
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-white/10">
                        <p class="text-xs text-gray-500 mb-2">Imagini atașate</p>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                            @foreach ($todo->attachments as $att)
                                @php
                                    $path = is_array($att) ? ($att['path'] ?? null) : $att;
                                    $url = $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
                                @endphp
                                @if ($url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="block group">
                                        <img src="{{ $url }}" alt="" class="w-full h-24 object-cover rounded-lg border border-gray-200 dark:border-white/10 group-hover:opacity-90" />
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Thread (comments + system events) --}}
            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        Conversație ({{ $comments->count() }})
                    </h3>
                </div>

                @if ($comments->isEmpty())
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nu există încă răspunsuri. Apasă "Răspunde / Comentează" pentru a adăuga unul.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($comments as $comment)
                            @php
                                $authorName = $comment->author?->name ?? '—';
                                $authorInitials = collect(explode(' ', $authorName))->map(fn ($p) => mb_substr($p, 0, 1))->join('');
                                $authorInitials = mb_strtoupper(mb_substr($authorInitials, 0, 2));
                                $isSystem = !empty($comment->event_type);
                                $isSelf = (int) $comment->author_marketplace_admin_id === (int) $currentAdminId;
                                $align = $isSystem ? 'justify-center' : ($isSelf ? 'justify-end' : 'justify-start');
                            @endphp

                            @if ($isSystem)
                                <div class="flex justify-center">
                                    <div class="rounded-full px-3 py-1 text-xs font-medium" style="{{ $bubbleSystem }}">
                                        <span class="font-semibold">{{ $authorName }}</span> · {!! $comment->body !!}
                                        <span class="opacity-70 ml-1">· {{ $comment->created_at?->diffForHumans() }}</span>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-start gap-3 {{ $isSelf ? 'flex-row-reverse' : '' }}">
                                    <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                                         style="{{ $isSelf ? $bubbleSelfAvatar : $bubbleOtherAvatar }}">
                                        {{ $authorInitials ?: '?' }}
                                    </div>
                                    <div class="flex-1 max-w-[80%] {{ $isSelf ? 'text-right' : '' }}">
                                        <div class="text-xs text-gray-500 mb-1">
                                            <strong>{{ $authorName }}</strong>
                                            <span class="text-gray-400">· {{ $comment->created_at?->format('d M Y, H:i') }}</span>
                                        </div>
                                        <div class="rounded-2xl px-4 py-3 inline-block text-left" style="{{ $isSelf ? $bubbleSelf : $bubbleOther }}">
                                            <div class="prose prose-sm max-w-none">
                                                {!! $comment->body !!}
                                            </div>
                                            @if (!empty($comment->attachments))
                                                <div class="mt-3 grid grid-cols-2 gap-2">
                                                    @foreach ($comment->attachments as $att)
                                                        @php
                                                            $path = is_array($att) ? ($att['path'] ?? null) : $att;
                                                            $url = $path ? \Illuminate\Support\Facades\Storage::disk('public')->url($path) : null;
                                                        @endphp
                                                        @if ($url)
                                                            <a href="{{ $url }}" target="_blank" rel="noopener" class="block">
                                                                <img src="{{ $url }}" alt="" class="w-full h-20 object-cover rounded-md border border-black/10" />
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

        </div>

        {{-- ========== Sidebar ========== --}}
        <div class="space-y-4">

            <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 p-5">
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Detalii</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Status</dt>
                        <dd><x-filament::badge :color="$statusBadge['color']">{{ $statusBadge['label'] }}</x-filament::badge></dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Prioritate</dt>
                        <dd><x-filament::badge :color="$priorityBadge['color']">{{ $priorityBadge['label'] }}</x-filament::badge></dd>
                    </div>
                    @if ($todo->category)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Categorie</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ $todo->category->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Creat de</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $todo->creator?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Asignat la</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $todo->assignee?->name ?? '— nealocat —' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-gray-500">Deschis</dt>
                        <dd class="text-gray-800 dark:text-gray-200">{{ $todo->opened_at?->format('d M Y, H:i') }}</dd>
                    </div>
                    @if ($todo->first_response_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Primul răspuns</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ $todo->first_response_at->diffForHumans($todo->opened_at, ['parts' => 2, 'short' => true]) }}</dd>
                        </div>
                    @endif
                    @if ($todo->resolved_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Rezolvat</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ $todo->resolved_at->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif
                    @if ($todo->closed_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-gray-500">Închis</dt>
                            <dd class="text-gray-800 dark:text-gray-200">{{ $todo->closed_at->format('d M Y, H:i') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

        </div>

    </div>
</x-filament-panels::page>
