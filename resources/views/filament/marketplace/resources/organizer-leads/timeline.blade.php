@php
    use App\Models\Marketplace\OrganizerLeadEvent;

    $iconFor = function (string $type): string {
        return match ($type) {
            OrganizerLeadEvent::TYPE_PAGE_VIEW_LANDING    => 'heroicon-o-cursor-arrow-rays',
            OrganizerLeadEvent::TYPE_PAGE_VIEW_ONBOARDING => 'heroicon-o-clipboard-document-list',
            OrganizerLeadEvent::TYPE_CTA_CLICK            => 'heroicon-o-hand-raised',
            OrganizerLeadEvent::TYPE_FORM_SUBMITTED       => 'heroicon-o-check-badge',
            OrganizerLeadEvent::TYPE_STATUS_CHANGED       => 'heroicon-o-arrow-path',
            OrganizerLeadEvent::TYPE_NOTE                 => 'heroicon-o-pencil-square',
            OrganizerLeadEvent::TYPE_EMAIL_SENT           => 'heroicon-o-envelope',
            OrganizerLeadEvent::TYPE_CALL                 => 'heroicon-o-phone',
            OrganizerLeadEvent::TYPE_DEMO_SCHEDULED       => 'heroicon-o-calendar-days',
            OrganizerLeadEvent::TYPE_ASSIGNED             => 'heroicon-o-user',
            default                                       => 'heroicon-o-circle-stack',
        };
    };

    $colorFor = function (string $type): string {
        return match ($type) {
            OrganizerLeadEvent::TYPE_FORM_SUBMITTED       => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            OrganizerLeadEvent::TYPE_STATUS_CHANGED       => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            OrganizerLeadEvent::TYPE_NOTE                 => 'bg-gray-100 text-gray-800 dark:bg-gray-700/60 dark:text-gray-300',
            OrganizerLeadEvent::TYPE_EMAIL_SENT           => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
            OrganizerLeadEvent::TYPE_CALL                 => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            OrganizerLeadEvent::TYPE_DEMO_SCHEDULED       => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            OrganizerLeadEvent::TYPE_PAGE_VIEW_LANDING,
            OrganizerLeadEvent::TYPE_PAGE_VIEW_ONBOARDING => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
            OrganizerLeadEvent::TYPE_CTA_CLICK            => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
            default                                       => 'bg-gray-100 text-gray-700',
        };
    };
@endphp

<x-filament::section class="mt-6">
    <x-slot name="heading">Activitate ({{ $events->count() }})</x-slot>
    <x-slot name="description">
        Toate evenimentele înregistrate pentru acest lead: vizite pe paginile publice,
        schimbări de status, note, apeluri, emailuri. Vizitele anonime de pe
        /devino-partener și /inregistrare-locatie sunt legate la lead prin cookie-ul
        de sesiune când se trimite formularul.
    </x-slot>

    @if ($events->isEmpty())
        <p class="text-gray-500 italic text-sm">Niciun eveniment înregistrat încă.</p>
    @else
        <ol class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 space-y-5">
            @foreach ($events as $event)
                <li class="ml-6">
                    <span class="absolute -left-3.5 flex h-7 w-7 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900 {{ $colorFor($event->event_type) }}">
                        <x-dynamic-component :component="$iconFor($event->event_type)" class="h-4 w-4" />
                    </span>
                    <div class="flex items-baseline justify-between gap-2 flex-wrap">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $event->type_label }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 font-mono">
                            {{ $event->created_at->format('d M Y H:i') }}
                            @if ($event->performedBy)
                                · <span class="font-semibold">{{ $event->performedBy->name }}</span>
                            @endif
                        </p>
                    </div>
                    @if ($event->summary)
                        <p class="mt-1 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $event->summary }}</p>
                    @endif
                    @if (!empty($event->page_url) || !empty($event->payload))
                        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                            @if ($event->page_url)
                                <p><span class="font-semibold">URL:</span> <code class="text-xs">{{ $event->page_url }}</code></p>
                            @endif
                            @if (!empty($event->payload['cta_id']))
                                <p>
                                    <span class="font-semibold">CTA:</span>
                                    <code class="text-xs">{{ $event->payload['cta_id'] }}</code>
                                    @if (!empty($event->payload['cta_label']))
                                        <span class="ml-1">— „{{ $event->payload['cta_label'] }}"</span>
                                    @endif
                                </p>
                            @endif
                            @if (!empty($event->payload['utm']) && array_filter($event->payload['utm']))
                                <p><span class="font-semibold">UTM:</span>
                                    @foreach (array_filter($event->payload['utm']) as $k => $v)
                                        <code class="text-xs ml-1">{{ $k }}={{ $v }}</code>
                                    @endforeach
                                </p>
                            @endif
                            @if (!empty($event->payload['referrer']))
                                <p><span class="font-semibold">Referrer:</span> <span class="break-all">{{ $event->payload['referrer'] }}</span></p>
                            @endif
                            @if (!empty($event->ip_address))
                                <p><span class="font-semibold">IP:</span> {{ $event->ip_address }}</p>
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</x-filament::section>
