@php
    use App\Models\MarketplaceTaxTemplate;

    // "Generator documente" — ONLY the generate actions. Already-generated
    // templates render a disabled (non-clickable) button. The generated PDFs and
    // their details/download live in the separate "Documente generate" section.
    $isEventFinished = $event->isPast() || $event->status === 'archived';
    $isEventPublished = (bool) $event->is_published;

    // Decont templates require the event to have sales before they can generate.
    $hasSales = \App\Models\Order::where('event_id', $event->id)
        ->whereIn('status', ['paid', 'confirmed', 'completed'])
        ->where('total', '>', 0)
        ->exists();
    $decontTypes = ['decont', 'decont_ontop', 'decont_inclus'];

    // Trigger → can generate now? + blocked reason.
    $triggerCanGenerate = [
        'after_event_published'  => $isEventPublished,
        'after_event_finished'   => $isEventFinished,
        'after_payout_completed' => true,
        null                     => true,
    ];
    $triggerBlockedReason = [
        'after_event_published' => 'Evenimentul nu e publicat',
        'after_event_finished'  => 'Evenimentul nu e încheiat',
    ];

    // Already-generated lookup — a template with an existing document is shown as
    // a disabled button (no regenerate here).
    $existingByTemplate = collect();
    foreach ($generatedDocs as $d) {
        $existingByTemplate[$d->marketplace_tax_template_id] = $d;
    }
    $orgDocsByType = $organizerDocs->keyBy('document_type');
@endphp

<div class="flex flex-wrap gap-2">
    @forelse($templates as $template)
        @php
            $trigger = $template->trigger;
            $canGenerate = $triggerCanGenerate[$trigger] ?? true;
            $blockedReason = !$canGenerate ? ($triggerBlockedReason[$trigger] ?? '') : '';

            if (in_array($template->type, $decontTypes) && !$hasSales) {
                $canGenerate = false;
                $blockedReason = 'Nu există vânzări pe eveniment';
            }

            $hasDocument = ($existingByTemplate[$template->id] ?? null) || ($orgDocsByType[$template->type] ?? null);
        @endphp

        @if($hasDocument)
            {{-- Already generated → non-clickable --}}
            <span
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg cursor-not-allowed whitespace-nowrap dark:text-green-400 dark:bg-green-900/20 dark:border-green-800"
                title="Deja generat — vezi secțiunea Documente generate"
            >
                <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                {{ $template->name }}
            </span>
        @elseif($canGenerate)
            <button
                type="button"
                x-data="{ loading: false }"
                x-on:click="
                    if (!confirm('Generează documentul {{ addslashes($template->name) }}?')) return;
                    loading = true;
                    fetch('/marketplace/api/events/{{ $event->id }}/generate-document', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ template_id: {{ $template->id }} })
                    })
                    .then(r => r.json())
                    .then(data => {
                        loading = false;
                        if (data.success) { window.location.reload(); }
                        else { alert(data.message || 'Eroare la generare'); }
                    })
                    .catch(e => { loading = false; alert('Eroare: ' + e.message); })
                "
                x-bind:disabled="loading"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition disabled:opacity-50 whitespace-nowrap"
            >
                <template x-if="loading">
                    <svg class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                </template>
                <svg x-show="!loading" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span x-show="!loading">{{ $template->name }}</span>
                <span x-show="loading">Se generează...</span>
            </button>
        @else
            <span
                class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium text-gray-400 bg-gray-100 dark:bg-gray-800 rounded-lg cursor-not-allowed whitespace-nowrap"
                title="{{ $blockedReason }}"
            >
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H10m4-6a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                {{ $template->name }}
            </span>
        @endif
    @empty
        <p class="text-xs text-gray-400">Nu există template-uri de documente configurate.</p>
    @endforelse
</div>
